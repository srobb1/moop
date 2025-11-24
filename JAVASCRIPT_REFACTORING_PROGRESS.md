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

### ✅ PHASE 3B: COMPLETED (4 hours)
**Goal**: Consolidate 80% duplicate search logic + Add advanced filtering UI  
**Status**: COMPLETED 2025-11-24
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

### 🔧 PHASE 3B: Consolidate Shared Search Logic + Advanced Filtering (COMPLETED - 4 hours)
**Goal**: Extract 80% duplicate code into reusable module + Add advanced search filtering  
**Status**: COMPLETED 2025-11-24

**Completed Deliverables:**

1. ✅ **Created `js/core/annotation-search.js`** - Reusable AnnotationSearch class
   - Handles input validation, progress tracking, AJAX search, results display
   - Configurable for single/multiple organism searches
   - Supports custom URL builders and extra AJAX parameters

2. ✅ **Migrated all 3 display pages to use AnnotationSearch module**
   - `js/pages/groups-display.js` - refactored
   - `js/pages/multi-organism-search.js` - refactored
   - `js/pages/organism-display.js` - refactored
   - Result: ~320 lines of duplicate code removed (65% reduction)

3. ✅ **Advanced Search Filter Modal** - Full implementation
   - Dynamic source type grouping with source names
   - Checkbox selection with Select All / Deselect All per type
   - Filter state persistence when reopening modal
   - Proper source filtering applied to search results
   - Visual feedback: filter count badge on icon
   - Source-based result filtering working correctly

4. ✅ **Search UX Improvements**
   - Collapsible search info box (shows what terms are actually searched)
   - Compact result cap warning with organism list
   - Bold search terms in results for clarity
   - Applied filters displayed in search summary
   - Icon-only search and filter buttons
   - Flashing animation during search (instead of color change)
   - Consistent button behavior across all search pages

5. ✅ **Database Integration**
   - FTS5 search implementation for performance
   - REGEXP function support for pattern matching
   - Proper source filtering with database queries
   - Result cap at 2,500 per organism

**Commits made (Phase 3B):**
- `9798575` - Add search syntax & dynamic source filtering (Backend Phase 1)
- `ef836a4` - Advanced Search Filter Modal (Part 1 - Core Implementation)
- `fc1662b` - Advanced Search Filter Modal (Part 2 - Styling & Integration)
- `de27202` - Fix: Use correct annotation types from database
- `349bbe9` - Fix: Advanced Search Filter Modal - Unresponsive Input
- `6b07369` - Fix: Source filter not being applied
- `910d91b` - Redesign search and filter button layout
- `f176b64` - Improve search results page UX
- `69b1cad` - Simplify search buttons to icon-only
- `6c21de9` - Fix button alignment
- `06754da` - Expand search input field
- `46d7a22` - Fix filter badge positioning
- `4c5a9ac` - Restore original filter badge design
- `d7afd96` - Remove filter confirmation alert
- `4c359e3` - Fix filter badge visibility
- `c8c6b26` - Add compact result cap warning message
- `6c41406` - Advanced Search Filter UI Polish

**Key Achievements:**
- ✅ Search logic now DRY - single source of truth (AnnotationSearch module)
- ✅ User can easily filter results by annotation source
- ✅ Improved UX with clearer feedback and persistent filter state
- ✅ Performance enhanced with FTS5 database integration
- ✅ Consistent search behavior across all 3 display pages (groups, multi, organism)

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
| Phase 3B | ✅ DONE | 4 hrs | Consolidated search logic + Advanced filtering UI |
| Phase 3C | 📋 PLANNED | 1.5 hrs | Extract 4 lighter pages |
| Phase 3D | 📋 PLANNED | 15 min | Move utility files |
| Phase 4 | 📋 PLANNED | 1-2 hrs | Optional: JS registry & documentation |

**Total completed**: 7.5 hours  
**Total remaining**: 2-3 hours  
**Overall progress**: ~75% complete

---

## Key Accomplishments

✅ **Phase 3A Achievements:**
- Extracted 3 complex display pages to separate JS modules
- Fixed quoted search bug in sanitize_search_input()
- Removed all back-navigation system code
- Added new tab opening for organism_display links
- Identified 80% code duplication opportunity

✅ **Phase 3B Achievements:**
- Created reusable AnnotationSearch module (js/core/annotation-search.js)
- Consolidated 320+ lines of duplicate search code across 3 pages
- Implemented advanced search filtering with source type grouping
- Built dynamic filter modal with state persistence
- Enhanced search UX (collapsible hints, applied filters, term highlighting)
- Added FTS5 database integration for performance
- Implemented icon-only button design (search, filter, clear)
- All 3 display pages now use single unified search module

---

## Next Steps

1. **IMMEDIATE** (Next session):
   - Execute Phase 3C: Extract 4 lighter pages
     - `tools/parent_display.php` → `js/pages/parent-display.js`
     - `tools/retrieve_sequences.php` → `js/pages/retrieve-sequences.js`
     - `tools/blast.php` → `js/pages/blast.js`
     - `tools/sequences_display.php` → `js/pages/sequences-display.js`

2. **THEN**:
   - Phase 3D: Move utility files to organized locations
   - Phase 4: Optional documentation registry

---

## Notes for Next Developer

- ✅ Phases 1-3A-3B are COMPLETE - don't redo them
- Phase 3C extractions ready when needed (4 lighter pages identified)
- AnnotationSearch module at `js/core/annotation-search.js` is the foundation for search pages
- Advanced filter modal at `js/core/advanced-search-filter.js` handles source filtering
- All 3 display pages now use unified search pattern (see groups-display.js as example)
- Database indices optimized for FTS5 searches
- Git history preserved - can always recover files

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

**Last Updated**: 2025-11-24 19:30 UTC  
**Status**: Phase 3B Complete - 75% Overall Progress  
**Next Action**: Execute Phase 3C (Extract 4 lighter pages)
