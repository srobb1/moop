# PLAN: Adapt retrieve_sequence.php to Accept Sequence Ranges

## GOAL
Enable users to extract subsequences using range notation in the sequence ID input, 
supporting multiple formats like: "ID:1..10", "ID:1-10", "ID 1..10", "ID 1-10"

## CURRENT BEHAVIOR
- Single sequence IDs only (e.g., "ACA1_PVKU01005411.1_000003.1")
- Uses blastdbcmd with -entry flag (single IDs)

## NEW BEHAVIOR (TARGET)
When range notation is detected:
- Create temporary batch file with ID:range entries
- Use blastdbcmd with -entry_batch flag instead of -entry
- Delete temporary file after extraction
- Parse and display results normally

## FILES TO MODIFY

### 1. lib/extract_search_helpers.php
   Function: `parseFeatureIds()` → Enhance to detect and preserve ranges
   - Current: Splits on commas/newlines and trims
   - New: Detect range patterns in IDs (":1..10", ":1-10", " 1..10", " 1-10")
   - Store ranges separately or mark them for batch processing
   - Return: ['valid' => bool, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => bool, 'error' => '']

   Function: `extractSequencesFromBlastDb()` → Handle both single and batch modes
   - Signature: ($blast_db, $sequence_ids, $organism = '', $assembly = '', $ranges = [])
   - New logic:
     a) Check if $ranges is provided and not empty
     b) If has ranges: 
        - Create temp file with "ID:range" entries
        - Execute: blastdbcmd -db ... -entry_batch temp_file
        - Delete temp file after execution
     c) If no ranges (current logic):
        - Execute: blastdbcmd -db ... -entry IDs

### 2. lib/blast_functions.php (if needed)
   - May need to import the new extraction logic
   - Check if extractSequencesFromBlastDb is defined here or extract_search_helpers.php

## IMPLEMENTATION STEPS

### Step 1: Enhance parseFeatureIds()
Parse input to detect range patterns:
- Pattern 1: "ID:1..10" (colon + dots)
- Pattern 2: "ID:1-10" (colon + hyphen)
- Pattern 3: "ID 1..10" (space + dots)
- Pattern 4: "ID 1-10" (space + hyphen)

Return structure:
{
  'valid': bool,
  'uniquenames': ['ID1', 'ID2', ...],  // IDs without ranges
  'ranges': ['ID1:1-10', 'ID2:1-20'],  // Full ID:range strings
  'has_ranges': bool,
  'error': 'error message'
}

### Step 2: Update extractSequencesFromBlastDb()
Add parameter: $ranges = []
Logic:
```
if (!empty($ranges)) {
  // Create temp file
  $temp_file = tempnam(sys_get_temp_dir(), 'blastdb_');
  file_put_contents($temp_file, implode("\n", $ranges));
  
  // Execute with -entry_batch
  $cmd = "blastdbcmd -db " . escapeshellarg($blast_db) . " -entry_batch " . escapeshellarg($temp_file);
  
  // Execute and cleanup
  $output = [];
  $return_var = 0;
  @exec($cmd, $output, $return_var);
  
  // Delete temp file
  @unlink($temp_file);
  
  // Handle result (same as current)
} else {
  // Current logic (single entry mode)
}
```

### Step 3: Update extractSequencesForAllTypes()
Pass ranges to extractSequencesFromBlastDb():
```
$extract_result = extractSequencesFromBlastDb(
  $fasta_file,
  $uniquenames,
  $organism,
  $assembly,
  $ranges ?? []  // Add this parameter
);
```

### Step 4: Update controllers (retrieve_sequences.php, retrieve_selected_sequences.php)
In the ID parsing section:
```php
$id_parse = parseFeatureIds($uniquenames_string);
if (!$id_parse['valid']) {
    $extraction_errors[] = $id_parse['error'];
} else {
    $uniquenames = $id_parse['uniquenames'];
    $ranges = $id_parse['ranges'] ?? [];  // NEW: extract ranges
    // ... rest of logic
}
```

Then pass ranges to extraction:
```php
$extract_result = extractSequencesForAllTypes(
  $fasta_source['path'], 
  $uniquenames, 
  $sequence_types, 
  $selected_organism, 
  $selected_assembly,
  $ranges ?? []  // NEW: pass ranges
);
```

## TESTING APPROACH
1. Test parseFeatureIds() with various input formats
2. Test temp file creation/cleanup
3. Test blastdbcmd -entry_batch execution
4. Test mixed inputs (some IDs with ranges, some without)
5. Test edge cases (invalid ranges, missing IDs, malformed input)

## VALIDATION CHECKS
- Ensure temp files are cleaned up even on error
- Validate range format (start and end must be numbers)
- Error handling for missing/invalid IDs in range

