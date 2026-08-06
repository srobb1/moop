<?php
/**
 * PRIMER BLAST - check primer pairs against a genome and transcriptome.
 *
 * Answers three things for each pair, per notes/PRIMER_BLAST_TOOL_PLAN.md:
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

// The ONLY tuning knob, per the agreed spec. Clamped rather than rejected so a
// stray value never silently becomes something else.
$max_mismatch = (int)($_POST['max_mismatch'] ?? 1);
$max_mismatch = max(0, min(5, $max_mismatch));

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

// The radio in includes/source-list.php posts 'selected_source' directly, so take
// it when present rather than depending on JS to mirror it into hidden
// organism/assembly fields the way tools/blast.php does. It is still only a
// LOOKUP KEY: the filesystem path is resolved below from the access-checked
// $accessible_sources entry, never from anything the client sent.
$posted_source = trim($_POST['selected_source'] ?? '');
if ($posted_source !== '') {
    $selected_source = $posted_source;
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
            $gene_set_path = $source_obj['path'];
            $assembly_path = dirname($gene_set_path);

            $dbs = [];

            // Genome is always searched, in both modes.
            // 20 of 95 sources on this deployment have NO genome — they are
            // transcriptome assemblies, where that is correct rather than missing
            // data. So "the genome is always searched" cannot be an invariant; it
            // is a default that some sources genuinely cannot satisfy.
            $genome_db     = $assembly_path . '/genome.fa';
            $transcript_db = $gene_set_path . '/transcript.nt.fa';
            $has_genome    = PrimerBlast::databaseExists($genome_db);
            $has_transcript = PrimerBlast::databaseExists($transcript_db);

            if ($has_genome) {
                $dbs['genome'] = $genome_db;
            }

            if ($search_mode === 'transcript' && $has_transcript) {
                $dbs['transcript'] = $transcript_db;
            }

            if ($search_mode === 'transcript' && !$has_transcript) {
                $db_notes[] = 'This gene set has no transcriptome index, so cDNA product sizes '
                    . 'could not be calculated.';
            }

            // Say WHICH of the two situations this is. "No BLAST databases are
            // available" named neither the cause nor the fix, and on a
            // transcriptome-only assembly it fired every time, because Genomic DNA
            // is the default PCR input.
            if (!$has_genome) {
                if ($search_mode === 'genome' && $has_transcript) {
                    $search_error = 'This assembly has no genome sequence — it is a transcriptome '
                        . 'assembly. Set PCR input to "cDNA" to check these primers against its '
                        . 'transcripts.';
                } elseif ($search_mode === 'transcript' && $has_transcript) {
                    $db_notes[] = 'This assembly has no genome sequence, so contaminating genomic DNA '
                        . 'could not be checked — only the cDNA products below are reported.';
                } else {
                    $search_error = 'This source has neither a genome nor a transcriptome BLAST index, '
                        . 'so there is nothing to search. Build them from Admin → Organism Checklist.';
                }
            }

            // Report the user's INTENDED product first. With cDNA in the tube the
            // transcriptome result is the answer they came for and the genomic one
            // is the contamination warning beneath it; listing the genome first
            // buries the answer under a caveat.
            if ($search_mode === 'transcript' && isset($dbs['transcript'])) {
                $dbs = ['transcript' => $dbs['transcript']]
                     + (isset($dbs['genome']) ? ['genome' => $dbs['genome']] : []);
            }

            if (empty($dbs) && $search_error === null) {
                $search_error = 'No BLAST databases are available for the selected source.';
            }

            if (!empty($dbs) && $search_error === null) {
                foreach ($parse_result['pairs'] as $i => $pair) {
                    $results[$i] = ['pair' => $pair, 'by_db' => []];
                }

                foreach ($dbs as $label => $db) {
                    $blast = PrimerBlast::run($parse_result['pairs'], $db, [
                        'max_mismatch' => $max_mismatch,
                    ]);

                    if (!$blast['success']) {
                        $db_notes[] = ucfirst($label) . ' search failed: ' . $blast['error'];
                        continue;
                    }

                    $searched_dbs[$label] = basename($db);

                    foreach ($parse_result['pairs'] as $i => $pair) {
                        $found = PrimerPairs::findProducts(
                            $blast['hits'][$i] ?? [],
                            ['max_product' => $max_product]
                        );
                        $found['below_floor']   = $blast['below_floor'];
                        $found['over_mismatch'] = $blast['over_mismatch'];
                        $results[$i]['by_db'][$label] = $found;
                    }
                }
            }
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
        '/' . $site . '/css/display.css',
    ],
];

$display_config = [
    'title'        => 'Primer BLAST',
    'content_file' => __DIR__ . '/pages/primer_blast.php',
    'page_script'  => '/' . $site . '/js/primer-blast.js',
];

include_once __DIR__ . '/display-template.php';
