# Third-Party Licenses

## locBLAST - HSP Visualization with Connecting Lines
- **Source**: https://github.com/cobilab/locBLAST
- **License**: GNU General Public License v3.0 (GPL-3.0)
- **Used in**: `tools/blast_results_visualizer.php` - Function `generateHspVisualizationWithLines()`
- **Attribution**: The HSP visualization logic with connecting lines has been adapted from locBLAST's implementation in `xml.php`
- **Modifications**: 
  - Converted from inline HTML generation to a reusable PHP function
  - Adapted color scheme to match existing BLAST tool styling
  - Modified positioning logic to work with moop's data structures
  - Added CSS-based visual representation instead of raw divs

### GPL-3.0 Compatibility Note
While moop is licensed under the MIT License, the HSP visualization component incorporates GPL-3.0 code. This means:
- The visualization function and its output are subject to GPL-3.0 terms
- Users who receive this software are entitled to the freedoms provided by GPL-3.0
- The GPL-3.0 license is compatible with MIT in the sense that GPL-3.0 is more permissive regarding user freedoms
- Source code for the visualization component is provided in this repository

For more information on GPL-3.0, see: https://www.gnu.org/licenses/gpl-3.0.en.html
