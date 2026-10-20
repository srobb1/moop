# BUSCO on the gene set page — the contract between the compute box and MOOP

**Status:** agreed 2026-10-13 (user). Pipeline side built 2026-10-20; the MOOP page
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

BUSCO's output files change between versions (`short_summary*.txt` wording, the JSON keys). The
pipeline (`analysis_parsers/parse_BUSCO_to_MOOP_JSON.pl`) reads the `short_summary*.txt` files and
emits this fixed shape, so a BUSCO upgrade never touches MOOP. **Built 2026-10-20** — this is
Congeria's real file (`COKUS1KC`):

```json
{
   "protein_fasta_md5" : "5b913067381c103dba135861420cb8a5",
   "runs" : [
      { "lineage" : "metazoa_odb10",  "role" : "auto-selected", "version" : "5.4.4", "mode" : "proteins",
        "n" : 954, "complete" : 84.1, "single" : 77.3, "duplicated" : 6.8, "fragmented" : 5.3, "missing" : 10.6,
        "complete_n" : 802, "single_n" : 737, "duplicated_n" : 65, "fragmented_n" : 51, "missing_n" : 101,
        "dataset_date" : "2024-01-08", "date" : "2026-08-04" },
      { "lineage" : "eukaryota_odb10", "role" : "generic", ... }
   ],
   "warnings" : []
}
```

- **One file, one entry in `runs` per lineage** (decided 2026-10-20), most specific first:
  `auto-selected` (the lineage BUSCO's auto-lineage placed the gene set in), `fixed` (run with
  `-l`, here metazoa_odb10), `generic` (eukaryota_odb10). A gene set has 2 or 3.
- **The same lineage twice is kept once** (user, 2026-10-20): when auto-lineage picks metazoa, the
  auto run and the fixed metazoa run are the same result; the `auto-selected` one is kept. If they
  ever differ, both are kept and a warning says so.
- Percentages (one decimal, as BUSCO reports them) AND counts (`*_n`, from the summary), `n` = the
  number of BUSCOs in the lineage. The parser checks counts add up to `n` exactly, complete =
  single + duplicated, and the percentages add up to 100 (± 0.25); it dies rather than write a
  file that does not add up.
- `mode` is `proteins` for a gene set (`genome` for an assembly-level file, if ever run).
- `dataset_date` = the lineage dataset's creation date. `date` = the summary file's date: BUSCO
  does not record when it ran, so this is the closest there is.
- `warnings` (also printed on stderr) — the page may show them:
  - BUSCO ran on a different FASTA than this gene set's `protein.aa.fa` (`busco_job_cmd.txt`
    `IN_FASTA=`);
  - `protein.aa.fa` is newer than the BUSCO results: the gene models changed after BUSCO ran.
    First full run (2026-10-20): 6 gene sets — Chamaeleo MENDER_20260701 and five land
    planarians (bvag, cpin, crob, dmul, bkew, whose protein files were rewritten 2026-08-17).
- `protein_fasta_md5` = the proteins the file was made against.

### The input it reads (live tree `annotations/sbgenomes_2`, BUSCO 5.4.4, odb10)

    busco/<x>_auto/short_summary.generic.eukaryota_odb10.*.txt     -> generic
    busco/<x>_auto/short_summary.specific.<lineage>.*.txt          -> auto-selected (eukaryota -> generic)
    busco/<x>_met/short_summary.specific.metazoa_odb10.*.txt       -> fixed
    busco/<x>_*/busco_job_cmd.txt                                  IN_FASTA=, for the FASTA check

First full run: 88 of 91 `busco/` folders parsed (56 with 3 lineages, 32 with 2). The other 3 have
an analysis directory named differently from the gene set in `genomes/v2` (Drosophila BDGP6.54,
Rousettus Release-101, Sturnira Release-100.20210611) — the pipeline never looks there anyway.
`moop/annotations/SBGENOMES_2026-05-21` is a copy holding BUSCO 5.8.2 / odb12 re-runs; we stay on
the live tree for now (user, 2026-10-20).

## Getting it to MOOP

- `process_one_geneset.sh` (`make_busco_json`, after DeepLoc) writes `$GENESET_DATA/busco.json`
  on **every** run, not gated on the file existing: it is derived, and a reload must not leave
  the BUSCO of old gene models. No `busco/` folder, or a failed parse → no `busco.json` (and a
  warning), and the gene set carries on.
- `copy2moop_v2.sh` sends `busco.json` with the gene-set files; when there is none it removes any
  copy left on the server by an earlier run.
- Perl conventions (user maintains these scripts): named loop variables, never bare `$_`; all work
  in `sub main`, `main();` as the last line (the file-scope trap — `CLAUDE.md` §12).

## What MOOP does with it (web side, built after the files exist)

- `tools/gene_set.php` reads `busco.json` next to `geneset.json`. **No file → no BUSCO card, never
  an error** — most gene sets will not have one until the pipeline reruns.
- A card: one complete / fragmented / missing bar per entry in `runs` (lineage + version on each),
  single vs duplicated, counts from `*_n`; `warnings`, if any, as a note.
- Assembly page shows the genome-mode file the same way, if present.
