# MOOP Security Guide

**Last Updated:** 2026-11-25 (merged in the old `AUTHENTICATION_FLOW_DIAGRAM.md`; added the
Content-Security-Policy nonce, the web-server guard and bot protection)

This document covers the security architecture implemented in MOOP, including
access control, CSRF protection, session security, brute-force prevention,
input validation, XSS prevention, the Content-Security-Policy, the web-server guard and bot
protection. Hardening the host itself (SELinux, permissions, the no-exec guard's file name):
`docs/SELINUX_AND_HARDENING.md`.

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

## Passwords and user accounts

- Accounts live in `users.json` **outside the document root** (`/var/www/moop-private/users.json`
  here; the `users_file` setting). Passwords are stored as `password_hash(..., PASSWORD_DEFAULT)`
  (bcrypt, `$2y$...`) and checked with `password_verify()` in `login.php`.
- Created and changed on Admin -> Manage Users. Locked out or forgotten:
  `sudo -u apache php scripts/reset_password.php <user>` (`--list`, `--unlock`).
- ⚠️ Known gap (2026-11-25): `login.php` runs `password_verify()` only when the username exists,
  so a wrong username answers slightly faster than a wrong password -- in principle a way to test
  which usernames exist. (The old flow diagram called login "timing-attack resistant"; it is not.)
  Fix: verify against a dummy hash when the user is unknown.

## Who gets what -- the four kinds of visitor

| Visitor | How they get in | What they see |
|---|---|---|
| Public | nothing | public gene sets only (`is_public_assembly()`) |
| Inside the institute | address in `auto_login_ip_ranges` -> `IP_IN_RANGE` | all data, no admin pages |
| Collaborator | logs in | public data plus what their account and groups allow |
| Administrator | logs in, role admin | everything, plus the admin pages |

## Content-Security-Policy (CSP)

Two policies are sent and the browser enforces BOTH:
- nginx's (`/etc/nginx/nginx.conf`, template `docs/nginx/moop-headers.conf`): the allowed outside
  hosts for scripts, styles, images, connections and frames.
- PHP's (`includes/csp.php`, sent from `config_init.php` on every page since 2026-11-25): a strict
  `script-src 'self' 'nonce-...'`. An inline `<script>` runs only with the per-request nonce:
  write `<script<?= moop_nonce_attr() ?>>` (`layout.php`'s `inline_scripts` already do).
- So: no inline `on*=` handlers and no `javascript:` links -- they can never carry a nonce.
  `js/modules/utilities.js` handles the common cases declaratively (`data-confirm`,
  `data-click`, `data-action="reload"`, `img data-fallback`); CLAUDE.md section 7.
- A new outside script host goes in both policies.

## Web-server guard -- what the web never serves

`docs/nginx/moop-security.conf` (per site: `scripts/nginx_site_guard.sh <site> [prefix]`) is deployed
twice per site on this host: `/etc/nginx/moop-sites/<site>/guard.conf` (the site's own address,
prefix `/`) and `/etc/nginx/default.d/<deploy-name>` (the machine's address, prefix `/<folder>`).
It refuses to run `.php` in data/cache trees, and since 2026-11-25 returns 404 for `scripts/`,
`tests/`, `vendor/`, `notes/`, `docs/`, `lib/` and `includes/` (except
`lib/fasta_download_handler.php`), and `CLAUDE.md`, `README.md`, `composer.*`, `phpcompat.xml`.
`setup-check.php` compares both deployed copies with the script's output.

## Bot protection

nginx per-address rate limits by request class (`docs/nginx/moop-ratelimit*.conf`; the heavy
group and the exempt ranges are MOOP settings applied by the System Monitor's collector) and a
Cloudflare Turnstile human check (`lib/human_check.php`). Plan: `notes/BOT_PROTECTION_PLAN.md`.

## Troubleshooting logins

- "Invalid username or password" with the right password: the account may be locked
  (`scripts/reset_password.php --list`), or the hash is not `$2y$...` (reset it).
- Logged out on every page: the session cookie is per site (`moop_cookie_name()`) and `Secure` on
  https -- check the site is reached by its https name, not mixed http/https.
- Inside-the-institute auto-login not working: the visitor's address must be in
  `auto_login_ip_ranges`; behind a proxy MOOP sees the proxy's address (it logs a warning).

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
| `includes/csp.php` | Per-request nonce + strict script-src policy |
| `includes/session_init.php` | Session cookie (per-site name, HttpOnly, SameSite=Lax, Secure on https) |
| `lib/human_check.php` | Turnstile human check |
| `docs/nginx/moop-security.conf`, `scripts/nginx_site_guard.sh` | Web-server guard (no-exec + denied folders) |
| `scripts/reset_password.php` | Unlock / reset an account from the command line |

---

## Resources

- [OWASP XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP htmlspecialchars()](https://www.php.net/manual/en/function.htmlspecialchars.php)
- [PHP Prepared Statements](https://www.php.net/manual/en/pdo.prepared-statements.php)
