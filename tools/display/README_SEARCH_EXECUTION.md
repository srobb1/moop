# Search Execution Flow - Display Pages

## Overview

This document explains how searches are executed on `organism_display.php` and `groups_display.php`. Both pages use client-side JavaScript to make AJAX calls to the same backend endpoint, but differ in scope and iteration strategy.

---

## Table of Contents

1. [High-Level Architecture](#high-level-architecture)
2. [organism_display.php - Single Organism Search](#organism_displayphp---single-organism-search)
3. [groups_display.php - Multi-Organism Sequential Search](#groups_displayphp---multi-organism-sequential-search)
4. [Backend: annotation_search_ajax.php](#backend-annotation_search_ajaxphp)
5. [Database Search Strategy](#database-search-strategy)
6. [Key Differences Summary](#key-differences-summary)

---

## High-Level Architecture

```
┌──────────────────┐
│   User Browser   │
│  (JavaScript)    │
└────────┬─────────┘
         │ AJAX Request
         ↓
┌────────────────────────────────┐
│  annotation_search_ajax.php    │
│  - Access control              │
│  - Input sanitization          │
│  - Database queries            │
└────────┬───────────────────────┘
         │ SQL Query
         ↓
┌────────────────────────────────┐
│  SQLite Database               │
│  /data/organisms/{org}/        │
│  genes.sqlite                  │
└────────────────────────────────┘
```

---

## organism_display.php - Single Organism Search

### Purpose
Searches **one specific organism** that the user is currently viewing.

### Execution Flow

```
User submits search
       ↓
Validate input (≥3 chars)
       ↓
Detect search type (quoted vs keywords)
       ↓
Call searchOrganism()
       ↓
Single AJAX request
       ↓
Backend queries 1 database
       ↓
Display results
```

### Key Code

**Form Submission:**
```javascript
$('#organismSearchForm').on('submit', function(e) {
    e.preventDefault();
    
    const keywords = $('#searchKeywords').val().trim();
    
    // Validate input
    if (keywords.length < 3) {
        alert('Please enter at least 3 characters to search');
        return;
    }
    
    // Check for quoted search (exact phrase)
    const quotedSearch = /^".+"$/.test(keywords);
    
    // Hide organism content, show search results
    $('#organismHeader').slideUp();
    $('#organismContent').slideUp();
    
    // Search the single organism
    searchOrganism(organismName, keywords, quotedSearch);
});
```

**Search Function:**
```javascript
function searchOrganism(organism, keywords, quotedSearch) {
    $('#progressText').html(`Searching ${organism}...`);
    
    $.ajax({
        url: sitePath + '/tools/search/annotation_search_ajax.php',
        method: 'GET',
        data: {
            search_keywords: keywords,
            organism: organism,
            group: '',  // No group context
            quoted: quotedSearch ? '1' : '0'
        },
        dataType: 'json',
        success: function(response) {
            searchedOrganisms++;
            
            if (response.results && response.results.length > 0) {
                allResults = allResults.concat(response.results);
            }
            
            // Update progress and display
            displayResults();
        },
        error: function(xhr, status, error) {
            console.error('Search error:', error);
            displayResults();
        }
    });
}
```

### Characteristics
- ✅ **Simple**: One AJAX call
- ✅ **Fast**: Single database query
- ✅ **Direct**: Immediate results
- 🔢 **Scope**: 1 organism only

---

## groups_display.php - Multi-Organism Sequential Search

### Purpose
Searches **all organisms in a group** (e.g., "Mammals", "Insects", etc.).

### Execution Flow

```
User submits search
       ↓
Validate input (≥3 chars)
       ↓
Detect search type (quoted vs keywords)
       ↓
Call searchNextOrganism(0)
       ↓
┌──────────────────────┐
│  For each organism:  │
│  ├─ AJAX request     │
│  ├─ Display results  │
│  └─ Recurse to next  │
└──────────────────────┘
       ↓
All organisms complete
       ↓
finishSearch()
```

### Key Code

**Form Submission:**
```javascript
$('#groupSearchForm').on('submit', function(e) {
    e.preventDefault();
    
    const keywords = $('#searchKeywords').val().trim();
    
    // Validate input
    if (keywords.length < 3) {
        alert('Please enter at least 3 characters to search');
        return;
    }
    
    // Check for quoted search
    const quotedSearch = /^".+"$/.test(keywords);
    
    // Hide group description and organisms sections
    $('#groupDescription').slideUp();
    $('#organismsSection').slideUp();
    
    // Reset results
    allResults = [];
    searchedOrganisms = 0;
    
    // Disable search button
    $('#searchBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Searching...');
    
    // Start sequential search
    searchNextOrganism(keywords, quotedSearch, 0);
});
```

**Recursive Search Function:**
```javascript
function searchNextOrganism(keywords, quotedSearch, index) {
    // Base case: All organisms searched
    if (index >= totalOrganisms) {
        finishSearch();
        return;
    }
    
    const organism = groupOrganisms[index];
    $('#progressText').html(`Searching ${organism}... (${index + 1}/${totalOrganisms})`);
    
    $.ajax({
        url: sitePath + '/tools/search/annotation_search_ajax.php',
        method: 'GET',
        data: {
            search_keywords: keywords,
            organism: organism,
            group: groupName,  // Include group context for access control
            quoted: quotedSearch ? '1' : '0'
        },
        dataType: 'json',
        success: function(data) {
            // Display results for this organism (if any)
            if (data.results && data.results.length > 0) {
                allResults = allResults.concat(data.results);
                displayOrganismResults(data);
            }
            
            searchedOrganisms++;
            const progress = Math.round((searchedOrganisms / totalOrganisms) * 100);
            $('#progressFill').css('width', progress + '%').text(progress + '%');
            
            // Recursive call: Search next organism
            searchNextOrganism(keywords, quotedSearch, index + 1);
        },
        error: function(xhr, status, error) {
            console.error('Search error for ' + organism + ':', error);
            searchedOrganisms++;
            // Continue even on error
            searchNextOrganism(keywords, quotedSearch, index + 1);
        }
    });
}
```

**Completion Handler:**
```javascript
function finishSearch() {
    $('#searchBtn').prop('disabled', false).html('<i class="fa fa-search"></i> Search');
    
    if (allResults.length === 0) {
        $('#searchProgress').html('<div class="alert alert-warning">No results found. Try different search terms.</div>');
    } else {
        // Build jump-to navigation for multiple organisms
        // Display summary statistics
        // Show "Jump to results for: Organism A | Organism B | ..."
    }
}
```

### Characteristics
- 🔄 **Iterative**: N AJAX calls (one per organism)
- 📊 **Progressive**: Shows results as they arrive
- 🎯 **Comprehensive**: Searches entire group
- ⏱️ **Slower**: Takes longer with many organisms
- 🔢 **Scope**: Multiple organisms

### Why Sequential Instead of Parallel?

The search uses **sequential execution** (one after another) rather than parallel (all at once) because:

1. **Server load management**: Prevents overwhelming the server with simultaneous database queries
2. **Progress tracking**: Easier to show meaningful progress (X of N organisms)
3. **Error handling**: Can handle failures gracefully and continue
4. **Resource control**: Limits concurrent database connections
5. **User feedback**: Shows incremental results, feels more responsive

---

## Backend: annotation_search_ajax.php

### Location
`/data/moop/tools/search/annotation_search_ajax.php`

### Purpose
Single endpoint that handles all search requests from both display pages.

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search_keywords` | string | ✅ Yes | Search terms or gene ID |
| `organism` | string | ✅ Yes | Organism identifier |
| `group` | string | ❌ No | Group name (for access control) |
| `quoted` | string | ❌ No | "1" for exact phrase search |

### Processing Flow

```
1. Receive & validate parameters
         ↓
2. Access control check
   - Is user admin?
   - Does user have group access?
   - Is organism public?
   - Does user have organism-specific access?
         ↓
3. Sanitize search input
   - Remove dangerous characters
   - Filter short terms (<3 chars)
   - Strip quotes for quoted searches
         ↓
4. Locate SQLite database
   - Try: /data/organisms/{org}/genes.sqlite
   - Try: /data/organisms/{org}/{org}.genes.sqlite
         ↓
5. Execute search (two-phase)
   - Phase 1: Search by gene/transcript ID
   - Phase 2: If no results, search annotations
         ↓
6. Format & return JSON results
```

### Access Control Logic

```php
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_has_group_access = has_access('Collaborator', $group);
$organism_is_public = is_public_organism($organism);
$user_has_organism_access = has_access('Collaborator', $organism);

if (!$is_admin && !$user_has_group_access && 
    !$organism_is_public && !$user_has_organism_access) {
    // Access denied
    echo json_encode(['error' => 'Access denied', 'results' => []]);
    exit;
}
```

### Input Sanitization

```php
function sanitize_search_input($data, $quoted_search) {
    // Remove quotes if quoted search
    if ($quoted_search) {
        $data = trim($data, '"');
    }
    
    // Remove dangerous characters
    $data = preg_replace('/[\<\>\t\;]+/', ' ', $data);
    $data = htmlspecialchars($data);
    
    // Filter short terms (unless quoted)
    if (preg_match('/\s+/', $data)) {
        $data_array = explode(' ', $data, 99);
        foreach ($data_array as $key => &$value) {
            if (strlen($value) < 3 && !$quoted_search) {
                unset($data_array[$key]);
            }
        }
        $data = implode(' ', $data_array);
    }
    
    $data = stripslashes($data);
    return $data;
}
```

### Response Format

```json
{
    "organism": "Drosophila_melanogaster",
    "results": [
        {
            "organism": "Drosophila_melanogaster",
            "genus": "Drosophila",
            "species": "melanogaster",
            "common_name": "fruit fly",
            "feature_type": "mRNA",
            "feature_uniquename": "FBtr0100001",
            "feature_name": "ABC-1",
            "feature_description": "ATP-binding cassette transporter",
            "annotation_source": "UniProtKB",
            "annotation_accession": "P12345",
            "annotation_description": "ABC transporter family protein",
            "uniquename_search": false
        }
    ],
    "count": 1,
    "search_type": "Keyword"
}
```

---

## Database Search Strategy

### Two-Phase Search Approach

The backend uses a smart two-phase strategy to optimize search performance:

### Phase 1: Gene/Transcript ID Search

**When**: Always executed first  
**Purpose**: Direct lookups by feature uniquename (fast)  
**Columns searched**: `feature.feature_uniquename`

```sql
SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
       o.genus, o.species, o.common_name, o.subtype, f.feature_type
FROM feature f, organism o
WHERE f.organism_id = o.organism_id
  AND f.feature_uniquename LIKE '%LOC12345%'
ORDER BY f.feature_uniquename
LIMIT 100
```

**Example searches that match Phase 1:**
- `LOC12345`
- `NM_001234`
- `FBgn0012345`
- `AT1G01010`

### Phase 2: Annotation Search

**When**: Only if Phase 1 returns no results  
**Purpose**: Full-text search across annotations and descriptions  
**Columns searched**: 
- `annotation.annotation_description`
- `feature.feature_name`
- `feature.feature_description`
- `annotation.annotation_accession`

```sql
SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
       a.annotation_accession, a.annotation_description, 
       fa.score, fa.date, ans.annotation_source_name, 
       o.genus, o.species, o.common_name, f.feature_type
FROM annotation a, feature f, feature_annotation fa, 
     annotation_source ans, organism o 
WHERE ans.annotation_source_id = a.annotation_source_id 
  AND f.feature_id = fa.feature_id 
  AND fa.annotation_id = a.annotation_id 
  AND f.organism_id = o.organism_id
  AND (
    (a.annotation_description LIKE '%kinase%' OR f.feature_name LIKE '%kinase%' 
     OR f.feature_description LIKE '%kinase%' OR a.annotation_accession LIKE '%kinase%')
    AND
    (a.annotation_description LIKE '%protein%' OR f.feature_name LIKE '%protein%' 
     OR f.feature_description LIKE '%protein%' OR a.annotation_accession LIKE '%protein%')
  )
ORDER BY f.feature_uniquename
LIMIT 100
```

### Search Modes

#### 1. Keyword Search (Default)
**Input**: `kinase protein`  
**Logic**: AND (both terms must match)  
**SQL**: Each term searches all columns, all terms must match

```
(col1 LIKE '%kinase%' OR col2 LIKE '%kinase%' OR col3 LIKE '%kinase%')
AND
(col1 LIKE '%protein%' OR col2 LIKE '%protein%' OR col3 LIKE '%protein%')
```

#### 2. Quoted/Phrase Search
**Input**: `"ABC transporter"`  
**Logic**: Exact phrase  
**SQL**: Single phrase searches all columns

```
(col1 LIKE '%ABC transporter%' OR col2 LIKE '%ABC transporter%' OR col3 LIKE '%ABC transporter%')
```

#### 3. Gene ID Search
**Input**: `LOC12345`  
**Logic**: Direct match  
**SQL**: Searches only feature_uniquename column

```
feature_uniquename LIKE '%LOC12345%'
```

### buildLikeConditions() Function

Located in `/data/moop/tools/common_functions.php`

```php
function buildLikeConditions($columns, $search, $quoted = false) {
    $conditions = [];
    $params = [];

    if ($quoted) {
        // Treat as single phrase
        $searchConditions = [];
        foreach ($columns as $col) {
            $searchConditions[] = "$col LIKE ?";
            $params[] = "%" . $search . "%";
        }
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
    } else {
        // Split into individual terms
        $terms = preg_split('/\s+/', trim($search));
        foreach ($terms as $term) {
            $termConditions = [];
            foreach ($columns as $col) {
                $termConditions[] = "$col LIKE ?";
                $params[] = "%" . $term . "%";
            }
            // Each term can match any column
            $conditions[] = "(" . implode(" OR ", $termConditions) . ")";
        }
    }

    // Join all terms with AND (every term must match something)
    $sqlFragment = implode(" AND ", $conditions);

    return [$sqlFragment, $params];
}
```

---

## Key Differences Summary

### Comparison Table

| Aspect | organism_display.php | groups_display.php |
|--------|---------------------|-------------------|
| **Scope** | Single organism | Multiple organisms |
| **AJAX Calls** | 1 call | N calls (one per organism) |
| **Execution Strategy** | Direct call | Sequential/recursive |
| **Progress Bar** | Simple (0% → 100%) | Incremental (N/M organisms) |
| **Results Display** | All at once | Incremental (as each completes) |
| **Button Behavior** | Enabled throughout | Disabled during search |
| **Feature Link Path** | `tools/display/parent.php` | `tools/search/parent.php` |
| **"Read More" Button** | ❌ No | ✅ Yes (links to organism page) |
| **Jump Navigation** | ❌ No | ✅ Yes (if multiple organisms) |
| **Group Parameter** | Empty string | Group name |
| **Back Button** | "Back to Organism Page" | "Back to Group" |
| **Best For** | Deep dive into one organism | Broad search across group |

### When to Use Which?

**Use organism_display.php when:**
- User is focused on a specific organism
- Need detailed organism information
- Want fast, focused results
- Exploring organism resources

**Use groups_display.php when:**
- Comparing results across multiple organisms
- Don't know which organism contains target
- Need comprehensive group coverage
- Want to discover patterns across species

---

## Example Search Scenarios

### Scenario 1: Finding a Gene by ID

**Input**: `LOC123456`

**organism_display.php flow:**
```
1. User enters "LOC123456"
2. Single AJAX call to backend
3. Backend searches feature.feature_uniquename
4. Returns direct match
5. Displays 1 result immediately
```

**groups_display.php flow:**
```
1. User enters "LOC123456"
2. Searches organism #1 → no match
3. Searches organism #2 → no match
4. Searches organism #3 → match! (displays)
5. Searches organism #4 → no match
6. Completes search
7. Shows 1 result from organism #3
```

### Scenario 2: Keyword Search

**Input**: `ABC transporter`

**organism_display.php flow:**
```
1. User enters "ABC transporter"
2. Single AJAX call to backend
3. Backend searches feature_uniquename → no results
4. Backend searches annotations:
   - annotation_description LIKE '%ABC%' AND LIKE '%transporter%'
   - feature_name LIKE '%ABC%' AND LIKE '%transporter%'
5. Returns 15 matches
6. Displays all 15 results
```

**groups_display.php flow:**
```
1. User enters "ABC transporter"
2. Searches organism #1 → 5 matches (displays table)
3. Searches organism #2 → 3 matches (displays table)
4. Searches organism #3 → 0 matches (no display)
5. Searches organism #4 → 8 matches (displays table)
6. Completes search
7. Shows 3 tables with 16 total results
8. Provides "Jump to:" navigation
```

### Scenario 3: Quoted Search

**Input**: `"ATP binding cassette"`

**Both pages:**
```
1. User enters quoted phrase
2. Detects quotes with regex: /^".+"$/
3. Passes quoted=1 to backend
4. Backend treats as single phrase
5. SQL: annotation_description LIKE '%ATP binding cassette%'
6. Returns exact phrase matches only
```

---

## Performance Considerations

### organism_display.php
- **Single database connection**
- **One query execution**
- **Typical response**: 50-200ms
- **Best case**: Gene ID match in Phase 1 (~10ms)
- **Worst case**: Full annotation search (~500ms)

### groups_display.php
- **Multiple database connections**
- **Sequential query execution**
- **Typical response**: 500ms - 5s (depends on organism count)
- **Best case**: 5 organisms × 50ms = 250ms
- **Worst case**: 20 organisms × 500ms = 10s

### Optimization Strategies

1. **Database Indexing**: Ensure indexes on:
   - `feature.feature_uniquename`
   - `annotation.annotation_description`
   - `feature.feature_name`

2. **Result Limiting**: LIMIT 100 prevents excessive data transfer

3. **Sequential vs Parallel**: Sequential prevents server overload

4. **Caching**: Could cache frequent searches (not currently implemented)

5. **Progress Feedback**: Keeps users informed during long searches

---

## Troubleshooting

### Common Issues

**Issue**: "No results found" but gene exists  
**Solution**: Check if search term is at least 3 characters

**Issue**: Quoted search not working  
**Solution**: Ensure quotes are straight quotes (`"`) not smart quotes (`"` or `"`)

**Issue**: Group search is slow  
**Solution**: This is normal for large groups; consider searching individual organisms

**Issue**: "Access denied" error  
**Solution**: Check user permissions for organism or group

**Issue**: Database not found  
**Solution**: Verify SQLite files exist at:
- `/data/organisms/{organism}/genes.sqlite`
- `/data/organisms/{organism}/{organism}.genes.sqlite`

---

## Future Enhancements

Potential improvements to consider:

1. **Parallel Search Option**: For groups with many organisms
2. **Search History**: Remember recent searches
3. **Advanced Filters**: Filter by feature type, annotation source
4. **Export Results**: Export all results to CSV/Excel before selecting
5. **Search Suggestions**: Autocomplete based on common terms
6. **Fuzzy Matching**: Handle typos and variations
7. **Search Within Results**: Filter displayed results further
8. **Saved Searches**: Bookmark frequently used searches
9. **Result Caching**: Speed up repeated searches
10. **Batch Gene ID Search**: Upload list of IDs to search

---

## Related Files

- `/data/moop/tools/display/organism_display.php` - Single organism display/search
- `/data/moop/tools/display/groups_display.php` - Group display/search
- `/data/moop/tools/search/annotation_search_ajax.php` - Search endpoint
- `/data/moop/tools/common_functions.php` - Helper functions
- `/data/moop/tools/display/shared_results_table.js` - Results table creation
- `/data/moop/tools/display/shared_results_table.css` - Results table styling
- `/data/moop/access_control.php` - Permission checking

---

## Summary

Both display pages provide powerful search capabilities with different scopes:

- **organism_display.php**: Fast, focused search within a single organism
- **groups_display.php**: Comprehensive search across multiple related organisms

They share the same backend logic and display components, ensuring consistency while optimizing for their specific use cases. The two-phase search strategy (ID first, then annotations) provides both speed and thoroughness.
