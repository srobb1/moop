# Download filename audit

**Status: OPEN.** Raised 2026-07-30 — "I want to check all the file names of all the pages
as we test them. I have seen some not very well named, but I don't remember which."

Working checklist. Fill it in as each page is walked, rather than hunting for the bad ones
from memory.

---

## Why this is worth doing

A downloaded file is the part of MOOP that leaves the site. It gets renamed, emailed,
dropped in a shared folder, and opened months later by someone who never saw the page that
produced it. Everything the filename omits is gone at that point.

Two concrete failures already found this way:

- An export of a capped search recorded nothing about being capped — 37,500 rows that
  looked like a complete answer. Fixed 2026-07-30 (`715fc35`), which is what prompted this
  audit.
- `sequences_pep_2026-07-30_120000.fa` does not say which **organism** it came from. Two
  downloads from two organisms collide in the same folder with nothing to tell them apart.

---

## Every filename the app generates

Gathered by grepping `Content-Disposition` and `a.download` on 2026-07-30. Verify each by
actually clicking the button — several of these have never been checked.

| # | Produced by | Pattern | Reviewed? |
|---|---|---|---|
| 1 | `js/modules/annotation-search.js` (search results, Table CSV) | `annotation_search_{terms}_{Y-m-d}[_CAPPED-N-per-organism].csv` | ✅ 2026-07-30 |
| 2 | `api/download_search_fasta.php` (search results, FASTA) | `annotation_search_{terms}[_CAPPED-N-per-organism]_{Y-m-d}.fasta` | ✅ 2026-07-30 |
| 3 | `api/download_annotations.php` (gene page, Download All Annotations) | `{feature_uniquename}_annotations.csv` | ⬜ |
| 4 | `api/moopmart_export.php` (Data Exporter) | `moopmart_{format}_{date}.{ext}` | ⬜ |
| 5 | `lib/extract_search_helpers.php` (Retrieve Sequences) | `sequences_{seq_type}_{Y-m-d_His}.{ext}` | ⬜ |
| 6 | `lib/fasta_download_handler.php` (per-assembly FASTA) | `{organism}.{assembly}.{pattern}` | ⬜ |
| 7 | `api/download_zip.php` (Downloads, multi-select) | `moop_downloads_{Ymd_His}.tar.gz`, or the single file's own name | ⬜ |
| 8 | `api/blast_download.php` (BLAST results) | caller-supplied, sanitised | ⬜ |
| 9 | `api/download_file.php` (Downloads, single file) | the file's own basename | ⬜ |
| 10 | `js/registry.js` (admin) | `{type}_function_registry_{Y-m-d}.json` | ⬜ |
| 11 | `js/manage-registry.js` (admin) | `function_registry_{Y-m-d}.json` | ⬜ |
| 12 | `js/modules/gene-model-viewer.js` (gene model image) | `currentFilename` — needs tracing | ⬜ |
| 13 | DataTables Copy / CSV / Excel buttons on every results table | DataTables default (page title) — **unverified, likely poor** | ⬜ |

---

## Problems already visible from the table

These need no further testing; they are visible in the patterns themselves.

1. **Two conventions for the same file.** #10 is `{type}_function_registry_{date}` and #11
   is `function_registry_{date}` — the same artefact, named two ways, in one admin area.
2. **Separator drift.** #6 uses dots (`org.assembly.pattern`); everything else uses
   underscores. Dots read as file extensions to some tools.
3. **Date format drift.** `Y-m-d` (#1, #2, #10, #11), `Ymd_His` (#7), `Y-m-d_His` (#5).
   Only the ISO-ish `Y-m-d` sorts correctly in a file listing.
4. **Missing provenance.** #4, #5 and #7 carry no organism, assembly or gene set. #5 is the
   worst: two organisms' protein sets land as near-identical names.
5. **#13 is the big unknown.** Every results table has Copy/CSV/Excel from DataTables. If
   `filename` is not configured, the export is named after the page title — which on a group
   page is the same for every organism's table.

---

## A proposed convention, to argue with before applying

```
moop_{what}_{scope}_{date}[_MARKER].{ext}

  what    annotation_search | sequences | moopmart | annotations | downloads
  scope   organism, or organism.assembly.gene_set, or the group name
  date    Y-m-d  (sorts correctly; add _His only where several runs a day are expected)
  MARKER  CAPPED-N-per-organism, and anything else that makes the file PARTIAL
```

The rule worth keeping whatever the shape: **a filename must say what the file is, where it
came from, and whether it is complete.** The cap marker exists because the third one was
missing.

---

## Method

Walk each page, click every download, and record the ACTUAL filename produced — not what
the code appears to say. Two of the ones above were misread from source before being
clicked. Add the real name to the table, then judge it against the convention.
