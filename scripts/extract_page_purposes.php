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
// label      => [probe url, linkable?, human title]
// 'linkable' false = the page needs an organism/assembly/gene-set id, so there is no stable
// URL to send someone to. Those are reached by starting at the home page and picking one —
// which is exactly what the router tells the reader to do, rather than inventing a link that
// only works for whichever organism happened to be public when the page was written.
$pages = [
    'index'              => '/',
    'search'             => '/tools/search.php',
    'blast'              => '/tools/blast.php',
    'primer_blast'       => '/tools/primer_blast.php',
    'moopmart'           => '/tools/moopmart.php',
    'downloads'          => '/tools/downloads.php',
    'retrieve_sequences' => '/tools/retrieve_sequences.php',
    'groups'             => '/tools/groups.php?group=Cnidaria',
    // Data pages need real ids or they redirect. These use the first PUBLIC assembly, so the
    // check keeps working for an anonymous visitor — which is who the router is written for.
    'organism'           => '/tools/organism.php?organism=Nematostella_vectensis',
    'assembly'           => '/tools/assembly.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1',
    'gene_set'           => '/tools/gene_set.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2',
    'multi_organism'     => '/tools/multi_organism.php?organisms=Nematostella_vectensis',
    'jbrowse2'           => '/jbrowse2.php',
    'about'              => '/about.php',
    'help'               => '/help.php',
];

// Per-route presentation data. Kept here, beside the probe list, so a page cannot be probed
// without also declaring how a reader reaches it.
$meta = [
    'index'              => ['title' => 'Home',               'link' => '/',                          'linkable' => true],
    'search'             => ['title' => 'Annotation Search',  'link' => '/tools/search.php',          'linkable' => true],
    'blast'              => ['title' => 'BLAST',              'link' => '/tools/blast.php',           'linkable' => true],
    'primer_blast'       => ['title' => 'Primer BLAST',       'link' => '/tools/primer_blast.php',    'linkable' => true],
    'moopmart'           => ['title' => 'MOOPmart',           'link' => '/tools/moopmart.php',        'linkable' => true],
    'downloads'          => ['title' => 'Downloads',          'link' => '/tools/downloads.php',       'linkable' => true],
    'retrieve_sequences' => ['title' => 'Retrieve Sequences', 'link' => '/tools/retrieve_sequences.php', 'linkable' => true],
    // reached_by: written per route, not a generic line appended to whatever has no link.
    // These pages sit in a chain — organism -> assembly -> gene set — and saying "pick an
    // organism first" on the gene-set card is simply wrong. Each says the step that actually
    // gets you there.
    // groups and multi_organism LOOK alike and ACT alike — same page shape, same search,
    // differing only in how the organism set is chosen. Two near-identical cards would be
    // the same duplication this whole effort removes, so they render as ONE.
    'groups'   => ['title' => 'Group page', 'link' => null, 'linkable' => false,
                   'combine' => 'multi',
                   'reached_by' => 'From the home page — choose a curated group of organisms.'],
    'multi_organism' => ['title' => 'Group page', 'link' => null, 'linkable' => false,
                   'combine' => 'multi',
                   'reached_by' => 'From the home page — or build your own selection of organisms.'],
    'organism' => ['title' => 'Organism page', 'link' => null, 'linkable' => false,
                   'reached_by' => 'From the home page — pick an organism.'],
    'assembly' => ['title' => 'Assembly page', 'link' => null, 'linkable' => false,
                   'reached_by' => ['From an organism page — pick one of its assemblies.',
                                    'Or search for it by name from the home page.']],
    'gene_set' => ['title' => 'Gene set page', 'link' => null, 'linkable' => false,
                   'reached_by' => ['From an assembly page — pick one of its gene sets.',
                                    'Or search for it by name from the home page.']],
    'jbrowse2'           => ['title' => 'Genome Browser',     'link' => '/jbrowse2.php',              'linkable' => true],
];

$json  = in_array('--json',  $argv, true);
$write = in_array('--write', $argv, true);
$rows  = [];

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
    // Matches either form page_purpose() emits: a visible <p>, or a hidden <span> on a page
    // that already says the same thing visually and declares its purpose for the router only.
    if ($html !== false && preg_match('/<(p|span)[^>]*data-page-purpose[^>]*>(.*?)<\/\1>/s', $html, $m)) {
        // strip_tags because the sentence may carry gloss() spans; collapse whitespace so a
        // sentence wrapped across source lines compares equal to one that is not.
        $purpose = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[2]))));
    }
    $m = $meta[$name] ?? ['title' => $name, 'link' => null, 'linkable' => false];
    $rows[] = [
        'page'     => $name,
        'title'    => $m['title'],
        'link'     => $m['link'],
        'linkable' => $m['linkable'],
        'reached_by' => (array)($m['reached_by'] ?? []),
        'combine'    => $m['combine'] ?? '',
        'url'      => $path,
        'status'   => $status,
        'purpose'  => $purpose,
    ];
}

if ($write) {
    // Generated artifact, refreshed by re-running this script — the same idiom as the
    // organism and annotation caches. Only routes that actually declared a purpose are
    // written, so the router can never list a page that no longer says what it is for.
    $out = array_values(array_filter($rows, fn($r) => $r['purpose'] !== '' && isset($meta[$r['page']])));

    // Collapse routes sharing a 'combine' key into one card: first purpose wins, and every
    // way of reaching it is listed. Keeps the page list honest (both pages still probed and
    // still required to declare a purpose) while the reader sees one concept.
    $merged = [];
    foreach ($out as $r) {
        $k = $r['combine'] !== '' ? 'c:' . $r['combine'] : 'p:' . $r['page'];
        if (!isset($merged[$k])) {
            $merged[$k] = $r;
        } else {
            $merged[$k]['reached_by'] = array_values(array_unique(
                array_merge($merged[$k]['reached_by'], $r['reached_by'])
            ));
        }
    }
    $out = array_values($merged);
    // docs/, not metadata/: metadata/*.json is gitignored SITE data managed through the
    // admin UI, and this is derived from the app's own pages — reproducible from a checkout.
    // Same home and same reasoning as docs/function_registry.json, which is tracked.
    $file = __DIR__ . '/../docs/page_purposes.json';
    file_put_contents($file, json_encode([
        'generated' => date('c'),
        'routes'    => $out,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    @chmod($file, 0664);
    echo "Wrote " . count($out) . " routes to docs/page_purposes.json\n";
    exit(0);
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
