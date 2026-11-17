<?php
/**
 * GO gene counts: for each GO term, how many genes of each gene set carry it -- annotated to the
 * term itself or to a more specific one. docs/ONTOLOGIES.md; decided with Sofia 2026-11-17.
 *
 * - ALL GO sources combined (EggNOG2GO, InterPro2GO, PANTHER2GO, OMA2GO, ...): any annotation whose
 *   accession is a GO id. A gene counts once per term, whichever sources named it.
 * - Propagated up "is a" and "part of" ONLY -- GO's own rule (a gene annotated to "regulation of X"
 *   is not an X gene). The Data Exporter's "include more specific terms" must use the same rule
 *   (MOOP_GO_PROPAGATE), so a count equals what its exporter link finds.
 * - A gene = the root feature (up to two parents up), as the annotation counts use.
 *
 * One small SQLite per organism: <cache>/go_counts/<Organism>.sqlite, built to a temp file and
 * swapped in, so a reader never sees a half-built file. Kept PER GENE SET, so a visitor is shown
 * only the gene sets they may see, and sums across gene sets are exact (genes are disjoint).
 * Built only by housekeeping's background run or scripts/build_go_gene_counts.php -- never in a
 * page request. Recounted only when the organism's database or go.sqlite changed.
 */
require_once __DIR__ . '/ontologies.php';
require_once __DIR__ . '/cache_paths.php';

/** The relations a gene's GO annotation is carried up through. GO convention: is a + part of. */
const MOOP_GO_PROPAGATE = ['rdfs:subClassOf', 'BFO:0000050'];
/** The annotation TYPE the pipeline gives GO sources (EggNOG2GO, InterPro2GO...): a GO term page's
 *  Data Exporter links tick it, so the genes come with their GO terms as columns. */
const MOOP_GO_ANNOTATION_TYPE = 'Gene Ontology';
/** Bump when the build changes meaning, so every file is recounted. */
const MOOP_GO_COUNTS_VERSION = '1';

function moop_go_counts_dir(): string {
    return moop_cache_root() . '/go_counts';
}

function moop_go_counts_file(string $organism): string {
    return moop_go_counts_dir() . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $organism) . '.sqlite';
}

/** What a GO counts file must have been built from to be current: [key => value]. '' if GO is not built. */
function moop_go_counts_wanted(string $organism_db): array {
    $go = moop_ontology_meta('GO');
    if (!$go || !is_file($organism_db)) return [];
    clearstatcache(true, $organism_db);
    return ['version' => MOOP_GO_COUNTS_VERSION, 'go_built_at' => (string) ($go['built_at'] ?? ''),
            'db_mtime' => (string) filemtime($organism_db), 'db_size' => (string) filesize($organism_db)];
}

/** The meta table of a built counts file ([] if none or unreadable). */
function moop_go_counts_meta(string $organism): array {
    $file = moop_go_counts_file($organism);
    if (!is_file($file)) return [];
    try {
        $db = new PDO('sqlite:' . $file, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        return $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        return [];
    }
}

/** True when the organism's counts are missing or were built from another database or GO build. */
function moop_go_counts_stale(string $organism, string $organism_db): bool {
    $want = moop_go_counts_wanted($organism_db);
    if (!$want) return false;                       // no GO here: nothing to count
    $have = moop_go_counts_meta($organism);
    foreach ($want as $k => $v) if (($have[$k] ?? null) !== $v) return true;
    return false;
}

/**
 * Count one organism. Returns ['ok', 'error', 'gene_sets', 'terms', 'seconds'].
 * Reads organism.sqlite and go.sqlite (ATTACHed; only SELECTs touch them).
 */
function moop_go_counts_build(string $organism, string $organism_db): array {
    $t0   = microtime(true);
    $want = moop_go_counts_wanted($organism_db);
    $go   = moop_ontology_file('GO');
    if (!$want) return ['ok' => false, 'error' => 'GO is not built on this site', 'gene_sets' => 0, 'terms' => 0, 'seconds' => 0];
    $dir = moop_go_counts_dir();
    if (!is_dir($dir) && !moop_ensure_cache_dir($dir)) {
        return ['ok' => false, 'error' => "cannot create $dir", 'gene_sets' => 0, 'terms' => 0, 'seconds' => 0];
    }
    $file = moop_go_counts_file($organism);
    $tmp  = $file . '.tmp-' . getmypid();
    @unlink($tmp);
    try {
        $db = new PDO('sqlite:' . $tmp);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF;');
        $db->exec('ATTACH DATABASE ' . $db->quote($organism_db) . ' AS org');
        $db->exec('ATTACH DATABASE ' . $db->quote($go) . ' AS go');
        $db->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)');
        // Gene sets by their site key (assembly directory + gene set name), as access is checked.
        $db->exec('CREATE TABLE gene_set (gs_id INTEGER PRIMARY KEY, assembly TEXT NOT NULL, gene_set TEXT NOT NULL,
                                          genes_with_go INTEGER NOT NULL DEFAULT 0)');
        $db->exec('INSERT INTO gene_set (gs_id, assembly, gene_set)
                   SELECT gs.gene_set_id, g.genome_accession, gs.gene_set_name
                   FROM org.gene_set gs JOIN org.genome g ON g.genome_id = gs.genome_id');
        // (gene set, gene, GO term) as annotated -- every source, deduplicated.
        $db->exec("CREATE TEMP TABLE gt AS
                   SELECT DISTINCT f.gene_set_id AS gs, COALESCE(gp.feature_id, p.feature_id, f.feature_id) AS gene,
                                   a.annotation_accession AS term
                   FROM org.annotation a
                   JOIN org.feature_annotation fa ON fa.annotation_id = a.annotation_id
                   JOIN org.feature f             ON f.feature_id = fa.feature_id
                   LEFT JOIN org.feature p        ON p.feature_id = f.parent_feature_id
                   LEFT JOIN org.feature gp       ON gp.feature_id = p.parent_feature_id
                   WHERE a.annotation_accession LIKE 'GO:%'");
        $db->exec('CREATE INDEX temp.gt_term ON gt (term)');
        // Each annotation plus every term above it through is a / part of; direct = annotated to it.
        $rel = implode(',', array_map([$db, 'quote'], MOOP_GO_PROPAGATE));
        $db->exec('CREATE TABLE count (term TEXT NOT NULL, gs_id INTEGER NOT NULL, genes INTEGER NOT NULL,
                                       direct INTEGER NOT NULL, PRIMARY KEY (term, gs_id)) WITHOUT ROWID');
        $db->exec("INSERT INTO count (term, gs_id, genes, direct)
                   SELECT term, gs, COUNT(*), SUM(direct) FROM (
                       SELECT gs, gene, term, MAX(direct) AS direct FROM (
                           SELECT gs, gene, term, 1 AS direct FROM gt
                           UNION ALL
                           SELECT gt.gs, gt.gene, c.object, 0 FROM gt
                           JOIN go.closure c ON c.subject = gt.term AND c.relation IN ($rel)
                           WHERE c.object LIKE 'GO:%'
                       ) GROUP BY gs, gene, term
                   ) GROUP BY term, gs");
        $db->exec('UPDATE gene_set SET genes_with_go = (SELECT COUNT(DISTINCT gene) FROM gt WHERE gt.gs = gene_set.gs_id)');
        $db->exec('DELETE FROM gene_set WHERE genes_with_go = 0');
        $gene_sets = (int) $db->query('SELECT COUNT(*) FROM gene_set')->fetchColumn();
        $terms     = (int) $db->query('SELECT COUNT(DISTINCT term) FROM count')->fetchColumn();
        $ins = $db->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach ($want + ['organism' => $organism, 'built_at' => date('c'), 'rule' => implode(' + ', MOOP_GO_PROPAGATE),
                          'gene_sets' => (string) $gene_sets, 'terms' => (string) $terms] as $k => $v) {
            $ins->execute([$k, $v]);
        }
        $db->exec('DETACH DATABASE org');
        $db->exec('DETACH DATABASE go');
        $db = null;
        @chmod($tmp, 0664);
        if (!rename($tmp, $file)) throw new RuntimeException("cannot move the new file into $file");
        return ['ok' => true, 'error' => '', 'gene_sets' => $gene_sets, 'terms' => $terms, 'seconds' => round(microtime(true) - $t0, 1)];
    } catch (Throwable $e) {
        $db = null;
        @unlink($tmp);
        return ['ok' => false, 'error' => $e->getMessage(), 'gene_sets' => 0, 'terms' => 0, 'seconds' => round(microtime(true) - $t0, 1)];
    }
}

/**
 * Recount every organism whose counts are stale, smallest database first, until $budget_seconds
 * is spent (the rest wait for the next run). $only limits it to these organisms; $force recounts
 * them even when current. Returns ['built' => [org => result], 'failed' => [org => error],
 * 'left' => n, 'current' => n, 'go' => bool].
 */
function moop_go_counts_refresh(string $organism_data, int $budget_seconds = 240, array $only = [], bool $force = false): array {
    $out = ['built' => [], 'failed' => [], 'left' => 0, 'current' => 0, 'go' => (bool) moop_ontology_meta('GO')];
    if (!$out['go']) return $out;
    $todo = [];
    foreach (scandir($organism_data) ?: [] as $org) {
        if ($org[0] === '.' || ($only && !in_array($org, $only, true))) continue;
        $db = "$organism_data/$org/organism.sqlite";
        if (!is_file($db)) continue;
        if (!$force && !moop_go_counts_stale($org, $db)) { $out['current']++; continue; }
        $todo[$org] = filesize($db);
    }
    asort($todo);
    $t0 = time();
    foreach (array_keys($todo) as $org) {
        if (time() - $t0 >= $budget_seconds) { $out['left']++; continue; }
        $r = moop_go_counts_build($org, "$organism_data/$org/organism.sqlite");
        if ($r['ok']) $out['built'][$org] = $r; else $out['failed'][$org] = $r['error'];
    }
    // Files of organisms that are gone (retired, renamed) would be counted forever: remove them.
    foreach (glob(moop_go_counts_dir() . '/*.sqlite') ?: [] as $f) {
        $org = basename($f, '.sqlite');
        if (!is_file("$organism_data/$org/organism.sqlite")) @unlink($f);
    }
    return $out;
}

/**
 * Gene counts for these GO terms, over the gene sets THIS visitor may see.
 * Returns [term => ['genes' => total, 'direct' => total, 'organisms' => [org => genes]]];
 * a term no visible gene carries is left out. Read-only; nothing is built here.
 */
function moop_go_counts_for_terms(array $terms): array {
    $terms = array_values(array_unique(array_filter($terms, fn($t) => preg_match('/^GO:\d{7}$/', (string) $t))));
    if (!$terms) return [];
    // Visible gene sets, as organism => [assembly/gene_set => true].
    $visible = [];
    foreach (getAccessibleGeneSets() as $by_org) {
        foreach ($by_org as $org => $rows) {
            foreach ($rows as $r) $visible[$org][($r['assembly'] ?? '') . '/' . ($r['gene_set'] ?? '')] = true;
        }
    }
    $out = [];
    $in  = implode(',', array_fill(0, count($terms), '?'));
    foreach ($visible as $org => $keys) {
        $file = moop_go_counts_file($org);
        if (!is_file($file)) continue;
        try {
            $db = new PDO('sqlite:' . $file, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
            $st = $db->prepare("SELECT c.term, g.assembly || '/' || g.gene_set AS k, c.genes, c.direct
                                FROM count c JOIN gene_set g ON g.gs_id = c.gs_id WHERE c.term IN ($in)");
            $st->execute($terms);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (empty($keys[$row['k']])) continue;
                $t = $row['term'];
                $out[$t]['genes']  = ($out[$t]['genes'] ?? 0) + (int) $row['genes'];
                $out[$t]['direct'] = ($out[$t]['direct'] ?? 0) + (int) $row['direct'];
                $out[$t]['organisms'][$org] = ($out[$t]['organisms'][$org] ?? 0) + (int) $row['genes'];
            }
        } catch (Throwable $e) {
            continue;   // a file being replaced or unreadable: that organism is simply not counted
        }
    }
    return $out;
}

/**
 * One GO term's gene counts, one row per gene set THIS visitor may see (the term page's table):
 * [['organism', 'assembly', 'genome_name', 'gene_set', 'genes', 'direct', 'genes_with_go'], ...],
 * biggest first. Gene sets with no gene on the term are left out. Read-only.
 */
function moop_go_counts_by_gene_set(string $term): array {
    if (!preg_match('/^GO:\d{7}$/', $term)) return [];
    $visible = [];   // organism => [assembly/gene_set => accessible row]
    foreach (getAccessibleGeneSets() as $by_org) {
        foreach ($by_org as $org => $rows) {
            foreach ($rows as $r) $visible[$org][($r['assembly'] ?? '') . '/' . ($r['gene_set'] ?? '')] = $r;
        }
    }
    $out = [];
    foreach ($visible as $org => $keys) {
        $file = moop_go_counts_file($org);
        if (!is_file($file)) continue;
        try {
            $db = new PDO('sqlite:' . $file, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
            $st = $db->prepare('SELECT g.assembly, g.gene_set, g.genes_with_go, c.genes, c.direct
                                FROM count c JOIN gene_set g ON g.gs_id = c.gs_id WHERE c.term = ?');
            $st->execute([$term]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $acc = $keys[$row['assembly'] . '/' . $row['gene_set']] ?? null;
                if (!$acc) continue;
                $out[] = ['organism' => $org, 'assembly' => $row['assembly'], 'genome_name' => (string) ($acc['genome_name'] ?? ''),
                          'gene_set' => $row['gene_set'], 'genes' => (int) $row['genes'], 'direct' => (int) $row['direct'],
                          'genes_with_go' => (int) $row['genes_with_go']];
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    usort($out, fn($a, $b) => $b['genes'] <=> $a['genes'] ?: strcmp($a['organism'], $b['organism']));
    return $out;
}
