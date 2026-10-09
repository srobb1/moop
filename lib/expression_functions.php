<?php
/**
 * Gene expression from TPM tables.
 *
 * Contract: notes/EXPRESSION_BUNDLE_SPEC.md. In short:
 *
 *   organisms/{Org}/{Asm}/{GeneSet}/expression/{experiment}/   an "expression bundle":
 *       counts.tsv, tpm.tsv, samples.tsv, experiment.json, [qc.tsv], [provenance.json]
 *
 * Bundles are built on the compute box and copied over whole; the web server only READS them,
 * straight from disk — there is no database. Data that arrives any other way (a directory of
 * per-sample htseq-count files, say) is turned into a bundle by a converter first
 * (scripts/expression_import_htseq.php), so a change in how data is delivered never reaches the
 * pages.
 *
 * The parse/match functions take plain arrays and paths and never touch ConfigManager or the
 * session, so tests/smoke_tests.php can drive them on a made-up gene set.
 *
 * A missing value (NA, empty) is read as null and is NOT zero: "not measured" and "measured,
 * not expressed" are different answers to "is this gene expressed?".
 */

const MOOP_EXPRESSION_DEFAULT_THRESHOLD = 1.0;   // TPM

/**
 * Read a gene × sample matrix (counts.tsv or tpm.tsv): header row of sample ids, one row per id.
 * $integers: counts.tsv — every value must be a whole number. ".gz" is read transparently.
 *
 * @return array{samples: string[], rows: array<string, array<int, float|null>>}
 * @throws RuntimeException on a malformed file — a half-read matrix must never be loaded.
 */
function moop_expression_read_matrix(string $path, bool $integers = false): array
{
    $fh = @fopen(str_ends_with($path, '.gz') ? "compress.zlib://$path" : $path, 'r');
    if (!$fh) throw new RuntimeException("cannot read $path");

    $header  = null;
    $samples = null;
    $rows    = [];
    $line_no = 0;
    while (($line = fgets($fh)) !== false) {
        $line_no++;
        $line = rtrim($line, "\r\n");
        if ($line === '' || $line[0] === '#') continue;
        $cells = array_map('moop_expression_unquote', explode("\t", $line));

        if ($header === null) {
            $header = $cells;
            continue;
        }
        if ($samples === null) {
            // Normally the header's first cell names the id column (its text is ignored). R's
            // write.table() leaves that cell out, so the header is one cell SHORTER than the
            // data rows and every header cell is a sample. Decided once, on the first data row.
            $samples = count($cells) === count($header) + 1 ? $header : array_slice($header, 1);
        }
        if (count($cells) !== count($samples) + 1) {
            fclose($fh);
            throw new RuntimeException("$path line $line_no: " . (count($cells) - 1)
                . " values, header has " . count($samples) . " samples");
        }

        $id = $cells[0];
        if ($id === '') {
            fclose($fh);
            throw new RuntimeException("$path line $line_no: empty id");
        }
        if (isset($rows[$id])) {
            fclose($fh);
            throw new RuntimeException("$path line $line_no: id '$id' appears twice");
        }

        $vals = [];
        foreach (array_slice($cells, 1) as $i => $cell) {
            if ($cell === '' || strcasecmp($cell, 'NA') === 0 || strcasecmp($cell, 'NaN') === 0) {
                $vals[$i] = null;
            } elseif ($integers ? ctype_digit($cell) : (is_numeric($cell) && (float)$cell >= 0)) {
                $vals[$i] = (float)$cell;
            } else {
                fclose($fh);
                throw new RuntimeException("$path line $line_no: '{$cell}' for sample '{$samples[$i]}' is not "
                    . ($integers ? 'a read count (a whole number)' : 'a non-negative value'));
            }
        }
        $rows[$id] = $vals;
    }
    fclose($fh);

    if ($header === null) throw new RuntimeException("$path: no header row");
    if (empty($rows))     throw new RuntimeException("$path: no data rows");
    if (empty($samples))  throw new RuntimeException("$path: no sample columns");
    if (count(array_unique($samples)) !== count($samples)) {
        throw new RuntimeException("$path: a sample name appears twice in the header");
    }
    return ['samples' => $samples, 'rows' => $rows];
}

/**
 * Read a directory of per-sample htseq-count files into the same shape as a matrix.
 *
 * Each file: "id<TAB>count" per line. htseq-count's trailing "__no_feature", "__ambiguous", …
 * counters are not genes; they are returned separately so the build can report them (a sample
 * where most reads are __no_feature was counted against the wrong annotation).
 *
 * Sample name = file name minus a .txt/.tsv/.counts/.htseq/.gz-free extension, in natural order
 * (".2" before ".10"). An id missing from one file is null for that sample, not 0.
 *
 * @return array{samples: string[], rows: array<string, array<int, float|null>>, special: array<string, array<string,int>>}
 */
function moop_expression_read_count_dir(string $dir): array
{
    $files = array_values(array_filter(glob("$dir/*") ?: [], 'is_file'));
    if (!$files) throw new RuntimeException("$dir: no count files");
    natsort($files);
    $files = array_values($files);

    $samples = []; $special = []; $per_sample = [];
    foreach ($files as $i => $file) {
        $name = preg_replace('/\.(txt|tsv|counts?|htseq)$/i', '', basename($file));
        if (isset($special[$name])) throw new RuntimeException("$dir: two files give sample name '$name'");
        $samples[$i] = $name;
        $special[$name] = [];
        $counts = [];
        $line_no = 0;
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line_no++;
            if (trim($line) === '' || $line[0] === '#') continue;
            $cells = array_map('moop_expression_unquote', explode("\t", rtrim($line, "\r")));
            if (count($cells) !== 2) throw new RuntimeException(basename($file) . " line $line_no: expected 'id<TAB>count'");
            [$id, $n] = $cells;
            if (!ctype_digit($n)) throw new RuntimeException(basename($file) . " line $line_no: '$n' is not a read count");
            if (str_starts_with($id, '__')) { $special[$name][$id] = (int)$n; continue; }
            if (isset($counts[$id])) throw new RuntimeException(basename($file) . " line $line_no: id '$id' appears twice");
            $counts[$id] = (int)$n;
        }
        if (!$counts) throw new RuntimeException(basename($file) . ": no counts");
        $per_sample[$i] = $counts;
    }

    $rows = [];
    $all_ids = array_keys(array_merge(...array_map(fn($c) => array_fill_keys(array_keys($c), true), $per_sample)));
    foreach ($all_ids as $id) {
        $id = (string)$id;
        foreach ($per_sample as $i => $counts) {
            $rows[$id][$i] = isset($counts[$id]) ? (float)$counts[$id] : null;
        }
    }
    return ['samples' => $samples, 'rows' => $rows, 'special' => $special];
}

/**
 * Exonic length of every transcript and gene, from the gene set's exon_coords.tsv.
 *
 * Gene length is the UNION of its transcripts' exons — the same model htseq-count's default
 * "union" mode counts reads over — so overlapping exons are not counted twice.
 * exon_coords.tsv columns: transcript, chrom, strand, "start,end", "s,e;s,e;…" (1-based, inclusive).
 *
 * @param array $transcripts transcript uniquename => gene uniquename
 * @return array<string,int> feature uniquename => length in bases
 */
function moop_expression_feature_lengths(string $exon_coords, array $transcripts): array
{
    $fh = @fopen($exon_coords, 'r');
    if (!$fh) throw new RuntimeException("cannot read $exon_coords");
    $len = []; $gene_blocks = [];
    while (($line = fgets($fh)) !== false) {
        $c = explode("\t", rtrim($line, "\r\n"));
        if (count($c) < 5) continue;
        $tx = $c[0];
        $total = 0;
        foreach (explode(';', $c[4]) as $block) {
            [$s, $e] = array_map('intval', explode(',', $block) + [1 => 0]);
            if ($e < $s) continue;
            $total += $e - $s + 1;
            // Kept as a string, not [s, e] arrays: ~290k exons as PHP arrays exceed 128 MB.
            if (isset($transcripts[$tx])) $gene_blocks[$transcripts[$tx]] = ($gene_blocks[$transcripts[$tx]] ?? '') . "$s,$e;";
        }
        $len[$tx] = $total;
    }
    fclose($fh);

    foreach ($gene_blocks as $gene => $packed) {
        $blocks = array_map(fn($b) => array_map('intval', explode(',', $b)), explode(';', rtrim($packed, ';')));
        usort($blocks, fn($a, $b) => $a[0] <=> $b[0]);
        $total = 0; [$cs, $ce] = $blocks[0];
        foreach (array_slice($blocks, 1) as [$s, $e]) {
            if ($s <= $ce + 1) { $ce = max($ce, $e); continue; }
            $total += $ce - $cs + 1; [$cs, $ce] = [$s, $e];
        }
        $len[$gene] = $total + $ce - $cs + 1;
    }
    return $len;
}

/**
 * Raw counts → TPM, per sample:  rate = count / length;  TPM = rate / Σ rate × 10^6.
 *
 * Only features with a known length enter the sum; the rest come back in 'no_length' and get no
 * TPM. A null count stays null.
 *
 * @param array $rows    feature uniquename => counts per sample
 * @param array $lengths feature uniquename => bases
 * @return array{rows: array<string, array<int, float|null>>, no_length: string[]}
 */
function moop_expression_counts_to_tpm(array $rows, array $lengths): array
{
    $rate = []; $no_length = []; $sum = [];
    foreach ($rows as $f => $counts) {
        $f = (string)$f;
        if (empty($lengths[$f])) { $no_length[] = $f; continue; }
        foreach ($counts as $i => $n) {
            $r = $n === null ? null : $n / $lengths[$f];
            $rate[$f][$i] = $r;
            if ($r !== null) $sum[$i] = ($sum[$i] ?? 0.0) + $r;
        }
    }
    $out = [];
    foreach ($rate as $f => $rs) {
        foreach ($rs as $i => $r) {
            $out[$f][$i] = ($r === null || empty($sum[$i])) ? ($r === null ? null : 0.0) : $r / $sum[$i] * 1e6;
        }
    }
    return ['rows' => $out, 'no_length' => $no_length];
}

function moop_expression_unquote(string $cell): string
{
    $cell = trim($cell);
    if (strlen($cell) >= 2 && $cell[0] === '"' && substr($cell, -1) === '"') {
        $cell = substr($cell, 1, -1);
    }
    return $cell;
}

/**
 * Read samples.tsv and check it against the matrix.
 *
 * Row order is display order. Columns: sample_id, group, optional replicate; every other column
 * (stage, tissue, run_accession, track_key, …) is kept as an attribute — stored, not interpreted,
 * so a new column in the bundle needs no code change.
 * Without the file every sample is its own group, in matrix order.
 *
 * @return array{samples: list<array{name:string, group:string, replicate:string, attrs:array}>, warnings: string[]}
 */
function moop_expression_read_samples(?string $path, array $matrix_samples): array
{
    if ($path === null || !is_file($path)) {
        $out = [];
        foreach ($matrix_samples as $name) {
            $out[] = ['name' => $name, 'group' => $name, 'replicate' => '', 'attrs' => []];
        }
        return ['samples' => $out, 'warnings' => ['no samples.tsv — each sample is its own group']];
    }

    $lines = array_values(array_filter(
        array_map(fn($l) => rtrim($l, "\r\n"), file($path)),
        fn($l) => $l !== '' && $l[0] !== '#'
    ));
    if (empty($lines)) throw new RuntimeException("$path: empty");

    $header = array_map(fn($c) => strtolower(moop_expression_unquote($c)), explode("\t", array_shift($lines)));
    $col_sample = array_search('sample_id', $header, true);
    $col_group  = array_search('group', $header, true);
    if ($col_sample === false || $col_group === false) {
        throw new RuntimeException("$path: header must have 'sample_id' and 'group' columns");
    }
    $col_rep = array_search('replicate', $header, true);

    $known = array_flip($matrix_samples);
    $seen  = [];
    $out   = [];
    foreach ($lines as $n => $line) {
        $cells = array_map('moop_expression_unquote', explode("\t", $line));
        $name  = $cells[$col_sample] ?? '';
        if (!isset($known[$name])) {
            throw new RuntimeException("$path row " . ($n + 2) . ": sample '$name' is not in the matrix");
        }
        if (isset($seen[$name])) {
            throw new RuntimeException("$path row " . ($n + 2) . ": sample '$name' listed twice");
        }
        $seen[$name] = true;

        $attrs = [];
        foreach ($header as $i => $col) {
            if ($i === $col_sample || $i === $col_group || $i === $col_rep) continue;
            if (($cells[$i] ?? '') !== '') $attrs[$col] = $cells[$i];
        }
        $group = $cells[$col_group] ?? '';
        $out[] = [
            'name'      => $name,
            'group'     => $group !== '' ? $group : $name,
            'replicate' => $col_rep !== false ? ($cells[$col_rep] ?? '') : '',
            'attrs'     => $attrs,
        ];
    }

    $missing = array_diff($matrix_samples, array_keys($seen));
    if ($missing) {
        throw new RuntimeException("$path: samples in the matrix but not listed: " . implode(', ', array_slice($missing, 0, 5))
            . (count($missing) > 5 ? ' …(' . count($missing) . ' total)' : ''));
    }
    return ['samples' => $out, 'warnings' => []];
}

/**
 * Match a table's ids to the gene set's features.
 *
 * $genes:       gene uniquename => true
 * $transcripts: transcript uniquename => parent gene uniquename
 *
 * Exact first; then with a trailing version (".1") stripped on BOTH sides, but only where the
 * stripped form names exactly one feature — a guess that could land on the wrong gene is worse
 * than a miss. The level (gene or transcript) is whichever matches more ids.
 *
 * @return array{level: string, map: array<string,string>, exact: int, by_version: int, unmatched: string[]}
 */
function moop_expression_match_ids(array $ids, array $genes, array $transcripts): array
{
    $strip = fn(string $s) => preg_replace('/\.\d+$/', '', $s);

    $attempt = function (array $targets) use ($ids, $strip) {
        $stripped = [];
        foreach (array_keys($targets) as $u) {
            $k = $strip((string)$u);
            $stripped[$k] = isset($stripped[$k]) ? false : (string)$u;   // false = ambiguous
        }
        $map = []; $exact = 0; $by_version = 0; $unmatched = [];
        foreach ($ids as $id) {
            $id = (string)$id;
            if (isset($targets[$id])) {
                $map[$id] = $id; $exact++;
            } elseif (($hit = $stripped[$strip($id)] ?? false) !== false) {
                $map[$id] = $hit; $by_version++;
            } else {
                $unmatched[] = $id;
            }
        }
        return ['map' => $map, 'exact' => $exact, 'by_version' => $by_version, 'unmatched' => $unmatched];
    };

    $g = $attempt($genes);
    $t = $attempt($transcripts);
    return count($t['map']) > count($g['map'])
        ? ['level' => 'transcript'] + $t
        : ['level' => 'gene'] + $g;
}

/**
 * Collapse one feature's sample values to group means, in display order.
 *
 * A group's mean ignores null replicates; a group with no measured replicate is null.
 *
 * @return list<array{group: string, mean: float|null, n: int}>
 */
function moop_expression_group_means(array $samples, array $vals): array
{
    $groups = [];
    foreach ($samples as $i => $s) {
        $g = $s['group_name'];
        $groups[$g] ??= ['group' => $g, 'sum' => 0.0, 'n' => 0];
        $v = $vals[$i] ?? null;
        if ($v !== null) { $groups[$g]['sum'] += $v; $groups[$g]['n']++; }
    }
    $out = [];
    foreach ($groups as $g) {
        $out[] = ['group' => $g['group'], 'mean' => $g['n'] ? $g['sum'] / $g['n'] : null, 'n' => $g['n']];
    }
    return $out;
}

/**
 * The yes/no answer for one feature in one experiment.
 *
 * 'yes'     — some group's mean reaches the experiment's threshold
 * 'no'      — measured, and no group reaches it
 * 'unknown' — no row for this feature, or every value null
 *
 * @return array{call: string, top_group: ?string, top_mean: ?float, threshold: float}
 */
function moop_expression_call(array $experiment, ?array $vals): array
{
    $threshold = (float)$experiment['detect_threshold'];
    $res = ['call' => 'unknown', 'top_group' => null, 'top_mean' => null, 'threshold' => $threshold];
    if ($vals === null) return $res;

    foreach (moop_expression_group_means($experiment['samples'], $vals) as $g) {
        if ($g['mean'] === null) continue;
        if ($res['top_mean'] === null || $g['mean'] > $res['top_mean']) {
            $res['top_mean'] = $g['mean'];
            $res['top_group'] = $g['group'];
        }
    }
    if ($res['top_mean'] !== null) {
        $res['call'] = $res['top_mean'] >= $threshold ? 'yes' : 'no';
    }
    return $res;
}

// ============================================================================
// The gene page's answer: detected yes/no + one word per experiment
// ============================================================================

/**
 * Words for a gene's highest condition, in TPM (user, 2026-10-09: "yes/no and off/low/medium/high").
 *
 * Not "low/high relative to other genes" — fixed cut-offs, so the same word means the same thing
 * on every gene page. 'off' is exactly the experiment's own "not detected" (below its threshold),
 * so the word can never contradict the yes/no.
 */
const MOOP_EXPRESSION_LEVELS = [   // lower bound (TPM) => word, checked highest first
    100.0 => 'high',
    10.0  => 'medium',
];

function moop_expression_level(array $call): string
{
    if ($call['call'] === 'unknown') return 'no data';
    if ($call['call'] === 'no')      return 'off';
    foreach (MOOP_EXPRESSION_LEVELS as $min => $word) {
        if ($call['top_mean'] >= $min) return $word;
    }
    return 'low';
}

/**
 * The gene page's summary from experiments + this gene's values. Pure — no I/O — so the tests
 * drive it directly.
 *
 * @param array $experiments id => ['label', 'meta', 'detect_threshold', 'samples' => [['group_name', …]]]
 * @param array $values      id => vals in sample order (absent = this gene is not in that experiment)
 */
function moop_expression_summarize(array $experiments, array $values): array
{
    $out = ['experiments' => [], 'detected' => 0, 'with_data' => 0];
    foreach ($experiments as $eid => $e) {
        $call  = moop_expression_call($e, $values[$eid] ?? null);
        $level = moop_expression_level($call);
        if ($call['call'] !== 'unknown') $out['with_data']++;
        if ($call['call'] === 'yes')     $out['detected']++;
        $out['experiments'][] = [
            'groups'    => isset($values[$eid]) ? moop_expression_group_means($e['samples'], $values[$eid]) : [],
            'label'     => $e['label'],
            'citation'  => (string)($e['meta']['citation'] ?? ''),
            'call'      => $call['call'],
            'level'     => $level,
            'top_group' => $call['top_group'],
            'top_mean'  => $call['top_mean'],
            'threshold' => $call['threshold'],
        ];
    }
    return $out;
}

/**
 * Bars for one gene in one experiment: each condition's average, in display order.
 *
 * Bars, never a line — a line says "continuous between neighbours", true of a developmental
 * series and false of control/Cd/Cu/Hg/Zn (user, 2026-10-09). The pattern sparkline is the
 * Planosphere idea; the level pill beside it carries the magnitude.
 *
 * Scaled to this gene's highest condition, but never to less than MOOP_EXPRESSION_SPARK_FLOOR:
 * otherwise a silent gene's 0.1 vs 0.5 TPM would draw as dramatically as a real 1 vs 265.
 * An "off" row gets no bars at all, for the same reason. A condition with no measurement gets
 * no bar and says so in its hover; a measured zero gets a hairline, so the two stay distinct.
 *
 * @param array $groups from moop_expression_group_means()
 * @return string inline SVG, or '' when there is nothing honest to draw
 */
const MOOP_EXPRESSION_SPARK_FLOOR = 10.0;   // TPM

function moop_expression_sparkline_svg(array $groups, string $level, string $unit = 'TPM'): string
{
    if (!$groups || in_array($level, ['off', 'no data'], true)) return '';

    $w = 120; $h = 22; $gap = 2;
    $n = count($groups);
    $bw = max(2.0, ($w - $gap * ($n - 1)) / $n);
    $w  = (int)ceil($bw * $n + $gap * ($n - 1));
    $max = MOOP_EXPRESSION_SPARK_FLOOR;
    foreach ($groups as $g) if ($g['mean'] !== null && $g['mean'] > $max) $max = $g['mean'];

    $fmt = fn(float $v) => $v < 10 ? number_format($v, 1) : number_format($v, 0);
    $label = [];
    $bars  = '';
    foreach (array_values($groups) as $i => $g) {
        $x = round($i * ($bw + $gap), 2);
        if ($g['mean'] === null) {
            $tip = $g['group'] . ': no data';
            $bars .= sprintf('<rect class="expr-bar-none" x="%s" y="0" width="%s" height="%d"><title>%s</title></rect>',
                             $x, round($bw, 2), $h, htmlspecialchars($tip, ENT_QUOTES));
        } else {
            $bh  = max(1.0, round($g['mean'] / $max * $h, 2));   // a measured zero is a hairline
            $tip = $g['group'] . ': ' . $fmt($g['mean']) . " $unit";
            // The hit area is the full column, so a short bar is as easy to hover as a tall one.
            $bars .= sprintf('<g><title>%s</title><rect class="expr-bar-hit" x="%s" y="0" width="%s" height="%d"/>'
                           . '<rect class="expr-bar" x="%s" y="%s" width="%s" height="%s"/></g>',
                             htmlspecialchars($tip, ENT_QUOTES), $x, round($bw, 2), $h, $x, round($h - $bh, 2), round($bw, 2), $bh);
        }
        $label[] = $tip;
    }
    return sprintf('<svg class="expr-spark" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="%s">%s</svg>',
                   $w, $h, $w, $h, htmlspecialchars(implode('; ', $label), ENT_QUOTES), $bars);
}

// ============================================================================
// Reading bundles straight from disk — notes/EXPRESSION_BUNDLE_SPEC.md
// ============================================================================
//
// The web server only READS. Bundles are built on the compute box and copied over whole; nothing
// here writes. Any experiment that cannot be read is skipped and logged, so a half-copied folder
// never takes a gene page down — but access control fails CLOSED (see the overrides loader).

/** Where a gene set's bundles live: {organism_data}/{org}/{asm}/{gene_set}/expression */
function moop_expression_bundle_dir(string $organism, string $assembly, string $gene_set): string
{
    return rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . "/$organism/$assembly/$gene_set/expression";
}

/**
 * Web-side overrides of experiment.json fields (spec §Overrides). Lives in MOOP's metadata, never in
 * the bundle, so a clean `rsync --delete` of the bundles can never undo it.
 *
 * @return array|null  [] when there is no file; NULL when the file exists but cannot be read — the
 *                     caller must then show NOTHING: an unreadable file that restricted an experiment
 *                     would otherwise silently put it back to its bundle default (usually PUBLIC).
 */
function moop_expression_load_overrides(string $file): ?array
{
    if (!file_exists($file)) return [];
    $raw  = @file_get_contents($file);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        error_log("expression: $file is unreadable or not a JSON object — hiding all expression data until it is fixed");
        return null;
    }
    return $data;
}

/**
 * Fields a web-side override may replace — the same convention as config_editable.json over
 * site_config.php (ConfigManager::$editableConfigKeys): an allowlist, and an empty value means
 * "keep the default". Unlike ConfigManager, a key outside the list is not silently dropped from
 * view: scripts/check_expression.php reports it (moop_expression_override_problems()).
 */
const MOOP_EXPRESSION_OVERRIDABLE = ['label', 'access_level', 'detect_threshold', 'summary', 'citation'];

function moop_expression_apply_override(array $meta, array $override): array
{
    foreach (MOOP_EXPRESSION_OVERRIDABLE as $k) {
        if (array_key_exists($k, $override) && $override[$k] !== '' && $override[$k] !== null) {
            $meta[$k] = $override[$k];
        }
    }
    return $meta;
}

/**
 * Everything wrong with an overrides file for one gene set — for the checker, never the page.
 *
 * @param string[] $experiment_slugs  slugs that exist under this gene set
 * @return string[] one line per problem
 */
function moop_expression_override_problems(array $overrides, string $key_prefix, array $experiment_slugs): array
{
    $problems = [];
    $exists = array_flip($experiment_slugs);
    foreach ($overrides as $key => $ov) {
        if (!str_starts_with((string)$key, $key_prefix)) continue;
        $slug = substr((string)$key, strlen($key_prefix));
        if (!isset($exists[$slug])) $problems[] = "override for '$key' — no such experiment (left over after a reload?)";
        if (!is_array($ov)) { $problems[] = "override for '$key' is not an object"; continue; }
        foreach (array_keys($ov) as $field) {
            if (!in_array($field, MOOP_EXPRESSION_OVERRIDABLE, true)) {
                $problems[] = "override for '$key' sets '$field', which cannot be overridden (allowed: " . implode(', ', MOOP_EXPRESSION_OVERRIDABLE) . ')';
            }
        }
    }
    return $problems;
}

/**
 * The experiments of one gene set that this reader may see, in folder-name order.
 *
 * Each needs experiment.json (with a label), samples.tsv and tpm.tsv whose header lists exactly the
 * samples in samples.tsv. Overrides for "{$key_prefix}{slug}" replace experiment.json fields.
 *
 * @param array|null $overrides from moop_expression_load_overrides(); null = fail closed
 * @return array<int, array> index => ['slug','label','access_level','detect_threshold','meta','samples','tpm_file','tpm_columns']
 */
function moop_expression_bundle_experiments(string $dir, string $key_prefix, string $user_level, ?array $overrides): array
{
    require_once __DIR__ . '/functions_access.php';
    if ($overrides === null || !is_dir($dir)) return [];
    $granted = granted_access_level_value($user_level);

    $dirs = glob("$dir/*", GLOB_ONLYDIR) ?: [];
    sort($dirs, SORT_STRING);
    $out = [];
    foreach ($dirs as $d) {
        $slug = basename($d);
        try {
            $meta = json_decode((string)@file_get_contents("$d/experiment.json"), true);
            if (!is_array($meta)) throw new RuntimeException('experiment.json missing or not valid JSON');
            $ov = $overrides[$key_prefix . $slug] ?? [];
            if (!is_array($ov)) throw new RuntimeException('its override entry is not an object');
            $meta = moop_expression_apply_override($meta, $ov);
            if (trim((string)($meta['label'] ?? '')) === '') throw new RuntimeException('experiment.json has no label');

            // Access first: an experiment this reader may not see is not even opened.
            $access = (string)($meta['access_level'] ?? '');
            if ($granted < required_access_level_value($access)) continue;

            $tpm = "$d/tpm.tsv";
            $fh  = @fopen($tpm, 'r');
            if (!$fh) throw new RuntimeException('no readable tpm.tsv');
            $header = explode("\t", rtrim((string)fgets($fh), "\r\n"));
            fclose($fh);
            $columns = array_slice($header, 1);
            if (!$columns) throw new RuntimeException('tpm.tsv has no sample columns');

            if (!is_file("$d/samples.tsv")) throw new RuntimeException('no samples.tsv');
            $samples = moop_expression_read_samples("$d/samples.tsv", $columns)['samples'];
            $col_of = array_flip($columns);

            $out[] = [
                'slug'             => $slug,
                'label'            => (string)$meta['label'],
                'access_level'     => $access,
                'detect_threshold' => (float)($meta['detect_threshold'] ?? MOOP_EXPRESSION_DEFAULT_THRESHOLD),
                'meta'             => $meta,
                'samples'          => array_map(fn($x) => [
                    'name' => $x['name'], 'group_name' => $x['group'], 'replicate' => $x['replicate'], 'attrs' => $x['attrs'],
                ], $samples),
                'tpm_file'         => $tpm,
                // tpm.tsv column for each sample, in samples.tsv (display) order
                'tpm_columns'      => array_map(fn($x) => $col_of[$x['name']] + 1, $samples),
            ];
        } catch (RuntimeException $e) {
            error_log("expression: skipping $d — " . $e->getMessage());
        }
    }
    return $out;
}

/**
 * Values for some features in one experiment, in samples.tsv order.
 *
 * One pass over tpm.tsv, stopping once every requested feature is found (~1 ms for a 1 MB file).
 * A feature not in the file is simply absent from the result — "no data", never zero.
 *
 * @throws RuntimeException on a malformed row — the caller skips the experiment.
 * @return array<string, array<int, float|null>>
 */
function moop_expression_bundle_values(array $experiment, array $features): array
{
    $want = array_fill_keys(array_map('strval', $features), true);
    $out  = [];
    if (!$want) return $out;
    $fh = @fopen($experiment['tpm_file'], 'r');
    if (!$fh) throw new RuntimeException("cannot read {$experiment['tpm_file']}");
    fgets($fh);   // header
    $width = null;
    while (($line = fgets($fh)) !== false) {
        $tab = strpos($line, "\t");
        if ($tab === false) continue;
        $id = substr($line, 0, $tab);
        if (!isset($want[$id])) continue;

        $cells = explode("\t", rtrim($line, "\r\n"));
        $width ??= count($cells);
        $vals  = [];
        foreach ($experiment['tpm_columns'] as $c) {
            $cell = $cells[$c] ?? null;
            if ($cell === null) { fclose($fh); throw new RuntimeException("{$experiment['tpm_file']}: row $id is short"); }
            if ($cell === 'NA' || $cell === '') { $vals[] = null; continue; }
            if (!is_numeric($cell) || (float)$cell < 0) { fclose($fh); throw new RuntimeException("{$experiment['tpm_file']}: row $id has '$cell'"); }
            $vals[] = (float)$cell;
        }
        $out[$id] = $vals;
        unset($want[$id]);
        if (!$want) break;
    }
    fclose($fh);
    return $out;
}

/**
 * Everything the gene page's Expression section needs, read from the bundles.
 *
 * @return array|null null = show no section (no bundles, nothing visible, or overrides unreadable)
 */
function moop_expression_gene_page(string $organism, string $assembly, string $gene_set, string $gene, string $user_level): ?array
{
    $dir = moop_expression_bundle_dir($organism, $assembly, $gene_set);
    if (!is_dir($dir)) return null;
    $overrides = moop_expression_load_overrides(
        rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/expression_overrides.json');

    $experiments = moop_expression_bundle_experiments($dir, "$organism/$assembly/$gene_set/", $user_level, $overrides);
    $values = [];
    foreach ($experiments as $i => $e) {
        try {
            $v = moop_expression_bundle_values($e, [$gene]);
            if (isset($v[$gene])) $values[$i] = $v[$gene];
        } catch (RuntimeException $ex) {
            error_log("expression: skipping {$e['slug']} — " . $ex->getMessage());
            unset($experiments[$i]);
        }
    }
    return $experiments ? moop_expression_summarize($experiments, $values) : null;
}
