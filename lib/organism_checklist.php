<?php
/**
 * Organism Setup Checklist — the checks behind admin/organism_checklist.php.
 *
 * Until 2026-10-12 these ran inside the page template, and then (briefly) here, as the
 * checklist's OWN scans of the disk. Manage Organisms and the dashboard meanwhile read
 * the organism cache and computeDataHealthAlerts() -- so the three pages could disagree
 * about the same organism, and did: the checklist called two empty databases "OK" while
 * the other two flagged them, and its "ungrouped" test was looser than theirs.
 *
 * Now every check that the shared code already makes is READ from it:
 *   database present / empty      computeDataHealthAlerts()
 *   BLAST indexes, genome .fai    organism cache (blast_validation, fai_validation)
 *   missing FASTA per gene set    getGeneSetFastaGaps() (organism cache)
 *   chromosome names vs genome    getGeneSetSeqnameGaps() (organism cache)
 *   taxonomy tree, groups         getOrganismsNotInTree(), getUngroupedOrganisms()
 *   directory permissions         moop_permission_organism_dir_rule() (live)
 * Only checks nothing else makes stay live here: organism.json present, gene sets with
 * no genes.gff. The cache is as fresh as its last refresh, so the page reports which
 * organisms changed on disk since then (the same fingerprint test Manage Organisms uses).
 */

require_once __DIR__ . '/functions_filesystem.php';   // moop_organism_dirs()
require_once __DIR__ . '/blast_functions.php';
require_once __DIR__ . '/permission_check.php';

/**
 * Run every checklist check.
 *
 * @param string $organism_data  Organism data root (ConfigManager 'organism_data')
 * @param array  $sequence_types ConfigManager::getSequenceTypes()
 * @param string $metadata_path  ConfigManager 'metadata_path'
 * @return array Keyed results; see the return statement for the shape of each.
 */
function organism_checklist_checks(string $organism_data, array $sequence_types, string $metadata_path): array
{
    $organisms_in_system = organism_checklist_organisms($organism_data);

    $raw_cache  = loadJsonFile(moop_organism_cache_file(), []);
    $cache_data = is_array($raw_cache) ? ($raw_cache['data'] ?? []) : [];
    $staleness  = moop_organism_cache_staleness(is_array($raw_cache) ? $raw_cache : [], $organism_data,
                      "$metadata_path/taxonomy_tree_config.json", "$metadata_path/organism_assembly_groups.json");
    $health     = computeDataHealthAlerts($organism_data);

    $missing_json = [];
    foreach ($organisms_in_system as $org) {
        if (!file_exists("$organism_data/$org/organism.json")) $missing_json[] = $org;
    }

    $groups_entries  = loadJsonFile("$metadata_path/organism_assembly_groups.json", []);
    $web_server_info = getWebServerUser();

    return [
        'organisms_in_system'      => $organisms_in_system,
        'cache_generated'          => is_array($raw_cache) ? ($raw_cache['generated'] ?? null) : null,
        'cache_stale_organisms'    => $staleness['changed'],
        'cache_stale_reasons'      => $staleness['reasons'],   // config change, older format
        'no_database_organisms'    => $health['no_database_organisms'],
        'empty_database_organisms' => $health['empty_database_organisms'],
        'missing_json'             => $missing_json,
        'blast_issues'             => organism_checklist_blast_issues($cache_data),
        'fai_issues'               => organism_checklist_fai_issues($cache_data),
        'expected_group'           => $web_server_info['group'],
        'permission_issues'        => organism_checklist_permission_issues($organism_data, $organisms_in_system, $web_server_info['group']),
        'permission_expectation'   => moop_permission_expectation('data', 'directory', false, true),
        'exec_files_fix_command'   => moop_permission_fix_exec_files_command($organism_data),
        'gene_sets_missing_gff'    => organism_checklist_gene_sets_missing_gff($organism_data, $organisms_in_system, $sequence_types),
        'fasta_gaps'               => getGeneSetFastaGaps($cache_data, $sequence_types),
        'seqname_gaps'             => getGeneSetSeqnameGaps($cache_data),
        'organisms_not_in_tree'    => getOrganismsNotInTree($cache_data),
        'unassigned_organisms'     => getUngroupedOrganisms($cache_data, is_array($groups_entries) ? $groups_entries : []),
    ];
}

/** Every directory directly under the organism data root, sorted. */
function organism_checklist_organisms(string $organism_data): array
{
    return moop_organism_dirs($organism_data);
}

/**
 * FASTA files whose BLAST indexes are missing, read from the organism cache's
 * blast_validation (validateBlastIndexFiles(), aggregated across each assembly's gene
 * sets). Each row also carries whether the web server can build the index there --
 * checked live, and only for the rows that need it -- so the page can offer
 * "Generate now" or explain why not.
 */
function organism_checklist_blast_issues(array $cache_data): array
{
    $blast_issues = [];
    foreach ($cache_data as $org => $org_data) {
        foreach ($org_data['blast_validation'] ?? [] as $asm => $bv) {
            foreach ($bv['databases'] ?? [] as $db) {
                if (!empty($db['has_indexes'])) continue;
                $gs_fullpath = dirname($db['fasta_path']);
                $blast_issues[] = [
                    'organism'    => $org,
                    'assembly'    => $asm,
                    'gene_set'    => basename($gs_fullpath),
                    'fasta'       => $db['fasta'],
                    'missing'     => $db['missing_indexes'] ?? [],
                    'db_type'     => sequence_type_blast_dbtype($db['type'] ?? ''),
                    'gs_fullpath' => $gs_fullpath,
                    'perm_check'  => checkAssemblyCanGenerateBlast($gs_fullpath, [$db['fasta']]),
                ];
            }
        }
    }
    return $blast_issues;
}

/**
 * Assemblies whose genome FASTA has no .fai index, from the organism cache's
 * fai_validation. Assemblies with no genome at all (transcriptome/proteome-only, 24 of
 * 96 here) are not reported: no genome, nothing to index.
 */
function organism_checklist_fai_issues(array $cache_data): array
{
    $genome_file = genome_fasta_filename();
    $fai_issues = [];
    foreach ($cache_data as $org => $org_data) {
        foreach ($org_data['fai_validation'] ?? [] as $asm => $fai) {
            if (empty($fai['genome_fa_exists']) || !empty($fai['fai_exists'])) continue;
            $asm_dir = rtrim($org_data['path'] ?? '', '/') . '/' . $asm;
            $fai_issues[] = [
                'organism' => $org,
                'assembly' => $asm,
                'fasta'    => "$asm_dir/$genome_file",
                'writable' => is_writable($asm_dir),
            ];
        }
    }
    return $fai_issues;
}


/**
 * Organism directories that fail the SHARED organism-directory rule
 * (moop_permission_organism_dir_rule() + performPermissionCheck() in
 * lib/permission_check.php) -- the same judgement the dashboard and the Filesystem
 * Permissions page apply, run live here on just these directories.
 *
 * This used to be a private copy of the rule (writable / SGID / group) while the shared
 * checker tested readable / world-writable / writable, so each reported problems the
 * other could not see. Each row is the shared checker's result plus 'organism' and the
 * fix commands it suggests.
 */
function organism_checklist_permission_issues(string $organism_data, array $organisms, string $expected_group): array
{
    $rule  = moop_permission_organism_dir_rule($expected_group);
    $owner = getMoopOwner();

    $permission_issues = [];
    foreach ($organisms as $org) {
        $check = performPermissionCheck("$organism_data/$org", $rule, $expected_group);
        if (empty($check['issues'])) continue;
        $check['organism']     = $org;
        $check['fix_commands'] = moop_permission_fix_commands($check, $owner);
        $permission_issues[] = $check;
    }
    return $permission_issues;
}

/**
 * Gene sets with no annotations file.
 *
 * Nothing checked this before 2026-07-16 — unsurprisingly, since Step 1 never told
 * anyone to copy genes.gff in the first place. A gene set without it still BLASTs
 * (blast_functions.php resolves FASTA directly and never looks at the GFF), but
 * JBrowse gets no annotation track and feature_coords.tsv is never built, so BLAST
 * linkouts are dead. gene_set_functions.php logs "no genes.gff found — skipped" and
 * carries on, which is why this can sit unnoticed.
 *
 * Gene sets are identified by their FASTA payload (is_gene_set_dir), never by the
 * GFF — testing for the GFF to decide what IS a gene set makes the ones missing it
 * invisible, which was the pre-existing bug in validateAssemblyDirectories().
 */
function organism_checklist_gene_sets_missing_gff(string $organism_data, array $organisms, array $sequence_types): array
{
    $gene_sets_missing_gff = [];
    foreach ($organisms as $org) {
        foreach (glob("$organism_data/$org/*", GLOB_ONLYDIR) ?: [] as $asm_dir) {
            // No genome, no GFF: a GFF places genes on the genome's sequences, so a
            // transcriptome- or proteome-only assembly cannot have one. This used to ask
            // "protein-only?" instead, and listed 20 gene sets -- every one of them on an
            // assembly with no genome.fa, so all 20 were false alarms.
            if (!file_exists($asm_dir . '/' . ($sequence_types['genome']['pattern'] ?? genome_fasta_filename()))) continue;
            foreach (glob("$asm_dir/*", GLOB_ONLYDIR) ?: [] as $gs_dir) {
                if (!is_gene_set_dir($gs_dir)) continue;
                if (file_exists($gs_dir . '/' . genes_gff_filename())) continue;

                // Report what IS there, to help chase the GFF on the data server.
                $has = [];
                foreach ($sequence_types as $k => $seq) {
                    if ($k === 'genome') continue;
                    if (!empty($seq['pattern']) && file_exists($gs_dir . '/' . $seq['pattern'])) {
                        $has[] = $seq['label'] ?? $k;
                    }
                }
                $gene_sets_missing_gff[] = [
                    'path' => str_replace($organism_data . '/', '', $gs_dir),
                    'has'  => $has,
                ];
            }
        }
    }
    return $gene_sets_missing_gff;
}
