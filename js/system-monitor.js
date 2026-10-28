/**
 * System Monitor charts (admin/system_monitor.php). Plain inline SVG — no chart library, nothing
 * from another host (the CSP is 'self'). Data: window.MOOP_MONITOR = {range, series:[{t, cpu,
 * mem, req, fpm, s429}]}, written by the controller.
 *
 * One measure per chart (small multiples, never two y-axes); a single series needs no legend —
 * the caption names it. Hover or arrow keys move a crosshair that snaps to the nearest sample;
 * the tooltip shows the value first, then the time. Every number is also in the data table under
 * the charts, so nothing is hover-only. Labels are inserted with textContent.
 *
 * A gap in the samples (the collector or the host was down) breaks the line rather than drawing
 * a straight line across it, which would show activity that never happened.
 */
(function () {
  var data = window.MOOP_MONITOR || {series: []};
  var series = data.series || [];
  var is7d = data.range === '7d';
  var step = is7d ? 3600 : 60;                     // seconds between samples
  var SVG = 'http://www.w3.org/2000/svg';
  var M = {top: 8, right: 12, bottom: 22, left: 44};

  function el(name, attrs, parent) {
    var n = document.createElementNS(SVG, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function fmtTime(t) {
    var d = new Date(t * 1000);
    var hm = pad(d.getHours()) + ':' + pad(d.getMinutes());
    return is7d ? d.toLocaleDateString(undefined, {weekday: 'short', month: 'short', day: 'numeric'}) + ' ' + hm : hm;
  }
  function fmtNum(v) {
    if (v === null || v === undefined) return '—';
    if (Math.abs(v) >= 10000) return (v / 1000).toFixed(0) + 'K';
    if (Math.abs(v) >= 1000) return (v / 1000).toFixed(1) + 'K';
    return (Math.round(v * 10) / 10).toString();
  }
  // A clean axis top: 1, 2, 5 × 10^n at or above the data's maximum.
  function niceMax(v) {
    if (!(v > 0)) return 1;
    var p = Math.pow(10, Math.floor(Math.log10(v)));
    var steps = [1, 2, 2.5, 5, 10];
    for (var i = 0; i < steps.length; i++) if (steps[i] * p >= v) return steps[i] * p;
    return 10 * p;
  }

  function draw(fig) {
    var key = fig.dataset.key;
    var unit = fig.dataset.unit || '';
    var plot = fig.querySelector('.sysmon-plot');
    plot.textContent = '';
    var W = plot.clientWidth, H = plot.clientHeight;
    var svg = el('svg', {viewBox: '0 0 ' + W + ' ' + H, role: 'img'}, plot);
    var pts = series.filter(function (p) { return p[key] !== null && p[key] !== undefined; });
    if (pts.length < 2) {
      el('text', {x: W / 2, y: H / 2, 'text-anchor': 'middle', class: 'empty'}, svg).textContent = is7d
        ? 'Hourly figures appear once the first full hour is recorded.'
        : 'Not enough samples yet — the collector records one a minute.';
      return;
    }
    var t0 = series[0].t, t1 = series[series.length - 1].t;
    var dataMax = Math.max.apply(null, pts.map(function (p) { return p[key]; }));
    var yMax = fig.dataset.max ? Math.max(+fig.dataset.max, dataMax) : niceMax(dataMax);
    var iw = W - M.left - M.right, ih = H - M.top - M.bottom;
    var x = function (t) { return M.left + (t1 === t0 ? 0 : (t - t0) / (t1 - t0) * iw); };
    var y = function (v) { return M.top + ih - v / yMax * ih; };

    // Recessive grid + y ticks (0, half, top)
    [0, yMax / 2, yMax].forEach(function (v) {
      var yy = Math.round(y(v)) + 0.5;
      el('line', {x1: M.left, x2: W - M.right, y1: yy, y2: yy, class: 'grid'}, svg);
      el('text', {x: M.left - 6, y: yy + 4, 'text-anchor': 'end', class: 'tick'}, svg).textContent = fmtNum(v);
    });
    // x ticks: every 4 h (24 h view) or every midnight (7 d view)
    var tickStep = is7d ? 86400 : 4 * 3600;
    var first = Math.ceil(t0 / tickStep) * tickStep;
    if (is7d) { var d0 = new Date(t0 * 1000); d0.setHours(24, 0, 0, 0); first = d0.getTime() / 1000; }
    // The start and end are always labelled (a young or short window may hold no regular tick);
    // a regular tick too close to either end is skipped so labels never collide.
    var shortTime = function (t) { var d = new Date(t * 1000); return is7d ? d.toLocaleDateString(undefined, {weekday: 'short'}) + ' ' + pad(d.getHours()) + 'h' : pad(d.getHours()) + ':' + pad(d.getMinutes()); };
    el('text', {x: M.left, y: H - 6, 'text-anchor': 'start', class: 'tick'}, svg).textContent = shortTime(t0);
    el('text', {x: W - M.right, y: H - 6, 'text-anchor': 'end', class: 'tick'}, svg).textContent = shortTime(t1);
    for (var tt = first; tt <= t1; tt += tickStep) {
      if (x(tt) - M.left < 60 || W - M.right - x(tt) < 60) continue;
      el('text', {x: x(tt), y: H - 6, 'text-anchor': 'middle', class: 'tick'}, svg).textContent =
        is7d ? new Date(tt * 1000).toLocaleDateString(undefined, {weekday: 'short'}) : pad(new Date(tt * 1000).getHours()) + ':00';
    }

    // Line + area, broken wherever samples are missing
    var runs = [], run = [];
    series.forEach(function (p, i) {
      var v = p[key];
      var gap = i > 0 && p.t - series[i - 1].t > 2.5 * step;
      if (v === null || v === undefined || gap) { if (run.length) runs.push(run); run = []; }
      if (v !== null && v !== undefined) run.push(p);
    });
    if (run.length) runs.push(run);
    runs.forEach(function (r) {
      var d = r.map(function (p, i) { return (i ? 'L' : 'M') + x(p.t).toFixed(1) + ',' + y(p[key]).toFixed(1); }).join('');
      if (r.length > 1) {
        el('path', {d: d + 'L' + x(r[r.length - 1].t).toFixed(1) + ',' + y(0) + 'L' + x(r[0].t).toFixed(1) + ',' + y(0) + 'Z', class: 'area'}, svg);
      }
      el('path', {d: d, class: 'line'}, svg);
      if (r.length === 1) el('circle', {cx: x(r[0].t), cy: y(r[0][key]), r: 3, class: 'dot'}, svg);
    });

    // Crosshair + tooltip (pointer and keyboard)
    var cross = el('line', {y1: M.top, y2: M.top + ih, class: 'cross', visibility: 'hidden'}, svg);
    var dot = el('circle', {r: 4.5, class: 'dot', visibility: 'hidden'}, svg);
    var tip = document.createElement('div');
    tip.className = 'sysmon-tip'; tip.hidden = true;
    var tipVal = document.createElement('strong'), tipTime = document.createElement('span');
    tip.appendChild(tipVal); tip.appendChild(tipTime);
    plot.appendChild(tip);
    var idx = pts.length - 1;

    function show(i) {
      idx = Math.max(0, Math.min(pts.length - 1, i));
      var p = pts[idx], px = x(p.t), py = y(p[key]);
      cross.setAttribute('x1', px); cross.setAttribute('x2', px); cross.setAttribute('visibility', 'visible');
      dot.setAttribute('cx', px); dot.setAttribute('cy', py); dot.setAttribute('visibility', 'visible');
      tipVal.textContent = fmtNum(p[key]) + unit;
      tipTime.textContent = fmtTime(p.t);
      tip.hidden = false;
      var left = px + 12;
      if (left + tip.offsetWidth > W) left = px - 12 - tip.offsetWidth;
      tip.style.left = left + 'px';
      tip.style.top = Math.max(0, py - tip.offsetHeight - 8) + 'px';
    }
    function hide() {
      cross.setAttribute('visibility', 'hidden'); dot.setAttribute('visibility', 'hidden'); tip.hidden = true;
    }
    function nearest(px) {
      var best = 0, bd = Infinity;
      for (var i = 0; i < pts.length; i++) {
        var dd = Math.abs(x(pts[i].t) - px);
        if (dd < bd) { bd = dd; best = i; }
      }
      return best;
    }
    var hit = el('rect', {x: M.left, y: M.top, width: iw, height: ih, fill: 'transparent'}, svg);
    hit.addEventListener('pointermove', function (e) {
      var r = svg.getBoundingClientRect();
      show(nearest((e.clientX - r.left) * W / r.width));
    });
    hit.addEventListener('pointerleave', hide);
    fig.tabIndex = 0;
    fig.onkeydown = function (e) {
      if (e.key === 'ArrowLeft') { show(idx - 1); e.preventDefault(); }
      else if (e.key === 'ArrowRight') { show(idx + 1); e.preventDefault(); }
      else if (e.key === 'Escape') hide();
    };
    fig.onfocus = function () { show(idx); };
    fig.onblur = hide;
  }

  function buildTable() {
    var table = document.getElementById('sysmon-series-table');
    if (!table) return;
    var cols = [['t', 'Time'], ['cpu', 'CPU %'], ['mem', 'Memory %'], ['req', 'Requests'], ['fpm', 'PHP workers busy'], ['s429', 'Refused (429)']];
    var thead = table.createTHead().insertRow();
    cols.forEach(function (c) { var th = document.createElement('th'); th.textContent = c[1]; thead.appendChild(th); });
    var tbody = table.createTBody();
    series.slice().reverse().forEach(function (p) {
      var tr = tbody.insertRow();
      cols.forEach(function (c) { tr.insertCell().textContent = c[0] === 't' ? fmtTime(p.t) : fmtNum(p[c[0]]); });
    });
  }

  function drawAll() { document.querySelectorAll('.sysmon-chart').forEach(draw); }

  document.addEventListener('DOMContentLoaded', function () {
    drawAll();
    buildTable();
    var timer;
    window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(drawAll, 150); });
    // Both tables open sorted by total time (pages: column 3, who: column 3).
    if (window.jQuery && jQuery.fn.DataTable) {
      jQuery('#sysmon-pages').DataTable({order: [[3, 'desc']], pageLength: 25, autoWidth: false});
      jQuery('#sysmon-who').DataTable({order: [[3, 'desc']], pageLength: 10, autoWidth: false});
    }
  });
})();
