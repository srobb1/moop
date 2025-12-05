=== CLEAN ARCHITECTURE CONVERSION - COMPREHENSIVE STATUS ===
Date: December 5, 2025
Project Start: Multiple sessions throughout Phase 0-3

===== PROJECT GOAL =====

Convert legacy monolithic PHP display pages to clean architecture pattern:
  Wrapper (.php) → Loads config, handles logic, prepares data
  Content (pages/*.php) → Pure display HTML only  
  Layout (layout.php) → Consistent structure, navbar, footer

===== COMPLETED ===== 

✅ ADMIN PAGES (7/13 converted):
   1. admin.php - Dashboard wrapper + content file
   2. error_log.php - Error viewer wrapper + content file + About section
   3. manage_organisms.php - Organism manager wrapper + content file + About section
      * All AJAX handlers (fix_permissions, rename_assembly, delete_assembly, save_metadata)
      * Uses organism-management.js for complex UI
      * Admin-utilities.js for shared collapse functionality
   4. manage_groups.php - Group manager wrapper + content file + About section
      * Uses manage-groups.js for assembly selector and filtering
   5. manage_users.php - User manager wrapper + content file + About section
      * Form-based UI for create/edit users with assembly access control
      * Stale assembly audit section
   6. manage_annotations.php - Annotation manager wrapper + content file + About section
      * Sortable annotation types with jQuery UI
      * Edit descriptions, customize synonyms, manage DB types
   7. (Additional refinements to admin pages: permissions checks, UI improvements)

✅ MAIN DISPLAY PAGES (3/3 completed):
   1. index.php - Homepage (wrapper + content in tools/pages/)
   2. login.php - Login page (wrapper + content in tools/pages/)
   3. access_denied.php - Access denied (wrapper + content in tools/pages/)

✅ TOOL DISPLAY PAGES (5/5 completed):
   1. organism.php (tools/organism.php) - Organism display wrapper
   2. assembly.php (tools/assembly.php) - Assembly display wrapper
   3. groups.php (tools/groups.php) - Group display wrapper
   4. multi_organism.php (tools/multi_organism.php) - Multi-organism search
   5. parent.php (tools/parent.php) - Parent display

✅ INFRASTRUCTURE CREATED:
   - layout.php - Main rendering system with render_display_page()
   - display-template.php - Generic wrapper for similar pages
   - admin_init.php - Admin page initialization
   - tool_init.php - Tool page initialization
   - admin-utilities.js - Shared JavaScript utilities
   - organism-management.js - Complex organism management UI

===== ARCHITECTURE PATTERN ESTABLISHED =====

For ADMIN pages:
1. Wrapper: admin/PAGENAME.php
   - Include admin_init.php
   - Load data and configuration
   - Handle AJAX requests (if needed)
   - Call render_display_page() with content_file, data, title

2. Content: admin/pages/PAGENAME.php
   - Pure HTML/display content only
   - No <html>, <head>, <body> tags
   - No CSS/JS loading
   - Uses variables from $data array

3. JavaScript (optional):
   - admin-utilities.js for common collapse/toggle handlers
   - PAGENAME.js for page-specific logic
   - Load via $data['page_script']

4. About Section (standard on admin pages):
   - Use card with collapsible content
   - Gets collapse handler from admin-utilities.js

For DISPLAY pages:
1. Wrapper: tools/PAGENAME.php or main pages
   - Include tool_init.php or direct includes
   - Setup context/configuration
   - Call render_display_page()

2. Content: tools/pages/PAGENAME.php
   - Display content only
   - Access to config, context variables via $data

===== CRITICAL PATTERNS & LEARNINGS =====

1. PAGE_SCRIPT LOADING:
   - Pass via $data['page_script'] in wrapper
   - layout.php extracts and loads script in <head>
   - Script runs AFTER inline_scripts
   - Pattern: '/site_path/js/module-name.js'

2. INLINE SCRIPTS:
   - Pass array via $data['inline_scripts']
   - Injected into <head> BEFORE page_script
   - Use for page-specific variables/configuration
   - Example:
     $data['inline_scripts'] = [
       "const sitePath = '/" . $site . "';",
       "const pageData = " . json_encode($data) . ";"
     ];

3. BOOTSTRAP COLLAPSE CONFLICTS:
   - Remove data-bs-toggle attribute from HTML
   - Implement manual toggle in JavaScript
   - Remove attribute BEFORE adding click listener
   - Use simple CSS: .collapse { display: none; } .collapse.show { display: block; }

4. SHARED UTILITIES PATTERN:
   - admin-utilities.js loaded via inline_scripts
   - Create reusable handlers (collapse, toggle)
   - Each page's module can call utility functions
   - Reduces duplication across multiple pages

5. DATA AVAILABILITY:
   - layout.php calls extract($data) before rendering content
   - All $data array keys become local variables
   - Content files can access: $config, $site, $organisms, custom vars, etc.

===== REMAINING PAGES (10/13 admin pages) =====

PHASE 2 - CRUD Operations (4 pages):
   - manage_groups.php (Ready - established pattern)
   - manage_users.php (Ready - established pattern)
   - manage_annotations.php (Ready - established pattern)
   - manage_site_config.php (Ready - established pattern)

PHASE 3 - Advanced Management (6 pages):
   - manage_registry.php
   - manage_taxonomy_tree.php
   - filesystem_permissions.php
   - convert_groups.php
   - debug_permissions.php
   - admin_access_check.php (helper - check if conversion needed)

OPTIONAL REGISTRIES (Can integrate into clean architecture):
   - tools/registry.php - Function registry display
   - tools/js_registry.php - JavaScript registry display
   - (See REGISTRY_STYLING_PLAN.md for details)

===== UTILITY/HANDLER PAGES (May need conversion) =====

AJAX Handlers (minimal conversion):
   - tools/annotation_search_ajax.php (return JSON only)
   - tools/convert_groups.php (return JSON or minimal HTML)

Tools (consider conversion):
   - tools/blast.php (complex form/interaction)
   - tools/retrieve_sequences.php (display sequences)
   - tools/retrieve_selected_sequences.php (display sequences)
   - tools/sequences_display.php (display sequences)

Test Pages:
   - test_layout.php (use render_display_page for testing)

===== GIT STRATEGY =====

Each major component = separate commit:
1. Per-page conversion commits
2. Infrastructure updates as needed
3. Testing/validation commits
4. Batch cleanup commits at phase end

Current session approach:
- Focus on one category per work block
- Test each page individually
- Commit after each successful page
- Document patterns in this file

===== TESTING CHECKLIST ===== 

For each converted page:
✓ Page loads without errors
✓ HTML structure correct (view source)
✓ Navbar appears at top
✓ Footer appears at bottom
✓ CSS styling applied correctly
✓ Page-specific functionality works
✓ Search/filters work (if applicable)
✓ Database operations work (if applicable)
✓ JavaScript loads and runs (check console)
✓ Responsive design works (mobile view)
✓ No console errors or warnings
✓ Compared with original page visually

===== NEXT SESSION RECOMMENDATIONS =====

Quick Wins (Fast conversions using established pattern):
1. manage_groups.php - CRUD operations, simple form
2. manage_users.php - Similar to manage_groups
3. manage_annotations.php - Similar CRUD pattern
4. manage_site_config.php - Similar CRUD pattern

These should be fast (~30 min each) as pattern is proven.

Complex Pages (Estimate ~1-2 hours each):
- manage_registry.php - May need registry-specific handling
- manage_taxonomy_tree.php - Complex data structure
- filesystem_permissions.php - Complex permission display

Use manage_organisms.php and error_log.php as templates - the pattern
is now proven and reliable across different page types.

===== FILE ORGANIZATION =====

/admin/
├── admin.php ......................... Wrapper ✅
├── error_log.php ..................... Wrapper ✅
├── manage_organisms.php .............. Wrapper + AJAX handlers ✅
├── manage_groups.php ................. Wrapper ✅
├── manage_users.php .................. Wrapper ✅
├── manage_annotations.php ............ Wrapper ✅
├── manage_site_config.php ............ (TO CONVERT)
├── manage_registry.php ............... (PHASE 3)
├── manage_taxonomy_tree.php .......... (PHASE 3)
├── manage_database_config.php ........ (PHASE 3)
├── pages/
│   ├── admin.php ..................... Content ✅
│   ├── error_log.php ................. Content ✅
│   ├── manage_organisms.php .......... Content ✅
│   ├── manage_groups.php ............. Content ✅
│   ├── manage_users.php .............. Content ✅
│   ├── manage_annotations.php ........ Content ✅
│   └── manage_site_config.php ........ (TO CREATE)

/tools/
├── organism.php ...................... Wrapper
├── assembly.php ...................... Wrapper
├── groups.php ........................ Wrapper
├── multi_organism.php ................ Wrapper
├── parent.php ........................ Wrapper
├── display-template.php .............. Generic template
├── pages/
│   ├── organism.php .................. Content
│   ├── assembly.php .................. Content
│   ├── groups.php .................... Content
│   ├── multi_organism.php ............ Content
│   └── parent.php .................... Content

/includes/
├── layout.php ........................ Main rendering system

/js/
├── admin-utilities.js ................ Shared admin utilities
├── modules/
│   ├── organism-management.js ........ Organism-specific UI
│   └── [other page modules]

===== KEY DOCUMENTATION ===== 

See also:
- PHASE3_CONVERSION_CHECKLIST.md - Admin page conversion status
- DISPLAY_PAGES_SIMPLIFICATION_STRATEGY.md - Display page architecture
- REGISTRY_STYLING_PLAN.md - Registry integration plan
- PHASE2_IMPLEMENTATION_PLAN.md - Original phase 2 plan
- CLEAN_ARCHITECTURE_PLAN.md - Overall architecture design

===== STATUS SUMMARY =====

Overall Completion: 8/13 main pages converted + infrastructure

Main Display Pages: 100% (3/3)
Tool Display Pages: 100% (5/5)
Admin Pages: 23% (3/13)
Infrastructure: Complete

Next Priority: Convert remaining admin pages using established pattern
Estimated Time: 4-6 hours for Phase 2 (4 CRUD pages)
             2-3 hours for Phase 3 (6 advanced pages)

