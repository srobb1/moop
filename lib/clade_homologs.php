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

const MOOP_CLADE_FORMAT = 'clade_homologs/1';

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
            if ($fmt !== MOOP_CLADE_FORMAT) { error_log("MOOP: $f is not " . MOOP_CLADE_FORMAT . " -- skipped"); continue; }
            $open[$clade] = $db;
        } catch (Throwable $e) {
            error_log("MOOP: cannot open $f: " . $e->getMessage());
        }
    }
    return $open;
}

/**
 * The card's data for one gene: one entry per clade the gene is in.
 *   [['clade', 'label', 'method', 'gene_sets', 'organisms' => [organism => [
 *        'assembly', 'gene_set', 'self' (bool: the page's own organism),
 *        'orthologs' => [['gene', 'kind'], ...],          (from pair; never for 'self')
 *        'family'    => [gene, ...],                        (rest of the N0 HOG; paralogs on 'self')
 *     ]], 'family_total' => n]]
 * Only genes whose own gene set the visitor may see are listed or counted.
 */
function moop_clade_homologs_for_gene(string $organism, string $gene_set, string $gene): array {
    $out = [];
    foreach (moop_clades_open() as $clade => $db) {
        $st = $db->prepare('SELECT id, orthogroup FROM gene WHERE gene_lc = ? AND organism = ? AND gene_set = ?');
        $st->execute([strtolower($gene), $organism, $gene_set]);
        $me = $st->fetch(PDO::FETCH_ASSOC);
        if (!$me) continue;

        $members = [];   // organism/gene_set -> assembly
        foreach ($db->query('SELECT organism, assembly, gene_set FROM member') as $m) $members[$m['organism'] . '/' . $m['gene_set']] = $m['assembly'];
        $visible = function (string $org, string $gs) use ($members): bool {
            $asm = $members["$org/$gs"] ?? null;
            return $asm !== null && moop_dataset_visible($org, $asm, $gs);
        };
        $orgs = [];
        $slot = function (string $org, string $gs) use (&$orgs, $members, $organism) {
            if (!isset($orgs[$org])) $orgs[$org] = ['assembly' => $members["$org/$gs"] ?? '', 'gene_set' => $gs,
                                                    'self' => $org === $organism, 'orthologs' => [], 'family' => []];
        };

        // Orthologs: pairwise, stored both ways, so one indexed read from this gene's side.
        $st = $db->prepare('SELECT g.organism, g.gene_set, g.gene, p.kind FROM pair p JOIN gene g ON g.id = p.gene_b
                            WHERE p.gene_a = ? ORDER BY (p.kind = \'1:1\') DESC, g.gene');
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
        if (!$orgs) continue;

        // The page's own organism first (its paralogs), then the others by name.
        uksort($orgs, fn($a, $b) => [$a !== $organism, $a] <=> [$b !== $organism, $b]);
        $meta = $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
        $out[] = [
            'clade'        => $clade,
            'label'        => (string) ($meta['label'] ?? $clade),
            'version'      => (string) ($meta['orthofinder_version'] ?? ''),
            'gene_sets'    => count($members),
            'orthogroup'   => (string) $me['orthogroup'],
            'organisms'    => $orgs,
            'family_total' => $family_total,
        ];
    }
    usort($out, fn($a, $b) => strcmp($a['label'], $b['label']));
    return $out;
}

/**
 * Names and descriptions for the genes the card lists, from each organism's own database:
 * [organism => [gene => ['name' => ..., 'description' => ...]]]. One query per organism.
 */
function moop_clade_gene_labels(array $clades): array {
    $want = [];
    foreach ($clades as $c) foreach ($c['organisms'] as $org => $o) {
        foreach ($o['orthologs'] as $g) $want[$org][$g['gene']] = true;
        foreach (array_slice($o['family'], 0, 200) as $g) $want[$org][$g] = true;
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
