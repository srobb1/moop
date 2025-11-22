# JavaScript Refactoring Progress
**Started**: 2025-11-21  
**Goal**: Organize and extract 500+ lines of embedded JavaScript across 9 PHP files for better maintainability

---

## Overview

This document tracks the 4-phase JavaScript reorganization plan for MOOP. The approach follows one core principle:
- **Data stays with PHP** (user input, database, permissions)
- **Logic extracted to JS** (event handlers, AJAX, DOM manipulation)

**Total estimated effort**: ~7-8 hours across 4 weeks  
**Risk level**: LOW to MEDIUM (mostly file organization + careful extraction)

---

## Current Status

### ✅ PHASE 1: COMPLETED (30 mins) 
**Goal**: Organize third-party libraries

**What was done:**
1. ✅ Created `/js/unused/` directory for deprecated/unused libraries
2. ✅ Moved 7 unused local library files there:
   - `apexcharts.min.js` - Not referenced anywhere
   - `bootstrap.min.js` - Loading from CDN instead
   - `jquery.min.js` - Loading from CDN instead
   - `jszip.min.js` - Loading from CDN instead
   - `kinetic-v5.1.0.min.js` - Not referenced anywhere
   - `openGPlink.js` - Not referenced anywhere
   - `popper.min.js` - Loading from CDN instead

**Finding**: All dependencies are now loaded from CDN. Local copies were orphaned (960KB of dead code).

**Verification done**:
- ✅ Searched entire codebase for references - none found
- ✅ `/includes/head.php` uses only CDN links
- ✅ No 404 errors on any page
- ✅ All features still working

**Commit**: `21f1e1c` - "Phase 1: Move unused library files to js/unused directory - all deps now CDN-based"

---

## Next Steps (TODO)

### 📋 PHASE 2: Organize Existing Feature Code (Est. 30 mins)
**Goal**: Move existing extracted JS to organized `/js/features/` folder  
**Status**: NOT STARTED

**Files to organize:**
```
Current location          →  New location
js/datatable.js          →  js/features/datatable.js
js/datatable-config.js   →  js/features/datatable-config.js
js/phylo_tree.js         →  js/features/phylo-tree.js
js/manage_organisms.js   →  js/features/organism-management.js
js/source_list_manager.js →  js/features/source-list-manager.js
js/download2.js          →  js/features/download-handler.js
js/parent.js             →  js/features/parent-tools.js
js/index.js              →  KEEP (homepage-specific, can stay in /js/)
```

**Create core utilities:**
```
js/tools_utilities.js → js/core/utilities.js (reusable helpers)
```

**Steps to execute:**
1. Create `/js/features/` directory
2. Create `/js/core/` directory
3. Move files to new locations (use `git mv` for clean history)
4. Update all PHP file references (use find/replace, estimate ~15 files)
5. Test each major page for JS errors
6. Commit with message: "Phase 2: Organize existing feature JS files into /js/features and /js/core"

**Risk**: LOW - Just file moves, same functionality

**Testing after**:
- [ ] DataTable pages render correctly
- [ ] Organism management works
- [ ] Source list filtering works
- [ ] No console errors on any page

---

### 🔧 PHASE 3A: Extract Heavy PHP Pages (Est. 2-3 hours)
**Goal**: Extract 500+ lines of embedded JavaScript from 3 heavy PHP files  
**Status**: NOT STARTED

**Priority 1 - Heavy pages (must extract):**

#### 1. `tools/groups_display.php` → `js/pages/groups-display.js`
**Current embedded JS**: ~13 script tags, 500+ lines  
**What to extract**:
- Form submit handler: `$('#groupSearchForm').on('submit', ...)`
- Search functions: `searchNextOrganism()`, `displayOrganismResults()`, `finishSearch()`
- Progress bar management
- Result rendering and navigation

**What to keep in PHP**:
```php
<script>
const groupName = <?= json_encode($group_name) ?>;
const groupOrganisms = <?= json_encode(array_keys($group_organisms)) ?>;
const sitePath = '/<?= $site ?>';
</script>
<script src="/<?= $site ?>/js/pages/groups-display.js"></script>
```

#### 2. `tools/multi_organism_search.php` → `js/pages/multi-organism-search.js`
**Current embedded JS**: ~13 script tags, 500+ lines  
**Same extraction pattern as groups_display**

#### 3. `tools/organism_display.php` → `js/pages/organism-display.js`
**Current embedded JS**: ~14 script tags, 600+ lines  
**Largest extraction - same pattern**

**Execution plan**:
- Extract one page at a time
- Test thoroughly in browser after each
- Verify AJAX calls work
- Verify form submissions work
- Check navigation/back buttons work

---

### 📄 PHASE 3B: Extract Lighter PHP Pages (Est. 1 hour)
**Goal**: Extract remaining page-specific JavaScript  
**Status**: NOT STARTED

**Priority 2 - Lighter pages:**
- `tools/parent_display.php` → `js/pages/parent-display.js`
- `tools/retrieve_selected_sequences.php` → `js/pages/retrieve-sequences.js`
- `tools/retrieve_sequences.php` → `js/pages/retrieve-sequences-old.js`
- `tools/blast.php` → `js/pages/blast.js`
- `tools/sequences_display.php` → `js/pages/sequences-display.js`

**Each has < 100 lines of JS** - easier to extract

---

### 📚 PHASE 3C: Move Utility Files (Est. 15 mins)
**Goal**: Move utility files from `/tools/` to organized location  
**Status**: NOT STARTED

Move:
- `/tools/shared_results_table.js` → `/js/utils/results-table.js`
- `/tools/blast_canvas_graph.js` → `/js/utils/blast-canvas.js`

Update PHP references (~10 files)

---

### 📖 PHASE 4: Create JavaScript Registry & Documentation (Est. 1-2 hours)
**Goal**: Auto-generate searchable documentation of all JS functions  
**Status**: NOT STARTED

**Note**: This phase can be skipped initially. Complete Phases 1-3 first, then evaluate if registry is needed.

**Optional deliverables:**
- `/tools/generate_js_registry.php` - Scans all JS files, generates registry
- `/docs/js_registry.html` - Interactive searchable function documentation
- `/docs/JS_REGISTRY.md` - Markdown version for version control

---

## Target Directory Structure (After All Phases)

```
/js/
├── unused/                    # Deprecated/unused files (reference only)
│   ├── apexcharts.min.js
│   ├── bootstrap.min.js
│   ├── jquery.min.js
│   └── ... (6 more)
│
├── core/                      # Reusable core utilities
│   └── utilities.js           # Common helpers (from tools_utilities.js)
│
├── features/                  # Feature-specific, reusable code
│   ├── datatable.js
│   ├── datatable-config.js
│   ├── phylo-tree.js
│   ├── organism-management.js
│   ├── source-list-manager.js
│   ├── download-handler.js
│   └── parent-tools.js
│
├── pages/                     # Page-specific logic (extracted from PHP)
│   ├── groups-display.js
│   ├── multi-organism-search.js
│   ├── organism-display.js
│   ├── parent-display.js
│   ├── blast.js
│   ├── sequences-display.js
│   └── retrieve-sequences.js
│
├── utils/                     # Shared utility modules
│   ├── results-table.js       # (from /tools/)
│   └── blast-canvas.js        # (from /tools/)
│
├── index.js                   # Homepage-specific (stays in root)
└── [other existing files]
```

---

## Testing Checklist

After each phase, verify:

### Phase 2 Testing
- [ ] Open `/tools/groups_display.php` - no JS errors in console
- [ ] Open `/tools/organism_display.php` - datatable loads correctly
- [ ] Open `/tools/manage_organisms.php` - works as before
- [ ] Check browser Network tab - all JS files loading (200 OK)
- [ ] No 404 errors for JS files

### Phase 3A Testing (After each page extraction)
- [ ] Page loads without console errors
- [ ] Form submission works
- [ ] AJAX calls complete successfully
- [ ] Results display correctly
- [ ] Navigation/back buttons function
- [ ] Search/filter features work

### Phase 3B & 3C Testing
- [ ] All pages still functional
- [ ] No missing file references
- [ ] Download functionality works
- [ ] Result tables render correctly

---

## Key Principles (Keep in Mind)

1. **Data in PHP, Logic in JS**
   - Database queries: PHP ✓
   - User input validation: PHP first, JS for UX ✓
   - Permissions checks: PHP ✓
   - Event handlers: JS ✓
   - AJAX calls: JS ✓
   - DOM manipulation: JS ✓

2. **Variable Scoping**
   - PHP defines data in `<script>` block in HTML
   - External JS files can access those variables
   - Use window scope for cross-file communication
   - Example: PHP defines `const groupName = "xyz"`, JS uses `groupName` directly

3. **File Organization**
   - `/js/features/` = Reusable across multiple pages
   - `/js/pages/` = Specific to one page only
   - `/js/core/` = Common utilities used everywhere
   - `/js/utils/` = Shared utility modules

---

## Rollback Plan

If something breaks:

```bash
# See what changed
git status

# Revert last commit
git reset --hard HEAD~1

# Or check specific file history
git log --oneline -- js/
git show <commit-sha>:<file-path>
```

---

## Notes for Next Developer

- ✅ Phase 1 is COMPLETE - don't redo it
- Phase 2 should be done NEXT - it's quick and low-risk
- All libraries now from CDN - orphaned local files moved to `/js/unused/`
- Git history preserved - can always recover files
- Test in browser after EACH step - don't batch multiple changes
- Use `git mv` for file moves to keep clean history

---

## Commits Made

| Commit | Message |
|--------|---------|
| `21f1e1c` | Phase 1: Move unused library files to js/unused directory - all deps now CDN-based |

---

**Last Updated**: 2025-11-21 23:34 UTC  
**Next Action**: Begin Phase 2 when ready - estimate 30 mins
