# Gene naming v2 review — 2026-09-28

Working area: `dev/smr_dev/moop/naming_review/` (outside the repo; no code changed).
- `run_naming.sh <head|tree> <org> <asm> <gs> <out> [diamond_dir|none]` — runs the naming script read-only
- `code_head/` — committed HEAD copy (7a1565a7); `tree` = the repo working tree (uncommitted PANTHER rework)
- `panther/hmm_lengths.tsv` — PANTHER 19.0 model lengths (15,683 families) from the IPS 5.78 binHmm
- `diamond_review.sbatch`, `run_local.sh`, `tasks.tsv` — DIAMOND with the 17 naming columns, top 5 targets,
  E ≤ 1e-5, ultra-sensitive; run locally (4 cores), not on the queue
- `annotations/<org>/<asm>/<gs>/diamond/{ENS_homo_sapiens,UNIPROT_sprot}/` — its results
- `runs/` — naming outputs; `analysis/` — the analysis scripts below

## Status of the OMA side
Congeria new-template OMA complete (part1–3, mapGO). 6,774 CONKUS proteins with a human pairwise ortholog.

## Findings (Congeria, HEAD code)

### F1. DIAMOND is never used (production tables have 4 columns)
Every production DIAMOND table (all 17 dbs) is `qseqid sseqid stitle evalue` — no coverage, no bitscore,
and no E-value cutoff (hits at E = 2.78). The code (correctly) refuses them, so naming step 4 and closest
human tiers 5–7 get nothing from DIAMOND. The launcher `run_diamond_all.sbatch` writes 17 columns, but the
annotation pipeline's own DIAMOND step does not.

HEAD, no DIAMOND: 33,620 of 43,768 genes (77%) have no closest human; 20,656 (47%) no name.
HEAD + 17-column human DIAMOND: +1,465 tier-5 genes, +703 `-like` names; still 32,155 with no closest human.

### F2. The 50%/50% coverage filter drops fragment gene models — the "missing hits" issue
Genes with no closest human but a DIAMOND human hit (6,270), by their best hit:

| | both ≥ 50 | subject < 50 (our protein short) | query < 50 | both < 50 |
|---|---|---|---|---|
| E ≤ 1e-30 | (pass) | **942** | 148 | 297 |
| 1e-30 < E ≤ 1e-10 | (pass) | **1,580** | 206 | 831 |
| 1e-10 < E ≤ 1e-5 | 180 | 974 | 138 | 974 |

Congeria models are fragmentary: BUSCO C 84.1% / F 5.3% / M 10.6%; 27% of proteins < 100 aa; 16% do not
start with M. A fragment that aligns over most of ITS OWN length to one human protein at E ≤ 1e-30 is real
evidence of that gene; the target-coverage requirement throws it away. Same pattern in MMseqs2 RBH rows:
1,652 of 2,574 coverage failures are "target < 50%".

### F3. OMA pairwise "1:1" often picks ONE vertebrate ohnolog
3,098 of 3,143 genes named by a single OMA ortholog agree with their full-length MMseqs2 RBH (HGNC-resolved).
The 45 that disagree are mostly vertebrate 2R-WGD ohnolog pairs, where the mussel gene is co-ortholog of all:
HDAC1/HDAC2, PPP1CC/PPP1CA, BMPR1A/B, DRD5/DRD1, PRKG2/PRKG1, E2F5/E2F4, ARID1B/ARID1A, HOMER1/2, MAGI2/1,
DDX3Y/DDX3X (!), TAF4B/TAF4, SLC4A5/SLC4A10, LARP1B/LARP1. (analysis/rbh_vs_oma.cok_head.txt)

OMA's own HOGs see this: 168 target proteins with pairwise 1:1 have HOG co-orthology to >1 human gene
(HDAC1 → HDAC1+HDAC2; PPP1CC → PPP1CC+PPP1CA; WNT3A → WNT3A+WNT3). The code takes tier 1 (pairwise) and
never looks at tier 2 (HOG) when tier 1 exists, so these get a plain single-gene name.
Pairwise vs HOG, per protein: pw1/HOG1 3,932; pw1/HOG>1 168; pw1/HOG0 897; pw>1/HOG0 491; pw0/HOG≥1 327.
(OMA HOGs miss ~1,400 pairwise relations, so HOGs cannot simply replace pairwise.)

### F4. Implausible plain names from OMA pairwise-only links
FLG2 (filaggrin 2 — mammal-specific epidermal protein) named for a Congeria gene: OMA pairwise 1:1, no HOG
link, full-length RBH to NCOR1 at 25% identity. Also CDX1 (RBH EMX2), DYRK1A (RBH NEK4), CDKL5 (RBH ABL1),
COL23A1 (RBH SS18), GCK (RBH HK1), MNMIP1, CRACD, ATN1. Several are low-complexity / repeat-rich proteins.

### F5. OMA families without an HGNC group skip step 4 by design (374 tier-1 genes → PANTHER/domain/None)
Design choice (don't pick a member by score). Noted, not a bug.

## Questions for the user

1. **Fragments (F2).** For the closest human gene (evidence, not the name), accept a hit when most of OUR
   protein aligns (query cov ≥ 80%?) and the E-value is strong (≤ 1e-30?), whatever the human coverage —
   labelled "partial model"? And for names: allow `-like` from such hits, or keep names full-length only?
2. **Ohnologs (F3).** When OMA pairwise says 1:1 but the HOG gives several human co-orthologs, report the
   family (HDAC1/HDAC2 → "Histone deacetylases, class I family member", closest = family)? i.e. merge tiers
   1 and 2 instead of tier 1 hiding tier 2.
3. **Pairwise-only OMA links (F4).** Require a second line of support for a PLAIN name from OMA — the HOG,
   or agreement with the RBH / best DIAMOND hit — and otherwise demote (to `-like`, or to the family step)?
4. **Production DIAMOND (F1).** Change the annotation pipeline's DIAMOND step to the 17-column output with an
   E-value cutoff, and rerun all gene sets? Until then, add the review DIAMOND dirs to geneset config?
   (you said we can add files to the config: which key — a per-gene-set `diamond_dir` override?)
5. **Top-5 DIAMOND hits.** Keep 5 targets per query in production, so we can tell a clear best human gene
   from a near-tie between paralogs (the `-like` paralog problem, open item 5 of the 09-25 review)?

## DIAMOND runs (local, this node)
Task order 1 2 3 5 7 4 6 8 (human first). Congeria human: done (5 min, 17,354 queries aligned).

## Working-tree PANTHER rework, run on Congeria (+ human DIAMOND) — it runs (rc 0)
HEAD → tree: PANTHER names 12,226 → 2,803 (1,839 InterPro name, 964 PANTHER name); domain/repeat names
3,138 → 10,139; None 20,652 → 23,074.

### F6. Provenance text is false for ~1,900 domain-named genes
Step 6's rule says "…; no homolog or family evidence", but 1,927 of the domain-named genes HAVE a closest
human (tiers 1–5; e.g. a protein-kinase-domain gene with a human RBH). Must say what is true: e.g. "no
ortholog or full-length human homolog" — or name them differently (see Q7).

### F7. Weak domain evidence names genes
Domain names accepted at Pfam E = 0.08, 0.05 (IPR000436 Sushi, PF00248) and from PROSITE *patterns* alone
(PS00028 C2H2) — patterns are the weakest InterPro evidence. InterProScan's own thresholds (Pfam GA) let
these through; the E-value we print makes them look worse than the GA call, or better — unclear.

### F8. PANTHER names still wrong for evolutionary reasons
- "KRAB AND ZINC FINGER DOMAIN-CONTAINING" family member for a mussel gene (94% of model): KRAB is a
  tetrapod innovation; the model is mostly C2H2 repeats, which any ZNF fills. Repeat-dominated families
  cannot be judged by model coverage.
- "GEO02494P1" — another fly clone id pattern (GEO…P1) that got through.
- "MITOGEN-ACTIVATED PROTEIN KINASE KINASE KINASE 7-RELATED" — still a single-gene family name.

### F9. Fragments lose their PANTHER family too
9,494 genes lost the PANTHER name. In 3,949 of them the WHOLE protein is inside the family match (protein
coverage ≥ 80%) while model coverage < 80% — a fragment model or a genuinely short member; 1,288 of those
now have no name at all. Model coverage alone cannot tell "partial gene" from "one domain of a big family".

## More questions
6. **Fragments at the PANTHER step (F9)**: when ≥ 80% of OUR protein is inside the family match but < 80%
   of the model, and the protein looks partial (no start M / no stop / short), keep the family name marked
   "(partial)"? Or treat as domain-level evidence only?
7. **Domain step for genes with a homolog (F6)**: for genes with a tier 3–5 closest human but no full-length
   hit, is "X domain-containing protein" still the right name, or "<closest human>-like (partial)"?
8. **Domain evidence floor (F7)**: exclude PROSITE patterns as the sole evidence? A domain E-value floor
   (e.g. ≤ 1e-5), or trust member-database thresholds?
9. **Repeat-dominated families (F8)**: families whose model is mostly repeats (C2H2 ZNF, LRR, ankyrin, WD40)
   — never name at family level; fall to the domain step?

### F10. `-like` names: paralog ties, and names that are not the best hit (top-5 DIAMOND, HGNC-resolved)
Of 1,029 step-4 names (HEAD + human DIAMOND): next different human gene ≥ 95% of the named gene's
bitscore **416 (40%)**; 80–95% 180; < 80% 109; no other human gene in top 5 324.
Ties: UBE2D2/UBE2D4, ANO1/ANO2, SLC23A1/A2, CABLES1/2, FMO1–5, CYP3A4/3A7/3A43, TNNC2 vs CALM1/2/3.
63 names point at a gene scoring > 5% BELOW another human gene, because the stronger hit failed the 80%
full-length filter and a weaker full-length one passed: CFAP52-like (WDR90 562 vs CFAP52 119 — WD40
repeats), VARS1-like (EEF1G 208 vs 74), IDE-like ×3 (NRDC 226 vs 127), C1GALT1C1-like (C1GALT1 167 vs 73),
DHRS1-like (HSDL2 169 vs 86). (analysis/like_ties.txt)

10. **`-like` ties (F10)**: when the next different human gene is within 5% (or 10%?) of the best, name
    the shared HGNC group ("… family member"-like) or give no symbol, instead of picking one paralog?
11. **Best hit fails coverage (F10)**: when the overall best human hit fails FULL but a weaker hit passes,
    should the name be withheld (the evidence disagrees) rather than taken from the weaker hit?

### F11. Plain OMA names with no similarity support — many evolutionarily impossible
Plain (single-gene) OMA names vs the gene's DIAMOND human hits (top 5, E ≤ 1e-5, HGNC-resolved):

| | best hit | in top 5, not best | not in top 5 | no human hit |
|---|---|---|---|---|
| 1:1 | 3,395 | 105 | 214 | 57 |
| many:1 | 1,030 | 94 | 220 | 72 |

563 of 5,187 (10.9%) plain names have NO similarity support. Examples: DMP1 (dentin; vertebrate-only),
APOL2 (primate-specific), KRTAP5-4 / KRTAP5-7 (mammalian keratin-associated; the mussel protein is a
17%-Cys EGF-like protein), CASP14 (hits CASP3/7), CD40, FLG2, APOH ×25 (Sushi-repeat proteins; hits
SVEP1/CSMD). OMA links low-complexity / repeat / compositionally biased proteins.
Large many:1 sets are transposons: ZMYM1 ×42 (hAT dimerisation + ZMYM1/FAM200 RNase domains), HARBI1 ×31,
ZBED1 ×12 — plain names claim orthology to domesticated human genes. (analysis/oma_vs_diamond.bad.txt)

12. **Similarity support for plain OMA names (F4, F11)**: require the named human gene to be the best, or
    in the top N (5?), DIAMOND/MMseqs human hits — or HOG-supported — else demote (to family, or `-like`,
    or the next step)? Would change ~11% of plain names in Congeria.
13. **Transposon expansions**: many:1 sets above N copies (10?) whose human gene is transposon-derived
    (HGNC names "…transposase derived…", ZBED*, ZMYM*, HARBI1, and TE domains in InterPro) — name as the
    transposon family ("hAT transposase domain-containing protein") instead of the human gene?

## User answers (12:20)
1. Fragments: NO lower-coverage hits. Fix the models (mender) before accepting weaker evidence. No name > wrong name.
2. OMA: asked why OMA would be wrong; wants the information in provenance.
3. Families for HOG co-orthologs: YES — the family, never several genes; always a provenance note; keep code simple.
4. `-like`: YES to both rules (RBH tie-break → shared HGNC group → no -like name; never name from a weaker hit
   when the best human hit fails FULL). Must compare by gene identity, not name text.
5. Next annotation-pipeline run: all 17 DIAMOND columns + InterProScan JSON (PANTHER family/subfamily, hmm coords).

### F11 re-measured at GENE level (deep/: DIAMOND ultra-sensitive, 100 targets, E ≤ 10, the 563 unsupported)
"Top 5 targets" was proteins — isoforms of 1–2 genes can fill it. Rank of the OMA-named gene among distinct
human genes (HGNC-resolved):
- named gene NOT aligned at all (E ≤ 10, 100 targets): 177; no human hit at all: 47 → **224 with no alignment support**
- rank 1 (isoform crowding): 29 (only 2 at E ≤ 1e-10)
- rank 2–3: 71; rank 4–10: 138; rank > 10: 101
Low-complexity (SEG-like, ≥ 20% of the protein): unsupported 11.5% vs supported 4.9%.
Implication for production: max-target-seqs must be > 5 (isoforms) — 25 suggested.

## Decisions (12:40)
- OMA names are never discarded. Their support is measured and stated (tag + provenance): similarity to the
  named human gene (best / aligned-not-best / none) and PANTHER family (same / different), from InterProScan
  vs the human gene's Swiss-Prot PANTHER family. (Plain OMA names, Congeria: supported-by-similarity group
  95% same PANTHER family; no-alignment group 13% same, 62% different.)
- Every name carries a GO-style evidence tag at the end: `[CODE|relationship|support…]`, no colon inside.
- Human DIAMOND db: one protein per gene (Ensembl_canonical from the same release's GTF; RefSeq db: longest
  protein per gene), max-target-seqs 25. User handles the pipeline request.

## Change plan (draft, for approval)
Evidence tag vocabulary
| tag | meaning |
|---|---|
| ISO | orthology (OMA): `1:1`, `m:1` (n copies), `fam` (co-orthologs, HGNC group) |
| ISS | full-length similarity (`-like`): `rbh` or `bh`; `tie-rbh` / `tie-grp` when a paralog tie was resolved |
| ISM | sequence model: `pthr` (PANTHER family), `ipr` (InterPro domain/repeat) |
| TAS | human-curated name |
| SRC | the gene set's own (RefSeq/Ensembl) name |
support flags (ISO): `sim+` best human hit · `sim~` aligned, not best · `sim-` no alignment · `pthr+` / `pthr-`
same / different PANTHER family (omitted when either side has none) · `hog` HOG agrees

1. Tags on every name (above); full sentences stay in Gene Name Source.
2. OMA support checks → tag + provenance; no name dropped.
3. HOG co-orthologs: pairwise 1:1 but the HOG gives > 1 human genes (including the pairwise one) → family
   (HGNC group), closest human = the family; provenance explains. No shared group → same as other families.
4. `-like`: the best human GENE overall (HGNC id, any coverage) must itself have a full-length hit; if another
   gene is within 5% of its bitscore → the RBH among the tied genes → else their shared HGNC group
   ("… family member-like") → else no `-like` name (next step). Compared by gene id and numeric score only.
5. PANTHER step (working-tree rework): finish callers, lengths table in update_reference_data.sh, read the
   IPS JSON when present (exact hmm coords, subfamily), add GEO…P1 clone pattern.
6. Domain step: provenance no longer says "no homolog" when the gene has a closest human.
7. Tests: e2e fixture for every rule and tag; seeds; guard.
8. Docs: Methods, annotation descriptions; reruns Congeria / fly / Miniopterus / Montipora, compare to HEAD.
Still open: Q8 domain evidence floor, Q9 repeat-dominated PANTHER families, Q13 transposon expansions,
tie threshold (5%?).

## Implemented (working tree, uncommitted) — 2026-09-28 afternoon
- PANTHER rework finished: callers (process_one_geneset.sh), `update_panther()` in update_reference_data.sh
  (panther/hmm_lengths.tsv from $INTERPROSCAN_DIR binHmm, md5-checked), INTERPROSCAN_DIR in paths.sh.
- Clone pattern widened (GEO02494P1, FI19922P1-RELATED; checked against HGNC: KIAA…P1 excluded).
- Domain step: no PROSITE patterns; E ≤ 1e-5 for E-value databases; provenance no longer says "no homolog".
- Every name tagged `[CODE|…]` (ISO/ISS/ISM/TAS/SRC; `1to1`, `Nto1`, `fam`, `rbh`/`bh`, `tie-rbh`/`tie-grp`,
  `pthr`, `ipr`, `rpt`, `te`; support `sim+ sim~ sim-`, `pthr=`/`pthrX`, `hog`, `te`).
- OMA support checks (similarity by gene id + PANTHER family) in tag and provenance; OMA names never dropped.
- HOG family when pairwise is 1:1 but the HOG has several human genes (151 Congeria genes).
- `-like`: best human gene must be full-length; ties within 5% → RBH → shared HGNC group → no name.
- Repeat-built PANTHER families (≥ 25% repeat units) named for the repeat (116 Congeria genes).
- Transposable elements: curated Pfam table (13 families, 9 classes) → "<class> transposase domain-containing
  protein [ISM|te]"; replaces OMA many:1 names with ≥ 5 copies; 1:1 OMA orthologs keep the name + `te` flag.
- e2e test: 59 checks (24 genes), all pass; mutation-tested (each rule's break is caught); guard OK.

Congeria (new code, human + Swiss-Prot DIAMOND): OMA 1:1 3,668 · many:1 1,331 · family 1,631 · -like 674
(+204 tie-grp, 4 tie-rbh) · PANTHER 2,669 · repeat 116 · TE 603 · domain 8,492 · None 24,380.
430 OMA names flagged "not supported by homology evidence".

## New questions
14. ZMYM1 ×42 and ZBED1 ×8 keep OMA names: their hAT dimerisation domain (PF05699) is just above E 1e-5, and
    their catalytic domains are Pfam PF14291 "ZMYM1/FAM200, RNase-like" and PF27041 "ZBED1 RNase-like" —
    named after the domesticated human genes; Pfam gives no TE statement (clan CL0219, RNase H-like). Add
    them to the TE table as hAT-related? Or accept the hAT dimerisation domain at a weaker E (≤ 1e-3)?
15. Unsupported OMA families with absurd names stay (as decided), e.g. "Pregnancy specific glycoproteins
    family member [ISO|fam|sim-|pthrX]", "Keratin associated proteins family member" in a mussel. OK?
16. Some HGNC groups are domain groups, not families ("EF-hand domain containing family member",
    "BTB domain containing family member", "Abhydrolase domain containing …"). Acceptable?
17. TE names are scored as naming step 6 (domain) in the Gene Name Source table. OK, or a step of their own?

## Answers 14–17 and follow-up (afternoon)
14. Left out (recommended): PF14291 co-occurs with the hAT dimerisation domain in 46/79 Congeria proteins, PF27041 in
    6/30 (14 with a BED zinc finger); Pfam does not call them transposases. Middle ground if wanted later: count
    them as hAT only with PF05699 or PF02892 in the same protein.
15. DONE: unsupported OMA orthologs (no similarity hit to the named gene(s), E ≤ 1e-5, and no shared PANTHER family)
    are set aside for the name AND the closest human gene; the fallback name's tag carries `omaX`; provenance names
    the pair. Not applied when no human search was run. e2e G25; 62 checks.
16. Kept (HGNC domain groups read as "… family member").
17. TE names stay step 6.
- Methods doc rewritten (notes/GENE_NAMING_METHODS.md, as of 2026-09-28); Gene Name Source / Closest Gene
  descriptions updated in notes/annotation_config_descriptions.json.
- DIAMOND targets: human 50, others 5 (depth/ measurement: 5 → 2nd gene visible 77.7%, 25 → 99.4%, 50 → 100%).

## Test bug found and fixed (both test suites)
`check((EXPR) =~ /re/, 'desc', ...)`: in list context a FAILED match returns an empty list, so 'desc'
became the (true) result -- those checks could never fail. naming_end_to_end.pl had 3 (G11, G13, G25),
the new mender test 2. All wrapped in scalar(); each now fails on deliberately broken code. 62 checks pass.

## Final comparison runs (same inputs: human DIAMOND 50 targets + Swiss-Prot where done) — runs/final_<set>_{head,tree}
| step | Congeria HEAD→new | Montipora HEAD→new | fly HEAD→new* | Miniopterus HEAD→new |
|---|---|---|---|---|
| 2 native | – | – | – | 17,612 → 17,612 |
| 3 OMA | 6,708 → 6,306 | 7,059 → 6,715 | – | – |
| 4 -like | 1,276 → 906 | 1,432 → 986 | 5,765 → 5,250 | 381 → 380 |
| 5 PANTHER | 12,010 → 2,835 | 18,717 → 4,270 | 0 → 3,597 | 102 → 46 |
| 6 domain/TE | 3,124 → 9,273 | 4,725 → 12,438 | 3,906 → 1,766 | 5 → 24 |
| None | 20,650 → 24,448 | 20,828 → 28,352 | 4,315 → 3,373 | 61 → 99 |
*fly HEAD had no PANTHER input (no build dir), so not a fair comparison for PANTHER.
PANTHER → None samples: many bad (KERATIN ULTRA HIGH-SULFUR in a coral; APOLIPOPROTEIN L, CD59, OS10G clone id in a
mussel), some real losses (Hedgehog: Pfam E = 2.8e-4 fails the 1e-5 domain floor).

Fix: the DUF/UPF uninformative pattern was too broad -- it replaced 67 Miniopterus RefSeq names such as
"UPF0565 protein C2orf69 homolog". Now it flags only DUF/UPF-only names (checked on all PANTHER and RefSeq names
with DUF/UPF, and HGNC); 6 new e2e checks (68 total); Miniopterus native names 17,612 = HEAD.

Domain E-value floor experiment (runs/floor3_*): 1e-3 instead of 1e-5 recovers 496 (Congeria) / 978 (Montipora)
genes from None to a domain name; about half E <= 1e-4; mostly specific, plausible domains, many with sim~.
QUESTION for user: keep 1e-5, or 1e-3 (member-database thresholds already accepted these hits)?

## Decision (user): no domain E-value floor
Every InterProScan match already passed its member database's curated threshold (Pfam gathering thresholds etc.);
an E floor removes short domains systematically. Congeria Pfam matches: 26,133 at E <= 1e-5, 4,938 at 1e-5..1e-3,
8,157 above 1e-3 (all past Pfam GA). No floor names 665 more Congeria genes than 1e-3 (Pfam 489, SMART 165,
CDD 11; e.g. HECT, Macro, MAM, cyclin N, RRM, B-box). PROSITE patterns stay excluded. The TE rule also trusts
Pfam's threshold (so ZMYM1 copies' hAT dimerisation domain now counts). e2e G9 updated (Sushi at E 0.08 names it).
TODO when the pipeline keeps the InterProScan JSON: require a domain match to cover enough of its model
(hmmStart/hmmEnd/hmmLength, hmmBounds), e.g. >= 50% or COMPLETE for single domains; exact PANTHER coverage too.

## Later changes (2026-09-28 evening) — read these before the numbers above
- **Naming steps renumbered** (commit e73da279) so the number is the order the steps are tried:
  1 curated · 2 native / naming species · 3 OMA ortholog · **4 transposable element** · 5 full-length `-like` ·
  6 PANTHER family (or repeat) · 7 InterPro domain · none. Earlier sections of these notes use the OLD numbers
  (4 -like, 5 PANTHER, 6 domain, TE recorded as 6) — e.g. "step 4" above means today's step 5.
  Item 17 ("TE names stay step 6") is superseded: TE names are now step 4.
- Methods: terms (naming steps, closest-human tiers) defined in §1; the tag codes spelled out at the start of
  §5; the step-5 rule reworded ("only the best-matching human gene can give the name").
- No domain E-value floor; DUF/UPF pattern narrowed; unsupported OMA orthologs set aside — see "Decision (user)"
  and "Final comparison runs" above.
- InterProScan 5.78 JSON checked (276 Congeria merged proteins): per match location, hmmStart / hmmEnd /
  hmmLength / hmmBounds are reported for Pfam, SMART, PANTHER, NCBIfam, Gene3D, FunFam, PIRSF (PIRSR, SFLD without
  hmmBounds); NOT for CDD, PROSITE profiles/patterns, PRINTS, HAMAP, SUPERFAMILY (hmmLength only). So the JSON gives
  exact model coverage for the PANTHER step (replacing the lengths table) and for most domain databases; CDD and
  PROSITE-profile domains need another rule (keep as now, or require a second database to agree).
