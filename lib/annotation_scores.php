<?php
/**
 * What an annotation's Score means, and how to show it.
 *
 * WHY THIS EXISTS
 *
 * feature_annotation.score holds many kinds of number under one column heading: E-values
 * (lower is better), profile match scores (higher is better), a SignalP probability, a count
 * of membrane segments, OMA's relationship as a CODE (1 = 1:1 ... 4 = many:many), the
 * closest-gene TIER (1-7), the number of species sharing a duplication. Several kinds sit in
 * the same table — the Orthologs table puts "4" (many:many) beside "2e-16" (EggNOG) — so no
 * note above a table can explain its column: the meaning changes row by row.
 *
 * So the meaning travels with the cell. Each source maps to ONE kind; the table formats every
 * score from it (words where the number is a code), puts one plain sentence on hover, and
 * lists the kinds present in the table's (i) block.
 *
 * THE LOOKUP TABLE IS EDITABLE: metadata/annotation_scores.json, edited on Admin > Manage
 * Annotations ("Score meanings"), so a new analysis needs no code change (user, 2026-10-06).
 * The shipped default is metadata/annotation_scores.json.example; it is read until the admin
 * first saves. What stays in code is only HOW each display style formats a number.
 *
 *   kinds: { id: { label, display, explanation, [unit, unit_plural], [words: {"1": "1:1"}] } }
 *   rules: [ { [source], [type], kind } ]   `source` matches the START of the source name,
 *          `type` the whole annotation type, both case-insensitive; a rule naming both needs both.
 *          Two tiers: rules WITH a source are checked first, in file order, first match wins;
 *          rules with only a type are FALLBACKS ("anything else in Domains is an E-value"),
 *          checked only after every source rule. A fallback therefore can never shadow a
 *          specific rule, wherever it sits in the file — when it was one ordered list, moving
 *          "Domains -> E-value" above "ProSiteProfiles -> profile score" would have silently
 *          made every ProSite score read as an E-value.
 *
 * Where each default kind comes from in the pipeline (config/build_and_load_db/analysis_parsers/):
 *   E-values: DIAMOND, MMseqs2, EggNOG, InterProScan member databases; profile scores:
 *   InterProScan ProSiteProfiles/Hamap (column 9); SignalP SP(Sec/SPI) probability; ProtNLM
 *   prediction score; DeepTMHMM segment count; OMA relationship (OmaHogOrthologs.pm
 *   %RELATIONSHIP_SCORE); OMA paralogs' species count; closest-gene tier/rank
 *   (assign_gene_names_v2.pl header).
 *
 * A source no rule matches keeps the raw number — and is listed on the admin page, so a new
 * analysis is noticed rather than silently shown as a bare number.
 */

/** The display styles a kind can use. Code, not data: each one is a formatting rule. */
const MOOP_SCORE_DISPLAYS = [
    'evalue'  => 'E-value (short scientific: 2.7e-5)',
    'number'  => 'Number (one decimal)',
    'percent' => 'Percent (0–1 shown as 0–100%)',
    'count'   => 'Count with a unit (7 segments)',
    'words'   => 'Words for codes (1 → 1:1)',
    'none'    => 'No score (—)',
];

/** The score lookup table: the admin's file, else the shipped default. */
function moop_score_config_file(): string {
    $dir = null;
    if (class_exists('ConfigManager')) {
        try { $dir = ConfigManager::getInstance()->getPath('metadata_path'); } catch (\Throwable $e) { $dir = null; }
    }
    return ($dir ?: __DIR__ . '/../metadata') . '/annotation_scores.json';
}

function moop_score_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = moop_score_config_file();
    if (!is_file($file) && is_file($file . '.example')) {
        $file .= '.example';
    }
    if (!is_file($file)) {
        $file = __DIR__ . '/../metadata/annotation_scores.json.example';
    }
    $loaded = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $cfg = moop_score_config_normalize(is_array($loaded) ? $loaded : []);
    return $cfg;
}

/** Only well-formed entries; anything else is dropped rather than half-applied. */
function moop_score_config_normalize(array $c): array {
    $kinds = [];
    foreach ((array)($c['kinds'] ?? []) as $id => $k) {
        if (!is_array($k) || !isset(MOOP_SCORE_DISPLAYS[$k['display'] ?? ''])) continue;
        $kinds[(string)$id] = [
            'label'       => (string)($k['label'] ?? $id),
            'display'     => (string)$k['display'],
            'explanation' => (string)($k['explanation'] ?? ''),
            'unit'        => (string)($k['unit'] ?? ''),
            'unit_plural' => (string)($k['unit_plural'] ?? ($k['unit'] ?? '')),
            'words'       => array_map('strval', (array)($k['words'] ?? [])),
        ];
    }
    $rules = [];
    foreach ((array)($c['rules'] ?? []) as $r) {
        if (!is_array($r) || !isset($kinds[$r['kind'] ?? ''])) continue;
        $source = trim((string)($r['source'] ?? ''));
        $type   = trim((string)($r['type'] ?? ''));
        if ($source === '' && $type === '') continue;     // would match everything
        $rules[] = ['source' => $source, 'type' => $type, 'kind' => (string)$r['kind']];
    }
    return ['kinds' => $kinds, 'rules' => $rules];
}

/** The kind id for a source, or '' when no rule matches. */
function moop_score_kind(string $annotation_type, string $source, ?array $cfg = null): string {
    $cfg = $cfg ?? moop_score_config();
    foreach ([true, false] as $with_source) {           // source rules, then fallbacks
        foreach ($cfg['rules'] as $r) {
            if (($r['source'] !== '') !== $with_source) continue;
            if ($r['source'] !== '' && stripos($source, $r['source']) !== 0) continue;
            if ($r['type'] !== '' && strcasecmp($annotation_type, $r['type']) !== 0) continue;
            return $r['kind'];
        }
    }
    return '';
}

/**
 * Format one score cell.
 *
 * @return array{display:string, order:string, export:string, title:string}
 *   display — escaped HTML; order — the sort key (data-order, the stored number); export —
 *   what a download writes (the full number, or the words where it is only a code); title —
 *   the hover sentence.
 */
function moop_format_score($score, string $kind_id, ?array $cfg = null): array {
    $cfg  = $cfg ?? moop_score_config();
    $kind = $cfg['kinds'][$kind_id] ?? null;
    $dash = '<span class="text-muted">—</span>';
    $title = $kind['explanation'] ?? '';

    if ($score === null || $score === '' || ($kind && $kind['display'] === 'none')) {
        return ['display' => $dash, 'order' => '', 'export' => '', 'title' => $title];
    }
    $raw = (string)$score;
    $plain = ['display' => htmlspecialchars($raw), 'order' => $raw, 'export' => $raw, 'title' => $title];
    if ($kind === null || !is_numeric($score)) {
        return $plain;
    }
    $n = (float)$score;
    $i = (int)round($n);

    switch ($kind['display']) {
        case 'evalue':
            if ($n == 0.0) {
                $shown = '0';
            } elseif ($n >= 0.01 && $n < 1000) {
                $shown = rtrim(rtrim(sprintf('%.3f', $n), '0'), '.');
            } else {
                $shown = preg_replace('/e([+-])0*(\d)/', 'e$1$2', sprintf('%.1e', $n));   // 2.7e-05 -> 2.7e-5
                $shown = str_replace('e+', 'e', $shown);
            }
            return ['display' => htmlspecialchars($shown)] + $plain;
        case 'number':
            return ['display' => htmlspecialchars(rtrim(rtrim(sprintf('%.1f', $n), '0'), '.'))] + $plain;
        case 'percent':
            // One decimal: 0.9994 is "99.9%", not a rounded-up "100%" that claims certainty.
            return ['display' => htmlspecialchars(preg_replace('/\.0$/', '', sprintf('%.1f', $n * 100)) . '%')] + $plain;
        case 'count':
            $unit = $i === 1 ? $kind['unit'] : $kind['unit_plural'];
            return ['display' => htmlspecialchars(trim($i . ' ' . $unit))] + $plain;
        case 'words':
            // A code without words is shown as stored, never hidden.
            $words = $kind['words'][(string)$i] ?? null;
            if ($words === null || (float)$i !== $n) return $plain;
            return ['display' => htmlspecialchars($words), 'export' => $words] + $plain;
    }
    return $plain;
}

/**
 * The "Reading the Score column" list for a table: one entry per kind present, naming its
 * sources. Built from the rows actually in the table, so it never describes an absent source.
 *
 * @param array $rows  annotation rows (annotation_source_name, annotation_type)
 * @return string HTML, or '' when no row carries a known kind
 */
function moop_score_help_html(array $rows, ?array $cfg = null): string {
    $cfg = $cfg ?? moop_score_config();
    $by_kind = [];
    foreach ($rows as $r) {
        $source = (string)($r['annotation_source_name'] ?? '');
        $kind = moop_score_kind((string)($r['annotation_type'] ?? ''), $source, $cfg);
        if ($kind === '' || ($cfg['kinds'][$kind]['explanation'] ?? '') === '') continue;
        // OMA sources repeat per partner species ("(HUMAN)"): name the family once.
        $by_kind[$kind][preg_replace('/\s*\([A-Z]{5}\)$/', '', $source)] = true;
    }
    if (!$by_kind) return '';

    $html = '<div class="score-help mt-2"><strong>Reading the Score column</strong><ul class="mb-0">';
    foreach ($cfg['kinds'] as $id => $k) {       // in the file's kind order
        if (empty($by_kind[$id])) continue;
        $html .= '<li><span class="score-help-sources">' . htmlspecialchars(implode(', ', array_keys($by_kind[$id]))) . '</span>: '
               . htmlspecialchars($k['explanation']) . '</li>';
    }
    return $html . '</ul></div>';
}
