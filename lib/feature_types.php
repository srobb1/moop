<?php
/**
 * Which database feature types are transcripts -- defined ONCE.
 *
 * Counts and queries used to hard-code 'mRNA', so a gene set whose loader typed its
 * transcripts 'transcript' read as having none: the Schmidtea lugubris organism page said
 * "0 transcripts" over 20,659 of them (2026-10-21). Parastichopus (2,567) and Petromyzon (49)
 * mix both types. The Data Exporter had its own longer list (moopmartMrnaTypes()), so pages
 * disagreed about the same gene set. Every database query that means "transcript" uses this.
 *
 * (Reading a GFF is a different job with its own rule, moop_gff_is_transcript_type() in
 * lib/gff_gene_model.php: there any *RNA type under a gene is drawn as an isoform.)
 */

/** Lowercase transcript feature types, as stored in feature.feature_type (compare with LOWER()). */
function moop_transcript_types(): array {
    return ['mrna', 'transcript', 'lnc_rna', 'ncrna', 'pre_mirna', 'rrna', 'trna',
            'pseudogenic_transcript', 'processed_transcript'];
}

/**
 * SQL condition "this column holds a transcript type". The types are this file's own
 * constants, never user input, so they are inlined rather than bound -- which lets the
 * condition sit inside CASE WHEN and other places a placeholder list is awkward.
 */
function moop_transcript_type_sql(string $column): string {
    return "LOWER($column) IN ('" . implode("','", moop_transcript_types()) . "')";
}
