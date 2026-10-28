<?php
/**
 * SYSTEM MONITOR — the "How to read this page" guide and the one-line (i) help for each section.
 * Included by admin/pages/system_monitor.php. Kept in its own file so the page stays readable.
 *
 * Written for the admin who runs the site, not for a sysadmin: every card says what a number
 * means, what normal looks like here, and what to do when it is not. If a section of the page
 * changes, change its card here.
 */

// One-line help for each section header and tile, keyed by a short name the page uses.
$sysmon_help = [
    'recs'      => 'What to act on, most serious first. “Nothing to act on” is the normal state. Each item says what it rests on, so you can check it.',
    'charts'    => 'How things moved over the chosen window. Look for long stretches high up, not short spikes. Hover or use the arrow keys for exact values.',
    'traffic'   => 'Every request the web server answered, including images and scripts — who sent them (browsers, search engines, AI crawlers, scripts) and what they asked for.',
    'addresses' => 'The addresses that sent the most requests. One outside address far above the rest is usually a crawler; Internal ones are your own people.',
    'usage'     => 'Which organisms, assemblies and gene sets people open, search, BLAST and download — your own people and the public counted separately.',
    'pages'     => 'What each page costs the server. Sort by any column. Big “Total time” = where the server spends its effort; big “Slowest 5%” = what feels slow to people.',
    'warmcold'  => 'Whether pages found their data already in RAM (warm, fast) or had to read it from disk (cold, slower). Many cold requests = the databases do not fit in RAM.',
    'who'       => 'Who used the server most: logged-in people by name, everyone else by address. Kept 30 days, admins only.',
    'slowest'   => 'The slowest single requests. A page that took over 2 seconds (or failed) also gets an “open” link that reloads it with the same settings, so you can see the slowness yourself. Faster requests have no link — their settings are not kept.',
    'capacity'  => 'The figures behind “do we need more RAM, CPUs or PHP workers?”, and how to change the number of PHP workers safely.',
    'heavy'     => 'Pages that are expensive to run (exports, downloads, BLAST). Each address may run these only 20 times a minute, so one visitor cannot swamp the server.',
    'sync'      => 'Whether nginx (the web server) is using the IP ranges and heavy group set in MOOP. It should say “In step with MOOP”.',
    // tiles
    'CPU'                    => 'How busy the processors were over the last minute. Below 60% is relaxed; brief jumps are normal. Long stretches above 80% mean more CPUs would help.',
    'Memory used'            => 'RAM held by programs. Linux also fills spare RAM with cached files — that is not counted here, and is good. Above 90%, the server is short of RAM.',
    'PHP workers busy'       => 'How many of the PHP workers (each builds one page at a time) are busy now. If all are busy, visitors wait in a queue.',
    'Databases that fit in RAM' => 'How much of the organism databases RAM can hold at once. Below 100% is normal — people rarely use all of it. “Waiting on disk” says whether it hurts: near 0% means the part people use is in RAM. Status follows the waiting.',
    'Data disk used'         => 'The disk holding your databases, genomes and uploads. Above 80%, plan for space before adding big new data.',
];

/** The (i) for a section or tile; '' if there is no help text for it. */
$sysmon_i = function (string $key) use ($sysmon_help): string {
    return isset($sysmon_help[$key]) ? field_help($sysmon_help[$key]) : '';
};

$sysmon_guide = help_modal(
    'sysmon-guide',
    'How to read the System Monitor',
    [
        ['heading' => 'Start here', 'cards' => [
            ['label' => 'The four questions', 'text' => 'Is the server OK now? (top cards) · Do I need to do anything? (Recommendations) · What is using it? (charts down to Slowest) · Do we need more hardware? (Capacity).'],
            ['label' => 'A two-minute weekly check', 'text' => 'Read Recommendations. Switch to “Last 7 days” and glance at the charts for long high stretches. If nothing stands out, you are done.'],
            ['label' => 'Snapshot or history?', 'text' => 'The top cards are the latest one-minute sample. Everything else covers the window you pick: 24 hours, 7 days or 30 days.'],
            ['label' => 'Where the numbers come from', 'text' => 'A small program on the server samples it every minute, and MOOP notes each page it builds. Nothing here slows the site down.'],
        ]],
        ['heading' => 'Words used on this page', 'cards' => [
            ['label' => 'Request', 'text' => 'One thing a browser asked for: a page, an image, a search. One page view is usually one page request plus several small ones.'],
            ['label' => 'PHP worker', 'text' => 'A server process that builds one page at a time. There are up to 50; if all are busy, new visitors wait.'],
            ['label' => 'Warm / cold', 'text' => 'Warm: the data was already in RAM, so the page is fast. Cold: it had to be read from disk first, so the page is slower.'],
            ['label' => 'Working set', 'text' => 'The part of the databases people actually use. If it fits in RAM, the site stays warm; that decides how much RAM is enough.'],
            ['label' => '429 / refused', 'text' => 'A request turned away because one address asked too fast. Normal for crawlers; a real person should never see it.'],
            ['label' => 'Internal / public', 'text' => 'Internal: the institute network, VPN, and anyone logged in. Public: everyone else.'],
        ]],
        ['heading' => 'Responses (status codes)', 'cards' => [
            ['label' => '2xx', 'text' => 'Success — the page or file was sent.'],
            ['label' => '3xx', 'text' => 'A redirect, for example to the login page. Normal.'],
            ['label' => '4xx', 'text' => 'The request was refused or pointed nowhere: not found, not allowed, or 429 (too fast). Some is always normal.'],
            ['label' => '5xx', 'text' => 'Something broke on the server. Should be near zero; the pages that failed appear in Recommendations.'],
        ]],
        ['heading' => 'Heaviest pages — the columns', 'cards' => [
            ['label' => 'Total time', 'text' => 'All the time the server spent on this page in the window. A fast page used constantly can top this list.'],
            ['label' => 'Typical / Slowest 5% / Slowest', 'text' => 'Typical is the average. Slowest 5% is what the unluckiest 1 in 20 visitors waited. Slowest is the single worst case.'],
            ['label' => 'Memory', 'text' => 'RAM one request used. Peak memory is what can run the server out of RAM when many run at once.'],
            ['label' => 'In database / Cold', 'text' => 'Share of the page’s time spent in the database, and share of its requests that read from disk. High on both: the page waits on data, not code.'],
            ['label' => 'Why slow (Slowest requests)', 'text' => 'Cold database: waited on the disk for data. Database (warm): data was in RAM but the query is slow. PHP / other: not the database.'],
            ['label' => 'Limit', 'text' => 'Which rate limit nginx applies to the page: page, api (background requests), jbrowse (genome browser) or heavy (exports and downloads).'],
        ]],
        ['heading' => 'When to act', 'cards' => [
            ['label' => 'Add RAM', 'text' => 'Many cold requests with high disk wait, memory above about 90%, any swapping, or all PHP workers busy with no room for more.'],
            ['label' => 'Add CPUs', 'text' => 'CPU above 80% for long stretches while memory is fine — after checking Busiest addresses for a crawler.'],
            ['label' => 'Add PHP workers', 'text' => '“All PHP workers busy” keeps appearing and Capacity shows more fit in RAM. The steps are in the Capacity section.'],
            ['label' => 'A crawler', 'text' => 'One outside address or many AI crawlers near the top of Traffic. Rate limits already slow them; Turnstile (Site Configuration) stops them.'],
        ]],
    ],
    ['intro' => 'What each part of this page tells you, what normal looks like here, and when to act.']
);
