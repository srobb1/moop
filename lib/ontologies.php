<?php
/**
 * Ontologies (PLANA, BSPO, ECO, ...) as small read-only SQLite files: one per ontology.
 * Plan: notes/PLANOSPHERE_PAGE_ROSETTA_PLAN.md.
 *
 *   <cache_path>/ontologies/plana.sqlite     terms, synonyms, closure (every inferred link), meta
 *   <cache_path>/ontologies/sources/         the downloaded TSVs each file was built from
 *
 * WHERE THE DATA COMES FROM. A GitHub Action in planosphere/PAGE (ontology/) reasons over each
 * ontology's published release (relation-graph, ROBOT) and commits plain TSVs; MOOP only
 * downloads and loads them -- no OWL parsing, no reasoner, no Java here. The addresses are in
 * config/site_config.php ('ontologies'), NOT an admin setting: nobody can point a build at
 * another host through the web.
 *
 * READ-ONLY FOR VISITORS. Pages open these files with SQLITE_OPEN_READONLY. A file is only ever
 * BUILT -- to a temporary name, checked, then renamed over the old one -- by the command line
 * (scripts/build_ontologies.php) or housekeeping's background run. A build that fails or does
 * not pass its checks leaves the previous file in place. Nothing a visitor sends is ever stored.
 *
 * EVERY ROW IS CHECKED before it is stored: ids must look like PREFIX:local, fields come from a
 * fixed list, values have a length cap. Rows that fail are counted and reported, not stored.
 */

require_once __DIR__ . '/cache_paths.php';

/** An ontology id: PLANA:0000044, ECO:0001839, BSPO:0000055 (also BFO:, GO:, rdfs:subClassOf). */
const MOOP_ONTOLOGY_ID_RE   = '/^[A-Za-z][A-Za-z0-9_]{0,30}:[A-Za-z0-9_.-]{1,60}$/';
/** The only fields a terms file may carry (ontology/terms.rq in planosphere/PAGE). */
const MOOP_ONTOLOGY_FIELDS  = ['type', 'label', 'definition', 'obsolete', 'replaced_by',
                               'exact_synonym', 'related_synonym', 'narrow_synonym', 'broad_synonym'];
/** Downloads larger than this are refused (eco-closure.tsv is ~5 MB). */
const MOOP_ONTOLOGY_MAX_BYTES = 100 * 1024 * 1024;
/** Only this host may serve ontology files. */
const MOOP_ONTOLOGY_HOST    = 'raw.githubusercontent.com';

/**
 * An id as an ontology may write it -> PREFIX:local. PLANA writes "replaced by" targets as full
 * addresses (http://purl.obolibrary.org/obo/PLANA_0000113) while BSPO writes BSPO:0000021, so
 * both are accepted. Returns '' when it is neither.
 */
function moop_ontology_normalise_id(string $v): string {
    $v = trim($v);
    if (preg_match('#^https?://purl\.obolibrary\.org/obo/([A-Za-z][A-Za-z0-9]*)_([A-Za-z0-9_.-]+)$#', $v, $m)) {
        $v = $m[1] . ':' . $m[2];
    }
    return preg_match(MOOP_ONTOLOGY_ID_RE, $v) ? $v : '';
}

/** The configured ontologies: [prefix => ['name', 'terms', 'closure'?, 'release_key', 'search']]. */
function moop_ontology_sources(): array {
    $out = [];
    foreach (ConfigManager::getInstance()->getArray('ontologies', []) as $prefix => $o) {
        if (!is_array($o) || !preg_match('/^[A-Z][A-Z0-9]{1,15}$/', (string) $prefix) || empty($o['terms'])) continue;
        $out[$prefix] = $o + ['name' => $prefix, 'closure' => '', 'release_key' => strtolower($prefix), 'search' => false];
    }
    return $out;
}

function moop_ontology_dir(): string {
    return moop_cache_root() . '/ontologies';
}

/** The built file for one ontology (whether or not it exists yet). */
function moop_ontology_file(string $prefix): string {
    return moop_ontology_dir() . '/' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $prefix)) . '.sqlite';
}

/** Open one ontology read-only, or null when it has not been built. */
function moop_ontology_open(string $prefix): ?PDO {
    $file = moop_ontology_file($prefix);
    if (!is_file($file)) return null;
    try {
        $db = new PDO('sqlite:' . $file, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}

/** What a built file says about itself: release, built_at, row counts... ([] if not built). */
function moop_ontology_meta(string $prefix): array {
    $db = moop_ontology_open($prefix);
    if (!$db) return [];
    return $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Download one source file over HTTPS from the one allowed host, size-capped, to $dest.
 * Returns '' on success, else the reason.
 */
function moop_ontology_download(string $url, string $dest): string {
    $p = parse_url($url);
    if (($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') !== MOOP_ONTOLOGY_HOST) {
        return "refused: $url is not https://" . MOOP_ONTOLOGY_HOST;
    }
    $fh = @fopen($dest, 'wb');
    if (!$fh) return "cannot write $dest";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 300, CURLOPT_FAILONERROR => true, CURLOPT_USERAGENT => 'MOOP ontology loader',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_MAXFILESIZE => MOOP_ONTOLOGY_MAX_BYTES,
    ]);
    $ok  = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fh);
    if (!$ok) { @unlink($dest); return "download failed: $url ($err)"; }
    if (filesize($dest) > MOOP_ONTOLOGY_MAX_BYTES) { @unlink($dest); return "refused: $url is over the size cap"; }
    return '';
}

/**
 * Build one ontology's SQLite file from local TSV files (already downloaded or, in tests, made).
 *
 *   $files = ['terms' => path, 'closure' => path or '', 'release' => '2023-03-13']
 *
 * Builds <file>.tmp-<pid>, checks it, renames it over <file>. Returns
 * ['ok' => bool, 'error' => string, 'counts' => [...], 'skipped' => n].
 * $out_file is for tests; normally moop_ontology_file($prefix).
 */
function moop_ontology_build_from_files(string $prefix, array $files, ?string $out_file = null): array {
    $out_file = $out_file ?? moop_ontology_file($prefix);
    $dir = dirname($out_file);
    if (!is_dir($dir) && !moop_ensure_cache_dir($dir) && !@mkdir($dir, 0775, true)) {
        return ['ok' => false, 'error' => "cannot create $dir", 'counts' => [], 'skipped' => 0];
    }
    $tmp = $out_file . '.tmp-' . getmypid();
    @unlink($tmp);
    $skipped = 0;
    try {
        $db = new PDO('sqlite:' . $tmp);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF;');
        // type: 'class' (an anatomy term, evidence code...) or 'relation' (BFO:0000050 "part of").
        // Files made before the type row existed load with every term as a class.
        $db->exec('CREATE TABLE term (id TEXT PRIMARY KEY, type TEXT NOT NULL DEFAULT \'class\', label TEXT,
                                      label_lc TEXT, definition TEXT, obsolete INTEGER NOT NULL DEFAULT 0, replaced_by TEXT)');
        $db->exec('CREATE TABLE synonym (term_id TEXT NOT NULL, text TEXT NOT NULL, text_lc TEXT NOT NULL,
                                         kind TEXT NOT NULL, UNIQUE (term_id, text_lc, kind))');
        $db->exec('CREATE TABLE closure (subject TEXT NOT NULL, relation TEXT NOT NULL, object TEXT NOT NULL)');
        $db->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)');
        $db->beginTransaction();

        // ---- terms: term <TAB> field <TAB> value, one value per row ----
        $terms = [];
        $fh = @fopen($files['terms'] ?? '', 'r');
        if (!$fh) throw new RuntimeException('cannot read the terms file');
        $header = fgets($fh);
        if (trim((string) $header) !== "term\tfield\tvalue") throw new RuntimeException('terms file header is not "term field value"');
        $syn = $db->prepare('INSERT OR IGNORE INTO synonym (term_id, text, text_lc, kind) VALUES (?, ?, ?, ?)');
        while (($line = fgets($fh)) !== false) {
            $parts = explode("\t", rtrim($line, "\r\n"));
            if (count($parts) !== 3) { $skipped++; continue; }
            [$id, $field, $value] = $parts;
            $value = trim($value);
            if (!preg_match(MOOP_ONTOLOGY_ID_RE, $id) || !in_array($field, MOOP_ONTOLOGY_FIELDS, true)
                || $value === '' || strlen($value) > 4000) { $skipped++; continue; }
            $terms[$id] = $terms[$id] ?? ['type' => 'class', 'label' => null, 'definition' => null, 'obsolete' => 0, 'replaced_by' => null];
            if (substr($field, -8) === '_synonym') {
                $syn->execute([$id, $value, mb_strtolower($value), substr($field, 0, -8)]);
            } elseif ($field === 'type') {
                if ($value === 'class' || $value === 'relation') $terms[$id]['type'] = $value; else $skipped++;
            } elseif ($field === 'obsolete') {
                $terms[$id]['obsolete'] = strtolower($value) === 'true' ? 1 : 0;
            } elseif ($field === 'replaced_by') {
                $to = moop_ontology_normalise_id($value);
                if ($to !== '') $terms[$id]['replaced_by'] = $to; else $skipped++;
            } elseif ($terms[$id][$field] === null) {   // first label / definition wins
                $terms[$id][$field] = $value;
            }
        }
        fclose($fh);
        $ins = $db->prepare('INSERT INTO term (id, type, label, label_lc, definition, obsolete, replaced_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($terms as $id => $t) {
            $ins->execute([$id, $t['type'], $t['label'], $t['label'] === null ? null : mb_strtolower($t['label']),
                           $t['definition'], $t['obsolete'], $t['replaced_by']]);
        }

        // ---- closure: subject <TAB> relation <TAB> object (optional) ----
        if (!empty($files['closure'])) {
            $fh = @fopen($files['closure'], 'r');
            if (!$fh) throw new RuntimeException('cannot read the closure file');
            if (trim((string) fgets($fh)) !== "subject\trelation\tobject") throw new RuntimeException('closure file header is not "subject relation object"');
            $cl = $db->prepare('INSERT INTO closure (subject, relation, object) VALUES (?, ?, ?)');
            while (($line = fgets($fh)) !== false) {
                $parts = explode("\t", rtrim($line, "\r\n"));
                if (count($parts) !== 3 || !preg_match(MOOP_ONTOLOGY_ID_RE, $parts[0])
                    || !preg_match(MOOP_ONTOLOGY_ID_RE, $parts[1]) || !preg_match(MOOP_ONTOLOGY_ID_RE, $parts[2])) {
                    $skipped++; continue;
                }
                $cl->execute($parts);
            }
            fclose($fh);
        }

        $counts = [
            'terms'    => (int) $db->query('SELECT COUNT(*) FROM term')->fetchColumn(),
            'labelled' => (int) $db->query('SELECT COUNT(*) FROM term WHERE label IS NOT NULL')->fetchColumn(),
            'synonyms' => (int) $db->query('SELECT COUNT(*) FROM synonym')->fetchColumn(),
            'closure'  => (int) $db->query('SELECT COUNT(*) FROM closure')->fetchColumn(),
            'obsolete' => (int) $db->query('SELECT COUNT(*) FROM term WHERE obsolete = 1')->fetchColumn(),
            'relations'=> (int) $db->query("SELECT COUNT(*) FROM term WHERE type = 'relation'")->fetchColumn(),
        ];
        $meta = $db->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach (['prefix' => $prefix, 'release' => (string) ($files['release'] ?? ''),
                  'releases_sha' => (string) ($files['releases_sha'] ?? ''),
                  'built_at' => date('c'), 'skipped_rows' => (string) $skipped] + array_map('strval', $counts) as $k => $v) {
            $meta->execute([$k, $v]);
        }
        $db->commit();
        $db->exec('CREATE INDEX term_label ON term (label_lc)');
        $db->exec('CREATE INDEX syn_text ON synonym (text_lc)');
        $db->exec('CREATE INDEX syn_term ON synonym (term_id)');
        $db->exec('CREATE INDEX cl_object ON closure (object, relation)');
        $db->exec('CREATE INDEX cl_subject ON closure (subject)');
        $db->exec('CREATE INDEX cl_srel ON closure (subject, relation, object)');
        // Direct links (the ontology browser's tree and term pages, 2026-11-11). The closure holds
        // every INFERRED link (transitively); a link X -R-> Y is direct when no Z sits between them
        // by the same relation (X -R-> Z -R-> Y). Worked out once here, not per click: GO is ~45k
        // terms and the browser reads parents/children constantly.
        $db->exec('CREATE TABLE edge (subject TEXT NOT NULL, relation TEXT NOT NULL, object TEXT NOT NULL)');
        $db->exec("INSERT INTO edge (subject, relation, object)
                   SELECT c.subject, c.relation, c.object FROM closure c
                    WHERE c.subject <> c.object
                      AND NOT EXISTS (SELECT 1 FROM closure a JOIN closure b
                                        ON b.subject = a.object AND b.relation = c.relation AND b.object = c.object
                                       WHERE a.subject = c.subject AND a.relation = c.relation
                                         AND a.object <> c.object AND a.object <> c.subject)");
        $db->exec('CREATE INDEX edge_object ON edge (object, relation)');
        $db->exec('CREATE INDEX edge_subject ON edge (subject, relation)');
        $db->exec("INSERT INTO meta (key, value) VALUES ('edges', (SELECT COUNT(*) FROM edge))");
        $db->exec('ANALYZE');

        // The checks a file must pass before it replaces the live one.
        if ($counts['labelled'] === 0) throw new RuntimeException('no labelled terms -- refusing to replace the live file');
        if (!empty($files['closure']) && $counts['closure'] === 0) throw new RuntimeException('closure file gave no links');
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

/**
 * Download one configured ontology's files and build it. The CLI and housekeeping call this.
 * Returns the build result plus 'release'.
 */
function moop_ontology_build(string $prefix): array {
    $src = moop_ontology_sources()[$prefix] ?? null;
    if (!$src) return ['ok' => false, 'error' => "no ontology '$prefix' configured", 'counts' => [], 'skipped' => 0];
    $sdir = moop_ontology_dir() . '/sources';
    if (!is_dir($sdir) && !moop_ensure_cache_dir($sdir)) return ['ok' => false, 'error' => "cannot create $sdir", 'counts' => [], 'skipped' => 0];

    $base  = strtolower($prefix);
    $files = ['terms' => "$sdir/$base-terms.tsv", 'closure' => '', 'release' => ''];
    foreach (['terms' => $src['terms'], 'closure' => $src['closure'] ?? '', 'releases' => $src['releases'] ?? ''] as $kind => $url) {
        if ($url === '') continue;
        $dest = "$sdir/$base-$kind." . ($kind === 'releases' ? 'txt' : 'tsv');
        $err  = moop_ontology_download($url, "$dest.part");
        if ($err !== '') return ['ok' => false, 'error' => $err, 'counts' => [], 'skipped' => 0];
        @rename("$dest.part", $dest);
        @chmod($dest, 0664);
        if ($kind === 'closure') $files['closure'] = $dest;
        if ($kind === 'releases') {
            $files['releases_sha'] = (string) @hash_file('sha256', $dest);
            foreach (file($dest, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                [$k, $v] = array_pad(preg_split('/\s+/', trim($line), 2), 2, '');
                if ($k === $src['release_key'] && preg_match('/^[0-9-]{4,20}$/', $v)) $files['release'] = $v;
            }
        }
    }
    $r = moop_ontology_build_from_files($prefix, $files);
    $r['release'] = $files['release'];
    return $r;
}

/**
 * Search box: terms whose name or synonym matches $q, best first, from the given ontologies.
 *
 * Ranking (lower is better): 0 exact name, 1 exact EXACT synonym, 2 exact other synonym,
 * 3 name starts with, 4 synonym starts with, 5 name contains, 6 synonym contains.
 * Ties: shorter name first. Obsolete terms are left out. A plural with no hits retries as the
 * singular ("neurons" -> "neuron"). Each result says how it matched, so a visitor learns the
 * official name: ['id', 'label', 'ontology', 'rank', 'matched' => synonym text or '', 'kind'].
 */
function moop_ontology_search(string $q, array $prefixes, int $limit = 15): array {
    $q = mb_strtolower(trim(preg_replace('/\s+/', ' ', $q)));
    if (mb_strlen($q) < 2 || mb_strlen($q) > 100) return [];
    $results = moop_ontology_search_once($q, $prefixes, $limit);
    if (!$results && mb_strlen($q) > 3 && substr($q, -1) === 's') {
        $results = moop_ontology_search_once(mb_substr($q, 0, -1), $prefixes, $limit);
    }
    return $results;
}

function moop_ontology_search_once(string $q, array $prefixes, int $limit): array {
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $best = [];
    foreach ($prefixes as $prefix) {
        $db = moop_ontology_open($prefix);
        if (!$db) continue;
        // Only this ontology's own terms (plana.sqlite also holds the BFO/GO/BSPO terms it imports).
        $own = $prefix . ':%';
        $sql = "SELECT id, label, rank, matched, kind FROM (
                  SELECT t.id, t.label, CASE WHEN t.label_lc = :q THEN 0
                                             WHEN t.label_lc LIKE :pre ESCAPE '\\' THEN 3 ELSE 5 END AS rank,
                         '' AS matched, '' AS kind
                    FROM term t WHERE t.obsolete = 0 AND t.type = 'class' AND t.id LIKE :own AND t.label_lc LIKE :any ESCAPE '\\'
                  UNION ALL
                  SELECT t.id, t.label, CASE WHEN s.text_lc = :q AND s.kind = 'exact' THEN 1
                                             WHEN s.text_lc = :q THEN 2
                                             WHEN s.text_lc LIKE :pre ESCAPE '\\' THEN 4 ELSE 6 END,
                         s.text, s.kind
                    FROM synonym s JOIN term t ON t.id = s.term_id
                   WHERE t.obsolete = 0 AND t.type = 'class' AND t.id LIKE :own AND s.text_lc LIKE :any ESCAPE '\\')
                ORDER BY rank, length(label) LIMIT :lim";
        $st = $db->prepare($sql);
        $st->bindValue(':q', $q);
        $st->bindValue(':pre', $like . '%');
        $st->bindValue(':any', '%' . $like . '%');
        $st->bindValue(':own', $own);
        $st->bindValue(':lim', $limit * 3, PDO::PARAM_INT);
        $st->execute();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['label'] === null) continue;
            $id = $r['id'];
            if (!isset($best[$id]) || $r['rank'] < $best[$id]['rank']) {
                $best[$id] = ['id' => $id, 'label' => $r['label'], 'ontology' => $prefix, 'rank' => (int) $r['rank'],
                              'matched' => $r['matched'], 'kind' => $r['kind']];
            }
        }
    }
    uasort($best, fn($a, $b) => [$a['rank'], mb_strlen($a['label'])] <=> [$b['rank'], mb_strlen($b['label'])]);
    return array_slice(array_values($best), 0, $limit);
}

/**
 * Every term related to $term_id by one of $relations (closure), plus the term itself and every
 * subclass of it. For "expressed in any part of the eye": moop_ontology_expand('PLANA', 'PLANA:0000036',
 * ['BFO:0000050']). Returns a list of ids.
 */
function moop_ontology_expand(string $prefix, string $term_id, array $relations): array {
    $db = moop_ontology_open($prefix);
    if (!$db || !preg_match(MOOP_ONTOLOGY_ID_RE, $term_id)) return [$term_id];
    $rel = array_values(array_unique(array_merge(['rdfs:subClassOf'], array_filter($relations,
                        fn($r) => preg_match(MOOP_ONTOLOGY_ID_RE, $r)))));
    $st = $db->prepare('SELECT DISTINCT subject FROM closure WHERE object = ? AND relation IN ('
                       . implode(',', array_fill(0, count($rel), '?')) . ')');
    $st->execute(array_merge([$term_id], $rel));
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    array_unshift($ids, $term_id);
    return array_values(array_unique($ids));
}

/**
 * A term's or relationship's name from the given ontologies, e.g. moop_ontology_label('BFO:0000050',
 * ['PLANA']) -> "part of" (PLANA imports the RO relations with their names). '' if unknown.
 * "is a" is OWL's own relationship (rdfs:subClassOf), not defined by any ontology -- the one name
 * that is built in.
 */
function moop_ontology_label(string $id, array $prefixes): string {
    if ($id === 'rdfs:subClassOf') return 'is a';
    foreach ($prefixes as $prefix) {
        $db = moop_ontology_open($prefix);
        if (!$db) continue;
        $st = $db->prepare('SELECT label FROM term WHERE id = ?');
        $st->execute([$id]);
        $l = $st->fetchColumn();
        if ($l !== false && $l !== null) return (string) $l;
    }
    return '';
}

/** Where an admin's "Rebuild now" leaves its request for the background run. */
function moop_ontology_rebuild_marker(): string {
    return moop_ontology_dir() . '/rebuild.requested';
}

/**
 * Keep the built ontologies current. Called by housekeeping's background run.
 *
 * OPT-IN PER SITE: only ontologies that have been built here at least once are refreshed (plus
 * all configured ones when an admin pressed "Rebuild now"). A site that never built one -- e.g.
 * cuttingclass -- is left alone, and pays nothing.
 *
 * Refreshing costs one small download per source (releases.txt, a few bytes): an ontology is
 * rebuilt only when that file changed since it was built -- a new release of the ontology, or a
 * new recipe in the PAGE repo's Action -- or when a rebuild was requested. Each ontology is
 * rebuilt on its own; a failure leaves its previous file in place and is reported.
 *
 * Returns [prefix => ['state' => built|current|failed|not_built, 'release', 'built_at', 'error', 'counts']].
 */
function moop_ontology_refresh(bool $force = false): array {
    $marker = moop_ontology_rebuild_marker();
    $force  = $force || is_file($marker);
    $out    = [];
    $seen   = [];   // releases.txt fingerprints, fetched once per address
    foreach (moop_ontology_sources() as $prefix => $src) {
        $meta = moop_ontology_meta($prefix);
        if (!$meta && !$force) { $out[$prefix] = ['state' => 'not_built']; continue; }
        $rebuild = $force || !$meta;
        if (!$rebuild && !empty($src['releases'])) {
            $url = $src['releases'];
            if (!array_key_exists($url, $seen)) {
                $tmp = moop_ontology_dir() . '/releases.check-' . getmypid();
                $seen[$url] = moop_ontology_download($url, $tmp) === '' ? (string) hash_file('sha256', $tmp) : null;
                @unlink($tmp);
            }
            // Unreachable (null): keep the current file, report nothing new -- try again next week.
            $rebuild = $seen[$url] !== null && $seen[$url] !== ($meta['releases_sha'] ?? '');
        }
        if (!$rebuild) {
            $out[$prefix] = ['state' => 'current', 'release' => $meta['release'] ?? '', 'built_at' => $meta['built_at'] ?? ''];
            continue;
        }
        $r = moop_ontology_build($prefix);
        $m = moop_ontology_meta($prefix);
        $out[$prefix] = ['state' => $r['ok'] ? 'built' : 'failed', 'error' => $r['error'], 'counts' => $r['counts'],
                         'release' => $m['release'] ?? '', 'built_at' => $m['built_at'] ?? ''];
    }
    if ($force) @unlink($marker);
    return $out;
}

/** Prefixes of the configured ontologies with this purpose (anatomy, phenotype, evidence, names). */
function moop_ontology_by_purpose(string $purpose, bool $search_only = false): array {
    return array_keys(array_filter(moop_ontology_sources(), fn($o) => ($o['purpose'] ?? '') === $purpose
                                   && (!$search_only || !empty($o['search']))));
}

/**
 * The relationships a search of this purpose offers, [id => name], from the configured rows of
 * that purpose; names come from the ontologies themselves ("is a" is the one built in).
 */
function moop_ontology_relations(string $purpose): array {
    $prefixes = moop_ontology_by_purpose($purpose);
    $out = [];
    foreach ($prefixes as $p) {
        foreach ((array) (moop_ontology_sources()[$p]['relations'] ?? []) as $rel) {
            if (!isset($out[$rel])) $out[$rel] = moop_ontology_label($rel, $prefixes) ?: $rel;
        }
    }
    return $out;
}

/**
 * Expand a term within a purpose: the term, its subclasses, and everything related to it by
 * $relations, at any depth -- from EVERY ontology of that purpose that has a link tree. A BSPO
 * term ("dorsal side") expands through PLANA's tree, which includes the BSPO terms PLANA imports.
 * Only relationships the purpose offers are used. Returns a list of ids.
 */
function moop_ontology_expand_in(string $purpose, string $term_id, array $relations): array {
    $allowed = array_intersect($relations, array_keys(moop_ontology_relations($purpose)));
    $ids = [$term_id];
    foreach (moop_ontology_by_purpose($purpose) as $p) {
        if (empty(moop_ontology_sources()[$p]['closure'])) continue;
        $ids = array_merge($ids, moop_ontology_expand($p, $term_id, $allowed));
    }
    return array_values(array_unique($ids));
}

/** Names for many ids at once, [id => name], from the given ontologies (first that knows it). */
function moop_ontology_labels(array $ids, array $prefixes): array {
    $out  = [];
    $want = array_values(array_unique(array_filter($ids, fn($i) => preg_match(MOOP_ONTOLOGY_ID_RE, (string) $i))));
    foreach ($prefixes as $p) {
        if (!$want) break;
        $db = moop_ontology_open($p);
        if (!$db) continue;
        foreach (array_chunk($want, 500) as $chunk) {
            $st = $db->prepare('SELECT id, label FROM term WHERE label IS NOT NULL AND id IN ('
                               . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $id => $l) $out[$id] = $l;
        }
        $want = array_values(array_diff($want, array_keys($out)));
    }
    return $out;
}

/**
 * A small picture for an ontology term, if the site has one: images/term_icons/<ID with ":" as
 * "_">.png (PLANA:0004504 -> PLANA_0004504.png). Named by TERM ID, never by label -- labels
 * change between releases and other organisms' curations bring their own terms; adding an icon
 * is dropping in a file (site data, under images/, so the site-data backup keeps it).
 * Planosphere's PAGE icons (life stage, specimen, evidence) are the first set.
 * Returns an <img> with the label as alt/title, or '' -- callers always print the label too,
 * so a missing icon changes nothing else.
 */
function moop_term_icon(string $id, string $label = ''): string {
    static $have = null;
    if ($have === null) {
        $have = [];
        $dir  = rtrim((string) ConfigManager::getInstance()->getPath('absolute_images_path'), '/') . '/term_icons';
        foreach (glob($dir . '/*.png') ?: [] as $f) $have[basename($f, '.png')] = true;
    }
    $file = str_replace(':', '_', $id);
    if (!preg_match('/^[A-Za-z]+_[0-9]+$/', $file) || !isset($have[$file])) return '';
    $text = htmlspecialchars($label !== '' ? $label : $id);
    return '<img src="' . htmlspecialchars(moop_url('images/term_icons/' . $file . '.png')) . '" class="moop-term-icon" alt="'
         . $text . '" title="' . $text . '" width="22" height="22">';
}
