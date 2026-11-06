<?php
/**
 * CONFIGURATION INITIALIZATION
 * 
 * Single initialization point for all configuration.
 * Include this ONE TIME per page load, usually early in index.php or admin.php
 * 
 * Usage:
 *   include_once __DIR__ . '/config_init.php';
 *   
 *   // Then anywhere in your code:
 *   $config = ConfigManager::getInstance();
 *   $site_path = $config->getPath('site_path');
 *   $admin_email = $config->getString('admin_email');
 *   $tools = $config->getAllTools();
 *
 * SECURITY NOTE:
 * This file only loads configuration data. It does NOT:
 *   - Touch $_SESSION (user access control is separate)
 *   - Perform authentication (that's in access_control.php)
 *   - Validate user permissions (that's in helper functions)
 * Access control remains in access_control.php and is unaffected.
 */

// JSON helpers (loadJsonFile, loadJsonFileRequired, ...) are used pervasively.
// Load the dependency-free leaf here — the single early choke point every entry
// path hits (access_control -> config_init, admin_init, the jbrowse/api paths,
// and ConfigManager bootstrap below) — so the helpers are always in scope.
require_once __DIR__ . '/../lib/functions_json.php';

// Record deprecated calls (logs/deprecations/) -- this host's error_reporting hides them,
// so without this the first sign of a removed function would be a fatal after a PHP upgrade.
// Here at the same early choke point so every entry path is covered.
require_once __DIR__ . '/deprecation_log.php';

// Cache path helpers (moop_cache_dir_for, moop_organism_cache_file, ...). Loaded
// here at the single early choke point so every entry path — tools, admin, api,
// and standalone scripts (all of which include config_init) — can resolve where
// generated caches live without each rebuilding the path inline.
require_once __DIR__ . '/../lib/cache_paths.php';

// Glossary term definitions + the gloss() inline-help helper. Loaded at the same
// early choke point so gloss() is in scope for any view file that renders a term.
require_once __DIR__ . '/../lib/glossary.php';

// The other two on-page help affordances — field_help() for a single control and
// help_modal() for a set of things. Loaded here beside gloss() for the same reason:
// a view file must be able to call them without knowing what its controller included,
// or pages go back to hand-writing their own popover markup.
require_once __DIR__ . '/../lib/help_ui.php';
require_once __DIR__ . '/../lib/group_colors.php';   // moop_group_color(): one colour per group, site-wide

// Load the ConfigManager class
require_once __DIR__ . '/ConfigManager.php';

// Initialize ConfigManager with config files
ConfigManager::getInstance()->initialize(
    __DIR__ . '/../config/site_config.php',
    __DIR__ . '/../config/tools_config.php'
);

// Apply the site's time zone before anything formats a date. Done here because this is the
// one file every entry point (pages, AJAX endpoints, CLI scripts) passes through; set any
// later and the first date() call would already have used PHP's default. With php.ini
// silent, that default is UTC whatever the machine's clock says — which showed every time
// on the admin pages five hours ahead on a Central-time host.
moop_apply_timezone(ConfigManager::getInstance()->getString('timezone', ''));

// Validate configuration on boot (can be disabled in production with env var)
if (getenv('VALIDATE_CONFIG') !== 'false') {
    $config = ConfigManager::getInstance();
    if (!$config->validate()) {
        $errors = $config->getMissingKeys();
        error_log('Configuration validation errors: ' . json_encode($errors));
        // Continue anyway to avoid breaking the app, but log the issue
    }
}

/**
 * Filename of the gene-models GFF inside each gene_set directory
 * (organisms/{organism}/{assembly}/{gene_set}/). Single source of truth is the
 * 'genes_gff_filename' value in config/site_config.php — to rename the file across
 * the whole app (e.g. genomic.gff -> genes.gff), change only that value.
 */
function genes_gff_filename(): string {
    return ConfigManager::getInstance()->getString('genes_gff_filename', 'genes.gff');
}

/**
 * The per-organism annotation-search row cap, from site configuration.
 *
 * ONE home for the number. It used to appear as a literal in five places — the
 * cap test, two SQL LIMITs (necessarily cap+1), the "capped" flag in
 * tools/annotation_search_ajax.php, and the help text quoted to the user — so it
 * could not be changed safely, and the help would have gone on stating 2,500
 * whatever the search actually did. Everything derives from here now, which is
 * what lets the on-page help state the real number.
 *
 * Defined at this choke point rather than beside the queries because layout.php
 * emits it to JS on every page, and admin pages do not load database_queries.php.
 *
 * Admin-editable: Site Configuration -> Search Results Limit.
 */
function moop_search_results_limit(): int {
    static $limit = null;
    if ($limit === null) {
        $limit = ConfigManager::getInstance()->getInt('search_results_limit', 2500);
        // A zero or negative cap would return nothing at all; fall back rather than
        // let a bad config value silently empty every search on the site.
        if ($limit < 1) {
            $limit = 2500;
        }
    }
    return $limit;
}

/**
 * The search-results columns a site may show or hide by default (Site Configuration -> Search
 * results), each with its SHIPPED default: key => [label, shown by default, which view].
 * Mirrors RESULT_COLUMNS in js/modules/shared-results-table.js -- the smoke tests fail if the two
 * disagree. Select and Feature ID are not here: the row links and selections need them.
 */
function moop_result_columns(): array {
    return [
        'species'     => ['Species', false, 'all'],
        'assembly'    => ['Assembly', true, 'all'],
        'gene_set'    => ['Gene Set', false, 'all'],
        'type'        => ['Type', false, 'all'],
        'name'        => ['Name', true, 'all'],
        'description' => ['Description', true, 'all'],
        'ann_source'  => ['Annotation Source', true, 'one row per annotation'],
        'ann_id'      => ['Annotation ID', true, 'one row per annotation'],
        'ann_desc'    => ['Annotation Description', true, 'one row per annotation'],
        'matches'     => ['Matches', true, 'one row per gene'],
    ];
}

/**
 * This site's changes to which columns show by default: [key => bool], only where it differs from
 * the shipped default (Sofia, 2026-11-06: no Assembly column on cuttingclass). Empty = as shipped.
 * Unknown keys and non-booleans are dropped, so a stale setting can never hide a column by accident.
 */
function moop_result_column_overrides(): array {
    $saved = ConfigManager::getInstance()->getArray('search_results_columns', []);
    $cols  = moop_result_columns();
    $out   = [];
    foreach (is_array($saved) ? $saved : [] as $key => $shown) {
        if (isset($cols[$key]) && is_bool($shown) && $shown !== $cols[$key][1]) $out[$key] = $shown;
    }
    return $out;
}

/**
 * The SQL LIMIT for a capped search: one more row than the cap, so that
 * "more results exist" is detectable without a second COUNT query.
 */
function moop_search_query_limit(): int {
    return moop_search_results_limit() + 1;
}

/**
 * Filename for a configured sequence type, e.g. sequence_filename('genome') => 'genome.fa'.
 *
 * The same idea as genes_gff_filename() above, for the sequence FASTAs. These names are
 * admin-editable (Manage Site Configuration → sequence types), so nothing should hardcode
 * them or reach into the config array by hand.
 *
 * Callers currently do `$config->getSequenceTypes()[$k]['pattern']` inline in six places,
 * which is what this replaces. Worth noting one of them, functions_data.php:305, resolves
 * "is this the genome?" as `strpos($seq_config['pattern'], 'genome') !== false` — sniffing
 * the FILENAME for the word "genome". Rename the pattern in Site Configuration and that
 * silently stops recognising the genome. Ask by KEY, not by what the file happens to be
 * called.
 *
 * @param  string      $type Key from sequence_types: 'genome', 'protein', 'transcript', 'cds'
 * @return string|null The configured filename, or null if that type is not configured
 *                     (types can be turned off, so callers must handle null).
 */
function sequence_filename(string $type): ?string {
    $types = ConfigManager::getInstance()->getSequenceTypes();
    $pattern = $types[$type]['pattern'] ?? '';
    return $pattern !== '' ? $pattern : null;
}

/**
 * The configured sequence type a FASTA filename belongs to, e.g. 'protein.aa.fa' => 'protein'.
 * Null when the file is not one of the configured sequence FASTAs.
 */
function sequence_type_for_filename(string $filename): ?string {
    foreach (ConfigManager::getInstance()->getSequenceTypes() as $type => $seq_config) {
        if (($seq_config['pattern'] ?? '') !== '' && $seq_config['pattern'] === $filename) {
            return $type;
        }
    }
    return null;
}

/**
 * makeblastdb -dbtype for a sequence type: 'prot' for the protein type, 'nucl' otherwise.
 *
 * Decided by the type KEY. Every caller used to guess from the filename --
 * strpos($fasta, 'protein') -- which is right only while the protein pattern happens to
 * contain that word. Rename it in Site Configuration (say, to 'peptides.fa') and the
 * protein FASTA would be indexed as nucleotide, with no error until BLAST returned nothing.
 */
function sequence_type_blast_dbtype(string $type): string {
    return $type === 'protein' ? 'prot' : 'nucl';
}

/**
 * Filename of the reference genome FASTA, which lives at the ASSEMBLY level and is shared
 * across that assembly's gene sets. Convenience wrapper — the genome is asked for far more
 * often than the other types, and it is the one whose location differs.
 */
function genome_fasta_filename(): string {
    return sequence_filename('genome') ?? 'genome.fa';
}

/**
 * Is this tool switched on for this site? For the links one tool's page offers into ANOTHER
 * tool (the gene page's genome-browser link, BLAST's "Open Primer BLAST", ...): a button that
 * leads to a switched-off tool is left out, so what a page offers matches what the site offers
 * (Sofia, 2026-11-03). Shorthand for ConfigManager::isToolEnabled(); true unless switched off.
 */
// Defined HERE, not in lib/functions_tools.php: the navbar and the help modals that call it are
// drawn on pages that never load that file (About, login). Defined there, it took both pages down
// with a 500 for ~3 minutes on 2026-11-03. Every page loads this file before rendering anything.
function moop_tool_on(string $tool_id): bool {
    return ConfigManager::getInstance()->isToolEnabled($tool_id);
}

/**
 * Pick a language from an Accept-Language header: the highest-weighted entry that is one of
 * $codes, matching a region code exactly ('pt-br') or by its language ('es-MX' -> 'es'). Pure.
 * Returns '' when nothing matches.
 */
function moop_lang_negotiate(string $header, array $codes): string {
    $best = ''; $best_q = 0.0; $pos = 0;
    foreach (explode(',', strtolower($header)) as $part) {
        $pos++;
        $bits = array_map('trim', explode(';', $part));
        $tag  = $bits[0] ?? '';
        $q    = 1.0;
        foreach (array_slice($bits, 1) as $b) {
            if (strpos($b, 'q=') === 0) $q = (float) substr($b, 2);
        }
        $match = in_array($tag, $codes, true) ? $tag : (in_array(strtok($tag, '-'), $codes, true) ? strtok($tag, '-') : '');
        // Higher weight wins; on a tie the earlier entry wins (browsers list in preference order).
        if ($match !== '' && $q > $best_q) { $best = $match; $best_q = $q; }
    }
    return $best;
}

/**
 * The language this request is shown in -- cuttingclass step 5. On a site with ONE language
 * (simrbase) it is always 'en' and nothing else happens: no cookie, no header, no switch.
 * Otherwise, in order: ?lang= (the visitor's choice from the switch, remembered for a year in
 * the moop_lang cookie), the cookie, the browser's Accept-Language, English. Admin pages are
 * always English. Only codes the site actually uses are ever accepted.
 */
function moop_current_lang(): string {
    static $lang = null;
    if ($lang !== null) return $lang;
    $codes = ConfigManager::getInstance()->languageCodes();
    if (count($codes) < 2 || PHP_SAPI === 'cli' || defined('MOOP_ADMIN_PAGE')) return $lang = 'en';

    // Per site, like the session cookie: one site's language choice is not another's.
    if (!function_exists('moop_cookie_name')) require_once __DIR__ . '/session_init.php';
    $cookie = moop_cookie_name('moop_lang');
    $chosen = strtolower((string) ($_GET['lang'] ?? ''));
    if ($chosen !== '' && in_array($chosen, $codes, true)) {
        $lang = $chosen;
        if (!headers_sent()) {
            setcookie($cookie, $lang, ['expires' => time() + 365 * 86400, 'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
        }
    } elseif (in_array((string) ($_COOKIE[$cookie] ?? ''), $codes, true)) {
        $lang = (string) $_COOKIE[$cookie];
    } else {
        $lang = moop_lang_negotiate((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $codes) ?: 'en';
    }
    if (!headers_sent()) {
        // The same address answers in different languages: caches must key on what decided it.
        header('Vary: Accept-Language, Cookie', false);
        header('Content-Language: ' . $lang);
    }
    return $lang;
}

/** The current URL with ?lang=<code> set (for the language switch), other parameters kept. */
function moop_lang_url(string $code): string {
    $path  = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $query = $_GET;
    $query['lang'] = $code;
    return $path . '?' . http_build_query($query);
}

/**
 * Interface text in the visitor's language -- cuttingclass step 5b (notes/CUTTINGCLASS_PLAN.md).
 * Wrap the English as written: <?= t('Log In') ?>. The Spanish comes from the site's catalog
 * (Admin -> Manage Translations, lib/i18n.php); text with no translation shows in English, so
 * nothing ever renders blank. Placeholders are sprintf's: t('Showing %d of %d hits', $n, $total).
 *
 * On a one-language site (simrbase) and on admin pages this is the English, unchanged: no catalog
 * is read, nothing is recorded.
 *
 *   t()        HTML-escaped (template and arguments) -- for page text and attributes.
 *   t_plain()  not escaped -- for code that escapes for itself (the help helpers, JS via
 *              json_encode, headers).
 *   t_markup() the template escaped, the arguments inserted AS GIVEN -- for a sentence with
 *              markup inside it ('across %s organisms' with a <strong>). The caller escapes them.
 *
 * Escaping uses ENT_COMPAT, not ENT_QUOTES: attributes here are double-quoted, and an
 * apostrophe ("MOOP's") must come out as the same bytes it was before it was wrapped.
 */
function t(string $text, ...$args): string {
    $e = fn($s) => htmlspecialchars((string) $s, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    return moop_i18n_format($e(moop_i18n_text($text)), array_map($e, $args), $text, $e);
}

function t_plain(string $text, ...$args): string {
    return moop_i18n_format(moop_i18n_text($text), $args, $text, fn($s) => $s);
}

function t_markup(string $text, string ...$html_args): string {
    $e = fn($s) => htmlspecialchars((string) $s, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    return moop_i18n_format($e(moop_i18n_text($text)), $html_args, $text, $e);
}

/** The text to show for $english: the catalog's, when this request is in another language. */
function moop_i18n_text(string $english): string {
    if (moop_current_lang() === 'en') {
        // Still recorded on a multi-language site, so the spreadsheet can list what is shown.
        if (count(ConfigManager::getInstance()->languageCodes()) > 1 && PHP_SAPI !== 'cli' && !defined('MOOP_ADMIN_PAGE')) {
            require_once __DIR__ . '/../lib/i18n.php';
            moop_i18n_seen($english);
        }
        return $english;
    }
    require_once __DIR__ . '/../lib/i18n.php';
    return moop_i18n_lookup(moop_current_lang(), $english);
}

/**
 * sprintf the arguments in. A translation whose placeholders do not fit (the import refuses those,
 * but a hand-edited file could still have one) falls back to the English rather than a 500.
 */
function moop_i18n_format(string $template, array $args, string $english, callable $escape): string {
    if (!$args) return $template;
    try {
        return vsprintf($template, $args);
    } catch (\ValueError | \ArgumentCountError $e) {
        return vsprintf($escape($english), $args);
    }
}
