# PathResolver Configuration Requirements

**Component:** lib/JBrowse/PathResolver.php  
**Purpose:** Define required directory structure for JBrowse track management

---

## Required Configuration Keys

PathResolver requires the following keys to be defined in `config/site_config.php`:

### 1. jbrowse2.genomes_directory
**Type:** String (absolute path)  
**Purpose:** Base directory for reference genomes and annotations  
**Current Value:** `$site_path/data/genomes/`  

**Structure:**
```
{genomes_directory}/
├── {organism_1}/
│   ├── {assembly_1}/
│   │   ├── reference.fasta
│   │   ├── reference.fasta.fai
│   │   └── annotations.gff3.gz
│   └── {assembly_2}/
│       └── ...
└── {organism_2}/
    └── ...
```

**Example:**
```
/var/www/html/moop/data/genomes/
├── Nematostella_vectensis/
│   └── GCA_033964005.1/
│       ├── reference.fasta
│       ├── reference.fasta.fai
│       └── annotations.gff3.gz
└── Anoura_caudifer/
    └── GCA_004027475.1/
        └── ...
```

---

### 2. jbrowse2.tracks_directory
**Type:** String (absolute path)  
**Purpose:** Base directory for track data files (BigWig, BAM, VCF, etc.)  
**Current Value:** `$site_path/data/tracks/`  

**Structure:**
```
{tracks_directory}/
├── {organism}/
│   └── {assembly}/
│       ├── bigwig/
│       │   ├── sample1.pos.bw
│       │   └── sample1.neg.bw
│       ├── bam/
│       │   ├── sample1.bam
│       │   └── sample1.bam.bai
│       ├── vcf/
│       ├── gff/
│       └── ...
```

**Example:**
```
/var/www/html/moop/data/tracks/
├── Nematostella_vectensis/
│   └── GCA_033964005.1/
│       ├── bigwig/
│       │   ├── MOLNG-2707_S1-body-wall.pos.bw
│       │   └── MOLNG-2707_S1-body-wall.neg.bw
│       └── bam/
│           ├── MOLNG-2707_S3-body-wall.bam
│           └── MOLNG-2707_S3-body-wall.bam.bai
```

---

### 3. metadata_path
**Type:** String (absolute path)  
**Purpose:** Base directory for JBrowse2 track metadata (JSON files)  
**Current Value:** `$site_path/metadata`  

**Structure:**
```
{metadata_path}/
└── jbrowse2-configs/
    └── tracks/
        └── {organism}/
            └── {assembly}/
                ├── bigwig/
                │   └── track_id.json
                ├── bam/
                │   └── track_id.json
                ├── combo/
                │   └── combo_track_id.json
                └── ...
```

**Example:**
```
/var/www/html/moop/metadata/
└── jbrowse2-configs/
    └── tracks/
        └── Nematostella_vectensis/
            └── GCA_033964005.1/
                ├── bigwig/
                │   └── MOLNG-2707_S1-body-wall.pos.bw.json
                ├── bam/
                │   └── MOLNG-2707_S3-body-wall.bam.json
                └── combo/
                    └── simr:four_adult_tissues_molng-2707.json
```

---

## Automatic Directory Creation

**PathResolver automatically creates directories as needed:**

### When directories are created:
1. **getTrackDirectory()** - Creates: `{tracks_directory}/{organism}/{assembly}/{track_type}/`
2. **getMetadataDirectory()** - Creates: `{metadata_path}/jbrowse2-configs/tracks/{organism}/{assembly}/{track_type}/`

### Permissions:
- Directories created with: `0775` (rwxrwxr-x)
- Owner: Current PHP process user
- Group: Inherited from parent directory

### Error Handling:
- If directory creation fails, throws `RuntimeException`
- Includes full path in error message for debugging

---

## Configuration Validation

### At Runtime:
PathResolver validates configuration when methods are called:

```php
// If jbrowse2.genomes_directory not set:
throw new RuntimeException(
    "Configuration error: jbrowse2.genomes_directory not defined in site_config.php"
);

// If jbrowse2.tracks_directory not set:
throw new RuntimeException(
    "Configuration error: jbrowse2.tracks_directory not defined in site_config.php"
);
```

### Test Configuration:
```bash
# Test that all required paths are configured
php -r "
require_once '/data/moop/includes/config_init.php';
require_once '/data/moop/lib/JBrowse/PathResolver.php';
\$config = ConfigManager::getInstance();
\$resolver = new PathResolver(\$config);
echo 'Configuration OK\n';
"
```

---

## Base Directory Requirements

### Must Exist Before Installation:
These base directories **must exist** before PathResolver can create subdirectories:

1. `$site_path/data/` - Created during MOOP installation
2. `$site_path/metadata/` - Created during MOOP installation

### Auto-Created by PathResolver:
These subdirectories are **automatically created** by PathResolver as needed:

1. `{tracks_directory}/{organism}/{assembly}/{track_type}/`
2. `{metadata_path}/jbrowse2-configs/tracks/{organism}/{assembly}/{track_type}/`

---

## Migration from Flat Structure

### Old Structure (Deprecated):
```
/data/moop/data/tracks/bigwig/file.bw
```

### New Structure (Current):
```
/data/moop/data/tracks/Organism/Assembly/bigwig/file.bw
```

**PathResolver handles both:**
- Resolves old flat paths correctly
- Creates new hierarchical structure for new tracks

---

## Deployment Checklist

### For New Deployments:

1. ✅ Ensure `$site_path/data/` exists
2. ✅ Ensure `$site_path/metadata/` exists
3. ✅ Verify `site_config.php` has all required keys
4. ✅ Test PathResolver with:
   ```bash
   php /tmp/test_directory_creation.php
   ```
5. ✅ PathResolver will create subdirectories automatically

### For Existing Deployments:

1. ✅ Check existing directory structure matches
2. ✅ Update `site_config.php` if paths differ
3. ✅ Run migration script if needed (flat → hierarchical)
4. ✅ Test with sample organism/assembly

---

## Troubleshooting

### Error: "jbrowse2.genomes_directory not defined"
**Solution:** Add to `config/site_config.php`:
```php
'jbrowse2' => [
    'genomes_directory' => "$site_path/data/genomes/",
    // ...
],
```

### Error: "Failed to create directory"
**Possible Causes:**
1. Parent directory doesn't exist
2. Insufficient permissions
3. Disk space full

**Solution:**
```bash
# Check parent directory exists
ls -ld /var/www/html/moop/data/

# Check permissions
ls -ld /var/www/html/moop/data/tracks/

# Check disk space
df -h /var/www/html/moop/
```

### Error: "Cannot determine web URI"
**Cause:** Site directory name not found in path  
**Solution:** Verify path contains site name:
```php
// Correct:
/data/moop/data/tracks/...  (contains 'moop')
/var/www/html/moop/data/... (contains 'moop')

// Incorrect:
/var/www/html/data/tracks/... (missing 'moop')
```

---

## Examples

### Get track directory for BigWig:
```php
$resolver = new PathResolver($config);
$dir = $resolver->getTrackDirectory('Nematostella_vectensis', 'GCA_033964005.1', 'bigwig');
// Result: /var/www/html/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/
// Directory created if it doesn't exist
```

### Get metadata directory for combo track:
```php
$dir = $resolver->getMetadataDirectory('Organism', 'Assembly', 'combo');
// Result: /var/www/html/moop/metadata/jbrowse2-configs/tracks/Organism/Assembly/combo/
// Directory created if it doesn't exist
```

### Convert filesystem path to web URI:
```php
$uri = $resolver->toWebUri('/var/www/html/moop/data/tracks/Organism/Assembly/bigwig/file.bw');
// Result: /moop/data/tracks/Organism/Assembly/bigwig/file.bw
```

---

## Summary

✅ **No hardcoded paths** - All paths from config  
✅ **Automatic directory creation** - Creates structure as needed  
✅ **Clear error messages** - Config errors caught early  
✅ **Portable** - Works on any deployment  
✅ **Hierarchical** - Organized by organism/assembly/type  

**User Action Required:** None - directories created automatically  
**Admin Action Required:** Ensure base directories exist during installation
