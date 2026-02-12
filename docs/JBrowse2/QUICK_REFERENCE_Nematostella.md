# Quick Reference: Nematostella vectensis Integration

## TL;DR - Just Run This

```bash
cd /data/moop/tools/jbrowse
./integrate_nematostella.sh
```

**Time:** 20-30 minutes | **Risk:** Low | **Code changes:** None

---

## What Gets Added

- **Genome reference** (FASTA indexed) - PUBLIC
- **Gene annotations** (GFF compressed + indexed) - **auto-loaded** - PUBLIC
- **RNA-seq BAM** alignment track - **ADMIN ONLY**
- **RNA-seq BigWig** coverage tracks (pos/neg) - PUBLIC

### Access Control Strategy

- **Assembly:** PUBLIC (everyone can see the organism)
- **Annotations:** PUBLIC (auto-loaded, visible to all)
- **BigWig tracks:** PUBLIC (coverage data visible to all users)
- **BAM track:** ADMIN (raw alignments restricted to admin/IP whitelist)

---

## Files Created

```
data/genomes/Nematostella_vectensis/GCA_033964005.1/
├── reference.fasta + .fai
├── annotations.gff3.gz + .tbi

metadata/jbrowse2-configs/assemblies/
└── Nematostella_vectensis_GCA_033964005.1.json

metadata/jbrowse2-configs/tracks/
├── body_wall_rna_seq_alignments_s3.json
├── body_wall_rna_seq_coverage_pos.json
└── body_wall_rna_seq_coverage_neg.json
```

---

## Verify It Worked

```bash
# Check assembly exists
curl -s "http://localhost:8888/api/jbrowse2/get-config.php" | \
    jq '.assemblies[] | select(.organism == "Nematostella_vectensis")'

# Open in browser
http://localhost:8888/moop/jbrowse2.php
```

---

## Rollback

```bash
rm /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_*.json
rm /data/moop/metadata/jbrowse2-configs/tracks/body_wall*.json
rm -rf /data/moop/data/genomes/Nematostella_vectensis/
```

---

## Documentation

- **Walkthrough:** `docs/JBrowse2/WALKTHROUGH_Nematostella_vectensis.md`
- **Implementation Plan:** `docs/JBrowse2/IMPLEMENTATION_PLAN_Nematostella.md`
- **Summary:** `docs/JBrowse2/INTEGRATION_SUMMARY_Nematostella.md`
- **This Card:** `docs/JBrowse2/QUICK_REFERENCE_Nematostella.md`

---

## Key Insight

**The annotations (GFF) are automatically loaded by the API!**

No manual track creation needed - just run `setup_jbrowse_assembly.sh` and the GFF appears as a track. See `/api/jbrowse2/assembly.php` lines 242-261.

---

**Status:** ✅ Ready to execute
