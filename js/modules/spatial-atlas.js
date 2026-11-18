/**
 * Spatial atlas viewer -- one component for the atlas tool page (tools/spatial_atlas.php) and the
 * gene-page card (tools/pages/parent.php). Recreates the EndoAtlas authors' Dash app: a rotatable
 * 3-D model coloured by predicted expression, the gene's in situ picture, and the top genes with a
 * similar spatial pattern. Data from api/spatial_atlas.php (access-checked there).
 *
 * plotly.js (js/vendor/plotly-gl3d.min.js, scripts/vendor_assets.txt) is loaded only when a viewer
 * is first drawn, so a gene page whose card stays closed never downloads it.
 *
 * Usage:  MoopSpatialAtlas.mount(element, {
 *            api, plotlyUrl, geneUrl, toolUrl,                 // URLs (geneUrl/toolUrl: base, id appended)
 *            organism, assembly, gene_set, atlas, gene,        // what to show
 *            mode: 'tool' | 'card' })                          // card: "similar" rows link to the tool
 *         -> { show(geneId) }
 *
 * What the authors' app does, kept here (notes/SPATIAL_ATLAS_PLAN.md): values in mesh vertex order;
 * plotted x,y,z from OBJ axes per meta.axis_map; faces drawn i=third, j=first, k=second corner; viridis;
 * their lighting; black background; the segment grid as black dots; camera eye (0, 1, 1.2), aspect
 * 1:1:0.8; hover off. Differences, on purpose: the colour bar says Low/High (their tiny raw numbers
 * read as "20µ"), and the gene's name is above the plot, not under plotly's toolbar.
 */
(function () {
    'use strict';
    var T  = function (s) { return typeof moopT === 'function' ? moopT.apply(null, arguments) : s; };
    var Th = function (s) { return typeof moopTh === 'function' ? moopTh.apply(null, arguments) : esc(s); };
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var plotlyPromise = null;
    function loadPlotly(url) {
        if (window.Plotly) return Promise.resolve(window.Plotly);
        if (!plotlyPromise) {
            plotlyPromise = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = url;
                s.onload = function () { resolve(window.Plotly); };
                s.onerror = function () { plotlyPromise = null; reject(new Error('plotly')); };
                document.head.appendChild(s);
            });
        }
        return plotlyPromise;
    }

    function b64(str, Type) {
        var bin = atob(str), n = bin.length, buf = new ArrayBuffer(n), u8 = new Uint8Array(buf);
        for (var i = 0; i < n; i++) u8[i] = bin.charCodeAt(i);
        return new Type(buf);   // the server packs little-endian; every browser this runs on is
    }

    /** A gene's values back to numbers (lib/spatial_atlas.php moop_atlas_decode()). */
    function decode(g, n) {
        if (g.status !== 'expressed' || !g.vals) return new Float32Array(n);
        if (g.encoding === 'float32') return b64(g.vals, Float32Array);
        var codes = g.encoding === 'uint16' ? b64(g.vals, Uint16Array) : b64(g.vals, Uint8Array);
        var levels = g.encoding === 'uint16' ? 65535 : 255, span = g.vmax - g.vmin, out = new Float32Array(codes.length);
        for (var i = 0; i < codes.length; i++) out[i] = g.vmin + codes[i] / levels * span;
        return out;
    }

    function label(id, name) { return name ? name + ' | ' + id : id; }

    var cache = {};   // per atlas: Promise of {info, mesh}
    function atlasData(o) {
        var key = [o.organism, o.assembly, o.gene_set, o.atlas].join('/');
        if (!cache[key]) {
            var q = function (what) {
                return o.api + '?' + new URLSearchParams({ organism: o.organism, assembly: o.assembly, gene_set: o.gene_set, atlas: o.atlas, what: what });
            };
            cache[key] = Promise.all([fetch(q('info')), fetch(q('mesh'))]).then(function (r) {
                if (!r[0].ok || !r[1].ok) throw new Error('atlas');
                return Promise.all([r[0].json(), r[1].json()]);
            }).then(function (r) {
                var info = r[0], m = r[1], v = b64(m.vertices, Float32Array), f = b64(m.faces, Uint32Array);
                var ax = (info.axis_map || ['x', 'y', 'z']).map(function (a) { return { x: 0, y: 1, z: 2 }[a]; });
                var pick = function (arr, k) { var n = arr.length / 3, out = new Float32Array(n); for (var i = 0; i < n; i++) out[i] = arr[3 * i + k]; return out; };
                var mesh = { x: pick(v, ax[0]), y: pick(v, ax[1]), z: pick(v, ax[2]), n: v.length / 3,
                             i: pick(f, 2), j: pick(f, 0), k: pick(f, 1) };
                if (m.overlay) {
                    var ov = b64(m.overlay, Float32Array);
                    mesh.ox = pick(ov, ax[0]); mesh.oy = pick(ov, ax[1]); mesh.oz = pick(ov, ax[2]);
                }
                return { info: info, mesh: mesh, q: q };
            });
            cache[key].catch(function () { delete cache[key]; });
        }
        return cache[key];
    }

    function mount(root, o) {
        root.classList.add('sa-viewer');
        root.innerHTML =
            '<div class="row g-3 align-items-start">' +
              '<div class="col-lg-7 text-center">' +
                '<h5 class="sa-h">' + Th('Spatial prediction') + '</h5>' +
                '<div class="sa-gene-title" aria-live="polite"></div>' +
                '<div class="sa-plot" role="img" aria-label="' + Th('3-D model of predicted expression') + '"><div class="sa-wait">' + Th('Loading...') + '</div></div>' +
              '</div>' +
              '<div class="col-lg-5 text-center">' +
                '<h5 class="sa-h">' + Th('In situ results') + '</h5>' +
                '<div class="sa-gene-title sa-image-title"></div>' +
                '<div class="sa-image"></div>' +
              '</div>' +
            '</div>' +
            '<h6 class="sa-h mt-3 text-center">' + Th('Top 10 genes with similar spatial pattern based on pattern correlation analysis') + '</h6>' +
            '<div class="table-responsive"><table class="table table-sm sa-table mb-0"><thead><tr>' +
              '<th class="text-end">#</th><th>' + Th('Similar gene') + '</th><th class="text-end">' + Th('Correlation coefficient') + '</th><th></th>' +
            '</tr></thead><tbody></tbody></table></div>';
        var el = function (c) { return root.querySelector(c); };
        var plotEl = el('.sa-plot'), drawn = false, current = null;

        function fail(msg) { plotEl.innerHTML = '<div class="sa-wait">' + esc(msg) + '</div>'; }

        function show(geneId) {
            current = geneId;
            return Promise.all([atlasData(o), loadPlotly(o.plotlyUrl)]).then(function (r) {
                var d = r[0], Plotly = r[1];
                return fetch(d.q('gene') + '&gene=' + encodeURIComponent(geneId)).then(function (x) {
                    if (!x.ok) throw new Error('gene');
                    return x.json();
                }).then(function (g) {
                    if (current !== geneId) return;   // a newer choice arrived while this one loaded
                    render(d, Plotly, g);
                    if (typeof o.onShow === 'function') o.onShow(g);
                });
            }).catch(function () { fail(T('The atlas could not be loaded.')); });
        }

        function render(d, Plotly, g) {
            var info = d.info, m = d.mesh, lbl = label(g.gene_id, g.name);
            var titles = root.querySelectorAll('.sa-gene-title');
            var head = g.status === 'expressed' ? esc(lbl)
                     : g.status === 'not_expressed' ? esc(lbl) + '<br><span class="sa-note">' + Th('is not expressed in this study') + '</span>'
                     : esc(g.gene_id) + '<br><span class="sa-note">' + Th('is not in this atlas') + '</span>';
            titles[0].innerHTML = head;
            titles[1].innerHTML = g.status === 'not_in_study' ? '' : esc(lbl);

            var vals = decode(g, m.n), flat = g.status !== 'expressed' || g.vmax === g.vmin;
            var mesh = {
                type: 'mesh3d', x: m.x, y: m.y, z: m.z, i: m.i, j: m.j, k: m.k, name: '',
                intensity: vals, intensitymode: 'vertex', colorscale: info.colorscale || 'Viridis',
                cmin: flat ? 0 : g.vmin, cmax: flat ? 1 : g.vmax, showscale: !flat,
                lighting: { ambient: 0.18, diffuse: 1, fresnel: 0.1, specular: 1, roughness: 0.4 },
                lightposition: { x: 100, y: 200, z: 150 },
                hoverinfo: 'skip',
                colorbar: { xanchor: 'left', x: -0.1, yanchor: 'top', y: 1, xpad: 0.5, len: 0.3, thickness: 4,
                            title: { text: info.value_label || T('Expression prediction'), side: 'right' },
                            tickvals: [g.vmin, g.vmax], ticktext: [T('Low'), T('High')] }
            };
            var data = [mesh];
            if (m.ox) data.push({ type: 'scatter3d', mode: 'markers', x: m.ox, y: m.oy, z: m.oz, hoverinfo: 'skip',
                                  marker: { size: 1.5, color: 'black' }, showlegend: false });
            var asp = info.aspect || [1, 1, 0.8], eye = (info.camera && info.camera.eye) || [0, 1, 1.2];
            var layout = {
                paper_bgcolor: 'rgb(0,0,0)', font: { size: 14, color: 'white' }, hovermode: false, showlegend: false,
                margin: { l: 40, r: 40, t: 10, b: 20 }, height: 400,
                scene: { xaxis: { visible: false }, yaxis: { visible: false }, zaxis: { visible: false }, bgcolor: 'rgb(0,0,0)',
                         aspectratio: { x: asp[0], y: asp[1], z: asp[2] },
                         camera: { eye: { x: eye[0], y: eye[1], z: eye[2] } } },
                uirevision: 'atlas'   // keep the visitor's rotation when the gene changes
            };

            var img = el('.sa-image');
            img.innerHTML = g.image
                ? '<a href="' + esc(g.image.url) + '" target="_blank" rel="noopener"><img src="' + esc(g.image.url) + '" alt="' +
                  Th('In situ hybridization of %s', lbl) + '" class="sa-insitu"></a>'
                : '<div class="sa-noimage">' + Th('Expression pattern has not been experimentally validated') + '</div>';

            var tb = el('.sa-table tbody');
            if (!g.similar || !g.similar.length) {
                tb.innerHTML = '<tr><td colspan="4" class="text-center">' + Th('No similar genes listed for this gene.') + '</td></tr>';
            } else {
                tb.innerHTML = g.similar.map(function (s, n) {
                    var link = '<a href="' + esc(o.geneUrl + encodeURIComponent(s.gene_id)) + '" title="' + Th('Open the gene page') + '">' + esc(label(s.gene_id, s.name)) + '</a>';
                    var view = o.mode === 'tool'
                        ? '<button type="button" class="btn btn-sm sa-btn" data-gene="' + esc(s.gene_id) + '">' + Th('Show') + '</button>'
                        : '<a class="btn btn-sm sa-btn" href="' + esc(o.toolUrl + encodeURIComponent(s.gene_id)) + '">' + Th('Show in atlas') + '</a>';
                    return '<tr><td class="text-end">' + (n + 1) + '</td><td>' + link + '</td><td class="text-end">' + s.r.toFixed(3) + '</td><td class="text-end">' + view + '</td></tr>';
                }).join('');
            }

            // The 3-D model last: if this browser cannot draw WebGL, the picture and the table still show.
            var noGl = function () {
                drawn = false;
                fail(T('This browser cannot draw the 3-D model (WebGL is off or not supported).'));
            };
            try {
                if (!drawn) plotEl.innerHTML = '';
                var pr = Plotly.react(plotEl, data, layout, { scrollZoom: true, displaylogo: false, responsive: true });
                drawn = true;
                if (pr && pr.catch) pr.catch(noGl);
            } catch (e) { noGl(); }
        }

        root.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-gene]');
            if (b) show(b.getAttribute('data-gene'));
        });
        if (o.gene) show(o.gene);
        return { show: show };
    }

    window.MoopSpatialAtlas = { mount: mount };
})();
