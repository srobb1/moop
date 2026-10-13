# Expression — analysis plan for the compute box (reads → bundle)

**Status:** proposed 2026-10-13, for the user to pick up on the compute box. Nothing here is built.
**The output format is NOT defined here** — it is `EXPRESSION_BUNDLE_SPEC.md` (agreed 2026-10-09).
This note is how to get from raw reads to that bundle. Background and history:
`EXPRESSION_COUNT_TABLES_PLAN.md`.

## The goal

For every gene set with RNA-seq: per experiment, one folder MOOP can read as-is —
`{organism}/{assembly}/{gene_set}/expression/{experiment}/` with `counts.tsv`, `tpm.tsv`,
`samples.tsv`, `experiment.json`, `qc.tsv`, `provenance.json`. Built on the compute box, copied with
`rsync -a --delete`, only ever read on the web server.

The two metadata files the user asked for map onto the spec like this:

| User's words (2026-10-13) | Bundle file |
|---|---|
| "how the data was collected" — reference, tools, versions, parameters, strandedness | `provenance.json` |
| "the experimental details" — what the experiment is, lab, citation, who may see it | `experiment.json` |
| per-sample details — condition, stage, tissue, replicate, SRA run | `samples.tsv` |

## Rule zero: quantify against MOOP's OWN annotation

The gene ids in `counts.tsv` must be the gene set's exact uniquenames (`NV2g010624000.1`), so:

- Align to the **same genome FASTA** MOOP serves (`{assembly}/genome.fa`).
- Count against the **same `genes.gff`** MOOP serves for that gene set. Record its md5 in
  `provenance.json` (`annotation_gff_md5`) — matching MOOP's copy proves the ids line up before any
  match report is run.
- When the gene set is reloaded with new gene models, the expression must be re-quantified. The md5
  is how the (planned) `check_expression.php` will tell a stale bundle from a current one.

## Per experiment — the steps

### 0. Sample sheet first (by hand, once)

Write `samples.tsv` before running anything: `sample_id`, `group`, `replicate`, the factor columns,
`run_accession`. Row order = display order. Writing it first means the labels are decided by a
person, not reverse-engineered from file names afterwards (the 550 Nvec files had to be).

### 1. Reads

- SRA: `prefetch` + `fasterq-dump --split-files` per `run_accession`.
- Record read length, paired/single, and the library prep's CLAIMED strandedness — but do not trust
  the claim (step 4).

### 2. Read QC and trimming

- `fastp` (adapters + quality, JSON report per sample). Keep the reports; total and surviving read
  counts go into `qc.tsv`.

### 3. Alignment

- **STAR** against `genome.fa`, index built with the gene set's annotation (`gffread genes.gff -T -o
  genes.gtf` for `--sjdbGTFfile`). Same aligner as the existing Nvec BAMs and the JBrowse bigWigs.
- Keep the sorted BAM: it is also where the coverage tracks come from (step 7).

### 4. Strandedness — measure it, never assume it

The 2026-10-09 Nvec QC found every library labelled *Stranded* at 3–7% of reads assigned to genes
against ~40% for unstranded ones: counted on the wrong strand. That is invisible in the final TPM.

- Run STAR with **`--quantMode GeneCounts`**. `ReadsPerGene.out.tab` has three count columns side by
  side: unstranded, forward (`-s yes`), reverse (`-s reverse`).
- Pick per sample: if one stranded column assigns clearly more than the other (rule of thumb > 3×),
  the library is stranded that way; if they are close, it is unstranded.
- Write the result into `provenance.json` (`strandedness`) AND the assigned fraction into `qc.tsv`.
  If the measured strand disagrees with the claimed one, say so in `provenance.json` — that is a
  finding, not an error.

### 5. Gene counts

**Recommended: take the chosen column of STAR's `ReadsPerGene.out.tab`.** STAR counts the way
`htseq-count -m union` does by default, so this matches the existing 550 htseq files in method,
needs no second tool and no second pass over the BAM, and comes with the strand decision built in.

- Alternative: `htseq-count -s <measured> -m union -t exon` per sample — use it if exact parity with
  the old files matters more than simplicity. (Open decision in `EXPRESSION_COUNT_TABLES_PLAN.md`
  §Open decisions #2; the plan there leaned htseq "for consistency" before STAR GeneCounts was
  considered.)
- STAR's first four rows (`N_unmapped`, `N_multimapping`, `N_noFeature`, `N_ambiguous`) go to
  `qc.tsv`, never into `counts.tsv`.

### 6. TPM

- Gene level, from counts: length = union of the gene's exons (see the spec — the formula is there).
  The exon union is computable from `genes.gff`, or from MOOP's `exon_coords.tsv` for the gene set.
- **Only if isoform numbers are wanted:** also run `salmon quant` (transcriptome from `gffread -w`,
  `--libType A`, which also reports the strandedness it detected — a second opinion on step 4). Then
  ship `tpm_transcripts.tsv` and build `tpm.tsv` by summing to genes, as the spec says. Not needed
  for the gene page, which is gene-level.

### 7. Coverage tracks (optional, for JBrowse)

- `bamCoverage` (deepTools) per sample, split by strand for stranded libraries, normalised the same
  way for every sample (`--normalizeUsing CPM`). The file names go into `samples.tsv` `track_keys`,
  which is how a bar on the gene page links to its track.

### 8. Build the bundle

A **Perl bundle-maker** in `config/build_and_load_db/` (the user maintains these):
inputs = `samples.tsv`, `experiment.json`, the per-sample count files, `genes.gff`;
outputs = the bundle exactly per the spec — sorted `counts.tsv` / `tpm.tsv` (`LC_ALL=C` order),
`qc.tsv`, `provenance.json` filled from the run (tool versions, exact parameters, md5s, date).

- Keep the per-sample raw outputs untouched next to it (`raw/`): original data stays original.
- Conventions: named loop variables, never bare `$_`; all work in `sub main`, `main();` last
  (`CLAUDE.md` §12 — the file-scope trap). Add it to `check_perl_file_scope.pl`'s coverage.

## QC gates — check before copying to MOOP

| Check | Pass | Why |
|---|---|---|
| Assigned to genes | ≳ 30% of read pairs, similar across samples | 3–7% = wrong strand or wrong annotation |
| Ids match the gene set | ~100% of `counts.tsv` rows are genes in MOOP's DB | A mismatch means the wrong GFF was used |
| Replicates agree | Pearson r of log TPM ≳ 0.9 within a group | A sample that does not is mislabelled or failed |
| Groups separate | PCA of log TPM: replicates cluster, groups apart | Catches swapped samples before users do |
| Strand | measured = claimed, or the difference recorded | The Nvec lesson |

Then on the web server, after the copy: `php scripts/check_expression.php {Organism}` (read-only).
**Not written yet** — the bundle spec describes it; it is the next MOOP-side task, and it should exist
before the first real bundle is copied over.

## Order of work

1. One small experiment end to end (a few samples, one gene set already in MOOP) — proves the steps
   and the bundle-maker before anything is batched.
2. Re-quantify the Nvec "Stranded" libraries with the measured strand (fixes the 3–7% finding).
3. Batch the rest with the cluster's job scripts, one experiment per job.
4. Later, not needed for the pages: differential expression (DESeq2 on `counts.tsv`) can be built
   from the same bundles — the raw counts are kept for exactly that.

## Open decisions for the user

1. **STAR GeneCounts vs htseq-count** for gene counts (recommended: STAR GeneCounts — same method,
   one tool, strand built in).
2. **Salmon too?** Only if transcript-level expression is wanted on MOOP; it is not needed for the
   gene page.
3. **Which experiments first** — per organism, which projects are worth showing at launch.
