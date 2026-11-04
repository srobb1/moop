<?php
/**
 * Interface translations -- cuttingclass step 5b (notes/CUTTINGCLASS_PLAN.md). t() and friends
 * live in includes/config_init.php; this file is loaded only on a site with more than one
 * language, so simrbase never reads it.
 *
 * THE CATALOG -- one file per language, {metadata_path}/translations/{code}.json, SITE DATA
 * (snapshot to the private site-data repo, like pages and the glossary):
 *
 *   { "language": "es",
 *     "strings": { "Log In": { "text": "Iniciar sesión", "status": "reviewed", "notes": "",
 *                              "updated_at": "...", "updated_by": "admin" } } }
 *
 * Keyed by the English exactly as the code passes it to t(). Statuses follow the review
 * workflow: 'needs translation' (no Spanish yet), 'machine draft' (an agent wrote it), 'reviewed'
 * (a person checked it). Any Spanish text is shown to visitors whatever its status; a string with
 * none shows in English. Visitors never see a placeholder -- only an admin with "Show gaps" on.
 *
 * WHAT GETS TRANSLATED -- two lists, never hand-kept:
 *   - SEEN: every string t() was asked for on this site, recorded as pages are viewed
 *     ({cache}/i18n_seen.json, with up to five pages each). This is what the spreadsheet exports
 *     by default, so text for switched-off tools never reaches the translators.
 *   - IN THE CODE: a scan of the source for t('...') and the help helpers' literal text
 *     (moop_i18n_scan()), so a string can be translated before anyone has opened its page.
 *
 * THE SPREADSHEET -- moop_i18n_export_rows() / moop_i18n_import(): .xlsx written and read by
 * shuchkin/simplexlsxgen and shuchkin/simplexlsx (MIT; pure PHP -- this host's PHP has no zip
 * extension, which the larger libraries need). CSV is accepted on upload too.
 */

const MOOP_I18N_STATUSES = ['needs translation', 'machine draft', 'reviewed'];
const MOOP_I18N_COLUMNS  = ['English', 'Translation', 'Status', 'Keep these exactly', 'Where it appears', 'Notes'];

function moop_i18n_dir(): string {
    return rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/translations';
}

/**
 * The catalog for one language: [english => entry]. Read once per request; $reload reads the file
 * again (a save must start from what is on disk, never from an earlier read of it).
 */
function moop_i18n_catalog(string $lang, bool $reload = false): array {
    static $cache = [];
    if ($reload || !isset($cache[$lang])) {
        $cache[$lang] = [];
        if (preg_match(ConfigManager::LANGUAGE_CODE, $lang)) {
            $raw = @file_get_contents(moop_i18n_dir() . "/$lang.json");
            $doc = $raw === false ? null : json_decode($raw, true);
            if (is_array($doc['strings'] ?? null)) $cache[$lang] = $doc['strings'];
        }
    }
    return $cache[$lang];
}

/** The translation of $english, or the English (marked, for an admin with "Show gaps" on). */
function moop_i18n_lookup(string $lang, string $english): string {
    moop_i18n_seen($english);
    $text = (string) (moop_i18n_catalog($lang)[$english]['text'] ?? '');
    if (trim($text) !== '') return $text;
    return moop_i18n_show_gaps() ? "\u{27E6}" . $english . "\u{27E7}" : $english;
}

/**
 * "Show gaps": an admin's own switch (Manage Translations) that brackets untranslated text ⟦like
 * this⟧ on the real pages. A character, not markup, because t() is used inside attributes too.
 * Off for everyone else, and off while an admin previews the public view.
 */
function moop_i18n_show_gaps(): bool {
    return !empty($_SESSION['i18n_show_gaps']) && function_exists('moop_session_is_admin') && moop_session_is_admin();
}

// ---- SEEN: what this site actually shows -------------------------------------------------

function moop_i18n_seen_file(): string {
    if (!function_exists('moop_cache_root')) require_once __DIR__ . '/cache_paths.php';
    return moop_cache_root() . '/i18n_seen.json';
}

/** Note that this request showed $english; written once, at the end of the request. */
function moop_i18n_seen(string $english): void {
    static $registered = false;
    $GLOBALS['moop_i18n_seen_now'][$english] = true;
    if (!$registered) {
        $registered = true;
        register_shutdown_function('moop_i18n_seen_flush');
    }
}

/** Every string seen so far: [english => ['pages' => [...], 'first_seen' => date]]. */
function moop_i18n_seen_all(): array {
    $raw = @file_get_contents(moop_i18n_seen_file());
    $doc = $raw === false ? null : json_decode($raw, true);
    return is_array($doc) ? $doc : [];
}

/**
 * Merge this request's strings into the seen file. Most requests add nothing new, so the file
 * is read under a shared lock and rewritten only when something is new. A failure here costs
 * the spreadsheet a row, never the visitor a page: it is silent by design.
 */
function moop_i18n_seen_flush(): void {
    $now = array_keys($GLOBALS['moop_i18n_seen_now'] ?? []);
    if (!$now) return;
    $site = '/' . ConfigManager::getInstance()->getString('site');
    $page = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($page, $site . '/') === 0) $page = substr($page, strlen($site) + 1);
    if ($page === 'page.php' && isset($_GET['p'])) $page .= '?p=' . (string) $_GET['p'];

    $merge = function (array $seen) use ($now, $page): ?array {
        $changed = false;
        foreach ($now as $s) {
            if (!isset($seen[$s])) {
                $seen[$s] = ['pages' => [$page], 'first_seen' => date('Y-m-d')];
                $changed = true;
            } elseif (!in_array($page, $seen[$s]['pages'] ?? [], true) && count($seen[$s]['pages'] ?? []) < 5) {
                $seen[$s]['pages'][] = $page;
                $changed = true;
            }
        }
        return $changed ? $seen : null;
    };

    $file = moop_i18n_seen_file();
    if ($merge(moop_i18n_seen_all()) === null) return;
    $fh = @fopen($file, 'c+');
    if (!$fh) return;
    if (flock($fh, LOCK_EX)) {
        $doc  = json_decode((string) stream_get_contents($fh), true);
        $seen = $merge(is_array($doc) ? $doc : []);
        if ($seen !== null) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($seen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    @chmod($file, 0664);
}

// ---- IN THE CODE: a scan of the source -------------------------------------------------

/**
 * Which arguments of which functions are interface text. help_modal() is special-cased: its
 * title, and the heading / note / label / text / intro values inside its arrays.
 */
const MOOP_I18N_TEXT_ARGS = [
    't' => [0], 't_plain' => [0], 't_markup' => [0],
    'field_help' => [0, 1], 'help_modal_trigger' => [1, 2], 'page_purpose' => [0], 'page_purpose_info' => [0],
];

/**
 * Every literal interface string in the source: [english => ['file:line', ...]]. Reads PHP
 * tokens, so a commented-out call or a string that merely contains "t(" is not counted.
 * Admin pages are left out: they are always English.
 */
function moop_i18n_scan(?string $root = null): array {
    $root = rtrim($root ?? ConfigManager::getInstance()->getPath('root_path') . '/' . ConfigManager::getInstance()->getString('site'), '/');
    $skip = ['vendor', 'organisms', 'jbrowse2', 'node_modules', 'data', 'logs', 'notes', 'tests', 'docs',
             'metadata', 'admin', 'scripts', 'config', '.git', 'tmp'];
    $out  = [];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        fn($f) => !($f->isDir() && in_array($f->getFilename(), $skip, true))
    ));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $rel = substr($f->getPathname(), strlen($root) + 1);
        foreach (moop_i18n_scan_source((string) @file_get_contents($f->getPathname())) as [$text, $line]) {
            $out[$text][] = "$rel:$line";
        }
    }
    ksort($out, SORT_STRING | SORT_FLAG_CASE);
    return $out;
}

/** The interface strings in one PHP source: [[text, line], ...]. Pure. */
function moop_i18n_scan_source(string $src): array {
    $toks = array_values(array_filter(@token_get_all($src) ?: [],
        fn($t) => !(is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))));
    $found = [];
    $n = count($toks);
    for ($i = 0; $i < $n; $i++) {
        $tk = $toks[$i];
        if (!is_array($tk) || $tk[0] !== T_STRING) continue;
        $fn = strtolower($tk[1]);
        if (!isset(MOOP_I18N_TEXT_ARGS[$fn]) && $fn !== 'help_modal') continue;
        $prev = $toks[$i - 1] ?? null;
        if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) continue;
        if (($toks[$i + 1] ?? null) !== '(') continue;

        // Split the call's arguments at top-level commas.
        $args = [[]]; $depth = 0;
        for ($j = $i + 2; $j < $n; $j++) {
            $t = $toks[$j];
            if (in_array($t, ['(', '[', '{'], true) || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $depth++;
            elseif (in_array($t, [')', ']', '}'], true)) { if ($depth === 0) break; $depth--; }
            elseif ($t === ',' && $depth === 0) { $args[] = []; continue; }
            $args[count($args) - 1][] = $t;
        }
        $lit = fn(array $a) => (count($a) === 1 && is_array($a[0]) && $a[0][0] === T_CONSTANT_ENCAPSED_STRING)
            ? [moop_i18n_unquote($a[0][1]), $a[0][2]] : null;

        if ($fn === 'help_modal') {
            if ($l = $lit($args[1] ?? [])) $found[] = $l;
            // 'key' => 'value' pairs anywhere in the sections and options.
            $all = array_merge($args[2] ?? [], $args[3] ?? []);
            foreach ($all as $k => $t) {
                if (!is_array($t) || $t[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
                $arrow = $all[$k + 1] ?? null; $val = $all[$k + 2] ?? null; $after = $all[$k + 3] ?? null;
                if (in_array(moop_i18n_unquote($t[1]), ['heading', 'note', 'label', 'text', 'intro'], true)
                    && is_array($arrow) && $arrow[0] === T_DOUBLE_ARROW
                    && is_array($val) && $val[0] === T_CONSTANT_ENCAPSED_STRING && in_array($after, [',', ']', null], true)) {
                    $found[] = [moop_i18n_unquote($val[1]), $val[2]];
                }
            }
            continue;
        }
        foreach (MOOP_I18N_TEXT_ARGS[$fn] as $pos) {
            if ($l = $lit($args[$pos] ?? [])) $found[] = $l;
        }
    }
    return array_values(array_filter($found, fn($f) => trim($f[0]) !== ''));
}

/** A PHP string literal's value: '...' or "..." (no variables -- those are not literals). */
function moop_i18n_unquote(string $lit): string {
    $q = $lit[0];
    $body = substr($lit, 1, -1);
    return $q === "'" ? strtr($body, ["\\\\" => "\\", "\\'" => "'"]) : stripcslashes($body);
}

// ---- JavaScript: moopT() / moopTh() in js/ (js/modules/utilities.js) --------------------

/**
 * Every moopT('...') / moopTh('...') literal in the site's JavaScript: [english => ['js/x.js:line']].
 * Cached in {cache}/i18n_js_strings.json against the newest .js file's time, so a page in another
 * language costs a directory listing, not a re-scan.
 */
function moop_i18n_scan_js(?string $root = null): array {
    $cfg  = ConfigManager::getInstance();
    $root = rtrim($root ?? $cfg->getPath('root_path') . '/' . $cfg->getString('site'), '/');
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator("$root/js", FilesystemIterator::SKIP_DOTS),
        fn($f) => !($f->isDir() && $f->getFilename() === 'vendor')));
    foreach ($it as $f) if ($f->getExtension() === 'js') $files[$f->getPathname()] = $f->getMTime();
    $stamp = $files ? max($files) . ':' . count($files) : '0';

    if (!function_exists('moop_cache_root')) require_once __DIR__ . '/cache_paths.php';
    $cache = moop_cache_root() . '/i18n_js_strings.json';
    $doc = json_decode((string) @file_get_contents($cache), true);
    if (($doc['stamp'] ?? null) === $stamp && is_array($doc['strings'] ?? null)) return $doc['strings'];

    $out = [];
    foreach (array_keys($files) as $path) {
        $rel = substr($path, strlen($root) + 1);
        foreach (moop_i18n_scan_js_source((string) @file_get_contents($path)) as [$text, $line]) $out[$text][] = "$rel:$line";
    }
    ksort($out, SORT_STRING | SORT_FLAG_CASE);
    @file_put_contents($cache, json_encode(['stamp' => $stamp, 'strings' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($cache, 0664);
    return $out;
}

/** The moopT()/moopTh() literals in one JS source: [[text, line], ...]. Pure. */
function moop_i18n_scan_js_source(string $src): array {
    $found = [];
    preg_match_all('/\bmoopT[hm]?\(\s*(?:\'((?:[^\'\\\\\n]|\\\\.)*)\'|"((?:[^"\\\\\n]|\\\\.)*)")\s*[,)]/', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($m as $hit) {
        $raw  = ($hit[1][1] ?? -1) >= 0 ? $hit[1][0] : $hit[2][0];
        $text = preg_replace_callback('/\\\\(u[0-9a-fA-F]{4}|.)/', function ($e) {
            $c = $e[1];
            if ($c[0] === 'u' && strlen($c) === 5) return mb_chr(hexdec(substr($c, 1)), 'UTF-8');
            return ['n' => "\n", 't' => "\t"][$c] ?? $c;
        }, $raw);
        if (trim($text) !== '') $found[] = [$text, substr_count($src, "\n", 0, $hit[0][1]) + 1];
    }
    return $found;
}

/** The translations the browser needs on a page in $lang: [english => translation], translated only. */
function moop_i18n_js_catalog(string $lang): array {
    $cat = moop_i18n_catalog($lang);
    $out = [];
    foreach (moop_i18n_scan_js() as $en => $where) {
        $tr = (string) ($cat[$en]['text'] ?? '');
        if (trim($tr) !== '') $out[$en] = $tr;
    }
    return $out;
}

// ---- placeholders --------------------------------------------------------------------

/**
 * Do two strings take the same arguments? Every %s/%d must still be there, as many times; a
 * translation may reorder them with %1$s / %2$d ("Mostrando %2$d: %1$d"), so '%d %d' and
 * '%2$d %1$d' match, '%d %d' and '%d' do not, and '%1$d %1$d' is not '%1$d %2$d'.
 */
function moop_i18n_placeholders_match(string $english, string $translation): bool {
    $sig = function (string $s): array {
        $out = []; $next = 1;
        foreach (moop_i18n_placeholders_in_order($s) as $p) {
            preg_match('/^%(?:(\d+)\$)?.*([a-zA-Z])$/', $p, $m);
            $pos = $m[1] !== '' ? (int) $m[1] : $next++;
            $out[$pos] = ($out[$pos] ?? '') . $m[2];
        }
        ksort($out);
        return $out;
    };
    return $sig($english) === $sig($translation);
}

/** The placeholders in the order they appear. */
function moop_i18n_placeholders_in_order(string $s): array {
    preg_match_all('/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', str_replace('%%', '', $s), $m);
    return $m[0];
}

/** The sprintf placeholders in a string, sorted: '%d', '%1$s', ... ('%%' is not one). */
function moop_i18n_placeholders(string $s): array {
    preg_match_all('/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', str_replace('%%', '', $s), $m);
    $p = $m[0];
    sort($p);
    return $p;
}

// ---- status ---------------------------------------------------------------------------

/**
 * One row per string for a language: the union of what is seen, what the code holds and what
 * the catalog already has. ['english', 'text', 'status', 'notes', 'seen' => pages, 'code' => file:lines].
 */
function moop_i18n_rows(string $lang, ?array $scan = null): array {
    $scan    = $scan ?? moop_i18n_scan();
    $js      = moop_i18n_scan_js();
    foreach ($js as $en => $where) $scan[$en] = array_merge($scan[$en] ?? [], $where);
    $seen    = moop_i18n_seen_all();
    $catalog = moop_i18n_catalog($lang);
    $rows = [];
    foreach (array_unique(array_merge(array_keys($scan), array_keys($seen), array_keys($catalog))) as $en) {
        $en = (string) $en;
        $c  = $catalog[$en] ?? [];
        $text = (string) ($c['text'] ?? '');
        $rows[$en] = [
            'english' => $en,
            'text'    => $text,
            'status'  => trim($text) === '' ? 'needs translation' : (in_array($c['status'] ?? '', MOOP_I18N_STATUSES, true) && $c['status'] !== 'needs translation' ? $c['status'] : 'machine draft'),
            'notes'   => (string) ($c['notes'] ?? ''),
            // Text built by JavaScript is never seen by the server; it counts as shown -- it
            // belongs to modules the pages load -- with its files as where it appears.
            'seen'    => $seen[$en]['pages'] ?? (isset($js[$en]) ? array_map(fn($w) => strtok($w, ':'), array_slice($js[$en], 0, 3)) : []),
            'code'    => $scan[$en] ?? [],
        ];
    }
    uksort($rows, 'strnatcasecmp');
    return $rows;
}

/** Counts per status, over the strings this site has shown (and over all, for the code). */
function moop_i18n_counts(array $rows): array {
    $c = ['shown' => 0, 'in_code' => 0, 'unused' => 0] + array_fill_keys(MOOP_I18N_STATUSES, 0);
    foreach ($rows as $r) {
        if ($r['seen']) { $c['shown']++; $c[$r['status']]++; }
        if ($r['code']) $c['in_code']++;
        if (!$r['seen'] && !$r['code']) $c['unused']++;
    }
    return $c;
}

// ---- the spreadsheet ----------------------------------------------------------------

/**
 * The spreadsheet's rows (header first). $scope 'shown' = strings this site has displayed (the
 * default -- what translators should spend time on); 'all' = also every string in the code.
 */
function moop_i18n_export_rows(string $lang, string $scope = 'shown'): array {
    $out = [MOOP_I18N_COLUMNS];
    foreach (moop_i18n_rows($lang) as $r) {
        if ($scope === 'shown' && !$r['seen']) continue;
        if ($scope === 'all' && !$r['seen'] && !$r['code']) continue;
        $where = $r['seen'] ? implode(', ', $r['seen']) : 'not shown yet: ' . implode(', ', array_slice($r['code'], 0, 3));
        $out[] = [$r['english'], $r['text'], $r['status'], implode(' ', moop_i18n_placeholders($r['english'])), $where, $r['notes']];
    }
    return $out;
}

/** The .xlsx itself, as bytes: the strings, and a second sheet of instructions. */
function moop_i18n_xlsx(array $rows, string $language_name): string {
    require_once __DIR__ . '/../vendor/autoload.php';
    // A leading "\0" is SimpleXLSXGen's "plain text" marker: without it a cell starting with '='
    // becomes a formula and <b> becomes bold.
    $raw = fn(array $rs) => array_map(fn($r) => array_map(fn($c) => "\0" . $c, $r), $rs);
    $help = [
        ['How to fill in this sheet'],
        ["Write the $language_name in the Translation column. Leave the English column exactly as it is: it is how MOOP finds each line."],
        ['Status: "machine draft" when an agent wrote it, "reviewed" once a person has checked it. A blank Translation stays "needs translation" and the site shows the English.'],
        ['Keep these exactly: codes like %s or %d stand for a number or a name filled in by the site. Every one must appear in the translation, the same number of times.'],
        ['Where it appears: the page(s) the line was seen on, so you can look at it in context.'],
        ['Upload the finished file on Admin -> Manage Translations. MOOP shows what would change and refuses lines with missing codes before anything is saved.'],
    ];
    $x = \Shuchkin\SimpleXLSXGen::fromArray($raw($rows), $language_name);
    $x->setColWidth('A', 50)->setColWidth('B', 50)->setColWidth('C', 18)->setColWidth('D', 14)
      ->setColWidth('E', 30)->setColWidth('F', 30)->freezePanes('A2');
    $x->addSheet($raw($help), 'How to fill this in');
    $x->setColWidth('A', 120);
    return (string) $x;
}

/** Rows from an uploaded .xlsx or .csv file: [[cell, ...], ...], or a string saying what failed. */
function moop_i18n_read_upload(string $path, string $name) {
    $head = (string) @file_get_contents($path, false, null, 0, 4);
    if (strncmp($head, "PK\x03\x04", 4) === 0) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $x = \Shuchkin\SimpleXLSX::parse($path);
        if (!$x) return 'The spreadsheet could not be read: ' . \Shuchkin\SimpleXLSX::parseError();
        return array_map(fn($r) => array_map('strval', $r), $x->rows(0));
    }
    if (preg_match('/\.csv$/i', $name)) {
        $fh = @fopen($path, 'r');
        if (!$fh) return 'The file could not be read.';
        $rows = [];
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) $rows[] = array_map('strval', $r);
        fclose($fh);
        if ($rows && strncmp($rows[0][0] ?? '', "\xEF\xBB\xBF", 3) === 0) $rows[0][0] = substr($rows[0][0], 3);
        return $rows;
    }
    return 'Upload the .xlsx this page exported (or a .csv saved from it).';
}

/**
 * Check an uploaded sheet against the catalog -- nothing is saved here. Returns:
 *   'changes'  [english => new entry]            new or changed translations / statuses / notes
 *   'refused'  [[english, why], ...]             placeholders that do not match, unknown status...
 *   'unknown'  [english, ...]                    English the site does not have (edited, or old)
 *   'kept'     n                                 blank in the sheet, already translated: kept
 *   'same'     n                                 unchanged
 * or ['error' => message].
 */
function moop_i18n_check_upload(string $lang, array $rows, array $known): array {
    if (!$rows) return ['error' => 'The file is empty.'];
    $hdr = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
    $col = fn(string $name) => array_search(strtolower($name), $hdr, true);
    $ci = ['en' => $col('English'), 'tr' => $col('Translation'), 'st' => $col('Status'), 'no' => $col('Notes')];
    if ($ci['tr'] === false) $ci['tr'] = $col('Spanish');
    if ($ci['en'] === false || $ci['tr'] === false) {
        return ['error' => 'The first row must name the columns, including "English" and "Translation". Use the spreadsheet this page exports.'];
    }
    $catalog = moop_i18n_catalog($lang);
    $res = ['changes' => [], 'refused' => [], 'unknown' => [], 'kept' => 0, 'same' => 0];
    foreach (array_slice($rows, 1) as $r) {
        $en = (string) ($r[$ci['en']] ?? '');
        if (trim($en) === '') continue;
        if (!isset($known[$en])) { $res['unknown'][] = $en; continue; }
        $tr     = trim((string) ($r[$ci['tr']] ?? ''));
        $status = strtolower(trim((string) ($ci['st'] !== false ? ($r[$ci['st']] ?? '') : '')));
        $notes  = trim((string) ($ci['no'] !== false ? ($r[$ci['no']] ?? '') : ''));
        $old    = $catalog[$en] ?? [];

        if ($status !== '' && !in_array($status, MOOP_I18N_STATUSES, true)) {
            $res['refused'][] = [$en, 'Status "' . $status . '" is not one of: ' . implode(', ', MOOP_I18N_STATUSES) . '.'];
            continue;
        }
        if ($tr === '') {
            // A blank cell never erases a translation: say so, change nothing else.
            if (trim((string) ($old['text'] ?? '')) !== '') { $res['kept']++; continue; }
            if ($notes === (string) ($old['notes'] ?? '')) { $res['same']++; continue; }
            $res['changes'][$en] = ['text' => '', 'status' => 'needs translation', 'notes' => $notes];
            continue;
        }
        if (!moop_i18n_placeholders_match($en, $tr)) {
            $want = implode(' ', moop_i18n_placeholders($en)) ?: 'none';
            $res['refused'][] = [$en, 'The codes do not match. The English has: ' . $want . '; the translation has: '
                                      . (implode(' ', moop_i18n_placeholders($tr)) ?: 'none') . '.'];
            continue;
        }
        if ($status === '' || $status === 'needs translation') $status = 'machine draft';
        $new = ['text' => $tr, 'status' => $status, 'notes' => $notes];
        if ($new['text'] === (string) ($old['text'] ?? '') && $new['status'] === (string) ($old['status'] ?? '')
            && $new['notes'] === (string) ($old['notes'] ?? '')) { $res['same']++; continue; }
        $res['changes'][$en] = $new;
    }
    return $res;
}

/** Apply checked changes to a language's catalog. Atomic write. Returns ['success', 'message']. */
function moop_i18n_save(string $lang, array $changes, string $who): array {
    if (!preg_match(ConfigManager::LANGUAGE_CODE, $lang) || $lang === 'en') return ['success' => false, 'message' => 'Not a language this page translates into.'];
    $dir = moop_i18n_dir();
    if (!is_dir($dir) && !@mkdir($dir, 02775, true)) {
        return ['success' => false, 'message' => "Could not create $dir -- the web server cannot write there (see Manage Filesystem Permissions)."];
    }
    @chmod($dir, 02775);
    $strings = moop_i18n_catalog($lang, true);
    $when = date('c');
    foreach ($changes as $en => $c) {
        $strings[$en] = ['text' => (string) $c['text'], 'status' => (string) $c['status'], 'notes' => (string) $c['notes'],
                         'updated_at' => $when, 'updated_by' => $who];
    }
    uksort($strings, 'strnatcasecmp');
    $json = json_encode(['language' => $lang, 'strings' => $strings], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $file = "$dir/$lang.json";
    $tmp  = "$dir/.$lang." . getmypid() . '.tmp';
    if ($json === false || @file_put_contents($tmp, $json . "\n") === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        return ['success' => false, 'message' => "Could not write $file -- the web server cannot write there (see Manage Filesystem Permissions)."];
    }
    @chmod($file, 0664);
    moop_i18n_catalog($lang, true);
    return ['success' => true, 'message' => count($changes) . ' line' . (count($changes) === 1 ? '' : 's') . ' saved.'];
}
