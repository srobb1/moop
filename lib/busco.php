<?php
/**
 * BUSCO completeness for the gene set page (and the assembly page, for a genome-mode run).
 *
 * The contract is notes/BUSCO_GENE_SET_SPEC.md: the annotation pipeline normalises BUSCO's own
 * output into busco.json (analysis_parsers/parse_BUSCO_to_MOOP_JSON.pl) and copy2moop_v2.sh ships
 * it beside geneset.json. MOOP never parses BUSCO's files, so a BUSCO upgrade never touches this.
 *
 * Read defensively all the same: the file is produced elsewhere, and a page must never fail or
 * show a half-drawn bar because one is malformed. A run missing any number is dropped; a file
 * with no usable run is treated as no file -- which means no card, never an error.
 */

/** Roles in the order the pipeline writes them, with what each means to a reader. */
const MOOP_BUSCO_ROLES = [
    'auto-selected' => 'Best-fit lineage',
    'fixed'         => 'Fixed lineage',
    'generic'       => 'Broadest lineage',
];

/**
 * Load and validate {dir}/busco.json.
 *
 * @return array{runs: list<array>, warnings: list<string>}|null  null = no file, or nothing usable.
 */
function moop_load_busco(string $dir): ?array {
    $file = rtrim($dir, '/') . '/busco.json';
    if (!is_file($file)) {
        return null;
    }
    $doc = json_decode((string)@file_get_contents($file), true);
    if (!is_array($doc) || !is_array($doc['runs'] ?? null)) {
        error_log("MOOP: unreadable busco.json at $file");
        return null;
    }

    $runs = [];
    foreach ($doc['runs'] as $run) {
        if (!is_array($run) || !is_string($run['lineage'] ?? null) || $run['lineage'] === '') {
            continue;
        }
        $clean = [
            'lineage'      => $run['lineage'],
            'role'         => is_string($run['role'] ?? null) ? $run['role'] : '',
            'version'      => is_scalar($run['version'] ?? null) ? (string)$run['version'] : '',
            'mode'         => is_string($run['mode'] ?? null) ? $run['mode'] : '',
            'dataset_date' => is_string($run['dataset_date'] ?? null) ? $run['dataset_date'] : '',
            'date'         => is_string($run['date'] ?? null) ? $run['date'] : '',
        ];
        $ok = true;
        foreach (['complete', 'single', 'duplicated', 'fragmented', 'missing'] as $k) {
            if (!is_numeric($run[$k] ?? null) || !is_numeric($run[$k . '_n'] ?? null)) {
                $ok = false;
                break;
            }
            $clean[$k]        = (float)$run[$k];
            $clean[$k . '_n'] = (int)$run[$k . '_n'];
        }
        if (!$ok || !is_numeric($run['n'] ?? null) || (int)$run['n'] <= 0) {
            continue;
        }
        $clean['n'] = (int)$run['n'];
        $runs[] = $clean;
    }
    if (!$runs) {
        error_log("MOOP: busco.json at $file has no usable runs");
        return null;
    }

    $warnings = array_values(array_filter($doc['warnings'] ?? [], fn($w) => is_string($w) && trim($w) !== ''));
    return ['runs' => $runs, 'warnings' => $warnings];
}

/**
 * BUSCO's own one-line notation, e.g. C:84.1%[S:77.3%,D:6.8%],F:5.3%,M:10.6%,n:954 -- what a
 * reader pastes into a paper or compares with a published figure.
 */
function moop_busco_notation(array $run): string {
    $p = fn(float $v) => number_format($v, 1) . '%';
    return 'C:' . $p($run['complete']) . '[S:' . $p($run['single']) . ',D:' . $p($run['duplicated'])
         . '],F:' . $p($run['fragmented']) . ',M:' . $p($run['missing']) . ',n:' . $run['n'];
}
