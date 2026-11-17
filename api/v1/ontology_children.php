<?php
/**
 * api/v1/ontology_children.php — the terms directly below an ontology term (JSON).
 *
 * GET o (PLANA, GO, ...), id (PLANA:0000001) [, parts=1: part of as well as is a].
 *
 * The stable api/v1 name for api/ontology_children.php (the one implementation, with its access check), so the
 * address in the agent guide (llms.txt) never changes. Rate-limit class: api.
 */
require __DIR__ . '/../ontology_children.php';
