# Gene naming: every pathway, with examples

A lookup page for `assign_gene_names_v2.pl`: every way a gene can end up with the name it has,
one entry each, with a real gene that took that pathway and how many genes take it in three gene
sets. The flowchart is [NAMING_DECISION_TREE.md](NAMING_DECISION_TREE.md); the full rules and their
reasons are in [GENE_NAMING_METHODS.md](GENE_NAMING_METHODS.md). Counts and examples come from
naming runs of 2026-10-05 (branch `ensembl-id-versions`) and were produced by
`config/build_and_load_db/scripts/naming_pathways.pl` (see the end of this page to regenerate).

| Gene set | Genes | Kind |
|---|---|---|
| Congeria kusceri COKUS1KC | 43,768 | mollusc, pipeline names |
| Phagocata velata pvel.kc1 | 40,829 | flatworm, pipeline names |
| Danio rerio GRCz11 / Ensembl 20260404 | 25,447 | fish, keeps its own (ZFIN) names; counts below are the pipeline's pick |

## How to read a name

`SYMBOL: approved name [TAG|marks]` -- the text in square brackets at the END is the evidence tag
(a name may contain brackets of its own, e.g. "NADH dehydrogenase [ubiquinone] ..."; the tag is
always the last pair).

| Tag | Meaning |
|---|---|
| `ISO` | orthology (OMA, or the PANTHER tree) -- a plain name, no "-like" |
| `ISS` | similarity -- always "-like" |
| `ISM` | sequence model -- a family, repeat or domain name |
| `SRC` | the gene set's own name (RefSeq / Ensembl) |
| `TAS` | named by a curator |
| `1to1`, `2to1`, `Nto1` | OMA pairs it with one human gene; N genes here carry that name |
| `mto1` | OMA many:1, but only this gene here carries the name |
| `fam` | co-ortholog of several human genes: named for their family |
| `rbh` / `bh` | reciprocal best hit / one-way best hit |
| `tie-rbh` / `tie-grp` | human paralogs within 5% of each other: one reciprocal hit decided / named for their family |
| `sp` | a Swiss-Prot protein of another species |
| `pthr`, `rpt`, `ipr`, `te` | PANTHER family, repeat, InterPro domain, transposable element |
| `tree`, `via` | (step 5) placed by the PANTHER tree; closest human found through another species |

The last letter of a mark always means the same thing:

| Letter | Means | Marks |
|---|---|---|
| `+` | agrees | `sim+` the named human gene is the best human hit · `pthr+` same PANTHER family · `tree+` the PANTHER tree places it with the named gene |
| `~` | partly | `sim~` the named gene is a hit, but another human gene scores higher |
| `-` | no evidence (missing) | `sim-` no similarity hit to the named gene at all |
| `C` | **c**onflicts: the evidence points elsewhere | `pthrC` a different PANTHER family · `treeC` the tree places it with other human genes · `omaC` an OMA name withheld (best hit another gene AND a different PANTHER family) |
| `X` | e**x**cluded: set aside | `omaX` an OMA pairing nothing supports (no hit to that human gene, no shared PANTHER family) |
| `R` | **r**ejected: a whole pairing | `omaR` OMA paired ≥ 5 genes here with one human gene and fewer than half pass the checks -- none of them is named after it |

`hog`: OMA's HOG agrees. `omaX`, `omaC`, `omaR` (and `treeC` on a step-6 name) mean the call was
not used: the next step names the gene and the mark stays in its tag, so you can see OMA (or the
best hit) was passed over; the gene page's Cautions statement says why. `treeC` on an OMA name is
only a mark -- the name stays. Counts and examples: [Marks](#marks-a-step-passed-over-on-the-way).

## The order

The first step that gives a name wins:
**1** curator → **2** the gene set's own name → **3** OMA ortholog → **4** transposable element →
**5** PANTHER tree → **6** full-length human hit → **7** Swiss-Prot protein of another species →
**8** PANTHER family → **9** InterPro domain → **none**.

Every step uses a name only if it is informative: no "uncharacterized protein", LOC/CG/Gm ids,
clone names (si:, zgc:, RIKEN), DUF/UPF-only names, or an Ensembl id used as a name
(GENE_NAMING_METHODS.md §4).

Thresholds used below: **full-length** = E ≤ 1e-10 and ≥ 80% of both proteins aligned;
**tie** = another human gene scoring within 5% of the best; **PANTHER family** = ≥ 75% of the
family model (≥ 80% by protein residues when there is no InterProScan JSON); **domain** = ≥ 50% of
its model where the model is known; **trusted tree placement** = PANTHER match E ≤ 1e-10 covering
≥ 50% of the protein and of the family model.

---

## Step 1 · curator

| | |
|---|---|
| **When** | a curated name exists for the gene (e.g. Chamaeleo's Apollo file) |
| **Name** | the curator's name, as is (not checked) · `[TAS]` |
| **Genes** | none in these three gene sets |

## Step 2 · the gene set's own name

**2a native name kept** -- RefSeq / Ensembl gene sets keep their own name when it is informative.

| | |
|---|---|
| **Name** | the source's name · `[SRC]` |
| **Example** | Danio `ENSDARG00000000001.6` → `slc35a5: solute carrier family 35 member A5 [SRC]` |
| **Genes** | Danio 16,734 |

A native name that is uninformative is replaced by the pipeline's name -- including a gene with
no name of its own, which Ensembl gives as its id (`ENSDARG00000079610.5` → `AKAP9-like`;
8,713 Danio genes, 7,453 of them id-only, fixed 2026-10-05). A name whose description only repeats
its symbol (`B9d2: B9d2`, RefSeq's fly product names) is judged by the symbol and kept when the
symbol is real (744 fly genes, fixed the same day). A gene with neither keeps no name. A gene that
keeps its own name also gets a "MOOP name" statement: the name MOOP's own steps give it, and by
which step.

(Step 2 can also be a *naming species* from `geneset_config.yaml` -- currently switched off for
every gene set.)

## Step 3 · OMA human ortholog

OMA's pairwise orthologs (closest human tier 1) or its HOGs (tier 2). Before naming, OMA's set is
checked -- see `omaX`, `omaC`, `omaR` under [Marks](#marks-a-step-passed-over-on-the-way).

| | When | Name · tag | Example | Congeria | Phagocata | Danio |
|---|---|---|---|---:|---:|---:|
| **3a** | OMA 1:1 with one human gene | `SYMBOL: name [ISO\|1to1]` | `COKUS1KC_0000188` → MEI4: meiotic double-stranded break formation protein 4 `[ISO\|1to1\|sim+\|pthr+\|hog]` | 3,525 | 2,340 | 9,311 |
| **3b** | OMA many:1 -- several genes here, one human gene; each copy gets the plain name and "(k of N)" | `SYMBOL: name (1 of N) [ISO\|Nto1]` | `COKUS1KC_0000055` → CUEDC2: CUE domain containing 2 (1 of 2) `[ISO\|2to1\|…]` | 1,167 | 2,001 | 4,249 |
| **3c** | OMA many:1, but the other copies were named by other evidence | `SYMBOL: name [ISO\|mto1]` | Danio `ENSDARG00000028027` → TRIM63 `[ISO\|mto1\|sim+\|pthrC\|hog]` | 11 | 20 | 10 |
| **3d** | as 3a/3b, and the gene carries a transposon domain like its human ortholog (a gene domesticated from a transposon; fewer than 5 copies here) | `… [ISO\|1to1\|…\|te]` | Danio `ENSDARG00000011042` → PGBD5: piggyBac transposable element derived 5 `[…\|te]` | 2 | 13 | 10 |
| **3e** | OMA pairwise 1:1 with one human gene, but OMA's HOG makes it co-ortholog of several (copies duplicated on the human side) | `<HGNC group or PANTHER family> family member [ISO\|fam]` | `COKUS1KC_0000821` → T-box transcription factors family member (pairwise: TBXT) | 40 | 18 | 15 |
| **3f** | co-ortholog of several human genes sharing an HGNC group that is a family by descent (PANTHER coherence ≥ 0.6) | `<HGNC group> family member [ISO\|fam]` | `COKUS1KC_0000232` → Bombesin receptors family member (BRS3/GRPR/NMBR) | 920 | 1,436 | 1,107 |
| **3g** | co-ortholog of several human genes, no such HGNC group, but one PANTHER family it is a whole member of (≥ 50% of the model, or a full-length hit to a member) | `<PANTHER family> family member [ISO\|fam]` | `COKUS1KC_0000050` → Complement component-related sushi domain-containing family member | 683 | 1,078 | 483 |

A co-ortholog family steps 3e–3g cannot name gets the transposon check (step 4), then skips
steps 5–7 (they would each pick one copy) and goes to step 8.

## Step 4 · transposable element

| | |
|---|---|
| **When** | a transposable-element Pfam domain -- before steps 5–7, which would name it after a human gene domesticated from such an element. Also when OMA pairs ≥ 5 genes here with one human gene and they carry the domain (a transposon family, not orthologs) |
| **Name** | `<class> transposase domain-containing protein [ISM\|te]` |
| **Example** | `COKUS1KC_0000028` → PIF/Harbinger transposase domain-containing protein |
| **Genes** | **4a** Congeria 761 · Phagocata 443 · Danio 90 |

## Step 5 · PANTHER tree placement

A trusted TreeGrafter placement with exactly one human ortholog, AND that gene is also the gene's
closest human gene by similarity (one gene, not a family). The tree alone never names a gene.

| | Closest human found by | Name · tag | Example | Congeria | Phagocata | Danio |
|---|---|---|---|---:|---:|---:|
| **5a** | MMseqs2 reciprocal best hit | `SYMBOL: name [ISO\|tree\|rbh]` | `COKUS1KC_0000335` → FAN1: FANCD2 and FANCI associated nuclease 1 | 419 | 277 | 612 |
| **5b** | DIAMOND best hit | `[ISO\|tree\|bh]` | `COKUS1KC_0000430` → ZMYND11: zinc finger MYND-type containing 11 (1 of 3) | 100 | 247 | 374 |
| **5c** | another species' OMA ortholog (or RBH + Ensembl Compara) | `[ISO\|tree\|via]` | `COKUS1KC_0000643` → POPDC1: popeye domain cAMP effector 1 (1 of 2) | 160 | 256 | 535 |

## Step 6 · full-length human hit ("-like")

The human gene the protein is MOST similar to must itself be a full-length hit. Similarity is not
orthology, so the name is always "-like". **Withheld (treeC)** when a trusted PANTHER tree
placement joins the gene to other human genes -- see [Marks](#marks-a-step-passed-over-on-the-way).

| | When | Name · tag | Example | Congeria | Phagocata | Danio |
|---|---|---|---|---:|---:|---:|
| **6a** | full-length, reciprocal best hit | `SYMBOL-like: name-like [ISS\|rbh]` | `COKUS1KC_0000052` → ANO1-like: anoctamin 1-like | 244 | 202 | 319 |
| **6b** | full-length, best hit one way only | `SYMBOL-like: name-like [ISS\|bh]` | `COKUS1KC_0001005` → ENPP5-like | 225 | 398 | 1,128 |
| **6c** | human paralogs tie (within 5%); exactly one of them is a full-length reciprocal best hit | `SYMBOL-like … [ISS\|rbh\|tie-rbh]` | `COKUS1KC_0023174` → PIWIL2-like; Danio → TUBA1A-like (tie with TUBA1B, 1C, 3C, …) | 2 | 7 | 10 |
| **6d** | paralogs tie, no single RBH; they share an HGNC group | `<HGNC group> family member [ISS\|bh\|tie-grp]` | `COKUS1KC_0000081` → Anoctamins family member (ANO1, ANO2, ANO4) | 154 | 326 | 299 |
| **6e** | paralogs tie, no RBH, no HGNC group; they share a PANTHER family | `<PANTHER family> family member [ISS\|bh\|tie-grp]` | `COKUS1KC_0000555` → Vacuolar protein sorting-associated protein 13 family member (VPS13C, VPS13A) | 61 | 128 | 115 |

No step-6 name when: the best human gene is not full-length (a weaker full-length hit to
another gene never names it), the tied paralogs share no family, the human gene's name is not
informative, or the tree contradicts it (treeC).

## Step 7 · Swiss-Prot protein of another species

| | |
|---|---|
| **When** | the best Swiss-Prot hit is a non-human protein, full-length, scoring at least as high as any human hit; no other Swiss-Prot protein within 5% (a paralog tie); and no full-length human hit that step 6 left unnamed |
| **Name** | `<name>-like (<species>) [ISS\|bh\|sp]` -- no symbol; the species shows where it came from (contamination shows this way) |
| **Example** | Danio `ENSDARG00000001463` → L-threonine 3-dehydrogenase-like (Bos taurus) |
| **Genes** | **7a** Congeria 191 · Phagocata 219 · Danio 251 |

## Step 8 · PANTHER family

| | When | Name · tag | Example | Congeria | Phagocata | Danio |
|---|---|---|---|---:|---:|---:|
| **8a** | ≥ 75% of a PANTHER family's model; InterPro has a curated Family entry for it | `<InterPro name> family member [ISM\|pthr]` | `COKUS1KC_0000239` → TRIM45/56/19-like family member | 1,209 | 1,628 | 1,458 |
| **8b** | as 8a; no InterPro entry (or InterPro's name describes a function, not a family) | `<PANTHER name, sentence case> family member [ISM\|pthr]` | `COKUS1KC_0000226` → SET domain-containing protein-related family member | 793 | 806 | 442 |
| **8c** | the family match is ≥ 25% repeat units (any protein with such repeats fills it) | `<repeat>-containing protein [ISM\|rpt]` | `COKUS1KC_0000314` → Zinc finger C2H2-type domain-containing protein | 98 | 52 | 61 |

## Step 9 · InterPro domain

| | When | Name · tag | Example | Congeria | Phagocata | Danio |
|---|---|---|---|---:|---:|---:|
| **9a** | an InterPro Domain covering ≥ 50% of its model (not DUF/UPF/uncharacterised); the lowest E-value one | `<domain> domain-containing protein [ISM\|ipr]` | `COKUS1KC_0000001` → Sushi/SCR/CCP domain-containing protein | 8,667 | 7,737 | 2,750 |
| **9b** | an InterPro Repeat | `<repeat>-containing protein [ISM\|ipr]` | `COKUS1KC_0000193` → Ankyrin repeat-containing protein | 754 | 814 | 101 |

## No name

| | When | Name | Example | Congeria | Phagocata | Danio |
|---|---|---|---|---:|---:|---:|
| **0a** | it has hits, but none passed a step (partial hits, weak or fragmentary domains, ties with no family) | `None` -- provenance: "hits did not pass the naming tests (found: …)" plus its best partial human hit | `COKUS1KC_0000006` | 6,601 | 6,970 | 1,657 |
| **0b** | no hit in any database, no OMA ortholog in any species, no InterProScan homology | `None` -- "no hits"; under 100 aa it adds "a short protein, N aa" | `COKUS1KC_0000005` | 16,981 | 13,413 | 60 |

No name beats a wrong name: a gene is left unnamed rather than named from weak evidence.

---

## Marks: a step passed over on the way

A check that sets a call aside does not stop naming: the next step names the gene, and the name
it gets carries the mark (or, for treeC, its provenance says why).

| Mark | What was set aside | Where the gene goes | Example | Congeria | Phagocata | Danio |
|---|---|---|---|---:|---:|---:|
| `omaX` | an OMA human ortholog nothing supports: no similarity hit to that human gene and no shared PANTHER family | next step (5–9) | Danio `ENSDARG00000000563` → TTN-like `[ISS\|rbh\|omaX]` | 291 | 197 | 381 |
| `omaC` | an OMA name with both checks against it: its best human hit is ANOTHER gene AND its PANTHER family differs (hidden paralogy, or a pair made through a shared domain) | next step | Danio `ENSDARG00000010878` → Cyclin-dependent kinase inhibitor 1 family member `[ISM\|pthr\|omaC]` | 84 | 46 | 143 |
| `omaR` | an OMA many:1 pairing mostly rejected: ≥ 5 genes here paired with one human gene and fewer than half pass the checks -- the rest are not named after it either | next step | `COKUS1KC_0000001` (one of 18 Sushi-domain proteins paired with APOH) → Sushi/SCR/CCP domain-containing protein `[…\|omaR]` | 82 | 0 | 118 |
| `treeC` (step 6) | a step-6 "-like" or tie-family name the PANTHER tree contradicts (a trusted placement with other human genes). On Danio such names matched ZFIN 29% of the time (20 of 68); when BLAST and tree disagreed, each was right about as often (20 vs 19) -- so neither names it | next step (7 is skipped: its full-length human hit was left unnamed) | `COKUS1KC_0001559` → Dynein heavy chain family member `[ISM\|pthr]`, provenance: "…; the full-length best hit does not name it "Dynein heavy chain family member": TreeGrafter places it with human …" | 38 | 36 | 143 |
| `treeC` on a name that stays | the tree contradicts an OMA name (step 3): the name stays (OMA is the stronger call), the tag says so, and Cautions states it | -- | `IOTA: iota kinase [ISO\|1to1\|sim+\|treeC]` (test gene) | | | |

Also set aside before naming (they change which human genes count, not which step names):
human readthroughs and other Ensembl models of one human gene (counted as that gene, or dropped);
OMA chains through another species that nothing supports (closest human tier 4); and a gene that
spans both halves of a human readthrough (a possibly fused model) is not named by OMA.

## Regenerate

```
perl config/build_and_load_db/scripts/naming_pathways.pl \
  Congeria=<…/COKUS1KC/naming_decisions.tsv> Phagocata=<…> Danio=<…> > pathways.tsv
```

Each row: the pathway, then per gene set its gene count and one example (gene | name | provenance).
A gene the script cannot place is listed as `?? unclassified` -- a new pathway, or a rule this page
has not caught up with. For a gene set that keeps its own names (Danio), the pathway is the
pipeline's pick, read from that step's `S<n>` column; its treeC count there includes genes whose
step 6 was skipped for an OMA family (160 vs the 143 actually withheld).
