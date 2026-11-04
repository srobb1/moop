<?php
/**
 * Visitor text that is not inside t() -- a RATCHET, not a zero-tolerance check (CLAUDE.md 7b).
 *
 *   php tests/i18n_unwrapped_check.php            exit 1 if a template gained unwrapped text
 *   php tests/i18n_unwrapped_check.php --update   rewrite the baseline (after wrapping, or
 *                                                 deliberately accepting a new leftover)
 *
 * WHAT IT READS. The HTML parts of the visitor templates (PHP's own tokens, T_INLINE_HTML --
 * text inside PHP code is not looked at): every text node with real words in it, and every
 * placeholder / title / aria-label / alt attribute with a literal value. Text inside <script>,
 * <style>, <pre>, <code> and <textarea> is code or data, not interface, and is skipped.
 *
 * WHY A BASELINE. On 2026-11-04 the visitor templates still held a few hundred known leftovers:
 * the system-administration help pages (installation documentation, deliberately English) and
 * fragments that only make sense inside a PHP-built sentence. Failing on those would make the
 * check useless; ignoring them would let new ones in. So tests/i18n_unwrapped_baseline.json
 * records exactly what is there now, file by file, and the check fails only on text that is
 * NOT in it. Wrap something and its entry is reported as gone -- run --update to shrink the
 * baseline. The baseline should only ever get smaller.
 *
 * Admin pages are not checked: they are always English (CLAUDE.md 7b). Hermetic: reads source
 * files only.
 */

// MOOP_I18N_ROOT points the check at another checkout -- how it is proven to fail (a copy
// with one bare sentence added must exit 1).
$ROOT     = rtrim(getenv('MOOP_I18N_ROOT') ?: dirname(__DIR__), '/');
$BASELINE = __DIR__ . '/i18n_unwrapped_baseline.json';

// The visitor templates: every page content file, the shared visitor includes, the toolbox.
// includes/ also holds non-template PHP (bootstrap, config, session); listed here as skipped.
$NOT_TEMPLATES = ['access_control.php', 'ConfigManager.php', 'config_init.php', 'session_init.php',
                  'asset_url.php', 'deprecation_log.php', 'fatal_log.php', 'head-resources.php',
                  'layout.php', 'source-selector-helpers.php', 'view_as_banner.php'];
$files = array_merge(
    glob("$ROOT/tools/pages/*.php"),
    glob("$ROOT/tools/pages/help/*.php"),
    array_filter(glob("$ROOT/includes/*.php"), fn($f) => !in_array(basename($f), $NOT_TEMPLATES, true)),
    ["$ROOT/lib/tool_section.php"]
);
sort($files);

/** Unwrapped visitor text in one PHP template: list of strings (whitespace collapsed). */
function moop_unwrapped_text(string $src): array {
    $found  = [];
    $in_tag = false;   // a chunk can start inside a tag whose attributes PHP is writing
    $raw    = null;    // inside <script>/<style>/<pre>/<code>/<textarea>
    foreach (token_get_all($src) as $tk) {
        if (!is_array($tk) || $tk[0] !== T_INLINE_HTML) continue;
        $html  = $tk[1];
        $parts = preg_split('/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $p) {
            $is_tag = $i % 2 === 1;
            if (!$is_tag && $in_tag) {
                // The tail of a tag opened before this chunk: skip to its '>'.
                $gt = strpos($p, '>');
                if ($gt === false) continue;
                $p = substr($p, $gt + 1);
                $in_tag = false;
            }
            if ($is_tag) {
                if (strpos($p, '<!--') === 0) continue;
                if (preg_match('#^<(script|style|textarea|pre|code)\b#i', $p, $m) && substr($p, -2) !== '/>') { $raw = strtolower($m[1]); continue; }
                if ($raw && preg_match('#^</' . $raw . '\s*>#i', $p)) { $raw = null; continue; }
                if ($raw) continue;
                if (preg_match_all('/\b(placeholder|title|aria-label|alt)="([^"<>{}$]*)"/', $p, $am, PREG_SET_ORDER)) {
                    foreach ($am as $a) {
                        if (preg_match_all('/[A-Za-z]/', $a[2]) >= 2) $found[] = $a[1] . '="' . trim($a[2]) . '"';
                    }
                }
                continue;
            }
            if ($raw) continue;
            // A text part that opens a tag PHP finishes (`<option value="`) is markup, not text.
            $lt = strrpos($p, '<');
            if ($lt !== false) $p = substr($p, 0, $lt);
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            // Real words only: at least two letters in a row, not just punctuation / a symbol.
            if (!preg_match('/\pL{2,}/u', $text)) continue;
            $found[] = $text;
        }
        $lt = strrpos($html, '<');
        $gt = strrpos($html, '>');
        $in_tag = $lt !== false && ($gt === false || $lt > $gt) && strpos(substr($html, $lt, 4), '<!--') !== 0;
    }
    return $found;
}

$now = [];
foreach ($files as $f) {
    $rel = substr($f, strlen($ROOT) + 1);
    $list = moop_unwrapped_text((string) file_get_contents($f));
    if ($list) $now[$rel] = array_count_values($list);
}
ksort($now);

if (in_array('--update', $argv, true)) {
    foreach ($now as &$counts) ksort($counts);
    unset($counts);
    file_put_contents($BASELINE, json_encode($now, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    $n = array_sum(array_map('array_sum', $now));
    echo "Baseline written: $n unwrapped pieces in " . count($now) . " files.\n";
    exit(0);
}

$base = json_decode((string) @file_get_contents($BASELINE), true);
if (!is_array($base)) {
    echo "No baseline at $BASELINE -- run: php tests/i18n_unwrapped_check.php --update\n";
    exit(1);
}

$new = [];
$gone = 0;
foreach ($now as $file => $counts) {
    foreach ($counts as $text => $n) {
        $had = (int) ($base[$file][$text] ?? 0);
        if ($n > $had) $new[] = "$file: \"$text\"" . ($n - $had > 1 ? ' (x' . ($n - $had) . ')' : '');
    }
}
foreach ($base as $file => $counts) {
    foreach ($counts as $text => $n) $gone += max(0, $n - (int) ($now[$file][$text] ?? 0));
}

if ($new) {
    echo "Visitor text not inside t() -- wrap it (CLAUDE.md 7b), e.g. <?= t('Log In') ?>:\n";
    foreach ($new as $line) echo "  - $line\n";
    echo "\n" . count($new) . " new. If one is not interface text, or must stay English, accept it with\n"
       . "  php tests/i18n_unwrapped_check.php --update\n";
    exit(1);
}
echo 'No new unwrapped visitor text (' . array_sum(array_map('array_sum', $now)) . ' known leftovers'
   . ($gone ? "; $gone wrapped since the baseline -- run --update to shrink it" : '') . ").\n";
exit(0);
