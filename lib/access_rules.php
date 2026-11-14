<?php
/**
 * Who may see things that are not data sets themselves: content pages and analyses.
 * Plan: notes/PERMISSIONS_PLAN.md. Data sets keep their own home (groups file + users.json).
 *
 * One file, {metadata_path}/access_rules.json -- SITE DATA, written by the web, snapshot to the
 * private backup. Only items that differ from their default are stored, so it stays short:
 *
 *   { "pages":    { "unpublished-atlas": {"visible_to": "dataset", "organism": "...", "assembly": "...", "gene_set": "..."},
 *                   "draft-faq":         {"visible_to": "admins"} },
 *     "analyses": { "expression:Org/asm/gs/Some_experiment": {"visible_to": "internal"} } }
 *
 * ANALYSES are keyed "kind:name" (MOOP_ANALYSIS_KINDS) and can only NARROW their data set: the
 * visitor must pass moop_dataset_visible() for the data set AND the analysis's rule. So an analysis
 * offers dataset (= no narrowing) / logged_in / internal / admins -- never "everyone". With no stored
 * rule an analysis gets its kind's default (moop_analysis_default()): an expression experiment's
 * bundle access_level, "dataset" for the rest.
 *
 * The settings (§2 of the plan):
 *   everyone   public                                   (default for a page)
 *   logged_in  collaborators, internal network, admins
 *   internal   internal network + admins
 *   admins     admins only
 *   dataset    whoever may see that organism/assembly[/gene set] (moop_dataset_visible)
 *
 * FAILS CLOSED: an unknown setting, a dataset rule without a usable data set, or an access_rules.json
 * that exists but cannot be read means "admins only" -- never back to "everyone".
 */

const MOOP_ACCESS_SETTINGS = ['everyone', 'logged_in', 'internal', 'admins', 'dataset'];

/**
 * The kinds of analysis and how each names one (the part after "kind:"):
 *   expression  {organism}/{assembly}/{gene_set}/{experiment folder}   (same as expression_overrides.json keys)
 *   images      {organism}/{analysis}     (gene_images.sqlite's analysis column; one file per organism)
 *   clade       {clade}                   (an OrthoFinder run; spans organisms, each row still needs its data set)
 */
const MOOP_ANALYSIS_KINDS    = ['expression', 'images', 'clade'];
const MOOP_ANALYSIS_SETTINGS = ['dataset', 'logged_in', 'internal', 'admins'];

function moop_access_rules_file(): string {
    return rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/access_rules.json';
}

/**
 * The stored rules: ['ok' => bool, 'pages' => [slug => rule]]. 'ok' false = the file exists but is
 * unreadable or not valid JSON -- callers then fail closed. Re-read when the file changes (a save in
 * this request, or the smoke tests).
 */
function moop_access_rules(): array {
    static $memo = [];
    $file = moop_access_rules_file();
    clearstatcache(true, $file);
    if (!file_exists($file)) return ['ok' => true, 'pages' => [], 'analyses' => []];
    $key = $file . "\0" . @filemtime($file) . "\0" . @filesize($file);
    if (isset($memo[$key])) return $memo[$key];
    $raw  = @file_get_contents($file);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        error_log("MOOP: $file is unreadable or not valid JSON -- every restricted item is shown to admins only until it is fixed");
        return $memo[$key] = ['ok' => false, 'pages' => [], 'analyses' => []];
    }
    $pages = [];
    foreach ((array) ($data['pages'] ?? []) as $slug => $rule) {
        if (is_string($slug) && is_array($rule)) $pages[$slug] = moop_access_rule_normalise($rule);
    }
    $analyses = [];
    foreach ((array) ($data['analyses'] ?? []) as $akey => $rule) {
        if (!is_string($akey)) continue;
        $to = is_array($rule) ? (string) ($rule['visible_to'] ?? '') : '';
        // An unusable analysis rule fails closed too -- including "everyone", which would widen.
        $analyses[$akey] = ['visible_to' => in_array($to, MOOP_ANALYSIS_SETTINGS, true) ? $to : 'admins'];
    }
    return $memo[$key] = ['ok' => true, 'pages' => $pages, 'analyses' => $analyses];
}

/** A rule in its stored shape; anything unusable becomes "admins" (fail closed). */
function moop_access_rule_normalise(array $rule): array {
    $to = (string) ($rule['visible_to'] ?? '');
    if (!in_array($to, MOOP_ACCESS_SETTINGS, true)) return ['visible_to' => 'admins'];
    if ($to !== 'dataset') return ['visible_to' => $to];
    $org = (string) ($rule['organism'] ?? ''); $asm = (string) ($rule['assembly'] ?? ''); $gs = (string) ($rule['gene_set'] ?? '');
    if (!moop_plain_name($org) || !moop_plain_name($asm) || ($gs !== '' && !moop_plain_name($gs))) return ['visible_to' => 'admins'];
    return ['visible_to' => 'dataset', 'organism' => $org, 'assembly' => $asm, 'gene_set' => $gs];
}

/** Does this viewer pass this (normalised) rule? */
function moop_access_rule_allows(array $rule): bool {
    $level = strtoupper((string) get_access_level());
    switch ($rule['visible_to'] ?? 'admins') {
        case 'everyone':  return true;
        case 'logged_in': return has_access('COLLABORATOR');
        case 'internal':  return in_array($level, ['IP_IN_RANGE', 'ADMIN'], true);
        case 'dataset':
            if ($rule['gene_set'] !== '') return moop_dataset_visible($rule['organism'], $rule['assembly'], $rule['gene_set']);
            $dir = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . "/{$rule['organism']}/{$rule['assembly']}";
            return is_dir($dir) && has_assembly_access($rule['organism'], $rule['assembly']);
        default:          return has_access('ADMIN');   // 'admins', and anything unforeseen
    }
}

/** A rule in plain words, for the admin pages (English, like every admin page). */
function moop_access_rule_label(array $rule): string {
    switch ($rule['visible_to'] ?? 'admins') {
        case 'everyone':  return 'Everyone';
        case 'logged_in': return 'Logged in';
        case 'internal':  return 'Internal network + admins';
        case 'dataset':   return isset($rule['organism'])
                              ? 'Same as data set: ' . implode(' / ', array_filter([$rule['organism'], $rule['assembly'], $rule['gene_set']], 'strlen'))
                              : 'Same as its data set';   // an analysis
        default:          return 'Admins only';
    }
}

/**
 * The data sets a "same as data set" rule can name, for the admin dialog: every organism / assembly /
 * gene set in the groups file (where data sets get their access), plus each assembly on its own.
 * [['value' => 'org|asm|gs', 'organism', 'label'], ...] sorted by organism.
 */
function moop_access_dataset_choices(): array {
    $file = rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/organism_assembly_groups.json';
    $out = [];
    foreach ((array) json_decode((string) @file_get_contents($file), true) as $e) {
        $org = (string) ($e['organism'] ?? ''); $asm = (string) ($e['assembly'] ?? ''); $gs = (string) ($e['gene_set'] ?? '');
        if ($org === '' || $asm === '') continue;
        $out["$org|$asm|"] = ['value' => "$org|$asm|", 'organism' => $org, 'label' => "$asm — whole assembly"];
        if ($gs !== '') $out["$org|$asm|$gs"] = ['value' => "$org|$asm|$gs", 'organism' => $org, 'label' => "$asm / $gs"];
    }
    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values($out);
}

/** The dialog's posted fields -> a rule for moop_*_rule_set() (validated there). */
function moop_access_rule_from_post(array $post): array {
    $to = (string) ($post['visible_to'] ?? '');
    if ($to !== 'dataset') return ['visible_to' => $to];
    [$org, $asm, $gs] = array_pad(explode('|', (string) ($post['dataset'] ?? ''), 3), 3, '');
    return ['visible_to' => 'dataset', 'organism' => $org, 'assembly' => $asm, 'gene_set' => $gs];
}

/** A content page's rule (the default, "everyone", when none is stored). */
function moop_page_rule(string $slug): array {
    $rules = moop_access_rules();
    if (!$rules['ok']) return ['visible_to' => 'admins'];
    return $rules['pages'][$slug] ?? ['visible_to' => 'everyone'];
}

/**
 * May this viewer see the content page $slug? Says nothing about whether the page exists --
 * readers use moop_page_for_visitor() (lib/content_pages.php), which answers both the same way.
 */
function moop_page_visible(string $slug): bool {
    return moop_access_rule_allows(moop_page_rule($slug));
}

/**
 * Set (or with null / "everyone", clear) a page's rule. Returns ['ok' => bool, 'error' => ?string].
 */
function moop_page_rule_set(string $slug, ?array $rule): array {
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug)) return ['ok' => false, 'error' => 'Not a page address.'];
    if ($rule !== null) {
        $norm = moop_access_rule_normalise($rule);
        if ($norm['visible_to'] !== ($rule['visible_to'] ?? null)) return ['ok' => false, 'error' => 'That setting, or its data set, is not valid.'];
        $rule = $norm['visible_to'] === 'everyone' ? null : $norm;
    }
    return moop_access_rules_write('pages', $slug, $rule);
}

// ---------------------------------------------------------------------------------------------
// Analyses
// ---------------------------------------------------------------------------------------------

/** Is $key a well-formed analysis key ("kind:name", a known kind, safe path parts, no "..")? */
function moop_analysis_key_valid(string $key): bool {
    if (!preg_match('/^([a-z]+):([A-Za-z0-9_.\/-]{1,300})$/', $key, $m)) return false;
    if (!in_array($m[1], MOOP_ANALYSIS_KINDS, true)) return false;
    foreach (explode('/', $m[2]) as $part) if ($part === '' || $part === '.' || $part === '..') return false;
    $parts = substr_count($m[2], '/') + 1;
    return ['expression' => 4, 'images' => 2, 'clade' => 1][$m[1]] === $parts;
}

/**
 * An old-style access level (PUBLIC / COLLABORATOR / IP_IN_RANGE / ADMIN, as expression bundles carry
 * it in experiment.json) as an analysis setting. PUBLIC = no narrowing ("dataset"). Missing or
 * unknown = admins, as before (required_access_level_value() fails closed the same way).
 */
function moop_access_level_to_setting(?string $level): string {
    switch (strtoupper(trim((string) $level))) {
        case 'PUBLIC':       return 'dataset';
        case 'COLLABORATOR': return 'logged_in';
        case 'IP_IN_RANGE':  return 'internal';
        default:             return 'admins';
    }
}

/**
 * An analysis's setting when access_rules.json says nothing about it. For an expression experiment
 * that is its bundle's access_level (experiment.json, written on the compute box); for the other kinds
 * "dataset". The expression tool passes the level it has already read, so a page reads each file once.
 */
function moop_analysis_default(string $key): string {
    if (!moop_analysis_key_valid($key)) return 'admins';
    [$kind, $name] = explode(':', $key, 2);
    if ($kind !== 'expression') return 'dataset';
    $parts = explode('/', $name);
    $file = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . "/{$parts[0]}/{$parts[1]}/{$parts[2]}/expression/{$parts[3]}/experiment.json";
    $meta = json_decode((string) @file_get_contents($file), true);
    return moop_access_level_to_setting(is_array($meta) ? (string) ($meta['access_level'] ?? '') : '');
}

/**
 * An analysis's rule: ['visible_to' => ..., 'set_here' => bool]. set_here false = its default.
 * $default: the kind's default when the caller has already read it (else moop_analysis_default()).
 */
function moop_analysis_rule(string $key, ?string $default = null): array {
    if (!moop_analysis_key_valid($key)) return ['visible_to' => 'admins', 'set_here' => false];
    $rules = moop_access_rules();
    if (!$rules['ok']) return ['visible_to' => 'admins', 'set_here' => false];
    if (isset($rules['analyses'][$key])) return $rules['analyses'][$key] + ['set_here' => true];
    $d = $default ?? moop_analysis_default($key);
    return ['visible_to' => in_array($d, MOOP_ANALYSIS_SETTINGS, true) ? $d : 'admins', 'set_here' => false];
}

/**
 * Does this viewer pass the analysis's OWN rule? Only half the answer: callers go through
 * moop_dataset_visible($org, $asm, $gs, $key), which also requires the data set.
 */
function moop_analysis_allows(string $key, ?string $default = null): bool {
    $rule = moop_analysis_rule($key, $default);
    return $rule['visible_to'] === 'dataset' ? true : moop_access_rule_allows($rule);
}

/**
 * Set an analysis's rule, or clear it with null. $default is the analysis's own default
 * (moop_analysis_default() unless given): a rule equal to it is not stored, so the file only lists
 * what an admin changed -- and clearing a rule hands the analysis back to its bundle.
 */
function moop_analysis_rule_set(string $key, ?array $rule, ?string $default = null): array {
    if (!moop_analysis_key_valid($key)) return ['ok' => false, 'error' => 'Not an analysis.'];
    if ($rule !== null) {
        $to = (string) ($rule['visible_to'] ?? '');
        if (!in_array($to, MOOP_ANALYSIS_SETTINGS, true)) return ['ok' => false, 'error' => 'An analysis can be limited to logged-in users, the internal network or admins -- never opened wider than its data set.'];
        $rule = $to === ($default ?? moop_analysis_default($key)) ? null : ['visible_to' => $to];
    }
    return moop_access_rules_write('analyses', $key, $rule);
}

/**
 * Write one rule into access_rules.json (null removes it). Written to a temp file and renamed; a write
 * the web server cannot do is reported, never silent. Refuses to overwrite a file it cannot read (that
 * would drop every other rule in it). A no-op clear does not write.
 */
function moop_access_rules_write(string $section, string $key, ?array $rule): array {
    $file = moop_access_rules_file();
    $data = [];
    if (file_exists($file)) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data)) return ['ok' => false, 'error' => "$file cannot be read, so it was not changed."];
    }
    if ($rule === null && !isset($data[$section][$key])) return ['ok' => true, 'error' => null];   // nothing to clear: no write
    if ($rule === null) unset($data[$section][$key]); else $data[$section][$key] = $rule;
    if (isset($data[$section])) {
        if (!$data[$section]) unset($data[$section]); else ksort($data[$section]);
    }
    $dir = dirname($file);
    $json = json_encode((object) $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp  = "$dir/.access_rules." . getmypid() . '.tmp';
    if ($json === false || @file_put_contents($tmp, $json . "\n") === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => "Could not write $file -- the web server cannot write there."];
    }
    @chmod($file, 0664);
    return ['ok' => true, 'error' => null];
}
