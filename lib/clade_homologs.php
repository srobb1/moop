<?php
/**
 * Clade comparative genomics (OrthoFinder runs over MOOP gene sets) for the gene page's
 * "Comparative genomics" card. One read-only file per clade, shipped by compute:
 *
 *     organisms/.clades/<clade>/homologs.sqlite      format clade_homologs/1
 *
 * Format, and the words the page must use: docs/CLADE_HOMOLOGS_FORMAT.md. In short: the card shows
 * "Orthologs in <clade>" (OrthoFinder pairwise, typed 1:1 / 1:many / many:1 / many:many) and
 * "Gene family in <clade>" (the rest of the N0 HOG, paralogs included) -- never plain "homologs",
 * which on a gene page means the similarity annotations.
 *
 * ACCESS: a gene is listed, or counted, only if the visitor may see ITS OWN gene set
 * (moop_dataset_visible). A clade mixes many gene sets; any of them can be private.
 */

const MOOP_CLADE_FORMATS = ['clade_homologs/1'];

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
 *   ['clade', 'label', 'method', 'version', 'gene_sets', 'orthogroup', 'family_total', 'self_gene',
 *    'via' => null | ['gene', 'gene_set']     (this page's gene set is not the clade's: matched through the crosswalk)
 *    'organisms' => [organism => ['assembly', 'gene_set', 'self', 'orthologs' => [['gene','kind'],..], 'family' => [gene,..]]],
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
    $visible = function (string $org, string $gs) use ($members): bool {
        $asm = $members["$org/$gs"] ?? null;
        return $asm !== null && moop_dataset_visible($org, $asm, $gs);
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
    // Orthologs: pairwise, stored both ways, so one indexed read from this gene's side.
    $st = $db->prepare("SELECT g.organism, g.gene_set, g.gene, p.kind FROM pair p JOIN gene g ON g.id = p.gene_b
                        WHERE p.gene_a = ? ORDER BY (p.kind = '1:1') DESC, g.gene");
    $st->execute([$me['id']]);
    $listed = [];
    foreach ($st as $r) {
        if (!$visible($r['organism'], $r['gene_set'])) continue;
        $slot($r['organism'], $r['gene_set']);
        $orgs[$r['organism']]['orthologs'][] = ['gene' => $r['gene'], 'kind' => $r['kind']];
        $listed[$r['organism'] . "\0" . $r['gene']] = true;
    }
    // Gene family: everything else in this gene's whole-clade HOG (N0), paralogs included.
    $st = $db->prepare("SELECT g.organism, g.gene_set, g.gene FROM hog h0
                        JOIN hog h ON h.hog = h0.hog AND h.node = 'N0' JOIN gene g ON g.id = h.gene_id
                        WHERE h0.gene_id = ? AND h0.node = 'N0' AND g.id != ? ORDER BY g.organism, g.gene");
    $st->execute([$me['id'], $me['id']]);
    $family_total = 0;
    foreach ($st as $r) {
        if (isset($listed[$r['organism'] . "\0" . $r['gene']]) || !$visible($r['organism'], $r['gene_set'])) continue;
        $slot($r['organism'], $r['gene_set']);
        $orgs[$r['organism']]['family'][] = $r['gene'];
        $family_total++;
    }
    if (!$orgs) return [];
    uksort($orgs, fn($a, $b) => [$a !== $organism, $a] <=> [$b !== $organism, $b]);
    $meta = $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
    return [[
        'clade'        => $clade,
        'label'        => (string) ($meta['label'] ?? $clade),
        'method'       => (string) ($meta['method'] ?? 'OrthoFinder'),
        'version'      => (string) ($meta['method_version'] ?? $meta['orthofinder_version'] ?? ''),
        'gene_sets'    => count($members),
        'orthogroup'   => (string) $me['orthogroup'],
        'via'          => $via,
        'self_gene'    => (string) $me['gene'],
        'self_set'     => (string) $me['gene_set'],
        'organism'     => $organism,
        'organisms'    => $orgs,
        'aliases'      => [],
        'family_total' => $family_total,
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
    $base = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/');
    foreach ($want as $org => $genes) {
        $f = "$base/$org/organism.sqlite";
        if (!is_file($f)) continue;
        try {
            $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
            foreach (array_chunk(array_keys($genes), 400) as $chunk) {
                $st = $db->prepare('SELECT feature_uniquename, feature_name, feature_description FROM feature WHERE feature_uniquename IN ('
                                   . implode(',', array_fill(0, count($chunk), '?')) . ')');
                $st->execute(array_map('strval', $chunk));
                foreach ($st as $r) $out[$org][$r['feature_uniquename']] = ['name' => (string) $r['feature_name'], 'description' => (string) $r['feature_description']];
            }
        } catch (Throwable $e) {
            error_log("MOOP: clade gene labels for $org: " . $e->getMessage());
        }
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
 * The Comparative genomics card as a TSV, one row per gene: its orthologs in each clade, the other
 * members of its gene family and its paralogs -- the WHOLE family (the card shows the first 200).
 * $section is the page's compgen_section (moop_clade_homologs_for_gene() + its gene).
 */
function moop_clade_homologs_tsv(array $section): string {
    $cell   = fn($s) => str_replace(["\t", "\r", "\n"], ' ', (string) $s);
    $labels = moop_clade_gene_labels($section['clades'], null);
    $out    = "gene\tclade\tmethod\tversion\tgene_family\torganism\trelationship\tkind\thomolog\tgene_set\tname\tdescription\talso_in\n";
    foreach ($section['clades'] as $c) {
        foreach ($c['organisms'] as $org => $o) {
            $rows = [];
            if (!$o['self']) foreach ($o['orthologs'] as $g) $rows[] = ['ortholog', $g['kind'], $g['gene']];
            foreach ($o['family'] as $g) $rows[] = [$o['self'] ? 'paralog' : 'family member (not ortholog)', '', $g];
            foreach ($rows as [$rel, $kind, $gene]) {
                $l    = $labels[$org][$gene] ?? ['name' => '', 'description' => ''];
                $name = moop_clade_has_symbol($l['name'], $gene) ? $l['name'] : '';
                $desc = trim(preg_replace('/\s*\[[^\]]*\]\s*$/', '', $l['description']));
                if ($desc !== '' && !moop_clade_has_symbol($desc, $gene)) $desc = '';   // only an id, as on the card
                $also = [];
                foreach ($c['aliases'][$org][$gene] ?? [] as $a) $also[] = $a['gene_set'] . ':' . $a['gene'];
                $out .= implode("\t", array_map($cell, [$section['gene'], $c['label'], $c['method'], $c['version'], $c['orthogroup'],
                        $org, $rel, $kind, $gene, $o['gene_set'], $name, $desc, implode(', ', $also)])) . "\n";
            }
        }
    }
    return $out;
}
