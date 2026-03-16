# MOOP Security Guide

**Last Updated:** March 2026

This document covers the security architecture implemented in MOOP, including
access control, CSRF protection, session security, brute-force prevention,
input validation, and XSS prevention.

---

## Access Control

### Access Levels

MOOP uses four access levels (in order of privilege):

| Level | How Assigned | What They Can See |
|-------|-------------|-------------------|
| `PUBLIC` | Default — no login | Only assemblies in the "PUBLIC" group |
| `IP_IN_RANGE` | Auto-login from configured IP ranges | All organisms and assemblies (no admin panel) |
| `COLLABORATOR` | Logged in with credentials | Specific organisms/assemblies assigned in users.json |
| `ADMIN` | Logged in with admin role | Everything + admin panel |

### Key Functions (`includes/access_control.php`)

```php
is_logged_in()                         // bool
get_access_level()                     // 'PUBLIC' | 'IP_IN_RANGE' | 'COLLABORATOR' | 'ADMIN'
has_access('COLLABORATOR')             // bool — checks level hierarchy
has_assembly_access($organism, $asm)   // bool — checks groups + user access list
require_access('COLLABORATOR')         // exits/redirects if not met
is_public_assembly($organism, $asm)    // bool — checks organism_assembly_groups.json
```

### How Authentication Works

**IP-Based Auto-Login:**
Users from configured IP ranges (set in Admin Dashboard → Site Configuration,
stored in `config_editable.json` as `auto_login_ip_ranges`) are automatically:
- Logged in with username `IP_USER_{ip}`
- Assigned `access_level` = `'IP_IN_RANGE'`
- Given access to all organisms and assemblies
- Blocked from the admin panel (admin requires `'ADMIN'` level)

**Regular Login (`login.php`):**
- Validates username/password against `users.json` (bcrypt hashed)
- Regenerates session ID to prevent session fixation (`session_regenerate_id(true)`)
- Sets `access_level` = `'ADMIN'` or `'COLLABORATOR'` based on role
- Stores user-specific access permissions in session

**Admin Page Protection (`admin/admin_init.php`):**
- Verifies user is logged in with `'ADMIN'` access level
- IP_IN_RANGE users are explicitly blocked (302 redirect to `access_denied.php`)
- Automatically verifies CSRF token on all POST requests

### Session Flow

```
login.php
  └─> Validates username/password (bcrypt)
  └─> session_regenerate_id(true)  ← prevents session fixation
  └─> Sets $_SESSION["logged_in"] = true
  └─> Sets $_SESSION["username"] = username
  └─> Sets $_SESSION["access"] = user_access_array
  └─> Sets $_SESSION["role"] = 'admin' or null
  └─> Sets $_SESSION["access_level"] = 'ADMIN' or 'COLLABORATOR'

access_control.php (included by all pages)
  └─> Checks IP range for auto-login
  └─> Provides helper functions reading from $_SESSION
  └─> Generates CSRF token

admin_init.php (included by all admin pages)
  └─> Validates ADMIN access level
  └─> Verifies CSRF token on POST requests
  └─> Blocks IP_IN_RANGE users

logout.php
  └─> session_destroy()
  └─> Redirects to login
```

### Assembly Visibility

Assembly visibility is controlled by `metadata/organism_assembly_groups.json`.
The special group name `PUBLIC` makes an assembly visible to unauthenticated users.
Logged-in users see assemblies assigned to them in `users.json` plus all PUBLIC assemblies.

---

## CSRF Protection

CSRF (Cross-Site Request Forgery) protection prevents attackers from tricking
authenticated users into submitting malicious requests.

### How It Works

CSRF protection is **centralized** — you don't need to write verification code:

1. **Token generation:** `access_control.php` generates a token on every page load
2. **HTML forms:** Add `<?= csrf_input_field() ?>` inside every `<form method="post">`
3. **AJAX requests:** `js/modules/csrf.js` automatically attaches the token as
   an `X-CSRF-Token` header to all jQuery AJAX calls and fetch requests
4. **Verification:** `admin_init.php` calls `csrf_protect()` on every POST automatically

### Files Involved

| File | Role |
|------|------|
| `includes/access_control.php` | Generates token, provides `csrf_input_field()` and `csrf_protect()` |
| `admin/admin_init.php` | Calls `csrf_protect()` on every POST |
| `js/modules/csrf.js` | Auto-attaches token to jQuery AJAX and fetch requests |
| `includes/head-resources.php` | Emits `<meta name="csrf-token">` tag |

### Adding CSRF to New Code

**New HTML form:**
```php
<form method="post" action="my_page.php">
    <?= csrf_input_field() ?>
    <!-- form fields -->
    <button type="submit">Save</button>
</form>
```

**New admin AJAX endpoint:** Just include `admin_init.php` — CSRF is verified automatically.
jQuery will send the token as `X-CSRF-Token` header automatically via `csrf.js`.

---

## Session Security

### Session Fixation Prevention
`login.php` calls `session_regenerate_id(true)` immediately after successful
authentication. This invalidates the old session ID, preventing an attacker
from pre-setting a session ID and waiting for the user to authenticate with it.

### Session Properties
- Session data (`$_SESSION`) is the **single source of truth** for access control
- Access level is never read from GET/POST parameters — only from session
- Helper functions always read fresh from `$_SESSION` (no caching)

---

## Brute-Force Login Protection

Implemented in `lib/functions_login_protection.php`, called from `login.php`.

| Threshold | Action |
|-----------|--------|
| 5 failed attempts | 2-second delay before response |
| 10 failed attempts | 15-minute account lockout |

State is tracked in `logs/login_attempts.json` (per-username, with timestamps).
Successful login resets the counter for that username.

---

## Path Traversal Prevention

File download handlers (e.g., `lib/fasta_download_handler.php`) use `realpath()`
to resolve the actual filesystem path, then verify it starts with the expected
base directory:

```php
$real = realpath($requested_path);
if ($real === false || strpos($real, $base_dir) !== 0) {
    // Reject — path traversal attempt
    http_response_code(403);
    exit;
}
```

This prevents `../../etc/passwd` style attacks.

---

## Shell Command Safety

All shell commands use `escapeshellarg()` on every user-supplied argument:

```php
$cmd = $blast_path . ' -db ' . escapeshellarg($db) . ' -evalue ' . escapeshellarg($evalue);
exec($cmd, $output, $return_code);
```

Never interpolate user input directly into shell strings.

---

## SQL Injection Prevention

All database queries use PDO prepared statements with parameterized values:

```php
$stmt = $dbh->prepare('SELECT * FROM feature WHERE feature_id = ?');
$stmt->execute([$feature_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

Never use `$dbh->query()` with user-supplied values interpolated in.

---

## Proxy/IP Warning

If IP-based auto-login is enabled and an `X-Forwarded-For` header is detected,
`access_control.php` logs a warning. Behind a reverse proxy, `REMOTE_ADDR` is
the proxy's IP (not the visitor's), which could grant unintended access.

---

## JBrowse2 Track Authentication

Track files in `data/tracks/` are protected by JWT (JSON Web Token) authentication:

1. `.htaccess` in `data/tracks/` blocks all direct access (returns 403)
2. Track requests go through `api/jbrowse2/tracks.php`
3. `tracks.php` validates the JWT token before serving the file
4. Tokens are signed with RSA keys in `certs/` (private key signs, public key verifies)
5. Tokens expire after 1 hour

---

## XSS (Cross-Site Scripting) Prevention

### Output Escaping

All user-controlled data displayed in HTML is escaped using `htmlspecialchars()`:

```php
<?= htmlspecialchars($feature_name) ?>
```

This converts dangerous characters (`<`, `>`, `&`, `"`, `'`) to HTML entities.

### Encoding by Context

| Context | Function |
|---------|----------|
| HTML text | `htmlspecialchars()` |
| HTML attributes | `htmlspecialchars($val, ENT_QUOTES)` |
| URLs | `urlencode()` or `rawurlencode()` |
| JSON | `json_encode()` |
| JavaScript data | `json_encode()` via inline_scripts |

### PHP to JavaScript Data Passing

Pass PHP values to JavaScript via the `inline_scripts` key in the `$data` array
(never interpolate PHP variables directly into `<script>` tags):

```php
'inline_scripts' => [
    "const sitePath = " . json_encode($site_path) . ";",
    "const organism = " . json_encode($organism_name) . ";",
]
```

---

## Security Checklist for Code Review

When reviewing or writing MOOP code:

- [ ] All `echo`/`<?=` with variables use `htmlspecialchars()`?
- [ ] All database queries use prepared statements with `?` placeholders?
- [ ] All forms include `<?= csrf_input_field() ?>`?
- [ ] All admin endpoints include `admin_init.php`?
- [ ] All shell commands use `escapeshellarg()` on arguments?
- [ ] All file path operations validate with `realpath()` + base-dir check?
- [ ] URLs in href attributes are properly encoded?
- [ ] JSON responses use `json_encode()`?
- [ ] Error messages don't leak sensitive information?
- [ ] No hardcoded passwords or API keys in code?

---

## Files Reference

| File | Security Role |
|------|--------------|
| `includes/access_control.php` | Auth functions, CSRF token generation, IP auto-login |
| `admin/admin_init.php` | Admin auth + CSRF verification on POST |
| `admin/admin_access_check.php` | Validates ADMIN role, blocks IP_IN_RANGE |
| `js/modules/csrf.js` | Auto-attaches CSRF token to AJAX requests |
| `includes/head-resources.php` | Emits CSRF meta tag |
| `login.php` | Authentication, session fixation fix |
| `lib/functions_login_protection.php` | Brute-force protection (delay + lockout) |
| `lib/fasta_download_handler.php` | Path traversal prevention |
| `api/jbrowse2/tracks.php` | JWT validation for track access |
| `lib/jbrowse/track_token.php` | JWT token generation and verification |

---

## Resources

- [OWASP XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP htmlspecialchars()](https://www.php.net/manual/en/function.htmlspecialchars.php)
- [PHP Prepared Statements](https://www.php.net/manual/en/pdo.prepared-statements.php)
