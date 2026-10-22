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
$download_expression = ($_POST['download_expression'] ?? '') === '1';

// Start output buffering if download is requested to prevent any stray output
if ($download_file_flag || $download_expression) {
    ob_start();
}

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/parent_functions.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/fasta_index.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sequence_types = $config->getSequenceTypes();
$siteTitle = $config->getString('siteTitle');

// A real page, sent as 404, for a gene that is not there -- in place of a bare die() line with
// no way back (and, until 2026-10-13, the raw ID echoed unescaped: reflected XSS).
function moop_gene_not_found(string $uniquename, string $organism_name = '', string $organism_label = ''): void {
    global $site;
    http_response_code(404);
    echo render_display_page(__DIR__ . '/pages/gene_not_found.php', [
        'uniquename' => $uniquename, 'organism_name' => $organism_name,
        'organism_label' => $organism_label, 'site' => $site,
    ], 'Gene not found');
    exit;
}

// Validate required parameters
if (empty($_GET['organism']) || empty($_GET['uniquename'])) {
    moop_gene_not_found((string)($_GET['uniquename'] ?? ''));
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

// Which gene set. A link may name one (assembly + gene_set: search results, BLAST, the home
// page ID search); without it, an ID found in more than one gene set the viewer can open is
// ASKED about, never guessed -- before 2026-10-20 the page silently took whichever row came
// first and ignored the parameters the links already sent.
$req_assembly = (string)($_GET['assembly'] ?? '');
$req_gene_set = (string)($_GET['gene_set'] ?? '');
if ($req_assembly !== '' || $req_gene_set !== '') {
    $scoped = array_filter($accessible_sources, fn($src) =>
        // the accession, or the assembly's directory name (they differ for a few assemblies)
        ($req_assembly === '' || in_array($req_assembly, [$src['genome_accession'] ?? null, $src['assembly'] ?? null], true))
        && ($req_gene_set === '' || ($src['gene_set'] ?? '') === $req_gene_set));
    $accessible_gene_set_ids = array_values(array_unique(array_filter(array_column($scoped, 'gene_set_id'))));
}
$__label = trim(($organism_info['genus'] ?? '') . ' ' . ($organism_info['species'] ?? '')) ?: str_replace('_', ' ', $organism_name);
$__holding = moop_feature_gene_set_ids((string)$uniquename, $db, $accessible_gene_set_ids);
if (!$__holding) {
    // Not in any gene set the viewer may open (or not in the one named): the same 404 either
    // way, so a link cannot be used to learn what a restricted gene set holds.
    moop_gene_not_found((string)$uniquename, $organism_name, $__label);
}
if (count($__holding) > 1) {
    $__choices = [];
    foreach ($accessible_sources as $src) {
        if (!in_array((int)($src['gene_set_id'] ?? 0), $__holding, true)) continue;
        $accession = $src['genome_accession'] ?? $src['assembly'];
        $__choices["$accession/{$src['gene_set']}"] = [
            'assembly_label' => assembly_label($src['genome_name'] ?? '', $accession),
            'gene_set'       => $src['gene_set'],
            'url'            => "/$site/tools/parent.php?organism=" . urlencode($organism_name)
                              . '&uniquename=' . urlencode((string)$uniquename)
                              . '&assembly=' . urlencode($accession) . '&gene_set=' . urlencode($src['gene_set']),
        ];
    }
    echo render_display_page(__DIR__ . '/pages/gene_choose_gene_set.php', [
        'uniquename' => (string)$uniquename, 'organism_label' => $__label,
        'choices' => array_values($__choices), 'site' => $site,
    ], 'Choose a gene set');
    exit;
}
$accessible_gene_set_ids = $__holding;   // the one gene set; everything below reads only it

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
    moop_gene_not_found((string)$uniquename, $organism_name,
        trim(($organism_info['genus'] ?? '') . ' ' . ($organism_info['species'] ?? '')) ?: str_replace('_', ' ', $organism_name));
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

// The gene's location and its drawable model, from the ONE gene-model reader
// (lib/gff_gene_model.php): tabix region when indexed, grep of genes.gff otherwise, ids
// matched by whole value. This page used to carry its own two copies of that parse, matched
// ids with strpos(), and read only Parent= links.
require_once __DIR__ . '/../lib/gff_gene_model.php';
$feature_loc = null;
$gene_model  = null;
$__gm = moop_gene_model($organism_name, $genome_accession, $gene_set_name, $feature_uniquename, $type);
if ($__gm !== null) {
    $feature_loc = $__gm['loc'];
    if ($__gm['isoforms']) {
        $gene_model = [
            'gene'     => array_merge($feature_loc, ['id' => $feature_uniquename, 'type' => $type]),
            // 'anchor' is repointed at the annotation cards once the children are known (below).
            'isoforms' => array_map(fn($i) => [
                'id' => $i['id'], 'type' => $i['type'],
                'anchor' => 'annot_section_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $i['id'] . '_' . ($analysis_order[0] ?? 'annotation')),
                'start' => $i['start'], 'end' => $i['end'], 'strand' => $i['strand'],
                'exons' => $i['exons'], 'cds' => $i['cds'],
            ], $__gm['isoforms']),
        ];
    }
} else {
    // No GFF record: the gene's span from feature_coords.tsv still gives the overview its Location.
    $feature_loc = moop_feature_coords_loc($gene_set_dir, $feature_uniquename);
}

// Check whether genomic sequence fetch is available (the genome FASTA stays at assembly level)
$genome_fasta_file    = genome_fasta_filename();
$genome_seq_available = file_exists("$assembly_dir_base/$genome_fasta_file")
                     && file_exists("$assembly_dir_base/$genome_fasta_file.fai");

$family_feature_ids = [$feature_id];
$retrieve_these_seqs = [$feature_uniquename];

// Every descendant, flat (sequence retrieval, annotations) -- and the same rows as a tree for the
// nested display. One query: getChildrenHierarchical() ran this very query again internally.
$children = getChildren($feature_id, $db, $accessible_gene_set_ids);
$children_hierarchical = childTreeFromRows($feature_id, $children);

// The gene's symbol (HIVEP3), shown before its description — '' when feature_name holds an id or
// a LOC placeholder rather than a name. See moop_gene_symbol().
$gene_symbol = moop_gene_symbol($name, $feature_uniquename, array_column($children, 'feature_uniquename'));

// ── Big genes: a lighter page ────────────────────────────────────────────────
// A few "genes" have hundreds of transcripts -- transcriptome clusters, mostly in planarians
// (1,083 in Obama nungara onun.kc3.gc000000). Rendering a full annotation card and every
// sequence for each ran PHP out of memory (128 MB) and served nginx's bare 500 page; at 618
// transcripts the page that did load was 22.5 MB. 189 genes site-wide have >= 50.
//
// Above the threshold the page lists the transcripts with their counts, and loads one
// transcript's annotation card on demand -- through THIS page (…&transcript=ID), so it
// passes exactly the same access checks and gene lookup as the full page, rather than
// through a second endpoint that would have to duplicate them. Sequences are offered as
// downloads instead of being printed inline. (User, 2026-10-06: threshold 50 for now.)
$big_gene = count($children_hierarchical) >= MOOP_BIG_GENE_TRANSCRIPTS;

// The requested transcript, for an on-demand card: it must be one of THIS gene's direct
// children, found in the access-filtered list above -- never looked up on its own.
$fragment_child = null;
if ($big_gene && isset($_GET['transcript'])) {
    foreach ($children_hierarchical as $__i => $__c) {
        if ($__c['feature_uniquename'] === (string)$_GET['transcript']) {
            $fragment_child = $__c;
            $fragment_position = $__i;
            break;
        }
    }
    if ($fragment_child === null) {
        http_response_code(404);
        exit('That transcript is not part of this gene.');
    }
}

// Annotations for what the page will SHOW: everything on a normal gene; on a big gene only
// the gene itself (and the one requested transcript, for a card). One query either way.
$all_feature_ids = [$feature_id];
$__descend = function (array $node) use (&$__descend, &$all_feature_ids) {
    $all_feature_ids[] = $node['feature_id'];
    foreach ($node['grandchildren'] ?? [] as $__g) $__descend($__g);
};
if (!$big_gene) {
    foreach ($children as $child) {
        $all_feature_ids[] = $child['feature_id'];
    }
} elseif ($fragment_child !== null) {
    $__descend($fragment_child);
}
$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);

// On a big gene, each transcript's annotation COUNT for the list, from one GROUP BY rather
// than the rows themselves; a transcript's count includes its CDS and protein.
$child_annotation_counts = [];
if ($big_gene) {
    $__top = [];        // feature_id -> the direct child it belongs to
    foreach ($children_hierarchical as $__c) {
        $__stack = [$__c];
        while ($__stack) {
            $__n = array_pop($__stack);
            $__top[$__n['feature_id']] = $__c['feature_id'];
            foreach ($__n['grandchildren'] ?? [] as $__g) $__stack[] = $__g;
        }
    }
    foreach (array_chunk(array_keys($__top), 900) as $__ids) {
        $__rows = fetchData('SELECT feature_id, COUNT(*) AS n FROM feature_annotation WHERE feature_id IN ('
                            . implode(',', array_fill(0, count($__ids), '?')) . ') GROUP BY feature_id', $db, $__ids);
        foreach ($__rows as $__r) {
            $__t = $__top[$__r['feature_id']];
            $child_annotation_counts[$__t] = ($child_annotation_counts[$__t] ?? 0) + (int)$__r['n'];
        }
    }
}

// The on-demand card: that transcript's card alone, no page around it.
if ($fragment_child !== null) {
    // Table ids must not collide with cards already on the page: give each transcript its
    // own numbered block.
    $count = 100000 + $fragment_position * 100;
    echo generateChildAnnotationCards($fragment_child, $all_annotations, $analysis_order, $annotation_colors,
        $annotation_labels, $analysis_desc, $organism_name, $count, false, $annotated_child_types ?? []);
    exit;
}

// Protein lengths, from the protein FASTA's .fai — a point lookup, no sequence read. Shown on
// each protein in the Feature Hierarchy, and the longest in the overview: a short protein is a
// clue worth seeing at the top (user, 2026-10-06), and the longest is the one the naming
// pipeline reads length and predicted features from (assign_gene_names_v2.pl,
// longest_member: ties broken by id — same rule here). An id missing from the index (a gene
// set whose FASTA names differ) simply shows no length.
$protein_lengths = [];
$longest_protein = '';
$protein_ids = [];
foreach ($children as $__c) {
    if (_fasta_key_for_type((string)$__c['feature_type']) === 'protein') {
        $protein_ids[] = $__c['feature_uniquename'];
    }
}
$protein_pattern = $sequence_types['protein']['pattern'] ?? null;
$protein_fasta = $protein_pattern ? (glob("$gene_set_dir/$protein_pattern")[0] ?? null) : null;
if ($protein_ids && $protein_fasta && moop_fasta_index_available($protein_fasta)) {
    foreach (moop_fai_lookup($protein_fasta . '.fai', $protein_ids) as $__id => $__e) {
        $protein_lengths[$__id] = $__e['len'];
    }
    uksort($protein_lengths, 'strcmp');
    foreach ($protein_lengths as $__id => $__len) {
        if ($longest_protein === '' || $__len > $protein_lengths[$longest_protein]) {
            $longest_protein = $__id;
        }
    }
}

// How this gene was named — the overview card's statement list. Empty on a database with no
// naming tables (most, for now), which simply leaves the card as it was.
$gene_naming = getGeneNaming($feature_id, $db);

// The isoform the name was taken from, when the pipeline says (a `protein` statement, only on
// genes with more than one protein and evidence pointing at one). Marked in the tree, and the
// statement links to that transcript's annotations.
$naming_isoform = null;
foreach ($gene_naming['statements'] as $__s) {
    if ($__s['kind'] === 'protein' && !empty($__s['accession'])) {
        $naming_isoform = moop_resolve_naming_protein($__s['accession'], $children, $feature_id);
        break;
    }
}

// For the Annotations header, which starts collapsed: say what is inside before it is opened.
$annotation_total = 0;
foreach ($all_annotations as $__by_type) {
    foreach ($__by_type as $__rows) {
        $annotation_total += count($__rows);
    }
}
$annotation_total += array_sum($child_annotation_counts);   // a big gene's transcripts, not loaded as rows

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

// After the anchors are set (just above). A big gene's diagram draws ONE isoform (the one with the longest protein, else the first)
// instead of every row -- 360 rows was 18,000 px. Others are drawn in their card on demand.
$big_gene_diagram_anchor = '';
if ($big_gene && !empty($gene_model['isoforms'])) {
    $__longest_tx = $longest_protein !== '' ? (moop_resolve_naming_protein($longest_protein, $children, $feature_id)['mrna'] ?? null) : null;
    $__want = $__longest_tx !== null ? moop_annotation_card_anchor($__longest_tx) : null;
    foreach ($gene_model['isoforms'] as $__iso) {
        if ($__want !== null && ($__iso['anchor'] ?? null) === $__want) { $big_gene_diagram_anchor = $__want; break; }
    }
    if ($big_gene_diagram_anchor === '') $big_gene_diagram_anchor = (string)($gene_model['isoforms'][0]['anchor'] ?? '');
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
if (count($children_hierarchical) > 1 && !$big_gene) {   // a big gene's isoform annotations are not loaded
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

// The sequences, ONCE, through the same extractor every sequence page uses -- shown in the
// Sequences card and served by its Download buttons, so the two cannot differ. The card used to
// run its own FASTA reader while Download went through this one; that is how the page once
// showed lowercase (soft-masked) bases that the download did not. Same order as before: IDs
// sorted as sort() does. A big gene shows no sequences, so it extracts only for a download.
$__ids = array_keys($typed_ids);
sort($__ids);
$typed_ids = array_combine($__ids, array_map(fn($i) => $typed_ids[$i], $__ids));
$displayed_content = [];
if (is_dir($gene_set_dir) && (!$big_gene || ($download_file_flag && !empty($sequence_type)))) {
    $displayed_content = extractSequencesForAllTypes($gene_set_dir, $typed_ids, $sequence_types, $organism_name, $genome_accession)['content'];
}

// Handle download request if present (BEFORE rendering page)
if ($download_file_flag && !empty($sequence_type)) {
    if (is_dir($gene_set_dir)) {
        if (!empty($displayed_content) && isset($displayed_content[$sequence_type])) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            handleSequenceDownload($download_file_flag, $sequence_type, $displayed_content[$sequence_type],
                [$organism_name ?? '', $genome_accession ?? '', $gene_set_name ?? '', $feature_uniquename ?? '']);
        }
    }
}


// Expression: "is this gene expressed?" — one word per experiment, read straight from the gene
// set's expression bundles (plain files copied over from the compute box; this page only reads).
// Spec: notes/EXPRESSION_BUNDLE_SPEC.md. No bundles → no section. Gene-set access is already
// settled — this feature was loaded through $accessible_gene_set_ids — and each experiment's own
// access level (with metadata/expression_overrides.json applied) is checked inside.
require_once __DIR__ . '/../lib/expression_functions.php';
$__expr_data = moop_expression_gene_data($organism_name, $genome_accession, $gene_set_name,
                                         $feature_uniquename, get_access_level());
$expression_summary = $__expr_data
    ? moop_expression_summarize($__expr_data['experiments'], $__expr_data['values']) : null;

// "Download TSV" in the Expression card: the same read the card shows, one row per sample.
if ($download_expression && $__expr_data && $__expr_data['values']) {
    require_once __DIR__ . '/../lib/download_filename.php';
    while (ob_get_level()) ob_end_clean();
    $__fn = moop_download_filename('expression',
        [$organism_name, $genome_accession, $gene_set_name, $feature_uniquename], 'tsv');
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . addcslashes($__fn, '"\\') . '"');
    echo moop_expression_gene_tsv($feature_uniquename, $__expr_data['experiments'], $__expr_data['values']);
    exit;
}

// Render page using layout system
echo render_display_page(
    __DIR__ . '/pages/parent.php',
    [
        'organism_name' => $organism_name,
        'feature_id' => $feature_id,
        'feature_uniquename' => $feature_uniquename,
        'description' => $description,
        'gene_symbol' => $gene_symbol,
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
        'all_annotations' => $all_annotations,
        'gene_naming' => $gene_naming,
        'naming_isoform' => $naming_isoform,
        'protein_lengths' => $protein_lengths,
        'longest_protein' => $longest_protein,
        'annotation_total' => $annotation_total,
        'big_gene' => $big_gene,
        'big_gene_diagram_anchor' => $big_gene_diagram_anchor,
        'child_annotation_counts' => $child_annotation_counts,
        'analysis_order' => $analysis_order,
        'annotation_colors' => $annotation_colors,
        'annotation_labels' => $annotation_labels,
        'analysis_desc' => $analysis_desc,
        'retrieve_these_seqs' => $retrieve_these_seqs,
        'gene_name' => $gene_name,
        'available_sequences' => $displayed_content ? formatSequenceResults($displayed_content, $sequence_types) : [],
        'enable_downloads' => true,
        'assembly_name' => $genome_accession,
        'site' => $site,
	'siteTitle' => $siteTitle,
        'annotated_child_types' => $annotated_child_types,
        'gene_model' => $gene_model,
        'feature_loc' => $feature_loc,
        // Neighbour buttons need the gene set's coordinates file (api/gene_neighbor.php reads it).
        'neighbor_nav' => is_file("$gene_set_dir/feature_coords.tsv") ? [
            'organism' => $organism_name, 'assembly' => $genome_accession,
            'gene_set' => $gene_set_name, 'uniquename' => $feature_uniquename,
            'strand'   => (string)($feature_loc['strand'] ?? '.'),
        ] : null,
        'genome_seq_available' => $genome_seq_available,
        'expression_summary' => $expression_summary,
        'page_styles' => ["/$site/css/parent.css", "/$site/css/parent-nav.css"],
        'page_script' => [
            "/$site/js/modules/collapse-handler.js",
            "/$site/js/modules/parent-tools.js",
            "/$site/js/modules/gene-model-viewer.js",
            "/$site/js/modules/sequence-formatter.js",
            "/$site/js/modules/parent-nav.js",
            "/$site/js/modules/isoform-minimap.js",
            "/$site/js/modules/gene-neighbors.js",
            // Loaded here, not left to sequences_display.php: that include only runs when
            // there are sequences to show, so the overview Copy button would be dead on a
            // gene with none.
            "/$site/js/modules/copy-to-clipboard.js"
        ],
        'inline_scripts' => [
            "const geneModelData = " . json_encode($gene_model, JSON_HEX_TAG) . ";",
            // json_encode, not addslashes(): addslashes() leaves `</script>` and line breaks
            // alone, so it is only safe for values that happen never to contain them.
            "const moopOrganism = " . json_encode($organism_name, JSON_HEX_TAG) . ";",
            "const moopAssembly = " . json_encode($genome_accession, JSON_HEX_TAG) . ";",
            "const moopGeneSet = " . json_encode($gene_set_name, JSON_HEX_TAG) . ";",
            "const moopSite = " . json_encode('/' . $site, JSON_HEX_TAG) . ";",
            "const siteTitle = " . json_encode($siteTitle, JSON_HEX_TAG) . ";",
            "const genomeSequenceAvailable = " . ($genome_seq_available ? 'true' : 'false') . ";"
        ]
    ],
    // The tab says what the gene IS first when it has a name: "HIVEP3 · h1SMcG0000013".
    htmlspecialchars(($gene_symbol !== '' ? $gene_symbol . ' · ' : '') . $feature_uniquename)
);
?>
