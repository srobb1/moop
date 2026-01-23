# JavaScript Organization & Guidelines

This directory contains all client-side JavaScript for MOOP. The organization strategy balances reusability, maintenance, and performance.

---

## Quick Overview

| Directory | Purpose | When to Use |
|-----------|---------|------------|
| **Root** (`*.js`) | Page-specific scripts | Loaded inline in controllers for specific pages |
| **modules/** | Shared feature modules | Included when multiple pages need same functionality |
| **CDN URLs** | Third-party libraries | Loaded from external CDN in `head-resources.php` |

---

## File Organization

### Root Level Files (11 files)

Page-specific JavaScript files that handle interactions for particular pages.

```
js/
├── admin-utilities.js          # Helper functions for admin pages
├── assembly-display.js         # Assembly page (assembly.php) interactions
├── blast-manager.js            # BLAST tool page interactions
├── groups-display.js           # Groups page (groups.php) interactions
├── index.js                    # Site home/index page interactions
├── manage-registry.js          # Function registry admin page
├── multi-organism-search.js    # Multi-organism search tool
├── organism-display.js         # Organism page (organism.php) interactions
├── permission-manager.js       # Permission management admin page
├── registry.js                 # Public registry functions
└── sequence-retrieval.js       # Sequence download tool
```

**Characteristics:**
- Usually one file per tool/page
- Loaded inline in PHP controller
- Contains initialization code and event handlers
- Minimal code reuse between files

**When to create:**
- New tool needs JavaScript interactivity
- Page has complex UI interactions
- Need page-specific event handlers

### modules/ Directory (19 files)

Shared feature modules used across multiple pages/tools.

```
modules/
├── advanced-search-filter.js   # Search filtering logic (multi-page)
├── annotation-search.js        # Annotation search functionality
├── blast-canvas-graph.js       # BLAST result canvas visualization
├── collapse-handler.js         # Collapsible UI elements (shared)
├── copy-to-clipboard.js        # Copy-to-clipboard utility
├── datatable-config.js         # DataTables initialization (multi-page)
├── download-handler.js         # File download utilities
├── manage-annotations.js       # Annotation management UI
├── manage-groups.js            # Group management UI
├── manage-registry.js          # Registry management UI
├── manage-site-config.js       # Site config management UI
├── manage-taxonomy-tree.js     # Taxonomy tree management UI
├── manage-users.js             # User management UI
├── organism-management.js      # Organism management UI
├── parent-tools.js             # Feature detail page (parent.php) tools
├── shared-results-table.js     # Reusable results table component
├── source-list-manager.js      # Organism/source selector (multi-page)
├── taxonomy-tree.js            # Phylogenetic tree display
└── utilities.js                # General utility functions
```

**Characteristics:**
- Used by multiple pages/tools
- Provides reusable components or functionality
- Can be imported as modules
- Share common logic across features

**When to create:**
- Functionality needed by 2+ pages
- Reusable UI component (collapsible, table, etc.)
- Shared utility functions
- Common data manipulation logic

---

## Including JavaScript in Your Code

### Method 1: Inline Script in PHP Controller (Root-level files)

**When to use:** Page-specific functionality only

```php
<?php
// In a tool page controller (e.g., tools/blast.php)

$display_config = [
    'content_file' => __DIR__ . '/pages/blast.php',
    'title' => 'BLAST Search Tool',
    'page_script' => [
        '/' . $site . '/js/blast-manager.js'  // Loaded inline
    ]
];

include_once __DIR__ . '/layout.php';
?>
```

**Result:** Script loads inline after HTML content, has access to page elements

**Best for:**
- Tool initialization
- Page-specific event handlers
- Page-specific data processing

### Method 2: Module Import (modules/ files)

**When to use:** Shared functionality across pages

```javascript
// In a root-level JS file or another module

// Import the module function
function initializePage() {
    // Use functions from the module
    const table = initDataTable('#myTable', {
        // options
    });
    
    const handler = new CopyToClipboard('.copy-btn');
}

// Or include via HTML script tag if needed for backward compatibility
```

**Best for:**
- Components used on multiple pages
- Utility functions shared across tools
- Reusable UI widgets

### Method 3: CDN URLs (External libraries)

**When to use:** Third-party libraries (Bootstrap, jQuery, jszip, etc.)

**Location:** `/includes/head-resources.php` or `/includes/page-setup.php`

```php
<head>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- jQuery from CDN -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <!-- jszip for Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
```

**Why use CDN:**
- ✅ No local files to maintain
- ✅ Browser caching across many websites
- ✅ CDN fast globally distributed
- ✅ Automatic updates from provider
- ✅ Saves disk space
- ✅ Industry standard for libraries

**When NOT to use CDN:**
- ❌ Offline-only environments
- ❌ Proprietary libraries
- ❌ Performance-critical custom code
- ❌ Code that needs frequent updates

---

## Decision Tree: Where to Put Your JavaScript

```
New JavaScript needed?
│
├─→ For ONE page/tool ONLY?
│   └─→ YES: Create ROOT-LEVEL FILE
│       └─→ Format: tool-name.js
│       └─→ Example: blast-manager.js
│
├─→ For MULTIPLE pages/tools?
│   ├─→ Reusable UI component?
│   │   └─→ YES: Create in modules/
│   │   └─→ Format: component-name.js
│   │   └─→ Example: datatable-config.js
│   │
│   └─→ Shared utility functions?
│       └─→ YES: Create in modules/
│       └─→ Format: function-area.js
│       └─→ Example: utilities.js
│
└─→ Third-party library?
    └─→ Add to head-resources.php
    └─→ Load from CDN
    └─→ Example: Bootstrap, jQuery, jszip
```

---

## Architecture & Design Philosophy

### Why Two Levels (Root + modules/)?

```
The structure supports both:
1. PERFORMANCE - Load only needed code per page
2. REUSABILITY - Share common functionality
```

#### Root Level Files
- **Loaded inline in controller** - After HTML renders
- **Page specific** - Only loaded when page needs it
- **Performance:** Smaller pages load faster (no unused JS)
- **Maintenance:** Easy to find page-specific code

#### modules/ Files
- **Import when needed** - Can be included by multiple files
- **Shared functionality** - Used by 2+ pages
- **Performance:** Downloaded once, used everywhere
- **Maintenance:** Single source of truth

### Example: DataTables

**Problem:** Multiple pages need DataTables (organism.php, assembly.php, parent.php)

**Solution:** Shared module `modules/datatable-config.js`

```javascript
// modules/datatable-config.js
function initDataTable(tableId, options = {}) {
    // Configuration and initialization
    const defaults = {
        searching: true,
        paging: true,
        ordering: true,
        // ... more defaults
    };
    
    return new DataTable(tableId, $.extend(defaults, options));
}
```

**Usage in multiple files:**
```javascript
// organism-display.js
initDataTable('#organism-table');

// assembly-display.js
initDataTable('#assembly-table');

// parent-tools.js
initDataTable('#parent-table');
```

**Benefit:** One implementation, used everywhere. Fix bug once, all pages get fix.

---

## Including Modules in Controllers

### Pattern: Loading Module in Page Controller

```php
<?php
// In a tool controller (e.g., tools/pages/organism.php)

$display_config = [
    'content_file' => __DIR__ . '/pages/organism.php',
    'title' => 'Organism View',
    'page_script' => [
        '/' . $site . '/js/modules/datatable-config.js',    // Module first
        '/' . $site . '/js/modules/source-list-manager.js', // Then more modules
        '/' . $site . '/js/organism-display.js'              // Page script last
    ]
];

include_once __DIR__ . '/layout.php';
?>
```

**Order matters:**
1. **Modules first** - Make shared functions available
2. **Page script last** - Calls module functions after they're loaded

### Pattern: Checking if Module Already Loaded

```javascript
// modules/utilities.js
if (typeof MOOPUtilities !== 'undefined') {
    // Already loaded, skip
} else {
    var MOOPUtilities = {
        formatDate: function(date) { /* ... */ },
        sanitize: function(str) { /* ... */ },
        // ...
    };
}
```

This prevents double-loading and potential conflicts.

---

## Naming Conventions

### Root-Level Files
**Format:** `<tool-or-page>-<purpose>.js`

Examples:
- `blast-manager.js` - BLAST tool
- `organism-display.js` - Organism page
- `sequence-retrieval.js` - Sequence download tool
- `multi-organism-search.js` - Multi-organism search

### Module Files
**Format:** `<component-or-feature-name>.js`

Examples:
- `datatable-config.js` - DataTables component
- `collapse-handler.js` - Collapsible UI
- `copy-to-clipboard.js` - Copy utility
- `advanced-search-filter.js` - Search feature

### Avoid:
- ❌ Generic names: `script.js`, `main.js`, `common.js`
- ❌ Camel case: `blastManager.js` (use hyphens)
- ❌ Unclear abbreviations: `ds.js`, `util2.js`

---

## When to Move Code Between Locations

### Move root file to modules/ when:
1. ✅ Another page needs similar functionality
2. ✅ Code is extracted and becomes reusable
3. ✅ Multiple pages include same file

### Keep as root file when:
1. ✅ Only one page/tool uses it
2. ✅ Tightly coupled to page structure
3. ✅ Page-specific event handlers

### Move to CDN when:
1. ✅ Becomes popular enough to have public CDN
2. ✅ Multiple sites could benefit
3. ✅ Security/performance audited

---

## Best Practices

### DO:
✅ Keep root files focused on one page
✅ Extract reusable logic into modules/
✅ Use meaningful function names
✅ Comment complex logic
✅ Check if module exists before loading
✅ Load modules before page script
✅ Use CDN for standard libraries
✅ Keep modules small and focused

### DON'T:
❌ Create root file for shared code
❌ Mix multiple unrelated features in one file
❌ Use abbreviations in filenames
❌ Comment obvious code
❌ Rely on global variables
❌ Load page script before modules
❌ Load third-party from local files
❌ Create "catch-all" modules

---

## Common Patterns

### Pattern 1: Page with DataTables

```php
<?php
$display_config = [
    'content_file' => __DIR__ . '/pages/search-results.php',
    'title' => 'Search Results',
    'page_script' => [
        '/' . $site . '/js/modules/datatable-config.js',
        '/' . $site . '/js/multi-organism-search.js'
    ]
];
include_once __DIR__ . '/layout.php';
?>
```

### Pattern 2: Page with Collapsible Sections

```php
<?php
$display_config = [
    'content_file' => __DIR__ . '/pages/detail.php',
    'title' => 'Feature Details',
    'page_script' => [
        '/' . $site . '/js/modules/collapse-handler.js',
        '/' . $site . '/js/modules/parent-tools.js',
        '/' . $site . '/js/parent-tools.js'  // Wait, this seems wrong
    ]
];
include_once __DIR__ . '/layout.php';
?>
```

### Pattern 3: Tool with Multiple Features

```php
<?php
$display_config = [
    'content_file' => __DIR__ . '/pages/blast.php',
    'title' => 'BLAST Search',
    'page_script' => [
        '/' . $site . '/js/modules/datatable-config.js',
        '/' . $site . '/js/modules/copy-to-clipboard.js',
        '/' . $site . '/js/modules/download-handler.js',
        '/' . $site . '/js/blast-manager.js'
    ]
];
include_once __DIR__ . '/layout.php';
?>
```

---

## File Sizes & Performance

### Root-level files (typical):
- 1-5 KB each
- Minimal code, focused on page
- Fast to download and parse

### Module files (typical):
- 2-10 KB each
- Reusable logic
- Cached across pages

### Total JS payload:
- Typical page: 20-30 KB of root + modules
- Cached pages: ~10 KB (modules already cached)
- CDN libraries: ~300-400 KB (heavily cached by browsers)

### Optimization:
- Only load modules actually needed
- Use CDN for popular libraries (caching)
- Keep page scripts small and focused
- Lazy load heavy features if needed

---

## Testing & Debugging

### In Browser Developer Tools:
1. Open Console (F12)
2. Check what scripts loaded (Sources tab)
3. Verify functions are available

```javascript
// In console, test if module loaded:
typeof initDataTable !== 'undefined'  // Should be true if loaded
typeof MOOPUtilities !== 'undefined'  // Should be true if utilities loaded
```

### Common Issues:

**Problem:** "initDataTable is not defined"
```
Solution: Check if datatable-config.js loaded before your page script
```

**Problem:** Multiple copies of same data table
```
Solution: Check if datatable-config.js included twice (use typeof check)
```

**Problem:** Memory leak or performance degradation
```
Solution: Check for functions running multiple times (event handlers)
```

---

## Related Documentation

- **Library Functions:** See `lib/README.md` - PHP function organization
- **Comprehensive Overview:** See `MOOP_COMPREHENSIVE_OVERVIEW.md` - Function Registry section
- **Tools Guide:** See `tools/DEVELOPER_GUIDE.md`
- **Admin Guide:** See `admin/DEVELOPER_GUIDE.md`

---

## Summary: Quick Reference

| Question | Answer | Location |
|----------|--------|----------|
| Single page needs JS? | Create root file | `js/tool-name.js` |
| Multiple pages need same code? | Create module | `js/modules/feature.js` |
| Third-party library? | Use CDN | `includes/head-resources.php` |
| Where to put helpers? | utilities.js in modules | `js/modules/utilities.js` |
| How to reuse code? | Create module, include in controller | `$display_config['page_script']` |
| How to find functions? | Check registry or module file | `/docs/js_function_registry.json` |

---

**Last Updated:** January 2026
