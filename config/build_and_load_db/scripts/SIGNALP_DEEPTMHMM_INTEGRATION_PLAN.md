# Plan: wire SignalP6 + DeepTMHMM into process_one_geneset.sh

Status as of 2026-09-16: **core wiring done and verified**, per the checklist
below. Done: both parser bug fixes, the shared loader's Accession-URL fix, the
`process_one_geneset.sh` wiring (`make_signalp_moop`/`make_deeptmhmm_moop`,
missing-file checks), and the `setup_new_moopdb_and_load_data.sh` load line.
Verified against a scratch copy of the real Anoura `organism.sqlite` — 100%
feature-attach rate, correct TMhelix/Beta sheet split, clean `1.0` version
string, `NULL` accession URLs.

**Also done:** `lib/parent_functions.php` now renders the accession as plain
text when `annotation_accession_url` is NULL/empty, instead of emitting a
broken `<a href="SP">`. Required `git sparse-checkout add lib` (this repo's
sparse-checkout was previously limited to `config/build_and_load_db`) — `lib/`
is now checked out too.

**Not done yet** (still needs its own session):
- The optional DeepTMHMM Topology file (GLOB/SP/TM/SP+TM/BETA per protein,
  from `.3line`'s header line) — skipped this round to stay in scope, not
  ruled out.
- Has **not** been run against real per-organism data or with `--reload` —
  only against a disposable scratch copy. That's the user's call to trigger.
- `.3line` FASTA linking and the topology illustration remain deferred, as
  before (see Decisions below).

## Goal

Two new parsers already exist and were reviewed against real data
(`Anoura_caudifer/GCA_004027475.1/SIMR_2025-01-24`):

- `analysis_parsers/parse_SIGNALP_to_MOOP_TSV.pl`
- `analysis_parsers/parse_DEEPTMHMM_to_MOOP_TSV.pl`

Wire them into `scripts/process_one_geneset.sh` (build step) and
`scripts/setup_new_moopdb_and_load_data.sh` (load step), fixing the bugs found
during review first.

## Decisions made (do not re-litigate without asking)

- **Accession stays human-readable**: `SP`, `TMhelix`, `Beta sheet` — not a
  UniProt keyword or SO term. User's and a colleague's call.
- **Annotation Accession URL stays blank** for both sources. There is no real
  per-term registry to link to (SignalP/DeepTMHMM are local predictions, not
  database entries), so rather than inventing a link, `lib/parent_functions.php`
  gets fixed to render the accession as plain text when the URL is empty,
  instead of emitting a broken `<a href="SP">`.
- **SignalP: SP predictions only** — LIPO/TAT/TATLIPO/PILIN are deliberately
  dropped (user + colleague determined most people only care about SP). Not a
  bug; do not "fix" this later without asking.
- **DeepTMHMM's `.3line` topology FASTA**: user likes the idea of copying it
  into the served tree and linking accessions to a specific record (same
  `.fai`-style point-lookup pattern already used for protein/transcript/cds
  FASTAs — see the comment above the `.fai` loop in `process_one_geneset.sh`).
  **Deferred as a fast-follow.** Needs a new small index format (the file is
  3 lines/record, a plain `.fai` won't parse it) plus a new serving endpoint.
  Not part of this round.
- **Topology illustration** (inside/membrane/outside cartoon per protein):
  someday idea, no new data needed when it happens — `deeptmhmm_results.gff3`
  already has segment type + start/end coordinates per protein, which is all
  a renderer would need. Not part of this round; just noted so it isn't lost.

## Bugs found in review (fix before wiring in)

### `parse_SIGNALP_to_MOOP_TSV.pl`
1. `next unless $line =~ /\tSP\t/` matches the substring anywhere in the line,
   not the Prediction column. Change to check `$line[1] eq 'SP'` after the
   split.
2. `$line[-1]` (the CS-position text) is only safe because every SP row in the
   sampled file happens to have 9 fields. Add a guard: only use it when
   `defined` and it looks like `CS pos: \d+-\d+`; otherwise write description
   as plain `"Signal Peptide"` with no position.
3. Minor: quote `$top_hits` in the `` `date '+%Y-%m-%d' -r $top_hits` ``
   backtick call; drop unused `%annot`; die message still says
   `.homologs.moop.tsv` (copy-paste from another parser) — should say
   `.domains.moop.tsv`; add `close OUT`.

### `parse_DEEPTMHMM_to_MOOP_TSV.pl`
1. **Real bug**: every record with `Number of predicted TMRs: N > 0` is
   written with accession `TMhelix`, even when the underlying segments are
   `Beta sheet` (beta-barrel proteins). Confirmed in Anoura: 5 proteins
   (16–20 "TMRs" each) are 100% beta-strand with zero `TMhelix` lines. Fix:
   determine per-record whether the repeated segment type is `TMhelix` or
   `Beta sheet` (count the actual feature lines, don't trust the comment
   text) and emit the matching accession. Total row count is unchanged
   (4,415 in Anoura), just correctly split (~4,410 TMhelix / 5 Beta sheet).
2. Add a `defined` guard on the `Number of predicted TMRs:` regex match
   (currently would warn + emit an undef id on a malformed comment line).
3. **New addition** (small, optional — confirm before dropping): also parse
   `deeptmhmm_results.3line`'s header line, e.g.
   `>ACA1_..._000047.1 | SP+TM`, and emit a second output file
   `DeepTMHMM_Topology.domains.moop.tsv` with one row per **non-GLOB**
   protein, accession = the classification token (`SP`, `TM`, `SP+TM`,
   `BETA`). This is DeepTMHMM's own independent signal-peptide/topology call
   — cheap to extract, and a nice cross-check against SignalP's SP count.
   Distribution in Anoura: GLOB 16,150 (skipped) / SP 1,591 / TM 3,540 /
   SP+TM 870 / BETA 5 → 6,006 rows written.
   Needs the `.3line` file path added as a second input arg to this parser
   (or a separate small script — TBD at implementation time, whichever reads
   more naturally alongside the existing gff3 parsing).

## Shared loader fix (touches all annotation types, but is safe)

### `data_loaders/load_annotations_sqlite.pl`
The header regex for Accession URL is `/^## Annotation Accession URL:\s*(.+?)\s*$/`
— `.+?` requires at least one character, so a *genuinely* blank value doesn't
match at all and `$accession_url //= die(...)` would fire. Today this is
masked by accident: the parser's `print OUT "## Annotation Accession URL: $x\n"`
always leaves a trailing space before the newline when `$x` is empty, and the
regex backtracks into capturing that single space — so the DB currently would
store a literal `" "`, not `NULL`.

Fix: loosen the regex to `.*` so it can capture a true empty string, and
normalize to `undef`/`NULL` when the trimmed value is `''`, matching the same
NULL-vs-placeholder discipline already used for `feature_annotation.score`
(`"-"` → `NULL`, never `0`). Only affects rows with no URL — i.e. only the two
new sources; every one of the existing 43 sources in a real organism DB has a
real URL and is untouched.

## Shell wiring

### `scripts/process_one_geneset.sh`
- Add `make_signalp_moop()` and `make_deeptmhmm_moop()`, same pattern as the
  existing ProtNLM block (~line 288), each gated by `has_data`:
  ```bash
  has_data SignalP.domains.moop.tsv \
    || { echo "Building SignalP moop files"; make_signalp_moop; }
  has_data DeepTMHMM.domains.moop.tsv \
    || { echo "Building DeepTMHMM moop files"; make_deeptmhmm_moop; }
  ```
- Both functions must `return 0` (log + skip) on missing/empty input rather
  than dying — confirmed one gene set currently ships empty analysis dirs:
  `Medicago_truncatula/GCF_003473485.1/MedtrA17_geneset` (no signalp6/ or
  deeptmhmm/ contents at all despite the directories existing). This must not
  fail the whole organism.
- `make_deeptmhmm_moop()` extracts a clean version from
  `deeptmhmm_version.txt` before passing it to the parser — the raw content is
  `### DeepTMHMM 1.0 - Academic Version ###`, and that full string must not
  land in `annotation_source.annotation_source_version` (it's part of a
  uniqueness key and is user-visible in the source picker/MOOPmart). Strip to
  `1.0`, e.g.:
  ```bash
  VERSION=$(sed -n 's/^#*[[:space:]]*DeepTMHMM[[:space:]]*\([0-9.]*\).*/\1/p' \
              "$TDIR/deeptmhmm_version.txt" | head -1)
  VERSION=${VERSION:-1.0}
  ```
- Add to **both** `check_missing_files_gff()` and `check_missing_files_t2g()`:
  ```bash
  [ -f "$ANALYSIS_DIR/signalp6/signalp6_results.tsv" ]    || log_missing "signalp6/signalp6_results.tsv"
  [ -f "$ANALYSIS_DIR/deeptmhmm/deeptmhmm_results.gff3" ] || log_missing "deeptmhmm/deeptmhmm_results.gff3"
  ```
- `--reload` already works with no changes: line ~209's `rm -f ./*.moop.tsv`
  already matches both new output filenames.

### `scripts/setup_new_moopdb_and_load_data.sh`
One new line, after the `*.iprscan.moop.tsv` load (~line 156):
```bash
load_files "*.domains.moop.tsv" "SignalP / DeepTMHMM domains"
```
This one glob catches `SignalP.domains.moop.tsv`, `DeepTMHMM.domains.moop.tsv`,
and (if kept) `DeepTMHMM_Topology.domains.moop.tsv` in one call — all three
share the `Domains` annotation type already used by InterProScan's members, so
**no schema change, no new annotation type, nothing to add to
`metadata/annotation_config.json`.**

## Web app fix

### `lib/parent_functions.php`
Not materialized in this sparse checkout yet
(`core.sparseCheckout` = `config/build_and_load_db` only) — run
`git sparse-checkout add lib` first (additive, doesn't remove anything already
checked out) so the file can be edited in the same repo/commit.

Change the accession-link rendering (~line 469-477) so that when
`trim($row['annotation_accession_url'])` is empty, the accession is emitted as
plain text instead of `<a href="...">`. Today every accession is always
wrapped in a link, so a blank URL currently produces `<a href="SP">SP</a>` —
a broken relative link on whatever page the user is on.

## Verification, before touching real data

1. `perl -c` on both edited parsers; `php -l` on the edited PHP file.
2. Re-run both parsers standalone against the Anoura files and check counts:
   - SignalP: 1,904 rows (unchanged from before).
   - DeepTMHMM domains: 4,415 rows total, split ~4,410 TMhelix / 5 Beta sheet.
   - DeepTMHMM Topology (if kept): 6,006 rows (GLOB skipped).
3. Build a scratch SQLite DB with `create_schema_sqlite.sql`, load a handful of
   real feature rows + all three annotation files through the real
   `load_annotations_sqlite.pl`, and confirm:
   - `annotation_source.annotation_accession_url` is `NULL`, not `" "`.
   - `annotation_source.annotation_source_version` for DeepTMHMM is `1.0`, not
     the banner string.
   - Feature IDs still resolve directly (no `:pep`/`:cds` fallback needed —
     confirmed 100% direct match against Anoura's real `organism.sqlite` in
     the earlier review).
4. Do **not** run `--reload` against the real per-organism data or touch any
   live `organism.sqlite` until the above passes and the user has reviewed the
   diff.

## Explicitly out of scope for this round

- `.3line` FASTA linking (fast-follow, see Decisions above).
- Topology illustration/cartoon (someday, no new data needed).
- Stale probe paths in `scripts/check_status.sh` / `check_annotation_links.sh`
  (pre-existing bug found during review — every gene set currently reports
  MISS_DIAMOND/MISS_EGGNOG/MISS_IPRSCAN because those scripts test paths like
  `diamond_blast/UNIPROT_sprot/tophit.tsv.gz` and `eggnog_mapper/emapper.annotations`
  that don't exist anywhere in the current tree; the real paths are
  `diamond/UNIPROT_sprot/diamond_results.tsv` and
  `eggnog_mapper/eggnog_mapper_results.tsv`). Separate cleanup, unrelated to
  this task — flagging per the standing "say when the code is bad" instruction
  in CLAUDE.md §9b, not bundling the fix in here.

## Repo housekeeping to do alongside this (currently untracked/dirty)

At the time this plan was written, `git status` showed, besides the two new
parsers:
- `SignalP.domains.moop.tsv` — stray test output sitting in `analysis_parsers/`,
  should be deleted before committing (it's a generated file, not source).
- `parse_DEEPTMHMM_to_MOOP_TSV.pl~` and `parse_SIGNALP_to_MOOP_TSV.pl~` and
  `DeepTMHMM.domains.moop.tsv~` — editor backup files, should be deleted or
  `.gitignore`'d.
- `../scripts/.process_one_geneset.sh.swp` — a live editor swap file; leave
  alone if a vim session may still be open on it, otherwise delete.
- `../scripts/old/`, `../copy2moop_20260728.log`, `../status.before.log` —
  unrelated to this task, not touched by this plan.
