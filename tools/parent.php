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
$sources_by_group      = getAccessibleAssemblies($organism_name);
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
    $coord_out = [];
    if (file_exists($feature_coords_tsv)) {
        exec('grep -m1 ' . escapeshellarg('^' . $feature_uniquename . "\t") . ' ' . escapeshellarg($feature_coords_tsv), $coord_out);
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
            } elseif ($gff_available) {
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

        // First pass: find the gene's GFF ID and collect mRNA children
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

            // mRNA / transcript child
            if (preg_match('/\bParent=([^;,]+)/', $p[8], $par_m)) {
                $parent = $par_m[1];
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

// Check whether genomic sequence fetch is available (genome.fa stays at assembly level)
$genome_seq_available = file_exists("$assembly_dir_base/genome.fa") && file_exists("$assembly_dir_base/genome.fa.fai");

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

// Build typed ID map and sequence list (parent + all children)
$typed_ids = [$feature_uniquename => $type];
$retrieve_these_seqs = [$feature_uniquename];
foreach ($children as $child) {
    $typed_ids[$child['feature_uniquename']] = $child['feature_type'];
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
            handleSequenceDownload($download_file_flag, $sequence_type, $displayed_content[$sequence_type]);
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
        'page_styles' => ["/moop/css/parent.css", "/moop/css/parent-nav.css"],
        'page_script' => [
            "/moop/js/modules/collapse-handler.js",
            "/moop/js/modules/parent-tools.js",
            "/moop/js/modules/gene-model-viewer.js",
            "/moop/js/modules/sequence-formatter.js",
            "/moop/js/modules/parent-nav.js"
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
