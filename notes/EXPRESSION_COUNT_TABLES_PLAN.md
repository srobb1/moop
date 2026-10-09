# Expression from TPM tables — the simple plan

**Status:** decided 2026-10-09 (user), build started the same day. **Supersedes** the bigWig
route in `EXPRESSION_DATA_LAYER_PLAN.md`, `EXPRESSION_GENE_PAGE_PLAN.md` and
`EXPRESSION_EXPLORER_PLAN.md` as the *first* way expression gets into MOOP. Those notes are kept:
if an experiment exists only as bigWigs, the bigWig precompute can come back later as a second
producer of the same files described below. Nothing in the display layer cares which one made them.

## ⭐ DECISION 2026-10-09 (user) — flat files built on the compute box; NO expression.sqlite

**The contract is `EXPRESSION_BUNDLE_SPEC.md`.** Everything below this section is history and
reasoning; where it disagrees with the spec, the spec wins.

Why (user's words, condensed): DB loads happen on another machine and are copied over, never updated
in place; past pain with reads/writes, loads eating resources, and "is the DB current and correct?";
"I like clean reloads"; "a single person can easily maintain this webserver and a few others … without
having to think hard. But the pages need to load fast." Treat today's code as a test system.

- Bundles are produced on the compute box (Perl/Python there — **no PHP**), incl. `tpm.tsv`;
  copied with `rsync --delete`. The web server only READS. `expression.sqlite` and
  `scripts/build_expression_db.php` are to be retired.
- Measured: scanning a ~1 MB `tpm.tsv` to one gene = 0.8 ms warm. tabix keyed by gene id works
  (gene id as sequence name) but costs ~20 ms/query in process start → a growth path for big files only.
- `samples.tsv` keeps **`group`** (the bar label = a condition within the experiment, NOT the
  experiment). A rename to `condition` was proposed and declined by the user ("group is good").
- The Google Sheet is NOT needed by the compute box; other data there can fill `samples.tsv`.
  `scripts/expression_samples_from_sheet.php` stays an optional MOOP-side helper (keep or drop — open).
- **New read-count system is possible.** Gene-level (STAR + htseq/featureCounts) is all the gene page
  needs; add salmon only if isoform-level numbers are wanted.

**Next, in order:**
1. ✅ DONE 2026-10-09 — reader reads bundles directly; skip+log unreadable experiments.
   `moop_expression_gene_page()` → `moop_expression_bundle_experiments()` + `…_bundle_values()` +
   shared pure `moop_expression_summarize()`. Gene-page HTML verified BYTE-IDENTICAL to the sqlite
   version on 4 genes; page ~0.1 s. Nvec bundles got `tpm.tsv` (one-off, computed on MOOP — the Perl
   bundle-maker will own this) and `developmental_stage`→`stage`.
   ✅ + `metadata/expression_overrides.json` (spec §Overrides). Follows the config_editable.json
     convention the user pointed to: allowlist `MOOP_EXPRESSION_OVERRIDABLE` (label, access_level,
     detect_threshold, summary, citation), empty value keeps the default — but unlike ConfigManager a
     disallowed key is REPORTED (`moop_expression_override_problems()`, for the checker). Corrupt file →
     NOTHING shown (fail closed). Added to the site-data snapshot list + `.json.example`. Live-tested:
     Lotan→ADMIN hid it from IP_IN_RANGE; corrupt file hid the section. Admin editor page: later.
2. `scripts/check_expression.php` — read-only checker (ids, samples, sorting, numbers, fields).
3. Perl bundle-maker for the compute box (`config/build_and_load_db/…`): per-sample htseq files + GFF →
   counts.tsv, tpm.tsv (exon-union lengths), qc.tsv, provenance.json skeleton; samples.tsv supplied.
4. ✅ PARTLY DONE 2026-10-09 — sqlite RETIRED: `scripts/build_expression_db.php`, the writer, the db reader
   (`moop_expression_open/experiments/values/gene_summary`), `moop_expression_load_experiment/rows` and the
   cache file `/var/www/moop-cache/…/NV2/expression.sqlite` are gone; tests rewritten to run on bundles
   (access case/typo, thresholds, null ≠ 0 all still covered). Kept: the parse/match/TPM functions
   (`read_matrix`, `read_samples`, `match_ids`, `feature_lengths`, `counts_to_tpm`, `read_count_dir`) — the
   checker (step 2) and the htseq converter use them, and they are the reference the Perl bundle-maker
   should agree with. Still open: descriptive column names in the 3 Nvec bundles.

## The goal, in the user's words

"Simple. simple. simple. Is this gene expressed — yes/no. No graphics." Expression is something you
can get to when you need it, never the main feature. Graphics only inside the tool, where a heatmap
is genuinely simpler than a table for many genes × many experiments.

## Why TPM tables and not bigWigs

The bigWig plan (970 lines, nothing built) rebuilt per-gene numbers from coverage: a ~100 GB pass
through the tracks server, a tool not installed, a JWT that breaks the download cache, strand pairing
by filename, and gene-body means that include introns. A quantification pipeline (salmon, kallisto,
RSEM, featureCounts) already produces the per-gene number. Ingest that.

**The one real cost:** the table's ids must match the gene set's ids. A table quantified against an
older annotation will not match, so the builder reports the match rate and refuses a poor one.
Nvec's tables are quantified against NV2 (user, 2026-10-09).

**TPM, not raw counts, for yes/no.** Raw reads depend on depth and transcript length, so "50 reads"
means different things in different samples. Counts can be added later as an extra download.

## What the first real data looks like (2026-10-09)

Nvec has **550 samples of htseq-count output** (STAR on Nvec200, `htseq-count` against the NV2g
gene set) on another web server — protected, ssh-only from specific IPs, so MOOP cannot pull them.
The user will zip and upload. One file per sample, `NV2g000009000.1<TAB>725`, plus htseq's trailing
`__no_feature` / `__ambiguous` / … counters. **Raw counts, not TPM** — MOOP converts (below).

Their metadata table has the track-sheet columns (key, technique, category, institute, source,
experiment, developmental-stage, condition, summary, citation, project, accession). Its first
column shows the bigWig name (`MOLNG-1901.1.bw`), so `key` should link a sample to its JBrowse
track. ⚠️ **The metadata needs a cleanup pass before it is shown:** the Bazzini rows are a 2–10 h
wild-type vs α-amanitin time course but say stage `adult` and summary "adult sexed samples,
several tissues" (copy-paste), `experiment` is empty, and one condition is `8h_a_manitin`.

Upload: unpublished data, so outside the web root (`~/expression_import/`), zip kept as the
original. Needed with it: the metadata table as TSV/CSV (the sheet export if it is a sheet), and the
count-file ↔ table-row mapping if file names do not already carry `key`.

**Counts → TPM:** rate = count / exonic length; TPM = rate / Σ rate × 10⁶ per sample. Gene length is
the UNION of its transcripts' exons from the gene set's `exon_coords.tsv` — the same model
`htseq-count -m union` counts over. htseq's `__` counters and unmatched ids stay out of the
denominator. Raw counts are stored alongside (download / DESeq2 users).

**What users see (user, 2026-10-09: "most users don't care about TPM, they like genes"):** TPM is
the yardstick under the hood, never the headline — raw counts cannot carry a yes/no because they
scale with sequencing depth and gene length. Gene page: "Expressed in 4 of 6 experiments", expand →
yes/no + highest condition, no numbers. Tool: words or shaded cells, number on hover; heatmap is
colour only; downloads carry TPM AND raw counts, labelled. *Open:* plain yes/no vs
high/medium/low/off on the gene page.

## ⚠️ The uploaded Nvec counts — QC findings (2026-10-09)

`readcounts.tar.gz` + `readcounts.html` (user upload, left in `organisms/` — 404 over HTTP, control
README also 404). Extracted to `~/expression_import/nvec_2026-10-09/` (outside the docroot);
`metadata.tsv` there is parsed from the HTML.

- **550 files `N.tcs_v2.20211130.versioned.counts.txt`**, each 24,526 genes + 5 htseq counters — the
  exact NV2 gene count, ids `NV2g…​.1`. File N ↔ HTML row N (the link).
- **The HTML header is missing a column.** Row cells are: file, key, NAME, technique, category,
  institute, source, experiment, developmental-stage, condition, summary, citation, project — the
  header omits `name` and calls the last column `accession`. Every column after `key` is mislabelled.
- **Not all RNA-seq:** ChIP-Seq 46, small_RNA-seq 16, Bisulfite-seq 5, BAC 1. Technique spelled 4 ways
  (`RNASeq`, `RNA-Seq`, `RNAseq`, …). 286 rows have a blank `source` (Yanai, by experiment title).

**% of counted reads assigned to genes, and Spearman ρ of gene ranks vs known-good samples**
(Technau RNA-Seq | Lotan; first 12 samples per group):

| Group | n | assigned (median) | % assigned | ρ | Verdict |
|---|---|---|---|---|---|
| Technau RNA-Seq | 7 | 33 M | 41% | 1.00 / 0.92 | ✅ good |
| Lotan | 16 | 5.5 M | 43% | 0.92 / 1.00 | ✅ good |
| Dunn | 12 | 3.8 M | 40% | 0.92 / 0.84 | ✅ good |
| Zilberman RNAseq | 1 | 3.6 M | 40% | 0.88 / 0.95 | ✅ good |
| Smith RNA-Seq (ref + hourly) | 91 | 1.2 M | **5%** | 0.83 / 0.75 | ❓ ranks OK, assignment far too low; some samples ~0 |
| Gibson cyc / eba / she ("poly-A **Stranded**") | 58 | 0.7–2.1 M | **4–7%** | 0.56–0.71 | 🔴 suspected wrong strand |
| Bazzini ("Ribo-dep **Stranded**") | 10 | 1.2 M | **3%** | 0.52 | 🔴 suspected wrong strand |
| Yanai (blank source) | 287 | **~0** | 18% | 0.21 | 🔴 essentially no reads counted (CEL-seq 3′ tags? wrong read aligned?) |
| ChIP-Seq (control, not RNA) | 46 | 6.2 M | 16% | 0.81 | — not expression |
| BAC (control) | 1 | 2.1 M | 16% | 0.10 | — not expression |

**The strand diagnosis:** every library labelled *Stranded* has 3–7% assigned against ~40% for
unstranded ones, and ranks genes WORSE than ChIP input does. That is the signature of htseq-count run
with the wrong `-s` (`yes` on a dUTP/`reverse` library): mostly antisense reads get counted.
**Unverified** — needs the htseq command used, or one BAM re-counted with `-s reverse` (or STAR
`ReadsPerGene.out.tab`, which has unstranded/forward/reverse columns side by side). This is what
`provenance.json`'s `quantifier_params` + `strandedness` and `qc.tsv` exist to catch.

Loadable now with confidence: Technau RNA-Seq, Lotan, Dunn, Zilberman RNAseq (36 samples).

## ✅ First real build — three Nvec example experiments (2026-10-09)

Compute box down, so 3 clean experiments were picked as the worked example. Bundles in
`organisms/Nematostella_vectensis/GCA_033964005.1/NV2/expression/`, first built to an
`expression.sqlite` (14.5 MB; retired and deleted the same day — the page now reads the bundles). Staging symlinks in
`~/expression_import/nvec_2026-10-09/stage/`.

| Bundle | Samples | Groups |
|---|---|---|
| `Dunn_development_timecourse` (PRJNA189768) | 12 | zygote 2 hpf → polyp 10 dpf, 2 reps each |
| `Lotan_heavy_metals` (PRJNA247455) | 16 | control ×4, Cd/Cu/Hg/Zn ×3 |
| `Technau_development` (PRJNA200689) | 5 | gastrula, planula ×2, adult female ×2 — `_ss` libs 453/458 EXCLUDED (23–26% assigned vs 41–43%) |

- 24,526 / 24,526 ids matched exactly in all three.
- **Hand decisions to confirm with the user:** Dunn sheet names say `planula_5hpf` (stage column `5dpf`)
  → used 5 dpf; `polyp_10dpf` vs stage column `1dpf polyp` → used 10 dpf (a 1-dpf polyp is not
  biology); `zygote_2hfp` typo. Sheet values kept per sample as `sheet_name`/`sheet_stage`/
  `sheet_condition`. `access_level` set PUBLIC (published BioProjects). `citation` left blank —
  not invented. provenance: htseq `-s` "unknown — not recorded".
- **Biology check passes:** brachyury (TBXT, NV2g010624000.1) zygote 1.2 → early blastula 4.2 →
  mid-blastula 209 → gastrula 265 TPM, down in polyp; Technau independently: gastrula 450, adult 2.2.
  Hg induces Hsp70-like NV2g012640000.1 12× (68 → 819); Cd does not.

**Metadata now comes from the track Google Sheet** (user, 2026-10-09 — a Nematostella researcher
curated it: sheet `1RmSAkjrtZTw6VhpKJBH0HfG5F4VMtPEzXUSOhJLiPBM`, gid `1331908411`).
`scripts/expression_samples_from_sheet.php --bundle=DIR --sheet-id=… --gid=…` (or `--sheet-tsv=FILE`):
- **The sheet owns descriptive metadata** (developmental_stage, tissue, condition, run_accession,
  biosample, sheet_name, track_keys → samples.tsv; citation, project_accession, lab →
  experiment.json when every sample agrees). **samples.tsv owns grouping + display order**
  (sample_id, group, replicate) — the sheet has no column for that. Re-run whenever the sheet changes.
  This settles open decision 1 below.
- Sample `X` ↔ TRACK_ID `X.bw` / `X.pos.bw` / `X.neg.bw`. Reading **stops at the
  `##### Combo Tracks: Updated …` header** (row 2807; user: ignore everything after it).
- **Repeated rows are by design** (user): each `# name … ### end` block is a JBrowse overlay; a track
  is listed once per overlay it belongs to and is also shown on its own. No row is "the standalone
  one" (only 27 of 1,166 ids sit outside any block). 425 ids repeat; all copies are equal.
- **17 ids have copies that disagree** — and since every copy writes the SAME JBrowse track file,
  JBrowse shows whichever copy was processed last. Mostly an edit made to one copy only:
  `WT_96hpf_*` stage `96hpf` vs `planula, 96hpf`; `SAMN138768xx … str1` biosample filled in one copy;
  `PRJNA200689.430/431` and `.440` NAME (`Input_planula-2` ×7 vs `-1` ×5 — looks like a real mix-up);
  `MOLNG-385.52` NGS_file shows `.53`'s barcode `GTTTCG` in its second copy (copy-paste shift).
  None are in our 3 experiments; the importer refuses such a sample. Full list with sheet rows:
  `~/expression_import/nvec_2026-10-09/sheet/conflicting_track_copies.txt`.
  ✅ **All fixed by the user 2026-10-09** (re-pulled: 0 conflicts, 1,166 ids / 1,663 copies, every
  FILENAME/TRACK_PATH identical to the original download). Along the way one edit pointed
  `MOLNG-385.53`'s TRACK_PATH at `.52`'s file — caught by diffing file pointers against the
  original download. **Do that diff after any bulk sheet edit, before regenerating JBrowse.**
- The researcher's edits confirm the hand calls: Dunn planula **5 dpf**, polyp **10 dpf**. Citations:
  Dunn PMID:23601508, Lotan PMID:25145541, Technau PMID:24642862. Every sample has its SRR.

**Display decision (user, 2026-10-09): yes/no headline + off / low / medium / high per experiment.**

**The yes/no threshold problem.** % of genes whose highest group mean reaches the threshold:

| | ≥1 | ≥5 | ≥10 | ≥50 | ≥100 TPM |
|---|---|---|---|---|---|
| Dunn | 88% | 69% | 59% | 31% | 17% |
| Lotan | 78% | 58% | 45% | 12% | 6% |
| Technau | 77% | 59% | 49% | 21% | 11% |

At 1 TPM almost every gene is "yes", so a bare yes/no carries little information. Leaning:
yes/no stays the headline ("detected"), plus one word per experiment — off <1 · low 1–10 ·
medium 10–100 · high ≥100 TPM (Dunn: 12 / 29 / 42 / 17%). Decision pending (user).

## The expression bundle — how the compute box should produce this (2026-10-09)

Agreed direction: a standard bundle generated on the compute box, so every future experiment
arrives the same way and the MOOP builder reads ONE shape. The 550-file htseq zip is a one-off
import that gets converted into this format.

### Layout — mirrors MOOP's hierarchy

```
expression/
  {Organism}/                       same names MOOP uses (Nematostella_vectensis)
    {Assembly}/                     GCA_033964005.1
      {GeneSet}/                    NV2 — the ids in the tables ARE this gene set's uniquenames
        {experiment}/               short stable slug (Bazzini_amanitin_timecourse) — becomes an id/URL
          experiment.json
          samples.tsv
          counts.tsv
          tpm.tsv            (optional)
          qc.tsv
          provenance.json
          raw/               per-sample tool output exactly as produced; never edited
```

Shipping to MOOP = `rsync` a gene set's `expression/` into
`organisms/{Org}/{Asm}/{GeneSet}/expression/`, then `scripts/build_expression_db.php`.
`raw/` need not be shipped.

### Files

| File | Contents | Why |
|---|---|---|
| **`counts.tsv`** | gene × sample matrix of **raw integer counts**. Header `gene_id` then sample ids. | The canonical artefact. TPM derives from counts, never the reverse; DESeq2 users need counts. |
| `tpm.tsv` | same shape, TPM | Optional. Ship it from salmon/kallisto (better length handling). Absent → MOOP computes from counts + exon lengths. |
| **`samples.tsv`** | one row per sample: `sample_id`, `group`, `replicate`, factor columns (`stage`, `tissue`, `condition`, `genotype`, `sex`, `time`), `run_accession`, `track_key` | Labels written down on purpose. Row order = display order. `track_key` links to the JBrowse bigWig. |
| `experiment.json` | `label`, `summary`, `lab`, `citation`, `project_accession`, `assay` (`bulk_rna` \| `scrna_pseudobulk`), `access_level`, `contact`, optional `detect_threshold` | One record per experiment — not repeated on all 550 rows as the current table does. |
| **`qc.tsv`** | per sample: total reads, uniquely mapped, assigned to genes, `__no_feature`, `__ambiguous`, … | Flags a bad sample, or one counted against the wrong annotation. |
| **`provenance.json`** | genome FASTA + GFF file names and md5, aligner + version, quantifier + version + exact params (e.g. `htseq-count -s reverse -m union -t exon`), strandedness, date, who | The "how was this made" record no track has today (see the tracks-server metadata-contract idea). A GFF md5 matching MOOP's proves the ids match before any match report. |
| `raw/` | per-sample htseq / STAR / salmon output, untouched | Original data stays original. |

### Format rules

- Tab-separated UTF-8, one header row, no quoting, `NA` for missing. `.gz` allowed.
- Ids in `counts.tsv` are the gene set's exact uniquenames, version included (`NV2g000009000.1`).
- `sample_id` unique within the experiment and stable forever once published.
- Factor column names from the fixed list above; values free but consistent (always `2h`, never
  `2h` and `2hpf` mixed — the track sheet has `2hfp`, `tentancle`, `UnkownSex`).

### Other data types

- **Single-cell:** same folder, same files. `assay: scrna_pseudobulk`; `counts.tsv` = counts summed
  per cell type, each cell type (× biological replicate) a sample. Optional
  `pct_cells_expressing.tsv`, same shape. The h5ad/Seurat object stays on the compute box.
- **ATAC / ChIP:** not in this tree — they stay JBrowse tracks. A gene-level summary (peak near
  gene) could be one extra file later.

### Open decisions

1. ✅ SETTLED 2026-10-09: the sheet for descriptive metadata, samples.tsv for grouping (see above).
   *Was:* **Source of truth for sample metadata — `samples.tsv` or the Google Sheet?** Two hand-kept copies
   are how the Bazzini rows came to say "adult". Recommendation: `samples.tsv` is the source; the
   track sheet references `sample_id`.
2. **htseq-count or salmon going forward?** htseq on the STAR alignments matches the existing 550 and
   reuses the BAMs behind the bigWigs; salmon is faster with better TPM but is a second
   quantification path. Leaning htseq, for consistency.
3. A bundling script for the compute box (Perl, matching the pipeline scripts): per-sample htseq →
   `counts.tsv` + `qc.tsv` + `provenance.json`. Offered, not yet asked for.

## MOOP-side input

The builder reads only the bundle above. Anything else goes through a converter first.

### Original first-cut input spec (superseded by the bundle; kept for the reasoning)

```
organisms/{Org}/{Asm}/{GeneSet}/expression/{experiment}/
  tpm.tsv          id  sample1  sample2 ...     gene OR transcript ids; TPM
  samples.tsv      sample  group  [replicate]  [any other columns = factors]
  experiment.json  {"label", "description", "citation", "access_level", "detect_threshold"}
```

- `tpm.tsv` — tab-separated, one header row. The first column is the id; its header is ignored.
  `NA`/empty = no value (kept distinct from 0). R's quoted output is accepted.
- `samples.tsv` — **the row order is the display order.** Every sample in `tpm.tsv` must appear
  exactly once. Optional: without it each sample is its own group (and the builder says so).
  This file is where sample labels get written down on purpose — the 2026-09-14 review found
  they cannot be recovered from track names (Yanai has no stage anywhere).
- `experiment.json` — optional. `access_level` defaults to `PUBLIC`, which means "no restriction
  BEYOND the gene set" — a reader must always have gene-set access first. `detect_threshold`
  defaults to 1 TPM.

Source data stays in `organisms/` (it is original data). The built database is regenerable, so it
goes to `cache_path`: `{cache_path}/{Org}/{Asm}/{GeneSet}/expression.sqlite`.

## Build

`php scripts/build_expression_db.php --organism=X --assembly=Y --gene-set=Z`

- Ids are matched exactly, then with a trailing version (`.1`) stripped on both sides — only where
  that is unambiguous. Gene vs transcript level is decided by which matches more.
- Transcript-level tables are summed to genes (gene TPM = Σ transcript TPM); transcript rows are
  kept too, for the mRNA rows on the gene page.
- Reports per experiment: ids read, matched exact / by version, unmatched (with examples), and
  how many of the gene set's genes have a value. **Refuses below `--min-match` (default 0.5).**
- Writes to a temp file and renames, so a page never reads a half-built database.

Schema (one row per feature × experiment; values a JSON array in `sample` ordinal order):
`experiment`, `sample`, `expr(feature_uniquename, experiment_id, level, gene_uniquename, vals)`.

## Display

1. **Gene page** — one line, no graphics: "Expressed in 3 of 4 experiments (≥ 1 TPM in at least
   one group)". Expands to a small table: experiment · yes/no · highest group (TPM). Link to the tool.
2. **Expression tool** — paste genes, pick experiments → table (default, downloadable TSV);
   heatmap tab, log2(TPM+1), replicates averaged per group, blocks per experiment. Hand-written SVG.
3. **Single-cell** fits the same shape: pseudobulk per cell type, each cell type a "sample".
4. **ATAC / ChIP** stay JBrowse tracks — no clean per-gene yes/no.

**Access:** an experiment is visible iff the reader has gene-set access AND meets the
experiment's `access_level` (case-normalized, unknown fails closed). Filtered server-side.

## Order of work

1. ✅ Builder + reader library + smoke tests on a made-up fixture (TPM matrix path).
1b. ✅ **Bundle format is the ONLY builder input** (2026-10-09). `moop_expression_load_experiment()`
   reads counts.tsv and/or tpm.tsv (+ samples.tsv with `sample_id`, experiment.json, qc.tsv,
   provenance.json, `.gz` allowed for the matrices). tpm.tsv given → used as-is (the user expects the
   pipeline may supply normalized values later — no MOOP change needed then; if it is CPM rather than
   TPM, add a `unit` to experiment.json at that point). counts only → TPM from exon-union lengths.
   experiment.json / samples.tsv extra columns are stored whole (`meta`, `attrs` JSON) so a new
   field needs no schema change. Converter `scripts/expression_import_htseq.php` turns a directory of
   per-sample htseq files into a bundle (never overwrites hand-edited samples.tsv / experiment.json /
   provenance.json; experiment.json defaults to COLLABORATOR). 44 smoke tests, mutation-checked.
   End-to-end dry run on real NV2 ids: 49 MB peak. (Lengths first blew the 128 MB CLI limit — exons
   as PHP arrays; now packed strings, and both CLI scripts set memory_limit 4G.)
1c. ✅ **Gene-page Expression section — SHIPPED to the working tree 2026-10-09** (uncommitted at time of
   writing). `moop_expression_level()` + `moop_expression_gene_summary()` in the lib; one read in
   `tools/parent.php`; card `#pnav-expression` between Feature Hierarchy and Annotations (sidebar entry
   automatic). Header "detected in N of M experiments" (M = experiments WITH this gene; missing ones
   counted separately, never as "no"); per experiment: label + PMID link, level pill (TPM in the hover),
   "highest: <condition>" when detected; one-line key. Hidden when no expression.sqlite or no visible
   experiment. Levels: off = below the experiment's own threshold (so it never contradicts "no"),
   low <10, medium <100, high ≥100 TPM. Verified live (brachyury: high / low / high) + screenshots;
   Montipora gene shows no section; 9 tests, mutation-checked.
   No link to the tool yet — it does not exist, and a dead link is worse than none.
1d. ✅ **Bars + "Show all" (2026-10-09, user).** "highest: X" column REPLACED by a bar sparkline per
   experiment (Planosphere's idea — planosphere.stowers.org gene search, last column — but BARS, never a
   line: a line implies continuity, true of a stage series, false of treatments). One bar per condition
   in samples.tsv order, scaled to the gene's max in that experiment with a **10 TPM floor**; "off" rows
   draw nothing (0.1 vs 0.5 TPM is not a pattern); measured zero = hairline, unmeasured = dashed outline;
   hover = "<condition>: N TPM" (SVG <title>). `moop_expression_sparkline_svg()`, server-side, no JS.
   First 5 experiments shown, rest in a Bootstrap collapse behind "Show all N / Show fewer" — all values
   are already in the one read, so no second request. Measured: 0.7 ms/gene today; simulated
   30 exp × 40 samples = 2.5 ms (cold-from-disk NOT measured — could not drop page cache).
   Needed a fix in `js/modules/collapse-handler.js`: it hijacks every collapse trigger and never
   updated `aria-expanded` (stale for screen readers on blast/parent/sequences pages too).
2. Nvec's 550 htseq files (user zipping + uploading) → convert to bundle → dry-run match report →
   metadata cleanup with the user.
3. Gene-page line + expandable table.
4. The tool (table first, heatmap second).
