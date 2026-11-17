<?php
/**
 * api/v1/pathway_genes — the genes in KEGG pathways (or modules), in one or more organisms (or one
 * assembly / gene set of one organism). From eggNOG's KEGG assignments ("KEGG Pathway (eggNOG)",
 * "KEGG Module (eggNOG)"; lib/kegg.php).
 *
 * GET organism  (required) organism directory name(s), comma-separated, up to 20 -- from api/v1/catalog
 *     assembly, gene_set  (optional, with one organism) narrow to those
 *     pathway   KEGG pathway or module id(s), comma-separated, up to 20: map04142 (also 04142, ko04142,
 *               hsa04142 -- all read as map04142), M00001. api/v1/pathways lists a gene set's pathways.
 *     name      instead of / as well as pathway: pathways whose name contains this text ("lysosome")
 *     match     any (default) | all -- all: only genes in every listed pathway (not with name=)
 *     format    json (default) | tsv
 *
 * score = how many KOs eggNOG gave the gene (1 = one; more = several possible KOs, so the pathway may come
 * from any of them). Shape as api/v1/domain_genes. Rate-limit class: heavy. lib/annotation_genes.php.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/annotation_genes.php';
header('Cache-Control: private, no-store');
moop_v1_annotation_genes_endpoint(['type' => 'Pathways', 'param' => 'pathway', 'normalise' => 'moop_v1_pathway_id',
                                   'max_acc' => 20, 'by_name' => true, 'cap' => 2000, 'label' => 'pathway_genes']);
