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
bash run_all_v2.sh                 # or --no-copy to build without the rsync
bash scripts/check_status.sh > status.after.log
diff status.before.log status.after.log
```

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

**Web-side cycle guards.** The data fix is the cure, but the guard is the seatbelt and
should go in regardless, so bad data can never pin a worker again:

- `lib/moopmart_functions.php` (~line 49) — the `WITH RECURSIVE` CTE
- `lib/parent_functions.php` — `getAncestors()`

SQLite here is **3.34.1** (both CLI and `pdo_sqlite`), so the built-in `CYCLE` clause
(3.42+) is unavailable. Use the tested form:

```sql
AND f.feature_id <> c.feature_id   -- don't follow a self-reference
WHERE c.depth < 20                 -- and never recurse deeper than 20
```

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
