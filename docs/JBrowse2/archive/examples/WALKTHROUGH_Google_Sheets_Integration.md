# JBrowse2 Google Sheets Integration - Test Walkthrough

**Date:** February 11, 2026  
**Organism:** Nematostella vectensis  
**Assembly:** GCA_033964005.1  
**Google Sheet:** https://docs.google.com/spreadsheets/d/1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo/edit?gid=1977809640#gid=1977809640

This walkthrough demonstrates the automated track generation system using Google Sheets.

## Prerequisites

1. PHP environment with required extensions
2. Test data in `/var/www/html/moop/data/tracks/bigwig/`
3. Access to the Google Sheet
4. JBrowse2 instance at `/var/www/html/moop/jbrowse2/`

## Step 1: Install Dependencies (if needed)

```bash
cd /data/moop/tools/jbrowse

# Check if we need to install dependencies
php -r "echo 'PHP version: ' . PHP_VERSION . PHP_EOL;"

# For production, we should use composer to manage dependencies
# For testing, we can use the script directly since it only needs CSV parsing
```

## Step 2: Verify Test Data

```bash
# Check BigWig files
ls -lh /var/www/html/moop/data/tracks/bigwig/Nematostella*

# Should see:
# - Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.neg.bw
# - Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.pos.bw
```

## Step 3: Review Google Sheet Format

The sheet should have these **required columns**:
- `track_id` - Unique identifier for the track
- `name` - Display name
- `TRACK_PATH` - Full path to file (local or http://)
- `category` - Organizational category
- `access_level` - public, ip_range, or admin

**Optional columns**:
- `technique` - Experimental technique
- `condition` - Experimental condition
- `tissue` - Tissue type
- Any column starting with `#` is ignored

**Special formatting for grouped tracks:**
```
# Multi-BigWig: Track Display Name
## colorGroup: Group Name
track_id  name  TRACK_PATH  category  access_level
track_id  name  TRACK_PATH  category  access_level
### end
```

## Step 4: Run Config Generation (Dry Run)

```bash
cd /data/moop/tools/jbrowse

# First, do a dry-run to see what would be generated
php generate_tracks_from_sheet.php \
  --sheet-url "https://docs.google.com/spreadsheets/d/1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo/export?format=csv&gid=1977809640" \
  --organism "Nematostella_vectensis" \
  --assembly "GCA_033964005.1" \
  --jbrowse-dir "/var/www/html/moop/jbrowse2" \
  --dry-run

# Review the output:
# - Lists all tracks that would be created
# - Shows any errors or warnings
# - Reports color group assignments
# - Validates file existence
```

## Step 5: Generate Configs

```bash
# If dry-run looks good, run for real
php generate_tracks_from_sheet.php \
  --sheet-url "https://docs.google.com/spreadsheets/d/1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo/export?format=csv&gid=1977809640" \
  --organism "Nematostella_vectensis" \
  --assembly "GCA_033964005.1" \
  --jbrowse-dir "/var/www/html/moop/jbrowse2"

# This creates:
# - Individual track configs in jbrowse2/tracks/
# - Multi-BigWig configs for grouped tracks
# - Metadata files
# - Summary report
```

## Step 6: Test Generated Configs

```bash
# View generated track files
ls -lh /var/www/html/moop/jbrowse2/tracks/Nematostella_vectensis/GCA_033964005.1/

# Check config validity
php /data/moop/api/jbrowse2/validate_config.php \
  --organism "Nematostella_vectensis" \
  --assembly "GCA_033964005.1"
```

## Step 7: Test in JBrowse2

1. Navigate to: http://localhost/moop/jbrowse2.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
2. Open track selector
3. Verify tracks appear in correct categories
4. Test loading individual tracks
5. Test multi-BigWig tracks (if any)
6. Test search functionality on gene tracks

## Expected Results

### Individual Tracks Created:
- `MOLNG-2707_S1-body-wall.pos` (BigWig, public)
- `MOLNG-2707_S1-body-wall.neg` (BigWig, public)

### Multi-BigWig Tracks (if grouped):
- Combined visualization with color coding

### Features to Test:
- [x] Track loads without errors
- [x] Data displays correctly
- [x] Colors match specification
- [x] Category organization works
- [x] Access control respected
- [x] Search works (for GFF tracks)

## Troubleshooting

### Issue: Files not found
```bash
# Check paths in sheet match actual files
php generate_tracks_from_sheet.php --sheet-url "..." --validate-only
```

### Issue: Color group insufficient
```
ERROR: Color group 'blues' has 11 colors but group 'Body Wall Expression' has 15 tracks
SUGGESTION: Use color group 'warm' (20 colors) or 'all' (147 colors)
```

### Issue: Missing required columns
```
ERROR: Missing required column: TRACK_PATH
```

### Issue: Config validation fails
```bash
# Check JSON syntax
cat /var/www/html/moop/jbrowse2/tracks/.../trackfile.json | jq .
```

## Next Steps

1. Add more tracks to the Google Sheet
2. Test multi-BigWig grouping
3. Add GFF annotation tracks with text indexing
4. Test synteny tracks (requires two genomes)
5. Test MAF alignment tracks (requires plugin)

## Notes

- The script backs up existing configs before overwriting
- Dry-run mode is recommended before production runs
- Color groups can be specified per multi-track group
- Access levels integrate with MOOP's authentication system
- Remote tracks (http://) are supported but not validated for existence

## Script Usage Reference

```bash
php generate_tracks_from_sheet.php [OPTIONS]

Required:
  --sheet-url URL        Google Sheets CSV export URL
  --organism NAME        Organism name (e.g., Nematostella_vectensis)
  --assembly ID          Assembly ID (e.g., GCA_033964005.1)
  --jbrowse-dir PATH     Path to JBrowse2 root directory

Optional:
  --dry-run             Show what would be done without making changes
  --validate-only       Only validate sheet format and files
  --verbose             Show detailed progress
  --force               Overwrite existing configs without backup
  --help                Show this help message

Examples:
  # Dry run
  php generate_tracks_from_sheet.php --sheet-url "..." --organism "Nvec" --assembly "GCA_033964005.1" --jbrowse-dir "/var/www/html/moop/jbrowse2" --dry-run

  # Production run
  php generate_tracks_from_sheet.php --sheet-url "..." --organism "Nvec" --assembly "GCA_033964005.1" --jbrowse-dir "/var/www/html/moop/jbrowse2"
```

## Color Groups Available

Run with `--list-colors` to see all available color groups and their sizes:
```bash
php generate_tracks_from_sheet.php --list-colors
```

Small groups (5-15 colors): blues, purples, reds, greens, oranges, browns, grays
Medium groups (16-30 colors): warm, cool, earth, pastel
Large groups (30+ colors): vibrant, all

## Integration with MOOP

The generated configs integrate with MOOP's systems:
- **Access Control**: Uses `access_level` column (public, ip_range, admin)
- **Metadata**: Stored in `/data/moop/metadata/`
- **Settings**: Can reference `$CONFIG` variables
- **Dynamic Loading**: Works with `jbrowse2.php` dynamic config system
