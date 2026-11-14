<?php
/**
 * What an assembly's genome.json declares about it -- read here, in one place, so the BLAST-only
 * rule (moop_blast_only_genome()), the organism checks and the admin pages cannot disagree.
 * No dependencies: loaded by includes/access_control.php and by the organism-cache code alike.
 */

/**
 * Does this assembly directory's genome.json declare it BLAST-only -- a genome offered for BLAST
 * with no gene set ("blast_only": true)? Sofia, 2026-11-14 (notes/ASSEMBLY_WITHOUT_GENE_SET_PLAN.md
 * item 8): the declaration says what the assembly IS, so an assembly mid-setup (genome copied, gene
 * set still coming) is not mistaken for one; access still comes from its groups entry
 * (gene_set "blast_only").
 */
if (!function_exists('moop_genome_declares_blast_only')) {
function moop_genome_declares_blast_only(string $assembly_dir): bool {
    $file = rtrim($assembly_dir, '/') . '/genome.json';
    if (!is_file($file)) return false;
    $json = json_decode((string)@file_get_contents($file), true);
    return is_array($json) && ($json['blast_only'] ?? false) === true;
}
}
