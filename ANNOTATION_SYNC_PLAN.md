# Annotation Type Synchronization System Plan

**Date Created:** 2025-11-26  
**Status:** Planning Phase  
**Priority:** Medium  

## Overview

This plan outlines the implementation of an annotation type synchronization system that:
1. Queries the database for all annotation types
2. Syncs them with the metadata configuration
3. Supports annotation type synonyms (e.g., "Orthology" and "Orthologs")
4. Allows picking a canonical display label for synonyms
5. Marks unused entries and discovers new types
6. Ensures parent_display properly displays all annotation types

## Problem Statement

Currently:
- `annotation_config.json` has separate entries for "Orthology" and "Orthologs" with identical descriptions
- These are treated as separate types in the UI, even though they're synonymous
- No synchronization between database annotation types and the JSON config
- Admins have no way to know if all DB annotation types are described in the config
- No way to discover new annotation types added to the database without manual checking

## Solution Architecture

### 1. Enhanced annotation_config.json Schema

Current structure:
```json
{
  "annotation_types": {
    "Orthology": {
      "display_name": "Orthology",
      "color": "primary",
      "order": 1,
      "description": "...",
      "enabled": true
    }
  }
}
```

New structure with synonym support:
```json
{
  "annotation_types": {
    "Orthology": {
      "display_name": "Orthology",
      "display_label": "Orthology",
      "color": "primary",
      "order": 1,
      "description": "...",
      "enabled": true,
      "synonyms": ["Orthologs"],
      "in_database": true,
      "annotation_count": 42,
      "feature_count": 38,
      "new": false
    },
    "Homology": {
      "display_name": "Homology",
      "display_label": "Homology",
      "color": "info",
      "order": 3,
      "description": "...",
      "enabled": false,
      "synonyms": [],
      "in_database": false,
      "annotation_count": 0,
      "feature_count": 0,
      "new": false
    }
  }
}
```

**New Fields Explanation:**
- `display_label`: Which synonym to actually display in the UI (picked by admin)
- `synonyms`: Array of alias names that map to this canonical entry
- `in_database`: Auto-populated flag indicating if this type exists in DB
- `annotation_count`: Auto-populated count of annotations from the database
- `feature_count`: Auto-populated count of distinct features with this annotation type
- `new`: Auto-populated flag for types discovered in DB but not in config

### 2. Helper Functions

Location: `lib/functions_json.php` (already has `saveJsonFile()`)

Functions to add to `admin/manage_annotations.php`:

#### getAnnotationTypesFromDB($dbFile)
```php
/**
 * Get all annotation types from database with their counts and feature counts
 * Queries annotation_source and feature_annotation tables for:
 *   - Distinct annotation_type values
 *   - Count of annotations per type
 *   - Count of distinct features per type
 * 
 * @param string $dbFile - Path to SQLite database
 * @return array - [annotation_type => ['annotation_count' => N, 'feature_count' => M]]
 *                  ordered by feature_count DESC
 * 
 * Example return:
 *   [
 *     'Orthology' => ['annotation_count' => 42, 'feature_count' => 38],
 *     'Homology' => ['annotation_count' => 15, 'feature_count' => 12],
 *     ...
 *   ]
 */
function getAnnotationTypesFromDB($dbFile) {
    // Query: SELECT DISTINCT annotation_type,
    //               COUNT(DISTINCT a.annotation_id) as annotation_count,
    //               COUNT(DISTINCT fa.feature_id) as feature_count
    //        FROM annotation_source ans
    //        LEFT JOIN annotation a ON ...
    //        LEFT JOIN feature_annotation fa ON ...
    //        GROUP BY annotation_type
    // Returns: ['Orthology' => ['annotation_count' => 42, 'feature_count' => 38], ...]
}
```

#### getAnnotationTypeMapping($annotation_config)
```php
/**
 * Build mapping from DB types to canonical config names
 * Uses synonyms to map all aliases to their canonical entry
 * 
 * @param array $annotation_config - Loaded annotation_config.json
 * @return array - [db_type => canonical_name] mapping
 * 
 * Example:
 *   Input:  "Orthology" has synonyms: ["Orthologs"]
 *   Output: ['Orthology' => 'Orthology', 'Orthologs' => 'Orthology']
 */
function getAnnotationTypeMapping($annotation_config) {
    // For each annotation_type in config:
    //   - Map the name to itself
    //   - Map each synonym to the canonical name
    // Returns complete mapping
}
```

#### syncAnnotationTypes($annotation_config, $db_types)
```php
/**
 * Synchronize annotation types between config and database
 * Creates entries for unmapped DB types, marks unused entries
 * Populates annotation_count and feature_count for each type
 * 
 * @param array $annotation_config - Current annotation_config.json
 * @param array $db_types - [annotation_type => ['annotation_count' => N, 'feature_count' => M]]
 * @return array - Updated config with sync metadata
 * 
 * Process:
 *   1. Mark all existing entries as in_database = false initially
 *   2. For each DB type:
 *      - If mapped via synonym: mark canonical as in_database = true
 *      - Add annotation_count and feature_count to canonical
 *      - If not mapped: create new entry with "new" flag
 *   3. Return updated config
 *   
 * Example:
 *   Input DB types: ['Orthology' => ['annotation_count' => 42, 'feature_count' => 38], ...]
 *   Output: config with in_database=true, annotation_count=42, feature_count=38
 */
function syncAnnotationTypes($annotation_config, $db_types) {
    // Implementation details...
}
```

#### consolidateSynonym(&$annotation_config, $canonical_name, $synonym_name)
```php
/**
 * Consolidate a synonym entry into the canonical entry
 * Removes the synonym as separate entry, adds to synonyms array
 * 
 * @param array &$annotation_config - Reference to config (modified in place)
 * @param string $canonical_name - Target canonical entry
 * @param string $synonym_name - Synonym entry to consolidate
 * @return bool - Success status
 * 
 * Process:
 *   1. Add $synonym_name to $canonical_name['synonyms']
 *   2. Combine db_count values
 *   3. Remove $synonym_name as separate entry
 *   4. Save updated config
 */
function consolidateSynonym(&$annotation_config, $canonical_name, $synonym_name) {
    // Implementation details...
}
```

#### getAnnotationDisplayLabel($db_annotation_type, $annotation_config)
```php
/**
 * Get display label for an annotation type from database
 * Resolves through synonym mapping and returns configured display_label
 * 
 * @param string $db_annotation_type - Type from annotation_source table
 * @param array $annotation_config - Loaded annotation_config.json
 * @return string - Display label to use in UI
 * 
 * Example:
 *   DB has: annotation_type = "Orthologs"
 *   Config: "Orthology" has display_label = "Orthology", synonyms = ["Orthologs"]
 *   Result: "Orthology"
 */
function getAnnotationDisplayLabel($db_annotation_type, $annotation_config) {
    // Get mapping
    // Find canonical name for DB type
    // Return configured display_label
}
```

### 3. manage_annotations.php Enhancements

#### Phase 1: Add Database Query
- Query all organisms' databases for annotation types
- Aggregate all unique annotation_type values with counts
- Call `syncAnnotationTypes()` to sync config

#### Phase 2: Add Sync Status UI
Show two sections:

**Section A: Annotation Types Status**
```
┌─────────────────────────────────────────────────────────────────┐
│ Annotation Types Synchronization Status                         │
├─────────────────────────────────────────────────────────────────┤
│ Database has 8 distinct types | Config has 11 entries           │
│                                                                 │
│ ✅ In Database & Configured:                                    │
│   • Orthology (synonyms: Orthologs)                             │
│     ├─ Annotations: 42 | Features: 38                           │
│   • Homology (synonyms: Homologs)                               │
│     ├─ Annotations: 15 | Features: 12                           │
│   • Domains                                                      │
│     ├─ Annotations: 128 | Features: 95                          │
│   ...                                                            │
│                                                                 │
│ ⚠️  NOT USED (in config but not in database):                   │
│   • Mapping - 0 annotations, 0 features [REMOVE]                │
│   • Aliases - 0 annotations, 0 features [REMOVE]                │
│   • Publications - 0 annotations, 0 features [REMOVE]           │
│                                                                 │
│ 🆕 NEW (in database but not in config):                         │
│   • CustomType - 5 annotations, 4 features [ADD]                │
│   • NewAnnotations - 3 annotations, 2 features [ADD]            │
└─────────────────────────────────────────────────────────────────┘
```

**Section B: Synonym Management**
```
┌─────────────────────────────────────────────────────────────────┐
│ Manage Synonyms                                                 │
├─────────────────────────────────────────────────────────────────┤
│ Consolidate similar annotation types:                           │
│                                                                 │
│ Canonical Entry: [Orthology ▼]                                  │
│ Synonym to Add: [Orthologs ▼]                                   │
│ Display As:     [Orthology ▼]                                   │
│                 [Consolidate] [Cancel]                          │
│                                                                 │
│ Current Synonyms for "Orthology": [Orthologs]                   │
│ - Remove "Orthologs" as synonym [×]                             │
└─────────────────────────────────────────────────────────────────┘
```

#### Phase 3: Add Sync Action
Button to sync with database:
```
[🔄 Sync with Database] - Query DB and update config with new types
[📊 View Sync Report] - Shows what would change
```

### 4. parent_display.php Updates

#### Update annotation loading
```php
// Current:
$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);
foreach ($analysis_order as $annotation_type) {
    $annot_results = $all_annotations[$feature_id][$annotation_type] ?? [];
}

// New:
$annotation_config = loadJsonFileRequired("$metadata_path/annotation_config.json");
$all_annotations = getAllAnnotationsForFeatures(
    $all_feature_ids, 
    $db, 
    [],  // genome_ids
    $annotation_config  // NEW: pass config for synonym mapping
);

// Now uses canonical names and display labels
foreach ($analysis_order as $canonical_name) {
    $annot_results = $all_annotations[$feature_id][$canonical_name] ?? [];
    $display_label = $annotation_config['annotation_types'][$canonical_name]['display_label'];
    $color = $annotation_config['annotation_types'][$canonical_name]['color'];
    echo generateAnnotationTableHTML($annot_results, ..., $display_label, $color, ...);
}
```

### 5. parent_functions.php Updates

#### Update getAllAnnotationsForFeatures()
```php
/**
 * Get all annotations for features with synonym mapping
 * Maps DB annotation_type to canonical config names
 * 
 * @param array $feature_ids - Feature IDs to get annotations for
 * @param string $dbFile - Database path
 * @param array $genome_ids - Optional: limit to specific genomes
 * @param array $annotation_config - NEW: config with synonym mappings
 * @return array - [feature_id => [canonical_type => [results]]]
 */
function getAllAnnotationsForFeatures($feature_ids, $dbFile, $genome_ids = [], $annotation_config = []) {
    // Query database (unchanged)
    // Build mapping from annotation_config
    $type_mapping = getAnnotationTypeMapping($annotation_config);
    
    // Organize results by feature_id and CANONICAL type
    $organized = [];
    foreach ($results as $row) {
        $feature_id = $row['feature_id'];
        $db_type = $row['annotation_type'];
        
        // Map DB type to canonical type
        $canonical_type = $type_mapping[$db_type] ?? $db_type;
        
        if (!isset($organized[$feature_id][$canonical_type])) {
            $organized[$feature_id][$canonical_type] = [];
        }
        $organized[$feature_id][$canonical_type][] = $row;
    }
    
    return $organized;
}
```

## Implementation Phases

### Phase 1: Foundation (Priority: HIGH)
1. Add helper functions to manage_annotations.php
2. Add new fields to annotation_config.json schema
3. Test getAnnotationTypesFromDB() with existing databases
4. **Commit:** "Add annotation type sync helper functions"

### Phase 2: UI - Query & Display (Priority: HIGH)
1. Query database in manage_annotations.php
2. Build sync status display
3. Show DB types vs config types comparison
4. Mark unused vs new entries
5. **Commit:** "Add annotation sync status display in manage_annotations UI"

### Phase 3: UI - Synonym Management (Priority: HIGH)
1. Add UI for consolidating synonyms
2. Add form to add new DB types to config
3. Add action buttons (add/remove/consolidate)
4. **Commit:** "Add synonym consolidation UI to manage_annotations"

### Phase 4: parent_display Integration (Priority: MEDIUM)
1. Update getAllAnnotationsForFeatures() to accept config
2. Implement synonym mapping in annotation grouping
3. Update parent_display.php to use display_label
4. Test display with Orthology/Orthologs example
5. **Commit:** "Map annotation types through synonyms in parent display"

### Phase 5: Testing & Documentation (Priority: MEDIUM)
1. Test with actual databases
2. Test Orthology/Orthologs consolidation
3. Test discovering new annotation types
4. Update README/docs with synonym management
5. **Commit:** "Add annotation sync documentation and examples"

## Testing Checklist

- [ ] `getAnnotationTypesFromDB()` returns correct annotation_count from sample databases
- [ ] `getAnnotationTypesFromDB()` returns correct feature_count (distinct features per type)
- [ ] Feature counts correctly show which types are actually used
- [ ] `getAnnotationTypeMapping()` correctly maps synonyms
- [ ] `syncAnnotationTypes()` identifies unmapped DB types
- [ ] Consolidating "Orthologs" into "Orthology" combines feature_counts correctly
- [ ] parent_display shows "Orthology" for both DB types after consolidation
- [ ] Unused annotation types (feature_count = 0) marked as "Not used" in config
- [ ] New DB types can be added to config via manage_annotations UI
- [ ] Feature counts in UI match database queries
- [ ] All synonyms group correctly in parent_display with combined feature counts
- [ ] Backward compatible with existing annotation_config.json format

## Migration Strategy

For existing deployments:
1. Add new schema fields with default values
2. Make fields optional initially
3. Run sync on first access (auto-populate new fields)
4. Admins can consolidate synonyms manually or accept defaults
5. No breaking changes to existing functionality

## Files Modified

| File | Changes |
|------|---------|
| `lib/functions_json.php` | ✅ Add `saveJsonFile()` (DONE) |
| `admin/manage_annotations.php` | Add sync functions, query DB, display UI |
| `tools/parent_display.php` | Use `display_label`, pass config to annotation loading |
| `lib/parent_functions.php` | Add config parameter to `getAllAnnotationsForFeatures()` |
| `metadata/annotation_config.json` | Add new schema fields |

## Example: Orthology/Orthologs Consolidation

### Before
```json
{
  "annotation_types": {
    "Orthology": {"display_name": "Orthology", ...},
    "Orthologs": {"display_name": "Orthologs", ...}  // Duplicate
  }
}
```

Database has:
- Some annotation_source entries with `annotation_type = "Orthology"`
- Some with `annotation_type = "Orthologs"`

UI shows them as separate types ❌

### After
```json
{
  "annotation_types": {
    "Orthology": {
      "display_name": "Orthology",
      "display_label": "Orthology",
      "synonyms": ["Orthologs"],
      "in_database": true,
      "annotation_count": 57,
      "feature_count": 52,
      ...
    }
  }
}
```

Database query results:
- "Orthology" type: 30 annotations on 28 features
- "Orthologs" type: 27 annotations on 24 features
- After consolidation: Combined to 57 annotations on 52 distinct features

UI shows:
- Single "Orthology" entry
- Annotations: 57 | Features: 52
- Both "Orthology" and "Orthologs" DB types map to this canonical entry ✅

## Future Enhancements

1. **Auto-consolidation**: Suggest consolidation for similar names
2. **Batch operations**: Consolidate multiple synonyms at once
3. **Color coding**: Smart color assignment for new types
4. **Statistics**: Track annotation type usage across organisms
5. **Validation**: Warn if config has unused entries

## References

- Related issues:
  - Orthology/Orthologs duplication
  - Need DB-config synchronization
  - Parent display mapping issues
  
- Related functions:
  - `getAnnotationSourcesByType()` in database_queries.php
  - `getAllAnnotationsForFeatures()` in parent_functions.php
  - `generateAnnotationTableHTML()` in parent_functions.php

---

**Last Updated:** 2025-11-26  
**Next Review:** When starting Phase 1 implementation
