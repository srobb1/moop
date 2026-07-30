/**
 * MOOP JavaScript smoke tests — the search-term and highlighting logic.
 *
 * Companion to tests/smoke_tests.php, and deliberately the same shape: plain assertions,
 * no framework, no dependencies, exit 0 = all pass. Run:
 *
 *     node tests/js_smoke_tests.js
 *
 * WHY THESE FUNCTIONS. They are pure, they decide what the user sees on every results
 * page, and they are the ones that have actually been wrong:
 *
 *   - the "is this input usable?" rule was once three different expressions of one idea
 *     living in three files, none of which agreed (see search-terms.js);
 *   - highlighting marked `HD` inside "PHD finger protein 12" for a search of HDAC, on a
 *     row whose annotation column matched HDAC properly — the short wrong mark appears in
 *     an earlier column, so it is the one the user sees first.
 *
 * Everything here runs against the real module files, loaded into a vm context, so a test
 * cannot drift from the shipped source the way a copied fixture would.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// MOOP_JS_ROOT lets the suite run against a DIFFERENT checkout of the modules — which is
// how you prove the tests can actually fail. Point it at an older revision and the
// highlighting cases must go red; if they stay green the tests are not testing anything.
const ROOT = process.env.MOOP_JS_ROOT || path.resolve(__dirname, '..');
const MODULES = ['js/modules/search-terms.js', 'js/modules/shared-results-table.js'];

const sandbox = { console };
vm.createContext(sandbox);
for (const rel of MODULES) {
    vm.runInContext(fs.readFileSync(path.join(ROOT, rel), 'utf8'), sandbox, { filename: rel });
}
const {
    moopSearchTerms, moopSearchInputIsUsable, moopHighlightableTerms,
    moopTermHighlight, highlightSearchTerms, moopRowHighlightText,
} = sandbox;

// ---------------------------------------------------------------------------- harness
let PASS = 0, FAIL = 0;
const FAILURES = [];

// A throw part-way through must be a REPORTED failure with a non-zero exit, not a bare
// stack trace. Without this the suite dies silently mid-run: the summary never prints, and
// a caller that pipes the output (`| tail`) reads the pipe's exit status and calls it a
// pass. That is exactly how a broken suite looks like a green one.
process.on('uncaughtException', (err) => {
    console.log('\n  FAIL  suite aborted: ' + err.message);
    console.log('\n' + '-'.repeat(60));
    console.log('JS smoke tests: ' + PASS + ' passed, ' + (FAIL + 1) + ' failed (aborted early)');
    console.log('SOME JS SMOKE TESTS FAILED');
    process.exit(1);
});
function group(name) { console.log('\n== ' + name + ' =='); }
function ok(cond, label) {
    if (cond) { PASS++; console.log('  PASS  ' + label); }
    else { FAIL++; FAILURES.push(label); console.log('  FAIL  ' + label); }
}
function eq(got, want, label) {
    const same = JSON.stringify(got) === JSON.stringify(want);
    if (!same) console.log('        got  ' + JSON.stringify(got) +
                           '\n        want ' + JSON.stringify(want));
    ok(same, label);
}

/** The visible marks in highlighted HTML, tagged exact (amber) or stem (blue). */
function marks(html) {
    return (html.match(/<strong[^>]*>(.*?)<\/strong>/g) || [])
        .map(m => m.replace(/<[^>]*>/g, '') + (/fff3cd/.test(m) ? '[exact]' : '[stem]'));
}

// ---------------------------------------------------------------------------- terms
group('moopSearchTerms — splitting');
eq(moopSearchTerms('histone deacetylase 1'), ['histone', 'deacetylase', '1'], 'splits on whitespace');
eq(moopSearchTerms('  spaced   out  '), ['spaced', 'out'], 'collapses repeated whitespace');
eq(moopSearchTerms('"zinc finger"'), ['zinc finger'], 'a quoted phrase is ONE term, not two');
eq(moopSearchTerms('""'), [], 'an empty phrase yields no terms');
eq(moopSearchTerms(''), [], 'empty input yields no terms');
eq(moopSearchTerms('--- +++'), [], 'punctuation-only tokens are dropped');
eq(moopSearchTerms('HDAC -'), ['HDAC'], 'keeps real terms, drops punctuation-only ones');

group('moopSearchInputIsUsable — at least one term of 2+ characters');
ok(moopSearchInputIsUsable('F8'), 'F8 (a real gene symbol) is usable');
ok(moopSearchInputIsUsable('AR'), 'AR (androgen receptor) is usable');
ok(!moopSearchInputIsUsable('a'), 'a single letter is refused');
ok(!moopSearchInputIsUsable('1'), 'a single digit is refused');
ok(moopSearchInputIsUsable('histone deacetylase 1'), 'a short term alongside long ones is fine');
ok(!moopSearchInputIsUsable(''), 'empty input is refused');

group('moopHighlightableTerms — same threshold as the gate');
eq(moopHighlightableTerms('histone deacetylase 1'), ['histone', 'deacetylase'],
   'the 1-character term is not highlightable but the others are');
eq(moopHighlightableTerms('F8 clotting'), ['F8', 'clotting'],
   'a 2-character term IS highlightable — if it can be searched it must be markable');

// ---------------------------------------------------------------------------- highlighting
group('moopTermHighlight — exact vs stem vs nothing');
eq(moopTermHighlight('HDAC1 and HDAC2', 'HDAC', false), { text: 'HDAC', exact: true },
   'the literal term is found and marked exact');
eq(moopTermHighlight('protein kinase', 'proteins', false), { text: 'protein', exact: false },
   'a porter stem is reached by trimming from the right');
eq(moopTermHighlight('PHD finger protein 12', 'HDAC', false), null,
   'a mid-word letter run (HD inside PHD) is NOT a stem match');
eq(moopTermHighlight('PHD finger protein 12', 'HDAC', true), null,
   'and is refused outright once the row matched the term exactly elsewhere');
eq(moopTermHighlight('coagulation factor VIII', 'F8', false), null,
   'a term that is genuinely absent from this cell marks nothing');

group('highlightSearchTerms — the reported HDAC row (Bipalium vagum)');
const hdacRow = {
    feature_name: 'Phf12',
    feature_description: 'PHD finger protein 12 OS=Mus musculus OX=10090 GN=Phf12 PE=1 SV=1',
    annotation_description: 'Sin3-type complex: any of a number of conserved histone '
        + 'deacetylase complexes (HDACs) containing at least one class I histone '
        + 'deacetylase (e.g. HDAC1 and HDAC2 in mammals)',
};
const hdacText = moopRowHighlightText(hdacRow);
eq(marks(highlightSearchTerms(hdacRow.feature_description, 'HDAC', hdacText)), [],
   'Description marks nothing — no more HD inside PHD');
eq(marks(highlightSearchTerms(hdacRow.feature_name, 'HDAC', hdacText)), [],
   'Name marks nothing');
eq(marks(highlightSearchTerms(hdacRow.annotation_description, 'HDAC', hdacText)),
   ['HDAC[exact]', 'HDAC[exact]', 'HDAC[exact]'],
   'Annotation Description marks every real HDAC as exact');

group('highlightSearchTerms — stem marking must keep working');
const stemRow = { feature_description: 'protein kinase activity', annotation_description: '' };
eq(marks(highlightSearchTerms(stemRow.feature_description, 'proteins', moopRowHighlightText(stemRow))),
   ['protein[stem]'], 'proteins still marks protein as a stem hit');
const tRow = { feature_description: 'transposon protein', annotation_description: '' };
eq(marks(highlightSearchTerms(tRow.feature_description, 'transposons', moopRowHighlightText(tRow))),
   ['transposon[stem]'], 'transposons still marks transposon');
const exactRow = { feature_description: 'HDAC1 deacetylase', annotation_description: '' };
eq(marks(highlightSearchTerms(exactRow.feature_description, 'HDAC', moopRowHighlightText(exactRow))),
   ['HDAC[exact]'], 'HDAC is still marked inside HDAC1');

group('highlightSearchTerms — edges');
const noneRow = { feature_description: 'PHD finger protein 12', annotation_description: 'zinc finger' };
eq(marks(highlightSearchTerms(noneRow.feature_description, 'HDAC', moopRowHighlightText(noneRow))), [],
   'a row with no HDAC anywhere still refuses to mark HD in PHD');
eq(marks(highlightSearchTerms('a zinc finger domain', '"zinc finger"', '')),
   ['zinc finger[exact]'], 'a quoted phrase is marked as one unit');
eq(highlightSearchTerms('', 'HDAC', ''), '', 'empty text returns empty, not undefined');
eq(highlightSearchTerms('some text', '', ''), 'some text', 'empty keywords leave the text alone');
ok(!/undefined/.test(String(highlightSearchTerms(null, 'HDAC', ''))),
   'null text never renders the string "undefined"');

// ---------------------------------------------------------------------------- report
console.log('\n' + '-'.repeat(60));
console.log('JS smoke tests: ' + PASS + ' passed, ' + FAIL + ' failed');
if (FAIL) {
    console.log('\nFailures:');
    FAILURES.forEach(f => console.log('  - ' + f));
    console.log('SOME JS SMOKE TESTS FAILED');
    process.exit(1);
}
console.log('ALL JS SMOKE TESTS PASSED');
