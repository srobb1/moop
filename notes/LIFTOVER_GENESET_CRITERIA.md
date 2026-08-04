# Liftover gene sets — what they are, and when to load one into MOOP

Status: **diagnosis complete, criteria proposed, not yet implemented.** Raised 2026-08-04
when `Parastichopus_parvimensis` failed its reload (job 5014319).

Related: `PIPELINE_RELOAD_PLAN.md`, `ANNOTATION_LOADING_DATA_DEFECTS.md`.

---

## 1. What happened

`Parastichopus_parvimensis` has for months been live with a gene set row, **0 features**,
306,781 annotations and **0** `feature_annotation` links — a page that looks built and
holds nothing. The 2026-08-04 reload failed and, correctly, **refused to publish**:

```
GFF format: refseq
GFF: 0 distinct protein_id(s)
ERROR: no CDS protein_id found in genes.gff -- wrong file, or not a RefSeq GFF?
!! LOAD FAILED: ... has hierarchy defects
!! Refusing to report success -- annotations must not be loaded
!! on top of this, and it must not be copied to the web server.
   - the feature file produced NO features at all -- organism, genome and gene_set rows
     were still created, so this looks like a successful load
```

That integrity message describes the live state exactly. The gate is working: it is the
reason this is now a decision rather than another silent empty publish.

## 2. It is a LiftOn file, not a RefSeq file

RefSeq annotation was lifted onto this genome with **LiftOn**, so the identifiers are
genuinely RefSeq while the file shape is the liftover tool's.

```
ctg.000000F  LiftOn  gene  20322 28640 . + . ID=gene-LOC139985202;Dbxref=GeneID:139985202;…;source=Liftoff
ctg.000000F  LiftOn  mRNA  20322 28640 . + . ID=rna-XM_071999466.1;…;mutation=frameshift,stop_codon_gain;protein_identity=0.165;dna_identity=0.608;status=LiftOn_chaining_algorithm
ctg.000000F  LiftOn  CDS   23592 23606 . + 0 Parent=rna-XM_071999466.1          ← bare
```

**The failure chain**, all correct behaviour given a wrong assumption:

1. `scripts/process_one_geneset.sh:449-456` matches `ID=gene-` **and** `Dbxref=GeneID:`
   → classifies `refseq`. Right about the id namespace, wrong about the file shape.
2. `refseq` ⇒ `ensure_organism_json "gene" "mRNA,transcript,protein"` — expects a protein layer.
3. `refseq` ⇒ runs `rename_RefSeq_cds_fasta.pl`, which **dies at :201** without CDS `protein_id`.
4. LiftOn omits `protein_id` on CDS **precisely when the liftover produced no usable
   protein**. Bare `Parent=` is the tool saying so.
5. `features.tsv` ends up empty → 0 features → integrity gate stops the copy.

**How to recognise a liftover file** (any one is sufficient):
- **column 2 (source) is `LiftOn` / `Liftoff`** — the cleanest signal, and what GFF3's
  source column is for
- `source=Liftoff` on genes, `status=LiftOn_chaining_algorithm` on mRNAs
- `mutation=`, `protein_identity=`, `dna_identity=` attributes, which NCBI never emits
- **no `##gff-version` header** — a real NCBI download has one

## 3. ⭐ protein_identity is NOT a load criterion

The user's call, 2026-08-04, and it corrects the first reading of this:

> *"protein identity is fine, because we generate our own annotations for these proteins"*

MOOP does not depend on the lifted RefSeq name. Every protein goes through our own
homology and domain analyses, so a protein that has diverged from its RefSeq source is
still perfectly useful — it just gets named by us instead of inheriting a name. **Low
`protein_identity` therefore disqualifies nothing.**

What matters is whether the sequence is a **real ORF**, not how closely it matches the
thing it was lifted from.

Corollary: if the file is not really RefSeq, **do not present it as RefSeq**. It should
not claim RefSeq provenance in `geneset.json`, and its features should be named from our
analyses rather than from the lifted `product=` text.

## 4. Proposed criteria for loading a liftover gene set

Load the protein layer for a transcript only when all hold:

1. **Protein-coding.** `gene_biotype=protein_coding`; lncRNA/tRNA/snRNA/rRNA carry no
   protein layer (they are still valid transcript features).
2. **The reading frame survived.** No `frameshift`, `start_lost`, `stop_codon_gain`,
   `stop_missing`, or `no_protein` in the mRNA's `mutation=` attribute. Inframe indels
   (`inframe_insertion`, `inframe_deletion`) are FINE — they preserve the frame.
3. **It translates cleanly.** CDS length divisible by 3, no internal stop codon. Belt and
   braces against `mutation=` being absent or wrong on a file from another tool.
4. **It aligns to something** in our own homology run — i.e. it survives the analyses we
   would name it from anyway.

Explicitly **not** criteria: `protein_identity`, `dna_identity`, or whether the lifted
`product=` name is informative.

`mutation=` makes rules 1–2 cheap: it is present on **every** mRNA, so this is a filter
over the GFF, not a translation pass.

## 5. Measured: Parastichopus does not pass

45,553 mRNAs. Grouped by `mutation=`:

| outcome | mRNAs | share |
|---|---|---|
| **structurally clean** (synonymous, nonsynonymous, inframe indels only) | **1,491** | **3.3%** |
| any `frameshift` | 42,510 | 93.3% |
| `no_protein` | 1,255 | 2.8% |
| `start_lost` / `stop_missing` / `stop_codon_gain` without frameshift | ~300 | 0.6% |

Consistent with the older 06-03 file, where only **6,705 distinct `protein_id`s** appeared
across **44,298 CDS parents** (~15% — LiftOn is more permissive about emitting one than
rule 2 is).

**So a criteria-respecting load yields ~1,491 proteins from 45,553 transcripts.** Whether a
gene set that is 3% usable belongs on the site is a scientific call, not a technical one.

⚠️ **All annotations are generated at the PROTEIN level, for speed** (user, 2026-08-04) —
computed on the protein and then floated up to the transcript at load time, see
`reference_annotations_attach_to_mrna`. **No protein means no annotation.** That kills the
obvious middle option: a transcripts-only load is not "most of the value minus the
proteins", it is browsable gene models with *nothing known about them*.

- **A. Do not load it.** Mark the gene set inactive. Cheapest and honest.
- **B. Transcripts only, no protein layer.** ❌ **Not viable.** Yields gene models and
  JBrowse tracks with zero functional annotation, because there is no protein to compute
  from. Worth stating explicitly so it is not proposed again as the safe compromise.
- **C. All transcripts, plus the 1,491 valid proteins.** The only option that produces any
  annotation. Gene models for all 45,553; the protein layer holds the 3% that survived,
  named by our own analyses. The gene set then contains far fewer proteins than
  transcripts, which the page must say plainly or it reads as missing data.

**C is the recommendation**, with the caveat that 3% is small enough that A is defensible
— and the criteria above are what decides A vs C for the next liftover gene set.

## 6. Implementation sketch (not started)

- Add a `liftoff` branch to the detector in `process_one_geneset.sh`, keyed on column 2 and
  tested **before** the refseq rule, since these files match both.
- Give it `ensure_organism_json "gene" "mRNA,transcript"` (no protein layer) and skip
  `rename_RefSeq_cds_fasta.pl`.
- ⚠️ **Do not relax `rename_RefSeq_cds_fasta.pl:201`.** That guard did its job here — it
  caught a file that was not what the pipeline thought it was. Fix the classification, not
  the check.
- `geneset.json` source should say what it is (liftover from RefSeq), not "RefSeq".
- If option C is ever chosen, the `mutation=` filter is the gate, applied when building
  `features.tsv`.
