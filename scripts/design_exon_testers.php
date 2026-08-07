<?php
/**
 * Generate TEST FIXTURE primer pairs with known exon behaviour.
 *
 * ⚠️ This is a test-fixture generator, NOT a primer design tool. It applies only
 * the crudest oligo checks (length, GC, Wallace Tm, homopolymer run, 3' clamp)
 * and it does NOT check specificity — it never runs BLAST. Primers it emits can
 * and do land in paralog families. Real primer design is Primer3's job and is
 * planned as a separate page; do not let this grow into it.
 *
 * What it IS good for: producing pairs whose exon behaviour is known BEFORE the
 * tool runs, because each primer is PLACED from exon_coords.tsv rather than
 * found by searching. That makes them usable as expected-answer fixtures for
 * Primer BLAST's cDNA-to-genome mapping (lib/primer/ExonMap.php).
 *
 * Four categories, each exercising a different path through the tool:
 *
 *   junction_plus   forward primer straddles a junction on a plus-strand gene
 *                   → cDNA product, NO genomic product, "junction primer" badge
 *   junction_minus  the same on a MINUS-strand gene — the reversed mapping, and
 *                   the case most likely to be silently wrong
 *   intron_span     both primers internal but in different exons
 *                   → both products, "Spans N introns", no junction badge
 *   single_exon     both primers inside one exon — the negative control
 *                   → cDNA and gDNA the same size, "No intron between the primers"
 *
 * Always CHECK the output against the tool before trusting a pair as a fixture:
 * a category is a prediction until the page agrees with it.
 *
 * Usage:
 *   php scripts/design_exon_testers.php organisms/Org/Assembly/GeneSet [--fasta]
 */

$BASE = dirname(__DIR__);
require_once $BASE . '/lib/primer/ExonMap.php';

$gene_set = $argv[1] ?? '';
$as_fasta = in_array('--fasta', $argv, true);

if ($gene_set === '') {
    fwrite(STDERR, "Usage: php scripts/design_exon_testers.php <gene-set-path> [--fasta]\n");
    exit(1);
}
if ($gene_set[0] !== '/') {
    $gene_set = $BASE . '/' . $gene_set;
}
$gene_set = rtrim($gene_set, '/');

$index      = $gene_set . '/' . ExonMap::FILENAME;
$transcript = $gene_set . '/transcript.nt.fa';

foreach ([$index, $transcript, $transcript . '.fai'] as $needed) {
    if (!is_readable($needed)) {
        fwrite(STDERR, "Missing or unreadable: $needed\n");
        if ($needed === $index) {
            fwrite(STDERR, "Build it from Admin → Manage BLAST Linkouts, or:\n"
                         . "  php scripts/backfill_exon_coords.php --only=" . basename(dirname(dirname($gene_set))) . "\n");
        }
        exit(1);
    }
}

const PRIMER_LEN = 20;

function faidx($file, $region) {
    $out = [];
    exec('samtools faidx ' . escapeshellarg($file) . ' ' . escapeshellarg($region) . ' 2>/dev/null', $out);
    array_shift($out);
    return strtoupper(implode('', $out));
}
function revcomp($s) { return strrev(strtr($s, 'ACGT', 'TGCA')); }
function gc_pct($s) { return (substr_count($s, 'G') + substr_count($s, 'C')) / strlen($s) * 100; }

/**
 * Wallace rule: 2°C per A/T, 4°C per G/C — the standard estimate at this length.
 *
 * NOT the salt-adjusted 64.9 + 41*(GC-16.4)/N formula, which is for longer
 * oligos: on a 20-mer that one returns 48-56°C across the entire 40-60% GC band,
 * so combining it with a 56-64°C window accepts nothing at all. (It did exactly
 * that here, and the generator silently produced no output.)
 */
function tm_wallace($s) {
    return 2 * (substr_count($s, 'A') + substr_count($s, 'T'))
         + 4 * (substr_count($s, 'G') + substr_count($s, 'C'));
}

function usable($s) {
    if (strlen($s) !== PRIMER_LEN)                 return false;
    if (preg_match('/[^ACGT]/', $s))               return false;
    if (preg_match('/(A{5}|C{5}|G{5}|T{5})/', $s)) return false;
    $g = gc_pct($s);
    if ($g < 40 || $g > 60)                        return false;
    if (!preg_match('/[GC]$/', $s))                return false;   // 3' GC clamp
    return true;
}

/** Transcript coordinate ranges of each exon, in transcript order. */
function exon_bounds(array $rec) {
    $walk   = ($rec['strand'] === '-') ? array_reverse($rec['exons']) : $rec['exons'];
    $bounds = [];
    $off    = 0;
    foreach ($walk as list($s, $e)) {
        $len      = $e - $s + 1;
        $bounds[] = [$off + 1, $off + $len];
        $off     += $len;
    }
    return $bounds;
}

// ---------------------------------------------------------------- candidates
$fh    = fopen($index, 'r');
$cands = ['+' => [], '-' => []];
while (($line = fgets($fh)) !== false) {
    $r = ExonMap::parseRow($line);
    if (!$r) continue;
    // Bare accessions only: those are what the transcript FASTA carries, and the
    // index writes both forms, so taking both would design the same pair twice.
    if (preg_match('/^(rna|gene|id|cds)-/', $r['transcript_id'])) continue;
    if (count($r['exons']) < 3) continue;
    foreach ($r['exons'] as list($s, $e)) {
        if ($e - $s + 1 < 60) continue 2;    // room for a 20-mer well inside
    }
    if (count($cands[$r['strand']]) < 400) {
        $cands[$r['strand']][] = $r;
    }
    if (count($cands['+']) >= 400 && count($cands['-']) >= 400) break;
}
fclose($fh);

$designs = [];

// ------------------------------------------- junction primers, both strands
foreach (['+' => 'plus', '-' => 'minus'] as $strand => $word) {
    foreach ($cands[$strand] as $rec) {
        $len = ExonMap::transcriptLength($rec);
        $seq = faidx($transcript, $rec['transcript_id']);
        if (strlen($seq) !== $len) continue;   // FASTA disagrees: skip, do not guess

        $bounds = exon_bounds($rec);
        foreach (array_slice($bounds, 0, -1) as $bd) {
            $j    = $bd[1];                    // last transcript base of this exon
            $half = (int)(PRIMER_LEN / 2);
            if ($j < $half || $j + $half > $len) continue;

            $f = substr($seq, $j - $half, PRIMER_LEN);
            if (!usable($f)) continue;
            if (count(ExonMap::toGenomicBlocks($rec, $j - $half + 1, $j + $half)) !== 2) continue;

            for ($rs = $j + 180; $rs + PRIMER_LEN <= min($len, $j + 700); $rs += 7) {
                $r = revcomp(substr($seq, $rs, PRIMER_LEN));
                if (!usable($r) || abs(tm_wallace($f) - tm_wallace($r)) > 4) continue;
                // The reverse primer must NOT also cross a junction: one variable
                // at a time, or the fixture tests two things and pins neither.
                if (count(ExonMap::toGenomicBlocks($rec, $rs + 1, $rs + PRIMER_LEN)) !== 1) continue;

                $designs["junction_$word"] = [
                    'transcript' => $rec['transcript_id'],
                    'strand'     => $strand,
                    'forward'    => $f,
                    'reverse'    => $r,
                    'product'    => $rs + PRIMER_LEN - ($j - $half),
                    'expect'     => 'cDNA product only, NO genomic product, "junction primer" badge',
                ];
                break 3;
            }
        }
    }
}

// ------------------------------- intron-spanning (no junction) and control
foreach ($cands['+'] as $rec) {
    if (isset($designs['intron_span'], $designs['single_exon'])) break;

    $len = ExonMap::transcriptLength($rec);
    $seq = faidx($transcript, $rec['transcript_id']);
    if (strlen($seq) !== $len) continue;
    $bounds = exon_bounds($rec);

    if (!isset($designs['intron_span']) && count($bounds) >= 3) {
        for ($a = $bounds[0][0] + 5; $a + PRIMER_LEN <= $bounds[0][1] - 5; $a += 3) {
            $f = substr($seq, $a - 1, PRIMER_LEN);
            if (!usable($f)) continue;
            for ($b = $bounds[2][0] + 5; $b + PRIMER_LEN <= $bounds[2][1] - 5; $b += 3) {
                $r = revcomp(substr($seq, $b - 1, PRIMER_LEN));
                if (!usable($r) || abs(tm_wallace($f) - tm_wallace($r)) > 4) continue;
                if (count(ExonMap::toGenomicBlocks($rec, $a, $a + PRIMER_LEN - 1)) !== 1) continue;
                if (count(ExonMap::toGenomicBlocks($rec, $b, $b + PRIMER_LEN - 1)) !== 1) continue;
                $designs['intron_span'] = [
                    'transcript' => $rec['transcript_id'], 'strand' => '+',
                    'forward' => $f, 'reverse' => $r, 'product' => $b + PRIMER_LEN - $a,
                    'expect'  => 'both products, gDNA larger, "Spans 2 introns", no junction badge',
                ];
                break 2;
            }
        }
    }

    if (!isset($designs['single_exon'])) {
        foreach ($bounds as $bd) {
            if ($bd[1] - $bd[0] + 1 < 200) continue;
            for ($a = $bd[0] + 5; $a + PRIMER_LEN <= $bd[1] - 120; $a += 3) {
                $f = substr($seq, $a - 1, PRIMER_LEN);
                if (!usable($f)) continue;
                for ($b = $a + 100; $b + PRIMER_LEN <= $bd[1] - 5; $b += 3) {
                    $r = revcomp(substr($seq, $b - 1, PRIMER_LEN));
                    if (!usable($r) || abs(tm_wallace($f) - tm_wallace($r)) > 4) continue;
                    $designs['single_exon'] = [
                        'transcript' => $rec['transcript_id'], 'strand' => '+',
                        'forward' => $f, 'reverse' => $r, 'product' => $b + PRIMER_LEN - $a,
                        'expect'  => 'cDNA and gDNA the SAME size, "No intron between the primers"',
                    ];
                    break 3;
                }
            }
        }
    }
}

// --------------------------------------------------------------- report out
if (empty($designs)) {
    fwrite(STDERR, "No usable fixtures found in this gene set.\n");
    exit(1);
}

if ($as_fasta) {
    foreach ($designs as $name => $d) {
        echo ">{$name}_F\n{$d['forward']}\n>{$name}_R\n{$d['reverse']}\n";
    }
    exit(0);
}

foreach ($designs as $name => $d) {
    printf("%-15s %s (%s strand)   expected product %d bp\n",
        $name, $d['transcript'], $d['strand'] === '-' ? 'minus' : 'plus', $d['product']);
    printf("    F %s   GC %2.0f%%  Tm %d\n", $d['forward'], gc_pct($d['forward']), tm_wallace($d['forward']));
    printf("    R %s   GC %2.0f%%  Tm %d\n", $d['reverse'], gc_pct($d['reverse']), tm_wallace($d['reverse']));
    printf("    expect: %s\n\n", $d['expect']);
}
echo "Re-run with --fasta to paste straight into Primer BLAST.\n";
echo "⚠️ Specificity is NOT checked — confirm each pair on the page before using it as a fixture.\n";
