<?php
/**
 * Crosswalk -- ID mappings between transcriptomes, microarrays, ESTs and reference genes, one
 * read-only SQLite file per organism:   organisms/<organism>/crosswalk.sqlite
 * On simrbase this is Planosphere's Rosetta Stone; the visible name is per site ('name' below).
 * Plan: notes/PLANOSPHERE_PAGE_ROSETTA_PLAN.md (step 3).
 *
 * MODEL: every accession maps to one or more REFERENCE genes (the hubs: schMedS3 h1 genes and h2
 * genes). Two accessions are "the same gene" when they reach the same reference gene. Hubs whose
 * genes are loaded in this MOOP ('gene_set' set below) become gene-page links; the others (h2,
 * not in simrbase) show as ids linking to WormBase ParaSite.
 *
 * SOURCE: the 3-column mapping files (seq_id, ref_id, seq_id_source) made on the compute side by
 * the planosphere/RosettaStone method (pblat, >=95% identity). Only the gene-level hub files are
 * loaded: they also carry the transcript and smed_20140614 rows, i.e. everything the old Rosetta
 * page queried, at a fifth of the size of all mapping files.
 *
 * READ-ONLY for visitors; built only by the CLI (same rules as lib/ontologies.php: temp file,
 * checks, rename; a failed build leaves the previous file).
 */

/** docs/CROSSWALK_FORMAT.md -- a shipped file without this 'format' in meta is refused. */
const MOOP_CROSSWALK_FORMAT = 'crosswalk/1';

/** Organisms with a crosswalk configured: [organism => ['folder', 'hubs' => [hub => [file, gene_set?, assembly?]], 'name']]. */
function moop_crosswalk_sources(): array {
    $out = [];
    foreach (ConfigManager::getInstance()->getArray('crosswalk', []) as $org => $s) {
        if (is_array($s) && !empty($s['folder']) && !empty($s['hubs']) && preg_match('/^[A-Za-z0-9_.-]+$/', (string) $org)) $out[$org] = $s;
    }
    return $out;
}

function moop_crosswalk_file(string $organism): string {
    return rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/'
         . preg_replace('/[^A-Za-z0-9_.-]/', '', $organism) . '/crosswalk.sqlite';
}

function moop_crosswalk_open(string $organism): ?PDO {
    return moop_crosswalk_check($organism) === '' ? moop_crosswalk_connect($organism) : null;
}

function moop_crosswalk_connect(string $organism): ?PDO {
    $f = moop_crosswalk_file($organism);
    if (!is_file($f)) return null;
    try {
        $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Is this organism's crosswalk.sqlite in the format MOOP expects (docs/CROSSWALK_FORMAT.md)?
 * '' = yes, or no file at all (nothing to use); otherwise the reason -- for the admin view.
 * The compute pipeline usually builds the file, so this is the check that a shipped file is right.
 * Remembered per request (pages ask once per row).
 */
function moop_crosswalk_check(string $organism): string {
    static $seen = [];
    if (isset($seen[$organism])) return $seen[$organism];
    if (!is_file(moop_crosswalk_file($organism))) return $seen[$organism] = '';
    $db = moop_crosswalk_connect($organism);
    if (!$db) return $seen[$organism] = 'cannot be opened';
    try {
        $cols = array_column($db->query('PRAGMA table_info(map)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['accession', 'accession_lc', 'source', 'ref_id', 'hub'] as $c) {
            if (!in_array($c, $cols, true)) return $seen[$organism] = "table map has no column $c";
        }
        $idx = $db->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'map'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['m_acc', 'm_ref'] as $i) if (!in_array($i, $idx, true)) return $seen[$organism] = "index $i is missing (lookups would be slow)";
        $fmt = $db->query("SELECT value FROM meta WHERE key = 'format'")->fetchColumn();
        if ($fmt !== MOOP_CROSSWALK_FORMAT) return $seen[$organism] = "meta 'format' is " . var_export($fmt, true) . ', expected ' . MOOP_CROSSWALK_FORMAT;
    } catch (PDOException $e) {
        return $seen[$organism] = 'not a crosswalk file (' . $e->getMessage() . ')';
    }
    return $seen[$organism] = '';
}

function moop_crosswalk_meta(string $organism): array {
    $db = moop_crosswalk_connect($organism);
    return $db ? $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
}

/**
 * Build from local mapping files: $hubs = [hub => path]. Returns ['ok', 'error', 'counts', 'skipped'].
 */
function moop_crosswalk_build_from_files(string $organism, array $hubs, ?string $out_file = null): array {
    $out_file = $out_file ?? moop_crosswalk_file($organism);
    $tmp = $out_file . '.tmp-' . getmypid();
    @unlink($tmp);
    $skipped = 0;
    try {
        $db = new PDO('sqlite:' . $tmp);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF; PRAGMA cache_size = -200000;');
        $db->exec('CREATE TABLE map (accession TEXT NOT NULL, accession_lc TEXT NOT NULL, source TEXT NOT NULL,
                                     ref_id TEXT NOT NULL, hub TEXT NOT NULL)');
        $db->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)');
        $db->beginTransaction();
        $ins = $db->prepare('INSERT INTO map (accession, accession_lc, source, ref_id, hub) VALUES (?,?,?,?,?)');
        $per_hub = [];
        foreach ($hubs as $hub => $path) {
            if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $hub)) throw new RuntimeException("bad hub name $hub");
            $fh = @fopen($path, 'r');
            if (!$fh) throw new RuntimeException("cannot read $path");
            if (trim((string) fgets($fh)) !== "seq_id\tref_id\tseq_id_source") throw new RuntimeException("$path: header is not seq_id ref_id seq_id_source");
            $n = 0;
            while (($line = fgets($fh)) !== false) {
                $c = explode("\t", rtrim($line, "\r\n"));
                if (count($c) !== 3 || $c[1] === '"NA"' || $c[0] === '' || $c[1] === ''
                    || strlen($c[0]) > 200 || strlen($c[1]) > 100 || !preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $c[2])) { $skipped++; continue; }
                $ins->execute([$c[0], mb_strtolower($c[0]), $c[2], $c[1], $hub]);
                $n++;
            }
            fclose($fh);
            $per_hub[$hub] = $n;
        }
        $db->commit();
        $db->exec('CREATE INDEX m_acc ON map (accession_lc)');
        $db->exec('CREATE INDEX m_ref ON map (ref_id, hub)');
        $db->exec('ANALYZE');
        $counts = [
            'rows'       => (int) $db->query('SELECT COUNT(*) FROM map')->fetchColumn(),
            'accessions' => (int) $db->query('SELECT COUNT(DISTINCT accession_lc) FROM map')->fetchColumn(),
            'genes'      => (int) $db->query('SELECT COUNT(DISTINCT ref_id) FROM map')->fetchColumn(),
            'sources'    => (int) $db->query('SELECT COUNT(DISTINCT source) FROM map')->fetchColumn(),
        ];
        if ($counts['rows'] === 0) throw new RuntimeException('no mappings -- refusing to replace the live file');
        $meta = $db->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach (['format' => MOOP_CROSSWALK_FORMAT, 'organism' => $organism, 'built_at' => date('c'), 'skipped_rows' => (string) $skipped,
                  'hubs' => json_encode($per_hub)] + array_map('strval', $counts) as $k => $v) $meta->execute([$k, $v]);
        if ((string) $db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('integrity check failed');
        $db = null;
        @chmod($tmp, 0664);
        if (!@rename($tmp, $out_file)) throw new RuntimeException("cannot move the new file into place: $out_file");
        return ['ok' => true, 'error' => '', 'counts' => $counts, 'skipped' => $skipped];
    } catch (Throwable $e) {
        $db = null;
        @unlink($tmp);
        return ['ok' => false, 'error' => $e->getMessage(), 'counts' => [], 'skipped' => $skipped];
    }
}

/** Build one organism's crosswalk from its configured folder (the CLI calls this). */
function moop_crosswalk_build(string $organism): array {
    $src = moop_crosswalk_sources()[$organism] ?? null;
    if (!$src) return ['ok' => false, 'error' => "no crosswalk configured for $organism", 'counts' => [], 'skipped' => 0];
    $hubs = [];
    foreach ($src['hubs'] as $hub => $h) {
        $file = basename((string) ($h['file'] ?? ''));   // a file NAME inside the folder, never a path
        if ($file === '') return ['ok' => false, 'error' => "hub $hub has no file", 'counts' => [], 'skipped' => 0];
        $hubs[$hub] = rtrim($src['folder'], '/') . '/' . $file;
    }
    return moop_crosswalk_build_from_files($organism, $hubs);
}

/**
 * The reference genes each accession maps to: [accession_lc => [['ref_id', 'hub'], ...]].
 * Case-insensitive, like the old Rosetta page. Read-only.
 */
function moop_crosswalk_genes(string $organism, array $accessions): array {
    $db = moop_crosswalk_open($organism);
    $lc = array_values(array_unique(array_map('mb_strtolower', array_filter($accessions, 'strlen'))));
    if (!$db || !$lc) return [];
    $out = [];
    foreach (array_chunk($lc, 500) as $chunk) {
        $st = $db->prepare('SELECT DISTINCT accession_lc, ref_id, hub FROM map WHERE accession_lc IN ('
                           . implode(',', array_fill(0, count($chunk), '?')) . ') ORDER BY hub, ref_id');
        $st->execute($chunk);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['accession_lc']][] = ['ref_id' => $r['ref_id'], 'hub' => $r['hub']];
    }
    return $out;
}

/** A reference gene as a link target: gene page if its hub's genes are in this MOOP, else ''. */
function moop_crosswalk_gene_url(string $organism, string $ref_id, string $hub): string {
    $h = moop_crosswalk_sources()[$organism]['hubs'][$hub] ?? [];
    if (empty($h['gene_set'])) return '';
    return moop_url('tools/parent.php') . '?' . http_build_query(['organism' => $organism, 'uniquename' => $ref_id,
           'assembly' => (string) ($h['assembly'] ?? ''), 'gene_set' => (string) $h['gene_set']]);
}

/**
 * For results tables: each accession's best reference genes, as display-ready links.
 * Order of preference = the order the hubs are configured (h1 genes, then smed_20140614, then h2):
 * the first hub with a match is shown; 'all' lists every match for a tooltip.
 * Returns [accession_lc => ['show' => [['id', 'url', 'external']...], 'all' => [ids]]].
 */
function moop_crosswalk_gene_links(string $organism, array $accessions): array {
    $hubs  = array_keys(moop_crosswalk_sources()[$organism]['hubs'] ?? []);
    $found = moop_crosswalk_genes($organism, $accessions);
    $out   = [];
    foreach ($found as $acc => $rows) {
        $by_hub = [];
        foreach ($rows as $r) $by_hub[$r['hub']][] = $r['ref_id'];
        $show = [];
        foreach ($hubs as $hub) {
            if (empty($by_hub[$hub])) continue;
            foreach (array_unique($by_hub[$hub]) as $id) {
                $url = moop_crosswalk_gene_url($organism, $id, $hub);
                $show[] = ['id' => $id, 'url' => $url ?: 'https://parasite.wormbase.org/Multi/Search/Results?species=all;idx=;q=' . rawurlencode($id),
                           'external' => $url === ''];
            }
            break;
        }
        $out[$acc] = ['show' => $show, 'all' => array_values(array_unique(array_column($rows, 'ref_id')))];
    }
    return $out;
}
