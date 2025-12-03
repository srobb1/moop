# Display Pages Review - Heart of the System

These 4 pages are critical to the user experience. They follow similar patterns but have important differences.

---

## Files Under Review

1. **organism_display.php** (294 lines) - View single organism with search
2. **assembly_display.php** (274 lines) - View single assembly with search
3. **groups_display.php** (265 lines) - View group with organisms list
4. **multi_organism_search.php** (187 lines) - Search across multiple organisms

---

## Common Structure

All 4 pages follow the same general pattern:

```
1. Load config + tool_init.php
2. Get and validate URL parameters  
3. Load data from files/database
4. Check user access
5. Output HTML (DOCTYPE, head, navbar, content, footer)
6. Include JavaScript libraries and page-specific JS
```

This is **MOSTLY GOOD** but has issues.

---

## Issues Found

### 🔴 CRITICAL ISSUE #1: HTML Structure Inconsistency

**Problem:** Files don't close HTML properly

```php
// organism_display.php (line 293)
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
// ← Ends here, no </body></html>

// But page starts with:
<!DOCTYPE html>
<html lang="en">
<head>...</head>
<body class="bg-light">
// ← Opening tags present
```

**Result:** 
- HTML is technically incomplete
- footer.php only has `</footer>`, not closing tags
- Browser corrects it automatically (not ideal)

---

### 🔴 CRITICAL ISSUE #2: Script Includes Before Header

**Problem:** All scripts included BEFORE footer

```php
// Lines 254-287: jQuery, DataTables, Bootstrap scripts
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
... many more ...
<!-- Page-specific logic -->
<script src="/<?= $site ?>/js/organism-display.js"></script>

</body>
</html>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
```

**Why it's wrong:**
- Scripts should be in `<head>` or at end of `<body>`
- Currently they're between `</body>` and footer include
- footer.php doesn't have closing `</html>`
- Creates invalid HTML structure

**Result:**
- Script execution order unclear
- HTML validation fails
- Page structure fragile

---

### 🟡 ISSUE #3: Duplicate Script Includes

**Problem:** jQuery and Bootstrap included locally, but also in head.php

```php
// In head.php (lines included):
- Bootstrap CSS/JS (via CDN)
- jQuery (if included)

// In organism_display.php (lines 254-287):
- jQuery again
- Bootstrap again  
- DataTables (3 files)
- Custom scripts
```

**Result:**
- jQuery loaded twice (wasteful)
- Bootstrap loaded twice (potentially conflicts)
- Hard to maintain - where should we add scripts?

---

### 🟡 ISSUE #4: Mix of Concerns - Too Much in One File

**Problem:** These files mix:
- Data loading logic (queries, file parsing)
- Access control checks
- Display/template rendering
- JavaScript configuration
- Inline styles

**Lines breakdown:**
```
organism_display.php (294 lines total):
- 12 lines: PHP logic (data loading)
- 2 lines: access checks
- 280 lines: HTML template + script includes
```

**Result:**
- Hard to debug data issues vs display issues
- Testing is difficult
- Reusing logic is difficult

---

### 🟡 ISSUE #5: Manual HTML Output

**Problem:** All HTML written manually in each file

```php
// Every file does this:
<!DOCTYPE html>
<html lang="en">
<head>
  <title>...</title>
  <?php include_once __DIR__ . '/../includes/head.php'; ?>
  <link rel="stylesheet" href="...">
  <link rel="stylesheet" href="...">
  ...
</head>
<body class="bg-light">
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="container mt-5">
  <!-- Page-specific content -->
</div>
... all scripts ...
```

**Result:**
- Lots of copy-paste boilerplate
- If you change structure, must edit 4+ files
- Easy to miss one file, creating inconsistency

---

### 🟡 ISSUE #6: No Separation - Content vs Structure

**Problem:** Page content mixed with HTML structure

```php
// What you see in organism_display.php:
- Line 15: <html> tag
- Line 43: Search form HTML
- Line 95: Organism info card
- Line 287: JavaScript
- Line 290: </html> comment
- Line 293: Footer include (after HTML closed)
```

**Better approach:**
- File contains ONLY content
- Wrapper handles structure
- Clean separation

---

### 🟡 ISSUE #7: Inconsistent Pattern - assembly_display.php

**Problem:** assembly_display.php has a bug

```php
// Line 106: References undefined variable
$context = createToolContext('assembly', [
    'organism' => $organism_name,
    'assembly' => $assembly_accession,  // ← NOT DEFINED
    'display_name' => $assembly_info['genome_name']
]);
```

**Result:**
- `$assembly_accession` used but never set
- May cause PHP notice/warning

---

### 🟡 ISSUE #8: Inconsistent Access Control

**Problem:** Different patterns across files

```php
// groups_display.php (line 38):
if (!is_public_group($group_name)) {
    requireAccess('Collaborator', $group_name);
}

// multi_organism_search.php (lines 20-28):
foreach ($organisms as $organism) {
    $is_public = is_public_organism($organism);
    $has_organism_access = has_access('Collaborator', $organism);
    if (!$has_organism_access && !$is_public) {
        header("Location: /$site/access_denied.php");
        exit;
    }
}

// assembly_display.php:
// NO explicit access check! Relies on validation functions
```

**Result:**
- Hard to understand permission model
- Inconsistent error handling
- Some pages allow public access implicitly, others explicit

---

## What Works Well ✅

1. **Similar Architecture** - All 4 follow same general pattern
2. **Good Search UI** - Search interface is consistent and well-designed
3. **Tool Integration** - Good use of tool context system
4. **Data Loading** - Proper use of helper functions
5. **Image Handling** - Fallback images work well

---

## Recommendations

### SHORT TERM (Fixes for now)

1. **Fix footer.php** - Add `</body></html>` closing tags
2. **Fix assembly_display.php bug** - Define `$assembly_accession`
3. **Move scripts to head.php or before footer** - Remove duplicate includes
4. **Standardize access control** - Use consistent pattern across all pages
5. **Remove duplicate jQuery/Bootstrap** - Keep in head.php only

### MEDIUM TERM (Refactor for maintainability)

1. **Separate content from structure** - Use new layout pattern
2. **Extract data loading** - Put in separate functions/classes
3. **Create display templates** - Move HTML to separate files
4. **Consolidate script includes** - One place to manage JS

### LONG TERM (Architecture upgrade)

1. **Front controller pattern** - Route all display pages through one entry point
2. **Template inheritance** - Create base template, pages extend it
3. **Component-based structure** - Search, results, header as separate components
4. **MVC separation** - Model (data), View (display), Controller (logic)

---

## Priority Issues to Fix NOW

### 1. Fix Footer HTML (5 minutes)
```php
// Add to end of includes/footer.php:
</body>
</html>
```

### 2. Fix assembly_display.php (2 minutes)
```php
// Add after line 20:
$assembly_accession = $assembly_info['genome_accession'] ?? $assembly_param;
```

### 3. Remove Duplicate Scripts (10 minutes)
Move all script includes to head.php or before footer.php include. Keep only ONE jQuery, ONE Bootstrap.

### 4. Standardize Access Control (15 minutes)
Use same pattern everywhere:
```php
// Standard pattern for all display pages:
if (!canAccessPage($context)) {
    header("Location: /$site/access_denied.php");
    exit;
}
```

---

## Should We Do Full Refactor to Clean Architecture?

**YES, but phases:**

### Phase 1: Quick Fixes (30 min today)
- Fix closing tags
- Fix bugs
- Remove duplicate scripts
- Standardize access control

### Phase 2: Prepare Infrastructure (1 day soon)
- Create layout.php wrapper
- Create pages/ directories
- Document new pattern

### Phase 3: Refactor Display Pages (2-3 days)
- Move content to pages/
- Use layout system
- Test each page thoroughly

### Result
- Pages go from 294 lines → 50 lines (just content)
- Bug-free structure
- Easy to change layout once, affects all pages
- Professional codebase

---

## File-by-File Assessment

| File | Lines | Status | Priority Fix |
|------|-------|--------|--------------|
| organism_display.php | 294 | ⚠️ Works but messy | Remove duplicate scripts, fix footer |
| assembly_display.php | 274 | 🔴 Has bug | Fix $assembly_accession undefined |
| groups_display.php | 265 | ⚠️ Works but inconsistent | Standardize access control |
| multi_organism_search.php | 187 | ⚠️ Works but messy | Remove duplicate scripts, fix footer |

---

## Testing Checklist After Fixes

For each page, verify:

```
[ ] Opens without errors
[ ] Dev tools shows valid HTML (DOCTYPE, html, head, body, proper closing)
[ ] No JavaScript console errors
[ ] No duplicate script warnings
[ ] Search form works
[ ] Search produces results (if applicable)
[ ] All links work
[ ] All tools appear
[ ] Downloads work (if applicable)
[ ] Access control works (public vs restricted)
```

---

## Conclusion

**The display pages are the heart of your system.** They work, but need:

1. **Immediate fixes** (30 min): Close HTML, fix bugs, dedupe scripts
2. **Soon refactor** (2-3 days): Split into content + layout pattern
3. **Result**: Professional, maintainable, bug-free code that scales

Ready to proceed with clean architecture implementation?

