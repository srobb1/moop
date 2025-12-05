=== ADMIN PAGE CONVERSION READINESS ANALYSIS ===

COMPLETED (3/13):
✅ admin.php (converted)
   - Wrapper: admin/admin.php
   - Content: admin/pages/admin.php
   - Pattern: Simple info display

✅ error_log.php (converted)
   - Wrapper: admin/error_log.php
   - Content: admin/pages/error_log.php
   - Pattern: Display with filters + About section
   - Special: Collapse handlers via admin-utilities.js

✅ manage_organisms.php (converted)
   - Wrapper: admin/manage_organisms.php (206 lines)
   - Content: admin/pages/manage_organisms.php (79KB)
   - Pattern: Complex CRUD with AJAX handlers
   - Special: organism-management.js for complex UI

---

READY TO CONVERT - PHASE 2 (4 pages, should be quick):

1. manage_groups.php
   Estimated: 30-45 minutes
   Reason: Similar to manage_organisms but simpler
   Pattern: CRUD form + table display
   Next Steps:
   - Create admin/pages/manage_groups.php (extract display HTML)
   - Simplify admin/manage_groups.php wrapper
   - Add page_script if needs custom JS

2. manage_users.php
   Estimated: 30-45 minutes
   Reason: Similar to manage_groups
   Pattern: CRUD form + table display
   Dependencies: Similar to manage_groups
   Next Steps:
   - Create admin/pages/manage_users.php
   - Simplify admin/manage_users.php wrapper
   - Add page_script if needs custom JS

3. manage_annotations.php
   Estimated: 30-45 minutes
   Reason: Similar to manage_groups
   Pattern: CRUD form + table display
   Next Steps:
   - Create admin/pages/manage_annotations.php
   - Simplify admin/manage_annotations.php wrapper
   - Add page_script if needs custom JS

4. manage_site_config.php
   Estimated: 45-60 minutes
   Reason: Config display + editing
   Pattern: Form with settings
   Special: Might need validation logic in wrapper
   Next Steps:
   - Create admin/pages/manage_site_config.php
   - Check for validation logic (may belong in wrapper)
   - Add page_script if needs custom JS

---

PHASE 3 PAGES (More complex, 6 pages):

1. manage_registry.php
   Estimated: 1-2 hours
   Reason: Registry display/management
   Special: May integrate with registry generation tools
   Dependencies: Check REGISTRY_STYLING_PLAN.md

2. manage_taxonomy_tree.php
   Estimated: 1-2 hours
   Reason: Complex tree data structure
   Special: May need custom JS for tree interaction
   
3. filesystem_permissions.php
   Estimated: 1-2 hours
   Reason: Permission display/management
   Special: Complex nested data structure

4. convert_groups.php
   Estimated: 1 hour
   Reason: Batch operation/conversion tool
   Pattern: Form + operation results

5. debug_permissions.php
   Estimated: 45 minutes
   Reason: Debug display tool
   Pattern: Diagnostic output

6. admin_access_check.php
   Status: CHECK IF NEEDS CONVERSION
   Question: Is this a display page or just a utility?
   Action: Inspect file before deciding

---

CONVERSION CHECKLIST FOR PHASE 2 PAGES

For each page (manage_groups, manage_users, manage_annotations, manage_site_config):

1. INSPECT CURRENT FILE:
   - [ ] Open admin/manage_XXXX.php
   - [ ] Note total line count
   - [ ] Identify HTML structure section
   - [ ] Identify configuration/logic section
   - [ ] Check for AJAX handlers

2. IDENTIFY CONTENT:
   - [ ] Find opening display HTML (usually after logic)
   - [ ] Find closing display HTML (before footer)
   - [ ] Note any inline CSS or scripts
   - [ ] Note variables used in display

3. CREATE CONTENT FILE:
   - [ ] Create admin/pages/manage_XXXX.php
   - [ ] Copy only display HTML section
   - [ ] Remove all PHP logic (except variable echoes)
   - [ ] Remove HTML structure tags
   - [ ] Remove script/style tags

4. CREATE WRAPPER:
   - [ ] Copy pattern from admin/error_log.php or admin/manage_organisms.php
   - [ ] Keep all logic/AJAX handlers in wrapper
   - [ ] Prepare $data array with needed variables
   - [ ] Add page_script if needs custom JS
   - [ ] Call render_display_page()

5. TEST:
   - [ ] Load page in browser
   - [ ] Verify HTML structure correct
   - [ ] Verify navbar/footer appear
   - [ ] Verify CSS styling applied
   - [ ] Test any forms/functionality
   - [ ] Check browser console for errors
   - [ ] Verify responsive design

6. COMMIT:
   - [ ] Make commit with message:
         "Phase 2: Convert manage_XXXX to clean architecture"

---

ESTIMATED EFFORT:

Phase 2 (4 pages):
  manage_groups.php        .......... 30-45 min
  manage_users.php         .......... 30-45 min
  manage_annotations.php   .......... 30-45 min
  manage_site_config.php   .......... 45-60 min
  Testing & refinement     .......... 30-45 min
  ────────────────────────────────────────────
  TOTAL:                              ~3.5-4 hours

Phase 3 (6 pages):
  Complex pages            .......... 1-2 hours each
  TOTAL:                              ~8-12 hours

---

TEMPLATE FOR PHASE 2 CONVERSIONS:

WRAPPER TEMPLATE (admin/manage_XXXX.php):
────────────────────────────────────────────
<?php
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Load page-specific config
$siteTitle = $config->getString('siteTitle');

// Handle any AJAX requests (if applicable)
// handleAdminAjax(function($action) { ... });

// Load data needed for display
// $data_items = getData();

// Configure display
$display_config = [
    'title' => 'Manage XXXX - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_XXXX.php',
];

// Prepare data for content file
$data = [
    'items' => $items,
    'config' => $config,
    // Add other needed variables
    'page_script' => '/' . $config->getString('site') . '/js/manage-XXXX.js',
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
────────────────────────────────────────────

CONTENT TEMPLATE (admin/pages/manage_XXXX.php):
────────────────────────────────────────────
<!-- Pure display HTML only - no <html>, <head>, <body> tags -->
<!-- No CSS/JS loading - layout.php handles it -->

<div class="container mt-4">
    <h1><?php echo htmlspecialchars($title); ?></h1>
    
    <!-- Page content here -->
    
</div>
────────────────────────────────────────────

