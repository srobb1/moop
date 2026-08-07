<?php
/**
 * Primer tool smoke tests — input parsing.
 *
 *   PrimerInput::parse()  — the four supported input shapes, the pairing rules,
 *                           and the validation that must never fail silently.
 *
 * Plain PHP assertions, no framework, no site data — same philosophy as
 * tests/smoke_tests.php, and hermetic so they pass on any checkout.
 *
 * Run:  php tests/primer_smoke_tests.php     (exit 0 = all pass, 1 = failures)
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = dirname(__DIR__);
require_once "$BASE/lib/primer/PrimerInput.php";

// ----------------------------------------------------------------------------
// Tiny test harness (same shape as tests/smoke_tests.php)
// ----------------------------------------------------------------------------
$PASS = 0; $FAIL = 0; $FAILURES = [];
function group($name) { echo "\n== $name ==\n"; }
function ok($cond, $label) {
    global $PASS, $FAIL, $FAILURES;
    if ($cond) { $PASS++; echo "  PASS  $label\n"; }
    else       { $FAIL++; $FAILURES[] = $label; echo "  FAIL  $label\n"; }
}

$F = 'GCTTGAGCTGTTATCTGTGC';   // 20 nt
$R = 'GCGGTGCTTCTGGGCTGAGT';   // 20 nt

// ----------------------------------------------------------------------------
group('shape 1 — FASTA, one primer per record, paired by _F / _R suffix');

$r = PrimerInput::parse(">TP53_F\n$F\n>TP53_R\n$R\n");
ok($r['shape'] === 'fasta_one_per_record', 'shape detected as fasta_one_per_record');
ok(count($r['pairs']) === 1, 'one pair formed');
ok(($r['pairs'][0]['name'] ?? '') === 'TP53', 'pair named from the shared base name');
ok(($r['pairs'][0]['forward'] ?? '') === $F, 'forward sequence taken from the _F record');
ok(($r['pairs'][0]['reverse'] ?? '') === $R, 'reverse sequence taken from the _R record');
ok(empty($r['errors']), 'no errors');

// Suffix pairing must not depend on file order.
$r = PrimerInput::parse(">TP53_R\n$R\n>TP53_F\n$F\n");
ok(($r['pairs'][0]['forward'] ?? '') === $F, 'suffix pairing survives reversed record order');

// Alternative suffix vocabularies.
foreach ([['fwd', 'rev'], ['left', 'right'], ['forward', 'reverse'], ['1', '2']] as $sfx) {
    $r = PrimerInput::parse(">g_{$sfx[0]}\n$F\n>g_{$sfx[1]}\n$R\n");
    ok(count($r['pairs']) === 1 && $r['pairs'][0]['forward'] === $F,
       "suffix pair _{$sfx[0]}/_{$sfx[1]} recognised");
}

// ----------------------------------------------------------------------------
group('shape 2 — FASTA, both primers in one record, N-separated');

$r = PrimerInput::parse(">my-pair\n{$F}NNNNNNNN{$R}\n");
ok($r['shape'] === 'fasta_pair_in_record', 'shape detected as fasta_pair_in_record');
ok(count($r['pairs']) === 1, 'one pair formed');
ok(($r['pairs'][0]['forward'] ?? '') === $F, 'forward is the fragment before the N-run');
ok(($r['pairs'][0]['reverse'] ?? '') === $R, 'reverse is the fragment after the N-run');
ok(($r['pairs'][0]['name'] ?? '') === 'my-pair', 'pair keeps the record name');

// A run at the threshold splits; a shorter run is a degenerate base, not a separator.
$r = PrimerInput::parse(">t\n{$F}NNN{$R}\n");
ok(count($r['pairs']) === 1, 'a run of exactly 3 N separates');

$r = PrimerInput::parse(">degenerate\n{$F}NN{$R}\n");
ok($r['shape'] === 'fasta_one_per_record',
   'a run of 2 N is NOT a separator — degenerate bases are left alone');

// Three fragments cannot be a pair, and must be reported rather than truncated.
$r = PrimerInput::parse(">triple\n{$F}NNNN{$R}NNNN{$F}\n");
ok(empty($r['pairs']), 'a record splitting into 3 fragments forms no pair');
ok(!empty($r['errors']), 'and it is reported as an error, not dropped silently');

// ----------------------------------------------------------------------------
group('shape 3 — TSV / CSV');

$r = PrimerInput::parse("name\tforward\treverse\nTP53\t$F\t$R\n");
ok($r['shape'] === 'delimited', 'tab-delimited detected');
ok(count($r['pairs']) === 1 && $r['pairs'][0]['name'] === 'TP53', 'TSV row parsed');
ok(($r['pairs'][0]['forward'] ?? '') === $F, 'TSV forward column read');

$r = PrimerInput::parse("name,left,right\nTP53,$F,$R\n");
ok(count($r['pairs']) === 1 && $r['pairs'][0]['reverse'] === $R, 'CSV with left/right headers parsed');

// ----------------------------------------------------------------------------
group('shape 4 — bare sequences, paired by adjacency');

$r = PrimerInput::parse("$F\n$R\n");
ok($r['shape'] === 'bare', 'bare sequences detected');
ok(count($r['pairs']) === 1, 'adjacency pairing forms one pair');
ok(!empty($r['warnings']), 'and the adjacency assumption is stated as a warning');

// ----------------------------------------------------------------------------
group('validation — nothing may fail silently');

$r = PrimerInput::parse(">bad_F\nGCTTGAGCTG#TTATCTGTGC\n>bad_R\n$R\n");
ok(empty($r['pairs']), 'a primer with an invalid character forms no pair');
ok(!empty($r['errors']), 'the invalid character is reported');
ok(strpos(implode(' ', $r['errors']), 'bad') !== false, 'the offending record is named in the error');

$r = PrimerInput::parse(">a\n$F\n>b\n$R\n>c\n$F\n");
ok(!empty($r['errors']), 'an odd primer count is reported rather than guessed at');
ok(strpos(implode(' ', $r['errors']), 'c') !== false, 'the unpaired record is named');
ok(count($r['pairs']) === 1, 'the pairs that could be formed are still returned');

$r = PrimerInput::parse(">short_F\nGCTTGAGCT\n>short_R\n$R\n");
ok(count($r['pairs']) === 1, 'a short primer still searches (over-report, never under-report)');
ok(!empty($r['warnings']), 'but its length is warned about');

$r = PrimerInput::parse(">dup\n$F\n>dup\n$R\n");
ok(!empty($r['warnings']), 'duplicate names are warned about (vendor forms key on name)');

$r = PrimerInput::parse('');
ok(!empty($r['errors']) && empty($r['pairs']), 'empty input is an error, not an empty success');

// Sequences pasted with position numbers and wrapped lines must still parse.
$r = PrimerInput::parse(">wrapped_F\n1 GCTTGAGCTG\n11 TTATCTGTGC\n>wrapped_R\n$R\n");
ok(($r['pairs'][0]['forward'] ?? '') === $F,
   'wrapped sequence with position numbers is reassembled correctly');

// Lower-case input is normalised.
$r = PrimerInput::parse(">lc_F\n" . strtolower($F) . "\n>lc_R\n" . strtolower($R) . "\n");
ok(($r['pairs'][0]['forward'] ?? '') === $F, 'lower-case sequence is upper-cased');

// IUPAC degenerate bases are legal in a primer.
$r = PrimerInput::parse(">deg_F\nGCTTGAGCTGTTATCTGTRC\n>deg_R\n$R\n");
ok(count($r['pairs']) === 1 && empty($r['errors']), 'IUPAC ambiguity codes are accepted');

// ----------------------------------------------------------------------------
// PrimerPairs — product formation. Synthetic hits, no BLAST, no site data.
// ----------------------------------------------------------------------------
require_once "$BASE/lib/primer/PrimerPairs.php";

/** Build a hit the way PrimerBlast emits one. */
function hit($subject, $start, $end, $strand, $mismatch = 0, $three_prime = 0) {
    return [
        'subject'  => $subject, 'strand' => $strand,
        'start'    => $start,   'end'    => $end,
        'length'   => 20,       'mismatch' => $mismatch,
        'gapopen'  => 0, 'qstart' => 1, 'qend' => 20, 'qlength' => 20,
        'three_prime_mismatch' => $three_prime,
    ];
}

// A generous explicit bound. These tests exercise the pairing RULES, so they must
// not break when the default product size changes for product reasons.
$BIG = ['max_product' => 100000];

group('PrimerPairs — a product needs opposite strands AND primers that face each other');

// The measured wallaby case: the user's "forward" primer aligns to the MINUS
// strand because the gene is on the minus strand. Roles must not be hard-coded.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr2', 75271555, 75271574, '-')],
    'reverse' => [hit('chr2', 75263833, 75263852, '+')],
], $BIG);
ok($p['product_count'] === 1, 'one product forms when the plus hit is left of the minus hit');
ok(($p['products'][0]['size'] ?? 0) === 7742, 'product size matches the measured 7,742 bp');
ok(($p['products'][0]['start'] ?? 0) === 75263833, 'product starts at the plus-strand hit');
ok(($p['products'][0]['end'] ?? 0) === 75271574, 'product ends at the minus-strand hit');

// Same strand cannot bracket anything.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+')],
    'reverse' => [hit('chr1', 2000, 2019, '+')],
]);
ok($p['product_count'] === 0, 'two same-strand hits form no product');

// Facing AWAY: minus on the left, plus on the right. Looks like a valid pair on
// a plain BLAST page, amplifies nothing.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '-')],
    'reverse' => [hit('chr1', 2000, 2019, '+')],
]);
ok($p['product_count'] === 0, 'primers pointing away from each other form no product');

group('PrimerPairs — counting, bounds, and the asymmetry flag');

// The central rule: many hits for one primer, one product.
$reverse_hits = [hit('chr1', 5000, 5019, '-')];
for ($i = 1; $i <= 14; $i++) {
    $reverse_hits[] = hit('chr' . (1 + $i), 900000 + $i, 900019 + $i, '-');
}
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+')],
    'reverse' => $reverse_hits,
], $BIG);
ok($p['primer_hits']['reverse'] === 15, 'per-primer hit counts are reported (15 reverse hits)');
ok($p['product_count'] === 1, 'but only ONE product forms — count products, not hits');
ok($p['carried_by'] === 'forward', 'and specificity is flagged as resting on the forward primer');

// Size bound discards, and says so.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+')],
    'reverse' => [hit('chr1', 9000000, 9000019, '-')],
], ['max_product' => 50000]);
ok($p['product_count'] === 0, 'a product beyond the size bound is not listed');
ok($p['over_max'] === 1, 'and the discard is COUNTED, never silent');

// Same-primer products are real unwanted amplification and must be reported.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+'), hit('chr1', 3000, 3019, '-')],
    'reverse' => [],
], $BIG);
ok($p['product_count'] === 1, 'one primer hitting in both orientations forms a product');
ok(($p['products'][0]['self_pairing'] ?? false) === true, 'and it is flagged as self-pairing');

// Products are ordered by size so the list reads predictably.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+')],
    'reverse' => [hit('chr1', 1500, 1519, '-'), hit('chr1', 9000, 9019, '-')],
], $BIG);
ok($p['product_count'] === 2, 'two products found');
ok($p['products'][0]['size'] < $p['products'][1]['size'], 'products are sorted by size');

// Mismatch counts survive onto the product, since they are reported per product.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+', 0)],
    'reverse' => [hit('chr1', 1500, 1519, '-', 2)],
]);
ok(($p['products'][0]['max_mismatch'] ?? null) === 2, 'the product carries the worst mismatch count');

group('PrimerPairs — primaryProduct() must compare like with like');

// The trap: a self-pairing artefact SMALLER than the real amplicon. Sorting by
// size alone would make it "the" product, and the genomic-vs-cDNA verdict would
// then be computed from an artefact and be confidently wrong.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+'), hit('chr1', 1200, 1219, '-')],
    'reverse' => [hit('chr1', 5000, 5019, '-')],
], $BIG);
$primary = PrimerPairs::primaryProduct($p);
ok($p['products'][0]['self_pairing'] === true, 'the smallest product here IS the self-pairing artefact');
ok($primary['self_pairing'] === false, 'primaryProduct skips it and picks the real forward+reverse pair');
ok($primary['size'] === 4020, 'and returns that pair’s size, not the artefact’s');

// Among real pairs, the cleanest match wins over the smallest.
$p = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+', 0)],
    'reverse' => [hit('chr1', 1200, 1219, '-', 2), hit('chr1', 5000, 5019, '-', 0)],
], $BIG);
$primary = PrimerPairs::primaryProduct($p);
ok($primary['max_mismatch'] === 0, 'fewest mismatches beats smallest size');

ok(PrimerPairs::primaryProduct(['products' => []]) === null, 'no products yields null, not a crash');

group('PrimerBlast — locating 3\' mismatches from BTOP');

require_once "$BASE/lib/primer/PrimerBlast.php";

ok(PrimerBlast::threePrimeMismatches('20', 1, 20, 20) === 0,
   'a perfect 20/20 alignment has no 3\' mismatches');
ok(PrimerBlast::threePrimeMismatches('7AG12', 1, 20, 20) === 0,
   'a mismatch at query position 8 is outside the last-5 window');
ok(PrimerBlast::threePrimeMismatches('17AG2', 1, 20, 20) === 1,
   'a mismatch at position 18 is inside the window');
ok(PrimerBlast::threePrimeMismatches('17AG1AG', 1, 20, 20) === 2,
   'two mismatches at 18 and 20 are both counted');
ok(PrimerBlast::threePrimeMismatches('15', 1, 15, 20) === 5,
   'an unaligned 3\' tail counts against the window — it is not a match');

group('PrimerPairs — amplification test uses POSITION, not just count');

// Same total mismatch count, opposite verdicts: position is what decides.
$five_prime = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+', 2, 0)],   // 2 mismatches, none near the 3' end
    'reverse' => [hit('chr1', 1500, 1519, '-', 0, 0)],
], $BIG);
$three_prime = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+', 2, 2)],   // same 2, both at the 3' end
    'reverse' => [hit('chr1', 1500, 1519, '-', 0, 0)],
], $BIG);
ok(PrimerPairs::isAmplifiable($five_prime['products'][0]),
   '2 mismatches away from the 3\' end still amplifies');
ok(!PrimerPairs::isAmplifiable($three_prime['products'][0]),
   'the SAME 2 mismatches at the 3\' end do not');

// Total-mismatch cutoff.
$many = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+', 6, 0)],
    'reverse' => [hit('chr1', 1500, 1519, '-', 0, 0)],
], $BIG);
ok(!PrimerPairs::isAmplifiable($many['products'][0]),
   '6 total mismatches is implausible regardless of position');

// Size still rules it out on its own.
$big = PrimerPairs::findProducts([
    'forward' => [hit('chr1', 1000, 1019, '+', 0, 0)],
    'reverse' => [hit('chr1', 9000, 9019, '-', 0, 0)],
], $BIG);
ok(!PrimerPairs::isAmplifiable($big['products'][0]),
   'a perfect-match product over 2 kb still does not amplify');

// ----------------------------------------------------------------------------
group('ExonMap — transcript coordinates to genomic blocks');

require_once "$BASE/lib/primer/ExonMap.php";

/** Blocks as a comparable string, so a failure prints what it actually got. */
function bstr($blocks) {
    $out = [];
    foreach ($blocks as list($s, $e)) { $out[] = "$s-$e"; }
    return implode(',', $out);
}

// A three-exon transcript, 35 nt total:
//   exon 1  100-109   transcript  1-10
//   exon 2  200-219   transcript 11-30
//   exon 3  300-304   transcript 31-35
$EXONS = [[100, 109], [200, 219], [300, 304]];
$plus  = ['chr' => 'chr1', 'strand' => '+', 'exons' => $EXONS];
$minus = ['chr' => 'chr1', 'strand' => '-', 'exons' => $EXONS];

ok(ExonMap::transcriptLength($plus) === 35, 'transcript length is the total exonic length');

// --- plus strand ---
ok(bstr(ExonMap::toGenomicBlocks($plus, 1, 10)) === '100-109',
   'a range filling exon 1 maps to one block');
ok(bstr(ExonMap::toGenomicBlocks($plus, 3, 7)) === '102-106',
   'a range inside one exon maps to one block, offset correctly');
ok(bstr(ExonMap::toGenomicBlocks($plus, 8, 13)) === '107-109,200-202',
   'a JUNCTION-SPANNING range splits into two blocks either side of the intron');
ok(bstr(ExonMap::toGenomicBlocks($plus, 29, 32)) === '218-219,300-301',
   'the second junction splits too');
ok(bstr(ExonMap::toGenomicBlocks($plus, 1, 35)) === '100-109,200-219,300-304',
   'the whole transcript maps back to exactly the exon list');

// --- minus strand: transcript position 1 is the HIGHEST genomic coordinate ---
ok(bstr(ExonMap::toGenomicBlocks($minus, 1, 5)) === '300-304',
   'on the minus strand transcript 1-5 is the LAST exon, not the first');
ok(bstr(ExonMap::toGenomicBlocks($minus, 1, 3)) === '302-304',
   'within a minus-strand exon the range runs right to left');
ok(bstr(ExonMap::toGenomicBlocks($minus, 4, 8)) === '217-219,300-301',
   'a minus-strand junction range splits, in ascending genomic order');
ok(bstr(ExonMap::toGenomicBlocks($minus, 26, 35)) === '100-109',
   'the END of a minus-strand transcript is the FIRST exon');
ok(bstr(ExonMap::toGenomicBlocks($minus, 1, 35)) === '100-109,200-219,300-304',
   'the whole minus-strand transcript also maps back to the exon list');

// The regression that matters: strand must actually be USED. The codebase has
// form here -- the wallaby work found a $strand computed and never read.
ok(ExonMap::toGenomicBlocks($plus, 3, 7) !== ExonMap::toGenomicBlocks($minus, 3, 7),
   'the SAME range maps somewhere different on the minus strand');

// --- containment: no picture beats a picture in the wrong place ---
ok(ExonMap::toGenomicBlocks($plus, 30, 40) === [],
   'a range running past the transcript maps to nothing, rather than being clipped');
ok(ExonMap::toGenomicBlocks($plus, 0, 5) === [],
   'a range starting before position 1 maps to nothing');
ok(bstr(ExonMap::toGenomicBlocks($plus, 13, 8)) === '107-109,200-202',
   'a reversed range is normalised — BLAST reports sstart > send on the minus strand');

// --- abutting exons are one box, not two ---
$abut = ['chr' => 'chr1', 'strand' => '+', 'exons' => [[100, 109], [110, 119]]];
ok(bstr(ExonMap::toGenomicBlocks($abut, 1, 20)) === '100-119',
   'exons with no intron between them merge into a single block');

ok(ExonMap::span(ExonMap::toGenomicBlocks($plus, 8, 13)) === [107, 202],
   'span() gives the outer region a browser link needs');
ok(ExonMap::span([]) === null, 'span() of nothing is null, not a bogus region');

// --- a hit's genomic strand is the transcript strand applied to it ---
ok(ExonMap::genomicStrand('+', '+') === '+' && ExonMap::genomicStrand('+', '-') === '-',
   'on a plus-strand transcript the hit strand is already genomic');
ok(ExonMap::genomicStrand('-', '+') === '-' && ExonMap::genomicStrand('-', '-') === '+',
   'on a MINUS-strand transcript both hit strands flip');

// --- mapProduct: the whole amplicon, both primers ---
$product = [
    'subject' => 'XM_1.1', 'start' => 5, 'end' => 33, 'size' => 29,
    'hits' => [
        ['start' => 5,  'end' => 12, 'strand' => '+', 'mismatch' => 0, 'primer' => 'forward'],
        ['start' => 26, 'end' => 33, 'strand' => '-', 'mismatch' => 1, 'primer' => 'reverse'],
    ],
];
$m = ExonMap::mapProduct($plus, $product);
ok($m !== null && $m['chr'] === 'chr1', 'a cDNA product maps onto its chromosome');
ok($m['span'] === [104, 302], 'the amplicon span is the outer edge of the mapped blocks');
ok($m['introns'] === 2, 'the amplicon is reported as crossing both introns');
ok($m['spliced'] === true, 'the product is flagged because a primer crosses a junction');
ok(bstr($m['hits'][0]['blocks']) === '104-109,200-201' && $m['hits'][0]['spliced'] === true,
   'the forward primer straddling the junction becomes two blocks');
ok(bstr($m['hits'][1]['blocks']) === '215-219,300-302' && $m['hits'][1]['spliced'] === true,
   'the reverse primer straddling the second junction becomes two blocks');
ok($m['hits'][1]['mismatch'] === 1, 'per-primer mismatch counts survive the mapping');

// Wholly within one exon: one block, and NOT flagged as spliced.
$inside = [
    'subject' => 'XM_1.1', 'start' => 12, 'end' => 28, 'size' => 17,
    'hits' => [
        ['start' => 12, 'end' => 16, 'strand' => '+', 'mismatch' => 0, 'primer' => 'forward'],
        ['start' => 24, 'end' => 28, 'strand' => '-', 'mismatch' => 0, 'primer' => 'reverse'],
    ],
];
$mi = ExonMap::mapProduct($plus, $inside);
ok($mi['spliced'] === false && $mi['introns'] === 0,
   'a product inside one exon is neither spliced nor intron-crossing');
ok(count($mi['hits'][0]['blocks']) === 1, 'a primer inside one exon stays one block');

// On the minus strand the primers keep their roles but flip genomic strand.
$mm = ExonMap::mapProduct($minus, $product);
ok($mm['hits'][0]['strand'] === '-' && $mm['hits'][1]['strand'] === '+',
   'both primers flip genomic strand on a minus-strand transcript');

// All or nothing: half a product drawn is worse than none.
$runs_off = [
    'subject' => 'XM_1.1', 'start' => 1, 'end' => 34, 'size' => 34,
    'hits' => [
        ['start' => 1,  'end' => 8,  'strand' => '+', 'mismatch' => 0, 'primer' => 'forward'],
        ['start' => 30, 'end' => 99, 'strand' => '-', 'mismatch' => 0, 'primer' => 'reverse'],
    ],
];
ok(ExonMap::mapProduct($plus, $runs_off) === null,
   'a product with one unmappable primer maps to nothing at all, not to half a picture');

// --- parsing ---
$row = ExonMap::parseRow("rna-XM_1.1\tNC_1\t-\t100,304\t100,109;200,219;300,304\n");
ok($row !== null && $row['strand'] === '-' && count($row['exons']) === 3,
   'a well-formed index row parses');
ok(ExonMap::parseRow("rna-XM_1.1\tNC_1\t+\t100,304\n") === null,
   'a truncated row is rejected outright, not parsed into half a transcript');
ok(ExonMap::parseRow("rna-XM_1.1\tNC_1\t+\t100,304\t100,109;200\n") === null,
   'a malformed exon span rejects the whole row');

// --- loading by id ---
$tmp = sys_get_temp_dir() . '/moop_exonmap_test_' . getmypid();
@mkdir($tmp, 0777, true);
file_put_contents($tmp . '/exon_coords.tsv',
    "rna-XM_1.1\tNC_1\t+\t100,304\t100,109;200,219;300,304\n" .
    "XM_1.1\tNC_1\t+\t100,304\t100,109;200,219;300,304\n" .
    "XM_2.1\tNC_1\t-\t900,999\t900,999\n");

$loaded = ExonMap::load($tmp, ['XM_1.1', 'XM_2.1']);
ok(count($loaded) === 2, 'both requested transcripts load in one pass');
ok(($loaded['XM_2.1']['strand'] ?? '') === '-', 'the right record came back for each id');
ok(ExonMap::load($tmp, ['XM_missing']) === [],
   'an id with no row is simply absent — the caller draws no link');
ok(ExonMap::load($tmp . '/nope', ['XM_1.1']) === [],
   'a gene set with no exon index yields nothing, and does not error');

// Ids are matched on the id FIELD only. "NC_1" appears in every line as the
// chromosome; a substring match would return another transcript's exons.
ok(ExonMap::load($tmp, ['NC_1']) === [],
   'a value that only appears in another column never matches');

@unlink($tmp . '/exon_coords.tsv');
@rmdir($tmp);

// ----------------------------------------------------------------------------
echo "\n" . str_repeat('-', 60) . "\n";
echo "Primer smoke tests: $PASS passed, $FAIL failed\n";
if ($FAIL > 0) {
    echo "FAILED:\n  - " . implode("\n  - ", $FAILURES) . "\n";
    exit(1);
}
echo "ALL PRIMER SMOKE TESTS PASSED\n";
exit(0);
