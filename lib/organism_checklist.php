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
        'gene_sets_missing_gff' => organism_checklist_gene_sets_missing_gff($organism_data, $organisms_in_system, $sequence_types),
        'organisms_not_in_tree' => organism_checklist_not_in_tree($organisms_in_system, "$metadata_path/taxonomy_tree_config.json"),
        'unassigned_organisms'  => organism_checklist_unassigned($organism_data, $organisms_in_system),
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
                            'missing'  => $db['missing_indexes'],
                        ];
                    }
                }
            }
        }
    }

    foreach ($blast_issues as &$issue) {
        $issue['gs_fullpath'] = $organism_data . '/' . $issue['organism'] . '/' . $issue['assembly'] . '/' . ($issue['gene_set'] ?? 'v1');
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

/** Quick permission check on each organism directory. */
function organism_checklist_permission_issues(string $organism_data, array $organisms, string $expected_group): array
{
    $permission_issues = [];
    foreach ($organisms as $org) {
        $org_dir = "$organism_data/$org";

        if (!file_exists($org_dir)) {
            $permission_issues[] = [
                'organism' => $org,
                'path' => $org_dir,
                'issues' => ['Directory does not exist']
            ];
            continue;
        }

        // Get full permission with leading digits (e.g., 2775, 0755)
        $perms_full = substr(sprintf('%o', fileperms($org_dir)), -4);
        $perms = ltrim($perms_full, '0') ?: '0';

        $group = 'unknown';
        if (function_exists('posix_getgrgid')) {
            $gr = posix_getgrgid(filegroup($org_dir));
            if ($gr) { $group = $gr['name']; }
        } elseif ($org_dir) {
            $stat_out = [];
            @exec("stat -c '%G' " . escapeshellarg($org_dir) . " 2>/dev/null", $stat_out);
            if (!empty($stat_out[0]) && $stat_out[0] !== 'UNKNOWN') { $group = $stat_out[0]; }
        }

        $issues = [];

        // Judge by IMPACT, not by an exact mode string — same rule the dashboard and
        // the Filesystem Permissions page use (lib/permission_check.php). The old test
        // here was `$perms !== '2775'`, which is wrong in both directions: it flags an
        // apache-owned 2755 directory the web CAN write, and it would pass a 2775
        // directory whose SELinux label blocks writes anyway.
        //
        // What actually matters: can the web server CREATE files here? makeblastdb and
        // samtools faidx write new files, and creating a file needs write on the parent
        // directory, not on the file.
        if (!is_writable($org_dir)) {
            $issues[] = "The web server ($expected_group) cannot write here — "
                      . "Build BLAST Index and index generation will fail ($perms)";
        }

        // SGID keeps new files in the web group. Without it, files created here inherit
        // the creator's group and the next tool along cannot read them.
        if (!($perms_full[0] === '2')) {
            $issues[] = "Missing the SGID bit ($perms) — new files here will not inherit "
                      . "the $expected_group group";
        }

        // Check group matches the web server group
        if ($group !== $expected_group) {
            $issues[] = "Group is $group, should be $expected_group";
        }

        if (!empty($issues)) {
            $permission_issues[] = [
                'organism' => $org,
                'path' => $org_dir,
                'perms' => $perms,
                'group' => $group,
                'issues' => $issues
            ];
        }
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

/** Organisms with no group assignment in organism_assembly_groups.json. */
function organism_checklist_unassigned(string $organism_data, array $organisms): array
{
    $groups_file = dirname($organism_data) . '/metadata/organism_assembly_groups.json';
    $group_assignments = [];
    if (file_exists($groups_file)) {
        $group_data = loadJsonFile($groups_file, []);
        if (is_array($group_data)) {
            foreach ($group_data as $item) {
                $org = $item['organism'] ?? '';
                if (!empty($org)) {
                    $group_assignments[$org] = $item['groups'] ?? [];
                }
            }
        }
    }

    $unassigned_organisms = [];
    foreach ($organisms as $org) {
        if (empty($group_assignments[$org])) {
            $unassigned_organisms[] = $org;
        }
    }
    return $unassigned_organisms;
}
