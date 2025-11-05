# Bootstrap 5.3.2 Migration Notes

## Changes Made (2025-11-03)

### Upgraded From Bootstrap 4.5.2 → 5.3.2
- **header.php** - Main site header (used by all pages)
- **login.php** - Login page
- **admin/error_log.php** - Error log viewer

### DataTables Upgrade
- **Old:** DataTables 1.10.24 with Bootstrap 4 support
- **New:** DataTables 1.13.4 with Bootstrap 5 support
- Updated CSS and JS CDN links

### jQuery Update
- **Old:** jQuery 3.5.1
- **New:** jQuery 3.6.0

### Dependency Changes
- **Old:** Required Popper.js separately
- **New:** Bootstrap 5.3.2 Bundle includes Popper.js
- Removed separate Popper.js CDN link (no longer needed)

---

## Features to Review

### Modals ⚠️
**Status:** Used in BLAST tools (not yet migrated to MOOP format)

**Bootstrap 4 vs 5 Changes:**
- Class names remain mostly compatible
- Animation/transition timing may be slightly different
- API changes exist but backwards compatible with jQuery

**TODO:** When BLAST tools are migrated to MOOP format, test modals thoroughly
- File: `/data/moop/tools/blast/blast_input.php` (uses modal.html)
- File: `/data/moop/tools/extract/annot_search_input.php` (uses modals)

**Plan:** Review modal functionality when BLAST scripts are reformatted

---

### Dropdowns 🎯
**Status:** Not currently used

**Available in Bootstrap 5.3.2:** Yes, fully featured
- Dropdowns use `data-bs-` attributes (instead of `data-` in v4)
- More accessible with ARIA labels
- Popper.js support for automatic positioning

**Future Use:** When you need dropdowns, they're built-in and ready
- Example: Navigation menus with dropdown items
- Example: Select-style dropdowns for filters
- Example: Contextual menu items in tables

**Migration Note:** Current Bootstrap 5.3.2 includes full dropdown support

---

## Testing Checklist
- [x] Header renders correctly
- [x] Login page displays properly
- [x] Admin pages with Bootstrap 5.3.2 classes work
- [x] DataTables display correctly
- [x] Error log viewer displays correctly
- [ ] Test on mobile (responsive changes)
- [ ] Verify modals work when BLAST tools are migrated
- [ ] Test dropdowns when implemented

---

## Summary
✅ All pages now use **Bootstrap 5.3.2 consistently**  
✅ DataTables upgraded to latest Bootstrap 5 compatible version  
✅ Removed redundant Popper.js dependency  
⚠️ Modals need testing when BLAST tools are migrated  
🎯 Dropdowns ready for future implementation
