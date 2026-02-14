# Synteny Track Migration Plan

## Current Status (2026-02-14)

### What We've Done
1. ✅ Migrated regular tracks to dynamic permission-based config generation
2. ✅ Cleaned up old static config files (archived)
3. ✅ Archived obsolete shell scripts and Python scripts
4. ✅ Created `generate_tracks_from_sheet.php` for regular tracks with permission filtering

### What Needs Review: Synteny System

#### Current Synteny Files:
- `tools/jbrowse/generate_synteny_tracks_from_sheet.php` - Generates static configs
- `metadata/synteny-tracks.json` - Static metadata storage
- `jbrowse2/configs/{assembly}/synteny/*.json` - Static config files

#### Questions to Answer:

1. **Do synteny tracks need permission levels?**
   - Currently: All synteny tracks appear to be PUBLIC
   - Need to clarify: Should synteny have PUBLIC/PROTECTED/COLLABORATOR/PRIVATE levels?

2. **Should synteny use dynamic config generation?**
   - Pro: Consistent with regular tracks, allows permission filtering
   - Con: Synteny is more complex (involves TWO assemblies)
   - Decision needed: Keep static configs or migrate to dynamic?

3. **How does synteny integrate with our permission system?**
   - Current: Google Sheet column "Public Visibility" (yes/no)
   - New system: Would need to support permission levels like regular tracks
   - Question: How should bidirectional synteny tracks handle permissions?

#### Migration Options:

**Option A: Keep Synteny Static (Recommended for now)**
- Synteny tracks remain as static JSON files
- Simpler to maintain (synteny is complex enough)
- Still works with current permission system (just filter by PUBLIC visibility)
- Pro: Less risk, synteny already working
- Con: Inconsistent with regular tracks

**Option B: Migrate Synteny to Dynamic**
- Create `api/jbrowse2/synteny-config.php` 
- Parse Google Sheet on-demand
- Filter by permission level
- Pro: Fully consistent system
- Con: More complex, needs careful testing with bidirectional tracks

**Option C: Hybrid Approach**
- Generate static synteny configs but filter them dynamically
- Keep `generate_synteny_tracks_from_sheet.php` for generation
- Add permission filtering when serving configs
- Pro: Best of both worlds
- Con: Still some complexity

### Recommended Next Steps:

1. **Immediate (Next Session):**
   - Decide on synteny permission strategy
   - Review if current synteny system is working correctly
   - Test synteny tracks in browser with current setup

2. **Short Term:**
   - If keeping static: Document that synteny uses static configs
   - If migrating: Create migration plan similar to regular tracks
   - Add permission level column to synteny sheet if needed

3. **Long Term:**
   - Consider if synteny needs same permission levels as regular tracks
   - Evaluate if bidirectional synteny complicates permission model
   - May want synteny to be simpler (PUBLIC/PRIVATE only)

### Files to Review Next Time:
```bash
# Synteny generation script
tools/jbrowse/generate_synteny_tracks_from_sheet.php

# Synteny metadata
metadata/synteny-tracks.json

# Synteny static configs (if they exist)
jbrowse2/configs/*/synteny/

# How config.php currently handles synteny
api/jbrowse2/config.php (grep for synteny)
```

### Key Decision Needed:
**Should synteny tracks follow the same dynamic permission-based system as regular tracks, or remain as static configs with simple PUBLIC/PRIVATE filtering?**

Consider:
- Synteny is already complex (2 assemblies, bidirectional)
- May not need fine-grained PROTECTED/COLLABORATOR levels
- Current system may be "good enough" for synteny use case
- Focus effort on regular tracks which are more numerous

