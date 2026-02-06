# JBrowse2 Administrator Guide

**Audience:** System Administrators, Bioinformaticians  
**Purpose:** Setup, maintenance, and troubleshooting

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Adding Assemblies](#adding-assemblies)
3. [Managing Access Control](#managing-access-control)
4. [Track Configuration](#track-configuration)
5. [Bulk Loading](#bulk-loading)
6. [Maintenance Tasks](#maintenance-tasks)
7. [Troubleshooting](#troubleshooting)
8. [Remote Tracks Server](#remote-tracks-server)

---

## Prerequisites

### Required Tools

Install these tools before setting up assemblies:

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y samtools tabix jq sqlite3

# Verify installations
samtools --version    # Should be 1.x or higher
bgzip --version       # Part of tabix package
tabix --version
jq --version
sqlite3 --version
```

### File Structure Requirements

Organism directories should follow this structure:

```
/organisms/
└── {ORGANISM_NAME}/
    ├── {ASSEMBLY_ID}/
    │   ├── genome.fa          # Or custom name
    │   └── genomic.gff        # Or custom name
    └── organism.sqlite        # Optional, for metadata
```

**Example:**
```
/organisms/
└── Anoura_caudifer/
    ├── GCA_004027475.1/
    │   ├── genome.fa
    │   └── genomic.gff
    └── organism.sqlite
```

---

## Adding Assemblies

### Single Assembly Setup

Complete process to add one assembly:

#### Step 1: Prepare Files (5-10 minutes)

```bash
cd /data/moop

# Basic usage (assumes genome.fa and genomic.gff)
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1

# With custom filenames
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/MyOrg/MyAssembly \
    --genome-file scaffold.fa \
    --gff-file genes.gff
```

**What this does:**
- Creates `/data/moop/data/genomes/{organism}/{assembly}/`
- Creates symlinks to original files
- Indexes FASTA with samtools (`.fai` file)
- Compresses GFF with bgzip (`.gz` file)
- Indexes GFF with tabix (`.tbi` file)

**Time:** 1-10 minutes depending on genome size

#### Step 2: Register Assembly (30 seconds)

```bash
cd /data/moop

# Basic usage (auto-detects aliases from organism.sqlite)
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1

# With custom display name
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1 \
    --display-name "Anoura caudifer (Tailless Bat)"

# With explicit aliases
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1 \
    --alias "ACA1" \
    --alias "Anoura_genome_v1"

# With custom access level
./tools/jbrowse/add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1 \
    --access-level "Collaborator"  # or "Public" or "ALL"
```

**What this does:**
- Validates files exist and are complete
- Auto-detects `genome_name` from organism.sqlite
- Creates `/metadata/jbrowse2-configs/assemblies/{organism}_{assembly}.json`
- Assembly is immediately available to users with appropriate access

**Time:** 10-30 seconds

#### Step 3: Verify Setup

```bash
# Check metadata file was created
ls -la /data/moop/metadata/jbrowse2-configs/assemblies/Anoura_caudifer_GCA_004027475.1.json

# Validate JSON
jq . /data/moop/metadata/jbrowse2-configs/assemblies/Anoura_caudifer_GCA_004027475.1.json

# Test API endpoint
curl -s "http://localhost:8888/api/jbrowse2/get-config.php" | jq '.assemblies[] | select(.name=="Anoura_caudifer_GCA_004027475.1")'
```

---

## Managing Access Control

### Assembly Access Levels

Set when registering assembly or edit metadata file later:

```json
{
  "defaultAccessLevel": "Public"  // Options: "Public", "Collaborator", "ALL"
}
```

| Level | Who Can Access |
|-------|----------------|
| `Public` | Everyone (including anonymous users) |
| `Collaborator` | Logged-in users with Collaborator role |
| `ALL` | Administrators only |

### Changing Access Level

Edit the assembly metadata file:

```bash
# Edit metadata
nano /data/moop/metadata/jbrowse2-configs/assemblies/Anoura_caudifer_GCA_004027475.1.json

# Change this line:
"defaultAccessLevel": "Collaborator"  # Was "Public"

# Validate JSON
jq . /data/moop/metadata/jbrowse2-configs/assemblies/Anoura_caudifer_GCA_004027475.1.json

# Changes take effect immediately (no restart needed)
```

### User Access Levels

Set in user session (handled by MOOP authentication):

```php
// In your login system
$_SESSION['access_level'] = 'Collaborator';  // or 'Public' or 'ALL'
$_SESSION['is_admin'] = true;  // Forces 'ALL' access
```

---

## Track Configuration

### Track Metadata Files

Create track definition files in `/metadata/jbrowse2-configs/tracks/`:

```bash
cd /data/moop/metadata/jbrowse2-configs/tracks

# Create track definition
cat > rna_seq_coverage.json << 'EOF'
{
  "name": "RNA-seq Coverage",
  "description": "RNA-seq expression coverage across replicates",
  "type": "quantitative",
  "track_id": "rna_seq_coverage",
  "access_levels": ["Public", "Collaborator", "ALL"],
  "groups": ["Transcriptomics"],
  "file_template": "{organism}_{assembly}_rna_coverage.bw",
  "format": "bigwig",
  "color": "#1f77b4",
  "display": {
    "type": "LinearWiggleDisplay",
    "displayCrossHatches": true
  }
}
EOF
```

### Track Data Files

Place track data files in `/data/moop/data/tracks/`:

```bash
# Naming convention: {organism}_{assembly}_{track_id}.{format}
/data/moop/data/tracks/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw
/data/moop/data/tracks/Anoura_caudifer_GCA_004027475.1_dna_alignment.bam
/data/moop/data/tracks/Anoura_caudifer_GCA_004027475.1_dna_alignment.bam.bai
```

### Supported Track Formats

| Format | Extension | Use Case |
|--------|-----------|----------|
| BigWig | `.bw` | Coverage data (RNA-seq, ChIP-seq) |
| BAM | `.bam` + `.bai` | Sequence alignments |
| VCF | `.vcf.gz` + `.tbi` | Variants (SNPs, indels) |
| GFF3 | `.gff3.gz` + `.tbi` | Gene annotations |

---

## Bulk Loading

### Bulk Load Multiple Assemblies

Use the orchestrator script to load many assemblies at once.

#### Method 1: From Manifest File

Create a manifest listing all assemblies to load:

```bash
# Create manifest
cat > /tmp/assemblies.txt << 'EOF'
# One path per line, optional parameters after path
/organisms/Anoura_caudifer/GCA_004027475.1
/organisms/Montipora_capitata/HIv3 --genome-file scaffold.fa
/organisms/Bradypodion_pumilum/ASM356671v1 --display-name "Bradypodion pumilum"
EOF

# Load all assemblies
cd /data/moop
./tools/jbrowse/bulk_load_assemblies.sh /tmp/assemblies.txt
```

#### Method 2: Auto-Discovery

Automatically discover all organisms in `/organisms/`:

```bash
cd /data/moop

# Auto-discover and load all
./tools/jbrowse/bulk_load_assemblies.sh --auto-discover --organisms /organisms

# With options
./tools/jbrowse/bulk_load_assemblies.sh \
    --auto-discover \
    --organisms /organisms \
    --log /tmp/jbrowse_load.log
```

#### Monitoring Progress

```bash
# Watch log file in real-time
tail -f /tmp/jbrowse2_bulk_load_*.log

# Check status
grep "✓" /tmp/jbrowse2_bulk_load_*.log | wc -l  # Count successful
grep "✗" /tmp/jbrowse2_bulk_load_*.log | wc -l  # Count failures
```

### Performance Estimates

| Assemblies | File Prep | Registration | Total |
|------------|-----------|--------------|-------|
| 1 | 1-10 min | 30 sec | 2-11 min |
| 10 | 10-100 min | 5 min | 15-105 min |
| 50 | 50-500 min | 25 min | 75-525 min |

**Note:** Time varies greatly with genome size.

---

## Maintenance Tasks

### Removing an Assembly

```bash
cd /data/moop

# Remove metadata (archive with timestamp)
TIMESTAMP=$(date +%s)
mv /data/moop/metadata/jbrowse2-configs/assemblies/Organism_Assembly.json \
   /data/moop/metadata/jbrowse2-configs/assemblies/Organism_Assembly.json.removed.$TIMESTAMP

# Remove genome files (symlinks only, preserves originals)
rm -rf /data/moop/data/genomes/Organism/Assembly

# Assembly is immediately hidden from users
```

### Updating an Assembly

If genome files are updated, re-run Phase 1:

```bash
cd /data/moop

# Re-process files (overwrites indexes)
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Organism/Assembly

# Metadata unchanged, no need to re-register
```

### Backup Strategy

#### Critical Files to Backup

```bash
# 1. Assembly metadata
tar -czf jbrowse_metadata_$(date +%Y%m%d).tar.gz \
    /data/moop/metadata/jbrowse2-configs/

# 2. JWT keys
tar -czf jwt_keys_$(date +%Y%m%d).tar.gz \
    /data/moop/certs/jwt_*.pem

# 3. Track data (if not backed up elsewhere)
tar -czf tracks_$(date +%Y%m%d).tar.gz \
    /data/moop/data/tracks/
```

#### What NOT to Backup

- `/data/moop/data/genomes/` - These are symlinks, backup originals instead
- `/data/moop/jbrowse2/node_modules/` - Can be rebuilt with npm

### Monitoring

#### Check Assembly Count

```bash
# Count assemblies by access level
jq -r '.defaultAccessLevel' /data/moop/metadata/jbrowse2-configs/assemblies/*.json | sort | uniq -c
```

#### Check Disk Usage

```bash
# Genome data
du -sh /data/moop/data/genomes/

# Track data
du -sh /data/moop/data/tracks/

# Total
du -sh /data/moop/data/
```

#### Verify All Assemblies

```bash
cd /data/moop

# Check each assembly has required files
for assembly in /data/moop/metadata/jbrowse2-configs/assemblies/*.json; do
    name=$(basename "$assembly" .json)
    echo "Checking: $name"
    
    # Extract organism and assembly from filename
    organism=$(echo "$name" | cut -d_ -f1)
    assembly_id=$(echo "$name" | cut -d_ -f2-)
    
    # Check files
    if [ -f "/data/moop/data/genomes/$organism/$assembly_id/reference.fasta" ]; then
        echo "  ✓ FASTA found"
    else
        echo "  ✗ FASTA missing"
    fi
done
```

---

## Troubleshooting

### Assembly Not Showing in UI

**Symptoms:** Assembly metadata exists but doesn't appear in JBrowse2

**Checklist:**
1. Validate JSON syntax: `jq . metadata_file.json`
2. Check `defaultAccessLevel` matches user's access
3. Check session authentication is working
4. Clear browser cache
5. Check API response: `curl http://localhost:8888/api/jbrowse2/get-config.php | jq .`

### File Not Found Errors

**Symptoms:** API returns assembly but JBrowse2 shows "File not found"

**Common Causes:**
1. Symlinks broken (original files moved/deleted)
2. Permissions wrong (web server can't read files)
3. URI paths wrong in metadata (filesystem path instead of web path)

**Solutions:**
```bash
# Check symlinks
ls -la /data/moop/data/genomes/Organism/Assembly/

# Check permissions
ls -la /data/moop/data/genomes/Organism/Assembly/reference.fasta

# Fix permissions
chmod 644 /data/moop/data/genomes/Organism/Assembly/*

# Verify URI in metadata
jq '.sequence.adapter.fastaLocation.uri' metadata_file.json
# Should be: /moop/data/genomes/... (NOT /data/moop/...)
```

### Slow Performance

**Symptoms:** JBrowse2 is slow to load or navigate

**Solutions:**
1. **Enable caching** - See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)
2. **Reduce track count** - Limit tracks shown by default
3. **Optimize indexes** - Ensure `.fai` and `.tbi` files exist
4. **Use BigWig** - Convert large WIG/bedGraph to BigWig format

### JWT Token Errors

**Symptoms:** "Token expired" or "Invalid token" errors

**Solutions:**
```bash
# Check keys exist
ls -la /data/moop/certs/jwt_*.pem

# Check key permissions
chmod 600 /data/moop/certs/jwt_private_key.pem
chmod 644 /data/moop/certs/jwt_public_key.pem

# Verify key format
head /data/moop/certs/jwt_private_key.pem
# Should start with: -----BEGIN RSA PRIVATE KEY-----

# Test token generation
php -r "require 'lib/jbrowse/track_token.php'; echo generateTrackToken('test', 'test', 'Public');"
```

---

## Remote Tracks Server

### Overview

For production, track data should be served from a separate server.

**Current Setup:**
```
MOOP Server (localhost:8888)
  ├── JBrowse2 UI
  ├── Config API
  └── Fake Tracks Server (for testing)
```

**Production Setup:**
```
MOOP Server (moop.example.com)
  ├── JBrowse2 UI
  └── Config API (generates JWT tokens)

Tracks Server (tracks.example.com)
  ├── BigWig files
  ├── BAM files
  └── JWT validation
```

### JWT Key Distribution

On MOOP server:
```bash
# Keep private key (signs tokens)
/data/moop/certs/jwt_private_key.pem (chmod 600)
```

On tracks server(s):
```bash
# Copy public key (validates tokens)
scp /data/moop/certs/jwt_public_key.pem tracks-admin@tracks.example.com:/etc/tracks-server/
```

### Tracks Server Setup

See [SECURITY.md](SECURITY.md) for detailed tracks server configuration with:
- Apache mod_rewrite
- Nginx auth_request
- JWT validation script
- HTTP range request support

**Quick start for tracks server:**

1. Copy public key to tracks server
2. Install JWT validation (PHP or Python)
3. Configure web server (Apache/Nginx)
4. Test: `curl -H "Range: bytes=0-1000" "https://tracks.example.com/bigwig/test.bw?token=JWT"`

---

## Best Practices

### DO ✅

- Keep original organism files in `/organisms/` untouched
- Use symlinks in `/data/moop/data/genomes/`
- Validate JSON after editing metadata
- Test assemblies after adding
- Back up metadata and JWT keys
- Use descriptive display names
- Document custom configurations

### DON'T ❌

- Don't modify original organism files
- Don't hardcode paths in code
- Don't commit JWT private keys to git
- Don't skip validation steps
- Don't forget to index files (.fai, .tbi)
- Don't use filesystem paths in metadata (use web URIs)

---

## Support & Resources

- **Scripts README:** [../../tools/jbrowse/README.md](../../tools/jbrowse/README.md)
- **Developer Guide:** [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)
- **Security Guide:** [SECURITY.md](SECURITY.md)
- **API Reference:** [API_REFERENCE.md](API_REFERENCE.md)

---

**Need help?** Check the troubleshooting section or contact the development team.
