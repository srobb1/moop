# MOOP BLAST Search Flow Chart

## User Request Path (tools/blast.php)

```
┌─────────────────────────────────────────────────────────────┐
│ User submits BLAST search form                              │
│ (tools/blast.php - POST request)                            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Parse form data:                                             │
│ - query sequence                                             │
│ - blast_program (blastn, blastp, blastx, tblastn, tblastx)  │
│ - blast_db (path to database)                               │
│ - search options (evalue, max_results, matrix, etc.)        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Validation:                                                  │
│ - Check user access to selected assembly                    │
│ - Validate BLAST database exists                            │
│ - Validate query sequence format                            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ lib/blast_functions.php::executeBlastSearch()               │
│ ✓ Executes BLAST program via command line                   │
│ ✓ Output format: ASN.1 archive (outfmt 11)                 │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
         ▼                       ▼
    ┌─────────────┐         ┌─────────────┐
    │ Convert to  │         │ Convert to  │
    │ XML output  │         │ Pairwise    │
    │ (outfmt 5)  │         │ (outfmt 0)  │
    │ (visual)    │         │ (download)  │
    └──────┬──────┘         └──────┬──────┘
           │                       │
           ▼                       ▼
    Return to blast.php with:
    - success flag
    - XML output (for visualization)
    - Pairwise output (for download)
           │
           └───────────────┬──────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│ tools/pages/blast.php - Display Results                     │
│                                                              │
│ if (isset($blast_result) && !empty($blast_result)):         │
│   Call generateCompleteBlastVisualization()                 │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ lib/blast_results_visualizer.php::                          │
│ generateCompleteBlastVisualization()                        │
│                                                              │
│ 1. Parse XML output using SimpleXML                         │
│ 2. Extract hit information                                  │
│ 3. Format display HTML                                      │
│ 4. Generate HSP visualizations                              │
│ 5. Return complete HTML for display                         │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┴───────────┬──────────────────┐
         │                       │                  │
         ▼                       ▼                  ▼
    ┌─────────────┐         ┌─────────────┐  ┌────────────┐
    │ Hit Summary │         │ HSP Details │  │ Pairwise   │
    │ List        │         │ with color  │  │ Text       │
    │             │         │ alignments  │  │ (download) │
    └─────────────┘         └─────────────┘  └────────────┘
                                   │
         ┌─────────────────────────┘
         │
         ▼
    Rendered in browser (HTML)

```

## Key Functions Called

### Execution Phase
- **tools/blast.php** - Main controller, handles form, calls executeBlastSearch
- **lib/blast_functions.php::executeBlastSearch()** - Runs BLAST command
  - Creates temporary files
  - Executes BLAST with outfmt 11 (ASN.1)
  - Converts to XML (outfmt 5) for visualization
  - Converts to pairwise (outfmt 0) for download

### Display Phase
- **tools/pages/blast.php** - Content file that displays results
- **lib/blast_results_visualizer.php::generateCompleteBlastVisualization()** - Main visualization function
  - Parses XML from BLAST output
  - Generates HTML/CSS formatted alignments
  - Calls supporting functions for HSP rendering
  - **Does NOT call locBLAST code**

### Supporting Functions in blast_results_visualizer.php
- `generateHspVisualizationWithLines()` - Creates HSP alignment display
- `parseBlastXML()` - Parses BLAST XML output
- Various helper functions for formatting and coloring

---

## Code Attribution Review

### What we use from locBLAST
**NOTHING** - We have NOT copied or adapted any code from locBLAST.

### Why locBLAST was cloned
- Reference implementation for BLAST XML parsing techniques
- Understanding output format handling
- Learning alignment visualization concepts
- **Not used in production code**

### Current Implementation
All MOOP BLAST visualization is:
1. **Written from scratch** in lib/blast_results_visualizer.php
2. **Pure PHP with HTML/CSS** - no JavaScript canvas/SVG rendering
3. **Uses SimpleXML** for parsing BLAST output
4. **Generates HTML tables and divs** for display
5. **Color-coded by score** using CSS classes

---

## Data Flow Summary

```
User Input
    ↓
BLAST Command Execution (system: NCBI BLAST+)
    ↓
ASN.1 Archive (internal format)
    ├─→ Convert to XML (visualization)
    └─→ Convert to Pairwise (download)
    ↓
PHP Parsing (SimpleXML)
    ↓
HTML Generation (PHP)
    ↓
Browser Display (CSS styled)
```

