# GO Enrichment Analysis Tool - Implementation Plan

## Overview
Create a web-based GO (Gene Ontology) enrichment analysis tool using the goatools library. This tool will allow users to submit a study gene set and perform statistical enrichment analysis against population data with associated GO terms.

---

## 1. Architecture & File Structure

### Backend Files to Create
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
├── data/
│   └── go_enrichment/                       # Temporary working directory
│       ├── results/                         # Results storage
│       ├── input/                           # Uploaded input files
│       └── associations/                    # Pre-built GO associations per assembly
```

### Key Integration Points
- Register tool in `/data/moop/config/tools_config.php`
- Leverage existing tool initialization from `tool_init.php`
- Use existing permission/access control system
- Store results with expiration cleanup (like BLAST tool)

---

## 2. Backend Implementation

### 2.1 Installation & Dependencies
**File:** `scripts/go_enrichment_requirements.txt`
```
goatools==1.3.3
openpyxl==3.11.2
```

**Installation command:**
```bash
pip install -r scripts/go_enrichment_requirements.txt
```

### 2.2 Core Functions
**File:** `lib/go_enrichment_functions.php`

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

### 2.3 Python Script
**File:** `scripts/run_go_enrichment.py`

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

## 3. Frontend - Main Tool Page

### File: `tools/go_enrichment.php`

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

### 4.1 Database Structure Assumptions
Assuming existing tables:
- `genes` (gene_id, organism_id, assembly_id)
- `annotations` (gene_id, annotation_type, annotation_value)
  - Where annotation_type = 'GO' and annotation_value = 'GO:0045449;GO:0005783;...'

### 4.2 Pre-computed Association Files
Create periodic cron job or on-demand generation to create:
- `data/go_enrichment/associations/{organism}_{assembly}_associations.txt`
- Format: `GENE_ID\tGO:0000001;GO:0000002;GO:0000003`
- One entry per line

---

## 5. Data Flow

### User Workflow:
```
1. User visits /tools/go_enrichment.php
2. Selects organism(s)/assembly(ies)
3. Enters study gene list
4. Adjusts enrichment parameters (optional)
5. Clicks "Run Analysis"
6. Progress indicator shows while processing
7. Results displayed in table format
8. User can download results in multiple formats
```

### Backend Processing:
```
1. Validate input genes
2. Build population list from database
3. Get GO associations (from cache or generate)
4. Call Python script via exec()
5. Parse goatools output (Excel)
6. Format results for HTML/JSON
7. Store downloadable files in temp directory
8. Return to user
```

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

### Phase 1: Foundation (Week 1)
- [ ] Install goatools via pip
- [ ] Create Python wrapper script
- [ ] Build `go_enrichment_functions.php` with core logic
- [ ] Create `go_enrichment.php` with basic form

### Phase 2: Data Preparation (Week 2)
- [ ] Query database for GO associations
- [ ] Build association file generator
- [ ] Create caching mechanism
- [ ] Pre-generate files for common assemblies

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
2. **GO Subgraph Visualization**: Interactive GO tree display
3. **Term Clustering**: Group similar enriched GO terms
4. **Batch Analysis**: Process multiple gene lists in one job
5. **Publication Export**: Generate analysis summary for papers
6. **Integration with BLAST Results**: Directly enrich BLAST hit sets
7. **Custom GO Databases**: Allow user-provided annotation files

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
