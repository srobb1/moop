# Shared Results Table Implementation

## Overview
The results tables displayed in `organism_display.php` and `groups_display.php` now use shared code to ensure consistency and maintainability.

## Files Created

### 1. `shared_results_table.css`
Contains all CSS styling for the results tables, including:
- Table layout and responsiveness
- DataTables customization
- Column sorting indicators
- Progress bars and loading spinners
- Search input styling
- Export button styling

### 2. `shared_results_table.js`
Contains JavaScript functions for creating and managing results tables:

#### `createOrganismResultsTable(organism, results, sitePath, linkBasePath)`
Creates the HTML structure for an organism results table.

**Parameters:**
- `organism` - The organism identifier
- `results` - Array of result objects from the search
- `sitePath` - The site base path (e.g., '/moop')
- `linkBasePath` - Base path for feature links (default: 'tools/display/parent_display.php')
  - Use 'tools/display/parent_display.php' for organism_display.php
  - Use 'tools/search/parent_display.php' for groups_display.php

**Returns:** HTML string for the complete table

#### `initializeResultsTable(tableId, selectId, isUniquenameSearch)`
Initializes a DataTable with all features including export, filtering, and selection.

**Parameters:**
- `tableId` - jQuery selector for the table (e.g., '#resultsTable_organism')
- `selectId` - Unique identifier for the select all button
- `isUniquenameSearch` - Boolean indicating if annotation columns should be shown

## Usage

### In organism_display.php
```javascript
// Display results using shared function
$('#resultsContainer').append(
    createOrganismResultsTable(organism, results, sitePath, 'tools/display/parent_display.php')
);
```

### In groups_display.php
```javascript
// Display results using shared function with custom link path
const tableHtml = createOrganismResultsTable(organism, results, sitePath, 'tools/search/parent_display.php');
$('#resultsContainer').append(tableHtml);
```

## Benefits

1. **Single Source of Truth**: Any changes to table styling or functionality only need to be made once
2. **Consistency**: Both pages will always display results tables identically
3. **Maintainability**: Easier to fix bugs and add features
4. **Reduced Code Duplication**: ~350 lines of duplicated code consolidated into shared files

## Making Changes

To modify the results table appearance or behavior:

1. **Styling changes**: Edit `shared_results_table.css`
2. **Functionality changes**: Edit `shared_results_table.js`
3. **Both pages will automatically reflect the changes** - no need to edit multiple files

## Integration

Both PHP files include the shared resources:
```html
<link rel="stylesheet" href="shared_results_table.css">
<script src="shared_results_table.js"></script>
```

The JavaScript functions are called from the page-specific code to generate and display the tables.
