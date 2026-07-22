<?php
/**
 * Glossary — single source of truth for MOOP term definitions.
 *
 * Definitions live in metadata/glossary.json (admin-editable via Manage Glossary),
 * NOT in this file. gloss('annotation') renders the word with a dashed underline
 * and a hover/focus popover carrying the definition — so a reader who knows the
 * concept by a different name can confirm it right where they are reading, without
 * any extra text added to the page.
 *
 * Because every dashed term pulls from that one file, definitions cannot drift
 * across pages, and an admin can change the site's vocabulary without touching
 * code. (Contrast today: "annotation" is written out in the search header popover,
 * the annotation-types modal, and js/modules/help-text.js — three copies that
 * already differ.)
 */

/**
 * All term => definition pairs from metadata/glossary.json.
 * Read once per request and cached; returns [] if the file is missing or invalid,
 * so a bad file degrades to plain words rather than breaking a page.
 */
function glossary_terms(): array {
    static $terms = null;
    if ($terms !== null) {
        return $terms;
    }

    // metadata_path is resolved via ConfigManager when available (view files call
    // gloss() well after config init); a __DIR__-relative fallback keeps the helper
    // usable from early CLI scripts that load this before ConfigManager.
    $file = null;
    if (class_exists('ConfigManager')) {
        try {
            $file = ConfigManager::getInstance()->getPath('metadata_path') . '/glossary.json';
        } catch (\Throwable $e) {
            $file = null;
        }
    }
    if ($file === null || !is_file($file)) {
        $file = __DIR__ . '/../metadata/glossary.json';
    }
    // Pre-setup clone: only the shipped template exists. Fall back to it so the
    // default vocabulary still works before metadata/glossary.json is created.
    if (!is_file($file) && is_file($file . '.example')) {
        $file .= '.example';
    }

    $loaded = function_exists('loadJsonFile')
        ? loadJsonFile($file, [])
        : (is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : []);

    // Normalize keys for case-insensitive lookup while preserving the admin's
    // chosen display spelling in the value's own key is not needed — gloss() shows
    // whatever the caller passes. Store lower-cased keys for matching.
    $terms = [];
    foreach ((array) $loaded as $k => $v) {
        if (is_string($v) && $v !== '') {
            $terms[strtolower((string) $k)] = $v;
        }
    }
    return $terms;
}

/**
 * Render a glossary term inline: the word, dashed-underlined, its definition on
 * hover or keyboard focus. Falls back to the plain word if the term is unknown,
 * so a typo never leaves a dead affordance on the page.
 *
 * @param string      $term    Glossary key (case-insensitive), e.g. 'annotation'.
 * @param string|null $display Text to show, if different from the key (e.g. a plural).
 */
function gloss(string $term, ?string $display = null): string {
    $label = $display ?? $term;
    $def   = glossary_terms()[strtolower($term)] ?? '';
    if ($def === '') {
        return htmlspecialchars($label);
    }
    return '<span class="gloss" tabindex="0" role="button"'
         . ' data-bs-toggle="popover" data-bs-trigger="hover focus"'
         . ' data-bs-placement="top"'
         . ' data-bs-title="' . htmlspecialchars($term, ENT_QUOTES) . '"'
         . ' data-bs-content="' . htmlspecialchars($def, ENT_QUOTES) . '">'
         . htmlspecialchars($label) . '</span>';
}
