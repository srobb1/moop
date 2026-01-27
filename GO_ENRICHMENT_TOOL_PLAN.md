# GO Enrichment Analysis Tool - Implementation Plan

## Overview
Create a web-based GO (Gene Ontology) enrichment analysis tool for statistical enrichment analysis. This tool will allow users to submit a study gene set and perform analysis against population data with associated GO terms.

**Two Implementation Approaches:**
1. **Integration Approach**: Use goatools library (simpler, proven)
2. **Custom Approach**: Build custom enrichment logic leveraging new ontology databases (more control, integrates better with MOOP)

This plan covers both options with recommendation to start with goatools, then migrate to custom approach.

---

## Implementation Approaches Comparison

### Approach 1: goatools Integration (Recommended for v1.0)

**Pros:**
- Well-tested, peer-reviewed library
- Multiple statistical methods built-in
- Fast implementation (goatools does the heavy lifting)
- Proven results, easy validation

**Cons:**
- Python dependency required
- External library maintenance
- Less integration with MOOP's ontology browser
- P-values already calculated (less control)

### Approach 2: Custom PHP Implementation (Recommended for v2.0)

**Pros:**
- Full control over algorithms
- Integrates directly with new ontology databases
- No external dependencies
- Can leverage MOOP's caching strategy
- Easier to customize statistics
- Works with Ontology Browser tool

**Cons:**
- More development time (~20-30 hours)
- Must implement statistical functions correctly
- Need careful validation against known results

**Synergy with Ontology Browser:**
- Both use `/data/moop/metadata/ontologies/go.sqlite`
- Reuse `ontology_functions.php` code
- Share term descent logic and caching
- Custom approach can use precalculated counts from browser

---

## 1. Architecture & File Structure

### Backend Files to Create (Approach 1 - goatools)
```
/data/moop/
├── tools/
│   └── go_enrichment.php                    # Main tool interface & form
├── lib/
│   ├── go_enrichment_functions.php          # Core enrichment logic
│   └── go_enrichment_results_parser.php     # Parse & format goatools output
├── scripts/
│   ├── run_go_enrichment.py                 # Python wrapper for goatools
│   └── go_enrichment_requirements.txt       # Python dependencies
└── data/
    └── go_enrichment/                       # Temporary working directory
        ├── results/                         # Results storage
        ├── input/                           # Uploaded input files
        └── associations/                    # Pre-built GO associations per assembly
```

### Backend Files to Create (Approach 2 - Custom PHP)
```
/data/moop/
├── tools/
│   └── go_enrichment.php                    # Main tool interface & form
├── lib/
│   ├── go_enrichment_functions.php          # Custom enrichment logic
│   ├── ontology_functions.php               # Reused from Ontology Browser
│   └── go_enrichment_statistics.php         # Statistical functions
├── scripts/
│   └── precalculate_go_stats.php            # Pre-compute p-values (optional)
└── data/
    └── go_enrichment/
        └── results/                         # Results storage
```

**Key Difference:** 
- Approach 1: Uses Python/goatools (needs associations file)
- Approach 2: Queries go.sqlite directly, calculates stats in PHP

### Key Integration Points
- Register tool in `/data/moop/config/tools_config.php`
- Leverage existing tool initialization from `tool_init.php`
- Use existing permission/access control system
- Store results with expiration cleanup (like BLAST tool)

---

## 2. Backend Implementation

### 2.1 Installation & Dependencies (Approach 1 - goatools only)
**File:** `scripts/go_enrichment_requirements.txt`
```
goatools==1.3.3
openpyxl==3.11.2
```

**Installation command:**
```bash
pip install -r scripts/go_enrichment_requirements.txt
```

**Note:** Approach 2 (custom PHP) requires NO external dependencies beyond PHP/SQLite.

### 2.2 Core Functions (Approach 1 - goatools)

#### Functions to implement:
1. **`prepareEnrichmentInput()`**
   - Validate study gene list (one ID per line)
   - Retrieve population for selected organism(s)/assembly(ies)
   - Generate association file from database

2. **`runGoEnrichmentAnalysis()`**
   - Execute Python script with user parameters
   - Handle timeouts and errors
   - Return result file path

3. **`parseEnrichmentResults()`**
   - Read Excel output from goatools
   - Extract key statistics: GO term, description, p-value, study genes, population genes
   - Format for HTML display and JSON export

4. **`getGoAssociationData()`**
   - Query database for ID → GO term mappings
   - Build association file in goatools format
   - Cache for each organism/assembly combination

5. **`getPopulationIds()`**
   - Retrieve all gene IDs for selected organism/assembly
   - Return as text file for goatools

### 2.3 Python Script (Approach 1 - goatools)

```python
#!/usr/bin/env python3
"""
GO Enrichment Analysis Runner
Wraps goatools find_enrichment.py with error handling
"""
import sys
import json
from pathlib import Path
from goatools.cli.find_enrichment import main as run_enrichment

def run_analysis(study_file, population_file, association_file, 
                 pval, method, pval_field, output_file):
    """Execute GO enrichment analysis"""
    try:
        args = [
            study_file,
            population_file,
            association_file,
            f'--pval={pval}',
            f'--method={method}',
            f'--pval_field={pval_field}',
            f'--outfile={output_file}'
        ]
        sys.argv = ['find_enrichment.py'] + args
        run_enrichment()
        
        return {
            'success': True,
            'output_file': output_file,
            'message': 'Enrichment analysis completed successfully'
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e),
            'message': 'Enrichment analysis failed'
        }

if __name__ == '__main__':
    result = json.dumps(run_analysis(
        sys.argv[1], sys.argv[2], sys.argv[3],
        sys.argv[4], sys.argv[5], sys.argv[6], sys.argv[7]
    ))
    print(result)
```

---

## 2.4 Custom PHP Implementation (Approach 2 - Alternative)

### Architecture
Instead of Python, implement enrichment analysis directly in PHP using:
- `/data/moop/metadata/ontologies/go.sqlite` - ontology terms and relationships
- `organism.sqlite` - annotation data (GO term to gene mappings)
- Standard statistical functions (hypergeometric test, Fisher's exact test)

### Core Functions
**File:** `lib/go_enrichment_functions.php`

```php
/**
 * Custom GO Enrichment Analysis
 */

// Get study set gene IDs with GO annotations
function getStudyGeneAnnotations($gene_ids, $organism_db) {
    // Returns: ['GO:0008150' => ['GENE1', 'GENE2', ...], ...]
}

// Get population gene IDs with GO annotations
function getPopulationAnnotations($organism_ids, $organism_db) {
    // Returns: ['GO:0008150' => ['GENE1', 'GENE2', ...], ...]
}

// Get all descendants of a term (including itself)
function getTermDescendants($term_id, $ont_db) {
    // Query go.sqlite for all children recursively
    // Returns: [$term_id, $child1, $child2, ...]
}

// Hypergeometric test (Fisher's exact for 2x2 contingency)
function calculatePValue($study_count, $pop_count, $term_study, $term_pop) {
    /**
     * Hypergeometric distribution:
     * P(X >= k) = probability of observing k or more successes
     * 
     * study_count: total study genes
     * pop_count: total population genes
     * term_study: study genes with this term
     * term_pop: population genes with this term
     */
}

// Multiple hypothesis correction
function correctPValues($pvalues, $method = 'fdr_bh') {
    // 'fdr_bh': Benjamini-Hochberg
    // 'bonferroni': Simple Bonferroni
    // 'holm': Holm-Bonferroni
    // Returns corrected p-values
}

// Main enrichment analysis
function runEnrichmentAnalysis($study_ids, $organism_ids, $pval_threshold = 0.05) {
    $org_db = new SQLite3(ORGANISM_DB_PATH);
    $ont_db = OntologyManager::getDatabase('GO');
    
    // Get annotations
    $study_annot = getStudyGeneAnnotations($study_ids, $org_db);
    $pop_annot = getPopulationAnnotations($organism_ids, $org_db);
    
    // For each GO term in population
    $results = [];
    foreach (array_keys($pop_annot) as $term_id) {
        $descendants = getTermDescendants($term_id, $ont_db);
        
        // Count genes in study with this term or descendants
        $study_count = countGenesWithTerms($study_ids, $descendants, $org_db);
        $pop_count = countGenesWithTerms(array_keys($pop_annot), $descendants, $org_db);
        
        // Calculate p-value using hypergeometric test
        $pval = calculatePValue(
            count($study_ids),
            count(array_keys($pop_annot)),
            $study_count,
            $pop_count
        );
        
        $results[$term_id] = [
            'p_value' => $pval,
            'study_genes' => $study_count,
            'pop_genes' => $pop_count,
            'ratio' => $study_count / count($study_ids)
        ];
    }
    
    // Apply multiple testing correction
    $corrected = correctPValues(array_column($results, 'p_value'), 'fdr_bh');
    
    // Filter by threshold and sort
    foreach ($results as $term_id => &$result) {
        $result['p_value_corrected'] = $corrected[$term_id];
    }
    
    return array_filter($results, fn($r) => $r['p_value_corrected'] <= $pval_threshold);
}
```

### PHP Statistics Library
**File:** `lib/go_enrichment_statistics.php`

```php
class StatisticalFunctions {
    /**
     * Hypergeometric cumulative distribution function
     * P(X >= k) for hypergeometric distribution
     */
    public static function hypergeometricPValue($M, $N, $n, $k) {
        /**
         * M: total population size
         * N: number of success states in population (genes with term)
         * n: number of draws (study set size)
         * k: number of observed successes (study genes with term)
         */
    }
    
    public static function fisherExactTest($a, $b, $c, $d) {
        // 2x2 contingency table
        // [[a, b], [c, d]]
    }
    
    public static function benjaminiHochbergCorrection($pvalues) {
        // FDR correction
    }
    
    public static function bonferroniCorrection($pvalues) {
        // Simple Bonferroni
    }
    
    public static function holmCorrection($pvalues) {
        // Step-down Bonferroni
    }
}
```

### Advantages of Custom Approach
1. **No Python dependency** - everything PHP/SQLite
2. **Direct ontology access** - uses go.sqlite directly
3. **Precalculation possible** - cache term descendants, pre-compute stats
4. **Integration** - Ontology Browser can reuse same functions
5. **Customizable** - add MOOP-specific logic easily
6. **Validation** - results can be validated against goatools

### Disadvantages
1. **More development time** - implement statistics carefully
2. **Statistical correctness** - must validate p-value calculations
3. **Performance** - may need caching/optimization

### Migration Path
1. **v1.0:** Use goatools (faster to implement)
2. **v1.1:** Build custom implementation in parallel
3. **v2.0:** Switch to custom when validated, deprecate goatools

---

## 3. Frontend - Main Tool Page
**File:** `tools/go_enrichment.php`

#### Layout Sections:

1. **About Section** (Collapsible Card)
   - Explain what GO enrichment analysis is
   - Link to relevant resources
   - Brief methodology description
   - Citation information for goatools

2. **Input Form**
   ```
   ┌─ Study Gene List (required)
   │  └─ Large text input or file upload
   │     └─ Format: One gene ID per line
   │
   ├─ Parameters Panel
   │  ├─ P-value threshold: [0.05] (slider or input)
   │  ├─ Statistical method: [dropdown: fdr_bh, bonferroni, holm, etc]
   │  ├─ P-value field: [dropdown: fdr_bh, p_holm, etc]
   │  └─ Advanced options (collapsible)
   │
   ├─ Data Selection
   │  ├─ Organism: [multi-select dropdown or list]
   │  ├─ Assembly: [dependent dropdown - updates based on organism]
   │  └─ Or: Use all assemblies (checkbox)
   │
   └─ Submit Button
   ```

3. **Results Display**
   - Table showing:
     - GO Term ID (link to AmiGO database)
     - Description
     - p-value / adjusted p-value
     - Study genes count / Study genes (expandable list)
     - Population genes count / Significant ratio
   - Sort/filter capabilities
   - Download buttons

4. **Export Options**
   - Excel file (.xlsx) - from goatools output
   - CSV file - reformatted results
   - JSON file - for programmatic access

---

## 4. Data Preparation

### 4.1 Using New Ontology Database Structure

**New approach (v1.1+):** With separate ontology databases, data preparation simplifies:

```
organism.sqlite:
  - feature (genes/sequences)
  - annotation (GO terms associated with genes)
  - feature_annotation (join table)

go.sqlite:
  - terms (GO term metadata)
  - relationships (parent/child hierarchy)
```

**Association file building** (no longer needed with custom approach):
- Previously: Generate `associations.txt` files for goatools
- Now: Query go.sqlite for term hierarchy + organism.sqlite for genes
- Direct: No intermediate files needed

### 4.2 Population Building (Approach 2 - Custom)

```php
function getPopulationIds($organism_ids, $organism_db) {
    // Query organism.sqlite for all feature_ids
    // Returns array of gene IDs in selected organisms
}

function getPopulationGeneToTerms($organism_ids, $organism_db) {
    // Build: gene_id => [GO:0001, GO:0002, ...]
    // Includes all descendants (via go.sqlite hierarchies)
    
    $query = "
        SELECT DISTINCT f.feature_id, a.annotation_accession
        FROM feature f
        JOIN feature_annotation fa ON f.feature_id = fa.feature_id
        JOIN annotation a ON fa.annotation_id = a.annotation_id
        WHERE f.organism_id IN (" . implode(',', $organism_ids) . ")
        AND a.annotation_source_id = (
            SELECT annotation_source_id FROM annotation_source 
            WHERE annotation_source_name = 'Gene Ontology'
        )
    ";
    // Returns: {'GENE1': ['GO:0008150', 'GO:0009987', ...], ...}
}
```

### 4.3 Caching Strategy with Ontology Database

```php
// Cache key combines organism selections
$cache_key = md5("enrichment_pop_" . implode("_", sort($organism_ids)));

// Cache expires when:
// 1. Time-based (24 hours)
// 2. New annotations added
// 3. GO terms updated (go.sqlite version change)

if ($cached_pop = cache_get($cache_key)) {
    return $cached_pop;
}

$population = buildPopulation($organism_ids);
cache_set($cache_key, $population, 86400);  // 24 hours
```

---

## 5. Data Flow

### Approach 1 (goatools)
```
1. User visits /tools/go_enrichment.php
2. Selects organism(s)/assembly(ies)
3. Enters study gene list
4. Adjusts enrichment parameters (optional)
5. Clicks "Run Analysis"
   → Generate association file (organism.sqlite → associations.txt)
   → Call Python script (goatools find_enrichment)
   → Parse Excel output
6. Results displayed in table format
7. User can download results in multiple formats
```

### Approach 2 (Custom PHP)
```
1. User visits /tools/go_enrichment.php
2. Selects organism(s)/assembly(ies)
3. Enters study gene list
4. Adjusts enrichment parameters (optional)
5. Clicks "Run Analysis"
   → Query go.sqlite for GO hierarchy
   → Query organism.sqlite for gene-term mappings
   → Calculate p-values for each term
   → Apply multiple testing correction
6. Results displayed in table format
7. User can download results
```

### Backend Processing Comparison

| Step | Approach 1 (goatools) | Approach 2 (Custom) |
|------|----------------------|-------------------|
| Validate input genes | PHP | PHP |
| Build population list | PHP → file | PHP (cache) |
| Get GO associations | File generation | go.sqlite query |
| Calculate enrichment | Python goatools | PHP + stats lib |
| Parse results | Excel parsing | Direct array |
| Format output | HTML/JSON | HTML/JSON |

---

## 6. Parameter Reference

### Parameters Matching goatools find_enrichment.py:

| Parameter | Type | Default | Options | Description |
|-----------|------|---------|---------|-------------|
| pval | float | 0.05 | 0.01-0.1 | P-value significance threshold |
| method | dropdown | fdr_bh | bonferroni, holm, fdr_bh, fdr_by | Multiple testing correction method |
| pval_field | dropdown | fdr_bh | p_bonf, p_holm, fdr_bh, fdr_by | Which p-value column to use for filtering |

### Advanced Options:
- min_overlap: minimum study genes in GO term (default: 1)
- min_obo_support: minimum genes in population with GO term
- indent: GO hierarchy indentation in output

---

## 7. Results Display Strategy

### Table Display:
```
GO Term | Description | P-Value | Method | Study Count | Ratio | Study Genes
GO:0008150 | biological_process | 0.0001 | fdr_bh | 42 | 42/100 | [EXPAND]
```

### Interactive Features:
- Expand row to see full gene list
- Click GO term to open AmiGO or QuickGO
- Sort by any column
- Filter by p-value, description text
- Highlight significant terms (configurable threshold)

### Export Formats:
1. **Excel** - Direct from goatools (retain all columns)
2. **CSV** - Comma-separated for spreadsheet import
3. **JSON** - For programmatic access with full metadata
4. **TSV** - Tab-separated for R/Python analysis

---

## 8. Error Handling

### Input Validation:
- Gene list not empty
- Valid gene IDs in selected organism/assembly
- At least one gene in both study and population
- File size limits (e.g., max 10MB for upload)

### Processing Errors:
- Python subprocess timeout (set to 5-10 minutes)
- goatools execution failures
- Missing GO associations
- Insufficient overlap between study and population

### User-Friendly Error Messages:
- "No genes found in population for selected organism"
- "Gene IDs not recognized - please verify gene list"
- "Analysis timed out - try with fewer genes or simpler analysis"

---

## 9. Implementation Phases

### Phase 1: Foundation (Approach 1 - goatools, Week 1-2)
- [ ] Install goatools via pip
- [ ] Create Python wrapper script
- [ ] Build `go_enrichment_functions.php` with core logic
- [ ] Create `go_enrichment.php` with basic form
- [ ] Test with sample data

### Phase 2: Data Integration with Ontology Database
- [ ] Ensure go.sqlite is available (created by Ontology Browser tool)
- [ ] Build association file generator using go.sqlite
- [ ] Create caching mechanism
- [ ] Test goatools with new association source

### Phase 3: Results & Display (Week 3)
- [ ] Build results parser
- [ ] Create HTML table display
- [ ] Implement sorting/filtering
- [ ] Add download functionality

### Phase 4: Polish & Integration (Week 4)
- [ ] Register tool in tools_config.php
- [ ] Add styling and UX improvements
- [ ] Implement temporary file cleanup
- [ ] Add comprehensive help/documentation
- [ ] Performance optimization
- [ ] Security audit (input sanitization, permissions)

### Phase 5: Custom PHP Implementation (v2.0 - Future, Weeks 5-6)
- [ ] Implement `go_enrichment_statistics.php`
- [ ] Implement `StatisticalFunctions` class
- [ ] Implement custom enrichment logic
- [ ] Validate against goatools results
- [ ] Performance testing
- [ ] Migration testing
- [ ] Deprecate goatools approach

### Recommended Path
1. **v1.0:** Implement Phases 1-4 with goatools
2. **v1.1:** Add ontology database integration (Phase 2)
3. **v2.0:** Implement custom PHP (Phase 5), make default
4. **v2.1+:** Optional goatools removal (if custom proven reliable)

---

## 10. Security Considerations

### Input Validation:
- Sanitize all file inputs (whitelist allowed characters)
- Validate gene IDs format
- Limit study list size
- Check organism/assembly access permissions

### File Handling:
- Store results outside web root or with .htaccess protection
- Implement result file expiration (7-30 days)
- Validate uploaded files before processing
- Use temporary directories for processing

### Permission Model:
- Respect existing access control
- Only allow analysis on permitted organisms/assemblies
- Log enrichment analyses for audit trail
- Consider rate limiting to prevent abuse

---

## 11. Performance Optimization

### Caching Strategy:
- Cache GO association files per assembly
- Cache population IDs per organism/assembly
- Invalidate cache on data updates
- Pre-compute associations during low-traffic periods

### Processing:
- Run goatools in background for large analyses
- Use AJAX polling for progress updates
- Consider job queue system for very large datasets
- Implement analysis timeouts (prevent hung processes)

---

## 12. Testing Checklist

### Unit Tests:
- [ ] Input validation functions
- [ ] Association file generation
- [ ] Results parsing
- [ ] Permission checks

### Integration Tests:
- [ ] Full workflow with sample data
- [ ] Multiple organism/assembly combinations
- [ ] Different parameter settings
- [ ] Various error conditions

### User Acceptance Tests:
- [ ] Intuitive UI/UX
- [ ] Results accuracy against manual goatools
- [ ] Download functionality
- [ ] Mobile responsiveness

---

## 13. Documentation

### User Documentation:
- Tool purpose and use cases
- Step-by-step usage guide
- Parameter explanation
- Result interpretation guide
- FAQ and troubleshooting

### Developer Documentation:
- Code comments explaining logic
- Function documentation with parameters
- Database schema for GO data
- Deployment instructions

---

## 14. Future Enhancements

1. **Comparison Mode**: Compare enrichment between two study sets
2. **GO Subgraph Visualization**: Interactive GO tree display (use Ontology Browser)
3. **Term Clustering**: Group similar enriched GO terms
4. **Batch Analysis**: Process multiple gene lists in one job
5. **Publication Export**: Generate analysis summary for papers
6. **Integration with BLAST Results**: Directly enrich BLAST hit sets
7. **Custom GO Databases**: Allow user-provided annotation files
8. **Visualization Integration**: Link to Ontology Browser for term hierarchy visualization
9. **Statistical Rigor**: Add additional statistical methods (GSEA, hypergeometric, etc.)
10. **Caching/Precalculation**: Pre-compute p-values for common organisms

---

## Quick Start for Development

### Local Testing:
```bash
# Install dependencies
pip install -r scripts/go_enrichment_requirements.txt

# Test goatools
python3 -m goatools.cli.find_enrichment --help

# Create test data
echo "AT1G01010" > test_study.txt
echo "AT1G01010\nAT1G01020" > test_population.txt
echo "AT1G01010\tGO:0045449;GO:0005783" > test_associations.txt

# Run enrichment
python3 scripts/run_go_enrichment.py test_study.txt test_population.txt test_associations.txt 0.05 fdr_bh fdr_bh test_results.xlsx
```

---

## Configuration Constants (tools_config.php entry)

```php
'go_enrichment' => [
    'id'              => 'go_enrichment',
    'name'            => 'GO Enrichment Analysis',
    'icon'            => 'fa-sitemap',
    'description'     => 'Gene Ontology enrichment analysis for gene sets',
    'btn_class'       => 'btn-info',
    'url_path'        => '/tools/go_enrichment.php',
    'context_params'  => ['organism', 'assembly', 'organisms'],
    'pages'           => 'all',
],
```

---

## End of Plan

This plan provides a comprehensive roadmap for implementing a production-ready GO enrichment tool integrated with your MOOP system. Start with Phase 1 and move sequentially through phases.

---

## Appendix: Ontology Database Integration

### What Changed with Separate Ontology Databases

Previously, this plan assumed:
- All GO data would need to be in organism.sqlite
- Generate association files as intermediate step
- Simpler single database but cluttered schema

Now with new architecture:
- go.sqlite stores all GO term structure
- organism.sqlite stores only annotation mappings
- Better separation of concerns
- Cleaner schema in main database

### Synergy with Ontology Browser Tool

**Ontology Browser** also uses go.sqlite:
- Both tools query the same term hierarchy
- Both can cache descendant calculations
- Both benefit from precalculated statistics

**Shared Code:**
```php
// Both tools can use:
$ont_db = OntologyManager::getDatabase('GO');
$descendants = getTermDescendants($term_id, $ont_db);

// Ontology Browser uses for visualization
// GO Enrichment uses for p-value calculation
```

### Custom Approach Validation

When implementing Approach 2 (Custom PHP), validate against goatools:

```php
// For a set of test genes, run both:
$goatools_results = runGoatoolsEnrichment($test_study, $test_pop);
$custom_results = runCustomEnrichment($test_study, $test_pop);

// Compare:
foreach ($goatools_results as $term_id => $goatools_pval) {
    $custom_pval = $custom_results[$term_id]['p_value_corrected'];
    $diff = abs($goatools_pval - $custom_pval);
    
    if ($diff > 0.0001) {  // Allow small floating point differences
        echo "MISMATCH: $term_id - goatools: $goatools_pval, custom: $custom_pval\n";
    }
}

// Should show identical or nearly identical results
```

### Migration to Custom Approach Checklist

- [ ] Implement all statistical functions
- [ ] Validate p-values against goatools
- [ ] Test multiple correction methods
- [ ] Benchmark performance vs goatools
- [ ] Test with various gene set sizes
- [ ] Test with different organism combinations
- [ ] Caching strategy working correctly
- [ ] Results shown to users are identical
- [ ] Goatools code can be safely removed

