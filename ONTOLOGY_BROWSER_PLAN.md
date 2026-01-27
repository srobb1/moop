# Ontology Browser Tool - Feature Plan

**Date:** January 27, 2026  
**Status:** Planning Phase  
**Priority:** Medium (useful for advanced analysis, not blocking)

## Overview

An interactive hierarchical browser for Gene Ontology (and other ontologies) that displays:
- Ontology terms organized in a tree structure
- Count of sequences/genes associated with each term (including children)
- Dynamic count updates as users navigate deeper into the tree
- Visual indication of which terms have associated data

## Problem Statement

Currently, users exploring Gene Ontology terms must:
1. Know the specific term they're looking for (or use external GO tools)
2. Run separate searches for each term to see how many sequences match
3. Manually track term hierarchies and relationships
4. Use external tools like AMIGO or QuickGO to browse GO

An Ontology Browser would provide an integrated tool within MOOP to explore terms and their prevalence in the database.

## User Stories

1. **As a researcher:** I want to browse GO terms and see which biological processes are most common in my selected organisms
2. **As a curator:** I want to identify gaps in annotation by seeing which terms have zero sequences associated
3. **As an analyst:** I want to explore the ontology hierarchy and see how term specificity affects sequence counts
4. **As a user:** I want to click a term and automatically search for all sequences with that annotation

## Proposed Architecture

### Option 1: Build from Scratch (Recommended)
**Concept:** Query the annotation database directly and build a dynamic tree

**Pros:**
- Full control over caching and performance optimization
- Can customize for MOOP's specific database structure
- Lightweight and integrated
- Can easily include/exclude organisms

**Cons:**
- Requires building tree structure and counting logic
- Need to handle circular references in ontologies
- More development time

### Option 2: Integrate GOtools
**Concept:** Use existing GOtools library as foundation

**Pros:**
- Don't reinvent the wheel
- Well-tested ontology parsing
- Standard GO format support

**Cons:**
- External dependency
- May be overkill for our needs
- Requires understanding another codebase
- Potential licensing/compatibility issues

### Option 3: Hybrid Approach
**Concept:** Use a lightweight ontology library (like Python's obonet) as data preparation, then build custom interface

**Pros:**
- Leverage existing parsing tools
- Custom UI optimized for MOOP
- Better control than full GOtools

**Cons:**
- Adds Python dependency
- More complex setup

## Recommended Approach: Option 1 (Build from Scratch)

### Data Structure

**Ontology Term Object:**
```php
{
    "term_id": "GO:0008150",
    "name": "biological_process",
    "definition": "...",
    "is_a": ["GO:0003674"],  // Parent terms
    "part_of": [],
    "regulates": [],
    "children": ["GO:0009987", "GO:0019538"],  // Child terms
    "sequence_count": 45203,  // Total sequences with this term (including children)
    "direct_count": 234,      // Only directly annotated to this term
    "level": 0,               // Depth in hierarchy
    "alt_ids": ["GO:old123"]  // Alternative IDs
}
```

### Data Sources

**Where to get ontology data:**
1. Import from OBO file format (standard ontology format)
2. Parse and store in database/cache
3. Calculate transitive closure (what's annotated to children affects parent count)

**Where to get annotation counts:**
- Query annotation tables for sequences with each term
- Cache results with TTL (ontology rarely changes)
- Filter by selected organisms

### System Components

#### 1. Backend - Ontology Data Layer

**Files:**
```
/data/moop/lib/ontology_functions.php
├── parseOntologyFile()           # Parse OBO format files
├── buildOntologyTree()            # Build hierarchical structure
├── getOntologyTerm()              # Get term by ID
├── getTermAncestors()             # Get all parent terms
├── getTermDescendants()           # Get all child terms
└── calculateTermCounts()          # Count sequences per term
```

**Database Tables:**
```sql
CREATE TABLE ontology_terms (
    term_id VARCHAR(20) PRIMARY KEY,
    ontology VARCHAR(50),           -- 'GO', 'CHEBI', etc
    name VARCHAR(255),
    definition TEXT,
    is_obsolete BOOLEAN,
    creation_date DATETIME
);

CREATE TABLE ontology_relationships (
    parent_term_id VARCHAR(20),
    child_term_id VARCHAR(20),
    relationship_type VARCHAR(50),  -- 'is_a', 'part_of', 'regulates'
    PRIMARY KEY (parent_term_id, child_term_id, relationship_type),
    FOREIGN KEY (parent_term_id) REFERENCES ontology_terms(term_id),
    FOREIGN KEY (child_term_id) REFERENCES ontology_terms(term_id)
);

CREATE TABLE ontology_term_counts (
    term_id VARCHAR(20),
    organism_id INT,
    direct_count INT,              -- Directly annotated
    total_count INT,                -- Including children
    last_updated DATETIME,
    PRIMARY KEY (term_id, organism_id),
    FOREIGN KEY (term_id) REFERENCES ontology_terms(term_id)
);
```

#### 2. Backend - API Endpoints

**AJAX endpoints:**
```
GET /tools/ontology_ajax.php?action=get_root_terms&ontology=GO
GET /tools/ontology_ajax.php?action=get_children&term_id=GO:0008150
GET /tools/ontology_ajax.php?action=get_term_info&term_id=GO:0008150
GET /tools/ontology_ajax.php?action=search_terms&query=kinase
GET /tools/ontology_ajax.php?action=get_term_counts&term_id=GO:0008150&organisms=org1,org2
```

**Response Format:**
```json
{
    "success": true,
    "data": {
        "term_id": "GO:0008150",
        "name": "biological_process",
        "definition": "Any process specifically pertinent to the functioning of an integrated living unit.",
        "direct_count": 234,
        "total_count": 45203,
        "children": [
            {
                "term_id": "GO:0009987",
                "name": "cellular_process",
                "total_count": 12034,
                "has_children": true
            },
            {
                "term_id": "GO:0019538",
                "name": "protein_metabolic_process",
                "total_count": 8901,
                "has_children": true
            }
        ]
    }
}
```

#### 3. Frontend - Interactive Tree

**Files:**
```
/data/moop/tools/ontology_browser.php          (entry point)
/data/moop/tools/pages/ontology_browser.php    (UI template)
/data/moop/js/ontology_browser.js              (tree logic)
/data/moop/css/ontology_browser.css            (styling)
```

**Features:**
- Expandable/collapsible tree nodes
- Count badges on each node
- Lazy loading (only load children when expanded)
- Search/filter functionality
- Visual indicators for leaf vs branch nodes
- Hover tooltips with term definitions

**UI Layout:**
```
┌─────────────────────────────────────────┐
│ Ontology Browser - Gene Ontology         │
├─────────────────────────────────────────┤
│ [Search box] [Filter] [Reset]           │
├─────────────────────────────────────────┤
│                                         │
│ ▼ biological_process (45,203)           │
│   ├─ ▼ cellular_process (12,034)        │
│   │  ├─ ▶ cell_adhesion (890)           │
│   │  ├─ ▶ cell_death (1,203)            │
│   │  └─ ▶ cell_differentiation (2,103)  │
│   ├─ ▼ metabolic_process (8,901)        │
│   │  ├─ ▶ protein_metabolism (5,203)    │
│   │  ├─ ▶ lipid_metabolism (1,203)      │
│   │  └─ ▶ carbohydrate_metabolism (890) │
│   └─ ▶ biological_regulation (15,203)   │
│                                         │
│ ▼ molecular_function (23,102)           │
│   ├─ ▶ binding (18,903)                 │
│   └─ ▶ catalytic_activity (12,103)      │
│                                         │
│ ▼ cellular_component (8,901)            │
│   ├─ ▶ cellular_anatomical (5,203)      │
│   └─ ▶ membrane (3,203)                 │
│                                         │
└─────────────────────────────────────────┘
```

#### 4. Integration Points

**From Search Results:**
- "Refine by GO term" link
- Pre-select term in browser, auto-search

**From Organism Page:**
- "View all GO annotations" button
- Opens browser pre-filtered to organism

**From Multi-Organism Analysis:**
- Browse GO terms across selected organisms
- Compare counts across organisms

### Implementation Phases

#### Phase 1: Core Browser (Estimated: 8-10 hours)
1. **Data Preparation:**
   - Create import script for GO OBO file
   - Parse and store in database (1-2h)
   - Calculate term counts for all sequences (2-3h)

2. **Backend:**
   - Create ontology query functions (2-3h)
   - Build API endpoints (1-2h)
   - Implement caching (1h)

3. **Frontend:**
   - Basic tree UI with expandable nodes (2-3h)
   - Lazy loading of children (1-2h)
   - Click handlers to trigger search (1h)

**Deliverable:** Basic ontology tree with counts, clickable to search

#### Phase 2: Enhanced Features (Estimated: 4-6 hours)
1. **Search & Filter:**
   - Search by term name/ID (1-2h)
   - Filter by count range (1h)
   - Highlight search results (1h)

2. **Visualization:**
   - Term definitions in tooltips (1h)
   - Color coding by count level (1h)
   - Breadcrumb navigation (1h)

3. **Performance:**
   - Further caching optimization (1h)
   - Query optimization (1h)

**Deliverable:** Searchable, filtered browser with enhanced UX

#### Phase 3: Integration & Documentation (Estimated: 3-4 hours)
1. **Integration:**
   - Add browser to search results (1h)
   - Add to organism page (1h)
   - Add to multi-organism analysis (1h)

2. **Documentation:**
   - Help page (1h)
   - Inline tooltips (30min)
   - Usage examples (30min)

**Deliverable:** Fully integrated with documentation

#### Phase 4: Advanced Features (Estimated: 4-6 hours) - Optional
1. **Comparative View:**
   - Compare term counts across organisms (2-3h)

2. **Export:**
   - Export selected terms and counts (1-2h)

3. **Statistics:**
   - Term usage distribution (1h)
   - Richness/diversity metrics (1h)

**Deliverable:** Advanced analytics and comparison

**Total Effort: 19-26 hours** (Phase 1-3 essential; Phase 4 optional for v1.0)

### OBO File Format Handling

**What we need to parse:**
```
[Term]
id: GO:0008150
name: biological_process
def: "Any process specifically pertinent to the functioning of an integrated living unit." [GOC:gs]
is_a: GO:0003674 ! molecular_function
relationship: part_of GO:0008150

[Term]
id: GO:0009987
name: cellular_process
is_a: GO:0008150 ! biological_process
```

**Import Strategy:**
1. Download GO OBO file from http://purl.obolibrary.org/obo/go.obo
2. Parse line-by-line into term objects
3. Build parent/child relationships
4. Store in database
5. Calculate transitive closure (what terms have annotations through children)

### Count Calculation Strategy

**Transitive Closure Problem:**
When a sequence is annotated with GO:0009987 (cellular_process), it should count toward:
- GO:0009987 (direct)
- GO:0008150 (biological_process - parent)
- All ancestors up the tree

**Solution:**
1. Calculate once on import/update
2. Store both direct and total counts
3. Cache results by organism
4. Update only when new annotations added

```sql
SELECT COUNT(DISTINCT f.feature_id)
FROM feature_annotation fa
JOIN annotation a ON fa.annotation_id = a.annotation_id
JOIN annotation_source ans ON a.annotation_source_id = ans.annotation_source_id
WHERE ans.annotation_source_name = 'Gene Ontology'
AND (
    -- Either direct match or parent match
    a.annotation_accession = 'GO:0008150'
    OR a.annotation_accession IN (
        SELECT child_term_id FROM ontology_relationships
        WHERE parent_term_id = 'GO:0008150'
        UNION ALL
        SELECT grandchild FROM recursive_descendants
    )
)
AND f.organism_id = ?
```

### Performance Considerations

**Scale:**
- GO has ~45,000 terms
- Most sequences have 1-50 GO annotations
- Tree depth: typically 8-15 levels

**Optimization:**
- Load only root terms initially
- Lazy load children on expand
- Cache counts with 1-hour TTL
- Use materialized view for term ancestors
- Pre-calculate all counts on import

**Query Optimization:**
- Index on: term_id, organism_id, parent/child relationships
- Avoid recursive queries in real-time (pre-calculate)
- Batch queries when possible

### UI/UX Considerations

**Visual Hierarchy:**
- Root terms always expanded
- Color intensity based on count (white = 0, blue = high)
- Term names truncated if too long (hover for full)
- Indent levels show hierarchy depth

**Interactions:**
- Double-click to search that term
- Single-click to expand/collapse
- Right-click context menu: "Search", "View children", "Copy ID"
- Breadcrumb trail showing current path

**Accessibility:**
- Keyboard navigation (arrow keys, enter to expand)
- Screen reader compatible
- High contrast mode support
- Mobile responsive (tree collapses to mobile view)

## Future Enhancements

1. **Multi-Ontology Support:**
   - CHEBI (Chemical Entities of Biological Interest)
   - MESH (Medical Subject Headings)
   - Custom user-defined ontologies

2. **Comparative Visualization:**
   - Venn diagrams showing term overlap
   - Term usage trends over time
   - Organism clustering by GO profile

3. **Advanced Analytics:**
   - Term enrichment analysis
   - Statistical significance testing
   - Machine learning for term prediction

4. **Export & Integration:**
   - Export term lists with counts
   - Integration with R/Python analysis
   - API access for external tools

5. **Customization:**
   - User-saved term selections
   - Custom term highlighting
   - Term relationship filters (show only "is_a", exclude "part_of", etc.)

## Technical Decisions

**Database vs File-based:**
- Decision: Database (SQLite)
- Rationale: Enables organism-specific filtering, caching, fast queries

**Real-time vs Pre-calculated:**
- Decision: Pre-calculated with caching
- Rationale: Ensures responsive UI, ontology structure stable

**Tree Library:**
- Decision: Custom JavaScript (no external dependency)
- Rationale: Simple recursive structure, full control, lightweight

**Ontology Format:**
- Decision: OBO format (standard)
- Rationale: Compatible with all major ontologies, well-documented parsing

## Success Criteria

- [ ] Users can browse GO hierarchy interactively
- [ ] Counts accurately reflect sequences per term (including children)
- [ ] Browser loads in < 1 second for typical interaction
- [ ] Search finds terms quickly (< 200ms)
- [ ] Clicking a term triggers relevant sequence search
- [ ] Works with any organism or organism group
- [ ] Integrates seamlessly with existing search results
- [ ] Documentation clear and helpful
- [ ] Mobile-responsive design

## Risk Mitigation

**Risk:** Ontology file too large to parse/store
- **Mitigation:** Stream processing, store only terms with annotations

**Risk:** Term counts incorrect due to circular relationships
- **Mitigation:** Validate ontology on import, unit tests for transitive closure

**Risk:** Performance degradation with many organisms
- **Mitigation:** Cache aggressively, organism-specific aggregation tables

**Risk:** Ontology updates break system
- **Mitigation:** Version tracking, update script with validation, rollback capability

## Next Steps

1. Get approval on architecture and scope
2. Obtain GO OBO file and analyze structure
3. Design database schema for term storage
4. Implement Phase 1 (core browser)
5. User testing and feedback
6. Iterate based on feedback
7. Complete remaining phases

---

**Related Features:** Search & Filter, Multi-Organism Analysis, Advanced Filtering  
**Affected Users:** Researchers, bioinformaticians, curators  
**Priority:** Medium (useful for exploration, nice-to-have for v1.0)  
**Budget:** 19-26 hours for full implementation (v1.0 with phases 1-3)

---

## REVISED: Separate Ontology Databases (Better Approach)

**Note:** This updated approach uses separate SQLite files for each ontology instead of adding tables to organism.sqlite.

### Why Separate Databases?

1. **No organism.sqlite pollution** - keeps annotation data separate from ontology structure
2. **Independent updates** - update GO without touching organism data
3. **Scalable** - add new ontologies without modifying main database
4. **Cleaner** - each database has single responsibility
5. **Reusable** - ontology files could be shared across MOOP instances

### Revised Architecture

```
/data/moop/metadata/ontologies/
├── go.sqlite              # Gene Ontology
├── chebi.sqlite           # Chemical Entities
├── mesh.sqlite            # Medical Subject Headings
└── custom.sqlite          # User-defined
```

### Database Schema (per ontology)

Each ontology.sqlite contains ONLY ontology structure:

```sql
-- go.sqlite
CREATE TABLE terms (
    term_id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(255),
    definition TEXT,
    is_obsolete BOOLEAN,
    created_date DATETIME
);

CREATE TABLE relationships (
    parent_term_id VARCHAR(20),
    child_term_id VARCHAR(20),
    relationship_type VARCHAR(50),  -- 'is_a', 'part_of', 'regulates'
    PRIMARY KEY (parent_term_id, child_term_id, relationship_type)
);

CREATE TABLE root_terms (
    term_id VARCHAR(20) PRIMARY KEY
);

CREATE INDEX idx_parent ON relationships(parent_term_id);
CREATE INDEX idx_child ON relationships(child_term_id);
```

### How Counts Work

Counts are calculated ON-DEMAND by querying BOTH databases:

```php
function getTermCounts($term_id, $ontology, $organism_ids) {
    // Get ontology database
    $ont_db = OntologyManager::getDatabase($ontology);
    
    // Get all descendants from ontology DB
    $descendants = getDescendantTerms($term_id, $ont_db);
    $all_terms = array_merge([$term_id], $descendants);
    
    // Query organism.sqlite for sequences with these annotations
    $org_db = new SQLite3(ORGANISM_DB_PATH, SQLITE3_OPEN_READONLY);
    $query = "SELECT COUNT(DISTINCT f.feature_id)
              FROM feature_annotation fa
              JOIN annotation a ON fa.annotation_id = a.annotation_id
              WHERE a.annotation_accession IN (" . implode(',', array_fill(0, count($all_terms), '?')) . ")
              AND fa.organism_id IN (" . implode(',', $organism_ids) . ")";
    
    return $org_db->querySingle($query, $all_terms);
}
```

### Connection Management

```php
class OntologyManager {
    private static $connections = [];
    
    public static function getDatabase($ontology) {
        if (!isset(self::$connections[$ontology])) {
            $path = "/data/moop/metadata/ontologies/$ontology.sqlite";
            self::$connections[$ontology] = new SQLite3($path, SQLITE3_OPEN_READONLY);
        }
        return self::$connections[$ontology];
    }
}
```

### Import Script

New utility to create/update ontology databases:

```php
// /data/moop/scripts/import_ontology.php
// Usage: php import_ontology.php GO /path/to/go.obo
// Creates: /data/moop/metadata/ontologies/go.sqlite

$ontology_name = $argv[1];  // 'GO', 'CHEBI', 'MESH'
$obo_file = $argv[2];       // Path to OBO file

$db = createOntologyDatabase($ontology_name);
parseOboFile($obo_file, $db);
buildIndices($db);
echo "Created ontologies/$ontology_name.sqlite\n";
```

### Advantages Over Single Database

| Aspect | Single DB | Separate DBs |
|--------|-----------|--------------|
| organism.sqlite size | Larger | Smaller |
| Ontology updates | Touch organism DB | Independent |
| Adding new ontology | Modify schema | Just add file |
| Query complexity | Single DB joins | Two DB queries |
| Connection overhead | None | Minimal (cached) |
| Scalability | Limited | Excellent |

### Performance Impact

**Negligible:**
- Open ontology DB once per session (connection pooling)
- Ontology queries are simple (single DB, no joins)
- Organism queries unchanged
- Caching mitigates any cross-DB overhead

### Caching Strategy

```php
$cache_key = md5("term_counts_$term_id" . implode("_", $organism_ids));

if ($cached = cache_get($cache_key)) {
    return $cached['count'];
}

// Calculate count (queries both databases)
$count = getTermCounts($term_id, $ontology, $organism_ids);

// Cache for 1 hour
cache_set($cache_key, ['count' => $count], 3600);

return $count;
```

### Revised Folder Structure

```
/data/moop/
├── metadata/
│   ├── organisms/           (existing)
│   │   ├── organism1.json
│   │   └── organism2.json
│   └── ontologies/          (NEW)
│       ├── go.sqlite        (NEW)
│       ├── go.obo           (backup)
│       ├── chebi.sqlite     (future)
│       └── README.md        (ontology docs)
├── lib/
│   ├── ontology_functions.php  (NEW)
│   └── ...
├── tools/
│   ├── ontology_browser.php    (NEW)
│   ├── ontology_ajax.php       (NEW)
│   └── pages/
│       └── ontology_browser.php (NEW)
├── scripts/
│   └── import_ontology.php     (NEW)
├── js/
│   └── ontology_browser.js     (NEW)
└── css/
    └── ontology_browser.css    (NEW)
```

### Updated Implementation (No changes to phases)

Still **Phase 1: 8-10 hours**
- Parse OBO → create go.sqlite (1-2h)
- Query functions, connection pooling (1-2h)
- API endpoints (1h)
- Tree UI (2-3h)

**Key difference:** 
- Functions query TWO databases instead of one
- Connection caching eliminates overhead
- No organism.sqlite schema changes needed

