<?php
/**
 * PARENT DISPLAY PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (parent.php)
 *   ↓
 * Validate user access
 *   ↓
 * Load parent feature data from database
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/parent.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Validate user access (via access_control.php)
 * - Load parent feature data from database
 * - Configure title, scripts, styles
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (pages/parent.php does that)
 * 
 * URL Parameters:
 * - organism: Organism name (required)
 * - uniquename: Feature uniquename (required)
 */

// Check for download request early (before other processing)
$download_file_flag = isset($_POST['download_file']) && $_POST['download_file'] == '1';
$sequence_type = trim($_POST['sequence_type'] ?? '');

// Start output buffering if download is requested to prevent any stray output
if ($download_file_flag) {
    ob_start();
}

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/parent_functions.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sequence_types = $config->getSequenceTypes();
$siteTitle = $config->getString('siteTitle');

// Validate required parameters
if (empty($_GET['organism']) || empty($_GET['uniquename'])) {
    die("Error: Missing required parameters. Please provide both 'organism' and 'uniquename' parameters.");
}

$uniquename = $_GET['uniquename']; // uniquename is a feature identifier, not an assembly

// Setup organism context (validates param, loads info, checks access)
$organism_context = setupOrganismDisplayContext($_GET['organism'], $organism_data, true);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Verify and get database path
$db = verifyOrganismDatabase($organism_name, $organism_data);

// Get accessible gene_sets for permission-based DB filtering
$sources_by_group      = getAccessibleGeneSets($organism_name);
$accessible_sources    = flattenSourcesList($sources_by_group);
$accessible_gene_set_ids = array_values(array_filter(array_column($accessible_sources, 'gene_set_id')));

// Security: Verify user has access to at least one gene_set for this organism
if (empty($accessible_gene_set_ids)) {
    die("Error: No accessible gene sets found for this organism.");
}

// Load annotation configuration using helper
$annotation_config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFileRequired($annotation_config_file, "Missing annotation_config.json");

$analysis_order = [];
$analysis_desc = [];
$annotation_colors = [];
$annotation_labels = [];

// Require new format with annotation_types
if (isset($annotation_config['annotation_types'])) {
    $types = $annotation_config['annotation_types'];
    // Sort by order
    uasort($types, function($a, $b) {
        return ($a['order'] ?? 999) - ($b['order'] ?? 999);
    });
    
    foreach ($types as $key => $type_config) {
        if ($type_config['enabled'] ?? true) {
            $analysis_order[] = $key;
            $analysis_desc[$key] = $type_config['description'] ?? '';
            $annotation_colors[$key] = $type_config['color'] ?? 'secondary';
            $annotation_labels[$key] = $type_config['display_label'] ?? $key;
        }
    }
} else {
    die("Error: annotation_config.json must use the new 'annotation_types' format. Legacy format is no longer supported.");
}

// Define parent types from organism.json feature_types, fallback to defaults
$parents = ['gene', 'pseudogene'];
if (!empty($organism_info['feature_types']['parents'])) {
    $parents = $organism_info['feature_types']['parents'];
}

// Get ancestors for the feature
$ancestors = getAncestors($uniquename, $db, $accessible_gene_set_ids);

// Save the highest ancestor with type in $parents in these variables
[$ancestor_feature_id, $ancestor_feature_uniquename, $ancestor_feature_type] = ['', '', ''];

if (count($ancestors) == 1) {
    // self only, no parents
    $ancestor = $ancestors[0];
    $ancestor_feature_id = $ancestor['feature_id'];
    $ancestor_feature_type = $ancestor['feature_type'];
    $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
    $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
} elseif (count($ancestors) > 1) {
    // self, plus at least one ancestor
    foreach ($ancestors as $ancestor) {
        $ancestor_feature_id = $ancestor['feature_id'];
        $ancestor_feature_type = $ancestor['feature_type'];
        $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
        $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
        if (in_array($ancestor_feature_type, $parents)) {
            // Stop: we reached our valid parent type for a page
            break;
        }
    }
}

// Performing SQL query to get info associated with found Parent ID
$row = getFeatureById($ancestor_feature_id, $db, $accessible_gene_set_ids);

// Get all info about Highest Parent
if (empty($row)) { 
    die("The gene $uniquename was not found in the database. Please, check the spelling carefully or try to find it in the search tool.");
}

$feature_id = $row['feature_id'];
$feature_uniquename = $row['feature_uniquename'];
$parent_id = $row['parent_feature_id'];
$name = $row['feature_name'];
$description = $row['feature_description'];      
$genus = $row['genus'];
$species = $row['species'];
$species_subtype = $row['subtype'];
$type = $row['feature_type'];
$common_name = $row['common_name'];
$genome_accession = $row['genome_accession'];
$genome_name      = $row['genome_name'];
$feature_gene_set_id = $row['gene_set_id'];

// Resolve gene_set name and directories first (needed for caching below)
$gene_set_name    = $row['gene_set_name'] ?? 'v1';
$assembly_dir_base = $config->getPath('organism_data') . '/' . $organism_name . '/' . $genome_accession;
$gene_set_dir     = $assembly_dir_base . '/' . $gene_set_name;

// Which child feature types have annotations somewhere in this gene set?
// Used to suppress purely structural types (exon, CDS) from the hierarchy and
// annotation cards while still showing annotated types even when this specific
// gene has 0 annotations for that type.
$annotated_child_types = getAnnotatedFeatureTypesInGeneSet((int)$feature_gene_set_id, $db, moop_cache_dir_for($gene_set_dir));

// Look up feature coordinates and build gene model from GFF.
// Fast path: feature_coords.tsv (tiny file) → tabix indexed GFF (milliseconds).
// Fallback:  plain grep on genes.gff for gene sets not yet indexed.
// With tabix the whole region is fetched in one call and parsed in PHP —
// no separate grep passes for mRNAs or exons.
$feature_loc   = null;
$gene_model    = null;
$gff_file      = "$gene_set_dir/" . genes_gff_filename();
$gff_available = file_exists($gff_file) && filesize($gff_file) > 0;

$genomes_dir        = $config->getPath('genomes_directory');
$tabix_gff          = "$genomes_dir/$organism_name/$genome_accession/$gene_set_name/annotations.gff3.gz";
$tabix_available    = file_exists($tabix_gff) && (file_exists("$tabix_gff.tbi") || file_exists("$tabix_gff.csi"));
$feature_coords_tsv = "$gene_set_dir/feature_coords.tsv";

if ($tabix_available || $gff_available) {
    $region_lines = [];   // all GFF lines for this gene's region

    // ── Step 1: get coordinates from feature_coords.tsv ─────────────────────
    //
    // The file is keyed on the TRANSCRIPT in column 1, with that transcript's GENE in
    // column 2:
    //
    //     NV2t011319001.1      NV2g011319000.1   chr2   6033193   6048126   +
    //     NV2t011319001.1:cds  NV2g011319000.1   chr2   6033193   6048126   +
    //
    // So a column-1 lookup can never match on a GENE page, which is what this page shows
    // in almost every case -- the controller resolves whatever was requested up to its
    // gene. The lookup missed every time, Step 2 found only the bare gene record, and the
    // model was then rebuilt by two more scans of the whole genes.gff further down.
    // Three full passes over 49 MB for Nematostella NV2, on every load, while the tabix
    // index that answers the same question in one seek sat unused on 69 of 72 gene sets.
    // Measured: 0.59s -> 0.08s cold, 0.44s -> 0.08s warm.
    $coord_out    = [];
    $matched_self = false;   // did we match THIS feature, or its gene?
    if (file_exists($feature_coords_tsv)) {
        exec('grep -m1 ' . escapeshellarg('^' . $feature_uniquename . "\t") . ' ' . escapeshellarg($feature_coords_tsv), $coord_out);
        $matched_self = !empty($coord_out[0]);

        if (!$matched_self) {
            // Not a transcript — try column 2, i.e. treat it as a gene id. awk rather than
            // grep so the field is matched exactly and cannot hit a coordinate or a
            // neighbouring column; it exits at the first hit, so it is one partial pass.
            exec('awk -F "\t" -v u=' . escapeshellarg($feature_uniquename)
                 . ' ' . escapeshellarg('$2==u {print; exit}')
                 . ' ' . escapeshellarg($feature_coords_tsv), $coord_out);
        }
    }

    if (!empty($coord_out[0])) {
        $cp = explode("\t", trim($coord_out[0]));   // uniquename, gene_id, seqname, start, end, strand
        if (count($cp) >= 5) {
            $feature_loc = [
                'seqname'    => $cp[2],
                'start'      => (int)$cp[3],
                'end'        => (int)$cp[4],
                'strand'     => $cp[5] ?? '.',
                'loc_string' => $cp[2] . ':' . $cp[3] . '-' . $cp[4],
            ];
            $region = escapeshellarg($cp[2] . ':' . $cp[3] . '-' . $cp[4]);
            if ($tabix_available) {
                exec('tabix ' . escapeshellarg($tabix_gff) . ' ' . $region, $region_lines);
            } elseif ($gff_available && $matched_self) {
                // Only safe when the row IS this feature. On a gene, `grep -F <gene id>`
                // returns the gene and its mRNAs but NOT the exons, whose Parent is the
                // mRNA -- which would populate $isoforms just enough to skip the targeted
                // greps below and render isoforms with no structure. Without an index a
                // gene falls through to Step 2, exactly as it did before.
                exec('grep -F ' . escapeshellarg($feature_uniquename) . ' ' . escapeshellarg($gff_file), $region_lines);
            }
        }
    }

    // ── Step 2: fallback grep if feature_coords.tsv missed ──────────────────
    if (empty($region_lines) && $gff_available) {
        exec('grep -m1 -E ' . escapeshellarg('ID=[^;:]*:?' . preg_quote($feature_uniquename) . '(;|$)') . ' ' . escapeshellarg($gff_file), $region_lines);
        if (empty($region_lines)) {
            $tmp = [];
            exec('grep -m1 -F ' . escapeshellarg($feature_uniquename) . ' ' . escapeshellarg($gff_file), $tmp);
            if (!empty($tmp[0])) {
                $p = explode("\t", $tmp[0]);
                if (isset($p[2]) && strtolower($p[2]) === strtolower($type)) $region_lines = $tmp;
            }
        }
        if (!$feature_loc && !empty($region_lines[0])) {
            $p = explode("\t", $region_lines[0]);
            if (count($p) >= 7) {
                $feature_loc = [
                    'seqname'    => $p[0], 'start' => (int)$p[3], 'end' => (int)$p[4],
                    'strand'     => $p[6], 'loc_string' => $p[0] . ':' . $p[3] . '-' . $p[4],
                ];
            }
        }
    }

    // ── Step 3: parse region lines for gene model ────────────────────────────
    if ($feature_loc && !empty($region_lines)) {
        $gff_gene_id = $feature_uniquename;
        $isoforms    = [];
        $exon_like   = ['exon', 'five_prime_utr', 'three_prime_utr', 'utr'];

        // Resolve THIS gene's GFF id before looking at any child.
        //
        // A tabix region returns every line OVERLAPPING the span, so a neighbouring gene
        // that overlaps this one arrives in the same batch — and its mRNAs are perfectly
        // valid mRNA records. Without knowing our own id first we cannot tell them apart,
        // and the ids are not sorted in our favour: an overlapping neighbour starting
        // earlier is emitted first. NV2g007734000.1 (5 mRNAs) drew 6, the extra being
        // NV2t007735001.1 — a different gene's transcript, in the diagram and in the
        // sequence list built from it.
        foreach ($region_lines as $line) {
            $p = explode("\t", $line);
            if (count($p) < 9) continue;
            if (strtolower($p[2]) !== strtolower($type)) continue;
            if (!preg_match('/\bID=([^;]+)/', $p[8], $gid_m)) continue;
            if (strpos($p[8], $feature_uniquename) !== false ||
                preg_match('/\bID=[^;:]*:?' . preg_quote($feature_uniquename) . '(;|$)/', $p[8])) {
                $gff_gene_id = $gid_m[1];
                break;
            }
        }

        // First pass: collect the mRNA children OF THIS GENE
        foreach ($region_lines as $line) {
            $p = explode("\t", $line);
            if (count($p) < 9) continue;
            if (!preg_match('/\bID=([^;]+)/', $p[8], $id_m)) continue;

            $ft = strtolower($p[2]);

            // Gene record — capture the actual GFF ID (may differ from DB uniquename)
            if ($ft === strtolower($type) && (
                strpos($p[8], $feature_uniquename) !== false ||
                preg_match('/\bID=[^;:]*:?' . preg_quote($feature_uniquename) . '(;|$)/', $p[8])
            )) {
                $gff_gene_id = $id_m[1];
                continue;
            }

            // mRNA / transcript child — only if its Parent IS this gene. $parent was read
            // here before and never compared, which is what let a neighbour's transcript
            // into the diagram once the region fetch started returning more than one gene.
            if (preg_match('/\bParent=([^;,]+)/', $p[8], $par_m)) {
                $parent = $par_m[1];
                if ($parent !== $gff_gene_id) continue;
                if (in_array($ft, ['mrna', 'transcript', 'mrna_with_minus_1_frameshift']) || strpos($ft, 'rna') !== false) {
                    $mid = $id_m[1];
                    $isoforms[$mid] = [
                        'id'     => $mid,
                        'type'   => $p[2],
                        'anchor' => 'annot_section_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $mid . '_' . ($analysis_order[0] ?? 'annotation')),
                        'start'  => (int)$p[3],
                        'end'    => (int)$p[4],
                        'strand' => $p[6],
                        'exons'  => [],
                        'cds'    => [],
                    ];
                }
            }
        }

        // Second pass: collect exon/CDS into their parent isoforms
        foreach ($region_lines as $line) {
            $p = explode("\t", $line);
            if (count($p) < 9) continue;
            $ft = strtolower($p[2]);
            if ($ft !== 'cds' && !in_array($ft, $exon_like)) continue;
            if (!preg_match('/\bParent=([^;,]+)/', $p[8], $pm)) continue;
            if (!isset($isoforms[$pm[1]])) continue;
            $coord = ['start' => (int)$p[3], 'end' => (int)$p[4]];
            if ($ft === 'cds') {
                $isoforms[$pm[1]]['cds'][]   = $coord;
            } else {
                $isoforms[$pm[1]]['exons'][] = array_merge($coord, ['type' => $p[2]]);
            }
        }

        // If tabix region lacked mRNAs (fallback grep case), do one targeted grep
        if (empty($isoforms) && $gff_available) {
            $mrna_raw = [];
            exec('grep -E ' . escapeshellarg('Parent=' . preg_quote($gff_gene_id) . '(;|$)') . ' ' . escapeshellarg($gff_file), $mrna_raw);
            foreach ($mrna_raw as $line) {
                $p = explode("\t", $line);
                if (count($p) < 9 || !preg_match('/\bID=([^;]+)/', $p[8], $m)) continue;
                $mid = $m[1];
                $isoforms[$mid] = [
                    'id' => $mid, 'type' => $p[2],
                    'anchor' => 'annot_section_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $mid . '_' . ($analysis_order[0] ?? 'annotation')),
                    'start' => (int)$p[3], 'end' => (int)$p[4], 'strand' => $p[6],
                    'exons' => [], 'cds' => [],
                ];
            }
            if (!empty($isoforms)) {
                $patterns  = array_map(fn($mid) => '-e ' . escapeshellarg('Parent=' . $mid), array_keys($isoforms));
                $child_raw = [];
                exec('grep -F ' . implode(' ', $patterns) . ' ' . escapeshellarg($gff_file), $child_raw);
                foreach ($child_raw as $line) {
                    $p = explode("\t", $line);
                    if (count($p) < 9) continue;
                    $ft = strtolower($p[2]);
                    if ($ft !== 'cds' && !in_array($ft, $exon_like)) continue;
                    if (!preg_match('/\bParent=([^;,]+)/', $p[8], $pm) || !isset($isoforms[$pm[1]])) continue;
                    $coord = ['start' => (int)$p[3], 'end' => (int)$p[4]];
                    if ($ft === 'cds') $isoforms[$pm[1]]['cds'][] = $coord;
                    else               $isoforms[$pm[1]]['exons'][] = array_merge($coord, ['type' => $p[2]]);
                }
            }
        }

        $isoform_list = array_values(array_filter($isoforms, fn($i) => !empty($i['exons']) || !empty($i['cds'])));
        if (!empty($isoform_list)) {
            $gene_model = [
                'gene'     => array_merge($feature_loc, ['id' => $feature_uniquename, 'type' => $type]),
                'isoforms' => $isoform_list,
            ];
        }
    }
}

// Check whether genomic sequence fetch is available (the genome FASTA stays at assembly level)
$genome_fasta_file    = genome_fasta_filename();
$genome_seq_available = file_exists("$assembly_dir_base/$genome_fasta_file")
                     && file_exists("$assembly_dir_base/$genome_fasta_file.fai");

$family_feature_ids = [$feature_id];
$retrieve_these_seqs = [$feature_uniquename];

// Get children with hierarchical structure (for proper nesting)
$children_hierarchical = getChildrenHierarchical($feature_id, $db, $accessible_gene_set_ids);

// Get all children flat for sequence retrieval (keeping getChildren for backwards compatibility)
$children = getChildren($feature_id, $db, $accessible_gene_set_ids);

// Optimize: Get ALL annotations for parent and all children in ONE query
$all_feature_ids = [$feature_id];
foreach ($children as $child) {
    $all_feature_ids[] = $child['feature_id'];
}
$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);

// Repoint the gene-model diagram's row links at the annotation CARDS.
//
// The isoform ids in $gene_model come from the GFF, and the cards are keyed by the DATABASE
// uniquename. Those differ on RefSeq gene sets, where the GFF prefixes transcripts with
// "rna-": the diagram linked to "rna-XM_048723428.1" and the card is "XM_048723428.1", so
// every row click on gene 5500758 did nothing. NV2-style sets matched by coincidence, which
// is why the diagram looked fine there.
//
// Done here rather than where the model is built, because that runs before the children are
// fetched — the database is the only thing that can say which transcript a GFF id is.
if (!empty($gene_model['isoforms']) && !empty($children)) {
    $by_uniquename = [];
    foreach ($children as $__c) {
        $by_uniquename[$__c['feature_uniquename']] = $__c['feature_uniquename'];
    }
    foreach ($gene_model['isoforms'] as &$__iso) {
        $gff_id = (string)($__iso['id'] ?? '');
        $match  = $by_uniquename[$gff_id] ?? null;

        if ($match === null) {
            // Strip a leading GFF-flavour prefix ("rna-", "transcript:", "mRNA:") and retry.
            $stripped = preg_replace('/^[A-Za-z]+[-:]/', '', $gff_id);
            $match = $by_uniquename[$stripped] ?? null;
        }
        if ($match === null) {
            // Last resort: the uniquename is a suffix of the GFF id.
            foreach ($by_uniquename as $u) {
                if ($u !== '' && substr($gff_id, -strlen($u)) === $u) { $match = $u; break; }
            }
        }
        // No match leaves the row unlinked, which is honest — better than a link that
        // silently scrolls nowhere.
        $__iso['anchor'] = $match !== null ? moop_annotation_card_anchor($match) : null;
    }
    unset($__iso);
}

// Do all the isoforms carry the SAME annotations?
//
// Worth saying out loud, because it is a real biological observation and the page cannot
// show it: the annotation tables are grouped per transcript, so a reader comparing five
// isoforms has to scan five sets to notice they are identical. Measured over a 372-gene
// sample of this organism, a third of multi-isoform genes are in exactly that position.
//
// NOT a licence to render them once. The annotation genuinely belongs to the transcript,
// and collapsing would make the page structure depend on the data — some genes grouped,
// some not. Telling the reader costs one line and hides nothing.
//
// "Same" means the same set of (annotation type, accession) pairs. Scores and descriptions
// are deliberately excluded: two isoforms hit by the same domain at slightly different
// scores still carry the same annotations, which is what the sentence claims.
$isoform_annotation_signature = static function (array $by_type): string {
    $keys = [];
    foreach ($by_type as $type => $rows) {
        foreach ($rows as $r) {
            $keys[] = $type . "\x1f" . ($r['annotation_accession'] ?? '');
        }
    }
    sort($keys, SORT_STRING);
    return implode("\x1e", array_unique($keys));
};

$isoforms_share_annotations = false;
$annotated_isoform_count    = 0;
if (count($children_hierarchical) > 1) {
    $sigs = [];
    foreach ($children_hierarchical as $__child) {
        $sigs[] = $isoform_annotation_signature($all_annotations[$__child['feature_id']] ?? []);
    }
    // An all-empty set is not an interesting thing to announce.
    $isoforms_share_annotations = count(array_unique($sigs)) === 1 && $sigs[0] !== '';
    $annotated_isoform_count    = count($sigs);
}

// Build typed ID map and sequence list (parent + all children).
//
// The map comes from the shared expansion so every sequence-retrieval path in the app
// walks the hierarchy the same way — see expandFeaturesToAllSequenceTypes(). This page
// starts at the feature the user is looking at, so the DESCENDANTS half is what does the
// work here (a gene yields all its isoforms with their CDS and proteins); the ancestors
// half is what makes the same call correct when the starting point is an mRNA or protein.
// $accessible_gene_set_ids is passed through as the access filter, exactly as the
// getChildren() call above does — dropping it would expand into gene sets the viewer
// cannot see.
$typed_ids = expandFeaturesToAllSequenceTypes(
    [$feature_uniquename], $db, '', '', $accessible_gene_set_ids
);
// Guarantee the feature itself is present even if the expansion found nothing.
$typed_ids[$feature_uniquename] = $typed_ids[$feature_uniquename] ?? $type;

// The on-page sequence list stays driven by $children, which is also what the annotation
// query and the hierarchical display use.
$retrieve_these_seqs = [$feature_uniquename];
foreach ($children as $child) {
    $retrieve_these_seqs[] = $child['feature_uniquename'];
}
$retrieve_these_seqs = array_unique($retrieve_these_seqs);
sort($retrieve_these_seqs);
$gene_name = implode(",", $retrieve_these_seqs);

// Handle download request if present (BEFORE rendering page)
if ($download_file_flag && !empty($sequence_type)) {
    if (is_dir($gene_set_dir)) {
        $extract_result = extractSequencesForAllTypes($gene_set_dir, $typed_ids, $sequence_types, $organism_name, $genome_accession);
        $displayed_content = $extract_result['content'];

        if (!empty($displayed_content) && isset($displayed_content[$sequence_type])) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            handleSequenceDownload($download_file_flag, $sequence_type, $displayed_content[$sequence_type],
                [$organism_name ?? '', $genome_accession ?? '', $gene_set_name ?? '', $feature_uniquename ?? '']);
        }
    }
}


// Render page using layout system
echo render_display_page(
    __DIR__ . '/pages/parent.php',
    [
        'organism_name' => $organism_name,
        'feature_id' => $feature_id,
        'feature_uniquename' => $feature_uniquename,
        'description' => $description,
        'type' => $type,
        'genus' => $genus,
        'species' => $species,
        'species_subtype' => $species_subtype,
        'common_name' => $common_name,
        'genome_accession' => $genome_accession,
        'genome_name' => $genome_name,
        'gene_set_name' => $gene_set_name,
        'children' => $children,
        'children_hierarchical' => $children_hierarchical,
        'isoforms_share_annotations' => $isoforms_share_annotations,
        'annotated_isoform_count'    => $annotated_isoform_count,
        'db' => $db,
        'all_annotations' => $all_annotations,
        'analysis_order' => $analysis_order,
        'annotation_colors' => $annotation_colors,
        'annotation_labels' => $annotation_labels,
        'analysis_desc' => $analysis_desc,
        'retrieve_these_seqs' => $retrieve_these_seqs,
        'gene_name' => $gene_name,
        'enable_downloads' => true,
        'assembly_name' => $genome_accession,
        'site' => $site,
	'siteTitle' => $siteTitle,
        'annotated_child_types' => $annotated_child_types,
        'gene_model' => $gene_model,
        'feature_loc' => $feature_loc,
        'genome_seq_available' => $genome_seq_available,
        'page_styles' => ["/$site/css/parent.css", "/$site/css/parent-nav.css"],
        'page_script' => [
            "/$site/js/modules/collapse-handler.js",
            "/$site/js/modules/parent-tools.js",
            "/$site/js/modules/gene-model-viewer.js",
            "/$site/js/modules/sequence-formatter.js",
            "/$site/js/modules/parent-nav.js",
            "/$site/js/modules/isoform-minimap.js"
        ],
        'inline_scripts' => [
            "const geneModelData = " . json_encode($gene_model) . ";",
            "const moopOrganism = '" . addslashes($organism_name) . "';",
            "const moopAssembly = '" . addslashes($genome_accession) . "';",
            "const moopGeneSet = '" . addslashes($gene_set_name) . "';",
            "const moopSite = '/" . addslashes($site) . "';",
            "const siteTitle = '" . addslashes($siteTitle) . "';",
            "const genomeSequenceAvailable = " . ($genome_seq_available ? 'true' : 'false') . ";"
        ]
    ],
    htmlspecialchars($feature_uniquename)
);
?>
