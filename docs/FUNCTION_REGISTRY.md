# Function Registry

**Auto-generated documentation**

Generated: 2025-11-21 22:49:01

## Summary

- **Total Functions**: 105
- **Files Scanned**: 17

## Quick Navigation

- [lib/blast_functions.php](#lib-blast_functionsphp) - 5 functions
- [lib/blast_results_visualizer.php](#lib-blast_results_visualizerphp) - 15 functions
- [lib/database_queries.php](#lib-database_queriesphp) - 11 functions
- [lib/extract_search_helpers.php](#lib-extract_search_helpersphp) - 11 functions
- [lib/functions_access.php](#lib-functions_accessphp) - 3 functions
- [lib/functions_data.php](#lib-functions_dataphp) - 7 functions
- [lib/functions_database.php](#lib-functions_databasephp) - 8 functions
- [lib/functions_display.php](#lib-functions_displayphp) - 5 functions
- [lib/functions_errorlog.php](#lib-functions_errorlogphp) - 3 functions
- [lib/functions_filesystem.php](#lib-functions_filesystemphp) - 7 functions
- [lib/functions_json.php](#lib-functions_jsonphp) - 4 functions
- [lib/functions_system.php](#lib-functions_systemphp) - 2 functions
- [lib/functions_tools.php](#lib-functions_toolsphp) - 7 functions
- [lib/functions_validation.php](#lib-functions_validationphp) - 6 functions
- [lib/parent_functions.php](#lib-parent_functionsphp) - 6 functions
- [lib/tool_config.php](#lib-tool_configphp) - 4 functions
- [tools/sequences_display.php](#tools-sequences_displayphp) - 1 functions

---

## lib/blast_functions.php

**5 function(s)**

### `getBlastDatabases()` (Line 23)

Located in: `lib/blast_functions.php` at line 23

**Description:**

```
/**
* Get list of available BLAST databases for an assembly
* Looks for FASTA files matching configured sequence type patterns
* (protein.aa.fa, cds.nt.fa, transcript.nt.fa)
*
* @param string $assembly_path Path to assembly directory
* @return array Array of BLAST databases with type and path
*   Format: [
*     ['name' => 'protein', 'path' => '/path/to/protein.aa.fa', 'type' => 'protein'],
*     ['name' => 'cds', 'path' => '/path/to/cds.nt.fa', 'type' => 'nucleotide']
*   ]
*/
```

**Used in 2 unique file(s) (3 total times):**
- `/data/moop/lib/blast_functions.php` (1x):
  - Line 23: `function getBlastDatabases($assembly_path) {`
- `/data/moop/tools/blast.php` (2x):
  - Line 122: `$all_dbs = getBlastDatabases($selected_source_obj[\'path\']);`
  - Line 599: `$dbs = getBlastDatabases($source[\'path\']);`

### `filterDatabasesByProgram()` (Line 72)

Located in: `lib/blast_functions.php` at line 72

**Description:**

```
/**
* Filter BLAST databases by program type
* Returns only databases compatible with the selected BLAST program
*
* @param array $databases Array of databases from getBlastDatabases()
* @param string $blast_program BLAST program: blastn, blastp, blastx, tblastn, tblastx
* @return array Filtered array of compatible databases
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/blast_functions.php` (1x):
  - Line 72: `function filterDatabasesByProgram($databases, $blast_program) {`

### `executeBlastSearch()` (Line 110)

Located in: `lib/blast_functions.php` at line 110

**Description:**

```
/**
* Execute BLAST search
* Runs BLAST command with outfmt 11 (ASN.1), then converts using blast_formatter
*
* @param string $query_seq FASTA sequence to search
* @param string $blast_db Path to BLAST database (without extension)
* @param string $program BLAST program (blastn, blastp, blastx, etc.)
* @param array $options Additional BLAST options (evalue, max_hits, matrix, etc.)
* @return array Result array with 'success', 'output', 'error', and 'stderr' keys
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/blast_functions.php` (1x):
  - Line 110: `function executeBlastSearch($query_seq, $blast_db, $program, $options = []) {`
- `/data/moop/tools/blast.php` (1x):
  - Line 166: `$blast_result = executeBlastSearch($query_with_header, $blast_db, $blast_program, $blast_options);`

### `extractSequencesFromBlastDb()` (Line 299)

Located in: `lib/blast_functions.php` at line 299

**Description:**

```
/**
* Extract sequences from BLAST database using blastdbcmd
* Used by fasta extract and download tools
* Supports parent->child lookup from database
*
* @param string $blast_db Path to BLAST database (without extension)
* @param array $sequence_ids Array of sequence IDs to extract
* @param string $organism Optional organism name for parent/child lookup
* @param string $assembly Optional assembly name for parent/child lookup
* @return array Result array with 'success', 'content', and 'error' keys
*/
```

**Used in 2 unique file(s) (3 total times):**
- `/data/moop/lib/blast_functions.php` (1x):
  - Line 299: `function extractSequencesFromBlastDb($blast_db, $sequence_ids, $organism = \'\', $assembly = \'\') {`
- `/data/moop/lib/extract_search_helpers.php` (2x):
  - Line 168: `$extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames, $organism, $assembly);`
  - Line 189: `$extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames, $organism, $assembly);`

### `validateBlastSequence()` (Line 350)

Located in: `lib/blast_functions.php` at line 350

**Description:**

```
/**
* Validate BLAST sequence input
* Checks if input is valid FASTA format
*
* @param string $sequence Raw sequence input (may or may not have FASTA header)
* @return array Array with 'valid' bool and 'error' string
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/blast_functions.php` (1x):
  - Line 350: `function validateBlastSequence($sequence) {`
- `/data/moop/tools/blast.php` (1x):
  - Line 137: `$validation = validateBlastSequence($search_query);`

---

## lib/blast_results_visualizer.php

**15 function(s)**

### `parseBlastResults()` (Line 19)

Located in: `lib/blast_results_visualizer.php` at line 19

**Description:**

```
/**
* Parse BLAST results from XML output
* Supports multiple queries, each with Hit/HSP hierarchy
*
* @param string $blast_xml Raw BLAST XML output
* @return array Array of parsed query results, each with hits array
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 19: `function parseBlastResults($blast_xml) {`
  - Line 735: `$parse_result = parseBlastResults($blast_result[\'output\']);`

### `generateHitsSummaryTable()` (Line 288)

Located in: `lib/blast_results_visualizer.php` at line 288

**Description:**

```
/**
* Generate HTML for hits summary table
*
* @param array $results Parsed BLAST results
* @param int $query_num Query number for linking to hit sections
* @return string HTML table
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 288: `function generateHitsSummaryTable($results, $query_num = 1) {`
  - Line 988: `$html .= generateHitsSummaryTable($query, $query_num);`

### `generateBlastGraphicalView()` (Line 352)

Located in: `lib/blast_results_visualizer.php` at line 352

**Description:**

```
/**
* Generate BLAST graphical results using SVG
* Displays hits/HSPs as colored rectangles with score-based coloring
* Similar to canvas graph but with better styling and E-value display
*
* @param array $results Parsed BLAST results
* @return string SVG HTML
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (1x):
  - Line 352: `function generateBlastGraphicalView($results) {`

### `generateAlignmentViewer()` (Line 533)

Located in: `lib/blast_results_visualizer.php` at line 533

**Description:**

```
/**
* Generate alignment viewer section
* Displays alignments organized by Hit, with multiple HSPs per Hit
*
* @param array $results Parsed BLAST results from parseBlastResults()
* @return string HTML with alignment viewer
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 533: `function generateAlignmentViewer($results, $blast_program = \'blastn\', $query_num = 1) {`
  - Line 991: `$html .= generateAlignmentViewer($query, $blast_program, $query_num);`

### `generateBlastStatisticsSummary()` (Line 650)

Located in: `lib/blast_results_visualizer.php` at line 650

**Description:**

```
/**
* Generate BLAST results statistics summary
* Pretty card showing overall results statistics
*
* @param array $results Parsed BLAST results
* @param string $query_seq Query sequence
* @param string $blast_program BLAST program name
* @return string HTML statistics card
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (1x):
  - Line 650: `function generateBlastStatisticsSummary($results, $query_seq, $blast_program) {`

### `generateCompleteBlastVisualization()` (Line 730)

Located in: `lib/blast_results_visualizer.php` at line 730

**Description:**

```
/**
* Generate complete BLAST results visualization
* Combines all visualization components
*
* @param array $blast_result Result from executeBlastSearch()
* @param string $query_seq The query sequence
* @param string $blast_program The BLAST program used
* @return string Complete HTML visualization
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (1x):
  - Line 730: `function generateCompleteBlastVisualization($blast_result, $query_seq, $blast_program, $blast_options = []) {`
- `/data/moop/tools/blast.php` (1x):
  - Line 558: `<?= generateCompleteBlastVisualization($blast_result, $search_query, $blast_program, $blast_options ?? []) ?>`

### `generateHspVisualizationWithLines()` (Line 985)

Located in: `lib/blast_results_visualizer.php` at line 985

**Description:**

```
/**
* Generate HSP visualization with connecting lines (ported from locBLAST)
* Displays HSPs as colored segments with lines connecting adjacent HSPs
* Adapted from: https://github.com/cobilab/locBLAST (GPL-3.0)
*
* @param array $results Parsed BLAST results
* @param string $blast_program BLAST program name (blastn, blastp, etc.)
* @return string HTML with HSP visualization
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 985: `$html .= generateHspVisualizationWithLines($query, $blast_program, $query_num);`
  - Line 1012: `function generateHspVisualizationWithLines($results, $blast_program = \'blastn\', $query_num = 1) {`

### `getHspColorClass()` (Line 1144)

Located in: `lib/blast_results_visualizer.php` at line 1144

**Description:**

```
/**
* Get HSP color class based on bit score
* Mirrors locBLAST color_key function
*
* @param float $score Bit score
* @return string CSS class name for color
*/
```

**Used in 1 unique file(s) (3 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (3x):
  - Line 1144: `$color = getHspColorClass($hsp_scores[$first_idx]);`
  - Line 1191: `$color = getHspColorClass($hsp_scores[$current_idx]);`
  - Line 1243: `function getHspColorClass($score) {`

### `getColorStyle()` (Line 1263)

Located in: `lib/blast_results_visualizer.php` at line 1263

**Description:**

```
/**
* Get inline CSS style for color class
*
* @param string $colorClass CSS class name
* @return string Inline style
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (1x):
  - Line 1263: `function getColorStyle($colorClass) {`

### `formatBlastAlignment()` (Line 603)

Located in: `lib/blast_results_visualizer.php` at line 603

**Description:**

```
/**
* Format BLAST alignment output with frame-aware coordinate tracking
* Ported from locBLAST fmtprint() - handles frame shifts for BLASTx/tBLASTx
*
* @param int $length Alignment length
* @param string $query_seq Query sequence with gaps
* @param int $query_seq_from Query start coordinate
* @param int $query_seq_to Query end coordinate
* @param string $align_seq Midline (match indicators)
* @param string $sbjct_seq Subject sequence with gaps
* @param int $sbjct_seq_from Subject start coordinate
* @param int $sbjct_seq_to Subject end coordinate
* @param string $p_m Plus/Minus strand
* @param int $query_frame Query reading frame (0=none, ±1,2,3 for proteins)
* @param int $hit_frame Subject reading frame
* @return string Formatted alignment text
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 603: `$alignment_text = formatBlastAlignment(`
  - Line 1292: `function formatBlastAlignment($length, $query_seq, $query_seq_from, $query_seq_to, $align_seq, $sbjct_seq, $sbjct_seq_from, $sbjct_seq_to, $p_m = \'Plus\', $query_frame = 0, $hit_frame = 0) {`

### `generateQueryScoreLegend()` (Line 1073)

Located in: `lib/blast_results_visualizer.php` at line 1073

**Description:**

```
/**
* Generate query score legend (outside overflow container)
* Shows score color ranges and query bar info
*
* @param int $query_length Total query length
* @param string $query_name Optional query name/ID
* @return string HTML for legend and query bar
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 1073: `$html .= generateQueryScoreLegend($results[\'query_length\'], $results[\'query_name\']);`
  - Line 1435: `function generateQueryScoreLegend($query_length, $query_name = \'\') {`

### `generateQueryScaleTicks()` (Line 1099)

Located in: `lib/blast_results_visualizer.php` at line 1099

**Description:**

```
/**
* Generate query scale ticks (inside overflow container to be clipped)
* Shows tick marks and vertical reference lines
* Must be positioned at top of the HSP rows
*
* @param int $query_length Total query length
* @return string HTML for scale ticks and vertical lines
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 1099: `$html .= generateQueryScaleTicks($results[\'query_length\']);`
  - Line 1488: `function generateQueryScaleTicks($query_length) {`

### `generateQueryScale()` (Line 1099)

Located in: `lib/blast_results_visualizer.php` at line 1099

**Description:**

```
/**
* Generate query scale ruler with intelligent tick spacing
* Ported from locBLAST unit() function - displays as positioned overlay
* Includes horizontal query bar representation aligned with HSP boxes
* Tick lines are positioned absolutely and will be clipped by parent container
*
* @param int $query_length Total query length
* @param string $query_name Optional query name/ID
* @return string HTML for scale labels, ticks, and query bar
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (1x):
  - Line 1554: `function generateQueryScale($query_length, $query_name = \'\') {`

### `getToggleQuerySectionScript()` (Line 1659)

Located in: `lib/blast_results_visualizer.php` at line 1659

**Description:**

```
/**
* JavaScript function for toggling query sections (embedded in PHP output)
* Called onclick from query section headers
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (1x):
  - Line 1659: `function getToggleQuerySectionScript() {`
- `/data/moop/tools/blast.php` (1x):
  - Line 555: `<?= getToggleQuerySectionScript() ?>`

### `toggleQuerySection()` (Line 962)

Located in: `lib/blast_results_visualizer.php` at line 962

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/blast_results_visualizer.php` (2x):
  - Line 962: `$html .= \'<div id=\"query-\' . $query_num . \'-header\" style=\"padding: 15px; cursor: pointer; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;\" onclick=\"toggleQuerySection(\\\'query-\' . $query_num . \'-content\\\', this);\">\';`
  - Line 1662: `function toggleQuerySection(contentId, headerElement) {`

---

## lib/database_queries.php

**11 function(s)**

### `getFeatureById()` (Line 28)

Located in: `lib/database_queries.php` at line 28

**Description:**

```
/**
* Get feature data by feature_id
* Returns complete feature information including organism and genome data
*
* @param int $feature_id - Feature ID to retrieve
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Feature row with organism and genome info, or empty array
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 28: `function getFeatureById($feature_id, $dbFile, $genome_ids = []) {`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 98: `$row = getFeatureById($ancestor_feature_id, $db, $accessible_genome_ids);`

### `getFeatureByUniquename()` (Line 65)

Located in: `lib/database_queries.php` at line 65

**Description:**

```
/**
* Get feature data by feature_uniquename
* Returns complete feature information including organism and genome data
*
* @param string $feature_uniquename - Feature uniquename to retrieve
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Feature row with organism and genome info, or empty array
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/parent_functions.php` (1x):
  - Line 19: `$feature = getFeatureByUniquename($feature_uniquename, $dbFile, $genome_ids);`
- `/data/moop/lib/database_queries.php` (1x):
  - Line 65: `function getFeatureByUniquename($feature_uniquename, $dbFile, $genome_ids = []) {`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 95: `$feature_result = getFeatureByUniquename($uniquename, $db);`

### `getChildrenByFeatureId()` (Line 102)

Located in: `lib/database_queries.php` at line 102

**Description:**

```
/**
* Get immediate children of a feature (not recursive)
* Returns direct children only
*
* @param int $parent_feature_id - Parent feature ID
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Array of child feature rows
*/
```

**Used in 2 unique file(s) (3 total times):**
- `/data/moop/lib/parent_functions.php` (2x):
  - Line 75: `$results = getChildrenByFeatureId($feature_id, $dbFile, $genome_ids);`
  - Line 257: `$results = getChildrenByFeatureId($feature_id, $dbFile, $genome_ids);`
- `/data/moop/lib/database_queries.php` (1x):
  - Line 102: `function getChildrenByFeatureId($parent_feature_id, $dbFile, $genome_ids = []) {`

### `getParentFeature()` (Line 130)

Located in: `lib/database_queries.php` at line 130

**Description:**

```
/**
* Get immediate parent of a feature by ID
* Returns minimal parent info for hierarchy traversal
*
* @param int $feature_id - Feature ID to get parent of
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Parent feature row (minimal fields), or empty array
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/parent_functions.php` (1x):
  - Line 46: `$feature = getParentFeature($feature_id, $dbFile, $genome_ids);`
- `/data/moop/lib/database_queries.php` (1x):
  - Line 130: `function getParentFeature($feature_id, $dbFile, $genome_ids = []) {`

### `getFeaturesByType()` (Line 157)

Located in: `lib/database_queries.php` at line 157

**Description:**

```
/**
* Get all features of specific types in a genome
* Useful for getting genes, mRNAs, or other feature types
*
* @param string $feature_type - Feature type to retrieve (e.g., 'gene', 'mRNA')
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Array of features with specified type
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 157: `function getFeaturesByType($feature_type, $dbFile, $genome_ids = []) {`

### `searchFeaturesByUniquename()` (Line 187)

Located in: `lib/database_queries.php` at line 187

**Description:**

```
/**
* Search features by uniquename with optional organism filter
* Used for quick feature lookup and search suggestions
*
* @param string $search_term - Search term for feature uniquename (supports wildcards)
* @param string $dbFile - Path to SQLite database
* @param string $organism_name - Optional: Filter by organism name
* @return array - Array of matching features
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 187: `function searchFeaturesByUniquename($search_term, $dbFile, $organism_name = \'\') {`

### `getAnnotationsByFeature()` (Line 219)

Located in: `lib/database_queries.php` at line 219

**Description:**

```
/**
* Get all annotations for a feature
* Returns annotations with their sources and metadata
*
* @param int $feature_id - Feature ID to get annotations for
* @param string $dbFile - Path to SQLite database
* @return array - Array of annotation records
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 219: `function getAnnotationsByFeature($feature_id, $dbFile) {`

### `getOrganismInfo()` (Line 240)

Located in: `lib/database_queries.php` at line 240

**Description:**

```
/**
* Get organism information
* Returns complete organism record with taxonomic data
*
* @param string $organism_name - Organism name (genus + species)
* @param string $dbFile - Path to SQLite database
* @return array - Organism record, or empty array if not found
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 240: `function getOrganismInfo($organism_name, $dbFile) {`

### `getAssemblyStats()` (Line 258)

Located in: `lib/database_queries.php` at line 258

**Description:**

```
/**
* Get assembly/genome statistics
* Returns feature counts and metadata for an assembly
*
* @param string $genome_accession - Genome/assembly accession
* @param string $dbFile - Path to SQLite database
* @return array - Genome record with feature counts, or empty array
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 258: `function getAssemblyStats($genome_accession, $dbFile) {`
- `/data/moop/tools/assembly_display.php` (1x):
  - Line 19: `$assembly_info = getAssemblyStats($assembly_accession, $db_path);`

### `searchFeaturesAndAnnotations()` (Line 282)

Located in: `lib/database_queries.php` at line 282

**Description:**

```
/**
* Search features and annotations by keyword
* Supports both keyword and quoted phrase searches
* Used by annotation_search_ajax.php
*
* @param string $search_term - Search term or phrase
* @param bool $is_quoted_search - Whether this is a quoted phrase search
* @param string $dbFile - Path to SQLite database
* @return array - Array of matching features with annotations
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 282: `function searchFeaturesAndAnnotations($search_term, $is_quoted_search, $dbFile) {`
- `/data/moop/tools/annotation_search_ajax.php` (1x):
  - Line 85: `$results = searchFeaturesAndAnnotations($search_input, $quoted_search, $db);`

### `searchFeaturesByUniquenameForSearch()` (Line 379)

Located in: `lib/database_queries.php` at line 379

**Description:**

```
/**
* Search features by uniquename (primary search)
* Returns only features, not annotations
* Used as fast path before annotation search
*
* @param string $search_term - Search term for uniquename
* @param string $dbFile - Path to SQLite database
* @param string $organism_name - Optional: Filter by organism
* @return array - Array of matching features
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/database_queries.php` (1x):
  - Line 379: `function searchFeaturesByUniquenameForSearch($search_term, $dbFile, $organism_name = \'\') {`
- `/data/moop/tools/annotation_search_ajax.php` (1x):
  - Line 80: `$results = searchFeaturesByUniquenameForSearch($search_input, $db);`

---

## lib/extract_search_helpers.php

**11 function(s)**

### `parseOrganismParameter()` (Line 29)

Located in: `lib/extract_search_helpers.php` at line 29

**Description:**

```
/**
* Parse organism parameter from various sources and formats
*
* Handles multiple input formats:
* - Array from multi-search context (organisms[])
* - Single organism from context parameters
* - Comma-separated string
*
* @param string|array $organisms_param - Raw parameter value
* @param string $context_organism - Optional fallback organism
* @return array - ['organisms' => [], 'string' => 'comma,separated,list']
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 29: `function parseOrganismParameter($organisms_param, $context_organism = \'\') {`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 50: `$organism_result = parseOrganismParameter($organisms_param, $context[\'organism\']);`

### `parseContextParameters()` (Line 65)

Located in: `lib/extract_search_helpers.php` at line 65

**Description:**

```
/**
* Extract context parameters from request
*
* Checks explicit context_* fields first (highest priority), then regular fields as fallback
*
* @return array - ['organism' => '', 'assembly' => '', 'group' => '', 'display_name' => '', 'context_page' => '']
*/
```

**Used in 4 unique file(s) (4 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 65: `function parseContextParameters() {`
- `/data/moop/tools/retrieve_selected_sequences.php` (1x):
  - Line 58: `$context = parseContextParameters();`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 41: `$context = parseContextParameters();`
- `/data/moop/tools/blast.php` (1x):
  - Line 31: `$context = parseContextParameters();`

### `validateExtractInputs()` (Line 86)

Located in: `lib/extract_search_helpers.php` at line 86

**Description:**

```
/**
* Validate extract/search inputs (organism, assembly, feature IDs)
*
* Comprehensive validation for extract operations
*
* @param string $organism - Organism name
* @param string $assembly - Assembly name
* @param string $uniquenames_string - Comma-separated feature IDs
* @param array $accessible_sources - Available assemblies from getAccessibleAssemblies()
* @return array - ['valid' => bool, 'errors' => [], 'fasta_source' => null]
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 86: `function validateExtractInputs($organism, $assembly, $uniquenames_string, $accessible_sources) {`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 72: `$validation = validateExtractInputs($selected_organism, $selected_assembly, $uniquenames_string, $accessible_sources);`

### `parseFeatureIds()` (Line 128)

Located in: `lib/extract_search_helpers.php` at line 128

**Description:**

```
/**
* Parse and validate feature IDs from user input
*
* Handles both comma and newline separated formats
*
* @param string $uniquenames_string - Comma or newline separated IDs
* @return array - ['valid' => bool, 'uniquenames' => [], 'error' => '']
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 128: `function parseFeatureIds($uniquenames_string) {`
- `/data/moop/tools/retrieve_selected_sequences.php` (1x):
  - Line 77: `$id_parse = parseFeatureIds($uniquenames_string);`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 78: `$id_parse = parseFeatureIds($uniquenames_string);`

### `extractSequencesForAllTypes()` (Line 159)

Located in: `lib/extract_search_helpers.php` at line 159

**Description:**

```
/**
* Extract sequences for all available types from BLAST database
*
* Iterates through all sequence types and extracts for the given feature IDs
*
* @param string $assembly_dir - Path to assembly directory
* @param array $uniquenames - Feature IDs to extract
* @param array $sequence_types - Available sequence type configurations (from site_config)
* @param string $organism - Organism name (for parent/child database lookup)
* @param string $assembly - Assembly name (for parent/child database lookup)
* @return array - ['success' => bool, 'content' => [...], 'errors' => []]
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 159: `function extractSequencesForAllTypes($assembly_dir, $uniquenames, $sequence_types, $organism = \'\', $assembly = \'\') {`
- `/data/moop/tools/retrieve_selected_sequences.php` (1x):
  - Line 102: `$extract_result = extractSequencesForAllTypes($assembly_dir, $uniquenames, $sequence_types);`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 114: `$extract_result = extractSequencesForAllTypes($fasta_source[\'path\'], $uniquenames, $sequence_types, $selected_organism, $selected_assembly);`

### `formatSequenceResults()` (Line 214)

Located in: `lib/extract_search_helpers.php` at line 214

**Description:**

```
/**
* Format extracted sequences for display component
*
* Converts extracted content into format expected by sequences_display.php
*
* @param array $displayed_content - Extracted sequences by type
* @param array $sequence_types - Type configurations (from site_config)
* @return array - Formatted for sequences_display.php inclusion
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 214: `function formatSequenceResults($displayed_content, $sequence_types) {`
- `/data/moop/tools/retrieve_selected_sequences.php` (1x):
  - Line 198: `$available_sequences = formatSequenceResults($displayed_content, $sequence_types);`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 356: `$available_sequences = formatSequenceResults($displayed_content, $sequence_types);`

### `sendFileDownload()` (Line 237)

Located in: `lib/extract_search_helpers.php` at line 237

**Description:**

```
/**
* Send file download response and exit
*
* Sets appropriate headers and outputs file content
* Should be called before any HTML output
*
* @param string $content - File content to download
* @param string $sequence_type - Type of sequence (for filename)
* @param string $file_format - Format (fasta or txt)
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 237: `function sendFileDownload($content, $sequence_type, $file_format = \'fasta\') {`
- `/data/moop/tools/retrieve_selected_sequences.php` (1x):
  - Line 113: `sendFileDownload($displayed_content[$sequence_type], $sequence_type, $file_format);`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 140: `sendFileDownload($displayed_content[$sequence_type], $sequence_type, $file_format);`

### `buildFilteredSourcesList()` (Line 257)

Located in: `lib/extract_search_helpers.php` at line 257

**Description:**

```
/**
* Build organism-filtered list of accessible assembly sources
*
* Filters nested sources array by organism list
*
* @param array $sources_by_group - Nested array from getAccessibleAssemblies()
* @param array $filter_organisms - Optional organism filter list
* @return array - Nested array [group][organism][...assemblies]
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 257: `function buildFilteredSourcesList($sources_by_group, $filter_organisms = []) {`

### `flattenSourcesList()` (Line 286)

Located in: `lib/extract_search_helpers.php` at line 286

**Description:**

```
/**
* Flatten nested sources array for sequential processing
*
* Converts nested [group][organism][...sources] structure to flat list
* Useful for iterating all sources without nested loops
*
* @param array $sources_by_group - Nested array from getAccessibleAssemblies()
* @return array - Flat list of all sources
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 286: `function flattenSourcesList($sources_by_group) {`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 58: `$accessible_sources = flattenSourcesList($sources_by_group);`

### `assignGroupColors()` (Line 307)

Located in: `lib/extract_search_helpers.php` at line 307

**Description:**

```
/**
* Assign Bootstrap colors to groups for consistent UI display
*
* Uses Bootstrap color palette cyclically across groups
* Same group always gets same color (idempotent)
*
* @param array $sources_by_group - Groups to assign colors to
* @return array - [group_name => bootstrap_color]
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 307: `function assignGroupColors($sources_by_group) {`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 242: `$group_color_map = assignGroupColors($sources_by_group);`

### `getAvailableSequenceTypesForDisplay()` (Line 330)

Located in: `lib/extract_search_helpers.php` at line 330

**Description:**

```
/**
* Get available sequence types from all accessible sources
*
* Scans assembly directories to determine which sequence types are available
* Useful for populating UI dropdowns/display options
*
* @param array $accessible_sources - Flattened list of sources
* @param array $sequence_types - Type configurations (from site_config)
* @return array - [type => label] for types that have available files
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/extract_search_helpers.php` (1x):
  - Line 330: `function getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types) {`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 145: `$available_types = getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types);`

---

## lib/functions_access.php

**3 function(s)**

### `getAccessibleAssemblies()` (Line 15)

Located in: `lib/functions_access.php` at line 15

**Description:**

```
/**
* Get assemblies accessible to current user
* Filters assemblies based on user access level and group membership
*
* @param string $specific_organism Optional organism to filter by
* @param string $specific_assembly Optional assembly to filter by
* @return array Organized by group -> organism, or assemblies for specific organism/assembly
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/functions_access.php` (1x):
  - Line 15: `function getAccessibleAssemblies($specific_organism = null, $specific_assembly = null) {`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 57: `$sources_by_group = getAccessibleAssemblies();`
- `/data/moop/tools/blast.php` (1x):
  - Line 87: `$sources_by_group = getAccessibleAssemblies();`

### `getPhyloTreeUserAccess()` (Line 131)

Located in: `lib/functions_access.php` at line 131

**Description:**

```
/**
* Get phylogenetic tree user access for display
* Returns organisms accessible to current user for phylo tree display
*
* @param array $group_data Array of organism/assembly/groups data
* @return array Array of accessible organisms with true value
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_access.php` (1x):
  - Line 131: `function getPhyloTreeUserAccess($group_data) {`

### `requireAccess()` (Line 170)

Located in: `lib/functions_access.php` at line 170

**Description:**

```
/**
* Require user to have specific access level or redirect to access denied
*
* @param string $level Required access level (e.g., 'Collaborator', 'Admin')
* @param string $resource Resource name (e.g., group name or organism name)
* @param array $options Options array with keys:
*   - redirect_on_deny (bool, default: true) - Redirect to deny page if no access
*   - deny_page (string, default: /$site/access_denied.php) - URL to redirect to
* @return bool True if user has access, false otherwise
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_access.php` (1x):
  - Line 170: `function requireAccess($level, $resource, $options = []) {`
- `/data/moop/tools/groups_display.php` (1x):
  - Line 39: `requireAccess(\'Collaborator\', $group_name);`

---

## lib/functions_data.php

**7 function(s)**

### `getGroupData()` (Line 12)

Located in: `lib/functions_data.php` at line 12

**Description:**

```
/**
* Get group metadata from organism_assembly_groups.json
*
* @return array Array of organism/assembly/groups data
*/
```

**Used in 4 unique file(s) (4 total times):**
- `/data/moop/lib/functions_data.php` (1x):
  - Line 12: `function getGroupData() {`
- `/data/moop/tools/organism_display.php` (1x):
  - Line 188: `$group_data = getGroupData();`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 25: `$group_data = getGroupData();`
- `/data/moop/tools/groups_display.php` (1x):
  - Line 23: `$group_data = getGroupData();`

### `getAllGroupCards()` (Line 30)

Located in: `lib/functions_data.php` at line 30

**Description:**

```
/**
* Get all group cards from metadata
* Returns card objects for every group in the system
*
* @param array $group_data Array of organism/assembly/groups data
* @return array Associative array of group_name => card_info
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/functions_data.php` (2x):
  - Line 30: `function getAllGroupCards($group_data) {`
  - Line 166: `$all_cards = getAllGroupCards($group_data);`

### `getPublicGroupCards()` (Line 53)

Located in: `lib/functions_data.php` at line 53

**Description:**

```
/**
* Get group cards that have at least one public assembly
* Returns card objects only for groups containing assemblies in the "Public" group
*
* @param array $group_data Array of organism/assembly/groups data
* @return array Associative array of group_name => card_info for public groups only
*/
```

**Used in 1 unique file(s) (3 total times):**
- `/data/moop/lib/functions_data.php` (3x):
  - Line 53: `function getPublicGroupCards($group_data) {`
  - Line 172: `$cards_to_display = getPublicGroupCards($group_data);`
  - Line 186: `$cards_to_display = getPublicGroupCards($group_data);`

### `getAccessibleOrganismsInGroup()` (Line 81)

Located in: `lib/functions_data.php` at line 81

**Description:**

```
/**
* Filter organisms in a group to only those with at least one accessible assembly
* Respects user permissions for assembly access
*
* @param string $group_name The group name to filter
* @param array $group_data Array of organism/assembly/groups data
* @return array Filtered array of organism => [accessible_assemblies]
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_data.php` (1x):
  - Line 81: `function getAccessibleOrganismsInGroup($group_name, $group_data) {`
- `/data/moop/tools/groups_display.php` (1x):
  - Line 35: `$group_organisms = getAccessibleOrganismsInGroup($group_name, $group_data);`

### `getAssemblyFastaFiles()` (Line 131)

Located in: `lib/functions_data.php` at line 131

**Description:**

```
/**
* Get FASTA files for an assembly
*
* Scans the assembly directory for FASTA files matching configured sequence types.
* Uses patterns from $sequence_types global to identify file types (genome, protein, transcript, cds).
*
* @param string $organism_name The organism name
* @param string $assembly_name The assembly name (accession)
* @return array Associative array of type => ['path' => relative_path, 'label' => label]
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/functions_data.php` (1x):
  - Line 131: `function getAssemblyFastaFiles($organism_name, $assembly_name) {`
- `/data/moop/tools/assembly_display.php` (1x):
  - Line 104: `$fasta_files = getAssemblyFastaFiles($organism_name, $assembly_accession);`
- `/data/moop/tools/organism_display.php` (1x):
  - Line 208: `<?php $fasta_files = getAssemblyFastaFiles($organism_name, $assembly); ?>`

### `getIndexDisplayCards()` (Line 164)

Located in: `lib/functions_data.php` at line 164

**Description:**

```
/**
* Get cards to display on index page based on user access level
*
* @param array $group_data Array of group data from getGroupData()
* @return array Cards to display with title, text, and link
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_data.php` (1x):
  - Line 164: `function getIndexDisplayCards($group_data) {`

### `formatIndexOrganismName()` (Line 176)

Located in: `lib/functions_data.php` at line 176

**Description:**

```
/**
* Format organism name for index page display with italics
*
* @param string $organism Organism name with underscores
* @return string Formatted name with proper capitalization and italics
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/functions_data.php` (2x):
  - Line 176: `$formatted_name = formatIndexOrganismName($organism);`
  - Line 198: `function formatIndexOrganismName($organism) {`

---

## lib/functions_database.php

**8 function(s)**

### `validateDatabaseFile()` (Line 13)

Located in: `lib/functions_database.php` at line 13

**Description:**

```
/**
* Validates database file is readable and accessible
*
* @param string $dbFile - Path to SQLite database file
* @return array - Validation results with 'valid' and 'error' keys
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_database.php` (1x):
  - Line 13: `function validateDatabaseFile($dbFile) {`
- `/data/moop/tools/annotation_search_ajax.php` (1x):
  - Line 64: `$db_validation = validateDatabaseFile($db);`

### `validateDatabaseIntegrity()` (Line 44)

Located in: `lib/functions_database.php` at line 44

**Description:**

```
/**
* Validate database integrity and data quality
*
* Checks:
* - File is readable
* - Valid SQLite database
* - All required tables exist
* - Tables have data
* - Data completeness (no orphaned records)
*
* @param string $dbFile - Path to SQLite database file
* @return array - Validation results with status and details
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_database.php` (1x):
  - Line 44: `function validateDatabaseIntegrity($dbFile) {`

### `getDbConnection()` (Line 181)

Located in: `lib/functions_database.php` at line 181

**Description:**

```
/**
* Get database connection
*
* @param string $dbFile - Path to SQLite database file
* @return PDO - Database connection
* @throws PDOException if connection fails
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/functions_database.php` (2x):
  - Line 181: `function getDbConnection($dbFile) {`
  - Line 202: `$dbh = getDbConnection($dbFile);`

### `fetchData()` (Line 200)

Located in: `lib/functions_database.php` at line 200

**Description:**

```
/**
* Execute SQL query with prepared statement
*
* @param string $sql - SQL query with ? placeholders
* @param string $dbFile - Path to SQLite database file
* @param array $params - Parameters to bind to query (optional)
* @return array - Array of associative arrays (results)
* @throws PDOException if query fails
*/
```

**Used in 3 unique file(s) (14 total times):**
- `/data/moop/lib/parent_functions.php` (1x):
  - Line 225: `$results = fetchData($query, $dbFile, $params);`
- `/data/moop/lib/database_queries.php` (11x):
  - Line 52: `$results = fetchData($query, $dbFile, $params);`
  - Line 89: `$results = fetchData($query, $dbFile, $params);`
  - Line 118: `return fetchData($query, $dbFile, $params);`
  - Line 144: `$results = fetchData($query, $dbFile, $params);`
  - Line 175: `return fetchData($query, $dbFile, $params);`
  - Line 208: `return fetchData($query, $dbFile, $params);`
  - Line 229: `return fetchData($query, $dbFile, [$feature_id]);`
  - Line 246: `$results = fetchData($query, [$organism_name, $organism_name], $dbFile);`
  - Line 268: `$results = fetchData($query, $dbFile, [$genome_accession]);`
  - Line 366: `return fetchData($query, $dbFile, $params);`
  - Line 405: `return fetchData($query, $dbFile, $params);`
- `/data/moop/lib/functions_database.php` (2x):
  - Line 200: `function fetchData($sql, $dbFile, $params = []) {`
  - Line 283: `$results = fetchData($query, $db_path, $params);`

### `buildLikeConditions()` (Line 235)

Located in: `lib/functions_database.php` at line 235

**Description:**

```
/**
* Build SQL LIKE conditions for multi-column search
* Supports both quoted (phrase) and unquoted (word-by-word) searches
*
* Creates SQL WHERE clause fragments for searching multiple columns.
* Supports both keyword search (AND logic) and quoted phrase search.
*
* Keyword search: "ABC transporter"
*   - Splits into terms: ["ABC", "transporter"]
*   - Logic: (col1 LIKE '%ABC%' OR col2 LIKE '%ABC%') AND (col1 LIKE '%transporter%' OR col2 LIKE '%transporter%')
*   - Result: Both terms must match somewhere
*
* Quoted search: '"ABC transporter"'
*   - Keeps as single phrase: "ABC transporter"
*   - Logic: (col1 LIKE '%ABC transporter%' OR col2 LIKE '%ABC transporter%')
*   - Result: Exact phrase must match
*
* @param array $columns - Column names to search
* @param string $search - Search string (unquoted: words separated by space, quoted: single phrase)
* @param bool $quoted - If true, treat entire $search as single phrase; if false, split on whitespace
* @return array - [$sqlFragment, $params] for use with fetchData()
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_database.php` (1x):
  - Line 235: `function buildLikeConditions($columns, $search, $quoted = false) {`

### `getAccessibleGenomeIds()` (Line 271)

Located in: `lib/functions_database.php` at line 271

**Description:**

```
/**
* Get accessible genome IDs from database for organism
*
* @param string $organism_name - Organism name
* @param array $accessible_assemblies - List of accessible assembly names
* @param string $db_path - Path to SQLite database file
* @return array - Array of genome IDs
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_database.php` (1x):
  - Line 271: `function getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db_path) {`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 32: `$accessible_genome_ids = getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db);`

### `loadOrganismInfo()` (Line 295)

Located in: `lib/functions_database.php` at line 295

**Description:**

```
/**
* Load organism info from organism.json file
*
* @param string $organism_name - Organism name
* @param string $organism_data_dir - Path to organism data directory
* @return array|null - Organism info array or null if not found
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_database.php` (1x):
  - Line 295: `function loadOrganismInfo($organism_name, $organism_data_dir) {`
- `/data/moop/lib/functions_display.php` (1x):
  - Line 230: `$organism_info = loadOrganismInfo($organism_name, $organism_data_dir);`

### `verifyOrganismDatabase()` (Line 326)

Located in: `lib/functions_database.php` at line 326

**Description:**

```
/**
* Verify organism database file exists
*
* @param string $organism_name - Organism name
* @param string $organism_data_dir - Path to organism data directory
* @return string - Database path if exists, exits with error if not
*/
```

**Used in 4 unique file(s) (4 total times):**
- `/data/moop/lib/functions_database.php` (1x):
  - Line 326: `function verifyOrganismDatabase($organism_name, $organism_data_dir) {`
- `/data/moop/tools/assembly_display.php` (1x):
  - Line 16: `$db_path = verifyOrganismDatabase($organism_name, $organism_data);`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 22: `$db = verifyOrganismDatabase($organism_name, $organism_data);`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 88: `$db = verifyOrganismDatabase($selected_organism, $organism_data);`

---

## lib/functions_display.php

**5 function(s)**

### `loadOrganismAndGetImagePath()` (Line 18)

Located in: `lib/functions_display.php` at line 18

**Description:**

```
/**
* Load organism info and get image path
*
* Loads organism.json file and returns the image path using getOrganismImagePath()
* Encapsulates all the loading logic in one place.
*
* @param string $organism_name The organism name
* @param string $images_path URL path to images directory (e.g., 'moop/images')
* @param string $absolute_images_path Absolute file system path to images directory
* @return array ['organism_info' => array, 'image_path' => string]
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/functions_display.php` (1x):
  - Line 18: `function loadOrganismAndGetImagePath($organism_name, $images_path = \'moop/images\', $absolute_images_path = \'\') {`
- `/data/moop/tools/annotation_search_ajax.php` (1x):
  - Line 76: `$organism_data_result = loadOrganismAndGetImagePath($organism, $images_path, $absolute_images_path);`
- `/data/moop/tools/multi_organism_search.php` (1x):
  - Line 109: `$organism_data_result = loadOrganismAndGetImagePath($organism, $images_path, $absolute_images_path);`

### `getOrganismImagePath()` (Line 10)

Located in: `lib/functions_display.php` at line 10

**Description:**

```
/**
* Get organism image file path
*
* Returns the URL path to an organism's image with fallback logic:
* 1. Custom image from organism.json if defined
* 2. NCBI taxonomy image if taxon_id exists and image file found
* 3. Empty string if no image available
*
* @param array $organism_info Array from organism.json with keys: images, taxon_id
* @param string $images_path URL path to images directory (e.g., 'moop/images')
* @param string $absolute_images_path Absolute file system path to images directory
* @return string URL path to image file or empty string if no image
*/
```

**Used in 3 unique file(s) (4 total times):**
- `/data/moop/lib/functions_display.php` (2x):
  - Line 32: `$result[\'image_path\'] = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);`
  - Line 52: `function getOrganismImagePath($organism_info, $images_path = \'moop/images\', $absolute_images_path = \'\') {`
- `/data/moop/tools/organism_display.php` (1x):
  - Line 88: `$image_src = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);`
- `/data/moop/tools/groups_display.php` (1x):
  - Line 180: `$image_src = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);`

### `getOrganismImageCaption()` (Line 100)

Located in: `lib/functions_display.php` at line 100

**Description:**

```
/**
* Get organism image caption with optional link
*
* Returns display caption for organism image:
* - Custom images: caption from organism.json or empty string
* - NCBI taxonomy fallback: "Image from NCBI Taxonomy" with link to NCBI
*
* @param array $organism_info Array from organism.json with keys: images, taxon_id
* @param string $absolute_images_path Absolute file system path to images directory
* @return array ['caption' => caption text, 'link' => URL or empty string]
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_display.php` (1x):
  - Line 100: `function getOrganismImageCaption($organism_info, $absolute_images_path = \'\') {`
- `/data/moop/tools/organism_display.php` (1x):
  - Line 89: `$image_info = getOrganismImageCaption($organism_info, $absolute_images_path);`

### `validateOrganismJson()` (Line 153)

Located in: `lib/functions_display.php` at line 153

**Description:**

```
/**
* Validate organism.json file
*
* Checks:
* - File exists
* - File is readable
* - Valid JSON format
* - Contains required fields (genus, species, common_name, taxon_id)
*
* @param string $json_path - Path to organism.json file
* @return array - Validation results with status and details
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_display.php` (1x):
  - Line 153: `function validateOrganismJson($json_path) {`

### `setupOrganismDisplayContext()` (Line 225)

Located in: `lib/functions_display.php` at line 225

**Description:**

```
/**
* Complete setup for organism display pages
* Validates parameter, loads organism info, checks access, returns context
* Use this to replace boilerplate in organism_display, assembly_display, parent_display
*
* @param string $organism_name Organism from GET/POST
* @param string $organism_data_dir Path to organism data directory
* @param bool $check_access Whether to check access control (default: true)
* @param string $redirect_home Home URL for redirects (default: /moop/index.php)
* @return array Array with 'name' and 'info' keys, or exits on error
*/
```

**Used in 4 unique file(s) (4 total times):**
- `/data/moop/lib/functions_display.php` (1x):
  - Line 225: `function setupOrganismDisplayContext($organism_name, $organism_data_dir, $check_access = true, $redirect_home = \'/moop/index.php\') {`
- `/data/moop/tools/assembly_display.php` (1x):
  - Line 12: `$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);`
- `/data/moop/tools/organism_display.php` (1x):
  - Line 10: `$organism_context = setupOrganismDisplayContext($_GET[\'organism\'] ?? \'\', $organism_data);`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 18: `$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);`

---

## lib/functions_errorlog.php

**3 function(s)**

### `logError()` (Line 15)

Located in: `lib/functions_errorlog.php` at line 15

**Description:**

```
/**
* Log an error to the error log file
*
* @param string $error_message The error message to log
* @param string $context Optional context (e.g., organism name, page name)
* @param array $additional_info Additional details to log
* @return void
*/
```

**Used in 5 unique file(s) (9 total times):**
- `/data/moop/lib/functions_errorlog.php` (1x):
  - Line 15: `function logError($error_message, $context = \'\', $additional_info = []) {`
- `/data/moop/lib/functions_display.php` (3x):
  - Line 55: `logError(\'getOrganismImagePath received invalid organism_info\', \'organism_image\', [`
  - Line 79: `logError(\'NCBI taxonomy image not found\', \'organism_image\', [`
  - Line 108: `logError(\'getOrganismImageCaption received invalid organism_info\', \'organism_image\', [`
- `/data/moop/tools/sequences_display.php` (1x):
  - Line 126: `logError(`
- `/data/moop/tools/annotation_search_ajax.php` (3x):
  - Line 55: `logError(\'Database not found for organism\', $organism, [`
  - Line 66: `logError(\'Database file not accessible\', $organism, [`
  - Line 128: `logError(\'Incomplete annotation records found\', $organism, [`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 124: `logError($err, \"download_fasta\", [\'user\' => $_SESSION[\'username\'] ?? \'unknown\']);`

### `getErrorLog()` (Line 42)

Located in: `lib/functions_errorlog.php` at line 42

**Description:**

```
/**
* Get error log entries
*
* @param int $limit Maximum number of entries to retrieve (0 = all)
* @return array Array of error entries
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_errorlog.php` (1x):
  - Line 42: `function getErrorLog($limit = 0) {`

### `clearErrorLog()` (Line 75)

Located in: `lib/functions_errorlog.php` at line 75

**Description:**

```
/**
* Clear the error log file
*
* @return bool True if successful
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_errorlog.php` (1x):
  - Line 75: `function clearErrorLog() {`

---

## lib/functions_filesystem.php

**7 function(s)**

### `validateAssemblyDirectories()` (Line 17)

Located in: `lib/functions_filesystem.php` at line 17

**Description:**

```
/**
* Validate assembly directories match database records
*
* Checks that for each genome in the database, there is a corresponding directory
* named either genome_name or genome_accession
*
* @param string $dbFile - Path to SQLite database file
* @param string $organism_data_dir - Path to organism data directory
* @return array - Validation results with genomes list and mismatches
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_filesystem.php` (1x):
  - Line 17: `function validateAssemblyDirectories($dbFile, $organism_data_dir) {`

### `validateAssemblyFastaFiles()` (Line 116)

Located in: `lib/functions_filesystem.php` at line 116

**Description:**

```
/**
* Validate assembly FASTA files exist
*
* Checks if each assembly directory contains the required FASTA files
* based on sequence_types patterns from site config
*
* @param string $organism_dir - Path to organism directory
* @param array $sequence_types - Sequence type patterns from site_config
* @return array - Validation results for each assembly
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_filesystem.php` (1x):
  - Line 116: `function validateAssemblyFastaFiles($organism_dir, $sequence_types) {`

### `renameAssemblyDirectory()` (Line 183)

Located in: `lib/functions_filesystem.php` at line 183

**Description:**

```
/**
* Rename an assembly directory
*
* Renames a directory within an organism folder from old_name to new_name
* Used to align directory names with genome_name or genome_accession
* Returns manual command if automatic rename fails
*
* @param string $organism_dir - Path to organism directory
* @param string $old_name - Current directory name
* @param string $new_name - New directory name
* @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_filesystem.php` (1x):
  - Line 183: `function renameAssemblyDirectory($organism_dir, $old_name, $new_name) {`

### `deleteAssemblyDirectory()` (Line 242)

Located in: `lib/functions_filesystem.php` at line 242

**Description:**

```
/**
* Delete an assembly directory
*
* Recursively deletes a directory within an organism folder
* Used to remove incorrectly named or unused assembly directories
* Returns manual command if automatic delete fails
*
* @param string $organism_dir - Path to organism directory
* @param string $dir_name - Directory name to delete
* @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_filesystem.php` (1x):
  - Line 242: `function deleteAssemblyDirectory($organism_dir, $dir_name) {`

### `rrmdir()` (Line 273)

Located in: `lib/functions_filesystem.php` at line 273

**Description:**

```
/**
* Recursively remove directory
*
* Helper function to delete a directory and all its contents
*
* @param string $dir - Directory path
* @return bool - True if successful
*/
```

**Used in 1 unique file(s) (3 total times):**
- `/data/moop/lib/functions_filesystem.php` (3x):
  - Line 273: `if (rrmdir($dir_path)) {`
  - Line 291: `function rrmdir($dir) {`
  - Line 303: `if (!rrmdir($path)) {`

### `getFileWriteError()` (Line 323)

Located in: `lib/functions_filesystem.php` at line 323

**Description:**

```
/**
* Check file writeability and return error info if file is not writable
* Uses web server group and keeps original owner
*
* @param string $filepath - Path to file to check
* @return array|null - Array with error details if not writable, null if ok
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_filesystem.php` (1x):
  - Line 323: `function getFileWriteError($filepath) {`

### `getDirectoryError()` (Line 354)

Located in: `lib/functions_filesystem.php` at line 354

**Description:**

```
/**
* Check directory existence and writeability, return error info if issues found
* Uses owner of /moop directory and web server group
* Automatically detects if sudo is needed for the commands
*
* Usage:
*   $dir_error = getDirectoryError('/path/to/directory');
*   if ($dir_error) {
*       // Display error alert with fix instructions
*   }
*
* Can be used in any admin page that needs to ensure a directory exists and is writable.
* Common use cases:
*   - Image cache directories (ncbi_taxonomy, organisms, etc)
*   - Log directories
*   - Upload/temp directories
*   - Any other required filesystem paths
*
* @param string $dirpath - Path to directory to check
* @return array|null - Array with error details if directory missing/not writable, null if ok
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_filesystem.php` (1x):
  - Line 369: `function getDirectoryError($dirpath) {`

---

## lib/functions_json.php

**4 function(s)**

### `loadJsonFile()` (Line 14)

Located in: `lib/functions_json.php` at line 14

**Description:**

```
/**
* Load JSON file safely with error handling
*
* @param string $path Path to JSON file
* @param mixed $default Default value if file doesn't exist (default: [])
* @return mixed Decoded JSON data or default value
*/
```

**Used in 3 unique file(s) (4 total times):**
- `/data/moop/lib/functions_database.php` (1x):
  - Line 297: `$organism_info = loadJsonFile($organism_json_path);`
- `/data/moop/lib/functions_json.php` (2x):
  - Line 14: `function loadJsonFile($path, $default = []) {`
  - Line 88: `$existing = loadJsonFile($file_path);`
- `/data/moop/tools/groups_display.php` (1x):
  - Line 20: `$group_descriptions = loadJsonFile($group_descriptions_file, []);`

### `loadJsonFileRequired()` (Line 36)

Located in: `lib/functions_json.php` at line 36

**Description:**

```
/**
* Load JSON file and require it to exist
*
* @param string $path Path to JSON file
* @param string $errorMsg Error message to log if file missing
* @param bool $exitOnError Whether to exit if file not found (default: true)
* @return mixed Decoded JSON data or empty array if error
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_json.php` (1x):
  - Line 36: `function loadJsonFileRequired($path, $errorMsg = \'\', $exitOnError = false) {`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 41: `$annotation_config = loadJsonFileRequired($annotation_config_file, \"Missing annotation_config.json\");`

### `loadAndMergeJson()` (Line 81)

Located in: `lib/functions_json.php` at line 81

**Description:**

```
/**
* Load existing JSON file and merge with new data
* Handles wrapped JSON automatically, preserves existing fields not in merge data
*
* @param string $file_path Path to JSON file to load
* @param array $new_data New data to merge in (overwrites matching keys)
* @return array Merged data (or just new_data if file doesn't exist)
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_json.php` (1x):
  - Line 81: `function loadAndMergeJson($file_path, $new_data = []) {`

### `decodeJsonString()` (Line 113)

Located in: `lib/functions_json.php` at line 113

**Description:**

```
/**
* Decode JSON string safely with type checking
*
* @param string $json_string JSON string to decode
* @param bool $as_array Return as array (default: true)
* @return array|null Decoded data or null if invalid
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_json.php` (1x):
  - Line 113: `function decodeJsonString($json_string, $as_array = true) {`

---

## lib/functions_system.php

**2 function(s)**

### `getWebServerUser()` (Line 14)

Located in: `lib/functions_system.php` at line 14

**Description:**

```
/**
* Get the web server user and group
*
* Detects the user running the current PHP process (web server)
*
* @return array - ['user' => string, 'group' => string]
*/
```

**Used in 2 unique file(s) (4 total times):**
- `/data/moop/lib/functions_filesystem.php` (2x):
  - Line 328: `$webserver = getWebServerUser();`
  - Line 374: `$webserver = getWebServerUser();`
- `/data/moop/lib/functions_system.php` (2x):
  - Line 14: `function getWebServerUser() {`
  - Line 66: `$webserver = getWebServerUser();`

### `fixDatabasePermissions()` (Line 48)

Located in: `lib/functions_system.php` at line 48

**Description:**

```
/**
* Attempt to fix database file permissions
*
* Tries to make database readable by web server user.
* Returns instructions if automatic fix fails.
*
* @param string $dbFile - Path to database file
* @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_system.php` (1x):
  - Line 48: `function fixDatabasePermissions($dbFile) {`

---

## lib/functions_tools.php

**7 function(s)**

### `getAvailableTools()` (Line 14)

Located in: `lib/functions_tools.php` at line 14

**Description:**

```
/**
* Get available tools filtered by context
* Returns only tools that have the required context parameters available
*
* @param array $context - Context array with optional keys: organism, assembly, group, display_name
* @return array - Array of available tools with built URLs
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 14: `function getAvailableTools($context = []) {`
- `/data/moop/lib/tool_section.php` (1x):
  - Line 62: `$tools = getAvailableTools($context ?? []);`

### `createIndexToolContext()` (Line 50)

Located in: `lib/functions_tools.php` at line 50

**Description:**

```
/**
* Create a tool context for index/home page
*
* @param bool $use_onclick_handler Whether to use onclick handler for tools
* @return array Context array for tool_section.php
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 50: `function createIndexToolContext($use_onclick_handler = true) {`

### `createOrganismToolContext()` (Line 65)

Located in: `lib/functions_tools.php` at line 65

**Description:**

```
/**
* Create a tool context for an organism display page
*
* @param string $organism_name The organism name
* @param string $display_name Optional display name (defaults to organism_name)
* @return array Context array for tool_section.php
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 65: `function createOrganismToolContext($organism_name, $display_name = null) {`
- `/data/moop/tools/organism_display.php` (1x):
  - Line 64: `$context = createOrganismToolContext($organism_name, $organism_info[\'common_name\'] ?? $organism_name);`

### `createAssemblyToolContext()` (Line 81)

Located in: `lib/functions_tools.php` at line 81

**Description:**

```
/**
* Create a tool context for an assembly display page
*
* @param string $organism_name The organism name
* @param string $assembly_accession The assembly/genome accession
* @param string $display_name Optional display name (defaults to assembly_accession)
* @return array Context array for tool_section.php
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 81: `function createAssemblyToolContext($organism_name, $assembly_accession, $display_name = null) {`
- `/data/moop/tools/assembly_display.php` (1x):
  - Line 72: `$context = createAssemblyToolContext($organism_name, $assembly_accession, $assembly_info[\'genome_name\']);`

### `createGroupToolContext()` (Line 96)

Located in: `lib/functions_tools.php` at line 96

**Description:**

```
/**
* Create a tool context for a group display page
*
* @param string $group_name The group name
* @return array Context array for tool_section.php
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 96: `function createGroupToolContext($group_name) {`
- `/data/moop/tools/groups_display.php` (1x):
  - Line 92: `$context = createGroupToolContext($group_name);`

### `createFeatureToolContext()` (Line 112)

Located in: `lib/functions_tools.php` at line 112

**Description:**

```
/**
* Create a tool context for a feature/parent display page
*
* @param string $organism_name The organism name
* @param string $assembly_accession The assembly/genome accession
* @param string $feature_name The feature name
* @return array Context array for tool_section.php
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 112: `function createFeatureToolContext($organism_name, $assembly_accession, $feature_name) {`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 198: `$context = createFeatureToolContext($organism_name, $genome_accession, $feature_uniquename);`

### `createMultiOrganismToolContext()` (Line 128)

Located in: `lib/functions_tools.php` at line 128

**Description:**

```
/**
* Create a tool context for multi-organism search page
*
* @param array $organisms Array of organism names
* @param string $display_name Optional display name
* @return array Context array for tool_section.php
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 128: `function createMultiOrganismToolContext($organisms, $display_name = \'Multi-Organism Search\') {`
- `/data/moop/tools/multi_organism_search.php` (1x):
  - Line 79: `$context = createMultiOrganismToolContext($organisms);`

---

## lib/functions_validation.php

**6 function(s)**

### `test_input()` (Line 23)

Located in: `lib/functions_validation.php` at line 23

**Description:**

```
/**
* Sanitize user input - remove dangerous characters
*
* DEPRECATED: Use context-specific sanitization instead:
* - For database queries: Use prepared statements with parameter binding
* - For HTML output: Use htmlspecialchars() at the point of output
* - For URL parameters: Use urlencode()/urldecode() as needed
*
* This function is kept for backwards compatibility but combines multiple
* concerns and is typically misused. It applies both raw character removal
* and HTML escaping, which should be handled separately based on context.
*
* @param string $data - Raw user input
* @return string - Sanitized string with < > removed and HTML entities escaped
* @deprecated Use prepared statements and context-specific escaping
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_validation.php` (1x):
  - Line 23: `function test_input($data) {`

### `sanitize_search_input()` (Line 40)

Located in: `lib/functions_validation.php` at line 40

**Description:**

```
/**
* Sanitize search input specifically for use in database search queries
*
* This function handles search-specific sanitization that removes or escapes
* characters that could interfere with search functionality while preserving
* useful search characters like spaces, quotes, and basic punctuation.
*
* @param string $input - Raw search input from user
* @return string - Sanitized search string safe for database queries
*/
```

**Used in 2 unique file(s) (3 total times):**
- `/data/moop/lib/functions_validation.php` (2x):
  - Line 40: `function sanitize_search_input($input) {`
  - Line 64: `$term = sanitize_search_input($term);`
- `/data/moop/tools/annotation_search_ajax.php` (1x):
  - Line 49: `$search_input = sanitize_search_input($search_keywords, $quoted_search);`

### `validate_search_term()` (Line 63)

Located in: `lib/functions_validation.php` at line 63

**Description:**

```
/**
* Validate a search term for safety and usability
*
* Checks that a search term meets minimum requirements and doesn't contain
* problematic patterns that could cause issues with database queries or
* return meaningless results.
*
* @param string $term - Search term to validate
* @return array - Validation result with 'valid' boolean and 'error' message
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_validation.php` (1x):
  - Line 63: `function validate_search_term($term) {`

### `is_quoted_search()` (Line 92)

Located in: `lib/functions_validation.php` at line 92

**Description:**

```
/**
* Check if a search term is quoted (surrounded by quotes)
*
* @param string $term - Search term to check
* @return bool - True if term is quoted, false otherwise
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/functions_validation.php` (1x):
  - Line 92: `function is_quoted_search($term) {`

### `validateOrganismParam()` (Line 107)

Located in: `lib/functions_validation.php` at line 107

**Description:**

```
/**
* Validate and extract organism parameter from GET/POST
* Redirects to home if missing/empty
*
* @param string $organism_name Organism name to validate
* @param string $redirect_on_empty URL to redirect to if empty (default: /moop/index.php)
* @return string Validated organism name
*/
```

**Used in 4 unique file(s) (4 total times):**
- `/data/moop/lib/functions_validation.php` (1x):
  - Line 107: `function validateOrganismParam($organism_name, $redirect_on_empty = \'/moop/index.php\') {`
- `/data/moop/lib/functions_display.php` (1x):
  - Line 227: `$organism_name = validateOrganismParam($organism_name, $redirect_home);`
- `/data/moop/tools/assembly_display.php` (1x):
  - Line 8: `$organism_name = validateOrganismParam($_GET[\'organism\'] ?? \'\');`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 11: `$organism_name = validateOrganismParam($_GET[\'organism\'] ?? \'\', null);`

### `validateAssemblyParam()` (Line 123)

Located in: `lib/functions_validation.php` at line 123

**Description:**

```
/**
* Validate and extract assembly parameter from GET/POST
* Redirects to home if missing/empty
*
* @param string $assembly Assembly accession to validate
* @param string $redirect_on_empty URL to redirect to if empty
* @return string Validated assembly name
*/
```

**Used in 3 unique file(s) (3 total times):**
- `/data/moop/lib/functions_validation.php` (1x):
  - Line 123: `function validateAssemblyParam($assembly, $redirect_on_empty = \'/moop/index.php\') {`
- `/data/moop/tools/assembly_display.php` (1x):
  - Line 9: `$assembly_accession = validateAssemblyParam($_GET[\'assembly\'] ?? \'\');`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 12: `$uniquename = validateAssemblyParam($_GET[\'uniquename\'] ?? \'\', null);`

---

## lib/parent_functions.php

**6 function(s)**

### `getAncestors()` (Line 18)

Located in: `lib/parent_functions.php` at line 18

**Description:**

```
/**
* Get hierarchy of features (ancestors)
* Traverses up the feature hierarchy from a given feature to its parents/grandparents
* Optionally filters by genome_ids for permission-based access
*
* @param string $feature_uniquename - The feature uniquename to start from
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
* @return array - Array of features: [self, parent, grandparent, ...]
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/parent_functions.php` (1x):
  - Line 18: `function getAncestors($feature_uniquename, $dbFile, $genome_ids = []) {`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 71: `$ancestors = getAncestors($uniquename, $db, $accessible_genome_ids);`

### `getAncestorsByFeatureId()` (Line 28)

Located in: `lib/parent_functions.php` at line 28

**Description:**

```
/**
* Helper function for recursive ancestor traversal
* Fetches ancestors by feature_id (used internally by getAncestors)
* Optionally filters by genome_ids for permission-based access
*
* @param int $feature_id - The feature ID to start from
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Array of ancestor features
*/
```

**Used in 1 unique file(s) (3 total times):**
- `/data/moop/lib/parent_functions.php` (3x):
  - Line 28: `$parent_ancestors = getAncestorsByFeatureId($feature[\'parent_feature_id\'], $dbFile, $genome_ids);`
  - Line 45: `function getAncestorsByFeatureId($feature_id, $dbFile, $genome_ids = []) {`
  - Line 55: `$parent_ancestors = getAncestorsByFeatureId($feature[\'parent_feature_id\'], $dbFile, $genome_ids);`

### `getChildren()` (Line 72)

Located in: `lib/parent_functions.php` at line 72

**Description:**

```
/**
* Get all children and descendants of a feature
* Recursively fetches all child features at any depth
* Optionally filters by genome_ids for permission-based access
*
* @param int $feature_id - The parent feature ID
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
* @return array - Flat array of all children and descendants
*/
```

**Used in 3 unique file(s) (4 total times):**
- `/data/moop/lib/parent_functions.php` (2x):
  - Line 72: `function getChildren($feature_id, $dbFile, $genome_ids = []) {`
  - Line 79: `$child_descendants = getChildren($row[\'feature_id\'], $dbFile, $genome_ids);`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 122: `$children = getChildren($feature_id, $db, $accessible_genome_ids);`
- `/data/moop/tools/retrieve_sequences.php` (1x):
  - Line 99: `$children = getChildren($feature_id, $db);`

### `generateAnnotationTableHTML()` (Line 99)

Located in: `lib/parent_functions.php` at line 99

**Description:**

```
/**
* Generate annotation table with export buttons
* Creates a responsive HTML table displaying annotations with sorting/filtering
*
* @param array $results - Annotation results from database
* @param string $uniquename - Feature uniquename (for export)
* @param string $type - Feature type (for export)
* @param int $count - Table counter (ensures unique IDs)
* @param string $annotation_type - Type of annotation (e.g., "InterPro")
* @param string $desc - Description/definition of annotation type
* @param string $color - Bootstrap color class for badge
* @param string $organism - Organism name (for export)
* @return string - HTML for the annotation table section
*/
```

**Used in 2 unique file(s) (3 total times):**
- `/data/moop/lib/parent_functions.php` (1x):
  - Line 99: `function generateAnnotationTableHTML($results, $uniquename, $type, $count, $annotation_type, $desc, $color = \'warning\', $organism = \'\') {`
- `/data/moop/tools/parent_display.php` (2x):
  - Line 257: `echo generateAnnotationTableHTML($annot_results, $feature_uniquename, $type, $count, $annotation_type, $analysis_desc[$annotation_type] ?? \'\', $color, $organism_name);`
  - Line 321: `echo generateAnnotationTableHTML($annot_results, $child_uniquename, $child_type, $count, $annotation_type, $analysis_desc[$annotation_type] ?? \'\', $color, $organism_name);`

### `getAllAnnotationsForFeatures()` (Line 195)

Located in: `lib/parent_functions.php` at line 195

**Description:**

```
/**
* Get all annotations for multiple features at once (optimized)
* Fetches annotations for multiple features in a single query
* Optionally filters by genome_ids for permission-based access
*
* @param array $feature_ids - Array of feature IDs to fetch annotations for
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
* @return array - Organized as [$feature_id => [$annotation_type => [results]]]
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/parent_functions.php` (1x):
  - Line 195: `function getAllAnnotationsForFeatures($feature_ids, $dbFile, $genome_ids = []) {`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 129: `$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);`

### `generateTreeHTML()` (Line 256)

Located in: `lib/parent_functions.php` at line 256

**Description:**

```
/**
* Generate tree-style HTML for feature hierarchy
* Creates a hierarchical list with box-drawing characters (like Unix 'tree' command)
*
* @param int $feature_id - The parent feature ID
* @param string $dbFile - Path to SQLite database
* @param string $prefix - Internal use for recursion
* @param bool $is_last - Internal use for recursion
* @return string - HTML string with nested ul/li tree structure
*/
```

**Used in 2 unique file(s) (3 total times):**
- `/data/moop/lib/parent_functions.php` (2x):
  - Line 256: `function generateTreeHTML($feature_id, $dbFile, $prefix = \'\', $is_last = true, $genome_ids = []) {`
  - Line 299: `$html .= generateTreeHTML($row[\'feature_id\'], $dbFile, $prefix, $is_last_child, $genome_ids);`
- `/data/moop/tools/parent_display.php` (1x):
  - Line 221: `<?= generateTreeHTML($feature_id, $db) ?>`

---

## lib/tool_config.php

**4 function(s)**

### `getTool()` (Line 52)

Located in: `lib/tool_config.php` at line 52

**Description:**

```
/**
* Get a specific tool configuration
*
* @param string $tool_id - The tool identifier
* @return array|null - Tool configuration or null if not found
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/lib/tool_config.php` (2x):
  - Line 52: `function getTool($tool_id) {`
  - Line 76: `$tool = getTool($tool_id);`

### `getAllTools()` (Line 62)

Located in: `lib/tool_config.php` at line 62

**Description:**

```
/**
* Get all available tools
*
* @return array - Array of all tool configurations
*/
```

**Used in 1 unique file(s) (1 total times):**
- `/data/moop/lib/tool_config.php` (1x):
  - Line 62: `function getAllTools() {`

### `buildToolUrl()` (Line 75)

Located in: `lib/tool_config.php` at line 75

**Description:**

```
/**
* Build tool URL with context parameters
*
* @param string $tool_id - The tool identifier
* @param array $context - Context array with organism, assembly, group, display_name
* @param string $site - Site variable (from site_config.php)
* @return string|null - Built URL or null if tool not found
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/tool_config.php` (1x):
  - Line 75: `function buildToolUrl($tool_id, $context, $site) {`
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 35: `$url = buildToolUrl($tool_id, $context, $site);`

### `isToolVisibleOnPage()` (Line 105)

Located in: `lib/tool_config.php` at line 105

**Description:**

```
/**
* Check if a tool should be visible on a specific page
*
* @param array $tool - Tool configuration
* @param string $page - Page identifier (index, organism, group, assembly, parent, multi_organism_search)
* @return bool - True if tool should be visible on this page
*/
```

**Used in 2 unique file(s) (2 total times):**
- `/data/moop/lib/tool_config.php` (1x):
  - Line 105: `function isToolVisibleOnPage($tool, $page) {`
- `/data/moop/lib/functions_tools.php` (1x):
  - Line 31: `if ($current_page && !isToolVisibleOnPage($tool, $current_page)) {`

---

## tools/sequences_display.php

**1 function(s)**

### `extractSequencesFromFasta()` (Line 113)

Located in: `tools/sequences_display.php` at line 113

**Description:**

```
/**
* Extract sequences from a FASTA file for specific feature IDs
*
* @param string $fasta_file Path to FASTA file
* @param array $feature_ids Array of feature IDs to extract
* @return array Associative array with feature_id => sequence content
*/
```

**Used in 1 unique file(s) (2 total times):**
- `/data/moop/tools/sequences_display.php` (2x):
  - Line 113: `$sequences = extractSequencesFromFasta($fasta_file, $feature_ids, $seq_type, $extraction_errors);`
  - Line 252: `function extractSequencesFromFasta($fasta_file, $feature_ids, $seq_type, &$errors) {`

---

