# Expression Explorer — many genes × chosen experiments

**Status:** architecture decided 2026-07-14; **revised 2026-09-14** (user): built on the same modules
as the gene-page expression section, which ships first. Not built.

A standalone tool where the user:

1. **Builds a gene list** — pasted, or handed over from the gene page or a results table
2. **Chooses experiments** — the same picker as the gene page
3. **Gets a heatmap** — genes × samples, grouped by experiment

## What it reuses, and what it adds

The catalog, the matrix API, the experiment picker and the chart module are specified in
`EXPRESSION_GENE_PAGE_PLAN.md` ("One set of modules, two pages"). The gene page is this tool with its
gene list fixed to one gene. The Explorer adds only:

```
tools/expression.php          controller (tool_init.php pattern)
tools/pages/expression.php    gene-list input + picker + chart
js/expression.js              page script: gene list, hand-offs, heatmap controls
```

plus the heatmap view inside `js/modules/expression-chart.js`, and an entry in
`config/tools_config.php`.

## Query shape

Many genes × chosen experiments, **one gene set per matrix**. Gene ids belong to a gene set, so a list
cannot mix organisms; comparing across organisms would go through orthology
(`NEW_TOOL_SURVEY_AND_RECOMMENDATIONS.md`), which is separate work. Only Nvec has registered RNA-seq
today, so this limits nothing yet.

## Where the gene list comes from

- **Pasted ids**, checked against the gene set. Report the ids that did not match; never drop them
  silently.
- **From the gene page** — "Open in Explorer" posts the gene and the chosen experiments.
- **From a results table** — the shared results table (`js/modules/shared-results-table.js`) has row
  selection for export but no "send selected genes to a tool" action. Adding one would serve more than
  expression: gene lists are one of the recurring gaps in `NEW_TOOL_SURVEY_AND_RECOMMENDATIONS.md`.
  Read the selection from the table's own DataTables instance, never page-wide (CLAUDE.md §9b).
- **How many genes:** unmeasured. 1,000 genes is ~4 MB of rows, but cold that may be ~1,000 random
  reads (CLAUDE.md §9: the cold cache dominates). Measure before setting a cap. A heatmap stops being
  readable well before that anyway.

## The heatmap

- Rows = genes, each linking to its gene page. Columns = samples, **grouped into one block per
  experiment with a visible gap**; replicates collapsed to the group mean, expandable.
- **Colour is scaled within each experiment block** (log, or z-score across that gene's samples in the
  block). Never one colour scale across experiments while normalization is unverified (data-layer
  §6c).
- `null` (no data) is drawn distinctly — hatched or grey — never in the colour for zero.
- Rows sort by input order or by a chosen column. **Open:** clustering rows needs either a vendored
  library or hand-written hierarchical clustering; leave it out of v1.
- A short list can also use the gene page's per-experiment charts, one line per gene.
- A "show numbers" table with export, as on the gene page.

## Serving the query

- **Production:** the precomputed matrix, through the shared API.
- **Prototype and sanity gate:** live `bigWigSummary` behind the same API. That is one exec plus a
  network read per cell, so 20 genes × 24 samples = 480 calls ≈ **50 s serially**. Hard cap, and cache
  the result under `cache_path` keyed by (gene set, sample set). Build it first anyway: it is how the
  precomputed numbers get checked (data-layer §6g).

## Changed on 2026-09-14

- **Rendering.** The old note named Chart.js. No chart library is bundled; the chart module is
  hand-written SVG (gene-page plan, shared component 4).
- **Fan-out.** The old note said to fan out with `proc_open()`, "the same pattern `AnnotationSearch`
  already uses". It does not: annotation search fans out **in JavaScript**, one request per organism,
  concurrency 5 (`js/modules/annotation-search.js:559`; the Data Exporter does the same at
  `js/modules/moopmart.js:850`). Either works for the live prototype; the JavaScript version gives
  step-level progress for free.
- **API.** Was `gene_ids × track_ids → { gene: { track: signal } }`. Now keyed by **sample** — strand
  pairs merged on the server by the gene's strand — and by gene set.
- **Scale.** "966 RNASeq tracks in 17 experiment groups" is 17 experiments and **~646 samples**.
- **Picker tree.** `source → experiment → tissue/condition` does not describe most experiments; their
  factors sit in dropped sheet columns and in display names (gene-page plan, shared component 1).

## Depends on

- `EXPRESSION_GENE_PAGE_PLAN.md` — the shared modules, and the build order for both pages.
- `EXPRESSION_DATA_LAYER_PLAN.md` — catalog source, coordinates, the bigWig read path and its gotchas
  (negative `.neg.bw` values, rc=255 → null, `-udcDir`, token-poisoned cache key), precompute.
- Upstream: `SRA_RNASEQ_DISCOVERY_PLAN.md` feeds *new* experiments in — and may recover Yanai's time
  points from their accessions.
