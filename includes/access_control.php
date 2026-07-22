<?php
/**
 * Centralized Access Control
 * Include this file on every page that needs access control
 */

require_once __DIR__ . '/session_init.php';
if (!isset($_SESSION)) {
    moop_session_start();
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

/**
 * "View as PUBLIC" — an admin's only way to see what an unauthenticated visitor sees.
 *
 * WHY THIS EXISTS: auto_login_ip_ranges runs below on EVERY request, and logout.php only
 * calls session_destroy() — so from inside a trusted subnet you are re-logged-in as
 * IP_IN_RANGE on the very next request. There is no way to be logged out from an internal
 * address, which means the PUBLIC path (what a visitor actually sees) is otherwise
 * impossible to test on a deployment reached only from trusted IPs. Publishing a gene set
 * without being able to verify it is publishing blind.
 *
 * HOW IT WORKS: the real identity stays in $_SESSION untouched; only the ACCESSORS below
 * lie. That is what lets view_as.php verify "are you really an admin?" while the rest of
 * the app sees a public visitor — an admin can always get back out.
 *
 * Toggled only via /view_as.php (POST + CSRF + real-admin check).
 */
function moop_viewing_as_public() {
    return !empty($_SESSION['view_as_public']);
}

/**
 * The REAL session identity, ignoring any active "view as PUBLIC" mode.
 *
 * Only view_as.php should need these — everything else must go through the normal
 * accessors, or the preview would not be a faithful preview.
 */
function moop_real_access_level() {
    return $_SESSION['access_level'] ?? 'PUBLIC';
}

function moop_real_username() {
    return $_SESSION['username'] ?? '';
}

/**
 * Is the REAL session an administrator?
 *
 * Deliberately checks both identity sources: the access level AND the users-file role.
 * admin_access_check.php requires both to agree, so entering/leaving the preview must
 * use the same bar — otherwise a non-admin could reach the toggle.
 */
function moop_real_is_admin() {
    return moop_real_access_level() === 'ADMIN'
        && ($_SESSION['role'] ?? null) === 'admin';
}

// The auto-login below would re-grant IP_IN_RANGE the moment a preview session lost its
// logged_in flag, leaving the banner claiming "viewing as public" while the user quietly
// had full data access — a preview that lies is worse than no preview.
if (!moop_viewing_as_public()) {
    foreach ($ip_ranges as $range) {
        $range_start = $range['start'] ?? null;
        $range_end = $range['end'] ?? null;

        if ($range_start && $range_end) {
            $start_long = ip2long($range_start);
            $end_long = ip2long($range_end);

            if ($visitor_ip_long !== false && $visitor_ip_long >= $start_long && $visitor_ip_long <= $end_long) {
                if (empty($_SESSION["logged_in"])) {
                    $_SESSION["logged_in"] = true;
                    $_SESSION["username"] = "IP_USER_" . $visitor_ip;
                    $_SESSION["access_level"] = 'IP_IN_RANGE';
                    $_SESSION["access"] = [];
                }
                break;
            }
        }
    }
}

/**
 * Helper functions to access session data securely
 * These functions always read from $_SESSION (single source of truth)
 *
 * Each one answers as a PUBLIC visitor while "view as PUBLIC" is active. Everything that
 * decides access — has_access(), has_assembly_access(), has_gene_set_access(),
 * require_access(), admin_access_check.php — is built on these four, so overriding here
 * covers the whole app rather than each call site.
 */
function get_access_level() {
    if (moop_viewing_as_public()) {
        return 'PUBLIC';
    }
    return $_SESSION["access_level"] ?? 'PUBLIC';
}

function get_user_access() {
    if (moop_viewing_as_public()) {
        return [];
    }
    return $_SESSION["access"] ?? [];
}

function is_logged_in() {
    if (moop_viewing_as_public()) {
        return false;
    }
    return $_SESSION["logged_in"] ?? false;
}

function get_username() {
    if (moop_viewing_as_public()) {
        return '';
    }
    return $_SESSION["username"] ?? '';
}

/**
 * The session's users-file role ('admin'), or null.
 *
 * $_SESSION['role'] is a SECOND identity source alongside access_level, and reading it raw
 * bypasses the preview — the Admin Tools link and the annotation-search admin branch both
 * did exactly that. Read it through here so "view as PUBLIC" cannot be half-applied.
 */
function moop_session_role() {
    if (moop_viewing_as_public()) {
        return null;
    }
    return $_SESSION['role'] ?? null;
}

function moop_session_is_admin() {
    return moop_session_role() === 'admin';
}

/**
 * Does this entry's group list contain the magic PUBLIC group?
 *
 * `PUBLIC` is a group NAME with special meaning: its presence in an entry's groups is what
 * makes an assembly visible to unauthenticated visitors. Group names are free text — Manage
 * Groups only trim()s them — so an admin creating "Public" or "public" would previously have
 * produced a group that looked right in the UI and in the JSON but matched none of the
 * case-sensitive in_array('PUBLIC', ...) checks, silently publishing nothing.
 *
 * Defined here rather than in lib/functions_access.php because this file is loaded on every
 * request and carries no includes of its own. PHP resolves function names at CALL time, so
 * the lib/ callers below resolve it fine regardless of include order.
 *
 * @param  mixed $groups The entry's `groups` value (array; anything else is treated as empty)
 * @return bool
 */
if (!function_exists('groups_include_public')) {
function groups_include_public($groups) {
    if (!is_array($groups)) {
        return false;
    }
    foreach ($groups as $g) {
        if (strtoupper(trim((string)$g)) === 'PUBLIC') {
            return true;
        }
    }
    return false;
}
}

/**
 * Whether a single organism_assembly_groups.json entry is public — visible to
 * everyone at access level PUBLIC.
 *
 * Public is a per-gene-set PROPERTY OF THE DATA: the entry's own `public: true`
 * flag, set via the visibility toggle on Manage Groups. A public gene set stays
 * in whatever taxonomic groups it already belongs to — it does NOT join a "PUBLIC"
 * group. The legacy "PUBLIC" group membership is still honoured so any older data
 * keeps working, but new data uses the flag.
 *
 * FAIL-CLOSED: only a literal boolean `true` (or the legacy group) makes an entry
 * public. A missing, string, or malformed flag means NOT public. This is the one
 * place that decides it — every is_public_* check funnels through here.
 *
 * @param  array $entry One decoded organism_assembly_groups.json entry.
 * @return bool
 */
if (!function_exists('entry_is_public')) {
function entry_is_public($entry) {
    if (is_array($entry) && ($entry['public'] ?? null) === true) {
        return true;
    }
    return groups_include_public(is_array($entry) ? ($entry['groups'] ?? null) : null);
}
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
    
    $groups_data = loadJsonFile($groups_file, []);
    if (!$groups_data) {
        return false;
    }
    
    foreach ($groups_data as $entry) {
        if ($entry['organism'] === $organism_name) {
            if (entry_is_public($entry)) {
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
    
    $groups_data = loadJsonFile($groups_file, []);
    if (!$groups_data) {
        return false;
    }
    
    foreach ($groups_data as $entry) {
        if ($entry['organism'] === $organism_name && $entry['assembly'] === $assembly_name) {
            if (entry_is_public($entry)) {
                return true;
            }
        }
    }
    
    return false;
}
}

/**
 * Check if a specific gene_set is public (in PUBLIC group)
 *
 * @param string $organism_name
 * @param string $assembly_name
 * @param string $gene_set
 * @return bool
 */
if (!function_exists('is_public_gene_set')) {
function is_public_gene_set($organism_name, $assembly_name, $gene_set) {
    $config = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');

    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (!file_exists($groups_file)) {
        return false;
    }

    $groups_data = loadJsonFile($groups_file, []);
    if (!$groups_data) {
        return false;
    }

    foreach ($groups_data as $entry) {
        if ($entry['organism'] === $organism_name &&
            $entry['assembly'] === $assembly_name &&
            ($entry['gene_set'] ?? '') === $gene_set) {
            return entry_is_public($entry);
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
    
    $groups_data = loadJsonFile($groups_file, []);
    if (!$groups_data) {
        return false;
    }
    
    foreach ($groups_data as $entry) {
        if (in_array($group_name, $entry['groups']) && entry_is_public($entry)) {
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

    // Level names are case-insensitive. $required_level is caller-supplied, and the
    // IP_IN_RANGE branch below decides on `!== 'ADMIN'` — so a caller writing
    // has_access('admin') would otherwise hand the whole trusted subnet admin rights.
    // Normalised inline (not via lib/functions_access.php) to keep this file, which is
    // loaded on every request, free of includes it does not already have.
    $required_level = strtoupper(trim((string)$required_level));
    $access_level   = strtoupper(trim((string)$access_level));

    // ADMIN can do everything, including satisfy an ADMIN requirement.
    if ($access_level === 'ADMIN') {
        return true;
    }

    // IP_IN_RANGE gets full DATA access (equivalent to a collaborator with every
    // organism) but is NOT an administrator — it must never satisfy an ADMIN
    // requirement, or the whole trusted subnet would gain admin privileges.
    if ($access_level === 'IP_IN_RANGE') {
        return $required_level !== 'ADMIN';
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
        if (isset($user_access[$organism_name][$assembly_name])) {
            return true;
        }
    }

    return false;
}
}

/**
 * Check if user has access to a specific gene_set
 *
 * @param string $organism_name
 * @param string $assembly_name
 * @param string $gene_set
 * @return bool
 */
if (!function_exists('has_gene_set_access')) {
function has_gene_set_access($organism_name, $assembly_name, $gene_set) {
    if (has_access('ADMIN') || has_access('IP_IN_RANGE')) {
        return true;
    }

    if (is_public_gene_set($organism_name, $assembly_name, $gene_set)) {
        return true;
    }

    if (has_access('COLLABORATOR')) {
        $user_access = get_user_access();
        $allowed = $user_access[$organism_name][$assembly_name] ?? [];
        return in_array('*', $allowed) || in_array($gene_set, $allowed);
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
 * Heuristic: does the current request expect a JSON response rather than an HTML page?
 * True for API endpoints (path contains /api/), jQuery/fetch AJAX (X-Requested-With or
 * X-CSRF-Token header), or an explicit Accept: application/json. Used so auth/CSRF
 * failures return a machine-readable error to fetch()/$.ajax callers instead of an HTML
 * page — which would otherwise surface as the cryptic
 * "Unexpected token '<', <!DOCTYPE ... is not valid JSON".
 */
if (!function_exists('request_expects_json')) {
function request_expects_json(): bool {
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    return strpos($script, '/api/') !== false
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
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
        // Answer AJAX/API callers with JSON even if the caller didn't explicitly ask
        // for it — an HTML page here breaks a fetch()/$.ajax caller with a JSON parse error.
        if ($json_response || request_expects_json()) {
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

// ── Cloudflare Turnstile — human verification (once per session) ──────────────
// Runs after all auth (IP-range, session login) so authenticated users are
// never blocked. Only unauthenticated visitors need to pass the challenge.
// Skipped on verify-human.php itself to avoid an infinite redirect loop.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'verify-human.php') {
    $config = ConfigManager::getInstance();
    $ts     = $config->getArray('turnstile', []);
    // "View as PUBLIC" is exempt on purpose. The preview answers "what data does a visitor
    // see", not "does the bot challenge work" — and this deployment is reachable only from
    // internal addresses, so if the challenge could not reach Cloudflare the admin would be
    // bounced into an unpassable redirect with no page left to click "Leave preview" on.
    if (!empty($ts['enabled'])
        && empty($_SESSION['human_verified'])
        && !is_logged_in()
        && !moop_viewing_as_public()
    ) {
        $return = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /' . $config->getString('site', 'moop') . '/verify-human.php?return=' . urlencode($return));
        exit;
    }
    unset($ts);
}
// ─────────────────────────────────────────────────────────────────────────────
