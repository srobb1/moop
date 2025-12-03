# Phase 3: Converting Remaining PHP Pages to Clean Architecture

## Status: IN PROGRESS

### COMPLETED (New Clean Architecture):
- ✅ organism.php (tools/organism.php)
- ✅ assembly.php (tools/assembly.php)
- ✅ groups.php (tools/groups.php)
- ✅ multi_organism.php (tools/multi_organism.php)
- ✅ parent.php (tools/parent.php)
- ✅ index.php - Homepage (refactored in this session)
- ✅ login.php - Login page (refactored in this session)
- ✅ access_denied.php - Access denied page (just converted)

### TO CONVERT - High Priority (Main Display Pages):
- ✅ index.php - Homepage
- ✅ login.php - Login page
- ℹ️  logout.php - Logout handler (NOT a display page - no conversion needed)
- ✅ access_denied.php - Access denied page

### TO CONVERT - Admin Pages:
- [ ] admin/admin.php - Admin dashboard
- [ ] admin/manage_site_config.php - Site configuration
- [ ] admin/filesystem_permissions.php - Permissions management
- [ ] admin/manage_organisms.php - Organism management
- [ ] admin/manage_groups.php - Group management
- [ ] admin/manage_users.php - User management
- [ ] admin/manage_taxonomy_tree.php - Taxonomy management
- [ ] admin/manage_registry.php - Registry management
- [ ] admin/manage_annotations.php - Annotations management
- [ ] admin/error_log.php - Error log viewer
- [ ] admin/convert_groups.php - Group conversion tool
- [ ] admin/debug_permissions.php - Debug permissions
- [ ] admin/admin_access_check.php - (Helper file - check if needs conversion)

### TO CONVERT - Utility/Handler Pages:
- [ ] tools/blast.php - BLAST tool
- [ ] tools/retrieve_sequences.php - Sequence retrieval
- [ ] tools/retrieve_selected_sequences.php - Selected sequence retrieval
- [ ] tools/annotation_search_ajax.php - AJAX handler (may not need full conversion)
- [ ] tools/generate_registry.php - Registry generation
- [ ] tools/generate_js_registry.php - JS registry generation
- [ ] tools/sequences_display.php - Sequence display
- [ ] test_layout.php - Layout test page

### Library/Include Files (Already Modular - No Conversion Needed):
- ✅ lib/* - Function libraries (remain as-is)
- ✅ config/* - Configuration files (remain as-is)
- ✅ includes/* - Core infrastructure (already modular)

### Old Files to Remove (After Testing):
- [ ] tools/organism_display.php
- [ ] tools/assembly_display.php
- [ ] tools/assembly_display1.php
- [ ] tools/groups_display.php
- [ ] tools/multi_organism_search.php
- [ ] tools/parent_display.php
- [ ] tools/display-template.php (if not needed)
- [ ] includes/page-setup.php (if replaced by layout.php)

---

## Conversion Strategy

### Main Display Pages (index.php, login.php, etc.):
Use `render_display_page()` wrapper in main file, move content to tools/pages/

### Admin Pages:
Use `render_display_page()` wrapper, move admin-specific content to admin/pages/

### AJAX/Handler Pages:
Keep minimal - just handle request and return response (no HTML wrapping)

### Test Pages:
Use `render_display_page()` for testing layout system

---

## Testing Checklist Template

For each converted page:
- [ ] Page loads without errors
- [ ] All CSS styling applies correctly
- [ ] Navigation bar appears
- [ ] Footer appears (if applicable)
- [ ] All page-specific functionality works
- [ ] Search/filter functions work (if applicable)
- [ ] Database operations work (if applicable)
- [ ] JavaScript loads and functions properly (if applicable)
- [ ] Responsive design works (test mobile view)
- [ ] Compared with original page for visual consistency

