# Organism JSON Validation

## Overview

The organism.json validation functionality in `admin/manage_organisms.php` ensures that all organisms have properly formatted metadata files with the required fields for the MOOP system.

## Implementation

### Function: `validateOrganismJson()`

Located in: `tools/moop_functions.php`

This function validates organism.json files by checking:

1. **File Existence**: Verifies the organism.json file exists in the organism directory
2. **File Readability**: Checks that the web server has read permissions
3. **JSON Format**: Validates the file contains proper JSON syntax
4. **Required Fields**: Ensures all required fields are present and non-empty:
   - `genus` - The genus classification of the organism
   - `species` - The species classification
   - `common_name` - Common name for display
   - `taxon_id` - NCBI taxonomy identifier

### Return Value

The function returns an associative array with the following structure:

```php
[
    'exists' => bool,                    // File exists
    'readable' => bool,                  // File is readable
    'valid_json' => bool,                // Valid JSON format
    'has_required_fields' => bool,       // All required fields present
    'required_fields' => [               // List of required field names
        'genus',
        'species',
        'common_name',
        'taxon_id'
    ],
    'missing_fields' => [],              // Array of missing field names
    'errors' => []                       // Array of error messages
]
```

### Usage in manage_organisms.php

The validation is automatically run for all organisms when the page loads:

1. Each organism's validation results are stored in the `json_validation` key
2. Results are displayed in the Database Details modal for each organism
3. Validation status is shown with color-coded badges:
   - Green checkmarks indicate passing checks
   - Red x marks indicate failures
   - Detailed error messages explain what needs to be fixed

## Viewing Validation Results

In the **Manage Organisms** page:

1. Click on an organism's database status button
2. In the modal that appears, scroll to the **Organism Metadata (organism.json)** section
3. View the validation results:
   - **File Status**: Shows if file exists, is readable, and contains valid JSON
   - **Errors**: Lists any validation failures
   - **Required Fields**: Shows which required fields are present

## Example organism.json

```json
{
    "genus": "Anoura",
    "species": "caudifer",
    "common_name": "Tailed Tailless Bat",
    "taxon_id": "27642",
    "text_src": "https://en.wikipedia.org/wiki/Tailed_tailless_bat",
    "html_p": [
        {
            "text": "The tailed tailless bat is...",
            "style": "",
            "class": "fs-5"
        }
    ]
}
```

## Running Tests

A comprehensive test suite is included to verify the validation function:

```bash
php tests/test_organism_json_validation.php
```

### Test Cases

The test suite verifies:

1. **Missing File Detection** - Correctly identifies when organism.json doesn't exist
2. **Invalid JSON Detection** - Detects malformed JSON syntax
3. **Missing Fields Detection** - Identifies missing required fields
4. **Valid File Acceptance** - Passes validation for properly formatted files
5. **Permission Checking** - Detects unreadable files
6. **Wrapped JSON Handling** - Correctly processes single-level wrapped JSON structures

## Error Handling

The validation handles several error scenarios:

- **File not found**: Returns error "organism.json file does not exist"
- **Permission denied**: Returns error "organism.json file is not readable"
- **Invalid JSON**: Returns the JSON parse error message
- **Missing fields**: Lists which required fields are missing

All errors are collected in the `errors` array for comprehensive feedback to the administrator.

## Integration with manage_organisms.php

The validation results are integrated into the organism management interface:

1. Displayed in modal dialogs when clicking organism status buttons
2. Color-coded validation indicators (green = pass, red = fail)
3. Detailed error messages for troubleshooting
4. Field-by-field status display for all required fields

## Notes

- The validation function handles both normal and wrapped JSON structures
- File permissions are checked to ensure the web server can read the file
- All required fields must be non-empty (null or empty strings are considered missing)
- The validation runs on every page load to ensure data consistency
