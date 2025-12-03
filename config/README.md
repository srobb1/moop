# Configuration System Documentation

## Overview

The MOOP configuration system provides centralized, type-safe access to all application settings. It's designed to separate **default structural settings** (paths, database locations) from **editable user settings** (site title, admin email), making the system both flexible and secure.

### Key Principles

1. **Single Source of Truth**: All configuration flows through `ConfigManager`
2. **Defaults + Overrides**: `site_config.php` provides defaults, `config_editable.json` provides overrides
3. **Type Safety**: Different getters for different data types (`getString()`, `getPath()`, `getArray()`)
4. **Lazy Loading**: Configuration is loaded once and cached in memory
5. **Easy Testing**: Singleton pattern allows easy mocking in tests

---

## Files in the Config Directory

### 1. **site_config.php** - Default Configuration
The structural backbone of the system. Contains all default settings that may be overridden.

**What it defines:**
- File paths (root_path, organism_data, metadata_path, etc.)
- Default site title and email
- Sequence types with patterns and display labels
- IP ranges for auto-login
- Tool registry paths
- All other application settings

**Key characteristics:**
- **Read-only via admin interface** (for safety)
- PHP file that returns a configuration array
- Used as base defaults that are merged with editable config
- Edit via SSH/direct file access for structural changes (paths)
- Edit via Admin Dashboard for appearance settings (title, email, colors)

**When to edit:**
- Moving installation to different server path
- Changing root directory location
- Adding new tools or custom settings
- Deployment-specific configurations

**Example:**
```php
$root_path = '/var/www/html';  // Change for different server
$site = 'moop';                 // Change for multi-site deployments

return [
    'root_path' => $root_path,
    'siteTitle' => 'SIMRbase',  // Default, can be overridden
    'admin_email' => 'admin@example.com',
    // ... more settings
];
```

### 2. **config_editable.json** - User-Editable Configuration
Runtime configuration that can be edited through the Admin Dashboard. Contains only settings that users should be able to change.

**What it stores:**
- Site title
- Admin email
- Header image filename
- Favicon filename
- Sequence types (display labels and colors)
- Auto-login IP ranges

**Key characteristics:**
- **Created automatically** on first edit
- **Backed up** when changes are made
- **Change-logged** for audit trail
- Contains `_metadata` section with last updated timestamp
- If missing, all values come from `site_config.php` defaults

**When it's created:**
- First time an admin saves changes via "Manage Site Configuration"
- Contains all editable settings at that moment

**Example structure:**
```json
{
  "siteTitle": "My Custom Site Title",
  "admin_email": "newemail@example.com",
  "header_img": "banner.png",
  "favicon_filename": "favicon.ico",
  "sequence_types": {
    "protein": {
      "label": "Protein Sequences",
      "color": "bg-info"
    }
  },
  "_metadata": {
    "last_updated": "2024-12-03 12:34:56",
    "last_updated_by": "admin"
  }
}
```

### 3. **config_schema.php** - Configuration Documentation Reference
**Important Note:** Despite its name, this is NOT a formal validation schema. It's a **textual reference guide** for admins and developers about the configuration system.

**What it actually contains:**
- Quick-start guide for admins
- List of required vs optional config keys
- Explanations of what each path means
- How to add new tools
- How to add new configuration values
- Multi-site deployment instructions
- Troubleshooting tips
- Security notes

**What it does NOT do:**
- It does NOT enforce validation rules (ConfigManager has hardcoded `requiredKeys` instead)
- It does NOT validate data types
- It does NOT perform runtime validation

**Actual validation happens in:**
- `ConfigManager.php` has hardcoded `requiredKeys` array (lines 53-61)
- `ConfigManager->validate()` method (line 372) only checks if required keys exist
- That's it - no data type validation, no complex rules

**Example of what's in config_schema.php:**
```php
'required_config_keys' => [
    'root_path' => 'Server root directory (usually /var/www/html)',
    'site' => 'Site subdirectory name (e.g., moop, simrbase)',
    'siteTitle' => 'Display name of your site',
    'admin_email' => 'Contact email for admins',
],

'troubleshooting' => [
    'config_not_loading' => 'Check if config/site_config.php exists and is readable',
    'site_won\'t_load' => 'Check required paths in site_config.php exist on server',
],
```

**Used by:**
- Admins as a reference guide when configuring the system
- Developers when adding new settings or debugging
- Not automatically processed by code

### 4. **tools_config.php** - External Tools Configuration
Configuration for external tools and services that MOOP integrates with (BLAST, aligners, etc.).

**What it defines:**
- Available tools and their parameters
- Tool binary paths
- Tool-specific options and defaults
- Integration points in the application

**Key characteristics:**
- Separate from site configuration for modularity
- Tools can be enabled/disabled
- Parameters can be customized per installation
- Optional - app works without additional tools

**Example:**
```php
'blast' => [
    'enabled' => true,
    'binary_path' => '/usr/bin/blastp',
    'database_path' => '/path/to/databases',
    'parameters' => [...]
],
```

### 5. **config_init.php** - Initialization Bootstrap
The entry point for loading configuration in any page. Should be included once per page load.

**What ConfigManager is:**
- A singleton class (one instance shared across entire page)
- Centralizes all configuration access
- Loads and merges configuration from multiple files
- Caches configuration in memory for performance
- Provides type-safe getters for different value types

**What config_init.php does:**
1. Requires the ConfigManager class
2. Creates a singleton instance of ConfigManager
3. Initializes it with `site_config.php` and `tools_config.php`
4. Validates that all required keys exist
5. Logs any missing or invalid keys to error log

**How to use:**
```php
// Include once per page
include_once __DIR__ . '/includes/config_init.php';

// Get the singleton instance (same instance throughout page)
$config = ConfigManager::getInstance();

// Use it to access configuration
$siteTitle = $config->getString('siteTitle');
$organism_path = $config->getPath('organism_data');
```

**Note:** Once initialized, you can call `ConfigManager::getInstance()` anywhere in your code without re-including config_init.php. The singleton pattern ensures it's the same instance.

### 6. **build_and_load_db** - Database Build Script
Utility script (likely a shell script) for building and loading the SQLite databases from FASTA files.

---

## How ConfigManager Works (Step by Step)

### 1. Initialization - What Happens on Page Load

```
Page Load
    ↓
include config_init.php
    ↓
ConfigManager::getInstance() 
    └─→ Creates singleton (one instance for entire page)
    ↓
ConfigManager->initialize(site_config.php, tools_config.php)
    ├─→ Load site_config.php (default values)
    ├─→ Load config_editable.json (if exists, user overrides)
    ├─→ Load tools_config.php (tool registry)
    ├─→ Merge all three (editable overrides site_config)
    ├─→ Validate (check required keys exist)
    └─→ Cache in memory (ready to use)
    ↓
Configuration is now ready to use throughout the page
```

### 2. Merging Configuration

When a config value is requested, ConfigManager searches in this order:

```
Request: $config->getString('siteTitle')
    ↓
Check config_editable.json first (has it been edited by admin?)
    ├─→ YES: Return edited value
    ├─→ NO: Continue to next
    ↓
Check site_config.php (what's the default?)
    ├─→ YES: Return default value
    ├─→ NO: Continue to next
    ↓
Return NULL (no value found)
```

**Real Example:**
```
Admin edits siteTitle to "My Custom Title"
    ↓
ConfigManager saves to config_editable.json
    ↓
Next page load:
    - config_editable.json has: "My Custom Title"
    - site_config.php has: "SIMRbase" (original)
    ↓
Request $config->getString('siteTitle')
    ↓
Finds "My Custom Title" in config_editable.json (highest priority)
    ↓
Returns "My Custom Title"
```

### 3. The Singleton Pattern

ConfigManager uses the singleton pattern - **one shared instance**:

```php
// First call - creates the instance
$config = ConfigManager::getInstance();

// Second call - returns the SAME instance (doesn't reload)
$config = ConfigManager::getInstance();  // Same object as above

// Throughout the entire page - all code uses the same instance
// No reloading, no re-merging, just cached memory access
```

This means:
- ✅ Configuration is loaded only once per page load
- ✅ Very fast (just returns cached data)
- ✅ Memory efficient (one instance for entire page)
- ✅ Consistent (all code sees same values)

---

## How the Configuration System Works

### Loading Process

```
Page Load
    ↓
include config_init.php
    ↓
ConfigManager::getInstance()->initialize()
    ↓
Load site_config.php (defaults)
    ↓
Load config_editable.json (overrides) if exists
    ↓
Merge: defaults + overrides
    ↓
Validate against schema
    ↓
Cache in memory (singleton)
    ↓
Ready to use throughout page
```

### Configuration Lookup Priority

When requesting a config value, ConfigManager looks in this order:

1. **config_editable.json** (user overrides) - highest priority
2. **site_config.php** (defaults) - fallback
3. **Hardcoded defaults in ConfigManager** - final fallback (requiredKeys only)

Example with `siteTitle`:
- If admin edited it: use value from `config_editable.json`
- Otherwise: use value from `site_config.php`
- Otherwise: return null (no hardcoded fallback for siteTitle)

### Validation System

ConfigManager has a **basic validation** system that only checks if required keys exist. Here's exactly what happens:

**1. Required Keys (hardcoded in ConfigManager)**
```php
// These 7 keys MUST exist in configuration
private $requiredKeys = [
    'root_path',      // Base server directory
    'site',           // Site subdirectory name
    'site_path',      // Full path (root_path + site)
    'organism_data',  // Organism data directory
    'metadata_path',  // Metadata configuration directory
    'siteTitle',      // Site display name
    'admin_email',    // Admin contact email
];
```

**2. What validate() Does**
```php
public function validate() {
    $this->errors = [];
    
    // Just checks if each required key exists
    foreach ($this->requiredKeys as $key) {
        if (!isset($this->config[$key])) {
            $this->errors[] = "Missing required config: $key";
        }
    }
    
    return empty($this->errors);  // Returns true if no errors
}
```

**3. Validation Checklist**
- ✅ Checks if all 7 required keys exist
- ✅ Returns list of missing keys via `getMissingKeys()`
- ❌ Does NOT validate data types (string vs array)
- ❌ Does NOT validate path values (doesn't check if paths exist)
- ❌ Does NOT check if paths are readable by web server
- ❌ Does NOT apply complex rules or constraints

**4. When Validation Runs**
- Automatically on page load (in config_init.php)
- Can be disabled with `VALIDATE_CONFIG=false` environment variable
- Errors are logged but don't prevent app from running

**Important Note:** To add more validation (e.g., checking if paths are readable), you would need to add code to ConfigManager's `validate()` method.

### Editing Configuration

**Via Admin Dashboard (Recommended):**
```
Admin Portal → Manage Site Configuration
    ↓
Edit form fields
    ↓
Submit → ConfigManager saves to config_editable.json
    ↓
Auto-backup created
    ↓
Change logged
```

**Via Direct File Edit (SSH):**
```
Connect via SSH
    ↓
Edit config/site_config.php (structural) or
Edit config/config_editable.json (user settings)
    ↓
Save file
    ↓
On next page load, ConfigManager reloads
```

---

## Using Configuration in Your Code

### Basic Usage

```php
// Include config (do this once per page/controller)
include_once __DIR__ . '/../includes/config_init.php';

// Get ConfigManager singleton
$config = ConfigManager::getInstance();

// Access configuration values
$site_title = $config->getString('siteTitle');
$organism_path = $config->getPath('organism_data');
$ip_ranges = $config->getArray('auto_login_ip_ranges');
$tools = $config->getAllTools();
```

### Type-Specific Getters

```php
// Get string values
$email = $config->getString('admin_email');  // Returns 'admin@example.com'

// Get path values (filesystem or web paths)
$site_path = $config->getPath('site_path');      // '/var/www/html/moop'
$images_web = $config->getPath('images_path');   // 'moop/images' (for <img src>)
$images_fs = $config->getPath('absolute_images_path');  // '/var/www/html/moop/images'

// Get array values
$ranges = $config->getArray('auto_login_ip_ranges');  // Array of IP ranges
$types = $config->getArray('sequence_types');        // Sequence type configs

// Get all configuration
$all = $config->getAll();  // Full config array

// Get all tools
$tools = $config->getAllTools();  // Tool registry
```

### In Admin Pages

```php
// In any admin page (e.g., manage_site_config.php)
include_once __DIR__ . '/admin_init.php';  // This includes config_init.php

// Config is already loaded, just use it
$config = ConfigManager::getInstance();
$current_title = $config->getString('siteTitle');

// Display to user
echo "Current title: " . htmlspecialchars($current_title);
```

### In Regular Pages

```php
// In any regular page (e.g., index.php, organism display)
include_once __DIR__ . '/includes/config_init.php';

$config = ConfigManager::getInstance();
$site_title = $config->getString('siteTitle');

// Use in HTML
echo "<title>" . htmlspecialchars($site_title) . "</title>";
```

### In API Endpoints

```php
// In API script
header('Content-Type: application/json');
include_once __DIR__ . '/includes/config_init.php';

$config = ConfigManager::getInstance();

// Return config data
echo json_encode([
    'site_title' => $config->getString('siteTitle'),
    'organism_data_path' => $config->getPath('organism_data'),
]);
```

---

## Configuration Variables Reference

### Paths (Use getPath())
```php
'root_path'              => '/var/www/html'
'site'                   => 'moop'
'site_path'              => '/var/www/html/moop'
'organism_data'          => '/var/www/html/moop/organisms'
'metadata_path'          => '/var/www/html/moop/metadata'
'absolute_images_path'   => '/var/www/html/moop/images'
'images_path'            => 'moop/images'        // For <img src>
'banners_path'           => '/var/www/html/moop/images/banners'
'docs_path'              => '/var/www/html/moop/docs'
'custom_css_path'        => '/var/www/html/moop/css/custom.css'
'users_file'             => '/var/www/html/users.json'
```

### Strings (Use getString())
```php
'siteTitle'              => 'SIMRbase'           // Editable
'admin_email'            => 'admin@example.com'  // Editable
'header_img'             => 'header_img.png'     // Editable
'favicon_filename'       => 'favicon.ico'        // Editable
'images_dir'             => 'images'
```

### Arrays (Use getArray())
```php
'sequence_types' => [                   // Editable
    'protein' => [
        'pattern' => 'protein.aa.fa',
        'label'   => 'Protein',
        'color'   => 'bg-info'
    ],
    // ...
]

'auto_login_ip_ranges' => [             // Editable
    [
        'start' => '127.0.0.1',
        'end'   => '127.0.0.1'
    ]
]
```

---

## Common Patterns and Examples

### Example 1: Display Site Title
```php
$config = ConfigManager::getInstance();
$title = $config->getString('siteTitle');
echo "<h1>" . htmlspecialchars($title) . "</h1>";
```

### Example 2: Get All Organism Data
```php
$config = ConfigManager::getInstance();
$org_path = $config->getPath('organism_data');

$organisms = scandir($org_path);
foreach ($organisms as $org) {
    if ($org[0] !== '.') {
        echo "Found organism: $org\n";
    }
}
```

### Example 3: Check IP-Based Auto-Login
```php
$config = ConfigManager::getInstance();
$ranges = $config->getArray('auto_login_ip_ranges');
$user_ip = $_SERVER['REMOTE_ADDR'];

foreach ($ranges as $range) {
    if (ip2long($user_ip) >= ip2long($range['start']) &&
        ip2long($user_ip) <= ip2long($range['end'])) {
        // User is in auto-login range
        return true;
    }
}
```

### Example 4: Get Image Path for HTML
```php
$config = ConfigManager::getInstance();

// For <img src> - use web path
$header_img = $config->getPath('images_path') . '/banners/header.png';
echo "<img src='/$header_img' alt='Header'>";

// For file operations - use absolute path
$img_file = $config->getPath('absolute_images_path') . '/banners/header.png';
if (file_exists($img_file)) {
    // Process image
}
```

### Example 5: Display Sequence Type Labels
```php
$config = ConfigManager::getInstance();
$seq_types = $config->getArray('sequence_types');

foreach ($seq_types as $type => $config) {
    echo "<span class='badge {$config['color']}'>";
    echo htmlspecialchars($config['label']);
    echo "</span>";
}
```

---

## Security Considerations

### Configuration Data is Public
Configuration contains **no secrets** (passwords, API keys, tokens). It's safe to log or display for debugging.

### Access Control Remains Separate
ConfigManager only loads configuration data. It does NOT:
- Manage user sessions (`$_SESSION`)
- Perform authentication (login)
- Check user permissions
- Validate user input

These remain in:
- `includes/access_control.php` - Authentication/authorization
- Individual page permission checks

### Editable Settings are Admin-Only
The admin interface (`manage_site_config.php`) is protected by the same access control system. Only admins can edit `config_editable.json`.

### Paths are Validated
ConfigManager validates that all configured paths:
- Are absolute paths (not relative)
- Exist on the filesystem
- Are readable by the web server
- Have correct permissions (via separate Filesystem Permissions admin page)

---

## Troubleshooting

### Missing Configuration Keys
**Error**: "Configuration validation errors: missing keys..."

**Solution**:
1. Check that `config/site_config.php` exists and is readable
2. Check `config_schema.php` for required keys
3. Run validation from `admin/filesystem_permissions.php`
4. Review error logs for specific missing keys

### Configuration Not Updating
**Problem**: Changes to `site_config.php` don't take effect

**Solution**:
1. ConfigManager caches in memory - restart the app/reload page
2. Check that `config_editable.json` doesn't override your setting
3. Verify file permissions (should be 664)
4. Clear browser cache (sometimes cached HTML)

### editable.json Not Found
**Problem**: Admin can't save configuration changes

**Solution**:
1. Check directory permissions: `/var/www/html/moop/config/` should be 2775
2. Check web server ownership: should be owned by `ubuntu:www-data`
3. Use Admin → Filesystem Permissions to fix
4. Try saving from admin interface again

### Paths Returning NULL
**Problem**: `$config->getPath('some_path')` returns null

**Solution**:
1. Check that the key is spelled correctly
2. Verify it's defined in `site_config.php`
3. Use `$config->getAll()` to see all available keys
4. Check for typos in your code

---

## Migration and Maintenance

### Backing Up Configuration
```bash
# Backup both files
cp config/site_config.php config/site_config.php.backup
cp config/config_editable.json config/config_editable.json.backup

# Or use admin interface auto-backups in metadata/backups/
```

### Updating site_config.php
When updating the application:
1. New default settings are added to `site_config.php`
2. `config_editable.json` overrides are preserved
3. On first access, ConfigManager merges new + old settings
4. Migration runs if needed (e.g., new keys)

### Adding New Configuration
1. Add to `site_config.php` with sensible default
2. Update `config_schema.php` with validation rules
3. Use `$config->getString()` or `getPath()` in code
4. Optional: Add to admin interface for editing

---

## ConfigManager API Reference

```php
// Get singleton instance
$config = ConfigManager::getInstance();

// Get values (type-safe)
$config->getString('key')          // String value
$config->getPath('key')            // Path value (filesystem safe)
$config->getArray('key')           // Array value
$config->getAll()                  // All config as array

// Get tool registry
$config->getAllTools()             // All tools
$config->getTool('tool_name')      // Single tool

// Validation
$config->validate()                // Validate config structure
$config->getMissingKeys()          // List missing keys
$config->getErrors()               // List validation errors

// Initialization (usually only called by config_init.php)
$config->initialize($site_config_file, $tools_config_file)
$config->saveEditableConfig($data, $config_dir)  // Save to editable.json
$config->getEditableConfigMetadata()             // Get edit history
```

---

## File Layout Reference

```
config/
├── site_config.php          # Default configuration (edit for structural changes)
├── config_editable.json     # User editable settings (created by admin interface)
├── config_schema.php        # Validation schema
├── tools_config.php         # External tools configuration
├── config_init.php          # Boot configuration in pages
├── README.md                # This file
└── (backups/change_log/)    # Auto-created by admin interface

includes/
├── ConfigManager.php        # Main config class
└── config_init.php          # Configuration initialization (symlink or copy)
```

---

## Next Steps

1. **Using Configuration**: Import `config_init.php` in your page
2. **Adding Settings**: Edit `site_config.php` and `config_schema.php`
3. **Admin Panel**: Use "Manage Site Configuration" to edit user settings
4. **Admin Panel**: Use "Filesystem Permissions" to validate paths
5. **Debugging**: Use `$config->getAll()` to see all available settings
