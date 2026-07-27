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

## Incremental runs vs. a reload — `--reload`

Every build step is gated on its own output already existing (`has_data features.tsv
|| REBUILD=true`, and so on). That makes a re-run cheap when you are adding one new
gene set to an otherwise current tree — which is the common case, and it leaves the
organism's existing gene sets completely untouched.

It also means **a plain re-run over a fully built tree rebuilds nothing and reloads
nothing.** It cannot pick up a fixed parser or a fixed loader, because it never re-runs
them. Use `--reload` for that.

```sh
bash run_all_v2.sh              # incremental: only what is missing
bash run_all_v2.sh --reload     # regenerate intermediates AND reload every database
bash run_all_v2.sh --reload --no-copy
```

### The invariant

**A reload invalidates exactly what the invocation names, and nothing else.**

| invocation | what is invalidated |
|---|---|
| `run_all_v2.sh` | nothing; builds only what is missing |
| `run_all_v2.sh --reload` | every `organism.sqlite` dropped, every intermediate rebuilt |
| `sbatch …sbatch Foo` | nothing; builds only Foo's missing gene sets |
| `MOOP_RELOAD=1 sbatch …sbatch Foo` | Foo's database dropped, all its gene sets rebuilt |
| `MOOP_RELOAD=1 sbatch …sbatch Foo ASM1 gsC` | **only gsC**: its rows deleted from Foo's database, its intermediates removed, then reloaded. gsA and gsB untouched |

That last row is why the unit of work is the organism (see the header of
`moop_process_genome_data_v2.sbatch`). `organism.sqlite` holds every gene set, so a job
that owned only one gene set could not drop it safely.

Dropping matters on its own: constraints (`UNIQUE`, `NOT NULL`, a corrected
`FOREIGN KEY`) cannot be added to an existing SQLite file, so loading into a surviving
database silently keeps the OLD schema while reporting success.

The narrowed case deletes rows via `scripts/delete_gene_set.sh` before reloading,
rather than just re-running the loaders. Both loaders **upsert** — matching on
`(uniquename, gene_set_id)` and `(feature_id, annotation_id)` — so a re-run corrects
what is still present and adds what is new, but leaves behind every row the new files
no longer contain. Commit `3566673` stopped emitting `:cds`/`:pep` rows for non-coding
transcripts; without the deletion, reloading with the fixed parser would add the
correct rows, keep all the bogus ones, and report success.

**Not touched by `--reload`, deliberately:** `genome.json`, `geneset.json` and the
`date_added` they carry. Those are site metadata, not derived data. `organism.json`
*is* regenerated, because the rebuild path has always done that.

`--force` is accepted as an alias for `--reload`.

## Components

- `run_all_v2.sh` — top-level driver; submits a SLURM array over ORGANISMS
- `scripts/moop_process_genome_data_v2.sbatch` — one job = one ORGANISM. Resolves that
  organism's active gene sets (narrowed by the optional assembly/gene-set arguments),
  loops over them, then runs the whole-database steps ONCE and copies to moop
- `scripts/process_one_geneset.sh` — builds and loads a single gene set. Was
  `moop_process_genome_data_v2.sbatch` until 2026-07-27
- `scripts/delete_gene_set.sh` — removes one gene set from an organism database,
  leaving its siblings intact. Used by a narrowed reload
- `scripts/list_active_genesets.sh` — **the** definition of "which genesets are
  active". `run_all_v2.sh` and `scripts/check_status.sh` both call it; it prints to
  stdout and writes no file, so it cannot disturb a run in flight
- `active_genesets.tsv` — human-readable snapshot of what was active at the last
  submission. **Nothing reads it.** The SLURM array indexes into a frozen per-run copy
  under `runs/`, because a task finds its work by line number and the list therefore
  has to stay byte-identical for the whole life of the array — which a status check
  rewriting the same path used to break
- `scripts/setup_new_moopdb_and_load_data.sh` — creates the SQLite DB and loads a gene
  set plus its annotations. Gates annotation loading on features actually existing.
  Never drops the database — invalidation belongs to the organism-level job
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

**Per gene set** — `setup_new_moopdb_and_load_data.sh` does these three, in order:

1. `data_loaders/create_schema_sqlite.sql` (once per organism database)
2. `data_loaders/load_genes_sqlite.pl` — one gene set at a time
3. `data_loaders/load_annotations_sqlite.pl` — accepts many files in ONE invocation;
   it builds its caches once per run, so calling it per file scales badly

**Per organism, ONCE, after every gene set has loaded** — `moop_process_genome_data_v2.sbatch`:

4. `data_loaders/make_annotation_sources_cache.pl`
5. `data_loaders/build_fts_index.sql`, then `VACUUM`

Steps 4 and 5 rewrite the whole organism database, so running them per gene set did the
same work N times over an ever-growing file — three full FTS rebuilds and three
`VACUUM`s for a 1.8 GB, 3-gene-set organism. Keep them at the organism level.

Every loader runs integrity checks at the end. `load_genes_sqlite.pl` **exits non-zero**
on a structural problem — zero features, a text `'NULL'` parent, a self-parent, a
dangling parent, zero roots — so the pipeline stops instead of loading annotations onto
a hierarchy the website cannot walk. A source row that named itself as its own parent is
a warning, not fatal: the loader already handled it correctly as a root.
`load_annotations_sqlite.pl` exits non-zero when it attaches nothing at all.
