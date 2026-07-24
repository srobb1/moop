# Schema review and the cold-query problem

Reviewed 2026-07-24 at the user's request, pre-launch. Everything below was measured on
this host. Schema fixes are **applied**; the database rebuild is **not yet done**.

---

## Part 1 — Cold queries are a storage problem, not a schema problem

```
organisms/   67 GB across 85 SQLite databases   (inside a 267 GB data tree)
volume       /dev/mapper/datavg-datalv (XFS) on sdb -- ROTA=1, i.e. SPINNING
RAM          15 GB total, ~12 GB page cache, 2 GB used
measured     identical COUNT(*): 7,051 ms cold vs 2 ms warm  (~3,500x)
```

67 GB of data behind a 12 GB cache on rotating media. A random read costs ~5–10 ms in seek
time, so a cold query touching a few hundred scattered pages takes seconds. **No table
design produces a 3,500× gap — only storage does.** Check you are not measuring the disk
before optimising SQL.

### Levers, in order of payoff

| # | Lever | Effect |
|---|---|---|
| 1 | **More RAM** (16 → 64 GB; it is a VM) | After the shrink below, the whole corpus caches and the penalty disappears rather than shrinking. See `notes/IT_REQUEST_RAM_INCREASE.md`. |
| 2 | **Flash-backed storage**, or lvmcache/bcache in front of the spinning volume | 50–100× on cold seeks. Confirm with IT whether `ROTA=1` is real or a virtualisation artifact. |
| 3 | **Contentless FTS5** — applied | 17–45% off every database (~20 GB corpus-wide). |
| 4 | `VACUUM` after rebuilds | Freed pages are only returned to the filesystem by VACUUM. Also makes cold reads sequential. |

### Answered: "can I have virtual RAM that is hard drive space?"

No — that is swap, and it runs the wrong direction: RAM overflowing *to* disk, which is
slower. The 3 GB of swap here is correctly unused. The real form of the idea is
**lvmcache/bcache**: a small SSD as a cache in front of the slow volume. That does work.

### Answered: "should we move to flat files and drop the database?"

No. Flat files have the *same* cold-I/O problem, because the problem is storage. Worse,
SQLite's B-tree indexes are exactly what let a cold query touch a few hundred pages instead
of reading the whole file — removing them makes cold reads worse, not better. It would also
give up FTS5, measured at ~11× faster than `LIKE`.

The correct split is the one already in place: bulk regenerable per-feature data in flat
files (`feature_coords.tsv`), searchable data in SQLite. Keep it.

### The biggest single win: FTS5 was storing a second copy of the text

FTS5 keeps a full duplicate of every indexed column in an internal `_content` table unless
told not to. Measured:

| Database | `_content` | total | share |
|---|---|---|---|
| Nematostella_vectensis | 307 MB | 680 MB | **45%** |
| Drosophila_melanogaster | 26 MB | 86 MB | 30% |
| Medicago_truncatula | 8 MB | 47 MB | 17% |

That text already lives in `feature` and `annotation`. **Safe to drop** because MOOP's
queries use the FTS tables only for `MATCH` and `rowid` — every displayed column is
selected from the real tables through the rowid join, and there is no `snippet()` or
`highlight()` anywhere (`lib/database_queries.php`).

Verified on this host's SQLite 3.34.1: `MATCH`, `bm25()` weighting and the rowid join all
work contentless; index size fell 5,700 KB → 2,592 KB on a synthetic 40k-row test.

Constraint accepted: a contentless FTS5 table is INSERT-only — no UPDATE/DELETE
(`contentless_delete` needs 3.43). `build_fts_index.sql` already drops and rebuilds
wholesale, so this costs nothing. **Revisit if incremental FTS updates are ever needed.**

---

## Part 2 — Schema bugs found and fixed

Applied to `config/build_and_load_db/create_schema_sqlite.sql` on 2026-07-24. All verified
against a freshly built test database.

| # | Bug | Consequence | Fix |
|---|---|---|---|
| 1 | `genome` referenced `organisms(organism_id)` — **no such table** (it is `organism`) | With FK enforcement on, every genome insert fails: `no such table: main.organisms` | Corrected to `organism` |
| 2 | **`PRAGMA foreign_keys` was never set anywhere** — no PHP, Perl or SQL file | Every FK and every `ON DELETE CASCADE` was decorative. Deleting a gene left its mRNA/CDS/protein and all annotations behind | `PRAGMA foreign_keys = ON` added to both loaders (it is per-connection; it cannot live in the schema file) |
| 3 | `feature_uniquename ... UNIQUE` **plus** an explicit `feature_unqiuename_idx` on the same column | Two identical B-trees, 6 MB each in Nematostella, both written on every insert, both competing for page cache | Replaced by one non-unique index that serves lookups the composite key cannot |
| 4 | `feature_uniquename` unique **globally**, not per gene set | Six organisms already have 2–3 gene sets. Two sets sharing an ID scheme could not coexist — and because the loader looked features up by uniquename alone, the second load would silently **reassign** the first set's features | `UNIQUE (gene_set_id, feature_uniquename)`; loader lookups now gene-set scoped |
| 5 | `annotation_source` had no unique constraint on `(name, version)` | The 17 duplicate `Ensembl <species> ` pairs — two sources users cannot tell apart, each holding half the annotations | `UNIQUE (annotation_source_name, annotation_source_version)` |
| 6 | `feature_annotation` had no unique constraint on `(feature_id, annotation_id)` | Deduplication existed only in the loader's in-memory hash — good for one run, nothing else | `UNIQUE (feature_id, annotation_id)` |
| 7 | `score TEXT NOT NULL`, holding e-values **and** the literal string `"-"` (599,447 of 1,397,415 rows) | Sorted lexicographically, so `"100"` ordered before `"9"` and every score-less row sorted first | `score REAL` **nullable**; `"-"` loads as NULL, never `0.0` |
| 8 | `feature.gene_set_id` nullable | UNIQUE treats NULLs as distinct, defeating #4. (0 rows affected in practice) | `NOT NULL` |

### Why `(gene_set_id, feature_uniquename)` and not assembly + gene set + uniquename

`gene_set_id` **already determines** the assembly and the organism, through
`gene_set UNIQUE (genome_id, gene_set_name)` and `genome UNIQUE (organism_id,
genome_accession)`. Adding the assembly to the feature key would restate a fact the schema
already guarantees, and denormalised facts drift. Two columns is the correct key.

---

## Part 3 — What still has to happen

The schema file is fixed, but **no existing database has been rebuilt**, so every problem
above is still live in the data.

1. **Reclaim FTS space on existing databases** (no reload needed):
   `sqlite3 <db> < build_fts_index.sql` then `VACUUM`. This alone returns ~20 GB.
2. **Repair `parent_feature_id` in the 85 existing databases** — `'NULL'`→NULL,
   `''`→NULL, self-parent→NULL. See `notes/` and the loader fixes of the same date; without
   this, nothing resolves up to a gene.
3. **Web-side cycle guards** — `lib/moopmart_functions.php` (~line 49) and `getAncestors()`
   in `lib/parent_functions.php`. Data repair is the cure; the guard is the seatbelt, and it
   must go in regardless so bad data can never pin a php-fpm worker again.
4. **Full rebuild** to pick up items 1, 4, 5, 6, 7, 8 above (the constraints cannot be added
   to existing files without a rebuild). Deduplicate `annotation_source` first or the new
   UNIQUE will reject the load.
5. **Parastichopus_parvimensis** — 0 features, 306,781 orphaned annotations. A failed load
   that reported success; the new loader checks catch this class now.

### Post-launch: STRICT tables

SQLite 3.37+ `STRICT` tables would have rejected the string `'NULL'` in an INTEGER column
outright, making that entire bug class impossible. This host is on **3.34.1** (both CLI and
`pdo_sqlite`), so it is unavailable today.

**Do not upgrade compute first.** A STRICT table's schema fails to parse on older SQLite,
so a compute box writing STRICT tables while the webserver stays on 3.34.1 would leave the
web application unable to open the databases at all. Upgrade the webserver first, or both
together. Not before launch — `sqlite-libs` is a core RHEL 9 library that `dnf` itself
depends on, and PHP links the same one.
