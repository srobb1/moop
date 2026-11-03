<?php
/**
 * MOOP Tool Functions
 * Tool configuration, context creation, and tool availability management
 */

/**
 * Get available tools filtered by context.
 * Reads tool definitions from ConfigManager (config/tools_config.php).
 *
 * @param array $context Keys: page, organism, assembly, gene_set, group, display_name, organisms, loc
 * @return array Tools that match the context, each with a built 'url' key added
 */
function getAvailableTools($context = []) {
    $config   = ConfigManager::getInstance();
    $all_tools = $config->getAllTools();
    $site     = $config->getString('site');
    $current_page = $context['page'] ?? null;

    // A site that saved its own toolbox (Admin -> Manage Tools, 'tools_layout.toolbox') gets
    // exactly those tools in that order, and its own labels; one that never did gets the
    // registry's order and 'toolbox' flags, as shipped.
    $saved_order = $config->toolboxOrder();
    if ($saved_order !== null) {
        $ordered = [];
        foreach ($saved_order as $id) {
            if (isset($all_tools[$id])) $ordered[$id] = ['toolbox' => true] + $all_tools[$id];
        }
        $all_tools = $ordered;
    }

    $tools = [];
    foreach ($all_tools as $tool_id => $tool) {
        if ($current_page && !_tool_visible_on_page($tool, $current_page)) {
            continue;
        }
        $url = _build_tool_url($tool, $context, $site);
        if ($url !== null) {
            $tools[$tool_id] = array_merge($tool, ['url' => $url, 'name' => $config->toolLabel((string) $tool_id, 'toolbox')]);
        }
    }
    return $tools;
}

/**
 * Build a URL for a tool given the current page context.
 * Returns null if any required_params are missing from context.
 * Handles organisms[] array params separately from scalar params.
 */
function _build_tool_url($tool, $context, $site) {
    if (!empty($tool['required_params'])) {
        foreach ($tool['required_params'] as $req) {
            if (empty($context[$req])) {
                return null;
            }
        }
    }

    $url      = "/$site" . $tool['url_path'];
    $params   = [];
    $organisms = [];

    foreach ($tool['context_params'] as $param) {
        if (!empty($context[$param])) {
            if ($param === 'organisms' && is_array($context[$param])) {
                $organisms = $context[$param];
            } else {
                $params[$param] = $context[$param];
            }
        }
    }

    if (empty($params) && empty($organisms)) {
        return $url;
    }

    $sep = '?';
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
        $sep  = '&';
    }
    foreach ($organisms as $org) {
        $url .= $sep . 'organisms[]=' . urlencode($org);
        $sep  = '&';
    }

    return $url;
}

/**
 * Check whether a tool should appear on the given page.
 */
function _tool_visible_on_page($tool, $page) {
    $pages = $tool['pages'] ?? 'all';
    if ($pages === 'all') {
        return true;
    }
    if (is_array($pages)) {
        return in_array($page, $pages);
    }
    return $page === $pages;
}

/**
 * Create a tool context for tool_section.php
 *
 * @param string $page  'index' | 'organism' | 'assembly' | 'gene_set' | 'group' | 'parent' | 'multi_organism_search'
 * @param array  $params organism, assembly, gene_set, group, organisms, display_name, use_onclick_handler, loc
 * @return array
 */
function createToolContext($page, $params = []) {
    $context = ['page' => $page];

    // ⚠️ This list used to be hardcoded, and it silently DROPPED any key not on
    // it. A tool could declare 'feature' in its context_params, a page could
    // pass 'feature' here, and the parameter would simply never reach the URL —
    // no error, no warning, just a tool that arrives with nothing to work on.
    // That cost a debugging session on Primer Maker, which was written, wired
    // and committed before anyone noticed the key was being discarded.
    //
    // Now the tools themselves say what they accept, so declaring a context
    // param in config/tools_config.php is all it takes. The base keys stay
    // because some are consumed HERE rather than by any one tool
    // (use_onclick_handler, page) or are used to derive display_name below.
    // required_params too: a tool that needs a flag the page sets (Expression needs
    // has_expression) would otherwise never see it -- the same silent drop as above.
    $declared = [];
    foreach (ConfigManager::getInstance()->getAllTools(true) as $tool) {   // incl. switched-off: context only
        foreach (array_merge($tool['context_params'] ?? [], $tool['required_params'] ?? []) as $p) {
            $declared[$p] = true;
        }
    }
    $optional_keys = array_unique(array_merge(
        ['organism', 'assembly', 'gene_set', 'group', 'organisms', 'display_name', 'use_onclick_handler', 'loc'],
        array_keys($declared)
    ));

    foreach ($optional_keys as $key) {
        if (!empty($params[$key])) {
            $context[$key] = $params[$key];
        }
    }

    if (empty($context['display_name'])) {
        if (!empty($context['organism'])) {
            $context['display_name'] = $context['organism'];
        } elseif (!empty($context['group'])) {
            $context['display_name'] = $context['group'];
        } elseif (in_array($page, ['index', 'multi_organism_search'])) {
            $context['display_name'] = 'Multi-Organism Search';
        }
    }

    return $context;
}

/**
 * The id of the switched-off tool whose page $script is, or null. Pure -- the decision
 * moop_tool_page_guard() acts on, kept apart so it can be tested without sending a 404.
 * Matches the script against each tool's url_path; scripts that are no tool's page (AJAX
 * endpoints, help) are never matched.
 */
function moop_disabled_tool_for_script(ConfigManager $config, string $script): ?string {
    $script = '/' . ltrim($script, '/');
    $prefix = '/' . $config->getString('site');
    foreach ($config->getAllTools(true) as $id => $tool) {
        $path = (string) ($tool['url_path'] ?? '');
        if ($path !== '' && $script === $prefix . $path && !$config->isToolEnabled((string) $id)) {
            return (string) $id;
        }
    }
    return null;
}

/**
 * Stop here with the site's 404 page if THIS script is the page of a tool the site switched off
 * ('tools_disabled'). Called by tools/tool_init.php and jbrowse2.php, so a tool hidden from the
 * menus is not still usable by anyone who knows its address.
 */
function moop_tool_page_guard(): void {
    $config = ConfigManager::getInstance();
    $id = moop_disabled_tool_for_script($config, (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($id === null) return;
    // Links to it stay on other pages (Sofia, 2026-11-03), so whoever follows one is told WHY,
    // not just "not found" -- and an admin, who can do something about it, is told where.
    $name = $config->toolLabel($id, 'menu');   // "Genome Browser", not the button text "View in Genome Browser"
    $message = function_exists('moop_session_is_admin') && moop_session_is_admin()
        ? "$name is switched off on this site. To switch it back on: Admin → Manage Tools."
        : "$name is not offered on this site.";
    require_once __DIR__ . '/functions_display.php';
    moop_not_found_page($message);
}

