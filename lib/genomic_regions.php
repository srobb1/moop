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
require_once __DIR__ . '/fasta_index.php';       // moop_fai_lookup()
require_once __DIR__ . '/feature_coords.php';    // feature_coords.tsv lookups
require_once __DIR__ . '/parent_functions.php';   // moop_hierarchy_walk()

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
    $genes = array_keys(array_diff_key($want, $loci));
    foreach (moop_feature_coords_by_gene($gene_set_dir, $genes) as $gene => $locus) {
        $loci[$gene] = $locus + ['kind' => 'gene'];
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
 * Open an assembly's genome for a batch of cuts: the .fai entries for these sequence names,
 * looked up in ONE pass, and the FASTA held open. Cutting each region through
 * extractFastaRegion() re-read the .fai from the top every time -- 5.7 ms a region on
 * Carollia's 1.9 M-line index, once per gene and once per EXON in the Data Exporter.
 * (samtools faidx loads its index once too; this is the same idea.)
 *
 * @param list<string> $chrs every sequence name the batch will cut from
 * @return array{fh: resource, fai: array<string, array{len: int, off: int, lb: int, lw: int}>}|null
 *         null when the genome or its .fai is missing
 */
function moop_genome_open(string $assembly_dir, array $chrs): ?array {
    $fasta = "$assembly_dir/" . genome_fasta_filename();
    if (!is_file($fasta) || !is_file("$fasta.fai")) return null;
    $chrs = array_values(array_unique(array_filter(array_map('strval', $chrs), 'strlen')));
    $fh   = fopen($fasta, 'rb');
    if ($fh === false) return null;
    return ['fh' => $fh, 'fai' => $chrs ? moop_fai_lookup("$fasta.fai", $chrs) : []];
}

function moop_genome_close(?array $genome): void {
    if ($genome && is_resource($genome['fh'])) fclose($genome['fh']);
}

/** Of these names, the ones the opened genome does not contain (see moop_genome_missing_seqnames()). */
function moop_genome_missing(array $genome, array $chrs): array {
    return array_values(array_unique(array_filter(array_map('strval', $chrs),
        fn($c) => $c !== '' && !isset($genome['fai'][$c]))));
}

/**
 * The bases of a region, 5'->3' along the feature. The returned region's end is clipped to
 * the contig, so a header built from it states what was actually extracted.
 *
 * @param array $genome from moop_genome_open(), whose $chrs included this region's
 * @return array{seq: string, region: array}|null null if the contig is not in the genome
 */
function moop_genomic_sequence(array $genome, array $region): ?array {
    $entry = $genome['fai'][$region['chr']] ?? null;
    if (!$entry) return null;
    $seq = moop_fasta_read_at($genome['fh'], $entry, $region['start'], $region['end']);
    if ($seq === null || $seq === '') return null;
    $region['end'] = $region['start'] + strlen($seq) - 1;
    return ['seq' => $region['strand'] === '-' ? reverseComplement($seq) : $seq, 'region' => $region];
}

/**
 * Of these sequence names, the ones this assembly's genome does not contain.
 *
 * A gene set and its genome can name the same chromosome differently (Ensembl "2L" vs RefSeq
 * "NC_004354.4"). Every cut is then empty, and without this check the exports said nothing:
 * Drosophila's genomic FASTA came back as an empty file with HTTP 200 (2026-10-21; five gene
 * sets were affected). Checked once per batch -- one pass over the .fai.
 *
 * @param list<string> $chrs sequence names from the gene set's coordinates
 * @return list<string> the names missing from genome.fa.fai, in first-seen order
 */
function moop_genome_missing_seqnames(string $assembly_dir, array $chrs): array {
    $chrs = array_values(array_unique(array_filter(array_map('strval', $chrs), 'strlen')));
    if (!$chrs) return [];
    $found = moop_fai_lookup("$assembly_dir/" . genome_fasta_filename() . '.fai', $chrs);
    return array_values(array_filter($chrs, fn($c) => !isset($found[$c])));
}

/**
 * Does this gene set name its chromosomes the way its genome does? One pass over
 * feature_coords.tsv and one over the .fai -- for the organism cache, never a page load.
 *
 * @return array{missing: int, total: int, examples: list<string>}|null null when there is
 *         nothing to compare (no coordinates, or no genome index)
 */
function moop_gene_set_seqname_gap(string $gene_set_dir, string $assembly_dir): ?array {
    $chrs = moop_feature_coords_seqnames($gene_set_dir);
    if ($chrs === null || !is_file("$assembly_dir/" . genome_fasta_filename() . '.fai')) return null;
    $missing = moop_genome_missing_seqnames($assembly_dir, $chrs);
    return ['missing' => count($missing), 'total' => count($chrs), 'examples' => array_slice($missing, 0, 3)];
}

/**
 * Why features got no genomic sequence, as notes for the person who asked -- shared by the
 * Data Exporter and Sequence Retrieval so both say the same thing.
 *
 * @param array{no_coords?: list<string>, missing_chr?: array<string, list<string>>, no_exons?: list<string>} $skips
 * @return list<string>
 */
function moop_genomic_skip_notes(array $skips): array {
    $eg    = fn(array $ids) => ' (e.g. ' . implode(', ', array_slice($ids, 0, 3)) . (count($ids) > 3 ? ', …' : '') . ')';
    $n     = fn(int $k, string $one) => number_format($k) . ' ' . $one . ($k === 1 ? '' : 's');
    $notes = [];
    if (!empty($skips['missing_chr'])) {
        $ids   = array_merge(...array_values($skips['missing_chr']));
        $names = array_keys($skips['missing_chr']);
        $one   = count($names) === 1;
        $notes[] = $n(count($ids), 'feature') . ' skipped: ' . ($one ? 'sequence' : 'sequences')
                 . " '" . implode("', '", array_slice($names, 0, 3)) . "'" . (count($names) > 3 ? ' and ' . (count($names) - 3) . ' more' : '')
                 . ($one ? ' is' : ' are') . " not in this assembly's genome -- the gene set and the genome name the chromosomes differently"
                 . $eg($ids) . '.';
    }
    if (!empty($skips['no_coords'])) {
        $notes[] = $n(count($skips['no_coords']), 'feature') . ' skipped: no genome coordinates in this gene set'
                 . $eg($skips['no_coords']) . '.';
    }
    if (!empty($skips['no_exons'])) {
        $notes[] = $n(count($skips['no_exons']), 'feature') . ' skipped: no exons found for them in the gene set\'s GFF'
                 . $eg($skips['no_exons']) . '.';
    }
    return $notes;
}

/**
 * One FASTA record, in the header format all genomic output shares:
 *   >ID[:from-to] [gene=G transcript=T] genomic|upstream_500bp|downstream_500bp chr:start-end(strand)
 * $context names the gene (and transcript) a record belongs to when the ID alone does not
 * say -- Sequence Retrieval, where several typed IDs can share one output.
 */
function moop_genomic_fasta_record(string $id, string $part, int $bp, array $region, string $seq, ?int $from = null, ?int $to = null, string $context = ''): string {
    $label = $part === 'body' ? 'genomic' : "{$part}_{$bp}bp";
    $name  = $id . ($from !== null ? ":$from-$to" : '');
    $ctx   = $context !== '' ? " $context" : '';
    return ">$name$ctx $label {$region['chr']}:{$region['start']}-{$region['end']}({$region['strand']})\n"
         . rtrim(chunk_split($seq, MOOP_FASTA_LINE_WIDTH, "\n")) . "\n";
}

/**
 * Which feature's span each typed ID's genomic sequence is cut from, and the gene it belongs to.
 *
 *   gene        its own span
 *   transcript  its own span (introns included) -- NOT its gene's: isoforms with different
 *               start sites have different upstream sequence
 *   CDS/protein its transcript's span. A protein has no genomic span of its own worth
 *               cutting; "upstream of this protein" means upstream of its isoform's start.
 *
 * The transcript is the nearest ancestor (or the ID itself) that is not a CDS, protein or
 * exon; the gene is the top of the chain. No list of transcript types, so lncRNA, tRNA etc.
 * work, and a bacterial protein -> CDS -> gene chain anchors on the gene.
 *
 * @param int[] $gene_set_ids scope -- a uniquename is unique only within one gene set
 * @return array<string, array{locus: string, gene: string, gene_name: ?string, transcript: ?string}> IDs not in
 *         the database are absent
 */
function moop_genomic_anchors(array $ids, string $db_path, array $gene_set_ids = []): array {
    $walk = [];
    // In batches: SQLite caps the parameters one query may bind (32,766 here), and a whole
    // gene set's proteins (61k for Myotis myotis) is over it -- the query failed outright.
    foreach (array_chunk(array_values(array_unique($ids)), 5000) as $batch) {
        foreach (moop_hierarchy_walk($batch, $db_path, 'up', $gene_set_ids) as $row) {
            $walk[(string)$row['seed_name']][(int)$row['depth']] = $row;
        }
    }
    $sub = ['cds' => 1, 'protein' => 1, 'polypeptide' => 1, 'exon' => 1];
    $out = [];
    foreach ($walk as $id => $chain) {
        ksort($chain);
        $top   = end($chain);
        $gene  = (string)$top['feature_uniquename'];
        $locus = $gene;
        foreach ($chain as $row) {
            if (!isset($sub[strtolower((string)$row['feature_type'])])) { $locus = (string)$row['feature_uniquename']; break; }
        }
        // A RefSeq gene's uniquename is a bare GeneID (118665088); its name (PIGG) is what a
        // reader recognises. Only a real name: one word, and not just another of its IDs.
        $name = trim((string)($top['feature_name'] ?? ''));
        if ($name === '' || preg_match('/\s/', $name) || $name === $gene || $name === $locus || $name === $id) $name = null;
        $out[$id] = ['locus' => $locus, 'gene' => $gene, 'gene_name' => $name, 'transcript' => $locus !== $gene ? $locus : null];
    }
    return $out;
}

/**
 * Sequence Retrieval's genomic records: for every typed ID, its region (or the typed range
 * of it) and, when asked, its flanks -- each its own record, as the gene page and the Data
 * Exporter give them. Where the region comes from is moop_genomic_anchors().
 *
 * A range typed on a protein or CDS is in ITS coordinates (amino acids, CDS bases), which
 * say nothing about where on the genome that is -- so such a request gets its flanks but no
 * cut region, and a note says why, rather than a cut that looks right and is not.
 *
 * @param list<array{id: string, start: ?int, end: ?int}> $requests parseFeatureIds()
 * @param array    $anchors moop_genomic_anchors(); empty = every ID is its own locus
 * @param callable $emit    optional: given each record as it is cut, instead of collecting them
 *                          in 'fasta' -- a download then holds one region at a time (a region
 *                          runs to 500 kb, so a batch of them collected was up to ~300 MB)
 * @return array{fasta: string, notes: list<string>}
 */
function moop_retrieve_genomic(array $requests, string $gene_set_dir, string $assembly_dir, int $upstream = 0, int $downstream = 0, array $anchors = [], ?callable $emit = null): array {
    $out = ['fasta' => '', 'notes' => []];
    if (!$requests) return $out;
    if (!is_file("$gene_set_dir/feature_coords.tsv") || !is_file("$assembly_dir/" . genome_fasta_filename() . '.fai')) {
        $out['notes'][] = 'Genomic sequence is not available for this gene set: it has no genome coordinates.';
        return $out;
    }

    $anchor_of = fn(string $id) => $anchors[$id]['locus'] ?? $id;
    $loci    = moop_feature_loci($gene_set_dir, array_values(array_unique(array_map($anchor_of, array_column($requests, 'id')))));
    $genome  = moop_genome_open($assembly_dir, array_column($loci, 'chr'));
    if (!$genome) {
        $out['notes'][] = 'Genomic sequence is not available: this assembly has no readable genome.';
        return $out;
    }
    $missing  = array_flip(moop_genome_missing($genome, array_column($loci, 'chr')));
    $skips    = [];
    $done     = [];
    $toobig   = [];
    $no_range = [];
    foreach ($requests as $r) {
        $id     = $r['id'];
        $anchor = $anchors[$id] ?? null;
        $locus  = $loci[$anchor_of($id)] ?? null;
        if (!$locus) {
            // Not in the database: already shown red in the ID box, nothing to add. In it but
            // not on the genome: say so -- this used to give nothing, silently.
            if (($anchor || !$anchors) && !in_array($id, $skips['no_coords'] ?? [], true)) $skips['no_coords'][] = $id;
            continue;
        }
        if (isset($missing[$locus['chr']])) {
            if (!in_array($id, $skips['missing_chr'][$locus['chr']] ?? [], true)) $skips['missing_chr'][$locus['chr']][] = $id;
            continue;
        }
        $context = '';
        if ($anchor && $anchor['gene'] !== $id) {
            $context = 'gene=' . $anchor['gene']
                     . (($anchor['gene_name'] ?? null) !== null ? ' gene_name=' . $anchor['gene_name'] : '')
                     . ($anchor['transcript'] !== null && $anchor['transcript'] !== $id ? ' transcript=' . $anchor['transcript'] : '');
        }
        $parts = [];
        if ($r['start'] !== null && $anchor_of($id) !== $id) {
            $no_range[] = $id;
        } else {
            $parts[] = ['body', 0, $r['start'], $r['end']];
        }
        // Flanks belong to the feature, not to a range of it: added once per ID.
        if (!isset($done[$id])) {
            if ($upstream > 0)   $parts[] = ['upstream', $upstream, null, null];
            if ($downstream > 0) $parts[] = ['downstream', $downstream, null, null];
            $done[$id] = true;
        }
        foreach ($parts as [$part, $bp, $from, $to]) {
            $region = moop_genomic_region($locus, $part, $bp, $from, $to);
            if (!$region) continue;
            if ($region['end'] - $region['start'] + 1 > MOOP_MAX_REGION_BP) { $toobig[] = $id; continue; }
            $got = moop_genomic_sequence($genome, $region);
            if (!$got) continue;
            $shown_to = $from !== null ? $from + strlen($got['seq']) - 1 : null;
            $record = moop_genomic_fasta_record($id, $part, $bp, $got['region'], $got['seq'], $from, $shown_to, $context);
            if ($emit) $emit($record); else $out['fasta'] .= $record;
        }
    }
    moop_genome_close($genome);
    array_push($out['notes'], ...moop_genomic_skip_notes($skips));
    if ($no_range) {
        $out['notes'][] = 'A range on a protein or CDS ID counts amino acids or coding bases, not genome positions, so no genomic region was cut for: '
                        . implode(', ', array_unique($no_range)) . '. Its flanks are given; for a genomic range, use the transcript ID.';
    }
    if ($toobig) {
        $out['notes'][] = 'Genomic sequence longer than ' . number_format(MOOP_MAX_REGION_BP) . ' bp is not shown here: '
                        . implode(', ', array_unique($toobig)) . '. The Data Exporter has no such limit.';
    }
    return $out;
}
