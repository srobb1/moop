<?php
/**
 * api/v1/annotations — every annotation of a gene (or one transcript), as CSV.
 *
 * GET organism, uniquename (a gene or transcript ID). The gene record (api/v1/gene) links to it as
 * links.annotations_csv; its own lists are capped, this is the whole table.
 *
 * The stable api/v1 name for api/download_annotations.php (the one implementation, with its access
 * check). Rate-limit class: heavy (rate_limit_heavy).
 */
require __DIR__ . '/../download_annotations.php';
