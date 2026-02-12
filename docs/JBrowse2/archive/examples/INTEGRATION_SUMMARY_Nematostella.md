# Nematostella vectensis Integration - Summary

**Date:** February 10, 2026  
**Status:** Ready to Execute  
**Estimated Time:** 20-30 minutes

---

## What We Created

### Documentation Files

1. **WALKTHROUGH_Nematostella_vectensis.md** - Step-by-step user guide
   - Complete walkthrough for adding the organism
   - Commands for each step
   - Troubleshooting section
   - Verification procedures

2. **IMPLEMENTATION_PLAN_Nematostella.md** - Technical implementation plan
   - System analysis showing no code changes needed
   - Detailed phase breakdown
   - Optional enhancements for future
   - Risk assessment and testing plan

3. **integrate_nematostella.sh** - Automated integration script
   - One-command integration
   - Validates prerequisites
   - Runs all setup steps
   - Validates results

---

## Key Findings

### ✅ No Code Changes Required!

The existing MOOP JBrowse2 infrastructure handles everything:

1. **Genome Reference** - `setup_jbrowse_assembly.sh` indexes FASTA
2. **GFF Annotations** - Automatically loaded by `/api/jbrowse2/assembly.php` (lines 242-261)
3. **BAM Tracks** - `add_bam_track.sh` creates metadata
4. **BigWig Tracks** - `add_bigwig_track.sh` creates metadata

### Critical Discovery

The `/api/jbrowse2/assembly.php` automatically detects and adds annotation tracks:

```php
// Lines 242-261: Auto-adds annotations if GFF exists
$annotations_file = __DIR__ . "/../../data/genomes/{$organism}/{$assembly}/annotations.gff3.gz";
if (file_exists($annotations_file)) {
    $assembly_config['tracks'][] = [
        'name' => 'Annotations',
        'trackId' => 'annotations',
        'type' => 'FeatureTrack',
        'adapter' => [
            'type' => 'GffAdapter',
            'gffLocation' => [...],
            'index' => [...]
        ]
    ];
}
```

**This means:** Just run the setup script and GFF annotations appear automatically!

---

## Files Available

### Source Data
```
/data/moop/organisms/Nematostella_vectensis/GCA_033964005.1/
├── genome.fa         ← Reference genome
└── genomic.gff       ← Gene annotations
```

### Track Data
```
/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/
├── bam/
│   ├── MOLNG-2707_S3-body-wall.bam          ← RNA-seq alignments
│   └── MOLNG-2707_S3-body-wall.bam.bai      ← BAM index
└── bigwig/
    ├── MOLNG-2707_S1-body-wall.pos.bw       ← Coverage (+)
    └── MOLNG-2707_S1-body-wall.neg.bw       ← Coverage (-)
```

---

## How to Execute

### Option 1: Automated Script (Recommended)

```bash
cd /data/moop/tools/jbrowse
./integrate_nematostella.sh
```

**What it does:**
1. Validates all prerequisites
2. Prepares genome files (indexes, compresses)
3. Registers assembly in JBrowse2
4. Adds all tracks (BAM + BigWig)
5. Validates everything worked

**Time:** ~20 minutes

### Option 2: Manual Steps (For Learning)

Follow the detailed walkthrough:

```bash
# Step 1: Prepare genome (5-10 min)
cd /data/moop
./tools/jbrowse/setup_jbrowse_assembly.sh \
    /data/moop/organisms/Nematostella_vectensis/GCA_033964005.1

# Step 2: Register assembly (30 sec)
./tools/jbrowse/add_assembly_to_jbrowse.sh \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --display-name "Nematostella vectensis (Starlet Sea Anemone)" \
    --access-level PUBLIC

# Step 3: Add BAM track (2 min)
./tools/jbrowse/add_bam_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bam/MOLNG-2707_S3-body-wall.bam \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Alignments" \
    --category "RNA-seq" \
    --access PUBLIC

# Step 4a: Add positive strand coverage (2 min)
./tools/jbrowse/add_bigwig_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.pos.bw \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Coverage (+)" \
    --category "RNA-seq Coverage" \
    --access PUBLIC \
    --color "#1f77b4"

# Step 4b: Add negative strand coverage (2 min)
./tools/jbrowse/add_bigwig_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.neg.bw \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Coverage (-)" \
    --category "RNA-seq Coverage" \
    --access PUBLIC \
    --color "#ff7f0e"
```

See `WALKTHROUGH_Nematostella_vectensis.md` for full details.

---

## What Will Be Created

### Genome Data
```
/data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/
├── reference.fasta              ← Symlink to genome.fa
├── reference.fasta.fai          ← FASTA index
├── annotations.gff3             ← Symlink to genomic.gff
├── annotations.gff3.gz          ← Compressed GFF
└── annotations.gff3.gz.tbi      ← GFF index (tabix)
```

### Metadata
```
/data/moop/metadata/jbrowse2-configs/assemblies/
└── Nematostella_vectensis_GCA_033964005.1.json

/data/moop/metadata/jbrowse2-configs/tracks/
├── body_wall_rna_seq_alignments_s3.json
├── body_wall_rna_seq_coverage_pos.json
└── body_wall_rna_seq_coverage_neg.json
```

---

## Expected Result in JBrowse2

When you navigate to `http://localhost:8888/moop/jbrowse2.php`:

1. **Assembly List** shows:
   - "Nematostella vectensis (Starlet Sea Anemone)"

2. **Clicking opens genome browser** with:
   - ✅ Reference sequence track (PUBLIC)
   - ✅ Annotations track (genes from GFF) - **auto-loaded** (PUBLIC)
   - ✅ Body Wall RNA-seq Alignments (BAM) - **ADMIN ONLY**
   - ✅ Body Wall RNA-seq Coverage (+) (BigWig) - **PUBLIC**
   - ✅ Body Wall RNA-seq Coverage (-) (BigWig) - **PUBLIC**

3. **All tracks are:**
   - Browsable (pan/zoom)
   - Searchable (by gene name)
   - Togglable (show/hide)
   - Interactive (click for details)

---

## Testing

### Quick Test

```bash
# Test assembly API
curl -s "http://localhost:8888/api/jbrowse2/get-config.php" | \
    jq '.assemblies[] | select(.organism == "Nematostella_vectensis")'
```

### Full Test

```bash
# Check assembly with tracks
curl -s "http://localhost:8888/api/jbrowse2/assembly.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1" | jq .
```

### Browser Test

1. Open: `http://localhost:8888/moop/jbrowse2.php`
2. Find "Nematostella vectensis" 
3. Click to open
4. Verify all tracks load

---

## Rollback (If Needed)

```bash
# Remove metadata
rm /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json
rm /data/moop/metadata/jbrowse2-configs/tracks/body_wall*.json

# Remove genome data (optional)
rm -rf /data/moop/data/genomes/Nematostella_vectensis/

# Refresh browser
```

---

## Future Enhancements (Optional)

See `IMPLEMENTATION_PLAN_Nematostella.md` for detailed plans:

1. **Auto-discovery** - Automatically find and add tracks
2. **Grouped tracks** - Group pos/neg strands together
3. **Track search** - Find tracks by metadata
4. **Filename parsing** - Extract metadata from filenames

**Priority:** Low - Current system works well

---

## Documentation Reference

| Document | Purpose | Location |
|----------|---------|----------|
| **WALKTHROUGH** | User guide with commands | `docs/JBrowse2/WALKTHROUGH_Nematostella_vectensis.md` |
| **IMPLEMENTATION PLAN** | Technical details | `docs/JBrowse2/IMPLEMENTATION_PLAN_Nematostella.md` |
| **Integration Script** | Automated setup | `tools/jbrowse/integrate_nematostella.sh` |
| **Admin Guide** | General setup guide | `docs/JBrowse2/ADMIN_GUIDE.md` |
| **API Reference** | API documentation | `docs/JBrowse2/API_REFERENCE.md` |

---

## Summary

✅ **Ready to Execute:** All scripts and documentation complete  
✅ **No Code Changes:** Existing system handles everything  
✅ **Well Tested:** Scripts used successfully with other organisms  
✅ **Documented:** Complete walkthrough and troubleshooting  
✅ **Automated:** One-command integration available  

**Recommended Next Step:** Run `./integrate_nematostella.sh` and verify in browser

---

## Timeline

| Task | Time | Status |
|------|------|--------|
| Documentation | 30 min | ✅ Complete |
| Script creation | 15 min | ✅ Complete |
| **Integration execution** | **20-30 min** | ⏳ **Ready** |
| Testing | 10 min | After integration |
| **Total** | **~1.5 hours** | **Ready to start** |

---

**Status:** ✅ Planning Complete - Ready for Execution  
**Risk Level:** Low  
**Success Rate:** High (proven scripts)  
**Time to Production:** 20-30 minutes
