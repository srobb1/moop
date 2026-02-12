# Code Review: generate_tracks_from_sheet.py

**Date:** 2026-02-11  
**Reviewer Assessment:** Efficient, Well-Structured, Production-Ready  
**Dependencies:** Python 3 Standard Library Only ✅

---

## Executive Summary

### ✅ Strengths

1. **No External Dependencies** - Uses only Python standard library (urllib, csv, json, subprocess, pathlib)
2. **Well Organized** - Clear function separation, logical flow
3. **Comprehensive** - Handles single tracks, combo tracks, colors, access control
4. **Good Error Handling** - Validates input, checks files exist, reports issues
5. **Extensible** - Easy to add new track types
6. **Battle-Tested Pattern** - Based on working Perl implementation

### ⚠ Areas for Improvement

1. **Documentation** - Needs more inline comments
2. **Track Type Extension** - Could be more explicit about adding new types
3. **Error Collection** - Could track more metadata about failures

### 📊 Metrics

- **Lines of Code:** 809
- **Functions:** 13
- **Cyclomatic Complexity:** Low-Medium (good)
- **Dependencies:** 0 external (excellent)
- **Reusability:** High

---

## Dependency Analysis

### Current Dependencies (All Standard Library)

```python
import sys              # System-specific parameters
import argparse         # Command-line argument parsing
import csv             # CSV/TSV file reading
import re              # Regular expressions (sheet ID extraction, color parsing)
import json            # JSON handling (not heavily used, could remove)
import os              # OS interface (not heavily used, could remove)
import subprocess      # Running bash scripts
from datetime import datetime   # Timestamp (only used once)
from urllib.request import urlopen  # Download Google Sheets as TSV
from pathlib import Path    # Modern path handling
```

### ✅ **No Virtual Environment Needed**

All imports are from Python 3.6+ standard library. Script will run on any system with Python 3.6+.

### 🔄 **Alternative: PHP or Perl?**

**Recommendation: Keep Python**

| Language | Pros | Cons | Verdict |
|----------|------|------|---------|
| **Python (current)** | ✅ No dependencies<br>✅ Readable<br>✅ Pathlib for paths<br>✅ Already written | ⚠️ Requires Python 3.6+ | **Best choice** |
| **PHP** | ✅ Already used in MOOP<br>✅ Good for web integration | ❌ Less readable for CLI<br>❌ Would need rewrite | Not worth rewriting |
| **Perl** | ✅ Original script in Perl<br>✅ Good text processing | ❌ Less maintainable<br>❌ Harder to read<br>❌ Would need rewrite | Not worth rewriting |

**Verdict:** Python is the right choice. It's readable, has no external dependencies, and is already working.

---

## Code Structure Analysis

### Function Organization

```
download_sheet_as_tsv()         # Download Google Sheet as TSV
parse_sheet()                   # Parse TSV into tracks + combo tracks
determine_track_type()          # Auto-detect track type from file extension
track_exists()                  # Check if track already created
is_remote_track()              # Check if HTTP/HTTPS URL
resolve_track_path()           # Resolve relative/absolute/URL paths
verify_track_exists()          # Verify local files exist
generate_single_track()        # Create individual track (calls bash scripts)
generate_combo_track()         # Create multi-BigWig track
get_color()                    # Get color from color group
suggest_color_groups()         # Suggest color groups for N files
print_color_groups()           # List all available color groups
main()                         # Entry point, argument parsing, orchestration
```

### ✅ Good Separation of Concerns

Each function has a single responsibility. Easy to test and maintain.

---

## Adding New Track Types

### Current Track Types

```python
def determine_track_type(row):
    """Determine track type from file extension only"""
    track_path = row.get('TRACK_PATH', '')
    ext = Path(track_path).suffix.lower()
    
    if ext in ['.bw', '.bigwig']:
        return 'bigwig'
    elif ext in ['.bam']:
        return 'bam'
    elif ext in ['.vcf', '.gz']:
        if 'vcf' in track_path.lower():
            return 'vcf'
        elif 'gff' in track_path.lower():
            return 'gff'
        return None
    elif ext in ['.gff', '.gff3']:
        return 'gff'
    elif ext in ['.fa', '.fasta', '.fna']:
        return 'fasta'
    
    return None
```

### 📝 How to Add New Track Types

**Example: Adding CRAM support**

#### Step 1: Add to `determine_track_type()`

```python
def determine_track_type(row):
    """Determine track type from file extension only"""
    track_path = row.get('TRACK_PATH', '')
    ext = Path(track_path).suffix.lower()
    
    # ... existing code ...
    
    elif ext in ['.cram']:  # NEW: Add CRAM support
        return 'cram'
    
    return None
```

#### Step 2: Add to `generate_single_track()`

```python
def generate_single_track(row, organism, assembly, moop_root, default_color='DodgerBlue', dry_run=False):
    # ... existing validation code ...
    
    # ... existing track types (bigwig, bam, vcf, gff) ...
    
    elif track_type == 'cram':  # NEW: Handle CRAM tracks
        cmd = [
            'bash', str(script_dir / 'add_cram_track.sh'),  # Create this script
            resolved_path, organism, assembly,
            '--name', name,
            '--track-id', track_id,
            '--category', category,
            '--access', access
        ]
        if description:
            cmd.extend(['--description', description])
```

#### Step 3: Create the bash script

Create `/data/moop/tools/jbrowse/add_cram_track.sh` following the pattern of `add_bam_track.sh`.

### Future Track Types to Add

| Type | Extension | Priority | Notes |
|------|-----------|----------|-------|
| **CRAM** | `.cram` | High | More efficient than BAM, uses CramAdapter |
| **PAF** | `.paf` | High | Long-read alignments, uses PAFAdapter |
| **BED** | `.bed` | Medium | Generic feature tracks, uses BedTabixAdapter |
| **GTF** | `.gtf` | Medium | Gene annotations, uses GtfAdapter |
| **BedGraph** | `.bedgraph`, `.bg` | Medium | Text-based signal, uses BedGraphAdapter |
| **BigBed** | `.bb`, `.bigbed` | Medium | Indexed BED format, uses BigBedAdapter |
| **HiC** | `.hic` | Low | Chromatin interactions, uses HicAdapter |

### 📍 Code Locations for Extensions

```
Line ~373-400:   determine_track_type()     ← Add new extensions here
Line ~437-545:   generate_single_track()    ← Add new track handling here
Line ~26-50:     Docstring                  ← Update documentation here
```

---

## Code Quality Assessment

### ✅ Strengths

1. **Input Validation**
   - Checks for required columns
   - Validates file existence (local files)
   - Handles missing/empty values gracefully

2. **Error Handling**
   - Try/except blocks around subprocess calls
   - Collects and reports all issues at end
   - Differentiates skipped vs failed tracks

3. **Path Handling**
   - Uses modern `pathlib.Path`
   - Handles absolute, relative, and URL paths
   - Cross-platform compatible

4. **Reporting**
   - Progress messages during processing
   - Comprehensive summary at end
   - Lists all skipped/failed tracks

5. **Extensibility**
   - Color system is data-driven (COLORS dict)
   - Track types easy to add
   - Access levels configurable

### ⚠ Areas for Improvement

#### 1. **Add More Inline Comments**

Current state: Docstrings present but limited inline comments.

**Recommendation:** Add comments to complex sections:
- Sheet parsing logic (combo track markers)
- Color resolution (exact=, index syntax)
- Path resolution logic

#### 2. **Error Details**

Current: Tracks which tracks failed, but not always why.

**Recommendation:** Collect error messages:
```python
failed_tracks.append({
    'track_id': track_id,
    'name': name,
    'error': result.stderr if result else 'Unknown error'
})
```

#### 3. **Configuration File**

Current: Color groups hardcoded.

**Future Enhancement:** Load from JSON config file for easier customization.

#### 4. **Logging**

Current: Print statements.

**Future Enhancement:** Use Python `logging` module for better control.

---

## Performance Analysis

### Current Performance: **Good**

- Downloads sheet once (not per-track)
- Checks existing tracks before creating
- Minimal I/O operations
- No database queries

### Bottlenecks

1. **Sequential Processing** - Tracks processed one at a time
2. **Subprocess Calls** - Each track spawns bash script

### Optimization Opportunities (Low Priority)

If processing 1000+ tracks becomes slow:

1. **Parallel Processing**
   ```python
   from concurrent.futures import ThreadPoolExecutor
   with ThreadPoolExecutor(max_workers=4) as executor:
       results = executor.map(generate_single_track, tracks)
   ```

2. **Batch Metadata Creation**
   - Create all JSON files first
   - Regenerate configs once at end

**Current Assessment:** Not needed unless >500 tracks.

---

## Security Considerations

### ✅ Good Practices

1. **No Code Injection** - Uses subprocess with list (not shell=True)
2. **Path Validation** - Checks file existence
3. **No Eval/Exec** - No dynamic code execution

### ⚠ Considerations

1. **Google Sheets Access** - Public read access required
2. **File Permissions** - Runs as user, respects file permissions
3. **URL Validation** - Could add URL format validation

---

## Testing Recommendations

### Current Testing: Manual

**Recommended Test Cases:**

1. **Unit Tests**
   ```python
   def test_determine_track_type():
       assert determine_track_type({'TRACK_PATH': 'file.bw'}) == 'bigwig'
       assert determine_track_type({'TRACK_PATH': 'file.bam'}) == 'bam'
   
   def test_resolve_track_path():
       path, is_remote = resolve_track_path('data/file.bw', '/data/moop')
       assert path == '/data/moop/data/file.bw'
       assert not is_remote
   
   def test_get_color():
       assert get_color('blues', 0) == 'Navy'
       assert get_color('exact=Red', 0) == 'Red'
   ```

2. **Integration Tests**
   - Test with sample Google Sheet
   - Verify track creation
   - Test error conditions

3. **Edge Cases**
   - Empty sheet
   - Missing required columns
   - Invalid color groups
   - Malformed combo track markers

---

## Comparison to Original Perl Script

### Improvements Over Perl Version

| Aspect | Perl Script | Python Script | Winner |
|--------|-------------|---------------|--------|
| **Readability** | Complex regex, hard to follow | Clear logic, modern syntax | Python ✅ |
| **Path Handling** | String concatenation | pathlib.Path | Python ✅ |
| **Error Reporting** | Basic | Comprehensive summary | Python ✅ |
| **Dependencies** | Core Perl + LWP | Python stdlib only | Tie |
| **Maintainability** | Difficult | Easy | Python ✅ |
| **Performance** | Fast | Fast enough | Tie |

### Preserved Features from Perl

✅ Color group system (blues, reds, exact=Color)  
✅ Combo track support with color groups  
✅ Metadata columns (technique, tissue, condition)  
✅ Category-based organization  
✅ Auto-detection logic  

---

## Final Recommendations

### ✅ Keep Python - No Changes Needed

**Reasons:**
1. ✅ **Zero external dependencies** - Works everywhere Python 3.6+ exists
2. ✅ **Well structured** - Easy to maintain and extend
3. ✅ **Good performance** - Fast enough for current use case
4. ✅ **Already working** - Production ready

### 📝 Suggested Improvements (Optional, Low Priority)

1. **Add inline comments** to complex sections (2-3 hours)
2. **Create unit tests** for core functions (4-6 hours)
3. **Add logging module** instead of print() (1-2 hours)
4. **Document CRAM/BED support** and create scripts (4-6 hours each)

### 🚫 Don't Do

- ❌ Don't rewrite in PHP or Perl (waste of time)
- ❌ Don't add external dependencies (requests, pandas, etc.)
- ❌ Don't over-engineer (current design is good)

---

## Code Quality Score

| Metric | Score | Notes |
|--------|-------|-------|
| **Functionality** | 9/10 | Does everything needed, comprehensive |
| **Readability** | 7/10 | Good structure, could use more comments |
| **Maintainability** | 8/10 | Easy to modify, well organized |
| **Performance** | 8/10 | Fast enough, room for optimization if needed |
| **Security** | 8/10 | Good practices, no major issues |
| **Dependencies** | 10/10 | Zero external dependencies ⭐ |
| **Error Handling** | 8/10 | Good reporting, could collect more details |
| **Extensibility** | 9/10 | Easy to add new track types |

**Overall Score: 8.4/10 - Production Ready** ✅

---

## Conclusion

**The script is well-written, efficient, and production-ready.**

### Key Strengths:
- ✅ No external dependencies (huge win)
- ✅ Clean, maintainable code structure
- ✅ Comprehensive error reporting
- ✅ Easy to extend with new track types

### Recommendation:
**Use as-is.** Optional enhancements can be added incrementally if needed, but the current implementation is solid and ready for production use.

**No need for virtual environment, PHP rewrite, or Perl rewrite.**
