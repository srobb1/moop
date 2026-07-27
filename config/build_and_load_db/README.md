# MOOP Build and Load DB

Scripts for building and loading MOOP organism databases.

## Data hierarchy

```
organisms/{organism}/{assembly}/{gene_set}/
```

## What is reusable and what is not

**Read this before copying anything.** These three directories are not equally portable.

| Directory | Portable? | Notes |
|---|---|---|
| `data_loaders/` | **Yes — generic** | Schema, FTS builder, and the two loaders. Any MOOP deployment needs these to build a database. No site-specific paths. |
| `scripts/` | **Mostly NOT — examples** | SLURM orchestration, per-analysis prep, FASTA renamers. The `rename_*_fasta.pl` and `make_t2g_from_fasta.pl` scripts are generic; everything else assumes SIMR's cluster (`#SBATCH`, `module load`, `/home/smr/sciproj/...`, the `copy2moop` host). |
| `analysis_parsers/` | **Partly — examples** | Converters from analysis output to MOOP TSV. The parsing logic is reusable, but most carry hardcoded reference-database paths and versions. |

Anything under `scripts/` or `analysis_parsers/` that hardcodes a path is here for
**version history and as a worked example**, not for reuse as-is. `data_loaders/` is the
part to depend on.

`create_schema_sqlite.sql` is the **contract** between this pipeline and the MOOP web
application — the PHP reads the tables it defines. Change them together.

## Naming convention

- `parse_<SOURCE>_to_MOOP_TSV.pl` — converts one input format into MOOP's TSV.
  The name states input and output; nothing fetches anything.
- `load_<what>_sqlite.pl` — inserts a MOOP TSV into `organism.sqlite`.
- `rename_<source>_*_fasta.pl` — rewrites FASTA headers so their sequence IDs match
  `feature_uniquename`. See "The ID invariant" below.
- `make_*` / `run_*` `.sh` — SIMR pipeline glue.

## The ID invariant

**`feature_uniquename` in the database IS the FASTA lookup key.** MOOP retrieves a
sequence by using the feature's uniquename directly as the key into the gene set's
`transcript.nt.fa` / `cds.nt.fa` / `protein.aa.fa`.

Every source needs the two sides made to agree, and each source disagrees differently:

- **RefSeq** — the GFF CDS `ID=` (`cds-XP_...`) does not match the FASTA header
  (`lcl|NC_...._cds_XP_..._1`). `rename_RefSeq_cds_fasta.pl` joins them on `protein_id`
  and rewrites the header.
- **Ensembl** — `rename_Ensembl_cds_fasta.pl` builds `CDS:<protein_id>` to match what
  `parse_Ensembl_GFF_to_MOOP_TSV.pl` emits.
- **Generic GFF** — `rename_generic_fasta.pl` appends `:cds` / `:pep` only where the ID
  would otherwise collide with a transcript ID.
- **transcript2gene (T2G)** — all three FASTAs share one identifier, so the type is
  decided by which file is read. This is why T2G gene sets can end up with a single
  collapsed feature row; see `notes/` for the open design question.

When this invariant breaks, **nothing errors** — sequence retrieval simply returns
nothing. Verify with `scripts/check_sequence_id_match.sh`, and note that the renamers
now exit non-zero when any record fails to match.

## Incremental runs vs. a reload — `--force`

Every build step is gated on its own output already existing (`has_data features.tsv
|| REBUILD=true`, and so on). That makes a re-run cheap when you are adding one new
gene set to an otherwise current tree — which is the common case.

It also means **a plain re-run over a fully built tree rebuilds nothing and reloads
nothing.** It cannot pick up a fixed parser or a fixed loader, because it never re-runs
them. Use `--force` for that:

```sh
bash run_all_v2.sh              # incremental: only what is missing
bash run_all_v2.sh --force      # regenerate intermediates AND reload every database
bash run_all_v2.sh --force --no-copy
```

`--force` does two distinct things:

1. Sets `REBUILD=true` up front, so `isoforms.tsv`, `geneNames.tsv` and `features.tsv`
   are regenerated from source instead of reused.
2. **Drops each `organism.sqlite`** so it is recreated from `create_schema_sqlite.sql`.
   This part is not optional: constraints (`UNIQUE`, `NOT NULL`, a corrected
   `FOREIGN KEY`) cannot be added to an existing SQLite file, so loading into a
   surviving database silently keeps the OLD schema while reporting success.

The database is per **organism** but jobs run per **gene set**, so the drop is
coordinated by `MOOP_RELOAD_TOKEN`: the first gene set to reach an organism with a new
token drops the database and records the token; that organism's other gene sets see
their own token already recorded and load alongside. It happens inside the `flock`
`setup_new_moopdb_and_load_data.sh` already holds, so concurrent array tasks cannot
both decide they are first.

For a single organism by hand:

```sh
FORCE_RELOAD=1 sbatch scripts/moop_process_genome_data_v2.sbatch Organism Assembly GeneSet
```

`MOOP_RELOAD_TOKEN` defaults to the SLURM job id there, which is what you want.

**Not touched by `--force`:** `genome.json` and `geneset.json`, including the
`date_added` they carry. Those are site metadata, not derived data. `organism.json`
*is* regenerated, because the rebuild path has always done that.

## Components

- `run_all_v2.sh` — top-level driver
- `scripts/list_active_genesets.sh` — **the** definition of "which genesets are
  active". `run_all_v2.sh` and `scripts/check_status.sh` both call it; it prints to
  stdout and writes no file, so it cannot disturb a run in flight
- `active_genesets.tsv` — human-readable snapshot of what was active at the last
  submission. **Nothing reads it.** The SLURM array indexes into a frozen per-run copy
  under `runs/`, because a task finds its work by line number and the list therefore
  has to stay byte-identical for the whole life of the array — which a status check
  rewriting the same path used to break
- `scripts/moop_process_genome_data_v2.sbatch` — main SLURM job; processes one organism
- `scripts/setup_new_moopdb_and_load_data.sh` — creates the SQLite DB and loads a gene
  set plus its annotations. Gates annotation loading on features actually existing.
- `data_loaders/` — schema, FTS index builder, and the gene/annotation loaders
- `analysis_parsers/` — GFF/FASTA parsers and analysis-output converters
- `scripts/make_*_moop_files.sh` — per-analysis-type file preparation

## Requirements

Perl 5.10+ with `DBI`, `DBD::SQLite` and `URI::Escape`, plus `sqlite3`. Without a system
Perl that has them, a self-contained environment works and needs no root:

```sh
micromamba create -n moop-dbtools -c conda-forge -c bioconda \
    perl perl-dbi perl-dbd-sqlite perl-uri
micromamba run -n moop-dbtools perl data_loaders/load_genes_sqlite.pl ...
```

`perl-uri` supplies `URI::Escape`, which `analysis_parsers/parse_GFF3_to_MOOP_TSV.pl`
needs to decode percent-escapes in GFF attributes. It is easy to miss because the
loaders themselves do not use it — only the parsers do.

## Load order

Order matters — annotations attach to features by uniquename, and the FTS index is built
from both.

1. `data_loaders/create_schema_sqlite.sql` (once per organism database)
2. `data_loaders/load_genes_sqlite.pl` — one gene set at a time
3. `data_loaders/load_annotations_sqlite.pl` — accepts many files in ONE invocation;
   it builds its caches once per run, so calling it per file scales badly
4. `data_loaders/make_annotation_sources_cache.pl`
5. `data_loaders/build_fts_index.sql`, then `VACUUM`

`setup_new_moopdb_and_load_data.sh` does all five in order.

Every loader runs integrity checks at the end and reports problems rather than exiting 0
regardless. A load that attaches no annotations, or leaves a feature as its own parent,
is reported as a failure.
