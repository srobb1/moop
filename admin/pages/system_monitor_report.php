<?php
/**
 * SYSTEM MONITOR - downloadable reports (2026-11-23). Controller: admin/system_monitor.php?report=it|usage.
 *
 * A standalone page (no site menus, styles inline) that opens in any browser and prints to PDF.
 *   it     -- for the institute's IT group: server load and capacity, what to act on, traffic and how
 *             much was refused, the busiest outside addresses (to block, if they want), security status.
 *   usage  -- for colleagues and funders: how many people, from where, what they used and looked for.
 *             NO addresses, user names or individual visits -- counts only. Zero-result search words
 *             are listed (they are the point: what people want), without who searched.
 *
 * Variables: everything the monitor page gets (see the controller), plus $report.
 */
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$n = fn($x) => number_format((float) $x);
$span = ['24h' => 'the last 24 hours', '7d' => 'the last 7 days', '30d' => 'the last 30 days'][$range];
$period_from = date('j M Y', time() - ['24h' => 86400, '7d' => 7 * 86400, '30d' => 30 * 86400][$range]);
$period_to = date('j M Y, H:i');
$people = $visitors['people'] ?? [];
$kinds = array_count_values(array_column($people, 'kind'));
$g = ($visitors['gate'] ?? []) + ['people' => 0, 'search' => 0, 'agents' => 0, 'turned_away' => 0, 'challenge' => 0, 'other' => 0];
$s429 = (int) ($traffic['s429'] ?? 0);
$let_in = $g['people'] + $g['search'] + $g['agents'] + $g['other'];
$refused = $g['turned_away'] + $g['challenge'] + $s429;
$pct = fn($a, $b) => $b ? round(100 * $a / $b) . '%' : '—';
$org = fn($o) => '<i>' . $h(str_replace('_', ' ', $o)) . '</i>';
$fmt_s = fn($x) => $x === null ? '—' : ($x < 60 ? round($x) . ' s' : ($x < 3600 ? round($x / 60, 1) . ' min' : round($x / 3600, 1) . ' h'));
$title = ($report === 'it' ? 'Server report' : 'Usage report') . ' — ' . $siteTitle;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($title) ?></title>
<style>
  :root { --ink: #1f2933; --muted: #5f6b76; --line: #d9dee3; --wash: #f3f6f8; --accent: #0e7490; }
  body { font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: #fff; margin: 0; }
  main { max-width: 60rem; margin: 0 auto; padding: 24px 16px 48px; }
  h1 { font-size: 1.5rem; margin: 0 0 .2rem; } h2 { font-size: 1.1rem; margin: 1.8rem 0 .5rem; border-bottom: 2px solid var(--accent); padding-bottom: .2rem; }
  .sub { color: var(--muted); margin: 0 0 1rem; }
  .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .6rem; }
  .tile { border: 1px solid var(--line); border-radius: 6px; padding: .5rem .7rem; background: var(--wash); }
  .tile b { display: block; font-size: 1.4rem; } .tile span { color: var(--muted); font-size: .85rem; }
  table { border-collapse: collapse; width: 100%; margin: .3rem 0 .8rem; }
  th, td { text-align: left; padding: .3rem .5rem; border-bottom: 1px solid var(--line); vertical-align: top; }
  th { background: var(--wash); font-weight: 600; } td.r, th.r { text-align: right; white-space: nowrap; }
  .note { color: var(--muted); font-size: .85rem; } .crit { color: #b42318; font-weight: 600; } .warn { color: #a15c07; font-weight: 600; }
  .print { margin: 0 0 1rem; } .print button { font: inherit; padding: .35rem .8rem; border: 1px solid var(--accent); background: #fff; color: var(--accent); border-radius: 4px; cursor: pointer; }
  @media print { .print { display: none; } main { padding: 0; } h2 { break-after: avoid; } tr { break-inside: avoid; } }
  @media (max-width: 600px) { th, td { padding: .25rem .3rem; font-size: .85rem; } }
</style>
</head>
<body><main>
<p class="print"><button type="button" onclick="window.print()">Print or save as PDF</button></p>
<h1><?= $h($title) ?></h1>
<p class="sub"><?= $h($period_from) ?> – <?= $h($period_to) ?> (<?= $h($span) ?>) · <?= $h(parse_url((string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '')) ?></p>

<?php if ($report === 'it'): ?>
<?php
  $sec = $_SESSION['security_updates'] ?? null;
  $libs = $_SESSION['library_advisories'] ?? null;
  $vuln = $libs ? array_sum(array_map(fn($p) => count($p['vulns'] ?? []), $libs['packages'] ?? [])) : null;
  $perm = $_SESSION['perm_summary'] ?? null;
  $errors5 = array_sum(array_column($pages['by_script'] ?? [], 'errors'));
?>
<h2>Summary</h2>
<div class="tiles">
  <div class="tile"><b><?= $n($traffic['req'] ?? 0) ?></b><span>requests to the web server (all files)</span></div>
  <div class="tile"><b><?= $pct($refused, $let_in + $refused) ?></b><span>of page requests refused (bots, by the human check and rate limits)</span></div>
  <div class="tile"><b><?= $n($s429) ?></b><span>refused by nginx rate limits (429)</span></div>
  <div class="tile"><b><?= $n($errors5) ?></b><span>server errors (5xx) from the application</span></div>
  <div class="tile"><b><?= $h($cap['mem_peak'] ?? '—') ?>%</b><span>peak memory used of <?= round(($cap['total_mb'] ?? 0) / 1024, 1) ?> GB; swap peak <?= (int) ($cap['swap_peak_mb'] ?? 0) ?> MB</span></div>
  <div class="tile"><b><?= $h($cap['cpu_avg'] ?? '—') ?>% / <?= $h($cap['cpu_peak'] ?? '—') ?>%</b><span>CPU average / peak</span></div>
</div>

<h2>To act on</h2>
<?php if (!$recs): ?><p>Nothing — resources, workers, errors and rate-limit settings all look fine.</p><?php else: ?>
<table><thead><tr><th>Priority</th><th>What</th><th>Why</th></tr></thead><tbody>
<?php foreach ($recs as $r): ?>
  <tr><td class="<?= $r['level'] === 'critical' ? 'crit' : ($r['level'] === 'warning' ? 'warn' : '') ?>"><?= $r['level'] === 'critical' ? 'Act now' : ($r['level'] === 'warning' ? 'Worth doing' : 'Note') ?></td>
      <td><?= $h($r['title']) ?></td><td class="note"><?= $h($r['detail']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>

<h2>Capacity</h2>
<table><tbody>
  <tr><th>CPU, average / peak</th><td class="r"><?= $h($cap['cpu_avg'] ?? '—') ?>% / <?= $h($cap['cpu_peak'] ?? '—') ?>%</td></tr>
  <tr><th>Share of time CPU was above 80%</th><td class="r"><?= $h($cap['cpu_busy_share'] ?? '—') ?>%</td></tr>
  <tr><th>Memory, peak used</th><td class="r"><?= $h($cap['mem_peak'] ?? '—') ?>% of <?= round(($cap['total_mb'] ?? 0) / 1024, 1) ?> GB</td></tr>
  <tr><th>Swap, peak</th><td class="r"><?= (int) ($cap['swap_peak_mb'] ?? 0) ?> MB</td></tr>
  <tr><th>Waiting on disk (iowait), average / peak</th><td class="r"><?= $h($cap['iowait_avg'] ?? '—') ?>% / <?= $h($cap['iowait_peak'] ?? '—') ?>%</td></tr>
  <tr><th>PHP workers (pm.max_children)</th><td class="r"><?= $h($max_children ?? '—') ?></td></tr>
  <tr><th>Times every PHP worker was busy</th><td class="r"><?= (int) ($cap['workers_reached'] ?? 0) ?></td></tr>
  <tr><th>Memory per busy worker</th><td class="r"><?= $h($cap['per_worker_mb'] ?? '—') ?> MB</td></tr>
  <tr><th>Largest single request</th><td class="r"><?= $h($cap['largest_mb'] ?? '—') ?> MB</td></tr>
</tbody></table>

<h2>Traffic and protection</h2>
<table><thead><tr><th>Page requests to the application</th><th class="r">Requests</th></tr></thead><tbody>
  <tr><td>Let in — people (passed the human check, signed in, or on the institute network)</td><td class="r"><?= $n($g['people']) ?></td></tr>
  <tr><td>Let in — verified search engines (Google, Bing, DuckDuckGo; pages only)</td><td class="r"><?= $n($g['search']) ?></td></tr>
  <tr><td>Let in — agent API (AI assistants and scripts)</td><td class="r"><?= $n($g['agents']) ?></td></tr>
  <tr><td>Let in — anonymous, unchecked (login page; before the human check went on)</td><td class="r"><?= $n($g['other']) ?></td></tr>
  <tr><td>Refused — by the human check (Cloudflare Turnstile)</td><td class="r"><?= $n($g['turned_away']) ?></td></tr>
  <tr><td>Refused — challenge shown, never solved</td><td class="r"><?= $n($g['challenge']) ?></td></tr>
  <tr><td>Refused — nginx per-address rate limits</td><td class="r"><?= $n($s429) ?></td></tr>
</tbody></table>
<p class="note">Turned away by the human check: <?= $n(count($visitors['away_ips'] ?? [])) ?> distinct addresses.</p>

<h2>Busiest outside addresses</h2>
<table><thead><tr><th>Address</th><th>Location</th><th class="r">Requests</th></tr></thead><tbody>
<?php $shown = 0; foreach (($traffic['internet']['ips'] ?? $traffic['ips'] ?? []) as $ip => $cnt): if (moop_monitor_ip_origin((string) $ip, $ranges, $self_ips) !== 'internet') continue; if (++$shown > 25) break; ?>
  <tr><td><code><?= $h($ip) ?></code></td><td><?= $h(moop_geoip_label(moop_geoip_lookup((string) $ip))) ?></td><td class="r"><?= $n($cnt) ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2>Pages that used the most server time</h2>
<table><thead><tr><th>Page</th><th class="r">Requests</th><th class="r">Average</th><th class="r">Slowest 5%</th><th class="r">Peak memory</th><th class="r">Errors</th></tr></thead><tbody>
<?php foreach (array_slice($pages['by_script'] ?? [], 0, 12, true) as $s => $p): ?>
  <tr><td><code><?= $h($s) ?></code></td><td class="r"><?= $n($p['count']) ?></td><td class="r"><?= moop_monitor_fmt_ms($p['mean_ms'] ?? 0) ?></td>
      <td class="r"><?= moop_monitor_fmt_ms($p['p95_ms'] ?? 0) ?></td><td class="r"><?= $h($p['max_mb'] ?? '') ?> MB</td><td class="r"><?= $n($p['errors'] ?? 0) ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2>Security status</h2>
<table><tbody>
  <tr><th>OS security updates pending</th><td><?= $sec === null ? 'not checked' : ($sec['error'] ? 'check failed: ' . $h($sec['error']) : $n($sec['packages'] ?? 0) . ' package(s)' . (!empty($sec['reboot']) ? ' · reboot pending' : '') . ' (checked ' . $h($sec['checked_at'] ?? '') . ')') ?></td></tr>
  <tr><th>Known vulnerabilities in bundled libraries</th><td><?= $vuln === null ? 'not checked' : $n($vuln) ?></td></tr>
  <tr><th>File-permission findings</th><td><?= $perm === null ? 'not checked' : $n($perm['finding_count'] ?? 0) . ' (high ' . $n($perm['high'] ?? 0) . ')' ?></td></tr>
  <tr><th>Human check</th><td><?= !empty(ConfigManager::getInstance()->getArray('turnstile', [])['enabled']) ? 'on (Cloudflare Turnstile)' : 'off' ?></td></tr>
</tbody></table>

<?php else: ?>
<?php
  $outside = array_filter($people, fn($p) => $p['kind'] !== 'internal');
  $by_cc = moop_metrics_visitor_places($people, 'country');
  $by_rg = array_values(array_filter(moop_metrics_visitor_places($people, 'region'), fn($r) => $r['label'] !== 'Institute network'));
  $by_org = moop_metrics_visitor_organisms($people);
  $vs = moop_metrics_visit_stats(array_values(array_filter($visitors['visits'] ?? [], fn($v) => $v['kind'] === 'human')));
  $countries = count(array_filter($by_cc, fn($r) => !in_array($r['label'], ['Unknown', 'Institute network'], true)));
  $miss_words = [];
  foreach ($misses as $m) { $k = mb_strtolower($m['words']); $miss_words[$k] = ['w' => $m['words'], 'n' => ($miss_words[$k]['n'] ?? 0) + 1]; }
  uasort($miss_words, fn($a, $b) => $b['n'] <=> $a['n']);
  $refs = $visitors['referrers'] ?? [];
  uasort($refs, fn($a, $b) => count($b['people']) <=> count($a['people']));
  $ag = $visitors['agents']['by_agent'] ?? [];
?>
<h2>Summary</h2>
<div class="tiles">
  <div class="tile"><b><?= $n(count($people)) ?></b><span>people used the site</span></div>
  <div class="tile"><b><?= $n($kinds['human'] ?? 0) ?></b><span>public visitors from outside</span></div>
  <div class="tile"><b><?= $n(($kinds['user'] ?? 0) + ($kinds['internal'] ?? 0)) ?></b><span>signed-in users and institute staff</span></div>
  <div class="tile"><b><?= $n($countries) ?></b><span>countries</span></div>
  <div class="tile"><b><?= $n(count($by_org)) ?></b><span>organisms used</span></div>
  <div class="tile"><b><?= $n($vs['visits']) ?></b><span>visits by the public; typical length <?= $fmt_s($vs['median_visit_s']) ?></span></div>
</div>
<p class="note">A person is a visitor who passed the site's “are you human” check, someone signed in, or an address on the institute
  network; bots and crawlers are not counted. People are counted by network address, so these are close estimates. Locations come
  from the address (country reliable; region usually right). IP geolocation by DB-IP.</p>

<h2>People per day (last 30 days)</h2>
<table><thead><tr><th>Day</th><th class="r">Public</th><th class="r">Signed in</th><th class="r">Institute</th><th class="r">Total</th></tr></thead><tbody>
<?php foreach ($people_daily as $d => $k): ?>
  <tr><td><?= $h(date('D j M', strtotime($d))) ?></td><td class="r"><?= (int) ($k['human'] ?? 0) ?></td><td class="r"><?= (int) ($k['user'] ?? 0) ?></td>
      <td class="r"><?= (int) ($k['internal'] ?? 0) ?></td><td class="r"><?= array_sum($k) ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2>Where people are</h2>
<table><thead><tr><th>Country</th><th class="r">People</th><th>Organisms they used (people)</th></tr></thead><tbody>
<?php foreach ($by_cc as $r): $o = array_slice($r['orgs'], 0, 5, true); ?>
  <tr><td><?= $h($r['label']) ?></td><td class="r"><?= $n($r['people']) ?></td>
      <td><?= $o ? implode(', ', array_map(fn($k, $v) => $org($k) . " ($v)", array_keys($o), $o)) : '<span class="note">none</span>' ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<table><thead><tr><th>State / region</th><th class="r">People</th></tr></thead><tbody>
<?php foreach (array_slice($by_rg, 0, 30) as $r): ?><tr><td><?= $h($r['label']) ?></td><td class="r"><?= $n($r['people']) ?></td></tr><?php endforeach; ?>
</tbody></table>

<h2>What people used</h2>
<table><thead><tr><th>Organism</th><th class="r">People</th><th class="r">Uses</th><th>Where from (people)</th></tr></thead><tbody>
<?php foreach (array_slice($by_org, 0, 30) as $r): $pl = array_slice($r['places'], 0, 5, true); ?>
  <tr><td><?= $org($r['organism']) ?></td><td class="r"><?= $n($r['people']) ?></td><td class="r"><?= $n($r['uses']) ?></td>
      <td><?= $h(implode(', ', array_map(fn($k, $v) => "$k ($v)", array_keys($pl), $pl))) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="note">Uses = organism, gene and genome-browser pages, searches, BLAST runs and downloads.</p>

<h2>Most used pages (public visitors)</h2>
<table><thead><tr><th>Page</th><th class="r">Views</th><th class="r">Typical time on page</th></tr></thead><tbody>
<?php foreach (array_slice($vs['pages'], 0, 15, true) as $label => $p): ?>
  <tr><td><?= $h($label) ?></td><td class="r"><?= $n($p['views']) ?></td><td class="r"><?= $fmt_s($p['median_s']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2>What people looked for and did not find</h2>
<?php if (!$miss_words): ?><p>No searches came back empty in this period (recorded since 23 Nov 2026).</p><?php else: ?>
<table><thead><tr><th>Searched for (no results)</th><th class="r">Times</th></tr></thead><tbody>
<?php foreach (array_slice($miss_words, 0, 40) as $w): ?><tr><td><code><?= $h($w['w']) ?></code></td><td class="r"><?= (int) $w['n'] ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>

<h2>How people found the site</h2>
<?php if (!$refs): ?><p>No links from other sites recorded in this period (recorded since 23 Nov 2026).</p><?php else: ?>
<table><thead><tr><th>Linked from</th><th class="r">People</th></tr></thead><tbody>
<?php foreach (array_slice($refs, 0, 20, true) as $host => $r): ?><tr><td><?= $h($host) ?></td><td class="r"><?= count($r['people']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>

<h2>AI assistants and programs using the data API</h2>
<?php if (!$ag): ?><p>None in this period.</p><?php else: ?>
<table><thead><tr><th>Assistant / program</th><th class="r">Calls</th></tr></thead><tbody>
<?php foreach ($ag as $name => $a): ?><tr><td><?= $h($name) ?></td><td class="r"><?= $n($a['requests']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php endif; ?>

<p class="note" style="margin-top:2rem">Generated by the site's System Monitor on <?= $h($period_to) ?>.</p>
</main></body></html>
