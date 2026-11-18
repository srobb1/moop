/**
 * Spatial atlas tool page (tools/spatial_atlas.php): the gene picker around the shared viewer
 * (js/modules/spatial-atlas.js). The chosen gene goes into the address (?gene=), so a view can be
 * linked to and the back button works.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    if (typeof moopAtlas === 'undefined' || !window.MoopSpatialAtlas) return;
    var input = document.getElementById('saPick'), list = document.getElementById('saPickList');
    var viewer = MoopSpatialAtlas.mount(document.getElementById('saViewer'), Object.assign({}, moopAtlas, {
        onShow: function (g) {
            if (g.status !== 'not_in_study') input.value = g.name ? g.name + ' | ' + g.gene_id : g.gene_id;
        }
    }));

    function choose(id, push) {
        list.hidden = true;
        viewer.show(id);
        var u = new URL(location.href);
        u.searchParams.set('gene', id);
        if (push) history.pushState({ gene: id }, '', u); else history.replaceState({ gene: id }, '', u);
    }
    window.addEventListener('popstate', function (e) {
        var id = (e.state && e.state.gene) || new URL(location.href).searchParams.get('gene') || moopAtlas.gene;
        if (id) viewer.show(id);
    });
    // "Show" in the similar-genes table: the viewer switches itself; keep the address in step.
    document.getElementById('saViewer').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-gene]');
        if (b) { var u = new URL(location.href); u.searchParams.set('gene', b.getAttribute('data-gene')); history.pushState({ gene: b.getAttribute('data-gene') }, '', u); }
    });

    var timer = null, seq = 0;
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { list.hidden = true; return; }
        timer = setTimeout(function () {
            var mine = ++seq;
            var p = new URLSearchParams({ organism: moopAtlas.organism, assembly: moopAtlas.assembly, gene_set: moopAtlas.gene_set,
                                          atlas: moopAtlas.atlas, what: 'search', q: q });
            fetch(moopAtlas.api + '?' + p).then(function (r) { return r.json(); }).then(function (d) {
                if (mine !== seq) return;
                var genes = d.genes || [];
                list.innerHTML = genes.length
                    ? genes.map(function (g) {
                        return '<button type="button" class="list-group-item list-group-item-action" role="option" data-id="' + esc(g.gene_id) + '">' +
                               (g.name ? '<strong>' + esc(g.name) + '</strong> | ' : '') + esc(g.gene_id) + '</button>';
                      }).join('')
                    : '<div class="list-group-item text-muted">' + moopTh('No matches found') + '</div>';
                list.hidden = false;
            }).catch(function () { list.hidden = true; });
        }, 200);
    });
    list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-id]');
        if (b) choose(b.getAttribute('data-id'), true);
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var first = list.hidden ? null : list.querySelector('[data-id]');
            if (first) choose(first.getAttribute('data-id'), true);
        } else if (e.key === 'Escape') {
            list.hidden = true;
        } else if (e.key === 'ArrowDown' && !list.hidden) {
            var f = list.querySelector('[data-id]');
            if (f) { e.preventDefault(); f.focus(); }
        }
    });
    list.addEventListener('keydown', function (e) {
        var cur = document.activeElement;
        if (e.key === 'ArrowDown' && cur.nextElementSibling) { e.preventDefault(); cur.nextElementSibling.focus(); }
        if (e.key === 'ArrowUp') { e.preventDefault(); (cur.previousElementSibling || input).focus(); }
        if (e.key === 'Escape') { list.hidden = true; input.focus(); }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.sa-pick')) list.hidden = true; });
    input.addEventListener('focus', function () { input.select(); });
});
