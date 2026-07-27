# Pipeline reload — where we stopped 2026-07-24, and what to do next

**Decision (user, 2026-07-24): RELOAD everything on compute and copy over. Do NOT write an
in-place repair script for the 85 existing databases.** A full pipeline run regenerates
them correctly, and the reload also picks up the new schema constraints, which cannot be
added to existing files anyway.

All work is committed and **pushed** — 10 commits, `d250421`..`3566673`.

---

## 1. Why a reload is needed at all

`parent_feature_id` never held a real SQL NULL in any of the 85 databases, so **nothing
could resolve a protein / mRNA / CDS id up to its gene**. Three encodings of "no parent"
all came from the loader:

| encoding | cause | scope |
|---|---|---|
| string `'NULL'` (4 chars) | loader default | **81 of 85 DBs** |
| empty string `''` | auto-created parents | e.g. Schmidtea_nova 5,491 |
| self-reference | source rows naming themselves | 8 DBs, 7 feature types |

SQLite stores text that will not coerce to a number in an INTEGER column as TEXT, so
`WHERE parent_feature_id IS NULL` matched **zero rows** and nothing errored.

Live consequences, measured:

- `moopmartResolveInputIds()` returned `[]` for protein, mRNA **and gene** input, so
  MOOPmart's **By Feature IDs** export silently produced nothing.
- The recursive CTE **hangs** on self-parented rows — a pinned php-fpm worker.
- Nematostella RS_101: **0 of 32,370** CDS features were retrievable, because the CDS
  FASTA was never renamed (the RefSeq join was broken).
- Parastichopus_parvimensis: 0 features, 306,781 orphaned annotations.

---

## 2. Do this next, in order

### Step 1 — pull on compute

```sh
cd ~/sciproj/SBGENOMES/dev/smr_dev/moop/moop-pipeline
git pull
```

The sparse checkout persists; `git pull` needs no extra flags. **Scripts were renamed**, so
any wrapper or shell history referencing `import_genes_sqlite.pl`,
`load_annotations_fast.pl`, `make_feature_table_from_*` or `get_*.moop.pl` must be updated.
Everything inside the checkout was updated in the same commit.

### Step 2 — check the environment

The parsers need `URI::Escape`, which the loaders do not, so it is easy to miss:

```sh
micromamba create -n moop-dbtools -c conda-forge -c bioconda \
    perl perl-dbi perl-dbd-sqlite perl-uri
```

### Step 3 — baseline before you start

```sh
bash scripts/check_status.sh > status.before.log
```

Expect `BAD_PARENTS` on essentially every gene set — that is the bug being fixed, and it is
the number that must go to zero afterwards.

### Step 4 — reload one organism first, not all 92

Pick one of each shape and confirm before committing to a full run:

| shape | organism | what to prove |
|---|---|---|
| RefSeq eukaryote | `Nematostella_vectensis` / `RS_101` | CDS ids now match `cds.nt.fa` (was 0/32,370) |
| T2G | `Bipalium_kewense` | protein/CDS rows now EXIST, ids suffixed `:pep`/`:cds` |
| generic GFF | `Nematostella_vectensis` / `NV2` | no `:cds`/`:pep` rows for non-coding transcripts |
| RefSeq prokaryote | `Bradyrhizobium_diazoefficiens` | 230 pseudogene CDS records now match |

Each load now prints an integrity block and **exits non-zero** on failure instead of
reporting success. A load that attaches no annotations is a failed load.

### Step 5 — full run, then verify

```sh
bash run_all_v2.sh --reload        # add --no-copy to build without the rsync
bash scripts/check_status.sh > status.after.log
diff status.before.log status.after.log
```

**`--reload` is required here, and it was added on 2026-07-27 because this step did not
work as written.** Every build step is gated on its own output already existing
(`has_data features.tsv || REBUILD=true`), so a plain `bash run_all_v2.sh` over the
existing tree would have rebuilt nothing and reloaded nothing — it would have re-copied
the same broken databases and reported success. `--reload` bypasses those gates *and*
drops each `organism.sqlite` so it is recreated from the current schema, which is the
only way the new constraints can apply: `UNIQUE`, `NOT NULL` and a corrected
`FOREIGN KEY` cannot be added to an existing SQLite file.

**The unit of work is now the ORGANISM, not the gene set** (2026-07-27). One job owns
one `organism.sqlite`, so a reload's scope and the database's scope are the same thing
and dropping is safe by construction. To reload part of an organism afterwards:

```sh
MOOP_RELOAD=1 sbatch scripts/moop_process_genome_data_v2.sbatch Org Assembly GeneSet
```

which deletes only that gene set's rows (`scripts/delete_gene_set.sh`) and leaves its
siblings alone. See the README section "Incremental runs vs. a reload".

Checking progress mid-run is now safe. `check_status.sh` used to rewrite
`active_genesets.tsv`, which was also the file the running SLURM array indexed into by
line number — so looking at how the reload was going could renumber the task list
underneath the still-queued tasks. The array now reads a frozen per-run snapshot under
`runs/`, and both scripts get their listing from `scripts/list_active_genesets.sh`,
which writes no file at all.

`BAD_PARENTS`, `ID_MISMATCH`, `NO_FEATURES` and `ORPHAN_ANNOT` should all be gone. If any
remain, the tag names the exact failure.

### Step 6 — after copying to moop, confirm on the web side

```sh
sqlite3 organisms/Nematostella_vectensis/organism.sqlite \
  "SELECT COUNT(*) FROM feature WHERE parent_feature_id IS NULL;"   -- was 0, must be > 0
```

Then paste a **protein** id into MOOPmart's By-Feature-IDs box. It returned nothing all
along; it should now resolve to the gene.

---

## 3. Still open — not done, deliberately

**Web-side cycle guards — DONE 2026-07-27** (commit `8ef74d4`), and there were FOUR
walks, not the two listed here:

- `getAncestors()` and `getChildren()` in `lib/parent_functions.php`
- `moopmartResolveInputIds()` in `lib/moopmart_functions.php`
- `generateTreeHTML()` in `lib/parent_functions.php` — **not a CTE at all.** It recurses
  in PHP, one query per node, so it never appeared in a search for `WITH RECURSIVE`, and
  the first three guards did not fix the gene page.

The symptom was worse than "a pinned worker": `generateTreeHTML()` recursed until PHP
exhausted memory, so the gene page returned a hard **500 at ~8s and 2 GB** for all
66,596 self-parented features (Bipalium_kewense 39,065, Schmidtea_lugubris 14,313,
Schmidtea_nova 13,218). Now 200 in 0.6–5.0s; healthy pages byte-identical.

The two CTEs in `lib/extract_search_helpers.php` use `UNION` rather than `UNION ALL`,
which deduplicates and therefore already terminates. They need no guard.

**FTS space reclaim (~20 GB).** The FTS index is now contentless, but existing databases
keep the old `_content` tables until rebuilt. `setup_new_moopdb_and_load_data.sh` does this
during a reload; for a database you are not reloading:

```sh
sqlite3 organism.sqlite < data_loaders/build_fts_index.sql
sqlite3 organism.sqlite "VACUUM;"     -- required, or the file does not shrink
```

**The RAM request.** `notes/IT_REQUEST_RAM_INCREASE.md` is ready to send. 67 GB of
databases behind a ~12 GB page cache on a rotational volume; same `COUNT(*)` is 7,051 ms
cold and 2 ms warm. Ask is 16 → 64 GB.

**Annotation source deduplication.** The trailing-space bug is fixed at source, and the new
schema has `UNIQUE (annotation_source_name, annotation_source_version)` — but the 17
existing duplicate `Ensembl <species> ` pairs must be merged **before** a reload, or the new
constraint will reject them.

**`metazoa_r62` transcript FASTA headers** (Amphimedon, compute only). Contains fused ids
like `GeneID_100616083M_001279299.1` / `gene:GeneID_100616083otch`, consistent with an
unquoted substitution replacing a single leading character. Not deployed, so a lead rather
than a fire.

**Parastichopus_parvimensis** — 0 features, 306,781 orphaned annotations. The new checks
catch this class now; the reload should fix it, and `NO_FEATURES` will say so if not.

**Schmidtea_mediterranea is missing 4 of its 5 gene sets** (found 2026-07-27). The
active list has `schMedA2h1_orig`, `schMedA2h2_orig`, `schMedS3h1_WBPS19`,
`schMedS3h2_WBPS19` and `smed_20140614`; the live `organism.sqlite` holds only
`schMedS3h1_WBPS19` (50,739 features). All five assembly directories ARE on the web
server, so their FASTAs and BLAST indexes copied — only the database rows are absent.
Four fifths of the flagship planarian is invisible to search, MOOPmart and gene pages.
The other six multi-gene-set organisms match exactly (2/2, 2/2, 2/2, 3/3, 2/2, 2/2), so
this is not systemic. Run `check_status.sh` before the reload to see whether those four
report `NO_FEATURES` (never loaded) or `NOT_BUILT` (inputs missing).

**`test_ejr` is `active: true` but has no database and no directory on the web server.**
A reload will process it. Probably wants `active: false`.

---

## 4. Things worth not forgetting

- **`feature_uniquename` IS the FASTA lookup key.** When that breaks nothing errors —
  retrieval just returns empty, indistinguishable from a gene having no sequence. This is
  the single most expensive failure mode in this pipeline; `ID_MISMATCH` in
  `check_status.sh` now detects it.
- **`undef` is the only way to write SQL NULL through DBI.** The string `'NULL'` and `''`
  are values, and SQLite will accept either into an INTEGER column.
- **Do not upgrade SQLite compute-first.** A `STRICT` table's schema fails to parse on older
  SQLite, so a compute box writing STRICT tables while the webserver stays on 3.34.1 would
  leave the web app unable to open the databases at all. Webserver first, or both together.
  STRICT tables (3.37+) would make the `'NULL'` bug class impossible — worth doing
  post-launch.
- **The gene-set discovery loop is duplicated** in `run_all_v2.sh` and `check_status.sh`
  (the same `for META in "$GENOMES"/*/*/*/metadata.yaml` + `active: true` grep). Two
  definitions of "what is active" will drift; one should call the other.
- **`analysis_parsers/notes.sh`** is a scratch file with a syntax error and stale paths, not
  a runnable script. Left as-is deliberately.
- **`parse_OMA_HOG_to_MOOP_TSV.pl` does not compile** (undeclared `$db`, `$OTHERORG` under
  `use strict`). The user said it is work in progress — left alone.
