# Archived JBrowse2 API Files

This directory contains API files that are no longer used in the current system.

## Archived Date
2026-02-14

## Why These Files Were Archived

The JBrowse2 system has been streamlined to use a single configuration endpoint.

### Old System (Multiple Endpoints)
- Multiple PHP files each serving different parts of config
- Complex routing logic
- Difficult to maintain security consistently

### New System (Single Primary Endpoint)
**Active Files:**
- `../config.php` - Primary endpoint (currently used)
- `../config-optimized.php` - Available for >1000 tracks (not currently used)

**Features:**
- Single secure endpoint for all JBrowse2 configurations
- Returns assembly lists OR full configs based on parameters
- Consistent permission filtering
- JWT token generation for track access
- Optimized endpoint available for future scaling needs

## Archived Files

1. **assembly-cached.php** - Old cached assembly endpoint
2. **config.json.php** - Old JSON-only config endpoint
3. **fake-tracks-server.php** - Test file for development
4. **get-assembly-definition.php** - Old assembly definition endpoint
5. **get-config.php** - Old main config endpoint (replaced by config.php)
6. **test-assembly.php** - Test file for development
7. **tracks.php** - Old tracks-only endpoint

## Active Files (Moved Out of Archive)

- **config-optimized.php** → Moved to `../config-optimized.php` (2026-02-14)
  - Available for assemblies with > 1000 tracks
  - Uses lazy-loading via track URIs
  - Not currently used but ready if needed

## Current Active Flow

1. User visits: `jbrowse2.php`
2. JavaScript loads: `js/jbrowse2-loader.js`
3. Loader fetches: `api/jbrowse2/config.php`
   - Without params → returns assembly list
   - With organism/assembly params → returns full JBrowse2 config
4. JBrowse2 loads tracks directly from URLs with JWT tokens

## If You Need to Restore

These files are preserved in case they contain logic needed for reference.
To restore a file, simply move it back to the parent directory.
