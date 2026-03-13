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
//
// SECURITY: Always use REMOTE_ADDR, never HTTP_X_FORWARDED_FOR or HTTP_CLIENT_IP.
// Those headers are set by the client and can be spoofed to bypass IP-based auto-login.
// REMOTE_ADDR is the actual TCP connection IP and cannot be forged by a remote client.
//
// ⚠️  WARNING: If you deploy behind a reverse proxy (nginx, Apache mod_proxy, AWS ALB,
// Cloudflare, etc.), the proxy's IP will appear in REMOTE_ADDR instead of the real
// client IP. In that case, auto_login_ip_ranges will NOT work as expected.
// Consult your system administrator before enabling auto_login_ip_ranges in a proxied
// environment. Do NOT simply switch to X-Forwarded-For without also restricting which
// proxy IPs are trusted.
$visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$visitor_ip_long = ip2long($visitor_ip);

// Auto-login IP-based users with ALL access (but only if not already logged in as another user type)
// IP ranges are configured in site_config.php under 'auto_login_ip_ranges'
$config = ConfigManager::getInstance();
$ip_ranges = $config->getArray('auto_login_ip_ranges', []);

// Warn in server error log if X-Forwarded-For is present while IP auto-login is configured.
// This helps admins detect proxy deployments where IP auto-login may not behave as expected.
if (!empty($ip_ranges) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    error_log(
        'MOOP SECURITY WARNING: auto_login_ip_ranges is configured but HTTP_X_FORWARDED_FOR ' .
        'header is present (value: ' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '). ' .
        'If this server is behind a reverse proxy, REMOTE_ADDR (' . $visitor_ip . ') may be ' .
        'the proxy IP rather than the real client IP. Review your proxy configuration.'
    );
}

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
            if (isset($entry['groups']) && in_array('PUBLIC', $entry['groups'])) {
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
 * @param string $required_level Required access level: 'PUBLIC', 'COLLABORATOR', 'ADMIN', or 'IP_IN_RANGE'
 * @param string $resource_name Optional: specific organism or resource name to check against user_access
 * @return bool True if user has access, false otherwise
 */
if (!function_exists('has_access')) {
function has_access($required_level = 'PUBLIC', $resource_name = null) {
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
function require_access($required_level = 'COLLABORATOR', $resource_name = null) {
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
    // ADMIN and IP_IN_RANGE have access to everything
    if (has_access('ADMIN') || has_access('IP_IN_RANGE')) {
        return true;
    }
    
    // Public assemblies are accessible to everyone
    if (is_public_assembly($organism_name, $assembly_name)) {
        return true;
    }
    
    // Collaborators check their specific access list
    if (has_access('COLLABORATOR')) {
        $user_access = get_user_access();
        if (isset($user_access[$organism_name]) && is_array($user_access[$organism_name]) && in_array($assembly_name, $user_access[$organism_name])) {
            return true;
        }
    }

    return false;
}
}

// ============================================================
// CSRF PROTECTION
// ============================================================
// These functions protect every state-changing form and AJAX
// request from Cross-Site Request Forgery attacks.
//
// HOW IT WORKS:
//   1. A random token is generated once per session and stored
//      in $_SESSION['csrf_token'].
//   2. Every HTML form includes a hidden field with that token
//      (use csrf_input_field() inside each <form>).
//   3. Every POST handler verifies the submitted token matches
//      the session token (use verify_csrf_token() at the top).
//   4. For AJAX requests, jQuery automatically reads the token
//      from the <meta name="csrf-token"> tag (set in
//      head-resources.php) and sends it as the X-CSRF-Token
//      header. API endpoints call verify_csrf_token() on that.
//
// HOW TO ADD CSRF TO A NEW PAGE:
//   - In the HTML form (pages/*.php): add csrf_input_field() inside the <form> tag
//   - In the POST handler (controller *.php): call
//     verify_csrf_token() and exit/redirect on failure.
//   - AJAX endpoints verify via get_csrf_token_from_request().
// ============================================================

/**
 * Get (or create) the session CSRF token.
 *
 * Called automatically - you rarely need this directly.
 * Use csrf_input_field() in forms and verify_csrf_token() in handlers.
 *
 * @return string 64-character hex CSRF token
 */
if (!function_exists('generate_csrf_token')) {
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}

/**
 * Verify a submitted CSRF token against the session token.
 *
 * Uses hash_equals() to prevent timing-based side-channel attacks.
 *
 * @param string $submitted_token  Token from $_POST['csrf_token'] or X-CSRF-Token header
 * @return bool  True if valid
 */
if (!function_exists('verify_csrf_token')) {
function verify_csrf_token($submitted_token) {
    $session_token = $_SESSION['csrf_token'] ?? '';
    if (empty($session_token) || empty($submitted_token)) {
        return false;
    }
    return hash_equals($session_token, $submitted_token);
}
}

/**
 * Render a hidden CSRF input field for use inside HTML forms.
 *
 * USAGE (in any form in pages/*.php):
 *   <form method="post">
 *     [echo csrf_input_field()]
 *     ... other fields ...
 *   </form>
 *
 * @return string  HTML hidden input element
 */
if (!function_exists('csrf_input_field')) {
function csrf_input_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES) . '">';
}
}

/**
 * Extract the CSRF token from the current request.
 *
 * Checks (in order):
 *   1. X-CSRF-Token HTTP header  (sent automatically by jQuery AJAX setup)
 *   2. $_POST['csrf_token']      (sent by regular HTML forms)
 *
 * @return string  Token string, or empty string if not present
 */
if (!function_exists('get_csrf_token_from_request')) {
function get_csrf_token_from_request() {
    // HTTP header (jQuery AJAX - set up in layout.php)
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($header)) {
        return $header;
    }
    // Form POST field
    return $_POST['csrf_token'] ?? '';
}
}

/**
 * Verify CSRF for the current request and abort with 403 if invalid.
 *
 * USAGE at the top of any POST handler:
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *       csrf_protect();
 *       // ... safe to process form now
 *   }
 *
 * For JSON API endpoints, pass true to send a JSON error response:
 *   csrf_protect(true);
 *
 * @param bool $json_response  If true, respond with JSON on failure (for AJAX endpoints)
 */
if (!function_exists('csrf_protect')) {
function csrf_protect($json_response = false) {
    $token = get_csrf_token_from_request();
    if (!verify_csrf_token($token)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("MOOP SECURITY: CSRF token mismatch from IP $ip, URI: " . ($_SERVER['REQUEST_URI'] ?? ''));
        if ($json_response) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please reload the page and try again.']);
            exit;
        }
        http_response_code(403);
        // Show a simple error - the user can go back and resubmit
        $config = ConfigManager::getInstance();
        $site   = $config->getString('site');
        echo '<!DOCTYPE html><html><body>';
        echo '<h2>Security Error</h2>';
        echo '<p>Your form submission could not be verified. This can happen if your session expired or the page was open in multiple tabs.</p>';
        echo '<p><a href="javascript:history.back()">Go back and try again</a></p>';
        echo '</body></html>';
        exit;
    }
}
}

// Generate the token on every page load so it is available for forms and meta tags.
// This is safe to call multiple times - it only creates the token if it doesn't exist.
if (session_status() === PHP_SESSION_ACTIVE) {
    generate_csrf_token();
}
