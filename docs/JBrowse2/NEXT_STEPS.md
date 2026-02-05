# JBrowse2 Integration - Next Steps

## Current Status ✅

1. **Assembly Definition Created** - Anoura_caudifer assembly is defined in `/metadata/jbrowse2-configs/Anoura_caudifer.json`
2. **API Endpoint Working** - `/api/jbrowse2/get-config.php` serves assemblies based on user permissions
3. **MOOP Integration** - JBrowse2 page uses MOOP authentication, navbar, and layout
4. **Dynamic Config Loading** - Assemblies loaded based on user login status and access level
   - Anonymous users: See only "Public" assemblies
   - Logged-in users: See assemblies based on their access level
   - Admin users: See all assemblies

## Browser Testing

Access JBrowse2 Genome Browser:
- **URL**: http://localhost:8000/moop/jbrowse2 (with SSH tunnel)
- **Layout**: Includes MOOP navbar, header, footer
- **Login**: Sign in to see more assemblies based on your access level

## What Still Needs Work

### 1. **JBrowse2 Fullscreen View Issues**
   - When opening a specific assembly in fullscreen, JBrowse2 fails to initialize
   - **Root Cause**: JBrowse2 React application doesn't load properly
   - **Solution**: May need to:
     - Use JBrowse2's embed API instead of trying to initialize dynamically
     - Or use a proper JBrowse2 build with a working REST API

### 2. **FASTA File Access**
   - Symlinks are properly set up in `/var/www/html/moop/data/genomes/`
   - Files should be accessible at `/moop/data/genomes/{organism}/{assembly}/reference.fasta`
   - Need to verify FASTA index (.fai) files are generated

### 3. **Track Configuration**
   - Currently only showing reference sequence track
   - Need to add:
     - Annotation tracks (GFF3 files)
     - BAM files for alignment view
     - VCF files for variant view

### 4. **Bulk Assembly Loading Script**
   - Location: `/tools/jbrowse/` (to be created)
   - Should:
     - Read from organism database
     - Create modular assembly definitions
     - Avoid hardcoding paths
     - Support bulk loading with reproducible scripts

## Assembly Definition Format

Assemblies are defined as modular JSON files in `/metadata/jbrowse2-configs/`:

```json
{
  "name": "Anoura_caudifer_GCA_004027475.1",
  "displayName": "Anoura caudifer (GCA_004027475.1)",
  "aliases": ["ACA1", "GCA_004027475.1"],
  "accessLevel": "Public",
  "sequence": {
    "type": "ReferenceSequenceTrack",
    "trackId": "Anoura_caudifer_GCA_004027475.1-ReferenceSequenceTrack",
    "adapter": {
      "type": "BgzipFastaAdapter",
      "fastaLocation": {
        "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta"
      },
      "faiLocation": {
        "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta.fai"
      }
    }
  },
  "tracks": []
}
```

## Testing Checklist

- [ ] Anonymous user sees only Public assemblies
- [ ] Logged-in user sees their permitted assemblies
- [ ] Admin sees all assemblies
- [ ] Clicking "View Genome" opens in new window with full path
- [ ] JBrowse2 loads and displays assembly
- [ ] Can view reference sequence
- [ ] Can zoom and pan
- [ ] Annotations load (when tracks are added)

## Configuration Files

- **Main Config**: `/config/moop_config.json`
  - Sets `jbrowse2.FASTA_URI_BASE` and other paths

- **Assembly Definitions**: `/metadata/jbrowse2-configs/*.json`
  - One file per assembly
  - Modular approach allows permission-based filtering

- **API**: `/api/jbrowse2/get-config.php`
  - Builds complete config from assembly definitions
  - Filters by user access level

- **UI Pages**: 
  - `/jbrowse2.php` - Main genome browser page with assembly list
  - `/jbrowse2-view.php` - Fullscreen JBrowse2 viewer
  - `/tools/pages/jbrowse2.php` - UI template for genome browser

- **JavaScript**:
  - `/js/jbrowse2-loader.js` - Loads assemblies and creates UI
  - `/js/jbrowse2-view-loader.js` - Initializes fullscreen JBrowse2

## Next Priority Tasks

1. Fix JBrowse2 initialization in fullscreen view
2. Add annotation tracks to assembly definitions
3. Create bulk assembly loading script in `/tools/jbrowse/`
4. Document permission system for multi-org setup
5. Test with multiple assemblies
