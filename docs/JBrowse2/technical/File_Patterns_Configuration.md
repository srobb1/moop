# JBrowse2 File Patterns Configuration

**Date:** February 12, 2026  
**Purpose:** Define file naming patterns for reference genomes and annotations

---

## Configuration Philosophy

**Single Source of Truth** - File patterns are defined once in `site_config.php` and used throughout the system.

---

## File Pattern Configuration

### 1. Reference Genome Pattern

**Config Location:** `sequence_types['genome']['pattern']`  
**Current Value:** `genome.fa`  
**Purpose:** Pattern for reference genome FASTA files in organism directories

**Location:**
```php
'sequence_types' => [
    // ... other types ...
    'genome' => [
        'pattern' => 'genome.fa',  // ← SINGLE SOURCE OF TRUTH
        'label' => 'GENOME',
        'color' => 'bg-warning text-dark',
    ]
],
```

**Usage:**
- BLAST tool looks for this file
- Sequence retrieval uses this pattern
- JBrowse setup script symlinks this file

---

### 2. Annotation File Pattern

**Config Location:** `annotation_file`  
**Current Value:** `genomic.gff`  
**Purpose:** Pattern for primary annotation GFF files in organism directories

**Location:**
```php
'annotation_file' => 'genomic.gff',  // ← SINGLE SOURCE OF TRUTH
```

**Usage:**
- JBrowse setup script symlinks and compresses this file
- PathResolver uses this for AUTO annotation resolution

---

## Directory Structure

### Source Files (in /organisms/)
```
/organisms/
└── {organism}/
    └── {assembly}/
        ├── genome.fa          ← sequence_types['genome']['pattern']
        ├── genomic.gff        ← annotation_file
        ├── protein.aa.fa      ← sequence_types['protein']['pattern']
        ├── transcript.nt.fa   ← sequence_types['transcript']['pattern']
        └── cds.nt.fa          ← sequence_types['cds']['pattern']
```

### JBrowse Files (in /data/genomes/)
```
/data/genomes/
└── {organism}/
    └── {assembly}/
        ├── reference.fasta        → symlink to ../../organisms/.../genome.fa
        ├── reference.fasta.fai    ← indexed by setup script
        ├── annotations.gff3       → symlink to ../../organisms/.../genomic.gff
        ├── annotations.gff3.gz    ← compressed by setup script
        └── annotations.gff3.gz.tbi ← indexed by setup script
```

---

## File Transformation Flow

### 1. Genome FASTA
```
Source:     /organisms/{organism}/{assembly}/genome.fa
            ↓ (symlink created by setup_jbrowse_assembly.sh)
Symlink:    /data/genomes/{organism}/{assembly}/reference.fasta
            ↓ (indexed by samtools faidx)
Index:      /data/genomes/{organism}/{assembly}/reference.fasta.fai
```

### 2. Annotation GFF
```
Source:     /organisms/{organism}/{assembly}/genomic.gff
            ↓ (symlink created by setup_jbrowse_assembly.sh)
Symlink:    /data/genomes/{organism}/{assembly}/annotations.gff3
            ↓ (compressed by bgzip)
Compressed: /data/genomes/{organism}/{assembly}/annotations.gff3.gz
            ↓ (indexed by tabix)
Index:      /data/genomes/{organism}/{assembly}/annotations.gff3.gz.tbi
```

---

## PathResolver Integration

PathResolver uses these config values for AUTO path resolution:

```php
// AUTO fasta resolution
$genomePattern = $config->get('sequence_types')['genome']['pattern'];
// Source: /organisms/{organism}/{assembly}/{$genomePattern}
// Resolves to: {genomes_directory}/{organism}/{assembly}/reference.fasta

// AUTO gff resolution  
$annotationFile = $config->get('annotation_file');
// Source: /organisms/{organism}/{assembly}/{$annotationFile}
// Resolves to: {genomes_directory}/{organism}/{assembly}/annotations.gff3.gz
```

---

## Setup Script Usage

The `setup_jbrowse_assembly.sh` script uses these patterns:

```bash
# Default values (can be overridden with --genome-file and --gff-file)
GENOME_FILE="genome.fa"      # From sequence_types['genome']['pattern']
GFF_FILE="genomic.gff"       # From annotation_file

# Create symlinks
ln -s /organisms/{org}/{asm}/$GENOME_FILE /data/genomes/{org}/{asm}/reference.fasta
ln -s /organisms/{org}/{asm}/$GFF_FILE /data/genomes/{org}/{asm}/annotations.gff3

# Compress and index
bgzip < annotations.gff3 > annotations.gff3.gz
tabix annotations.gff3.gz
samtools faidx reference.fasta
```

---

## Customization

### To change genome file pattern:
1. Update `site_config.php`:
   ```php
   'genome' => [
       'pattern' => 'assembly.fasta',  // Your custom name
   ]
   ```
2. Rename files in `/organisms/` to match
3. Re-run setup scripts

### To change annotation file pattern:
1. Update `site_config.php`:
   ```php
   'annotation_file' => 'genes.gff3',  // Your custom name
   ```
2. Rename files in `/organisms/` to match
3. Re-run setup scripts

---

## Benefits of This Approach

✅ **No duplication** - Pattern defined once  
✅ **Consistency** - All tools use same patterns  
✅ **Flexibility** - Easy to customize per deployment  
✅ **Maintainability** - Change in one place affects all tools  
✅ **Documentation** - Config serves as documentation  

---

## Related Files

- **Config:** `/data/moop/config/site_config.php`
- **PathResolver:** `/data/moop/lib/JBrowse/PathResolver.php`
- **Setup Script:** `/data/moop/tools/jbrowse/setup_jbrowse_assembly.sh`
- **Bulk Setup:** `/data/moop/tools/jbrowse/bulk_load_assemblies.sh`

---

## Examples

### Access genome pattern in PHP:
```php
$config = ConfigManager::getInstance();
$genomePattern = $config->get('sequence_types')['genome']['pattern'];
// Result: "genome.fa"
```

### Access annotation pattern in PHP:
```php
$config = ConfigManager::getInstance();
$annotationFile = $config->get('annotation_file');
// Result: "genomic.gff"
```

### Check if files exist:
```php
$orgPath = "/organisms/Nematostella_vectensis/GCA_033964005.1";
$genomePath = "$orgPath/" . $config->get('sequence_types')['genome']['pattern'];
$gffPath = "$orgPath/" . $config->get('annotation_file');

if (file_exists($genomePath) && file_exists($gffPath)) {
    echo "Assembly files present\n";
}
```

---

*Last updated: February 12, 2026*
