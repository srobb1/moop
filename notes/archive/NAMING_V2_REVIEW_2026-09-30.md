# Naming v2 review, 2026-09-30: an evolutionary biologist's critique

Written as a critic who works on orthology inference and gene-family evolution, asked one
question: can a biologist trust a name this pipeline gives a gene in a new species, and does
each name claim only what its evidence supports? Figures are from the latest *Congeria
kusceri* run (`naming_review/runs/refactor_pipe1001_tree0929_labels`: 43,768 genes, OMA with
the 10-species template, no PANTHER tree placements in that run).

## Verdict

The design is sound, and better than most genome-annotation naming. It separates orthology
(plain names) from similarity (`-like`) from family and domain membership. It never picks one
member of a paralog family by BLAST score. It prefers no name (53% of Congeria genes) to a
guessed one, and every name carries its evidence. The problems below are about claims a few
rules make that are stronger than their evidence, and about evidence that is counted as
independent when it is not.

## What is right

- **Plain names only from orthology calls** (OMA pairwise or HOG, or the PANTHER tree agreeing
  with similarity). A best BLAST hit is not the closest relative (long-branch effects, unequal
  rates), and the fly benchmark shows it: full-length reciprocal best hits pointed at the true
  ortholog in 78% of cases, at a paralog in 8%, at a gene with no fly ortholog in 14%. So `-like`
  is the honest label.
- **Co-orthologs are named as a family** (1,603 Congeria genes). A gene that predates a human
  duplication (the two vertebrate whole-genome duplications, most often) is equally related to
  every copy; the most similar copy is only the slowest-evolving one.
- **HOG over pairwise** when OMA's HOG makes a pairwise 1:1 a co-ortholog set (HDAC1/HDAC2).
  The HOG is computed on the fixed species tree and is the more complete call.
- **Support and conflict rules** (`omaX`, `omaC`) catch the classic failure of pairwise
  orthology: hidden paralogy after differential loss, and pairs made through a shared domain
  or repeat.
- **Transitive links (tier 4) are labelled as not orthology.** Orthology is not transitive.
- **Other species never name a gene by default.** Their names are often transferred from
  human by BLAST already; using them would launder a similarity call into an orthology name.

## Problems, most serious first

### 1. Evidence is counted as independent when it is not

`sim+`, `pthr+`, `tree+` and the OMA call all come from the same signal: sequence similarity.
PANTHER family membership is an HMM score; a TreeGrafter placement is an HMM score on a tree
built from the same kind of alignments; OMA's pairwise call uses alignment distances. When a
paralog is more similar than the ortholog (hidden paralogy, a fast-evolving ortholog), all
four can agree and all be wrong together. So "OMA + best hit + same family + tree" is not four
independent witnesses. The planned consensus rule (count methods that agree) must not present
the count as independent confirmation.

- **Do:** keep the tags as they are (they say what agrees), but do not add a score that adds
  them up. In the Methods, say that these checks share the similarity signal and catch
  disagreement better than they confirm agreement.
- **The truly independent evidence is gene order (synteny).** For a gene set with a close,
  well-assembled relative (Dreissena for Congeria, Anolis for Chamaeleo), conserved
  neighbourhood is the standard way to tell orthologs from paralogs. It stays future work
  (`jcvi` MCScan), but it is the only thing that would genuinely raise confidence.

### 2. Many:1 copies all carry the plain human symbol

1,088 Congeria genes are named `SYMBOL: name` with an `Nto1` tag, where N (the copies OMA
pairs with the one human gene) runs from 2 to 18. The orthology claim is correct: lineage-specific
duplicates (in-paralogs) are all co-orthologs of the one human gene. But a biologist reading
`ALPHA: alpha synthase` on 18 genes will assume 18 functional equivalents of ALPHA, and after
duplication copies often split or change function. Large copy numbers are also where OMA
artefacts concentrate (repeats, transposons: the `>= 5 copies + TE domain` rule already catches
one kind).

- **Do (user's call):** show the copy count in the description, as Ensembl does for
  one-to-many orthologs (`ALPHA: alpha synthase (1 of 18)`), keeping the symbol for search. Today it is only in the
  tag (`18to1`).
- **Check:** list the many:1 groups with more than ~5 copies and no TE domain, and look at
  whether they are real expansions or repeat-driven pairs.

### 3. Non-reciprocal `-like` names have no benchmark

520 Congeria names are `ISS|bh`: a full-length best hit that is **not** reciprocal. The fly
benchmark measured reciprocal full-length hits (78% ortholog). A non-reciprocal best hit means
the human gene has a better match elsewhere in this genome: a paralog here, or a
lineage-specific copy. That is weaker evidence, and it is untested.

- **Do:** rerun the fly benchmark split into `rbh` and `bh`. If `bh` is clearly worse, name
  those genes by their shared HGNC group or PANTHER family instead (as for a paralog tie), or
  by their domain.

### 4. Fast-evolving genomes (the flatworms)

Similarity-based rules are biased toward the slowest-evolving member of a family. Flatworms
evolve fast, so expect fewer OMA calls that pass support, more `sim~`, and more genes left
unnamed. This is correct behaviour; do not relax the filters for them. The closest-species
*Schmidtea* column is provenance only (its FASTA carries ids, not names).

- **Do:** after the flatworm runs, compare the share of genes named by OMA with Congeria. A
  large drop is expected; a large rise in `-like` or `bh` names would be a warning sign (see 3).

### 5. "None" mixes two different cases

23,187 Congeria genes have no name. Some have no detectable homolog anywhere (candidate
lineage-specific or orphan genes, or non-coding models). Others have homologs, even human
ones, that did not pass the rules. To a biologist these are very different. The decision table
tells them apart; the database does not.

- **Do (small):** in the Gene Name Source table, give unnamed genes a reason class: `no homolog
  found` versus `homologs, not nameable (partial / tie / family unresolved)`.

### 6. Domain names for multidomain proteins

9,959 names are `X domain-containing protein`, from the gene's best domain by E-value. For a
large multidomain protein, the best-scoring domain can be a small, common one. The name is not
wrong (it states a part), but it can mislead about the whole protein. The planned fix needs the
JSON: require the domain match to cover enough of its own model (`hmmStart`/`hmmEnd`/
`hmmLength`), which removes fragments of domains.

### 7. The PANTHER tree step (step 5)

The rule is careful: a tree name needs a trusted placement (E <= 1e-10, >= 50% of protein and
model), exactly one human ortholog at a speciation node, and agreement with the closest human
gene by similarity. Three caveats:

- PANTHER's reference trees sample invertebrates sparsely, so a mollusc or flatworm protein is
  often grafted inside a vertebrate clade. The fix (move the graft up to the species' own
  lineage) can only add human genes, which is the safe direction.
- The speciation and duplication labels on PANTHER's nodes are themselves inferred by
  reconciliation, so the tree is a model, not ground truth.
- Agreement with similarity is not independent (see 1). On the Congeria sample, tree and OMA
  agreed 215 of 221 times; the 6 disagreements looked like misplacements. That supports using
  the tree as a vote, never to overrule OMA, which is what the code does.

## PANTHER tree integration: status

| Part | Status |
|---|---|
| `scripts/panther_placements.py` (graft point to human genes, lineage fix) | done; tested on a 3,235-protein Congeria sample |
| PANTHER 19.0 TreeGrafter data (`update_reference_data.sh panther_trees`) | downloaded |
| Pipeline wiring (`process_one_geneset.sh`: JSON + trees + ncbi-taxon-id -> placements -> naming) | done |
| Naming: step 5, `tree+` / `treeC` votes, decision-table column | done; synthetic tests (G31-G35) |
| A full gene set's InterProScan JSON | **none exists yet**: no end-to-end run on real data |
| JSON model coordinates for PANTHER model coverage (steps 3 and 7; the TSV underestimates short members) | **not done** |
| JSON model coverage for InterProScan domains (step 8, problem 6 above) | **not done** |
| PANTHER subfamily (`PTHR...:SF...`) names from the JSON | **not done** |
| Benchmark of tree names against Ensembl Compara (fly) | **not done** |

So the tree is integrated, but untested on real data, and the JSON's other uses are still
to do.

## Test runs needed (the user submits; agent sessions cannot)

InterProScan with TSV and JSON (the stand-in `scripts/run_interproscan_geneset.sh` now runs
5.78-109.0 with `-f TSV,JSON`, as the annotation pipeline does):

1. **Drosophila melanogaster** FB_Rel_6.54: the benchmark with a truth set (Ensembl Compara).
   Measures step 5 and problem 3 (`rbh` vs `bh`).
2. **Congeria kusceri** COKUS1KC: OMA complete, the review baseline; the whole-gene-set version
   of the 3,235-protein sample.
3. One flatworm (for example *Schmidtea mediterranea* smed_20140614 itself): the fast-evolving
   case (problem 4).

Then, once the JSON exists: JSON model coverage for steps 3, 7 and 8, and the fly benchmark
of tree names.
