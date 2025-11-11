<?php
/**
 * Tool Configuration
 * Central registry for all available tools
 * Defines tool metadata, URLs, and context requirements
 */

$available_tools = [
    'download_fasta' => [
        'id' => 'download_fasta',
        'name' => 'Download FASTA',
        'icon' => 'fa-dna',
        'description' => 'Search and download sequences',
        'btn_class' => 'btn-success',
        'url_path' => '/tools/extract/download_fasta.php',
        'context_params' => ['organism', 'assembly', 'group', 'display_name'],
    ],
    // Future tools can be added here
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
    
    $url = "/$site" . $tool['url_path'];
    $params = [];
    
    // Build query parameters from context
    foreach ($tool['context_params'] as $param) {
        if (!empty($context[$param])) {
            $params[$param] = $context[$param];
        }
    }
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

?>
