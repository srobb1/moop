# Synteny and Comparative Genomics Guide

**Purpose:** Complete guide for dual-assembly comparisons and synteny tracks  
**Status:** Backend complete ✅ | Frontend UI needed ⏳ | MAF/MCScan in progress ⚠️

---

## Overview

MOOP supports comparing two genome assemblies side-by-side with synteny tracks showing genomic relationships. This includes:

- **PIF/PAF tracks** - Whole genome alignments (✅ working)
- **MAF tracks** - Multiple alignment format from Cactus (⚠️ in progress)
- **MCScan anchors** - Orthologous gene pairs (⚠️ not fully implemented)

---

## Quick Start

### 1. Via API (Working Now!)

```bash
# Request dual-assembly config
curl "http://moop.example.com/api/jbrowse2/config.php?assembly1=Org1_Asm1&assembly2=Org2_Asm2"
```

**Returns:**
- Both assembly definitions
- All tracks for both assemblies (access-filtered)
- Synteny tracks connecting them
- LinearSyntenyView configuration

### 2. Via Google Sheets (Recommended)

Add synteny tracks to your Google Sheet with special columns:

```
track_id,name,category,ASSEMBLY1,ASSEMBLY2,TRACK_PATH,ACCESS_LEVEL
nvec_synteny,Nvec200 vs Nvec100,Synteny,Nvec200,Nvec100,/data/synteny/genomes.pif.gz,public
```

---

## Supported Track Types

### ✅ PIF/PAF Tracks (Working)

**Format:** PairwiseIndexedPAF (`.pif.gz` + `.tbi` index)

**Generated from:** minimap2 alignments

**Example:**
```bash
# Run minimap2
minimap2 -x asm5 -c assembly1.fa assembly2.fa > alignment.paf

# Convert to PIF
sort -k6,6 -k8,8n alignment.paf | bgzip > alignment.pif.gz
tabix -s 6 -b 8 -e 9 alignment.pif.gz
```

**Google Sheet:**
```
track_id,name,category,ASSEMBLY1,ASSEMBLY2,TRACK_PATH,ACCESS_LEVEL
paf_align,Genome Alignment,Synteny,Nvec200,Nvec100,/data/synteny/align.pif.gz,public
```

---

### ⚠️ MAF Tracks (In Progress)

**Format:** Multiple Alignment Format from Cactus

**Challenge:** Need to specify multiple aligned genomes with labels/colors

#### Two Approaches

##### Approach 1: MafAdapter (Recommended)

**Pros:**
- Direct from Cactus output (no conversion)
- Uses specialized alignment viewer
- Best for alignment visualization

**Cons:**
- Slower for large files
- Requires .gzi index

**Files needed:**
- `alignment.maf.gz` (bgzipped)
- `alignment.maf.gz.gzi` (index)

**Usage:**
```bash
# Prepare Cactus MAF
bgzip alignment.maf
samtools faidx alignment.maf.gz  # Creates .gzi index
```

##### Approach 2: BigBed/BigMaf (For Large Files)

**Pros:**
- Fast random access
- Smaller file size
- No plugin needed

**Cons:**
- Requires conversion with `mafToBigMaf` tool
- Different visualization (feature-based)

**Files needed:**
- `alignment.bb` or `alignment.bigbed`

**Usage:**
```bash
# Convert MAF to BigMaf
mafToBigMaf alignment.maf chrom.sizes output.bb
```

#### Which Format to Use?

| Use Case | Recommendation |
|----------|----------------|
| File < 500 MB | MAF with MafAdapter |
| File > 500 MB | Consider BigMaf |
| Need alignment visualization | MAF (required) |
| Just need feature positions | BigMaf works |
| Cactus direct output | MAF (no conversion) |

#### Google Sheet Format for MAF

##### Option 1: Auto-Detection (for local files)

```
track_id,name,category,track_path,access_level,organism,assembly
primate_maf,Primate Alignment,Conservation,/data/cactus/primates.maf.gz,PUBLIC,Homo_sapiens,hg38
```

Script auto-detects aligned genomes from MAF header.

##### Option 2: Explicit Samples (recommended)

```
track_id,name,category,track_path,access_level,organism,assembly,samples
primate_maf,Primate Alignment,Conservation,/data/cactus/primates.maf.gz,PUBLIC,Homo_sapiens,hg38,hg38,Human;panTro6,Chimp;gorGor6,Gorilla
```

**Samples format:** `assemblyId,Label;assemblyId2,Label2;...`

##### Option 3: Remote MAF Files

```
track_id,name,category,track_path,access_level,organism,assembly,samples
cactus_remote,Remote MAF,Conservation,https://data.org/primates.maf.gz,PUBLIC,Homo_sapiens,hg38,hg38,Human;panTro6,Chimp;gorGor6,Gorilla
```

#### Example: 20 Mammal Cactus Alignment

```
track_id: cactus_20mammals
name: 20 Mammal Alignment
category: Conservation
track_path: /data/cactus/mammals20.maf.gz
organism: Homo_sapiens
assembly: hg38
samples: hg38,Human;panTro6,Chimpanzee;gorGor6,Gorilla;ponAbe3,Orangutan;nomLeu3,Gibbon;rheMac10,Rhesus;macFas5,Crab-eating macaque;papAnu4,Baboon;chlSab2,Green monkey;calJac4,Marmoset;saiBol1,Squirrel monkey;mm39,Mouse;rn7,Rat;cavPor3,Guinea pig;bosTau9,Cow;canFam6,Dog;felCat9,Cat;galGal6,Chicken;xenTro10,Frog;danRer11,Zebrafish
```

#### MAF Track Configuration (Generated JSON)

```json
{
  "type": "MafTrack",
  "trackId": "cactus_20mammals",
  "name": "20 Mammal Alignment",
  "category": ["Conservation"],
  "assemblyNames": ["hg38"],
  "adapter": {
    "type": "MafAdapter",
    "mafLocation": {
      "uri": "data/cactus/mammals20.maf.gz",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "data/cactus/mammals20.maf.gz.gzi",
        "locationType": "UriLocation"
      }
    },
    "samples": [
      {"id": "hg38", "label": "Human"},
      {"id": "panTro6", "label": "Chimpanzee"},
      {"id": "gorGor6", "label": "Gorilla"}
    ]
  },
  "displays": [{
    "type": "LinearMafDisplay",
    "displayId": "cactus_20mammals-LinearMafDisplay"
  }]
}
```

**Important:** The `samples` array is **required** for MAF tracks to display properly!

#### Preparing MAF Files

```bash
# From Cactus output
bgzip alignment.maf
samtools faidx alignment.maf.gz  # Creates .gzi

# Verify samples in header
zcat alignment.maf.gz | head -50 | grep "^s "
# Should show: s hg38.chr1 ...
#              s panTro6.chr1 ...
```

---

### ⚠️ MCScan Anchor Tracks (Not Fully Implemented)

**Format:** MCScan anchors file + 2 BED files

**Generated from:** MCScanX or jcvi tools

**Use case:** Display orthologous gene pairs between genomes

**Files needed:**
- `genome1_genome2.anchors` - Ortholog pairs
- `genome1.bed` - Gene positions in assembly 1
- `genome2.bed` - Gene positions in assembly 2

**Google Sheet Format:**
```
track_id,name,category,ASSEMBLY1,ASSEMBLY2,TRACK_PATH,BED1_PATH,BED2_PATH,ACCESS_LEVEL
nvec_orthologs,Nvec Orthologs,Synteny,Nvec200,Nvec100,/data/synteny/anchors,/data/synteny/nvec200.bed,/data/synteny/nvec100.bed,public
```

**Status:** Backend handler exists (`add_mcscan_track.sh`) but needs testing and frontend integration.

---

## Dual-Assembly API

### How It Works

```
User Request
    ↓
config.php detects assembly1 & assembly2 params
    ↓
generateDualAssemblyConfig()
    ├── Load assembly1 definition
    ├── Load assembly2 definition
    ├── Check user has access to BOTH
    ├── Load tracks for assembly1 (filtered)
    ├── Load tracks for assembly2 (filtered)
    └── Load synteny tracks connecting them (filtered)
    ↓
Return combined config with LinearSyntenyView
```

### API Routing

```php
// In config.php
$assembly1 = $_GET['assembly1'] ?? null;
$assembly2 = $_GET['assembly2'] ?? null;

if ($assembly1 && $assembly2) {
    // Dual-assembly mode
    generateDualAssemblyConfig($assembly1, $assembly2, $user_access_level);
} elseif ($organism && $assembly) {
    // Single-assembly mode
    generateAssemblyConfig($organism, $assembly, $user_access_level);
} else {
    // Assembly list
    generateAssemblyList($user_access_level);
}
```

### Response Structure

```json
{
  "assemblies": [
    { "name": "Org1_Asm1", "sequence": {...} },
    { "name": "Org2_Asm2", "sequence": {...} }
  ],
  "tracks": [
    { "assemblyNames": ["Org1_Asm1"], "trackId": "track1", ... },
    { "assemblyNames": ["Org2_Asm2"], "trackId": "track2", ... },
    { "assemblyNames": ["Org1_Asm1", "Org2_Asm2"], "trackId": "synteny1", ... }
  ],
  "defaultSession": {
    "name": "Org1_Asm1 vs Org2_Asm2",
    "views": [{
      "type": "LinearSyntenyView",
      "views": [
        { "type": "LinearGenomeView", "tracks": [...] },
        { "type": "LinearGenomeView", "tracks": [...] }
      ]
    }]
  }
}
```

---

## Prerequisites

### Both Assemblies Must Be Loaded

Before adding synteny tracks, ensure both genomes exist:

```bash
# Check assemblies exist
ls /data/moop/metadata/jbrowse2-configs/assemblies/ | grep -E "(Org1_Asm1|Org2_Asm2)"
```

If missing, add them first:

```bash
# Setup assembly files
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Org1/Asm1
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Org2/Asm2

# Register in JBrowse2
./tools/jbrowse/add_assembly_to_jbrowse.sh Org1 Asm1
./tools/jbrowse/add_assembly_to_jbrowse.sh Org2 Asm2
```

---

## File Storage

### Directory Structure

```
data/tracks/
└── synteny/
    ├── Org1_Asm1__Org2_Asm2/
    │   ├── alignment.pif.gz
    │   ├── alignment.pif.gz.tbi
    │   ├── alignment.maf.gz        # MAF format
    │   ├── alignment.maf.gz.gzi
    │   ├── genes.anchors           # MCScan format
    │   ├── org1.bed
    │   └── org2.bed
    └── ...

metadata/jbrowse2-configs/tracks/
└── synteny/
    └── Org1_Asm1__Org2_Asm2/
        ├── alignment_pif.json
        ├── alignment_maf.json
        └── genes_anchors.json
```

**Note:** Use `__` (double underscore) to separate assembly names in directory structure.

---

## Access Control

Synteny tracks respect the same access control as regular tracks:

```json
{
  "metadata": {
    "access_level": "PUBLIC"  // or COLLABORATOR, ADMIN
  }
}
```

Users must have access to:
1. Both assemblies
2. The synteny track itself

If user lacks access to either assembly, the synteny track won't appear.

---

## Testing

### Test Dual-Assembly API

```bash
# Test with real assemblies
curl -s "http://localhost:8888/api/jbrowse2/config.php?assembly1=Nematostella_vectensis_GCA_033964005.1&assembly2=Anoura_caudifer_GCA_004027475.1" | jq '.assemblies[].name'

# Should return both assembly names
```

### Test Synteny Track Loading

```bash
# Check synteny tracks directory
ls -la /data/moop/metadata/jbrowse2-configs/tracks/synteny/

# Validate track JSON
jq . /data/moop/metadata/jbrowse2-configs/tracks/synteny/Org1__Org2/track.json
```

---

## Next Steps (TODO)

### Frontend UI Needed

Currently API works, but need a user interface:

1. **Assembly pair selector** - Let users pick two assemblies
2. **Synteny view launcher** - Open LinearSyntenyView with both assemblies
3. **Preset comparisons** - Quick links for common comparisons

### Complete MAF Support

1. Test with real Cactus alignments
2. Verify `samples` array generation
3. Test auto-detection vs explicit samples
4. Add color customization

### Complete MCScan Support

1. Test anchor file parsing
2. Verify BED file handling
3. Add to Google Sheets workflow
4. Test with real MCScanX output

---

## Troubleshooting

### Synteny Track Not Showing

**Check:**
1. Both assemblies loaded: `ls /data/moop/metadata/jbrowse2-configs/assemblies/`
2. Synteny track metadata exists: `ls /data/moop/metadata/jbrowse2-configs/tracks/synteny/`
3. User has access to both assemblies
4. Track file exists and is indexed

### MAF Track Shows Empty

**Check:**
1. `samples` array is present in track JSON
2. MAF file has `.gzi` index
3. Assembly names in `samples` match MAF file headers

### API Returns Error

**Check:**
1. Both assembly names are correct (case-sensitive)
2. Assembly metadata files exist
3. PHP error logs: `tail -f /var/log/apache2/error.log`

---

## Reference

See also:
- [SETUP_NEW_ORGANISM.md](SETUP_NEW_ORGANISM.md) - Adding new assemblies
- [workflows/GOOGLE_SHEETS_WORKFLOW.md](workflows/GOOGLE_SHEETS_WORKFLOW.md) - Google Sheets integration
- [technical/SECURITY.md](technical/SECURITY.md) - Access control details
- [reference/API_REFERENCE.md](reference/API_REFERENCE.md) - Full API documentation

---

**Status Summary:**
- ✅ Dual-assembly API working
- ✅ PIF/PAF tracks working
- ⚠️ MAF tracks in progress (needs testing)
- ⚠️ MCScan anchors partially implemented
- ⏳ Frontend UI needed
