# Single organism data organization

## Directory Structure

This `organisms` directory contains one sub-directory for each organism. Each sub-directory is named with the organism's genus and species, and optional subclassification, all separated with underscores.

Examples: `Anoura_caudifer`, `Pantera_pardus_fusca`

## Organism Sub-Directory Contents

Each organism-specific sub-directory contains:
- A SQLite database file (named `organism.sqlite`) - houses gene, transcript, and annotation information
- One or more genome assembly directories - each containing FASTA sequence files and BLAST databases

```text
Anoura_caudifer [genus_species]
|--> organism.sqlite
|--> GCA_004027475.1_AnoCau_v1_BIUU_genomic [genome_uniquename]
     |--> transcript.nt.fa [transcript/mRNA fasta]
     |--> protein.aa.fa [peptides/protein fasta]
     |--> cds.nt.fa [coding nucleotide fasta]
     |--> genome.nt.fa [genome fasta]
     |--> *.nhr, *.nin, *.nsq [BLAST database files for nucleotide sequences]
     |--> *.phr, *.pin, *.psq [BLAST database files for protein sequences]
```

## File Organization and Patterns

FASTA file naming patterns and locations are configured in the site configuration (`admin/manage_site_config.php`). This allows flexibility in how different data sources organize their files while maintaining a consistent interface.

## Status and Management

The status of each organism, its assemblies, and their associated files can be reviewed and managed through:
- **Admin Dashboard**: `admin/manage_organisms.php` - View and manage organism and assembly status
- Displays which FASTA files are present for each assembly
- Shows data loading status and any issues

## Visibility and Configuration

Organism and assembly visibility is managed through the metadata directory (`../metadata/`):
- `organism_assembly_groups.json` - Defines which organisms and assemblies are available and their access level
- `group_descriptions.json` - Provides grouping and display information
- `taxonomy_tree_config.json` - Defines the taxonomy tree structure
- `annotation_config.json` - Specifies annotation types and properties

The configuration determines which organisms/assemblies are visible to different users and how they are displayed in the application.



