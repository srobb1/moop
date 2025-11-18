# Database Query Consolidation

## Overview

This document describes the consolidated database query builder functions that centralize SQL queries used across the MOOP display and search tools.

**File:** `/tools/database_queries.php`
**Included in:** `/tools/moop_functions.php`

## Purpose

- **DRY Principle:** Eliminate duplicate SQL queries across multiple files
- **Maintainability:** Update queries in one place instead of hunting through code
- **Consistency:** Ensure consistent parameter handling and error checking
- **Permission Safety:** Built-in support for genome_id filtering for access control

## Quick Start

All functions are automatically available after including `moop_functions.php`.

```php
// Feature queries
$feature = getFeatureById($feature_id, $db_path);
$feature = getFeatureByUniquename($feature_uniquename, $db_path);
$children = getChildrenByFeatureId($parent_id, $db_path);
$parent = getParentFeature($feature_id, $db_path);

// Organism and Assembly queries
$organism = getOrganismInfo($organism_name, $db_path);
$stats = getAssemblyStats($genome_accession, $db_path);

// Annotation queries
$annotations = getAnnotationsByFeature($feature_id, $db_path);

// Search queries
$results = searchFeaturesAndAnnotations($search_term, $is_quoted, $db_path);
$features = searchFeaturesByUniquenameForSearch($search_term, $db_path);
```

## Function Reference

### Feature Queries

#### `getFeatureById($feature_id, $dbFile, $genome_ids = [])`
Returns complete feature information by feature ID.

**Returns:** Associative array with keys:
- `feature_id`, `feature_uniquename`, `feature_name`, `feature_description`
- `feature_type`, `parent_feature_id`, `genome_id`, `organism_id`
- `genus`, `species`, `subtype`, `common_name`, `taxon_id` (from organism)
- `genome_accession`, `genome_name` (from genome table)

**Optional Parameter:** `$genome_ids` - Array of accessible genome IDs for permission filtering

**Example:**
```php
$feature = getFeatureById(42, $db_path);
if (!empty($feature)) {
    echo "{$feature['feature_uniquename']} ({$feature['feature_type']})";
}
```

---

#### `getFeatureByUniquename($feature_uniquename, $dbFile, $genome_ids = [])`
Returns complete feature information by uniquename (preferred identifier).

**Parameters:**
- `$feature_uniquename` - String uniquename to search for
- `$dbFile` - Path to SQLite database
- `$genome_ids` - Optional array for permission filtering

**Returns:** Same as `getFeatureById`

**Example:**
```php
$feature = getFeatureByUniquename('AT1G01010', $db_path, $accessible_genomes);
```

---

#### `getChildrenByFeatureId($parent_feature_id, $dbFile, $genome_ids = [])`
Returns **immediate children only** (not recursive).

**Returns:** Array of feature rows with:
- `feature_id`, `feature_uniquename`, `feature_name`, `feature_description`
- `feature_type`, `parent_feature_id`

**Note:** For recursive children/descendants, use `getChildren()` from `parent_functions.php`

**Example:**
```php
$children = getChildrenByFeatureId($parent_id, $db_path);
foreach ($children as $child) {
    echo "  {$child['feature_uniquename']} ({$child['feature_type']})\n";
}
```

---

#### `getParentFeature($feature_id, $dbFile, $genome_ids = [])`
Returns immediate parent feature (minimal fields for hierarchy traversal).

**Returns:** Array with:
- `feature_id`, `feature_uniquename`, `feature_type`, `parent_feature_id`

**Example:**
```php
$parent = getParentFeature($feature_id, $db_path);
if (!empty($parent) && $parent['parent_feature_id']) {
    $grandparent = getParentFeature($parent['parent_feature_id'], $db_path);
}
```

---

#### `getFeaturesByType($feature_type, $dbFile, $genome_ids = [])`
Get all features of a specific type (e.g., all genes or all mRNAs).

**Parameters:**
- `$feature_type` - String like 'gene', 'mRNA', 'exon'
- Rest as above

**Returns:** Array of matching features

**Example:**
```php
$genes = getFeaturesByType('gene', $db_path, $accessible_genomes);
echo "Found " . count($genes) . " genes";
```

---

#### `searchFeaturesByUniquename($search_term, $dbFile, $organism_name = '')`
Quick feature lookup by uniquename (supports wildcards).

**Parameters:**
- `$search_term` - Uniquename to search (LIKE pattern)
- `$organism_name` - Optional organism filter

**Returns:** Array of matching features with organism info
**Limit:** 50 results max

**Example:**
```php
$matches = searchFeaturesByUniquename('AT1G%', $db_path, 'Arabidopsis thaliana');
```

---

### Organism & Assembly Queries

#### `getOrganismInfo($organism_name, $dbFile)`
Get organism information and taxonomy data.

**Parameters:**
- `$organism_name` - Organism name (supports both "Genus species" and common name)

**Returns:** Array with:
- `organism_id`, `genus`, `species`, `common_name`, `subtype`, `taxon_id`

**Example:**
```php
$organism = getOrganismInfo('Homo sapiens', $db_path);
echo "{$organism['common_name']} ({$organism['taxon_id']})";
```

---

#### `getAssemblyStats($genome_accession, $dbFile)`
Get assembly/genome statistics.

**Returns:** Array with:
- `genome_id`, `genome_accession`, `genome_name`
- `gene_count` - Number of genes in assembly
- `mrna_count` - Number of mRNA transcripts
- `total_features` - Total features of all types

**Example:**
```php
$stats = getAssemblyStats('GCF_000001405.40', $db_path);
echo "Assembly has {$stats['gene_count']} genes";
```

---

### Annotation Queries

#### `getAnnotationsByFeature($feature_id, $dbFile)`
Get all annotations associated with a feature.

**Returns:** Array of annotation records with:
- `annotation_id`, `annotation_accession`, `annotation_description`
- `annotation_source_name`, `annotation_source_id`
- `score`, `date`, `additional_info`

**Example:**
```php
$annotations = getAnnotationsByFeature($feature_id, $db_path);
foreach ($annotations as $anno) {
    echo "{$anno['annotation_accession']}: {$anno['annotation_description']}\n";
}
```

---

### Search Queries

#### `searchFeaturesAndAnnotations($search_term, $is_quoted_search, $dbFile)`
Search both features and annotations by keyword or exact phrase.

**Parameters:**
- `$search_term` - Search string or quoted phrase
- `$is_quoted_search` - Boolean: true for exact phrase, false for keyword search
- `$dbFile` - Database path

**Behavior:**
- **Keyword search** (`$is_quoted_search = false`):
  - Splits on whitespace: "ABC transporter" → ["ABC", "transporter"]
  - Returns results where ALL terms appear (AND logic)
  - Matches across all columns: annotation_description, feature_name, feature_description, annotation_accession
  
- **Quoted search** (`$is_quoted_search = true`):
  - Treats entire string as one phrase: "ABC transporter"
  - Returns only exact phrase matches
  - Checks all columns

**Returns:** Array of matching results with:
- `feature_uniquename`, `feature_name`, `feature_description`, `feature_type`, `organism_id`
- `annotation_accession`, `annotation_description`
- `score`, `date`, `annotation_source_name`
- `genus`, `species`, `common_name`, `subtype`

**Limit:** 100 results max

**Example:**
```php
// Keyword search: Find both "ABC" and "transporter"
$results = searchFeaturesAndAnnotations('ABC transporter', false, $db_path);

// Exact phrase search: Find only "ABC transporter" as phrase
$results = searchFeaturesAndAnnotations('ABC transporter', true, $db_path);
```

---

#### `searchFeaturesByUniquenameForSearch($search_term, $dbFile, $organism_name = '')`
Quick feature search by uniquename (used as primary search before annotation search).

**Parameters:**
- `$search_term` - Uniquename to search (LIKE pattern)
- `$organism_name` - Optional organism filter

**Returns:** Array of matching features with organism info
**Limit:** 100 results max

**Example:**
```php
// Check if feature exists first
$features = searchFeaturesByUniquenameForSearch('AT1G01010', $db_path);
if (!empty($features)) {
    // Display features
} else {
    // Fall back to annotation search
}
```

---

## Permission-Based Access Control

Most functions support an optional `$genome_ids` parameter for permission filtering:

```php
// Get accessible genome IDs for current user
$accessible_genomes = getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db);

// Query only returns data from accessible genomes
$feature = getFeatureById($feature_id, $db_path, $accessible_genomes);

// Empty result if feature is not in an accessible genome
if (empty($feature)) {
    die("Feature not accessible");
}
```

**Note:** When `$genome_ids` is provided, results are filtered to only those genomes. When empty, no filtering is applied (backwards compatible).

## Migration Guide

### Before (Duplicate Queries)

```php
// In parent_display.php
$query = "SELECT f.feature_id, f.feature_uniquename, ...
          FROM feature f
          JOIN organism o ON f.organism_id = o.organism_id
          JOIN genome g ON f.genome_id = g.genome_id
          WHERE f.feature_id = ?";
$results = fetchData($query, [$feature_id], $db_path);
$feature = $results[0] ?? [];

// Same query in sequences_display.php
$query = "SELECT f.feature_id, f.feature_uniquename, ...
          FROM feature f
          JOIN organism o ON f.organism_id = o.organism_id
          JOIN genome g ON f.genome_id = g.genome_id
          WHERE f.feature_id = ?";
$results = fetchData($query, [$feature_id], $db_path);
$feature = $results[0] ?? [];
```

### After (Consolidated)

```php
// In both files - no duplication
$feature = getFeatureById($feature_id, $db_path);
```

### Migration Checklist

- [x] Identify common query patterns
- [x] Create query builder functions with consistent interfaces
- [x] Add permission-based filtering support
- [x] Include database_queries.php in moop_functions.php
- [ ] Update parent_display.php to use consolidated queries (next step)
- [ ] Update display_functions.php to use consolidated queries
- [ ] Update search functions to use consolidated queries
- [ ] Remove duplicate query definitions from individual files
- [ ] Update documentation with query mappings

## Implementation Status

| Module | Status | Notes |
|--------|--------|-------|
| parent_functions.php | ✓ Helpers created | Now has both old (recursive) and new (non-recursive) functions |
| parent_display.php | ⏳ Ready for migration | Can use getFeatureById, getFeatureByUniquename |
| sequences_display.php | ⏳ Ready for migration | Can use query helpers |
| organism_display.php | ⏳ Ready for migration | Can use getOrganismInfo, getFeaturesByType |
| assembly_display.php | ⏳ Ready for migration | Can use getAssemblyStats |
| annotation_search_ajax.php | ⏳ Ready for migration | Can use searchFeaturesAndAnnotations |
| multi_organism_search.php | ⏳ Ready for migration | Can use existing search functions |

## Benefits Achieved

✅ **DRY Code** - Eliminated query duplication
✅ **Maintainability** - Update queries in one place
✅ **Consistency** - Standard parameter handling
✅ **Security** - All queries use prepared statements (via fetchData)
✅ **Permission Safety** - Built-in genome_id filtering
✅ **Performance** - Optimized JOIN patterns
✅ **Documentation** - Clear function interfaces

## Performance Notes

- All queries use efficient JOINs where possible
- Recursive queries (getChildren, getAncestors) are in parent_functions.php to avoid circular dependencies
- Search queries are limited to 100 results to prevent performance issues
- Consider adding pagination for large result sets in the future
