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
 * Create a tool context for index/home page
 * 
 * @param bool $use_onclick_handler Whether to use onclick handler for tools
 * @return array Context array for tool_section.php
 */
function createIndexToolContext($use_onclick_handler = true) {
    return [
        'display_name' => 'Multi-Organism Search',
        'page' => 'index',
        'use_onclick_handler' => $use_onclick_handler
    ];
}

/**
 * Create a tool context for an organism display page
 * 
 * @param string $organism_name The organism name
 * @param string $display_name Optional display name (defaults to organism_name)
 * @return array Context array for tool_section.php
 */
function createOrganismToolContext($organism_name, $display_name = null) {
    return [
        'organism' => $organism_name,
        'display_name' => $display_name ?? $organism_name,
        'page' => 'organism'
    ];
}

/**
 * Create a tool context for an assembly display page
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_accession The assembly/genome accession
 * @param string $display_name Optional display name (defaults to assembly_accession)
 * @return array Context array for tool_section.php
 */
function createAssemblyToolContext($organism_name, $assembly_accession, $display_name = null) {
    return [
        'organism' => $organism_name,
        'assembly' => $assembly_accession,
        'display_name' => $display_name ?? $assembly_accession,
        'page' => 'assembly'
    ];
}

/**
 * Create a tool context for a group display page
 * 
 * @param string $group_name The group name
 * @return array Context array for tool_section.php
 */
function createGroupToolContext($group_name) {
    return [
        'group' => $group_name,
        'display_name' => $group_name,
        'page' => 'group'
    ];
}

/**
 * Create a tool context for a feature/parent display page
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_accession The assembly/genome accession
 * @param string $feature_name The feature name
 * @return array Context array for tool_section.php
 */
function createFeatureToolContext($organism_name, $assembly_accession, $feature_name) {
    return [
        'organism' => $organism_name,
        'assembly' => $assembly_accession,
        'display_name' => $feature_name,
        'page' => 'parent'
    ];
}

/**
 * Create a tool context for multi-organism search page
 * 
 * @param array $organisms Array of organism names
 * @param string $display_name Optional display name
 * @return array Context array for tool_section.php
 */
function createMultiOrganismToolContext($organisms, $display_name = 'Multi-Organism Search') {
    return [
        'organisms' => $organisms,
        'display_name' => $display_name,
        'page' => 'multi_organism_search'
    ];
}
