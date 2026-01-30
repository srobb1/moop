# MOOP Database Tools

Database setup, analysis, and loading utilities for MOOP (Multiple Organisms One Platform).

## Important: Work Off the Server

**You do not need to run these tools on your MOOP web server.** All analysis, database creation, and loading can be done on a separate machine (local computer, HPC cluster, etc.). Once complete, simply copy the final `organism.sqlite` file to your MOOP organisms data directory.

This keeps the web server free from processing overhead and allows you to test data before deployment. See the guides below for details on deploying the final database.

## Documentation

Choose a guide based on your task:

### [DATABASE_LOADING.md](DATABASE_LOADING.md) - Creating and Loading the Database
Instructions for:
- Setting up the SQLite database
- Loading gene features from TSV files
- Loading annotations into the database

Start here if you have gene data or annotations ready to load.

### [ANALYSIS_AND_PARSING.md](ANALYSIS_AND_PARSING.md) - BLAST Homolog Analysis
Complete workflow for:
- Downloading reference protein databases (UniProt, Ensembl)
- Running DIAMOND BLASTP searches
- Parsing DIAMOND results into MOOP annotation format
- Loading annotations into the database

Start here if you want to find homologous proteins for your genes.

## Installing Conda or Mamba

You need a conda-compatible package manager **only if you want to use these database tools**. Choose from several options:

**When do you need conda/mamba?**
1. **Database loading (minimal)** - Just need Perl, DBI, DBD::SQLite (smallest install)
2. **DIAMOND analysis** - If you want to run DIAMOND BLASTP searches
3. **InterProScan** - If you want to run protein domain analysis
4. **Optional** - You can also set up your own analysis workflows and just use our database loading scripts

### Minimal Install (Recommended for Database Loading Only)

If you only need to load gene and annotation data into the database:

```bash
# Create minimal environment
mamba create -n moop-dbtools-minimal -c bioconda -c conda-forge \
  perl perl-dbi perl-dbd-sqlite

# Activate environment
mamba activate moop-dbtools-minimal
```

This minimal install is ~100 MB and includes everything needed for database creation and loading.

### Full Install (With Analysis Tools)

If you want to include DIAMOND, InterProScan, or both:

```bash
# Full environment with all tools
mamba env create -f environment.yml
mamba activate moop-dbtools
```

This install is larger (~2-3 GB) due to analysis tool databases.

**Selective Installation:**

If you want only specific tools, edit `environment.yml` before creating the environment:
- Remove the `diamond` line if you have DIAMOND installed elsewhere
- Remove the `interproscan` line if you don't need protein domain analysis (saves ~1-2 GB)

Or install/remove after creation:
```bash
# Create minimal environment first
mamba create -n moop-dbtools -c bioconda -c conda-forge perl perl-dbi perl-dbd-sqlite

# Add specific tools as needed
mamba activate moop-dbtools
mamba install -c bioconda diamond
mamba install -c bioconda interproscan

# Or remove tools to save space
mamba remove diamond interproscan
```

**Using External Tools:**

You can still use our Perl parsing scripts with tools installed elsewhere:
- Have DIAMOND on your system? Use `parse_DIAMOND_to_MOOP_TSV.pl` with your own DIAMOND installation
- Have InterProScan installed? Use `parse_InterProScan_to_MOOP_TSV.pl` with your own InterProScan
- No need for conda at all if you already have these tools!

If you already have Perl, DBI, and DBD::SQLite installed elsewhere, you don't need conda just for database loading.

**Available conda/mamba options:**
- **Miniforge** (recommended) - Lightweight, conda-based, smallest download
- Mambaforge - Lightweight, mamba-based, faster for large environments
- Anaconda - Full-featured, larger download
- Miniconda - Smaller than Anaconda, still substantial
- Micromamba - Ultra-lightweight (C++ implementation)

### Quick Install: Miniforge (Recommended)

We recommend **Miniforge** because it's the smallest lightweight option that includes conda.

**Linux/Mac:**
```bash
# Download installer
curl -L https://github.com/conda-forge/miniforge/releases/latest/download/Miniforge3-$(uname)-$(uname -m).sh -o miniforge.sh

# Run installer
bash miniforge.sh

# Follow prompts, accept license, choose install location
# Add to PATH when asked

# Verify installation
conda --version
```

**Windows:**
Download the `.exe` installer from:
https://github.com/conda-forge/miniforge/releases

Run the installer and follow the setup wizard.

**After installation:**
```bash
# Initialize shell (do this once)
conda init

# Restart your terminal, then you're ready to use conda
```

**Alternative: Mambaforge (faster for large environments)**

If you prefer faster package resolution, use Mambaforge instead:
https://github.com/conda-forge/mambaforge/releases

Use `mamba` instead of `conda` for all commands below.

## Quick Reference

### Setup (required for all tasks)

```bash
# Create the environment
conda env create -f environment.yml

# Or if you installed Mambaforge:
mamba env create -f environment.yml

# Activate the environment
conda activate moop-dbtools
```

### Common Tasks

**Convert GFF to MOOP gene format:**
```bash
perl parse_GFF3_to_MOOP_TSV.pl genomic.gff3 organisms.tsv Chamaeleo calyptratus CCA3 > genes.tsv
```

**Create empty database:**
```bash
sqlite3 organism.sqlite < create_schema_sqlite.sql
```

**Load gene data:**
```bash
perl import_genes_sqlite.pl organism.sqlite genes.tsv
```

**Load annotations:**
```bash
perl load_annotations_fast.pl organism.sqlite annotations.tsv
```

**Run DIAMOND BLAST:**
```bash
diamond blastp --ultra-sensitive --evalue 1e-5 --query proteins.fa --db ref.dmnd --out hits.tsv --outfmt 6 qseqid sseqid stitle evalue
```

**Parse DIAMOND results:**
```bash
perl parse_DIAMOND_to_MOOP_TSV.pl hits.tsv "Database Name" "2025-06-17" "http://db.url" "http://accession.url/"
```

**Run InterProScan protein domain analysis:**
```bash
interproscan.sh -i proteins.fa -f tsv -o proteins_interpro.tsv
```

**Parse InterProScan results:**
```bash
perl parse_InterProScan_to_MOOP_TSV.pl proteins_interpro.tsv
```
This automatically generates multiple MOOP-format TSV files:
- One file per analysis type (Pfam, PANTHER, InterPro, etc.)
- InterPro2GO.iprscan.moop.tsv and PANTHER2GO.iprscan.moop.tsv for Gene Ontology terms
- Detects InterProScan version automatically
- Caches GO.obo reference file for subsequent runs

## Comprehensive Workflow Examples

### Scenario 1: Loading GFF Gene Annotations + BLAST Homologs

Complete workflow from GFF file to populated database:

```bash
# 1. Create organisms metadata TSV (one-time setup for your organisms)
cat > organisms.tsv << 'EOF'
genus	species	common-name	simrbase-prefix	source	accession	ncbi-taxon-id	feature-types
Chamaeleo	calyptratus	Veiled Chameleon	CCA3	SIMR	CCA3	179908	mRNA,gene
Aeorestes	cinereus	Hoary bat	ACI1	DNAzoo	GCA_011751095.1	257879	mRNA,gene
EOF

# 2. Convert GFF to MOOP gene TSV format
perl parse_GFF3_to_MOOP_TSV.pl genomic.gff3 organisms.tsv Chamaeleo calyptratus CCA3 > genes.tsv

# 3. Create empty database
sqlite3 organism.sqlite < create_schema_sqlite.sql

# 4. Load genes into database
perl import_genes_sqlite.pl organism.sqlite genes.tsv

# 5. Run DIAMOND BLAST (optional - for homolog annotations)
diamond makedb --in uniprot_sprot.fasta --db uniprot_sprot.dmnd
diamond blastp --ultra-sensitive --evalue 1e-5 \
  --query proteins.fa --db uniprot_sprot.dmnd \
  --out tophit.tsv --max-target-seqs 1 \
  --outfmt 6 qseqid sseqid stitle evalue

# 6. Parse DIAMOND results to MOOP format
perl parse_DIAMOND_to_MOOP_TSV.pl tophit.tsv "UniProtKB/Swiss-Prot" "2025-06-17" \
  "https://www.uniprot.org" "https://www.uniprot.org/uniprotkb/"

# 7. Load annotations into database
perl load_annotations_fast.pl organism.sqlite UniProtKB_Swiss-Prot.homologs.moop.tsv

# 8. Copy to MOOP server
scp organism.sqlite user@moop-server:/path/to/organisms/data/
```

### Scenario 2: Multiple Organisms in Single Batch

Process multiple organisms from one organizations TSV:

```bash
# organisms.tsv contains 10 organisms
# Process each one:

for organism in "Chamaeleo calyptratus CCA3" "Aeorestes cinereus GCA_011751095.1"; do
  read genus species accession <<< "$organism"
  
  # Convert GFF to MOOP format
  perl parse_GFF3_to_MOOP_TSV.pl ${genus}_${species}.gff3 organisms.tsv \
    $genus $species $accession > ${genus}_${species}.genes.tsv
  
  # Load into database
  perl import_genes_sqlite.pl organism.sqlite ${genus}_${species}.genes.tsv
done
```

### Scenario 3: Test Data Included

Test the tools with included sample data:

```bash
cd test_data

# View organisms metadata
cat organisms.tsv

# Extract features from test GFF file
perl ../parse_GFF3_to_MOOP_TSV.pl genomic.gff3 organisms.tsv Chamaeleo calyptratus CCA3

# Create database and load genes
sqlite3 test_organism.sqlite < ../create_schema_sqlite.sql
perl ../parse_GFF3_to_MOOP_TSV.pl genomic.gff3 organisms.tsv Chamaeleo calyptratus CCA3 > features.tsv
perl ../import_genes_sqlite.pl test_organism.sqlite features.tsv

# Verify database loaded
sqlite3 test_organism.sqlite "SELECT COUNT(*) FROM feature;"
```

## Scripts Overview

| Script | Purpose | Input | Output |
|--------|---------|-------|--------|
| `parse_GFF3_to_MOOP_TSV.pl` | Convert GFF to MOOP format | GFF3 + organisms.tsv | genes.tsv with headers |
| `import_genes_sqlite.pl` | Load gene features | genes.tsv | Updated organism.sqlite |
| `load_annotations_fast.pl` | Load annotations | annotations.tsv | Updated organism.sqlite |
| `parse_DIAMOND_to_MOOP_TSV.pl` | Convert DIAMOND output | tophit.tsv | `*_homologs.moop.tsv` |
| `create_schema_sqlite.sql` | Database schema | - | organism.sqlite |
| `setup_new_db_and_load_data_fast_per_org.sh` | Orchestrate loading | - | Runs all steps |

## Requirements

All installed in the conda environment:
- Perl 5.10+
- DBI module
- DBD::SQLite module  
- DIAMOND sequence search tool

## Deactivating the Environment

When done:

```bash
conda deactivate
```

## Contributing

For issues or improvements, please open an issue or pull request.

