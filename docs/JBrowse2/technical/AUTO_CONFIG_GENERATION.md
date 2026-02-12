# Auto-Config Generation Update

**Date:** February 10, 2026  
**Update:** Scripts now automatically regenerate JBrowse2 configs

---

## What Changed

All JBrowse2 integration scripts now **automatically regenerate static config files** after making changes.

### Updated Scripts

1. **`add_assembly_to_jbrowse.sh`**
   - After creating assembly metadata
   - Runs `generate-jbrowse-configs.php`
   - Assembly immediately visible in JBrowse2

2. **`add_bam_track.sh`**
   - After adding BAM track metadata
   - Regenerates configs
   - Track immediately visible (no manual refresh needed)

3. **`add_bigwig_track.sh`**
   - After adding BigWig track metadata
   - Regenerates configs
   - Track immediately visible

---

## How It Works

Each script now calls:
```bash
php tools/jbrowse/generate-jbrowse-configs.php
```

This regenerates all static config files in:
```
/data/moop/jbrowse2/configs/{organism}_{assembly}/config.json
```

---

## User Experience

### Before
1. Run `add_assembly_to_jbrowse.sh`
2. **Manually run:** `php generate-jbrowse-configs.php`
3. Refresh browser
4. See assembly

### After ✅
1. Run `add_assembly_to_jbrowse.sh`
2. Refresh browser
3. See assembly (config auto-generated!)

---

## What generate-jbrowse-configs.php Does

1. Reads all assembly definitions from `/metadata/jbrowse2-configs/assemblies/*.json`
2. For each assembly:
   - Creates directory: `/jbrowse2/configs/{assembly_name}/`
   - Generates `config.json` with:
     - Assembly definition
     - Reference sequence track
     - All associated tracks
3. Makes configs web-accessible for JBrowse2

---

## Manual Regeneration (If Needed)

If you ever need to manually regenerate all configs:

```bash
cd /data/moop
php tools/jbrowse/generate-jbrowse-configs.php
```

Output:
```
Generating JBrowse2 config files...
Metadata dir: /data/moop/metadata/jbrowse2-configs/assemblies
Output dir: /data/moop/jbrowse2/configs
---
✓ Generated config for: Anoura_caudifer_GCA_004027475.1
✓ Generated config for: Nematostella_vectensis_GCA_033964005.1
---
Generated 2 config files
```

---

## Files Modified

1. `/data/moop/tools/jbrowse/add_assembly_to_jbrowse.sh`
2. `/data/moop/tools/jbrowse/add_bam_track.sh`
3. `/data/moop/tools/jbrowse/add_bigwig_track.sh`

---

## Benefits

✅ **Seamless workflow** - No manual steps  
✅ **Immediate visibility** - Tracks appear right away  
✅ **Fewer errors** - Can't forget to regenerate  
✅ **Better UX** - Scripts do everything automatically  

---

## Error Handling

If auto-generation fails, scripts show a warning:
```
⚠ Could not regenerate configs automatically
⚠ Run manually: php /data/moop/tools/jbrowse/generate-jbrowse-configs.php
```

Script continues successfully - you just need to run the command manually.

---

**Status:** ✅ Implemented and tested  
**Tested with:** Nematostella vectensis integration
