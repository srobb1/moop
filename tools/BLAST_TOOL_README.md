# BLAST Search Tool

## Overview

The BLAST Search tool integrates BLAST+ sequence searching into MOOP. Users can search sequences against organism databases while respecting access permissions and group/organism context.

---

## Architecture

### Controller & View Pattern

**Controller:** `/data/moop/tools/blast.php`
- Initializes tool environment
- Loads accessible assemblies
- Handles form submission
- Processes BLAST searches
- Prepares data for view
- Calls layout system

**View:** `/data/moop/tools/pages/blast.php`
- Renders source selector (organism/assembly)
- Renders BLAST program selector
- Renders sequence input form
- Displays BLAST results
- Includes JavaScript for interactivity

### Libraries Used

**lib/blast_functions.php**
- `getBlastDatabases($assembly_path)` - Scan for BLAST databases
- `filterDatabasesByProgram($databases, $blast_program)` - Filter by compatibility
- `executeBlastSearch($query_seq, $db_path, $program, $options)` - Run BLAST
- `validateBlastSequence($sequence)` - Validate input sequence

**lib/blast_results_visualizer.php**
- Format BLAST XML results for display
- Create HTML result tables
- Highlight alignments

**lib/extract_search_helpers.php**
- Sequence extraction helpers
- Search utilities

**includes/source-selector-helpers.php**
- Source selection logic
- Assembly filtering by context

---

## How It Works

### Data Flow

```
User visits /tools/blast.php
    ↓
blast.php (controller) loads:
├─ Configuration (organisms, sequences types, etc.)
├─ Tool initialization (tool_init.php)
├─ Access control (user permissions)
├─ Accessible assemblies (respect user access)
└─ BLAST libraries (blast_functions.php, etc.)
    ↓
Prepare data:
├─ Get context (organism, assembly, group if provided)
├─ Build source selector options
├─ Prepare BLAST program choices
└─ Load layout system
    ↓
render_display_page() calls layout.php with:
├─ Content file: pages/blast.php
├─ Data array with sources, programs, etc.
└─ Page title
    ↓
pages/blast.php (view) displays:
├─ Source selector (organism/assembly picker)
├─ BLAST program selector
├─ Sequence input textarea
├─ Advanced options (evalue, maxhits, matrix)
└─ Results if search was performed
    ↓
User enters sequence and selects:
├─ BLAST program (BLASTn, BLASTp, etc.)
├─ Organism/Assembly
└─ Advanced options
    ↓
Form submitted to POST
    ↓
blast.php handles POST:
├─ Validate sequence format
├─ Validate selected assembly (access check)
├─ Get database path from assembly
├─ Execute BLAST search via executeBlastSearch()
├─ Format results via blast_results_visualizer
└─ Pass results back to view
    ↓
pages/blast.php displays results
```

---

## BLAST Program Compatibility

| Program | Input | Database | Use Case |
|---------|-------|----------|----------|
| **BLASTn** | DNA | Nucleotide | DNA vs DNA |
| **BLASTp** | Protein | Protein | Protein vs Protein |
| **BLASTx** | DNA (→Protein) | Protein | DNA translated vs Protein |
| **tBLASTn** | Protein (→DNA) | Nucleotide | Protein vs DNA translated |
| **tBLASTx** | DNA (→DNA) | Nucleotide | DNA translated vs DNA translated |

### Database Requirements

BLAST databases are created using `makeblastdb` command during organism setup.

Database files for each assembly:
```
/organism_data/Organism/Assembly/
├── *.nhr, *.nin, *.nsq    (nucleotide BLAST databases)
└── *.phr, *.pin, *.psq    (protein BLAST databases)
```

**Note:** SQLite databases (genes.sqlite, annotations.sqlite) are created separately for searching feature data via:
- `create_schema_sqlite.sql` - SQLite schema
- `import_genes_sqlite.pl` - Import gene data
- `load_annotations_fast.pl` - Load annotations

These are used for feature search, not BLAST searches.

---

## Using the BLAST Tool

### For End Users

1. **Access the Tool**
   - Click "BLAST Search" from any page's Tools menu
   - Or navigate directly to `/moop/tools/blast.php`

2. **Select Source**
   - Choose organism from dropdown
   - Choose assembly (auto-filters from selected organism)
   - Available assemblies depend on user permissions

3. **Enter Sequence**
   - Paste FASTA format: `>header\nACGT...`
   - Or paste raw sequence: `ACGT...`
   - Tool auto-detects and validates

4. **Choose Program**
   - Select BLAST program (BLASTn, BLASTp, BLASTx, tBLASTn, tBLASTx)
   - Automatically filters compatible databases

5. **Optional: Advanced Settings**
   - E-value threshold (default: 1e-6)
   - Max number of hits (default: 50)
   - Scoring matrix (for protein searches)
   - Low complexity filtering (yes/no)

6. **Search**
   - Click "Search" button
   - Results display below after search completes

7. **Download Results**
   - Results shown in HTML table format
   - Can copy/save from browser

### For Developers

#### Including BLAST Functions

```php
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/blast_results_visualizer.php';
```

#### Getting Available Databases

```php
$assembly_path = '/data/moop/organism_data/Homo_sapiens/GRCh38';
$databases = getBlastDatabases($assembly_path);

// Returns array like:
// [
//   'refseq_rna' => ['type' => 'nucleotide', 'path' => '...'],
//   'proteins' => ['type' => 'protein', 'path' => '...'],
//   ...
// ]
```

#### Filtering Databases by Program

```php
$compatible_dbs = filterDatabasesByProgram($databases, 'blastp');
// Returns only protein databases for BLASTp
```

#### Running a BLAST Search

```php
$query = '>test\nMGHFDDRRGGYVASSDPDEQAEVRERL...';
$db_path = '/data/moop/organism_data/Homo_sapiens/GRCh38/proteins';
$options = [
    'evalue' => '1e-6',
    'max_target_seqs' => 50,
    'matrix' => 'BLOSUM62',  // for protein
    'dust' => 'yes'
];

$result = executeBlastSearch($query, $db_path, 'blastp', $options);

// Returns:
// [
//   'success' => true,
//   'output' => '<XML...>',
//   'error' => '',
//   'exit_code' => 0
// ]
```

#### Displaying Results

```php
$html_results = formatBlastResults($result['output']);
echo $html_results;
```

---

## Configuration

The BLAST tool uses configuration from ConfigManager:

```php
$config = ConfigManager::getInstance();

// Get paths
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');

// Get settings
$sequence_types = $config->getSequenceTypes();
$site_title = $config->getString('siteTitle');
```

---

## File Structure

```
tools/
├── blast.php                        # BLAST controller
├── pages/
│   └── blast.php                    # BLAST view template
├── BLAST_TOOL_README.md             # This file
├── BLAST_QUICK_REFERENCE.md         # Quick reference guide
└── DEVELOPER_GUIDE.md               # General tools guide

lib/
├── blast_functions.php              # BLAST functions
├── blast_functions.php.backup       # Backup of functions
├── blast_results_visualizer.php     # Result formatting
├── extract_search_helpers.php       # Search helpers
└── [other library files]
```

---

## Security Features

### Access Control

- Respects user permissions for assemblies
- Only shows assemblies user can access
- Validates assembly access before executing search

### Input Validation

- Validates FASTA format before executing
- Auto-adds FASTA header if missing
- Validates sequence characters (ATGC for DNA, standard amino acids for protein)

### Command Security

- Uses `escapeshellarg()` for all BLAST arguments
- Prevents command injection
- Validates database paths

### Error Handling

- Errors logged to `/data/moop/logs/error.log`
- User-friendly error messages (no system paths exposed)
- Graceful failure on missing databases

---

## Troubleshooting

### "No databases found for this assembly"

**Causes:**
- Assembly doesn't have BLAST databases created yet
- BLAST+ not installed on server
- Database files corrupted or missing

**Solution:**
- Verify BLAST+ is installed: `which blastp`
- Check files exist: `ls -la /organism_data/Organism/Assembly/`
- Recreate databases using setup scripts

### "Selected assembly not accessible"

**Causes:**
- User doesn't have permission for assembly
- Assembly removed from user's access list

**Solution:**
- Contact administrator
- Check user permissions in admin interface

### "Invalid sequence format"

**Causes:**
- Sequence contains invalid characters
- Malformed FASTA header
- Empty sequence

**Solution:**
- Ensure FASTA format: `>header\nACGT...`
- Or just raw sequence: `ACGTACGT...`
- Remove special characters (numbers, symbols)
- Ensure non-empty

### "BLAST search failed"

**Causes:**
- BLAST+ not installed
- Database corrupted
- Server system error
- Timeout on large sequences

**Solution:**
- Check server logs: `/data/moop/logs/error.log`
- Try smaller sequence or different database
- Contact system administrator

### "Empty results"

**Causes:**
- No matches found (expected)
- E-value threshold too stringent
- Wrong sequence type for database

**Solution:**
- Try lower E-value threshold (less stringent, more results)
- Verify program matches sequence type
- Try different assembly/database

---

## Performance Considerations

### Query Size
- Small sequences (< 500bp): Fast
- Medium sequences (500bp - 5kb): Moderate
- Large sequences (> 5kb): Can take minutes

### Database Size
- Smaller databases: Faster
- Larger databases (whole genomes): Slower

### Optimization Tips

1. **For Speed:**
   - Use higher E-value (less stringent, faster)
   - Reduce max hits requested
   - Search against smaller assemblies

2. **For Sensitivity:**
   - Use lower E-value (more stringent, slower)
   - Enable low complexity filtering
   - Use appropriate matrix (BLOSUM62 for protein)

3. **Typical Times:**
   - BLASTn on small database: < 1 second
   - BLASTp on large database: 10-30 seconds
   - tBLASTx on large database: 30+ seconds

---

## Future Enhancements

- [ ] Result caching for identical searches
- [ ] Batch search (multiple sequences at once)
- [ ] Result export (CSV, TSV, XML)
- [ ] Search history and saved searches
- [ ] Database selection by custom criteria
- [ ] Advanced scoring options
- [ ] Result filtering and sorting UI

---

## Related Documentation

- **Tools Development:** See `DEVELOPER_GUIDE.md`
- **Page Architecture:** See `MOOP_COMPREHENSIVE_OVERVIEW.md`
- **Configuration:** See `/config/README.md`
- **BLAST Quick Reference:** See `BLAST_QUICK_REFERENCE.md`

---

**Last Updated:** January 2026
