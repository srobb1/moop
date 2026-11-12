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

const MOOP_CLADE_FORMATS = ['clade_homologs/1', 'clade_homologs/2'];   // 2 adds alias_set + alias (other gene sets)

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
 *   ['clade', 'label', 'method', 'version', 'gene_sets', 'orthogroup', 'family_total',
 *    'via' => null | ['gene', 'gene_set', 'alias_method']    (format 2: this page's gene matched by sequence)
 *    'organisms' => [organism => ['assembly', 'gene_set', 'self', 'orthologs' => [['gene','kind'],..], 'family' => [gene,..]]],
 *    'aliases'   => [organism => [gene => [['gene_set','assembly','gene'], ...]]]]   (format 2; other gene sets' ids)
 * Only genes whose own gene set the visitor may see are listed or counted.
 */
function moop_clade_homologs_for_gene(string $organism, string $gene_set, string $gene): array {
    $out = [];
    foreach (moop_clades_open() as $clade => $db) {
        foreach (moop_clade_homologs_in($db, $clade, $organism, $gene_set, $gene) as $entry) $out[] = $entry;
    }
    usort($out, fn($a, $b) => strcmp($a['label'], $b['label']));
    return $out;
}

/** One clade file's entries for one gene (see moop_clade_homologs_for_gene). Separate for tests. */
function moop_clade_homologs_in(PDO $db, string $clade, string $organism, string $gene_set, string $gene): array {
    $has_alias = (bool) $db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'alias'")->fetchColumn();
    $members = [];   // organism/gene_set -> assembly (reference gene sets, and alias gene sets)
    foreach ($db->query('SELECT organism, assembly, gene_set FROM member') as $m) $members[$m['organism'] . '/' . $m['gene_set']] = $m['assembly'];
    $alias_sets = [];
    if ($has_alias) foreach ($db->query('SELECT organism, assembly, gene_set FROM alias_set') as $m) $alias_sets[$m['organism'] . '/' . $m['gene_set']] = $m['assembly'];
    $visible = function (string $org, string $gs) use ($members, $alias_sets): bool {
        $asm = $members["$org/$gs"] ?? $alias_sets["$org/$gs"] ?? null;
        return $asm !== null && moop_dataset_visible($org, $asm, $gs);
    };

    // The reference gene(s) this page is about: the gene itself (a member gene set), or -- format 2 --
    // the reference gene(s) a gene of another gene set matches by sequence.
    $refs = [];   // [[id, orthogroup, via|null]]
    $st = $db->prepare('SELECT id, orthogroup, gene, gene_set FROM gene WHERE gene_lc = ? AND organism = ? AND gene_set = ?');
    $st->execute([strtolower($gene), $organism, $gene_set]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) $refs[] = [$r, null];
    elseif ($has_alias) {
        $st = $db->prepare('SELECT g.id, g.orthogroup, g.gene, g.gene_set FROM alias a JOIN gene g ON g.id = a.gene_id
                            WHERE a.gene_lc = ? AND a.organism = ? AND a.gene_set = ? ORDER BY g.gene');
        $st->execute([strtolower($gene), $organism, $gene_set]);
        $method = (string) $db->query("SELECT value FROM meta WHERE key = 'alias_method'")->fetchColumn();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($visible($organism, $r['gene_set'])) $refs[] = [$r, ['gene' => $r['gene'], 'gene_set' => $r['gene_set'], 'alias_method' => $method]];
        }
    }
    if (!$refs) return [];
    $meta = $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);

    $out = [];
    foreach ($refs as [$me, $via]) {
        $orgs = [];
        $ids = [];   // gene.id of every listed gene, for its aliases
        $slot = function (string $org, string $gs) use (&$orgs, $members, $organism) {
            if (!isset($orgs[$org])) $orgs[$org] = ['assembly' => $members["$org/$gs"] ?? '', 'gene_set' => $gs,
                                                    'self' => $org === $organism, 'orthologs' => [], 'family' => []];
        };
        // Orthologs: pairwise, stored both ways, so one indexed read from this gene's side.
        $st = $db->prepare("SELECT g.id, g.organism, g.gene_set, g.gene, p.kind FROM pair p JOIN gene g ON g.id = p.gene_b
                            WHERE p.gene_a = ? ORDER BY (p.kind = '1:1') DESC, g.gene");
        $st->execute([$me['id']]);
        $listed = [];
        foreach ($st as $r) {
            if (!$visible($r['organism'], $r['gene_set'])) continue;
            $slot($r['organism'], $r['gene_set']);
            $orgs[$r['organism']]['orthologs'][] = ['gene' => $r['gene'], 'kind' => $r['kind']];
            $listed[$r['organism'] . "\0" . $r['gene']] = true;
            $ids[$r['id']] = [$r['organism'], $r['gene']];
        }
        // Gene family: everything else in this gene's whole-clade HOG (N0), paralogs included.
        $st = $db->prepare("SELECT g.id, g.organism, g.gene_set, g.gene FROM hog h0
                            JOIN hog h ON h.hog = h0.hog AND h.node = 'N0' JOIN gene g ON g.id = h.gene_id
                            WHERE h0.gene_id = ? AND h0.node = 'N0' AND g.id != ? ORDER BY g.organism, g.gene");
        $st->execute([$me['id'], $me['id']]);
        $family_total = 0;
        foreach ($st as $r) {
            if (isset($listed[$r['organism'] . "\0" . $r['gene']]) || !$visible($r['organism'], $r['gene_set'])) continue;
            $slot($r['organism'], $r['gene_set']);
            $orgs[$r['organism']]['family'][] = $r['gene'];
            $ids[$r['id']] = [$r['organism'], $r['gene']];
            $family_total++;
        }
        if (!$orgs) continue;
        uksort($orgs, fn($a, $b) => [$a !== $organism, $a] <=> [$b !== $organism, $b]);

        // Format 2: each listed gene's ids in the other gene sets of its species (plus this page's
        // reference gene's, so a reader on the reference page sees them too).
        $aliases = [];
        if ($has_alias) {
            $ids[$me['id']] = [$organism, $me['gene']];
            foreach (array_chunk(array_keys($ids), 400) as $chunk) {
                // Integers inlined (they are gene.id values, cast): bound as text they would not match an
                // alias.gene_id column without INTEGER affinity.
                $st = $db->query('SELECT gene_id, organism, gene_set, gene FROM alias WHERE gene_id IN (' . implode(',', array_map('intval', $chunk)) . ') ORDER BY gene_set, gene');
                foreach ($st as $r) {
                    if (!$visible($r['organism'], $r['gene_set'])) continue;
                    [$o, $g] = $ids[$r['gene_id']];
                    $aliases[$o][$g][] = ['gene_set' => $r['gene_set'], 'assembly' => $alias_sets[$r['organism'] . '/' . $r['gene_set']] ?? '', 'gene' => $r['gene']];
                }
            }
        }
        $out[] = [
            'clade'        => $clade,
            'label'        => (string) ($meta['label'] ?? $clade),
            'method'       => (string) ($meta['method'] ?? 'OrthoFinder'),
            'version'      => (string) ($meta['method_version'] ?? $meta['orthofinder_version'] ?? ''),
            'gene_sets'    => count($members),
            'orthogroup'   => (string) $me['orthogroup'],
            'via'          => $via,
            'self_gene'    => (string) $me['gene'],
            'organisms'    => $orgs,
            'aliases'      => $aliases,
            'family_total' => $family_total,
        ];
    }
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
