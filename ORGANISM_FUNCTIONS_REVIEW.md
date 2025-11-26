# Organism Data Collection Functions - Code Review & Consolidation Analysis

**Generated:** 2025-11-26

## Overview
There are **28 organism/assembly-related functions** scattered across the codebase that retrieve, validate, and manage organism data. Many have overlapping responsibilities that could be consolidated to reduce code duplication.

---

## Complete Function Inventory

### Data Retrieval Functions (9)

| File | Function | Purpose | Usage |
|------|----------|---------|-------|
| `lib/database_queries.php` | `getOrganismInfo()` | Get organism metadata from database | Used to fetch genus, species, common name, taxon_id |
| `lib/database_queries.php` | `getAssemblyStats()` | Get feature/gene counts for assembly | Assembly statistics on display pages |
| `lib/functions_data.php` | `getAccessibleOrganismsInGroup()` | Filter organisms by group and access level | Groups display, filtering by permissions |
| `lib/functions_data.php` | `getAssemblyFastaFiles()` | Get FASTA file paths for assembly | BLAST, sequence extraction tools |
| `lib/functions_database.php` | `loadOrganismInfo()` | Load organism.json from filesystem | Organism metadata from JSON files |
| `lib/functions_display.php` | `loadOrganismAndGetImagePath()` | Load organism info AND get image path | Combined operation - organism display setup |
| `lib/functions_display.php` | `getOrganismImagePath()` | Get path to organism image | Display pages, image rendering |
| `lib/functions_display.php` | `setupOrganismDisplayContext()` | Complete setup for organism display page | organism_display.php, assembly_display.php |
| `admin/manage_organisms.php` | `get_all_organisms()` | Get all organisms from JSON | Admin management pages |

### Organism Info Loading Functions (5)

| File | Function | Purpose | Issue |
|------|----------|---------|-------|
| `admin/manage_organisms.php` | `get_all_organisms_info()` | Load organism info from filesystem | Used 10+ times in same file |
| `admin/manage_phylo_tree.php` | `get_organisms_metadata()` | Load organism metadata | Similar to above, different file |
| `admin/manage_phylo_tree.php` | `build_tree_from_organisms()` | Build phylo tree data | Depends on `get_organisms_metadata()` |
| `admin/manage_phylo_tree.php` | `fetch_organism_image()` | Get organism image | Duplicates `getOrganismImagePath()` logic |
| `admin/createUser.php` | `getOrganisms()` | Get organisms list | Retrieves data differently than others |

### Image/Display Functions (3)

| File | Function | Purpose | Overlap |
|------|----------|---------|---------|
| `lib/functions_display.php` | `getOrganismImagePath()` | Get image file path | Both handle organism images |
| `lib/functions_display.php` | `getOrganismImageCaption()` | Get image caption/metadata | Requires `getOrganismImagePath()` |
| `lib/functions_display.php` | `validateOrganismJson()` | Validate organism.json structure | Part of organism info loading |

### Tool Context Functions (3)

| File | Function | Purpose | Overlap |
|------|----------|---------|---------|
| `lib/functions_tools.php` | `createOrganismToolContext()` | Setup context for organism tools | Creates organism data for tools |
| `lib/functions_tools.php` | `createAssemblyToolContext()` | Setup context for assembly tools | Similar pattern, assembly-focused |
| `lib/functions_tools.php` | `createMultiOrganismToolContext()` | Setup context for multi-organism tools | Combined organism setup |

### Validation Functions (4)

| File | Function | Purpose | Location |
|------|----------|---------|----------|
| `lib/functions_validation.php` | `validateOrganismParam()` | Validate organism name parameter | Input validation |
| `lib/functions_validation.php` | `validateAssemblyParam()` | Validate assembly name parameter | Input validation |
| `lib/functions_filesystem.php` | `validateAssemblyDirectories()` | Check assembly directories exist | File system validation |
| `lib/functions_display.php` | `validateOrganismJson()` | Validate organism.json structure | JSON validation |

### Filesystem Functions (4)

| File | Function | Purpose | Used By |
|------|----------|---------|---------|
| `lib/functions_filesystem.php` | `validateAssemblyDirectories()` | Verify assembly directories | Setup, validation |
| `lib/functions_filesystem.php` | `validateAssemblyFastaFiles()` | Check FASTA files exist | Sequence tools |
| `lib/functions_filesystem.php` | `renameAssemblyDirectory()` | Admin: rename assembly | Admin only |
| `lib/functions_filesystem.php` | `deleteAssemblyDirectory()` | Admin: delete assembly | Admin only |

### Parser/Helper Functions (2)

| File | Function | Purpose | Used By |
|------|----------|---------|---------|
| `lib/extract_search_helpers.php` | `parseOrganismParameter()` | Parse organism name from input | Multi-organism search |
| `lib/functions_data.php` | `formatIndexOrganismName()` | Format organism name for display | Index page |

---

## Code Duplication Issues Identified

### 1. **Organism Info Loading (3 functions, HIGH DUPLICATION)**
```
❌ get_all_organisms_info()          (admin/manage_organisms.php:13)
❌ get_organisms_metadata()           (admin/manage_phylo_tree.php:17)
✅ loadOrganismInfo()                 (lib/functions_database.php:301)
```

**Problem:** Three different functions load organism.json files. They use different approaches and aren't reusing each other.

**Consolidation Option:**
- Make `loadOrganismInfo()` a central function
- Have other two call it in a loop
- Difference: `loadOrganismInfo()` loads single organism, others load all

### 2. **Image Path Functions (2 functions, MEDIUM DUPLICATION)**
```
❌ getOrganismImagePath()             (lib/functions_display.php:10)
❌ fetch_organism_image()             (admin/manage_phylo_tree.php:40)
```

**Problem:** Both retrieve organism image paths but implemented separately.

**Consolidation Option:**
- `fetch_organism_image()` should call `getOrganismImagePath()`
- Or consolidate into single function

### 3. **Tool Context Setup (3 functions, LOW-MEDIUM DUPLICATION)**
```
✅ createOrganismToolContext()        (lib/functions_tools.php:65)
✅ createAssemblyToolContext()        (lib/functions_tools.php:81)
✅ createMultiOrganismToolContext()   (lib/functions_tools.php:128)
```

**Problem:** Three functions do similar setup. Assembly context likely builds on organism context.

**Consolidation Option:**
- One function with optional parameters
- Or have Assembly context call Organism context, then add assembly data
- Multi-organism could orchestrate multiple calls

### 4. **Organism Retrieval - Different Patterns (3 functions, LOW DUPLICATION)**
```
❓ getOrganisms()                     (admin/createUser.php)
❓ get_all_organisms()                (admin/manage_groups.php)
❌ get_all_organisms_info()           (admin/manage_organisms.php:13)
```

**Problem:** Different files retrieve organisms in different ways. Unclear which is "canonical."

**Consolidation Option:**
- Single `getAllOrganisms()` function
- Parameter to include/exclude info
- Centralize in `lib/functions_data.php`

### 5. **Assembly Validation (2 functions, MEDIUM DUPLICATION)**
```
✅ validateAssemblyDirectories()      (lib/functions_filesystem.php:17)
✅ validateAssemblyFastaFiles()       (lib/functions_filesystem.php:116)
```

**Problem:** Both validate assemblies but check different things. Could be combined.

**Consolidation Option:**
- One `validateAssembly()` function with options
- Or have one call the other

---

## Recommended Consolidation Refactoring

### Phase 1: Core Functions (Highest Impact)

**1. Create Central Organism Data Function**
```php
// lib/functions_data.php

function loadAllOrganisms($organism_data_dir, $include_info = true) {
    // Returns all organisms with optional metadata
    // Used by: get_all_organisms(), get_all_organisms_info(), getOrganisms()
}

function getOrganismInfoComplete($organism_name, $organism_data_dir, $db_path) {
    // Returns: filesystem info + database info + image path
    // Combines loadOrganismInfo() + getOrganismImagePath() + getOrganismInfo()
}
```

**2. Consolidate Image Functions**
```php
// lib/functions_display.php - already exists, improve it

function getOrganismImageData($organism_info, $organism_name, $images_path, $absolute_path = '') {
    // Returns: { path, caption, url, alt_text }
    // Used by: getOrganismImagePath() + getOrganismImageCaption()
    // Admin functions call this instead of fetch_organism_image()
}
```

**3. Simplify Tool Context**
```php
// lib/functions_tools.php - could be simplified

function createToolContext($context_type = 'organism', $params = []) {
    // Unified function for organism, assembly, multi-organism contexts
    // Reduces 3 functions to 1 with flexibility
}
```

### Phase 2: Assembly Operations

**4. Unified Assembly Validation**
```php
// lib/functions_filesystem.php

function validateAssembly($assembly_path, $checks = ['directories', 'fasta']) {
    // validateAssemblyDirectories() + validateAssemblyFastaFiles()
    // Returns detailed validation results
}
```

### Phase 3: Parameter Retrieval

**5. Centralize Organism Retrieval**
```php
// lib/functions_data.php

function getAllOrganisms($organism_data_dir, $options = []) {
    // $options: ['include_info' => true, 'filter_group' => null, 'accessible_only' => false]
    // Replaces: getOrganisms() + get_all_organisms() + get_all_organisms_info()
}
```

---

## Function Usage Summary

### High Usage (10+ calls)
- `get_all_organisms_info()` - 10+ times in admin/manage_organisms.php alone
- `setupOrganismDisplayContext()` - Multiple display pages
- `createOrganismToolContext()` - Multiple tools

### Medium Usage (3-5 calls)
- `loadOrganismInfo()`, `getOrganismImagePath()`, `getAssemblyFastaFiles()`

### Low Usage (1-2 calls)
- `fetch_organism_image()`, `validateOrganismJson()`, Various filesystem functions

---

## Benefits of Consolidation

✅ **Reduced Code Duplication** - 3+ functions doing same thing → 1 shared function

✅ **Easier Maintenance** - Changes to organism loading only need to happen in one place

✅ **Better Performance** - Could cache organism data, apply once instead of 10 times

✅ **Clearer API** - Developers know which function to call instead of guessing

✅ **Type Safety** - Single return structure for organism data

✅ **Better Testing** - Fewer edge cases to test if logic is centralized

---

## Migration Path

1. **Create new consolidated functions** in `lib/functions_data.php` and `lib/functions_display.php`
2. **Refactor existing functions** to call new consolidated versions (backwards compatible)
3. **Update high-usage code** to use new functions first (admin pages)
4. **Test extensively** (organism display, admin pages, tools)
5. **Deprecate old functions** after migration complete

---

## Notes

- Image functions in `lib/functions_display.php` are already reasonably organized
- Assembly operations are less duplicated than organism loading
- Consider whether single-organism vs all-organisms should be separate functions
- Tool context functions are architectural - consolidation should be careful
- Some duplication may be intentional (admin vs. user-facing functions)
