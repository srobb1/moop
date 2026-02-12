# JBrowse CLI vs Custom Scripts Analysis

**Date:** 2026-02-11  
**Status:** Recommendation for Hybrid Approach

---

## Executive Summary

Your custom scripts (`add_bam_track.sh`, `add_bigwig_track.sh`, `add_vcf_track.sh`) generate **valid JBrowse2 configs** but with **significant value-added features** beyond what the official JBrowse CLI provides. The concern about config format changes is valid, but can be mitigated with a **hybrid approach**.

**Recommendation:** Use JBrowse CLI when available, fallback to custom generation, and add metadata enrichment layer.

---

## Config Format Comparison

### BAM Track - JBrowse CLI Output
```json
{
  "type": "AlignmentsTrack",
  "trackId": "test-bam",
  "name": "Test BAM",
  "adapter": {
    "type": "BamAdapter",
    "bamLocation": {
      "uri": "/path/to/file.bam",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "/path/to/file.bam.bai",
        "locationType": "UriLocation"
      },
      "indexType": "BAI"
    }
  },
  "category": ["Alignments"],
  "assemblyNames": ["assembly_name"]
}
```

### BAM Track - Your Custom Script Output
```json
{
  "trackId": "organism_assembly_file",
  "name": "Human Readable Name",
  "assemblyNames": ["organism_assembly"],
  "category": ["Alignments"],
  "type": "AlignmentsTrack",
  "adapter": {
    "type": "BamAdapter",
    "bamLocation": {
      "uri": "/moop/data/tracks/bam/file.bam",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "/moop/data/tracks/bam/file.bam.bai",
        "locationType": "UriLocation"
      }
    }
  },
  "displays": [
    {
      "type": "LinearAlignmentsDisplay",
      "displayId": "trackId-LinearAlignmentsDisplay"
    },
    {
      "type": "LinearPileupDisplay",
      "displayId": "trackId-LinearPileupDisplay"
    }
  ],
  "metadata": {
    "description": "User provided description",
    "access_level": "Public",
    "file_path": "/data/moop/data/tracks/bam/file.bam",
    "file_size": 123456,
    "total_reads": "1000000",
    "mapped_reads": "950000",
    "added_date": "2026-02-11T18:00:00Z",
    "google_sheets_metadata": {
      "technique": "RNA-seq",
      "institute": "Lab Name",
      "tissue": "liver",
      ...
    }
  }
}
```

---

## Key Differences

### What JBrowse CLI Provides ✅
1. **Official format** - Guaranteed to work with current JBrowse2
2. **Automatic adapter detection** - Infers correct adapter type
3. **Index auto-discovery** - Finds .bai, .tbi files automatically
4. **Forward compatibility** - Updates with JBrowse2 releases
5. **Minimal but complete** - Just what JBrowse needs

### What JBrowse CLI Lacks ❌
1. **No custom metadata** - Can't add access_level, google_sheets_metadata
2. **No display presets** - Doesn't specify LinearAlignmentsDisplay, colors, etc.
3. **No file management** - Doesn't track original paths, sizes, stats
4. **No validation stats** - Doesn't count reads, variants, etc.
5. **No MOOP integration** - Doesn't know about your permission system

### What Your Custom Scripts Provide ✅
1. **MOOP access control** - `access_level` field for permissions
2. **Google Sheets integration** - `google_sheets_metadata` for external data
3. **Display configuration** - Preset displays with colors
4. **File statistics** - Read counts, variant counts, file sizes
5. **Audit trail** - Added date, file paths, analyst info
6. **User-friendly** - Auto-generates sensible names and IDs

---

## Risks of Custom Config Generation

### Your Concern is Valid ✅

If JBrowse2 changes config format in future versions:

1. **Adapter types might change** - e.g., "BamAdapter" → "BamV2Adapter"
2. **New required fields** - e.g., might require `version: "2.0"`
3. **Deprecated fields** - e.g., `displays` might become `renderers`
4. **Different nesting** - Structure could be reorganized
5. **Your scripts would break** - Need to update manually

### But...

- JBrowse2 has been **stable** - Config format unchanged since v2.0 (2021)
- Your configs are **valid** - No conflicting or wrong fields
- JBrowse is **backward compatible** - Old configs still work
- Changes would require **major version bump** (v3.0)

---

## Recommended Hybrid Approach

### Strategy: CLI First, Custom Fallback, Metadata Layer

```bash
#!/bin/bash
# Hybrid approach pseudocode

function add_track_hybrid() {
    local file=$1
    local organism=$2
    local assembly=$3
    
    # Try JBrowse CLI first if installed
    if command -v jbrowse &> /dev/null; then
        log_info "Using JBrowse CLI for config generation"
        
        # Generate base config with CLI
        jbrowse add-track "$file" \
            --assemblyNames "${organism}_${assembly}" \
            --trackId "$trackId" \
            --name "$trackName" \
            --category "$category" \
            --load inPlace \
            --target "$TEMP_CONFIG" 2>&1
        
        # Read generated config
        BASE_CONFIG=$(jq '.tracks[-1]' "$TEMP_CONFIG")
        
    else
        log_warn "JBrowse CLI not found, using custom config generation"
        
        # Generate config with your current logic
        BASE_CONFIG=$(generate_custom_bam_config)
    fi
    
    # LAYER 2: Add MOOP-specific metadata
    ENRICHED_CONFIG=$(echo "$BASE_CONFIG" | jq ". + {
        metadata: {
            description: \"$DESCRIPTION\",
            access_level: \"$ACCESS_LEVEL\",
            file_path: \"$TARGET_PATH\",
            file_size: $FILE_SIZE,
            total_reads: \"$TOTAL_READS\",
            mapped_reads: \"$MAPPED_READS\",
            added_date: \"$(date -u +"%Y-%m-%dT%H:%M:%SZ")\",
            google_sheets_metadata: {
                technique: \"$TECHNIQUE\",
                institute: \"$INSTITUTE\",
                ...
            }
        }
    }")
    
    # LAYER 3: Add display presets if not present
    if ! echo "$ENRICHED_CONFIG" | jq -e '.displays' > /dev/null; then
        ENRICHED_CONFIG=$(add_display_presets "$ENRICHED_CONFIG")
    fi
    
    # Save final config
    echo "$ENRICHED_CONFIG" > "$METADATA_FILE"
}
```

---

## Implementation Plan

### Phase 1: Add CLI Detection (Immediate)

Modify your scripts to:
1. Check if `jbrowse` command exists
2. If yes, use it for **adapter config only**
3. Add your metadata layer on top
4. Keep current behavior as fallback

### Phase 2: Config Validation (Recommended)

Add validation to detect format changes:
```bash
function validate_jbrowse_config() {
    local config=$1
    
    # Check required JBrowse fields exist
    jq -e '.trackId and .type and .adapter' "$config" || {
        log_error "Invalid JBrowse config structure"
        return 1
    }
    
    # Warn if unexpected fields (might indicate format change)
    local unknown_fields=$(jq -r 'keys[] | select(. as $k | 
        ["trackId", "name", "type", "adapter", "assemblyNames", 
         "category", "displays", "metadata"] | index($k) | not)' "$config")
    
    if [ -n "$unknown_fields" ]; then
        log_warn "Unexpected fields in config: $unknown_fields"
        log_warn "JBrowse format may have changed"
    fi
}
```

### Phase 3: Version Checking (Future)

```bash
# Check JBrowse CLI version
JBROWSE_VERSION=$(jbrowse --version 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')

if [ "$JBROWSE_VERSION" != "2.x.x" ]; then
    log_warn "JBrowse CLI version $JBROWSE_VERSION detected"
    log_warn "Scripts tested with 2.x.x - config may differ"
fi
```

---

## Specific Recommendations

### 1. Keep Your Display Presets ✅

JBrowse CLI doesn't specify display types. Your presets are valuable:
- **BAM**: LinearAlignmentsDisplay + LinearPileupDisplay
- **BigWig**: LinearWiggleDisplay with custom colors
- **VCF**: LinearVariantDisplay

These should **always be added** regardless of CLI usage.

### 2. Keep Your Metadata System ✅

The `metadata` block is not part of JBrowse2 spec—it's your extension:
- Access control (`access_level`)
- Google Sheets integration
- File statistics
- Audit trail

This should **always be added** and is your competitive advantage.

### 3. Use CLI for Adapter Config (When Available) ✅

The adapter configuration is where JBrowse format changes would occur:
- `BamAdapter`, `BigWigAdapter`, `VcfTabixAdapter`
- `bamLocation`, `bigWigLocation`, `vcfGzLocation`
- Index locations and types

Let the CLI handle this part when available.

### 4. Add Missing CLI Features ✅

JBrowse CLI is missing:
- `indexType: "BAI"` in BAM adapter (you should add this)
- Display configuration (you have this)
- Color presets for BigWig (you have this)

These are **enhancements**, not conflicts.

---

## Testing Strategy

### Test Both Paths Work

```bash
# Test 1: CLI path
USE_CLI=1 ./add_bam_track.sh test.bam Organism Assembly

# Test 2: Custom path (simulate CLI not installed)
USE_CLI=0 ./add_bam_track.sh test.bam Organism Assembly

# Test 3: Validate both configs work in JBrowse2
./test-jbrowse-config.sh cli-generated.json
./test-jbrowse-config.sh custom-generated.json
```

### Monitor for Format Changes

Set up a test that compares CLI output over time:
```bash
# Run monthly
jbrowse add-track test.bam --assemblyNames test > latest_cli_format.json
diff latest_cli_format.json known_good_format.json || alert_admin
```

---

## Pros and Cons

### Option A: Keep Current Custom Generation
**Pros:**
- No dependencies
- Full control
- Works offline
- Tested and stable

**Cons:**
- Must manually track JBrowse format changes
- Risk of incompatibility with future versions
- Duplicates JBrowse CLI logic

### Option B: Use JBrowse CLI Only
**Pros:**
- Always compatible
- Official format
- Less maintenance

**Cons:**
- Loses metadata system
- Loses display presets
- Loses access control
- Loses Google Sheets integration
- **Not viable for MOOP**

### Option C: Hybrid Approach (RECOMMENDED) ✅
**Pros:**
- Best of both worlds
- CLI compatibility when available
- Keeps all MOOP features
- Graceful fallback
- Future-proof

**Cons:**
- Slightly more complex
- Need to maintain both paths

---

## Conclusion

**Your custom scripts are NOT wrong.** They generate valid JBrowse2 configs with significant value-added features that the CLI doesn't provide.

**The CLI should be used** for the core adapter configuration when available, but your metadata system and display presets should always be layered on top.

**Implement the hybrid approach:**
1. Detect if JBrowse CLI is installed
2. Use it for adapter config if available
3. Fall back to your custom generation if not
4. Always add your metadata layer
5. Always add your display presets
6. Add validation to detect format changes

This gives you:
- ✅ Compatibility with JBrowse format changes (via CLI)
- ✅ All your custom MOOP features (metadata, access control)
- ✅ Works with or without CLI installed
- ✅ Future-proof architecture

---

## Next Steps

1. Review this analysis
2. Decide if hybrid approach is worth the complexity
3. If yes, I can implement it in your scripts
4. If no, document the risk and monitor JBrowse releases
5. Consider creating a test suite for config validation

**Estimated Implementation Time:** 2-3 hours for hybrid approach
