<?php
/**
 * SCORE MEANINGS — the controller half of the "Score meanings" card on Manage Annotations.
 *
 * Edits metadata/annotation_scores.json: what the Score column means for each annotation
 * source, and how the gene page shows it (lib/annotation_scores.php has the format and the
 * reasoning). Lives on Manage Annotations because that is where an admin goes when adding an
 * analysis; kept in its own file so that page does not grow another 200 lines.
 *
 * Included by admin/manage_annotations.php after admin_init.php (auth + CSRF on every POST).
 * POST-redirect-GET, like Manage Glossary — but unlike it, a failed save is REPORTED: an
 * unchecked write that says "saved" is the recurring silent-failure shape on this site.
 */

require_once __DIR__ . '/../lib/annotation_scores.php';
require_once __DIR__ . '/../lib/cache_paths.php';

$score_file = moop_score_config_file();
$score_file_write_error = getFileWriteError($score_file);
// The admin's file, else the shipped default — edited from whichever is in force, so the
// first save writes the defaults out together with the change.
$score_raw = loadJsonFile(is_file($score_file) ? $score_file : $score_file . '.example', []);
if (!is_array($score_raw)) $score_raw = [];
$score_raw['kinds'] = (array)($score_raw['kinds'] ?? []);
$score_raw['rules'] = array_values((array)($score_raw['rules'] ?? []));

$score_action = (string)($_POST['_score_action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $score_action !== '') {
    $flash = null;
    $kinds = &$score_raw['kinds'];
    $rules = &$score_raw['rules'];
    $idx   = (int)($_POST['rule_index'] ?? -1);

    // "1 = 1:1" per line -> ["1" => "1:1"]
    $parse_words = static function (string $text): array {
        $words = [];
        foreach (preg_split('/\R/', $text) as $line) {
            if (preg_match('/^\s*(-?\d+)\s*[=:→]\s*(.+?)\s*$/u', $line, $m)) $words[$m[1]] = $m[2];
        }
        return $words;
    };
    $rule_from_post = static function (): array {
        return array_filter([
            'source' => trim((string)($_POST['source'] ?? '')),
            'type'   => trim((string)($_POST['type'] ?? '')),
            'kind'   => trim((string)($_POST['kind'] ?? '')),
        ], 'strlen');
    };

    if ($score_file_write_error) {
        $flash = ['type' => 'danger', 'msg' => 'The score file is not writable; nothing was saved.'];

    } elseif ($score_action === 'kind_save') {
        $id = trim((string)($_POST['kind_id'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $display = (string)($_POST['display'] ?? '');
        if ($id === '') {        // a new kind: an id from its label
            $id = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
            if (isset($kinds[$id])) $id = '';
        }
        if ($id === '' || $label === '' || !isset(MOOP_SCORE_DISPLAYS[$display])) {
            $flash = ['type' => 'danger', 'msg' => 'A kind needs a label (not already used) and a display style.'];
        } else {
            $kinds[$id] = array_filter([
                'label'       => $label,
                'display'     => $display,
                'unit'        => trim((string)($_POST['unit'] ?? '')),
                'unit_plural' => trim((string)($_POST['unit_plural'] ?? '')),
                'words'       => $display === 'words' ? $parse_words((string)($_POST['words'] ?? '')) : [],
                'explanation' => trim((string)($_POST['explanation'] ?? '')),
            ], static fn($v) => $v !== '' && $v !== []);
            $flash = ['type' => 'success', 'msg' => 'Saved the kind "' . $label . '".'];
        }

    } elseif ($score_action === 'kind_delete') {
        $id = (string)($_POST['kind_id'] ?? '');
        $used = array_filter($rules, static fn($r) => ($r['kind'] ?? '') === $id);
        if ($used) {
            $flash = ['type' => 'danger', 'msg' => count($used) . ' rule(s) still use this kind. Change or delete them first.'];
        } else {
            unset($kinds[$id]);
            $flash = ['type' => 'success', 'msg' => 'Deleted the kind.'];
        }

    } elseif ($score_action === 'rule_add' || $score_action === 'rule_save') {
        $rule = $rule_from_post();
        if (!isset($rule['kind'], $kinds[$rule['kind']]) || (!isset($rule['source']) && !isset($rule['type']))) {
            $flash = ['type' => 'danger', 'msg' => 'A rule needs a kind, and a source or a type (or both).'];
        } elseif ($score_action === 'rule_save' && isset($rules[$idx])) {
            $rules[$idx] = $rule;
            $flash = ['type' => 'success', 'msg' => 'Saved rule ' . ($idx + 1) . '.'];
        } else {
            // A new SOURCE rule goes above the first type-wide rule: rules are first-match, so
            // added at the end it would sit under "Domains -> E-value" and never apply. A
            // type-only rule goes at the end.
            $at = count($rules);
            if (isset($rule['source'])) {
                foreach ($rules as $i => $r) {
                    if (empty($r['source'])) { $at = $i; break; }
                }
            }
            array_splice($rules, $at, 0, [$rule]);
            $flash = ['type' => 'success', 'msg' => 'Added a rule at position ' . ($at + 1) . '.'];
        }

    } elseif ($score_action === 'rule_move' && isset($rules[$idx])) {
        $to = $idx + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
        if (isset($rules[$to])) {
            [$rules[$idx], $rules[$to]] = [$rules[$to], $rules[$idx]];
        }
        $flash = ['type' => 'success', 'msg' => 'Moved the rule.'];

    } elseif ($score_action === 'rule_delete' && isset($rules[$idx])) {
        array_splice($rules, $idx, 1);
        $flash = ['type' => 'success', 'msg' => 'Deleted the rule.'];
    }
    unset($kinds, $rules);

    if (($flash['type'] ?? '') === 'success') {
        $score_raw['rules'] = array_values($score_raw['rules']);
        if (saveJsonFile($score_file, $score_raw) === false) {
            $flash = ['type' => 'danger', 'msg' => 'Could not write ' . $score_file . ' — nothing was saved. Check that the web server can write to the metadata directory.'];
        }
    }
    $_SESSION['score_flash'] = $flash;
    header('Location: manage_annotations.php#score-meanings');
    exit;
}

$score_flash = $_SESSION['score_flash'] ?? null;
unset($_SESSION['score_flash']);

// Every source on the site that NO rule explains — what a new analysis looks like before
// anyone describes it. Read from the per-organism source caches (housekeeping keeps them),
// not from 85 databases.
$score_cfg = moop_score_config_normalize($score_raw);
$score_unmatched = [];
$score_source_total = 0;
// For the rules' Type dropdown: every annotation type the site has, plus any a rule names
// (so a rule for a type not loaded yet keeps its value), plus Protein Features — the
// pipeline's new type for SignalP, DeepTMHMM and DeepLoc (2026-10-06), before it is loaded.
$score_types = ['Protein Features' => true];
foreach ($score_raw['rules'] as $__r) {
    if (!empty($__r['type'])) $score_types[(string)$__r['type']] = true;
}
$organisms_dir = $config->getPath('organism_data');
foreach (glob("$organisms_dir/*/organism.sqlite") ?: [] as $__db) {
    $__cache = moop_annotation_sources_cache_file(basename(dirname($__db)));
    $__by_type = $__cache !== '' && is_file($__cache) ? json_decode((string)file_get_contents($__cache), true) : null;
    foreach ((array)$__by_type as $__type => $__sources) {
        foreach ((array)$__sources as $__s) {
            $__name = (string)($__s['name'] ?? '');
            if ($__name === '') continue;
            $score_source_total++;
            $score_types[(string)$__type] = true;
            if (moop_score_kind((string)$__type, $__name, $score_cfg) === '') {
                $score_unmatched["$__type\x1f$__name"] = ['type' => (string)$__type, 'source' => $__name];
            }
        }
    }
}
ksort($score_unmatched);
$score_types = array_keys($score_types);
natcasesort($score_types);
$score_types = array_values($score_types);
$score_unmatched = array_values($score_unmatched);
