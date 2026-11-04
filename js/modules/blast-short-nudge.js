/**
 * BLAST: point a short DNA query at BLASTn-short, before and after it is lost.
 *
 * A 20 nt primer run through BLASTn or Megablast at the defaults comes back with nothing --
 * Megablast cannot seed it (word 28), BLASTn finds it and the 1e-3 E-value discards it --
 * and a bare "no matches" reads as "not in this genome". Two nudges, both SUGGESTIONS: the
 * program is only ever changed by the user's click, because someone who chose BLASTn with
 * a strict cut-off on purpose should get exactly that.
 *
 *   1. On the form: a line under the program box while the query is short DNA and BLASTn
 *      or Megablast is selected, with a Switch button.
 *   2. On a zero-hit result: the reason (rendered server-side by blast_short_query_reason())
 *      and a Re-run button (.blast-rerun-short).
 *
 * Both switch through the program <select>'s own change handler, so the BLASTn-short preset
 * (word 7, E-value 1000, ...) is applied the one way it always is -- and then put the
 * DATABASE back: the preset jumps to the genome database, which would make a re-run quietly
 * search somewhere other than the search it is re-running.
 *
 * The threshold comes from PHP (BLAST_SHORT_QUERY_NT via blastShortQueryNt), so the form
 * and the results cannot disagree about what "short" means.
 */
(function () {
    'use strict';

    // Every DNA-query program except BLASTn-short itself. BLASTx/tBLASTx translate the query,
    // so 20 letters is under 7 amino acids -- lost too, and BLASTx is what a fresh form shows.
    const SHORT_PROGRAMS = ['blastn', 'megablast', 'blastx', 'tblastx'];

    /** Letter counts of each record in a FASTA or plain-sequence paste. */
    function recordLengths(text) {
        const lengths = [];
        let cur = null;
        text.split('\n').forEach(line => {
            if (line.trim().startsWith('>')) {
                if (cur !== null) lengths.push(cur);
                cur = 0;
            } else {
                cur = (cur || 0) + line.replace(/[^A-Za-z]/g, '').length;
            }
        });
        if (cur !== null) lengths.push(cur);
        return lengths.filter(n => n > 0);
    }

    /**
     * Switch to BLASTn-short, keeping the chosen database. By VALUE: the change handler
     * rebuilds the database buttons, so a saved element is detached by the time it returns.
     * A protein database (after BLASTx) has no DNA twin, so the preset's choice stands.
     */
    function switchToShort(form) {
        const select = form.querySelector('#blast_program');
        if (!select || !select.querySelector('option[value="blastn-short"]')) return false;
        const was = form.querySelector('input[name="blast_db"]:checked')?.value;
        select.value = 'blastn-short';
        select.dispatchEvent(new Event('change'));
        const again = was && [...form.querySelectorAll('input[name="blast_db"]')].find(r => r.value === was);
        if (again && !again.disabled) again.checked = true;
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form    = document.getElementById('blastForm');
        const query   = document.getElementById('query');
        const select  = document.getElementById('blast_program');
        const suggest = document.getElementById('blast-short-suggest');
        if (!form || !query || !select || typeof blastShortQueryNt === 'undefined') return;

        function update() {
            if (!suggest) return;
            const lengths = recordLengths(query.value);
            const short   = lengths.filter(n => n < blastShortQueryNt);
            const isDna   = typeof detectSequenceType !== 'function'
                         || detectSequenceType(query.value).type === 'nucleotide';
            const show = short.length > 0 && isDna && SHORT_PROGRAMS.includes(select.value);
            suggest.classList.toggle('d-none', !show);
            if (!show) return;
            suggest.querySelector('.blast-short-what').textContent = lengths.length === 1
                ? moopT('Short sequence (%d letters):', short[0])
                : (short.length === 1 ? moopT('%d of your %d sequences is under %d letters:', short.length, lengths.length, blastShortQueryNt)
                                      : moopT('%d of your %d sequences are under %d letters:', short.length, lengths.length, blastShortQueryNt));
        }

        query.addEventListener('input', update);
        select.addEventListener('change', update);
        update();

        suggest?.querySelector('.blast-short-switch')?.addEventListener('click', function () {
            switchToShort(form);
            update();
        });

        // Results: re-run the same query against the same database with BLASTn-short.
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.blast-rerun-short');
            if (!btn) return;
            if (!switchToShort(form)) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>' + moopTh('Re-running…');
            // requestSubmit, not submit(): the form's own submit handler fills the organism
            // fields and shows the queue position, and submit() skips it.
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        });
    });
})();
