# Library Functions Documentation

This directory contains all shared PHP functions used across MOOP. Functions are organized by category to facilitate discovery and reuse.

---

## Quick Start

### Using a Library Function

```php
// 1. Find which file has the function (see below or check registry)
// 2. Include that file
include_once __DIR__ . '/../lib/functions_data.php';

// 3. Call the function
$organisms = getAccessibleOrganisms();
```

### Adding a New Function

```php
// 1. Determine category (data? display? validation?)
// 2. Add to appropriate functions_*.php file
// 3. Document with PHPDoc comments
// 4. Regenerate registry via admin interface
```

---

## File Organization by Category

### Data & Retrieval (Loading and querying data)

#### `functions_data.php` (217 lines)
**Purpose:** Load organisms, assemblies, groups; access-controlled data retrieval

**Key Functions:**
- `getAccessibleOrganisms()` - Get organisms user can access
- `getAccessibleAssemblies()` - Get assemblies user can access  
- `getAccessibleGroups()` - Get groups user can access
- `getOrganismList($organism_data)` - Load all organisms
- `flattenSourcesList($sources_by_group)` - Flatten nested sources
- `filterAssembliesByOrganism($sources, $organism)` - Filter by organism

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_data.php';
$orgs = getAccessibleOrganisms();
$groups = getAccessibleGroups();
```

#### `database_queries.php` (148 lines)
**Purpose:** Database query operations, result processing

**Key Functions:**
- Query execution helpers
- Result formatting
- Row processing
- Data transformation

**Usage:** Called internally by higher-level functions

#### `functions_database.php` (184 lines)
**Purpose:** Database connection, prepared statements, helpers

**Key Functions:**
- Database connection management
- Query execution wrappers
- Result fetching
- Error handling

**Usage:** Foundation for all database operations

#### `functions_json.php` (103 lines)
**Purpose:** JSON file loading, validation, error handling

**Key Functions:**
- `loadJsonFile($path, $default)` - Safe JSON loading
- `validateJsonFile($path)` - Check JSON validity
- Organism metadata loading

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_json.php';
$data = loadJsonFile('/path/to/file.json', []);
```

---

### Display & Rendering (HTML output, formatting)

#### `functions_display.php` (509 lines)
**Purpose:** Display utilities, formatting, organism image/info display

**Key Functions:**
- `loadOrganismInfo($organism_name, $organism_data)` - Load organism metadata
- `getOrganismImagePath($organism, $absolute_images_path)` - Get image path
- `loadOrganismAndGetImagePath(...)` - Combined loader
- HTML formatting helpers

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_display.php';
$info = loadOrganismInfo('Homo_sapiens', $organism_data);
$image = getOrganismImagePath('Homo_sapiens', $abs_path);
```

#### `display_functions.php` (23 lines)
**Purpose:** Display rendering helpers (minimal, mostly empty)

**Note:** Most display logic in `functions_display.php`

#### `parent_functions.php` (234 lines)
**Purpose:** Feature/parent display helpers, hierarchy navigation

**Key Functions:**
- Feature detail page helpers
- Hierarchy navigation (parents, children, siblings)
- Feature relationship queries
- Annotation display helpers

**Usage:** Called by feature detail page (parent.php)

#### `tool_section.php` (107 lines)
**Purpose:** Tool section rendering, toolbar, tool configuration

**Key Functions:**
- Render tool section HTML
- Build toolbar buttons
- Configure tool display
- Tool context helpers

**Usage:** Called by display pages to render tool sections

---

### Searching & Analysis (BLAST, sequence search, extraction)

#### `search_functions.php` (312 lines)
**Purpose:** Search and query helpers, feature searching

**Key Functions:**
- Feature search queries
- Search term parsing
- Result filtering and sorting
- Search index operations

**Usage:**
```php
include_once __DIR__ . '/../lib/search_functions.php';
$results = searchFeatures($search_term, $assembly_db);
```

#### `blast_functions.php` (432 lines)
**Purpose:** BLAST searching and database operations

**Key Functions:**
- `getBlastDatabases($assembly_path)` - Scan for available databases
- `filterDatabasesByProgram($databases, $program)` - Filter by compatibility
- `executeBlastSearch($query, $db_path, $program, $options)` - Run BLAST
- `validateBlastSequence($sequence)` - Validate input
- Database format validation

**Usage:**
```php
include_once __DIR__ . '/../lib/blast_functions.php';
$dbs = getBlastDatabases($assembly_path);
$result = executeBlastSearch($seq, $db, 'blastp', $opts);
```

#### `blast_results_visualizer.php` (236 lines)
**Purpose:** BLAST result formatting and HTML display

**Key Functions:**
- Parse BLAST XML output
- Format results for display
- Highlight alignments
- Create result tables

**Usage:**
```php
include_once __DIR__ . '/../lib/blast_results_visualizer.php';
$html = formatBlastResults($blast_xml_output);
```

#### `extract_search_helpers.php` (248 lines)
**Purpose:** Sequence extraction and search helpers

**Key Functions:**
- Extract sequences from BLAST databases
- Parse sequence requests
- Format sequence output
- Handle multiple sequence types

**Usage:** Called by sequence extraction tools

---

### File & System (File operations, paths, system utilities)

#### `functions_filesystem.php` (287 lines)
**Purpose:** File system operations, path handling, directory scanning

**Key Functions:**
- `getDirectorySize($path)` - Calculate directory size
- `scanOrganimDirectory($organism_data)` - List organisms
- `getRegistryLastUpdate($registry_file)` - Check registry staleness
- File permissions checking
- Path validation and normalization

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_filesystem.php';
$size = getDirectorySize('/data/moop/organism_data/Homo_sapiens');
```

#### `functions_system.php` (156 lines)
**Purpose:** System utilities, cleanup, optimization

**Key Functions:**
- System information
- Cleanup operations
- Cache management
- Performance utilities

**Usage:** Called internally by system maintenance tasks

#### `fasta_download_handler.php` (289 lines)
**Purpose:** FASTA download processing, format conversion, streaming

**Key Functions:**
- `generateFastaFile($sequences, $format)` - Create FASTA
- `streamFastaDownload($fasta_data, $filename)` - Download
- Format conversion (DNA to protein, etc.)
- Sequence validation for FASTA

**Usage:**
```php
include_once __DIR__ . '/../lib/fasta_download_handler.php';
streamFastaDownload($fasta_content, 'sequences.fasta');
```

---

### Security & Validation (Access control, input validation, error handling)

#### `functions_access.php` (198 lines)
**Purpose:** Access control and permissions checking

**Key Functions:**
- User permission validation
- Organism/assembly access checks
- Role-based access control
- Group membership validation

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_access.php';
if (!canAccessOrganism($organism, $_SESSION['user'])) {
    header('Location: /access_denied.php');
    exit;
}
```

#### `functions_validation.php` (267 lines)
**Purpose:** Input validation and sanitization

**Key Functions:**
- Validate organism names
- Validate assembly names
- Validate sequence input
- Sanitize user input
- Check file paths
- Validate parameters

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_validation.php';
if (!validateOrganismName($organism)) {
    echo "Invalid organism name";
    exit;
}
```

#### `functions_errorlog.php` (89 lines)
**Purpose:** Error logging and handling

**Key Functions:**
- Log errors to file
- Format error messages
- Track error types
- Email alerts (optional)

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_errorlog.php';
logError("Something went wrong: " . $error_message);
```

---

### Utilities (General purpose, helpers, configuration)

#### `moop_functions.php` (324 lines)
**Purpose:** General-purpose utilities, commonly used helpers

**Key Functions:**
- `loadJsonFile($path, $default)` - JSON loading
- `saveJsonFile($path, $data)` - JSON saving
- `getDirectorySize($path)` - Directory size
- HTML encoding/escaping
- Array utilities
- String utilities

**Usage:**
```php
include_once __DIR__ . '/../lib/moop_functions.php';
$data = loadJsonFile('/path/to/file.json', []);
```

#### `common_functions.php` (143 lines)
**Purpose:** Common/shared utility functions

**Key Functions:**
- Commonly repeated operations
- Convenient wrappers around other functions
- Shorthand helpers

**Usage:** Called by many pages for convenience functions

#### `tool_config.php` (123 lines)
**Purpose:** Tool configuration and metadata

**Key Functions:**
- Load tool configuration
- Get tool info
- Build tool lists
- Configure tool behavior

**Usage:**
```php
include_once __DIR__ . '/../lib/tool_config.php';
$tools = getAllTools();
$tool = getTool('blast_search');
```

#### `functions_tools.php` (156 lines)
**Purpose:** Tool support and context utilities

**Key Functions:**
- Parse tool context parameters
- Get accessible tools for user
- Tool availability checking
- Tool context preparation

**Usage:**
```php
include_once __DIR__ . '/../lib/functions_tools.php';
$context = parseContextParameters();
```

---

### Backup Files

#### `blast_functions.php.backup` (432 lines)
**Purpose:** Backup of BLAST functions (version control)

**Note:** Do not include in production code. For version control only.

---

## Function Registry

### Purpose

The function registry provides a centralized way to discover and document all available functions.

### Accessing the Registry

**For Users:**
- Log in as admin
- Navigate to "Manage > Function Registry"
- Search for function name or keyword
- View file location, parameters, documentation

**For Developers:**
- Check `/docs/function_registry.json`
- Contains all function metadata
- Auto-generated from code comments

### Example Registry Entry

```json
{
  "function_name": "getAccessibleOrganisms",
  "file": "lib/functions_data.php",
  "parameters": {
    "user_id": "string (optional)"
  },
  "returns": "array of organism names",
  "description": "Get organisms user has access to",
  "examples": [
    "$orgs = getAccessibleOrganisms();",
    "$orgs = getAccessibleOrganisms('user@example.com');"
  ],
  "lastUpdated": "2026-01-15"
}
```

### Regenerating the Registry

**When to regenerate:**
- After adding new functions
- After updating documentation
- After renaming functions
- Before deployment

**How to regenerate:**
1. Log in as admin
2. Navigate to "Manage > Function Registry"
3. Click "Regenerate Registry"
4. System scans all lib/*.php files
5. Extracts function names and documentation
6. Updates `/docs/function_registry.json`

### Documenting Functions

For functions to appear in the registry, they must have PHPDoc comments:

```php
/**
 * Brief description
 * 
 * Longer description of what the function does,
 * including any important details or behavior.
 *
 * @param string $organism_name The name of the organism
 * @param array $options Optional settings
 * @return array Array of organism info
 * 
 * @example
 * $info = getOrganismInfo('Homo_sapiens');
 */
function getOrganismInfo($organism_name, $options = []) {
    // Implementation
}
```

---

## Including Functions in Your Code

### Basic Pattern

```php
<?php
include_once __DIR__ . '/tool_init.php';  // Get config
include_once __DIR__ . '/../lib/functions_data.php';  // Load functions

// Now functions are available
$organisms = getAccessibleOrganisms();
?>
```

### Best Practices

1. **Include at top** - After tool_init.php
2. **Include once** - Use `include_once`, not `include`
3. **Use appropriate file** - Find function in right category
4. **Check registry** - Use admin interface to verify function exists
5. **Document usage** - Add comments explaining what functions do
6. **Validate inputs** - Check user input before passing to functions

### Common Mistakes

❌ **Wrong:** Including files not related to your code
```php
include_once __DIR__ . '/../lib/blast_functions.php';  // If not using BLAST
```

✅ **Right:** Only include files you need
```php
include_once __DIR__ . '/../lib/functions_data.php';  // Data loading
```

❌ **Wrong:** Duplicating function logic
```php
$organisms = array_keys($metadata['organisms']);  // Duplicating function
```

✅ **Right:** Using centralized function
```php
include_once __DIR__ . '/../lib/functions_data.php';
$organisms = getAccessibleOrganisms();  // Single implementation
```

---

## Statistics

| Category | Files | Lines | Purpose |
|----------|-------|-------|---------|
| Data & Retrieval | 4 | 652 | Load data, queries |
| Display & Rendering | 4 | 773 | HTML, formatting |
| Searching & Analysis | 4 | 1,228 | Search, BLAST |
| File & System | 3 | 732 | Files, cleanup |
| Security & Validation | 3 | 554 | Permissions, input |
| Utilities | 4 | 746 | Helpers, config |
| **TOTAL** | **23** | **4,685** | **All shared functions** |

---

## Adding a New Function

### Step 1: Choose the Right File

Determine which category your function belongs to:
- Data loading? → `functions_data.php`
- Display formatting? → `functions_display.php`
- File operations? → `functions_filesystem.php`
- Input validation? → `functions_validation.php`
- General helper? → `moop_functions.php`

### Step 2: Write the Function

```php
/**
 * Brief description
 * 
 * Longer description of what the function does.
 *
 * @param type $param Description
 * @return type Description
 */
function myNewFunction($param) {
    // Implementation
    return $result;
}
```

### Step 3: Document It

Add PHPDoc comments with:
- Brief description (first line)
- Longer description
- `@param` for each parameter
- `@return` for return value
- `@example` for usage

### Step 4: Regenerate Registry

1. Log in as admin
2. Manage > Function Registry
3. Click "Regenerate Registry"
4. Function now appears in registry

### Step 5: Include and Use

```php
include_once __DIR__ . '/../lib/functions_category.php';
$result = myNewFunction($value);
```

---

## Related Documentation

- **Comprehensive Overview:** See `MOOP_COMPREHENSIVE_OVERVIEW.md` - Function Registry section
- **Tools Guide:** See `tools/DEVELOPER_GUIDE.md`
- **Admin Guide:** See `admin/DEVELOPER_GUIDE.md`
- **Configuration:** See `config/README.md`

---

**Last Updated:** January 2026
