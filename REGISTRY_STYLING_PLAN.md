# Function Registry Styling Plan

## Current State Analysis

### Registry Generation
- **PHP Registry:** `tools/generate_registry.php` → `/docs/function_registry.html` (562 KB)
- **JS Registry:** `tools/generate_js_registry.php` → `/docs/js_function_registry.html` (95 KB)
- Both are CLI-run scripts (executed manually or via cron)
- Generate standalone HTML files with inline CSS and JavaScript

### Current Styling (Not Integrated)
- Uses blue/gray color scheme (#3498db, #34495e)
- Generic bootstrap-like styling
- Not matching site branding (SIMRbase)
- Doesn't use site's CSS framework or colors
- Standalone pages with embedded styles

### Issues
1. **Color Mismatch:** Current registries use generic blue (#3498db) - doesn't match site theme
2. **No Site Integration:** Registries are not part of clean architecture pattern
3. **No Navbar/Footer:** Registries don't have site navigation
4. **Not Responsive:** Limited mobile responsiveness
5. **Inline CSS:** All CSS embedded, not linked to site stylesheets
6. **No Consistent Branding:** Different styling than main site pages

---

## Proposed Solution: Integrate Registries into Clean Architecture

### Option A: Convert to Display Pages (Recommended)
Convert registry generators to use clean architecture:

```
Registry Generation (background):
  tools/generate_registry.php (CLI script)
  └─ Output: /docs/function_registry_data.json
  
Display Pages (clean architecture):
  tools/registry.php (wrapper)
  └─ tools/pages/registry.php (content)
  
  tools/js_registry.php (wrapper)
  └─ tools/pages/js_registry.php (content)
```

**Advantages:**
- Uses site navbar/footer (consistent branding)
- Integrates with layout.php system
- Can use site CSS and colors
- Appears in site navigation
- Professional, integrated appearance
- Data separation from presentation

**Changes Needed:**
1. Modify generators to output JSON data files
2. Create display wrappers (registry.php, js_registry.php)
3. Create content files with display logic
4. Use site color scheme and styling
5. Add to navbar/site navigation

### Option B: Custom Styling (Simpler)
Keep current structure but update CSS to match site:

```
Current structure stays:
  tools/generate_registry.php → /docs/function_registry.html
  tools/generate_js_registry.php → /docs/js_function_registry.html
```

**Changes Needed:**
1. Extract current inline CSS
2. Update colors to match site theme
3. Use site fonts
4. Improve branding/header
5. Add site logo/link back to home

**Disadvantages:**
- Still standalone (not integrated)
- No navbar/footer
- Duplicate styling logic

---

## Site Colors & Theme Reference

### SIMRbase Branding
- Site Title: "SIMRbase"
- Primary Color: Blue (#3498db used currently, but may need refinement)
- Navbar: Dark (#34495e or similar)
- Footer: Dark
- Accents: Various (greens, oranges for different elements)
- Font: Bootstrap default stack

### Current Registry Colors
- Header: #34495e (dark gray-blue)
- Primary Links: #2980b9 (blue)
- Badges: #3498db (bright blue)
- Code Background: #f8f8f8 (light gray)
- Warning/Unused: #cc0000 (red)

---

## Detailed Implementation Plan

### Phase 1: Data Separation
**Goal:** Separate data generation from presentation

**Files to Modify:**
1. `tools/generate_registry.php`
   - Add JSON output alongside HTML
   - Save to: `/docs/function_registry_data.json`
   - Keep current HTML generation for now

2. `tools/generate_js_registry.php`
   - Add JSON output alongside HTML
   - Save to: `/docs/js_function_registry_data.json`
   - Keep current HTML generation for now

**Changes:**
- Add function: `saveRegistryAsJson($registry, $filepath)`
- Output format: Array of files/functions with metadata
- Include timestamps, stats, unused functions list

### Phase 2: Create Display Pages (if Option A chosen)
**Files to Create:**
1. `tools/registry.php` (PHP Registry Display Wrapper)
   - Load registry data (JSON or regenerate)
   - Pass to display-template.php
   - ~40-50 lines

2. `tools/pages/registry.php` (PHP Registry Content)
   - Display registry HTML
   - Use site CSS/colors
   - Search/filter functionality
   - ~200-300 lines

3. `tools/js_registry.php` (JS Registry Display Wrapper)
   - Load JS registry data
   - Pass to display-template.php
   - ~40-50 lines

4. `tools/pages/js_registry.php` (JS Registry Content)
   - Display JS registry HTML
   - Use site CSS/colors
   - Search/filter functionality
   - ~200-300 lines

### Phase 3: Styling Updates
**Files to Modify:**

1. Update color scheme:
   ```
   Current → New
   #3498db → [Primary site color]
   #34495e → [Site navbar color]
   #2980b9 → [Site link color]
   #cc0000 → [Site warning color]
   ```

2. Add to CSS or create `/moop/css/registry.css`:
   - Use site variables/colors
   - Match responsive design
   - Match site typography
   - Link back styling to site navigation

3. Consider using Bootstrap 5 classes (already in site)

### Phase 4: Navigation Integration
**Changes:**
1. Add registry links to navbar/footer
2. Or create admin-only registry section
3. Update site navigation menu

---

## Timeline & Effort Estimate

### Option A (Full Integration) - Recommended
- **Phase 1 (Data Sep):** 1 hour
- **Phase 2 (Display Pages):** 2 hours
- **Phase 3 (Styling):** 1 hour
- **Phase 4 (Navigation):** 30 min
- **Total:** ~4.5 hours
- **Result:** Professional, integrated registries

### Option B (Styling Only)
- **Extract CSS:** 30 min
- **Update Colors:** 30 min
- **Improve Branding:** 30 min
- **Test/Refine:** 30 min
- **Total:** ~2 hours
- **Result:** Better looking, still standalone

---

## Recommendation

**Go with Option A (Full Integration)** because:
1. ✅ Follows clean architecture pattern
2. ✅ Consistent with site branding
3. ✅ Professional appearance
4. ✅ Easier to maintain
5. ✅ Can be added to navigation
6. ✅ Proper use of existing infrastructure
7. ✅ More scalable for future enhancements

---

## Questions to Answer Before Implementation

1. Should registries be public or admin-only?
2. How often should registries be regenerated? (hourly? daily? on-demand?)
3. Should there be a "Regenerate Registry" button?
4. What colors/branding should be used?
5. Should registries be in main navbar or separate admin section?
6. Should unused functions show warning by default?

