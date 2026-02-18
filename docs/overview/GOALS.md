# MOOP System Goals & Principles

**Last Updated:** November 7, 2025

---

## Core System Goals

The MOOP platform is built around these five interconnected goals:

### 1. 🔤 Clear Code
**Code should be self-explanatory and easy to understand on first read.**

**What this means:**
- Variable names describe their purpose (`$organism_name` not `$o`)
- Function names describe what they do (`validateDatabaseIntegrity()` not `checkDB()`)
- Logic flows top-to-bottom without confusion
- Comments explain "why", not "what"
- No cryptic abbreviations or unclear patterns

**How we achieve it:**
- Use semantic naming conventions
- Keep functions focused (do one thing well)
- Avoid nested ternary operators and complex conditionals
- Use helper functions to break down complex logic
- Document non-obvious behavior

**Example - GOOD:**
```php
$db_validation = validateDatabaseIntegrity($organism_db_path);
if (!$db_validation['database_valid']) {
    logError('Invalid database', $organism_name, [
        'errors' => $db_validation['errors']
    ]);
    return;
}
```

**Example - BAD:**
```php
$v = chkDB($p); 
if (!$v) { die('err'); }  // What was being checked? Why fail silently?
```

---

### 2. 📚 Easy to Maintain
**Future developers should be able to modify code without breaking things.**

**What this means:**
- Changes in one place don't cascade unexpectedly
- New features don't require hunting through 10 files
- Related code lives together (not scattered)
- Clear dependencies between modules
- Comprehensive error logging for debugging

**How we achieve it:**
- DRY principle: Don't repeat code - extract duplicates into functions
- Centralized configuration (single point of change)
- Separated concerns (display logic ≠ database logic ≠ security logic)
- Helper functions for common patterns
- Well-documented dependencies

**Example - GOOD:**
```php
// Single place for button configuration
// Used by organism_display.php, groups_display.php, parent_display.php
$buttons = DataTableExportConfig.getSearchResultsButtons();
```

**Example - BAD:**
```php
// Button config copied into every display page
// Change one = update 3 files = easy to miss one
// Copy/paste code in organism_display.php
// Copy/paste code in groups_display.php  
// Copy/paste code in parent_display.php
```

---

### 3. 🔒 Secure System
**User data is protected, unauthorized access is prevented, errors don't leak sensitive info.**

**What this means:**
- Access control based on `$_SESSION` only (never global variables)
- No SQL injection (prepared statements always)
- No URL parameter manipulation (security checks server-side)
- Errors logged, not shown to users
- Admin-only pages actually protected
- IP-based auto-login works without security holes

**How we achieve it:**
- Helper functions for access checks (`is_logged_in()`, `get_access_level()`)
- Session-based authentication (authoritative source of truth)
- Prepared statements with parameter binding
- Server-side validation and verification
- Comprehensive error logging to `/admin/error_log.php`
- Defense in depth (multiple security layers)

**Example - GOOD:**
```php
// Helper always reads fresh from $_SESSION
if (!is_logged_in() || get_access_level() !== 'Admin') {
    header("Location: /access_denied.php");
    exit;
}
```

**Example - BAD:**
```php
// Global variable can be stale or manipulated
global $logged_in, $access_level;
if (!$logged_in) { ... }  // What if session changed? What if someone modified $logged_in?
```

---

### 4. 🎨 Clean CSS
**Styling is organized, reusable, and not scattered throughout code.**

**What this means:**
- All styles in `css/moop.css` (single source of truth)
- NO inline `style` attributes on HTML elements
- NO embedded `<style>` blocks in PHP files
- CSS classes have semantic names (describe purpose, not appearance)
- No repeated CSS rules
- Responsive design principles

**How we achieve it:**
- Use CSS classes, not inline styles
- Create utility and component classes
- Keep Bootstrap integration clean
- Organized sections in CSS file
- Media queries for responsive design

**Example - GOOD:**
```html
<!-- HTML is clean, readable, semantic -->
<div class="organism-card">
    <div class="organism-icon">...</div>
    <div class="organism-name">...</div>
</div>
```
```css
/* CSS is centralized and reusable */
.organism-card { background: white; border-radius: 8px; }
.organism-icon { width: 50px; height: 50px; }
```

**Example - BAD:**
```html
<!-- Mixed concerns, hard to maintain -->
<div style="background: white; border-radius: 8px; padding: 15px;">
    <div style="width: 50px; height: 50px; background: #ccc;">...</div>
</div>
```

---

### 5. 🧹 No Duplicated Code
**Don't repeat yourself - extract common patterns into reusable functions.**

**What this means:**
- One function, used in multiple places
- Copy/paste code is a code smell
- Helpers reduce complexity
- Maintenance is centralized
- Changes apply everywhere automatically

**How we achieve it:**
- Extract repeated patterns into functions
- Use configuration arrays for repeated data
- Leverage helper functions from `moop_functions.php`
- DataTables button config centralized in `datatable-config.js`
- Database validation in `validateDatabaseIntegrity()`

**Example - GOOD:**
```php
// One function, used everywhere
function logError($message, $context, $details) { ... }

// Called in multiple places
logError('Database error', $organism, []);
logError('Permission denied', $file, []);
logError('Invalid data', $user, []);
```

**Example - BAD:**
```php
// Duplicated pattern - violation of DRY principle
file_put_contents(...);  // In file1.php
file_put_contents(...);  // In file2.php
file_put_contents(...);  // In file3.php
// If the pattern needs to change, you have to find and fix all 3
```

---

## Supporting Goals: Admin Tools

A great admin panel helps manage users and data effectively.

### Admin Tools Available
- ✅ **Error Log Viewer** - `/admin/error_log.php` (filter, search, clear logs)
- ✅ **User Management** - Create/manage user accounts and access levels
- ✅ **Organism Management** - Add/edit organisms and configure access
- ✅ **Annotation Management** - View and manage annotation sources
- ✅ **Group Management** - Organize organisms into groups
- ✅ **Phylogenetic Tree** - Configure tree structure and relationships

### Admin Tool Principles
1. **Transparent** - Admins can see what's happening (error logs, user activity)
2. **Powerful** - Admins can make broad changes (clear logs, manage users)
3. **Safe** - Admins can't accidentally break things (confirmations, backups)
4. **Informative** - Clear feedback on actions (success/error messages)
5. **Well-Protected** - Requires authentication and admin role

---

## How These Goals Work Together

```
Clear Code
    ↓
Easy to Maintain (clear code is easier to maintain)
    ↓
Secure System (maintainable code is easier to secure)
    ↓
Clean CSS & No Duplication (reduces complexity, improves security)
    ↓
Good Admin Tools (admins can effectively manage the secure, maintainable system)
```

---

## Code Quality Checklist

When writing new code, ask yourself:

### Clarity
- [ ] Variable names clearly describe their purpose
- [ ] Function names describe what they do
- [ ] Logic is easy to follow top-to-bottom
- [ ] No cryptic abbreviations
- [ ] Comments explain "why", not "what"

### Maintainability
- [ ] This functionality isn't duplicated elsewhere
- [ ] Related code is grouped together
- [ ] Dependencies are clear
- [ ] Changes in one place don't break others
- [ ] Configuration is centralized

### Security
- [ ] Using `is_logged_in()`, `get_access_level()` helpers (not globals)
- [ ] Using prepared statements (not raw SQL)
- [ ] URL parameters not trusted for access control
- [ ] Errors are logged, not shown to users
- [ ] No sensitive data in logs

### CSS
- [ ] Styles are in `css/moop.css` (not inline)
- [ ] Using CSS classes (not inline styles)
- [ ] CSS class names describe purpose
- [ ] No repeated CSS rules
- [ ] Responsive design considered

### Code Duplication
- [ ] Logic is extracted to helper functions
- [ ] No copy/paste code
- [ ] Repeated patterns use configuration arrays
- [ ] Common operations use shared helpers

---

## Key Files for Each Goal

### Clear Code
- See `FUNCTION_REFERENCE.md` for function naming and purposes
- See `copilot-instructions.md` for code patterns

### Easy to Maintain
- `/data/moop/tools/moop_functions.php` - Helper functions
- `/data/moop/js/datatable-config.js` - Centralized button config
- `/data/moop/site_config.php` - Global configuration

### Secure System
- `/data/moop/access_control.php` - Session-based access helpers
- `/data/moop/SECURITY_IMPLEMENTATION.md` - Complete security documentation
- See `copilot-instructions.md` Security section

### Clean CSS
- `/data/moop/css/moop.css` - Main stylesheet
- See `copilot-instructions.md` Styling Guidelines

### No Duplicated Code
- Use `FUNCTION_REFERENCE.md` to check what helpers exist
- Ask: "Has someone already solved this problem?"
- Extract patterns into `moop_functions.php` or helpers

### Admin Tools
- `/data/moop/admin/error_log.php` - View and manage errors
- `/data/moop/admin/` - Other management tools

---

## System Architecture at a Glance

```
User Interface (HTML/CSS/JavaScript)
    ↓
Access Control (session-based security checks)
    ↓
Page Logic (organize data, prepare for display)
    ↓
Database Layer (prepared statements, validation)
    ↓
SQLite Databases (one per organism)

Error Logging (all errors tracked)
Admin Tools (manage users, data, logs)
```

---

## Standards Applied

### Naming Conventions
- **Files:** lowercase_with_underscores.php
- **Functions:** camelCase()
- **Constants:** UPPERCASE_WITH_UNDERSCORES
- **Variables:** $snake_case
- **CSS Classes:** kebab-case

### Code Organization
- **Includes at top:** Config, security, helpers
- **Logic in middle:** Processing, validation, database
- **Output at bottom:** HTML, JSON, redirects

### Documentation
- **Code comments:** Explain non-obvious logic only
- **PHPDoc:** On functions (parameters, return value)
- **Markdown files:** High-level documentation

---

## Continuous Improvement

As you maintain the system:
1. ✅ Follow these goals when writing new code
2. ✅ Refactor code that violates these principles
3. ✅ Extract duplicate code into helper functions
4. ✅ Log errors for admin visibility
5. ✅ Check admin tools regularly for issues
6. ✅ Keep CSS consolidated in `moop.css`
7. ✅ Use prepared statements always
8. ✅ Use session helpers for access control

---

## Summary

**Clear + Maintainable + Secure + Clean + No Duplication = Excellent System**

A system that is easy to read is easy to maintain. A system that is easy to maintain is easy to secure. A secure system with good admin tools is a professional system that can grow and evolve.

These five goals are interconnected - achieving one makes the others easier to achieve. Focus on all five, and you'll build something great.
