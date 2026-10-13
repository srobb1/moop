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
 * The GFF itself is read by lib/gff_gene_model.php -- the one gene-model reader the gene page
 * and the GFF download share (2026-10-13). This file used to grep for `Parent=<id>` itself,
 * which found NOTHING on 40% of sampled gene pages: a RefSeq gene's database id is its GeneID
 * (102421115) while its transcripts hang under "gene-FHDC1", and Schmidtea links by geneID=.
 */

if (!function_exists('moop_gene_isoforms')) {

/**
 * Every transcript of a gene, in GFF order.
 *
 * @param string $gene_uniquename The gene's DATABASE id, as the gene page has it -- matched to
 *                                the GFF by lib/gff_gene_model.php (rna-/gene: prefixes, GeneID)
 * @return array Each: ['id','fasta_id','type','start','end','strand','length']
 *               'fasta_id' is '' when the transcript has no sequence in the
 *               FASTA, and 'length' is null then — an isoform we cannot offer a
 *               sequence for is still worth listing, but must not look pickable.
 */
function moop_gene_isoforms(string $organism, string $assembly, string $gene_set, string $gene_uniquename): array
{
    require_once __DIR__ . '/gff_gene_model.php';
    if ($gene_uniquename === '') return [];
    $model = moop_gene_model($organism, $assembly, $gene_set, $gene_uniquename, '');
    if ($model === null) return [];

    $transcripts = array_values(array_filter($model['children'], fn($c) => $c['id'] !== '' && moop_gff_is_transcript_type($c['type'])));
    $gene_set_path = ConfigManager::getInstance()->getPath('organism_data') . "/$organism/$assembly/$gene_set";
    $fai = $gene_set_path . '/transcript.nt.fa.fai';
    $wanted = [];
    foreach ($transcripts as $t) {
        $wanted[] = $t['id'];
        $wanted[] = preg_replace('/^(?:rna|cds|gene|id)-/', '', $t['id']);
    }
    require_once __DIR__ . '/fasta_index.php';
    $idx = is_readable($fai) ? moop_fai_lookup($fai, array_values(array_unique($wanted))) : [];

    $out = [];
    foreach ($transcripts as $t) {
        $bare = preg_replace('/^(?:rna|cds|gene|id)-/', '', $t['id']);
        $fasta_id = isset($idx[$t['id']]) ? $t['id'] : (isset($idx[$bare]) ? $bare : '');
        $out[] = $t + ['fasta_id' => $fasta_id, 'length' => $fasta_id !== '' ? $idx[$fasta_id]['len'] : null];
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
 * @param string $id       The exact FASTA id (e.g. "X-RA:cds" from the Sequences card), or a
 *                         transcript id in either convention (rna- prefixed or bare); both are tried.
 * @param string $seq_type One of moop_primer_sequence_types().
 */
function moop_transcript_sequence(string $gene_set_path, string $id, string $seq_type = 'transcript'): string
{
    $types   = ConfigManager::getInstance()->getSequenceTypes();
    $pattern = $types[$seq_type]['pattern'] ?? null;
    if ($pattern === null || !in_array($seq_type, moop_primer_sequence_types(), true)) {
        return '';
    }

    $fa = $gene_set_path . '/' . $pattern;
    if ($id === '' || !is_readable($fa) || !is_readable($fa . '.fai')) {
        return '';
    }

    // The .fai reader the rest of the site uses (exact ids, uppercased like every other
    // sequence on the site), rather than a `samtools faidx` process per lookup.
    require_once __DIR__ . '/fasta_index.php';
    $got = moop_fasta_fetch_with_headers($fa, array_values(array_unique([$id, preg_replace('/^(?:rna|cds|gene|id)-/', '', $id)])));
    foreach ($got as $rec) {
        if ($rec['seq'] !== '') return $rec['seq'];
    }
    return '';
}

}
