<?php
/**
 * SYSTEM MONITOR - Content File. Controller: admin/system_monitor.php.
 *
 * Variables: $range ('24h'|'7d'), $age, $collector_ok, $sync, $tiles, $now, $traffic, $pages,
 *            $max_children, $ranges, $heavy, $cap, $recs, $flash, $usage, $days_covered, $site
 * Charts are drawn by js/system-monitor.js from window.MOOP_MONITOR.series; every chart has the
 * same numbers in the data table below it, so nothing is hover-only.
 */
$h = fn($s) => htmlspecialchars((string) $s);
require __DIR__ . '/system_monitor_help.php';   // $sysmon_guide, $sysmon_i()
$level_icon = ['good' => 'check-circle', 'warning' => 'exclamation-triangle', 'critical' => 'times-circle', 'unknown' => 'question-circle'];
$level_word = ['good' => 'OK', 'warning' => 'Watch', 'critical' => 'High', 'unknown' => 'Unknown'];
$span = ['24h' => 'last 24 hours', '7d' => 'last 7 days', '30d' => 'last 30 days'][$range];
$per  = ['24h' => 'minute', '7d' => 'hour', '30d' => 'day'][$range];
$pct = fn($n, $total) => $total > 0 ? round(100 * $n / $total) . '%' : '—';
// Row labels. What each one includes is spelled out in the guide (system_monitor_help.php) — keep both in step.
$ua_words = ['browser' => 'Web browsers', 'search' => 'Search engines', 'ai' => 'AI crawlers', 'script' => 'Scripts & automated tools',
             'other' => 'Unrecognised programs', 'none' => 'Did not say what it is'];
$origin_words = moop_monitor_origin_words();
$cls_words = ['page' => 'Pages', 'api' => 'AJAX / API', 'jbrowse' => 'Genome browser', 'heavy' => 'Exports, downloads, BLAST',
              'static' => 'Static files (CSS, JS, images)'];
?>

<div class="container-fluid px-4 mt-4 sysmon">

  <div class="d-flex flex-wrap align-items-center gap-3">
    <?= page_title('System Monitor', 'fa fa-chart-line') ?>
    <?= help_modal_trigger('sysmon-guide', 'How to read this page') ?>
  </div>
  <p class="text-muted mb-3">
    What the server is doing: resources, traffic, the heaviest pages and who is using them.
    <?php if ($collector_ok): ?>
      Last sample <?= (int) $age ?> s ago.
    <?php endif; ?>
  </p>

  <?php if (!$collector_ok): ?>
    <div class="alert alert-warning">
      <i class="fa fa-exclamation-triangle me-1"></i>
      <?php if ($age === null): ?>
        The collector has not written any data yet. It is installed with
        <code>sudo scripts/install_monitor.sh</code> (README Quick Start step 7).
      <?php else: ?>
        The collector's last sample is <?= (int) floor($age / 60) ?> minutes old — its timer is not running
        (<code>systemctl status moop-monitor.timer</code>). Figures below are from then.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- One filter row, above everything it scopes -->
  <div class="sysmon-filters mb-3" role="group" aria-label="Time range">
    <a class="btn btn-sm <?= $range === '24h' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?range=24h">Last 24 hours</a>
    <a class="btn btn-sm <?= $range === '7d' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?range=7d">Last 7 days</a>
    <a class="btn btn-sm <?= $range === '30d' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?range=30d">Last 30 days</a>
    <?php if ($range === '30d' && $days_covered !== null && $days_covered < 30): ?>
      <span class="small text-muted align-self-center">Page and visitor figures cover <?= (int) $days_covered ?> day(s) so far — the log started 2026-10-28.</span>
    <?php endif; ?>
  </div>

  <!-- Right now -->
  <?php if ($tiles): ?>
  <h2 class="h6 text-muted mb-2">Right now <span class="fw-normal">— the latest one-minute sample (CPU: average over that
    minute; the rest: at that moment). Averages and peaks are in the charts and under Capacity.</span></h2>
  <div class="sysmon-tiles mb-4">
    <?php foreach ($tiles as $t): ?>
      <div class="sysmon-tile">
        <div class="sysmon-tile-label"><?= $h($t['label']) ?> <?= $sysmon_i(strpos($t['label'], 'Disk filling') === 0 ? 'Data disk used' : $t['label']) ?></div>
        <div class="sysmon-tile-value"><?= $h($t['value']) ?></div>
        <div class="sysmon-tile-sub"><?= $h($t['sub']) ?></div>
        <div class="sysmon-status sysmon-<?= $h($t['level']) ?>">
          <i class="fa fa-<?= $level_icon[$t['level']] ?>" aria-hidden="true"></i> <?= $level_word[$t['level']] ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (($pages['skipped'] ?? 0) > 0): ?>
    <div class="alert alert-warning small">
      <i class="fa fa-exclamation-triangle me-1" aria-hidden="true"></i>
      The request log hit its safety limit in this window (a day's log over <?= MOOP_METRICS_DAILY_CAP_MB ?> MB, or the disk
      under <?= MOOP_METRICS_MIN_FREE_PCT ?>% free): <?= number_format($pages['skipped']) ?> ordinary fast requests were counted but
      not logged, so page and usage figures below are incomplete. Slow and failed requests were all kept. Usually a crawler —
      see Busiest addresses.
    </div>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="alert <?= $flash[0] === 'ok' ? 'alert-success' : 'alert-danger' ?>"><?= $h($flash[1]) ?></div>
  <?php endif; ?>

  <!-- Recommendations: what to act on, most serious first -->
  <?php
    $worst = $recs ? $recs[0]['level'] : null;
    $rec_head = $worst === 'critical' ? 'adm-head-danger' : ($worst === 'warning' ? 'adm-head-warn' : 'adm-head');
  ?>
  <div class="card adm-card mb-4" id="sysmon-recs">
    <div class="card-header <?= $rec_head ?>"><h5 class="mb-0">Recommendations <?= $sysmon_i('recs') ?></h5></div>
    <div class="card-body">
      <?php if (!$recs): ?>
        <span class="sysmon-status sysmon-good"><i class="fa fa-check-circle" aria-hidden="true"></i> Nothing to act on.</span>
        <span class="small text-muted">Resources, workers, errors and rate-limit settings all look fine for the <?= $span ?>
          (pages judged on the last 7 days).</span>
      <?php else: ?>
        <ul class="list-unstyled mb-0 sysmon-recs">
        <?php foreach ($recs as $r): ?>
          <li class="sysmon-rec">
            <div class="sysmon-status sysmon-<?= $h($r['level']) ?>">
              <i class="fa fa-<?= $level_icon[$r['level']] ?? 'info-circle' ?>" aria-hidden="true"></i>
              <?= $r['level'] === 'critical' ? 'Act now' : ($r['level'] === 'warning' ? 'Worth doing' : 'Note') ?>
            </div>
            <div class="sysmon-rec-body">
              <strong><?= $h($r['title']) ?></strong>
              <div class="small text-muted"><?= $h($r['detail']) ?></div>
              <?php if (($r['action']['type'] ?? '') === 'heavy_add'): ?>
                <form method="post" action="system_monitor.php<?= $range !== '24h' ? '?range=' . $h($range) : '' ?>" class="mt-1">
                  <?= csrf_input_field() ?>
                  <input type="hidden" name="action" value="heavy_add">
                  <input type="hidden" name="script" value="<?= $h($r['action']['script']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-primary">Add to heavy group</button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- Charts: one measure each (never two y-axes) -->
  <div class="card adm-card mb-4">
    <div class="card-header adm-head"><h5 class="mb-0">Over the <?= $span ?> <?= $sysmon_i('charts') ?></h5></div>
    <div class="card-body">
      <div class="sysmon-charts">
        <figure class="sysmon-chart" data-key="cpu" data-unit="%" data-max="100">
          <figcaption>CPU used</figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="mem" data-unit="%" data-max="100">
          <figcaption>Memory used</figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="req" data-unit=" requests">
          <figcaption>Requests per <?= $per ?></figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="fpm" data-unit=" busy"<?= $max_children ? ' data-max="' . (int) $max_children . '"' : '' ?>>
          <figcaption>PHP workers busy<?= $max_children ? ' (of ' . (int) $max_children . ')' : '' ?></figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="iowait" data-unit="%">
          <figcaption>Waiting on disk (iowait) — high means cold database reads</figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="dread" data-unit=" MB/s">
          <figcaption>Reads from the database disk</figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="s429" data-unit=" refused">
          <figcaption>Requests refused by rate limits (429) per <?= $per ?></figcaption><div class="sysmon-plot"></div>
        </figure>
      </div>
      <details class="mt-2">
        <summary class="small text-muted">Show these numbers as a table</summary>
        <div class="sysmon-table-wrap"><table class="table table-sm sysmon-num" id="sysmon-series-table"></table></div>
      </details>
    </div>
  </div>

  <div class="row g-4">
    <!-- Traffic -->
    <div class="col-xl-6">
      <div class="card adm-card h-100">
        <div class="card-header adm-head"><h5 class="mb-0">Traffic, <?= $span ?> <?= $sysmon_i('traffic') ?></h5></div>
        <div class="card-body">
          <p class="small text-muted mb-2">
            <?= number_format($traffic['req']) ?> requests · <?= moop_monitor_fmt_bytes($traffic['bytes']) ?> sent ·
            <strong><?= number_format($traffic['s429']) ?></strong> refused by rate limits
            <?php if ($traffic['fpm_max_children_reached'] > 0): ?>
              · <span class="sysmon-status sysmon-critical"><i class="fa fa-times-circle" aria-hidden="true"></i>
              all PHP workers busy <?= (int) $traffic['fpm_max_children_reached'] ?>×</span>
            <?php endif; ?>
          </p>

          <h6 class="mt-3">Who is visiting (by user agent)</h6>
          <table class="table table-sm sysmon-num">
            <thead><tr><th>Kind</th><th class="text-end">Requests</th><th class="text-end">Share</th><th class="w-50"></th></tr></thead>
            <tbody>
            <?php foreach ($traffic['ua'] as $k => $n): ?>
              <tr><td><?= $h($ua_words[$k] ?? $k) ?></td><td class="text-end"><?= number_format($n) ?></td>
                  <td class="text-end"><?= $pct($n, $traffic['req']) ?></td>
                  <td><span class="sysmon-bar" style="width: <?= $traffic['req'] ? round(100 * $n / $traffic['req'], 1) : 0 ?>%"></span></td></tr>
            <?php endforeach; ?>
            <?php if (!$traffic['ua']): ?><tr><td colspan="4" class="text-muted">No traffic recorded yet.</td></tr><?php endif; ?>
            </tbody>
          </table>

          <h6 class="mt-3">What they asked for</h6>
          <table class="table table-sm sysmon-num">
            <thead><tr><th>Kind of request</th><th class="text-end">Requests</th><th class="text-end">Share</th></tr></thead>
            <tbody>
            <?php foreach ($traffic['cls'] as $k => $n): ?>
              <tr><td><?= $h($cls_words[$k] ?? $k) ?></td><td class="text-end"><?= number_format($n) ?></td><td class="text-end"><?= $pct($n, $traffic['req']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>

          <h6 class="mt-3">Responses</h6>
          <p class="small mb-0">
            <?php foreach ($traffic['status'] as $k => $n): ?>
              <span class="me-3"><strong><?= $h($k) ?></strong> <?= number_format($n) ?></span>
            <?php endforeach; ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Busiest addresses -->
    <div class="col-xl-6">
      <div class="card adm-card h-100">
        <div class="card-header adm-head"><h5 class="mb-0">Busiest addresses, <?= $span ?> <?= $sysmon_i('addresses') ?></h5></div>
        <div class="card-body">
          <p class="small text-muted">From the web server's log — every request, including static files.
            Only <strong>Internet</strong> addresses come from outside your network; the guide
            (How to read this page) explains each label.</p>
          <table class="table table-sm sysmon-num">
            <thead><tr><th>Address</th><th></th><th class="text-end">Requests</th></tr></thead>
            <tbody>
            <?php foreach ($traffic['ips'] as $ip => $n): ?>
              <tr><td><code><?= $h($ip) ?></code></td>
                  <?php $o = moop_monitor_ip_origin((string) $ip, $ranges, $self_ips); [$o_word, $o_why] = $origin_words[$o]; ?>
                  <td><span class="badge <?= $o === 'internet' ? 'bg-warning-subtle text-dark border border-warning' : 'bg-light text-dark border' ?>"
                            title="<?= $h($o_why) ?>"><?= $h($o_word) ?></span></td>
                  <td class="text-end"><?= number_format($n) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$traffic['ips']): ?><tr><td colspan="3" class="text-muted">No traffic recorded yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
          <?php if ($traffic['limited']): ?>
            <h6 class="mt-3">Rate-limit refusals by limit</h6>
            <p class="small mb-0"><?php foreach ($traffic['limited'] as $z => $n): ?>
              <span class="me-3"><code><?= $h($z) ?></code> <?= number_format($n) ?></span><?php endforeach; ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- What people use: organisms, assemblies, gene sets -->
  <?php
    $vis = $range === '30d' ? 'visitor-days' : 'visitors';
    $usage_table = function (array $rows, string $label) use ($h, $vis) {
        uasort($rows, fn($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));
        $rows = array_slice($rows, 0, 15, true);
        ob_start(); ?>
        <table class="table table-sm sysmon-num mb-0">
          <thead>
            <tr><th rowspan="2"><?= $h($label) ?></th><th colspan="2" class="text-center">Internal</th><th colspan="2" class="text-center">Public</th></tr>
            <tr><th class="text-end">Requests</th><th class="text-end"><?= ucfirst($vis) ?></th><th class="text-end">Requests</th><th class="text-end"><?= ucfirst($vis) ?></th></tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $name => $c): ?>
            <?php $parts = explode('/', $name); $parts[0] = str_replace('_', ' ', $parts[0]); /* organism only: accessions keep their underscores */ ?>
            <tr><td><?= $h(implode(' / ', $parts)) ?></td>
              <td class="text-end"><?= number_format($c['in']) ?></td><td class="text-end"><?= number_format($c['in_v']) ?></td>
              <td class="text-end"><?= number_format($c['out']) ?></td><td class="text-end"><?= number_format($c['out_v']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="5" class="text-muted">Nothing recorded yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
        <?php return ob_get_clean();
    };
  ?>
  <div class="card adm-card my-4" id="sysmon-usage">
    <div class="card-header adm-head"><h5 class="mb-0">What people use, <?= $span ?> <?= $sysmon_i('usage') ?></h5></div>
    <div class="card-body">
      <p class="small text-muted">Which organisms, assemblies and gene sets people open, search, BLAST and download — top 15 of
        each. <em>Internal</em> = your own people (institute network, VPN, and collaborators who log in);
        <em>public</em> = everyone else. <?= $range === '30d'
          ? 'Over 30 days, visitors are counted per day (someone who came on 3 days counts 3).'
          : 'Visitors are distinct people or addresses, so one busy visitor or crawler cannot make something look popular.' ?>
        Search words are never recorded — only which organisms a search covered.</p>
      <ul class="nav nav-tabs" role="tablist">
        <?php $tabs = ['view' => 'Viewed', 'search' => 'Searched', 'blast' => 'BLAST', 'download' => 'Downloaded']; $first = true; ?>
        <?php foreach ($tabs as $k => $label): ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link<?= $first ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#sysmon-usage-<?= $k ?>" type="button" role="tab"><?= $label ?></button>
          </li>
        <?php $first = false; endforeach; ?>
      </ul>
      <div class="tab-content pt-3">
        <?php $first = true; foreach ($tabs as $k => $label): ?>
          <div class="tab-pane fade<?= $first ? ' show active' : '' ?>" id="sysmon-usage-<?= $k ?>" role="tabpanel">
            <div class="row g-4">
              <div class="col-12 sysmon-usage-table"><div class="table-responsive"><?= $usage_table($usage[$k]['organism'] ?? [], 'Organism') ?></div></div>
              <?php if ($k !== 'search'): ?>
              <div class="col-12 sysmon-usage-table"><div class="table-responsive"><?= $usage_table($usage[$k]['assembly'] ?? [], 'Assembly') ?></div></div>
              <div class="col-12 sysmon-usage-table"><div class="table-responsive"><?= $usage_table($usage[$k]['gene_set'] ?? [], 'Gene set') ?></div></div>
              <?php endif; ?>
            </div>
          </div>
        <?php $first = false; endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Heaviest pages -->
  <div class="card adm-card my-4">
    <div class="card-header adm-head"><h5 class="mb-0">Heaviest pages, <?= $span ?> <?= $sysmon_i('pages') ?></h5></div>
    <div class="card-body">
      <p class="small text-muted">From MOOP's own request log (PHP only): what each page costs. Sorted by total
        time — a cheap page called constantly can cost more than a slow one called rarely. Peak memory is
        what one request held at most; it is what runs the server out of RAM.
        <?= number_format($pages['requests']) ?> requests.</p>
      <div class="table-responsive">
      <table class="table table-sm table-hover sysmon-num sysmon-dt" id="sysmon-pages">
        <thead><tr><th>Page</th><th>Limit</th><th class="text-end">Requests</th><th class="text-end">Total time</th><th class="text-end">Typical</th>
          <th class="text-end">Slowest 5%</th><th class="text-end">Slowest</th><th class="text-end">Memory, typical</th>
          <th class="text-end">Memory, peak</th><th class="text-end">Errors</th>
          <th class="text-end" title="Share of this page's time spent in SQLite (running queries and reading rows)">In database</th>
          <th class="text-end" title="Share of requests that had to read from disk rather than RAM">Cold</th></tr></thead>
        <tbody>
        <?php foreach ($pages['by_script'] as $script => $s): ?>
          <tr><td><code><?= $h($script) ?></code></td>
            <td><small><?= $h(moop_monitor_class($script, $heavy)) ?></small></td>
            <td class="text-end"><?= number_format($s['count']) ?></td>
            <td class="text-end" data-order="<?= (int) $s['total_ms'] ?>"><?= moop_monitor_fmt_ms($s['total_ms']) ?></td>
            <td class="text-end" data-order="<?= (int) $s['mean_ms'] ?>"><?= moop_monitor_fmt_ms($s['mean_ms']) ?></td>
            <td class="text-end" data-order="<?= (int) $s['p95_ms'] ?>"><?= moop_monitor_fmt_ms($s['p95_ms']) ?></td>
            <td class="text-end" data-order="<?= (int) $s['max_ms'] ?>"><?= moop_monitor_fmt_ms($s['max_ms']) ?></td>
            <td class="text-end"><?= $h($s['mean_mb']) ?> MB</td>
            <td class="text-end"><?= $h($s['max_mb']) ?> MB</td>
            <td class="text-end"><?= $s['errors'] ? '<span class="sysmon-status sysmon-critical"><i class="fa fa-times-circle" aria-hidden="true"></i> ' . (int) $s['errors'] . '</span>' : '0' ?></td>
            <td class="text-end"><?= !empty($s['db_n']) ? round(100 * ($s['db_ms'] ?? 0) / max(1, $s['total_ms'])) . '%' : '—' ?></td>
            <td class="text-end"><?= !empty($s['io_n']) ? round(100 * ($s['cold'] ?? 0) / $s['io_n']) . '%' : '—' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <!-- Databases: warm or cold -->
  <?php $io = $pages['io'] ?? ['measured' => 0, 'cold' => 0, 'disk_kb' => 0]; ?>
  <div class="card adm-card mb-4" id="sysmon-warmcold">
    <div class="card-header adm-head"><h5 class="mb-0">Databases: warm or cold, <?= $span ?> <?= $sysmon_i('warmcold') ?></h5></div>
    <div class="card-body">
      <p class="small text-muted">A request is <em>warm</em> when everything it needed was already in RAM, and <em>cold</em> when it
        had to read from disk (more than <?= MOOP_METRICS_COLD_KB ?> KB). Cold requests are the slow ones on this site. The
        organisms below are where the disk reads go: together they are the part of the
        <?= isset($now['db_total_mb']) ? round($now['db_total_mb'] / 1024, 1) . ' GB of databases' : 'databases' ?> that people
        actually use — the <em>working set</em> — and that is what the RAM cache
        (<?= isset($now['cache_mb']) ? round($now['cache_mb'] / 1024, 1) . ' GB now' : '—' ?>) has to hold for the site to stay warm.</p>
      <?php if ($io['measured'] > 0): ?>
        <?php $cold_pct = round(100 * $io['cold'] / $io['measured']); ?>
        <p class="mb-2">Of <?= number_format($io['measured']) ?> requests: <strong><?= 100 - $cold_pct ?>% warm</strong>,
          <strong><?= $cold_pct ?>% cold</strong> · <?= moop_monitor_fmt_bytes($io['disk_kb'] * 1024) ?> read from disk in total.</p>
        <div class="table-responsive sysmon-usage-table">
        <table class="table table-sm sysmon-num mb-0">
          <thead><tr><th>Organism</th><th class="text-end">Requests</th><th class="text-end">Warm (from RAM)</th><th class="text-end">Cold (from disk)</th><th class="text-end">Read from disk</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($pages['by_org_disk'] ?? [], 0, 20, true) as $org => $o): ?>
            <tr><td><?= $h(str_replace('_', ' ', $org)) ?></td><td class="text-end"><?= number_format($o['requests']) ?></td>
              <?php $cp = round(100 * $o['cold'] / max(1, $o['requests'])); ?>
              <td class="text-end"><?= 100 - $cp ?>%</td><td class="text-end"><?= $cp ?>%</td>
              <td class="text-end"><?= moop_monitor_fmt_bytes($o['disk_kb'] * 1024) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php else: ?>
        <p class="small text-muted mb-0">No measurements yet in this window (recorded from 2026-10-28).</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <!-- Who -->
    <div class="col-12">
      <div class="card adm-card h-100">
        <div class="card-header adm-head"><h5 class="mb-0">Who, <?= $span ?> <?= $sysmon_i('who') ?></h5></div>
        <div class="card-body">
          <p class="small text-muted">Logged-in users by name; everyone else by address. PHP requests only. Kept 30 days.</p>
          <div class="table-responsive">
          <table class="table table-sm table-hover sysmon-num sysmon-dt" id="sysmon-who">
            <thead><tr><th>User / address</th><th>Access</th><th class="text-end">Requests</th><th class="text-end">Total time</th><th class="text-end">Memory, peak</th></tr></thead>
            <tbody>
            <?php foreach ($pages['by_who'] as $who => $w): ?>
              <tr><td><?= $h($who) ?></td><td><small><?= $h($w['level']) ?></small></td>
                <td class="text-end"><?= number_format($w['count']) ?></td>
                <td class="text-end" data-order="<?= (int) $w['total_ms'] ?>"><?= moop_monitor_fmt_ms($w['total_ms']) ?></td>
                <td class="text-end"><?= $h($w['max_mb']) ?> MB</td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Slowest requests -->
    <div class="col-12">
      <div class="card adm-card h-100">
        <div class="card-header adm-head"><h5 class="mb-0">Slowest requests, <?= $span ?> <?= $sysmon_i('slowest') ?></h5></div>
        <div class="card-body">
          <p class="small text-muted">Requests over <?= MOOP_METRICS_SLOW_MS / 1000 ?> s or that failed keep their settings, including
            search words (not BLAST sequences or pasted gene lists), so you can see what went on: page loads get an <em>open</em>
            link to load them again exactly as they ran; background requests and forms show their settings instead. None yet if
            everything has been fast.</p>
          <div class="table-responsive">
          <table class="table table-sm sysmon-num">
            <thead><tr><th>When</th><th>Page</th><th class="text-end">Time</th><th>Why slow</th><th class="text-end">In database</th><th class="text-end">From disk</th>
              <th class="text-end">Memory</th><th>Who</th><th class="text-end">Status</th></tr></thead>
            <tbody>
            <?php foreach ($pages['slowest'] as $r): ?>
              <tr><td><small><?= date($range === '24h' ? 'H:i:s' : 'D j M H:i', (int) $r['ts']) ?></small></td>
                <td><code><?= $h($r['script']) ?></code>
                  <?php
                    // A link only for real pages; a background request (a search's per-organism call,
                    // AJAX) would open raw data, so its settings are shown as text instead.
                    $is_page = in_array(moop_monitor_class($r['script'], $heavy), ['page', 'heavy'], true) && strpos($r['script'], 'api/') !== 0;
                  ?>
                  <?php if (($r['params'] ?? '') !== '' && $r['method'] === 'GET' && $is_page): ?>
                    <a class="small ms-1" href="/<?= $h($site) ?>/<?= $h($r['script']) ?>?<?= $h($r['params']) ?>" target="_blank" rel="noopener"
                       title="Open this request again, with the same parameters">open <i class="fa fa-external-link-alt" aria-hidden="true"></i></a>
                  <?php elseif (($r['params'] ?? '') !== ''): ?>
                    <?php parse_str($r['params'], $pv); ?>
                    <div class="small text-muted text-break"><?php foreach ($pv as $pk => $pval): ?><span class="me-2"><?= $h($pk) ?>:
                      <strong><?= $h(is_array($pval) ? implode(', ', $pval) : $pval) ?></strong></span><?php endforeach; ?></div>
                  <?php endif; ?></td>
                <td class="text-end"><?= moop_monitor_fmt_ms($r['ms']) ?></td>
                <td><small><?= $h(moop_monitor_why_slow($r) ?? 'not measured') ?></small></td>
                <td class="text-end"><?= !empty($r['db_n']) ? moop_monitor_fmt_ms($r['db_ms']) : '—' ?></td>
                <td class="text-end"><?= ($r['disk_kb'] ?? null) === null ? '—' : moop_monitor_fmt_bytes($r['disk_kb'] * 1024) ?></td>
                <td class="text-end"><?= $h($r['peak_mb']) ?> MB</td>
                <td><small><?= $h($r['user'] !== '' ? $r['user'] : $r['ip']) ?></small></td>
                <td class="text-end"><?= (int) $r['status'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$pages['slowest']): ?><tr><td colspan="9" class="text-muted">Nothing logged yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Capacity: when to add RAM or CPUs, and PHP workers -->
  <div class="card adm-card mb-4" id="sysmon-capacity">
    <div class="card-header adm-head"><h5 class="mb-0">Capacity — RAM, CPUs and PHP workers <?= $sysmon_i('capacity') ?></h5></div>
    <div class="card-body">
      <?php if ($cap['samples'] < 60 || ($pages['requests'] ?? 0) < 500): ?>
        <p class="small text-muted"><i class="fa fa-info-circle" aria-hidden="true"></i>
          These figures firm up after a few days of real use — so far <?= number_format($cap['samples']) ?> samples and
          <?= number_format($pages['requests']) ?> logged requests in this window.</p>
      <?php endif; ?>
      <div class="row g-4">
        <div class="col-lg-6">
          <table class="table table-sm sysmon-num mb-0">
            <tbody>
              <tr><th>CPU, average / peak</th><td class="text-end"><?= $h($cap['cpu_avg'] ?? '—') ?>% / <?= $h($cap['cpu_peak'] ?? '—') ?>%</td></tr>
              <tr><th>Share of time CPU was above 80%</th><td class="text-end"><?= $h($cap['cpu_busy_share'] ?? '—') ?>%</td></tr>
              <tr><th>Memory, peak used</th><td class="text-end"><?= $h($cap['mem_peak'] ?? '—') ?>% of <?= round($cap['total_mb'] / 1024, 1) ?> GB</td></tr>
              <tr><th>Swap, peak</th><td class="text-end"><?= (int) $cap['swap_peak_mb'] ?> MB</td></tr>
              <tr><th>Waiting on disk (iowait), average / peak</th><td class="text-end"><?= $h($cap['iowait_avg'] ?? '—') ?>% / <?= $h($cap['iowait_peak'] ?? '—') ?>%</td></tr>
              <tr><th>Databases / RAM caching files</th><td class="text-end"><?= isset($now['db_total_mb']) ? round($now['db_total_mb'] / 1024, 1) . ' GB / ' . round($now['cache_mb'] / 1024, 1) . ' GB' : '—' ?></td></tr>
              <tr><th>Requests that went cold</th><td class="text-end"><?= ($pages['io']['measured'] ?? 0) ? round(100 * $pages['io']['cold'] / $pages['io']['measured']) . '%' : '—' ?></td></tr>
              <tr><th>Times every PHP worker was busy</th><td class="text-end"><?= (int) $cap['workers_reached'] ?></td></tr>
              <tr><th>Memory per busy worker (typical-heavy request + worker)</th><td class="text-end"><?= $h($cap['per_worker_mb']) ?> MB</td></tr>
              <tr><th>PHP workers allowed now (pm.max_children)</th><td class="text-end"><?= $max_children ?? '—' ?></td></tr>
              <tr><th>…if all were busy at once, PHP would need</th><td class="text-end"><?= $cap['php_worst_mb'] !== null ? round($cap['php_worst_mb'] / 1024, 1) . ' GB' : '—' ?></td></tr>
              <?php foreach (moop_monitor_disks($now['disks'] ?? []) as $d): ?>
              <tr><th>Disk: <?= $h($d['label']) ?> <span class="fw-normal small text-muted d-block"><?= $h($d['role']) ?></span></th>
                <td class="text-end"><?= $h($d['used_pct']) ?>% · <?= $h($d['free_gb']) ?> GB free of <?= $h($d['size_gb']) ?></td></tr>
              <?php endforeach; ?>
              <tr><th>Workers that fit in 60% of RAM</th><td class="text-end"><?= $cap['suggested_max_children'] ?? '—' ?></td></tr>
              <tr><th>Largest single request seen</th><td class="text-end"><?= $h($cap['largest_mb']) ?> MB</td></tr>
              <tr><td colspan="2" class="small text-muted">The estimate uses a typical-heavy request (95th percentile), not the
                largest. Many large exports at once would need more — the heavy group's limit of 3 at once per address is what
                keeps that in check.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="col-lg-6 small">
          <h6>When is it time to add…</h6>
          <ul>
            <li><strong>RAM</strong> — many requests going cold with high disk wait (the databases no longer fit in the cache), memory
              regularly above ~85–90% used, <em>any</em> swapping, or “every PHP worker busy”
              while RAM has no room for more workers. RAM runs out first on this server (capacity test, 2026-10-23), and it
              also holds the page cache that keeps the databases fast, so it is the usual first upgrade.</li>
            <li><strong>CPUs</strong> — CPU above 80% for long stretches (not short spikes), while memory is fine. Check the
              busiest addresses first: a crawler can look exactly like “we need more CPUs”.</li>
            <li><strong>PHP workers</strong> — “every PHP worker busy” keeps appearing <em>and</em> the row above says more
              workers fit in RAM.</li>
          </ul>
          <h6>How to change the number of PHP workers</h6>
          <ol>
            <li>Edit <code>/etc/php-fpm.d/www.conf</code> (as root) and set <code>pm.max_children</code>. Keep it at or below
              “workers that fit in 60% of RAM”.</li>
            <li>Check and apply: <code>sudo php-fpm -t &amp;&amp; sudo systemctl reload php-fpm</code>
              (a reload finishes the requests in progress; nobody is cut off).</li>
            <li>Come back here after a few days: “every PHP worker busy” should fall to 0 without memory climbing.</li>
          </ol>
          <p class="mb-0 text-muted">More workers only help if the server has the memory for them; past that point they make
            things worse, because the server starts swapping.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Heavy group -->
  <div class="card adm-card mb-4" id="sysmon-heavy">
    <div class="card-header adm-head"><h5 class="mb-0">Heavy group (strictest rate limit) <?= $sysmon_i('heavy') ?></h5></div>
    <div class="card-body">
      <p class="small text-muted">Exports, downloads and other expensive scripts: one address may run these at most 20 times a
        minute (burst 10, 3 at once). Changes reach nginx within a minute; nothing to re-run.
        Recommendations above suggest additions from what pages measurably cost.</p>
      <div class="sysmon-heavy-list">
        <?php foreach ($heavy as $s): ?>
          <form method="post" action="system_monitor.php<?= $range !== '24h' ? '?range=' . $h($range) : '' ?>" class="sysmon-heavy-item">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="heavy_remove">
            <input type="hidden" name="script" value="<?= $h($s) ?>">
            <code><?= $h($s) ?></code>
            <button type="submit" class="btn btn-link btn-sm p-0 ms-1 text-muted" title="Remove from the heavy group"
                    aria-label="Remove <?= $h($s) ?> from the heavy group"><i class="fa fa-times" aria-hidden="true"></i></button>
          </form>
        <?php endforeach; ?>
        <?php if (!$heavy): ?><span class="small text-muted">Empty — every script gets the ordinary page or AJAX limit.</span><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- nginx rate-limit sync -->
  <?php if ($sync): ?>
  <div class="card adm-card mb-5">
    <div class="card-header <?= ($sync['state'] ?? '') === 'error' ? 'adm-head-danger' : 'adm-head' ?>"><h5 class="mb-0">Rate-limit settings <?= $sysmon_i('sync') ?></h5></div>
    <div class="card-body small">
      <?php $st = $sync['state'] ?? 'unknown'; $lv = $st === 'in_sync' ? 'good' : ($st === 'error' ? 'critical' : 'warning'); ?>
      <span class="sysmon-status sysmon-<?= $lv ?>"><i class="fa fa-<?= $level_icon[$lv] ?>" aria-hidden="true"></i>
        <?= $st === 'in_sync' ? 'In step with MOOP' : ($st === 'error' ? 'Not applied' : 'Waiting') ?></span>
      — <?= $h($sync['message'] ?? '') ?>.
      <?php if (!empty($sync['ranges'])): ?><br>Never limited (auto-login ranges): <?= $h($sync['ranges']) ?><?php endif; ?>
      <?php if (!empty($sync['changed'])): ?><br>Last changed <?= date('Y-m-d H:i', (int) $sync['changed']) ?>.<?php endif; ?>
      <br>Change the ranges in <a href="manage_site_config.php">Site Configuration</a>; nginx follows within a minute.
    </div>
  </div>
  <?php endif; ?>
</div>

<?= $sysmon_guide ?>
