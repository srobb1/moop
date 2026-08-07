<?php
/**
 * Listing a gene's isoforms, and fetching a transcript's sequence.
 *
 * ⚠️ READ FROM THE GFF, NOT FROM THE DATABASE HIERARCHY — deliberately, and for
 * the same reason tools/parent.php builds its gene model that way. The loader
 * wrote the STRING 'NULL' (and in some gene sets a self-loop) into
 * parent_feature_id across 81 databases, so `WHERE parent_feature_id = ?`
 * returns nothing and a recursive CTE can hang. The GFF is the source those
 * rows were derived from and it is correct.
 *
 * ⚠️ THE TWO ID CONVENTIONS. A RefSeq GFF calls a transcript "rna-XM_048724567.1"
 * while the transcript FASTA calls it "XM_048724567.1". Looking one up by the
 * other silently finds nothing — which is the same trap ExonMap documents, and
 * the reason sequence lookup tries both forms rather than assuming either.
 *
 * NOTE tools/parent.php:360 does the same GFF grep inline to build its gene
 * model. It should move to this function next time someone is in both files;
 * it was left alone here to keep a new tool's first draft from editing the gene
 * page.
 */

if (!function_exists('moop_gene_isoforms')) {

/**
 * Every transcript of a gene, in GFF order.
 *
 * @param string $gene_set_path Directory holding genes.gff and transcript.nt.fa
 * @param string $gene_id       The gene's GFF ID, e.g. "gene-LOC116613690"
 * @return array Each: ['id','fasta_id','type','start','end','strand','length']
 *               'fasta_id' is '' when the transcript has no sequence in the
 *               FASTA, and 'length' is null then — an isoform we cannot offer a
 *               sequence for is still worth listing, but must not look pickable.
 */
function moop_gene_isoforms(string $gene_set_path, string $gene_id): array
{
    $gff = $gene_set_path . '/' . genes_gff_filename();
    if ($gene_id === '' || !is_readable($gff)) {
        return [];
    }

    // Anchored on the Parent= value ending at ';' or end of line, so
    // "Parent=gene-LOC1" does not also match "Parent=gene-LOC10".
    $lines = [];
    exec('grep -E ' . escapeshellarg('Parent=' . preg_quote($gene_id, '/') . '(;|$)')
         . ' ' . escapeshellarg($gff), $lines);

    // The FASTA's own index tells us which transcripts actually have sequence,
    // and how long they are — one small read instead of one samtools call per
    // isoform just to find out whether it exists.
    $lengths = [];
    $fai = $gene_set_path . '/transcript.nt.fa.fai';
    if (is_readable($fai)) {
        $fh = fopen($fai, 'r');
        while (($row = fgets($fh)) !== false) {
            $tab = strpos($row, "\t");
            if ($tab === false) continue;
            $name = substr($row, 0, $tab);
            $rest = explode("\t", rtrim($row));
            $lengths[$name] = (int)($rest[1] ?? 0);
        }
        fclose($fh);
    }

    $out = [];
    foreach ($lines as $line) {
        $p = explode("\t", $line);
        if (count($p) < 9 || !preg_match('/\bID=([^;]+)/', $p[8], $m)) {
            continue;
        }
        $id   = $m[1];
        $bare = preg_replace('/^(?:rna|cds|gene|id)-/', '', $id);

        $fasta_id = '';
        if (isset($lengths[$id]))        $fasta_id = $id;
        elseif (isset($lengths[$bare]))  $fasta_id = $bare;

        $out[] = [
            'id'       => $id,
            'fasta_id' => $fasta_id,
            'type'     => $p[2],
            'start'    => (int)$p[3],
            'end'      => (int)$p[4],
            'strand'   => $p[6],
            'length'   => $fasta_id !== '' ? $lengths[$fasta_id] : null,
        ];
    }

    return $out;
}

/**
 * Which sequence types a feature can be fetched BY ID.
 *
 * Not 'genome': genome.fa holds chromosomes, so a gene id is not a record in it
 * and the genomic sequence has to come from a coordinate slice instead. Not
 * 'protein' either — primers are DNA. Listing what works keeps the caller from
 * offering a button that leads nowhere.
 */
function moop_primer_sequence_types(): array
{
    return ['transcript', 'cds'];
}

/**
 * A feature's sequence for one sequence type, or '' if it is not there.
 *
 * The filename comes from the sequence_types config, the same mapping the
 * Sequences section renders from, rather than a second hardcoded list.
 *
 * @param string $id       Either id convention; both are tried.
 * @param string $seq_type One of moop_primer_sequence_types().
 */
function moop_transcript_sequence(string $gene_set_path, string $id, string $seq_type = 'transcript'): string
{
    $types   = ConfigManager::getInstance()->getArray('sequence_types');
    $pattern = $types[$seq_type]['pattern'] ?? null;
    if ($pattern === null || !in_array($seq_type, moop_primer_sequence_types(), true)) {
        return '';
    }

    $fa = $gene_set_path . '/' . $pattern;
    if ($id === '' || !is_readable($fa) || !is_readable($fa . '.fai')) {
        return '';
    }

    foreach ([$id, preg_replace('/^(?:rna|cds|gene|id)-/', '', $id)] as $try) {
        $out = [];
        exec('samtools faidx ' . escapeshellarg($fa) . ' ' . escapeshellarg($try) . ' 2>/dev/null', $out);
        array_shift($out);                       // the header line
        $seq = strtoupper(implode('', $out));
        if ($seq !== '') {
            return $seq;
        }
    }
    return '';
}

}
