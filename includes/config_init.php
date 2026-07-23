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

// Load the ConfigManager class
require_once __DIR__ . '/ConfigManager.php';

// Initialize ConfigManager with config files
ConfigManager::getInstance()->initialize(
    __DIR__ . '/../config/site_config.php',
    __DIR__ . '/../config/tools_config.php'
);

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
 * Filename of the reference genome FASTA, which lives at the ASSEMBLY level and is shared
 * across that assembly's gene sets. Convenience wrapper — the genome is asked for far more
 * often than the other types, and it is the one whose location differs.
 */
function genome_fasta_filename(): string {
    return sequence_filename('genome') ?? 'genome.fa';
}

?>
