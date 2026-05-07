<?php
/**
 * Tool Configuration
 * Central registry for all available tools
 * Defines tool metadata, URLs, and context requirements
 */

$available_tools = [
    'download_fasta' => [
        'id' => 'download_fasta',
        'name' => 'Retrieve Sequences',
        'icon' => 'fa-dna',
        'description' => 'Search and download sequences',
        'btn_class' => 'btn-success',
        'url_path' => '/tools/retrieve_sequences.php',
        'context_params' => ['organism', 'assembly', 'group', 'display_name', 'organisms'],
        'pages' => 'all',  // Show on all pages
    ],
    'blast_search' => [
        'id' => 'blast_search',
        'name' => 'BLAST Search',
        'icon' => 'fa-dna',
        'description' => 'Search sequences against databases',
        'btn_class' => 'btn-warning',
        'url_path' => '/tools/blast.php',
        'context_params' => ['organism', 'assembly', 'group', 'display_name', 'organisms'],
        'pages' => 'all',  // Show on all pages
    ],
    'taxonomy_search' => [
        'id' => 'taxonomy_search',
        'name' => 'Search Organisms',
        'icon' => 'fa-search',
        'description' => 'Search selected organisms',
        'btn_class' => 'btn-info',
        'url_path' => '/tools/multi_organism.php',
        'context_params' => ['organisms', 'display_name'],
        'pages' => ['index'],  // Show only on index page
    ],
    'genome_browser' => [
        'id'              => 'genome_browser',
        'name'            => 'View in Genome Browser',
        'icon'            => 'fa-dna',
        'description'     => 'Open this feature in JBrowse2 genome browser',
        'btn_class'       => 'btn-primary',
        'url_path'        => '/jbrowse2.php',
        'context_params'  => ['organism', 'assembly', 'loc'],
        'required_params' => ['loc'],
        'pages'           => ['parent'],
    ],
    'downloads' => [
        'id'             => 'downloads',
        'name'           => 'Downloads',
        'icon'           => 'fa-download',
        'description'    => 'Download genome files (FASTA, GFF, and more)',
        'btn_class'      => 'btn-secondary',
        'url_path'       => '/tools/downloads.php',
        'context_params' => ['organism', 'assembly', 'group', 'display_name'],
        'pages'          => ['organism', 'assembly', 'group'],
    ],
    // Future tools can be added here
    // Pages can be:
    //   'all' - Show on all pages
    //   ['page1', 'page2'] - Show on specific pages (index, organism, group, assembly, parent, multi_organism_search)
    //   Omit 'pages' key - defaults to 'all'
];

/**
 * Get a specific tool configuration
 * 
 * @param string $tool_id - The tool identifier
 * @return array|null - Tool configuration or null if not found
 */
function getTool($tool_id) {
    global $available_tools;
    return $available_tools[$tool_id] ?? null;
}

/**
 * Get all available tools
 * 
 * @return array - Array of all tool configurations
 */
function getAllTools() {
    global $available_tools;
    return $available_tools;
}

/**
 * Build tool URL with context parameters
 * 
 * @param string $tool_id - The tool identifier
 * @param array $context - Context array with organism, assembly, group, display_name
 * @param string $site - Site variable (from site_config.php)
 * @return string|null - Built URL or null if tool not found
 */
function buildToolUrl($tool_id, $context, $site) {
    $tool = getTool($tool_id);
    if (!$tool) {
        return null;
    }

    // If tool declares required_params, all must be present and non-empty in context
    if (!empty($tool['required_params'])) {
        foreach ($tool['required_params'] as $req) {
            if (empty($context[$req])) {
                return null;
            }
        }
    }

    $url = "/$site" . $tool['url_path'];
    $params = [];
    $organisms = [];
    
    // Build query parameters from context
    foreach ($tool['context_params'] as $param) {
        if (!empty($context[$param])) {
            // Separate organisms array for special handling
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
    
    // Build URL with regular params first
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
        $separator = '&';
    } else {
        $separator = '?';
    }
    
    // Append organisms with [] notation
    if (!empty($organisms)) {
        foreach ($organisms as $org) {
            $url .= $separator . 'organisms[]=' . urlencode($org);
            $separator = '&';
        }
    }
    
    return $url;
}

/**
 * Check if a tool should be visible on a specific page
 * 
 * @param array $tool - Tool configuration
 * @param string $page - Page identifier (index, organism, group, assembly, parent, multi_organism_search)
 * @return bool - True if tool should be visible on this page
 */
function isToolVisibleOnPage($tool, $page) {
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

?>
