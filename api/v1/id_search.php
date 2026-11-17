<?php
/**
 * api/v1/id_search.php — find a gene, transcript or protein by ID, in every gene set this visitor can open (JSON).
 *
 * GET q (an ID, or the start of one). Returns { results: [{uniquename, type, organism, assembly, gene_set, url}] }.
 *
 * The stable api/v1 name for api/feature_search.php (the one implementation, with its access check), so the
 * address in the agent guide (llms.txt) never changes. Rate-limit class: api.
 */
require __DIR__ . '/../feature_search.php';
