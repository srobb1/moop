<?php
/**
 * PAGE curations -- where genes are expressed, by anatomy term, from published papers -- as one
 * read-only SQLite file per organism:   organisms/<organism>/anatomy_expression.sqlite
 * Plan: notes/PLANOSPHERE_PAGE_ROSETTA_PLAN.md (step 2). Not "expression": that is MOOP's RNA-seq
 * heatmap tool (HOW MUCH); this is WHERE.
 *
 * SOURCE: the 9-column curation table in the PAGE repository (curation/OUTPUT/
 * term_annotations_*.txt), set per organism in config/site_config.php ('anatomy_expression').
 * Publication titles come from PubMed (E-utilities) at build time; if PubMed is unreachable the
 * build still succeeds and results show the PMID.
 *
 * NEVER REWRITTEN: rows are stored as curated. What the loader cannot use is put on a REVIEW
 * list (shown to admins), never silently dropped or "fixed": unknown or obsolete terms, evidence
 * codes that are not ECO, accessions with stray punctuation, a PMID in the Accession column,
 * duplicates (same curation entered twice -- compared after normalising the date).
 *
 * READ-ONLY for visitors; built only by the CLI or housekeeping (same rules as lib/ontologies.php:
 * temp file, checks, rename; a failed build leaves the previous file).
 */

require_once __DIR__ . '/ontologies.php';

/**
 * Lower-case and drop accents, for searching names: "Sánchez Alvarado" and "sanchez alvarado"
 * match. An explicit table (this server has no intl extension, and iconv's transliteration
 * depends on the locale -- it gave "S?nchez").
 */
function moop_page_fold(string $s): string {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (['a' => 'àáâãäåāăą', 'c' => 'çćĉċč', 'd' => 'ďđ', 'e' => 'èéêëēĕėęě', 'g' => 'ĝğġģ', 'h' => 'ĥħ',
                  'i' => 'ìíîïĩīĭįı', 'j' => 'ĵ', 'k' => 'ķ', 'l' => 'ĺļľŀł', 'n' => 'ñńņňŉ', 'o' => 'òóôõöøōŏő',
                  'r' => 'ŕŗř', 's' => 'śŝşš', 't' => 'ţťŧ', 'u' => 'ùúûüũūŭůűų', 'w' => 'ŵ', 'y' => 'ýÿŷ', 'z' => 'źżž'] as $to => $from) {
            foreach (preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY) as $ch) $map[$ch] = $to;
        }
        $map += ['ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'þ' => 'th'];
    }
    return strtr(mb_strtolower($s), $map);
}

const MOOP_PAGE_COLUMNS = ['Accession', 'Term', 'Experimental Evidence', 'Biotype', 'Life Cycle Stage',
                           'Specimen', 'Curator (ORCID)', 'Pubmed ID', 'Date'];

/** Organisms with PAGE curations configured: [organism => ['curations' => url]]. */
function moop_page_sources(): array {
    $out = [];
    foreach (ConfigManager::getInstance()->getArray('anatomy_expression', []) as $org => $s) {
        if (is_array($s) && !empty($s['curations']) && preg_match('/^[A-Za-z0-9_.-]+$/', (string) $org)) $out[$org] = $s;
    }
    return $out;
}

function moop_page_file(string $organism): string {
    return rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/'
         . preg_replace('/[^A-Za-z0-9_.-]/', '', $organism) . '/anatomy_expression.sqlite';
}

function moop_page_open(string $organism): ?PDO {
    $f = moop_page_file($organism);
    if (!is_file($f)) return null;
    try {
        $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}

function moop_page_meta(string $organism): array {
    $db = moop_page_open($organism);
    return $db ? $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
}

/** "2021-06-16" or "Wed Jun 16 18:34:23 2021" -> "2021-06-16"; anything else -> ''. */
function moop_page_date(string $d): string {
    $d = trim($d);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
    if (preg_match('/^[A-Z][a-z]{2} ([A-Z][a-z]{2}) +(\d{1,2}) [\d:]{8} (\d{4})$/', $d, $m)) {
        $t = strtotime("$m[2] $m[1] $m[3]");
        return $t ? date('Y-m-d', $t) : '';
    }
    return '';
}

/** "PLANA:0000016: pharynx (Synonym = ..." -> "PLANA:0000016"; '' if no id. */
function moop_page_term_id(string $cell): string {
    return preg_match('/^\s*([A-Za-z][A-Za-z0-9_]*:[A-Za-z0-9_.-]+?)(?::|\s|$)/', $cell, $m) ? $m[1] : '';
}

/**
 * Build one organism's file from a local curation table. $known_terms: [id => ['obsolete'=>0|1,
 * 'replaced_by'=>id|null]] from the anatomy ontologies (empty = skip term checks, e.g. tests).
 * $pubs: [pmid => ['title','authors','journal','year']]. Returns ['ok','error','counts','review'].
 */
function moop_page_build_from_file(string $organism, string $table, array $known_terms, array $pubs = [],
                                   string $source = '', ?string $out_file = null): array {
    $out_file = $out_file ?? moop_page_file($organism);
    $tmp = $out_file . '.tmp-' . getmypid();
    @unlink($tmp);
    $review = [];   // kind => [detail => count]
    $note = function (string $kind, string $detail) use (&$review) { $review[$kind][$detail] = ($review[$kind][$detail] ?? 0) + 1; };
    try {
        $fh = @fopen($table, 'r');
        if (!$fh) throw new RuntimeException('cannot read the curation table');
        $header = array_map('trim', explode("\t", rtrim((string) fgets($fh), "\r\n")));
        if ($header !== MOOP_PAGE_COLUMNS) throw new RuntimeException('the curation table does not have the 9 PAGE columns');

        $db = new PDO('sqlite:' . $tmp);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF;');
        $db->exec('CREATE TABLE curation (id INTEGER PRIMARY KEY, accession TEXT NOT NULL, accession_lc TEXT NOT NULL,
                     term_id TEXT NOT NULL, evidence_id TEXT, biotype_id TEXT, stage_id TEXT, specimen_id TEXT,
                     curator TEXT, pmid TEXT, curated_on TEXT, copies INTEGER NOT NULL DEFAULT 1)');
        // authors = short form for display ("Currie KW et al."); authors_all = every author, for searching.
        $db->exec('CREATE TABLE publication (pmid TEXT PRIMARY KEY, title TEXT, authors TEXT, authors_all TEXT, journal TEXT, year TEXT,
                                             search_text TEXT)');   // folded title + all authors + journal + year
        $db->exec('CREATE TABLE review (kind TEXT NOT NULL, detail TEXT NOT NULL, rows INTEGER NOT NULL)');
        $db->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)');
        $db->beginTransaction();
        $ins = $db->prepare('INSERT INTO curation (accession, accession_lc, term_id, evidence_id, biotype_id, stage_id,
                               specimen_id, curator, pmid, curated_on) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $bump = $db->prepare('UPDATE curation SET copies = copies + 1 WHERE id = ?');
        $seen = [];   // the same curation entered twice (date normalised) -> one row, copies counted
        $rows = 0;
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;
            $c = explode("\t", $line);
            if (trim($c[0]) === 'Accession') { $note('extra header line', 'a second header row in the table'); continue; }
            if (count($c) !== 9) { $note('wrong number of columns', count($c) . ' columns'); continue; }
            $rows++;
            [$acc, $term_cell, $eco, $bio, $stage, $spec, $curator, $pmid, $date] = array_map('trim', $c);
            $term = moop_page_term_id($term_cell);
            if ($acc === '' || $term === '') { $note('no accession or term', "row $rows"); continue; }
            if (preg_match('/[,;:\s]/', $acc))          $note('accession has punctuation or a space', $acc);
            if (ctype_digit($acc) && $acc === $pmid)    $note('accession is the paper\'s PMID (no sequence)', "$acc");
            if (!preg_match('/^ECO:\d+$/', $eco))       $note('evidence is not an ECO code', $eco);
            if ($known_terms) {
                $k = $known_terms[$term] ?? null;
                if ($k === null)                        $note('term not in the anatomy ontologies', $term);
                elseif (!empty($k['obsolete']))         $note($k['replaced_by'] ? 'obsolete term (search follows its replacement)' : 'obsolete term with no replacement',
                                                              $term . ($k['replaced_by'] ? ' -> ' . $k['replaced_by'] : ''));
            }
            $day = moop_page_date($date);
            if ($day === '') $note('date not understood', $date);
            $key = implode("\t", [$acc, $term, $eco, $bio, $stage, $spec, $curator, $pmid]);
            if (isset($seen[$key])) { $bump->execute([$seen[$key]]); $note('duplicate curation (kept once)', "$acc $term PMID $pmid"); continue; }
            $ins->execute([$acc, mb_strtolower($acc), $term, $eco, $bio, $stage, $spec, $curator, $pmid, $day]);
            $seen[$key] = (int) $db->lastInsertId();
        }
        fclose($fh);

        $pins = $db->prepare('INSERT OR REPLACE INTO publication (pmid, title, authors, authors_all, journal, year, search_text) VALUES (?,?,?,?,?,?,?)');
        foreach ($pubs as $pmid => $p) {
            $all = $p['authors_all'] ?? ($p['authors'] ?? '');
            $pins->execute([(string) $pmid, $p['title'] ?? '', $p['authors'] ?? '', $all, $p['journal'] ?? '', $p['year'] ?? '',
                            moop_page_fold(implode(' ', [$p['title'] ?? '', $all, $p['journal'] ?? '', $p['year'] ?? '']))]);
        }
        $rins = $db->prepare('INSERT INTO review (kind, detail, rows) VALUES (?,?,?)');
        foreach ($review as $kind => $details) foreach ($details as $d => $n) $rins->execute([$kind, (string) $d, $n]);

        $counts = [
            'rows_read'    => $rows,
            'curations'    => (int) $db->query('SELECT COUNT(*) FROM curation')->fetchColumn(),
            'accessions'   => (int) $db->query('SELECT COUNT(DISTINCT accession_lc) FROM curation')->fetchColumn(),
            'terms'        => (int) $db->query('SELECT COUNT(DISTINCT term_id) FROM curation')->fetchColumn(),
            'publications' => (int) $db->query('SELECT COUNT(DISTINCT pmid) FROM curation')->fetchColumn(),
            'titled'       => (int) $db->query('SELECT COUNT(*) FROM publication')->fetchColumn(),
        ];
        $meta = $db->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach (['organism' => $organism, 'source' => $source, 'built_at' => date('c')] + array_map('strval', $counts) as $k => $v) $meta->execute([$k, $v]);
        $db->commit();
        foreach (['term_id', 'accession_lc', 'pmid', 'evidence_id', 'stage_id'] as $col) $db->exec("CREATE INDEX c_$col ON curation ($col)");
        $db->exec('ANALYZE');
        if ($counts['curations'] === 0) throw new RuntimeException('no curations -- refusing to replace the live file');
        if ((string) $db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('integrity check failed');
        $db = null;
        @chmod($tmp, 0664);
        if (!@rename($tmp, $out_file)) throw new RuntimeException("cannot move the new file into place: $out_file");
        return ['ok' => true, 'error' => '', 'counts' => $counts, 'review' => $review];
    } catch (Throwable $e) {
        $db = null;
        @unlink($tmp);
        return ['ok' => false, 'error' => $e->getMessage(), 'counts' => [], 'review' => $review];
    }
}

/**
 * Titles for PMIDs from PubMed (NCBI E-utilities esummary), 200 per request. Network: build time
 * only (CLI / housekeeping), never in a page request. Returns what it could get; [] if unreachable.
 */
function moop_page_fetch_publications(array $pmids): array {
    $pmids = array_values(array_unique(array_filter($pmids, 'ctype_digit')));
    $out = [];
    foreach (array_chunk($pmids, 200) as $chunk) {
        $ch = curl_init('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['db' => 'pubmed', 'retmode' => 'json',
                            'tool' => 'MOOP', 'id' => implode(',', $chunk)]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'MOOP PAGE loader',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $d = $code === 200 ? json_decode((string) $body, true) : null;
        foreach (($d['result']['uids'] ?? []) as $uid) {
            $r = $d['result'][$uid] ?? [];
            $authors = array_map(fn($a) => (string) ($a['name'] ?? ''), (array) ($r['authors'] ?? []));
            $out[(string) $uid] = [
                'title'   => mb_substr(trim((string) ($r['title'] ?? '')), 0, 500),
                'authors' => mb_substr(count($authors) > 3 ? $authors[0] . ' et al.' : implode(', ', $authors), 0, 200),
                'authors_all' => mb_substr(implode(', ', $authors), 0, 2000),
                'journal' => mb_substr((string) ($r['source'] ?? ''), 0, 120),
                'year'    => preg_match('/\d{4}/', (string) ($r['pubdate'] ?? ''), $m) ? $m[0] : '',
            ];
        }
        usleep(400000);   // NCBI asks for at most 3 requests a second without a key
    }
    return $out;
}

/** Download, check and build one organism's file (the CLI and housekeeping call this). */
function moop_page_build(string $organism): array {
    $src = moop_page_sources()[$organism] ?? null;
    if (!$src) return ['ok' => false, 'error' => "no PAGE curations configured for $organism", 'counts' => [], 'review' => []];
    $sdir = moop_ontology_dir() . '/sources';
    if (!is_dir($sdir) && !moop_ensure_cache_dir($sdir)) return ['ok' => false, 'error' => "cannot create $sdir", 'counts' => [], 'review' => []];
    $local = "$sdir/page-" . preg_replace('/[^A-Za-z0-9_.-]/', '', $organism) . '.tsv';
    $err = moop_ontology_download($src['curations'], "$local.part");
    if ($err !== '') return ['ok' => false, 'error' => $err, 'counts' => [], 'review' => []];
    rename("$local.part", $local);

    // Terms the anatomy ontologies know, with obsolete / replaced-by, for the review list.
    $known = [];
    foreach (moop_ontology_by_purpose('anatomy') as $p) {
        $db = moop_ontology_open($p);
        if (!$db) continue;
        foreach ($db->query("SELECT id, obsolete, replaced_by FROM term WHERE type = 'class'") as $r) {
            $known[$r['id']] = ['obsolete' => (int) $r['obsolete'], 'replaced_by' => $r['replaced_by']];
        }
    }
    $pmids = [];
    foreach (file($local, FILE_IGNORE_NEW_LINES) ?: [] as $i => $line) {
        $c = explode("\t", $line);
        if ($i > 0 && isset($c[7]) && ctype_digit(trim($c[7]))) $pmids[] = trim($c[7]);
    }
    $pubs = moop_page_fetch_publications($pmids);
    return moop_page_build_from_file($organism, $local, $known, $pubs, $src['curations']);
}

/**
 * Curations whose term is in $term_ids (an expanded term set; null = any term), for one organism,
 * newest papers first. $filters: ['evidence' => [ECO ids]] (an expanded set), ['stage' => [PLANA
 * ids]], ['pmid' => one paper] -- a paper alone is a search too (search by publication).
 * Obsolete terms are followed: a curation on a term whose replacement is in the set is included
 * (the ids to look for are the set plus every term replaced by one of them).
 * Returns ['rows' => [...], 'total' => n, 'capped' => bool]. Read-only.
 */
function moop_page_search(string $organism, ?array $term_ids, array $filters = [], int $limit = 5000): array {
    $db = moop_page_open($organism);
    // A search needs something to look for: a term set, or a paper (filters['pmid']).
    if (!$db || ($term_ids === null && empty($filters['pmid'])) || ($term_ids !== null && !$term_ids)) {
        return ['rows' => [], 'total' => 0, 'capped' => false];
    }
    $where = []; $params = [];
    if ($term_ids !== null) {
        $term_ids = array_values(array_unique(array_merge($term_ids, moop_page_replaced_into($term_ids))));
        $where[]  = 'c.term_id IN (' . implode(',', array_fill(0, count($term_ids), '?')) . ')';
        $params   = $term_ids;
    }
    if (!empty($filters['pmid'])) { $where[] = 'c.pmid = ?'; $params[] = (string) $filters['pmid']; }
    foreach (['evidence' => 'c.evidence_id', 'stage' => 'c.stage_id'] as $f => $col) {
        if (!empty($filters[$f])) {
            $where[] = "$col IN (" . implode(',', array_fill(0, count($filters[$f]), '?')) . ')';
            $params  = array_merge($params, array_values($filters[$f]));
        }
    }
    $sql = 'SELECT c.accession, c.term_id, c.evidence_id, c.stage_id, c.specimen_id, c.biotype_id, c.pmid,
                   c.curated_on, c.copies, p.title, p.authors, p.journal, p.year
              FROM curation c LEFT JOIN publication p ON p.pmid = c.pmid
             WHERE ' . implode(' AND ', $where);
    $st = $db->prepare("SELECT COUNT(*) FROM ($sql)");
    $st->execute($params);
    $total = (int) $st->fetchColumn();
    $st = $db->prepare("$sql ORDER BY p.year DESC, c.pmid, c.accession LIMIT " . (int) $limit);
    $st->execute($params);
    return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'capped' => $total > $limit];
}

/** Obsolete terms whose replacement is in $ids (so their curations follow the replacement). */
function moop_page_replaced_into(array $ids): array {
    $out = [];
    foreach (moop_ontology_by_purpose('anatomy') as $p) {
        $db = moop_ontology_open($p);
        if (!$db) continue;
        foreach (array_chunk(array_values($ids), 500) as $chunk) {
            $st = $db->prepare('SELECT id FROM term WHERE obsolete = 1 AND replaced_by IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $st->execute($chunk);
            $out = array_merge($out, $st->fetchAll(PDO::FETCH_COLUMN));
        }
    }
    return $out;
}

/** Evidence codes actually used in this organism's curations, with how many curations each. */
function moop_page_evidence_used(string $organism): array {
    $db = moop_page_open($organism);
    return $db ? $db->query('SELECT evidence_id, COUNT(*) FROM curation GROUP BY evidence_id ORDER BY 2 DESC')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
}

/**
 * Evidence filter choices, from ECO itself: every evidence code the curations use, plus every ECO
 * grouping that covers two or more of them ("in situ hybridization evidence" = colorimetric +
 * FISH) -- but not groupings that cover all of them (that is "any evidence"). Nothing hand-listed:
 * a new evidence type joins its group. Returns [[id, label, count, members => [ids]], ...].
 */
function moop_page_evidence_choices(string $organism): array {
    $used = moop_page_evidence_used($organism);
    if (!$used) return [];
    $eco    = moop_ontology_by_purpose('evidence');
    $groups = [];
    foreach ($eco as $p) {
        $db = moop_ontology_open($p);
        if (!$db) continue;
        $st = $db->prepare("SELECT object FROM closure WHERE subject = ? AND relation = 'rdfs:subClassOf'");
        foreach (array_keys($used) as $code) {
            $st->execute([$code]);
            $groups[$code][] = $code;            // a code belongs to its own group (RNA-seq covers scRNA-seq AND itself)
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $anc) {
                // Only the evidence ontology's own groupings: ECO is built on BFO, whose top classes
                // ("generically dependent continuant") mean nothing as a filter.
                if (strpos($anc, $p . ':') === 0) $groups[$anc][] = $code;
            }
        }
    }
    $choices = [];
    $labels  = moop_ontology_labels(array_merge(array_keys($used), array_keys($groups)), $eco);
    foreach ($groups as $g => $members) {
        $members = array_values(array_unique($members));
        if (count($members) < 2 || count($members) === count($used)) continue;
        $key = implode(',', $members);
        // Several ancestors can cover the same members; keep the most specific (largest closure = deepest).
        if (!isset($choices[$key]) || strlen($labels[$g] ?? '') > strlen($choices[$key]['label'])) {
            $choices[$key] = ['id' => $g, 'label' => $labels[$g] ?? $g, 'group' => true, 'count' => array_sum(array_intersect_key($used, array_flip($members))), 'members' => $members];
        }
    }
    $out = array_values($choices);
    foreach ($used as $code => $n) $out[] = ['id' => $code, 'label' => $labels[$code] ?? $code, 'group' => false, 'count' => $n, 'members' => [$code]];
    return $out;
}

/** Curations recorded on any of $accessions (case-insensitive), with their papers. Read-only. */
function moop_page_for_accessions(string $organism, array $accessions, int $limit = 2000): array {
    $db = moop_page_open($organism);
    $lc = array_values(array_unique(array_map('mb_strtolower', array_filter($accessions, 'strlen'))));
    if (!$db || !$lc) return [];
    $out = [];
    foreach (array_chunk($lc, 500) as $chunk) {
        $st = $db->prepare('SELECT c.accession, c.term_id, c.evidence_id, c.stage_id, c.specimen_id, c.pmid, c.curated_on,
                                   p.title, p.authors, p.journal, p.year
                              FROM curation c LEFT JOIN publication p ON p.pmid = c.pmid
                             WHERE c.accession_lc IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $st->execute($chunk);
        $out = array_merge($out, $st->fetchAll(PDO::FETCH_ASSOC));
    }
    usort($out, fn($a, $b) => [$b['year'], $a['term_id']] <=> [$a['year'], $b['term_id']]);
    return array_slice($out, 0, $limit);
}

/**
 * Papers matching $q -- a PMID, or words that must ALL appear in the title, authors or journal
 * (any order, case-insensitive) -- with how many curations each has. Newest first. Read-only.
 * Returns [['pmid', 'title', 'authors', 'journal', 'year', 'records'], ...].
 */
function moop_page_find_publications(string $organism, string $q, int $limit = 100): array {
    $db = moop_page_open($organism);
    $q  = trim(preg_replace('/\s+/', ' ', $q));
    if (!$db || $q === '' || mb_strlen($q) > 200) return [];
    $sql = 'SELECT c.pmid, p.title, p.authors, p.journal, p.year, COUNT(*) AS records
              FROM curation c LEFT JOIN publication p ON p.pmid = c.pmid';
    $params = [];
    if (preg_match('/^(?:pmid:?\s*)?(\d{4,9})$/i', $q, $m)) {
        $sql .= ' WHERE c.pmid = ?';
        $params[] = $m[1];
    } else {
        $words = array_slice(array_filter(explode(' ', moop_page_fold($q)), fn($w) => mb_strlen($w) >= 2), 0, 8);
        if (!$words) return [];
        $conds = [];
        foreach ($words as $w) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $w) . '%';
            $conds[] = "p.search_text LIKE ? ESCAPE '\\'";
            $params[] = $like;
        }
        $sql .= ' WHERE ' . implode(' AND ', $conds);
    }
    $sql .= ' GROUP BY c.pmid ORDER BY p.year DESC, c.pmid LIMIT ' . (int) $limit;
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Search by gene or transcript ID: any ids (h1 gene, SMED3, dd_v6, EST, array probe, symbol...)
 * -> through the crosswalk to their reference genes -> every accession mapped to those genes ->
 * their curations. So any id of a gene finds the records made under any other id of it.
 * Returns ['rows' => curations, 'matched' => [input => [ref ids]], 'unknown' => [inputs]].
 */
function moop_page_search_ids(string $organism, array $ids, int $limit = 5000): array {
    require_once __DIR__ . '/crosswalk.php';
    $ids  = array_values(array_unique(array_filter(array_map('trim', $ids), 'strlen')));
    $refs = moop_crosswalk_genes($organism, $ids);
    $matched = []; $all_refs = [];
    foreach ($ids as $in) {
        $r = array_values(array_unique(array_column($refs[mb_strtolower($in)] ?? [], 'ref_id')));
        if ($r) { $matched[$in] = $r; $all_refs = array_merge($all_refs, $r); }
    }
    $members = moop_crosswalk_members($organism, array_merge($ids, $all_refs));
    $rows    = moop_page_for_accessions($organism, array_merge($ids, $all_refs, array_column($members, 'accession')), $limit);
    // An id the crosswalk does not know may still have curations under exactly that id.
    $direct  = array_flip(array_map('mb_strtolower', array_column($rows, 'accession')));
    $unknown = array_values(array_filter($ids, fn($i) => !isset($matched[$i]) && !isset($direct[mb_strtolower($i)])));
    return ['rows' => $rows, 'matched' => $matched, 'unknown' => $unknown];
}

/**
 * Anatomy terms for MANY genes at once (the Data Exporter's "Anatomical expression" column).
 * $genes: [key => [ids of that gene: gene id, transcript ids, ...]]. Each gene's ids reach their
 * reference genes through the crosswalk, then every accession mapped to those genes, then the
 * curations on any of them -- the same path as the gene-page card, batched so a whole export
 * chunk costs a handful of queries. Returns [key => [term_id => label]], terms sorted by label;
 * a gene with no curations is absent. Read-only; [] for an organism without these files.
 */
function moop_page_terms_for_genes(string $organism, array $genes): array {
    require_once __DIR__ . '/crosswalk.php';
    require_once __DIR__ . '/ontologies.php';
    $page = moop_page_open($organism);
    if (!$genes || !$page) return [];
    $lc = fn($s) => mb_strtolower((string) $s);

    // Only accessions WITH a curation matter (~39k for Schmidtea). A gene's crosswalk can reach
    // thousands of accessions, so the members are streamed and kept only if curated -- holding
    // them all (moop_crosswalk_members) ran a 250-gene export chunk out of memory.
    static $curated = [];
    if (!isset($curated[$organism])) {
        $curated[$organism] = array_flip($page->query('SELECT DISTINCT accession_lc FROM curation')->fetchAll(PDO::FETCH_COLUMN));
    }
    $is_curated = $curated[$organism];

    $owners = [];   // own id / reference gene (lower case) -> [gene key => true]
    $all    = [];
    foreach ($genes as $key => $ids) {
        foreach (array_filter($ids, 'strlen') as $id) { $owners[$lc($id)][$key] = true; $all[] = $id; }
    }
    // own ids -> their reference genes
    $by_ref = [];   // ref_id (as stored) -> gene keys
    foreach (moop_crosswalk_genes($organism, $all) as $acc_lc => $rows) {
        foreach ($rows as $r) $by_ref[$r['ref_id']] = ($by_ref[$r['ref_id']] ?? []) + ($owners[$acc_lc] ?? []);
    }
    foreach ($all as $id) $by_ref[$id] = ($by_ref[$id] ?? []) + ($owners[$lc($id)] ?? []);

    // curated accession -> gene keys: the gene's own ids, then every accession mapped to its refs
    $hit = [];
    foreach ($owners as $acc_lc => $keys) if (isset($is_curated[$acc_lc])) $hit[$acc_lc] = $keys;
    $xw = moop_crosswalk_open($organism);
    if ($xw) {
        foreach (array_chunk(array_keys($by_ref), 500) as $chunk) {
            $st = $xw->prepare('SELECT accession_lc, ref_id FROM map WHERE ref_id IN ('
                               . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $st->execute(array_map('strval', $chunk));
            while ($r = $st->fetch(PDO::FETCH_NUM)) {
                if (isset($is_curated[$r[0]])) $hit[$r[0]] = ($hit[$r[0]] ?? []) + ($by_ref[$r[1]] ?? []);
            }
        }
    }
    if (!$hit) return [];

    $terms = [];
    foreach (array_chunk(array_keys($hit), 500) as $chunk) {
        $st = $page->prepare('SELECT DISTINCT accession_lc, term_id FROM curation WHERE accession_lc IN ('
                             . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $st->execute(array_map('strval', $chunk));
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            foreach (array_keys($hit[$r[0]] ?? []) as $key) $terms[$key][$r[1]] = true;
        }
    }
    if (!$terms) return [];
    $ids    = array_keys(array_merge(...array_values($terms)));
    $labels = moop_ontology_labels($ids, moop_ontology_by_purpose('anatomy'));
    $out = [];
    foreach ($terms as $key => $set) {
        $row = [];
        foreach (array_keys($set) as $t) $row[$t] = $labels[$t] ?? $t;
        asort($row, SORT_NATURAL | SORT_FLAG_CASE);
        $out[$key] = $row;
    }
    return $out;
}
