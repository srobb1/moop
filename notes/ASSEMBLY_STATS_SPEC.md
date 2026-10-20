# Assembly statistics — the contract between the compute box and MOOP

**Status:** agreed 2026-10-20 (user). Pipeline side built 2026-10-20
(`analysis_parsers/assembly_stats_to_MOOP_JSON.pl`); the MOOP page
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
   "version"      : 2,
   "fasta_md5"    : "…",
   "fasta_bytes"  : …,
   "fasta_mtime"  : …,
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
| `contigs` | records split at every run of **≥ 10 consecutive N** (QUAST's default); runs shorter than 10 stay inside the contig. Leading/trailing N runs are trimmed **whatever their length** (user, 2026-10-20), not counted as contigs |
| `contig_n50` / `contig_l50` | as `n50` / `l50`, over those contigs, against the **total contig length** — the sum of the contig lengths, which includes any short (< 10) N runs left inside them but none of the ≥ 10 runs they were split at |
| `gap_bases` | count of N (and n) bases anywhere |
| `gap_pct` | `gap_bases / total_length × 100`, one decimal |
| `gc_pct` | `(G+C) / (A+C+G+T) × 100`, one decimal — Ns and any other IUPAC codes excluded from both sides |
| `fasta_bytes` / `fasta_mtime` | byte size and mtime (epoch seconds) of that `genome.fa`, taken before it is read — what decides whether the file is current (version 2+) |
| `fasta_md5` | md5 of the `genome.fa` the figures were computed from, so MOOP (or a person) can tell the stats belong to the genome on disk |
| `date` | when the file was written (YYYY-MM-DD) |
| `version` | `2` (2: `fasta_bytes` / `fasta_mtime` added). Bump if a definition above changes, so MOOP can tell old files from new |

Integers as JSON numbers, not strings. A field that cannot be computed is **omitted**, not
written as 0 or null.

### Checks — die rather than write a file that does not add up

- `sequences` ≥ 1, `total_length` > 0;
- `l50` ≤ `l90` ≤ `sequences` and `n50` ≥ `n90`; the same for the contig fields;
- `gap_bases` ≤ `total_length`;
- total contig length + bases in the split runs (≥ 10 N) + bases in the trimmed end runs (any length) == `total_length`;
- `sequences`, `total_length`, `n50`, `l50` agree with `genome.fa.fai` when one exists (cheap,
  and it catches reading the wrong file).

## Tested 2026-10-20

| Genome | Result | Time / memory |
|---|---|---|
| Nematostella GCA_033964005.1 (252 Mb) | 30 seqs, N50 16,735,929, L50 7, N90 13,131,831, L90 14 — exactly the `.fai` figures above; 793 contigs, contig N50 803,730 (L50 84); gaps 114,400 bp; GC 41.0% | 2 s |
| Tammar wallaby Haploid_plus_Y (3.35 Gb) | 9 seqs, N50 483,301,654, L50 3; 111 contigs, contig N50 113,185,175; gaps 1,225 bp; GC 38.7% | 106 s, 8 MB |
| Made-up edge cases | leading 3 N, internal 5 N (kept), 12 N split across lines (split), trailing 2 N, an all-N record, lower case: every figure as worked by hand | — |

Cross-checked with `seqkit stats` / `fx2tab` (count, total, longest, N50, N count, GC) and an
independent count of ≥ 10 N runs (Nvec: 763 runs + 30 records = 793 contigs). `fasta_md5` = `md5sum`.

## Getting it to MOOP

- **`process_one_geneset.sh`** runs the script right after `genome.fa.fai` is built, whenever the
  genome is present. **Recomputed only when the genome is not the one the file records — its
  byte size or mtime differs from `fasta_bytes` / `fasta_mtime` — or the file's `version` is not
  the script's** (user, 2026-10-20) — the genome can be 3.4 Gb.
- **✅ DONE 2026-10-20 (was a TO DO from the review of 45b1ea5): "current" no longer rests on "newer than".**
  `is_current()` skips when `assembly_stats.json` is newer than `genome.fa`. A genome replaced with
  `rsync -a` / `cp -p` keeps its ORIGINAL, older mtime, so the old stats would read as current and
  never be redone -- silently wrong. Fix: record the genome's byte size and mtime in the file
  (`fasta_bytes`, `fasta_mtime`) and treat it as current only when BOTH equal the genome's current
  `stat` exactly (and `version` matches). Bump `$FORMAT_VERSION` to 2 so every file is redone once
  and gains the two fields. MOOP's reader ignores fields it does not know, so this needs no web change.
  Built as described; tested with a genome replaced by an OLDER file via `cp -p` (json newer than
  it): recomputed. Unchanged genome: skipped. A version-1 file: recomputed.
  (The `.fai` / BLAST `needs_rebuild` checks in the same block share the `-nt` rule -- the same
  exposure, for the pipeline to decide separately.)
- **Per assembly, though the pipeline is per gene set** (user, 2026-10-20): the SCRIPT decides —
  when the file is current it exits at once without reading the genome. So an assembly's first
  gene set computes it and the others skip; no new loop, no per-assembly step. `--reload` does
  not force it (the figures depend only on `genome.fa`); a definition change bumps `version`,
  which does. Written to `.tmp` and renamed, so it is never half-written.
- No genome → the file is removed, and the gene set carries on. A failed run (bad FASTA, figures
  that do not add up, disagreement with the `.fai`) → the file is removed and a warning printed.
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
  page shows N50 **and L50** (user, 2026-10-20), contig N50, and gaps / GC where useful.
- Percentages are JSON numbers, so one decimal is lost when it is zero: `gap_pct` 0.04 is written
  `0`, GC 41.0 is written `41`. Format them on the page (`number_format($v, 1)`).
- **Fallback:** when there is no file — another installation that does not run this pipeline,
  or an assembly not yet re-run — the web server computes scaffold `sequences`,
  `total_length`, `n50` and `l50` from `genome.fa.fai`. (`moop_fai_stats()` returns sequences,
  total and N50 today; L50 is to be added there.) The contig, gap and GC
  fields simply do not appear. One reader, two sources; the file wins.
- A missing or malformed file is the fallback, never an error.
