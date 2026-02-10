<?php
/**
 * Centralized Access Control
 * Include this file on every page that needs access control
 */

if (!isset($_SESSION)) {
    session_start();
}

include_once __DIR__ . '/config_init.php';

// Tool section component path constant
define('TOOL_SECTION_PATH', __DIR__ . '/../lib/tool_section.php');

// Get visitor IP address (use empty string if not set, which won't match any IP range)
$visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$visitor_ip_long = ip2long($visitor_ip);

// Auto-login IP-based users with ALL access (but only if not already logged in as another user type)
// IP ranges are configured in site_config.php under 'auto_login_ip_ranges'
$config = ConfigManager::getInstance();
$ip_ranges = $config->getArray('auto_login_ip_ranges', []);

foreach ($ip_ranges as $range) {
    $range_start = $range['start'] ?? null;
    $range_end = $range['end'] ?? null;
    
    if ($range_start && $range_end) {
        $start_long = ip2long($range_start);
        $end_long = ip2long($range_end);
        
        if ($visitor_ip_long !== false && $visitor_ip_long >= $start_long && $visitor_ip_long <= $end_long) {
            if (!isset($_SESSION["logged_in"]) || $_SESSION["access_level"] !== 'IP_IN_RANGE') {
                $_SESSION["logged_in"] = true;
                $_SESSION["username"] = "IP_USER_" . $visitor_ip;
                $_SESSION["access_level"] = 'IP_IN_RANGE';
                $_SESSION["access"] = [];
            }
            break;
        }
    }
}

/**
 * Helper functions to access session data securely
 * These functions always read from $_SESSION (single source of truth)
 */
function get_access_level() {
    return $_SESSION["access_level"] ?? 'PUBLIC';
}

function get_user_access() {
    return $_SESSION["access"] ?? [];
}

function is_logged_in() {
    return $_SESSION["logged_in"] ?? false;
}

function get_username() {
    return $_SESSION["username"] ?? '';
}

/**
 * Check if an organism belongs to a public group
 * 
 * @param string $organism_name The organism name
 * @return bool True if organism is in a public group
 */
if (!function_exists('is_public_organism')) {
function is_public_organism($organism_name) {
    $config = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');
    
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (!file_exists($groups_file)) {
        return false;
    }
    
    $groups_data = json_decode(file_get_contents($groups_file), true);
    if (!$groups_data) {
        return false;
    }
    
    foreach ($groups_data as $entry) {
        if ($entry['organism'] === $organism_name) {
            if (isset($entry['groups']) && in_array('Public', $entry['groups'])) {
                return true;
            }
        }
    }
    
    return false;
}
}

/**
 * Check if a specific assembly is public (in Public group)
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_name The assembly name
 * @return bool True if this specific assembly is in the Public group
 */
if (!function_exists('is_public_assembly')) {
function is_public_assembly($organism_name, $assembly_name) {
    $config = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');
    
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (!file_exists($groups_file)) {
        return false;
    }
    
    $groups_data = json_decode(file_get_contents($groups_file), true);
    if (!$groups_data) {
        return false;
    }
    
    foreach ($groups_data as $entry) {
        if ($entry['organism'] === $organism_name && $entry['assembly'] === $assembly_name) {
            if (isset($entry['groups']) && in_array('PUBLIC', $entry['groups'])) {
                return true;
            }
        }
    }
    
    return false;
}
}

/**
 * Check if a group has at least one public assembly
 * 
 * @param string $group_name The group name
 * @return bool True if this group contains at least one assembly in Public group
 */
if (!function_exists('is_public_group')) {
function is_public_group($group_name) {
    $config = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');
    
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (!file_exists($groups_file)) {
        return false;
    }
    
    $groups_data = json_decode(file_get_contents($groups_file), true);
    if (!$groups_data) {
        return false;
    }
    
    foreach ($groups_data as $entry) {
        if (in_array($group_name, $entry['groups']) && in_array('PUBLIC', $entry['groups'])) {
            return true;
        }
    }
    
    return false;
}
}

/**
 * Check if user has access to a specific resource
 * 
 * @param string $required_level Required access level: 'Public', 'Collaborator', 'Admin', or 'ALL'
 * @param string $resource_name Optional: specific organism or resource name to check against user_access
 * @return bool True if user has access, false otherwise
 */
if (!function_exists('has_access')) {
function has_access($required_level = 'Public', $resource_name = null) {
    $access_level = get_access_level();
    $user_access = get_user_access();
    
    // ADMIN and IP_IN_RANGE have access to everything
    if ($access_level === 'ADMIN' || $access_level === 'IP_IN_RANGE') {
        return true;
    }
    
    // Public access is always allowed
    if ($required_level === 'PUBLIC') {
        return true;
    }
    
    // Collaborator access check
    if ($required_level === 'COLLABORATOR' && $access_level === 'COLLABORATOR') {
        // If no specific resource is requested, grant access
        if ($resource_name === null) {
            return true;
        }
        // Check if user has access to this specific resource
        if (isset($user_access[$resource_name])) {
            return true;
        }
    }
    
    return false;
}
}

/**
 * Require a specific access level or redirect to index
 * 
 * @param string $required_level Required access level
 * @param string $resource_name Optional: specific resource name
 */
if (!function_exists('require_access')) {
function require_access($required_level = 'Collaborator', $resource_name = null) {
    if (!has_access($required_level, $resource_name)) {
        $config = ConfigManager::getInstance();
        $site = $config->getString('site');
        header("Location: /$site/access_denied.php");
        exit;
    }
}
}

/**
 * Check if user has access to a specific assembly
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_name The assembly name
 * @return bool True if user has access to this assembly
 */
if (!function_exists('has_assembly_access')) {
function has_assembly_access($organism_name, $assembly_name) {
    // ALL and Admin have access to everything
    if (has_access('ALL')) {
        return true;
    }
    
    // Public assemblies are accessible to everyone
    if (is_public_assembly($organism_name, $assembly_name)) {
        return true;
    }
    
    // Collaborators check their specific access list
    if (has_access('Collaborator')) {
        $user_access = get_user_access();
        if (isset($user_access[$organism_name]) && is_array($user_access[$organism_name]) && in_array($assembly_name, $user_access[$organism_name])) {
            return true;
        }
    }
    
    return false;
}
}
