<?php
/**
 * Tests for the BLAST alignment formatter (lib/blast_results_visualizer.php).
 *
 * Plain assertions, no framework, hermetic -- same philosophy as tests/smoke_tests.php.
 * Exit 0 = all pass.
 *
 * What these exist to pin down: the coordinate arithmetic. Wrapping is easy to eyeball,
 * but the numbers at the ends of the lines are not, and they are wrong in ways nobody
 * reports -- a translated side advances 3 bases per residue, a gap advances none, and a
 * minus-strand hit counts DOWN. Each of those is one line of code and one silent bug.
 */

require_once __DIR__ . '/../lib/blast_results_visualizer.php';

$pass = 0;
$fail = 0;

function check($label, $expected, $actual) {
    global $pass, $fail;
    if ($expected === $actual) {
        $pass++;
        echo "  ok   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
        echo "       expected: " . var_export($expected, true) . "\n";
        echo "       actual:   " . var_export($actual, true) . "\n";
    }
}

/** Pull the trailing coordinate off every Query/Sbjct line. */
function coords($text, $label) {
    $out = [];
    foreach (explode("\n", $text) as $line) {
        if (strpos($line, $label) === 0 && preg_match('/\s(\d+)$/', $line, $m)) {
            $out[] = (int)$m[1];
        }
    }
    return $out;
}

/** Pull the leading coordinate off every Query/Sbjct line. */
function starts($text, $label) {
    $out = [];
    foreach (explode("\n", $text) as $line) {
        if (strpos($line, $label) === 0 && preg_match('/^\S+\s+(\d+)\s/', $line, $m)) {
            $out[] = (int)$m[1];
        }
    }
    return $out;
}

echo "\n--- per-residue steps come from the PROGRAM, not the frame ---\n";
check('blastn  query step', 1, blastAlignmentResidueSteps('blastn')['query']);
check('blastn  hit step',   1, blastAlignmentResidueSteps('blastn')['hit']);
check('blastp  query step', 1, blastAlignmentResidueSteps('blastp')['query']);
check('blastx  query step', 3, blastAlignmentResidueSteps('blastx')['query']);
check('blastx  hit step',   1, blastAlignmentResidueSteps('blastx')['hit']);
check('tblastn query step', 1, blastAlignmentResidueSteps('tblastn')['query']);
check('tblastn hit step',   3, blastAlignmentResidueSteps('tblastn')['hit']);
check('tblastx query step', 3, blastAlignmentResidueSteps('tblastx')['query']);
check('tblastx hit step',   3, blastAlignmentResidueSteps('tblastx')['hit']);
// The trap: blastn reports frames of +/-1 but is NOT translated. Keying the step off
// "frame != 0" triples every blastn coordinate.
check('blastn-short stays 1', 1, blastAlignmentResidueSteps('blastn-short')['query']);
check('unknown program defaults to 1', 1, blastAlignmentResidueSteps('nonsense')['query']);

echo "\n--- wrapping ---\n";
$seq150 = str_repeat('A', 150);
$hsp = [
    'query_seq' => $seq150, 'hit_seq' => $seq150, 'midline' => $seq150,
    'query_from' => 1, 'query_to' => 150, 'hit_from' => 1, 'hit_to' => 150,
];
$out = formatBlastAlignment($hsp, 'blastp');
check('150 residues -> 3 blocks of Query', 3, count(coords($out, 'Query')));
check('block end coordinates', [60, 120, 150], coords($out, 'Query'));
check('block start coordinates', [1, 61, 121], starts($out, 'Query'));
$longest = max(array_map('strlen', explode("\n", $out)));
check('no line exceeds ~60 + label overhead', true, $longest <= 80);

echo "\n--- a short HSP still renders as a single block (the 86aa case) ---\n";
$seq86 = str_repeat('M', 86);
$short = [
    'query_seq' => $seq86, 'hit_seq' => $seq86, 'midline' => $seq86,
    'query_from' => 18, 'query_to' => 103, 'hit_from' => 18, 'hit_to' => 103,
];
$out = formatBlastAlignment($short, 'blastp');
$short_coords = coords($out, 'Query');
check('86 residues -> 2 blocks (60 + 26)', 2, count($short_coords));
check('ends at 103', 103, end($short_coords));

echo "\n--- gaps consume no coordinate ---\n";
$gapped = [
    'query_seq' => 'AAAAA-----AAAAA',   // 10 residues, 5 gaps
    'hit_seq'   => 'AAAAAAAAAAAAAAA',   // 15 residues
    'midline'   => 'AAAAA     AAAAA',
    'query_from' => 1, 'query_to' => 10, 'hit_from' => 1, 'hit_to' => 15,
];
$out = formatBlastAlignment($gapped, 'blastp');
check('query ends at 10, not 15', [10], coords($out, 'Query'));
check('subject ends at 15',       [15], coords($out, 'Sbjct'));

echo "\n--- translated sides advance 3 bases per residue ---\n";
$tx = [
    'query_seq' => str_repeat('M', 60), 'hit_seq' => str_repeat('M', 60),
    'midline'   => str_repeat('M', 60),
    'query_from' => 1, 'query_to' => 180, 'hit_from' => 1, 'hit_to' => 60,
    'query_frame' => 1, 'hit_frame' => 0,
];
$out = formatBlastAlignment($tx, 'blastx');
check('blastx query 60 residues -> ends at 180', [180], coords($out, 'Query'));
check('blastx subject stays nucleotide-free -> 60', [60], coords($out, 'Sbjct'));

// The same HSP under blastn must NOT triple, even though frames are non-zero.
$nt = $tx;
$nt['query_to'] = 60;
$nt['query_frame'] = 1;
$nt['hit_frame'] = 1;
$out = formatBlastAlignment($nt, 'blastn');
check('blastn with frame=1 does NOT triple', [60], coords($out, 'Query'));

echo "\n--- minus strand counts DOWN ---\n";
$minus = [
    'query_seq' => str_repeat('A', 60), 'hit_seq' => str_repeat('A', 60),
    'midline'   => str_repeat('A', 60),
    'query_from' => 1, 'query_to' => 60,
    'hit_from'   => 500, 'hit_to' => 441,      // to < from
];
$out = formatBlastAlignment($minus, 'blastn');
check('descending subject ends at 441', [441], coords($out, 'Sbjct'));
check('ascending query still ends at 60', [60], coords($out, 'Query'));

// Negative frame is the other way NCBI signals it.
$negframe = $minus;
$negframe['hit_from'] = 500;
$negframe['hit_to']   = 441;
$negframe['hit_frame'] = -1;
$out = formatBlastAlignment($negframe, 'blastn');
check('negative frame also descends', [441], coords($out, 'Sbjct'));

echo "\n--- multi-block minus strand keeps descending across blocks ---\n";
$minus2 = [
    'query_seq' => str_repeat('A', 120), 'hit_seq' => str_repeat('A', 120),
    'midline'   => str_repeat('A', 120),
    'query_from' => 1, 'query_to' => 120,
    'hit_from'   => 500, 'hit_to' => 381,
];
$out = formatBlastAlignment($minus2, 'blastn');
check('two descending blocks', [441, 381], coords($out, 'Sbjct'));
check('second block starts at 440', [500, 440], starts($out, 'Sbjct'));

echo "\n--- degenerate input does not fatal ---\n";
check('empty sequences -> empty string', '', formatBlastAlignment([], 'blastn'));
check('missing midline still renders', true,
    strpos(formatBlastAlignment([
        'query_seq' => 'AAA', 'hit_seq' => 'AAA',
        'query_from' => 1, 'query_to' => 3, 'hit_from' => 1, 'hit_to' => 3,
    ], 'blastn'), 'Query') === 0);

echo "\n";
echo "passed: $pass   failed: $fail\n";
exit($fail === 0 ? 0 : 1);
