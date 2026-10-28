<?php
/**
 * Genome data for JBrowse: data/genomes/ through the same token check as track files.
 *
 * data/genomes/ holds what JBrowse reads for every registered assembly and gene set -- the
 * genome sequence (reference.fasta + .fai, symlinks into organisms/), the gene track
 * (annotations.gff3.gz + .tbi) and any text-search index (trix/). Until 2026-10-20 nginx
 * served those files directly: config.php appended a token nothing checked, so anyone who
 * could guess a path (organism + accession + gene set name) could download any genome and
 * gene set, restricted or not.
 *
 * Now config.php rewrites those URLs to this endpoint, with a token bound to the one file and
 * issued only to a viewer who may open that assembly (moop_jbrowse_viewer_gene_sets()), and
 * nginx denies the directory (docs/nginx/moop-security.conf). The checking and the Range
 * handling JBrowse depends on are tracks.php's -- one copy, not two.
 *
 * Token claims are prefixed "genomes/", so a token for a genome file cannot be replayed
 * against a track file with the same relative path, or the reverse.
 *
 * GET ?file={organism}/{assembly}[/{gene_set}]/{name}&token=JWT
 */
require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../lib/request_metrics.php';   // System Monitor; this endpoint skips access_control.php
moop_metrics_register();

$TRACKS_BASE_DIR    = __DIR__ . '/../../data/genomes';
$TOKEN_FILE_PREFIX  = 'genomes/';
// reference.fasta and annotations.gff3 are symlinks into the organism tree.
$ALLOWED_REAL_ROOTS = array_values(array_filter([realpath(ConfigManager::getInstance()->getPath('organism_data'))]));

require __DIR__ . '/tracks.php';
