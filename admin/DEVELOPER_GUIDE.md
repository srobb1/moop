# Admin Pages Developer Guide

## Quick Start

Creating a new admin page is simple! Just add ONE line at the top:

```php
<?php
include_once __DIR__ . '/admin_init.php';
```

That's it! This single include handles everything:
- ✅ Session management
- ✅ Admin access verification  
- ✅ Configuration loading
- ✅ Header image display
- ✅ Navigation bar
- ✅ All common functions

## Example Admin Page

```php
<?php
include_once __DIR__ . '/admin_init.php';

// Load any page-specific config values you need
$metadata_path = $config->getPath('metadata_path');
$organisms_file = "$metadata_path/organisms.json";

// Your page logic here
$data = loadJsonFile($organisms_file, []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>My Admin Page</title>
</head>
<body class="bg-light">
  <!-- navbar already included above -->
  
  <div class="container mt-5">
    <h2>My Admin Page</h2>
    <!-- Your HTML content here -->
  </div>
</body>
</html>
<?php include_once '../includes/footer.php'; ?>
```

## Available Variables After Including admin_init.php

### Config Object
```php
$config = ConfigManager::getInstance();

// String values
$header_img = $config->getString('header_img');
$images_path = $config->getString('images_path');
$site = $config->getString('site');
$siteTitle = $config->getString('siteTitle');

// Path values
$metadata_path = $config->getPath('metadata_path');
$organism_data = $config->getPath('organism_data');
$users_file = $config->getPath('users_file');
$absolute_images_path = $config->getPath('absolute_images_path');
```

### Pre-loaded Variables
```php
// These are automatically set and ready to use
$header_img       // Path to header image (for navbar)
$images_path      // Path to images directory
$site             // Site name/identifier
```

### Pre-included Files
The following are already included and functions available:
- `navigation.php` - Navigation functions
- `moop_functions.php` - Helper functions like `loadJsonFile()`, `getFileWriteError()`, etc.
- `head.php` - CSS and JS includes
- `navbar.php` - Header and navigation bar

## What admin_init.php Does

1. **Starts session** - If not already started
2. **Loads config system** - Initializes ConfigManager
3. **Checks admin access** - Verifies user is logged in as admin (uses admin_access_check.php)
4. **Loads configuration** - Gets all config values from ConfigManager
5. **Loads common includes** - Includes all necessary files for admin pages
6. **Sets header variables** - Prepares header_img, images_path, site for navbar

## Error Handling

If something goes wrong, errors will be logged to `logs/error.log`. Common issues:

### "Access Denied" message
- User is not logged in
- User is not an admin
- User doesn't have Admin access level

Check `includes/users.json` to verify user has admin role:
```json
{
  "username": {
    "role": "admin",
    "access": {},
    ...
  }
}
```

### Missing configuration values
Check `config/config.json` to ensure all required paths are set.

## Best Practices

1. **Always use `admin_init.php` first** - Put it as the very first include
2. **Load page-specific config after** - Then load any extra values your page needs
3. **Use ConfigManager** - Don't hardcode paths, use `$config->getPath()` or `$config->getString()`
4. **Use helper functions** - Functions like `loadJsonFile()`, `getFileWriteError()` are already available
5. **Don't duplicate includes** - Never include head.php, navbar.php, etc. yourself - they're in admin_init.php

## Troubleshooting

### Header image not showing?
Make sure `admin_init.php` is included at the very top, before any HTML output.

### Functions not found?
Check that the function is in one of the pre-included files (navigation.php, moop_functions.php, etc.)

### Getting "undefined variable" errors?
Use `$config->getPath()` or `$config->getString()` to load values, not global variables.
