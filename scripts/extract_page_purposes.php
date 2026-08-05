#!/usr/bin/env php
<?php
/**
 * Extract every page's one-sentence purpose from the RENDERED page.
 *
 * Each user-facing page declares what it is for with page_purpose() (lib/help_ui.php),
 * which renders the sentence visibly AND tags it `data-page-purpose`. This reads those
 * back so the "which page do I want?" router is generated from what the pages actually
 * say — not from a parallel list that drifts.
 *
 * ⚠️ Fetches over HTTP rather than grepping source, deliberately. A grep cannot tell
 * whether a page still renders the line, renders it conditionally, or renders it at all
 * after a refactor — and source-grep has produced several confidently wrong answers in this
 * codebase (CLAUDE.md §12b). If the page does not serve the sentence, it does not have one.
 *
 * ⚠️ Requests go to 127.0.0.1, which is OUTSIDE auto_login_ip_ranges, so this sees the page
 * as an ANONYMOUS visitor — the audience the router is written for. A page whose purpose
 * only appears once you are logged in would show up here as missing, which is the correct
 * signal, not a bug in this script.
 *
 * Usage:
 *   php scripts/extract_page_purposes.php            # table
 *   php scripts/extract_page_purposes.php --json     # machine-readable
 */

$base = 'http://127.0.0.1/moop';

// Pages a visitor can land on. Data pages need real ids in the query string or they
// redirect; these are resolved from the first accessible organism at runtime.
$pages = [
    'index'              => '/',
    'search'             => '/tools/search.php',
    'blast'              => '/tools/blast.php',
    'moopmart'           => '/tools/moopmart.php',
    'downloads'          => '/tools/downloads.php',
    'retrieve_sequences' => '/tools/retrieve_sequences.php',
    'groups'             => '/tools/groups.php?group=Cnidaria',
    'jbrowse2'           => '/jbrowse2.php',
    'about'              => '/about.php',
    'help'               => '/help.php',
];

$json = in_array('--json', $argv, true);
$rows = [];

foreach ($pages as $name => $path) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $html   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $purpose = '';
    if ($html !== false && preg_match('/<p[^>]*data-page-purpose[^>]*>(.*?)<\/p>/s', $html, $m)) {
        // strip_tags because the sentence may carry gloss() spans; collapse whitespace so a
        // sentence wrapped across source lines compares equal to one that is not.
        $purpose = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1]))));
    }
    $rows[] = ['page' => $name, 'url' => $path, 'status' => $status, 'purpose' => $purpose];
}

if ($json) {
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

$missing = 0;
printf("%-20s %-6s %s\n", 'PAGE', 'HTTP', 'PURPOSE');
printf("%-20s %-6s %s\n", str_repeat('-', 20), '----', str_repeat('-', 60));
foreach ($rows as $r) {
    if ($r['purpose'] === '') $missing++;
    printf("%-20s %-6s %s\n", $r['page'], $r['status'],
           $r['purpose'] !== '' ? $r['purpose'] : '— none declared —');
}
printf("\n%d of %d pages declare a purpose; %d missing.\n", count($rows) - $missing, count($rows), $missing);
exit($missing > 0 ? 1 : 0);
