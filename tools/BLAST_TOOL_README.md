# BLAST Tool Implementation

## Overview

The BLAST tool has been modernized to:
1. **Match the existing style** of other MOOP tools (FASTA download, search tools)
2. **Use centralized BLAST functions** shared across the application
3. **Integrate with the toolbox system** for consistent UI/UX
4. **Respect permission/access controls** for assemblies and databases
5. **Dynamically filter databases** based on BLAST program type

## Architecture

### New Files Created

#### `/data/moop/tools/blast_functions.php`
Centralized BLAST utilities used across the application:

- **`getBlastDatabases($assembly_path)`** - Scans assembly directory for BLAST databases
  - Returns array of available databases with type (nucleotide/protein)
  - Used by BLAST interface and other tools

- **`filterDatabasesByProgram($databases, $blast_program)`** - Filters databases by compatibility
  - BLASTn, tBLASTn, tBLASTx work with nucleotide databases
  - BLASTp, BLASTx work with protein databases
  - Used to show only compatible databases in the UI

- **`executeBlastSearch($query_seq, $blast_db, $program, $options)`** - Runs BLAST command
  - Takes FASTA sequence, database path, program, and options
  - Returns structured result with output, error messages, and exit code
  - Handles proper command escaping and error handling

- **`extractSequencesFromBlastDb($blast_db, $sequence_ids)`** - Extracts sequences via blastdbcmd
  - Previously duplicated in fasta_extract.php, download_fasta.php, sequences_display.php
  - Now consolidated and shared across all tools

- **`validateBlastSequence($sequence)`** - Validates FASTA input
  - Checks for valid format
  - Automatically adds FASTA header if missing
  - Returns validation result with error message

#### `/data/moop/tools/blast/index.php`
Modern BLAST search interface:

**Features:**
- Responsive design matching existing FASTA download tool style
- BLAST program selector (BLASTn, BLASTp, BLASTx, tBLASTn, tBLASTx)
- Assembly selector with scrollable list
- **Database list that updates dynamically** based on:
  - Selected assembly
  - Selected BLAST program (filters to compatible databases)
- Advanced options: E-value, max hits, scoring matrix, low complexity filtering
- Sequence validation before search
- HTML results display with download option

**Access Control:**
- Uses `getAccessibleAssemblies()` to respect user permissions
- Only shows assemblies the user has access to
- Validates assembly access before executing search

**User Experience:**
- Auto-selects first assembly and updates database list
- Database list grouped by type (nucleotide/protein)
- Clear error messaging
- Sequence input accepts both FASTA format and raw sequence
- Results displayed inline with option to download

### Modified Files

#### `/data/moop/tools/tool_config.php`
Added BLAST search tool to the toolbox system:
```php
'blast_search' => [
    'id' => 'blast_search',
    'name' => 'BLAST Search',
    'icon' => 'fa-dna',
    'description' => 'Search sequences against databases',
    'btn_class' => 'btn-warning',
    'url_path' => '/tools/blast/index.php',
    'context_params' => [],
    'pages' => 'all',
]
```

Now appears in the tools section on all display pages (organism, assembly, features, etc.)

#### `/data/moop/tools/extract/fasta_extract.php`
- Replaced inline blastdbcmd code with call to `extractSequencesFromBlastDb()`
- Cleaner, more maintainable code
- Consistent error handling

#### `/data/moop/tools/extract/download_fasta.php`
- Replaced inline blastdbcmd code with call to `extractSequencesFromBlastDb()`
- Improved error logging
- Cleaner code flow

#### `/data/moop/tools/display/sequences_display.php`
- Replaced inline blastdbcmd code with call to `extractSequencesFromBlastDb()`
- Better error handling and messaging
- Code consistency

## Key Features

### 1. Dynamic Database Filtering

The BLAST program selector automatically updates the available database list:

```javascript
// When user changes BLAST program
function updateDatabaseList() {
    const selectedProgram = document.getElementById('blast_program').value;
    const allDatabases = /* databases for selected assembly */;
    
    // Filter databases by program compatibility
    const compatible = allDatabases.filter(db => {
        if (['blastp', 'blastx'].includes(program)) 
            return db.type === 'protein';
        if (['blastn', 'tblastn', 'tblastx'].includes(program)) 
            return db.type === 'nucleotide';
        return true;
    });
}
```

### 2. Access Control Integration

The tool respects MOOP's assembly access system:

```php
// Get only assemblies user has access to
$sources_by_group = getAccessibleAssemblies();

// Verify access before executing search
$selected_source = null;
foreach ($accessible_sources as $source) {
    if ($source['assembly'] === $selected_assembly) {
        $selected_source = $source;
        break;
    }
}

if (!$selected_source) {
    $search_error = "You do not have access to the selected assembly.";
}
```

### 3. Unified BLAST Function Library

All BLAST operations now use centralized functions:

| Previous Location | Function | New Location |
|---|---|---|
| fasta_extract.php (inline) | Extract sequences | blast_functions.php |
| download_fasta.php (inline) | Extract sequences | blast_functions.php |
| sequences_display.php (inline) | Extract sequences | blast_functions.php |
| blast/old/ files | BLAST execution | blast_functions.php |

## Usage

### For Users

1. Navigate to BLAST Search from the tools menu or any organism/assembly page
2. Paste DNA or protein sequence (FASTA format or raw)
3. Select BLAST program (automatically filters compatible databases)
4. Choose assembly and database
5. Adjust advanced options if needed
6. Click "Search" to execute
7. View results inline or download as HTML

### For Developers

#### Using BLAST Functions

```php
include_once __DIR__ . '/../blast_functions.php';

// Get databases for an assembly
$databases = getBlastDatabases('/path/to/assembly');

// Filter for specific program
$protein_dbs = filterDatabasesByProgram($databases, 'blastp');

// Execute search
$result = executeBlastSearch(
    $sequence,
    '/path/to/database',
    'blastp',
    ['evalue' => '1e-6', 'max_hits' => 50]
);

if ($result['success']) {
    echo $result['output'];
} else {
    echo "Error: " . $result['error'];
}

// Extract sequences
$extract = extractSequencesFromBlastDb(
    '/path/to/database',
    ['seq1', 'seq2', 'seq3']
);
```

#### Adding BLAST to Toolbox

BLAST is already registered in `tool_config.php` and appears in the tools section on all pages. To show it on specific pages only, modify:

```php
'pages' => ['organism', 'assembly'],  // Show only on these pages
```

## Design Decisions

### 1. Centralized Functions
Rather than duplicating blastdbcmd code across multiple files, all BLAST operations are in one file. This:
- Reduces code duplication
- Makes maintenance easier
- Ensures consistent error handling
- Allows for easier future improvements

### 2. Dynamic Database Filtering
Database lists are filtered by BLAST program type in JavaScript to:
- Provide immediate user feedback
- Prevent invalid program/database combinations
- Improve user experience

### 3. Access Control at Multiple Levels
- Assembly-level filtering (only accessible assemblies shown)
- Runtime validation (verify access before search)
- This ensures consistency with other MOOP tools

### 4. Responsive Design
The interface uses Bootstrap and custom CSS matching:
- FASTA download tool styling
- Existing color scheme
- Accessibility best practices

## Database Format Requirements

BLAST databases must follow standard NCBI format:

**Nucleotide Databases:**
- `.nhr` - Header file (required)
- `.nin` - Index file (required) OR `.nal` - Alias list
- `.nsq` - Sequence file (required)

**Protein Databases:**
- `.phr` - Header file (required)
- `.pin` - Index file (required) OR `.pal` - Alias list
- `.psq` - Sequence file (required)

The `getBlastDatabases()` function automatically detects and lists available databases.

## Future Enhancements

Possible improvements:
1. Multi-sequence BLAST searches (fasta input with multiple sequences)
2. BLAST job queue/background processing for long searches
3. Results visualization and alignment viewing
4. Saved search history
5. Custom BLAST parameter presets
6. Export results in multiple formats (CSV, JSON, etc.)

## Troubleshooting

### "Selected BLAST database not found"
- Verify database files exist in assembly directory
- Check database naming follows NCBI format
- Ensure .nhr/.phr and .nin/.pin or .nal/.pal files present

### "No compatible databases found for this program"
- Verify BLAST program is correctly selecting
- Check assembly has databases of compatible type
- For BLASTp/BLASTx - need protein databases (.phr)
- For BLASTn/tBLASTn/tBLASTx - need nucleotide databases (.nhr)

### "BLAST execution failed"
- Check BLAST+ is installed on the server: `which blastp`
- Verify database files are readable: `ls -l database.*`
- Check BLAST can access database: `blastdbinfo -db /path/to/database`

### "No sequences found for requested IDs"
- Verify sequence IDs match exactly (case-sensitive)
- Check sequence IDs exist in database: `blastdbcmd -db ... -info`
- Check for leading/trailing whitespace in IDs

## Testing

All modified files have been validated for:
- PHP syntax correctness
- Function availability
- Integration with existing code
- Access control flow

To test the BLAST tool:
1. Ensure assemblies with BLAST databases exist
2. Navigate to `/moop/tools/blast/index.php`
3. Verify assemblies appear in the selector
4. Verify database list updates when BLAST program changes
5. Test with a sample sequence

