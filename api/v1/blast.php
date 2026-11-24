<?php
/**
 * BLAST for agents: submit a search, get a job id, check back for the result.
 *
 * SUBMIT (POST, or GET for short queries):
 *   organism   required (folder name, as in api/v1/catalog)
 *   assembly, gene_set   only when the organism has more than one (a 300 lists the choices)
 *   database   protein | transcript | cds | genome   (what this gene set has: a 400 lists them)
 *   program    blastn | megablast | blastn-short | blastp | blastx | tblastn | tblastx
 *   query      FASTA, or bare sequence; within blast_max_query_seqs / _letters -- send them together:
 *              one run of 5 queries took 1.6 s vs 2.6 s one by one (blastp), 17.7 s vs 32 s
 *              (tblastn, zebrafish genome), measured 2026-11-24 cold and warm; the cost is per run.
 *   evalue     default 1e-3 (blastn-short: 1000, its own default)   max_hits  default 50
 *   -> 202 {"job": id, "state": "starting", "check_back_seconds": 10, "status": <url>}
 *
 * STATUS (GET ?job=<id>[&format=md][&top=10]);  FILE: ?job=<id>&download=txt|tsv|xml
 *   state starting | waiting (+position) | running | done | failed (+error)
 *   done: per query the top hits (gene page link for gene-set databases) and "downloads":
 *   TXT / TSV / XML links -- the SAME files the BLAST page's buttons serve (lib/blast_jobs.php).
 *
 * Runs in the background (scripts/blast_job.php) through the same queue as the BLAST page, so
 * agents cannot crowd out people: one unfinished job per client at a time, then 429.
 */

include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/blast_functions.php';
require_once __DIR__ . '/../../lib/blast_jobs.php';
require_once __DIR__ . '/../../lib/blast_results_visualizer.php';   // blastHitFeatureId()
require_once __DIR__ . '/../../lib/feature_coords.php';             // hit -> its gene, as the page does

const BLAST_API_POLL_SECONDS = 10;

header('Cache-Control: private, no-store');
$out = function (int $code, array $body) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1] + $body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
};
$fail = fn(int $code, string $msg, array $extra = []) => $out($code, ['error' => $msg] + $extra);
$param = fn(string $k) => trim((string) ($_POST[$k] ?? $_GET[$k] ?? ''));
$config = ConfigManager::getInstance();

// ---------------------------------------------------------------- status of a job
if (($job = $param('job')) !== '') {
    if (!blast_job_valid_id($job)) $fail(400, 'job is the id a submit returned (32 hex characters).');
    // A download: the SAME saved file the page's buttons serve (blast_job_file()), from here
    // because api/v1/ is exempt from the human check that would stop an agent at api/blast_download.php.
    if (($dl = $param('download')) !== '') {
        $file = blast_job_file($job, $dl);
        if ($file === null) $fail(404, 'No such file: download is txt, tsv or xml, of a finished job (kept 24 hours).');
        header('Content-Type: ' . $file['mime'] . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
        header('Content-Length: ' . filesize($file['path']));
        readfile($file['path']);
        exit;
    }
    $state = blast_job_state($job);
    if ($state['state'] === 'unknown') {
        $fail(404, 'No such job. Jobs and their files are kept 24 hours.');
    }
    $meta = blast_job_read_json(blast_job_dir($job) . '/meta.json');
    $body = ['job' => $job, 'state' => $state['state']];
    foreach (['organism', 'assembly', 'gene_set', 'database', 'program'] as $k) $body[$k] = $meta[$k] ?? '';
    if (isset($state['position'])) $body['position_in_line'] = $state['position'];
    if ($state['state'] === 'failed') $body['error'] = $state['error'] ?? 'The search failed.';
    if (in_array($state['state'], ['starting', 'waiting', 'running'], true)) {
        $body['check_back_seconds'] = BLAST_API_POLL_SECONDS;
    }
    if ($state['state'] === 'done') {
        $downloads = [];
        foreach (blast_job_available_formats($job) as $f) {
            $downloads[$f] = moop_public_url('api/v1/blast.php') . '?job=' . $job . '&download=' . $f;
        }
        $top  = max(1, min(50, (int) ($param('top') ?: 10)));
        $hits = blast_api_hits($job, $meta, $top);
        $body += ['queries' => $hits, 'downloads' => $downloads,
                  'note' => 'Top ' . $top . ' hits per query. The downloads are the same files the BLAST page gives a '
                          . 'person (TXT = the alignments, TSV = one line per hit, XML = everything); kept 24 hours.'];
    }
    if ($param('format') === 'md') {
        header('Content-Type: text/markdown; charset=utf-8');
        echo blast_api_markdown($body);
        exit;
    }
    $out(200, $body);
}

// ---------------------------------------------------------------- submit
$organism = $param('organism');
$query    = str_replace("\r", '', (string) ($_POST['query'] ?? $_GET['query'] ?? ''));
$db_key   = strtolower($param('database'));
$program  = strtolower($param('program'));
if ($organism === '' || trim($query) === '' || $db_key === '' || $program === '') {
    $fail(400, 'Submit needs organism, database, program and query; check a job with job=<id>.',
          ['programs' => array_keys(blast_programs())]);
}

$programs = blast_programs();
if (!isset($programs[$program])) $fail(400, "Unknown program $program.", ['programs' => array_keys($programs)]);
$prog = $programs[$program];

// The sources this visitor may BLAST: the same list the BLAST page offers (BLAST-only genomes included).
// One entry per gene set: flattenSourcesList() repeats a gene set for every group it is in, and the
// repeats made an organism in two groups answer 300 "more than one gene set" forever -- even with
// assembly and gene_set given (2026-11-24, Acropora_millepora).
$sources = [];
foreach (flattenSourcesList(getAccessibleGeneSets(null, null, null, true)) as $s) {
    if ($s['organism'] === $organism) $sources[$s['assembly'] . '|' . ($s['gene_set'] ?? '')] ??= $s;
}
$sources = array_values($sources);
if (!$sources) $fail(404, "No organism $organism that you can open (names are as in api/v1/catalog).");
foreach (['assembly', 'gene_set'] as $k) {
    if (($v = $param($k)) !== '') $sources = array_values(array_filter($sources, fn($s) => ($s[$k] ?? '') === $v));
}
if (!$sources) $fail(404, "No such assembly / gene set of $organism that you can open.");
if ($db_key === 'genome') {   // one genome per assembly, whichever gene set: do not ask which
    $by_asm = [];
    foreach ($sources as $s) $by_asm[$s['assembly']] ??= $s;
    $sources = array_values($by_asm);
}
if (count($sources) > 1) {
    $fail(300, "$organism has more than one " . ($db_key === 'genome' ? 'assembly' : 'gene set') . '; ask again with assembly and gene_set.',
          ['choices' => array_map(fn($s) => ['assembly' => $s['assembly'], 'gene_set' => $s['gene_set'] ?? ''], $sources)]);
}
$source = $sources[0];

$dbs = [];
foreach (getBlastDatabases($source['path']) as $d) $dbs[$d['seq_type']] = $d;
if (!isset($dbs[$db_key])) $fail(400, "This gene set has no $db_key database.", ['databases' => array_keys($dbs)]);
$db = $dbs[$db_key];
$want_db = $prog['db'] === 'Protein' ? 'protein' : 'nucleotide';
if ($db['type'] !== $want_db) {
    $fit = array_keys(array_filter($programs, fn($p) => ($p['db'] === 'Protein' ? 'protein' : 'nucleotide') === $db['type']));
    $fail(400, "$program searches a " . ($want_db === 'protein' ? 'protein' : 'DNA') . " database, but $db_key is "
          . ($db['type'] === 'protein' ? 'protein' : 'DNA') . '.', ['programs_for_this_database' => $fit]);
}

// The query: valid FASTA, within the limits, and the kind of sequence the program reads.
if ($query[0] !== '>' && ltrim($query)[0] !== '>') $query = ">query_sequence\n" . trim($query);
$check = validateBlastSequence($query);
if (!$check['valid']) $fail(400, 'Invalid sequence: ' . $check['error']);
$records = preg_split('/^>/m', ltrim($query), -1, PREG_SPLIT_NO_EMPTY);
if (($too_big = blast_query_limit_error($query)) !== '') $fail(413, $too_big);   // the page's limits too
$residues = preg_replace('/[^A-Za-z*]/', '', implode('', array_map(fn($r) => substr($r, strcspn($r, "\n")), $records)));
$nt_share = strlen($residues) ? strlen(preg_replace('/[^ACGTUNacgtun]/', '', $residues)) / strlen($residues) : 1;
if (str_contains($prog['query'], 'DNA') && $nt_share < 0.9) $fail(400, "$program takes a DNA query, and this looks like protein. For a protein query use blastp or tblastn.");
if ($prog['query'] === 'Protein' && $nt_share >= 0.9) $fail(400, "$program takes a protein query, and this looks like DNA. For a DNA query use blastn or blastx.");

// Options: the page's defaults, and blastn-short's own presets (blast_program_presets()).
$presets = blast_program_presets($program);
$evalue = $param('evalue') !== '' ? $param('evalue') : ($presets['evalue'] ?? '1e-3');
if (!is_numeric($evalue) || (float) $evalue <= 0) $fail(400, 'evalue is a positive number, e.g. 1e-5.');
$max_hits_cap = max(1, $config->getInt('blast_max_hits', 1000));
$max_hits = max(1, min($max_hits_cap, (int) ($param('max_hits') ?: 50)));
$options = ['evalue' => $evalue, 'max_hits' => $max_hits, 'matrix' => 'BLOSUM62',
            'filter' => (bool) $prog['filter'], 'soft_masking' => (bool) $prog['soft_masking'],
            'task' => $prog['task'] ?? '', 'strand' => 'both'] + $presets;

// A short DNA query sent to a program that loses short queries: the same warning the page gives
// (blast_short_query_reason()), returned with the job so the agent can tell the person why 0 hits.
$shortest = min(array_map(fn($r) => strlen(preg_replace('/[^A-Za-z*]/', '', substr($r, strcspn($r, "\n")))), $records));
$warning  = blast_short_query_reason($program, $shortest, $options);

// One unfinished job per client (a logged-in user, else an address), so agents queue politely.
$client = hash('sha256', ($_SESSION['username'] ?? '') !== '' ? 'u:' . $_SESSION['username'] : 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''));
if ($active = blast_jobs_active_for($client)) {
    $fail(429, 'You already have a BLAST job running; check it, and submit the next one when it is done.',
          ['job' => $active[0], 'status' => moop_public_url('api/v1/blast.php') . '?job=' . $active[0]]);
}

[$id, $dir] = blast_job_create(['source' => 'api', 'client' => $client, 'organism' => $organism,
                                'assembly' => $source['assembly'], 'gene_set' => $source['gene_set'] ?? '',
                                'database' => $db_key, 'program' => $program, 'gene_set_dir' => $source['path']]);
if ($id === '' || !blast_job_write_json("$dir/request.json", [
        'query' => $query, 'db_path' => $db['path'], 'binary' => $prog['binary'] ?? $program, 'options' => $options])) {
    error_log('MOOP: api/v1/blast.php could not create a job under ' . moop_cache_root() . '/blast_jobs');
    $fail(503, 'The server could not save the job. Please try again later.');
}
if (!blast_job_spawn($id)) {
    blast_job_save_failure($dir, 'The search could not be started.');
    error_log("MOOP: api/v1/blast.php could not start the worker for job $id");
    $fail(503, 'The server could not start the search. Please try again later.');
}
$out(202, ['job' => $id, 'state' => 'starting', 'check_back_seconds' => BLAST_API_POLL_SECONDS,
           'status' => moop_public_url('api/v1/blast.php') . '?job=' . $id,
           'sequences' => count($records), 'max_hits' => $max_hits, 'evalue' => $evalue]
           + ($warning !== '' ? ['warning' => $warning . ' For DNA under ' . BLAST_SHORT_QUERY_NT . ' letters use program=blastn-short.'] : []));


/**
 * The top hits per query, read from the job's TSV (the file the person downloads, so the summary
 * can never disagree with it). Gene-set databases get a gene page link per hit.
 */
function blast_api_hits(string $job, array $meta, int $top): array
{
    $file = blast_job_file($job, 'tsv');
    if ($file === null) return [];
    $is_genome = ($meta['database'] ?? '') === 'genome';
    $has_db = !$is_genome && is_file(organism_db_path((string) ($meta['organism'] ?? '')));
    $lines = file($file['path'], FILE_IGNORE_NEW_LINES) ?: [];
    // The gene each hit belongs to (a protein or transcript id -> its gene), the lookup the page's links use.
    $genes = [];
    if ($has_db) {
        $ids = [];
        foreach ($lines as $l) if ($l !== '' && $l[0] !== '#') $ids[] = blastHitFeatureId(explode("\t", $l)[1] ?? '');
        foreach (moop_feature_coords_by_feature((string) ($meta['gene_set_dir'] ?? ''), array_unique($ids)) as $id => $e) {
            $genes[$id] = $e['gene_id'] ?? $id;
        }
    }
    $queries = [];
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $c = explode("\t", $line);
        if (count($c) < 12) continue;
        $q = $c[0];
        $queries[$q] ??= ['query' => $q, 'hit_count' => 0, 'hits' => []];
        $seen = array_column($queries[$q]['hits'], 'hit');
        if (!in_array($c[1], $seen, true)) $queries[$q]['hit_count']++;
        if (count($queries[$q]['hits']) >= $top || in_array($c[1], $seen, true)) continue;   // best HSP per hit
        $hit_id = blastHitFeatureId($c[1]);
        $hit = ['hit' => $hit_id, 'description' => $c[12] ?? '', 'percent_identity' => (float) $c[2],
                'alignment_length' => (int) $c[3], 'query_start' => (int) $c[6], 'query_end' => (int) $c[7],
                'hit_start' => (int) $c[8], 'hit_end' => (int) $c[9], 'evalue' => $c[10], 'bitscore' => (float) $c[11]];
        if ($has_db) {
            $hit['gene'] = $genes[$hit_id] ?? $hit_id;
            $hit['gene_page'] = moop_public_url('tools/parent.php') . '?uniquename=' . rawurlencode($hit['gene'])
                . '&organism=' . rawurlencode($meta['organism']) . '&assembly=' . rawurlencode($meta['assembly'] ?? '')
                . (($meta['gene_set'] ?? '') !== '' ? '&gene_set=' . rawurlencode($meta['gene_set']) : '');
        }
        $queries[$q]['hits'][] = $hit;
    }
    return array_values($queries);
}

function blast_api_markdown(array $b): string
{
    $md = "# BLAST job {$b['job']}\n\n**{$b['program']}** against the **{$b['database']}** database of "
        . "{$b['organism']} {$b['assembly']} {$b['gene_set']}\n\nState: **{$b['state']}**"
        . (isset($b['position_in_line']) ? " (place in line: {$b['position_in_line']})" : '') . "\n\n";
    if (isset($b['error'])) return $md . $b['error'] . "\n";
    if (isset($b['check_back_seconds'])) return $md . "Check again in {$b['check_back_seconds']} seconds.\n";
    foreach ($b['queries'] ?? [] as $q) {
        $md .= "## {$q['query']} -- {$q['hit_count']} hit" . ($q['hit_count'] === 1 ? '' : 's') . "\n\n"
             . "| Hit | % identity | Length | E-value | Bits | Description |\n|---|---|---|---|---|---|\n";
        foreach ($q['hits'] as $h) {
            $name = isset($h['gene_page']) ? "[{$h['hit']}]({$h['gene_page']})" : $h['hit'];
            $md .= "| $name | {$h['percent_identity']} | {$h['alignment_length']} | {$h['evalue']} | {$h['bitscore']} | "
                 . str_replace('|', '\\|', $h['description']) . " |\n";
        }
        $md .= "\n";
    }
    if (empty($b['queries'])) $md .= "No hits.\n\n";
    $md .= "Downloads (kept 24 hours): " . implode(' · ', array_map(fn($f, $u) => '[' . strtoupper($f) . "]($u)",
           array_keys($b['downloads'] ?? []), $b['downloads'] ?? [])) . "\n";
    return $md;
}
