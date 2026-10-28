<?php
/**
 * Where a BLAST search stands in the queue, for the page that is waiting on it.
 *
 * GET ticket=<hex>  (the hidden queue_ticket field the BLAST form posted)
 * Returns JSON: {state: 'waiting'|'running'|'unknown', position?, running?}
 *
 * Deliberately does NOT start a session. The BLAST request this is asking about may still be
 * in flight, and a session-starting request from the same visitor would queue behind its
 * session lock until it finished -- the poll would only ever answer after the fact. It needs
 * no identity anyway: the ticket is a random value only the submitting page knows, and the
 * answer is a place in line, nothing about the search itself.
 */

include_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/request_metrics.php';   // System Monitor; this endpoint skips access_control.php
moop_metrics_register();
require_once __DIR__ . '/../lib/blast_queue.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode(blast_queue_status((string)($_GET['ticket'] ?? '')));
