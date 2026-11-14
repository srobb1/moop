<?php
/**
 * Clade comparative genomics (OrthoFinder runs over MOOP gene sets) for the gene page's
 * "Orthologs in related species" and "Gene family in related species" cards. One read-only file per clade, shipped by compute:
 *
 *     organisms/.clades/<clade>/homologs.sqlite      format clade_homologs/1 or /2
 *
 * /2 adds sequence evidence to every ortholog pair (DIAMOND: support, evalue, bitscore, pident,
 * cov_a, cov_b) and the rule behind it in meta. A /1 file reads as before, with no evidence.
 *
 * Format, and the words the page must use: docs/CLADE_HOMOLOGS_FORMAT.md. In short: the card shows
 * "Orthologs in <clade>" (OrthoFinder pairwise, typed 1:1 / 1:many / many:1 / many:many) and
 * "Gene family in <clade>" (the rest of the N0 HOG, paralogs included) -- never plain "homologs",
 * which on a gene page means the similarity annotations.
 *
 * ACCESS: a gene is listed, or counted, only if the visitor may see ITS OWN gene set
 * (moop_dataset_visible). A clade mixes many gene sets; any of them can be private.
 */

const MOOP_CLADE_FORMATS = ['clade_homologs/1', 'clade_homologs/2'];

/** support values that count as a sequence-supported ortholog; the others (weak, no_hit) do not. */
const MOOP_CLADE_SUPPORTED = ['reciprocal_best', 'co_ortholog'];

/**
 * Is this ortholog backed by sequence? A /2 pair: its support is reciprocal_best or co_ortholog.
 * A /1 pair has no evidence and counts as supported -- the card shows it exactly as before.
 */
function moop_clade_supported(array $ortholog): bool {
    return !isset($ortholog['support']) || in_array($ortholog['support'], MOOP_CLADE_SUPPORTED, true);
}

function moop_clades_dir(): string {
    return rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/.clades';
}

/** Every valid clade file: [clade => PDO]. A file without the expected format is skipped (and logged). */
function moop_clades_open(): array {
    static $open = null;
    if ($open !== null) return $open;
    $open = [];
    foreach (glob(moop_clades_dir() . '/*/homologs.sqlite') ?: [] as $f) {
        $clade = basename(dirname($f));
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $clade)) continue;
        try {
            $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $fmt = $db->query("SELECT value FROM meta WHERE key = 'format'")->fetchColumn();
            if (!in_array($fmt, MOOP_CLADE_FORMATS, true)) { error_log("MOOP: $f is not a known clade format ($fmt) -- skipped"); continue; }
            $open[$clade] = $db;
        } catch (Throwable $e) {
            error_log("MOOP: cannot open $f: " . $e->getMessage());
        }
    }
    return $open;
}

/**
 * The card's data for one gene: one entry per (clade, reference gene) it reaches.
 *   ['clade', 'label', 'method', 'version', 'gene_sets', 'orthogroup', 'family' (N0 HOG id), 'outgroups', 'family_total', 'self_gene',
 *    'via' => null | ['gene', 'gene_set']     (this page's gene set is not the clade's: matched through the crosswalk)
 *    'organisms' => [organism => ['assembly', 'gene_set', 'self', 'orthologs' => [['gene','kind', + evidence],..], 'family' => [gene,..]]],
 *    'evidence'  => null (a /1 file) | ['rule', 'tool', 'version', 'evalue', 'min_cov']
 *   An ortholog from a /2 file also has 'support' (reciprocal_best|co_ortholog|weak|no_hit), 'evalue',
 *   'bitscore', 'pident', 'cov_self', 'cov_other' (coverage of this gene's / the ortholog's protein).
 *    'aliases'   => [organism => [gene => [['gene_set','assembly','gene'], ...]]]]   (its ids in other gene sets)
 *
 * Orthology is computed on ONE gene set per species (the clade's member gene sets). Every other gene
 * set of a species reaches it through that organism's crosswalk (RosettaStone, lib/crosswalk.php) --
 * the same mapping the images and anatomy search use; nothing extra in the clade file.
 * $page_ids: the page's own ids to look up in the crosswalk (the gene and its transcripts).
 * Only genes whose own gene set the visitor may see are listed or counted.
 */
function moop_clade_homologs_for_gene(string $organism, string $gene_set, string $gene, array $page_ids = []): array {
    require_once __DIR__ . '/crosswalk.php';
    $out = [];
    foreach (moop_clades_open() as $clade => $db) {
        $member_gs = $db->prepare('SELECT gene_set FROM member WHERE organism = ?');
        $member_gs->execute([$organism]);
        $ref_set = (string) $member_gs->fetchColumn();
        if ($ref_set === '') continue;                       // this organism is not in the clade
        if ($ref_set === $gene_set) {
            foreach (moop_clade_homologs_in($db, $clade, $organism, $gene, null) as $e) $out[] = $e;
            continue;
        }
        // Another gene set of this species: its reference gene(s) through the crosswalk hub that IS the
        // clade's gene set. No hub, or no match: no card (better than a guess).
        $refs = [];
        foreach (moop_crosswalk_genes($organism, array_merge([$gene], $page_ids)) as $rows) {
            foreach ($rows as $r) if ($r['hub'] === $ref_set) $refs[$r['ref_id']] = true;
        }
        foreach (array_keys($refs) as $ref) {
            foreach (moop_clade_homologs_in($db, $clade, $organism, (string) $ref, ['gene' => (string) $ref, 'gene_set' => $ref_set]) as $e) $out[] = $e;
        }
    }
    foreach ($out as &$e) $e['aliases'] = moop_clade_aliases($e);
    unset($e);
    usort($out, fn($a, $b) => strcmp($a['label'], $b['label']));
    return $out;
}

/**
 * One clade file's entry for one gene OF ITS MEMBER GENE SET (see moop_clade_homologs_for_gene).
 * Separate so it can be tested on a file. $via: set when the page reached this gene through the crosswalk.
 */
function moop_clade_homologs_in(PDO $db, string $clade, string $organism, string $gene, ?array $via): array {
    $members = [];   // organism/gene_set -> assembly
    foreach ($db->query('SELECT organism, assembly, gene_set FROM member') as $m) $members[$m['organism'] . '/' . $m['gene_set']] = $m['assembly'];
    $visible = function (string $org, string $gs) use ($members, $clade): bool {
        $asm = $members["$org/$gs"] ?? null;
        return $asm !== null && moop_dataset_visible($org, $asm, $gs, "clade:$clade");
    };
    $st = $db->prepare('SELECT g.id, g.orthogroup, g.gene, g.gene_set FROM gene g JOIN member m ON m.organism = g.organism AND m.gene_set = g.gene_set
                        WHERE g.gene_lc = ? AND g.organism = ?');
    $st->execute([strtolower($gene), $organism]);
    $me = $st->fetch(PDO::FETCH_ASSOC);
    if (!$me || !$visible($organism, $me['gene_set'])) return [];

    $orgs = [];
    $slot = function (string $org, string $gs) use (&$orgs, $members, $organism) {
        if (!isset($orgs[$org])) $orgs[$org] = ['assembly' => $members["$org/$gs"] ?? '', 'gene_set' => $gs,
                                                'self' => $org === $organism, 'orthologs' => [], 'family' => []];
    };
    $meta = $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
    $v2   = ($meta['format'] ?? '') === 'clade_homologs/2';
    // Orthologs: pairwise, stored both ways, so one indexed read from this gene's side. In a /2 file
    // gene_a is this gene, so cov_a is this gene's protein and cov_b the ortholog's.
    $ev = $v2 ? ', p.support, p.evalue, p.bitscore, p.pident, p.cov_a, p.cov_b' : '';
    $st = $db->prepare("SELECT g.organism, g.gene_set, g.gene, p.kind$ev FROM pair p JOIN gene g ON g.id = p.gene_b
                        WHERE p.gene_a = ? ORDER BY (p.kind = '1:1') DESC, g.gene");
    $st->execute([$me['id']]);
    $listed = [];
    foreach ($st as $r) {
        if (!$visible($r['organism'], $r['gene_set'])) continue;
        $slot($r['organism'], $r['gene_set']);
        $o = ['gene' => $r['gene'], 'kind' => $r['kind']];
        if ($v2) $o += ['support' => (string) $r['support'], 'evalue' => $r['evalue'], 'bitscore' => $r['bitscore'],
                        'pident' => $r['pident'], 'cov_self' => $r['cov_a'], 'cov_other' => $r['cov_b']];
        $orgs[$r['organism']]['orthologs'][] = $o;
        $listed[$r['organism'] . "\0" . $r['gene']] = true;
    }
    // Gene family: everything else in this gene's whole-clade HOG (N0), paralogs included.
    $st = $db->prepare("SELECT g.organism, g.gene_set, g.gene FROM hog h0
                        JOIN hog h ON h.hog = h0.hog AND h.node = 'N0' JOIN gene g ON g.id = h.gene_id
                        WHERE h0.gene_id = ? AND h0.node = 'N0' AND g.id != ? ORDER BY g.organism, g.gene");
    $st->execute([$me['id'], $me['id']]);
    // The family's NAME is this N0 HOG, not the orthogroup: with an outgroup in the run (compute,
    // 2026-11-13) one orthogroup spans the outgroup and can hold several N0 HOGs, so the orthogroup
    // would name a bigger group than the one listed. Orthogroup only when the gene has no HOG.
    $hq = $db->prepare("SELECT hog FROM hog WHERE gene_id = ? AND node = 'N0' LIMIT 1");
    $hq->execute([$me['id']]);
    $family_id = (string) ($hq->fetchColumn() ?: $me['orthogroup']);
    $family_total = 0;
    foreach ($st as $r) {
        if (isset($listed[$r['organism'] . "\0" . $r['gene']]) || !$visible($r['organism'], $r['gene_set'])) continue;
        $slot($r['organism'], $r['gene_set']);
        $orgs[$r['organism']]['family'][] = $r['gene'];
        $family_total++;
    }
    if (!$orgs) return [];
    uksort($orgs, fn($a, $b) => [$a !== $organism, $a] <=> [$b !== $organism, $b]);
    return [[
        'clade'        => $clade,
        'label'        => (string) ($meta['label'] ?? $clade),
        'method'       => (string) ($meta['method'] ?? 'OrthoFinder'),
        'version'      => (string) ($meta['method_version'] ?? $meta['orthofinder_version'] ?? ''),
        'gene_sets'    => count($members),
        'orthogroup'   => (string) $me['orthogroup'],
        'family'       => $family_id,
        'outgroups'    => (string) ($meta['outgroups'] ?? ''),
        'via'          => $via,
        'self_gene'    => (string) $me['gene'],
        'self_set'     => (string) $me['gene_set'],
        'self_assembly'=> (string) ($members[$organism . '/' . $me['gene_set']] ?? ''),
        'organism'     => $organism,
        'organisms'    => $orgs,
        'aliases'      => [],
        'family_total' => $family_total,
        'evidence'     => $v2 ? ['rule' => (string) ($meta['evidence_rule'] ?? ''), 'tool' => (string) ($meta['evidence_tool'] ?? 'DIAMOND'),
                                 'version' => (string) ($meta['evidence_version'] ?? ''), 'evalue' => (string) ($meta['evidence_evalue'] ?? ''),
                                 'min_cov' => (string) ($meta['evidence_min_cov'] ?? '')] : null,
    ]];
}

/**
 * Each listed gene's ids in the OTHER gene sets of its species, from that organism's crosswalk:
 * [organism => [gene => [['gene_set', 'assembly', 'gene'], ...]]]. Only crosswalk sources that are
 * gene sets on this site (configured with gene_set + assembly) and visible to the visitor.
 */
function moop_clade_aliases(array $entry): array {
    $want = [];
    foreach ($entry['organisms'] as $org => $o) {
        $want[$org] = ['set' => $o['gene_set'], 'genes' => array_merge(array_column($o['orthologs'], 'gene'), array_slice($o['family'], 0, 200))];
    }
    // The page's own gene too -- its species may have no row (no paralogs), so never infer it from the rows.
    $self_org = $entry['organism'];
    $want[$self_org] = ['set' => $entry['self_set'], 'genes' => array_merge($want[$self_org]['genes'] ?? [], [$entry['self_gene']])];
    $out = [];
    foreach ($want as $org => $w) {
        if ($org === '' || !$w['genes'] || !moop_crosswalk_open($org)) continue;
        $sources = moop_crosswalk_sources()[$org]['sources'] ?? [];
        foreach (moop_crosswalk_members($org, $w['genes']) as $m) {
            if ($m['hub'] !== $w['set']) continue;                          // mapped through this clade's gene set
            $src = $sources[$m['source']] ?? null;
            if (!$src || empty($src['gene_set']) || $src['gene_set'] === $w['set']) continue;   // a gene set on this site, not the same one
            if (!moop_dataset_visible($org, (string) $src['assembly'], (string) $src['gene_set'])) continue;
            $out[$org][$m['ref_id']][] = ['gene_set' => (string) $src['gene_set'], 'assembly' => (string) $src['assembly'], 'gene' => $m['accession']];
        }
    }
    return $out;
}

/**
 * Names and descriptions for the genes the card lists, from each organism's own database:
 * [organism => [gene => ['name' => ..., 'description' => ...]]]. One query per organism.
 * The card names the first 200 family members of each organism; the download ($family_cap null) all.
 */
function moop_clade_gene_labels(array $clades, ?int $family_cap = 200): array {
    $want = [];
    foreach ($clades as $c) foreach ($c['organisms'] as $org => $o) {
        foreach ($o['orthologs'] as $g) $want[$org][$g['gene']] = true;
        foreach (array_slice($o['family'], 0, $family_cap) as $g) $want[$org][$g] = true;
    }
    $out = [];
    foreach ($want as $org => $genes) {
        if ($l = moop_gene_labels($org, array_keys($genes))) $out[$org] = $l;
    }
    return $out;
}

/**
 * Names and descriptions of some genes of one organism, from its own database:
 * [gene => ['name' => ..., 'description' => ...]]; genes it does not have are left out.
 */
function moop_gene_labels(string $organism, array $genes): array {
    $f = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . "/$organism/organism.sqlite";
    if (!$genes || !is_file($f)) return [];
    $out = [];
    try {
        $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        foreach (array_chunk(array_values(array_unique(array_map('strval', $genes))), 400) as $chunk) {
            $st = $db->prepare('SELECT feature_uniquename, feature_name, feature_description FROM feature WHERE feature_uniquename IN ('
                               . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $st->execute($chunk);
            foreach ($st as $r) $out[$r['feature_uniquename']] = ['name' => (string) $r['feature_name'], 'description' => (string) $r['feature_description']];
        }
    } catch (Throwable $e) {
        error_log("MOOP: gene labels for $organism: " . $e->getMessage());
    }
    return $out;
}

/**
 * Is $label a real gene symbol / description, rather than an id? Many gene sets name an unnamed
 * gene by its id (LOC107373014, NY_Scal100_v1.14367.1): showing that beside the id says nothing.
 */
function moop_clade_has_symbol(string $label, string $gene): bool {
    $label = trim($label);
    if ($label === '' || strcasecmp($label, $gene) === 0 || stripos($label, $gene) !== false || stripos($gene, $label) !== false) return false;
    return !preg_match('/^(LOC\d+|NY_[A-Za-z0-9_.]+|[A-Za-z]{1,6}[_.]?\d{5,}[0-9._]*)$/', $label);
}

/**
 * A gene page card as a TSV, one row per PAIR: gene1 (this gene, as the clade has it -- the Rosetta
 * Stone match when the page's gene set is not the clade's), then gene2, each fully described, then
 * what the pair is.
 *   $part 'orthologs' -- "Orthologs in related species": its orthologs, with kind and (a /2 file)
 *                        the sequence evidence: support, evalue, bitscore, pident, both coverages.
 *   $part 'family'    -- "Gene family in related species": paralogs and the other family members,
 *                        the WHOLE family (the card names the first 200). No evidence: not pairs.
 * $section is the page's compgen_section (moop_clade_homologs_for_gene() + its gene).
 */
function moop_clade_homologs_tsv(array $section, string $part = 'orthologs'): string {
    $orth  = $part !== 'family';
    $cell  = fn($s) => str_replace(["\t", "\r", "\n"], ' ', (string) $s);
    $cols  = ['organism', 'assembly', 'gene_set', 'id', 'name', 'description'];
    $out   = implode("\t", array_merge(array_map(fn($c) => "gene1_$c", $cols), array_map(fn($c) => "gene2_$c", $cols),
                                     $orth ? ['relationship', 'kind', 'support', 'evalue', 'bitscore', 'pident', 'gene1_coverage', 'gene2_coverage',
                                              'gene_family', 'clade', 'method']
                                           : ['relationship', 'gene_family', 'clade', 'method'])) . "\n";
    // Labels for every listed gene, plus each clade's own gene (gene1 -- not in its organism lists).
    $labels = moop_clade_gene_labels($section['clades'], null);
    foreach ($section['clades'] as $c) $labels[$c['organism']] = ($labels[$c['organism']] ?? []) + moop_gene_labels($c['organism'], [$c['self_gene']]);
    $describe = function (string $org, string $asm, string $gs, string $gene) use ($labels): array {
        $l    = $labels[$org][$gene] ?? ['name' => '', 'description' => ''];
        $desc = trim(preg_replace('/\s*\[[^\]]*\]\s*$/', '', $l['description']));   // drop the "[ISO|1to1|...]" evidence tag
        if ($desc !== '' && !moop_clade_has_symbol($desc, $gene)) $desc = '';      // only an id, as on the card
        return [$org, $asm, $gs, $gene, moop_clade_has_symbol($l['name'], $gene) ? $l['name'] : '', $desc];
    };
    foreach ($section['clades'] as $c) {
        $g1     = $describe($c['organism'], $c['self_assembly'] ?? '', $c['self_set'], $c['self_gene']);
        $method = trim($c['method'] . ' ' . $c['version']);
        foreach ($c['organisms'] as $org => $o) {
            $rows = [];
            // "1:1" -> "1-to-1": Excel reads 1:1 as a time and shows 1:01 (Sofia, 2026-11-12).
            // Evidence (a /2 file): support, evalue, bitscore, pident, coverage of gene1's and gene2's protein.
            $ev = fn(array $g) => isset($g['support']) ? [$g['support'], $g['evalue'], $g['bitscore'], $g['pident'], $g['cov_self'], $g['cov_other']] : ['', '', '', '', '', ''];
            if ($orth && !$o['self']) foreach ($o['orthologs'] as $g) $rows[] = ['ortholog', $g['gene'], [str_replace(':', '-to-', $g['kind'])], $ev($g)];
            if (!$orth) foreach ($o['family'] as $g) $rows[] = [$o['self'] ? 'paralog' : 'family member (not ortholog)', $g, [], []];
            foreach ($rows as [$rel, $gene, $kind, $evidence]) {
                $out .= implode("\t", array_map($cell, array_merge($g1, $describe($org, $o['assembly'], $o['gene_set'], $gene),
                        [$rel], $kind, $evidence, [$c['family'] ?? $c['orthogroup'], $c['label'], $method]))) . "\n";
            }
        }
    }
    return $out;
}
