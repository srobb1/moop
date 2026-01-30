# Creating and Loading the MOOP Database

Instructions for setting up the SQLite database and loading gene and annotation data.

## Important: Off-Server Database Creation

**Database creation and loading do not need to be done on the MOOP web server.** You can create and populate the SQLite database on any machine with Perl and SQLite installed, then copy the final database file to your MOOP server.

Typical workflow:
1. **Local/HPC machine:** Download gene data, run DIAMOND analysis (optional)
2. **Local/HPC machine:** Create SQLite database and load all data
3. **MOOP server:** Copy the final `organism.sqlite` file to your organisms data directory

The only requirement on the MOOP server is the final SQLite database file in the correct location. For configuration details and the data directory location, see the MOOP main documentation.

This approach is useful for:
- Testing data before deployment
- Running large data loads on high-performance machines
- Avoiding server resource overhead during data processing
- Easy backup and version control of database creation workflows

## Quick Start

### 1. Set up Conda Environment

If you don't have conda installed, [install Miniconda](https://docs.conda.io/en/latest/miniconda.html).

```bash
# Create the environment from environment.yml
conda env create -f environment.yml

# Activate the environment
conda activate moop-dbtools
```

### 2. Prepare Gene Data from GFF (If needed)

If you have gene annotations in GFF3 format, convert them to MOOP TSV format using the feature table script.

First, ensure you have an organisms metadata TSV file with organism information and feature types:

**organisms.tsv format:**
```
genus	species	common-name	simrbase-prefix	source	accession	ncbi-taxon-id	feature-types	...
Chamaeleo	calyptratus	Veiled Chameleon	CCA3	SIMR	CCA3	179908	mRNA,gene	...
```

**Key columns:**
- `genus`, `species` - Scientific names (used to identify the organism)
- `accession` - Genome accession ID (used to identify the organism)
- `feature-types` - Comma-separated list of feature types to extract (e.g., `mRNA,gene`)
- Other columns: `common-name`, `simrbase-prefix`, `source`, `ncbi-taxon-id`, etc.

Then convert your GFF file to MOOP TSV format:

```bash
perl parse_GFF3_to_MOOP_TSV.pl genomic.gff3 organisms.tsv Chamaeleo calyptratus CCA3 > genes.tsv
```

**Parameters:**
- `genomic.gff3` - Your GFF3 annotation file
- `organisms.tsv` - Organisms metadata file
- `Chamaeleo calyptratus CCA3` - Organism identifiers (genus, species, accession)
- Output: `genes.tsv` (MOOP format with metadata headers)

The feature types to extract come from the `feature-types` column in organizations.tsv, so you don't need to specify them on the command line.

### 3. Create the Database Schema

```bash
sqlite3 organism.sqlite < create_schema_sqlite.sql
```

This creates an empty SQLite database with the MOOP schema (organism, genome, feature tables, etc.).

### 4. Load Gene Data

```bash
perl import_genes_sqlite.pl organism.sqlite genes.tsv
```

**Input file format:** Tab-delimited file with header comments:

```
## Genus: Anoura
## Species: caudifer
## Common Name: Tailed tailless bat
## NCBI Taxon ID: 27642
## Genome Accession: GCA_004027475.1
## Genome Name: ACA1
## Genome Description: Assembly from NCBI
Uniquename	This_Type	Parent_uniquename	This_Name	This_Description
gene_001	gene	scaffold_001	GENE1	Description of gene 1
gene_002	gene	scaffold_001	GENE2	Description of gene 2
```

**Header format:**
- Lines starting with `##` are metadata headers
- Required: `## Genus:` and `## Species:`
- Optional: `## Common Name:`, `## NCBI Taxon ID:`, `## Genome Accession:`, `## Genome Name:`, `## Genome Description:`, `## Species Subtype:`

**Data format:**
- Tab-delimited with 6 columns: Uniquename, This_Type, Parent_uniquename, This_Name, This_Description
- Uniquename: unique identifier for the feature (required, non-empty)
- This_Type: feature type (gene, exon, etc.)
- Parent_uniquename: identifier of parent feature (leave empty if no parent)
- This_Name: common name (optional)
- This_Description: description text (optional)

### 5. Load Annotation Data

```bash
perl load_annotations_fast.pl organism.sqlite annotations.tsv
```

**Input file format:** Tab-delimited file with header metadata:

```
## Annotation Source: UniProtKB/Swiss-Prot
## Annotation Source Version: 2025-06-17
## Annotation Source URL: https://www.uniprot.org
## Annotation Accession URL: https://www.uniprot.org/uniprotkb/
## Annotation Type: Homologs
## Annotation Creation Date: 2025-06-17
## Gene	Accession	Accession_Description	Score
PROTEIN_001	Q9BWM5	Zinc finger protein 416	3.94e-110
PROTEIN_002	Q16342	Programmed cell death protein 2	2.04e-210
```

**Header format (required):**
- All lines starting with `##` are metadata
- `## Annotation Source:` - Source database name
- `## Annotation Source Version:` - Version or release date
- `## Annotation Source URL:` - URL to database homepage
- `## Annotation Accession URL:` - URL prefix for individual records
- `## Annotation Type:` - Type of annotation (Homologs, GO Terms, etc.)
- `## Annotation Creation Date:` - Date of annotation creation

**Data format:**
- Tab-delimited with 4 columns: Gene, Accession, Accession_Description, Score
- Gene: gene/protein identifier from your dataset
- Accession: identifier in the reference database
- Accession_Description: description from the reference database
- Score: e-value, bit score, or other metric

## Scripts Overview

### `parse_GFF3_to_MOOP_TSV.pl`
Converts GFF3 annotation files to MOOP TSV format for database loading. Reads organism metadata from a centralized TSV file.

**Usage:** `perl parse_GFF3_to_MOOP_TSV.pl <genomic.gff3> <organisms.tsv> <genus> <species> <accession>`

**Features:**
- Parses GFF3 files with ID, Name, Note, and Parent attributes
- Extracts specified feature types (mRNA, gene, etc.)
- Reads organism metadata from TSV file (supports multiple organisms)
- Outputs MOOP-format TSV with metadata headers
- Automatically derives feature types from organisms.tsv

**Input:** GFF3 file + organisms metadata TSV
**Output:** genes.tsv with metadata headers

### `import_genes_sqlite.pl`
Imports gene features into SQLite database. Handles:
- Organism/genome creation (if not exists)
- Parent feature relationships
- Feature upsert (insert or update)

**Usage:** `perl import_genes_sqlite.pl <organism.sqlite> <genes.tsv>`

**Features:**
- Creates organism record if it doesn't exist (based on genus, species, subtype)
- Creates genome record if it doesn't exist (based on genome_accession)
- Automatically creates parent features if referenced but don't exist
- Updates existing features if metadata has changed
- Uses SQLite transactions for data integrity

### `load_annotations_fast.pl`
Batch loads gene annotations with optimized performance.

**Usage:** `perl load_annotations_fast.pl <organism.sqlite> <annotations.tsv>`

**Features:**
- Fast batch loading of annotations
- Preserves source metadata (database name, version, URLs)
- Maintains annotation creation date

### `create_schema_sqlite.sql`
SQL schema definition for the MOOP database. Defines:
- organism table
- genome table
- feature table (genes, exons, etc.)
- annotation table
- All relationships and indexes

## Requirements

- Perl 5.10+
- DBI module
- DBD::SQLite module

All are installed via the conda environment.

## Deactivating the Environment

When you're done:

```bash
conda deactivate
```

## Troubleshooting

### "perl: command not found"
Make sure to activate the conda environment:
```bash
conda activate moop-dbtools
```

### SQLite permission errors
Ensure you have write permissions in the directory where you're creating the database.

### Database locked
Only one process can write to SQLite at a time. Close other connections to the database file.

### "Cannot open file" errors
Check that:
- Input file path is correct
- File is readable (`ls -l` to check permissions)
- File contains valid tab-delimited data

### Features not loading / parent features not found
- Ensure parent features are loaded before child features that reference them
- Or run the script twice (first pass creates parent features, second pass links them)

## Notes

- The scripts use SQLite transactions for data integrity
- Parent features are automatically created if referenced but don't exist
- Duplicate features are updated with new metadata if changed
- Organism/genome records are checked and created only once
- It's safe to run these scripts multiple times on the same database

## See Also

- [ANALYSIS_AND_PARSING.md](ANALYSIS_AND_PARSING.md) - For running DIAMOND BLAST and parsing results
