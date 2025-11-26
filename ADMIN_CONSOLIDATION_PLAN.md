# Admin Scripts Consolidation Plan

## Phase 1: Quick Wins (Already Deprecated)

### 1. get_organisms_metadata() in manage_phylo_tree.php (Line 17)
**Status:** Already deprecated wrapper
**Action:** REMOVE - It's just a wrapper for loadAllOrganismsMetadata()
**File:** manage_phylo_tree.php
**Impact:** No impact - function just calls the lib version

---

## Phase 2: Move Functions to Lib

### 2. get_all_existing_groups($groups_data) → functions_data.php
**Current:** manage_groups.php Line 25
**Purpose:** Extract unique groups from group data array
**Type:** Generic data transformation
**Usage:** Only manage_groups.php
**Action:** Move to functions_data.php and update manage_groups.php to use it

### 3. sync_group_descriptions($existing_groups, $descriptions_data) → functions_data.php
**Current:** manage_groups.php Line 42
**Purpose:** Sync group descriptions with existing groups
**Type:** Generic data transformation
**Usage:** Only manage_groups.php
**Action:** Move to functions_data.php and update manage_groups.php to use it

### 4. fetch_organism_image($taxon_id, $organism_name = null) → functions_display.php
**Current:** manage_phylo_tree.php Line 24
**Purpose:** Fetch and cache organism image from NCBI
**Type:** Display/external API function
**Usage:** manage_phylo_tree.php (could be used elsewhere)
**Action:** Move to functions_display.php and update manage_phylo_tree.php to use it

### 5. fetch_taxonomy_lineage($taxon_id) → functions_data.php (or database.php)
**Current:** manage_phylo_tree.php Line 60
**Purpose:** Fetch taxonomic lineage from NCBI
**Type:** Data retrieval from external API
**Usage:** manage_phylo_tree.php (could be used elsewhere)
**Action:** Move to functions_data.php and update manage_phylo_tree.php to use it

### 6. build_tree_from_organisms($organisms) → functions_data.php
**Current:** manage_phylo_tree.php Line 130
**Purpose:** Build tree structure from organisms array
**Type:** Generic data transformation
**Usage:** manage_phylo_tree.php
**Action:** Move to functions_data.php and update manage_phylo_tree.php to use it

---

## Phase 3: Refactor Functions Using Config

### 7. get_all_organisms_info() in manage_organisms.php
**Current:** Line 151
**Uses globals:** $organism_data, $sequence_types
**Action:** 
- Add $organism_data_path parameter (dependency injection)
- Move to functions_data.php as it's a complex data aggregation function
- Rename to clarify purpose (e.g., getDetailedOrganismsInfo)

---

## Phase 4: Global Variable Cleanup

### 8. Replace global $organism_data usage
**Current locations:**
- manage_organisms.php: get_all_organisms_info() uses global $organism_data
- manage_phylo_tree.php: Various functions use global $absolute_images_path

**Action:** Convert to use $config->getPath() instead of globals
- Pass organism_data_path as parameter
- Pass absolute_images_path as parameter

---

## Phase 5: Consider Consolidating Similar Functions

### 9. Compare similar functions
- `loadAllOrganismsMetadata()` vs `get_all_organisms_info()`
  - Should one be built on top of the other?
  - Or are they fundamentally different?

---

## Implementation Order

1. **REMOVE** get_organisms_metadata() wrapper - already deprecated
2. **MOVE** get_all_existing_groups() → functions_data.php
3. **MOVE** sync_group_descriptions() → functions_data.php
4. **MOVE** fetch_organism_image() → functions_display.php
5. **MOVE** fetch_taxonomy_lineage() → functions_data.php
6. **MOVE** build_tree_from_organisms() → functions_data.php
7. **REFACTOR** get_all_organisms_info() → use params instead of globals
8. **UPDATE** manage_phylo_tree.php to use ConfigManager paths
9. **VERIFY** all files work correctly

---

## Summary

- **Functions to move to lib:** 6
- **Functions to remove:** 1 (deprecated wrapper)
- **Functions to refactor:** 1 (replace globals with params)
- **Files impacted:** manage_groups.php, manage_phylo_tree.php, manage_organisms.php
- **New lib functions:** ~6
- **Globals to eliminate:** $organism_data, $absolute_images_path (in these scripts)

