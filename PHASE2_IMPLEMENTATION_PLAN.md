# Phase 2: Display Page Conversion Plan

## Overview

Convert 5 display pages from old structure to new clean architecture using layout.php system.

---

## Pages to Convert

1. **organism_display.php** (294 lines) → FIRST
2. **assembly_display.php** (similar structure)
3. **groups_display.php** (similar structure)
4. **multi_organism_search.php** (similar structure)
5. **parent_display.php** (similar structure)

---

## Conversion Pattern

### Current Structure (294 lines per page)

```
organism_display.php
├── Load tool_init.php
├── Load config
├── Load page-specific includes
├── <!DOCTYPE html>
├── <head> with CSS
├── <body>
├── Navbar include
├── All page content (200+ lines)
└── Footer include + closing tags + scripts
```

### New Structure (Split into 2 files)

```
tools/organism_display.php (30-40 lines - wrapper)
├── Load tool_init.php
├── Load layout.php
├── Prepare data
└── Call render_display_page()

tools/pages/organism.php (50-150 lines - content)
└── ONLY page-specific display HTML
```

---

## Conversion Steps (For Each Page)

### Step 1: Create Content File

File: `tools/pages/organism.php`

Extract only the page display content (150 lines):
- Remove <!DOCTYPE>, <html>, <head>, <body> tags
- Remove navbar and footer
- Remove all script loads
- Keep only page-specific HTML

### Step 2: Create Wrapper

File: `tools/organism_display.php` (NEW - replace old one)

```php
<?php
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$organism_context = setupOrganismDisplayContext(
    $_GET['organism'] ?? '', 
    $organism_data
);

// Prepare data for content file
$data = [
    'organism_name' => $organism_context['name'],
    'organism_info' => $organism_context['info'],
    'config' => $config,
    'page_script' => '/moop/js/organism-display.js'
];

// Render using layout system
echo render_display_page(
    __DIR__ . '/pages/organism.php',
    $data,
    htmlspecialchars($organism_context['info']['common_name'] ?? '') . ' - ' . $config->getString('siteTitle')
);
?>
```

### Step 3: Verify Content File Has Access to Variables

Content file can use:
- `$organism_name`
- `$organism_info`
- `$config`
- And any other extracted variables

### Step 4: Test in Browser

1. Open organism page
2. Verify it displays correctly
3. Press F12 - check HTML structure
4. Verify no console errors

### Step 5: Save Old Version (Backup)

Keep original in case needed:
- `tools/organism_display.php.backup`

---

## Files That Need Changes

### Will Create
- `tools/pages/organism.php`
- `tools/pages/assembly.php`
- `tools/pages/groups.php`
- `tools/pages/multi_organism.php`
- `tools/pages/parent.php`

### Will Modify
- `tools/organism_display.php` (replace)
- `tools/assembly_display.php` (replace)
- `tools/groups_display.php` (replace)
- `tools/multi_organism_search.php` (replace)
- `tools/parent_display.php` (replace)

### Will Keep as Backup
- `.backup` versions of all old files

---

## Checklist for Each Conversion

### Preparation
- [ ] Examine original file structure
- [ ] Identify page content vs framework code
- [ ] Extract page-specific CSS if needed
- [ ] Identify page-specific scripts

### Create Content File
- [ ] Remove all HTML structure tags
- [ ] Keep only display HTML
- [ ] Ensure variables are available
- [ ] Check content length reasonable

### Create Wrapper
- [ ] Load all required includes
- [ ] Prepare all data needed
- [ ] Pass correct title
- [ ] Pass page-specific script

### Test
- [ ] Page loads without errors
- [ ] Content displays correctly
- [ ] F12 shows proper HTML structure
- [ ] No console errors
- [ ] Responsive design works

### Document
- [ ] Add docblock to content file
- [ ] Add docblock to wrapper
- [ ] Note any quirks or special handling

---

## Expected Results

### Code Reduction
- **Before:** 294 lines per page
- **After:** 30-40 line wrapper + 150 line content
- **Savings:** ~50% reduction

### Quality Improvements
- ✅ HTML structure guaranteed correct
- ✅ Scripts load in consistent order
- ✅ Proper opening/closing tags
- ✅ Professional pattern
- ✅ Easier to maintain

### Performance
- No change in page load time
- Cleaner HTML output
- Centralized script management

---

## Implementation Order

1. **organism_display.php** (Start here - most straightforward)
2. **assembly_display.php** (Similar structure)
3. **groups_display.php** (Similar structure)
4. **multi_organism_search.php** (May have different structure)
5. **parent_display.php** (Complex - save for later)

---

## Rollback Plan

If something breaks:
1. Restore `tools/organism_display.php.backup`
2. Delete `tools/pages/organism.php`
3. Test old version works
4. Diagnose issue
5. Retry conversion

---

## Testing Strategy

### After Each Conversion
1. Open page in browser
2. Verify displays correctly
3. Check DevTools HTML structure
4. Check console for errors
5. Test search/filters work
6. Check responsive on mobile

### Before Phase 3
1. All 5 pages working
2. All features functional
3. No console errors
4. All tests pass

---

## Commit Strategy

Each page conversion = 1 commit:
1. `Phase 2: Convert organism_display.php to clean architecture`
2. `Phase 2: Convert assembly_display.php to clean architecture`
3. `Phase 2: Convert groups_display.php to clean architecture`
4. `Phase 2: Convert multi_organism_search.php to clean architecture`
5. `Phase 2: Convert parent_display.php to clean architecture`

Plus summary commit at end:
6. `Phase 2 Complete: All 5 display pages converted - 50% code reduction`

---

## Notes

- All pages use similar structure - conversion pattern repeatable
- layout.php handles all HTML structure consistently
- Content files should be focused display only
- Each wrapper is minimal and clear
- Backwards compatible - old pages still work during transition

