# Third-Party Licenses

## locBLAST - BLAST Visualization and Alignment Formatting
- **Source**: https://github.com/cobilab/locBLAST
- **License**: GNU General Public License v3.0 (GPL-3.0)
- **Used in**: `/lib/blast_results_visualizer.php`
- **Attribution**: MOOP uses code ported and adapted from locBLAST for BLAST result visualization and alignment display

### Specific Functions Used and Modified

#### 1. `formatBlastAlignment()` - BLAST Alignment Formatter (Lines 1292-1425)
- **Original**: `fmtprint()` function from locBLAST's `xml.php`
- **Purpose**: Formats BLAST alignment output with proper coordinate tracking and frame-aware formatting
- **Modifications**:
  - Refactored from procedural code into a standalone, well-documented function
  - Adapted to work with MOOP's parsed BLAST result data structures
  - Enhanced with HTML entity encoding and better error handling
  - Added support for frame shifts in BLASTx, tBLASTx, and tBLASTn programs
  - Improved readability with proper indentation and formatting

**Original Code**: Handled output formatting with frame-aware coordinate calculations for:
- Query vs Subject sequence pairs
- Reading frame tracking (Plus/Minus strand, frames ±1, ±2, ±3)
- 60-character line wrapping with coordinate labels

**Current Code**: Maintains the same mathematical logic and algorithm for coordinate calculations while being restructured for use as a reusable PHP function.

#### 2. `generateHspVisualizationWithLines()` - HSP Visualization (Lines 1012-1234)
- **Original Concept**: HSP visualization logic from locBLAST
- **Purpose**: Displays High-Scoring Segment Pairs (HSPs) as colored segments with connecting lines
- **Modifications**:
  - Completely rewritten using HTML div elements with CSS flexbox styling (not Canvas or SVG)
  - Adapted color coding to match MOOP's BLAST tool styling
  - Restructured to work with MOOP's parsed data structures
  - Added interactive JavaScript features (click navigation, hit highlighting, smooth scrolling)
  - Improved accessibility and user experience with responsive layout

### GPL-3.0 License Implications

While MOOP is licensed under the MIT License, the use of GPL-3.0 code means:
- **For End Users**: You have the freedoms provided by GPL-3.0 regarding this software
- **License Compatibility**: The GPL-3.0 license covers the specific functions ported from locBLAST
- **Source Code**: All source code is available in this repository, complying with GPL-3.0 requirements
- **Derivative Works**: If you modify these functions, you should maintain the GPL-3.0 license for those modifications

**Important Note**: The GPL-3.0 requirement applies to the BLAST visualization components in this codebase. Other components of MOOP may use different licenses.

### References
- locBLAST GitHub: https://github.com/cobilab/locBLAST
- GPL-3.0 License: https://www.gnu.org/licenses/gpl-3.0.en.html
- MIT License (MOOP): https://opensource.org/licenses/MIT
