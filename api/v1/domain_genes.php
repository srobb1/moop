<?php
/**
 * api/v1/domain_genes — the genes carrying protein domains, in one or more organisms (or one assembly /
 * gene set of one organism).
 *
 * GET organism  (required) organism directory name(s), comma-separated, up to 20 -- from api/v1/catalog
 *     assembly, gene_set  (optional, with one organism) narrow to those
 *     domains   accession(s), comma-separated, up to 50: Pfam PF00085, InterPro IPR013766, SMART SM00454,
 *               CDD cd00051, PROSITE PS51352 ... (type "Domains": every InterProScan member database)
 *     name      instead of / as well as domains: domains whose NAME contains this text ("thioredoxin");
 *               up to 50 matching accessions per organism, listed in name_matched
 *     match     any (default) | all -- all: only genes with every listed domain (not with name=)
 *     format    json (default) | tsv (one row per gene x domain hit)
 *
 * Per gene set: total, shown (at most 2,000 genes), genes [{gene, name, description, hits: [{accession,
 * name, source, score (best e-value), on: [the transcripts carrying it]}]}]. A gene set the visitor may not
 * open is absent, as if it did not exist. Rate-limit class: heavy. lib/annotation_genes.php.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/annotation_genes.php';
header('Cache-Control: private, no-store');
moop_v1_annotation_genes_endpoint(['type' => 'Domains', 'param' => 'domains', 'normalise' => null, 'max_acc' => 50,
                                   'by_name' => true, 'cap' => 2000, 'label' => 'domain_genes']);
