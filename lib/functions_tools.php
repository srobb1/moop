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

    $tools = [];
    foreach ($all_tools as $tool_id => $tool) {
        if ($current_page && !_tool_visible_on_page($tool, $current_page)) {
            continue;
        }
        $url = _build_tool_url($tool, $context, $site);
        if ($url !== null) {
            $tools[$tool_id] = array_merge($tool, ['url' => $url]);
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
    $declared = [];
    foreach (ConfigManager::getInstance()->getAllTools() as $tool) {
        foreach (($tool['context_params'] ?? []) as $p) {
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
