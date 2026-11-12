<?php
/**
 * Gene images -- in situ images, single-cell plots, movies -- as one read-only SQLite file per
 * organism, built from a plain list:
 *
 *     organisms/<organism>/images/images.tsv      the source (one row per image; the truth)
 *     organisms/<organism>/images/<files>         the images, paths relative to images/
 *     organisms/<organism>/gene_images.sqlite     built from images.tsv by scripts/build_gene_images.php
 *
 * Format and rules: docs/ORGANISM_SIDE_FILES.md §2-3.
 *
 * ACCESS: every row names the data set it belongs to (assembly + gene set, optionally an
 * analysis). A visitor sees an image only if moop_dataset_visible() allows that data set --
 * the same rule as the gene set itself. Private and missing look the same (404).
 *
 * NEVER REWRITTEN: what the build cannot use goes on the review list, never silently dropped
 * or "fixed". Built into a temp file, checked, renamed; a failed build leaves the previous file.
 */

require_once __DIR__ . '/ontologies.php';

const MOOP_IMAGE_REQUIRED = ['file', 'kind', 'accession', 'assembly', 'gene_set'];
const MOOP_IMAGE_OPTIONAL = ['analysis', 'stage', 'terms', 'caption', 'pmid', 'citation', 'credit', 'license'];
const MOOP_IMAGE_KINDS    = ['in situ', 'movie', 'feature plot', 'violin plot', 'antibody', 'RNAi phenotype', 'live', 'other'];
const MOOP_IMAGE_EXTS     = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
                             'webp' => 'image/webp', 'm4v' => 'video/mp4', 'mp4' => 'video/mp4'];

function moop_images_org_dir(string $organism): string {
    return rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/'
         . preg_replace('/[^A-Za-z0-9_.-]/', '', $organism);
}
function moop_images_dir(string $organism): string  { return moop_images_org_dir($organism) . '/images'; }
function moop_images_file(string $organism): string { return moop_images_org_dir($organism) . '/gene_images.sqlite'; }

function moop_images_open(string $organism): ?PDO {
    $f = moop_images_file($organism);
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
 * Build gene_images.sqlite from images/images.tsv. $known_terms = [term id => true] from the
 * ontologies (empty = do not check terms). $out_file for tests.
 * Returns ['ok', 'error', 'counts', 'review'].
 */
function moop_images_build(string $organism, array $known_terms = [], ?string $tsv = null, ?string $out_file = null): array {
    $dir      = moop_images_dir($organism);
    $tsv      = $tsv ?? "$dir/images.tsv";
    $out_file = $out_file ?? moop_images_file($organism);
    $org_dir  = moop_images_org_dir($organism);
    $tmp      = $out_file . '.tmp-' . getmypid();
    @unlink($tmp);
    $review = [];
    $note = function (string $kind, string $detail) use (&$review) { $review[$kind][$detail] = ($review[$kind][$detail] ?? 0) + 1; };
    try {
        $fh = @fopen($tsv, 'r');
        if (!$fh) throw new RuntimeException("cannot read $tsv");
        $header = array_map('trim', explode("\t", rtrim((string) fgets($fh), "\r\n")));
        if ($missing = array_diff(MOOP_IMAGE_REQUIRED, $header)) throw new RuntimeException('images.tsv lacks column(s): ' . implode(', ', $missing));
        $col = array_flip($header);
        $langs = [];   // caption_<lang> columns
        foreach ($header as $h) if (preg_match('/^caption_([a-z]{2})$/', $h, $m)) $langs[$m[1]] = $col[$h];
        $unknown_cols = array_diff($header, MOOP_IMAGE_REQUIRED, MOOP_IMAGE_OPTIONAL, array_map(fn($l) => "caption_$l", array_keys($langs)));
        foreach ($unknown_cols as $u) $note('column not used', $u);

        $db = new PDO('sqlite:' . $tmp);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF;');
        $db->exec('CREATE TABLE image (id INTEGER PRIMARY KEY, file TEXT NOT NULL UNIQUE, kind TEXT NOT NULL,
                     assembly TEXT NOT NULL, gene_set TEXT NOT NULL, analysis TEXT, stage_id TEXT, caption TEXT,
                     captions TEXT, pmid TEXT, citation TEXT, credit TEXT, license TEXT, bytes INTEGER)');
        $db->exec('CREATE TABLE image_accession (image_id INTEGER NOT NULL, accession TEXT NOT NULL, accession_lc TEXT NOT NULL)');
        $db->exec('CREATE TABLE image_term (image_id INTEGER NOT NULL, term_id TEXT NOT NULL)');
        $db->exec('CREATE TABLE review (kind TEXT NOT NULL, detail TEXT NOT NULL, rows INTEGER NOT NULL)');
        $db->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)');
        $db->beginTransaction();
        $ins  = $db->prepare('INSERT INTO image (file, kind, assembly, gene_set, analysis, stage_id, caption, captions, pmid, citation, credit, license, bytes)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insA = $db->prepare('INSERT INTO image_accession (image_id, accession, accession_lc) VALUES (?,?,?)');
        $insT = $db->prepare('INSERT INTO image_term (image_id, term_id) VALUES (?,?)');
        $seen = []; $datasets = []; $rows = 0;
        $real_dir = realpath($dir);
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;
            $rows++;
            $c   = explode("\t", $line);
            $get = fn(string $k) => isset($col[$k]) ? trim($c[$col[$k]] ?? '') : '';
            $file = $get('file');
            if ($file === '') { $note('no file name', "row $rows"); continue; }
            if (isset($seen[$file])) { $note('same file listed twice (first kept)', $file); continue; }
            // The file must exist UNDER images/ -- no "..", no absolute path, no symlink out.
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $real = $real_dir ? realpath("$dir/$file") : false;
            if ($real === false || strpos($real, $real_dir . '/') !== 0 || !is_file($real)) { $note('file missing', $file); continue; }
            if (!isset(MOOP_IMAGE_EXTS[$ext])) { $note('file type not served', $file); continue; }
            $kind = $get('kind');
            if (!in_array($kind, MOOP_IMAGE_KINDS, true)) $note('unknown kind (kept)', $kind);
            [$asm, $gs] = [$get('assembly'), $get('gene_set')];
            if ($asm === '' || $gs === '') { $note('no data set (assembly / gene set)', $file); continue; }
            if (!isset($datasets["$asm/$gs"])) $datasets["$asm/$gs"] = is_dir("$org_dir/$asm/$gs");
            if (!$datasets["$asm/$gs"]) $note('data set not on this site (kept, shown to nobody until it is)', "$asm/$gs");
            $accs = array_values(array_filter(array_map('trim', explode(';', $get('accession'))), 'strlen'));
            if (!$accs) { $note('no gene or transcript ID', $file); continue; }
            $stage = $get('stage');
            $terms = array_values(array_filter(array_map('trim', explode(';', $get('terms'))), 'strlen'));
            if ($known_terms) {
                foreach (array_merge($stage !== '' ? [$stage] : [], $terms) as $t) {
                    if (!isset($known_terms[$t])) $note('term not in the ontologies (kept)', $t);
                }
            }
            $pmid = $get('pmid');
            if ($pmid !== '' && !ctype_digit($pmid)) $note('pmid is not a number (kept)', $pmid);
            $caps = [];
            foreach ($langs as $l => $i) if (trim($c[$i] ?? '') !== '') $caps[$l] = trim($c[$i]);
            $ins->execute([$file, $kind, $asm, $gs, $get('analysis') ?: null, $stage ?: null, $get('caption') ?: null,
                           $caps ? json_encode($caps, JSON_UNESCAPED_UNICODE) : null, $pmid ?: null, $get('citation') ?: null,
                           $get('credit') ?: null, $get('license') ?: null, filesize($real)]);
            $id = (int) $db->lastInsertId();
            foreach (array_unique($accs) as $a) $insA->execute([$id, $a, strtolower($a)]);
            foreach (array_unique($terms) as $t) $insT->execute([$id, $t]);
            $seen[$file] = true;
        }
        fclose($fh);
        $rins = $db->prepare('INSERT INTO review (kind, detail, rows) VALUES (?,?,?)');
        foreach ($review as $kind => $details) foreach ($details as $d => $n) $rins->execute([$kind, (string) $d, $n]);
        $counts = [
            'rows_read'   => $rows,
            'images'      => (int) $db->query('SELECT COUNT(*) FROM image')->fetchColumn(),
            'accessions'  => (int) $db->query('SELECT COUNT(DISTINCT accession_lc) FROM image_accession')->fetchColumn(),
            'tagged'      => (int) $db->query('SELECT COUNT(DISTINCT image_id) FROM image_term')->fetchColumn(),
            'terms'       => (int) $db->query('SELECT COUNT(DISTINCT term_id) FROM image_term')->fetchColumn(),
            'data_sets'   => (int) $db->query('SELECT COUNT(DISTINCT assembly || "/" || gene_set) FROM image')->fetchColumn(),
        ];
        $meta = $db->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach (['organism' => $organism, 'built_at' => date('c')] + array_map('strval', $counts) as $k => $v) $meta->execute([$k, $v]);
        $db->commit();
        $db->exec('CREATE INDEX a_acc ON image_accession (accession_lc)');
        $db->exec('CREATE INDEX a_img ON image_accession (image_id)');
        $db->exec('CREATE INDEX t_term ON image_term (term_id)');
        $db->exec('CREATE INDEX t_img ON image_term (image_id)');
        $db->exec('ANALYZE');
        if ($counts['images'] === 0) throw new RuntimeException('no usable images -- refusing to replace the live file');
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

/** Every anatomy/stage term id the ontologies know (for the build's review list). */
function moop_images_known_terms(): array {
    $known = [];
    foreach (moop_ontology_by_purpose('anatomy') as $p) {
        $db = moop_ontology_open($p);
        if (!$db) continue;
        foreach ($db->query("SELECT id FROM term WHERE type = 'class'") as $r) $known[$r['id']] = true;
    }
    return $known;
}

/**
 * One image row by its file name, or null -- including when the visitor may not see its data set
 * (callers answer 404 for both, so a private image cannot be told apart from a missing one).
 */
function moop_image_visible_row(string $organism, string $file): ?array {
    $db = moop_images_open($organism);
    if (!$db) return null;
    $st = $db->prepare('SELECT * FROM image WHERE file = ?');
    $st->execute([$file]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !moop_dataset_visible($organism, $row['assembly'], $row['gene_set'], (string) $row['analysis'])) return null;
    return $row;
}

/** Absolute path of an image file, only if it is inside images/ (no "..", no symlink out). */
function moop_image_path(string $organism, string $file): ?string {
    $base = realpath(moop_images_dir($organism));
    $real = $base ? realpath("$base/$file") : false;
    return ($real !== false && strpos($real, $base . '/') === 0 && is_file($real)) ? $real : null;
}

/**
 * Images for a set of gene/transcript IDs (case-insensitive), only from data sets the visitor
 * may see. Each row gets 'accessions' (the IDs it shows) and 'terms' (term ids).
 */
function moop_images_for_accessions(string $organism, array $accessions, int $limit = 500): array {
    $db = moop_images_open($organism);
    $accs = array_values(array_unique(array_map('strtolower', array_filter($accessions, 'strlen'))));
    if (!$db || !$accs) return [];
    $out = [];
    foreach (array_chunk($accs, 500) as $chunk) {
        $st = $db->prepare('SELECT DISTINCT i.* FROM image i JOIN image_accession a ON a.image_id = i.id
                            WHERE a.accession_lc IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $st->execute($chunk);
        foreach ($st as $r) $out[$r['id']] = $r;
    }
    return moop_images_finish($db, $organism, $out, $limit);
}

/** Images tagged with any of $term_ids (an expanded set), only from data sets the visitor may see. */
function moop_images_for_terms(string $organism, array $term_ids, int $limit = 500): array {
    $db = moop_images_open($organism);
    if (!$db || !$term_ids) return [];
    $out = [];
    foreach (array_chunk(array_values(array_unique($term_ids)), 500) as $chunk) {
        $st = $db->prepare('SELECT DISTINCT i.* FROM image i JOIN image_term t ON t.image_id = i.id
                            WHERE t.term_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $st->execute($chunk);
        foreach ($st as $r) $out[$r['id']] = $r;
    }
    return moop_images_finish($db, $organism, $out, $limit);
}

/** Access filter + attach accessions/terms + stable order (stage, then file). */
function moop_images_finish(PDO $db, string $organism, array $rows, int $limit): array {
    $rows = array_filter($rows, fn($r) => moop_dataset_visible($organism, $r['assembly'], $r['gene_set'], (string) $r['analysis']));
    usort($rows, fn($a, $b) => [(string) $a['stage_id'], $a['file']] <=> [(string) $b['stage_id'], $b['file']]);
    $rows = array_slice($rows, 0, $limit);
    if (!$rows) return [];
    $ids = array_column($rows, 'id');
    $in  = implode(',', array_map('intval', $ids));
    $accs = $terms = [];
    foreach ($db->query("SELECT image_id, accession FROM image_accession WHERE image_id IN ($in)") as $r) $accs[$r['image_id']][] = $r['accession'];
    foreach ($db->query("SELECT image_id, term_id FROM image_term WHERE image_id IN ($in)") as $r) $terms[$r['image_id']][] = $r['term_id'];
    foreach ($rows as &$r) { $r['accessions'] = $accs[$r['id']] ?? []; $r['terms'] = $terms[$r['id']] ?? []; }
    return $rows;
}
