# Track Generator Output Improvements

**Date:** 2026-02-14  
**Issue:** When using `--force track_id`, the output didn't clearly show that the specific track was regenerated.

## Changes Made

### 1. Added Real-Time Generation Feedback

**Before:**
```
Generating tracks...
------------------------------------------------------------

(silent processing...)

============================================================
RESULTS
============================================================
Total tracks processed: 36
  ✓ Created: 3
  ⊘ Skipped: 31
  ✗ Failed: 3
```

**After:**
```
Generating tracks...
------------------------------------------------------------

  ✓ Created: reference_seq (Reference sequence)
  ✓ Created: test_features_bed (Test BED Features)
  ♻ Regenerating: test_maf (Test MAF Alignment)
    ✓ Regenerated successfully
  ✗ Failed: test_paf

============================================================
RESULTS
============================================================
Total tracks processed: 36
  ✓ Created: 3 (2 new, 1 regenerated)
  ⊘ Skipped: 31
  ✗ Failed: 3

Regenerated Tracks:
  ♻ test_maf (Test MAF Alignment)

Failed Tracks:
  ✗ test_paf - Generation failed
```

### 2. Summary Section Improvements

Now distinguishes between:
- **New tracks** created (✓)
- **Regenerated tracks** (♻)
- **Skipped tracks** (⊘)
- **Failed tracks** (✗)

### 3. Regenerated Tracks Section

When tracks are regenerated with `--force`, they're listed in a separate section:

```
Regenerated Tracks:
  ♻ test_maf (Test MAF Alignment)
```

## Files Modified

1. **`/data/moop/lib/JBrowse/TrackGenerator.php`**
   - Updated `generateTracks()` to output real-time feedback
   - Added "Regenerating" message for forced tracks
   - Changed success array to include regeneration flag

2. **`/data/moop/tools/jbrowse/generate_tracks_from_sheet.php`**
   - Updated results summary to count new vs regenerated
   - Added "Regenerated Tracks" section
   - Shows breakdown: "Created: 3 (2 new, 1 regenerated)"

## Examples

### Force Regenerate Single Track
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
  --gid GID \
  --organism Organism \
  --assembly Assembly \
  --force test_maf
```

**Output clearly shows:**
```
  ♻ Regenerating: test_maf (Test MAF Alignment)
    ✓ Regenerated successfully
```

### Force Regenerate Multiple Tracks
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
  --gid GID \
  --organism Organism \
  --assembly Assembly \
  --force track1 track2 track3
```

**Output shows each one:**
```
  ♻ Regenerating: track1 (Track 1 Name)
    ✓ Regenerated successfully
  ♻ Regenerating: track2 (Track 2 Name)
    ✓ Regenerated successfully
  ♻ Regenerating: track3 (Track 3 Name)
    ✓ Regenerated successfully

Regenerated Tracks:
  ♻ track1 (Track 1 Name)
  ♻ track2 (Track 2 Name)
  ♻ track3 (Track 3 Name)
```

### Force Regenerate All Tracks
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
  --gid GID \
  --organism Organism \
  --assembly Assembly \
  --force
```

**Output shows all regenerations:**
```
Total tracks processed: 36
  ♻ Regenerated: 33
  ✓ Created: 3
  ⊘ Skipped: 0
  ✗ Failed: 0

Regenerated Tracks:
  ♻ track1 (Track 1)
  ♻ track2 (Track 2)
  ... (all regenerated tracks listed)
```

## Symbol Legend

| Symbol | Meaning |
|--------|---------|
| ✓ | Created (new track) |
| ♻ | Regenerated (forced update of existing track) |
| ⊘ | Skipped (already exists, not forced) |
| ✗ | Failed (error during generation) |

## Benefits

1. **Immediate feedback** - See what's happening in real-time
2. **Clear distinction** - Know which tracks were regenerated vs newly created
3. **Easy verification** - Confirm that `--force track_id` worked
4. **Better troubleshooting** - See exactly where failures occur

## Testing

Tested with:
- Single track regeneration: `--force test_maf` ✓
- Multiple track regeneration: `--force track1 track2` ✓
- Force all tracks: `--force` ✓
- Mixed creation and regeneration ✓

All scenarios now provide clear, actionable output.
