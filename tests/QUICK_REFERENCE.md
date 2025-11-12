# Quick Reference: organism.json Validation

## Quick Start

### What was added?
Validation for organism.json files that checks:
- ✓ File exists
- ✓ File is readable
- ✓ Contains valid JSON
- ✓ Has all required fields: genus, species, common_name, taxon_id

### Where to see it?
In the **Manage Organisms** page (`admin/manage_organisms.php`):
1. Find an organism in the table
2. Click its database status button
3. Scroll to "Organism Metadata (organism.json)" section
4. View the validation results with color-coded indicators

### Function Reference

**validateOrganismJson($path)**
- Location: `tools/moop_functions.php`
- Input: Path to organism.json file
- Output: Array with validation results
- Usage: Automatically called for each organism

### Validation Output Example

```php
[
    'exists' => true,
    'readable' => true,
    'valid_json' => true,
    'has_required_fields' => true,
    'required_fields' => ['genus', 'species', 'common_name', 'taxon_id'],
    'missing_fields' => [],
    'errors' => []  // Empty if all checks pass
]
```

### Common Errors & Fixes

**Error: "organism.json file does not exist"**
- Fix: Create organism.json in the organism directory

**Error: "organism.json file is not readable"**
- Fix: Check file permissions (should be readable by web server)

**Error: "organism.json contains invalid JSON"**
- Fix: Validate JSON syntax using a JSON validator

**Error: "Missing required fields: X, Y"**
- Fix: Add the missing fields to organism.json

### Test Coverage

6 test cases included in `tests/test_organism_json_validation.php`:
1. ✓ Missing file detection
2. ✓ Invalid JSON detection
3. ✓ Missing fields detection
4. ✓ Valid file acceptance
5. ✓ Permission checking
6. ✓ Wrapped JSON handling

### Run Tests
```bash
php tests/test_organism_json_validation.php
```

### Minimal Valid organism.json
```json
{
    "genus": "Genus",
    "species": "species",
    "common_name": "Common Name",
    "taxon_id": "12345"
}
```

### Files Changed
- `tools/moop_functions.php` - Added validation function
- `admin/manage_organisms.php` - Integrated validation into UI

### Files Added
- `tests/test_organism_json_validation.php` - Test suite
- `tests/ORGANISM_JSON_VALIDATION_README.md` - Full documentation
- `tests/IMPLEMENTATION_SUMMARY.md` - Implementation details
