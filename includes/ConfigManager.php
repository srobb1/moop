<?php
/**
 * ConfigManager - Centralized Configuration Management
 * 
 * Provides type-safe, cached access to all application configuration.
 * Manages both application settings and tool registry.
 * 
 * Usage:
 *   include_once __DIR__ . '/config_init.php';
 *   $config = ConfigManager::getInstance();
 *   $site_path = $config->getPath('site_path');
 *   $admin_email = $config->getString('admin_email');
 *   $tools = $config->getAllTools();
 * 
 * SECURITY NOTE:
 * This class handles CONFIGURATION DATA ONLY. It does not:
 *   - Touch user sessions ($_SESSION)
 *   - Perform authentication or authorization
 *   - Validate user permissions
 * Access control remains in includes/access_control.php and is unaffected.
 */

class ConfigManager
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Loaded configuration data
     */
    private $config = [];

    /**
     * Loaded tool registry
     */
    private $tools = [];

    /**
     * Configuration loading state
     */
    private $loaded = false;

    /** site_config.php as shipped, before this site's settings are laid over it. */
    private $shipped = [];

    /**
     * Settings that MERGE with their shipped value rather than replace it (see initialize()), so
     * "equal to the default" is not a simple comparison for them: never pruned.
     */
    const MERGED_KEYS = ['sequence_types', 'jbrowse2', 'blast_linkouts', 'turnstile'];

    /**
     * Validation errors
     */
    private $errors = [];

    /**
     * Required configuration keys
     */
    private $requiredKeys = [
        'root_path',
        'site',
        'site_path',
        'organism_data',
        'metadata_path',
        'siteTitle',
        'admin_email',
    ];

    /**
     * Editable configuration keys - whitelisted keys that can be edited via admin UI
     * Used in both initialize() and saveEditableConfig() to ensure consistency
     */
    /**
     * Keys that config_editable.json is allowed to override.
     *
     * A key missing from this list is SILENTLY IGNORED on load: the admin page saves it
     * to config_editable.json, saveEditableConfig() strips it back out (line ~506 uses
     * this same list), and the setting simply never takes effect — no error anywhere.
     * Adding a setting to the admin UI therefore means adding it HERE too, or the new
     * control does nothing and looks like it worked.
     */
    private $editableConfigKeys = ['link_prefix', 'ontologies', 'siteTitle', 'admin_email', 'sequence_types', 'header_img', 'favicon_filename', 'auto_login_ip_ranges', 'sample_feature_ids', 'home_search_examples', 'blast_sample_sequences', 'blast_num_threads', 'blast_memory_budget_mb', 'blast_max_concurrent', 'blast_queue_max_wait', 'blast_max_runtime', 'blast_max_output_mb', 'blast_max_hits', 'search_results_limit', 'search_results_columns', 'blast_linkouts', 'tracks_server', 'jbrowse2', 'site_data_path', 'cache_path', 'users_file', 'turnstile', 'footer', 'timezone', 'rate_limit_heavy', 'tools_disabled', 'tools_layout', 'primer_maker', 'crosswalk_display', 'main_menu', 'languages', 'languages_retired', 'home', 'theme_colors', 'technical_panels'];

    /**
     * Private constructor - use getInstance() instead
     */
    private function __construct() {}

    /**
     * Get singleton instance
     * 
     * @return ConfigManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize configuration from files
     * 
     * @param string $site_config_path Path to site_config.php
     * @param string $tools_config_path Path to tools_config.php
     * @return bool True if initialization successful
     */
    public function initialize($site_config_path, $tools_config_path)
    {
        if ($this->loaded) {
            return true; // Already loaded
        }

        // Load site configuration
        if (!file_exists($site_config_path)) {
            $this->errors[] = "Site config not found: $site_config_path";
            return false;
        }

        $this->config = include $site_config_path;
        if (!is_array($this->config)) {
            $this->errors[] = "Site config must return an array";
            return false;
        }
        $this->shipped = $this->config;

        // Load editable configuration (overrides defaults from site_config.php)
        $editable_config_path = dirname($site_config_path) . '/config_editable.json';
        if (file_exists($editable_config_path)) {
            $editable_config = loadJsonFile($editable_config_path, []);
            if (is_array($editable_config)) {
                // Use the centralized whitelist
                foreach ($this->editableConfigKeys as $key) {
                    // Only override if value is set AND non-empty (preserve site_config defaults for empty values)
                    if (isset($editable_config[$key]) && ($editable_config[$key] !== '' && $editable_config[$key] !== null)) {
                        // Special handling for nested arrays - deep merge to preserve defaults
                        if (in_array($key, ['sequence_types', 'jbrowse2', 'blast_linkouts']) && is_array($editable_config[$key]) && is_array($this->config[$key] ?? [])) {
                            foreach ($editable_config[$key] as $sub_key => $sub_value) {
                                if ($key === 'sequence_types' && isset($this->config[$key][$sub_key])) {
                                    $this->config[$key][$sub_key] = array_merge($this->config[$key][$sub_key], $sub_value);
                                } else {
                                    $this->config[$key][$sub_key] = $sub_value;
                                }
                            }
                        } elseif ($key === 'turnstile' && is_array($editable_config[$key])) {
                            // The SECRET only ever comes from secrets.php (via site_config.php).
                            // Saving Site Configuration used to copy it into this file, where it
                            // then overrode secrets.php — a rotated secret was silently ignored.
                            $ts = $editable_config[$key];
                            unset($ts['secret_key']);
                            // An EMPTY site key saved from the form means "not set here" -- the same
                            // rule as every other setting -- so a site key kept in secrets.php is not
                            // blanked by an empty box (cuttingclass, 2026-11-09).
                            if (trim((string) ($ts['site_key'] ?? '')) === '') unset($ts['site_key']);
                            $this->config[$key] = array_merge($this->config[$key] ?? [], $ts);
                        } else {
                            $this->config[$key] = $editable_config[$key];
                        }
                    }
                }
            }
        }

        // Load tool configuration
        if (!file_exists($tools_config_path)) {
            $this->errors[] = "Tools config not found: $tools_config_path";
            return false;
        }

        $this->tools = include $tools_config_path;
        if (!is_array($this->tools)) {
            $this->errors[] = "Tools config must return an array";
            return false;
        }

        $this->loaded = true;
        return true;
    }

    /**
     * Ensure configuration is loaded
     * 
     * @return bool True if loaded
     */
    private function ensureLoaded()
    {
        if (!$this->loaded) {
            trigger_error('ConfigManager not initialized. Call initialize() first.', E_USER_WARNING);
            return false;
        }
        return true;
    }

    /**
     * Get configuration value
     *
     * Dot notation reaches into nested settings: 'jbrowse2.trusted_tracks_servers'. Every typed
     * getter goes through here, so they all accept it. Until 2026-10-23 only getBoolean() did;
     * getArray('jbrowse2.trusted_tracks_servers') returned [] while the configured list held the
     * tracks server, and nothing said so. A literal key with a dot in it still wins.
     *
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     */
    public function get($key, $default = null)
    {
        if (!$this->ensureLoaded()) {
            return $default;
        }

        // Special handling: construct favicon_path from favicon_filename
        if ($key === 'favicon_path') {
            $site = $this->config['site'] ?? '';
            $images_dir = $this->config['images_dir'] ?? 'images';
            $favicon_filename = $this->config['favicon_filename'] ?? 'favicon.ico';
            return moop_url_base() . "/$images_dir/$favicon_filename";
        }

        if (array_key_exists($key, $this->config) || strpos($key, '.') === false) {
            return $this->config[$key] ?? $default;
        }
        $value = $this->config;
        foreach (explode('.', $key) as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    /**
     * Get configuration value as path (must be string)
     * 
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string Path value
     */
    public function getPath($key, $default = '')
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Get configuration value as URL (must be string)
     * 
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string URL value
     */
    public function getUrl($key, $default = '')
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Get configuration value as string
     * 
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string String value
     */
    public function getString($key, $default = '')
    {
        $value = $this->get($key, $default);
        // A number is text too. site_config.php writes numbers bare ('blast_num_threads' => 2)
        // while a saved admin form stores "2", and rejecting the int returned the DEFAULT --
        // so the setting only took effect once an admin happened to save it as a string.
        if (is_int($value) || is_float($value)) return (string)$value;
        return is_string($value) ? $value : $default;
    }

    /**
     * Get configuration value as integer
     * 
     * @param string $key Configuration key
     * @param int $default Default value
     * @return int Integer value
     */
    public function getInt($key, $default = 0)
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Get configuration value as array
     * 
     * @param string $key Configuration key
     * @param array $default Default value
     * @return array Array value
     */
    public function getArray($key, $default = [])
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Get sequence types configuration
     * 
     * @return array Sequence types definition
     */
    public function getSequenceTypes()
    {
        return $this->getArray('sequence_types', [
            'protein' => ['pattern' => 'protein.aa.fa', 'label' => 'Protein', 'color' => 'bg-info'],
            'transcript' => ['pattern' => 'transcript.nt.fa', 'label' => 'mRNA', 'color' => 'bg-feature-mrna'],
            'cds' => ['pattern' => 'cds.nt.fa', 'label' => 'CDS', 'color' => 'bg-success'],
            'genome' => ['pattern' => 'genome.fa', 'label' => 'GENOME', 'color' => 'bg-warning text-dark'],
        ]);
    }

    /**
     * Get specific tool configuration
     * 
     * @param string $tool_id Tool identifier
     * @return array|null Tool configuration or null if not found
     */
    public function getTool($tool_id)
    {
        if (!$this->ensureLoaded()) {
            return null;
        }

        return $this->tools[$tool_id] ?? null;
    }

    /**
     * Get all available tools
     * 
     * @return array Array of all tool configurations
     */
    public function getAllTools(bool $include_disabled = false)
    {
        if (!$this->ensureLoaded()) {
            return [];
        }
        if ($include_disabled) {
            return $this->tools;
        }
        // Tools the site switched off ('tools_disabled', Admin -> Manage Tools) are left out, so
        // every toolbox that lists tools drops them without knowing the setting exists.
        return array_filter($this->tools, fn($tool, $id) => $this->isToolEnabled((string) $id), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The navbar's Tools menu, in order: ['type' => 'tool', 'id', 'label', 'url_path'] or
     * ['type' => 'divider']. From the site's saved 'tools_layout.menu' when it has one, else
     * from the registry's menu_order / menu_divider_after -- which reproduce the menu as it was
     * hand-written in includes/toolbar.php until 2026-11-03. Switched-off tools are left out,
     * and a divider never leads, trails or doubles (one beside a switched-off tool would).
     * Here, not in lib/functions_tools.php: the navbar is drawn on pages that never load that.
     */
    public function getToolsMenu(): array
    {
        if (!$this->ensureLoaded()) return [];
        $order = $this->toolsMenuOrder();
        $items = [];
        foreach ($order as $id) {
            $id = (string) $id;
            if ($id === '|') {
                if ($items && end($items)['type'] !== 'divider') $items[] = ['type' => 'divider'];
                continue;
            }
            if (!isset($this->tools[$id]) || !$this->isToolEnabled($id)) continue;
            $items[] = ['type' => 'tool', 'id' => $id, 'label' => $this->toolLabel($id, 'menu'),
                        'url_path' => (string) ($this->tools[$id]['url_path'] ?? '')];
        }
        while ($items && end($items)['type'] === 'divider') array_pop($items);
        return $items;
    }

    /** True when Site Configuration puts the technical panels (BUSCO, assembly statistics, gene set
     *  comparison) under a closed "For researchers" section -- a teaching site. Default false. */
    public function technicalPanelsTucked(): bool
    {
        return ($this->config['technical_panels'] ?? 'show') === 'researchers';
    }

    /** The home page setting: ['mode' => 'builtin'|'page'|'intro', 'page' => slug|'']. */
    public function homeSetting(): array
    {
        $h = $this->config['home'] ?? [];
        $mode = is_array($h) ? (string) ($h['mode'] ?? 'builtin') : 'builtin';
        if (!in_array($mode, ['page', 'intro'], true)) return ['mode' => 'builtin', 'page' => ''];
        return ['mode' => $mode, 'page' => (string) ($h['page'] ?? '')];
    }

    /** A language code: ISO 639-1, optionally with a region ('pt-br'). */
    public const LANGUAGE_CODE = '/^[a-z]{2}(-[a-z]{2,4})?$/';

    /**
     * The site's languages, in order, English first: [['code' => 'en', 'name' => 'English'], ...].
     * English is always there even if a saved list forgot it -- it is what untranslated text falls
     * back to.
     */
    public function languages(): array
    {
        $out = [];
        foreach ((array) ($this->config['languages'] ?? []) as $l) {
            $code = (string) ($l['code'] ?? '');
            if (preg_match(self::LANGUAGE_CODE, $code) && !isset($out[$code])) $out[$code] = ['code' => $code, 'name' => (string) ($l['name'] ?? $code)];
        }
        if (!isset($out['en'])) $out = ['en' => ['code' => 'en', 'name' => 'English']] + $out;
        else $out = ['en' => $out['en']] + $out;
        return array_values($out);
    }

    /** Just the codes of languages(). */
    public function languageCodes(): array
    {
        return array_column($this->languages(), 'code');
    }

    /** Retired languages: [['code', 'name', 'retired_at', 'retired_by'], ...]. Their text is kept. */
    public function retiredLanguages(): array
    {
        return array_values(array_filter((array) ($this->config['languages_retired'] ?? []), fn($l) => is_array($l) && isset($l['code'])));
    }

    /** Why these language lists cannot be saved, or null. */
    public static function languagesProblem($active, $retired): ?string
    {
        if (!is_array($active) || !is_array($retired)) return 'not a list';
        $seen = [];
        foreach ([$active, $retired] as $n => $list) {
            foreach ($list as $l) {
                $code = is_array($l) ? (string) ($l['code'] ?? '') : '';
                $name = is_array($l) ? trim((string) ($l['name'] ?? '')) : '';
                if (!preg_match(self::LANGUAGE_CODE, $code)) return 'a language code must look like "fr" or "pt-br"';
                if ($name === '' || mb_strlen($name) > 40) return "\"$code\" needs a name";
                if (isset($seen[$code])) return "\"$code\" is listed twice";
                $seen[$code] = true;
                if ($n === 1 && $code === 'en') return 'English cannot be retired';
            }
        }
        return in_array('en', array_column($active, 'code'), true) ? null : 'English must stay';
    }

    /** The main bar as it was hand-written in includes/toolbar.php until 2026-11-03. */
    public static function defaultMainMenu(): array
    {
        return [
            ['type' => 'tools'],
            ['type' => 'link', 'label' => ['en' => 'About'], 'to' => ['builtin' => 'about']],
            ['type' => 'link', 'label' => ['en' => 'Help'],  'to' => ['builtin' => 'help']],
        ];
    }

    /** The site's main bar (saved), or the default. */
    public function mainMenu(): array
    {
        $saved = $this->config['main_menu'] ?? [];
        return is_array($saved) && $saved ? array_values($saved) : self::defaultMainMenu();
    }

    /**
     * The main bar, ready to draw: ['type' => 'tools'] | ['type' => 'link', 'label', 'href',
     * 'external'] | ['type' => 'dropdown', 'label', 'id', 'items' => [link | ['type' => 'divider']]].
     * A link to a page that no longer exists is left out; so is a drop-down left empty, and the
     * Tools drop-down when every tool is switched off. Dividers never lead, trail or double.
     */
    public function getMainMenu(string $lang = 'en'): array
    {
        $out = [];
        $n   = 0;
        foreach ($this->mainMenu() as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'tools') {
                if ($this->getToolsMenu()) $out[] = ['type' => 'tools'];
            } elseif ($type === 'link') {
                if ($link = $this->menuLink($item, $lang)) $out[] = $link;
            } elseif ($type === 'dropdown') {
                $items = [];
                foreach ((array) ($item['items'] ?? []) as $child) {
                    if (($child['type'] ?? '') === 'divider') {
                        if ($items && end($items)['type'] !== 'divider') $items[] = ['type' => 'divider'];
                    } elseif ($link = $this->menuLink($child, $lang)) {
                        $items[] = $link;
                    }
                }
                while ($items && end($items)['type'] === 'divider') array_pop($items);
                if ($items) {
                    $out[] = ['type' => 'dropdown', 'label' => $this->menuLabel($item, $lang),
                              'id' => 'menuDropdown' . (++$n), 'items' => $items];
                }
            }
        }
        return $out;
    }

    /** A menu item's label in $lang, falling back to English. */
    private function menuLabel(array $item, string $lang): string
    {
        $label = $item['label'] ?? '';
        // The site's own label in this language when it has one; otherwise the English, through
        // the interface catalog (t_plain()) -- so the default About / Help are translated there.
        $en = is_string($label) ? $label : trim((string) ($label['en'] ?? ''));
        $text = is_string($label) ? '' : trim((string) ($label[$lang] ?? ''));
        if ($lang !== 'en' && $text !== '') return $text;
        return $en !== '' && function_exists('t_plain') ? t_plain($en) : $en;
    }

    /** A link item resolved to ['type' => 'link', 'label', 'href', 'external'], or null. */
    private function menuLink(array $item, string $lang): ?array
    {
        $to    = (array) ($item['to'] ?? []);
        $site  = moop_url_base();
        $href  = null;
        $external = false;
        if (isset($to['builtin'])) {
            $href = ['about' => "$site/about.php", 'help' => "$site/help.php", 'home' => "$site/index.php",
                     'browse' => "$site/browse.php"][$to['builtin']] ?? null;
        } elseif (isset($to['page'])) {
            $slug = (string) $to['page'];
            $file = rtrim($this->getPath('metadata_path'), '/') . "/pages/$slug.json";
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug) && is_file($file)) {
                $href = "$site/page.php?p=" . rawurlencode($slug);
            }
        } elseif (isset($to['tool'])) {
            // A tool from the registry: its address follows the link prefix, and the link is not
            // shown where the tool is switched off or has no data (isToolEnabled).
            $id = (string) $to['tool'];
            if (isset($this->tools[$id]['url_path']) && $this->isToolEnabled($id)) {
                $href = $site . $this->tools[$id]['url_path'];
            }
        } elseif (isset($to['url'])) {
            $url = (string) $to['url'];
            if (preg_match('#^/[^/\\]#', $url)) {
                $href = $url;
            } elseif (preg_match('#^https?://#i', $url)) {
                $href = $url;
                $external = true;
            }
        }
        $label = $this->menuLabel($item, $lang);
        return ($href === null || $label === '') ? null : ['type' => 'link', 'label' => $label, 'href' => $href, 'external' => $external];
    }

    /** Why this main_menu cannot be saved, or null if it can. Used by saveEditableConfig(). */
    public static function mainMenuProblem($menu): ?string
    {
        if (!is_array($menu)) return 'not a list';
        $label_ok = function ($label): bool {
            if (!is_array($label) || trim((string) ($label['en'] ?? '')) === '') return false;
            foreach ($label as $lang => $text) {
                if (!preg_match(self::LANGUAGE_CODE, (string) $lang) || !is_string($text) || mb_strlen($text) > 60) return false;
            }
            return true;
        };
        $link_ok = function ($item) use ($label_ok): bool {
            $to = $item['to'] ?? null;
            if (!$label_ok($item['label'] ?? null) || !is_array($to) || count($to) !== 1) return false;
            if (isset($to['builtin'])) return in_array($to['builtin'], ['about', 'help', 'home', 'browse'], true);
            if (isset($to['page']))    return is_string($to['page']) && (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $to['page']);
            if (isset($to['tool']))    return is_string($to['tool']) && (bool) preg_match('/^[a-z0-9_]{1,40}$/', $to['tool']);
            if (isset($to['url']))     return is_string($to['url']) && mb_strlen($to['url']) <= 500
                                              && (preg_match('#^/[^/\\\\]#', $to['url']) || preg_match('#^https?://[^\s"\'<>]+$#i', $to['url']));
            return false;
        };
        $keys = [];
        $tools = 0;
        foreach ($menu as $i => $item) {
            $type = is_array($item) ? ($item['type'] ?? '') : '';
            if ($type === 'tools') { if (++$tools > 1) return 'the Tools menu twice'; continue; }
            if ($type === 'link') { if (!$link_ok($item)) return 'item ' . ($i + 1) . ' is not a valid link'; continue; }
            if ($type !== 'dropdown') return 'item ' . ($i + 1) . ' has no known type';
            $key = (string) ($item['key'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $key) || isset($keys[$key])) return 'drop-down ' . ($i + 1) . ' needs its own key';
            $keys[$key] = true;
            if (!$label_ok($item['label'] ?? null) || !is_array($item['items'] ?? null)) return 'drop-down ' . ($i + 1) . ' needs a name';
            foreach ($item['items'] as $child) {
                if (($child['type'] ?? '') === 'divider') continue;
                if (($child['type'] ?? '') !== 'link' || !$link_ok($child)) return 'an entry in drop-down ' . ($i + 1) . ' is not a valid link';
            }
        }
        return null;
    }

    /** The menu as ids and '|' dividers: the site's saved order, else the registry default. */
    public function toolsMenuOrder(): array
    {
        $saved = $this->config['tools_layout']['menu'] ?? null;
        return is_array($saved) ? array_values(array_map('strval', $saved)) : $this->defaultToolsMenuOrder();
    }

    /** The registry's menu: tools with a menu_order, in that order, '|' after menu_divider_after. */
    public function defaultToolsMenuOrder(): array
    {
        $in_menu = array_filter($this->tools, fn($t) => isset($t['menu_order']));
        uasort($in_menu, fn($a, $b) => $a['menu_order'] <=> $b['menu_order']);
        $order = [];
        foreach ($in_menu as $id => $t) {
            $order[] = (string) $id;
            if (!empty($t['menu_divider_after'])) $order[] = '|';
        }
        return $order;
    }

    /** The registry's toolbox: every tool not marked 'toolbox' => false, in registry order. */
    public function defaultToolboxOrder(): array
    {
        return array_values(array_map('strval', array_keys(array_filter($this->tools, fn($t) => ($t['toolbox'] ?? true) !== false))));
    }

    /**
     * A tool's label in the menu ('menu') or the toolbox ('toolbox'): the site's own label from
     * 'tools_layout.labels' if it set one, else the registry's menu_label / name.
     */
    public function toolLabel(string $tool_id, string $where = 'toolbox'): string
    {
        $tool  = $this->tools[$tool_id] ?? [];
        $saved = trim((string) ($this->config['tools_layout']['labels'][$tool_id][$where] ?? ''));
        if ($saved !== '') return $saved;
        return (string) ($where === 'menu' ? ($tool['menu_label'] ?? $tool['name'] ?? $tool_id) : ($tool['name'] ?? $tool_id));
    }

    /**
     * The site's saved toolbox order (tool ids, in order: the tools it shows), or null when it
     * never saved one -- then the registry's order and 'toolbox' flags apply, as shipped.
     */
    public function toolboxOrder(): ?array
    {
        $saved = $this->config['tools_layout']['toolbox'] ?? null;
        return is_array($saved) ? array_values(array_map('strval', $saved)) : null;
    }

    /**
     * Is this tool offered on this site? True unless its id is in 'tools_disabled' -- so an
     * unknown id, or a site that never set the key, answers true and nothing changes.
     */
    public function isToolEnabled(string $tool_id): bool
    {
        $off = $this->config['tools_disabled'] ?? [];
        if (is_array($off) && in_array($tool_id, $off, true)) return false;
        // 'available_if' (config/tools_config.php): a cheap check that this SITE has what the tool
        // needs. A tool with nothing to show is off everywhere -- menu, toolbox, its own page --
        // without anyone having to switch it off (cuttingclass changes stay opt-in).
        $check = $this->tools[$tool_id]['available_if'] ?? null;
        if (is_string($check) && function_exists($check) && !$check()) return false;
        return true;
    }

    /** Switched off by an admin (Manage Tools), as opposed to unavailable for lack of data. */
    public function isToolSwitchedOff(string $tool_id): bool
    {
        $off = $this->config['tools_disabled'] ?? [];
        return is_array($off) && in_array($tool_id, $off, true);
    }

    /**
     * Build tool URL with context parameters
     * 
     * @param string $tool_id Tool identifier
     * @param array $context Context array with organism, assembly, group, display_name
     * @return string|null Built URL or null if tool not found
     */
    public function buildToolUrl($tool_id, $context = [])
    {
        $tool = $this->getTool($tool_id);
        if (!$tool) {
            return null;
        }

        $site = $this->getString('site', 'moop');
        $url = moop_url_base() . $tool['url_path'];
        $params = [];

        // Build query parameters from context
        if (isset($tool['context_params']) && is_array($tool['context_params'])) {
            foreach ($tool['context_params'] as $param) {
                if (!empty($context[$param])) {
                    $params[$param] = $context[$param];
                }
            }
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Check if a tool should be visible on a specific page
     * 
     * @param string $tool_id Tool identifier
     * @param string $page Page identifier (index, organism, group, assembly, parent, multi_organism_search)
     * @return bool True if tool should be visible on this page
     */
    public function isToolVisibleOnPage($tool_id, $page)
    {
        $tool = $this->getTool($tool_id);
        if (!$tool) {
            return false;
        }

        // If 'pages' key not defined, default to 'all'
        $pages = $tool['pages'] ?? 'all';

        // 'all' means show on all pages
        if ($pages === 'all') {
            return true;
        }

        // If pages is an array, check if current page is in it
        if (is_array($pages)) {
            return in_array($page, $pages);
        }

        // If pages is a string (and not 'all'), treat as single page name
        return $page === $pages;
    }

    /**
     * Add or register a tool at runtime
     * 
     * @param string $tool_id Tool identifier
     * @param array $config Tool configuration
     * @return bool True if added successfully
     */
    public function addTool($tool_id, $config)
    {
        if (!is_array($config) || empty($config['id'])) {
            return false;
        }

        $this->tools[$tool_id] = $config;
        return true;
    }

    /**
     * Validate that all required configuration keys exist
     * 
     * @return bool True if all required configs are present
     */
    public function validate()
    {
        if (!$this->ensureLoaded()) {
            return false;
        }

        $this->errors = [];

        foreach ($this->requiredKeys as $key) {
            if (!isset($this->config[$key])) {
                $this->errors[] = "Missing required config: $key";
            }
        }

        return empty($this->errors);
    }

    /**
     * Get list of required configuration keys
     * 
     * @return array List of required keys
     */
    public function getRequiredKeys()
    {
        return $this->requiredKeys;
    }

    /**
     * Get list of missing required configuration keys
     * 
     * @return array List of missing keys
     */
    public function getMissingKeys()
    {
        if (!$this->ensureLoaded()) {
            return $this->requiredKeys;
        }

        $missing = [];
        foreach ($this->requiredKeys as $key) {
            if (!isset($this->config[$key])) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Get all validation errors
     * 
     * @return array List of error messages
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Reload configuration from files
     * Force fresh load from disk
     * 
     * @param string $site_config_path Path to site_config.php
     * @param string $tools_config_path Path to tools_config.php
     * @return bool True if reload successful
     */
    public function reload($site_config_path, $tools_config_path)
    {
        $this->config = [];
        $this->tools = [];
        $this->loaded = false;
        $this->errors = [];

        return $this->initialize($site_config_path, $tools_config_path);
    }

    /**
     * Get all loaded configuration (for debugging)
     * 
     * @param bool $include_tools Include tool registry in output
     * @return array All loaded configuration
     */
    public function getAllConfig($include_tools = true)
    {
        $result = [
            'loaded' => $this->loaded,
            'config' => $this->config,
            'errors' => $this->errors,
        ];

        if ($include_tools) {
            $result['tools'] = $this->tools;
        }

        return $result;
    }

    /**
     * Check if configuration is loaded
     * 
     * @return bool True if loaded
     */
    public function isLoaded()
    {
        return $this->loaded;
    }

    /**
     * Save editable configuration to config_editable.json
     * Only allows specific keys to be edited (whitelist approach)
     * 
     * @param array $data Key-value pairs to save
     * @param string $config_dir Directory containing config_editable.json
     * @return array ['success' => bool, 'message' => string]
     */
    /**
     * Is $value what site_config.php ships for $key? Numbers compare as numbers ("150" = 150), a
     * map's key order does not matter, a list's does. A key that ships no value is never "default".
     */
    public function isShippedDefault(string $key, $value): bool
    {
        if (!array_key_exists($key, $this->shipped)) return false;
        return self::normalisedSetting($this->shipped[$key]) === self::normalisedSetting($value);
    }

    private static function normalisedSetting($v)
    {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $x) $out[$k] = self::normalisedSetting($x);
            if ($out && array_keys($out) !== range(0, count($out) - 1)) ksort($out);   // a map
            return $out;
        }
        if (is_bool($v))    return $v ? 'true' : 'false';
        if ($v === null)    return '';
        if (is_numeric($v)) return (string) (0 + $v);
        return (string) $v;
    }

    /**
     * Split a save into what to store and what to drop: a value equal to the shipped default is not
     * stored, so the site keeps FOLLOWING the default as it changes. Saving Site Configuration used
     * to store every field it showed, which froze the defaults of the day into the site (found
     * 2026-11-06: blast_* limits, technical_panels, theme_colors). Returns [$data, $remove].
     */
    public function pruneShippedDefaults(array $data): array
    {
        $remove = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::MERGED_KEYS, true)) continue;
            if ($this->isShippedDefault((string) $key, $value)) {
                $remove[] = $key;
                unset($data[$key]);
            }
        }
        return [$data, $remove];
    }

    /**
     * The 'ontologies' setting (Site Configuration -> Ontologies), checked: the cleaned list, or
     * a message saying what is wrong. Lock kept HERE, whatever the page sends: files only from
     * https://raw.githubusercontent.com/ (lib/ontologies.php refuses any other host as well).
     * One row per ontology, keyed by its prefix:
     *   purpose   anatomy | phenotype | evidence | names
     *   terms     terms TSV (required)      closure  link-tree TSV (optional)
     *   releases  releases.txt (optional)   search   offered in the term box
     *   relations relationship ids the search offers (anatomy/phenotype), e.g. BFO:0000050
     */
    public static function checkOntologyList($list)
    {
        if (!is_array($list)) return 'The ontology list is not valid';
        if (count($list) > 30) return 'At most 30 ontologies';
        $url_ok = function ($u, string $ext): bool {
            return is_string($u) && strlen($u) <= 500
                && preg_match('#^https://raw\.githubusercontent\.com/[A-Za-z0-9._/-]+\.' . $ext . '$#', $u)
                && strpos($u, '..') === false;
        };
        $out = [];
        foreach ($list as $prefix => $o) {
            $prefix = strtoupper(trim((string) $prefix));
            if (!preg_match('/^[A-Z][A-Z0-9]{1,15}$/', $prefix)) return "Ontology prefix '" . substr($prefix, 0, 20) . "' is not valid (letters and digits, e.g. PLANA)";
            if (!is_array($o)) return "Ontology $prefix is not valid";
            $purpose = (string) ($o['purpose'] ?? '');
            if (!in_array($purpose, ['anatomy', 'phenotype', 'evidence', 'names'], true)) return "Ontology $prefix: choose its purpose";
            if (!$url_ok($o['terms'] ?? '', 'tsv')) return "Ontology $prefix: the terms file must be a .tsv at https://raw.githubusercontent.com/";
            foreach (['closure' => 'tsv', 'releases' => 'txt'] as $f => $ext) {
                $v = trim((string) ($o[$f] ?? ''));
                if ($v !== '' && !$url_ok($v, $ext)) return "Ontology $prefix: the $f file must be a .$ext at https://raw.githubusercontent.com/";
                $o[$f] = $v;
            }
            $rel = [];
            foreach ((array) ($o['relations'] ?? []) as $r) {
                $r = trim((string) $r);
                if ($r === '') continue;
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,30}:[A-Za-z0-9_.-]{1,60}$/', $r)) return "Ontology $prefix: '" . substr($r, 0, 40) . "' is not a relationship id (e.g. BFO:0000050)";
                $rel[] = $r;
            }
            $out[$prefix] = [
                'name'      => substr(trim(strip_tags((string) ($o['name'] ?? $prefix))), 0, 80) ?: $prefix,
                'purpose'   => $purpose,
                'terms'     => $o['terms'],
                'closure'   => $o['closure'],
                'releases'  => $o['releases'],
                'search'    => !empty($o['search']),
                'relations' => array_values(array_unique($rel)),
            ];
        }
        return $out;
    }

    public function saveEditableConfig($data, $config_dir, array $remove = [])
    {
        // Use the centralized whitelist of allowed editable keys
        $allowed_keys = $this->editableConfigKeys;

        // Start from the existing file so keys owned by other admin pages are preserved
        $config_file = $config_dir . '/config_editable.json';
        $editable_data = [];
        if (file_exists($config_file)) {
            $existing = loadJsonFile($config_file, []);
            if (is_array($existing)) {
                foreach ($allowed_keys as $key) {
                    if (isset($existing[$key])) {
                        $editable_data[$key] = $existing[$key];
                    }
                }
            }
        }

        // Keys this save takes back to the shipped default (pruneShippedDefaults()).
        foreach ($remove as $key) {
            if (in_array($key, $allowed_keys, true)) unset($editable_data[$key]);
        }

        // Overlay only the keys present in $data (from this save operation)
        foreach ($allowed_keys as $key) {
            if (isset($data[$key])) {
                // Validate email
                if ($key === 'admin_email' && !empty($data[$key])) {
                    if (!filter_var($data[$key], FILTER_VALIDATE_EMAIL)) {
                        return [
                            'success' => false,
                            'message' => 'Invalid email address'
                        ];
                    }
                }
                
                // Validate sequence_types
                if ($key === 'sequence_types') {
                    if (!is_array($data[$key])) {
                        return [
                            'success' => false,
                            'message' => 'Sequence types must be an array'
                        ];
                    }
                    // Validate each sequence type has required fields
                    foreach ($data[$key] as $seq_type => $seq_config) {
                        if (!is_array($seq_config) || !isset($seq_config['label']) || !isset($seq_config['pattern'])) {
                            return [
                                'success' => false,
                                'message' => "Sequence type '$seq_type' is missing required fields (label, pattern)"
                            ];
                        }
                    }
                }
                
                // tools_disabled: a list of known tool ids, nothing else
                if ($key === 'tools_disabled') {
                    if (!is_array($data[$key]) || array_diff($data[$key], array_keys($this->tools ?? []))) {
                        return [
                            'success' => false,
                            'message' => 'Unknown tool in the list of tools to switch off'
                        ];
                    }
                    $data[$key] = array_values(array_unique($data[$key]));
                }

                // tools_layout: menu = tool ids and '|' dividers; toolbox = tool ids; labels =
                // {id: {menu, toolbox}} short plain strings. Anything else is refused whole.
                if ($key === 'tools_layout') {
                    $known = array_keys($this->tools ?? []);
                    $v = $data[$key];
                    $ok = is_array($v) && !array_diff(array_keys($v), ['menu', 'toolbox', 'labels']);
                    foreach (['menu', 'toolbox'] as $list) {
                        if (!$ok || !isset($v[$list])) continue;
                        $allowed = $list === 'menu' ? array_merge($known, ['|']) : $known;
                        $ok = is_array($v[$list]) && !array_diff($v[$list], $allowed)
                            && count(array_filter($v[$list], fn($x) => $x !== '|')) === count(array_unique(array_filter($v[$list], fn($x) => $x !== '|')));
                    }
                    if ($ok && isset($v['labels'])) {
                        $ok = is_array($v['labels']) && !array_diff(array_keys($v['labels']), $known);
                        foreach ($ok ? $v['labels'] : [] as $pair) {
                            if (!is_array($pair) || array_diff(array_keys($pair), ['menu', 'toolbox'])) { $ok = false; break; }
                            foreach ($pair as $label) {
                                if (!is_string($label) || mb_strlen($label) > 60) { $ok = false; break 2; }
                            }
                        }
                    }
                    if (!$ok) {
                        return ['success' => false, 'message' => 'The tool layout is not valid; nothing was saved'];
                    }
                }

                if ($key === 'languages' || $key === 'languages_retired') {
                    $active  = $key === 'languages' ? $data[$key] : ($data['languages'] ?? $this->config['languages'] ?? []);
                    $retired = $key === 'languages_retired' ? $data[$key] : ($data['languages_retired'] ?? $this->config['languages_retired'] ?? []);
                    $problem = self::languagesProblem($active, $retired);
                    if ($problem !== null) {
                        return ['success' => false, 'message' => 'The languages are not valid (' . $problem . '); nothing was saved'];
                    }
                }

                if ($key === 'home') {
                    $h = $data[$key];
                    $ok = is_array($h) && (!$h || (in_array($h['mode'] ?? '', ['builtin', 'page', 'intro'], true)
                          && (($h['mode'] ?? '') === 'builtin' || preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', (string) ($h['page'] ?? '')))));
                    if (!$ok) return ['success' => false, 'message' => 'The home page setting is not valid; nothing was saved'];
                }

                if ($key === 'crosswalk_display') {
                    require_once __DIR__ . '/../lib/crosswalk.php';
                    $checked = moop_crosswalk_display_check($data[$key]);
                    if (is_string($checked)) return ['success' => false, 'message' => 'The Rosetta Stone settings are not valid (' . $checked . '); nothing was saved'];
                    $data[$key] = $checked;
                }

                if ($key === 'ontologies') {
                    $checked = self::checkOntologyList($data[$key]);
                    if (is_string($checked)) return ['success' => false, 'message' => $checked . '; nothing was saved'];
                    $data[$key] = $checked;
                }

                if ($key === 'theme_colors') {
                    $c = $data[$key];
                    $ok = is_array($c) && !array_diff(array_keys($c), ['main', 'assembly', 'action']);
                    foreach ($ok ? $c : [] as $v) {
                        if (!is_string($v) || ($v !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v))) { $ok = false; break; }
                    }
                    if (!$ok) return ['success' => false, 'message' => 'The colors are not valid (each must be #rrggbb or empty); nothing was saved'];
                    $data[$key] = array_filter(array_map('strtolower', $c), fn($v) => $v !== '');
                }

                if ($key === 'main_menu') {
                    $problem = self::mainMenuProblem($data[$key]);
                    if ($problem !== null) {
                        return ['success' => false, 'message' => 'The menu is not valid (' . $problem . '); nothing was saved'];
                    }
                }

                // Validate IP ranges
                if ($key === 'auto_login_ip_ranges') {
                    if (!is_array($data[$key])) {
                        return [
                            'success' => false,
                            'message' => 'IP ranges must be an array'
                        ];
                    }
                    foreach ($data[$key] as $range) {
                        if (!isset($range['start']) || !isset($range['end'])) {
                            return [
                                'success' => false,
                                'message' => 'Each IP range must have start and end addresses'
                            ];
                        }
                        // Validate IP format
                        if (!filter_var($range['start'], FILTER_VALIDATE_IP) || !filter_var($range['end'], FILTER_VALIDATE_IP)) {
                            return [
                                'success' => false,
                                'message' => 'Invalid IP address format'
                            ];
                        }
                    }
                }
                
                // Validate site_data_path
                if ($key === 'site_data_path') {
                    $path = trim($data[$key]);
                    if ($path !== '' && !str_starts_with($path, '/')) {
                        return [
                            'success' => false,
                            'message' => 'Site data backup path must be an absolute path (starting with /).'
                        ];
                    }
                }

                if ($key === 'technical_panels' && !in_array($data[$key], ['show', 'researchers'], true)) {
                    return ['success' => false, 'message' => 'The technical panels setting is not valid; nothing was saved'];
                }

                // Validate timezone: empty (use PHP's own setting) or a real identifier
                if ($key === 'timezone') {
                    $tz = trim((string)$data[$key]);
                    if ($tz !== '' && !moop_is_valid_timezone($tz)) {
                        return [
                            'success' => false,
                            'message' => "Unknown time zone '$tz'. Choose one from the list."
                        ];
                    }
                }

                // Validate cache_path (same rule: absolute, or empty for the in-tree default)
                if ($key === 'cache_path') {
                    $path = trim($data[$key]);
                    if ($path !== '' && !str_starts_with($path, '/')) {
                        return [
                            'success' => false,
                            'message' => 'Cache path must be an absolute path (starting with /).'
                        ];
                    }
                }

                if ($key === 'sequence_types' || $key === 'auto_login_ip_ranges' || $key === 'sample_feature_ids' || $key === 'home_search_examples' || $key === 'blast_sample_sequences' || $key === 'blast_linkouts' || $key === 'turnstile' || $key === 'footer' || $key === 'rate_limit_heavy' || $key === 'tools_disabled' || $key === 'tools_layout' || $key === 'main_menu' || $key === 'languages' || $key === 'languages_retired' || $key === 'home' || $key === 'theme_colors') {
                    $editable_data[$key] = $data[$key];
                } elseif (is_array($data[$key])) {
                    // Any structured value is kept as it is. The list above is hand-kept, and a key
                    // missing from it reached trim() and crashed the save with a TypeError -- the new
                    // 'primer_maker' key did exactly that on 2026-11-06 (and 'tracks_server' /
                    // 'jbrowse2' would too). Only text is trimmed.
                    $editable_data[$key] = $data[$key];
                } else {
                    $editable_data[$key] = trim((string)$data[$key]);
                }
            }
        }
        
        $config_file = $config_dir . '/config_editable.json';
        
        // Add metadata
        $editable_data['_metadata'] = [
            'last_updated' => date('c'),
            'last_updated_by' => get_username() ?? 'unknown'
        ];
        
        $json = json_encode($editable_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Failed to encode config: ' . json_last_error_msg()
            ];
        }
        
        if (file_put_contents($config_file, $json) === false) {
            return [
                'success' => false,
                'message' => "Failed to write config file. Check file permissions on: $config_file"
            ];
        }
        
        // Update in-memory config
        foreach ($editable_data as $key => $value) {
            if ($key !== '_metadata') {
                $this->config[$key] = $value;
            }
        }
        
        // Log the change
        $this->logConfigChange($data, $config_dir);
        
        return [
            'success' => true,
            'message' => 'Configuration saved successfully'
        ];
    }

    /**
     * Log configuration changes to change_log file
     * 
     * @param array $data The configuration data that was changed
     * @param string $config_dir Directory containing change_log
     * @return void
     */
    private function logConfigChange($data, $config_dir)
    {
        $log_dir = dirname($config_dir) . '/change_log';
        
        // Ensure log directory exists
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0775, true);
        }
        
        $log_file = $log_dir . '/site_config.log';
        
        $timestamp = date('Y-m-d H:i:s');
        $username = get_username() ?? 'unknown';
        
        // Build changes description
        $changes = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // For sequence types, show count
                $changes[] = "$key (types: " . count($value) . ")";
            } else {
                $changes[] = "$key";
            }
        }
        $changes_str = implode(', ', $changes);
        
        $log_entry = "[$timestamp] UPDATE by $username | Changed: $changes_str\n";
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Get editable configuration keys and their current values
     * 
     * @return array Array of editable config items with metadata
     */
    public function getEditableConfigMetadata()
    {
        return [
            'siteTitle' => [
                'label' => 'Site Title',
                'description' => 'The name displayed on the website (header, browser tab, etc.)',
                'type' => 'text',
                'current_value' => $this->getString('siteTitle', ''),
                'max_length' => 100,
            ],
            'admin_email' => [
                'label' => 'Administrator Email',
                'description' => 'Contact email for site administrators',
                'type' => 'email',
                'current_value' => $this->getString('admin_email', ''),
                'max_length' => 255,
            ],
            'sequence_types' => [
                'label' => 'Sequence File Types',
                'description' => 'Available sequence file types for searches. Customize labels and badge colors as needed.',
                'type' => 'sequence_types',
                'current_value' => $this->getSequenceTypes(),
                'note' => 'The file name is exact, not a wildcard: every organism must name this sequence type\'s file exactly this, in every gene-set directory. Badge colors use Bootstrap CSS classes (e.g., bg-info, bg-success, bg-warning)',
            ],
            'header_img' => [
                'label' => 'Header Banner Images',
                'description' => 'Every image in images/banners/ is a banner. The home page always '
                    . 'shows the one selected below, so the front door stays consistent; every '
                    . 'other page picks one at random on each load, so visitors see the collection '
                    . 'as they move around. Upload more to add them to the rotation.',
                'type' => 'file_upload',
                'current_value' => $this->getString('header_img', ''),
                'upload_info' => [
                    'destination' => 'images/banners/',
                    'recommended_dimensions' => '1920 x 300 px',
                    'min_width' => 1200,
                    'max_width' => 4000,
                    'min_height' => 200,
                    'max_height' => 500,
                    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                    'max_size_mb' => 5,
                ],
            ],
            'favicon_filename' => [
                'label' => 'Favicon Image',
                'description' => 'Browser tab icon, also shown as the logo in the navigation bar',
                'type' => 'file_upload',
                'current_value' => $this->getString('favicon_filename', 'favicon.ico'),
                'upload_info' => [
                    'destination' => 'images/',
                    'recommended_dimensions' => 'square PNG, 192-512 px, transparent background',
                    'min_width' => 16,
                    'max_width' => 1024,
                    'min_height' => 16,
                    'max_height' => 1024,
                    'allowed_types' => ['ico', 'png', 'jpg', 'jpeg', 'gif', 'webp'],
                    'max_size_mb' => 1,
                ],
            ],
            'auto_login_ip_ranges' => [
                'label' => 'Auto-Login IP Ranges',
                'description' => 'IP ranges for automatic login and access to all assemblies.',
                'type' => 'ip_ranges',
                'current_value' => $this->getArray('auto_login_ip_ranges', []),
                'note' => 'WARNING: Use for institutional access to all assemblies. Provides full access to all assemblies without login.',
            ],
            'blast_num_threads' => [
                'label' => 'BLAST CPU Threads',
                'description' => 'Maximum CPU threads per BLAST search. Should match the number of cores on this server. Each concurrent BLAST job uses this many threads.',
                'type' => 'number',
                'current_value' => $this->getInt('blast_num_threads', 2),
                'min' => 1,
                'max' => 256,
            ],
            // The BLAST queue (lib/blast_queue.php). Ranges are the save handler's bounds;
            // 0 means "size from this machine" where allowed.
            'blast_memory_budget_mb' => [
                'label' => 'Memory budget (MB)',
                'description' => 'Memory BLAST searches may use at once. Automatic = 60% of RAM; a fixed value must be at least 1024.',
                'type' => 'number', 'min' => 0, 'max' => 1048576,
                'current_value' => $this->getInt('blast_memory_budget_mb', 0),
            ],
            'blast_max_concurrent' => [
                'label' => 'Searches at once',
                'description' => 'At most this many BLAST searches run together. Automatic = CPU cores / threads + 2.',
                'type' => 'number', 'min' => 0, 'max' => 256,
                'current_value' => $this->getInt('blast_max_concurrent', 0),
            ],
            'blast_queue_max_wait' => [
                'label' => 'Longest wait in line (s)',
                'description' => 'After this a waiting search gives up with "server busy, try again".',
                'type' => 'number', 'min' => 10, 'max' => 600,
                'current_value' => $this->getInt('blast_queue_max_wait', 120),
            ],
            'blast_max_runtime' => [
                'label' => 'Longest run (s)',
                'description' => 'A search still running after this is stopped.',
                'type' => 'number', 'min' => 30, 'max' => 1800,
                'current_value' => $this->getInt('blast_max_runtime', 150),
            ],
            'blast_max_output_mb' => [
                'label' => 'Largest result (MB)',
                'description' => 'MB of XML. Bigger results are refused with a reason. The page needs ~4x this '
                    . 'in memory: 12.7 MB gave a 502 under PHP\'s 128 MB limit (measured 2026-10-27), so keep it '
                    . 'well under 10 to leave room for other users.',
                'type' => 'number', 'min' => 1, 'max' => 10,
                'current_value' => $this->getInt('blast_max_output_mb', 5),
            ],
            'blast_max_hits' => [
                'label' => 'Most hits (Maximum Hits)',
                'description' => 'The highest Maximum Hits a search may ask for. The result size limit above '
                    . 'still applies: one hit on a chromosome can carry thousands of alignments.',
                'type' => 'number', 'min' => 10, 'max' => 5000,
                'current_value' => $this->getInt('blast_max_hits', 1000),
            ],
            'search_results_limit' => [
                'label' => 'Search Results Limit',
                'description' => 'Maximum annotation-search results returned per organism. '
                    . 'A cross-organism search fans out over every selected organism, so this '
                    . 'caps what any one of them can contribute. Users are told when a search '
                    . 'hits the cap and are prompted to narrow it.',
                'type' => 'number',
                'current_value' => (int)$this->getInt('search_results_limit', 2500),
                'min' => 100,
                'max' => 50000,
                'note' => 'Raising this makes large searches slower to render in the browser, '
                    . 'not just slower to run. The on-page help quotes whatever is set here.',
            ],
            'blast_sample_sequences' => [
                'label' => 'BLAST Sample Sequences',
                'description' => 'Sample sequences for BLAST testing',
                'type' => 'sample_sequences',
                'current_value' => $this->getArray('blast_sample_sequences', [
                    'protein' => '',
                    'nucleotide' => '',
                ]),
                'note' => 'Protein and nucleotide sequences for users to test BLAST. Cannot be empty.',
            ],
            // Example chips -- lib/home_examples.php. Both are OVERRIDES: empty means
            // "choose automatically from data the viewer can open", which is the default.
            'sample_feature_ids' => [
                'label' => 'Example gene / feature IDs',
                'description' => 'Shown on the home page Genes tab and as "Load examples" in Sequence Retrieval.',
                'type' => 'sample_ids',
                'current_value' => $this->getArray('sample_feature_ids', []),
                'note' => 'Leave empty to choose automatically. Any ID a viewer cannot open is skipped for that viewer.',
            ],
            'home_search_examples' => [
                'label' => 'Example organism search terms',
                'description' => 'Shown under the home page Organisms search: names, common names, groups, assemblies or gene sets.',
                'type' => 'sample_ids',
                'current_value' => $this->getArray('home_search_examples', []),
                'note' => 'Leave empty to choose automatically. Any term a viewer cannot open is skipped for that viewer.',
            ],
            'site_data_path' => [
                'label' => 'Site Data Backup Path',
                'description' => 'Absolute filesystem path where config, metadata, and organism.json files are copied when they change, at most once an hour (housekeeping). Set to empty to disable. If it is a git repository the dashboard shows its status; committing stays manual — MOOP never commits.',
                'type' => 'text',
                'current_value' => $this->getPath('site_data_path', ''),
                'note' => 'Keep this directory outside the web root and private — it contains API keys and user account data.',
            ],
            'cache_path' => [
                'label' => 'Cache Directory',
                'description' => 'Absolute filesystem path for generated caches (organism scan, annotation counts, chromosome names). All contents are regenerable. Left empty, they go in a hidden .cache folder inside organisms/.',
                'type' => 'text',
                'current_value' => $this->getPath('cache_path', ''),
                'note' => 'Pointing this outside the document root lets the organisms/ tree be read-only to the web server. The directory must exist and be writable by the web server (correct owner/permission and, on SELinux hosts, the httpd_sys_rw_content_t label) — it is auto-created on the next housekeeping run if missing.',
            ],
            'theme_colors' => [
                'label' => 'Site Colors',
                'description' => 'The main color repaints the site: tool page headers, buttons, links, light washes and organism headers all follow it. Assembly and action colors are optional; left empty, they are worked out from the main color. Leave the main color empty for MOOP\'s own teal.',
                'type' => 'theme_colors',
                'current_value' => $this->getArray('theme_colors', []),
            ],
            'technical_panels' => [
                'label' => 'Technical Panels',
                'description' => 'BUSCO completeness, assembly statistics (N50, gaps, GC) and the gene set comparison table. Researchers want them; students meeting a genome page for the first time mostly do not. "For researchers" folds them into a closed section at the foot of the page, one click away.',
                'type' => 'select',
                'current_value' => $this->technicalPanelsTucked() ? 'researchers' : 'show',
            ],
            'timezone' => [
                'label' => 'Time Zone',
                'description' => 'The time zone dates and times are shown in across the site and admin pages. "Server default" uses whatever PHP is configured with, which is UTC when php.ini sets none — even if the machine clock is set to a local zone.',
                'type' => 'timezone',
                'current_value' => $this->getString('timezone', ''),
            ],
            'turnstile' => [
                'label'         => 'Cloudflare Turnstile',
                'description'   => 'Bot protection — visitors must pass a challenge once per session.',
                'type'          => 'group',
                'current_value' => $this->getArray('turnstile', ['enabled' => false, 'site_key' => '', 'secret_key' => '']),
            ],
            'footer' => [
                'label'       => 'Footer',
                'description' => 'Content displayed in the site footer: institute, license, and custom links.',
                'type'        => 'footer',
                'current_value' => $this->getArray('footer', [
                    'institute_name'   => '',
                    'institute_url'    => '',
                    'license_name'     => '',
                    'license_url'      => '',
                    'copyright_holder' => '',
                    'links'            => [],
                ]),
            ],
        ];
    }

    /**
     * Check if URL matches any trusted tracks server
     * 
     * Used for URL whitelist token strategy - determines which external servers
     * should receive JWT tokens for track authentication.
     * 
     * @param string $url URL to check
     * @return bool True if URL matches a trusted server
     */
    public function isTrustedTracksServer($url)
    {
        $trusted_servers = $this->getArray('jbrowse2.trusted_tracks_servers', []);
        
        foreach ($trusted_servers as $server) {
            // Normalize: strip trailing slash so comparison is consistent
            $normalized = rtrim($server, '/');
            // Match if URL IS the server, or starts with server/ (prevents https://trusted.com.evil.com bypass)
            if ($url === $normalized || strpos($url, $normalized . '/') === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get boolean configuration value
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param bool $default Default value
     * @return bool Boolean value
     */
    public function getBoolean($key, $default = false)
    {
        // Support dot notation for nested config
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        // Convert to boolean
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }
        
        return (bool)$value;
    }
}

?>
