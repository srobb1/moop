<?php
/**
 * PrimerNames — what a template sequence and its primers are CALLED.
 *
 * Naming is its own concern, not a side effect of tails or of design options:
 * the same base string names the results table, the TSV download, the "oligos to
 * order" FASTA and the pair handed to Primer BLAST. It was written out inline in
 * three of those places, which is exactly the setup where a user reads one name
 * on screen, orders a second from the FASTA, and gets a third back from the
 * specificity check.
 *
 * ⭐ A PASTED SEQUENCE WITH NO HEADER STILL GETS A NAME. It used to be the
 * literal string "sequence", so every primer ever designed from pasted DNA came
 * back as sequence_p1_F — identical across sequences, across sessions and across
 * users, which is worse than no name at all once two designs are in the same
 * spreadsheet or the same order form.
 *
 * @package MOOP\Primer
 */

class PrimerNames
{
    /**
     * Name a template: what the user typed, or one built from the sequence.
     *
     * The generated form is date–first five bases–length (20260818-ATGGC-1332bp).
     * Every part of it is something the user can check against the sequence in
     * front of them, which is what makes it a name rather than an id: two
     * different sequences pasted on the same day still differ, and the same
     * sequence pasted twice on the same day deliberately does NOT — re-running a
     * design should not silently rename its primers.
     *
     * ⚠️ FASTA CONVENTION: the id is the header up to the first whitespace, so
     * ">XM_001626548.3 heat shock protein" is named XM_001626548.3. Keeping the
     * whole line would put a description into every primer name and into the
     * download filename.
     *
     * @param string $header Header line as typed, without the '>'.
     * @param string $seq    Bare template DNA, marks and whitespace already gone.
     * @return string
     */
    public static function template($header, $seq)
    {
        $header = trim((string)$header);
        if ($header !== '') {
            $parts = preg_split('/\s+/', $header);
            if (($parts[0] ?? '') !== '') {
                return $parts[0];
            }
        }

        $seq   = (string)$seq;
        $start = strtoupper(substr($seq, 0, 5));

        return date('Ymd') . '-' . ($start !== '' ? $start : 'seq') . '-' . strlen($seq) . 'bp';
    }

    /**
     * The name with anything a FASTA id or a filename cannot carry replaced.
     *
     * @param string $record_id Template name, as typed or as generated.
     * @return string
     */
    public static function base($record_id)
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$record_id);
    }

    /**
     * What one primer is called, everywhere it appears.
     *
     * The _F/_R suffix is not decoration: PrimerInput pairs FASTA records by it,
     * so this is also what makes the per-row Check button hand Primer BLAST a
     * PAIR rather than two unrelated oligos.
     *
     * @param string $record_id Template name.
     * @param int    $rank      Pair rank, as primer3 reports it.
     * @param string $side      'left' or 'right'.
     * @return string
     */
    public static function primer($record_id, $rank, $side)
    {
        return self::base($record_id) . '_p' . (int)$rank
             . '_' . ($side === 'right' ? 'R' : 'F');
    }
}
