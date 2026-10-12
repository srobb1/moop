<?php
/**
 * Organism Setup Checklist — the checks behind admin/organism_checklist.php.
 *
 * These ran inside the page template (admin/pages/organism_checklist.php) until
 * 2026-10-12: nine separate filesystem sweeps interleaved with the HTML, two of them
 * duplicates (the organism list was built twice, and "missing organism.json" was
 * computed twice under two names). They are collected here so the page only renders,
 * every check reads the same organism list, and each can be tested on its own.
 *
 * Behaviour was moved, not changed: the rendered page is byte-identical before and
 * after the move. Corrections to the checks themselves are separate commits.
 */

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

    $missing_sqlite = [];
    $missing_json   = [];
    foreach ($organisms_in_system as $org) {
        if (!file_exists("$organism_data/$org/organism.sqlite")) $missing_sqlite[] = $org;
        if (!file_exists("$organism_data/$org/organism.json"))   $missing_json[]   = $org;
    }

    $web_server_info = getWebServerUser();

    return [
        'organisms_in_system'   => $organisms_in_system,
        'missing_sqlite'        => $missing_sqlite,
        'missing_json'          => $missing_json,
        'blast_issues'          => organism_checklist_blast_issues($organism_data, $organisms_in_system, $sequence_types),
        'fai_issues'            => organism_checklist_fai_issues($organism_data, $organisms_in_system),
        'expected_group'        => $web_server_info['group'],
        'permission_issues'     => organism_checklist_permission_issues($organism_data, $organisms_in_system, $web_server_info['group']),
        'permission_expectation'=> moop_permission_expectation('data', 'directory', false, true),
        'exec_files_fix_command'=> moop_permission_fix_exec_files_command($organism_data),
        'gene_sets_missing_gff' => organism_checklist_gene_sets_missing_gff($organism_data, $organisms_in_system, $sequence_types),
        'organisms_not_in_tree' => organism_checklist_not_in_tree($organisms_in_system, "$metadata_path/taxonomy_tree_config.json"),
        'unassigned_organisms'  => organism_checklist_unassigned("$metadata_path/organism_assembly_groups.json", $organisms_in_system),
    ];
}

/** Every directory directly under the organism data root, sorted. */
function organism_checklist_organisms(string $organism_data): array
{
    $organisms = [];
    if (is_dir($organism_data)) {
        foreach (scandir($organism_data) as $item) {
            if ($item !== '.' && $item !== '..' && is_dir("$organism_data/$item")) {
                $organisms[] = $item;
            }
        }
    }
    sort($organisms);
    return $organisms;
}

/**
 * FASTA files whose BLAST indexes are missing. BLAST indexes live in gene_set subdirs
 * under each assembly dir. Each row also carries whether the web server can build the
 * index there, so the page can offer "Generate now" or explain why not.
 */
function organism_checklist_blast_issues(string $organism_data, array $organisms, array $sequence_types): array
{
    $blast_issues = [];
    foreach ($organisms as $org) {
        $org_dir = "$organism_data/$org";
        if (!is_dir($org_dir)) continue;
        foreach (scandir($org_dir) as $asm) {
            if ($asm === '.' || $asm === '..' || !is_dir("$org_dir/$asm")) continue;
            $asm_path = "$org_dir/$asm";
            foreach (glob($asm_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
                $blast_validation = validateBlastIndexFiles($gs_dir, $sequence_types);
                foreach ($blast_validation['databases'] ?? [] as $db) {
                    if (!$db['has_indexes']) {
                        $blast_issues[] = [
                            'organism' => $org,
                            'assembly' => $asm,
                            'gene_set' => basename($gs_dir),
                            'fasta'    => $db['fasta'],
                            'db_type'  => sequence_type_blast_dbtype($db['type']),
                            'missing'  => $db['missing_indexes'],
                        ];
                    }
                }
            }
        }
    }

    foreach ($blast_issues as &$issue) {
        $issue['gs_fullpath'] = $organism_data . '/' . $issue['organism'] . '/' . $issue['assembly'] . '/' . $issue['gene_set'];
        $issue['perm_check']  = checkAssemblyCanGenerateBlast($issue['gs_fullpath'], [$issue['fasta']]);
    }
    unset($issue);

    return $blast_issues;
}

/**
 * Assemblies whose genome FASTA has no .fai index.
 *
 * The genome filename comes from config (Manage Site Configuration), not a literal.
 *
 * Correctly guarded on the FASTA existing, and that guard matters: 25 of 96 assemblies
 * here have no genome at all. They are sequence-only sets — no reference genome,
 * therefore no genes.gff and no JBrowse registration. Verified consistent 2026-07-16:
 * ZERO assemblies have annotations without a genome, so "missing genome.fa" is a
 * category, not a fault. Flagging a missing index for a genome that is not supposed to
 * exist would be 25 phantom warnings.
 */
function organism_checklist_fai_issues(string $organism_data, array $organisms): array
{
    $genome_pattern = genome_fasta_filename();
    $fai_issues = [];
    foreach ($organisms as $org) {
        $org_dir = "$organism_data/$org";
        if (is_dir($org_dir)) {
            foreach (scandir($org_dir) as $item) {
                if ($item === '.' || $item === '..') continue;
                $asm_dir = "$org_dir/$item";
                if (!is_dir($asm_dir)) continue;
                $fasta   = "$asm_dir/$genome_pattern";
                $fai     = "$fasta.fai";
                if (file_exists($fasta) && !file_exists($fai)) {
                    $fai_issues[] = [
                        'organism' => $org,
                        'assembly' => $item,
                        'fasta'    => $fasta,
                        'writable' => is_writable($asm_dir),
                    ];
                }
            }
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
            foreach (glob("$asm_dir/*", GLOB_ONLYDIR) ?: [] as $gs_dir) {
                if (!is_gene_set_dir($gs_dir)) continue;
                if (file_exists($gs_dir . '/' . genes_gff_filename())) continue;

                // Report what IS there: a protein-only set has no genomic coordinates,
                // so having no GFF is correct for it. One with cds/transcript almost
                // certainly should have one, and is worth chasing on the data server.
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

/**
 * Organisms missing from the homepage taxonomy selector.
 *
 * The tree file comes from metadata_path. It used to be derived as
 * dirname($organism_data) . '/metadata/taxonomy_tree_config.json', which resolves
 * correctly only because metadata_path happens to sit beside organisms/ on this
 * deployment. Both paths are configurable independently — point metadata_path anywhere
 * else and isAssemblyInTaxonomyTree() reads a file that does not exist, so every
 * organism reports "not yet in selector". (CLAUDE.md: never derive, use ConfigManager.)
 */
function organism_checklist_not_in_tree(array $organisms, string $tree_file): array
{
    $organisms_not_in_tree = [];
    foreach ($organisms as $org) {
        if (!isAssemblyInTaxonomyTree($org, '', $tree_file)) {
            $organisms_not_in_tree[] = $org;
        }
    }
    return $organisms_not_in_tree;
}

/**
 * Organisms with no group assignment in organism_assembly_groups.json.
 *
 * The file comes from metadata_path. It used to be derived as
 * dirname($organism_data) . '/metadata/...', the same mistake corrected for the tree
 * file above -- right here only because metadata/ happens to sit beside organisms/.
 *
 * The file has one entry per organism/assembly/gene set, so an organism can appear
 * several times (7 do today). It counts as assigned if ANY of its entries has a group.
 * This used to keep only the LAST entry per organism, so an organism whose last entry
 * was ungrouped would be reported unassigned despite groups on its other entries.
 * (Latent: no organism is in that state today.)
 */
function organism_checklist_unassigned(string $groups_file, array $organisms): array
{
    $entries = loadJsonFile($groups_file, []);
    if (!is_array($entries)) $entries = [];

    $assigned = [];
    foreach ($entries as $item) {
        $org = is_array($item) ? ($item['organism'] ?? '') : '';
        if ($org !== '' && !empty($item['groups'])) {
            $assigned[$org] = true;
        }
    }

    return array_values(array_filter($organisms, fn($org) => !isset($assigned[$org])));
}
