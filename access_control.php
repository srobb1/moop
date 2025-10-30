<?php
/**
 * Centralized Access Control
 * Include this file on every page that needs access control
 */

if (!isset($_SESSION)) {
    session_start();
}

include_once __DIR__ . '/site_config.php';

// Get visitor IP address (use empty string if not set, which won't match any IP range)
$visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Define allowed IP range for ALL access
$all_access_start_ip = ip2long("127.0.0.11");
$all_access_end_ip   = ip2long("127.0.0.11");
$visitor_ip_long = ip2long($visitor_ip);

// Auto-login IP-based users with ALL access (but only if not already logged in as another user type)
if ($visitor_ip_long >= $all_access_start_ip && $visitor_ip_long <= $all_access_end_ip) {
    if (!isset($_SESSION["logged_in"]) || $_SESSION["access_level"] !== 'ALL') {
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = "IP_USER_" . $visitor_ip;
        $_SESSION["access_level"] = 'ALL';
        $_SESSION["access"] = [];
    }
}

// Set access variables (read from session, never from GET/POST)
$logged_in = $_SESSION["logged_in"] ?? false;
$username  = $_SESSION["username"] ?? '';
$user_access = $_SESSION["access"] ?? [];
$access_level = $_SESSION["access_level"] ?? 'Public';

// For backward compatibility, set access_group based on access_level
$access_group = $access_level;

/**
 * Check if an organism belongs to a public group
 * 
 * @param string $organism_name The organism name
 * @return bool True if organism is in a public group
 */
if (!function_exists('is_public_organism')) {
function is_public_organism($organism_name) {
    global $organism_data;
    
    $groups_file = "$organism_data/organism_assembly_groups.json";
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
 * Check if user has access to a specific resource
 * 
 * @param string $required_level Required access level: 'Public', 'Collaborator', 'Admin', or 'ALL'
 * @param string $resource_name Optional: specific organism or resource name to check against user_access
 * @return bool True if user has access, false otherwise
 */
if (!function_exists('has_access')) {
function has_access($required_level = 'Public', $resource_name = null) {
    global $access_level, $user_access;
    
    // ALL and Admin have access to everything
    if ($access_level === 'ALL' || $access_level === 'Admin') {
        return true;
    }
    
    // Public access is always allowed
    if ($required_level === 'Public') {
        return true;
    }
    
    // Collaborator access check
    if ($required_level === 'Collaborator' && $access_level === 'Collaborator') {
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
    global $site;
    if (!has_access($required_level, $resource_name)) {
        header("Location: /$site/access_denied.php");
        exit;
    }
}
}
