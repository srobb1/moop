# Third-Party Licenses

## locBLAST Repository - Reference Only
- **Source**: https://github.com/cobilab/locBLAST
- **License**: GNU General Public License v3.0 (GPL-3.0)
- **Status**: **NOT USED IN MOOP PRODUCTION CODE**
- **Purpose**: Cloned for reference and learning purposes only

### Why locBLAST Was Cloned

The locBLAST repository was cloned during development to:
- Understand BLAST XML output format and structure
- Reference implementation for alignment visualization techniques
- Learning resource for HSP (High-Scoring Segment Pair) rendering concepts
- Study of coordinate transformation and frame-aware sequence alignment

### Current MOOP Implementation - Original Code

**All MOOP BLAST visualization code is original and independently developed:**

#### 1. BLAST Execution (`lib/blast_functions.php::executeBlastSearch()`)
- **Status**: Original MOOP code
- **Purpose**: Executes NCBI BLAST+ tool and formats output
- **Implementation**: 
  - Uses NCBI BLAST+ command-line tools (not locBLAST)
  - Outputs to ASN.1 archive format
  - Converts to XML (outfmt 5) for web visualization
  - Converts to pairwise text (outfmt 0) for downloadable results

#### 2. BLAST Results Display (`tools/pages/blast.php`)
- **Status**: Original MOOP code
- **Purpose**: Displays BLAST search form and results
- **Implementation**: PHP/HTML with form controls and result rendering

#### 3. BLAST Visualization (`lib/blast_results_visualizer.php`)
- **Status**: Original MOOP code  
- **Purpose**: Parses BLAST XML and generates interactive HTML visualization
- **Implementation**:
  - Pure PHP with SimpleXML for XML parsing
  - HTML/CSS flexbox for HSP visualization (not Canvas or SVG)
  - Inline JavaScript for interactivity (hit navigation, highlighting)
  - Color-coded alignments based on BLAST scores
  - No code derived from locBLAST
  - Color scheme concept informed by locBLAST's bit-score coloring approach (RGB values independently chosen)

**Key Functions and Attribution:**

All functions below are original MOOP implementations, with some informed by locBLAST concepts:

- `generateCompleteBlastVisualization()` - Main visualization controller (original)
- `generateHspVisualizationWithLines()` - HSP display with interactive features using HTML/CSS flexbox (original)
- `generateQueryScale()` - Query scale ruler with tick spacing (original implementation)
- `parseBlastXML()` - XML parsing and data extraction (original)
- `formatBlastAlignment()` - Alignment text formatting
  - **Concept**: Inspired by locBLAST's `fmtprint()` function for frame-aware coordinate tracking in BLASTx/tBLASTx
  - **Implementation**: Independently developed with original PHP logic for handling frame shifts
- `getHspColorClass()` - Color scheme based on bit scores
  - **Concept**: Informed by locBLAST's `color_key()` function for score-based coloring
  - **Implementation**: Original PHP code with independently chosen RGB values

### Code Architecture

The MOOP BLAST implementation follows this architecture:
```
NCBI BLAST+ (command-line tool)
    ↓
BLAST output (ASN.1 archive)
    ↓
blast_formatter (NCBI tool)
    ├─→ XML output (for visualization)
    └─→ Pairwise text (for download)
    ↓
PHP parsing (SimpleXML)
    ↓
HTML generation (PHP)
    ↓
Browser display (CSS styled)
```

This is completely independent from locBLAST, which uses Canvas/SVG rendering and PHP-based output generation.

### License Summary

**MOOP is licensed under the MIT License.**

Since MOOP does not use locBLAST code, there are no GPL-3.0 license implications. The locBLAST repository serves only as a reference and is not included in any compiled or deployed version of MOOP.

### References
- locBLAST GitHub: https://github.com/cobilab/locBLAST
- NCBI BLAST+: https://blast.ncbi.nlm.nih.gov/
- MIT License (MOOP): https://opensource.org/licenses/MIT
