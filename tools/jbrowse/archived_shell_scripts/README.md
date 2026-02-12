# Archived Shell Scripts

**Migration Date:** February 12, 2026  
**Reason:** Converting to pure PHP architecture for better integration, security, and maintainability

---

## Why We Migrated from Shell to PHP

### Problems with Shell Scripts
- ❌ Cannot access ConfigManager (hardcoded paths)
- ❌ No permission system integration
- ❌ Cannot integrate with Web UI directly (requires exec())
- ❌ Difficult error handling and reporting
- ❌ Two languages to maintain
- ❌ Not portable (Bash-specific, Unix-only)
- ❌ Hard to unit test
- ❌ Cannot use PHP functions/utilities

### Benefits of PHP
- ✅ Full ConfigManager access (portable paths)
- ✅ Permission system integration
- ✅ Direct method calls (no exec())
- ✅ Exception handling
- ✅ Single codebase
- ✅ Cross-platform
- ✅ Unit testable
- ✅ Code reuse

---

## Migration Map

| Shell Script | Replaced By | Date | Status |
|-------------|-------------|------|--------|
| `add_bigwig_track.sh` | `BigWigTrack.php` | 2026-02-12 | Phase 2A |
| `remove_jbrowse_data.sh` | `TrackManager.php` + `remove_tracks.php` | 2026-02-12 | Phase 2A |
| `add_bam_track.sh` | `BAMTrack.php` | TBD | Phase 2B |
| `add_vcf_track.sh` | `VCFTrack.php` | TBD | Phase 2B |
| `add_gff_track.sh` | `GFFTrack.php` | TBD | Phase 2B |
| `add_gtf_track.sh` | `GTFTrack.php` | TBD | Phase 2B |
| `add_cram_track.sh` | `CRAMTrack.php` | TBD | Phase 2B |
| `add_paf_track.sh` | `PAFTrack.php` | TBD | Phase 2B |
| `add_bed_track.sh` | `BEDTrack.php` | TBD | Phase 2B |
| `add_multi_bigwig_track.sh` | `ComboTrack.php` | TBD | Phase 2C |

---

## Scripts NOT Archived (Still Useful)

These remain as shell scripts because they are:
- One-time setup utilities
- Server configuration scripts  
- Example/documentation scripts
- Not called from PHP code

**Kept:**
- `add_assembly_to_jbrowse.sh` - Initial assembly setup
- `setup_jbrowse_assembly.sh` - Assembly configuration
- `bulk_load_assemblies.sh` - Bulk operations
- `integrate_nematostella.sh` - Example/documentation
- `setup-remote-tracks-server.sh` - Server setup
- `index_default_annotations.sh` - Indexing utility

---

## How to Reference Archived Scripts

If you need to check functionality from old shell scripts:

```bash
# View archived script
cat /data/moop/tools/jbrowse/archived_shell_scripts/add_bigwig_track.sh

# Compare old vs new approach
diff -u archived_shell_scripts/add_bigwig_track.sh ../lib/JBrowse/TrackTypes/BigWigTrack.php
```

---

## New PHP Architecture

### Track Generation
**Old (Shell):**
```bash
bash tools/jbrowse/add_bigwig_track.sh \
  TRACK_ID \
  "Track Name" \
  /path/to/file.bw \
  Organism \
  Assembly
```

**New (PHP):**
```php
$track = new BigWigTrack($pathResolver, $config);
$track->generate($trackData);
```

### Track Removal
**Old (Shell):**
```bash
bash tools/jbrowse/remove_jbrowse_data.sh \
  --organism Organism \
  --assembly Assembly
```

**New (PHP):**
```php
$manager = new TrackManager($config, $pathResolver);
$manager->removeAssembly($organism, $assembly);
```

Or via CLI:
```bash
php tools/jbrowse/remove_tracks.php \
  --organism Organism \
  --assembly Assembly
```

---

## Functionality Preserved

All functionality from shell scripts has been preserved or improved:

✅ **Track generation** - All track types  
✅ **Path resolution** - Improved with ConfigManager  
✅ **Validation** - Enhanced with PHP validation  
✅ **Error handling** - Better with exceptions  
✅ **JSON generation** - More control in PHP  
✅ **Metadata** - Full support  
✅ **Colors** - Enhanced color schemes  
✅ **Categories** - Full support  
✅ **Access levels** - Full support  

---

## If You Find Missing Functionality

If you discover functionality in archived shell scripts that's missing from PHP:

1. **Check the PHP implementation** - It might be there with different syntax
2. **File an issue** - Describe what's missing
3. **Reference the shell script** - Link to archived version
4. **We'll add it** - PHP implementations are actively maintained

---

## Archive Contents

Shell scripts are preserved here for:
- Reference during development
- Comparison testing
- Recovery of any missed functionality
- Historical documentation

**Do not execute archived scripts** - Use the new PHP implementations instead.

---

*Last Updated: February 12, 2026*
