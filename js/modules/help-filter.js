/**
 * Type-to-filter for help pages — one implementation instead of one per page.
 *
 * Markup:
 *   <input type="search" data-help-filter=".route-card"
 *          data-filter-msg="route-filter-msg" data-filter-noun="page" data-filter-nouns="pages">
 *   <div class="route-card" data-search="lower-cased text to match">…</div>
 *   <section data-filter-group>…cards…</section>   (optional: hidden when none of its cards match)
 *
 * Matches the server-built data-search attribute, never rendered text, so a query can
 * not match icon markup or a badge.
 */
(function () {
  function bind(input) {
    var items  = Array.prototype.slice.call(document.querySelectorAll(input.dataset.helpFilter));
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-filter-group]'));
    var msg    = input.dataset.filterMsg ? document.getElementById(input.dataset.filterMsg) : null;
    var noun   = input.dataset.filterNoun || 'item';
    var nouns  = input.dataset.filterNouns || noun + 's';

    function apply() {
      var q = input.value.trim().toLowerCase(), shown = 0;
      items.forEach(function (el) {
        var hit = !q || (el.dataset.search || '').indexOf(q) !== -1;
        el.style.display = hit ? '' : 'none';
        if (hit) shown++;
      });
      groups.forEach(function (g) {
        var any = items.some(function (el) { return g.contains(el) && el.style.display !== 'none'; });
        g.style.display = any ? '' : 'none';
      });
      if (msg) {
        msg.style.display = q ? '' : 'none';
        msg.textContent = shown === 0
          ? 'Nothing matches “' + input.value.trim() + '”'
          : shown + ' ' + (shown === 1 ? noun : nouns) + ' match';
      }
    }
    input.addEventListener('input', apply);
    input.addEventListener('search', apply);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[data-help-filter]').forEach(bind);
  });
})();
