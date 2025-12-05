# Quick Start Guide - Next Conversion Session

## 📍 Where We Are

- ✅ **3 admin pages converted** (admin, error_log, manage_organisms)
- ✅ **5 tool display pages converted** (organism, assembly, groups, multi_organism, parent)
- ✅ **3 main pages converted** (index, login, access_denied)
- ✅ **Infrastructure complete** (layout.php, admin-utilities.js, etc.)

**Total: 8/13 admin pages done · 42% overall completion**

---

## 🎯 Your Next Mission: Convert 4 Admin Pages (Phase 2)

These are quick and use the established pattern. Each should take 30-45 minutes.

### The 4 Pages to Convert (In Order)

1. **manage_groups.php** ← Start here
2. **manage_users.php** 
3. **manage_annotations.php**
4. **manage_site_config.php**

---

## 🚀 Quick Start (5 Minutes Setup)

```bash
# 1. Open these documentation files
MASTER_REFERENCE.md          # Full guide + templates
PROJECT_DASHBOARD.txt        # Visual overview
CURRENT_STATUS.md            # Detailed status
CONVERSION_READINESS.md      # Checklist

# 2. Reference these working examples
admin/error_log.php                 # Simple admin page (good template)
admin/manage_organisms.php          # Complex admin page (if needed)
admin/pages/error_log.php           # Content file example
admin/pages/manage_organisms.php    # Content file example

# 3. Start converting
Next step: Open admin/manage_groups.php and follow the pattern
```

---

## 📝 The Pattern (Copy-Paste Process)

### Step 1: Extract Content (5 min)
```bash
1. Open admin/manage_groups.php in editor
2. Find where display HTML starts (after all PHP logic)
3. Select from first <div> or <form> to last </div>
4. Copy that section
```

### Step 2: Create Content File (5 min)
```bash
1. Create new file: admin/pages/manage_groups.php
2. Paste the HTML you copied
3. Remove any <html>, <head>, <body>, <script>, <style> tags
4. Remove any <?php function definitions or includes at the top
5. Keep only the display HTML and variable outputs (<?php echo $var; ?>)
```

### Step 3: Create Wrapper (10 min)
```bash
1. Copy admin/error_log.php as template
2. Modify for manage_groups:
   - Change 'error_log' → 'manage_groups' in filenames
   - Change 'Error Log' → 'Manage Groups' in title
   - Adjust data loading logic (keep existing PHP logic from original)
   - Keep page_script if the page needs custom JavaScript
3. Save as admin/manage_groups.php (replacing old file)
```

### Step 4: Test & Commit (10-15 min)
```bash
1. Open browser: localhost/moop/admin/manage_groups.php
2. Check for errors in F12 console
3. Test any forms/buttons
4. Verify navbar and footer appear
5. Commit: git commit -m "Phase 2: Convert manage_groups to clean architecture"
```

---

## 🎯 Copy-Paste Wrapper Template

For **admin/manage_groups.php** (wrapper):

```php
<?php
/**
 * MANAGE GROUPS - Wrapper
 * 
 * Handles admin access verification and renders group management
 * using clean architecture layout system.
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Get config
$siteTitle = $config->getString('siteTitle');
$site = $config->getString('site');

// ========== PASTE YOUR EXISTING LOGIC HERE ==========
// All the PHP logic from the original manage_groups.php
// (AJAX handlers, data loading, etc.)
// Keep everything except the HTML display part
// ===================================================

// Configure display
$display_config = [
    'title' => 'Manage Groups - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_groups.php',
];

// Prepare data for content file
$data = [
    // Add variables needed by the content file here
    // Example: 'groups' => $groups,
    'config' => $config,
    // Optional: Add page-specific script
    // 'page_script' => '/' . $site . '/js/modules/manage-groups.js',
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
```

---

## 📋 Conversion Checklist

For each page:

- [ ] **Step 1:** Extract content HTML from original file
- [ ] **Step 2:** Create `admin/pages/manage_groups.php`
- [ ] **Step 3:** Create wrapper using template
- [ ] **Step 4:** Test in browser (localhost/moop/admin/manage_groups.php)
- [ ] **Step 5:** Verify no console errors (F12)
- [ ] **Step 6:** Commit with message

---

## ⚠️ If Something Breaks

1. **Page won't load?**
   - Check admin/admin_init.php is included
   - Check admin_access_check.php works
   - Look at browser DevTools console (F12)

2. **Missing navbar/footer?**
   - Make sure layout.php is included
   - Verify render_display_page() is called correctly

3. **Content not displaying?**
   - Check admin/pages/manage_groups.php has display HTML
   - Verify $data array has correct variable names
   - Make sure content file removes <html>, <body> tags

4. **Need help?**
   - Look at error_log.php (simple example)
   - Look at manage_organisms.php (complex example)
   - Check MASTER_REFERENCE.md for patterns

---

## 📊 Expected After This Session

✅ All 4 Phase 2 pages converted
✅ Ready to start Phase 3 (6 advanced pages)
✅ Codebase 60-70% converted to clean architecture
✅ Established workflow that others can follow

---

## 📚 Full Documentation

- **MASTER_REFERENCE.md** - Complete architecture guide
- **PROJECT_DASHBOARD.txt** - Visual status overview
- **CURRENT_STATUS.md** - Detailed progress tracking
- **CONVERSION_READINESS.md** - Quick reference checklist

---

## 🎬 Let's Go!

**Ready? Start with:** `admin/manage_groups.php`

**Time estimate:** 30-45 minutes for first page, then faster for the rest

**Goal this session:** Convert all 4 Phase 2 pages (3-4 hours total)

Good luck! The pattern is proven and reliable. 🚀

