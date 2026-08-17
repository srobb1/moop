/**
 * Primer Maker — form behaviour and downloads.
 *
 * Everything here is a convenience over a form that already works without it:
 * the page submits, designs primers and renders results with scripting off.
 * That is deliberate — the placeholder sync below is the only thing that would
 * otherwise need a round trip, and it is not worth making the tool depend on JS
 * for.
 *
 * Values from PHP arrive as `primerOptionDefaults` and `primerTailCatalogue`,
 * defined by inline_scripts in tools/primer_maker.php (CLAUDE.md §7 — PHP→JS
 * variable passing is the one thing inline scripts are for).
 */

(function () {
    'use strict';

    /**
     * Show each option's fallback value for the CHOSEN primer type.
     *
     * The boxes are blank by default and blank means "use the primer type's
     * value", so without this the user is asked to override a number they cannot
     * see. Placeholders come from the same PHP arrays the run uses, so they
     * cannot claim one value while primer3 is handed another.
     */
    function syncOptionPlaceholders() {
        if (typeof primerOptionDefaults === 'undefined') { return; }

        var checked = document.querySelector('input[name="primer_type"]:checked');
        if (!checked) { return; }

        var defaults = primerOptionDefaults[checked.value];
        if (!defaults) { return; }

        document.querySelectorAll('[data-option]').forEach(function (input) {
            var value = defaults[input.dataset.option];
            // An empty placeholder is better than a stale one: it says "primer3
            // decides" rather than naming a number from the previous preset.
            input.placeholder = (value === undefined || value === null) ? '' : String(value);
        });
    }

    /**
     * Show the sequences of the selected tail, and the custom boxes only when
     * "Custom tail…" is chosen.
     *
     * Server-side rendering already puts this in the right state on load; this
     * keeps it right as the user changes the select.
     */
    function syncTailFields() {
        var select = document.getElementById('tail');
        if (!select) { return; }

        var chosen = select.value;

        document.querySelectorAll('.tail-detail').forEach(function (node) {
            node.hidden = node.dataset.tail !== chosen;
        });

        var custom = document.getElementById('customTailFields');
        if (custom) {
            custom.hidden = chosen !== 'custom';
        }
    }

    /**
     * Rows of objects → a TSV file.
     *
     * The header is derived from the DATA's own keys, so it cannot drift from
     * the table that produced it. Tabs and newlines inside a value are flattened
     * to spaces — a stray one would shift every later column of that row, which
     * is the kind of corruption nobody notices until the numbers are wrong.
     */
    function downloadTsv(rows, filename) {
        if (!rows || !rows.length) { return; }

        var header = Object.keys(rows[0]);
        var lines = [header.join('\t')];

        rows.forEach(function (row) {
            lines.push(header.map(function (key) {
                var cell = row[key];
                return (cell === null || cell === undefined ? '' : String(cell))
                    .replace(/[\t\r\n]/g, ' ');
            }).join('\t'));
        });

        var blob = new Blob([lines.join('\n') + '\n'], { type: 'text/tab-separated-values' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function readJson(id) {
        var node = document.getElementById(id);
        if (!node) { return null; }
        try {
            return JSON.parse(node.textContent);
        } catch (e) {
            return null;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncOptionPlaceholders();
        syncTailFields();

        document.querySelectorAll('input[name="primer_type"]').forEach(function (radio) {
            radio.addEventListener('change', syncOptionPlaceholders);
        });

        var tailSelect = document.getElementById('tail');
        if (tailSelect) {
            tailSelect.addEventListener('change', syncTailFields);
        }

        // addEventListener rather than an onclick attribute: the site's CSP is
        // Report-Only pending the removal of inline handlers (CLAUDE.md), and
        // adding another would push that further away.
        var tsvButton = document.getElementById('downloadPrimerTsv');
        if (tsvButton) {
            tsvButton.addEventListener('click', function () {
                downloadTsv(readJson('primerMakerExport'), 'primer_maker_results.tsv');
            });
        }
    });
}());
