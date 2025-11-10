# Copilot Instructions for MOOP Project

---

## ⚡ Quick Reference (Most Important Rules)

**Use these for rapid decisions:**

1. **CSS:** Use classes in `css/moop.css` → NEVER inline `style` attributes
2. **Security:** Use `is_logged_in()`, `get_access_level()` helpers → NEVER global variables
3. **Errors:** Call `logError()` when operations fail (database, validation, permissions, etc.)
4. **Libraries:** DO NOT change Bootstrap 5.3.2 or DataTables 1.13.4 versions (tested & locked)
5. **Code:** Extract duplicates into helpers in `moop_functions.php` → Follow DRY principle
6. **Database:** Use prepared statements with parameter binding → NEVER raw SQL
7. **Sessions:** `$_SESSION` is authoritative → Never cache in globals
8. **URLs:** Never trust GET/POST for security decisions → Always validate server-side

**See detailed sections below for examples and reasoning.**

---

## 🚀 Migration Goal: Move Away from easy_gdb

**The old easy_gdb system is being replaced by MOOP. Do NOT maintain backwards compatibility.**

### Why This Matters
- easy_gdb is legacy code (old, inefficient, hard to maintain)
- MOOP is the new standard (cleaner, more secure, modern)
- Supporting both = double maintenance burden
- Goal: Completely replace easy_gdb

### What This Means
```php
// ❌ DON'T - Don't check for easy_gdb compatibility
if (isset($_GET['easy_gdb_mode'])) {
    // Use old easy_gdb logic
}

// ✅ DO - Only support MOOP
// Use new MOOP patterns exclusively
```

### New Code Policy
When writing new features:
1. **Only use MOOP** - Don't add easy_gdb compatibility
2. **No fallbacks** - Don't write "works with both"
3. **No legacy checks** - Don't support old parameter names
4. **No dual code paths** - One way: the MOOP way

### Refactoring Old Code
When refactoring any old easy_gdb code:
1. **Completely rewrite** using MOOP patterns
2. **Remove all easy_gdb references** (comments, variables, checks)
3. **Use modern approaches** (prepared statements, session-based access, etc.)
4. **Delete old branches** - Don't keep legacy "just in case"

**See `REMAINING_TODOS.md` for easy_gdb refactoring opportunities.**

---

## 🎯 System Goals

The MOOP platform prioritizes five interconnected goals:

1. **🔤 Clear Code** - Self-explanatory, easy to read (meaningful names, logical flow, comments explain "why")
2. **📚 Easy to Maintain** - Centralized configuration, helper functions, no code duplication, clear dependencies
3. **🔒 Secure System** - Session-based access control (no globals), prepared statements, error logging, defense in depth
4. **🎨 Clean CSS** - Styles in `css/moop.css` only (no inline styles), semantic class names, reusable components
5. **🧹 No Duplication** - Extract repeated patterns into helpers, use configuration arrays, one source of truth

**How they work together:** Clear code → Easier to maintain → Easier to secure → Cleaner system → Better admin tools

### Supporting Goal: Admin Tools
- Error log viewer with filtering and search
- User and organism management
- Data validation and monitoring
- Safe, transparent administration

**See `SYSTEM_GOALS.md` for detailed explanation of each goal with examples.**

---

## 📊 Generic Data Handling: Parent/Child Relationships

**IMPORTANT:** Code must be GENERIC for display, but show MEANINGFUL TEXT to users.

### The Problem

Current code hardcodes "gene" and "mRNA" throughout:
```php
// ❌ BAD - Hardcoded, non-generic
echo "This gene has " . count($children) . " alternative transcripts/isoforms (mRNA)";
$child_color_map = ['mRNA' => '#17a2b8', 'gene' => '#764ba2'];
```

In the future, we may have different parent/child relationships:
- Chromosome → Contig
- Protein → Domain
- Assembly → Chromosome
- Any custom relationships

### The Solution

**Use configuration + database to determine types, display meaningful labels to users:**

#### 1. Store Parent/Child Type Information in Config

**File:** `/data/moop/site_config.php` or organism-specific config

```php
// Define what parent types exist (from database schema or config)
// Used internally, not shown to users
$parent_types = ['gene', 'pseudogene'];
$child_types = ['mRNA', 'transcript', 'isoform'];

// Define USER-FRIENDLY display names
// What users actually see on screen
$feature_display_names = [
    'gene' => 'Gene',
    'pseudogene' => 'Pseudogene',
    'mRNA' => 'mRNA Transcript',
    'transcript' => 'Transcript',
    'isoform' => 'Isoform',
    'exon' => 'Exon',
    'CDS' => 'Coding Sequence'
];

// Define relationship descriptions
// Users see this when explaining relationships
$relationship_descriptions = [
    'gene-mRNA' => 'This %parent% has %count% alternative %children%',
    'chromosome-contig' => 'This %parent% contains %count% %children%',
    'protein-domain' => 'This %parent% includes %count% conserved %children%'
];
```

#### 2. Load Types from Database (Generic)

```php
<?php
// Query database for parent feature types
$parent_types = [];
$child_types = [];

try {
    $db = new PDO('sqlite:' . $db_path);
    
    // Get unique parent types from the database
    $stmt = $db->query("
        SELECT DISTINCT parent_feature.feature_type 
        FROM feature AS child_feature
        JOIN feature_relationship ON child_feature.feature_id = feature_relationship.subject_id
        JOIN feature AS parent_feature ON feature_relationship.object_id = parent_feature.feature_id
        WHERE parent_feature.uniquename = ?
    ");
    
    // Get child types for this parent
    $stmt = $db->prepare("
        SELECT DISTINCT child_feature.feature_type 
        FROM feature AS child_feature
        JOIN feature_relationship ON child_feature.feature_id = feature_relationship.subject_id
        WHERE feature_relationship.object_id = (
            SELECT feature_id FROM feature WHERE uniquename = ?
        )
    ");
    $stmt->execute([$uniquename]);
    $child_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    logError('Failed to load feature types', $organism_name, [
        'error' => $e->getMessage()
    ]);
}
?>
```

#### 3. Create Helper Functions (Generic Code, Meaningful Output)

**File:** `/data/moop/tools/moop_functions.php`

```php
/**
 * Get user-friendly display name for a feature type
 * 
 * @param string $feature_type - Internal type (e.g., 'mRNA', 'gene')
 * @return string - User-friendly name (e.g., 'mRNA Transcript')
 */
function getDisplayName($feature_type) {
    global $feature_display_names;
    
    if (!isset($feature_display_names)) {
        $feature_display_names = [
            'gene' => 'Gene',
            'pseudogene' => 'Pseudogene',
            'mRNA' => 'mRNA Transcript',
            'transcript' => 'Transcript',
            'isoform' => 'Isoform'
        ];
    }
    
    return $feature_display_names[$feature_type] ?? ucfirst($feature_type);
}

/**
 * Get pluralized display name
 */
function getDisplayNamePlural($feature_type, $count = 2) {
    $singular = getDisplayName($feature_type);
    
    // Simple pluralization rules
    if (substr($singular, -1) === 's') {
        return $singular . 'es';
    }
    return $singular . 's';
}

/**
 * Build relationship description for display
 * 
 * @param string $parent_type - Parent feature type
 * @param string $child_type - Child feature type
 * @param int $count - Number of children
 * @return string - User-friendly description
 */
function getRelationshipDescription($parent_type, $child_type, $count) {
    $parent_name = getDisplayName($parent_type);
    $child_name = getDisplayNamePlural($child_type, $count);
    
    return "This $parent_name has $count alternative $child_name";
}
```

#### 4. Use in Display (Generic Yet Meaningful)

```php
<?php
// Instead of:
// ❌ echo "This gene has " . count($children) . " alternative transcripts/isoforms (mRNA)";

// Do this:
// ✅ Use helper functions
$parent_type = $parent_feature['feature_type']; // From database
$first_child_type = $children[0]['feature_type']; // From database

$relationship_text = getRelationshipDescription(
    $parent_type, 
    $first_child_type, 
    count($children)
);

echo "<strong>Multiple Children:</strong> $relationship_text";
?>
```

#### 5. Store Colors Generically (Not Hardcoded)

**Instead of:**
```php
// ❌ BAD - Hardcoded
$child_color_map = ['mRNA' => '#17a2b8', 'gene' => '#764ba2'];
$color = $child_color_map[$child_type] ?? '#17a2b8';
```

**Do this:**
```php
// ✅ GOOD - Load from config
function getFeatureTypeColor($feature_type) {
    global $annotation_colors;
    
    // Fallback to a default color if not defined
    return $annotation_colors[$feature_type] ?? '#6c757d';
}
```

### Guidelines for Generic Data Handling

#### DO:
- ✅ Load feature types from database/config (not hardcode them)
- ✅ Use helper functions to convert types to display names
- ✅ Store display logic in functions (not scattered in HTML)
- ✅ Show users friendly names like "mRNA Transcript" not "mRNA"
- ✅ Use configuration for customizable values (colors, descriptions)
- ✅ Comment which values are internal vs. user-facing

#### DON'T:
- ❌ Hardcode "gene", "mRNA", "transcript" in display HTML
- ❌ Hardcode color maps specific to one relationship
- ❌ Use feature type directly in user-facing text
- ❌ Scatter formatting logic throughout PHP code
- ❌ Assume all organisms have genes as parents

### Example: Making It Generic

**Current (Hardcoded):**
```php
echo '  <strong>Multiple Children:</strong> This gene has ' . count($children) . ' alternative transcripts/isoforms (mRNA). ';
```

**Generic & Meaningful:**
```php
$parent_type = $parent['feature_type'];      // From DB: 'gene'
$child_type = $children[0]['feature_type'];  // From DB: 'mRNA'
$count = count($children);

$description = getRelationshipDescription($parent_type, $child_type, $count);
// Output: "This Gene has 3 alternative mRNA Transcripts"

echo "<strong>Multiple Children:</strong> $description";
```

### Future-Proofing

When a new relationship is added (e.g., Chromosome → Contig):

1. ✅ New types automatically work with generic code
2. ✅ Add display names to config: `'contig' => 'Contig Region'`
3. ✅ Add colors to config: `'contig' => '#28a745'`
4. ✅ No code changes needed (already generic!)

### See Also
- `SYSTEM_GOALS.md` - "No Duplicated Code" and "Easy to Maintain" sections
- `parent_display.php` - Current implementation (TODO: refactor to use these patterns)

---

## 🔧 Critical Library Versions

### ✅ REQUIRED (DO NOT CHANGE)
- **Bootstrap:** 5.3.2
- **jQuery:** 3.6.0
- **DataTables Core:** 1.13.4
- **DataTables Buttons:** 2.3.6 (JS)
- **DataTables Buttons:** 1.6.4 (JS only - hybrid approach for functionality)
- **DataTables 1.10.24** (JS - legacy compatibility layer)
- **Font Awesome:** 5.7.0 (button icons)
- **jszip:** 3.10.1 (Excel export)

### Why This Stack?
The download buttons require a **hybrid approach** combining modern (2.3.6) and legacy (1.6.4 + 1.10.24) libraries:
- Buttons 2.3.6 JS = Modern functionality (copy, csv, excel, print, colvis)
- Buttons 1.6.4 JS + DataTables 1.10.24 JS = Required for buttons to work with 2.3.6
- Font Awesome 5.7.0 = Button icons (remove it = icons disappear)

**Testing confirmed:** Removing any of these breaks button functionality

### NOT Needed (Already Removed)
- ~~DataTables Buttons 1.6.4 CSS~~ (2.3.6 CSS sufficient)
- ~~colReorder 1.5.5 JS~~ (sort works fine without it)
- ~~pdfmake~~ (users use browser print-to-PDF)

---

### ✅ DO:
- Use CSS classes from `moop/css/moop.css` for all styling
- Define new styles in `moop/css/moop.css` when adding new components
- Use semantic HTML class names that reflect purpose, not appearance

### ❌ DON'T:
- Use inline `style` attributes on HTML elements
- Use inline `<style>` tags in PHP files
- Apply styles directly to elements via JavaScript

### Example:
```php
// ❌ BAD
<div style="color: blue; padding: 10px;">Content</div>

// ✅ GOOD
<div class="content-box">Content</div>
// Then define in moop/css/moop.css:
// .content-box { color: blue; padding: 10px; }
```

### When Making Changes:
1. Always check if a style class already exists in `moop/css/moop.css`
2. If styling a new component, add the CSS class definition to `moop/css/moop.css`
3. Apply the class to the HTML element instead of using inline styles

---

## 🔒 Security: Sessions Only, No Global Variables

**SECURITY IS CRITICAL - This is not optional.**

### ✅ Golden Rule
- **Use `$_SESSION` directly** - ONLY authoritative source for access control
- **NEVER use global variables** for access data - These get cached and become stale
- **Use helper functions** - They always read fresh from `$_SESSION`

### ❌ NEVER DO THIS
```php
// BAD - Global variables can be stale or manipulated
global $logged_in, $access_level, $username;
if ($logged_in) { ... }

// BAD - Cached data loses synchronization
$_SESSION["access_level"] = "Admin";
global $access_level;  // Now $access_level might differ from session
```

### ✅ ALWAYS DO THIS
```php
// GOOD - Helper functions always read fresh from $_SESSION
if (is_logged_in()) { ... }
$level = get_access_level();  // Always current session value
$user = get_username();       // Always current session value
```

### Helper Functions (Always Use These)
Located in `/data/moop/access_control.php`:

```php
is_logged_in()       // Returns: true/false (reads $_SESSION["logged_in"])
get_access_level()   // Returns: 'Public', 'Collaborator', 'Admin', or 'ALL'
get_user_access()    // Returns: array of organisms user can access
get_username()       // Returns: username string
```

### Why This Matters

**Global Variable Caching Problem:**
```
Session Updated (access_level changed)
    ↓
Global variable NOT automatically updated (STALE!)
    ↓
Page security check uses stale global instead of fresh session
    ↓
SECURITY VULNERABILITY!
```

**Session-Based Solution:**
```
Session Updated
    ↓
Helper function reads from $_SESSION
    ↓
Always gets fresh, current value
    ↓
SECURE!
```

### Access Control Pattern
When protecting a page or checking access:

```php
<?php
include_once __DIR__ . '/access_control.php';

// Require specific access level (will redirect if not authorized)
require_access('Collaborator');

// OR check conditionally
if (is_logged_in() && get_access_level() === 'Admin') {
    // Show admin features
}
?>
```

### Never Trust GET/POST for Access
```php
// ❌ NEVER - User could manipulate URL parameters
if ($_GET['access_level'] === 'Admin') { ... }

// ✅ ALWAYS - Use session which is server-side secure
if (get_access_level() === 'Admin') { ... }
```

### Session-Based Flow
1. User logs in → `login.php` sets `$_SESSION` data
2. Page includes `access_control.php`
3. Page calls helper functions which read `$_SESSION` directly
4. All security checks based on session (fresh, authoritative)
5. Logout → `session_destroy()` clears all data

### Key Benefits
- ✅ No stale cache issues
- ✅ Single source of truth (`$_SESSION` only)
- ✅ No URL parameter injection possible
- ✅ Immediate effect if session data changes
- ✅ Consistent security across all pages

**See `SECURITY_IMPLEMENTATION.md` for detailed explanation and configuration.**

---

## 📋 Error Logging: Essential for Debugging

**Error logging is required for all new code.** Admins view errors in `/admin/error_log.php`.

### ✅ When to Log Errors

Log errors when:
- **Database operations fail** - File not found, connection errors, query errors
- **Data validation fails** - Missing required fields, invalid format, incomplete records
- **File operations fail** - Can't read/write files, permission errors
- **External service fails** - API errors, system command failures
- **Unexpected conditions occur** - Null values where data expected, type mismatches

### ✅ How to Log Errors
Use the `logError()` function from `/data/moop/tools/moop_functions.php`:

```php
logError($error_message, $context, $additional_info);
```

**Parameters:**
- `$error_message` (string) - What went wrong (e.g., "Database file not accessible")
- `$context` (string) - Where it happened (e.g., organism name, feature name)
- `$additional_info` (array) - Extra details for debugging

### ✅ Error Logging Examples

#### Example 1: Database File Error
```php
// Check if database file exists
if (!file_exists($db_path)) {
    logError(
        'Database file not found',
        $organism_name,
        [
            'search_term' => $search_term,
            'searched_paths' => [$db_path]
        ]
    );
    // Then handle error gracefully:
    echo "Database not available for this organism.";
    return;
}
```

#### Example 2: Data Validation Error
```php
// Validate database has required data
$result = validateDatabaseIntegrity($db_path);
if (!$result['database_valid']) {
    logError(
        'Invalid SQLite database',
        $organism_name,
        [
            'search_term' => $search_term,
            'validation_errors' => $result['errors']
        ]
    );
    return;
}
```

#### Example 3: Incomplete Records Error
```php
// Check for missing required fields
$missing_annotations = [];
foreach ($records as $record) {
    if (empty($record['annotation_source']) || empty($record['annotation_accession'])) {
        $missing_annotations[] = $record;
    }
}

if (!empty($missing_annotations)) {
    logError(
        'Incomplete annotation records found',
        $organism_name,
        [
            'search_term' => $search_term,
            'count' => count($missing_annotations),
            'records' => array_map(function($r) {
                return [
                    'feature_uniquename' => $r['feature_uniquename'] ?? 'UNKNOWN',
                    'annotation_source' => $r['annotation_source'] ?? 'MISSING',
                    'annotation_accession' => $r['annotation_accession'] ?? 'MISSING'
                ];
            }, $missing_annotations)
        ]
    );
    // Continue or stop based on severity
}
```

#### Example 4: Permission Error
```php
// Check file is readable
if (!is_readable($file_path)) {
    logError(
        'File not accessible',
        $organism_name,
        [
            'file_path' => $file_path,
            'validation_error' => 'File permission denied'
        ]
    );
    return;
}
```

### ❌ What NOT to Log

Don't log to error.log for:
- **Normal user actions** - Successful searches, valid uploads
- **Expected conditions** - Empty result sets, optional missing fields
- **Debug information only** - Use temporary `echo` or local debugging instead
- **Sensitive data** - Passwords, tokens, sensitive personal information

```php
// ❌ BAD - This is normal, not an error
logError('User performed search', $organism_name);

// ❌ BAD - This is expected
logError('No results found', $organism_name);

// ✅ GOOD - This is actually an error
logError('Search failed due to corrupted database', $organism_name);
```

### Error Log Viewer Features

**Location:** `/admin/error_log.php` (admin-only access)

**Features:**
- ✅ View last 500 errors
- ✅ Filter by error type (Database, Validation, Permission, etc.)
- ✅ Filter by organism (when applicable)
- ✅ Full-text search across all fields
- ✅ View detailed error information (user, IP, page, extra details)
- ✅ Clear log (creates timestamped backup)

**Logged Information:**
- `timestamp` - When the error occurred
- `error` - Error message
- `context` - What entity (organism, gene, etc.)
- `user` - Who was logged in
- `page` - Which page triggered error
- `ip` - Client IP address
- `details` - Additional debugging information (JSON formatted)

### Log Entry Structure

Each error is stored as JSON on a single line:
```json
{
  "timestamp": "2025-11-07 17:25:45",
  "error": "Database file not accessible",
  "context": "Montipora_capitata",
  "user": "testuser",
  "page": "/moop/admin/test",
  "ip": "192.168.1.100",
  "details": {
    "search_term": "hdac",
    "database_path": "/var/www/html/moop/organisms/Montipora_capitata/genes.sqlite",
    "validation_error": "Database file not readable (permission denied)"
  }
}
```

### Error Handling Pattern

When an error occurs:
1. **Log the error** - Call `logError()` with context and details
2. **Handle gracefully** - Don't crash, show user-friendly message
3. **Set HTTP status** - Use appropriate status codes (400, 403, 404, 500, etc.)
4. **Return clear response** - JSON for AJAX, HTML message for page

```php
<?php
include_once __DIR__ . '/access_control.php';
include_once __DIR__ . '/../tools/moop_functions.php';

try {
    // Attempt operation
    $db = new PDO('sqlite:' . $db_path);
    $result = $db->query("SELECT * FROM feature");
    
} catch (PDOException $e) {
    // Log the error
    logError(
        'Database query failed',
        $organism_name,
        [
            'query_type' => 'SELECT features',
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode()
        ]
    );
    
    // Return error to user
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
    exit;
}
?>
```

### Best Practices

1. ✅ **Always include context** - What entity/organism/file triggered the error?
2. ✅ **Include debugging details** - File paths, search terms, validation results
3. ✅ **Be descriptive** - "Database file not found" is better than "Error"
4. ✅ **Log early** - Log before you handle, so if handling fails you have the log
5. ✅ **Don't repeat yourself** - Check if error already logged in loop to avoid spam
6. ✅ **Clean up old logs** - Admin can clear logs via error_log.php UI
7. ✅ **Check logs regularly** - Part of maintenance routine

---

## 🛠️ JavaScript: User Feedback & Validation

**Use alerts and validation for user-facing feedback. Keep console clean.**

### When to Use Alert()
Use `alert()` for **important** user feedback only:
- User must take action or make a choice
- Error prevents operation from completing
- Selection required before proceeding

```javascript
// ✅ GOOD - User must know about this issue
if (selectedRows.length === 0) {
    alert('Please select at least one row to export.\n\nClick "Select All" to select all rows, or check individual row checkboxes.');
    return false;
}

// ✅ GOOD - Provide clear instruction
if (!featureIds || featureIds.length === 0) {
    alert('No valid Feature IDs found.');
    return false;
}
```

### When NOT to Use Alert()
Don't use `alert()` for:
- Debugging information (use `console.log()` instead)
- Normal program flow (use silent validation)
- Verbose logging (pollutes user experience)

```javascript
// ❌ BAD - This spams the user
alert('User clicked button');
alert('Processing started');
alert('Data loaded');

// ✅ GOOD - Log for debugging, silent for users
console.log('User clicked button');
console.log('Processing started');
console.log('Data loaded');
// User only sees alert if there's an actual problem
```

### Input Validation Pattern
Validate before attempting operations, show helpful messages if validation fails:

```javascript
// ✅ GOOD - Validate, then proceed
function exportData() {
    // Validate input
    if (!validateInput()) {
        alert('Validation failed: ' + getValidationError());
        return false;
    }
    
    // All checks passed - proceed silently
    performExport();
}
```

### Debugging with Console
Use `console.log()`, `console.error()`, `console.warn()` for developer debugging:

```javascript
// ✅ GOOD - Available in browser console for debugging
console.log('Feature IDs:', featureIds);
console.error('Database connection failed:', error);
console.warn('Deprecated API used - switch to new method');

// View with: Right-click → Inspect → Console tab
```

### Best Practices
- ✅ Alert for errors that block user action
- ✅ Console for debugging information
- ✅ Keep messages brief and actionable
- ✅ Always tell user what to do next
- ✅ Don't alert for normal operations
- ✅ Test user experience (no alert spam)

---

## 📁 File Organization Patterns

**Follow these patterns for consistency across the codebase.**

### PHP File Structure
```php
<?php
// 1. Includes at top (config, security, helpers)
include_once __DIR__ . '/access_control.php';
include_once __DIR__ . '/../site_config.php';
include_once __DIR__ . '/../tools/moop_functions.php';

// 2. Get parameters
$organism_name = $_GET['organism'] ?? '';
$uniquename = $_GET['uniquename'] ?? '';

// 3. Validate parameters
if (empty($organism_name) || empty($uniquename)) {
    die("Error: Missing required parameters.");
}

// 4. Check access control
if (!has_access('Collaborator', $organism_name)) {
    header("Location: /access_denied.php");
    exit;
}

// 5. Load data / Process logic
$data = fetchDataFromDatabase();

// 6. Load configuration
$config = loadConfig();

// 7. HTML output
?>
<!DOCTYPE html>
<!-- Display: Use configuration + data -->
```

### Database Query Pattern
```php
<?php
// Always use prepared statements
try {
    $db = new PDO('sqlite:' . $db_path);
    
    // Prepare query with placeholders
    $stmt = $db->prepare("
        SELECT * FROM feature 
        WHERE organism_id = ? 
        AND feature_type = ?
    ");
    
    // Execute with parameters (NEVER string concatenation)
    $stmt->execute([$organism_id, $feature_type]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    logError('Database query failed', $organism_name, [
        'error' => $e->getMessage()
    ]);
}
?>
```

### Directory Structure
```
/data/moop/
├── css/
│   └── moop.css              # Main stylesheet
├── js/
│   ├── datatable-config.js   # Button configuration
│   └── parent.js             # Page initialization
├── tools/
│   ├── display/              # Display pages
│   ├── search/               # Search functionality
│   └── extract/              # Export tools
├── admin/                    # Admin pages
├── groups/                   # Group functionality
└── organisms/                # Data directories
```

### Helper Function Location
- **Core utilities** → `/data/moop/tools/moop_functions.php`
- **Display-specific** → `/data/moop/tools/display/display_functions.php`
- **Search-specific** → `/data/moop/tools/search/search_functions.php`
- **Page-specific** → In the page file or `*_functions.php` pair

### CSS Organization
```css
/* moop.css structure */

/* 1. Global/Reset */
html, body { }

/* 2. Component sections */
.footer { }
.navbar { }
.organism-card { }

/* 3. Responsive design (at bottom) */
@media (max-width: 768px) { }
```

---

## 🔴 Critical Anti-Patterns to Avoid

**These mistakes appear frequently. Avoid them at all costs.**

### ❌ Don't Mix Concerns
Keep HTML, PHP logic, and SQL separate. Each layer should have one responsibility.

```php
// BAD - All concerns mixed together
<?php
    $result = $db->query("SELECT * FROM feature WHERE id=" . $_GET['id']);
    echo "<div style='color: red;'>" . $result['name'] . "</div>";
?>
```

**Better:**
```php
<?php
// 1. Get data
$data = getFeatureById($_GET['id']);
?>
<!-- 2. Display data -->
<div class="feature-name"><?= htmlspecialchars($data['name']) ?></div>
```

**Why:** Mixing concerns makes code:
- Hard to test (can't test logic without HTML)
- Hard to modify (change display = change logic)
- Hard to reuse (tied to one context)

### ❌ Don't Leave Dead Code
Remove commented-out code, unused functions, or old implementations. If you need history, use version control (git).

```php
// BAD - Dead code clutters the file
// function oldWayToFetchData() { ... }
// $result = fetchOldStyle();
// if (useNewApproach) { ... } else { ... oldCode... }
```

**Better:**
```php
// Remove it entirely. Git has the history if you need it.
$result = fetchNewStyle();
```

**Why:** Dead code:
- Confuses new developers
- Creates maintenance burden
- Clutters version control blame

### ❌ Don't Silently Fail
Always log errors, even if handling them gracefully. Silent failures hide bugs.

```php
// BAD - User never knows what went wrong
if (!file_exists($db_path)) {
    return null;  // Silently returns null, admin never sees this
}

// BAD - Function fails but no one is notified
if (!$result) {
    echo "Error occurred";
    return;
}
```

**Better:**
```php
// GOOD - Log so admin can debug and fix the issue
if (!file_exists($db_path)) {
    logError('Database file not found', $organism_name, [
        'path' => $db_path
    ]);
    return null;
}
```

**Why:** Silent failures:
- Hide data quality issues
- Prevent admins from knowing about problems
- Make debugging nearly impossible

---

## Already Covered (Don't Repeat These Mistakes)

These anti-patterns are already thoroughly covered in earlier sections. Reference them there:

- **Don't use global variables for access control** → See Security section
- **Don't hardcode business logic** → See Generic Data Handling section
- **Don't use inline styles** → See CSS & Styling section
- **Don't use raw SQL** → See File Organization patterns section

---

## 📚 Related Documentation

- **SYSTEM_GOALS.md** - Why these principles matter
- **SECURITY_IMPLEMENTATION.md** - Detailed security design
- **BUTTON_REQUIREMENTS.md** - Why library versions are pinned
- **REMAINING_TODOS.md** - Future improvement areas
