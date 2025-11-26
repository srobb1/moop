<?php
/**
 * MOOP Tool Functions
 * Tool configuration, context creation, and tool availability management
 */

/**
 * Get available tools filtered by context
 * Returns only tools that have the required context parameters available
 * 
 * @param array $context - Context array with optional keys: organism, assembly, group, display_name
 * @return array - Array of available tools with built URLs
 */
function getAvailableTools($context = []) {
    global $site, $available_tools;
    
    // Include tool configuration
    include_once __DIR__ . '/tool_config.php';
    
    // If $available_tools not set by include, return empty array
    if (!isset($available_tools) || !is_array($available_tools)) {
        return [];
    }
    
    // Get current page from context (optional)
    $current_page = $context['page'] ?? null;
    
    $tools = [];
    foreach ($available_tools as $tool_id => $tool) {
        // Check page visibility - skip if tool doesn't show on this page
        if ($current_page && !isToolVisibleOnPage($tool, $current_page)) {
            continue;
        }
        
        $url = buildToolUrl($tool_id, $context, $site);
        if ($url) {
            $tools[$tool_id] = array_merge($tool, ['url' => $url]);
        }
    }
    
    return $tools;
}

/**
 * Create a tool context for tool_section.php
 * 
 * Builds a context array with page type and available entity parameters.
 * Filters out null/empty values to keep context clean.
 * 
 * @param string $page Page identifier: 'index', 'organism', 'assembly', 'group', 'parent', 'multi_organism_search'
 * @param array $params Optional entity parameters: organism, assembly, group, organisms, display_name, use_onclick_handler
 * @return array Context array for tool_section.php
 * 
 * Examples:
 *   createToolContext('index', ['use_onclick_handler' => true])
 *   createToolContext('organism', ['organism' => $name, 'display_name' => $common_name])
 *   createToolContext('assembly', ['organism' => $org, 'assembly' => $acc, 'display_name' => $name])
 *   createToolContext('group', ['group' => $name])
 *   createToolContext('parent', ['organism' => $org, 'assembly' => $acc, 'display_name' => $feature])
 *   createToolContext('multi_organism_search', ['organisms' => $orgs, 'display_name' => $name])
 */
function createToolContext($page, $params = []) {
    $context = ['page' => $page];
    
    // Add optional parameters if provided and not null/empty
    $optional_keys = ['organism', 'assembly', 'group', 'organisms', 'display_name', 'use_onclick_handler'];
    foreach ($optional_keys as $key) {
        if (!empty($params[$key])) {
            $context[$key] = $params[$key];
        }
    }
    
    // Set sensible defaults for display_name if not provided
    if (empty($context['display_name'])) {
        if (!empty($context['organism'])) {
            $context['display_name'] = $context['organism'];
        } elseif (!empty($context['group'])) {
            $context['display_name'] = $context['group'];
        } elseif ($page === 'index') {
            $context['display_name'] = 'Multi-Organism Search';
        } elseif ($page === 'multi_organism_search') {
            $context['display_name'] = 'Multi-Organism Search';
        }
    }
    
    return $context;
}


