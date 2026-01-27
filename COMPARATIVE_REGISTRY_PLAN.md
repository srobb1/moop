# Comparative Registry Tool - Feature Plan

**Date:** January 27, 2026  
**Status:** Planning Phase  
**Priority:** Medium (enhancement, not blocking)

## Overview

A tool that allows users to view and compare organism metadata side-by-side for multiple organisms. Useful for researchers comparing evolutionary relationships, genome characteristics, and annotation coverage across species.

## Problem Statement

Currently, to compare organisms, users must:
1. Visit each organism page individually
2. Manually note down key information
3. Compare the information externally

A Comparative Registry would provide a streamlined way to see organism metadata in a single, sortable table view.

## User Stories

1. **As a researcher studying evolution:** I want to compare genome sizes and GC content across 10 related organisms to understand evolutionary trends
2. **As a curator:** I want to see which organisms have complete annotations for GO terms vs Pfam domains to identify gaps
3. **As a user building a dataset:** I want to select organisms from a tree and quickly view their key metadata to make informed selections
4. **As an analyst:** I want to export organism metadata comparison as CSV for further statistical analysis

## Proposed Features

### 1. Multi-Organism Metadata Selection

**How it works:**
- User navigates to "Comparative Registry" tool
- Uses Tree Select to choose multiple organisms
- Or selects an entire group/clade at once
- Can select 2-100 organisms (UI/performance constraints)

**UI:**
- Similar to multi-organism search interface
- Sidebar showing selected organisms
- "View Comparison" button when ready

### 2. Comparison Table View

**Display Format:**
```
Organism          Genus         Species        Common Name    Genome Size   GC %    # Genes   GO Terms   Pfam    Last Updated
---------------------------------------------------------------------------
Org 1             Genus1        species1       Name1          2.5 Mb        52%     5,243     4,102      3,421   Jan 2026
Org 2             Genus2        species2       Name2          3.1 Mb        48%     6,105     5,203      4,012   Dec 2025
Org 3             Genus3        species3       Name3          2.8 Mb        51%     5,680     4,901      3,801   Dec 2025
```

**Metadata Columns (user-selectable):**

#### Taxonomy Section
- [ ] Genus
- [ ] Species
- [ ] Subspecies/Strain
- [ ] Common Name
- [ ] Taxonomic Kingdom/Phylum/Class/Order/Family

#### Genome Information Section
- [ ] Genome Size (bp)
- [ ] GC Content (%)
- [ ] Number of Chromosomes
- [ ] Ploidy Level
- [ ] Assembly Accession

#### Feature Statistics Section
- [ ] Total Features/Genes
- [ ] Protein-coding Genes
- [ ] RNA Genes
- [ ] Predicted vs Validated Features

#### Annotation Coverage Section
- [ ] Features with GO Annotations (count + %)
- [ ] Features with Pfam Domains (count + %)
- [ ] Features with InterPro (count + %)
- [ ] Features with BLAST hits (count + %)
- [ ] Average Annotation per Feature

#### Sequencing/Assembly Section
- [ ] Assembly Accession
- [ ] Sequencing Platform
- [ ] Coverage Depth
- [ ] Assembly Version
- [ ] Assembly Date

#### Database Management Section
- [ ] Last Updated (MOOP database)
- [ ] Data Source Version
- [ ] Annotation Completeness (%)
- [ ] Number of Revisions

#### Links
- [ ] Link to Organism Page
- [ ] Link to Genome Assembly
- [ ] Download Options

### 3. User Customization

**Column Selection:**
- Default set of 6-8 columns
- User can add/remove columns via checkbox modal
- Column preferences saved to session/localStorage
- Export preferences for future sessions

**Sorting & Filtering:**
- Click column headers to sort (ascending/descending)
- Filter by text in any column
- Min/Max range filters for numeric columns (genome size, GC %, etc.)
- Color coding for values (e.g., low/med/high annotation coverage)

**Grouping:**
- Optional: Group by genus or higher taxonomy
- Collapse/expand groups
- Summary rows showing min/max/avg for numeric columns

### 4. Data Export

**Export Formats:**
- CSV (all selected metadata)
- Excel (.xlsx) with formatting and charts
- JSON (machine-readable format)
- Tab-separated TSV for easy import to R/Python

**Export Options:**
- Export visible rows only (after filtering/sorting)
- Export all selected organisms
- Include/exclude column headers
- Include/exclude organism images

### 5. Visualizations

**Optional Future Enhancements:**
- Scatter plot: Genome size vs GC content
- Bar chart: Annotation coverage comparison
- Heatmap: Metadata comparison (normalized values)
- Tree view: Show phylogenetic relationships with metadata overlays

### 6. Integration Points

**From Tree Select:**
- "Compare Selected" button in sidebar
- Pre-populate Comparative Registry with selected organisms

**From Group Page:**
- "Compare All Organisms" button
- "Compare Selected" when checkboxes are used

**From Organism Page:**
- "Compare with Other Organisms" link
- Pre-selects current organism + 5 related organisms

### 7. Help & Documentation

**Include in help:**
- When to use Comparative Registry
- How to select organisms
- Column explanations and definitions
- Tips for comparative analysis
- How to export and use results externally

## Technical Implementation

### Backend

**New Files:**
```
/data/moop/tools/comparative_registry.php       (entry point)
├── /tools/pages/comparative_registry.php        (UI template)
├── /lib/comparative_registry_queries.php        (database queries)
└── /js/comparative_registry.js                  (frontend logic)
```

**Database Query Functions:**
- `getOrganismMetadata($organism_ids)` - Get all metadata for organisms
- `getAnnotationCoverage($organism_ids)` - Calculate annotation percentages
- `getGenomeStats($organism_ids)` - Fetch genome information
- `getSequencingInfo($organism_ids)` - Assembly and sequencing details

**API Endpoint:**
```php
GET /tools/comparative_registry_ajax.php?organisms=org1,org2,org3&fields=genome_size,gc_content,genes,go_terms
```

### Frontend

**Reusable Components:**
- Organism selector (already exists in tree select)
- Metadata table with sorting/filtering
- Column customization modal
- Export options menu

**Libraries:**
- DataTables for advanced table functionality (already used elsewhere)
- Chart.js for visualizations (optional)
- SheetJS for Excel export

### Data Source

**Where to get metadata:**
- `organism.json` files (taxonomy, common name)
- Database schema (feature counts)
- `genome.sqlite` tables (assembly info)
- Annotation tables (coverage calculations)

## Implementation Phases

### Phase 1: Core Functionality (Estimated: 6-8 hours)
1. **Backend:**
   - Create entry point `/tools/comparative_registry.php`
   - Implement `getOrganismMetadata()` function
   - Create basic metadata queries
   - Build API endpoint

2. **Frontend:**
   - Create organism selection interface
   - Build basic comparison table
   - Implement sorting and basic filtering
   - Add CSV export

**Deliverable:** Functional tool showing core organism metadata (12 key columns)

### Phase 2: Enhanced Features (Estimated: 4-6 hours)
1. **Customization:**
   - Column selection modal
   - Save/restore column preferences
   - Advanced filtering (range selectors)
   - Color coding for values

2. **Export Options:**
   - Excel export with formatting
   - JSON export
   - TSV export

**Deliverable:** Customizable, exportable comparison tool

### Phase 3: Integration & Polish (Estimated: 3-4 hours)
1. **Integration:**
   - Add "Compare" buttons to tree select
   - Add buttons to group page
   - Add buttons to organism page
   - Pre-populate with related organisms

2. **Documentation:**
   - Add help page section
   - Add inline tooltips
   - Create usage examples
   - Document metadata definitions

**Deliverable:** Fully integrated tool with documentation

### Phase 4: Visualizations (Estimated: 4-5 hours) - Optional
1. Scatter plots for correlation analysis
2. Bar charts for coverage comparison
3. Heatmaps for normalized values
4. Interactive charts with drill-down

**Deliverable:** Visual comparison capabilities

**Total Effort: 17-23 hours** (Phase 1-3 essential; Phase 4 optional for v1.0)

## User Interface Mockup

### Step 1: Organism Selection
```
┌─────────────────────────────────────────┐
│ Comparative Registry - Select Organisms │
├─────────────────────────────────────────┤
│                                         │
│  [Tree Select Interface]    [Selected]  │
│                             ┌────────┐  │
│  □ Kingdom A               │Org 1   │  │
│   ├─ □ Phylum B1           │Org 2   │  │
│   │  ├─ □ Org 1 ✓          │Org 3   │  │
│   │  ├─ □ Org 2 ✓          │        │  │
│   │  └─ □ Org 3 ✓          │ Clear  │  │
│   └─ □ Phylum B2           │        │  │
│                             └────────┘  │
│                                         │
│               [View Comparison]         │
└─────────────────────────────────────────┘
```

### Step 2: Comparison Table
```
┌──────────────────────────────────────────────────────────────────┐
│ Comparative Registry Results                                     │
├──────────────────────────────────────────────────────────────────┤
│ [Column Selector] [Filter] [Sort] [Export]                      │
├────────────┬─────────┬──────────┬──────────┬──────────┬──────────┤
│ Organism   │ Genus   │ Genome   │ GC %     │ GO Terms │ Pfam     │
│            │         │ Size     │          │ (%)      │ (%)      │
├────────────┼─────────┼──────────┼──────────┼──────────┼──────────┤
│ Org 1      │ Genus1  │ 2.5 Mb   │ 52%      │ 78%      │ 65%      │
├────────────┼─────────┼──────────┼──────────┼──────────┼──────────┤
│ Org 2      │ Genus2  │ 3.1 Mb   │ 48%      │ 85%      │ 73%      │
├────────────┼─────────┼──────────┼──────────┼──────────┼──────────┤
│ Org 3      │ Genus3  │ 2.8 Mb   │ 51%      │ 82%      │ 70%      │
└────────────┴─────────┴──────────┴──────────┴──────────┴──────────┘
```

## Performance Considerations

**Scale Limitations:**
- Support 2-100 organisms (reasonable limit for comparison)
- Pre-calculate metadata on organism import
- Cache comparison results (5 min TTL)
- Lazy-load visualizations

**Query Optimization:**
- Use indexed queries on organism_id
- Batch calculations where possible
- Consider materializing annotation coverage percentages

**UI Performance:**
- Virtual scrolling for 100+ row tables
- Lazy-load images (organism photos)
- Debounce filter inputs
- Client-side sorting (after initial load)

## Future Enhancements

1. **Phylogenetic Integration:**
   - Show organisms on phylogenetic tree
   - Color-code by metadata values
   - Branch-level statistics

2. **Temporal Tracking:**
   - Compare across different data release versions
   - Show annotation growth over time
   - Track assembly improvements

3. **Correlation Analysis:**
   - Identify which metadata correlates with annotation coverage
   - Statistical significance testing
   - Automated insights/recommendations

4. **Custom Metadata:**
   - Allow users to add custom organism attributes
   - Share comparisons with colleagues
   - Save comparison templates

5. **Integration with Other Tools:**
   - Link to Multi-Organism Search with same organisms pre-selected
   - Export comparison to BLAST tool
   - Generate report documents

## Success Criteria

- [ ] Tool displays metadata for selected organisms in tabular format
- [ ] Users can customize visible columns
- [ ] Sorting works on all columns
- [ ] Filtering shows relevant results
- [ ] Export produces valid CSV/Excel/JSON
- [ ] Performance acceptable for 100 organisms
- [ ] Documentation complete and clear
- [ ] Integration points working from tree select and group page
- [ ] Users report it saves significant comparison time

## Risk Mitigation

**Risk:** Performance issues with large organism counts
- **Mitigation:** Implement query caching, pagination, virtual scrolling

**Risk:** Confusing column definitions
- **Mitigation:** Tooltips, help page, inline documentation

**Risk:** Metadata inconsistencies across organisms
- **Mitigation:** Data validation on import, null value handling, clear "N/A" indicators

**Risk:** Export format issues
- **Mitigation:** Thorough testing with external tools (Excel, R, Python)

## Next Steps

1. Review and approve plan
2. Create detailed UI mockups
3. Design database schema for cached metadata
4. Implement Phase 1 (core functionality)
5. User testing and feedback
6. Iterate on design based on feedback
7. Complete remaining phases

---

**Related Feature:** Multi-Organism Analysis  
**Affected Users:** Comparative biologists, phylogenetics researchers, data curators  
**Priority:** Medium (useful enhancement, not critical for core functionality)  
**Budget:** 17-23 hours for full implementation (v1.0 with phases 1-3)
