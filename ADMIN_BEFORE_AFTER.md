# Admin Scripts - Before & After Consolidation

## BEFORE Consolidation

```
/data/moop/admin/
├── manage_groups.php
│   ├── get_all_existing_groups()          [25 lines]
│   ├── sync_group_descriptions()          [40 lines]
│   └── HTML/Business logic
│
├── manage_phylo_tree.php
│   ├── get_organisms_metadata()           [4 lines - DEPRECATED]
│   ├── fetch_organism_image()             [33 lines]
│   ├── fetch_taxonomy_lineage()           [70 lines]
│   ├── build_tree_from_organisms()        [320 lines]
│   ├── renderTreeNode() [JS]              [n/a]
│   └── HTML/Business logic
│
└── manage_organisms.php
    ├── get_all_organisms_info()           [80 lines] - Uses global $organism_data
    └── HTML/Business logic

TOTAL ADMIN-SPECIFIC CODE: ~546 lines
ISSUES:
  - Code duplication between scripts
  - Functions mixed with business logic
  - Global variable dependencies
  - Hard to test
  - Hard to reuse
```

## AFTER Consolidation

```
/data/moop/admin/
├── manage_groups.php
│   └── HTML/Business logic (uses lib functions)
│       ├── getAllExistingGroups() [from lib]
│       ├── syncGroupDescriptions() [from lib]
│       └── getOrganismsWithAssemblies() [from lib]
│
├── manage_phylo_tree.php
│   └── HTML/Business logic (uses lib functions)
│       ├── fetch_organism_image() [from lib]
│       ├── fetch_taxonomy_lineage() [from lib]
│       ├── build_tree_from_organisms() [from lib]
│       └── renderTreeNode() [JS - stays]
│
└── manage_organisms.php
    └── HTML/Business logic (uses lib functions)
        ├── getDetailedOrganismsInfo() [from lib]
        └── loadAllOrganismsMetadata() [from lib]

/data/moop/lib/
├── functions_data.php [+5 new functions]
│   ├── getAllExistingGroups()          [13 lines]
│   ├── syncGroupDescriptions()          [40 lines]
│   ├── fetch_taxonomy_lineage()         [70 lines]
│   ├── build_tree_from_organisms()      [320 lines]
│   ├── getDetailedOrganismsInfo()       [80 lines]
│   └── [existing 9 functions]
│
└── functions_display.php [+1 new function]
    ├── fetch_organism_image()           [33 lines]
    └── [existing 5 functions]

TOTAL ADMIN-SPECIFIC CODE: ~296 lines (-46% reduction)
IMPROVEMENTS:
  ✅ No code duplication
  ✅ Functions separated from business logic
  ✅ No global dependencies (using params + ConfigManager)
  ✅ Easy to test
  ✅ Easy to reuse across app
  ✅ Admin scripts focus on UI/business logic only
```

## Concrete Example: manage_groups.php

### BEFORE
```php
<?php
include_once __DIR__ . '/admin_init.php';

$metadata_path = $config->getPath('metadata_path');
$organism_data = '../organisms';  // HARDCODED!

$groups_file = $metadata_path . '/organism_assembly_groups.json';
$groups_data = loadJsonFile($groups_file, []);

// FUNCTION DEFINED HERE - Should be in lib!
function get_all_existing_groups($groups_data) {
    $all_groups = [];
    foreach ($groups_data as $data) {
        if (!empty($data['groups'])) {
            foreach ($data['groups'] as $group) {
                $all_groups[$group] = true;
            }
        }
    }
    $group_list = array_keys($all_groups);
    sort($group_list);
    return $group_list;
}

// FUNCTION DEFINED HERE - Should be in lib!
function sync_group_descriptions($existing_groups, $descriptions_data) {
    // ... 40 lines of data transformation
}

$all_organisms = getOrganismsWithAssemblies($organism_data);  // Hardcoded path!
$all_existing_groups = get_all_existing_groups($groups_data);
$descriptions_data = loadJsonFile($descriptions_file, []);
$updated_descriptions = sync_group_descriptions($all_existing_groups, $descriptions_data);

// ... HTML and business logic
?>
```

### AFTER
```php
<?php
include_once __DIR__ . '/admin_init.php';

$metadata_path = $config->getPath('metadata_path');
$organism_data_path = $config->getPath('organism_data');  // From ConfigManager!

$groups_file = $metadata_path . '/organism_assembly_groups.json';
$groups_data = loadJsonFile($groups_file, []);

// USE LIBRARY FUNCTIONS - Clean and simple!
$all_organisms = getOrganismsWithAssemblies($organism_data_path);
$all_existing_groups = getAllExistingGroups($groups_data);  // From functions_data.php
$descriptions_data = loadJsonFile($descriptions_file, []);
$updated_descriptions = syncGroupDescriptions($all_existing_groups, $descriptions_data);  // From functions_data.php

// ... HTML and business logic
?>
```

### Benefits of After Version:
- ✅ Shorter file (admin logic only)
- ✅ Reusable functions in lib
- ✅ ConfigManager used for paths (not hardcoded)
- ✅ Easy to understand at a glance
- ✅ Can unit test functions in lib
- ✅ Other admin pages can reuse these functions

## Function Migration Map

| Current Location | Function Name | New Location | New Name |
|------------------|---------------|--------------|----------|
| manage_groups.php:25 | get_all_existing_groups | functions_data.php | getAllExistingGroups |
| manage_groups.php:42 | sync_group_descriptions | functions_data.php | syncGroupDescriptions |
| manage_phylo_tree.php:17 | get_organisms_metadata | DELETE (deprecated wrapper) | - |
| manage_phylo_tree.php:24 | fetch_organism_image | functions_display.php | fetch_organism_image |
| manage_phylo_tree.php:60 | fetch_taxonomy_lineage | functions_data.php | fetch_taxonomy_lineage |
| manage_phylo_tree.php:130 | build_tree_from_organisms | functions_data.php | build_tree_from_organisms |
| manage_organisms.php:151 | get_all_organisms_info | functions_data.php | getDetailedOrganismsInfo |

## Statistics

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| Functions in admin scripts | 7 | 2 | -71% |
| Functions in functions_data.php | 9 | 14 | +56% |
| Functions in functions_display.php | 5 | 6 | +20% |
| Admin script lines | ~546 | ~296 | -46% |
| Globals used | Multiple | 2 (config, session) | Reduced |
| Reusable functions | 2 | 7 | +250% |

## Why This Matters

### Code Maintainability
- Changes to data transformation logic only need to be made in one place
- Bugs fixed in lib are automatically fixed everywhere

### Code Reusability
- Other pages (tools, displays) can now use these functions
- Don't duplicate logic across the application

### Testability
- Functions in lib are easier to unit test
- Admin pages easier to test when they just call lib functions

### Clarity
- Admin scripts focus on business logic and UI
- Helper functions moved to where they belong (lib)
- Easier for new developers to understand code structure

### Configuration-Driven
- Hardcoded paths replaced with ConfigManager
- System works on any installation without code changes
