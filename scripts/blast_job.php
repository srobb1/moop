<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
/**
 * Background worker for one API BLAST job (api/v1/blast.php starts it; lib/blast_jobs.php).
 *
 *   php scripts/blast_job.php <job id>
 *
 * Holds the job's worker.lock for its whole life, waits its turn in the SAME queue as the
 * BLAST page (executeBlastSearch -> blast_queue_run: memory budget, bounded wait, runtime
 * kill), then writes the three downloads and result.json. If this process dies at any point
 * the kernel drops the lock and the job reads "failed" -- it cannot be left "running".
 */

umask(0007);   // job files: group-writable (apache + smr), never world-readable

$base = dirname(__DIR__);
require_once $base . '/includes/config_init.php';
require_once $base . '/lib/blast_functions.php';
require_once $base . '/lib/blast_jobs.php';
require_once $base . '/lib/functions_errorlog.php';

$id  = (string) ($argv[1] ?? '');
$dir = blast_job_dir($id);
if ($dir === '' || !is_file("$dir/request.json")) { fwrite(STDERR, "No such job: $id\n"); exit(2); }

$lock = @fopen("$dir/worker.lock", 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);   // another worker already has it
if (is_file("$dir/result.json")) exit(0);                   // finished already

$req = blast_job_read_json("$dir/request.json");
try {
    $result = executeBlastSearch($req['query'] ?? '', $req['db_path'] ?? '', $req['binary'] ?? '',
                                 ($req['options'] ?? []) + ['queue_ticket' => $id]);
    if (!empty($result['success'])) {
        if (!blast_job_save_result($dir, $result, ['queue_waited' => $result['queue_waited'] ?? 0])) {
            logError('BLAST job could not save its results', 'blast', ['job' => $id]);
            blast_job_save_failure($dir, 'The search ran but the server could not save its results. Please submit it again.');
        }
    } else {
        // Same rule as the page: BLAST's own stderr can name server paths -- input errors only.
        $failure = blast_failure_for_display($result, false);
        if ($failure['log']) {
            logError('BLAST job failed', 'blast', ['job' => $id, 'error' => $result['error'] ?? '',
                     'stderr' => $result['stderr'] ?? '', 'program' => $req['binary'] ?? '']);
        }
        blast_job_save_failure($dir, $failure['message']);
    }
} catch (Throwable $e) {
    logError('BLAST job crashed', 'blast', ['job' => $id, 'error' => $e->getMessage()]);
    blast_job_save_failure($dir, 'The search could not be run because of a server error. Please submit it again.');
}
flock($lock, LOCK_UN);
fclose($lock);
