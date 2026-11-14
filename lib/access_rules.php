<?php
/**
 * Who may see things that are not data sets themselves: content pages (now) and analyses (step 4).
 * Plan: notes/PERMISSIONS_PLAN.md. Data sets keep their own home (groups file + users.json).
 *
 * One file, {metadata_path}/access_rules.json -- SITE DATA, written by the web, snapshot to the
 * private backup. Only items that differ from their default are stored, so it stays short:
 *
 *   { "pages": { "unpublished-atlas": {"visible_to": "dataset", "organism": "...", "assembly": "...", "gene_set": "..."},
 *                "draft-faq":         {"visible_to": "admins"} } }
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
    if (!file_exists($file)) return ['ok' => true, 'pages' => []];
    $key = $file . "\0" . @filemtime($file) . "\0" . @filesize($file);
    if (isset($memo[$key])) return $memo[$key];
    $raw  = @file_get_contents($file);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        error_log("MOOP: $file is unreadable or not valid JSON -- every restricted item is shown to admins only until it is fixed");
        return $memo[$key] = ['ok' => false, 'pages' => []];
    }
    $pages = [];
    foreach ((array) ($data['pages'] ?? []) as $slug => $rule) {
        if (is_string($slug) && is_array($rule)) $pages[$slug] = moop_access_rule_normalise($rule);
    }
    return $memo[$key] = ['ok' => true, 'pages' => $pages];
}

/** A rule in its stored shape; anything unusable becomes "admins" (fail closed). */
function moop_access_rule_normalise(array $rule): array {
    $to = (string) ($rule['visible_to'] ?? '');
    if (!in_array($to, MOOP_ACCESS_SETTINGS, true)) return ['visible_to' => 'admins'];
    if ($to !== 'dataset') return ['visible_to' => $to];
    $safe = '/^[A-Za-z0-9_.-]+$/';
    $org = (string) ($rule['organism'] ?? ''); $asm = (string) ($rule['assembly'] ?? ''); $gs = (string) ($rule['gene_set'] ?? '');
    if (!preg_match($safe, $org) || !preg_match($safe, $asm) || ($gs !== '' && !preg_match($safe, $gs))) return ['visible_to' => 'admins'];
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
 * Written to a temp file and renamed; a write the web server cannot do is reported, never silent.
 * Refuses to overwrite a file it cannot read (that would drop every other rule in it).
 */
function moop_page_rule_set(string $slug, ?array $rule): array {
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug)) return ['ok' => false, 'error' => 'Not a page address.'];
    if ($rule !== null) {
        $norm = moop_access_rule_normalise($rule);
        if ($norm['visible_to'] !== ($rule['visible_to'] ?? null)) return ['ok' => false, 'error' => 'That setting, or its data set, is not valid.'];
        $rule = $norm['visible_to'] === 'everyone' ? null : $norm;
    }
    $file = moop_access_rules_file();
    $data = [];
    if (file_exists($file)) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data)) return ['ok' => false, 'error' => "$file cannot be read, so it was not changed."];
    }
    if ($rule === null && !isset($data['pages'][$slug])) return ['ok' => true, 'error' => null];   // nothing to clear: no write
    if ($rule === null) unset($data['pages'][$slug]); else $data['pages'][$slug] = $rule;
    if (isset($data['pages'])) {
        if (!$data['pages']) unset($data['pages']); else ksort($data['pages']);
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
