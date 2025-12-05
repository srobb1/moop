# Single organism data organization

## Directory Structure

This `organisms` directory contains one sub-directory for each organism. Each sub-directory is named with the organism's genus and species, and optional subclassification, all separated with underscores.

Examples: `Anoura_caudifer`, `Pantera_pardus_fusca`

## Organism Sub-Directory Contents

Each organism-specific sub-directory contains:
- A SQLite database file (named `[organism].genes.sqlite`) - houses gene, transcript, and annotation information
- One or more genome assembly directories - each containing FASTA sequence files

```text
Anoura_caudifer [genus_species]
|--> Anoura_caudifer.genes.sqlite
|--> GCA_004027475.1_AnoCau_v1_BIUU_genomic [genome_uniquename]
     |--> transcript.nt.fa [transcript/mRNA fasta]
     |--> protein.aa.fa [peptides/protein fasta]
     |--> cds.nt.fa [coding nucleotide fasta]
     |--> genome.nt.fa [genome fasta]
```

## Visibility and Configuration

Organism and assembly visibility is managed through the metadata directory (`../metadata/`):
- `organism_assembly_groups.json` - Defines which organisms and assemblies are available and their access level
- `group_descriptions.json` - Provides grouping and display information
- `taxonomy_tree_config.json` - Defines the taxonomy tree structure
- `annotation_config.json` - Specifies annotation types and properties

The configuration determines which organisms/assemblies are visible to different users and how they are displayed in the application.



