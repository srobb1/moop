# Function Registry

**Auto-generated documentation**

Generated: 2025-11-20 16:11:29

## Summary

- **Total Functions**: 105
- **Files Scanned**: 17

## Quick Navigation

- [lib/blast_functions.php](#lib-blast_functionsphp) - 5 functions
- [lib/blast_results_visualizer.php](#lib-blast_results_visualizerphp) - 15 functions
- [lib/database_queries.php](#lib-database_queriesphp) - 11 functions
- [lib/extract_search_helpers.php](#lib-extract_search_helpersphp) - 11 functions
- [lib/functions_access.php](#lib-functions_accessphp) - 3 functions
- [lib/functions_data.php](#lib-functions_dataphp) - 7 functions
- [lib/functions_database.php](#lib-functions_databasephp) - 8 functions
- [lib/functions_display.php](#lib-functions_displayphp) - 5 functions
- [lib/functions_errorlog.php](#lib-functions_errorlogphp) - 3 functions
- [lib/functions_filesystem.php](#lib-functions_filesystemphp) - 7 functions
- [lib/functions_json.php](#lib-functions_jsonphp) - 4 functions
- [lib/functions_system.php](#lib-functions_systemphp) - 2 functions
- [lib/functions_tools.php](#lib-functions_toolsphp) - 7 functions
- [lib/functions_validation.php](#lib-functions_validationphp) - 6 functions
- [lib/parent_functions.php](#lib-parent_functionsphp) - 6 functions
- [lib/tool_config.php](#lib-tool_configphp) - 4 functions
- [tools/sequences_display.php](#tools-sequences_displayphp) - 1 functions

---

## lib/blast_functions.php

**5 function(s)**

### `getBlastDatabases()` (Line 20)

Located in: `lib/blast_functions.php` at line 20

### `filterDatabasesByProgram()` (Line 69)

Located in: `lib/blast_functions.php` at line 69

### `executeBlastSearch()` (Line 107)

Located in: `lib/blast_functions.php` at line 107

### `extractSequencesFromBlastDb()` (Line 293)

Located in: `lib/blast_functions.php` at line 293

### `validateBlastSequence()` (Line 353)

Located in: `lib/blast_functions.php` at line 353

---

## lib/blast_results_visualizer.php

**15 function(s)**

### `parseBlastResults()` (Line 19)

Located in: `lib/blast_results_visualizer.php` at line 19

### `generateHitsSummaryTable()` (Line 288)

Located in: `lib/blast_results_visualizer.php` at line 288

### `generateBlastGraphicalView()` (Line 352)

Located in: `lib/blast_results_visualizer.php` at line 352

### `generateAlignmentViewer()` (Line 533)

Located in: `lib/blast_results_visualizer.php` at line 533

### `generateBlastStatisticsSummary()` (Line 650)

Located in: `lib/blast_results_visualizer.php` at line 650

### `generateCompleteBlastVisualization()` (Line 730)

Located in: `lib/blast_results_visualizer.php` at line 730

### `generateHspVisualizationWithLines()` (Line 985)

Located in: `lib/blast_results_visualizer.php` at line 985

### `getHspColorClass()` (Line 1144)

Located in: `lib/blast_results_visualizer.php` at line 1144

### `getColorStyle()` (Line 1263)

Located in: `lib/blast_results_visualizer.php` at line 1263

### `formatBlastAlignment()` (Line 603)

Located in: `lib/blast_results_visualizer.php` at line 603

### `generateQueryScoreLegend()` (Line 1073)

Located in: `lib/blast_results_visualizer.php` at line 1073

### `generateQueryScaleTicks()` (Line 1099)

Located in: `lib/blast_results_visualizer.php` at line 1099

### `generateQueryScale()` (Line 1099)

Located in: `lib/blast_results_visualizer.php` at line 1099

### `getToggleQuerySectionScript()` (Line 1659)

Located in: `lib/blast_results_visualizer.php` at line 1659

### `toggleQuerySection()` (Line 962)

Located in: `lib/blast_results_visualizer.php` at line 962

---

## lib/database_queries.php

**11 function(s)**

### `getFeatureById()` (Line 28)

Located in: `lib/database_queries.php` at line 28

### `getFeatureByUniquename()` (Line 65)

Located in: `lib/database_queries.php` at line 65

### `getChildrenByFeatureId()` (Line 102)

Located in: `lib/database_queries.php` at line 102

### `getParentFeature()` (Line 130)

Located in: `lib/database_queries.php` at line 130

### `getFeaturesByType()` (Line 157)

Located in: `lib/database_queries.php` at line 157

### `searchFeaturesByUniquename()` (Line 187)

Located in: `lib/database_queries.php` at line 187

### `getAnnotationsByFeature()` (Line 219)

Located in: `lib/database_queries.php` at line 219

### `getOrganismInfo()` (Line 240)

Located in: `lib/database_queries.php` at line 240

### `getAssemblyStats()` (Line 258)

Located in: `lib/database_queries.php` at line 258

### `searchFeaturesAndAnnotations()` (Line 282)

Located in: `lib/database_queries.php` at line 282

### `searchFeaturesByUniquenameForSearch()` (Line 379)

Located in: `lib/database_queries.php` at line 379

---

## lib/extract_search_helpers.php

**11 function(s)**

### `parseOrganismParameter()` (Line 29)

Located in: `lib/extract_search_helpers.php` at line 29

### `parseContextParameters()` (Line 65)

Located in: `lib/extract_search_helpers.php` at line 65

### `validateExtractInputs()` (Line 86)

Located in: `lib/extract_search_helpers.php` at line 86

### `parseFeatureIds()` (Line 128)

Located in: `lib/extract_search_helpers.php` at line 128

### `extractSequencesForAllTypes()` (Line 157)

Located in: `lib/extract_search_helpers.php` at line 157

### `formatSequenceResults()` (Line 197)

Located in: `lib/extract_search_helpers.php` at line 197

### `sendFileDownload()` (Line 220)

Located in: `lib/extract_search_helpers.php` at line 220

### `buildFilteredSourcesList()` (Line 240)

Located in: `lib/extract_search_helpers.php` at line 240

### `flattenSourcesList()` (Line 269)

Located in: `lib/extract_search_helpers.php` at line 269

### `assignGroupColors()` (Line 290)

Located in: `lib/extract_search_helpers.php` at line 290

### `getAvailableSequenceTypesForDisplay()` (Line 313)

Located in: `lib/extract_search_helpers.php` at line 313

---

## lib/functions_access.php

**3 function(s)**

### `getAccessibleAssemblies()` (Line 15)

Located in: `lib/functions_access.php` at line 15

### `getPhyloTreeUserAccess()` (Line 131)

Located in: `lib/functions_access.php` at line 131

### `requireAccess()` (Line 170)

Located in: `lib/functions_access.php` at line 170

---

## lib/functions_data.php

**7 function(s)**

### `getGroupData()` (Line 12)

Located in: `lib/functions_data.php` at line 12

### `getAllGroupCards()` (Line 30)

Located in: `lib/functions_data.php` at line 30

### `getPublicGroupCards()` (Line 53)

Located in: `lib/functions_data.php` at line 53

### `getAccessibleOrganismsInGroup()` (Line 81)

Located in: `lib/functions_data.php` at line 81

### `getAssemblyFastaFiles()` (Line 131)

Located in: `lib/functions_data.php` at line 131

### `getIndexDisplayCards()` (Line 164)

Located in: `lib/functions_data.php` at line 164

### `formatIndexOrganismName()` (Line 176)

Located in: `lib/functions_data.php` at line 176

---

## lib/functions_database.php

**8 function(s)**

### `validateDatabaseFile()` (Line 13)

Located in: `lib/functions_database.php` at line 13

### `validateDatabaseIntegrity()` (Line 44)

Located in: `lib/functions_database.php` at line 44

### `getDbConnection()` (Line 181)

Located in: `lib/functions_database.php` at line 181

### `fetchData()` (Line 200)

Located in: `lib/functions_database.php` at line 200

### `buildLikeConditions()` (Line 235)

Located in: `lib/functions_database.php` at line 235

### `getAccessibleGenomeIds()` (Line 271)

Located in: `lib/functions_database.php` at line 271

### `loadOrganismInfo()` (Line 295)

Located in: `lib/functions_database.php` at line 295

### `verifyOrganismDatabase()` (Line 326)

Located in: `lib/functions_database.php` at line 326

---

## lib/functions_display.php

**5 function(s)**

### `loadOrganismAndGetImagePath()` (Line 18)

Located in: `lib/functions_display.php` at line 18

### `getOrganismImagePath()` (Line 10)

Located in: `lib/functions_display.php` at line 10

### `getOrganismImageCaption()` (Line 100)

Located in: `lib/functions_display.php` at line 100

### `validateOrganismJson()` (Line 153)

Located in: `lib/functions_display.php` at line 153

### `setupOrganismDisplayContext()` (Line 225)

Located in: `lib/functions_display.php` at line 225

---

## lib/functions_errorlog.php

**3 function(s)**

### `logError()` (Line 15)

Located in: `lib/functions_errorlog.php` at line 15

### `getErrorLog()` (Line 42)

Located in: `lib/functions_errorlog.php` at line 42

### `clearErrorLog()` (Line 75)

Located in: `lib/functions_errorlog.php` at line 75

---

## lib/functions_filesystem.php

**7 function(s)**

### `validateAssemblyDirectories()` (Line 17)

Located in: `lib/functions_filesystem.php` at line 17

### `validateAssemblyFastaFiles()` (Line 116)

Located in: `lib/functions_filesystem.php` at line 116

### `renameAssemblyDirectory()` (Line 183)

Located in: `lib/functions_filesystem.php` at line 183

### `deleteAssemblyDirectory()` (Line 242)

Located in: `lib/functions_filesystem.php` at line 242

### `rrmdir()` (Line 273)

Located in: `lib/functions_filesystem.php` at line 273

### `getFileWriteError()` (Line 323)

Located in: `lib/functions_filesystem.php` at line 323

### `getDirectoryError()` (Line 354)

Located in: `lib/functions_filesystem.php` at line 354

---

## lib/functions_json.php

**4 function(s)**

### `loadJsonFile()` (Line 14)

Located in: `lib/functions_json.php` at line 14

### `loadJsonFileRequired()` (Line 36)

Located in: `lib/functions_json.php` at line 36

### `loadAndMergeJson()` (Line 81)

Located in: `lib/functions_json.php` at line 81

### `decodeJsonString()` (Line 113)

Located in: `lib/functions_json.php` at line 113

---

## lib/functions_system.php

**2 function(s)**

### `getWebServerUser()` (Line 14)

Located in: `lib/functions_system.php` at line 14

### `fixDatabasePermissions()` (Line 48)

Located in: `lib/functions_system.php` at line 48

---

## lib/functions_tools.php

**7 function(s)**

### `getAvailableTools()` (Line 14)

Located in: `lib/functions_tools.php` at line 14

### `createIndexToolContext()` (Line 50)

Located in: `lib/functions_tools.php` at line 50

### `createOrganismToolContext()` (Line 65)

Located in: `lib/functions_tools.php` at line 65

### `createAssemblyToolContext()` (Line 81)

Located in: `lib/functions_tools.php` at line 81

### `createGroupToolContext()` (Line 96)

Located in: `lib/functions_tools.php` at line 96

### `createFeatureToolContext()` (Line 112)

Located in: `lib/functions_tools.php` at line 112

### `createMultiOrganismToolContext()` (Line 128)

Located in: `lib/functions_tools.php` at line 128

---

## lib/functions_validation.php

**6 function(s)**

### `test_input()` (Line 23)

Located in: `lib/functions_validation.php` at line 23

### `sanitize_search_input()` (Line 40)

Located in: `lib/functions_validation.php` at line 40

### `validate_search_term()` (Line 63)

Located in: `lib/functions_validation.php` at line 63

### `is_quoted_search()` (Line 92)

Located in: `lib/functions_validation.php` at line 92

### `validateOrganismParam()` (Line 107)

Located in: `lib/functions_validation.php` at line 107

### `validateAssemblyParam()` (Line 123)

Located in: `lib/functions_validation.php` at line 123

---

## lib/parent_functions.php

**6 function(s)**

### `getAncestors()` (Line 18)

Located in: `lib/parent_functions.php` at line 18

### `getAncestorsByFeatureId()` (Line 28)

Located in: `lib/parent_functions.php` at line 28

### `getChildren()` (Line 72)

Located in: `lib/parent_functions.php` at line 72

### `generateAnnotationTableHTML()` (Line 99)

Located in: `lib/parent_functions.php` at line 99

### `getAllAnnotationsForFeatures()` (Line 195)

Located in: `lib/parent_functions.php` at line 195

### `generateTreeHTML()` (Line 256)

Located in: `lib/parent_functions.php` at line 256

---

## lib/tool_config.php

**4 function(s)**

### `getTool()` (Line 52)

Located in: `lib/tool_config.php` at line 52

### `getAllTools()` (Line 62)

Located in: `lib/tool_config.php` at line 62

### `buildToolUrl()` (Line 75)

Located in: `lib/tool_config.php` at line 75

### `isToolVisibleOnPage()` (Line 105)

Located in: `lib/tool_config.php` at line 105

---

## tools/sequences_display.php

**1 function(s)**

### `extractSequencesFromFasta()` (Line 113)

Located in: `tools/sequences_display.php` at line 113

---

