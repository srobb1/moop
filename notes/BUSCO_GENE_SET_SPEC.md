# BUSCO on the gene set page — the contract between the compute box and MOOP

**Status:** agreed 2026-10-13 (user). Pipeline side to be built on the compute box; the MOOP page
reads what is described here. Same model as expression (`EXPRESSION_BUNDLE_SPEC.md`): **built on the
compute box, copied to the web server, only ever read there.**

## Why

The annotation pipeline already runs BUSCO for every gene set and writes a `busco/` folder, e.g.

    dev/smr_dev/moop/annotations/SBGENOMES_2026-05-21/Congeria_kusceri/GCA_027627225.1/COKUS1KC/busco/

Nothing carries it to MOOP, so a completeness figure we already have (Congeria: complete 84.1%,
fragmented 5.3%) is shown nowhere. A gene-set page is exactly where a user decides whether to trust a
gene set.

## The file: `busco.json`, one per gene set

```
{organism}/{assembly}/{gene_set}/busco.json        protein-mode BUSCO of the gene set
{organism}/{assembly}/busco.json                   genome-mode BUSCO, if ever run  -> assembly page
```

Names are exactly MOOP's (`Congeria_kusceri/GCA_027627225.1/COKUS1KC/busco.json`).

**A separate file, NOT a field in `geneset.json` / `genome.json`.** Those are SITE METADATA: written
once by `process_one_geneset.sh` and deliberately left alone by `--reload` (see `run_all_v2.sh`). BUSCO
is DERIVED — it changes when the gene models change — so inside `geneset.json` it would silently go
stale after a reload. Keep the split: hand-kept metadata vs regenerated results.

## Shape — the pipeline normalises; MOOP never parses BUSCO's own files

BUSCO's output files change between versions (`short_summary*.txt` wording, the JSON summary only in
v5+). The pipeline reads whatever its BUSCO version wrote and emits this fixed shape, so a BUSCO
upgrade never touches MOOP. (Values below are illustrative — only Congeria's 84.1 / 5.3 are real.)

```json
{
  "lineage":    "metazoa_odb10",
  "version":    "5.7.1",
  "mode":       "proteins",
  "n":          954,
  "complete":   84.1,
  "single":     82.0,
  "duplicated": 2.1,
  "fragmented": 5.3,
  "missing":    10.6,
  "date":       "2026-05-21"
}
```

- Percentages, one decimal, as BUSCO reports them. `n` is the number of BUSCOs in the lineage, so
  counts can be shown too (`complete * n / 100`).
- `complete = single + duplicated`; `complete + fragmented + missing = 100` (± rounding). The
  converter should check both and fail loudly rather than write a file that does not add up.
- `mode` is `proteins` for a gene set and `genome` for the assembly-level file.
- `date` = when BUSCO ran (not when the file was copied).
- **Before writing the converter, read a REAL `busco/` folder** on the compute box — the format
  varies by version, which is the whole reason for this file.

## Getting it to MOOP

- `copy2moop_v2.sh` ships `busco.json` with the other DERIVED gene-set files, so every reload
  refreshes it.
- Perl conventions (user maintains these scripts): named loop variables, never bare `$_`; all work
  in `sub main`, `main();` as the last line (the file-scope trap — `CLAUDE.md` §12).

## What MOOP does with it (web side, built after the files exist)

- `tools/gene_set.php` reads `busco.json` next to `geneset.json`. **No file → no BUSCO card, never
  an error** — most gene sets will not have one until the pipeline reruns.
- A card: lineage + version, a complete / fragmented / missing bar, single vs duplicated.
- Assembly page shows the genome-mode file the same way, if present.
