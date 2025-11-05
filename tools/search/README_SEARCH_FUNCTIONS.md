# Search Functions Library

## Overview

`search_functions.php` contains core database and search utility functions used throughout the MOOP search system. This file consolidates search-related helper functions that were previously scattered across different files.

## Location

`/data/moop/tools/search/search_functions.php`

## Purpose

This file provides:
- Database connection management
- SQL query execution with prepared statements
- Dynamic SQL LIKE clause generation for multi-column searches
- Input sanitization and validation for search queries
- Search term validation utilities

---

## Functions Reference

### 1. `getDbConnection($dbFile)`

Establishes a PDO connection to a SQLite database.

**Parameters:**
- `$dbFile` (string) - Path to the SQLite database file

**Returns:**
- `PDO` - Database connection object

**Throws:**
- `PDOException` - If connection fails

**Example:**
```php
$db = getDbConnection('/data/organisms/Drosophila_melanogaster/genes.sqlite');
```

**Notes:**
- Currently supports SQLite only
- Sets error mode to exception for better error handling
- Could be extended to support MySQL, PostgreSQL in the future

---

### 2. `fetchData($sql, $params, $dbFile)`

Executes a prepared SQL query and returns the results.

**Parameters:**
- `$sql` (string) - SQL query with placeholders (`?`)
- `$params` (array) - Parameters to bind to the query (default: `[]`)
- `$dbFile` (string) - Path to the SQLite database file

**Returns:**
- `array` - Array of associative arrays containing query results

**Throws:**
- `PDOException` - If query fails

**Example:**
```php
$sql = "SELECT * FROM feature WHERE feature_uniquename LIKE ? AND feature_type = ?";
$params = ['%LOC123%', 'gene'];
$results = fetchData($sql, $params, $db);

foreach ($results as $row) {
    echo $row['feature_uniquename'];
}
```

**Security:**
- Uses prepared statements to prevent SQL injection
- Automatically closes database connection after query

---

### 3. `buildLikeConditions($columns, $search, $quoted)`

Builds SQL LIKE conditions for multi-column search.

**Parameters:**
- `$columns` (array) - Array of column names to search
- `$search` (string) - Search term(s)
- `$quoted` (bool) - Whether to treat search as a single quoted phrase (default: `false`)

**Returns:**
- `array` - `[$sqlFragment, $params]`
  - `$sqlFragment` (string) - SQL WHERE clause fragment
  - `$params` (array) - Parameters for binding

**Search Modes:**

#### Keyword Search (Default)
Each term must match at least one column (AND logic between terms, OR logic within columns).

```php
$columns = ['feature_name', 'feature_description', 'annotation_description'];
$search = 'ABC transporter';
list($sql, $params) = buildLikeConditions($columns, $search, false);

// Result:
// SQL: "(feature_name LIKE ? OR feature_description LIKE ? OR annotation_description LIKE ?) 
//       AND (feature_name LIKE ? OR feature_description LIKE ? OR annotation_description LIKE ?)"
// Params: ["%ABC%", "%ABC%", "%ABC%", "%transporter%", "%transporter%", "%transporter%"]
```

#### Quoted/Phrase Search
Treats entire search as a single phrase (OR logic across columns).

```php
$columns = ['feature_name', 'feature_description'];
$search = 'ATP binding cassette';
list($sql, $params) = buildLikeConditions($columns, $search, true);

// Result:
// SQL: "(feature_name LIKE ? OR feature_description LIKE ?)"
// Params: ["%ATP binding cassette%", "%ATP binding cassette%"]
```

**Example Usage in Query:**
```php
$columns = ['f.feature_name', 'f.feature_description', 'a.annotation_description'];
$search = 'kinase protein';
list($like, $terms) = buildLikeConditions($columns, $search, false);

$query = "SELECT f.feature_uniquename, f.feature_name, a.annotation_description
          FROM feature f 
          LEFT JOIN feature_annotation fa ON f.feature_id = fa.feature_id
          LEFT JOIN annotation a ON fa.annotation_id = a.annotation_id
          WHERE $like
          LIMIT 100";

$results = fetchData($query, $terms, $db);
```

**Search Logic:**

| Search Type | Input | Logic | Example |
|-------------|-------|-------|---------|
| Keyword | `ABC transporter` | Each word must match | `(col1 LIKE '%ABC%' OR col2 LIKE '%ABC%') AND (col1 LIKE '%transporter%' OR col2 LIKE '%transporter%')` |
| Quoted | `"ABC transporter"` | Exact phrase | `(col1 LIKE '%ABC transporter%' OR col2 LIKE '%ABC transporter%')` |

---

### 4. `sanitize_search_input($data, $quoted_search)`

Sanitizes and validates search input to prevent SQL injection and ensure valid search terms.

**Parameters:**
- `$data` (string) - Raw search input from user
- `$quoted_search` (bool) - Whether this is a quoted phrase search

**Returns:**
- `string` - Sanitized search string

**Operations:**
1. Removes quotes if quoted search
2. Removes dangerous characters: `<`, `>`, `\t`, `;`
3. HTML-encodes special characters
4. Filters out short terms (< 3 characters) unless quoted search
5. Removes slashes

**Example:**
```php
// Regular search - filters short terms
$clean = sanitize_search_input('a b ABC transporter', false);
// Returns: "ABC transporter" (removed "a" and "b")

// Quoted search - keeps short terms
$clean = sanitize_search_input('"a b ABC"', true);
// Returns: "a b ABC" (keeps all terms)

// Removes dangerous characters
$clean = sanitize_search_input('<script>ABC</script>', false);
// Returns: "script ABC script" (< and > removed)
```

**Security Features:**
- Prevents XSS attacks
- Removes SQL-dangerous characters
- Ensures minimum term length for performance

---

### 5. `validate_search_term($search_term, $min_length)`

Validates that a search term meets minimum requirements.

**Parameters:**
- `$search_term` (string) - The search term to validate
- `$min_length` (int) - Minimum length required (default: `3`)

**Returns:**
- `bool` - `true` if valid, `false` otherwise

**Example:**
```php
validate_search_term('ABC', 3);      // true
validate_search_term('ab', 3);       // false
validate_search_term('"ABC def"', 3); // true (validates content inside quotes)
validate_search_term('"ab"', 3);     // false
```

**Use Case:**
```php
$search = $_GET['keywords'] ?? '';

if (!validate_search_term($search, 3)) {
    echo json_encode(['error' => 'Search term must be at least 3 characters']);
    exit;
}
```

---

### 6. `is_quoted_search($search_term)`

Detects if a search query is a quoted phrase search.

**Parameters:**
- `$search_term` (string) - The search term to check

**Returns:**
- `bool` - `true` if wrapped in quotes, `false` otherwise

**Example:**
```php
is_quoted_search('"ABC transporter"');  // true
is_quoted_search('ABC transporter');    // false
is_quoted_search('"ABC"');              // true
is_quoted_search('');                   // false
```

**Use Case:**
```php
$search = $_GET['keywords'] ?? '';
$quoted = is_quoted_search($search);

// Pass to backend
$quoted_param = $quoted ? '1' : '0';
```

---

## Usage Examples

### Complete Search Flow

```php
<?php
include_once __DIR__ . '/search_functions.php';

// 1. Get and validate input
$search_input = $_GET['search'] ?? '';

if (!validate_search_term($search_input, 3)) {
    echo json_encode(['error' => 'Invalid search term']);
    exit;
}

// 2. Detect search type
$quoted = is_quoted_search($search_input);

// 3. Sanitize input
$clean_search = sanitize_search_input($search_input, $quoted);

// 4. Build query for gene ID search first
$columns = ['f.feature_uniquename'];
list($like, $terms) = buildLikeConditions($columns, $clean_search, false);

$query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description
          FROM feature f
          WHERE $like
          LIMIT 100";

$db = '/data/organisms/Drosophila_melanogaster/genes.sqlite';
$results = fetchData($query, $terms, $db);

// 5. If no results, search annotations
if (empty($results)) {
    $columns = ['a.annotation_description', 'f.feature_name', 'f.feature_description'];
    list($like, $terms) = buildLikeConditions($columns, $clean_search, $quoted);
    
    $query = "SELECT f.feature_uniquename, f.feature_name, a.annotation_description
              FROM feature f
              LEFT JOIN feature_annotation fa ON f.feature_id = fa.feature_id
              LEFT JOIN annotation a ON fa.annotation_id = a.annotation_id
              WHERE $like
              LIMIT 100";
    
    $results = fetchData($query, $terms, $db);
}

// 6. Return results
echo json_encode(['results' => $results]);
?>
```

---

## Integration

### Current Usage

The following files currently use these functions:

1. **`annotation_search_ajax.php`** - Main search endpoint
   - Uses all search functions
   - Primary entry point for AJAX searches

2. **Future files** - As you migrate away from easy_gdb
   - Any new search implementations
   - API endpoints
   - CLI search tools

### How to Include

```php
// Include at the top of your PHP file
include_once __DIR__ . '/search_functions.php';

// Or with full path
include_once '/data/moop/tools/search/search_functions.php';
```

---

## Best Practices

### 1. Always Sanitize Input
```php
// ✅ GOOD
$clean = sanitize_search_input($user_input, $quoted);
list($sql, $params) = buildLikeConditions($columns, $clean, $quoted);
$results = fetchData($query, $params, $db);

// ❌ BAD - Never use raw input
$query = "SELECT * FROM feature WHERE name LIKE '%$user_input%'";
```

### 2. Use Prepared Statements
```php
// ✅ GOOD - Uses placeholders
$query = "SELECT * FROM feature WHERE id = ?";
$results = fetchData($query, [$feature_id], $db);

// ❌ BAD - Direct interpolation
$query = "SELECT * FROM feature WHERE id = $feature_id";
```

### 3. Validate Before Processing
```php
// ✅ GOOD
if (!validate_search_term($search, 3)) {
    return ['error' => 'Search too short'];
}
$results = performSearch($search);

// ❌ BAD - No validation
$results = performSearch($search);
```

### 4. Handle Quoted Searches Correctly
```php
// ✅ GOOD
$quoted = is_quoted_search($search);
$clean = sanitize_search_input($search, $quoted);
list($sql, $params) = buildLikeConditions($columns, $clean, $quoted);

// ❌ BAD - Ignoring quote detection
$clean = sanitize_search_input($search, false);
```

---

## Performance Considerations

### Database Indexes

For optimal search performance, ensure these indexes exist:

```sql
-- Feature table indexes
CREATE INDEX idx_feature_uniquename ON feature(feature_uniquename);
CREATE INDEX idx_feature_name ON feature(feature_name);
CREATE INDEX idx_feature_type ON feature(feature_type);

-- Annotation table indexes
CREATE INDEX idx_annotation_description ON annotation(annotation_description);
CREATE INDEX idx_annotation_accession ON annotation(annotation_accession);

-- Junction table indexes
CREATE INDEX idx_feature_annotation_feature ON feature_annotation(feature_id);
CREATE INDEX idx_feature_annotation_annotation ON feature_annotation(annotation_id);
```

### Query Optimization

1. **Limit results**: Always use `LIMIT` to prevent excessive data transfer
2. **Search strategy**: Try specific searches (gene ID) before broad searches (annotations)
3. **Column selection**: Only select columns you need, not `SELECT *`

---

## Error Handling

All functions use exceptions for error handling:

```php
try {
    $results = fetchData($query, $params, $db);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
```

---

## Testing

### Manual Testing

```php
// Test connection
$db = '/data/organisms/test_organism/genes.sqlite';
try {
    $conn = getDbConnection($db);
    echo "✅ Connection successful\n";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}

// Test search
$columns = ['feature_name'];
$search = 'ABC';
list($sql, $params) = buildLikeConditions($columns, $search, false);
echo "SQL: $sql\n";
print_r($params);

// Test sanitization
$dirty = '<script>ABC</script>';
$clean = sanitize_search_input($dirty, false);
echo "Sanitized: $clean\n";
```

---

## Migration Guide

### Moving from common_functions.php

If you're migrating code from the old `common_functions.php`:

**Before:**
```php
include_once __DIR__ . '/../common_functions.php';
```

**After:**
```php
include_once __DIR__ . '/search_functions.php';
```

The function signatures remain the same, so no code changes needed.

---

## Security Notes

### SQL Injection Prevention
- ✅ Uses prepared statements with placeholders
- ✅ Never concatenates user input into SQL
- ✅ All parameters are properly bound

### XSS Prevention
- ✅ Uses `htmlspecialchars()` on all input
- ✅ Removes dangerous HTML characters
- ✅ Sanitizes before storage and display

### Input Validation
- ✅ Minimum length requirements
- ✅ Character filtering
- ✅ Term length validation

---

## Future Enhancements

Potential improvements:

1. **Full-text search**: Implement SQLite FTS5 for better performance
2. **Fuzzy matching**: Add Levenshtein distance for typo tolerance
3. **Search caching**: Cache frequent searches
4. **Advanced operators**: Support AND, OR, NOT operators
5. **Wildcard support**: Allow `*` and `?` wildcards
6. **Regular expressions**: Support regex searches
7. **Database abstraction**: Add MySQL/PostgreSQL support

---

## Related Files

- `/data/moop/tools/search/annotation_search_ajax.php` - Uses these functions
- `/data/moop/tools/display/organism_display.php` - Calls search endpoint
- `/data/moop/tools/display/groups_display.php` - Calls search endpoint
- `/data/moop/access_control.php` - Access control functions

---

## Summary

`search_functions.php` provides a clean, secure, and efficient foundation for all search operations in the MOOP system. By centralizing these functions, you ensure:

- **Consistency**: Same search behavior everywhere
- **Security**: Proper input sanitization and SQL injection prevention
- **Maintainability**: One place to update search logic
- **Performance**: Optimized query building
- **Flexibility**: Support for multiple search modes

Use these functions as the building blocks for all new search features in your system!
