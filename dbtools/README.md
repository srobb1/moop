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

## Custom Analysis Formats

**You are not limited to DIAMOND and InterProScan!** Any analysis tool output can be formatted to load into MOOP as long as it follows the MOOP annotation format rules:

### Required Format for Annotations

All annotation TSV files must have:

**1. Metadata Headers (lines starting with `##`)**
```
## Annotation Source: Your Tool Name
## Annotation Source Version: 1.0.0
## Annotation Accession URL: https://example.com/
## Annotation Source URL: https://example.com/
## Annotation Type: Protein Domains | Gene Families | Gene Ontology | Custom
## Annotation Creation Date: 2025-01-30
```

**2. Tab-Delimited Data Format**

Column order matters. Column headers can be anything, but data must follow this format:

```
## Gene    Accession    Description    Score
feature_uniquename    hit_id    hit_description    score_value
```

Where:
- **Column 1 (feature_uniquename):** Must match `This_Uniquename` from your genes.tsv file
- **Column 2 (hit_id):** Accession/ID for the hit in the reference database
- **Column 3 (hit_description):** Text description of the hit
- **Column 4 (score):** Numerical score, e-value, confidence, or other assessment metric

### Example Custom Annotation Format

```
## Annotation Source: MyProteinPredictor
## Annotation Source Version: 2.5.1
## Annotation Accession URL: https://myserver.org/protein/
## Annotation Source URL: https://myserver.org/
## Annotation Type: Protein Families
## Annotation Creation Date: 2025-01-30
## MyGene    PredictorHit    FamilyInfo    Confidence
CCA3t004839001.1    PRED001    Family_ABC    0.95
CCA3t004843001.1    PRED002    Family_XYZ    0.87
CCA3t004844001.1    PRED003    Family_ABC    0.92
```

### How to Load Your Custom Annotations

Once formatted correctly:

```bash
# Load your custom annotations into the organism database
perl load_annotations_fast.pl Chamaeleo_calyptratus.sqlite MyProteinPredictor.moop.tsv
```

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

**Key Concept:** Each organism gets its own SQLite database file

**Directory Structure:**
```
/var/www/html/moop/dbtools/
├── test_data/                          # Input test files (GFF3, organisms.tsv)
├── test_output/                        # Output directory for test workflows (gitignored)
├── parse_*.pl                          # Parser scripts
├── import_genes_sqlite.pl              # Database loader
└── load_annotations_fast.pl            # Annotation loader

Output databases created by workflows:
├── test_output/Chamaeleo_calyptratus.sqlite
├── test_output/Aeorestes_cinereus.sqlite
└── ... (one per organism)
```

### Scenario 1: Complete Workflow with Test Data

**Working directory:** `/var/www/html/moop/dbtools/test_output`

Complete workflow from GFF file to organism-specific database using included test data:

```bash
cd /var/www/html/moop/dbtools/test_output

# 1. Convert GFF3 to MOOP gene TSV format using test data
perl ../parse_GFF3_to_MOOP_TSV.pl ../test_data/genomic.gff3 ../test_data/organisms.tsv \
  Chamaeleo calyptratus CCA3 > Chamaeleo_calyptratus.genes.tsv

# 2. Create organism-specific database
sqlite3 Chamaeleo_calyptratus.sqlite < ../create_schema_sqlite.sql

# 3. Load genes into the database
perl ../import_genes_sqlite.pl Chamaeleo_calyptratus.sqlite Chamaeleo_calyptratus.genes.tsv

# 4. Verify genes loaded
sqlite3 Chamaeleo_calyptratus.sqlite "SELECT COUNT(*) FROM feature;"

# (Optional) If you have InterProScan results:
# perl ../parse_InterProScan_to_MOOP_TSV.pl interpro_results.tsv
# perl ../load_annotations_fast.pl Chamaeleo_calyptratus.sqlite PANTHER.iprscan.moop.tsv
```

### Scenario 1b: Production Workflow with DIAMOND

**For production use with your own data (one database per organism):**

```bash
cd /var/www/html/moop/dbtools

# 1. Create organisms metadata TSV (one-time setup)
cat > my_organisms.tsv << 'EOF'
genus	species	common-name	simrbase-prefix	source	accession	ncbi-taxon-id	feature-types
Chamaeleo	calyptratus	Veiled Chameleon	CCA3	SIMR	CCA3	179908	mRNA,gene
Aeorestes	cinereus	Hoary bat	ACI1	DNAzoo	GCA_011751095.1	257879	mRNA,gene
EOF

# 2. Convert GFF3 to MOOP gene TSV format
perl parse_GFF3_to_MOOP_TSV.pl your_genome.gff3 my_organisms.tsv \
  Chamaeleo calyptratus CCA3 > Chamaeleo_calyptratus.genes.tsv

# 3. Create organism-specific database
sqlite3 Chamaeleo_calyptratus.sqlite < create_schema_sqlite.sql

# 4. Load genes into the database
perl import_genes_sqlite.pl Chamaeleo_calyptratus.sqlite Chamaeleo_calyptratus.genes.tsv

# 5. Run DIAMOND BLAST (optional - for homolog annotations)
diamond makedb --in uniprot_sprot.fasta --db uniprot_sprot.dmnd
diamond blastp --ultra-sensitive --evalue 1e-5 \
  --query proteins.fa --db uniprot_sprot.dmnd \
  --out Chamaeleo_calyptratus.tophit.tsv --max-target-seqs 1 \
  --outfmt 6 qseqid sseqid stitle evalue

# 6. Parse DIAMOND results to MOOP format
perl parse_DIAMOND_to_MOOP_TSV.pl Chamaeleo_calyptratus.tophit.tsv "UniProtKB/Swiss-Prot" "2025-06-17" \
  "https://www.uniprot.org" "https://www.uniprot.org/uniprotkb/"

# 7. Load annotations into the organism database
perl load_annotations_fast.pl Chamaeleo_calyptratus.sqlite UniProtKB_Swiss-Prot.homologs.moop.tsv

# 8. Copy organism database to MOOP server
scp Chamaeleo_calyptratus.sqlite user@moop-server:/path/to/organisms/data/
```

### Scenario 2: Batch Processing Multiple Organisms

**Working directory:** `/var/www/html/moop/dbtools`

Process multiple organisms with a loop (each gets its own database):

```bash
cd /var/www/html/moop/dbtools

# Create/copy your organisms.tsv with multiple rows
# Each organism will get its own database file

for organism in "Chamaeleo calyptratus CCA3" "Aeorestes cinereus GCA_011751095.1"; do
  read genus species accession <<< "$organism"
  org_name="${genus}_${species}"
  
  # Convert GFF3 to MOOP format
  perl parse_GFF3_to_MOOP_TSV.pl ${org_name}.gff3 my_organisms.tsv \
    $genus $species $accession > ${org_name}.genes.tsv
  
  # Create organism-specific database
  sqlite3 ${org_name}.sqlite < create_schema_sqlite.sql
  
  # Load genes into organism database
  perl import_genes_sqlite.pl ${org_name}.sqlite ${org_name}.genes.tsv
  
  echo "Completed: $org_name"
done

# Verify databases created
ls -lh *.sqlite

# Verify each database
for db in *.sqlite; do
  echo "Database: $db - Features: $(sqlite3 $db 'SELECT COUNT(*) FROM feature;')"
done
```

### Scenario 3: Test Data - Quick Validation

**Quick test using included sample data:**

```bash
cd /var/www/html/moop/dbtools/test_output

# View organisms metadata
cat ../test_data/organisms.tsv

# Convert GFF3 to MOOP format (displays to screen)
perl ../parse_GFF3_to_MOOP_TSV.pl ../test_data/genomic.gff3 ../test_data/organisms.tsv \
  Chamaeleo calyptratus CCA3

# Full workflow: Create organism database and load genes
sqlite3 Chamaeleo_calyptratus.sqlite < ../create_schema_sqlite.sql

perl ../parse_GFF3_to_MOOP_TSV.pl ../test_data/genomic.gff3 ../test_data/organisms.tsv \
  Chamaeleo calyptratus CCA3 > Chamaeleo_calyptratus.genes.tsv

perl ../import_genes_sqlite.pl Chamaeleo_calyptratus.sqlite Chamaeleo_calyptratus.genes.tsv

# Verify database loaded
sqlite3 Chamaeleo_calyptratus.sqlite "SELECT COUNT(*) FROM feature;"
sqlite3 Chamaeleo_calyptratus.sqlite "SELECT DISTINCT type FROM feature;"
```

**Test files location:**
- GFF3: `../test_data/genomic.gff3`
- Organisms metadata: `../test_data/organisms.tsv`
- Output database: `test_output/Chamaeleo_calyptratus.sqlite`

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

