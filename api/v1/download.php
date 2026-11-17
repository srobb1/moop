<?php
/**
 * api/v1/download — one file of an assembly or gene set (FASTA, GFF), by its file name.
 *
 * GET organism, assembly, filename [, gene_set]. The links in api/v1/catalog are complete calls
 * to this; take them from there rather than building names by hand.
 *
 * The stable api/v1 name for api/download_file.php (the one implementation: access check,
 * path-traversal guard, blocked file types, download file name). Kept separate so the address in
 * the agent guide (llms.txt) never changes even if the site's own download code moves.
 * Rate-limit class: heavy (rate_limit_heavy).
 */
require __DIR__ . '/../download_file.php';
