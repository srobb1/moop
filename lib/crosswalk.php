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

/**
 * Organisms with a crosswalk configured: [organism => ['hubs' => [hub => [file?, gene_set?, assembly?, transcripts?]],
 * 'name', 'sources', 'always_show', 'folder'?]]. 'folder' (and each hub's 'file') is needed ONLY to build
 * crosswalk.sqlite on this server (scripts/build_crosswalk.php); when the compute side ships the
 * built file, leave it out -- the pages read only the .sqlite.
 */
function moop_crosswalk_sources(): array {
    $cm = ConfigManager::getInstance();
    // What people see -- title, groups, source names and links, the every-gene column -- can be
    // edited on Admin -> Rosetta Stone (admin/manage_crosswalk.php), saved as 'crosswalk_display'.
    // The hubs and build folder stay in site_config.php: they are server paths, never web-edited.
    $display = moop_crosswalk_display_check($cm->getArray('crosswalk_display', []));
    if (is_string($display)) $display = [];
    $out = [];
    foreach ($cm->getArray('crosswalk', []) as $org => $s) {
        if (is_array($s) && !empty($s['hubs']) && preg_match('/^[A-Za-z0-9_.-]+$/', (string) $org)) {
            $out[$org] = array_merge($s, $display[$org] ?? []);
        }
    }
    return $out;
}

/** Keys of a crosswalk entry that Admin -> Rosetta Stone may change (everything else is site_config.php). */
const MOOP_CROSSWALK_DISPLAY_KEYS = ['name', 'source_groups', 'sources', 'always_show'];

/**
 * Check a 'crosswalk_display' value: [organism => [name, source_groups, sources, always_show]].
 * Returns the cleaned value, or a string saying what is wrong (nothing is saved then). Pure.
 * Links: outside pages over https only, with {id}; a MOOP gene page by gene_set + assembly names.
 */
function moop_crosswalk_display_check($in) {
    if (!is_array($in)) return 'not a list';
    $name_re = '/^[A-Za-z0-9_.-]{1,100}$/';
    $https   = fn($u) => is_string($u) && strlen($u) <= 400 && preg_match('#^https://[^\s"<>\\\\]+$#', $u);
    $out = [];
    foreach ($in as $org => $d) {
        if (!preg_match($name_re, (string) $org) || !is_array($d)) return 'an organism name is not valid';
        if (array_diff(array_keys($d), MOOP_CROSSWALK_DISPLAY_KEYS)) return "$org: only the title, groups, sources and the every-gene column can be set here";
        $c = [];
        if (array_key_exists('name', $d)) {
            $n = trim((string) $d['name']);
            if ($n === '' || mb_strlen($n) > 100) return "$org: the title must be 1 to 100 characters";
            $c['name'] = $n;
        }
        if (array_key_exists('source_groups', $d)) {
            if (!is_array($d['source_groups']) || count($d['source_groups']) > 30) return "$org: at most 30 groups";
            $c['source_groups'] = [];
            foreach ($d['source_groups'] as $k => $l) {
                $l = trim((string) $l);
                if (!preg_match('/^[a-z0-9_]{1,30}$/', (string) $k) || $k === '_other') return "$org: a group key is not valid";
                if ($l === '' || mb_strlen($l) > 60) return "$org: every group needs a name of up to 60 characters";
                $c['source_groups'][(string) $k] = $l;
            }
        }
        if (array_key_exists('sources', $d)) {
            if (!is_array($d['sources']) || count($d['sources']) > 500) return "$org: at most 500 sources";
            $groups = $c['source_groups'] ?? null;
            $c['sources'] = [];
            foreach ($d['sources'] as $src => $x) {
                if (!preg_match($name_re, (string) $src) || !is_array($x)) return "$org: a source name is not valid";
                $e = [];
                $label = trim((string) ($x['label'] ?? ''));
                if (mb_strlen($label) > 200) return "$org, $src: the name is longer than 200 characters";
                if ($label !== '') $e['label'] = $label;
                $g = (string) ($x['group'] ?? '');
                if ($g !== '') {
                    if ($groups !== null && !isset($groups[$g])) return "$org, $src: its group no longer exists";
                    $e['group'] = $g;
                }
                $gs = (string) ($x['gene_set'] ?? ''); $as = (string) ($x['assembly'] ?? '');
                $url = (string) ($x['url'] ?? '');
                if ($gs !== '' || $as !== '') {
                    if (!preg_match($name_re, $gs) || !preg_match($name_re, $as)) return "$org, $src: the gene set is not valid";
                    if ($url !== '') return "$org, $src: choose a gene page OR an outside link, not both";
                    $e['gene_set'] = $gs; $e['assembly'] = $as;
                }
                if ($url !== '') {
                    if (!$https($url) || strpos($url, '{id}') === false) return "$org, $src: an outside link must start with https:// and contain {id} where the ID goes";
                    $e['url'] = $url;
                }
                $about = (string) ($x['about'] ?? '');
                if ($about !== '') {
                    if (!$https($about)) return "$org, $src: the \"about\" page must start with https://";
                    $e['about'] = $about;
                }
                $c['sources'][(string) $src] = $e;
            }
        }
        if (array_key_exists('always_show', $d)) {
            if (!is_array($d['always_show']) || count($d['always_show']) > 5) return "$org: at most 5 every-gene columns";
            foreach ($d['always_show'] as $src) if (!preg_match($name_re, (string) $src)) return "$org: an every-gene column is not valid";
            $c['always_show'] = array_values(array_unique(array_map('strval', $d['always_show'])));
        }
        $out[(string) $org] = $c;
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
    if (empty($src['folder'])) return ['ok' => false, 'error' => "no 'folder' configured for $organism: its crosswalk.sqlite is built elsewhere (compute side)", 'counts' => [], 'skipped' => 0];
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
 * Returns [accession_lc => ['show' => [['id', 'url', 'external', 'hub']...], 'all' => [ids]]].
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
                           'external' => $url === '', 'hub' => $hub];
            }
            break;
        }
        $out[$acc] = ['show' => $show, 'all' => array_values(array_unique(array_column($rows, 'ref_id')))];
    }
    return $out;
}

/**
 * The main search's fallback (annotation_search_ajax.php): a search that found NOTHING in an
 * organism, for one ID-like word, may be an ID this crosswalk maps -- a transcript from a
 * transcriptome MOOP does not load, a GenBank accession. Returns the gene(s) it maps to, as
 * moop_crosswalk_gene_links() shows them, or [] (no crosswalk, not one word, no mapping).
 *
 * $permitted = moop_search_permitted_scope(): null = everything open, else the {assembly,
 * gene_set} pairs this viewer may search. A hub whose gene set is NOT permitted is dropped, so the
 * hint never names a gene the viewer could not open; a hub not loaded in MOOP (an outside link)
 * is public data and stays.
 */
function moop_crosswalk_search_hint(string $organism, string $keywords, ?array $permitted): array {
    $term = trim(trim($keywords), '"\'');
    if (!isset(moop_crosswalk_sources()[$organism]) || strlen($term) < 3 || strlen($term) > 100
        || preg_match('/\s/', $term)) return [];
    $links = moop_crosswalk_gene_links($organism, [$term])[mb_strtolower($term)]['show'] ?? [];
    if ($permitted === null) return $links;
    $hubs = moop_crosswalk_sources()[$organism]['hubs'];
    $ok = [];
    foreach ($permitted as $p) $ok[$p['assembly'] . "\t" . $p['gene_set']] = true;
    return array_values(array_filter($links, function ($l) use ($hubs, $ok) {
        $h = $hubs[$l['hub']] ?? [];
        return $l['external'] || isset($ok[($h['assembly'] ?? '') . "\t" . ($h['gene_set'] ?? '')]);
    }));
}

/**
 * Reverse lookup for a gene page: every accession mapped to any of $ref_ids (the gene and its
 * transcripts), [['accession', 'source', 'ref_id', 'hub'], ...] sorted by source then accession.
 */
function moop_crosswalk_members(string $organism, array $ref_ids): array {
    $db = moop_crosswalk_open($organism);
    $ids = array_values(array_unique(array_filter($ref_ids, 'strlen')));
    if (!$db || !$ids) return [];
    $out = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $st = $db->prepare('SELECT DISTINCT accession, source, ref_id, hub FROM map WHERE ref_id IN ('
                           . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $st->execute($chunk);
        $out = array_merge($out, $st->fetchAll(PDO::FETCH_ASSOC));
    }
    usort($out, fn($a, $b) => [$a['source'], $a['accession']] <=> [$b['source'], $b['accession']]);
    return $out;
}

/** The sources in this organism's crosswalk file, with the configured name: [source => label], config order first. */
function moop_crosswalk_source_list(string $organism): array {
    $db = moop_crosswalk_open($organism);
    if (!$db) return [];
    $have = $db->query('SELECT DISTINCT source FROM map')->fetchAll(PDO::FETCH_COLUMN);
    $conf = moop_crosswalk_sources()[$organism]['sources'] ?? [];
    $out = [];
    foreach ($conf as $name => $c) if (in_array($name, $have, true)) $out[$name] = (string) ($c['label'] ?? $name);
    $rest = array_diff($have, array_keys($out));
    sort($rest, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($rest as $name) $out[$name] = $name;
    return $out;
}

/**
 * The source list in its configured groups ('source_groups', in that order; a source with no or an
 * unknown group goes under "Other", last): [['key', 'label', 'sources' => [name => label]], ...].
 * Empty groups are left out.
 */
function moop_crosswalk_source_groups(string $organism): array {
    $cfg    = moop_crosswalk_sources()[$organism] ?? [];
    $labels = array_map('strval', (array) ($cfg['source_groups'] ?? []));
    $out = [];
    foreach ($labels as $k => $l) $out[$k] = ['key' => (string) $k, 'label' => $l, 'sources' => []];
    $out['_other'] = ['key' => '_other', 'label' => 'Other', 'sources' => []];
    foreach (moop_crosswalk_source_list($organism) as $name => $label) {
        $g = (string) ($cfg['sources'][$name]['group'] ?? '');
        $out[isset($out[$g]) && $g !== '_other' ? $g : '_other']['sources'][$name] = $label;
    }
    return array_values(array_filter($out, fn($g) => $g['sources']));
}

/**
 * Where an id from a source opens: a MOOP gene page when the source's gene set is loaded here AND
 * the viewer may see it ($permitted as moop_search_permitted_scope(): null = all open), else the
 * source's outside 'url' ({id} replaced), else ''. Returns ['url', 'external'].
 */
function moop_crosswalk_id_link(string $organism, string $source, string $id, ?array $permitted): array {
    $c = moop_crosswalk_sources()[$organism]['sources'][$source] ?? [];
    if (!empty($c['gene_set'])) {
        $ok = $permitted === null;
        foreach ($permitted ?? [] as $p) if ($p['assembly'] === (string) ($c['assembly'] ?? '') && $p['gene_set'] === $c['gene_set']) $ok = true;
        if ($ok) return ['url' => moop_url('tools/parent.php') . '?' . http_build_query(['organism' => $organism, 'uniquename' => $id,
                         'assembly' => (string) ($c['assembly'] ?? ''), 'gene_set' => (string) $c['gene_set']]), 'external' => false];
        return ['url' => '', 'external' => false];
    }
    if (!empty($c['url']) && preg_match('#^https://#', (string) $c['url'])) {
        return ['url' => str_replace('{id}', rawurlencode($id), (string) $c['url']), 'external' => true];
    }
    return ['url' => '', 'external' => false];
}

/**
 * The translation tool: each typed id -> its reference gene(s) -> the ids of the same gene in the
 * $into sources. One row per (typed id, its source, reference gene, mapped id); with no $into, one
 * row per (typed id, source, reference gene). Every reference gene also carries its own transcripts
 * (the hub's 'transcripts' source), the 'always_show' sources' ids, and its description from the
 * organism database. Read-only. Returns ['rows', 'not_found' => [typed ids], 'capped' => bool].
 */
function moop_crosswalk_translate(string $organism, array $typed, array $into, ?array $permitted, int $max_rows = 5000): array {
    $res = ['rows' => [], 'not_found' => [], 'capped' => false];
    $db  = moop_crosswalk_open($organism);
    if (!$db) return $res;
    $typed = array_values(array_unique(array_filter(array_map('trim', $typed), 'strlen')));
    $cfg   = moop_crosswalk_sources()[$organism];
    $hubs  = $cfg['hubs'];
    $always = array_values(array_filter((array) ($cfg['always_show'] ?? []), 'is_string'));
    $into  = array_values(array_unique(array_filter($into, 'is_string')));

    // 1. The typed ids, as stored (their source), and the reference genes they reach.
    $query = [];   // lc => [[accession, source, ref_id, hub], ...]
    foreach (array_chunk(array_map('mb_strtolower', $typed), 500) as $chunk) {
        $st = $db->prepare('SELECT DISTINCT accession_lc, accession, source, ref_id, hub FROM map WHERE accession_lc IN ('
                           . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $st->execute($chunk);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $query[$r['accession_lc']][] = $r;
    }
    foreach ($typed as $t) if (!isset($query[mb_strtolower($t)])) $res['not_found'][] = $t;
    if (!$query) return $res;
    // Gene hubs (with a 'transcripts' source: h1, h2) are the reference genes. Another hub
    // (smed_20140614) is used only for an id that reaches no gene hub -- that is why it is a hub;
    // otherwise it would repeat what the always_show column already says.
    $is_gene_hub = fn(string $hub) => !empty($hubs[$hub]['transcripts']);
    foreach ($query as $lc => $rows) {
        $genes = array_values(array_filter($rows, fn($r) => $is_gene_hub($r['hub'])));
        if ($genes) $query[$lc] = $genes;
    }

    // 2. Everything else mapped to those reference genes.
    $refs = [];
    foreach ($query as $rows) foreach ($rows as $r) $refs[$r['ref_id']] = true;
    $members = [];   // ref_id \t hub => source => [accessions]
    foreach (moop_crosswalk_members($organism, array_keys($refs)) as $m) $members[$m['ref_id'] . "\t" . $m['hub']][$m['source']][] = $m['accession'];

    // 3. Descriptions of the reference genes that are features here.
    $desc = [];
    $orgdb = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/' . $organism . '/organism.sqlite';
    if (is_file($orgdb)) {
        try {
            $o = new PDO('sqlite:' . $orgdb, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
            foreach (array_chunk(array_keys($refs), 500) as $chunk) {
                $st = $o->prepare('SELECT feature_uniquename, feature_description FROM feature WHERE feature_uniquename IN ('
                                  . implode(',', array_fill(0, count($chunk), '?')) . ')');
                $st->execute($chunk);
                // A "description" with no space is an id copied in by the loader
                // (h1SMcG0008802 -> "h1SMcT0008802.2"), not a description.
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) if (preg_match('/\S\s+\S/', (string) $r['feature_description'])) $desc[$r['feature_uniquename']] = trim((string) $r['feature_description']);
            }
        } catch (PDOException $e) { /* descriptions are a nicety: the table still works without them */ }
    }

    $link = fn(string $source, string $id) => ['id' => $id] + moop_crosswalk_id_link($organism, $source, $id, $permitted);
    foreach ($typed as $t) {
        foreach ($query[mb_strtolower($t)] ?? [] as $q) {
            $key  = $q['ref_id'] . "\t" . $q['hub'];
            $ts_src = (string) ($hubs[$q['hub']]['transcripts'] ?? '');
            $base = [
                'query' => $q['accession'], 'query_source' => $q['source'], 'query_link' => $link($q['source'], $q['accession']),
                'ref_id' => $q['ref_id'], 'hub' => $q['hub'], 'ref_link' => $link($q['hub'], $q['ref_id']),
                'transcripts' => $ts_src !== '' ? array_map(fn($a) => $link($ts_src, $a), $members[$key][$ts_src] ?? []) : [],
                'always' => [],
                'description' => $desc[$q['ref_id']] ?? '',
            ];
            foreach ($always as $s) $base['always'][$s] = array_map(fn($a) => $link($s, $a), $members[$key][$s] ?? []);
            if (!$into) { $res['rows'][] = $base + ['mapped_source' => '', 'mapped' => null]; }
            foreach ($into as $s) {
                foreach ($members[$key][$s] ?? [] as $a) {
                    if (count($res['rows']) >= $max_rows) { $res['capped'] = true; return $res; }
                    $res['rows'][] = $base + ['mapped_source' => $s, 'mapped' => $link($s, $a)];
                }
            }
            if (count($res['rows']) >= $max_rows) { $res['capped'] = true; return $res; }
        }
    }
    return $res;
}

/**
 * A moop_crosswalk_translate() result as TSV -- the ONE writer for both the ID Translator's download
 * and the gene page's "Same gene in this species" download, so they never read differently:
 *
 *   organism, from_source, from_assembly, from_gene_set, from_id,
 *   reference_assembly, reference_gene_set, reference_gene, reference_transcripts,
 *   <each always-shown source>, description, to_source, to_assembly, to_gene_set, to_id
 *
 * from = the id asked about (typed into the tool, or the gene page's gene); to = the same gene in
 * another source. A source's assembly + gene set are filled only when it is a gene set on this site
 * (arrays, ESTs, old transcriptomes have none); the source code always says where an id comes from.
 * Several ids in one cell are space-separated, as on screen. No to_ columns when nothing was mapped.
 */
function moop_crosswalk_translate_tsv(string $organism, array $result, array $always, bool $with_mapped): string {
    $cfg  = moop_crosswalk_sources()[$organism]['sources'] ?? [];
    $site = fn(string $src) => !empty($cfg[$src]['gene_set']) ? [(string) ($cfg[$src]['assembly'] ?? ''), (string) $cfg[$src]['gene_set']] : ['', ''];
    $cell = fn($s) => str_replace(["\t", "\r", "\n"], ' ', (string) $s);
    $ids  = fn(array $links) => implode(' ', array_column($links, 'id'));
    $out  = implode("\t", array_map($cell, array_merge(
                ['organism', 'from_source', 'from_assembly', 'from_gene_set', 'from_id',
                 'reference_assembly', 'reference_gene_set', 'reference_gene', 'reference_transcripts'],
                $always, ['description'], $with_mapped ? ['to_source', 'to_assembly', 'to_gene_set', 'to_id'] : []))) . "\n";
    foreach ($result['rows'] as $r) {
        $row = array_merge([$organism, $r['query_source']], $site($r['query_source']), [$r['query']],
                           $site($r['hub']), [$r['ref_id'], $ids($r['transcripts'])]);
        foreach ($always as $s) $row[] = $ids($r['always'][$s] ?? []);
        $row[] = $r['description'];
        if ($with_mapped) $row = array_merge($row, [$r['mapped_source']], $site((string) $r['mapped_source']), [$r['mapped']['id'] ?? '']);
        $out .= implode("\t", array_map($cell, $row)) . "\n";
    }
    return $out;
}
