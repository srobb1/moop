# Single-cell expression bundle — what the compute box produces for MOOP

**Status:** draft, 2026-10-22 (agreed shape, no data yet). Extends `EXPRESSION_BUNDLE_SPEC.md`;
everything that file says — layout, file rules, ids, `experiment.json`, access levels, overrides,
`rsync --delete`, the web server only reading — holds here and is **not repeated**. This file covers
only what single-cell adds. Where the two disagree, the bundle spec wins until this one is updated.

## The model

Same rule as bulk: **one parser per analysis format, on the compute box** (a Seurat `.rds` today, an
h5ad or anything else later) writes the standard files below; they are copied to the web server and
MOOP reads them. A new format means a new parser, never a change to MOOP.

People hand over **Seurat objects** (`.rds` / `.RData`, R's own format — not h5ad). So the first parser
is an R script that opens the `.rds` and writes these files directly. No h5ad conversion step.

## Two parts — build the first, add the second when wanted

| Part | Files | What MOOP shows | Needed |
|---|---|---|---|
| **A. Per cell type** (pseudobulk) | the bulk bundle files + `pct_expressed.tsv` | Expression tool: heatmap and **dot plot**, genes × cell types; gene page summary | always |
| **B. Per cell** | `cells/` folder | **UMAP** coloured by a gene, one panel per gene | optional |

Part A is an ordinary bulk bundle whose groups are cell types, so everything that reads bulk data reads
it unchanged. Part B is only needed for the UMAP view.

## Layout

```
{organism}/{assembly}/{gene_set}/expression/{experiment}/
    experiment.json          required   assay = "scrna" (see below)
    samples.tsv              required   one row per cell type (or per replicate × cell type)
    counts.tsv               required   summed raw counts per column (pseudobulk)
    tpm.tsv                  required   normalized pseudobulk — what the heatmap reads (CPM, see below)
    pct_expressed.tsv        required   % of the column's cells with the gene detected — dot size
    qc.tsv                   recommended  per column: cells, median genes, median UMIs
    unmatched_features.tsv   recommended  features the parser could not map to a gene set id
    provenance.json          recommended
    cells/                   optional — Part B
        cells.tsv
        expression.tsv
        expression.idx
```

## Part A — per cell type

### Columns: what is one "sample" here

A column of `counts.tsv` is a set of cells summed together:

- **One column per cell type** when the object has no biological replicates.
- **One column per replicate × cell type** when it has them (`rep1_neuron`, `rep2_neuron`); `group` is
  the cell type, so replicates are averaged for display exactly as in bulk.

### `samples.tsv`

The bulk columns, with these meanings:

| Column | Meaning |
|---|---|
| `sample_id` | the column header: `neuron`, or `rep1_neuron` |
| `group` | the cell type as a person should read it: `neuron (Elav+)`. **Row order is display order** — put related cell types together, not alphabetical |
| `replicate` | as bulk |
| `n_cells` | **required here**: how many cells were summed into this column |
| `cell_type_id` | the label exactly as in the Seurat metadata, if `group` rewrites it |

### `counts.tsv` / `tpm.tsv`

- `counts.tsv`: for each gene and column, the **sum of raw counts** over that column's cells
  (Seurat `counts` layer, not `data`).
- `tpm.tsv`: **CPM** of those sums — `count / column total × 1,000,000`, 3 decimals. Droplet
  (10x 3′) counts are not length-biased, so no length correction. The file keeps the name `tpm.tsv` so
  every reader works unchanged; `experiment.json` says the unit (`"unit": "CPM"`), and MOOP labels
  values by that unit. *(Web-side follow-up: the pages say "TPM" today — they must read `unit`.)*
- Same sorting, id and `NA` rules as bulk.

### `pct_expressed.tsv`

Same shape and order as `tpm.tsv`; each value is the percentage (0–100, 1 decimal) of that column's
cells with a count > 0 for the gene. This is the dot size in a dot plot — "how many cells", which an
average cannot tell apart from "a few cells very high".

### `experiment.json` — single-cell fields

All bulk fields, plus:

```json
{
  "label": "Whole-animal single-cell atlas (Example Lab)",
  "assay": "scrna",
  "unit": "CPM",
  "detect_threshold": 1,
  "technology": "10x Chromium 3' v3",
  "n_cells": 48213,
  "cell_type_source": "metadata column 'celltype_final'",
  "embedding": "umap"
}
```

- `assay`: `scrna`. (The bundle spec's `scrna_pseudobulk` means the same for Part A; prefer `scrna`.)
- `unit`: `CPM` for droplet data; `TPM` only if a length-aware quantifier was used.
- `n_cells`: total cells in the object after filtering.
- `embedding`: the reduction written to `cells/` (`umap`, `tsne`), or absent if there is no Part B.

### `qc.tsv`

One row per column: `sample_id`, `n_cells`, `median_genes_per_cell`, `median_umis_per_cell`.

## Part B — per cell (optional)

### `cells/cells.tsv` — one row per cell

```
cell_id	cell_type	x	y	sample
AAACCTGAGAAGGCCT-1	neuron	-4.213	7.882	rep1
```

- `cell_type` is a `sample_id`'s cell type as in `samples.tsv` (`cell_type_id` if given, else `group`).
- `x`, `y`: the embedding coordinates, 3 decimals.
- **Row order defines the cell index** used in `expression.tsv` (first data row = 0). Never reorder
  one file without the other.
- About 50 bytes a cell: ~2.5 MB for 50,000 cells. MOOP sends it to the browser once per dataset.

### `cells/expression.tsv` — one line per gene, only the cells that express it

```
NV2g010624000.1	12:1.837,57:0.693,58:2.104,…
```

- Column 1: gene id. Column 2: comma-separated `cell_index:value` pairs, **only cells with a value
  above 0**, cell index ascending.
- Value: the **log-normalized** expression Seurat shows (`data` layer: `log1p(count / cell total ×
  10,000)`), 3 decimals. Say so in `provenance.json` if a different normalization (SCT) was used.
- Sorted by gene id, byte order. A gene detected in no cell has no line.
- Size: (cells × genes detected per cell) × ~10 bytes — e.g. 50,000 cells × 2,000 genes ≈ 1 GB.
  Fine on disk; never read whole.

### `cells/expression.idx` — where each gene's line starts

```
NV2g010624000.1	104857	52133
```

Gene id, byte offset of its line in `expression.tsv`, line length in bytes. MOOP seeks straight to
the line (milliseconds, whatever the file size) instead of scanning a gigabyte. The checker verifies
that every offset lands on its gene's line.

## Gene ids — the parser's main job

Seurat objects are often built on **gene symbols** or another annotation's ids, not the gene set's.
The parser must:

1. Map every feature to a gene set uniquename (with version: `NV2g010624000.1`), using a mapping file
   the person supplies when the names are not already ids.
2. **Undo Seurat's renaming:** Seurat replaces `_` with `-` in feature names, so `ABC_123` arrives as
   `ABC-123`. Map back against the gene set's real ids.
3. Write every feature it cannot map to `unmatched_features.tsv` (feature, reason) — never drop
   silently — and count them in `provenance.json`.
4. Record which gene set the object was built against. An object built on a different annotation is a
   different gene set, not this one.

## `provenance.json` — single-cell additions

At least: `source_file` (+ `md5`), `seurat_version`, `assay_used` (`RNA` / `SCT`), `normalization`,
`cell_type_column`, `reduction`, `cells_after_filtering`, `features_total`, `features_mapped`,
`features_unmatched`, `id_mapping_file`, `run_by`, `run_date`.

## R parser sketch (for the compute side — illustrative, not prescriptive)

```r
obj    <- readRDS("atlas.rds")
counts <- GetAssayData(obj, assay = "RNA", layer = "counts")   # genes x cells, sparse
lognrm <- GetAssayData(obj, assay = "RNA", layer = "data")
ctype  <- obj@meta.data[["celltype_final"]]
emb    <- Embeddings(obj, reduction = "umap")                 # cells x 2
# Part A: rowsum(t(counts), ctype) -> counts.tsv ; CPM -> tpm.tsv ;
#         per type, rowMeans(counts[, cells] > 0) * 100 -> pct_expressed.tsv
# Part B: cells.tsv from emb + ctype ; for each gene, its non-zero lognrm entries -> expression.tsv,
#         recording each line's byte offset -> expression.idx
```

## What MOOP will do with it (web side, when data exists)

1. Part A works in today's Expression tool as a heatmap with no change (cell types are groups).
   Then: label values by `unit`, and add a **dot plot** view (colour = CPM, size = `pct_expressed`).
2. Part B: a UMAP panel per gene, drawn on a `<canvas>` in the browser from `cells.tsv` and the gene's
   `expression.tsv` line. No charting library.
3. `check_expression.php` gains the single-cell checks: `n_cells`, `pct_expressed` shape, `cells.tsv` ↔
   `cell_type`, every `expression.idx` offset, unmatched-feature count.

Reference reviewed 2026-10-22: QTuLab/miniodp (Python Dash + Plotly, reads h5ad per request; UMAP and
violin per gene, 4 genes a page, no multi-gene dot plot). MOOP takes the outputs, not the stack.
