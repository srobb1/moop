<?php
/**
 * Image gallery: any slice of an organism's gene images (lib/gene_images.php), grouped by stage.
 *
 *   tools/image_gallery.php?organism=Schmidtea_mediterranea&analysis=davies_2017[&kind=movie,panel]
 *                          [&gene=h1SMcG0013999][&term=PLANA:0000023][&stage=PLANA:0000003]
 *
 * Where movies and panels live: a gene page shows a gene's still images and links here for its movies
 * and panels (MOOP_IMAGE_GALLERY_ONLY), filtered to the gene; Planosphere's Embryogenesis pages link
 * to the whole analysis. A gene filter finds the gene's images under any of its ids (the crosswalk), as
 * the gene page does; a term filter includes the term's parts and subtypes.
 *
 * Only images from data sets the visitor may see (moop_dataset_visible); files are served by
 * api/gene_image.php with the same check. Read-only. Docs: docs/ORGANISM_SIDE_FILES.md §2.
 */
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/gene_images.php';
require_once __DIR__ . '/../lib/crosswalk.php';

$organism = (string) ($_GET['organism'] ?? '');
if (!preg_match('/^[A-Za-z0-9_.-]+$/', $organism)) $organism = '';
$analysis = (string) ($_GET['analysis'] ?? '');
$kinds    = array_values(array_intersect(explode(',', (string) ($_GET['kind'] ?? '')), MOOP_IMAGE_KINDS));
$gene     = trim((string) ($_GET['gene'] ?? ''));
$term     = moop_ontology_normalise_id((string) ($_GET['term'] ?? ''));
$stage    = moop_ontology_normalise_id((string) ($_GET['stage'] ?? ''));
$LIMIT    = 1000;

$analyses = $organism !== '' ? moop_images_analyses($organism) : [];
if ($analysis !== '' && !isset($analyses[$analysis])) $analysis = '';

$images = [];
if ($organism !== '') {
    if ($gene !== '') {
        // The gene under every id it has: itself, the reference gene(s) it maps to, and every accession
        // mapped to those -- the same reach as the gene page's Images card.
        $refs = [];
        foreach (moop_crosswalk_genes($organism, [$gene]) as $rows) $refs = array_merge($refs, array_column($rows, 'ref_id'));
        $ids = array_merge([$gene], $refs, array_column(moop_crosswalk_members($organism, array_merge([$gene], $refs)), 'accession'));
        $images = moop_images_for_accessions($organism, $ids, $LIMIT);
    } elseif ($term !== '') {
        $rels = array_keys(moop_ontology_relations('anatomy'));
        $images = moop_images_for_terms($organism, moop_ontology_expand_in('anatomy', $term, $rels), $LIMIT);
    } elseif ($analysis !== '') {
        $images = moop_images_for_analysis($organism, $analysis, $kinds, $LIMIT);
    }
    $images = array_values(array_filter($images, fn($i) =>
        ($analysis === '' || $i['analysis'] === $analysis)
        && (!$kinds || in_array($i['kind'], $kinds, true))
        && ($stage === '' || $i['stage_id'] === $stage || in_array($stage, $i['terms'], true))));
}

// Labels: stages, terms, and the filter term.
$ids = [$term, $stage];
foreach ($images as $i) { $ids[] = $i['stage_id']; $ids = array_merge($ids, $i['terms']); }
$labels = moop_ontology_labels(array_values(array_unique(array_filter($ids))), moop_ontology_by_purpose('anatomy'));

// Group by stage (no stage last), in stage-id order (PLANA Stage 2 < Stage 3 ...).
$by_stage = [];
foreach ($images as $i) $by_stage[(string) $i['stage_id']][] = $i;
uksort($by_stage, fn($a, $b) => [$a === '', $a] <=> [$b === '', $b]);

$tool_title = $analysis !== '' ? $analyses[$analysis]['title'] : 'Image gallery';
echo render_display_page(__DIR__ . '/pages/image_gallery.php', [
    'organism' => $organism, 'analysis' => $analysis, 'analyses' => $analyses, 'kinds' => $kinds,
    'gene' => $gene, 'term' => $term, 'stage' => $stage, 'labels' => $labels,
    'by_stage' => $by_stage, 'total' => count($images), 'capped' => count($images) >= $LIMIT,
    'page_styles' => [moop_url_base() . '/css/parent.css'],
    'page_script' => [moop_url_base() . '/js/modules/gene-images.js'],
], $tool_title);
