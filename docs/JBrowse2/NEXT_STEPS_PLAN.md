# JBrowse2 Testing - Next Steps Plan

**Date:** Feb 5, 2026 - 18:30 UTC  
**Status:** ✅ Assembly setup complete, ready for browser testing  
**Next Phase:** Configure tracks and test visualization

---

## Where We Left Off

### ✅ Completed
1. JBrowse2 React frontend created: `/data/moop/jbrowse2/`
2. Anoura caudifer genome indexed and ready at `/data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/`
   - reference.fasta (symlinked)
   - reference.fasta.fai (indexed)
   - annotations.gff3.gz (sorted, compressed, indexed)
3. Site configuration updated (`site_config.php`)
4. JWT security infrastructure in place
5. API endpoints created
6. Automated setup script tested and working
7. All documentation and code committed

### Current State
- Genome data ready for visualization
- No tracks configured yet
- No JBrowse2 config.json assembly definitions yet
- Browser not yet tested

---

## Understanding JBrowse Add-Assembly / Add-Track Commands

### The Quick Answer
**YES, you're correct.** The JBrowse CLI commands only generate JSON config files.

```bash
jbrowse add-assembly reference.fasta
jbrowse add-track my_file.bam
```

What they do:
- Scan the file(s)
- Extract metadata (sequence names, length, track type, etc.)
- Generate JSON config entries
- Add those entries to `config.json`

What they DON'T do:
- Copy files anywhere
- Generate indices
- Move data
- Create symlinks
- Contact remote servers

### For Our Setup
Our approach is:
- ✅ Files already indexed and in place
- ✅ We manually manage directories (/data/genomes/, /data/tracks/)
- ✅ We can manually edit config.json OR use jbrowse CLI
- ✅ We use our API (/api/jbrowse2/assembly.php) instead of direct config.json for dynamic assembly selection

---

## Plan for Today (After Lunch)

### Phase 1: Configure First Assembly in JBrowse2 (30 min)

**Goal:** Get Anoura caudifer genome visible in the browser

#### Option A: Using JBrowse CLI (Easiest)
```bash
cd /data/moop/jbrowse2
jbrowse add-assembly /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta \
  --out . \
  --skipCheck
```

**Result:** Adds assembly config to `jbrowse2/config.json`

#### Option B: Manual config.json edit (More Control)
Edit `jbrowse2/config.json` directly to add:
```json
{
  "assemblies": [
    {
      "name": "Anoura_caudifer_GCA_004027475.1",
      "displayName": "Anoura caudifer (GCA_004027475.1)",
      "sequence": {
        "type": "ReferenceSequenceTrack",
        "trackId": "Anoura_caudifer_GCA_004027475.1_seq",
        "adapter": {
          "type": "TwoBitAdapter",
          "twoBitLocation": {
            "uri": "/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta"
          }
        }
      },
      "refNameAliases": {
        "adapter": {
          "type": "RefNameAliasAdapter",
          "location": {
            "uri": "/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta.fai"
          }
        }
      }
    }
  ]
}
```

**Recommendation:** Try Option A first (simpler), fall back to Option B if needed

---

### Phase 2: Add Test Tracks (30 min)

**Goal:** Add visual tracks so we can verify track loading works

#### Check What Tracks Are Available
```bash
ls -la /data/moop/data/tracks/
# Look for: *.bw (BigWig), *.bam (BAM files)
```

#### Add Track Using JBrowse CLI
```bash
cd /data/moop/jbrowse2
jbrowse add-track /data/moop/data/tracks/my_track.bw \
  --assemblyNames Anoura_caudifer_GCA_004027475.1 \
  --out .
```

**Or manually add to config.json:**
```json
{
  "tracks": [
    {
      "type": "QuantitativeTrack",
      "trackId": "Anoura_caudifer_coverage",
      "name": "RNA Coverage",
      "assemblyNames": ["Anoura_caudifer_GCA_004027475.1"],
      "adapter": {
        "type": "BigWigAdapter",
        "bigWigLocation": {
          "uri": "/data/tracks/rna_coverage.bw"
        }
      }
    }
  ]
}
```

**Note:** If BAM files, need BAI indices first:
```bash
samtools index /data/moop/data/tracks/my_file.bam
```

---

### Phase 3: Build and Test (30 min)

**Goal:** Start web server and visualize genome in browser

#### Build JBrowse2 (if needed)
```bash
cd /data/moop/jbrowse2
npm run build  # If not already done
```

#### Start PHP Web Server
```bash
cd /data/moop
php -S 127.0.0.1:8888 -t . &
```

#### Test in Browser
```
http://127.0.0.1:8888/jbrowse2/
```

**What to verify:**
1. ✓ JBrowse2 interface loads
2. ✓ Assembly dropdown shows "Anoura caudifer"
3. ✓ Can select chromosome/sequence
4. ✓ Reference sequence visible
5. ✓ Tracks appear if configured
6. ✓ Can pan/zoom

#### Quick API Test
```bash
curl -s "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public" | jq .
```

Should return assembly config with JWT token

---

### Phase 4: Document Results (15 min)

Create `/data/moop/docs/JBrowse2/FIRST_VISUALIZATION_TEST.md`

Document:
- ✓ What worked
- ✓ What didn't work
- ✓ Screenshots or descriptions
- ✓ Next issues to fix

---

## Potential Issues & Solutions

### Issue 1: JBrowse CLI not found
**Solution:** Install JBrowse CLI
```bash
npm install -g @jbrowse/cli
# Or use npx
npx @jbrowse/cli add-assembly ...
```

### Issue 2: Config.json format issues
**Solution:** Validate JSON syntax
```bash
jq . /data/moop/jbrowse2/config.json  # Should print without errors
```

### Issue 3: Tracks not loading
**Common causes:**
- Track file not indexed (BAM without BAI)
- Wrong path in config (use absolute paths)
- Missing adapter type (BigWigAdapter vs BAMAdapter vs etc.)

**Solution:** Check browser console for errors

### Issue 4: Assembly not loading
**Solution:** Verify FASTA index exists:
```bash
ls -la /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta*
# Should show: reference.fasta and reference.fasta.fai
```

---

## File Locations Reference

**Config Files:**
- JBrowse2 config: `/data/moop/jbrowse2/config.json`
- MOOP config: `/data/moop/config/site_config.php`

**Genome Data:**
- Anoura caudifer: `/data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/`
  - `reference.fasta` (indexed)
  - `annotations.gff3.gz` (sorted, indexed)

**Track Data:**
- All tracks: `/data/moop/data/tracks/`
  - `*.bw` (BigWig files - no indexing needed)
  - `*.bam` (BAM files - need *.bai index)

**API:**
- Assembly config: `/api/jbrowse2/assembly.php`
- Test: `/api/jbrowse2/test-assembly.php`

**Scripts:**
- Setup new organism: `tools/setup_jbrowse_assembly.sh /organisms/path`

---

## Estimated Timeline

| Phase | Task | Time | Status |
|-------|------|------|--------|
| 1 | Configure assembly | 30 min | Ready to start |
| 2 | Add tracks | 30 min | After Phase 1 |
| 3 | Build & test browser | 30 min | After Phase 2 |
| 4 | Document results | 15 min | After Phase 3 |
| **Total** | | **105 min** | **~1.75 hours** |

---

## Success Criteria

✅ We'll know it's working when:

1. **JBrowse2 loads** - Page renders without errors
2. **Assembly appears** - Dropdown shows Anoura caudifer
3. **Sequence visible** - Can see ATCG bases in sequence viewer
4. **Tracks render** - If configured, BigWig/BAM tracks display
5. **Pan/zoom works** - Can navigate around genome
6. **API returns data** - `/api/jbrowse2/test-assembly.php` returns proper config with JWT

---

## After This Session

Next phases (later):
1. **JWT validation testing** - Verify token-based access control
2. **Remote server setup** - Sync to tracks server when ready
3. **Multiple organisms** - Add more genomes using setup script
4. **Production hardening** - Performance, caching, security review

---

## Quick Reference Commands

```bash
# Check genome files
ls -la /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/

# Check track files
ls -la /data/moop/data/tracks/

# Start web server
cd /data/moop && php -S 127.0.0.1:8888 &

# Test API
curl -s "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public" | jq .

# Validate JSON config
jq . /data/moop/jbrowse2/config.json

# Build JBrowse2 (if needed)
cd /data/moop/jbrowse2 && npm run build
```

---

## Summary

**We have:** ✅ Indexed genome, configured MOOP backend  
**We need:** 📝 JBrowse2 config with assembly/tracks  
**We'll test:** 🌐 Browser visualization  

Estimated time: ~2 hours for full end-to-end test

See you after lunch! 🍽️

---

**Last Updated:** Feb 5, 2026 18:30 UTC  
**Session Status:** On break, resuming later  
**Next Reviewer:** Same session
