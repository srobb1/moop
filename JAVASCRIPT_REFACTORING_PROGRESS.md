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
**Status**: COMPLETED 2025-11-21

### ✅ PHASE 2: COMPLETED (30 mins)
**Goal**: Organize existing feature code  
**Status**: COMPLETED 2025-11-21

### ✅ PHASE 3A: COMPLETED (2.5 hours)
**Goal**: Extract 3 heavy PHP display pages  
**Status**: COMPLETED 2025-11-22

### 🔄 PHASE 3B: IN PROGRESS (Plan Complete)
**Goal**: Consolidate 80% duplicate search logic  
**Status**: PLANNED (2-3 hours)  
**Documentation**: See `PHASE_3_JS_CONSOLIDATION_PLAN.md`

### 📋 PHASES 3C-4: PLANNED
**Status**: PENDING (after Phase 3B)

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

### ✅ PHASE 3A: Extract Heavy PHP Pages (COMPLETED - 2.5 hours)
**Goal**: Extract 500+ lines of embedded JavaScript from 3 heavy PHP files  
**Status**: COMPLETED 2025-11-22

**Completed extractions:**

#### 1. ✅ `tools/groups_display.php` → `js/pages/groups-display.js`
- Extracted 159 lines of search logic
- Handles multi-organism search within a group
- Commit: `cb89bed`

#### 2. ✅ `tools/multi_organism_search.php` → `js/pages/multi-organism-search.js`
- Extracted 153 lines of search logic
- Handles multi-organism search across selected organisms
- Commit: `c5ba327`

#### 3. ✅ `tools/organism_display.php` → `js/pages/organism-display.js`
- Extracted 128 lines of search logic
- Handles single-organism search
- Commit: `934627a`

**Additional improvements:**
- ✅ Removed back-navigation system code (no longer needed)
- ✅ Added `target="_blank"` to organism display links (opens in new tabs)
- ✅ Fixed quoted search bug in `sanitize_search_input()` function
- Commits: `d236144`, `29bf8a5`, `fc3127b`

**Verification**:
- ✅ All 3 pages tested and working
- ✅ Search functionality works correctly
- ✅ Progress bars display properly
- ✅ No console errors
- ✅ Back button removal successful

---

### 🔧 PHASE 3B: Consolidate Shared Search Logic (Est. 2-3 hours)
**Goal**: Extract 80% duplicate code into reusable module  
**Status**: PLANNED

**Problem identified:**
- 3 display pages share ~80% identical logic (~320 lines of 440 total)
- Only differences: form IDs, loop vs single organism, URL building, AJAX params

**Solution:**
Create `js/core/annotation-search.js` with reusable `AnnotationSearch` class that handles:
- Input validation
- Results reset
- Progress bar rendering
- AJAX search calls
- Results display
- Success/error handling

**Benefits:**
- Reduce 440 lines → ~150 lines (65% code reduction)
- Single source of truth for search logic
- Easy to fix bugs (fix once, applies everywhere)
- Easy to add new search pages

**See**: `PHASE_3_JS_CONSOLIDATION_PLAN.md` for detailed strategy

**Implementation steps:**
1. [ ] Create `js/core/annotation-search.js` with AnnotationSearch class
2. [ ] Test with groups-display.js
3. [ ] Update multi-organism-search.js to use module
4. [ ] Update organism-display.js to use module
5. [ ] Full test suite on all 3 pages
**Implementation steps:**
1. [ ] Create `js/core/annotation-search.js` with AnnotationSearch class
2. [ ] Test with groups-display.js
3. [ ] Update multi-organism-search.js to use module
4. [ ] Update organism-display.js to use module
5. [ ] Full test suite on all 3 pages
6. [ ] Commit: "Phase 3B: Create reusable AnnotationSearch module"

---

### 📄 PHASE 3C: Extract Lighter PHP Pages (Est. 1.5 hours)
**Goal**: Extract remaining page-specific JavaScript  
**Status**: PLANNED

**Priority pages:**
- `tools/parent_display.php` → `js/pages/parent-display.js`
- `tools/retrieve_sequences.php` → `js/pages/retrieve-sequences.js`
- `tools/blast.php` → `js/pages/blast.js`
- `tools/sequences_display.php` → `js/pages/sequences-display.js`

**Each has < 150 lines of JS** - easier to extract

---

### 📚 PHASE 3D: Move Utility Files (Est. 15 mins)
**Goal**: Move utility files from `/tools/` to organized location  
**Status**: PLANNED

Move:
- `/tools/shared_results_table.js` → `/js/core/results-table.js`
- `/tools/blast_canvas_graph.js` → `/js/core/blast-canvas.js`

Update PHP references (~10 files)

---

### 📖 PHASE 4: Create JavaScript Registry & Documentation (Est. 1-2 hours)
**Goal**: Auto-generate searchable documentation of all JS functions  
**Status**: PLANNED

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

## Summary of Progress

| Phase | Status | Duration | Deliverables |
|-------|--------|----------|--------------|
| Phase 1 | ✅ DONE | 30 min | Organized libraries, 960KB dead code removed |
| Phase 2 | ✅ DONE | 30 min | Reorganized 7 JS files into `/js/features/` and `/js/core/` |
| Phase 3A | ✅ DONE | 2.5 hrs | Extracted 3 heavy pages (440 lines → modular JS) |
| Phase 3B | 📋 PLANNED | 2-3 hrs | Create reusable AnnotationSearch module (see plan doc) |
| Phase 3C | 📋 PLANNED | 1.5 hrs | Extract 4 lighter pages |
| Phase 3D | 📋 PLANNED | 15 min | Move utility files |
| Phase 4 | 📋 PLANNED | 1-2 hrs | Optional: JS registry & documentation |

**Total completed**: 3.5 hours  
**Total remaining**: 5-7 hours  
**Overall progress**: ~33% complete

---

## Key Accomplishments

✅ **Phase 3A Achievements:**
- Extracted 3 complex display pages to separate JS modules
- Fixed quoted search bug in sanitize_search_input()
- Removed all back-navigation system code
- Added new tab opening for organism_display links
- Identified 80% code duplication opportunity
- Created detailed Phase 3B consolidation plan

---

## Next Steps

1. **IMMEDIATE** (Next session):
   - Execute Phase 3B: Create AnnotationSearch module
   - Refactor all 3 display pages to use new module
   - Expected savings: 290 lines of duplicate code

2. **THEN**:
   - Phase 3C: Extract 4 lighter pages
   - Phase 3D: Move utility files
   - Phase 4: Optional documentation registry

---

## Notes for Next Developer

- ✅ Phases 1-3A are COMPLETE - don't redo them
- Phase 3B has a detailed plan in `PHASE_3_JS_CONSOLIDATION_PLAN.md` - follow it closely
- All libraries now from CDN - orphaned local files moved to `/js/unused/`
- Git history preserved - can always recover files
- Test in browser after EACH step - don't batch multiple changes
- Commit frequently with clear messages

---

## Commits Made

| Commit | Date | Message | Status |
|--------|------|---------|--------|
| `21f1e1c` | 2025-11-21 | Phase 1: Move unused library files to js/unused directory | ✅ |
| Multiple | 2025-11-21 | Phase 2: Reorganize JS files into /js/features and /js/core | ✅ |
| `62980b0` | 2025-11-22 | Add found/not found ID coloring and collapsible parent/child ID documentation | ✅ |
| `fc3127b` | 2025-11-22 | Fix quoted search: handle $quoted_search parameter in sanitize_search_input | ✅ |
| `cb89bed` | 2025-11-22 | Phase 3A: Extract JS from groups_display.php to js/pages/groups-display.js | ✅ |
| `c5ba327` | 2025-11-22 | Phase 3A: Extract JS from multi_organism_search.php to js/pages/multi-organism-search.js | ✅ |
| `934627a` | 2025-11-22 | Phase 3A: Extract JS from organism_display.php to js/pages/organism-display.js | ✅ |
| `d236144` | 2025-11-22 | Remove all back navigation system code from display pages and JS files | ✅ |
| `29bf8a5` | 2025-11-22 | Open organism_display pages in new tabs from groups and multi-search | ✅ |

---

**Last Updated**: 2025-11-22 00:58 UTC  
**Status**: Phase 3A Complete - Phase 3B Plan Ready  
**Next Action**: Execute Phase 3B (Create AnnotationSearch module)
