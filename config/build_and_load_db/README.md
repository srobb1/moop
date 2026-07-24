# MOOP Build and Load DB

Scripts for building and loading MOOP organism databases.

## Data hierarchy

```
organisms/{organism}/{assembly}/{gene_set}/
```

## Components

- `moop_process_genome_data.sbatch` — main SLURM job script; processes one organism
- `setup_new_moopdb_and_load_data.sh` — creates SQLite DB and loads annotations
- `data_loaders/` — SQL schema and Perl loaders
- `analysis_parsers/` — GFF/FASTA parsers
- `make_*.sh` — per-analysis-type file preparation scripts
