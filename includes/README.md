# Includes Directory Documentation

The `includes/` directory contains shared PHP files that are included by pages throughout the application. These handle common functionality like configuration, access control, and UI components.

---

## Core Files

### 1. **config_init.php** - Configuration Bootstrap
Initializes the configuration system on every page that needs it.

**What it does:**
- Loads the ConfigManager class
- Creates a singleton instance
- Initializes configuration from config files
- Validates required keys

**How to use:**
```php
// Include once per page
include_once __DIR__ . '/includes/config_init.php';

// Then use ConfigManager anywhere
$config = ConfigManager::getInstance();
$site_title = $config->getString('siteTitle');
```

**See Also:** For complete ConfigManager documentation, refer to `config/README.md`

---

### 2. **ConfigManager.php** - Configuration Access Layer
The main class for centralized configuration management. This is a singleton that loads and caches all configuration.

**Key Features:**
- Single instance per page (singleton pattern)
- Type-safe getters: `getString()`, `getPath()`, `getArray()`, `getAll()`
- Merges defaults with user edits
- Validates required keys
- In-memory caching for performance

**Common Methods:**
```php
$config = ConfigManager::getInstance();

// Get values
$config->getString('siteTitle');           // String value
$config->getPath('organism_data');         // Path (filesystem safe)
$config->getArray('sequence_types');       // Array value
$config->getAll();                         // All config

// Tools
$config->getAllTools();                    // Tool registry
$config->getTool('blast');                 // Single tool

// Validation
$config->validate();                       // Returns true/false
$config->getMissingKeys();                 // List missing keys
```

**Important:** Do NOT modify this file directly. For configuration questions, refer to `config/README.md`. To extend validation, add to the `validate()` method in this file.

---

### 3. **access_control.php** - Authentication & Authorization
Manages user sessions, authentication, and permission checks. Separate from configuration.

**What it does:**
- Starts PHP session
- Handles IP-based auto-login (from config)
- Provides user authentication state
- Provides permission check functions
- Redirects unauthorized users

**Key Functions:**
```php
// After including access_control.php, these are available:

// Check if user is logged in
if (isset($_SESSION['username'])) {
    // User is logged in
}

// Check if user is admin
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    // User is admin
}

// Set permissions in SESSION (done by login handler)
$_SESSION['username'] = 'username';
$_SESSION['is_admin'] = true;
$_SESSION['groups'] = ['group1', 'group2'];
```

**When to include:**
- Any page that requires user authentication
- Any page that needs to check user permissions
- Admin pages

**When NOT to include:**
- Public pages that don't need authentication
- API endpoints that handle their own auth
- Static asset handlers

**Important Security Note:** This file does NOT make authorization decisions on its own. Each page is responsible for checking permissions. Access control is separated from configuration—they are completely independent systems.

---

### 4. **head.php** - HTML Head Content
Common `<head>` section content included by all pages.

**What it contains:**
- Meta tags (charset, viewport)
- Bootstrap CSS/JS links
- MOOP custom CSS
- Favicon link
- Common JavaScript libraries

**What it does NOT include:**
- `<!DOCTYPE>`, `<html>`, `<head>` tags (page provides these)
- Page-specific stylesheets or scripts

**How to use:**
```php
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Your Page Title</title>
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
</head>
<body>
    <!-- Your content -->
</body>
</html>
```

**Configuration-aware:**
- Loads site favicon from config
- Uses site path from config for CSS/JS URLs

---

### 5. **header.php** - HTML Header with Navigation
Full HTML header including navigation bar, site title, and banner.

**What it includes:**
- `<!DOCTYPE>`, `<html>`, `<head>` tags
- Common `<head>` content (via head.php)
- Session start and access control
- Navigation bar
- Site header with title and banner
- Body tag opening

**How to use:**
```php
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<!-- Your page content here -->

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
```

**Important:**
- This includes BOTH `<head>` and the opening `<body>` tag
- Use with `footer.php` to complete the page structure
- Automatically includes access control (checks authentication)

---

### 6. **footer.php** - HTML Footer and Page Closure
Closes the `</body>` and `</html>` tags. Usually included with `header.php`.

**What it contains:**
- Footer HTML
- Page analytics/tracking (if configured)
- `</body>` and `</html>` tags

**How to use:**
```php
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<!-- Your page content here -->

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
```

---

### 7. **navbar.php** - Navigation Bar Component
Standalone navigation bar that can be included separately.

**Usage:**
```php
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>
```

**When to use:**
- When you need just the navbar without full header
- Custom page layouts

---

### 8. **toolbar.php** - Admin Toolbar Component
Toolbar for admin pages with action buttons and status indicators.

**Usage:**
```php
<?php include_once __DIR__ . '/../includes/toolbar.php'; ?>
```

**When to use:**
- Admin pages that need consistent toolbar
- Pages with bulk actions or status displays

---

### 9. **banner.php** - Banner/Alert Component
Reusable banner component for alerts, notifications, and status messages.

**Usage:**
```php
<?php include_once __DIR__ . '/../includes/banner.php'; ?>
```

**Parameters:** Usually controlled by page-level variables before including

---

## Include Patterns

### Basic Page Structure
```php
<?php
// 1. Start configuration
include_once __DIR__ . '/includes/config_init.php';

// 2. Include header (handles auth, HTML setup)
include_once __DIR__ . '/includes/header.php';

// 3. Your page content

// 4. Include footer
include_once __DIR__ . '/includes/footer.php';
?>
```

### Admin Page Structure
```php
<?php
// 1. Output buffering for AJAX handling (see note below)
ob_start();

// 2. Include admin init (which includes config and access control)
include_once __DIR__ . '/admin/admin_init.php';

// 3. Handle AJAX requests at top of file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Handle AJAX
    exit;
}

// 4. Stop output buffering
ob_end_clean();

// 5. Include header
include_once __DIR__ . '/includes/header.php';

// 6. Page content

// 7. Include footer
include_once __DIR__ . '/includes/footer.php';
?>
```

---

## Important Concepts

### Output Buffering (ob_start, ob_get_clean, etc.)

**What it is:**
Output buffering captures all PHP output (echo, HTML, etc.) into memory instead of immediately sending to browser.

**Why some admin pages use it:**
Admin pages need to handle AJAX requests that return JSON data, not HTML. Output buffering allows pages to:
1. Capture any debug output or warnings
2. Process AJAX requests before sending any HTML
3. Return clean JSON responses without HTML interference

**Example:**
```php
<?php
ob_start();  // Start capturing output

include_once __DIR__ . '/admin/admin_init.php';

// Handle AJAX - exits before HTML is output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $result = handleAction($_POST['action']);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;  // Don't output HTML, just JSON
}

ob_end_clean();  // Clear captured output, start fresh

// Now output HTML normally
include_once __DIR__ . '/includes/header.php';
// ... HTML content
include_once __DIR__ . '/includes/footer.php';
?>
```

**Without output buffering:**
- If any warnings/notices occur, they'd be output to browser
- AJAX responses would be mixed with debug messages
- Responses wouldn't be valid JSON

**With output buffering:**
- Debug output is captured but discarded
- AJAX requests return pure JSON
- HTML pages render cleanly

**Used in:**
- `admin/manage_organisms.php`
- `admin/manage_groups.php`
- `admin/manage_registry.php`
- `admin/filesystem_permissions.php`
- Other admin pages that handle AJAX

---

## Load Order & Dependencies

The typical include order is important:

1. **config_init.php** - Must be first (loads ConfigManager)
2. **access_control.php** - After config (needs ConfigManager)
3. **header.php** - After auth setup (may check permissions)
4. **Page-specific content**
5. **footer.php** - Last (closes HTML)

**DO NOT:**
- Include files out of order
- Include config_init.php after header.php
- Include access_control.php before config_init.php
- Mix include methods (one page uses different order)

**Why order matters:**
- ConfigManager must be loaded before anything tries to use it
- Access control depends on ConfigManager
- Header needs to know if user is authenticated

---

## Common Mistakes

### ❌ Wrong: Including in wrong order
```php
<?php
include_once __DIR__ . '/includes/header.php';  // Too early!
include_once __DIR__ . '/includes/config_init.php';  // Too late!
?>
```

### ✅ Right: Correct order
```php
<?php
include_once __DIR__ . '/includes/config_init.php';
include_once __DIR__ . '/includes/header.php';
?>
```

### ❌ Wrong: Calling ConfigManager before including config_init.php
```php
<?php
$config = ConfigManager::getInstance();  // ERROR: Class doesn't exist
include_once __DIR__ . '/includes/config_init.php';
?>
```

### ✅ Right: Include first, then use
```php
<?php
include_once __DIR__ . '/includes/config_init.php';
$config = ConfigManager::getInstance();  // Works!
?>
```

### ❌ Wrong: Forgetting ob_start on AJAX page
```php
<?php
// Errors/warnings output to browser as plain text
echo "ERROR: Connection failed";  
// Then AJAX tries to parse as JSON - fails
echo json_encode(['success' => true]);
?>
```

### ✅ Right: Using output buffering
```php
<?php
ob_start();  // Capture everything
// Warnings are captured but discarded
echo json_encode(['success' => true]);  // Only this is output
?>
```

---

## Configuration Reference

For complete information about configuration and ConfigManager, see **`config/README.md`**

This document covers includes. Configuration documentation is separate because:
- Configuration is loaded via ConfigManager (in this directory)
- Configuration files are in config/ directory
- Documentation belongs with the files it describes

---

## Troubleshooting

### "Call to undefined function" or "Class not found"
- Check that required includes are in place
- Verify include order is correct
- Check file paths are correct

### "Headers already sent" error
- This means HTML was output before you tried to set headers
- Usually happens with AJAX pages not using output buffering
- Add `ob_start()` at the top of the page

### "Configuration not loading"
- Verify config_init.php is included
- Check that config files exist in config/ directory
- See config/README.md for configuration troubleshooting

### Page looks broken (missing CSS/styling)
- head.php wasn't included
- Check that header.php or head.php is included
- Verify CSS paths in head.php are correct

---

## Adding New Includes

When creating a new include file:

1. **Add clear documentation header**
   ```php
   <?php
   /**
    * File Name - Brief description
    * 
    * What this file does
    * What it requires
    * How to use it
    */
   ```

2. **Check dependencies**
   - Does it need ConfigManager? Require config_init.php
   - Does it need auth? Require access_control.php

3. **Follow naming conventions**
   - Components: `component_name.php` (e.g., `toolbar.php`)
   - System files: `system_function.php` (e.g., `config_init.php`)

4. **Document in this README**
   - Add section describing the file
   - Explain how to use it
   - Note any dependencies

---

## File Reference

```
includes/
├── ConfigManager.php          # Configuration singleton class
├── config_init.php            # Bootstrap ConfigManager
├── access_control.php         # Authentication & authorization
├── head.php                   # Common <head> content
├── header.php                 # Full <head> + header + nav
├── footer.php                 # Footer + page closure
├── navbar.php                 # Standalone navbar
├── toolbar.php                # Admin toolbar
├── banner.php                 # Banner/alert component
├── README.md                  # This file
└── navigation.php.backup      # Backup (not used)
```

---

## Next Steps

1. **Using Configuration:** Refer to `config/README.md` for ConfigManager usage
2. **Checking Permissions:** Use functions from `access_control.php`
3. **Building Pages:** Use `header.php` + content + `footer.php`
4. **Admin Pages:** Use `ob_start()` + AJAX handling + `ob_end_clean()`
5. **Adding Includes:** Follow the pattern above and document here
