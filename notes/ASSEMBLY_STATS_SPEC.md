# Assembly statistics — the contract between the compute box and MOOP

**Status:** agreed 2026-10-20 (user). Pipeline side to be built on the compute box; the MOOP page
reads what is described here. Same model as BUSCO (`BUSCO_GENE_SET_SPEC.md`) and expression
(`EXPRESSION_BUNDLE_SPEC.md`): **built on the compute box, copied to the web server, only ever
read there.**

## Why

The organism page has a table comparing every assembly + gene set (commit 89734271,
`includes/gene_set_comparison.php`), so a reader can choose one without opening each. Its
"Assembly N50" column is computed today on the web server, in the background, from
`genome.fa.fai` (`lib/gene_set_summary.php`, `moop_fai_stats()`). That works, but:

- **A `.fai` only has sequence lengths.** It cannot give **contig N50** (scaffolds split at their
  gaps), the gap content, or GC — and contig N50 is often the more honest contiguity figure: a
  scaffolded assembly can post a large scaffold N50 that is mostly Ns.
- **No genome on the web server, no figure.** Phagocata velata's three assemblies have no
  `genome.fa` here, so their N50 shows "—". The compute box has every genome.
- It is derived, fixed per assembly, and cheap to compute next to the genome — exactly the kind
  of thing the pipeline should produce once rather than the web server re-derive.

## The file: `assembly_stats.json`, one per assembly

```
{organism}/{assembly}/assembly_stats.json
```

Beside `genome.json` and `genome.fa`. Names exactly MOOP's
(`Nematostella_vectensis/GCA_033964005.1/assembly_stats.json`).

**A separate file, NOT a field in `genome.json`.** `genome.json` is SITE METADATA: written once by
`process_one_geneset.sh` (`write_genome_json`) and deliberately left alone by `--reload`. These
statistics are DERIVED from `genome.fa`, so they belong with the other regenerated results and
are remade whenever the genome is. Same reasoning as `busco.json` vs `geneset.json`.

## Shape

```json
{
   "version"      : 1,
   "fasta_md5"    : "…",
   "date"         : "2026-10-20",
   "sequences"    : 30,
   "total_length" : 252273000,
   "n50"          : 16735929,
   "l50"          : 7,
   "n90"          : 13131831,
   "l90"          : 14,
   "longest"      : 21000000,
   "contigs"      : …,
   "contig_n50"   : …,
   "contig_l50"   : …,
   "gap_bases"    : …,
   "gap_pct"      : 0.8,
   "gc_pct"       : 38.1
}
```

The scaffold-level values above (`sequences`, `total_length`, `n50`, `l50`, `n90`, `l90`) are
Nematostella GCA_033964005.1's real figures, computed from its `.fai` on the web server — the
pipeline's output for that assembly must match them exactly. `longest`, the contig fields, gaps
and GC are illustrative.

### Definitions — exact, so two implementations agree

All lengths in bases. Case-insensitive (soft-masked lower-case bases count like upper-case).

| Field | Meaning |
|---|---|
| `sequences` | records in `genome.fa` |
| `total_length` | sum of all record lengths, **Ns included** |
| `n50` / `n90` | sort records longest first; the length of the record at which the running sum first reaches ≥ 50% / ≥ 90% of `total_length` |
| `l50` / `l90` | how many records that took (1-based) — "half the genome is in `l50` sequences" |
| `longest` | the longest record |
| `contigs` | records split at every run of **≥ 10 consecutive N** (QUAST's default); runs shorter than 10 stay inside the contig. Leading/trailing N runs are trimmed, not counted as contigs |
| `contig_n50` / `contig_l50` | as `n50` / `l50`, over those contigs, against the **total contig length** — the sum of the contig lengths, which includes any short (< 10) N runs left inside them but none of the ≥ 10 runs they were split at |
| `gap_bases` | count of N (and n) bases anywhere |
| `gap_pct` | `gap_bases / total_length × 100`, one decimal |
| `gc_pct` | `(G+C) / (A+C+G+T) × 100`, one decimal — Ns and any other IUPAC codes excluded from both sides |
| `fasta_md5` | md5 of the `genome.fa` the figures were computed from, so MOOP (or a person) can tell the stats belong to the genome on disk |
| `date` | when the file was written (YYYY-MM-DD) |
| `version` | `1`. Bump if a definition above changes, so MOOP can tell old files from new |

Integers as JSON numbers, not strings. A field that cannot be computed is **omitted**, not
written as 0 or null.

### Checks — die rather than write a file that does not add up

- `sequences` ≥ 1, `total_length` > 0;
- `l50` ≤ `l90` ≤ `sequences` and `n50` ≥ `n90`; the same for the contig fields;
- `gap_bases` ≤ `total_length`;
- total contig length + bases in the split runs (≥ 10 N, including leading/trailing ones) == `total_length`;
- `sequences`, `total_length`, `n50`, `l50` agree with `genome.fa.fai` when one exists (cheap,
  and it catches reading the wrong file).

## Getting it to MOOP

- **`process_one_geneset.sh`** writes `$ASSEMBLY_DATA/assembly_stats.json` whenever the genome is
  present — remade on every run, like `busco.json` (derived; a re-run must never leave the stats
  of a previous genome). No genome → no file, and the gene set carries on.
- **`copy2moop_v2.sh`** sends it with the other assembly-level files, in the `HAS_GENOME` block
  next to `genome.fa` / `genome.fa.fai`. When there is none, remove any copy left on the server
  by an earlier run (the same pattern as `busco.json`).
- One small script, e.g. `analysis_parsers/assembly_stats_to_MOOP_JSON.pl`, streaming the FASTA
  once (genomes reach several Gb — never slurp). Perl conventions (user maintains these
  scripts): named loop variables, never bare `$_`; all work in `sub main`, `main();` as the
  last line (the file-scope trap — `CLAUDE.md` §12; `check_perl_file_scope.pl` enforces it).
  Core modules only (`Digest::MD5`, `JSON::PP`) — see `config/build_and_load_db/cpanfile`.

## What MOOP does with it (web side, built after the files exist)

- `lib/gene_set_summary.php` reads `assembly_stats.json` when present and valid; the organism
  page's "Assembly N50" column then also shows L50 and contig N50, and gaps / GC where useful.
- **Fallback:** when there is no file — another installation that does not run this pipeline,
  or an assembly not yet re-run — the web server keeps computing scaffold `sequences`,
  `total_length`, `n50` and `l50` from `genome.fa.fai` as it does today. The contig, gap and GC
  fields simply do not appear. One reader, two sources; the file wins.
- A missing or malformed file is the fallback, never an error.
