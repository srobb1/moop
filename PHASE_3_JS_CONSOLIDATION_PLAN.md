# Phase 3A JS Consolidation - Code Reuse Plan

**Review Date**: 2025-11-22  
**Files Analyzed**: 
- `js/pages/groups-display.js` (159 lines)
- `js/pages/multi-organism-search.js` (153 lines)
- `js/pages/organism-display.js` (128 lines)

---

## Executive Summary

The three display pages share **~80% identical logic** with these differences:
1. **Loop structure**: groups/multi iterate through organisms, organism-display is single-organism
2. **Form IDs**: Different form selectors (`#groupSearchForm`, `#multiOrgSearchForm`, `#organismSearchForm`)
3. **Context handling**: groups sends group param, multi sends multi_search array, organism sends nothing
4. **UI hiding**: Different sections hide on search
5. **URL building**: multi-search appends multi_search params

---

## Shared Components (80%)

### 1. **Input Validation** ✅ IDENTICAL
```javascript
const keywords = $('#searchKeywords').val().trim();
if (keywords.length < 3) {
    alert('Please enter at least 3 characters to search');
    return;
}
const quotedSearch = /^".+"$/.test(keywords);
```

### 2. **Results Reset** ✅ IDENTICAL
```javascript
allResults = [];
searchedOrganisms = 0;
$('#searchResults').show();
$('#resultsContainer').html('');
```

### 3. **Progress Bar Rendering** ✅ IDENTICAL
```javascript
$('#searchProgress').html(`
    <div class="search-progress-bar">
        <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
    </div>
    <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
`);
```

### 4. **AJAX Search Call** 95% IDENTICAL
Only differences:
- `group` param: groups & multi include it, organism doesn't
- `selectedOrganisms` vs `[organism]` - just iteration pattern

### 5. **Display Results** ✅ IDENTICAL
```javascript
const tableHtml = createOrganismResultsTable(organism, results, sitePath, 'tools/parent_display.php', imageUrl);
const readMoreBtn = `<a href="${organismUrl}" target="_blank"...>Read More</a>`;
const modifiedHtml = tableHtml.replace(/(<span class="badge bg-primary">.*?<\/span>)/, `$1\n${readMoreBtn}`);
$('#resultsContainer').append(modifiedHtml);
```

### 6. **Finish Search Success Message** 95% IDENTICAL
Only difference: pluralization of "result" (all handle this the same way)

---

## Unique Components (20%)

### 1. **Loop vs Single Organism**
| File | Pattern |
|------|---------|
| groups-display.js | `searchNextOrganism(keywords, quotedSearch, 0)` - recursive loop |
| multi-organism-search.js | `searchNextOrganism(keywords, quotedSearch, 0)` - recursive loop |
| organism-display.js | `searchOrganism(organismName, keywords, quotedSearch)` - single call |

### 2. **Form Submission Handler**
| Aspect | Groups | Multi | Organism |
|--------|--------|-------|----------|
| Form ID | `#groupSearchForm` | `#multiOrgSearchForm` | `#organismSearchForm` |
| Hide sections | `#groupDescription`, `#organismsSection` | None | `#organismHeader`, `#organismContent` |
| UI scroll | No | Yes - smooth scroll to results | No |

### 3. **URL Building for "Read More"**
| File | URL Building |
|------|--------------|
| groups-display.js | Simple: `/organism_display.php?organism=X` |
| multi-organism-search.js | Complex: adds `&multi_search[]=` for each organism |
| organism-display.js | N/A - no "Read More" button needed |

### 4. **AJAX Data Parameters**
| File | Extra Params |
|------|--------------|
| groups-display.js | `group: groupName` |
| multi-organism-search.js | None |
| organism-display.js | `group: ''` |

---

## Recommended Consolidation Strategy

### **PHASE 3B: Create Reusable Search Module**

**Goal**: Extract 80% shared code into `js/core/annotation-search.js`

#### Step 1: Create Base Search Module
Create `js/core/annotation-search.js` with:
```javascript
/**
 * Reusable Annotation Search Module
 * Handles search logic for annotation searches across organisms
 * 
 * Configuration object passed to init():
 * {
 *   formSelector: '#groupSearchForm',           // Form to submit
 *   organismsVar: groupOrganisms,               // Array or single string
 *   totalVar: totalOrganisms,                   // Number
 *   hideSections: ['#groupDescription'],       // Optional
 *   scrollToResults: false,                     // Optional
 *   extraAjaxParams: {group: groupName},        // Optional
 *   urlBuilder: function(organism) {...}       // Optional URL builder
 * }
 */

class AnnotationSearch {
    constructor(config) {
        this.config = config;
        this.allResults = [];
        this.searchedOrganisms = 0;
    }
    
    init() {
        // Setup form submission
        // Common validation, AJAX, results display
    }
    
    search(keywords, quotedSearch, index) {
        // Abstract search logic for single/multiple
    }
    
    displayResults(data) {
        // Common results display
    }
}
```

#### Step 2: Update Each Page to Use Module

**groups-display.js** becomes:
```javascript
const searchManager = new AnnotationSearch({
    formSelector: '#groupSearchForm',
    organismsVar: groupOrganisms,
    totalVar: totalOrganisms,
    hideSections: ['#groupDescription', '#organismsSection'],
    scrollToResults: false,
    extraAjaxParams: {group: groupName}
});
searchManager.init();
```

**multi-organism-search.js** becomes:
```javascript
const searchManager = new AnnotationSearch({
    formSelector: '#multiOrgSearchForm',
    organismsVar: selectedOrganisms,
    totalVar: totalOrganisms,
    scrollToResults: true,
    urlBuilder: (organism) => {
        let url = sitePath + '/tools/organism_display.php?organism=' + encodeURIComponent(organism);
        selectedOrganisms.forEach(org => {
            url += '&multi_search[]=' + encodeURIComponent(org);
        });
        return url;
    }
});
searchManager.init();
```

**organism-display.js** becomes:
```javascript
const searchManager = new AnnotationSearch({
    formSelector: '#organismSearchForm',
    organismsVar: [organismName],  // Single organism as array
    totalVar: 1,
    hideSections: ['#organismHeader', '#organismContent'],
    scrollToResults: false,
    noReadMoreButton: true  // Single organism doesn't need "Read More"
});
searchManager.init();
```

#### Step 3: Benefits
- ✅ **~400 lines shared** → **~150 lines** (65% reduction)
- ✅ **Single source of truth** for search logic
- ✅ **Easy bug fixes** - fix once, applies everywhere
- ✅ **Consistent behavior** across all search pages
- ✅ **Easy to add new search pages** - just instantiate module with config

---

## Implementation Checklist

### Phase 3B (Consolidation)
- [ ] Create `js/core/annotation-search.js` with AnnotationSearch class
- [ ] Test the class with groups-display.js
- [ ] Update multi-organism-search.js to use module
- [ ] Update organism-display.js to use module
- [ ] Remove duplicate code from all 3 files
- [ ] Run full test suite on all 3 pages
- [ ] Commit: "Phase 3B: Create reusable AnnotationSearch module"

### Phase 3C (Lighter Pages)
After consolidation, can extract remaining pages:
- `tools/parent_display.php` - lightweight search
- `tools/index.php` - any embedded JS
- Other tool pages

---

## Effort Estimate
- **Phase 3B (Consolidation)**: ~2-3 hours
  - Create base module: 1 hour
  - Test & refactor each page: 1.5 hours
  - Full test suite: 0.5 hours

**Total Phase 3 (A + B)**: ~4 hours remaining

---

## Notes
- All 3 files already open organism_display in new tabs ✅
- Error logging is minimal across all files ✅
- Progress calculation already uses same math ✅
- AJAX endpoint `/tools/annotation_search_ajax.php` is shared ✅
