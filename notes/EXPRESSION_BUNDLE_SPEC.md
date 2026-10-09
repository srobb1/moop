# Expression bundle — the contract between the compute box and MOOP

**Status:** agreed 2026-10-09 (user). This is the format MOOP reads. Anything that produces it —
today's htseq-count output, a future read-count system, salmon — is the compute box's business.
Background and history: `EXPRESSION_COUNT_TABLES_PLAN.md`.

## The model in one paragraph

Expression data is **built on the compute box and copied to the web server**, the same way the
organism databases are. Each experiment is a folder of plain, greppable text files. A reload is a
clean replace (`rsync --delete`), never an in-place update. **The web server only ever reads these
files** — there is no load step, no database to rebuild, nothing to keep in sync. A read-only checker
on the web server confirms a copy is sound.

## Three levels — do not confuse them

```
experiment    "Embryonic development time course (Dunn Lab)"    one folder; experiment.json "label"
 └ group        "gastrula (24 hpf)"                               one bar on the gene page (a condition)
    └ sample      PRJNA189768.78 (replicate 1)                    one column in counts.tsv / tpm.tsv
                  PRJNA189768.84 (replicate 2)
```

`group` is a condition WITHIN the experiment, not the experiment. Replicates of a group are averaged
for display.

## Layout

```
{organism}/{assembly}/{gene_set}/expression/{experiment}/
    experiment.json        required
    samples.tsv            required
    counts.tsv             required (raw)
    tpm.tsv                required (what the pages read)
    tpm_transcripts.tsv    optional — only from a transcript-level quantifier (salmon etc.)
    qc.tsv                 recommended
    provenance.json        recommended
```

- Names are exactly MOOP's: `Nematostella_vectensis/GCA_033964005.1/NV2/expression/…`.
- `{experiment}` is a short, stable slug: letters, digits, `_ . -` only
  (`Dunn_development_timecourse`). It is an identifier — once published, never rename it.
- On the web server this lives at `organisms/{organism}/{assembly}/{gene_set}/expression/`.
  Copy a whole gene set's `expression/` at once: `rsync -a --delete …/NV2/expression/ host:…/NV2/expression/`.

## General file rules

- Tab-separated, UTF-8, Unix line endings, exactly one header row, no quoting.
- Missing numeric value: `NA`. Missing descriptive value: empty cell.
- Ids are the gene set's exact feature uniquenames **including the version**: `NV2g010624000.1`.

## `counts.tsv` — raw read counts (required)

```
gene_id	PRJNA189768.75	PRJNA189768.81	…
NV2g000001000.1	0	0	…
NV2g010624000.1	3	3	…
```

- One row per gene, one column per sample. Whole numbers only.
- Column headers after `gene_id` are the `sample_id`s, and must match `samples.tsv` exactly.
- **Sorted by `gene_id` in byte order** (`LC_ALL=C sort`). One row per gene, no duplicates.
- Genes only. Do NOT include htseq's `__no_feature`, `__ambiguous`, … lines — they go to `qc.tsv`.

## `tpm.tsv` — what the pages show (required)

Same shape, same samples in the same column order, same sorted gene rows as `counts.tsv`, values TPM.

**How TPM is computed (gene level, from counts):**

```
length(gene) = total bases in the UNION of all its isoforms' exons
               (overlapping exons counted once — the same model htseq-count -m union counts over)
rate         = count / length
TPM          = rate / (sum of rate over all genes in that sample) × 1,000,000
```

- Only genes of this gene set enter the sum — no `__` counters, no spike-ins.
- Round to 3 decimals. A gene with no exon length cannot have a TPM: write `NA` and report it.
- **If the quantifier gives TPM itself (salmon, kallisto, RSEM), use its values** — they handle
  length better. Gene TPM = sum of its transcripts' TPM.

## Genes with isoforms

- **Gene-level counters** (htseq-count, featureCounts) count a read once for the gene even when it
  lands on an exon shared by several isoforms. No isoform numbers exist; that is fine — the gene page
  is gene-level.
- **Transcript-level quantifiers** (salmon, kallisto, RSEM) apportion shared reads statistically.
  Then ALSO write `tpm_transcripts.tsv` (header `transcript_id`, same samples, transcript
  uniquenames like `NV2t010624001.1`, sorted), and build `tpm.tsv` by summing to genes.

## `samples.tsv` — which sample is which (required)

| Column | Required | Meaning |
|---|---|---|
| `sample_id` | **yes** | Exactly a column header of `counts.tsv`. Unique in the experiment; never reused. |
| `group` | **yes** | The label under the bar — a condition within the experiment, written for a human: `gastrula (24 hpf)`. Replicates share the exact same text. |
| `replicate` | recommended | `1`, `2`, `3`… within the group. |
| `stage` `tissue` `condition` `genotype` `sex` `time` | optional | Descriptive. Use these names; empty if not applicable. |
| `run_accession` | optional | `SRR765897` |
| `biosample` | optional | `SAMN…` |
| `track_keys` | optional | JBrowse track file name(s), `;`-separated: `PRJNA200689.452.pos.bw;PRJNA200689.452.neg.bw` |
| anything else | optional | Kept, shown nowhere yet, never interpreted. |

Rules:
1. **Row order is display order.** Bars appear in the order groups first appear — zygote before
   gastrula, control before treatments.
2. Every sample in `counts.tsv` is listed exactly once, and nothing else.

```
sample_id	group	replicate	stage	run_accession	track_keys
PRJNA189768.75	zygote (2 hpf)	1	embryo, 2hpf zygote	SRR765897	PRJNA189768.75.bw
PRJNA189768.81	zygote (2 hpf)	2	embryo, 2hpf zygote	SRR765904	PRJNA189768.81.bw
PRJNA189768.76	early blastula (7 hpf)	1	embryo, 7hpf early blastula	SRR765898	PRJNA189768.76.bw
```

## `experiment.json` (required)

```json
{
  "label": "Embryonic development time course (Dunn Lab)",
  "summary": "Characterization of differential transcript abundance through time during Nematostella vectensis development",
  "lab": "Dunn Lab",
  "citation": "PMID:23601508",
  "project_accession": "PRJNA189768",
  "assay": "bulk_rna",
  "access_level": "PUBLIC",
  "detect_threshold": 1,
  "contact": ""
}
```

- `label` (required) — the experiment's name on the page.
- `access_level` (required) — `PUBLIC`, `COLLABORATOR`, `IP_IN_RANGE` or `ADMIN`. Only ever narrows
  who sees it; the reader must already have access to the gene set. A misspelling fails closed (ADMIN only).
- `assay` — `bulk_rna`, or `scrna_pseudobulk` (cell types as groups).
- `detect_threshold` — TPM a condition must reach for "detected". Default 1.
- `citation` — `PMID:nnnn` becomes a PubMed link.

## `qc.tsv` (recommended)

One row per sample: `sample_id`, `assigned_to_genes`, then one column per counter the quantifier
reported (`__no_feature`, `__ambiguous`, `__alignment_not_unique`, …). It is how a wrong strand
setting gets caught: 2026-10-09, every "stranded" Nvec library had 3–7% assigned vs ~40% for the rest.

## `provenance.json` (recommended)

How it was made — free-form, but at least:
`genome`, `annotation_gff`, `annotation_gff_md5`, `aligner` + `aligner_version`,
`quantifier` + `quantifier_version` + `quantifier_params` (e.g. `htseq-count -s reverse -m union -t exon`),
`strandedness`, `run_by`, `run_date`.

## Large files — tabix, later, only if needed

Today a `tpm.tsv` is ~1 MB and a gene's row is found by reading it (≈1 ms). If one ever gets large
(transcript-level, hundreds of samples), the compute box may ship it bgzipped and indexed instead:

```
#gene_id	pos	S1	S2	…          (gene id as tabix "sequence name", constant pos 1)
bgzip tpm.tsv && tabix -s1 -b2 -e2 -c'#' tpm.tsv.gz
```

MOOP uses `tpm.tsv.gz` + `.tbi` whenever present. `zgrep` still works. Measured 2026-10-09: ~20 ms
per query (mostly process start), flat regardless of file size — slower than plain reading at today's
sizes, faster once files are big.

## Changing things on the web server — overrides, never edits

The bundle is a pure copy of what the compute box made: a web-side edit to `experiment.json` would be
silently undone by the next `rsync --delete` — and for `access_level` that means data quietly going
public again. So web-side decisions live in MOOP's own metadata (site data, backed up by the snapshot,
like `organism_assembly_groups.json`):

```json
// metadata/expression_overrides.json
{
  "Nematostella_vectensis/GCA_033964005.1/NV2/Lotan_heavy_metals": { "access_level": "COLLABORATOR" }
}
```

- Key = `{organism}/{assembly}/{gene_set}/{experiment}`. Values replace the bundle's `experiment.json`
  fields of the same name (`access_level`, `label`, `detect_threshold`, …).
- The bundle's value is the default; the override wins. The bundle is never modified on the web server.
- Future (user, 2026-10-09): an admin helper page edits this file, like the other admin JSON editors.
- An override whose experiment no longer exists is reported by `check_expression.php`, not silently kept.

## What the web server does

- **Reads** the files on each gene page (and in the tool). Writes nothing.
- **Skips and logs** any experiment it cannot read — a half-copied folder never breaks a gene page.
- `php scripts/check_expression.php Nematostella_vectensis` — read-only check after a copy: id match
  rate against the gene set, samples ↔ columns, sorting, numbers, required fields. Changes nothing.
