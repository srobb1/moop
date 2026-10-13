<?php
/**
 * Organism modals (Manage Organisms) -- the data behind each one.
 *
 * admin/api/get_organism_modal.php was a 1,168-line endpoint whose four render
 * functions ran SQL queries and directory scans from inside their HTML. The data
 * gathering lives here now; the markup is in admin/pages/organism_modals/{type}.php and
 * the endpoint only validates the request and dispatches. Each *_data() function
 * returns exactly the variables its view uses.
 *
 * Moved 2026-10-12 without changing output: all 351 modals (85 organisms x db /
 * metadata / status, plus 96 assemblies) rendered byte-identical before and after.
 */


/** Render one modal view with its data. */
function organism_modal_render(string $view, array $vars): void {
    extract($vars);
    include __DIR__ . '/../admin/pages/organism_modals/' . $view . '.php';
}

/**
 * Database modal. Null when the cache holds no database validation for the organism.
 */
function organism_modal_db_data($organism, $data, $organism_data): ?array {
    if (!$data['db_validation']) return null;
    $validation         = $data['db_validation'];
    // Live scan — always fresh so the rename helper reflects current disk state
    $assembly_validation = ($data['db_file'] && $data['path'])
        ? validateAssemblyDirectories($data['db_file'], $data['path'])
        : $data['assembly_validation'];
    $org_safe           = htmlspecialchars($organism);

    // Live query: get gene_sets from DB grouped by genome
    $gene_sets_by_genome = [];
    $gene_set_query_error = null;
    if ($data['db_file'] && $data['db_validation']['readable'] ?? false) {
        try {
            $gs_dbh = getDbConnection($data['db_file']);
            $gs_stmt = $gs_dbh->query("
                SELECT gs.gene_set_id, gs.gene_set_name, gs.gene_set_description,
                       g.genome_id, g.genome_name, g.genome_accession
                FROM gene_set gs
                JOIN genome g ON gs.genome_id = g.genome_id
                ORDER BY g.genome_name, gs.gene_set_name
            ");
            foreach ($gs_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $gkey = $row['genome_name'] ?: $row['genome_accession'];
                if (!isset($gene_sets_by_genome[$gkey])) {
                    $gene_sets_by_genome[$gkey] = [
                        'genome_name'      => $row['genome_name'],
                        'genome_accession' => $row['genome_accession'],
                        'gene_sets'        => []
                    ];
                }
                // Find the assembly dir for this genome
                $org_path = $organism_data . '/' . $organism;
                $asm_dir  = null;
                foreach ([$row['genome_name'], $row['genome_accession']] as $candidate) {
                    if ($candidate && is_dir("$org_path/$candidate")) { $asm_dir = $candidate; break; }
                }
                $gs_dir_exists = $asm_dir !== null && is_dir("$org_path/$asm_dir/{$row['gene_set_name']}");
                $gene_sets_by_genome[$gkey]['gene_sets'][] = [
                    'name'        => $row['gene_set_name'],
                    'description' => $row['gene_set_description'],
                    'asm_dir'     => $asm_dir,
                    'dir_exists'  => $gs_dir_exists,
                ];
            }
            $gs_dbh = null;
        } catch (PDOException $e) {
            $gene_set_query_error = $e->getMessage();
        }
    }

    // Per 'missing gene set directory' mismatch: the other directories in that assembly
    // (rename candidates) and how many features the orphaned DB row holds.
    $gene_set_mismatch_details = [];
    foreach (($assembly_validation['mismatches'] ?? []) as $mismatch) {
        if (($mismatch['type'] ?? '') !== 'missing_gene_set_directory') continue;
        $asm_path   = $data['path'] . '/' . $mismatch['assembly_dir'];
        $existing_gs_dirs = [];
        if (is_dir($asm_path)) {
            foreach (array_diff(scandir($asm_path), ['.', '..']) as $f) {
                if (is_dir("$asm_path/$f") && $f !== $mismatch['gene_set_name']) {
                    $existing_gs_dirs[] = $f;
                }
            }
        }
        // This mismatch means the gene set is a ROW in organism.sqlite with no
        // matching directory. The feature count tells the admin which case it is:
        // an empty leftover row (0) vs a real gene set whose data was not deployed (>0).
        $gs_feature_count = null;
        if (!empty($data['db_file']) && file_exists($data['db_file'])) {
            try {
                $_dbh  = getDbConnection($data['db_file']);
                $_stmt = $_dbh->prepare(
                    'SELECT COUNT(*) FROM feature f
                     JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                     WHERE gs.gene_set_name = ?'
                );
                $_stmt->execute([$mismatch['gene_set_name']]);
                $gs_feature_count = (int) $_stmt->fetchColumn();
                $_dbh = null;
            } catch (PDOException $e) {
                $gs_feature_count = null;
            }
        }
        $gene_set_mismatch_details[$mismatch['assembly_dir'] . '/' . $mismatch['gene_set_name']] = [
            'existing_gs_dirs' => $existing_gs_dirs,
            'gs_feature_count' => $gs_feature_count,
        ];
    }

    return compact('organism', 'data', 'organism_data', 'validation', 'assembly_validation', 'org_safe',
                   'gene_sets_by_genome', 'gene_set_query_error', 'gene_set_mismatch_details');
}

/** Metadata (organism.json) modal. */
function organism_modal_metadata_data($organism, $data, $organism_data): array {
    $json_val = $data['json_validation'];
    $org_safe = htmlspecialchars($organism);
    return compact('organism', 'data', 'organism_data', 'json_val', 'org_safe');
}

/** Assembly modal. */
function organism_modal_asm_data($organism, $assembly, $data, $sequence_types, $groups_data, $organism_data): array {
    $safe_asm_id         = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism . '_' . $assembly);
    $assembly_validation = $data['assembly_validation'];
    $assembly_path       = $data['path'] . '/' . $assembly;

    // FAI check (assembly-level)
    $genome_fa_path = $assembly_path . '/' . genome_fasta_filename();
    $fai_info = $data['fai_validation'][$assembly] ?? [
        'genome_fa_exists' => file_exists($genome_fa_path),
        'fai_exists'       => file_exists($genome_fa_path . '.fai'),
    ];
    $modal_has_missing_fai = ($fai_info['genome_fa_exists'] && !$fai_info['fai_exists']);

    $assembly_groups = getAssemblyGroups($organism, $assembly, $groups_data);

    // DB mismatch check
    $has_db_mismatch      = false;
    $db_mismatch_messages = [];
    $matching_genome      = null;
    if ($assembly_validation) {
        foreach ($assembly_validation['genomes'] as $genome) {
            if ($assembly === $genome['genome_name'] || $assembly === $genome['genome_accession']) {
                $matching_genome = $genome;
                break;
            }
        }
        if (!$matching_genome) {
            $has_db_mismatch        = true;
            $db_mismatch_messages[] = "Assembly directory '$assembly' does not match any genome_name or genome_accession in the database";
        }
    }

    // Canonical "Name (Accession)" label from the matched genome record (reuses the
    // db-directory check above); falls back to the bare directory name on a mismatch.
    $assembly_display = $matching_genome
        ? assembly_label($matching_genome['genome_name'] ?? '', $matching_genome['genome_accession'] ?? '')
        : $assembly;

    // Live scan: gene_set subdirs inside this assembly
    $gene_set_dirs_live = [];
    if (is_dir($assembly_path)) {
        foreach (array_diff(scandir($assembly_path), ['.', '..']) as $f) {
            // is_gene_set_dir(): a gene set is identified by its FASTA payload, as on the
            // setup checklist. Every subdirectory used to count, so an empty leftover
            // (Schmidtea's *_orig dirs) was listed as a gene set missing all its FASTAs.
            if (is_gene_set_dir("$assembly_path/$f")) $gene_set_dirs_live[] = $f;
        }
    }
    sort($gene_set_dirs_live);

    // Classify sequence types BY KEY: 'genome' is the assembly-level FASTA, the rest
    // live in each gene set -- the rule getAssemblyFastaFiles() and the checklist use.
    //
    // This used to classify by where a file was FOUND: a type with a file directly in
    // the assembly dir was assembly-level, anything else was expected per gene set. So an
    // assembly with no genome (transcriptome/proteome-only -- 24 of 96 here, by design)
    // had genome.fa "Missing" inside every gene set and a red "Missing required FASTA
    // files" summary, in the same modal that says a genome is not applicable to it.
    $asm_level_files = [];   // [type => ['config'=>..., 'file'=>string]]
    $gs_level_types  = [];   // [type => config]  — checked per gene_set
    foreach ($sequence_types as $type => $config) {
        if ($type === 'genome') {
            if (file_exists("$assembly_path/{$config['pattern']}")) {
                $asm_level_files[$type] = ['config' => $config, 'file' => $config['pattern']];
            }
        } else {
            $gs_level_types[$type] = $config;
        }
    }

    // Per gene_set: FASTA presence
    $gs_fasta_status = [];  // [gs => [type => ['found'=>bool, 'file'=>string|null]]]
    foreach ($gene_set_dirs_live as $gs) {
        $gs_fasta_status[$gs] = [];
        foreach ($gs_level_types as $type => $config) {
            // file_exists, not glob: a pattern is a literal filename, and glob would read
            // [ ] * ? in it as wildcards.
            $gs_fasta_status[$gs][$type] = file_exists("$assembly_path/$gs/{$config['pattern']}")
                ? ['found' => true,  'file' => $config['pattern']]
                : ['found' => false, 'file' => null];
        }
    }

    // Per gene_set: the labels of the types it lacks ("CDS", "mRNA"), so the header badge
    // and the summary say WHAT is missing, not just "Missing files".
    $gs_missing_labels = [];  // [gs => list<label>], only gene sets with gaps
    foreach ($gs_fasta_status as $gs => $files) {
        foreach ($files as $type => $fi) {
            if (!$fi['found']) $gs_missing_labels[$gs][] = $gs_level_types[$type]['label'] ?? $type;
        }
    }

    // Per gene_set: BLAST indexes
    $gs_blast_status = [];  // [gs => blast_validation_result]
    foreach ($gene_set_dirs_live as $gs) {
        $gs_blast_status[$gs] = validateBlastIndexFiles("$assembly_path/$gs", $sequence_types);
    }

    // Compute missing flags from live data
    $is_missing_live = false;
    if (!empty($gs_level_types)) {
        if (empty($gene_set_dirs_live)) {
            $is_missing_live = true;
        } else {
            foreach ($gs_fasta_status as $gs_files) {
                foreach ($gs_files as $fi) {
                    if (!$fi['found']) { $is_missing_live = true; break 2; }
                }
            }
        }
    }
    $modal_has_missing_blast = false;
    foreach ($gs_blast_status as $bv) {
        foreach ($bv['databases'] ?? [] as $db) {
            if (!$db['has_indexes']) { $modal_has_missing_blast = true; break 2; }
        }
    }

    // Directories in the organism folder, for the rename and delete dropdowns. Listed
    // once here; the modal used to run the same scandir twice, once per dropdown.
    $assembly_dir_options = [];
    $organism_path = $data['path'];
    if (is_dir($organism_path)) {
        $dirs = array_diff(scandir($organism_path), ['.', '..', 'organism.json', basename($data['db_file'] ?? '')]);
        foreach ($dirs as $dir) {
            if (is_dir("$organism_path/$dir")) $assembly_dir_options[] = $dir;
        }
    }

    return compact('organism', 'assembly', 'data', 'sequence_types', 'groups_data', 'organism_data', 'safe_asm_id', 'assembly_validation', 'assembly_path', 'genome_fa_path', 'fai_info', 'modal_has_missing_fai', 'assembly_groups', 'has_db_mismatch', 'db_mismatch_messages', 'matching_genome', 'assembly_display', 'gene_set_dirs_live', 'asm_level_files', 'gs_level_types', 'gs_fasta_status', 'gs_missing_labels', 'gs_blast_status', 'is_missing_live', 'modal_has_missing_blast', 'assembly_dir_options');
}

/** Overall-status modal. */
function organism_modal_status_data($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types): array {
    $status      = getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
    $checks      = $status['checks'];
    $pass_count  = $status['pass_count'];
    return compact('organism', 'data', 'status', 'checks', 'pass_count');
}
