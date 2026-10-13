<?php
/**
 * Genomic regions -- a feature's locus, its upstream and downstream flanks -- defined ONCE.
 *
 * Three places hand out genomic sequence: the gene page (gene-model viewer), the Data
 * Exporter (moopmartStreamGenomicFasta) and Sequence Retrieval. The two PHP pages call
 * these functions; the gene page computes the same numbers in JS (gene-model-viewer.js,
 * fetchFlank) from its GFF model, so a change to the rules here must be made there too.
 *
 * WHERE A FEATURE SITS
 *   gene        feature_coords.tsv. EVERY row there carries the GENE's span -- the
 *               transcript rows too (all five NV2g012457000.1 isoforms read
 *               16003283-16019242, while their own spans differ). Right for a gene.
 *   transcript  exon_coords.tsv, column 4: the transcript's own span. feature_coords.tsv
 *               would silently give its gene's span instead.
 * Both files are written at JBrowse registration; a gene set without them (transcriptome-
 * only) has no genomic sequence to give.
 *
 * ORIENTATION
 *   Every sequence reads 5'->3' along the feature: a minus-strand region is reverse-
 *   complemented. Upstream is before the feature's 5' end in that direction, so on the
 *   minus strand it lies at HIGHER coordinates.
 */

require_once __DIR__ . '/blast_functions.php';   // extractFastaRegion(), reverseComplement()

/** Largest region one record may hold -- the same limit api/get_sequence.php applies. */
const MOOP_MAX_REGION_BP = 500000;

/** Largest flank, as the Data Exporter has always allowed. */
const MOOP_MAX_FLANK_BP = 100000;

/**
 * Locate genes and transcripts of one gene set.
 *
 * @param list<string> $ids gene or transcript IDs; anything else is simply not returned
 * @return array<string, array{chr: string, start: int, end: int, strand: string, kind: string}>
 */
function moop_feature_loci(string $gene_set_dir, array $ids): array {
    $want = array_flip($ids);
    $loci = [];
    if (!$want) return $loci;

    // Transcripts first: their own span, never their gene's.
    $fh = @fopen("$gene_set_dir/exon_coords.tsv", 'r');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            $tab = strpos($line, "\t");
            if ($tab === false || !isset($want[substr($line, 0, $tab)])) continue;
            $p = explode("\t", rtrim($line, "\r\n"));
            if (count($p) < 4) continue;
            [$s, $e] = array_map('intval', explode(',', $p[3]) + [1 => 0]);
            if ($s < 1 || $e < $s) continue;
            $loci[$p[0]] = ['chr' => $p[1], 'start' => $s, 'end' => $e, 'strand' => $p[2], 'kind' => 'transcript'];
        }
        fclose($fh);
    }

    // Genes: column 2 of any row holds the gene, and every row carries the gene's span.
    $fh = @fopen("$gene_set_dir/feature_coords.tsv", 'r');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            $p = explode("\t", rtrim($line, "\r\n"));
            if (count($p) < 6 || !isset($want[$p[1]]) || isset($loci[$p[1]])) continue;
            $loci[$p[1]] = ['chr' => $p[2], 'start' => (int)$p[3], 'end' => (int)$p[4], 'strand' => $p[5], 'kind' => 'gene'];
        }
        fclose($fh);
    }
    return $loci;
}

/**
 * The genomic coordinates for one part of a feature.
 *
 *   'body'        the feature itself; with $from/$to, only those positions of it,
 *                 counted from its 5' end (1 = first base, in the feature's direction)
 *   'upstream'    $bp bases before the 5' end
 *   'downstream'  $bp bases after the 3' end
 *
 * Flanks stop at the start of the sequence (position 1). They are NOT clipped at the end
 * of the contig here -- that needs the .fai -- and extractFastaRegion() stops there.
 *
 * @return array{chr: string, start: int, end: int, strand: string}|null null when the
 *         part is empty: a range starting past the feature's end, or a flank of 0 bases
 *         or one that would begin before position 1 entirely
 */
function moop_genomic_region(array $locus, string $part, int $bp = 0, ?int $from = null, ?int $to = null): ?array {
    $minus = $locus['strand'] === '-';
    [$s, $e] = [$locus['start'], $locus['end']];

    if ($part === 'body') {
        if ($from !== null) {
            $len = $e - $s + 1;
            if ($from > $len) return null;
            $to = min($to ?? $len, $len);
            [$s, $e] = $minus ? [$locus['end'] - $to + 1, $locus['end'] - $from + 1]
                              : [$locus['start'] + $from - 1, $locus['start'] + $to - 1];
        }
    } elseif ($part === 'upstream' || $part === 'downstream') {
        if ($bp < 1) return null;
        $before = ($part === 'upstream') !== $minus;   // lower coordinates?
        [$s, $e] = $before ? [max(1, $locus['start'] - $bp), $locus['start'] - 1]
                           : [$locus['end'] + 1, $locus['end'] + $bp];
    } else {
        return null;
    }
    if ($e < $s) return null;
    return ['chr' => $locus['chr'], 'start' => $s, 'end' => $e, 'strand' => $minus ? '-' : '+'];
}

/**
 * The bases of a region, 5'->3' along the feature. The returned region's end is clipped to
 * the contig, so a header built from it states what was actually extracted.
 *
 * @return array{seq: string, region: array}|null null if the contig is not in the genome
 */
function moop_genomic_sequence(string $assembly_dir, array $region): ?array {
    $fasta = "$assembly_dir/" . genome_fasta_filename();
    $fai   = "$fasta.fai";
    if (!is_file($fasta) || !is_file($fai)) return null;

    $seq = extractFastaRegion($fasta, $fai, $region['chr'], $region['start'], $region['end']);
    if ($seq === null || $seq === '') return null;
    $region['end'] = $region['start'] + strlen($seq) - 1;
    return ['seq' => $region['strand'] === '-' ? reverseComplement($seq) : $seq, 'region' => $region];
}

/**
 * One FASTA record, in the header format all genomic output shares:
 *   >ID[:from-to] genomic|upstream_500bp|downstream_500bp chr:start-end(strand)
 */
function moop_genomic_fasta_record(string $id, string $part, int $bp, array $region, string $seq, ?int $from = null, ?int $to = null): string {
    $label = $part === 'body' ? 'genomic' : "{$part}_{$bp}bp";
    $name  = $id . ($from !== null ? ":$from-$to" : '');
    return ">$name $label {$region['chr']}:{$region['start']}-{$region['end']}({$region['strand']})\n"
         . rtrim(chunk_split($seq, 60, "\n")) . "\n";
}

/**
 * Sequence Retrieval's genomic records: for every typed gene or transcript, its region
 * (or the typed range of it) and, when asked, its flanks -- each its own record, as the
 * gene page and the Data Exporter give them.
 *
 * @param list<array{id: string, start: ?int, end: ?int}> $requests parseFeatureIds()
 * @return array{fasta: string, notes: list<string>}
 */
function moop_retrieve_genomic(array $requests, string $gene_set_dir, string $assembly_dir, int $upstream = 0, int $downstream = 0): array {
    $out = ['fasta' => '', 'notes' => []];
    if (!$requests) return $out;
    if (!is_file("$gene_set_dir/feature_coords.tsv") || !is_file("$assembly_dir/" . genome_fasta_filename() . '.fai')) {
        $out['notes'][] = 'Genomic sequence is not available for this gene set: it has no genome coordinates.';
        return $out;
    }

    $loci   = moop_feature_loci($gene_set_dir, array_values(array_unique(array_column($requests, 'id'))));
    $done   = [];
    $toobig = [];
    foreach ($requests as $r) {
        $locus = $loci[$r['id']] ?? null;
        if (!$locus) continue;   // a CDS or protein ID, or not in this gene set
        $parts = [['body', 0, $r['start'], $r['end']]];
        // Flanks belong to the feature, not to a range of it: added once per ID.
        if (!isset($done[$r['id']])) {
            if ($upstream > 0)   $parts[] = ['upstream', $upstream, null, null];
            if ($downstream > 0) $parts[] = ['downstream', $downstream, null, null];
            $done[$r['id']] = true;
        }
        foreach ($parts as [$part, $bp, $from, $to]) {
            $region = moop_genomic_region($locus, $part, $bp, $from, $to);
            if (!$region) continue;
            if ($region['end'] - $region['start'] + 1 > MOOP_MAX_REGION_BP) { $toobig[] = $r['id']; continue; }
            $got = moop_genomic_sequence($assembly_dir, $region);
            if (!$got) continue;
            $shown_to = $from !== null ? $from + strlen($got['seq']) - 1 : null;
            $out['fasta'] .= moop_genomic_fasta_record($r['id'], $part, $bp, $got['region'], $got['seq'], $from, $shown_to);
        }
    }
    if ($toobig) {
        $out['notes'][] = 'Genomic sequence longer than ' . number_format(MOOP_MAX_REGION_BP) . ' bp is not shown here: '
                        . implode(', ', array_unique($toobig)) . '. The Data Exporter has no such limit.';
    }
    return $out;
}
