# Display Pages Simplification Strategy

## Key Insight: All 4 Pages Follow Same Pattern

After analyzing organism, assembly, groups, and multi_organism_search pages:

### Pattern Identified

ALL 4 pages have:
1. **Identical HTML structure** (container, rows, cards)
2. **Identical search form layout** (search input, button, results)
3. **Identical data table rendering** (DataTables with same config)
4. **Identical script loading** (jQuery, Bootstrap, DataTables, custom modules)
5. **Only differences**: Variable names, context data, JavaScript handlers

### Structure Comparison

```
Old monolithic approach (4 pages × 250+ lines each = 1000+ lines):
- organism_display.php: 294 lines
- assembly_display.php: ~300 lines  
- groups_display.php: ~350 lines
- multi_organism_search.php: ~300 lines

New approach - 3-tier system:
1. display-template.php (100 lines) - GENERIC wrapper for ALL 4 pages
2. /{page}_display.php (40 lines each) - Configuration/context only
3. pages/{page}.php (150 lines each) - Content only

Result: 1000+ lines → ~500 lines = 50% reduction
```

---

## The Solution: Generic Display Template

Instead of creating 4 separate wrappers, create ONE generic template:

### File: `tools/display-template.php` (GENERIC)

```php
<?php
/**
 * GENERIC DISPLAY TEMPLATE
 * 
 * Used by: organism_display.php, assembly_display.php, 
 *          groups_display.php, multi_organism_search.php
 * 
 * How it works:
 * 1. Child page calls: include_once 'display-template.php'
 * 2. Child page sets up context variables and $display_config
 * 3. Template renders complete page using layout.php
 * 4. Template loads content file based on $display_config
 */

include_once __DIR__ . '/../includes/layout.php';

// Child page must define:
// - $display_config['title'] - Page title
// - $display_config['content_file'] - Path to content file
// - $display_config['page_script'] - JS file to load
// - $display_config['inline_scripts'] - Array of inline script strings
// - $data - Array of variables for content file

// Build inline scripts (makes variables available to JS)
if (!isset($display_config['inline_scripts'])) {
    $display_config['inline_scripts'] = [];
}

// Add to data array
$data['inline_scripts'] = $display_config['inline_scripts'];
$data['page_script'] = $display_config['page_script'];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);
?>
```

### File: `tools/organism_display.php` (SIMPLIFIED)

```php
<?php
include_once __DIR__ . '/tool_init.php';

// Load config and setup context
$organism_data = $config->getPath('organism_data');
$organism_context = setupOrganismDisplayContext($_GET['organism'] ?? '', $organism_data);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Configure display template
$display_config = [
    'title' => htmlspecialchars(
        ($organism_info['common_name'] ?? $organism_name) . ' - ' . $config->getString('siteTitle')
    ),
    'content_file' => __DIR__ . '/pages/organism.php',
    'page_script' => '/' . $config->getString('site') . '/js/organism-display.js',
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        "const organismName = '" . addslashes($organism_name) . "';"
    ]
];

// Prepare data for content file
$data = [
    'organism_name' => $organism_name,
    'organism_info' => $organism_info,
    'config' => $config,
    'site' => $config->getString('site'),
    'images_path' => $config->getString('images_path'),
    'absolute_images_path' => $config->getPath('absolute_images_path'),
];

// Use generic template
include_once __DIR__ . '/display-template.php';
?>
```

### File: `tools/assembly_display.php` (SIMPLIFIED)

```php
<?php
include_once __DIR__ . '/tool_init.php';

// Load config and setup context
$organism_data = $config->getPath('organism_data');
$assembly_param = $_GET['assembly'] ?? '';
$organism_name = $_GET['organism'] ?? '';

$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
$assembly_info = getAssemblyStats($assembly_param, ...);

// Configure display template
$display_config = [
    'title' => htmlspecialchars($assembly_info['name'] . ' - ' . $config->getString('siteTitle')),
    'content_file' => __DIR__ . '/pages/assembly.php',
    'page_script' => '/' . $config->getString('site') . '/js/assembly-display.js',
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        "const assemblyName = '" . addslashes($assembly_param) . "';"
    ]
];

// Prepare data for content file
$data = [
    'organism_name' => $organism_name,
    'assembly_param' => $assembly_param,
    'assembly_info' => $assembly_info,
    'config' => $config,
    'site' => $config->getString('site'),
];

// Use generic template
include_once __DIR__ . '/display-template.php';
?>
```

---

## Updated layout.php Enhancement

Need to update layout.php to support `inline_scripts`:

```php
// In render_display_page function, before page_script loads:

// Inline scripts (for page-specific variables)
<?php
if (isset($inline_scripts) && is_array($inline_scripts)) {
    foreach ($inline_scripts as $script) {
        echo '<script>' . "\n" . $script . "\n" . '</script>' . "\n";
    }
}
?>
```

This keeps things clean:
- Scripts load BEFORE page_script (which needs those variables)
- Page-specific variables injected cleanly
- Generic template handles all similar pages
- Each page only defines its configuration

---

## Implementation Plan

### Phase 2a: Create Generic Template
1. Create `display-template.php` (100 lines)
2. Update `layout.php` to support inline_scripts (3 lines added)

### Phase 2b: Simplify Each Page
1. Simplify `organism_display.php` (294 → 40 lines)
2. Simplify `assembly_display.php` (300 → 40 lines)
3. Simplify `groups_display.php` (350 → 45 lines)
4. Simplify `multi_organism_search.php` (300 → 40 lines)
5. Create content files (pages/organism.php, etc.)

### Benefits

✅ DRY principle: Template duplicated logic eliminated
✅ Maintainability: Change template once, affects all 4 pages
✅ Consistency: All 4 pages guaranteed to work same way
✅ Code reduction: 1000+ lines → 500 lines
✅ Clarity: Each file has single responsibility

---

## File Organization After Refactor

```
/tools/
├── display-template.php ........... Generic wrapper for search pages
├── organism_display.php ........... Config + context setup (40 lines)
├── assembly_display.php ........... Config + context setup (40 lines)
├── groups_display.php ............ Config + context setup (45 lines)
├── multi_organism_search.php ...... Config + context setup (40 lines)
├── parent_display.php ............ NOT refactored (different pattern)
├── pages/
│   ├── organism.php ............... Display content (150 lines)
│   ├── assembly.php ............... Display content (150 lines)
│   ├── groups.php ................ Display content (150 lines)
│   ├── multi_organism.php ......... Display content (150 lines)
│   └── test.php ................... Test content
└── *.backup ....................... Original backups preserved

/includes/
├── layout.php ..................... Main layout system (updated)
└── ...
```

---

## Why This Is Better Than Individual Wrappers

### Individual Approach (What We Started)
- 4 separate wrappers: organism_display.php, assembly_display.php, etc.
- Each wrapper duplicates template logic
- Changes require updating 4 files
- Hard to keep consistent

### Generic Template Approach (Recommended)
- 1 reusable template: display-template.php
- 4 simple config files: just context setup
- Change template once = affects all 4
- Guaranteed consistency

### Code Comparison

**Individual approach:**
```
organism_display.php:     62 lines (wrapper logic)
assembly_display.php:     62 lines (wrapper logic)
groups_display.php:       65 lines (wrapper logic)
multi_organism_search.php: 62 lines (wrapper logic)
TOTAL: 251 lines of template logic
```

**Generic approach:**
```
display-template.php:     100 lines (logic, reused by all 4)
organism_display.php:     40 lines (only config)
assembly_display.php:     40 lines (only config)
groups_display.php:       45 lines (only config)
multi_organism_search.php: 40 lines (only config)
TOTAL: 265 lines but 100 shared = effective 165 lines
```

Plus: Much easier to maintain, understand, and modify!

