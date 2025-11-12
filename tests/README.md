# MOOP Tests - organism.json Validation

## Overview

This directory contains tests and documentation for the organism.json validation feature added to the MOOP system's organism management interface.

## What is organism.json Validation?

The organism.json validation feature ensures that each organism directory contains a properly formatted metadata file with all required fields. This guarantees data consistency and helps administrators quickly identify configuration issues.

## Files in This Directory

### Test Files
- **test_organism_json_validation.php** - Comprehensive test suite with 6 test cases

### Documentation Files
- **README.md** - This file; overview and guide
- **QUICK_REFERENCE.md** - Quick reference for common tasks and error fixes
- **ORGANISM_JSON_VALIDATION_README.md** - Detailed technical documentation
- **IMPLEMENTATION_SUMMARY.md** - Implementation details and changes made

## Quick Start

### Running Tests

```bash
cd /data/moop
php tests/test_organism_json_validation.php
```

Expected output:
```
=== Organism JSON Validation Tests ===

Test 1: Missing organism.json file
✓ PASS: Correctly detects missing file

Test 2: Invalid JSON format
✓ PASS: Correctly identifies invalid JSON

Test 3: Missing required fields
✓ PASS: Correctly identifies missing fields
  Missing: common_name, taxon_id

Test 4: Valid JSON with all required fields
✓ PASS: Valid JSON with required fields passes all checks

Test 5: Unreadable file (permission denied)
✓ PASS: Correctly detects unreadable file

Test 6: Wrapped JSON (single-level wrapping)
✓ PASS: Correctly handles wrapped JSON

=== Test Summary ===
Passed: 6
Failed: 0
Total: 6
```

### Viewing Validation Results

1. Navigate to the **Manage Organisms** page: `admin/manage_organisms.php`
2. Find an organism in the table
3. Click the organism's database status button
4. In the modal that appears, scroll to the **Organism Metadata (organism.json)** section
5. View the validation results:
   - **Green checkmarks** indicate passing validation
   - **Red x marks** indicate failures
   - Error messages explain what needs to be fixed

## Validation Details

### Required Fields

The validation checks for four required fields in each organism.json file:

| Field | Description | Example |
|-------|-------------|---------|
| genus | Biological genus classification | "Anoura" |
| species | Biological species classification | "caudifer" |
| common_name | Common name for display | "Tailed Tailless Bat" |
| taxon_id | NCBI taxonomy identifier | "27642" |

### Validation Checks

1. **File Existence** - Verifies organism.json exists in the organism directory
2. **File Readability** - Checks that the web server can read the file
3. **JSON Format** - Validates proper JSON syntax
4. **Required Fields** - Ensures all required fields are present and non-empty
5. **Field Values** - Checks that field values are not null or empty strings

### Supported JSON Structures

The validation supports both normal and wrapped JSON:

**Normal Structure:**
```json
{
    "genus": "Anoura",
    "species": "caudifer",
    "common_name": "Tailed Tailless Bat",
    "taxon_id": "27642"
}
```

**Wrapped Structure:**
```json
{
    "organism_name": {
        "genus": "Anoura",
        "species": "caudifer",
        "common_name": "Tailed Tailless Bat",
        "taxon_id": "27642"
    }
}
```

## Test Coverage

The test suite covers six scenarios:

1. **Missing File** - Tests detection of missing organism.json
2. **Invalid JSON** - Tests detection of malformed JSON syntax
3. **Missing Fields** - Tests detection of missing required fields
4. **Valid File** - Tests acceptance of properly formatted files
5. **Unreadable File** - Tests detection of permission issues
6. **Wrapped JSON** - Tests support for single-level wrapped JSON

All tests pass successfully.

## Error Handling

### Common Errors and Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| "organism.json file does not exist" | File is missing | Create organism.json in organism directory |
| "organism.json file is not readable" | Permission denied | Check file permissions |
| "organism.json contains invalid JSON" | Malformed JSON | Validate JSON syntax |
| "Missing required fields: X, Y" | Missing field(s) | Add the missing field(s) to the JSON |

## Integration with manage_organisms.php

The validation is automatically integrated into the organism management interface:

1. **Automatic Execution** - Validation runs automatically when the page loads
2. **Per-Organism Analysis** - Each organism's validation is checked independently
3. **User Interface Display** - Results are shown in the database details modal
4. **Color-Coded Status** - Green = pass, Red = fail
5. **Detailed Feedback** - Error messages explain specific issues

## Function Reference

### validateOrganismJson($json_path)

**Location:** `tools/moop_functions.php`

**Parameters:**
- `$json_path` (string) - Full path to organism.json file

**Returns:** Array with validation results
```php
[
    'exists' => bool,               // File exists
    'readable' => bool,             // File is readable
    'valid_json' => bool,           // Valid JSON syntax
    'has_required_fields' => bool,  // All required fields present
    'required_fields' => [...],     // List of required fields
    'missing_fields' => [...],      // Array of missing fields
    'errors' => [...]               // Error messages
]
```

**Example Usage:**
```php
include 'tools/moop_functions.php';

$result = validateOrganismJson('/path/to/organism.json');

if ($result['has_required_fields'] && $result['valid_json']) {
    echo "Valid organism metadata";
} else {
    echo "Errors: " . implode(", ", $result['errors']);
}
```

## Files Modified

### tools/moop_functions.php
- **Added:** `validateOrganismJson()` function
- **Lines:** 1516-1584
- **Function:** Validates organism.json files

### admin/manage_organisms.php
- **Updated:** `get_all_organisms_info()` function
- **Added:** json_validation to organisms array
- **Added:** Display section in database modal
- **Lines:** 84-87, 134-143, 550-583

## Documentation

For more information, see:
- **QUICK_REFERENCE.md** - Quick reference guide
- **ORGANISM_JSON_VALIDATION_README.md** - Detailed technical documentation
- **IMPLEMENTATION_SUMMARY.md** - Implementation details

## Contributing

To add additional validation checks:

1. Modify `validateOrganismJson()` in `tools/moop_functions.php`
2. Add corresponding test case in `tests/test_organism_json_validation.php`
3. Run tests to verify: `php tests/test_organism_json_validation.php`
4. Update documentation as needed

## Support

For issues or questions:
1. Check the error message in the UI
2. Review the QUICK_REFERENCE.md for common solutions
3. Run the test suite to verify functionality
4. Review ORGANISM_JSON_VALIDATION_README.md for detailed information
