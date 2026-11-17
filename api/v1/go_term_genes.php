<?php
/**
 * api/v1/go_term_genes.php — the genes annotated with a GO term in one gene set (JSON).
 *
 * GET term (GO:0005515), organism, assembly, gene_set [, sub=1: include genes on more specific terms].
 *
 * The stable api/v1 name for api/go_term_genes.php (the one implementation, with its access check), so the
 * address in the agent guide (llms.txt) never changes. Rate-limit class: heavy.
 */
require __DIR__ . '/../go_term_genes.php';
