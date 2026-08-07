<?php
/**
 * ExonMap - map a range on a TRANSCRIPT back to the genomic blocks it occupies.
 *
 * WHY THIS EXISTS: a primer designed across an exon-exon junction has no
 * continuous match in the genome, which is the whole point of designing it that
 * way -- it cannot amplify contaminating gDNA. But it also means the primer's
 * only BLAST hit is on the transcript, and a transcript coordinate cannot be
 * shown in a genome browser. Without this class such a primer gets no Browser
 * link at all, and the picture that would explain WHY it is a good primer is
 * exactly the picture the user never sees.
 *
 * Mapped through the exon structure, the same hit becomes TWO genomic blocks,
 * which JBrowse's segments glyph draws as two boxes either side of the intron.
 *
 * The exon structure comes from {gene_set}/exon_coords.tsv, written at gene-set
 * registration by generateExonCoordsIndex() (lib/jbrowse/gene_set_functions.php):
 *
 *     transcript_id \t chr \t strand \t outerStart,outerEnd \t s1,e1;s2,e2;...
 *
 * with exon spans in ASCENDING genomic order, 1-based and inclusive, regardless
 * of strand. Reading the GFF per request instead was measured and rejected:
 * Nematostella's genes.gff is 240 MB and costs about a second per lookup.
 *
 * @package MOOP\Primer
 */

class ExonMap
{
    /** Name of the index file inside a gene-set directory. */
    const FILENAME = 'exon_coords.tsv';

    /**
     * Read the exon structure for a specific set of transcripts.
     *
     * ONE streaming pass whatever the number of ids, because the file is large
     * (18 MB / 106,708 rows for Nematostella RS_101) and a page asks about a
     * handful of subjects. Nothing is cached in memory across requests: the
     * whole reason the flat file exists is to keep this cheap enough not to need
     * that.
     *
     * Ids are matched EXACTLY. The index already writes both the GFF form
     * ("rna-XM_001635385.3") and the bare accession ("XM_001635385.3"), because
     * BLAST subjects come from the FASTA and carry the bare form -- so callers
     * do not have to guess which convention a gene set uses.
     *
     * @param string $gene_set_path  Directory holding exon_coords.tsv.
     * @param array  $transcript_ids Subject ids, already stripped of any ref|…| wrapper.
     * @return array id => record. Ids with no row are simply absent -- an
     *               absent id is not an error, it means "cannot be drawn", and
     *               the caller must fall back to no link rather than guess.
     */
    public static function load($gene_set_path, array $transcript_ids)
    {
        $file = rtrim($gene_set_path, '/') . '/' . self::FILENAME;
        if (!is_readable($file) || empty($transcript_ids)) {
            return [];
        }

        $wanted = array_flip(array_values($transcript_ids));
        $found  = [];
        $left   = count($wanted);

        $fh = fopen($file, 'r');
        if (!$fh) {
            return [];
        }

        while (($line = fgets($fh)) !== false) {
            // Compare only the id field. Substring-matching the whole line would
            // hit a chromosome name or a coordinate and return another gene's
            // exons under the id asked for -- wrong blocks, drawn confidently.
            $tab = strpos($line, "\t");
            if ($tab === false) {
                continue;
            }
            $id = substr($line, 0, $tab);
            if (!isset($wanted[$id]) || isset($found[$id])) {
                continue;
            }

            $record = self::parseRow($line);
            if ($record !== null) {
                $found[$id] = $record;
                if (--$left === 0) {
                    break;   // every id answered; no reason to read the rest
                }
            }
        }
        fclose($fh);

        return $found;
    }

    /**
     * Parse one index line, or null if it is not usable.
     *
     * A malformed row returns null rather than a partial record: half an exon
     * list maps to plausible-looking blocks in the wrong place, and there is no
     * way to notice that downstream.
     *
     * @return array|null ['transcript_id','chr','strand','start','end','exons'=>[[s,e],…]]
     */
    public static function parseRow($line)
    {
        $parts = explode("\t", rtrim($line, "\r\n"));
        if (count($parts) < 5) {
            return null;
        }

        $outer = explode(',', $parts[3]);
        if (count($outer) !== 2) {
            return null;
        }

        $exons = [];
        foreach (explode(';', $parts[4]) as $span) {
            $se = explode(',', $span);
            if (count($se) !== 2) {
                return null;
            }
            $s = (int)$se[0];
            $e = (int)$se[1];
            if ($s <= 0 || $e < $s) {
                return null;
            }
            $exons[] = [$s, $e];
        }
        if (empty($exons)) {
            return null;
        }

        // The writer sorts ascending, but sorting here too costs nothing and the
        // minus-strand walk below depends on the order being right.
        usort($exons, function ($a, $b) { return $a[0] <=> $b[0]; });

        return [
            'transcript_id' => $parts[0],
            'chr'           => $parts[1],
            'strand'        => $parts[2] === '-' ? '-' : '+',
            'start'         => (int)$outer[0],
            'end'           => (int)$outer[1],
            'exons'         => $exons,
        ];
    }

    /** Transcript length = total exonic length. */
    public static function transcriptLength(array $record)
    {
        $len = 0;
        foreach ($record['exons'] as list($s, $e)) {
            $len += $e - $s + 1;
        }
        return $len;
    }

    /**
     * Map a transcript range to the genomic blocks it covers.
     *
     * ⚠️ MINUS-STRAND TRANSCRIPTS REVERSE THE MAPPING. Transcript position 1 is
     * the 5' end of the mRNA, which for a minus-strand gene is the HIGHEST
     * genomic coordinate -- so the exons are walked in DESCENDING genomic order
     * and each one is read right-to-left. Assuming ascending would put every
     * minus-strand primer at the wrong end of its gene, and the drawing would
     * look entirely reasonable while being wrong.
     *
     * Returns blocks in ASCENDING genomic order (JBrowse's requirement), which
     * for a minus-strand transcript means the first block is the primer's 3' end.
     *
     * @param array $record ['strand','exons'] from parseRow().
     * @param int   $tstart 1-based transcript coordinate; order with $tend is
     *                      not assumed, because BLAST reports sstart > send for
     *                      a minus-strand alignment.
     * @param int   $tend
     * @return array List of [start, end], 1-based inclusive. EMPTY means the
     *               range is not fully mappable -- see the containment note below.
     */
    public static function toGenomicBlocks(array $record, $tstart, $tend)
    {
        $a = (int)min($tstart, $tend);
        $b = (int)max($tstart, $tend);

        // STRICT containment, deliberately. A BLAST hit can never run off the
        // end of its own subject, so a range outside the exon total does not
        // mean "clip it" -- it means the exon index and the FASTA disagree
        // (a stale index, or a CDS-only FASTA against full-transcript exons,
        // or a poly-A tail present in the sequence but absent from the genome).
        // In every one of those cases the right answer is no picture, not a
        // picture shifted by an unknown offset.
        $length = self::transcriptLength($record);
        if ($a < 1 || $b > $length || $a > $b) {
            return [];
        }

        $exons = $record['exons'];
        if ($record['strand'] === '-') {
            $exons = array_reverse($exons);
        }

        $blocks = [];
        $offset = 0;                       // transcript bases consumed before this exon

        foreach ($exons as list($s, $e)) {
            $len       = $e - $s + 1;
            $exon_from = $offset + 1;      // this exon's transcript range
            $exon_to   = $offset + $len;
            $offset   += $len;

            if ($b < $exon_from || $a > $exon_to) {
                continue;
            }

            $from = max($a, $exon_from);   // overlap, in transcript coordinates
            $to   = min($b, $exon_to);

            if ($record['strand'] === '-') {
                // Transcript runs right-to-left across the exon: the LOWEST
                // transcript position in the overlap is the HIGHEST genomic one.
                $blocks[] = [$e - ($to - $exon_from), $e - ($from - $exon_from)];
            } else {
                $blocks[] = [$s + ($from - $exon_from), $s + ($to - $exon_from)];
            }
        }

        return self::mergeBlocks($blocks);
    }

    /**
     * Sort ascending and join blocks that touch or overlap.
     *
     * Two exons abutting in the genome (no intron between them) are an artefact
     * of how the GFF was written, not two things to draw. Drawn unmerged they
     * become two boxes with a connector of zero length, which reads as a splice
     * junction that does not exist.
     */
    public static function mergeBlocks(array $blocks)
    {
        if (count($blocks) < 2) {
            return array_values($blocks);
        }

        usort($blocks, function ($x, $y) { return $x[0] <=> $y[0]; });

        $merged = [array_shift($blocks)];
        foreach ($blocks as list($s, $e)) {
            $last = count($merged) - 1;
            if ($s <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $e);
            } else {
                $merged[] = [$s, $e];
            }
        }

        return $merged;
    }

    /**
     * Which genomic strand a transcript-strand hit is really on.
     *
     * A primer that aligned to the PLUS strand of a minus-strand transcript is
     * on the genome's MINUS strand. Without this flip the browser draws the
     * arrows the wrong way round on every minus-strand gene -- and an arrow is
     * precisely what a reader uses to check a primer pair faces inwards.
     */
    public static function genomicStrand($transcript_strand, $hit_strand)
    {
        if ($transcript_strand !== '-') {
            return $hit_strand;
        }
        return $hit_strand === '-' ? '+' : '-';
    }

    /**
     * Place a whole cDNA product on the genome: the amplicon and both primers.
     *
     * Returns null if ANY part fails to map. A product with one primer placed
     * and the other missing would draw as a lone box that looks like a finding,
     * so it is all or nothing -- the caller then simply offers no link, which is
     * the behaviour that existed before this class.
     *
     * @param array $record  from parseRow()/load()
     * @param array $product from PrimerPairs::findProducts()
     * @return array|null ['chr','strand','span','blocks','hits','spliced','introns']
     */
    public static function mapProduct(array $record, array $product)
    {
        $blocks = self::toGenomicBlocks($record, $product['start'], $product['end']);
        if (empty($blocks)) {
            return null;
        }

        $hits    = [];
        $spliced = false;
        foreach ($product['hits'] as $hit) {
            $hit_blocks = self::toGenomicBlocks($record, $hit['start'], $hit['end']);
            if (empty($hit_blocks)) {
                return null;
            }
            if (count($hit_blocks) > 1) {
                $spliced = true;
            }
            $hits[] = [
                'primer'   => $hit['primer'],
                'mismatch' => $hit['mismatch'] ?? 0,
                'strand'   => self::genomicStrand($record['strand'], $hit['strand']),
                'blocks'   => $hit_blocks,
                'spliced'  => count($hit_blocks) > 1,
            ];
        }

        return [
            'chr'     => $record['chr'],
            'strand'  => $record['strand'],
            'span'    => self::span($blocks),
            'blocks'  => $blocks,
            'hits'    => $hits,
            'spliced' => $spliced,
            // How many introns the AMPLICON crosses. This is the number behind
            // the genomic-vs-cDNA size difference, now stated rather than left
            // for the reader to infer from two numbers.
            'introns' => count($blocks) - 1,
        ];
    }

    /**
     * Outer span of a block list, as [start, end], or null when empty.
     *
     * This is what a browser link's `loc` needs: the region to display, not the
     * individual boxes.
     */
    public static function span(array $blocks)
    {
        if (empty($blocks)) {
            return null;
        }
        $starts = array_column($blocks, 0);
        $ends   = array_column($blocks, 1);
        return [min($starts), max($ends)];
    }
}
