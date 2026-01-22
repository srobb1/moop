# Includes Directory Reference

The `/includes/` directory contains shared components that work together to provide page structure, configuration management, and access control.

---

## File Overview

### Core Configuration & Access Control

#### ConfigManager.php
**Purpose:** Singleton class for centralized configuration management

**What it does:**
- Loads site_config.php (static defaults)
- Loads config_editable.json (runtime overrides)
- Merges both configurations
- Provides methods to retrieve config values: `getPath()`, `getString()`, `getArray()`

**Usage:**
```php
$config = ConfigManager::getInstance();
$site_path = $config->getPath('site_path');
$title = $config->getString('siteTitle');
```

**Important:** Never instantiate directly - always use `getInstance()`

---

#### config_init.php
**Purpose:** Bootstrap file to initialize configuration

**What it does:**
- Requires ConfigManager.php
- Creates ConfigManager singleton instance
- Loads both configuration files (static + editable)
- Makes $config available to page

**Usage:**
```php
<?php
include_once __DIR__ . '/config_init.php';
// ConfigManager now initialized and ready to use
$config = ConfigManager::getInstance();
?>
```

**Important:** Include this ONCE per page load, usually early

---

#### access_control.php
**Purpose:** User authentication and permission validation

**What it does:**
- Checks $_SESSION for logged-in user
- Validates user permissions
- Checks IP-based access
- Determines access level (ALL, Admin, Collaborator, Visitor)
- Sets $_SESSION variables

**Usage:**
```php
<?php
include_once __DIR__ . '/config_init.php';
include_once __DIR__ . '/access_control.php';
// User access is now validated
?>
```

**Security Design:** 
- Separate from configuration (ConfigManager doesn't touch $_SESSION)
- Checks happen before page renders
- Invalid access results in early exit

---

### Page Structure & Layout

#### layout.php
**Purpose:** Core rendering function for page structure

**Main Function:** `render_display_page($content_file, $data, $title)`

**What it does:**
- Orchestrates page rendering
- Calls page-setup.php to open HTML
- Includes content file in the middle
- Calls footer.php to close HTML
- Passes $data to content file

**Usage:**
```php
<?php
include_once __DIR__ . '/config_init.php';
include_once __DIR__ . '/access_control.php';

$title = "Page Title";
$data = ['organism' => 'Example', 'count' => 42];
$content_file = __DIR__ . '/../tools/pages/organism.php';

render_display_page($content_file, $data, $title);
?>
```

---

#### page-setup.php
**Purpose:** Opens the HTML page structure

**What it outputs:**
- `<!DOCTYPE html>`
- `<html lang="en">` opening tag
- `<head>` section with meta tags
- Includes head-resources.php for CSS/JS
- Opens `<body>` tag
- Includes navbar.php (header with banner & toolbar)

**Important:** Must be paired with footer.php

**Called by:** layout.php (not usually called directly)

---

#### footer.php
**Purpose:** Closes the HTML page structure

**What it outputs:**
- Footer HTML content
- Closes `</body>` tag
- Closes `</html>` tag

**Paired with:** page-setup.php

**Called by:** layout.php (not usually called directly)

---

### Page Header Components

#### head-resources.php
**Purpose:** HTML head content (CSS, meta tags, fonts)

**What it contains:**
- Meta charset and viewport
- Bootstrap CSS
- MOOP custom CSS (moop.css)
- Favicon link
- Font links (Roboto, etc.)
- Other head resources

**Included by:** page-setup.php

**Configuration-aware:**
- Uses config for favicon
- Uses config for CSS paths

---

#### navbar.php
**Purpose:** Main page header (banner + toolbar)

**What it includes:**
- Calls banner.php (rotating header images)
- Calls toolbar.php (navigation toolbar)
- Provides visual header area

**Included by:** page-setup.php (automatically)

---

#### banner.php
**Purpose:** Rotating banner images at page top

**What it does:**
- Scans /images/banners/ for images
- Rotates between banners on each page load
- Uses blurred background + sharp foreground
- Falls back to config header_img if no banners found

**Configuration:** 
- Gets banner path from ConfigManager
- Uses header_img as fallback

**Included by:** navbar.php (automatically)

---

#### toolbar.php
**Purpose:** Tool toolbar showing context and navigation

**What it displays:**
- Current location/context
- Tool information
- Navigation help
- User session info

**Configuration-aware:**
- Gets tool info from config

**Included by:** navbar.php (automatically)

---

### Source Selection Components

#### source-selector-helpers.php
**Purpose:** Centralized logic for organism/assembly selection

**Main Function:** `prepareSourceSelection($context, $sources_by_group, ...)`

**What it does:**
- Parses context (organism, assembly, group)
- Builds filtered organism list
- Determines auto-selection
- Returns selection state

**Used by:** retrieve_sequences.php, blast.php

---

#### source-list.php
**Purpose:** HTML component for source selection UI

**What it renders:**
- Organism/Assembly list
- Radio buttons for selection
- Filter/search box
- Color-coded by group

**Required Variables:**
- $sources_by_group
- $selected_source
- $filter_organisms

**Used by:** Tools that need FASTA source selection

---

## Include Patterns

### Basic Page Structure (via layout.php)
```php
<?php
// Controller page (e.g., tools/organism.php)
include_once __DIR__ . '/../includes/config_init.php';
include_once __DIR__ . '/../includes/access_control.php';

// Prepare data
$title = "Organism Browser";
$data = ['organism' => 'Example'];

// Render page
render_display_page(
    __DIR__ . '/pages/organism.php',
    $data,
    $title
);
?>
```

### Admin Page Structure (with output buffering)
```php
<?php
// Start output buffering for AJAX
ob_start();

// Initialize admin environment
include_once __DIR__ . '/admin_init.php';  // includes config_init.php

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $result = handleAdminAction($_POST['action']);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Clear buffer and render HTML
ob_end_clean();
include_once __DIR__ . '/layout.php';
render_display_page(
    __DIR__ . '/pages/manage_organisms.php',
    $data,
    'Manage Organisms'
);
?>
```

---

## Include Dependencies

**Critical order:**
1. **config_init.php** - MUST be first (loads ConfigManager)
2. **access_control.php** - After config_init (uses ConfigManager)
3. **layout.php** / **page-setup.php** - After both above
4. Content or other components

**Why order matters:**
- ConfigManager must exist before config_init.php finishes
- access_control.php needs ConfigManager to function
- Page structure needs both config and access control ready

**DO NOT:**
- ❌ Include header/footer in wrong order
- ❌ Use ConfigManager before config_init.php
- ❌ Check permissions before access_control.php
- ❌ Mix old (head.php) and new (head-resources.php) naming

---

## Configuration Integration

All includes that use configuration do so via ConfigManager:

- **page-setup.php**: Gets site title from config
- **head-resources.php**: Gets favicon and CSS paths from config
- **banner.php**: Gets banner path from config
- **navbar.php**: Gets tool info from config

Configuration is never hardcoded - it all goes through ConfigManager.

For configuration details, see `/config/README.md`

---

## Security

### Separation of Concerns
- **ConfigManager + config_init.php**: Configuration loading only
- **access_control.php**: User authentication only
- **layout.php + page-setup.php**: Page structure only
- Each has one responsibility, making security audits easier

### Access Control Flow
```
Page loads
    ↓
config_init.php initializes ConfigManager
    ↓
access_control.php checks user
    ↓
If valid → page renders
If invalid → access_control.php exits early
```

### ConfigManager Security
- No direct file access in controllers
- All paths go through ConfigManager
- Prevents directory traversal attacks
- Centralizes security logic

---

## File Structure Reference

```
includes/
├── ConfigManager.php                # Configuration singleton
├── config_init.php                  # Bootstrap ConfigManager
├── access_control.php               # Authentication & authorization
├── layout.php                       # Page rendering orchestrator
├── page-setup.php                   # Open HTML page
├── footer.php                       # Close HTML page
├── head-resources.php               # CSS/meta/fonts for <head>
├── navbar.php                       # Header area (banner + toolbar)
├── banner.php                       # Rotating banner images
├── toolbar.php                      # Navigation toolbar
├── source-selector-helpers.php      # Organism/assembly selection logic
├── source-list.php                  # Source selection UI component
└── README.md                        # This file
```

---

## Common Mistakes & Solutions

### ❌ "Call to undefined ConfigManager"
**Cause:** ConfigManager::getInstance() called before config_init.php included

**Fix:**
```php
<?php
include_once __DIR__ . '/config_init.php';  // Include FIRST
$config = ConfigManager::getInstance();      // Then use
?>
```

### ❌ "Cannot modify header information"
**Cause:** HTML output before setting headers (usually on AJAX pages)

**Fix:** Use output buffering on AJAX pages
```php
<?php
ob_start();  // Capture output
// ... process
header('Content-Type: application/json');  // Safe now
echo json_encode($result);
?>
```

### ❌ "Undefined $_SESSION variable"
**Cause:** access_control.php not included

**Fix:**
```php
<?php
include_once __DIR__ . '/config_init.php';
include_once __DIR__ . '/access_control.php';  // Include this
// Now $_SESSION is set
?>
```

### ❌ Page looks broken (no styling)
**Cause:** head-resources.php not included

**Fix:** Use layout.php or page-setup.php which include it automatically

---

## For More Information

- **Configuration details:** See `/config/README.md`
- **Page architecture:** See `MOOP_COMPREHENSIVE_OVERVIEW.md`
- **Admin pages:** See `/admin/DEVELOPER_GUIDE.md`
- **Tools:** See `/tools/DEVELOPER_GUIDE.md`

---

**Last Updated:** January 2026
