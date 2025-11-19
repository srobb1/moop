# Tool Pages Developer Guide

## Quick Start

Creating a new tool page is simple! Just add ONE line at the top:

```php
<?php
include_once __DIR__ . '/../tools/tool_init.php';
```

That's it! This single include handles everything:
- ✅ Session management
- ✅ Access control verification  
- ✅ Configuration loading
- ✅ Header image setup
- ✅ Navigation bar
- ✅ All common functions

## Example Tool Page

```php
<?php
include_once __DIR__ . '/../tools/tool_init.php';

// Load any page-specific config values you need
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');

// Your page logic here
$organisms = getAllOrganisms($organism_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>My Tool</title>
  <?php include_once __DIR__ . '/../includes/head.php'; ?>
</head>
<body class="bg-light">
  <?php include_once __DIR__ . '/../includes/navbar.php'; ?>
  
  <div class="container mt-5">
    <h2>My Tool</h2>
    <!-- Your HTML content here -->
  </div>
</body>
</html>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
```

## Available Variables After Including tool_init.php

### Config Object
```php
$config = ConfigManager::getInstance();

// String values
$header_img = $config->getString('header_img');
$images_path = $config->getString('images_path');
$site = $config->getString('site');
$siteTitle = $config->getString('siteTitle');

// Path values
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$absolute_images_path = $config->getPath('absolute_images_path');
$users_file = $config->getPath('users_file');

// Sequence types
$sequence_types = $config->getSequenceTypes();
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
- `access_control.php` - User authentication and access checking
- `navigation.php` - Navigation functions
- `moop_functions.php` - Helper functions like `loadJsonFile()`, etc.

## What tool_init.php Does

1. **Starts session** - If not already started
2. **Loads access control** - Initializes user authentication
3. **Loads configuration** - Initializes ConfigManager
4. **Loads common includes** - navigation.php, moop_functions.php
5. **Sets header variables** - Prepares header_img, images_path, site

## Important Notes

### Display Pages
Tool pages typically display organisms, assemblies, or groups. They should include head.php and navbar.php in the HTML:

```php
<?php include_once __DIR__ . '/../includes/head.php'; ?>
```

in the `<head>` section, and:

```php
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>
```

right after `<body>` opens.

### AJAX Endpoints
For AJAX endpoints, you typically don't include head.php or navbar.php - just use tool_init.php to get config and access control:

```php
<?php
include_once __DIR__ . '/../tools/tool_init.php';

header('Content-Type: application/json');

// Your AJAX logic here
echo json_encode($result);
?>
```

## Best Practices

1. **Always use `tool_init.php` first** - Put it as the very first include
2. **Load page-specific config after** - Then load any extra values your page needs
3. **Use ConfigManager** - Don't hardcode paths, use `$config->getPath()` or `$config->getString()`
4. **Use helper functions** - Functions like `loadJsonFile()` are available
5. **Include head.php and navbar.php in HTML** - Not at the top, but in the HTML sections where they belong

## Troubleshooting

### Header image not showing?
Make sure to include head.php in the `<head>` section and navbar.php right after `<body>` opens.

### "Access Denied" message?
User might not be logged in or their access level doesn't allow them to view the page. Check their role in `includes/users.json`.

### Functions not found?
Check that the function is in one of the pre-included files (moop_functions.php, etc.)

### Getting "undefined variable" errors?
Use `$config->getPath()` or `$config->getString()` to load values, not global variables.

## Migrating Old Tool Pages

Old tool pages might have multiple includes like:
```php
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/navigation.php';
include_once __DIR__ . '/../moop_functions.php';
$config = ConfigManager::getInstance();
$header_img = $config->getString('header_img');
// etc.
```

Replace all of that with just:
```php
include_once __DIR__ . '/../tools/tool_init.php';
```

Then add any page-specific config you need after that.
