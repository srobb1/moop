/**
 * Gene page: the « and » buttons either side of the gene structure diagram (upstream and
 * downstream gene).
 *
 * Each button carries its genome side (data-side="left" | "right"); the page has already worked
 * out which side is upstream from the gene's strand. A click asks api/gene_neighbor.php for the
 * nearest gene on that side that has a gene page, then goes there. The lookup reads the gene
 * set's whole coordinates file (~0.25 s on the largest), so it runs on the click, never on load.
 *
 * When there is nothing further on the sequence the answer is shown under the diagram rather
 * than on a page of its own: the user stays where they were.
 */
(function () {
    const box = document.querySelector('.gene-neighbors');
    if (!box) return;
    const status  = box.querySelector('.gene-neighbor-status');
    const buttons = box.querySelectorAll('.gene-neighbor-btn');

    // "Go to the upstream gene page" on hover, at once: no delay and no fade (Sofia found the
    // default fade slow; the plain title attribute shows only after a pause of its own)
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        buttons.forEach(b => new bootstrap.Tooltip(b, {animation: false, delay: {show: 0, hide: 0}}));
    }

    function setBusy(busy, message) {
        buttons.forEach(b => { b.disabled = busy; });
        status.textContent = message || '';
    }

    buttons.forEach(btn => btn.addEventListener('click', async () => {
        const params = new URLSearchParams({
            organism:   box.dataset.organism,
            assembly:   box.dataset.assembly,
            gene_set:   box.dataset.geneSet,
            uniquename: box.dataset.uniquename,
            side:       btn.dataset.side,
        });
        setBusy(true, moopT('Finding the nearest gene…'));
        try {
            const resp = await fetch(`${window.sitePath}/api/gene_neighbor.php?${params}`);
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || `HTTP ${resp.status}`);
            if (data.url) {
                window.location.href = data.url;
                return;
            }
            // The buttons are icons now (« »), so the words come from data-label
            const direction = btn.dataset.label || moopT('neighbouring gene');   // already in the visitor's language
            const where = data.skipped
                ? (data.skipped === 1 ? moopT('No %1$s with a gene page on %2$s (%3$d gene without a page — see the genome browser).', direction, data.chr, 1)
                                      : moopT('No %1$s with a gene page on %2$s (%3$d genes without a page — see the genome browser).', direction, data.chr, data.skipped))
                : moopT('No %1$s with a gene page on %2$s.', direction, data.chr);
            setBusy(false, where);
            btn.disabled = true;
        } catch (err) {
            setBusy(false, moopT('Could not look up the neighbouring gene (%s).', err.message));
        }
    }));
})();
