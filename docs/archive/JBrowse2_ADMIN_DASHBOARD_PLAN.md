# JBrowse Admin Dashboard - Implementation Plan

**Date:** February 18, 2026  
**Status:** Ready for Implementation

---

## DECISION: Single Admin Page

**File:** `/data/moop/admin/manage_jbrowse.php`

**Rationale:**
- Limited management scope (sheets, tracks, configs, access)
- Easier navigation and maintenance
- Matches existing admin pattern (Manage Organisms, Manage Groups, etc.)
- Use tabs/collapsible sections for organization

---

## PAGE LAYOUT & FEATURES

### 1. QUICK STATS DASHBOARD (Top Section - Always Visible)

**Purpose:** At-a-glance overview of JBrowse system health

**Displays:**
- Total assemblies with tracks
- Total tracks (overall count)
- Tracks by type (BigWig, BAM, GFF, VCF, etc.)
- Tracks by access level (PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN)
- Warnings count (access level mismatches, broken URLs)
- Recently added tracks (last 7 days)

**Implementation:**
```php
// Query track metadata files
$tracks = [];
foreach (glob("metadata/jbrowse2-configs/tracks/*/*/*/*json") as $file) {
    $track = json_decode(file_get_contents($file), true);
    $tracks[] = $track;
}

// Aggregate stats
$stats = [
    'total_tracks' => count($tracks),
    'by_type' => array_count_values(array_column($tracks, 'type')),
    'by_access' => array_count_values(array_column($tracks, 'metadata.access_level')),
    'warnings' => countWarnings($tracks)
];
```

---

### 2. GOOGLE SHEETS REGISTRATION (Collapsible Section)

**Purpose:** Register and manage Google Sheets URLs per organism/assembly

**Storage Strategy:**
- Location: `/organisms/{organism}/{assembly}/jbrowse_tracks_sheet.txt`
- Format: Simple text file with key=value pairs
- Rationale: Co-located with organism data, backed up together

**File Format:**
```
SHEET_ID=1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo
GID=1977809640
REGISTERED_DATE=2026-02-18
AUTO_SYNC=true
```

**UI Components:**
1. Organism dropdown (populated from organisms directory)
2. Assembly dropdown (filtered by selected organism)
3. Sheet URL or ID input
4. GID (tab identifier) input
5. Test Connection button
6. Auto-sync checkbox
7. Preview table (first 5 rows after validation)
8. Register/Save button

**Validation Checks:**
1. Sheet accessibility (public download via export URL)
2. Required columns present: `track_id`, `name`, `TRACK_PATH`
3. Optional columns detected: `access_level`, `description`, `category`, etc.
4. Row count
5. Preview data quality

**Actions:**
- **Test:** Validate without saving (show preview)
- **Register:** Save sheet configuration to file
- **Re-sync:** Run `generate_tracks_from_sheet.php --force`
- **Unregister:** Remove sheet configuration file

---

### 3. TRACK LISTING (Main Section - Designed for Scale)

**Challenge:** Handle 500+ tracks efficiently without page slowdown

**Solution:** DataTables.js with server-side processing + AJAX

**Features:**
- Server-side pagination (20-50 tracks per page)
- AJAX search (no page reload)
- Multi-column filtering
- Sortable columns
- Bulk selection for batch operations
- Async URL validation (lazy load status badges)

**Display Columns:**
1. **Checkbox** - Multi-select for bulk actions
2. **Track ID/Name** - Primary identifier and display name
3. **Organism/Assembly** - Shortened (hover tooltip for full)
4. **Type** - BigWig, BAM, GFF, GTF, VCF, CRAM, etc.
5. **Access Level** - Badge with color coding
   - PUBLIC: green
   - COLLABORATOR: blue
   - IP_IN_RANGE: orange
   - ADMIN: red
6. **Source** - Local/Remote/UCSC/Ensembl/NCBI
7. **Status** - ✓ Valid | ⚠️ Warning | ✗ Error
8. **Actions** - Dropdown menu (⋮)
   - Edit metadata
   - Test URL
   - Regenerate
   - Delete

**Filters:**
- Organism (dropdown)
- Assembly (dropdown, filtered by organism)
- Track Type (dropdown)
- Access Level (dropdown)
- Source Type (Local/Remote)
- Status (All/Valid/Warning/Error)

**Search:**
- Full-text search across track_id, name, description
- Instant results via AJAX

**Bulk Actions:**
- Generate configs for selected assemblies
- Change access level (batch update)
- Delete selected tracks
- Export to CSV

---

### 4. URL VALIDATION & STATUS

**Public Source Detection:**
```php
$publicBases = [
    'hgdownload.soe.ucsc.edu',
    'genome.ucsc.edu',
    'ftp.ensembl.org',
    'ftp.ensemblgenomes.org',
    'ftp.ncbi.nlm.nih.gov',
    'download.ncbi.nlm.nih.gov'
];

function isPublicSource($url) {
    global $publicBases;
    foreach ($publicBases as $base) {
        if (strpos($url, $base) !== false) {
            return true;
        }
    }
    return false;
}
```

**Validation Rules:**
1. ✓ **Valid:** 
   - PUBLIC tracks from public sources (UCSC, Ensembl, NCBI)
   - Local tracks with any access level
   - Remote tracks that respond to HEAD request (200 OK)

2. ⚠️ **Warning:**
   - COLLABORATOR/ADMIN tracks using public sources
   - Message: "Public source should have PUBLIC access level"

3. ✗ **Error:**
   - URL returns 404 or connection error
   - File path doesn't exist on filesystem
   - Missing index files (.bai, .tbi, .csi required)

**Async Validation:**
```javascript
// Lazy load validation status (don't block page load)
async function validateTrack(trackId, url, isLocal) {
    if (isLocal) {
        // PHP backend checks file_exists()
        return await fetch(`/admin/api/jbrowse_validate_track.php?id=${trackId}`);
    } else {
        // HEAD request to remote URL
        try {
            const response = await fetch(url, { method: 'HEAD' });
            return { valid: response.ok, status: response.status };
        } catch (e) {
            return { valid: false, error: e.message };
        }
    }
}
```

---

### 5. CONFIG GENERATION (Collapsible Section)

**Purpose:** Generate JBrowse2 configuration files for selected assemblies

**Integration with Existing Scripts:**

Uses these existing tools:
1. `tools/jbrowse/generate_tracks_from_sheet.php` - Load tracks from Google Sheet
2. `tools/jbrowse/generate-jbrowse-configs.php` - Generate config.json files

**Generation Modes:**
1. **All Assemblies** - Generate for every assembly in system
2. **Single Assembly** - Select from dropdown
3. **Selected Assemblies** - Multi-select from track listing table

**Options:**
- **Re-sync from Google Sheets first** - Pull latest from registered sheets
- **Force regenerate** - Ignore timestamps, rebuild everything
- **Dry run** - Show what would be generated without changes

**Workflow:**
```php
// 1. Optional: Re-sync from sheets
if ($resync) {
    foreach ($selectedAssemblies as $asm) {
        $sheetFile = "/organisms/{$asm['org']}/{$asm['asm']}/jbrowse_tracks_sheet.txt";
        if (file_exists($sheetFile)) {
            $config = parse_ini_file($sheetFile);
            exec("php tools/jbrowse/generate_tracks_from_sheet.php {$config['SHEET_ID']} " .
                 "--gid {$config['GID']} --organism {$asm['org']} --assembly {$asm['asm']} " .
                 ($force ? "--force" : ""));
        }
    }
}

// 2. Generate configs
exec("php tools/jbrowse/generate-jbrowse-configs.php");
```

**Progress Display:**
- Progress bar (percentage complete)
- Real-time log output (scrollable)
- Status per assembly (✓ success, ⚠️ warnings, ✗ errors)
- Summary: X configs generated, Y warnings, Z errors

**Output:**
- Generates 4 configs per assembly:
  - `PUBLIC.json`
  - `COLLABORATOR.json`
  - `IP_IN_RANGE.json`
  - `ADMIN.json`
- Location: `/jbrowse2/configs/{organism}_{assembly}/`

---

## INTEGRATION WITH EXISTING SCRIPTS

### Using `generate_tracks_from_sheet.php`

**List existing tracks:**
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
    --list-existing \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Force regenerate specific tracks:**
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
    --force track1 track2 track3 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Regenerate all tracks:**
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
    --force \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Clean (remove tracks not in sheet):**
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
    --clean \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Dry run (preview changes):**
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
    --dry-run \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

### Using `remove_tracks.php`

**Delete single track:**
```bash
php tools/jbrowse/remove_tracks.php \
    --track TRACK_ID \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Delete all tracks for assembly:**
```bash
php tools/jbrowse/remove_tracks.php \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Dry run:**
```bash
php tools/jbrowse/remove_tracks.php \
    --track TRACK_ID \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1 \
    --dry-run
```

---

## IMPLEMENTATION FILES

```
admin/
├── manage_jbrowse.php                    # Main wrapper (uses admin_init.php + layout.php)
├── pages/
│   └── manage_jbrowse.php                # Content file (pure display, no HTML structure)
└── api/
    ├── jbrowse_register_sheet.php        # Register/update Google Sheet config
    ├── jbrowse_list_tracks.php           # DataTables server-side endpoint (JSON)
    ├── jbrowse_validate_track.php        # Validate single track URL/path
    ├── jbrowse_delete_tracks.php         # Delete single/multiple tracks
    ├── jbrowse_bulk_update.php           # Batch update access levels
    └── jbrowse_generate_configs.php      # Trigger config generation
```

### File Responsibilities

**admin/manage_jbrowse.php** (Wrapper)
- Includes `admin_init.php` (auth, config, includes)
- Includes `layout.php` (render system)
- Prepares data for content file
- Calls `render_display_page()`

**admin/pages/manage_jbrowse.php** (Content)
- Pure display HTML
- No `<html>`, `<head>`, `<body>` tags
- Uses data passed from wrapper
- Includes inline JavaScript for AJAX calls

**admin/api/*.php** (Endpoints)
- JSON responses only
- Handle AJAX requests from frontend
- Use `handleAdminAjax()` pattern from other admin pages
- Return structured data: `{success: bool, data: any, error: string}`

---

## DATA FLOW

### Track Listing (DataTables)

```
Browser
   ↓ (AJAX request with filters/search/pagination)
admin/api/jbrowse_list_tracks.php
   ↓ (scan metadata files)
metadata/jbrowse2-configs/tracks/*/*/*/*json
   ↓ (filter, sort, paginate)
JSON Response
   ↓
DataTables renders table
```

### Sheet Registration

```
User fills form
   ↓ (AJAX POST)
admin/api/jbrowse_register_sheet.php
   ↓ (validate sheet)
Download TSV from Google
   ↓ (check columns)
Validate required columns
   ↓ (save config)
/organisms/{org}/{asm}/jbrowse_tracks_sheet.txt
   ↓ (return status)
JSON Response → Update UI
```

### Config Generation

```
User clicks "Generate Configs"
   ↓ (AJAX POST)
admin/api/jbrowse_generate_configs.php
   ↓ (optional: re-sync)
exec("php tools/jbrowse/generate_tracks_from_sheet.php ...")
   ↓ (generate configs)
exec("php tools/jbrowse/generate-jbrowse-configs.php")
   ↓ (stream progress)
Server-Sent Events → Update progress bar
   ↓ (complete)
JSON Response → Show summary
```

---

## ADMIN DASHBOARD INTEGRATION

**Add to:** `admin/pages/admin.php`

**Section:** Data Management

```html
<div class="col-md-6 mb-3">
  <div class="card h-100">
    <div class="card-body">
      <h5 class="card-title">
        <i class="fa fa-dna"></i> JBrowse Track Management
      </h5>
      <p class="card-text">
        Manage JBrowse assemblies, tracks, and configurations. 
        Register Google Sheets, validate track URLs, and generate configs.
      </p>
      <a href="manage_jbrowse.php" class="btn btn-success">
        Go to JBrowse Management
      </a>
    </div>
  </div>
</div>
```

---

## STYLING & UX

**Match existing admin pages:**
- Bootstrap 5 cards and components
- Font Awesome icons
- Collapsible sections with `data-bs-toggle="collapse"`
- Color-coded badges for access levels
- DataTables styling (already in use)

**Color Scheme for Access Levels:**
```css
.badge-public { background-color: #28a745; } /* green */
.badge-collaborator { background-color: #007bff; } /* blue */
.badge-ip-in-range { background-color: #fd7e14; } /* orange */
.badge-admin { background-color: #dc3545; } /* red */
```

**Responsive Design:**
- Mobile-friendly table (horizontal scroll)
- Collapsible filters on small screens
- Stack cards vertically on mobile

---

## SECURITY

**Access Control:**
- Use `admin_init.php` for authentication
- Only admins can access this page
- All API endpoints check admin status

**Input Validation:**
- Sanitize sheet IDs and GIDs
- Validate organism/assembly names (alphanumeric + underscore)
- Prevent path traversal in file operations
- Escape shell commands (use `escapeshellarg()`)

**AJAX CSRF Protection:**
- Use existing session-based CSRF tokens
- Validate on all POST requests

---

## TESTING CHECKLIST

- [ ] Register Google Sheet with your URL
- [ ] Validate sheet columns detection
- [ ] Preview first 5 rows
- [ ] Save sheet configuration
- [ ] List all tracks (paginated)
- [ ] Filter by organism/assembly/type
- [ ] Search tracks by name
- [ ] Validate track URLs (local and remote)
- [ ] Detect public source warnings
- [ ] Bulk select tracks
- [ ] Generate configs (single assembly)
- [ ] Generate configs (multiple assemblies)
- [ ] Re-sync from sheet
- [ ] Delete single track
- [ ] Delete multiple tracks
- [ ] Test with 500+ tracks (performance)

---

## FUTURE ENHANCEMENTS

**Phase 2 (Optional):**
1. **Track Editor** - Edit track metadata inline
2. **Access Level Batch Update** - Change multiple tracks at once
3. **Track History** - View changes over time
4. **URL Health Monitoring** - Periodic checks, email alerts
5. **Import/Export** - Backup track configurations
6. **Track Templates** - Pre-configured track types
7. **Drag & Drop File Upload** - Upload track files directly
8. **Track Preview** - Inline IGV.js viewer

---

## IMPLEMENTATION ORDER

**Phase 1: Core Functionality**
1. ✅ Create wrapper: `admin/manage_jbrowse.php`
2. ✅ Create content: `admin/pages/manage_jbrowse.php` (basic layout)
3. ✅ Create API: `jbrowse_list_tracks.php` (track listing)
4. ✅ Implement DataTables with server-side processing
5. ✅ Add to admin dashboard

**Phase 2: Sheet Registration**
6. ✅ Create API: `jbrowse_register_sheet.php`
7. ✅ Add registration form UI
8. ✅ Test sheet validation

**Phase 3: Config Generation**
9. ✅ Create API: `jbrowse_generate_configs.php`
10. ✅ Add generation UI with progress
11. ✅ Test with existing scripts

**Phase 4: Validation & Polish**
12. ✅ Add URL validation
13. ✅ Add status badges
14. ✅ Add bulk actions
15. ✅ Performance testing with 500+ tracks

---

**Status:** Ready to implement  
**Next Step:** Create wrapper and content files
