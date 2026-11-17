<?php
/**
 * Which gene does this ID mean, for this visitor? The ONE answer, shared by the gene page
 * (tools/parent.php) and the gene record (api/v1/gene.php) -- so the access rules and the choice of
 * gene set cannot drift between what a person sees and what an agent downloads. Moved out of
 * tools/parent.php on 2026-11-17, unchanged; the gene page rendered byte-identical before and after.
 *
 * moop_gene_lookup() prints nothing and never exits; the caller turns the status into a page or JSON:
 *
 *   'no_organism'  no such organism, or not one this visitor may open (the SAME answer: a link must
 *                  not reveal that a restricted organism exists)
 *   'no_gene_sets' the organism has no gene set this visitor may open
 *   'not_found'    the ID is not in any gene set the visitor may open (or not in the one named)
 *   'choose'       the ID is in more than one of them, and no assembly / gene_set narrowed it --
 *                  asked about, never guessed ('choices': assembly_label, assembly, gene_set, url)
 *   'ok'           'row' (getFeatureById(): the gene -- the highest ancestor whose type is a page
 *                  type, organism.json feature_types.parents, default gene / pseudogene),
 *                  'gene_set_ids' (the one gene set), 'accessible_sources', 'ancestor_uniquename'
 *
 * Every status but 'no_organism' also carries 'label' ("Genus species"), for a not-found message.
 */

/**
 * @param string $assembly  optional: the genome accession or the assembly's directory name
 * @param string $gene_set  optional: the gene set name
 */
function moop_gene_lookup(string $organism, string $id, string $assembly = '', string $gene_set = ''): array {
    $organism_data = ConfigManager::getInstance()->getPath('organism_data');
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $organism) || str_contains($organism, '..')) return ['status' => 'no_organism'];
    $info = loadOrganismInfo($organism, $organism_data);
    if (!$info || (!is_public_organism($organism) && !has_access('COLLABORATOR', $organism))
        || !is_file("$organism_data/$organism/organism.sqlite")) {
        return ['status' => 'no_organism'];
    }
    $db    = "$organism_data/$organism/organism.sqlite";
    $label = trim(($info['genus'] ?? '') . ' ' . ($info['species'] ?? '')) ?: str_replace('_', ' ', $organism);

    // The gene sets this visitor may open, for permission-based DB filtering.
    $accessible_sources = flattenSourcesList(getAccessibleGeneSets($organism));
    $gene_set_ids = array_values(array_filter(array_column($accessible_sources, 'gene_set_id')));
    if (!$gene_set_ids) return ['status' => 'no_gene_sets', 'label' => $label];

    // Which gene set. A link may name one (assembly + gene_set: search results, BLAST, the home
    // page ID search); without it, an ID found in more than one gene set the viewer can open is
    // ASKED about, never guessed -- before 2026-10-20 the page silently took whichever row came
    // first and ignored the parameters the links already sent.
    if ($assembly !== '' || $gene_set !== '') {
        $scoped = array_filter($accessible_sources, fn($src) =>
            // the accession, or the assembly's directory name (they differ for a few assemblies)
            ($assembly === '' || in_array($assembly, [$src['genome_accession'] ?? null, $src['assembly'] ?? null], true))
            && ($gene_set === '' || ($src['gene_set'] ?? '') === $gene_set));
        $gene_set_ids = array_values(array_unique(array_filter(array_column($scoped, 'gene_set_id'))));
    }
    $holding = moop_feature_gene_set_ids($id, $db, $gene_set_ids);
    if (!$holding) {
        // Not in any gene set the viewer may open (or not in the one named): the same answer
        // either way, so a link cannot be used to learn what a restricted gene set holds.
        return ['status' => 'not_found', 'label' => $label];
    }
    if (count($holding) > 1) {
        $choices = [];
        foreach ($accessible_sources as $src) {
            if (!in_array((int)($src['gene_set_id'] ?? 0), $holding, true)) continue;
            $accession = $src['genome_accession'] ?? $src['assembly'];
            $choices["$accession/{$src['gene_set']}"] = [
                'assembly_label' => assembly_label($src['genome_name'] ?? '', $accession),
                'assembly'       => $accession,
                'gene_set'       => $src['gene_set'],
                'url'            => moop_url_base() . "/tools/parent.php?organism=" . urlencode($organism)
                                  . '&uniquename=' . urlencode($id)
                                  . '&assembly=' . urlencode($accession) . '&gene_set=' . urlencode($src['gene_set']),
            ];
        }
        return ['status' => 'choose', 'label' => $label, 'choices' => array_values($choices)];
    }
    $gene_set_ids = $holding;   // the one gene set; everything below reads only it

    // The gene: walk up from the ID to the highest ancestor whose type gets a page.
    $parents = !empty($info['feature_types']['parents']) ? $info['feature_types']['parents'] : ['gene', 'pseudogene'];
    $ancestors = getAncestors($id, $db, $gene_set_ids);
    $ancestor_id = '';
    $ancestor_uniquename = '';
    foreach ($ancestors as $ancestor) {
        $ancestor_id         = $ancestor['feature_id'];
        $ancestor_uniquename = $ancestor['feature_uniquename'];
        if (in_array($ancestor['feature_type'], $parents)) break;   // reached a page type
    }

    $row = getFeatureById($ancestor_id, $db, $gene_set_ids);
    if (empty($row)) return ['status' => 'not_found', 'label' => $label];

    return [
        'status'              => 'ok',
        'label'               => $label,
        'row'                 => $row,
        'gene_set_ids'        => $gene_set_ids,
        'accessible_sources'  => $accessible_sources,
        'ancestor_uniquename' => $ancestor_uniquename,
    ];
}
