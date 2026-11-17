<?php
/**
 * api/v1/region.php — a piece of an assembly's genome sequence (JSON).
 *
 * GET organism, assembly, seqname, start, end [, strand=+|-]. Returns { sequence, ... }.
 *
 * The stable api/v1 name for api/get_sequence.php (the one implementation, with its access check), so the
 * address in the agent guide (llms.txt) never changes. Rate-limit class: heavy.
 */
require __DIR__ . '/../get_sequence.php';
