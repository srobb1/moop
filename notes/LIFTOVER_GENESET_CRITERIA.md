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

## 7. Implemented (2026-09-17, commit 322b7d7a): classification, prefix, load

Fixed and verified end-to-end for Parastichopus. Chose **B** (load everything, no
`mutation=` filter) rather than the plan above, after a data point sections 1-6 didn't
have: MOOP's own homology pipeline, run blind to `mutation=`, independently found
Diamond/EggNOG hits for ~72-82% of the frameshifted transcripts too (vs. ~86-95% for the
structurally clean 3.3%) — filtering by `mutation=` would have thrown away genes already
supported by real annotation evidence, not just LiftOn's own self-assessment.

What shipped, across four files (`process_one_geneset.sh`, `parse_GFF3_to_MOOP_TSV.pl`,
`strip_id_prefix.pl`, `load_annotations_sqlite.pl`):

- **Classifier**: a GFF matching the refseq id pattern AND (source column is
  LiftOn/Liftoff, case-insensitive, OR no CDS line anywhere carries `ID=`) is
  classified `"lift"` and handled identically to `"other"` — not the `"liftoff"`
  branch sketched above, since `"other"`'s generic emitter already needs neither CDS
  `ID=` nor `protein_id=` (proven by Nematostella NV2 loading fine today with the
  same missing-CDS-ID shape). Duplicated in `parse_GFF3_to_MOOP_TSV.pl::detect_format`
  too — see the open item below, this turned out to need a third copy.
- **`geneset.json`** now says `"Liftover (LiftOn/Liftoff)"`, not `"RefSeq"`.
- **`moop-lift-prefix`** (new metadata.yaml key): every borrowed accession needs a
  short organism-code prefix so it's never mistaken for this organism's own.
  `strip_id_prefix.pl` gained a prepend-only mode (`--add` with no `--strip`) —
  anchored, idempotent, reusing the existing distinct-id-count and 50-char guards.
  Curator writes the code alone (e.g. `parpar1`); a trailing `_` is added
  automatically if missing.
- **`load_annotations_sqlite.pl`**: its prefix-reconciliation candidate list now
  covers prepend-only too, so Diamond/EggNOG/InterProScan/RBBH/OMA — all computed
  against the un-prefixed depositor sequences — still attach to the now-prefixed
  features. Verified: 904,475 EggNOG2GO rows alone, 0 "not found".
- **Hard gate**: a `"lift"`-classified gene set with no `moop-lift-prefix` set now
  stops before any work happens, naming exactly what was detected (source column
  value and/or missing CDS `ID=`) and what to add to `metadata.yaml`.

Verified against the real load (job 5299056): `OK - no parent/hierarchy problems
found`; 27,913 roots (`parent_feature_id IS NULL`) matching the gene count exactly
(was 0); full protein layer loaded (44,298 `cds`/44,298 `protein` rows, not just the
3.3% clean subset); ids correctly composited as `parpar1_rna-XM_071999466.1:pep`
(prefix first, MOOP's own `:pep`/`:cds` suffix after); FTS5 search index populated
(164,629 rows); copied to the moop web server.

### 7a. Open: gene names and descriptions are still empty

Discovered immediately after the above load succeeded — every gene/mRNA has
`feature_name`/`feature_description` empty. **Not a bug in `strip_id_prefix.pl` or
`moop-lift-prefix`** — verified the prefix mechanism is working correctly (see
above). The actual cause is a **third, unfixed copy** of the same
ensembl/refseq/generic detector:

`isoforms.tsv` — what `assign_gene_names.pl` joins against the homology files
(`UniProtKB_Swiss-Prot.homologs.moop.tsv`, `PANTHER.iprscan.moop.tsv`, etc., both
present and populated: 31,956 / 35,192 lines) to build `geneNames.tsv` — is built
from the *original* `$GENESET_DIR/genes.gff` (before any prefixing) by
`analysis_parsers/make_isoforms_from_gff.pl`, which has **its own independent
`detect_format()`**, never touched by this fix. It still matches Parastichopus's
`Dbxref=GeneID:` pattern and takes the "refseq" branch:

```perl
if ($line =~ /\tCDS\t.*\bParent=rna-([^;]+).*\bGeneID:([^;,]+).*\bprotein_id=([^;]+)/) {
    my ($tx_id, $gn_id, $prot_id) = ($1, $2, $3);
    ...
```

This requires **all three** of `Parent=rna-`, `GeneID:`, and `protein_id=` on the
*same* CDS line. Only ~38,340 of 497,556 CDS lines carry `protein_id=` (LiftOn's
usable-protein minority), so this branch only ever emits rows for that subset —
measured: 3,391 isoforms.tsv lines, not 21,055 genes. Worse, even for the genes it
does capture, the ids it extracts (`XM_071954504.1`, `cds-XP_071810605.1`, bare
numeric `139954605`) don't match the `rna-XM_...`/`gene-LOC...` ids used everywhere
else (`protein.aa.fa`, `cds.nt.fa`, `genes.gff`'s own `ID=`/`Parent=`) — this
branch's regex deliberately strips the `rna-` literal and synthesizes `cds-$prot_id`
itself, correct for genuine RefSeq, wrong for this file. The join against the
homology files therefore matches nothing, and `geneNames.tsv` silently ends up as a
bare header — the same failure shape as the original bug, one file further
downstream of where this fix stopped looking.

**The fix**: add the same lift-detection override to
`make_isoforms_from_gff.pl::detect_format` (source-column sniff is enough here; this
detector's "refseq" branch already keys off per-line `protein_id=` presence rather
than a whole-file signal, so the "no CDS has `protein_id=`" style check used
elsewhere doesn't map as directly — the column-2 sniff alone should suffice and is
cheaper). Once rerouted to "generic", that branch already reads `ID=`/`Parent=` off
mRNA/transcript lines, matching what `genes.gff`'s own structure — and therefore
`protein.aa.fa`/`cds.nt.fa` after `rename_generic_fasta.pl` — actually uses.

**There are likely more copies, not yet audited.** A sweep
(`grep -rl "sub detect_format\|ID=gene-.*Dbxref=GeneID"`) found the same signature in
four more files, each needing a check for whether it's live on the "lift" path or
dead/superseded before deciding whether it needs the same override:

- `analysis_parsers/get_names_from_gff.pl` — used on the RENAME=false
  refseq/ensembl path (native-name-keeping); not currently reached by `"lift"`
  (which is RENAME=true), but confirm this rather than assume it.
- `analysis_parsers/parse_RefSeq_GFF_to_MOOP_TSV.pl` — possibly superseded by the
  "Unified" `parse_GFF3_to_MOOP_TSV.pl`; check for remaining callers.
- `analysis_parsers/make_isoforms_refseq_gff.pl` — possibly superseded by the
  "Unified isoforms builder" (`make_isoforms_from_gff.pl`'s own header comment
  claims this); check for remaining callers.
- `analysis_parsers/rbbh/make_isoforms_rbbh_REFSEQGFF.pl` — RBBH-specific variant;
  unclear if reachable for a GFF-path gene set.

Before calling the liftover gene-set fix complete: grep for callers of each of the
four above, confirm live/dead, patch whichever are live and reachable, then rerun
Parastichopus's reload and confirm `geneNames.tsv` has real rows and gene pages show
names/descriptions.
