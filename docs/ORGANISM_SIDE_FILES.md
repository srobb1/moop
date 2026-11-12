# Organism side files — anatomy expression, gene images, crosswalk

Next to `organism.sqlite` an organism folder can hold extra, **optional** data files. Each is its own
read-only SQLite file, built from a plain source table by a script, and never written by a page
request. An organism without them shows nothing new.

```
organisms/<Organism>/
    organism.sqlite              genes, annotations       (compute pipeline; not covered here)
    crosswalk.sqlite             ID translation           docs/CROSSWALK_FORMAT.md
    anatomy_expression.sqlite    where genes are expressed, by anatomy term   §1 (BUILT)
    gene_images.sqlite           in situ images, plots, movies per gene       §2 (BEING BUILT)
    images/                      the image files + images.tsv (their source list)
```

Why separate files and not tables in `organism.sqlite`: that file is owned and rebuilt by the compute
pipeline; these are curated or imported on the web side, change on their own schedule, and must not
be lost when the pipeline reloads an organism. (CLAUDE.md §9 — keep the pipeline schema lean.)

**Common rules** (same as `lib/ontologies.php`):
- Built by a CLI script into a temp file, checked (row count > 0, `PRAGMA integrity_check`), then
  renamed over the live file. A failed build leaves the previous file in place.
- Opened `SQLITE_OPEN_READONLY` by pages.
- Rows are stored as the source has them. What a build cannot use goes on a **review** table shown
  to admins — never silently dropped or "fixed".
- **Access:** see §3. ⚠️ Today only the organism level is checked; the per-data-set check is the
  next priority.

---

## 1. `anatomy_expression.sqlite` — curated anatomical expression (PAGE)

Where a gene is expressed, by anatomy term, from published papers (in situ, RNA-seq, single-cell…).
Not to be confused with the **Expression Explorer** (RNA-seq heatmaps: *how much*); this is *where*.

| | |
|---|---|
| Code | `lib/anatomy_expression.php` (functions `moop_page_*`) |
| Build | `php scripts/build_anatomy_expression.php [Organism ...]` (as the files' owner) — **manual**; not a housekeeping task |
| Config | `site_config.php` → `anatomy_expression[<Organism>]` = `curations` (URL), `source`, `source_url` |
| Source | the 9-column PAGE table, downloaded from `raw.githubusercontent.com` only (`curation/OUTPUT/term_annotations_2024-05-24.txt` in planosphere/PAGE) into `<cache>/ontologies/sources/page-<Organism>.tsv` |
| Needs | the anatomy ontologies built first (`scripts/build_ontologies.php`) — for term checks and expansion |
| Network | PubMed E-utilities at build time only (paper titles); unreachable → build still succeeds, PMIDs shown |
| Used by | Anatomical Expression Search (`tools/anatomy_search.php`: by term / publication / gene IDs), gene page "Anatomical expression" card (`tools/parent.php`), Ontology Browser term counts |

**Source columns (header must match exactly):**
`Accession, Term, Experimental Evidence, Biotype, Life Cycle Stage, Specimen, Curator (ORCID), Pubmed ID, Date`

- `Accession` is a transcript/gene ID from any source; it reaches a gene through `crosswalk.sqlite`.
- `Term`, evidence, biotype, stage, specimen are ontology IDs (PLANA, BSPO, ECO). The label in the
  table is ignored — labels always come from the ontology by ID.

**Tables:**

| table | holds |
|---|---|
| `curation` | one row per distinct curation: `accession` (+ `accession_lc`), `term_id`, `evidence_id`, `biotype_id`, `stage_id`, `specimen_id`, `curator`, `pmid`, `curated_on` (ISO), `copies` (how many times it was entered) |
| `publication` | `pmid`, `title`, `authors` (short), `authors_all`, `journal`, `year`, `search_text` (accent-folded, for search) |
| `review` | `kind`, `detail`, `rows` — what the build could not use (unknown/obsolete term, non-ECO evidence, punctuation in an accession, PMID as accession, duplicates…) |
| `meta` | `organism`, `source` (URL), `built_at`, and the counts (`rows_read`, `curations`, `accessions`, `terms`, `publications`, `titled`) |

**Behaviour worth knowing:**
- Duplicates (same curation after normalising the date) are kept once, `copies` counted.
- Search follows obsolete terms to their `replaced_by`, so old curations still match.
- Term search expands through the ontology closure (part of, is a, …) — `moop_page_search()`.

Schmidtea mediterranea, built 2026-11-09: 80,789 rows read → 77,432 curations, 38,992 accessions,
180 terms, 162 papers. Copy on Planosphere: hand-built, see `todo_planosphere_browser_decision`.

---

## 2. `gene_images.sqlite` + `images/` — gene images (design 2026-11-12, being built)

In situ images, single-cell plots and movies, attached to genes and (when tagged) to anatomy and stage
terms. Shown on gene pages ("Images") and on Ontology Browser term pages (a gallery of images tagged
with the term or any of its parts).

**Files:**
```
organisms/<Organism>/images/
    images.tsv            the source list — one row per image (editable; the truth)
    <collection>/...      the files, one folder per collection (davies_2017/, zeng_2018/x1/, ...)
organisms/<Organism>/gene_images.sqlite    built from images.tsv
```
Thumbnails are made on first request into `cache_path` (regenerable → cache, not data).

**`images.tsv` columns:**

| column | example | notes |
|---|---|---|
| `file` | `davies_2017/SMED30001362_piwi-3_S3.png` | path under `images/` |
| `kind` | `in situ`, `feature plot`, `violin plot`, `movie` | |
| `accession` | `SMED30001362` | gene/transcript ID as the source has it; reaches other gene sets through the crosswalk |
| `assembly`, `gene_set` | `smed_20140614`, `smed_20140614` | **the data set the image belongs to — decides who may see it (§3)** |
| `analysis` | `Davies 2017 embryo staging` | optional; reserved for analysis-level access rules later |
| `stage` | `PLANA:0000003` | ontology ID |
| `terms` | `PLANA:0004517;PLANA:0000023` | anatomy ontology IDs, `;`-separated |
| `caption` | text | `caption_<lang>` columns for other languages |
| `pmid` | (PubMed ID) | source paper |
| `credit`, `license` | | |

Empty cell = "not recorded", never "none".

**First data (Schmidtea, from the old Planosphere database dump):**
- Davies et al. 2017 — 229 in situ images + 34 movies, gene + paper; 263 tagged with PLANA anatomy
  and stage terms. Old server: `/pub/analysis/wish/`, `/pub/movies/`.
- Zeng et al. 2018 — feature and violin plots, data sets `x1` and `xins`, ~49,000 genes, ~98,000
  files (~8 GB). Old server: `/pub/images/anz_gene_plots/{feature_plot,violin_plot}/{x1,xins}/`.

**Serving:** `organisms/` is denied to the web by nginx, so images go through an access-checked
endpoint (pattern: `api/jbrowse2/genome_file.php`, `lib/fasta_download_handler.php`): the file must be
listed in `images.tsv`, `realpath()` under `images/`, and its data set visible (§3). Private and missing
both answer 404.

---

## 3. Access — every row belongs to a data set

Rule (Sofia, 2026-11-12): side data respects the organism, assembly **and** gene set rules — if any
parent data set is private to the visitor, the item is private too. Later, an **analysis** attached to
a gene set may get its own permission.

- Images: each row names its `assembly`/`gene_set`; checked when served, listed on a gene page, and
  counted on a term page.
- ⚠️ **Gap, to fix next:** the Anatomical Expression Search (`tools/anatomy_search.php`) and the
  Rosetta Stone translator (`tools/crosswalk.php`) check only that the organism has *something*
  public. A private gene set's IDs would be listed. One shared per-data-set check, built with the
  images, then applied to both.
