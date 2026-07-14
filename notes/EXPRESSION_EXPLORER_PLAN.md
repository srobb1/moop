# Expression Explorer — many genes × few experiments

**Status:** architecture decided; not built. Sits on `EXPRESSION_DATA_LAYER_PLAN.md`.

A standalone MOOPmart-style tool where the user:

1. **Builds a gene list** (paste IDs, or search)
2. **Selects experiments** from a grouped tree (`source → experiment → tissue/condition`)
3. **Gets a heatmap / bar chart** — genes × samples, signal = mean coverage over the gene body

## Query shape

**Many genes × few experiments** — the inverse of the gene-page section
(`EXPRESSION_GENE_PAGE_PLAN.md`, 1 gene × 966 tracks). Both read the same data layer.

## Architecture

```
tools/expression.php          controller (tool_init.php pattern)
tools/pages/expression.php    UI: gene selector + experiment tree + viz
api/expression/query.php      AJAX: gene_ids × track_ids → matrix JSON
js/modules/expression.js      tree checkboxes, fetch, Chart.js render
```

`POST { organism, assembly, gene_ids[], track_ids[] }` → `{ gene_id: { track_id: signal } }`

## Two ways to serve the query

- **Precomputed (production).** Read `expression.sqlite` — N gene rows, slice the selected track
  columns (data-layer §6b). Instant, and **the query cap below stops being necessary at all**.
- **Live (prototype/fallback).** `bigWigSummary` per (gene × track). Works — verified — but it's one
  exec + network read per cell: 20 genes × 24 tracks = 480 calls ≈ **50 s serially**. Usable only for
  a demo, and only with a hard cap + `proc_open()` fan-out at concurrency ~5–10 (the same pattern
  `AnnotationSearch` already uses).

**Build the live path first anyway** — it's the sanity gate that proves the precomputed numbers are
right (data-layer §6g).

## UI notes

- **Experiment selector tree** from the track JSONs: `source → experiment → tissue/condition`
  checkboxes. Nvec has **966 RNASeq tracks in 17 experiment groups** — the user must filter to a group
  before querying; never fan out over all 966.
- Shares the catalog/grouping code with the gene-page section (data-layer §2, shared component #1).
- While on the live path, cap the query (e.g. ≤ 50 genes × 1 experiment group) and cache the resulting
  matrix under `cache_path` keyed by (assembly, gene set, track set).
- **Open:** heatmap vs. bar chart (standalone page is settled). Scale per gene (log / z-score) at
  display time — stored values are raw means (data-layer §6c).

## Depends on

- `EXPRESSION_DATA_LAYER_PLAN.md` — catalog, coords, token/bigWig read path, precompute. All the
  verified gotchas (negative `.neg.bw` values, rc=255 → null, `-udcDir`, token-poisoned cache key)
  live there, not here.
- Sibling: `EXPRESSION_GENE_PAGE_PLAN.md`
- Upstream: `SRA_RNASEQ_DISCOVERY_PLAN.md` feeds *new* experiments into the data layer.
