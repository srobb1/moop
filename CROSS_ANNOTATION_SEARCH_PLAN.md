# Cross-Annotation Search Feature Plan

**Date:** January 27, 2026  
**Status:** Planning Phase

## Problem Statement

Currently, MOOP cannot search for sequences that have annotations from MULTIPLE different sources (e.g., find genes that have BOTH a Pfam domain AND a Gene Ontology term). The search looks within individual annotation fields, so results must have all search terms in the same field.

**Example that fails:**
- User selects both "Gene Ontology" and "Pfam" sources
- Searches for: `PF00055 GO:0005886`
- Expected: Find sequences with PF00055 annotation AND GO:0005886 annotation
- Actual: No results (because it looks for both in same field)
- Sequence that should match: `ACA1_PVKU01000001.1_000004.1` (has both annotations, but in separate fields)

## Solution Options

### Option 1: Simple AND Logic (Easiest)
**Concept:** When multiple annotation sources are selected, create an AND query instead of combining into one field.

**How it would work:**
- User searches for `protein kinase` with both "Gene Ontology" and "Pfam" selected
- Query: Find sequences with (`GO annotation contains "protein kinase"`) AND (`Pfam annotation contains "protein kinase"`)
- Would find sequences that have the term in either annotation type

**Pros:**
- Relatively simple to implement
- Works with current search infrastructure
- User-friendly

**Cons:**
- Still limited (needs same term in both)
- Doesn't allow independent searches (GO ID + Pfam ID)

### Option 2: Advanced Multi-Source Search Tool (Recommended)
**Concept:** Create a dedicated "Advanced Multi-Source Search" tool that allows separate queries for each annotation source.

**Architecture:**
```
/data/moop/tools/multi_source_search.php  (entry point)
├── /tools/pages/multi_source_search.php  (UI template)
├── /js/multi_source_search.js           (search logic)
└── /lib/multi_source_queries.php        (database queries)
```

**Features:**
1. **Query Builder UI:**
   - Multiple rows, each for a different annotation source
   - User selects: Source (GO, Pfam, InterPro, etc.) + Search term + Logical operator (AND/OR)
   - Can add/remove rows dynamically
   - Example:
     ```
     [Gene Ontology] [contains] [kinase] [AND]
     [Pfam] [contains] [PF00055] [OR]
     [InterPro] [contains] [domain]
     ```

2. **Database Logic:**
   - Run separate queries for each annotation source
   - Combine results based on logical operators
   - Handle AND/OR/NOT operations on result sets in PHP
   - Return sequences matching the criteria

3. **Result Display:**
   - Show which annotations matched for each sequence
   - Highlight which criteria were satisfied
   - Allow filtering/sorting by number of matches

**Pros:**
- Most flexible and powerful
- Allows independent searches by annotation ID
- Can handle complex boolean logic
- Future-proof for additional features

**Cons:**
- More complex to implement
- Potentially slower for large result sets
- Need to process results in PHP instead of pure SQL

### Option 3: Hybrid Approach (Best Balance)
**Concept:** Keep simple searches as they are, but add a "Multi-Source Query" option.

**Implementation:**
1. Keep existing search tool unchanged
2. Add new "Advanced Multi-Source Search" tool
3. From search results, offer "Refine with additional sources" option
4. User can further filter/combine results

**Workflow:**
1. Search for `kinase` in Gene Ontology → Get 500 results
2. Click "Refine with another source"
3. Add filter: `Pfam contains "PF00055"`
4. Results narrowed to sequences with both

## Recommended Approach: Option 2 (Advanced Multi-Source Search Tool)

### Implementation Plan

**Phase 1: Database Query Layer**
- Create `buildMultiSourceQuery()` function
- Handle separate queries for each source
- Implement AND/OR/NOT logic on result sets
- Cache common queries for performance

**Phase 2: Backend**
- Create `/tools/multi_source_search.php` entry point
- Handle form submission
- Route to appropriate queries
- Format results

**Phase 3: Frontend**
- Create `/tools/pages/multi_source_search.php` UI
- Dynamic query builder (add/remove rows)
- Source selector dropdown
- Operator selection (AND/OR/NOT)
- Results display with annotation highlighting

**Phase 4: Integration**
- Add menu item for "Advanced Multi-Source Search"
- Update help documentation
- Add examples to help pages
- Test with real annotation data

### Database Query Logic Pseudocode

```php
// For: (GO contains "kinase") AND (Pfam contains "PF00")
$queries = [
    'GO' => "SELECT feature_id FROM features WHERE annotation_source='GO' AND annotation LIKE '%kinase%'",
    'Pfam' => "SELECT feature_id FROM features WHERE annotation_source='Pfam' AND annotation LIKE '%PF00%'"
];

$results_go = executeQuery($queries['GO']);
$results_pfam = executeQuery($queries['Pfam']);

// AND logic: Find common IDs
$intersection = array_intersect($results_go, $results_pfam);

// Return features with intersection IDs
$final_results = fetchFeaturesByIds($intersection);
```

### Estimated Effort
- **Phase 1 (Database):** 2-3 hours
- **Phase 2 (Backend):** 2-3 hours
- **Phase 3 (Frontend):** 4-5 hours
- **Phase 4 (Integration & Testing):** 2-3 hours
- **Total:** 10-14 hours

### Performance Considerations
- Result sets could be large (thousands of sequences)
- AND queries will reduce results naturally
- Consider limiting results per source (e.g., max 5000 per query)
- Cache frequently used queries
- Consider pagination for large result sets

### Future Enhancements
1. Save/reuse common multi-source searches
2. Export result combinations
3. Visual Venn diagrams showing overlap
4. Timeline/version-aware queries (old vs new annotations)
5. Probability scoring based on annotation confidence

## Next Steps
1. Get user feedback on preferred approach
2. Review database performance implications
3. Create UI mockup for query builder
4. Start Phase 1 implementation
5. User testing and iteration

---

**Related Issue:** Cross-annotation searches currently not possible  
**Affected Users:** Researchers needing complex annotation queries  
**Priority:** Medium (useful for advanced researchers, not blocking basic functionality)
