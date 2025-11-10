# MOOP Function Reference Guide

## Core Utilities & Search Functions (`moop_functions.php`)

Include with:
```php
include_once realpath(__DIR__ . '/../moop_functions.php');
```

### `test_input($data)` - Input Sanitization (DEPRECATED)
```php
$clean = test_input($_GET['search']);
// DEPRECATED: Use sanitize_input() or context-specific sanitization instead
// Removes slashes, < > characters, and HTML-encodes
```

### `sanitize_input($data)` - Input Sanitization (Recommended)
```php
$clean = sanitize_input($_GET['search']);
// Preferred alternative: removes slashes, < > characters, and HTML-encodes
// Better: use prepared statements + htmlspecialchars() at output point
```

### `getDbConnection($dbFile)` - Database Connection
```php
$db = getDbConnection('/path/to/database.sqlite');
// Returns PDO connection object
```

### `fetchData($sql, $params = [], $dbFile)` - Execute Query
```php
$sql = "SELECT * FROM feature WHERE feature_id = ?";
$results = fetchData($sql, [$feature_id], '/path/to/database.sqlite');
// Returns array of associative arrays
```

### `buildLikeConditions($columns, $search, $quoted = false)` - Build Search SQL
```php
[$sql_fragment, $params] = buildLikeConditions(
    ['feature_uniquename', 'feature_name'],
    'search term',
    false  // true for exact phrase search
);
$query = "SELECT * FROM feature WHERE " . $sql_fragment;
$results = fetchData($query, $params, $dbFile);
```

### `sanitize_search_input($data, $quoted_search)` - Clean Search Input
```php
$clean = sanitize_search_input($_GET['q'], false);
// Removes dangerous characters, filters short terms
```

### `validate_search_term($search_term, $min_length = 3)` - Validate Search
```php
if (validate_search_term($search_term)) {
    // Search term meets minimum requirements
}
```

### `is_quoted_search($search_term)` - Detect Quoted Search
```php
if (is_quoted_search($search_term)) {
    // User is doing a phrase search
}
```

---

## Display-Specific Functions

### In `tools/display/parent_display.php`

#### `getAncestors($feature_uniquename, $dbFile)` - Feature Lineage
```php
$ancestors = getAncestors('AT1G01010', $db);
// Returns array: [self, parent, grandparent, ...]
```

#### `getChildren($feature_id, $dbFile)` - Feature Descendants
```php
$all_descendants = getChildren($parent_feature_id, $db);
// Returns flat array of all children and descendants
```

---

### In `tools/display/display_functions.php`

#### `generateAnnotationTableHTML(...)` - Annotation Table
```php
$html = generateAnnotationTableHTML(
    $annotation_results,
    'AT1G01010',
    'mRNA',
    1,  // table counter
    'InterPro',
    'Domain predictions',
    'info',  // Bootstrap color
    'Arabidopsis thaliana'
);
```

#### `getAllAnnotationsForFeatures($feature_ids, $dbFile)` - Batch Annotations
```php
$organized = getAllAnnotationsForFeatures([$id1, $id2, $id3], $db);
// Returns: [$feature_id => [$annotation_type => [results]]]
```

#### `generateTreeHTML($feature_id, $dbFile)` - Feature Tree
```php
$html = generateTreeHTML($parent_id, $db);
// Returns nested <ul><li> tree with box-drawing characters
```

---

## Migration Notes

**OLD (Deprecated):**
```php
include_once realpath(__DIR__ . '/../common_functions.php');
// or for search:
include_once __DIR__ . '/search_functions.php';
```

**NEW (Recommended):**
```php
include_once realpath(__DIR__ . '/../moop_functions.php');
```

The old paths still work for backwards compatibility but will be removed in a future release.

---

## Function Quality Ratings

| Function | Rating | Why |
|----------|--------|-----|
| `fetchData()` | ⭐⭐⭐⭐ | Proper prepared statements, error handling |
| `getChildren()` | ⭐⭐⭐⭐ | Elegant recursion, handles all descendants |
| `buildLikeConditions()` | ⭐⭐⭐⭐ | SQL injection protection, flexible search |
| `getDbConnection()` | ⭐⭐⭐⭐ | Clean PDO setup, good error messages |
| `sanitize_search_input()` | ⭐⭐⭐⭐ | Comprehensive input cleaning, well-documented |
| `validate_search_term()` | ⭐⭐⭐⭐ | Handles quoted searches, flexible validation |
| `is_quoted_search()` | ⭐⭐⭐⭐ | Simple, reliable detection logic |
| `getAncestors()` | ⭐⭐⭐⭐ | Elegant recursion, proper null handling, matches getChildren pattern |
| `sanitize_input()` | ⭐⭐ | Generic approach, prefer context-specific sanitization |
| `test_input()` | ⭐⭐ | DEPRECATED - use sanitize_input() or context-specific methods |
| `generateTreeHTML()` | ⭐⭐⭐⭐ | Beautiful output, good recursion |
| `generateAnnotationTableHTML()` | ⭐⭐⭐⭐ | Feature-rich, good accessibility |


