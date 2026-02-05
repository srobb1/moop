# JBrowse2 Assembly Setup Tools

This directory contains scripts for automating the setup and bulk loading of genome assemblies into JBrowse2.

## Quick Start

Load a single assembly:
```bash
cd /data/moop

# Phase 1: Prepare files
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1

# Phase 2: Register in JBrowse2
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1

# Phase 3: Build and test
cd jbrowse2 && npm run build
cd .. && php -S 127.0.0.1:8888 &
```

Load multiple assemblies automatically:
```bash
cd /data/moop

# Auto-discover and load all organisms
./tools/jbrowse/bulk_load_assemblies.sh --auto-discover --organisms /organisms --build
```

## Scripts

### 1. setup_jbrowse_assembly.sh

**Purpose:** Prepare genome files for JBrowse2  
**Phase:** 1 (File preparation)  
**When to use:** First, for each new assembly

**What it does:**
- Creates `/data/moop/data/genomes/{organism}/{assembly}/` directory
- Creates symlinks to genome FASTA and GFF files
- Indexes genome with `samtools faidx`
- Sorts, compresses, and indexes GFF with `bgzip` and `tabix`
- Verifies all files created successfully

**Usage:**
```bash
./tools/jbrowse/setup_jbrowse_assembly.sh <organism_path> [OPTIONS]

# Examples:
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1

./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Montipora_capitata/HIv3 \
    --genome-file scaffold.fa \
    --gff-file genes.gff
```

**Options:**
- `--genome-file FILE` - Genome filename (default: genome.fa)
- `--gff-file FILE` - GFF filename (default: genomic.gff)
- `--display-name NAME` - Display name (optional)
- `--help` - Show help message

**Time:** 1-5 minutes per assembly (depends on genome size)

---

### 2. add_assembly_to_jbrowse.sh

**Purpose:** Register assembly in JBrowse2 configuration  
**Phase:** 2 (JBrowse2 registration)  
**When to use:** After setup_jbrowse_assembly.sh, for each assembly

**What it does:**
- Validates assembly files exist and are complete
- Auto-detects `genome_name` from organism.sqlite
- Registers assembly in `jbrowse2/config.json` using `jbrowse CLI`
- Creates multiple aliases for easy reference

**Usage:**
```bash
./tools/jbrowse/add_assembly_to_jbrowse.sh <organism> <assembly_id> [OPTIONS]

# Examples:
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1

./tools/jbrowse/add_assembly_to_jbrowse.sh Montipora_capitata HIv3 \
    --display-name "Montipora capitata (v3)"
```

**Options:**
- `--display-name NAME` - Custom display name
- `--alias NAME` - Add alias (can be used multiple times)
- `--help` - Show help message

**Time:** 10-30 seconds per assembly

**Auto-detection:**
- Queries `/organizations/{organism}/organism.sqlite`
- Extracts `genome_name` from `genome` table
- Uses as default alias if available
- Falls back to `assembly_id` if database not found

---

### 3. bulk_load_assemblies.sh

**Purpose:** Orchestrate bulk loading of multiple assemblies  
**Phase:** 1, 2, and optionally 3 (orchestrator)  
**When to use:** Loading multiple assemblies at once

**What it does:**
- Reads manifest file or auto-discovers organisms
- Runs Phase 1 (setup_jbrowse_assembly.sh) for each assembly
- Runs Phase 2 (add_assembly_to_jbrowse.sh) for each assembly
- Optionally runs Phase 3 (npm build) once at the end
- Optionally runs API tests on all loaded assemblies
- Logs everything to a timestamped log file

**Usage:**
```bash
# Method 1: Load from manifest file
./tools/jbrowse/bulk_load_assemblies.sh <manifest_file> [OPTIONS]

# Method 2: Auto-discover organisms
./tools/jbrowse/bulk_load_assemblies.sh --auto-discover [OPTIONS]

# Examples:
./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt

./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt --build --test

./tools/jbrowse/bulk_load_assemblies.sh --auto-discover --organisms /organisms --build --test
```

**Manifest File Format:**
```
# Comments start with #
/organisms/Organism1/Assembly1
/organisms/Organism2/Assembly2 --genome-file custom.fa --gff-file genes.gff
/organisms/Organism3/Assembly3 --display-name "Custom Name"
```

**Options:**
- `--auto-discover` - Auto-discover organisms (don't need manifest)
- `--organisms PATH` - Path to organisms directory (default: /organisms)
- `--build` - Run npm build after loading all assemblies
- `--test` - Run API tests after build
- `--log FILE` - Log file (default: /tmp/jbrowse2_bulk_load_<timestamp>.log)
- `--help` - Show help message

**Time:** 5 min/assembly + 5 min build + 1-2 min test

---

## Workflow

### Single Assembly
```
User
  ↓
setup_jbrowse_assembly.sh
  ↓ (files prepared)
add_assembly_to_jbrowse.sh
  ↓ (registered)
npm run build
  ↓
php -S 127.0.0.1:8888
  ↓
✓ Ready in browser
```

### Multiple Assemblies
```
User
  ↓
bulk_load_assemblies.sh
  ├─→ setup_jbrowse_assembly.sh (Assembly 1)
  ├─→ add_assembly_to_jbrowse.sh (Assembly 1)
  ├─→ setup_jbrowse_assembly.sh (Assembly 2)
  ├─→ add_assembly_to_jbrowse.sh (Assembly 2)
  ├─→ setup_jbrowse_assembly.sh (Assembly 3)
  ├─→ add_assembly_to_jbrowse.sh (Assembly 3)
  ├─→ npm run build (once)
  └─→ API tests (optional)
  ↓
✓ All ready in browser
```

## File Layout

```
/data/moop/
├── tools/jbrowse/
│   ├── setup_jbrowse_assembly.sh      (Phase 1 - file preparation)
│   ├── add_assembly_to_jbrowse.sh     (Phase 2 - registration)
│   ├── bulk_load_assemblies.sh        (Phase 3 - orchestrator)
│   └── README.md                       (this file)
│
├── data/genomes/
│   └── {ORGANISM}/{ASSEMBLY}/
│       ├── reference.fasta            (symlink to genome)
│       ├── reference.fasta.fai        (FASTA index)
│       ├── annotations.gff3           (symlink to GFF)
│       ├── annotations.gff3.gz        (compressed GFF)
│       └── annotations.gff3.gz.tbi    (GFF index)
│
├── jbrowse2/
│   ├── config.json                    (assembly definitions)
│   ├── package.json
│   └── static/
│
└── organisms/
    └── {ORGANISM}/
        ├── {ASSEMBLY}/
        │   ├── genome.fa              (original genome)
        │   ├── genomic.gff            (original GFF)
        │   └── organism.sqlite        (metadata)
        └── organism.sqlite            (shared metadata)
```

## Requirements

### System Tools
- `samtools` - Genome indexing
- `bgzip` - GFF compression
- `tabix` - GFF indexing
- `jbrowse` CLI - JBrowse2 configuration
- `curl` - API testing (optional)
- `jq` - JSON validation (optional)
- `sqlite3` - Genome name auto-detection (optional)
- `npm` - Building JBrowse2 (optional)

### Install Dependencies
```bash
# Ubuntu/Debian
sudo apt-get install samtools tabix jq sqlite3

# JBrowse CLI
npm install -g @jbrowse/cli

# npm (if not installed)
sudo apt-get install npm
```

## Troubleshooting

### Error: "samtools not found"
```bash
sudo apt-get install samtools
```

### Error: "jbrowse CLI not found"
```bash
npm install -g @jbrowse/cli
```

### Error: Assembly doesn't appear in JBrowse2
1. Check files exist: `ls /data/moop/data/genomes/{organism}/{assembly}/`
2. Validate JSON: `jq . /data/moop/jbrowse2/config.json`
3. Rebuild: `cd /data/moop/jbrowse2 && npm run build`
4. Restart server: `pkill -f "php -S" && php -S 127.0.0.1:8888 &`

### Error: "organism.sqlite not found" (warning)
This is optional. Scripts will still work but with fewer aliases.

To fix (optional):
```bash
# Place organism.sqlite at the expected location
cp /path/to/organism.sqlite /data/moop/organisms/{ORGANISM}/
```

## Documentation

For complete documentation, see:
- `docs/JBrowse2/ASSEMBLY_BULK_LOAD_GUIDE.md` - Complete bulk loading guide
- `docs/JBrowse2/NEXT_STEPS_PLAN.md` - Initial assembly setup plan
- `docs/JBrowse2/jbrowse2_GENOME_SETUP.md` - Detailed genome setup
- `docs/JBrowse2/jbrowse2_SETUP_COMPLETE.md` - Setup status

## Examples

### Load a single assembly
```bash
cd /data/moop

./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1

cd jbrowse2 && npm run build
cd .. && php -S 127.0.0.1:8888 &
```

### Load multiple assemblies from manifest
```bash
cd /data/moop

cat > /tmp/assemblies.txt << EOF
/organisms/Anoura_caudifer/GCA_004027475.1
/organisms/Montipora_capitata/HIv3
/organisms/Bradypodion_pumilum/ASM356671v1
EOF

./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt --build --test
```

### Auto-discover and load all organisms
```bash
cd /data/moop

./tools/jbrowse/bulk_load_assemblies.sh --auto-discover --organisms /organisms --build --test
```

### Load with custom options
```bash
cd /data/moop

cat > /tmp/assemblies.txt << EOF
/organisms/MyOrg/MyAssembly1
/organisms/MyOrg/MyAssembly2 --genome-file scaffold.fa --gff-file genes.gff
EOF

./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt --build
```

## Performance

- **Phase 1 (setup):** 1-5 min per assembly (depends on genome size)
- **Phase 2 (registration):** 10-30 sec per assembly
- **Phase 3 (build):** 2-10 min (runs once)
- **API tests:** 1-2 min for all assemblies

**Total for 10 assemblies:** ~30-60 minutes

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the comprehensive guide: `docs/JBrowse2/ASSEMBLY_BULK_LOAD_GUIDE.md`
3. Check log files: `/tmp/jbrowse2_bulk_load_*.log`

---

**Status:** ✅ Production Ready  
**Last Updated:** Feb 5, 2026  
**Version:** 1.0
