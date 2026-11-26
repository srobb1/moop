<?php
/**
 * PRIMER BLAST - check primer pairs against a genome and transcriptome.
 *
 * Answers three things for each pair, per notes/archive/PRIMER_BLAST_TOOL_PLAN.md:
 *   - how many products would form
 *   - where each one lands
 *   - how big each one is, and with how many mismatches
 *
 * Named primer_blast (not "primers") because a primer_maker page will sit
 * alongside it later; both share the engine in lib/primer/.
 *
 * ========== DATA FLOW ==========
 *
 * Browser Request → this file (controller)
 *   ↓  parse primers (paste or upload) → PrimerInput
 *   ↓  resolve BLAST databases SERVER-SIDE from the access-checked source
 *   ↓  PrimerBlast (genome, and transcriptome if a gene set was chosen)
 *   ↓  PrimerPairs → products
 *   ↓  display-template.php → layout.php → pages/primer_blast.php
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/primer/PrimerInput.php';
include_once __DIR__ . '/../lib/primer/PrimerBlast.php';
include_once __DIR__ . '/../lib/primer/PrimerPairs.php';
include_once __DIR__ . '/../lib/primer/ExonMap.php';
include_once __DIR__ . '/../lib/primer/PrimerBlastRun.php';   // search + verdict, shared with the API
include_once __DIR__ . '/../lib/genomic_regions.php';   // moop_genome_kind()
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../includes/source-selector-helpers.php';

$organism_data = $config->getPath('organism_data');
$siteTitle     = $config->getString('siteTitle');

$context      = parseContextParameters();
$display_name = $context['display_name'];

$sources_by_group   = getAccessibleGeneSets();
$accessible_sources = flattenSourcesList($sources_by_group);

$organisms_param         = $_GET['organisms'] ?? $_POST['organisms'] ?? '';
$organism_result         = parseOrganismParameter($organisms_param, '');
$filter_organisms_string = $organism_result['string'] ?? '';

// ---------------------------------------------------------------- form input
$primer_text = trim($_POST['primers'] ?? '');

// NO-JAVASCRIPT FALLBACK ONLY.
//
// With JS on, js/primer-blast.js reads a chosen file straight into the textarea and
// then CLEARS the file input, so the box is the single source of truth and no file
// ever reaches this branch. That is deliberate: the user must be able to see and
// edit exactly what will be searched.
//
// Without JS the file is posted, and it wins -- picking a file is the more recent
// explicit action, and the textarea may still hold text from a previous submit.
$upload_error = null;
if (!empty($_FILES['primer_file']['tmp_name']) && is_uploaded_file($_FILES['primer_file']['tmp_name'])) {
    if ($_FILES['primer_file']['size'] > 1048576) {
        $upload_error = 'Uploaded file is larger than 1 MB. Paste the primers instead, or split the file.';
    } else {
        $contents = file_get_contents($_FILES['primer_file']['tmp_name']);
        if ($contents === false) {
            $upload_error = 'Could not read the uploaded file.';
        } else {
            $primer_text = trim($contents);
        }
    }
}

// "Mismatches listed" -- what is LISTED, not what is searched (2026-10-13). The search
// always keeps hits up to PrimerBlast::SEARCH_MAX_MISMATCH so the verdict sees every site
// that could amplify; this only trims the table. Clamped rather than rejected so a stray
// value never silently becomes something else.
$max_mismatch = (int)($_POST['max_mismatch'] ?? PrimerBlast::DISPLAY_MISMATCH_DEFAULT);
$max_mismatch = max(0, min(PrimerBlast::SEARCH_MAX_MISMATCH, $max_mismatch));

$max_product = (int)($_POST['max_product'] ?? PrimerPairs::MAX_PRODUCT_DEFAULT);
$max_product = max(100, min(1000000, $max_product));

// 'genome' or 'transcript'. Transcript ALWAYS runs the genome search too: a
// transcript-only check cannot see genomic sites that would still amplify under
// standard PCR, so transcript-only is deliberately not offered.
$search_mode = ($_POST['search_mode'] ?? 'genome') === 'transcript' ? 'transcript' : 'genome';

$assembly_param = trim($_POST['assembly'] ?? $_GET['assembly'] ?? '');
if (!empty($context['organism']) && !empty($context['assembly'])) {
    $assembly_param = $context['assembly'];
}

$selected_organism = trim($_POST['organism'] ?? $_GET['organism'] ?? '');
$source_selection  = prepareSourceSelection(
    $context,
    $sources_by_group,
    $accessible_sources,
    $selected_organism,
    $assembly_param,
    $organism_result['organisms']
);

$filter_organisms            = $source_selection['filter_organisms'];
$selected_source             = $source_selection['selected_source'];
$selected_organism           = $source_selection['selected_organism'];
$selected_assembly_accession = $source_selection['selected_assembly_accession'];
$selected_assembly_name      = $source_selection['selected_assembly_name'];

$context_organism = $context['organism'];
$context_assembly = $context['assembly'];
$context_gene_set = $context['gene_set'];
$context_group    = $context['group'];

// The selector's radio (includes/scope_selector.php) posts 'selected_source' directly, so take
// it when present rather than depending on JS to mirror it into hidden
// organism/assembly fields the way tools/blast.php does. It is still only a
// LOOKUP KEY: the filesystem path is resolved below from the access-checked
// $accessible_sources entry, never from anything the client sent.
$posted_source = trim($_POST['selected_source'] ?? '');
if ($posted_source !== '') {
    $selected_source = $posted_source;
}

// ------------------------------------------------- which sources are usable here
//
// Computed for the source list so a dead end is visible BEFORE it is chosen, not
// only after a search fails. This tool needs the assembly's GENOME index; a
// transcriptome assembly legitimately has none, and 20 of the sources on this
// deployment are in that state.
//
// One stat per source; the list is ~95 entries, so this is cheap enough to run
// per page load, unlike the scans that CLAUDE.md §10 pushes into housekeeping.
// The organism list: the shared selector in single mode. Opened directly it starts with nothing
// chosen, as this tool always has; a finished run keeps its gene set (moop_scope_single_state()).
require_once __DIR__ . '/../lib/scope_selector.php';
$scope       = moop_scope_selector_data();
$scope_state = moop_scope_single_state($scope, $selected_source, false);

$source_availability = [];
foreach ($accessible_sources as $source) {
    $key = $source['organism'] . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
    $asm = dirname($source['path']);

    // A 'transcripts' assembly HAS a genome.fa -- one assembled transcript per gene standing in
    // for a genome -- so a file check alone called it a genome, and "Genomic DNA" mode reported
    // spliced cDNA hits as genomic products: a pair across an exon junction read as amplifying
    // genomic DNA, where an intron really sits between the primers (2026-11-03, 21 assemblies).
    $is_transcripts = moop_genome_kind($asm) === 'transcripts';
    if (PrimerBlast::databaseExists($asm . '/genome.fa') && !$is_transcripts) {
        $source_availability[$key] = ['ok' => true, 'reason' => '', 'usable_mode' => ''];
    } elseif ($is_transcripts && PrimerBlast::databaseExists($source['path'] . '/transcript.nt.fa')) {
        $source_availability[$key] = [
            'ok'          => false,
            'reason'      => 'Transcripts only, not a genome. Usable with PCR input set to cDNA.',
            'usable_mode' => 'transcript',
        ];
    } elseif (PrimerBlast::databaseExists($source['path'] . '/transcript.nt.fa')) {
        $source_availability[$key] = [
            'ok'          => false,
            'reason'      => 'No genome — transcriptome assembly. Usable with PCR input set to cDNA.',
            // Still usable, just not for the genomic question.
            'usable_mode' => 'transcript',
        ];
    } else {
        $source_availability[$key] = [
            'ok'          => false,
            'reason'      => 'No genome or transcriptome BLAST index — nothing to search.',
            // Nothing works here, so the page must NOT steer the user to cDNA as
            // though it would: that would trade one dead end for another.
            'usable_mode' => '',
        ];
    }
}

// ------------------------------------------------------------------- results
$search_error   = $upload_error;
$parse_result   = null;
$results        = [];      // one entry per pair
$searched_dbs   = [];      // label => true, for the results header
$db_notes       = [];

if ($search_error === null && $primer_text !== '' && !empty($selected_source)) {
    $parse_result = PrimerInput::parse($primer_text);

    if (empty($parse_result['pairs'])) {
        $search_error = 'No usable primer pairs were found in the input.';
    } else {
        // Resolve the source SERVER-SIDE. The client posts organism|assembly|gene_set;
        // it never supplies a path. Same rule as tools/blast.php -- a posted path
        // would let one assembly's databases be searched while the page reports another.
        $source_parts      = explode('|', $selected_source);
        $sel_organism      = $source_parts[0] ?? '';
        $sel_assembly      = $source_parts[1] ?? '';
        $sel_gene_set      = $source_parts[2] ?? '';
        $selected_gene_set = $sel_gene_set;

        $source_obj = null;
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $sel_organism &&
                $source['assembly'] === $sel_assembly &&
                ($source['gene_set'] ?? '') === $sel_gene_set) {
                $source_obj = $source;
                break;
            }
        }

        if (!$source_obj) {
            $search_error = 'You do not have access to the selected assembly.';
        } else {
            // These drive the JBrowse links on genomic products. They must come
            // from the POSTED source, not from prepareSourceSelection(), which
            // never sees the radio (it reads organism/assembly hidden fields that
            // only tools/blast.php's JS populates).
            $result_organism = $sel_organism;
            $result_assembly = $sel_assembly;

            // The search itself is lib/primer/PrimerBlastRun.php, shared with api/v1/primer_blast.php.
            $check        = primer_blast_check($parse_result['pairs'], $source_obj, $search_mode, $max_product);
            $results      = $check['results'];
            $searched_dbs = $check['searched_dbs'];
            $db_notes     = $check['db_notes'];
            $search_error = $check['error'];
        }
    }
}

// -------------------------------------------------------------------- render
$data = [
    'site'                        => $site,
    'siteTitle'                   => $siteTitle,
    'config'                      => $config,
    'accessible_sources'          => $accessible_sources,
    'sources_by_group'            => $sources_by_group,
    'context_organism'            => $context_organism,
    'context_assembly'            => $context_assembly,
    'context_gene_set'            => $context_gene_set,
    'context_group'               => $context_group,
    'selected_gene_set'           => $selected_gene_set ?? '',
    'display_name'                => $display_name,
    'filter_organisms'            => $filter_organisms,
    'filter_organisms_string'     => $filter_organisms_string,
    'selected_source'             => $selected_source,
    'source_availability'         => $source_availability,
    'scope'                       => $scope,
    'scope_state'                 => $scope_state,
    'result_organism'             => $result_organism ?? '',
    'result_assembly'             => $result_assembly ?? '',
    'selected_organism'           => $selected_organism,
    'selected_assembly_name'      => $selected_assembly_name,
    'selected_assembly_accession' => $selected_assembly_accession,

    'primer_text'                 => $primer_text,
    'max_mismatch'                => $max_mismatch,
    'max_product'                 => $max_product,
    'search_mode'                 => $search_mode,

    'parse_result'                => $parse_result,
    'results'                     => $results,
    'searched_dbs'                => $searched_dbs,
    'db_notes'                    => $db_notes,
    'search_error'                => $search_error,

    'page_styles'                 => [
        moop_url_base() . '/css/display.css',
    ],
];

$display_config = [
    'title'        => 'Primer BLAST',
    'content_file' => __DIR__ . '/pages/primer_blast.php',
    'page_script'  => [moop_url_base() . '/js/modules/scope-selector.js',
                       moop_url_base() . '/js/primer-blast.js'],
];

include_once __DIR__ . '/display-template.php';
