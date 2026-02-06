# JBrowse2 Assembly Bulk Loading Guide

**Purpose:** Automate the setup and registration of multiple genome assemblies for JBrowse2  
**Target Users:** System administrators, bioinformaticians  
**Time Estimate:** ~5 minutes per assembly + build time  
**Last Updated:** Feb 5, 2026

---

## Overview

The assembly bulk loading process consists of three main phases:

### Phase 1: File Preparation (Per Assembly)
Prepares genome and annotation files for JBrowse2:
- Creates `/data/moop/data/genomes/{organism}/{assembly}/` directory
- Creates symlinks to original genome and GFF files
- Indexes genome with `samtools faidx`
- Compresses and indexes GFF with `bgzip` and `tabix`

**Script:** `tools/jbrowse/setup_jbrowse_assembly.sh`

### Phase 2: JBrowse2 Configuration (Per Assembly)
Registers assembly in JBrowse2 config.json:
- Validates assembly files exist and are complete
- Auto-detects genome alias from organism database
- Registers assembly in JBrowse2 using `jbrowse add-assembly` CLI
- Creates multiple aliases for easy reference

**Script:** `tools/jbrowse/add_assembly_to_jbrowse.sh`

### Phase 3: Build & Deploy (Once)
Builds the JBrowse2 frontend and starts the server:
- Runs `npm run build` (if needed)
- Starts PHP web server for testing
- Verifies functionality

**Script:** `tools/jbrowse/bulk_load_assemblies.sh` (orchestrator)

---

## Quick Start: Load a Single Assembly

### 1. Prepare Files (5-10 minutes)

```bash
cd /data/moop
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1
```

**What this does:**
- ✓ Creates `/data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/`
- ✓ Creates symlinks: `reference.fasta` → genome file
- ✓ Indexes genome: `reference.fasta.fai`
- ✓ Compresses GFF: `annotations.gff3.gz`
- ✓ Indexes GFF: `annotations.gff3.gz.tbi`

**Expected output:**
```
════════════════════════════════════════════════════════════════
    JBrowse2 Assembly Setup
════════════════════════════════════════════════════════════════

ℹ Checking dependencies...
✓ samtools found: samtools 1.x.x
✓ bgzip found
✓ tabix found

ℹ Validating inputs...
✓ Organism: Anoura_caudifer
✓ Assembly: GCA_004027475.1
✓ Genome file found: genome.fa
✓ GFF file found: genomic.gff

ℹ Setting up directories...
✓ Created: /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1

ℹ Creating symlinks...
✓ Created symlink: reference.fasta → genome.fa
✓ Created symlink: annotations.gff3 → genomic.gff

ℹ Indexing genome with samtools...
✓ Created: reference.fasta.fai

ℹ Compressing and indexing GFF...
ℹ Sorting GFF file...
✓ GFF file sorted
✓ Created: annotations.gff3.gz
✓ Created: annotations.gff3.gz.tbi

ℹ Verifying setup...
✓ Found: reference.fasta
✓ Found: reference.fasta.fai
✓ Found: annotations.gff3
✓ Found: annotations.gff3.gz
✓ Found: annotations.gff3.gz.tbi

════════════════════════════════════════════════════════════════
✓ Assembly setup complete!
════════════════════════════════════════════════════════════════
```

### 2. Register in JBrowse2 (2-3 minutes)

```bash
cd /data/moop
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1
```

**What this does:**
- ✓ Validates files exist in `/data/moop/data/genomes/`
- ✓ Auto-detects `genome_name` from `organism.sqlite`
- ✓ Registers assembly in `jbrowse2/config.json`
- ✓ Creates aliases: `Anoura_caudifer`, `GCA_004027475.1`, `ACA1` (from DB)

### 3. Build & Test (5 minutes)

```bash
# Optional: Build JBrowse2 (if source files changed)
cd /data/moop/jbrowse2
npm run build

# Start server
cd /data/moop
php -S 127.0.0.1:8888 &

# Test API
curl -s "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public" | jq .

# Visit in browser
# http://127.0.0.1:8888/jbrowse2/
```

---

## Bulk Loading Multiple Assemblies

### Method 1: Sequential Manual Loading

Load each assembly one by one:

```bash
cd /data/moop

# Assembly 1
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1

# Assembly 2
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Montipora_capitata/HIv3 \
    --genome-file scaffold.fa \
    --gff-file genes.gff
./tools/jbrowse/add_assembly_to_jbrowse.sh Montipora_capitata HIv3

# Assembly 3
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Bradypodion_pumilum/ASM356671v1
./tools/jbrowse/add_assembly_to_jbrowse.sh Bradypodion_pumilum ASM356671v1

# Build once at the end
cd /data/moop/jbrowse2 && npm run build
php -S 127.0.0.1:8888 &
```

### Method 2: Automated Bulk Loading Script

Use the bulk loader script to load multiple assemblies automatically:

```bash
cd /data/moop

# Create a manifest file with all assemblies to load
cat > /tmp/assemblies_to_load.txt << EOF
/organisms/Anoura_caudifer/GCA_004027475.1
/organisms/Montipora_capitata/HIv3
/organisms/Bradypodion_pumilum/ASM356671v1
EOF

# Run bulk loader
./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies_to_load.txt
```

Or with options:

```bash
# With custom genome/GFF filenames for specific assemblies
cat > /tmp/assemblies_with_options.txt << EOF
/organisms/Anoura_caudifer/GCA_004027475.1
/organisms/Montipora_capitata/HIv3 --genome-file scaffold.fa --gff-file genes.gff
/organisms/Bradypodion_pumilum/ASM356671v1 --genome-file genome.fasta
EOF

./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies_with_options.txt --build --test
```

### Method 3: Load from Existing Directory

If you have multiple organisms already set up in `/organisms/`, auto-discover and load them:

```bash
cd /data/moop

# Auto-discover all organisms and load them
./tools/jbrowse/bulk_load_assemblies.sh --auto-discover --organisms /organisms --build --test
```

---

## Advanced Usage

### Load with Custom Display Names

```bash
cd /data/moop

# Prepare files
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1

# Register with custom display name
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1 \
    --display-name "Anoura caudifer (Tailed Tailless Bat)"
```

### Load with Custom Aliases

Override auto-detection and use explicit aliases:

```bash
cd /data/moop

./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Montipora_capitata/HIv3

# Register with custom aliases
./tools/jbrowse/add_assembly_to_jbrowse.sh Montipora_capitata HIv3 \
    --alias "M_capitata" \
    --alias "coral_v3" \
    --alias "Mcap"
```

### Load with Non-Standard File Names

```bash
cd /data/moop

# If genome file is "scaffold.fa" instead of "genome.fa"
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Montipora_capitata/HIv3 \
    --genome-file scaffold.fa \
    --gff-file genes.gff \
    --display-name "Montipora capitata (v3)"

./tools/jbrowse/add_assembly_to_jbrowse.sh Montipora_capitata HIv3
```

---

## File Layout Reference

After loading an assembly, you'll have:

```
/data/moop/
├── data/
│   └── genomes/
│       └── {ORGANISM}/
│           └── {ASSEMBLY_ID}/
│               ├── reference.fasta          (symlink to original genome)
│               ├── reference.fasta.fai      (FASTA index)
│               ├── annotations.gff3         (symlink to original GFF)
│               ├── annotations.gff3.gz      (compressed GFF)
│               └── annotations.gff3.gz.tbi  (GFF index)
│
├── jbrowse2/
│   ├── config.json                    (assembly definitions)
│   ├── package.json
│   ├── package-lock.json
│   └── static/
│
├── organisms/
│   └── {ORGANISM}/
│       ├── {ASSEMBLY_ID}/
│       │   ├── genome.fa              (original files)
│       │   ├── genomic.gff            (original files)
│       │   └── organism.sqlite        (metadata database)
│       └── organism.sqlite            (shared organism database)
│
└── tools/jbrowse/
    ├── setup_jbrowse_assembly.sh      (Phase 1 - file preparation)
    ├── add_assembly_to_jbrowse.sh     (Phase 2 - JBrowse2 registration)
    └── bulk_load_assemblies.sh        (Phase 3 - orchestrator/bulk loader)
```

---

## Script Reference

### setup_jbrowse_assembly.sh

Prepares genome files for JBrowse2. Run this first for each new assembly.

**Usage:**
```bash
./tools/jbrowse/setup_jbrowse_assembly.sh <organism_path> [OPTIONS]
```

**Options:**
- `--genome-file FILE` - Genome filename (default: genome.fa)
- `--gff-file FILE` - GFF filename (default: genomic.gff)
- `--display-name NAME` - Display name (optional)
- `--help` - Show help

**Example:**
```bash
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/MyOrg/MyAssembly \
    --genome-file scaffold.fa \
    --gff-file genes.gff
```

### add_assembly_to_jbrowse.sh

Registers a prepared assembly in JBrowse2 config. Run this after Phase 1.

**Usage:**
```bash
./tools/jbrowse/add_assembly_to_jbrowse.sh <organism> <assembly_id> [OPTIONS]
```

**Options:**
- `--display-name NAME` - Override display name
- `--alias NAME` - Add alias (can be used multiple times, overrides auto-detection)
- `--help` - Show help

**Example:**
```bash
./tools/jbrowse/add_assembly_to_jbrowse.sh MyOrg MyAssembly \
    --display-name "My Organism (v1)" \
    --alias "MyOrg_v1"
```

**Auto-detection:**
- Queries `/organisms/{organism}/organism.sqlite` for `genome_name`
- Uses `assembly_id` as fallback alias if database not available
- Creates aliases automatically for easy reference

### bulk_load_assemblies.sh

Orchestrates bulk loading of multiple assemblies. Can run all three phases.

**Usage:**
```bash
./tools/jbrowse/bulk_load_assemblies.sh <manifest_file> [OPTIONS]
./tools/jbrowse/bulk_load_assemblies.sh --auto-discover [OPTIONS]
```

**Options:**
- `--auto-discover` - Auto-discover organisms in /organisms/
- `--organisms PATH` - Path to organisms directory (default: /organisms)
- `--build` - Run npm build after loading all assemblies
- `--test` - Run API tests after build
- `--log FILE` - Log file (default: /tmp/jbrowse2_bulk_load_<timestamp>.log)
- `--help` - Show help

**Manifest File Format:**
```
# Comments start with #
/organisms/Organism1/Assembly1
/organisms/Organism2/Assembly2 --genome-file custom.fa --gff-file genes.gff
/organisms/Organism3/Assembly3
```

**Examples:**
```bash
# Load from manifest
./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt

# Load and build
./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt --build

# Load, build, and test
./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt --build --test

# Auto-discover and load all
./tools/jbrowse/bulk_load_assemblies.sh --auto-discover --organisms /organisms --build
```

---

## Troubleshooting

### Problem: "samtools not found"

**Solution:** Install samtools
```bash
sudo apt-get install samtools
```

### Problem: "tabix not found"

**Solution:** Install htslib tools
```bash
sudo apt-get install tabix
```

### Problem: "jbrowse CLI not found"

**Solution:** Install JBrowse CLI globally
```bash
npm install -g @jbrowse/cli
```

### Problem: "GFF file not found: genomic.gff"

**Solution:** Specify custom filename
```bash
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/MyOrganism/MyAssembly \
    --gff-file my_custom_genes.gff
```

### Problem: Assembly doesn't appear in JBrowse2

**Steps:**
1. Verify files were created:
   ```bash
   ls -lh /data/moop/data/genomes/{organism}/{assembly}/
   ```

2. Verify JBrowse2 config is valid:
   ```bash
   jq . /data/moop/jbrowse2/config.json
   ```

3. Check for errors in config:
   ```bash
   jq '.assemblies' /data/moop/jbrowse2/config.json | head -50
   ```

4. Rebuild JBrowse2:
   ```bash
   cd /data/moop/jbrowse2
   npm run build
   ```

5. Restart server:
   ```bash
   pkill -f "php -S"
   php -S 127.0.0.1:8888 &
   ```

### Problem: "organism.sqlite not found" warning

**Note:** This is optional. The script will:
- Warn if unable to auto-detect `genome_name`
- Fall back to using `assembly_id` as an alias
- Still work correctly, but with fewer aliases

**Solution (optional):** If you have an organism.sqlite database, place it at:
```bash
/data/moop/organisms/{ORGANISM}/organism.sqlite
```

It should have a `genome` table with a `genome_name` column.

### Problem: Manifest file not found

**Solution:** Create the manifest file with proper paths:
```bash
cat > /tmp/assemblies.txt << EOF
/organisms/Organism1/Assembly1
/organisms/Organism2/Assembly2
EOF

./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt
```

---

## Performance Notes

- **Phase 1 (File preparation):** 1-5 minutes per assembly
  - Depends on genome size
  - Uses single-threaded samtools
  
- **Phase 2 (JBrowse2 registration):** 10-30 seconds per assembly
  - Very fast, just updating JSON config
  
- **Phase 3 (Build):** 2-10 minutes (runs once, not per-assembly)
  - Depends on system resources
  - Can be skipped if source files didn't change

**Total for 10 assemblies:** ~30-60 minutes including build

---

## Scripting for Production

### Example: Automated pipeline with logging

```bash
#!/bin/bash

MOOP_ROOT="/data/moop"
ORGANISMS_DIR="/organisms"
LOG_FILE="/var/log/jbrowse2_bulk_load.log"

echo "Starting bulk assembly load at $(date)" | tee -a "$LOG_FILE"

# Find all assemblies and load them
for organism_path in $ORGANISMS_DIR/*/*/; do
    if [ ! -f "$organism_path/organism.sqlite" ]; then
        continue
    fi
    
    organism=$(basename $(dirname "$organism_path"))
    assembly=$(basename "$organism_path")
    
    echo "Loading: $organism / $assembly" | tee -a "$LOG_FILE"
    
    # Phase 1: Prepare files
    if $MOOP_ROOT/tools/jbrowse/setup_jbrowse_assembly.sh "$organism_path" >> "$LOG_FILE" 2>&1; then
        echo "  ✓ Files prepared" | tee -a "$LOG_FILE"
    else
        echo "  ✗ File preparation failed" | tee -a "$LOG_FILE"
        continue
    fi
    
    # Phase 2: Register in JBrowse2
    if $MOOP_ROOT/tools/jbrowse/add_assembly_to_jbrowse.sh "$organism" "$assembly" >> "$LOG_FILE" 2>&1; then
        echo "  ✓ Registered in JBrowse2" | tee -a "$LOG_FILE"
    else
        echo "  ✗ JBrowse2 registration failed" | tee -a "$LOG_FILE"
    fi
done

# Phase 3: Build once at the end
echo "Building JBrowse2..." | tee -a "$LOG_FILE"
cd "$MOOP_ROOT/jbrowse2"
if npm run build >> "$LOG_FILE" 2>&1; then
    echo "Build complete" | tee -a "$LOG_FILE"
else
    echo "Build failed!" | tee -a "$LOG_FILE"
fi

echo "Bulk load complete at $(date)" | tee -a "$LOG_FILE"
```

---

## Summary Checklist

- [ ] All organisms are in `/organisms/{organism}/{assembly}/`
- [ ] Each assembly has `genome.fa` (or custom filename) and `genomic.gff`
- [ ] Required tools are installed: `samtools`, `tabix`, `jbrowse` CLI
- [ ] Run `setup_jbrowse_assembly.sh` for each assembly
- [ ] Run `add_assembly_to_jbrowse.sh` for each assembly
- [ ] Run `npm run build` in `/data/moop/jbrowse2/`
- [ ] Start server: `php -S 127.0.0.1:8888 &`
- [ ] Test: `curl http://127.0.0.1:8888/api/jbrowse2/test-assembly.php`
- [ ] View in browser: `http://127.0.0.1:8888/jbrowse2/`

---

## Related Documentation

- `NEXT_STEPS_PLAN.md` - Initial assembly setup plan
- `jbrowse2_SETUP_COMPLETE.md` - Setup status and what's been done
- `jbrowse2_GENOME_SETUP.md` - Detailed genome setup guide
- `HANDOFF_NEW_MACHINE.md` - Setup for new machines

---

**Status:** ✅ Production Ready  
**Last Updated:** Feb 5, 2026  
**Version:** 1.0
