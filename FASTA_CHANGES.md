# FASTA File Handling Updates

## Changes Made

### 1. File Renaming
All FASTA files have been renamed from their long format (with organism, assembly, and genome description) to a simple pattern-only format:

**Old format:**
- `Anoura_caudifer.GCA_004027475.1_AnoCau_v1_BIUU_genomic.cds.nt.fa`
- `Lasiurus_cinereus.GCA_011751065.1.protein.aa.fa`

**New format:**
- `cds.nt.fa`
- `protein.aa.fa`
- `transcript.nt.fa`
- `genome.nt.fa` (optional)

Files renamed:
- `/var/www/html/organisms_data/Anoura_caudifer/GCA_004027475.1/cds.nt.fa`
- `/var/www/html/organisms_data/Anoura_caudifer/GCA_004027475.1/protein.aa.fa`
- `/var/www/html/organisms_data/Anoura_caudifer/GCA_004027475.1/transcript.nt.fa`
- `/var/www/html/organisms_data/Lasiurus_cinereus/GCA_011751095.1/cds.nt.fa`
- `/var/www/html/organisms_data/Lasiurus_cinereus/GCA_011751095.1/protein.aa.fa`
- `/var/www/html/organisms_data/Lasiurus_cinereus/GCA_011751095.1/transcript.nt.fa`
- `/var/www/html/organisms_data/Montipora_capitata/HIv3/cds.nt.fa`
- `/var/www/html/organisms_data/Montipora_capitata/HIv3/protein.aa.fa`

### 2. BLAST Database Rebuilding
All BLAST database index files have been rebuilt to match the new simplified filenames:
- Old indices (`.ndb`, `.nhr`, `.nin`, etc.) with long names were replaced
- New indices created for files named `cds.nt.fa`, `protein.aa.fa`, `transcript.nt.fa`
- Blastdbcmd now works correctly with the simplified filenames

### 3. Whole FASTA File Download Handler
Created new dedicated download handler at `tools/fasta_download_handler.php`:

**Purpose:** Serves complete FASTA files with organism and assembly information prepended to filename

**URL format:**
```
/moop/tools/fasta_download_handler.php?organism=Org_name&assembly=GCA_xxx&type=cds
```

**Downloaded filename format:**
```
{organism}.{assembly}.{pattern}
```

**Examples:**
- CDS: `Anoura_caudifer.GCA_004027475.1.cds.nt.fa`
- Protein: `Anoura_caudifer.GCA_004027475.1.protein.aa.fa`
- Transcript: `Anoura_caudifer.GCA_004027475.1.transcript.nt.fa`

**Integration points:**
- `tools/display/assembly_display.php` - Download buttons on assembly page
- `tools/display/organism_display.php` - Download buttons on organism page

### 4. Sequence Extraction Downloads (Unchanged)
The existing sequence extraction tools remain unchanged:
- `tools/extract/download_fasta.php` - Manual search and download tool
- `tools/extract/fasta_extract.php` - Legacy extraction tool
- Downloads from these tools keep timestamp format: `sequences_cds_2025-11-12_224500.fasta`

## Configuration
The patterns are defined in `site_config.php`:
```php
$sequence_types = [
    'protein' => ['pattern' => 'protein.aa.fa', 'label' => 'Protein'],
    'transcript' => ['pattern' => 'transcript.nt.fa', 'label' => 'mRNA'],
    'cds' => ['pattern' => 'cds.nt.fa', 'label' => 'CDS']
];
```

## Utility Scripts
A utility script is available at `tools/rename_fasta_files.php` for renaming any future files:
- Usage: `php rename_fasta_files.php [--dry-run] [--organism=name]`
- Dry run mode to preview changes before executing

## Benefits
1. **Simplicity**: Cleaner, more predictable file names
2. **Consistency**: Pattern matching works reliably across all code
3. **Informative downloads**: Users know exactly which organism and assembly they're downloading from
4. **Maintainability**: Reduced code complexity for file discovery
5. **Separation of concerns**: Dedicated handler for whole-file downloads vs. sequence extraction

## Important Admin Notes
When adding new FASTA files to an assembly:
1. Name files using the pattern: `cds.nt.fa`, `protein.aa.fa`, `transcript.nt.fa`, `genome.nt.fa`
2. **IMPORTANT:** Create BLAST database indices using `makeblastdb`:
   ```bash
   makeblastdb -in cds.nt.fa -dbtype nucl -out cds.nt.fa -parse_seqids
   makeblastdb -in protein.aa.fa -dbtype prot -out protein.aa.fa -parse_seqids
   makeblastdb -in transcript.nt.fa -dbtype nucl -out transcript.nt.fa -parse_seqids
   ```
3. Ensure proper file permissions (readable by web server)

