# JBrowse2 Configuration

## Overview

JBrowse2 requires a `config.json` file that tells it which assemblies and tracks are available. This file is located at:
```
/var/www/html/moop/jbrowse2/config.json
```

## Configuration Structure

The config.json file contains:
- **assemblies**: List of available genome assemblies with sequence sources
- **tracks**: List of data tracks (BigWig, BAM, VCF, etc.)
- **configuration**: Feature flags and settings
- **defaultSession**: Initial session configuration

## Important: Using Correct URI Paths

All FASTA file paths must be **web-accessible** paths from the root of the web server:

### ❌ WRONG (Filesystem paths)
```json
{
  "fastaLocation": {
    "uri": "/data/moop/data/genomes/Organism/Assembly/reference.fasta"
  }
}
```

### ✅ CORRECT (Web paths relative to server root)
```json
{
  "fastaLocation": {
    "uri": "/moop/data/genomes/Organism/Assembly/reference.fasta"
  }
}
```

The web server serves `/var/www/html/` as root, and MOOP is at `/var/www/html/moop/`, so:
- Filesystem: `/var/www/html/moop/data/genomes/...`
- Web URL: `/moop/data/genomes/...`

## Example Config

```json
{
  "assemblies": [
    {
      "name": "Anoura_caudifer_GCA_004027475.1",
      "displayName": "Anoura_caudifer (GCA_004027475.1)",
      "aliases": ["ACA1", "GCA_004027475.1"],
      "sequence": {
        "type": "ReferenceSequenceTrack",
        "trackId": "Anoura_caudifer_GCA_004027475.1-ReferenceSequenceTrack",
        "adapter": {
          "type": "IndexedFastaAdapter",
          "fastaLocation": {
            "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta",
            "locationType": "UriLocation"
          },
          "faiLocation": {
            "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta.fai",
            "locationType": "UriLocation"
          }
        }
      }
    }
  ],
  "configuration": {},
  "connections": [],
  "defaultSession": {
    "name": "New Session"
  },
  "tracks": []
}
```

## Creating/Updating config.json

The `config.json` is created manually. When you add a new assembly:

1. **Register assembly** using the setup script:
   ```bash
   ./tools/jbrowse/add_assembly_to_jbrowse.sh Organism Assembly
   ```
   This creates the assembly definition file in metadata.

2. **Update config.json** by adding the assembly to the static config:
   - Copy the assembly definition
   - Make sure URI paths use `/moop/data/genomes/...` format
   - Add to the `assemblies` array in config.json

3. **Verify** the config is valid JSON:
   ```bash
   jq . /var/www/html/moop/jbrowse2/config.json
   ```

## URI Path Reference

When adding assemblies to config.json:

| Item | Filesystem Path | Web URI |
|------|-----------------|---------|
| Reference FASTA | `/var/www/html/moop/data/genomes/{ORG}/{ASSEMBLY}/reference.fasta` | `/moop/data/genomes/{ORG}/{ASSEMBLY}/reference.fasta` |
| FASTA Index | `/var/www/html/moop/data/genomes/{ORG}/{ASSEMBLY}/reference.fasta.fai` | `/moop/data/genomes/{ORG}/{ASSEMBLY}/reference.fasta.fai` |
| BigWig Tracks | `/var/www/html/moop/data/tracks/{ORG}_{ASSEMBLY}_{NAME}.bw` | `/moop/data/tracks/{ORG}_{ASSEMBLY}_{NAME}.bw` |
| BAM Files | `/var/www/html/moop/data/tracks/{ORG}_{ASSEMBLY}_{NAME}.bam` | `/moop/data/tracks/{ORG}_{ASSEMBLY}_{NAME}.bam` |

## Troubleshooting

### "HTTP 404 fetching /data/moop/data/genomes/..."
**Problem**: URIs in config.json are using filesystem paths instead of web paths
**Solution**: Change `/data/moop/data/genomes/...` to `/moop/data/genomes/...`

### "HTTP 404 fetching /moop/data/genomes/..."
**Problem**: Files don't exist at that location
**Solution**: 
- Check files exist: `ls -la /var/www/html/moop/data/genomes/Organism/Assembly/`
- Verify symbolic links: `ls -l /var/www/html/moop/data/genomes/Organism/Assembly/reference.fasta`

### Assembly won't load in JBrowse2
**Problem**: Assembly in config.json but not displaying
**Cause**: Missing required files or invalid JSON
**Solution**:
- Validate JSON: `jq . /var/www/html/moop/jbrowse2/config.json`
- Check browser console for errors (F12)
- Check server logs: `tail /var/log/apache2/error.log`

## See Also

- `/data/moop/tools/jbrowse/` - Assembly setup scripts
- `/data/moop/docs/JBrowse2/ASSEMBLY_BULK_LOAD_GUIDE.md` - Bulk loading guide
- `/data/moop/api/jbrowse2/` - Dynamic API endpoints
