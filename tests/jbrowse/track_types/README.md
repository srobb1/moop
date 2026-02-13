# Track Type Test Files

This directory contains test files for the new JBrowse track types: PAF, MAF, PIF, and MCScan.

## Test Files

### Single Assembly Tracks
- `test.paf` - PAF alignment format (long-read alignments)
- `test.maf.gz` + `test.maf.gz.gzi` - MAF multiple alignment format (compressed with bgzip)

### Dual Assembly Synteny Tracks
- `test.pif.gz` + `test.pif.gz.tbi` - Pairwise Indexed PAF for whole genome synteny
- `test.anchors` + `anoura.bed` + `nvec.bed` - MCScan orthologs with BED files

## Assembly ID Requirements

**CRITICAL**: For synteny tracks (PIF and MCScan), the `assembly1` and `assembly2` fields must match **actual assembly names** that exist in the system.

### Finding Valid Assembly Names

```bash
# List available assemblies
ls /data/moop/metadata/jbrowse2-configs/assemblies/*.json

# Extract assembly names
jq -r '.name' /data/moop/metadata/jbrowse2-configs/assemblies/*.json
```

### Current Valid Assemblies (as of 2026-02-13)

1. `Anoura_caudifer_GCA_004027475.1`
2. `Nematostella_vectensis_GCA_033964005.1`

### Assembly Name Format

Assembly names follow the pattern: `{Organism}_{assemblyId}`

Example:
- Organism: `Nematostella_vectensis`
- Assembly ID: `GCA_033964005.1`
- Full assembly name: `Nematostella_vectensis_GCA_033964005.1`

## Google Sheet Format

### PAF Track (Single Assembly)
```
track_idnamecategorytrack_pathaccess_levelorganismassembly
test_pafPAF ReadsLong-read/path/to/file.pafPUBLICNematostella_vectensisGCA_033964005.1
```

### MAF Track (Single Assembly)
```
track_idnamecategorytrack_pathaccess_levelorganismassembly
test_mafConservationConservation/path/to/file.maf.gzPUBLICNematostella_vectensisGCA_033964005.1
```

### PIF Track (Dual Assembly)
```
track_idnamecategorytrack_pathaccess_levelorganismassemblyassembly1assembly2
test_pifSyntenySynteny/path/to/file.pif.gzPUBLICNematostella_vectensisGCA_033964005.1Anoura_caudifer_GCA_004027475.1Nematostella_vectensis_GCA_033964005.1
```

**Note**: 
- `assembly` = primary assembly (where track is stored)
- `assembly1` = first assembly in comparison (must be full name)
- `assembly2` = second assembly in comparison (must be full name)

### MCScan Track (Dual Assembly + BED files)
```
track_idnamecategorytrack_pathaccess_levelorganismassemblyassembly1assembly2bed1_pathbed2_path
test_mcscanOrthologsSynteny/path/to/file.anchorsPUBLICNematostella_vectensisGCA_033964005.1Anoura_caudifer_GCA_004027475.1Nematostella_vectensis_GCA_033964005.1/path/to/asm1.bed/path/to/asm2.bed
```

## Validation

Run the test script to validate:

```bash
cd /data/moop/tests/jbrowse/track_types
php test_validation_real.php
```

## Common Errors

### Assembly Not Found
```
Error: assembly1 'GCA_000001' not found in system
```
**Solution**: Use full assembly name like `Nematostella_vectensis_GCA_033964005.1`

### Assembly Mismatch
If assembly IDs don't match, JBrowse will fail to load the synteny track because it can't find the referenced assemblies.

## JBrowse2 Config Structure

The generated tracks will reference assemblies in the `assemblyNames` array:

```json
{
  "type": "SyntenyTrack",
  "assemblyNames": [
    "Nematostella_vectensis_GCA_033964005.1",
    "Anoura_caudifer_GCA_004027475.1"
  ],
  ...
}
```

Both assemblies must exist in the JBrowse2 configuration for the track to load properly.
