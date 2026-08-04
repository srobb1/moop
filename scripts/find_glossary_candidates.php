#!/usr/bin/env php
<?php
/**
 * Find glossary terms sitting un-glossed in page text.
 *
 * Reports a WORKLIST — file, line, context, proposed wrap — for a human to tick. It never
 * edits anything, and that is deliberate. Wrapping every occurrence automatically is wrong
 * in four ways that need judgement, all of them common in this codebase:
 *
 *   1. Convention is FIRST OCCURRENCE per page. Ten dotted underlines on ten "protein"s
 *      reads as a rendering fault, not as help.
 *   2. Occurrences inside a link or button — the popover fights the click.
 *   3. Proper noun against concept: "Gene Ontology" the database, "gene ontology" the idea.
 *      Only one of them wants a definition.
 *   4. A term inside a longer name that is already meaningful: "Gene Ontology annotations"
 *      does not want "annotation" glossed separately inside it.
 *
 * ── HOW IT DISCRIMINATES ─────────────────────────────────────────────────────────────
 *
 * It reads each file with PHP's own tokenizer and looks ONLY at T_INLINE_HTML — the literal
 * text between ?> and <?php, i.e. what the file actually prints. That single choice buys
 * most of the precision for free:
 *
 *   - PHP code, variable names and comments are different token types, so `$gene_set_id`
 *     and a comment mentioning "assembly" never match.
 *   - An ALREADY-WRAPPED term is invisible to it. `<?= gloss('domain') ?>` puts the word in
 *     a PHP string, not in inline HTML, so wrapped terms cannot be reported as unwrapped.
 *     No separate "is it already glossed" check is needed, and none can go stale.
 *
 * Within each inline-HTML chunk, tags and script/style bodies are masked out before
 * matching, so class names, ids and attribute values do not produce hits. Masking replaces
 * the span with spaces rather than deleting it, so byte offsets — and therefore reported
 * line numbers — stay true.
 *
 * ⚠️ SCOPE: this finds STATIC page text only. A term rendered from data — an annotation
 * source name in a table cell, a type label from annotation_config.json — is not in the
 * source at all and cannot be found this way. Those want wrapping at the point the value is
 * rendered, once, not per row. The summary says how many files were skipped for this.
 *
 * Usage:
 *   php scripts/find_glossary_candidates.php                # default page directories
 *   php scripts/find_glossary_candidates.php --all          # include lib/ and admin/pages/
 *   php scripts/find_glossary_candidates.php --term=domain  # one term
 *   php scripts/find_glossary_candidates.php --first        # first hit per file per term
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$base = dirname(__DIR__);
require_once $base . '/lib/glossary.php';

$opts = getopt('', ['all', 'term:', 'first', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "See the docblock at the top of this file.\n");
    exit(0);
}

$terms = glossary_terms();                       // keys already lower-cased
if (!$terms) {
    fwrite(STDERR, "No glossary terms found — is metadata/glossary.json readable?\n");
    exit(1);
}
if (isset($opts['term'])) {
    $t = strtolower($opts['term']);
    if (!isset($terms[$t])) {
        fwrite(STDERR, "Not a glossary term: {$opts['term']}\n");
        exit(1);
    }
    $terms = [$t => $terms[$t]];
}

// Longest first, so "gene set" is matched and consumed before "gene" can claim its prefix.
$term_keys = array_keys($terms);
usort($term_keys, fn($a, $b) => strlen($b) <=> strlen($a));

$dirs = ['tools/pages', 'includes'];
if (isset($opts['all'])) { $dirs[] = 'lib'; $dirs[] = 'admin/pages'; }

$files = [];
foreach ($dirs as $d) {
    $full = "$base/$d";
    if (!is_dir($full)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') $files[] = $f->getPathname();
    }
}
sort($files);

/**
 * Blank out everything that is not visible prose: script/style bodies, then any tag.
 * Replaced with spaces of equal length so offsets — and line numbers — survive.
 */
/**
 * A term and its plural, as one alternation.
 *
 * Plurals have to match or the tool reports the wrong thing: without this, "gene sets"
 * yields no `gene set` hit and a spurious `gene` one. Only the two regular English forms
 * are handled — trailing -s, and -y becoming -ies. That is enough for the vocabulary here
 * (assembly/assemblies, feature/features) and anything irregular simply matches its
 * singular, which is a missed hit rather than a wrong one.
 */
function term_pattern(string $term): string {
    $forms = [$term, $term . 's'];
    if (substr($term, -1) === 'y') $forms[] = substr($term, 0, -1) . 'ies';
    usort($forms, fn($a, $b) => strlen($b) <=> strlen($a));   // longest alternative first
    return '(?:' . implode('|', array_map(fn($f) => preg_quote($f, '/'), $forms)) . ')';
}

function mask_markup(string $html, bool &$in_tag): string {
    $mask = function ($m) { return str_repeat(' ', strlen($m[0])); };
    $html = preg_replace_callback('#<script\b.*?</script>#is', $mask, $html) ?? $html;
    $html = preg_replace_callback('#<style\b.*?</style>#is',   $mask, $html) ?? $html;
    $html = preg_replace_callback('#<!--.*?-->#s',             $mask, $html) ?? $html;

    // Tag state has to CARRY BETWEEN CHUNKS. A short-echo tag inside an HTML tag — say an
    // <input> whose placeholder is literal text and whose value comes from PHP — splits the
    // inline HTML into fragments, and the fragment after each echo begins in the MIDDLE of
    // the tag, with no `<` to anchor a regex. Masking each chunk independently therefore
    // treated attribute text as prose: the first version of this tool proposed wrapping
    // "assembly" inside a placeholder="" and "gene" inside a data- attribute. Applying that
    // would not merely be noise, it would break the markup.
    //
    // (Note for editors: never write a literal PHP close-tag inside a // comment here. It
    // ends PHP mode even in a comment, which is exactly how this file was broken once.)
    $out = '';
    $len = strlen($html);
    for ($i = 0; $i < $len; $i++) {
        $c = $html[$i];
        if ($in_tag) {
            $out .= ($c === "\n") ? "\n" : ' ';     // keep newlines so line numbers hold
            if ($c === '>') $in_tag = false;
        } else {
            if ($c === '<') { $in_tag = true; $out .= ' '; }
            else            { $out .= $c; }
        }
    }
    return $out;
}

$rows = [];
$seen_first = [];          // "file|term" => true, for --first
$dynamic_only = 0;

foreach ($files as $path) {
    $src = @file_get_contents($path);
    if ($src === false) continue;

    $tokens = @token_get_all($src);
    if (!$tokens) continue;

    $had_inline = false;
    $in_tag = false;   // carries across this file's inline-HTML chunks
    foreach ($tokens as $tok) {
        if (!is_array($tok) || $tok[0] !== T_INLINE_HTML) continue;
        $had_inline = true;
        $chunk = $tok[1];
        $start_line = $tok[2];
        $text = mask_markup($chunk, $in_tag);

        // Collect every hit first, then resolve overlaps. Matching term by term and
        // reporting as we go was wrong: "gene sets" produced a hit for `gene`, because
        // `gene set` cannot match the plural and `gene` happily matches its prefix. That
        // is failure mode 4 from the docblock, produced by the tool itself.
        $hits = [];
        foreach ($term_keys as $term) {
            $re = '/(?<![A-Za-z0-9])' . term_pattern($term) . '(?![A-Za-z0-9])/i';
            if (!preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE)) continue;
            foreach ($m[0] as [$hit, $off]) {
                $hits[] = ['off' => $off, 'len' => strlen($hit), 'text' => $hit, 'key' => $term];
            }
        }

        // Longest wins. A shorter term whose span falls inside an accepted one is dropped,
        // so `gene` inside "gene sets" disappears and "gene sets" is offered once.
        usort($hits, fn($a, $b) => $b['len'] <=> $a['len']);
        $kept = [];
        foreach ($hits as $h) {
            foreach ($kept as $k) {
                if ($h['off'] < $k['off'] + $k['len'] && $k['off'] < $h['off'] + $h['len']) {
                    continue 2;   // overlaps something longer
                }
            }
            $kept[] = $h;
        }

        foreach ($kept as $h) {
            $key = $path . '|' . $h['key'];
            if (isset($opts['first']) && isset($seen_first[$key])) continue;
            $seen_first[$key] = true;

            $line = $start_line + substr_count(substr($chunk, 0, $h['off']), "\n");
            $ctx = substr($chunk, max(0, $h['off'] - 42), 100);
            $ctx = trim(preg_replace('/\s+/', ' ', $ctx));

            $rows[] = [
                'file' => ltrim(str_replace($base, '', $path), '/'),
                'line' => $line,
                'term' => $h['text'],
                'key'  => $h['key'],
                'ctx'  => $ctx,
            ];
        }
    }
    if (!$had_inline) $dynamic_only++;
}

usort($rows, fn($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

if (!$rows) {
    fwrite(STDOUT, "No un-glossed glossary terms found in static page text.\n");
    exit(0);
}

$by_term = [];
$cur = null;
foreach ($rows as $r) {
    $by_term[$r['key']] = ($by_term[$r['key']] ?? 0) + 1;
    if ($cur !== $r['file']) { $cur = $r['file']; fwrite(STDOUT, "\n" . $cur . "\n"); }
    fwrite(STDOUT, sprintf("  %5d  %-16s %s\n", $r['line'], $r['term'], $r['ctx']));
    fwrite(STDOUT, sprintf("         %-16s -> <?= gloss('%s'%s) ?>\n", '',
        $r['key'],
        strtolower($r['term']) === $r['key'] ? '' : ", '" . $r['term'] . "'"));
}

arsort($by_term);
fwrite(STDOUT, "\n" . str_repeat('-', 64) . "\n");
fwrite(STDOUT, sprintf("%d occurrence(s) across %d file(s), %d term(s)\n",
    count($rows), count(array_unique(array_column($rows, 'file'))), count($by_term)));
foreach ($by_term as $t => $n) fwrite(STDOUT, sprintf("  %-18s %d\n", $t, $n));
fwrite(STDOUT, "\nReview each before wrapping — first occurrence per page only, and skip\n");
fwrite(STDOUT, "anything inside a link, a button, or a longer proper name.\n");
if ($dynamic_only) {
    fwrite(STDOUT, sprintf("\n%d file(s) emit no static HTML; terms rendered from data are not\n", $dynamic_only));
    fwrite(STDOUT, "visible to this tool and want wrapping where the value is rendered.\n");
}
