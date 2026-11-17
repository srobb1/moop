<?php
/**
 * api/v1/ontology_search.php — search ontology terms by name or ID (JSON).
 *
 * GET o (PLANA, GO, ...; comma-separated for several), q (text).
 *
 * The stable api/v1 name for api/ontology_terms.php (the one implementation, with its access check), so the
 * address in the agent guide (llms.txt) never changes. Rate-limit class: api.
 */
require __DIR__ . '/../ontology_terms.php';
