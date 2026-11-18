/**
 * Gene-page spatial atlas card(s) (tools/pages/parent.php): mount the shared viewer
 * (js/modules/spatial-atlas.js) the first time a card opens -- so plotly and the atlas data are only
 * downloaded by visitors who look. A card opened by a #pnav-atlas-<name> link mounts the same way.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    if (!window.MoopSpatialAtlas) return;
    document.querySelectorAll('.sa-card-body[data-atlas]').forEach(function (body) {
        var mounted = false;
        function go() {
            if (mounted) return;
            mounted = true;
            var opts;
            try { opts = JSON.parse(body.getAttribute('data-atlas')); } catch (e) { return; }
            MoopSpatialAtlas.mount(body.querySelector('.sa-mount'), opts);
        }
        body.addEventListener('shown.bs.collapse', function (e) { if (e.target === body) go(); });
        if (body.classList.contains('show')) go();
    });
});
