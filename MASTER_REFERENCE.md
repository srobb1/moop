# Clean Architecture Conversion - Master Reference Guide

## 🎯 Project Status Overview

**Current Completion: 8/13 main pages + full infrastructure**

| Category | Status | Count |
|----------|--------|-------|
| Main Display Pages | ✅ 100% | 3/3 |
| Tool Display Pages | ✅ 100% | 5/5 |
| Admin Pages | 🟨 23% | 3/13 |
| Infrastructure | ✅ Complete | - |

---

## 📋 Quick Navigation

### Pages by Status

**✅ ALREADY CONVERTED (Ready to Reference)**
- `admin/admin.php` → wrapper + `admin/pages/admin.php`
- `admin/error_log.php` → wrapper + `admin/pages/error_log.php`  
- `admin/manage_organisms.php` → wrapper + `admin/pages/manage_organisms.php`
- `tools/organism.php`, `assembly.php`, `groups.php`, `multi_organism.php`, `parent.php`
- `index.php`, `login.php`, `access_denied.php`

**🔄 READY TO CONVERT - Next 4 pages (Phase 2)**
1. `admin/manage_groups.php` - Similar to manage_organisms (simpler)
2. `admin/manage_users.php` - Similar to manage_groups
3. `admin/manage_annotations.php` - Similar to manage_groups
4. `admin/manage_site_config.php` - Configuration management

**⏳ PHASE 3 (6 advanced pages)**
- manage_registry.php, manage_taxonomy_tree.php, filesystem_permissions.php
- convert_groups.php, debug_permissions.php, admin_access_check.php

---

## 🏗️ The Architecture Pattern

### Three-Layer System

```
LAYER 1: WRAPPER (main file)
├─ Location: admin/PAGENAME.php or tools/PAGENAME.php
├─ Responsibility: Load config, handle logic, prepare data
├─ Size: 20-40 lines (or more if complex logic)
└─ Pattern: Include init → load data → call render_display_page()

LAYER 2: CONTENT (display file)
├─ Location: admin/pages/PAGENAME.php or tools/pages/PAGENAME.php
├─ Responsibility: Pure display HTML only
├─ Size: 50-200 lines
└─ Note: No HTML structure, no CSS/JS loading

LAYER 3: LAYOUT (infrastructure)
├─ Location: includes/layout.php
├─ Responsibility: Consistent HTML structure, navbar, footer
├─ Function: render_display_page($content_file, $data, $title)
└─ Features: Script injection, CSS loading, responsive layout
```

### Data Flow

```
WRAPPER prepares data:
  $data = [
    'organisms' => $organisms,
    'config' => $config,
    'page_script' => '/path/to/module.js',
    'inline_scripts' => ["const x = 5;"]
  ]
      ↓
layout.php receives data:
  echo render_display_page(
    'admin/pages/manage_organisms.php',
    $data,
    'Page Title'
  )
      ↓
layout.php processes:
  1. Extract $data → makes keys into variables
  2. Load CSS for page
  3. Include navbar
  4. Inject inline_scripts
  5. Load page_script module
  6. Include content file (now has access to all variables)
  7. Include footer
      ↓
CONTENT FILE accesses variables:
  <?php echo $organisms['name']; ?>
```

---

## 🔧 Implementation Checklist

### For Each Admin Page Conversion

#### Step 1: Inspect Current File
- [ ] `admin/manage_groups.php` - Identify structure
  - Where does logic end and display begin?
  - Are there AJAX handlers? (keep in wrapper)
  - Are there forms? (keep in wrapper logic)
  - What variables are used in display?

#### Step 2: Create Content File
- [ ] Create `admin/pages/manage_groups.php`
- [ ] Copy ONLY the display HTML section
- [ ] Remove DOCTYPE, html, head, body tags
- [ ] Remove any script/style tags
- [ ] Keep PHP variable outputs (echo statements)
- [ ] Keep form elements that get auto-filled

#### Step 3: Create/Update Wrapper
- [ ] Open/create `admin/manage_groups.php`
- [ ] Include admin_init.php
- [ ] Include layout.php
- [ ] Keep all business logic
- [ ] Prepare $data array with variables
- [ ] Call render_display_page()

#### Step 4: Test Page
- [ ] Load in browser: `localhost/moop/admin/manage_groups.php`
- [ ] Verify layout looks correct
- [ ] Verify navbar/footer appear
- [ ] Test forms/buttons work
- [ ] Check browser console (F12) for errors
- [ ] Test responsive design (resize window)

#### Step 5: Commit
```bash
git add admin/manage_groups.php admin/pages/manage_groups.php
git commit -m "Phase 2: Convert manage_groups to clean architecture"
```

---

## 📝 Copy-Paste Templates

### Wrapper Template (admin/manage_groups.php)

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

// Handle AJAX requests (if applicable)
// handleAdminAjax(function($action) {
//     if ($action === 'save_group' && isset($_POST['group_name'])) {
//         // Handle saving group
//         return true;
//     }
//     return false;
// });

// Load data for display
// $groups = getGroupsFromDatabase();

// Configure display
$display_config = [
    'title' => 'Manage Groups - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_groups.php',
];

// Prepare data for content file
$data = [
    // 'groups' => $groups,
    'config' => $config,
    // Add page_script if page needs custom JavaScript
    // 'page_script' => '/' . $site . '/js/modules/manage-groups.js',
    // Add inline scripts if needed for page-specific variables
    // 'inline_scripts' => [
    //     "const sitePath = '/" . $site . "';",
    // ]
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
```

### Content Template (admin/pages/manage_groups.php)

```php
<!-- MANAGE GROUPS - Content -->
<!-- Pure display HTML - no structure tags needed -->

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1>Manage Groups</h1>
            
            <!-- Add form section here if needed -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Create New Group</h5>
                </div>
                <div class="card-body">
                    <!-- Form elements -->
                </div>
            </div>
            
            <!-- Display groups in table -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through groups here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
```

---

## 🎯 Key Patterns to Remember

### 1. Page Script Loading
```php
// In wrapper (admin/manage_groups.php):
$data = [
    'page_script' => '/' . $config->getString('site') . '/js/modules/manage-groups.js',
];

// In layout.php - automatically injected into <head>:
<script src="<?php echo $page_script; ?>"></script>

// In manage-groups.js - can now access any variables:
console.log(sitePath); // Available from inline_scripts
```

### 2. Inline Scripts (Variables for JavaScript)
```php
// In wrapper:
$data = [
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        "const groupId = '" . addslashes($_GET['id'] ?? '') . "';",
    ]
];

// Injected into <head> BEFORE page_script loads
// JavaScript module can access these variables
```

### 3. Data Availability in Content File
```php
// In wrapper:
$data = [
    'groups' => $groups_array,
    'config' => $config,
    'user_role' => $_SESSION['role'] ?? 'guest',
];

// In content file (admin/pages/manage_groups.php):
<?php foreach ($groups as $group): ?>
    <tr>
        <td><?php echo htmlspecialchars($group['name']); ?></td>
        <td><?php echo $config->getString('siteTitle'); ?></td>
    </tr>
<?php endforeach; ?>
```

### 4. Shared Utilities (admin-utilities.js)
```php
// In wrapper, load shared utilities:
$data = [
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        "// Load shared admin utilities
        const script = document.createElement('script');
        script.src = sitePath + '/js/admin-utilities.js';
        document.head.appendChild(script);"
    ]
];

// In content file, use the utilities:
<button class="btn btn-secondary" data-toggle="collapse" data-target="#about">
    About
</button>
<div id="about" class="collapse">
    <!-- Content -->
</div>

// admin-utilities.js automatically sets up the collapse handler
```

---

## 📊 Expected Results After Each Conversion

### Code Reduction
- **Before:** 300-500 line monolithic file
- **After:** 30-40 line wrapper + 80-150 line content
- **Reduction:** 40-60%

### Quality Improvements
- ✅ HTML structure guaranteed correct
- ✅ Consistent with other pages
- ✅ Scripts load in proper order
- ✅ Easier to maintain and debug
- ✅ Reusable patterns

---

## �� Next Steps This Session

### Priority Order (What to work on next)

1. **manage_groups.php** (Estimated: 30-45 min)
   - Simplest of Phase 2 pages
   - Good starting point
   - Establishes workflow

2. **manage_users.php** (Estimated: 30-45 min)
   - Similar to manage_groups
   - Should go faster

3. **manage_annotations.php** (Estimated: 30-45 min)
   - Similar pattern
   - Continue momentum

4. **manage_site_config.php** (Estimated: 45-60 min)
   - May have validation logic
   - Last of Phase 2

### Testing Workflow
```bash
# After creating files:
1. View in browser
2. Press F12 (DevTools)
3. Check Console for errors
4. Resize window for responsive design
5. Test any forms/buttons
6. Compare with original if needed
```

---

## 📚 Reference Files

**Key Infrastructure:**
- `includes/layout.php` - Main rendering system (already complete)
- `admin/admin_init.php` - Admin initialization
- `js/admin-utilities.js` - Shared collapse/toggle handlers

**Reference Implementations:**
- `admin/manage_organisms.php` - Complex example with AJAX
- `admin/error_log.php` - Simple example with About section
- `tools/organism.php` - Display page example

**Documentation:**
- See PHASE3_CONVERSION_CHECKLIST.md for full status
- See DISPLAY_PAGES_SIMPLIFICATION_STRATEGY.md for architecture
- See SESSION_SUMMARY.txt for session notes

---

## 💡 Tips & Tricks

1. **Always backup original** - GitHub provides this, so don't worry
2. **Test in browser first** - View page before and after conversion
3. **Check console for errors** - F12 → Console tab is your friend
4. **Copy from working examples** - manage_organisms.php and error_log.php
5. **Use same variable names** - Makes content files cleaner
6. **Comment confusing parts only** - Self-documenting code is best
7. **Commit frequently** - One page = one commit

---

Generated: December 5, 2025
Based on: SESSION_SUMMARY.txt + Implementation docs + Current codebase analysis

