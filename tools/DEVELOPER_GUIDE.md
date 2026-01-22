# Tools Development Guide

## Overview

Tools follow a **controller + view template** architecture identical to admin pages:
- **Controller** (root tools/*.php): Handles logic, data loading, context validation
- **View Template** (tools/pages/*.php): Renders HTML display
- **Layout System** (includes/layout.php): Orchestrates rendering

---

## Quick Start: Creating a New Tool

### Step 1: Create Controller

Create `/data/moop/tools/my_tool.php`:

```php
<?php
/**
 * MY TOOL - Tool Description
 * 
 * What this tool does and how it works
 */

// 1. Initialize tool environment
include_once __DIR__ . '/tool_init.php';

// 2. Load page-specific libraries if needed
include_once __DIR__ . '/../lib/functions_data.php';

// 3. Get config values
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$site_title = $config->getString('siteTitle');

// 4. Load and validate data
$data = loadData();

// 5. Configure display
$title = "My Tool - $site_title";
$display_config = [
    'title' => $title,
    'content_file' => __DIR__ . '/pages/my_tool.php',
    'page_script' => '/' . $config->getString('site') . '/js/my_tool.js',
    'styles' => ['css/my_tool.css']
];

// 6. Call layout system
include_once __DIR__ . '/../includes/layout.php';
render_display_page(
    $display_config['content_file'],
    ['data' => $data],
    $display_config['title']
);
?>
```

### Step 2: Create View Template

Create `/data/moop/tools/pages/my_tool.php`:

```php
<div class="container mt-5">
    <h1>My Tool</h1>
    
    <div class="card">
        <div class="card-body">
            <p><?php echo htmlspecialchars($data['description']); ?></p>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['items'] as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

---

## tool_init.php: What It Provides

**tool_init.php** initializes the tool environment:

```php
include_once __DIR__ . '/tool_init.php';

// After include, you have:
// 1. Session started (if not already)
// 2. ConfigManager initialized ($config available)
// 3. User access validated (access_control checked)
// 4. Common libraries loaded (lib/ functions)
```

### What It Does

1. **Session Management**
   - Starts PHP session (if not already started)
   - Preserves existing session data

2. **Config Loading**
   - Initializes ConfigManager singleton
   - Loads site_config.php (defaults)
   - Loads config_editable.json (overrides)
   - Makes $config available

3. **Access Control**
   - Calls access_control.php
   - Validates user is logged in
   - Checks user has appropriate access
   - Redirects if not authorized

4. **Common Libraries**
   - Loads lib/moop_functions.php (general utilities)
   - Loads lib/functions_data.php (data retrieval)
   - Loads lib/functions_database.php (database helpers)

---

## Page Structure: Controller vs View

### Data Flow

```
User visits /tools/my_tool.php
    ↓
my_tool.php (controller) executes:
├─ Include tool_init.php
├─ Load configuration
├─ Validate access
├─ Load/process data
├─ Configure display settings
└─ Call render_display_page()
    ↓
render_display_page() (in layout.php):
├─ Includes page-setup.php (opens <html>, <head>, <body>)
├─ Includes head-resources.php (CSS, fonts, meta)
├─ Includes navbar.php (banner + toolbar)
├─ Includes content file (pages/my_tool.php)
├─ Includes footer.php (closes </body>, </html>)
└─ Returns complete HTML page
    ↓
pages/my_tool.php (view) displays:
├─ Page content
├─ Tables, forms, cards
├─ Data passed from controller
└─ JavaScript interactions
```

### Controller Responsibilities (tools/*.php)

✓ Load configuration  
✓ Validate user access  
✓ Load/query data  
✓ Process form submissions  
✓ Handle special logic  
✓ Prepare data for view  
✓ Configure display settings (title, scripts, styles)  
✓ Call render_display_page()  

❌ Should NOT output HTML directly  
❌ Should NOT include page tags  
❌ Should NOT mix logic and display  

### View Responsibilities (tools/pages/*.php)

✓ Display data received  
✓ Render HTML content  
✓ Create tables, forms, cards  
✓ Include JavaScript for interactivity  
✓ Format data for display  

❌ Should NOT perform queries  
❌ Should NOT process form submissions  
❌ Should NOT output page structure tags  
❌ Should NOT start sessions or load config  

---

## Available Resources

### ConfigManager Methods

```php
$config = ConfigManager::getInstance();

// Path getters (filesystem safe)
$root = $config->getPath('root_path');
$organism_data = $config->getPath('organism_data');
$metadata = $config->getPath('metadata_path');
$logs = $config->getPath('logs_path');

// String getters
$title = $config->getString('siteTitle');
$site = $config->getString('site');
$images_path = $config->getString('images_path');

// Array getters
$types = $config->getSequenceTypes();
$tools = $config->getAllTools();

// Tool info
$tool = $config->getTool('organism');
```

### Loaded Libraries

After `tool_init.php`, these are available:

**lib/moop_functions.php:**
- `loadJsonFile($path, $default)` - Load and decode JSON
- `saveJsonFile($path, $data)` - Save and encode JSON
- `getDirectorySize($path)` - Get directory size
- General utility functions

**lib/functions_data.php:**
- `getAccessibleOrganisms()` - Get organisms user can access
- `getAccessibleAssemblies()` - Get assemblies user can access
- `getAccessibleGroups()` - Get groups user can access
- Data retrieval helpers

**lib/functions_database.php:**
- Database connection helpers
- Query execution wrappers
- Result formatting

### Session Variables

After access control, these $_SESSION variables are set:

```php
$_SESSION['user'] = [
    'username' => 'user@example.com',
    'role' => 'collaborator',  // admin, collaborator, visitor
    'access_level' => 'collaborator'
];

$_SESSION['access'] = [
    'organisms' => [...],     // Accessible organisms
    'groups' => [...],        // Accessible groups
    'tools' => [...]          // Accessible tools
];
```

---

## Display Configuration

Tools configure display via array passed to `render_display_page()`:

```php
$display_config = [
    'title' => 'Page Title - Site Name',
    'content_file' => __DIR__ . '/pages/my_tool.php',
    'page_script' => '/' . $site . '/js/my_tool.js',
    'styles' => ['css/my_tool.css', 'css/other.css'],
    'inline_scripts' => [
        "const siteTitle = '" . $title . "';",
        "const organism = '" . $organism . "';"
    ]
];

render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);
```

### Typical Display Configuration

```php
// 1. Set title (shown in browser tab and header)
$title = htmlspecialchars($organism_name . ' - ' . $config->getString('siteTitle'));

// 2. Set content file path
$content_file = __DIR__ . '/pages/organism.php';

// 3. Set page scripts
$page_script = '/' . $config->getString('site') . '/js/organism-display.js';

// 4. Prepare data to pass to view
$page_data = [
    'organism' => $organism_name,
    'info' => $organism_info,
    'assemblies' => $assemblies,
    'groups' => $groups
];

// 5. Call layout
include_once __DIR__ . '/../includes/layout.php';
render_display_page($content_file, $page_data, $title);
```

---

## Context Parameters

Tools often accept context parameters to filter what's displayed:

```php
// Get parameters from URL
$organism = $_GET['organism'] ?? null;
$assembly = $_GET['assembly'] ?? null;
$group = $_GET['group'] ?? null;

// Parse standard context (handles all three)
$context = parseContextParameters();
$organism = $context['organism'];      // Organism name or null
$assembly = $context['assembly'];      // Assembly name or null
$group = $context['group'];            // Group name or null
$display_name = $context['display_name']; // "organism > assembly" format
```

**Helper Functions:**
- `parseContextParameters()` - Parse org/assembly/group from URL
- `getAccessibleOrganisms()` - Get organisms user can access
- `getAccessibleAssemblies()` - Get assemblies user can access
- `filterAssembliesByOrganism()` - Filter assemblies to specific organism

---

## Best Practices

### 1. Always Use tool_init.php First

```php
<?php
include_once __DIR__ . '/tool_init.php';
// Now $config and access control are ready
?>
```

### 2. Use ConfigManager, Never Hardcode Paths

❌ **Wrong:**
```php
$file = '/data/moop/metadata/organisms.json';
```

✅ **Right:**
```php
$metadata = $config->getPath('metadata_path');
$file = "$metadata/organisms.json";
```

### 3. Validate User Access

```php
if (!isset($_SESSION['access']['organisms'][$organism])) {
    header('Location: /moop/access_denied.php');
    exit;
}
```

### 4. Escape Output in Views

```php
<!-- Safe -->
<td><?php echo htmlspecialchars($item['name']); ?></td>

<!-- Unsafe - XSS risk -->
<td><?php echo $item['name']; ?></td>
```

### 5. Use render_display_page() for Consistency

```php
include_once __DIR__ . '/../includes/layout.php';
render_display_page($content_file, $data, $title);
```

Do NOT manually include head-resources.php, navbar.php, or footer.php - layout.php does this automatically.

### 6. Load Page-Specific Libraries After tool_init.php

```php
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/blast_functions.php';  // ✓ AFTER tool_init
```

### 7. Use Source Selector for Organism/Assembly Selection

For tools that need users to select organisms/assemblies:

```php
include_once __DIR__ . '/../includes/source-selector-helpers.php';

$sources_by_group = getAccessibleAssemblies();
$context = parseContextParameters();

// Returns selection info
$selection = prepareSourceSelection(
    $context,
    $sources_by_group,
    true  // auto-select if context provided
);
```

---

## Common Patterns

### Display Single Organism

```php
<?php
include_once __DIR__ . '/tool_init.php';

$organism = $_GET['organism'] ?? null;
if (!$organism || !isset($_SESSION['access']['organisms'][$organism])) {
    header('Location: /access_denied.php');
    exit;
}

$organism_data = $config->getPath('organism_data');
$organism_path = "$organism_data/$organism";
$db_file = "$organism_path/genes.sqlite";

$data = [
    'organism' => $organism,
    'db' => $db_file
];

include_once __DIR__ . '/../includes/layout.php';
render_display_page(
    __DIR__ . '/pages/my_organism.php',
    $data,
    "$organism - " . $config->getString('siteTitle')
);
?>
```

### Display with Filters/Search

```php
<?php
include_once __DIR__ . '/tool_init.php';

$search = $_GET['q'] ?? '';
$filter = $_GET['filter'] ?? 'all';

// Validate and sanitize
$search = trim(htmlspecialchars($search));

// Load data
$results = searchData($search, $filter);

$data = [
    'results' => $results,
    'search' => $search,
    'filter' => $filter
];

include_once __DIR__ . '/../includes/layout.php';
render_display_page(
    __DIR__ . '/pages/search_results.php',
    $data,
    "Search Results - " . $config->getString('siteTitle')
);
?>
```

### Display with JavaScript Interactivity

```php
<?php
include_once __DIR__ . '/tool_init.php';

$organism = $_GET['organism'];
$site = $config->getString('site');

$data = ['organism' => $organism];

// Pass JavaScript variables
$inline_script = <<<JS
    const organism = '$organism';
    const siteTitle = '{$config->getString('siteTitle')}';
    const site = '$site';
JS;

// Note: For inline scripts, you may need to modify render_display_page
// Or include scripts in the view template using PHP
include_once __DIR__ . '/../includes/layout.php';
render_display_page(
    __DIR__ . '/pages/my_tool.php',
    $data,
    "Tool - " . $config->getString('siteTitle')
);
?>
```

---

## File Structure

```
tools/
├── DEVELOPER_GUIDE.md               # This file
├── my_tool.php                      # Tool controller
├── pages/
│   └── my_tool.php                  # Tool view template
├── blast.php                        # BLAST tool controller
├── organism.php                     # Organism display
├── assembly.php                     # Assembly display
├── parent.php                       # Feature display
├── groups.php                       # Group browsing
├── multi_organism.php               # Multi-organism search
└── retrieve_sequences.php           # Sequence extraction

lib/
├── moop_functions.php               # General utilities
├── functions_data.php               # Data retrieval
├── functions_database.php           # Database helpers
├── blast_functions.php              # BLAST functions
├── blast_results_visualizer.php     # BLAST formatting
└── [other libraries]

includes/
├── layout.php                       # Page rendering orchestrator
├── page-setup.php                   # Open HTML
├── footer.php                       # Close HTML
├── head-resources.php               # CSS, meta, fonts
├── navbar.php                       # Header area
├── banner.php                       # Banner images
└── toolbar.php                      # Navigation toolbar
```

---

## Error Handling

### Access Denied

```php
if (!isset($_SESSION['access']['organisms'][$organism])) {
    header('Location: /moop/access_denied.php');
    exit;
}
```

### Missing Data

```php
if (!file_exists($db_file)) {
    echo "<div class='alert alert-danger'>Database not found for $organism</div>";
    exit;
}
```

### Logging Errors

```php
error_log("Tool error: " . $message, 3, "/data/moop/logs/error.log");
```

---

## Related Documentation

- **Admin Tools:** See `/admin/DEVELOPER_GUIDE.md`
- **Page Architecture:** See `MOOP_COMPREHENSIVE_OVERVIEW.md`
- **Configuration:** See `/config/README.md`
- **Includes System:** See `includes/README.md`
- **BLAST Tool:** See `BLAST_TOOL_README.md`

---

**Last Updated:** January 2026
