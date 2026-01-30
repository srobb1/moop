# BLAST Homolog Analysis and Parsing

Complete workflow for running DIAMOND BLAST searches and parsing results into MOOP format.

## Important: Off-Server Analysis

**All analysis, database creation, and loading can be done on a separate machine.** You do not need to run DIAMOND, Perl scripts, or SQLite operations on the MOOP web server.

Typical workflow:
1. **Local/HPC machine:** Download databases, run DIAMOND BLAST, parse results
2. **Local/HPC machine:** Create SQLite database and load all data
3. **MOOP server:** Copy the final `organism.sqlite` file to your organisms data directory

The only requirement on the MOOP server is the final SQLite database file in the correct location. For configuration details and the data directory location, see the MOOP main documentation.

This approach offers several advantages:
- Faster analysis on HPC clusters or local machines
- No resource overhead on the web server
- Easy to version control and backup database creation workflows
- Can be run on machines with DIAMOND/Perl installed without affecting production

## Overview

DIAMOND is a high-performance sequence similarity search tool that can work with large protein databases. This workflow allows you to:
1. Download and prepare protein databases (UniProtKB/Swiss-Prot or Ensembl)
2. Format databases for DIAMOND
3. Run DIAMOND BLASTP searches
4. Parse results into MOOP annotation format
5. Load annotations into the database

## Prerequisites

### Environment Setup

If you don't have conda installed, [install Miniconda](https://docs.conda.io/en/latest/miniconda.html).

```bash
# Create the environment from environment.yml
conda env create -f environment.yml

# Activate the environment
conda activate moop-dbtools
```

### Tools and Data Required

- DIAMOND (included in conda environment)
- `parse_DIAMOND_to_MOOP_TSV.pl` script
- Your organism protein sequences (FASTA format)
- Reference protein database (UniProt, Ensembl, or custom)

## Step 1: Download Protein Databases

### Option A: UniProtKB/Swiss-Prot

Download the canonical protein sequences from UniProt FTP server:

```bash
# Download UniProtKB/Swiss-Prot (canonical sequences only, ~500 MB compressed)
wget https://ftp.uniprot.org/pub/databases/uniprot/current_release/knowledgebase/complete/uniprot_sprot.fasta.gz

# Or using curl
curl -O https://ftp.uniprot.org/pub/databases/uniprot/current_release/knowledgebase/complete/uniprot_sprot.fasta.gz

# Optional: Decompress (only if you want to inspect the file)
gunzip uniprot_sprot.fasta.gz
```

**Note:** You don't need to decompress the `.gz` file. DIAMOND can work directly with gzip-compressed FASTA files. This saves disk space and time:

```bash
# You can use the .gz file directly with DIAMOND
diamond makedb --in uniprot_sprot.fasta.gz --db uniprot_sprot.dmnd
```

**Finding the version:**
Check the current release at: https://www.uniprot.org/help/downloads

You'll need the release date (e.g., "2025-06-17") for the metadata.

### Option B: Ensembl Protein Databases

Ensembl provides proteomes for many species across multiple releases. Here's how to navigate and download:

**Step 1: Browse available species**

See which organisms are available in Ensembl:
https://www.ensembl.org/info/about/species.html

**Step 2: Browse available releases**

Navigate to the Ensembl FTP server and browse available releases:
https://ftp.ensembl.org/pub/

Releases are named `release-XXX` where XXX is the release number (e.g., `release-115`, `release-114`).

**Step 3: Navigate to a specific release and species**

Once you've chosen a release (e.g., release 115) and species (e.g., Homo sapiens):

```
https://ftp.ensembl.org/pub/release-115/fasta/homo_sapiens/pep/
```

**Step 4: Download the proteome**

Example: Human proteome for Ensembl release 115

```bash
# Download using curl (recommended)
curl -OL https://ftp.ensembl.org/pub/release-115/fasta/homo_sapiens/pep/Homo_sapiens.GRCh38.pep.all.fa.gz

# Or using wget
wget https://ftp.ensembl.org/pub/release-115/fasta/homo_sapiens/pep/Homo_sapiens.GRCh38.pep.all.fa.gz

# Optional: Decompress (only if you want to inspect the file)
gunzip Homo_sapiens.GRCh38.pep.all.fa.gz
```

**Note:** You don't need to decompress the `.gz` file. DIAMOND can work directly with gzip-compressed FASTA files. This saves disk space and time:

```bash
# You can use the .gz file directly with DIAMOND
diamond makedb --in Homo_sapiens.GRCh38.pep.all.fa.gz --db ensembl_human.dmnd
```

**Finding files for other species:**

Browse the directory structure:
- Base FTP: https://ftp.ensembl.org/pub/
- Release folder: https://ftp.ensembl.org/pub/release-115/
- FASTA files: https://ftp.ensembl.org/pub/release-115/fasta/
- Species folder: https://ftp.ensembl.org/pub/release-115/fasta/homo_sapiens/
- Protein files: https://ftp.ensembl.org/pub/release-115/fasta/homo_sapiens/pep/

Replace `release-115` with your desired release and `homo_sapiens` with your species of interest.

### Option C: Custom Database

You can use any FASTA file of protein sequences. Ensure it's in standard FASTA format:
```
>protein_id description
MKTAYIAKQRQISFVKSHFSRQLEERLGLIEVQAPILSRVGDGTQDNLSGAEKAVQ
VKVKALPDAQFEVVHSLAKWKRQTLGQHDFSAGEGLYTHMKALRPDEDRLSPLHS
```

## Step 2: Format Database for DIAMOND

DIAMOND requires indexing the database before use. The database should be a FASTA file of protein sequences.

### Input file requirements

Your FASTA file should be in standard protein sequence format:
```
>protein_id description text
MKTAYIAKQRQISFVKSHFSRQLEERLGLIEVQAPILSRVGDGTQDNLSGAEKAVQ
VKVKALPDAQFEVVHSLAKWKRQTLGQHDFSAGEGLYTHMKALRPDEDRLSPLHS
>another_protein additional description
MKPQLVKASQVFLKQAFSLDEQHQQLSRDQRWRYTDEEFRPPNMRKVSALAQNVDK
```

**Key points:**
- Each sequence starts with `>` header line
- Header format: `>identifier description` (everything after the first space is description)
- Protein sequences on following lines (can be split across multiple lines)
- One or more sequences per file

### Index the database

Create DIAMOND-indexed databases from your FASTA files:

```bash
# Index UniProt Swiss-Prot database
diamond makedb --in uniprot_sprot.fasta --db uniprot_sprot.dmnd

# Index Ensembl human proteome
diamond makedb --in Homo_sapiens.GRCh38.pep.all.fa --db ensembl_human.dmnd

# Index custom database
diamond makedb --in your_proteins.fasta --db your_proteins.dmnd
```

This creates `.dmnd` binary index files that DIAMOND uses for fast searching.

**Time estimates:**
- Swiss-Prot: ~2-3 minutes
- Ensembl human: ~5-10 minutes
- Custom databases: Depends on size

**Note:** You only need to create the index once. Reuse the `.dmnd` file for multiple searches.

## Step 3: Run DIAMOND BLASTP Search

Once you have indexed databases, you can search your protein sequences against them.

### Your query file

Create or prepare a FASTA file with your organism's protein sequences:

```bash
# Check your query file format
head -20 your_proteins.fa
```

Should look like:
```
>PROTEIN_001 gene description
MKTAYIAKQRQISFVKSHFSRQLEERLGLIEVQAPILSRVGDGTQDNLSGAEKAVQ
VKVKALPDAQFEVVHSLAKWKRQTLGQHDFSAGEGLYTHMKALRPDEDRLSPLHS
>PROTEIN_002 another gene
MKPQLVKASQVFLKQAFSLDEQHQQLSRDQRWRYTDEEFRPPNMRKVSALAQNVDK
```

### Run DIAMOND BLASTP

Search your proteins against the reference database:

```bash
# Ultra-sensitive search with e-value threshold and top hit per query
diamond blastp \
  --ultra-sensitive \
  --evalue 1e-5 \
  --query your_proteins.fa \
  --db uniprot_sprot.dmnd \
  --out tophit.tsv \
  --max-target-seqs 1 \
  --outfmt 6 qseqid sseqid stitle evalue
```

**Parameters explained:**

- `--ultra-sensitive` - Most sensitive search mode (slowest, most hits)
  - Other options: `--sensitive`, `--fast`, `--faster` (trade speed for sensitivity)
- `--evalue 1e-5` - E-value threshold (lower = more stringent, fewer hits)
  - Common values: `1e-5`, `1e-10`, `1e-50`
  - Adjust based on your data and expected divergence from reference
- `--query your_proteins.fa` - Your organism's protein FASTA file (required)
- `--db uniprot_sprot.dmnd` - DIAMOND-indexed database (required)
- `--out tophit.tsv` - Output filename (will be overwritten if exists)
- `--max-target-seqs 1` - Keep only the top hit per query
  - Set higher (e.g., `--max-target-seqs 10`) to keep multiple hits
- `--outfmt 6 qseqid sseqid stitle evalue` - Required output format with these columns:
  - `qseqid` - Query sequence ID
  - `sseqid` - Subject (hit) sequence ID
  - `stitle` - Subject full description/title
  - `evalue` - E-value of the match

### Output format

The output file (tophit.tsv) is tab-delimited with 4 columns:
```
query_id         hit_id                  hit_description                              evalue
PROTEIN_001      Q9BWM5                  Zinc finger protein 416 OS=Homo sapiens...   3.94e-110
PROTEIN_002      sp|Q16342|PDCD2_HUMAN   sp|Q16342|PDCD2_HUMAN Programmed cell...    2.04e-210
```

### Performance optimization

For large databases or many queries, optimize speed:

```bash
# Faster search (less sensitive)
diamond blastp \
  --sensitive \
  --threads 8 \
  --evalue 1e-5 \
  --query your_proteins.fa \
  --db uniprot_sprot.dmnd \
  --out tophit.tsv \
  --max-target-seqs 1 \
  --outfmt 6 qseqid sseqid stitle evalue
```

**Performance tips:**
- Use `--sensitive` instead of `--ultra-sensitive` for ~3-5x speedup
- Use `--max-target-seqs 1` to search only for top hit (faster)
- Increase `--evalue` threshold to 1e-3 for looser matches (much faster)
- Use `--threads N` to parallelize (e.g., `--threads 8` for 8 CPU cores)
- Use `--block-size N` to adjust memory usage (higher = faster, more RAM)

**Example: Fast search with 8 threads**
```bash
diamond blastp \
  --sensitive \
  --threads 8 \
  --block-size 10 \
  --evalue 1e-5 \
  --query your_proteins.fa \
  --db uniprot_sprot.dmnd \
  --out tophit.tsv \
  --max-target-seqs 1 \
  --outfmt 6 qseqid sseqid stitle evalue
```

## Step 4: Parse DIAMOND Results to MOOP Format

The `parse_DIAMOND_to_MOOP_TSV.pl` script converts raw DIAMOND output into MOOP annotation format.

### UniProtKB/Swiss-Prot Example

```bash
perl parse_DIAMOND_to_MOOP_TSV.pl \
  tophit.tsv \
  'UniProtKB/Swiss-Prot' \
  '2025-06-17' \
  'https://www.uniprot.org' \
  'https://www.uniprot.org/uniprotkb/'
```

This generates: `UniProtKB_Swiss-Prot.homologs.moop.tsv`

### Ensembl Human Example

```bash
perl parse_DIAMOND_to_MOOP_TSV.pl \
  tophit.tsv \
  'Ensembl Homo sapiens' \
  '2025-06-17' \
  'https://www.ensembl.org/' \
  'https://www.ensembl.org/Multi/Search/Results?q='
```

This generates: `Ensembl_Homo_sapiens.homologs.moop.tsv`

### Script Parameters

1. **tophit.tsv** - DIAMOND output file (must have 4 tab-delimited columns)
2. **source** - Database source name (appears in annotation metadata)
   - Examples: "UniProtKB/Swiss-Prot", "Ensembl Homo sapiens", "Custom DB"
3. **version** - Database version/release date (YYYY-MM-DD format)
   - Examples: "2025-06-17", "release-115"
4. **source_url** - Database homepage URL
   - Example: "https://www.uniprot.org"
5. **annotation_url** - URL prefix for individual record lookup
   - Example: "https://www.uniprot.org/uniprotkb/" (accession appended: .../uniprotkb/Q9BWM5)

### Script Features

The parser handles multiple database formats automatically:

**Swiss-Prot format:**
- Input: `sp|ACCESSION|ID description text`
- Extracted: Accession = `ACCESSION`, Description = `ID: description text`

**Ensembl format:**
- Input: `ENSPXP... pep ... gene_symbol:SYMBOL description:DESC [Source:...]`
- Extracted: Accession = `ENSPXP...`, Description = `SYMBOL: DESC`

**Simple format:**
- Input: `ACCESSION description text`
- Extracted: As-is (no parsing needed)

**Script behavior:**
- Validates input file exists and is readable
- Skips malformed lines with warnings (doesn't crash)
- Reports line numbers for easy debugging
- Uses file modification date for annotation creation date
- Shows summary statistics (lines read, records parsed, errors)

### Example Output

```
## Annotation Source: UniProtKB/Swiss-Prot
## Annotation Source Version: 2025-06-17
## Annotation Source URL: https://www.uniprot.org
## Annotation Accession URL: https://www.uniprot.org/uniprotkb/
## Annotation Type: Homologs
## Annotation Creation Date: 2025-06-17
## Gene    Accession    Accession_Description    Score
PROTEIN_001    Q9BWM5    Zinc finger protein 416    3.94e-110
PROTEIN_002    Q16342    Programmed cell death protein 2    2.04e-210
PROTEIN_003    HRH2    Histamine receptor H2    1.5e-95
```

## Step 5: Load Homolog Annotations into Database

Once you have parsed MOOP-format annotations, load them into the database:

```bash
perl load_annotations_fast.pl organism.sqlite UniProtKB_Swiss-Prot.homologs.moop.tsv
```

The annotations are now searchable in MOOP with full provenance tracking (source, version, and URLs).

You can load multiple annotation sources into the same database:
```bash
# Load Swiss-Prot homologs
perl load_annotations_fast.pl organism.sqlite UniProtKB_Swiss-Prot.homologs.moop.tsv

# Load Ensembl human homologs
perl load_annotations_fast.pl organism.sqlite Ensembl_Homo_sapiens.homologs.moop.tsv
```

## Troubleshooting

### DIAMOND: "database not found"
Make sure you ran `diamond makedb` to index the FASTA file first:
```bash
diamond makedb --in your_file.fasta --db your_file.dmnd
```

### DIAMOND: "Invalid query file format"
Ensure your query file is valid FASTA format (sequences start with `>` headers):
```bash
# Check first few lines
head -20 your_query.fa
```

### Parser: "Missing required arguments"
Check that you provided exactly 5 arguments:
```bash
perl parse_DIAMOND_to_MOOP_TSV.pl <file> <source> <version> <url> <accession_url>
```

### Parser: Input file issues
The parser validates:
- File exists and is readable
- Each line has exactly 4 tab-delimited columns
- Required fields (query ID, hit ID, score) are non-empty

Check the output for line numbers and field counts of problematic lines.

### DIAMOND slow performance
- Try less-sensitive modes: `--sensitive` instead of `--ultra-sensitive`
- Use higher e-value threshold (less stringent): `--evalue 1e-3` instead of `--evalue 1e-10`
- Reduce database size by searching only relevant sequences

## Advanced Usage

### Iterative refinement
Run DIAMOND multiple times with different parameters:
```bash
# First pass: find all potential homologs (loose threshold)
diamond blastp --evalue 0.01 --query proteins.fa --db ref.dmnd --out pass1.tsv ...

# Second pass: stricter search on best hits (tight threshold)
diamond blastp --evalue 1e-10 --query proteins.fa --db ref.dmnd --out pass2.tsv ...
```

### Search against multiple databases
```bash
# UniProt Swiss-Prot
diamond blastp --query proteins.fa --db uniprot_sprot.dmnd --out uniprot.tsv ...
perl parse_DIAMOND_to_MOOP_TSV.pl uniprot.tsv "UniProtKB/Swiss-Prot" "2025-06-17" ...

# Ensembl human
diamond blastp --query proteins.fa --db ensembl_human.dmnd --out ensembl.tsv ...
perl parse_DIAMOND_to_MOOP_TSV.pl ensembl.tsv "Ensembl Homo sapiens" "2025-06-17" ...

# Load both into database
perl load_annotations_fast.pl organism.sqlite UniProtKB_Swiss_Prot.homologs.moop.tsv
perl load_annotations_fast.pl organism.sqlite Ensembl_Homo_sapiens.homologs.moop.tsv
```

## See Also

- [DATABASE_LOADING.md](DATABASE_LOADING.md) - For loading genes and annotations into the database
