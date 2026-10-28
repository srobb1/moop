<?php
/**
 * SYSTEM MONITOR - Content File. Controller: admin/system_monitor.php.
 *
 * Variables: $range ('24h'|'7d'), $age, $collector_ok, $sync, $tiles, $now, $traffic, $pages,
 *            $max_children, $ranges, $heavy, $cap, $recs, $flash
 * Charts are drawn by js/system-monitor.js from window.MOOP_MONITOR.series; every chart has the
 * same numbers in the data table below it, so nothing is hover-only.
 */
$h = fn($s) => htmlspecialchars((string) $s);
$level_icon = ['good' => 'check-circle', 'warning' => 'exclamation-triangle', 'critical' => 'times-circle', 'unknown' => 'question-circle'];
$level_word = ['good' => 'OK', 'warning' => 'Watch', 'critical' => 'High', 'unknown' => 'Unknown'];
$span = $range === '7d' ? 'last 7 days' : 'last 24 hours';
$pct = fn($n, $total) => $total > 0 ? round(100 * $n / $total) . '%' : '—';
$ua_words = ['browser' => 'Browsers', 'search' => 'Search engines', 'ai' => 'AI crawlers', 'script' => 'Scripts & automation',
             'other' => 'Other', 'none' => 'No user agent'];
$cls_words = ['page' => 'Pages', 'api' => 'AJAX / API', 'jbrowse' => 'Genome browser', 'heavy' => 'Exports, downloads, BLAST',
              'static' => 'Static files (CSS, JS, images)'];
?>

<div class="container-fluid px-4 mt-4 sysmon">

  <?= page_title('System Monitor', 'fa fa-chart-line') ?>
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
  </div>

  <!-- Right now -->
  <?php if ($tiles): ?>
  <div class="sysmon-tiles mb-4">
    <?php foreach ($tiles as $t): ?>
      <div class="sysmon-tile">
        <div class="sysmon-tile-label"><?= $h($t['label']) ?></div>
        <div class="sysmon-tile-value"><?= $h($t['value']) ?></div>
        <div class="sysmon-tile-sub"><?= $h($t['sub']) ?></div>
        <div class="sysmon-status sysmon-<?= $h($t['level']) ?>">
          <i class="fa fa-<?= $level_icon[$t['level']] ?>" aria-hidden="true"></i> <?= $level_word[$t['level']] ?>
        </div>
      </div>
    <?php endforeach; ?>
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
    <div class="card-header <?= $rec_head ?>"><h5 class="mb-0">Recommendations</h5></div>
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
                <form method="post" action="system_monitor.php<?= $range === '7d' ? '?range=7d' : '' ?>" class="mt-1">
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
    <div class="card-header adm-head"><h5 class="mb-0">Over the <?= $span ?></h5></div>
    <div class="card-body">
      <div class="sysmon-charts">
        <figure class="sysmon-chart" data-key="cpu" data-unit="%" data-max="100">
          <figcaption>CPU used</figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="mem" data-unit="%" data-max="100">
          <figcaption>Memory used</figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="req" data-unit=" requests">
          <figcaption>Requests per <?= $range === '7d' ? 'hour' : 'minute' ?></figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="fpm" data-unit=" busy"<?= $max_children ? ' data-max="' . (int) $max_children . '"' : '' ?>>
          <figcaption>PHP workers busy<?= $max_children ? ' (of ' . (int) $max_children . ')' : '' ?></figcaption><div class="sysmon-plot"></div>
        </figure>
        <figure class="sysmon-chart" data-key="s429" data-unit=" refused">
          <figcaption>Requests refused by rate limits (429) per <?= $range === '7d' ? 'hour' : 'minute' ?></figcaption><div class="sysmon-plot"></div>
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
        <div class="card-header adm-head"><h5 class="mb-0">Traffic, <?= $span ?></h5></div>
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
        <div class="card-header adm-head"><h5 class="mb-0">Busiest addresses, <?= $span ?></h5></div>
        <div class="card-body">
          <p class="small text-muted">From the web server's log — every request, including static files.
            <em>Internal</em> = inside the auto-login ranges, never rate-limited.</p>
          <table class="table table-sm sysmon-num">
            <thead><tr><th>Address</th><th></th><th class="text-end">Requests</th></tr></thead>
            <tbody>
            <?php foreach ($traffic['ips'] as $ip => $n): ?>
              <tr><td><code><?= $h($ip) ?></code></td>
                  <td><?= moop_monitor_ip_internal((string) $ip, $ranges) ? '<span class="badge bg-light text-dark border">Internal</span>' : '' ?></td>
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

  <!-- Heaviest pages -->
  <div class="card adm-card my-4">
    <div class="card-header adm-head"><h5 class="mb-0">Heaviest pages, <?= $span ?></h5></div>
    <div class="card-body">
      <p class="small text-muted">From MOOP's own request log (PHP only): what each page costs. Sorted by total
        time — a cheap page called constantly can cost more than a slow one called rarely. Peak memory is
        what one request held at most; it is what runs the server out of RAM.
        <?= number_format($pages['requests']) ?> requests.</p>
      <div class="table-responsive">
      <table class="table table-sm table-hover sysmon-num sysmon-dt" id="sysmon-pages">
        <thead><tr><th>Page</th><th>Limit</th><th class="text-end">Requests</th><th class="text-end">Total time</th><th class="text-end">Typical</th>
          <th class="text-end">Slowest 5%</th><th class="text-end">Slowest</th><th class="text-end">Memory, typical</th>
          <th class="text-end">Memory, peak</th><th class="text-end">Errors</th></tr></thead>
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
            <td class="text-end"><?= $s['errors'] ? '<span class="sysmon-status sysmon-critical"><i class="fa fa-times-circle" aria-hidden="true"></i> ' . (int) $s['errors'] . '</span>' : '0' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <!-- Who -->
    <div class="col-12">
      <div class="card adm-card h-100">
        <div class="card-header adm-head"><h5 class="mb-0">Who, <?= $span ?></h5></div>
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
        <div class="card-header adm-head"><h5 class="mb-0">Slowest requests, <?= $span ?></h5></div>
        <div class="card-body">
          <div class="table-responsive">
          <table class="table table-sm sysmon-num">
            <thead><tr><th>When</th><th>Page</th><th class="text-end">Time</th><th class="text-end">Memory</th><th>Who</th><th class="text-end">Status</th></tr></thead>
            <tbody>
            <?php foreach ($pages['slowest'] as $r): ?>
              <tr><td><small><?= date($range === '7d' ? 'D H:i' : 'H:i:s', (int) $r['ts']) ?></small></td>
                <td><code><?= $h($r['script']) ?></code></td>
                <td class="text-end"><?= moop_monitor_fmt_ms($r['ms']) ?></td>
                <td class="text-end"><?= $h($r['peak_mb']) ?> MB</td>
                <td><small><?= $h($r['user'] !== '' ? $r['user'] : $r['ip']) ?></small></td>
                <td class="text-end"><?= (int) $r['status'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$pages['slowest']): ?><tr><td colspan="6" class="text-muted">Nothing logged yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Capacity: when to add RAM or CPUs, and PHP workers -->
  <div class="card adm-card mb-4" id="sysmon-capacity">
    <div class="card-header adm-head"><h5 class="mb-0">Capacity — RAM, CPUs and PHP workers</h5></div>
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
              <tr><th>Times every PHP worker was busy</th><td class="text-end"><?= (int) $cap['workers_reached'] ?></td></tr>
              <tr><th>Memory per busy worker (typical-heavy request + worker)</th><td class="text-end"><?= $h($cap['per_worker_mb']) ?> MB</td></tr>
              <tr><th>PHP workers allowed now (pm.max_children)</th><td class="text-end"><?= $max_children ?? '—' ?></td></tr>
              <tr><th>…if all were busy at once, PHP would need</th><td class="text-end"><?= $cap['php_worst_mb'] !== null ? round($cap['php_worst_mb'] / 1024, 1) . ' GB' : '—' ?></td></tr>
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
            <li><strong>RAM</strong> — memory regularly above ~85–90% used, <em>any</em> swapping, or “every PHP worker busy”
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
    <div class="card-header adm-head"><h5 class="mb-0">Heavy group (strictest rate limit)</h5></div>
    <div class="card-body">
      <p class="small text-muted">Exports, downloads and other expensive scripts: one address may run these at most 20 times a
        minute (burst 10, 3 at once). Changes reach nginx within a minute; nothing to re-run.
        Recommendations above suggest additions from what pages measurably cost.</p>
      <div class="sysmon-heavy-list">
        <?php foreach ($heavy as $s): ?>
          <form method="post" action="system_monitor.php<?= $range === '7d' ? '?range=7d' : '' ?>" class="sysmon-heavy-item">
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
    <div class="card-header <?= ($sync['state'] ?? '') === 'error' ? 'adm-head-danger' : 'adm-head' ?>"><h5 class="mb-0">Rate-limit settings</h5></div>
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
