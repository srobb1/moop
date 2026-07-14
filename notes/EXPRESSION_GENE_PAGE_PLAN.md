# Gene-page expression section — 1 gene × many tracks

**Status:** idea captured 2026-07-14 (user). Not started. **Blocked on the precompute** in
`EXPRESSION_DATA_LAYER_PLAN.md` — see below, this is not optional.

## Idea

On a gene's parent page, add a section showing the RNA-seq experiments we have that cover this gene —
at-a-glance expression across our data, without leaving the page.

## Why this shape is hard: it is the *inverse* of the Explorer

The Explorer is many genes × few experiments. This is **one gene × ALL tracks** — 966 RNASeq bigWigs
for Nvec. And because the kent udc cache warms **per file**, touching 966 *different* files means
**every single read is a cold ~1 s read**:

> 966 tracks × ~1 s cold ≈ **~16 minutes per gene page**. Even at concurrency 10, ~1.6 min.
> **Live querying is disqualified** — not merely slow.

The warm 50 ms figure from the live-read tests does **not** apply here; that only holds when you hit
the same file repeatedly.

## Therefore: this feature *is* the precompute

With `expression.sqlite` (per-gene float32 BLOB, one row per gene — see the data-layer plan §6b), the
gene page becomes:

```sql
SELECT values FROM expression WHERE gene_id = ?   -- ONE row, ~4 KB, indexed
```

→ unpack 966 floats in PHP, group, render. **Milliseconds. Zero bigWig reads in the request path.**

This is the feature that justifies building the precompute at all. Nothing else about it is expensive.

## UI

**Never render 966 columns.** Group by the catalog tree from the track JSONs
(`source → experiment → condition`) — Nvec's 966 RNASeq tracks collapse to **17 experiment groups**:

- Default view: a compact **per-experiment summary strip** — one row per experiment, mean per
  condition, replicates collapsed. Skimmable in a glance.
- Expand an experiment → its individual samples/conditions.
- Scale per gene (log or z-score across samples) at display time — the stored value is a raw mean
  (data-layer §6c).
- Grey out / omit experiments where this gene has **no data** (NaN, not 0.0 — the distinction is
  preserved deliberately).
- Reuse the same grouping code as the Explorer's selector tree (data-layer §2, shared component #1).

Fits the existing gene page's section-card style, and the section-nav sidebar
(`plan_parent_page_nav_sidebar`) will pick it up automatically as another section.

## Honest caveat

Cross-experiment comparison on this page is only as trustworthy as the normalization consistency
across the 966 tracks — which is **unverified** (data-layer §6c). Until that's audited, present it as
*within-experiment* comparison and be careful about implying a gene is "higher in experiment A than
experiment B."

## Depends on

- `EXPRESSION_DATA_LAYER_PLAN.md` — catalog reader (§2), precompute → `expression.sqlite` (§5, §6).
  **Build that first.** This page is a thin read on top of it.
- Sibling: `EXPRESSION_EXPLORER_PLAN.md` — same data layer, opposite query shape.
