# Complete Clean Architecture Implementation Plan

**Date Started:** December 3, 2025  
**Status:** In Progress  
**Objective:** Transform display pages into professional clean architecture with improved naming

---

## Phase 0: Rename Include Files for Clarity

### Step 0.1: File Renames

Rename for maximum clarity:

```
includes/head.php       →  includes/head-resources.php
includes/header.php     →  includes/page-setup.php
includes/navbar.php     →  (keep as-is)
includes/toolbar.php    →  (keep as-is)
includes/footer.php     →  (keep as-is)
```

### Step 0.2: Why These Changes

| Old Name | Problem | New Name | Benefit |
|----------|---------|----------|---------|
| `head.php` | Ambiguous - is it full `<head>` tag or content inside? | `head-resources.php` | Crystal clear: CSS, JS, meta tags |
| `header.php` | Confusing - "header" suggests branding area, not full page setup | `page-setup.php` | Obvious: sets up page opening |
| `navbar.php` | ✓ Clear and matches Bootstrap convention | (keep) | Standard naming |
| `toolbar.php` | ✓ Clear | (keep) | Standard naming |
| `footer.php` | ✓ Standard web convention | (keep) | Standard naming |

### Step 0.3: Files to Update

After renaming, update all includes in these files:

**Root Level:**
- [ ] index.php
- [ ] login.php
- [ ] logout.php
- [ ] access_denied.php

**Admin:**
- [ ] admin/admin.php
- [ ] admin/admin_init.php
- [ ] admin/*.php (all admin pages)

**Tools:**
- [ ] tools/tool_init.php
- [ ] tools/*_display.php (all tool pages)

---

## Phase 1: Infrastructure Setup

### Step 1.1: Create layout.php (Core System)

**File:** `includes/layout.php` (NEW)

Functions:
- `render_display_page()` - Wraps content with HTML structure
- `render_json_response()` - Returns JSON for AJAX

**Purpose:** Central point for all page rendering. Handles:
- HTML structure (<!DOCTYPE>, <html>, <head>, <body>)
- Loading head-resources.php, page-setup.php, navbar.php, footer.php
- Script management (all external scripts in ONE place)
- Output buffering for proper HTML closure

### Step 1.2: Create Directory Structure

```bash
mkdir -p /data/moop/tools/pages
mkdir -p /data/moop/admin/pages
```

**Purpose:** Separate content from structure
- `tools/pages/` - Page content files (organism.php, assembly.php, etc.)
- `admin/pages/` - Admin page content files

### Step 1.3: Update footer.php

**Add to end of footer.php:**
```php
</body>
</html>
```

**Purpose:** Ensure HTML is properly closed

---

## Phase 2: Convert Display Pages

These are the heart of the system - convert one at a time.

### Display Page Conversion Pattern

For each old file, create THREE new files:

1. **Wrapper** (`tools/organism_display.php`)
   - Entry point
   - ~30 lines
   - Load config, validate params, call layout.php

2. **Content** (`tools/pages/organism.php`)
   - Only content-specific HTML
   - ~150 lines
   - NO HTML structure, NO includes

3. **Keep backup** (rename old file temporarily)
   - Keep old file as `.backup_old`
   - Use for fallback/reference during transition
   - Delete after 1 week of success

### Files to Convert (In Order)

#### Priority 1: Simple Pages (Start Here)
- [ ] Step 2.1: Convert organism_display.php → organism.php
- [ ] Step 2.2: Test organism.php thoroughly

#### Priority 2: Similar Pages
- [ ] Step 2.3: Convert assembly_display.php → assembly.php
- [ ] Step 2.4: Convert groups_display.php → groups.php
- [ ] Step 2.5: Convert multi_organism_search.php → multi_organism.php
- [ ] Step 2.6: Test all three

#### Priority 3: Complex Page
- [ ] Step 2.7: Convert parent_display.php → parent.php
- [ ] Step 2.8: Test parent.php thoroughly (most critical)

---

## Phase 3: Verify & Clean Up

### Step 3.1: Comprehensive Testing

For each converted page:
```
[ ] Opens without errors
[ ] Dev tools shows valid HTML structure
[ ] No JavaScript console errors
[ ] No duplicate script warnings
[ ] Page functionality works (search, downloads, etc.)
[ ] Links navigate correctly
[ ] AJAX works (if applicable)
[ ] Access control works
[ ] Tools appear correctly
```

### Step 3.2: Backup Old Files

```bash
# Keep originals as backup
cp tools/organism_display.php tools/organism_display.php.backup_old
cp tools/assembly_display.php tools/assembly_display.php.backup_old
# etc.
```

### Step 3.3: Cleanup (After 1 Week of Success)

```bash
# Delete old files after new ones proven stable
rm tools/organism_display.php.backup_old
rm tools/assembly_display.php.backup_old
# etc.
```

---

## Detailed Implementation Guide

### Step 0: File Renaming (Start Here!)

#### 0.1 - Rename the files

```bash
cd /data/moop/includes
mv head.php head-resources.php
mv header.php page-setup.php
```

#### 0.2 - Find and replace includes

**Search for:**
```
include_once __DIR__ . '/../includes/head.php'
include_once __DIR__ . '/includes/head.php'
include_once __DIR__ . '/../includes/header.php'
include_once __DIR__ . '/includes/header.php'
```

**Replace with:**
```
include_once __DIR__ . '/../includes/head-resources.php'
include_once __DIR__ . '/includes/head-resources.php'
include_once __DIR__ . '/../includes/page-setup.php'
include_once __DIR__ . '/includes/page-setup.php'
```

**Files to update:**
- index.php
- login.php
- logout.php
- access_denied.php
- admin/admin_init.php
- admin/admin.php
- All admin pages
- tools/tool_init.php
- All tool pages

#### 0.3 - Update includes/page-setup.php

At the top of page-setup.php, add documentation:

```php
<?php
/**
 * PAGE SETUP - Full Page Opening
 * 
 * Sets up the complete opening of an HTML page:
 * - Includes <!DOCTYPE html>
 * - Opens <html>, <head>, <body> tags
 * - Includes head-resources.php for CSS/JS/meta
 * - Includes navbar.php for navigation
 * 
 * PAIRED WITH: footer.php (which closes the page)
 * 
 * DO NOT include this file alone - always pair with footer.php
 */
```

#### 0.4 - Update includes/head-resources.php

At the top, add documentation:

```php
<?php
/**
 * HEAD RESOURCES - Stylesheet and Meta Tags
 * 
 * Contains only content that goes INSIDE the <head> tag:
 * - Meta tags (charset, viewport)
 * - CSS links (Bootstrap, custom)
 * - Any JavaScript that needs early loading
 * 
 * This is included by page-setup.php
 * This is NOT a full page - see page-setup.php for full page opening
 */
```

### Step 1: Create infrastructure/layout.php

**File:** `/data/moop/includes/layout.php`

[Full code provided below]

### Step 2: Create pages/ directories

```bash
mkdir -p /data/moop/tools/pages
mkdir -p /data/moop/admin/pages
```

### Step 3: Update footer.php

Add closing tags to end of footer.php:

```php
</body>
</html>
```

### Step 4: Create tools/pages/organism.php

**File:** `/data/moop/tools/pages/organism.php`

[Full code provided below]

### Step 5: Create new tools/organism_display.php wrapper

**File:** `/data/moop/tools/organism_display.php` (NEW)

[Full code provided below]

### Step 6: Test organism page

- [ ] Remove `tools/organism_display.php.backup_old` suffix
- [ ] Rename old file: `mv organism_display.php organism_display.php.backup_old`
- [ ] Open in browser: `/moop/tools/organism_display.php?organism=test_organism`
- [ ] Verify works identically to old version
- [ ] Check dev tools for valid HTML
- [ ] Test all functionality

### Step 7: Repeat for other display pages

Repeat steps 4-6 for:
- assembly_display.php
- groups_display.php
- multi_organism_search.php
- parent_display.php

---

## Code Files to Create

### File 1: includes/layout.php

```php
<?php
/**
 * PAGE LAYOUT SYSTEM
 * 
 * Provides unified page rendering with automatic header/footer wrapping.
 * All display pages (organism.php, assembly.php, etc.) use this system.
 * 
 * This ensures:
 * - Consistent HTML structure across all pages
 * - Proper opening and closing of all tags
 * - Centralized control of layout changes
 * - Separation of content from structure
 * 
 * USAGE:
 *   echo render_display_page('tools/pages/organism.php', [
 *       'organism_name' => $organism_name,
 *       'config' => $config,
 *   ], 'Page Title');
 */

/**
 * Render a display page with full HTML structure
 * 
 * @param string $content_file Path to content file (relative or absolute)
 * @param array $data Data to pass to content file
 * @param string $title Page title
 * @return string Complete HTML page
 */
function render_display_page($content_file, $data = [], $title = '') {
    // Ensure config is loaded
    if (!class_exists('ConfigManager')) {
        include_once __DIR__ . '/config_init.php';
    }
    include_once __DIR__ . '/access_control.php';
    
    // Get config instance
    $config = ConfigManager::getInstance();
    
    // Extract data to variables for use in content file
    extract($data);
    
    // Start output buffering to capture complete page
    ob_start();
    
    // Output HTML structure with all resources
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title><?= htmlspecialchars($title) ?></title>
        <?php include_once __DIR__ . '/head-resources.php'; ?>
    </head>
    <body class="bg-light">
        <?php include_once __DIR__ . '/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <?php 
            // Include content file
            if (file_exists($content_file)) {
                include $content_file;
            } else {
                echo '<div class="alert alert-danger">Error: Content file not found: ' . htmlspecialchars($content_file) . '</div>';
            }
            ?>
        </div>
        
        <?php include_once __DIR__ . '/footer.php'; ?>
        
        <!-- Script management - all external scripts in one place -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js"></script>
        
        <!-- MOOP shared modules -->
        <script src="/<?= $config->getString('site') ?>/js/modules/datatable-config.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/shared-results-table.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/annotation-search.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/advanced-search-filter.js"></script>
        
        <?php
        // If custom page-specific script is provided, include it
        if (isset($page_script)) {
            echo '<script src="' . htmlspecialchars($page_script) . '"></script>' . "\n";
        }
        ?>
    </body>
    </html>
    <?php
    
    return ob_get_clean();
}

/**
 * Render as JSON response (for AJAX requests)
 * Used when pages need to return data instead of HTML
 * 
 * @param array $data Data to return as JSON
 * @param int $status HTTP status code
 * @return void (outputs JSON and exits)
 */
function render_json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

?>
```

---

## Testing Checklist

### After Each Page Conversion

```
ORGANISM TEST:
[ ] Opens in browser
[ ] No PHP errors
[ ] Dev tools: HTML structure is valid (DOCTYPE, html, head, body, closing tags)
[ ] Console: No JavaScript errors
[ ] Search form works
[ ] Search produces results
[ ] Assemblies section displays
[ ] Download buttons work
[ ] Tools section appears
[ ] Links navigate correctly
[ ] Access control enforced

ASSEMBLY TEST:
[ ] Same as above, plus:
[ ] Stats display correctly
[ ] Assembly accession shows

GROUPS TEST:
[ ] Same as organism test, plus:
[ ] Group description shows
[ ] Organisms list displays

MULTI_ORGANISM TEST:
[ ] Same as above
[ ] Multiple organisms show correctly
[ ] Search works across all organisms

PARENT TEST:
[ ] Same as above, plus:
[ ] Annotations display correctly
[ ] Complex features work
[ ] (Most critical - test thoroughly)
```

---

## Commits Plan

After completing each phase:

```
Commit 1: "Phase 0: Rename include files for clarity"
  - head.php → head-resources.php
  - header.php → page-setup.php
  - Update all includes in existing pages

Commit 2: "Phase 1: Create clean architecture infrastructure"
  - Create includes/layout.php
  - Create tools/pages/ and admin/pages/ directories
  - Update footer.php with closing tags

Commit 3: "Phase 2.1: Convert organism_display to clean architecture"
  - Create tools/pages/organism.php
  - Create new tools/organism_display.php wrapper
  - Backup old organism_display.php

Commit 4: "Phase 2.2: Convert assembly_display to clean architecture"
  - Similar pattern

[Continue for each page...]

Commit N: "Phase 3: Clean up old backup files"
  - All new pages proven stable
  - Delete old backup files
  - Document completion
```

---

## Success Metrics

After completion:

- [ ] All 5 display pages working with new architecture
- [ ] HTML valid on all pages (dev tools check)
- [ ] 50% code reduction (1000+ lines → ~500)
- [ ] No duplicate includes
- [ ] Clear separation of content from structure
- [ ] One place to manage layout changes
- [ ] All functionality preserved
- [ ] Professional codebase

---

## Expected Time

- Phase 0 (Renaming): 30 minutes
- Phase 1 (Infrastructure): 1 hour
- Phase 2 (Convert 5 pages): 3-4 hours (30-45 min per page)
- Phase 3 (Testing & Cleanup): 1-2 hours

**Total: ~6-8 hours of focused work**

---

## Ready to Start?

Recommend starting with **Phase 0 (File Renaming)** today.

Proceeding with:
1. Rename files
2. Update includes
3. Commit Phase 0
4. Then move to Phase 1

Shall we begin?
