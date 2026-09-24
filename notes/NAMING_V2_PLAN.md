# Gene naming v2: closest human gene + tiered names

Status: design agreed 2026-09-24, not built yet.

Goal: every gene gets the **best name we can support** and, separately, its **closest human gene**
(HGNC symbol, name and the evidence behind it). Users want the human gene; the site should show it
simply, e.g. a "Closest Human Gene" table at the top of the gene page.

The analysis → moop TSV parsing, loading, and copy-to-MOOP steps stay as they are. Naming in
`process_one_geneset.sh` is replaced (on a branch). No backwards compatibility with
`assign_gene_names.pl` / `GeneNameInformativeness.pm` is needed.

---

## 1. Inputs

| Input | Where | Notes |
|---|---|---|
| OMA pairwise orthologs | `OMA_v2/<org>/<asm>/<gs>/Output/PairwiseOrthologs/<PREFIX>-<REF>.txt` | Relation type 1:1 / 1:many / many:1 / many:many. Independent of species tree |
| OMA HOGs | `Output/HierarchicalGroups.orthoxml` | Co-orthologs at the target–HUMAN LCA level. Only trusted for runs on the 10-species template with a fixed tree |
| OMA reference↔HUMAN pairs | same run, `PairwiseOrthologs/<REF>-HUMAN.txt` | Second link of a chain (MOUSE, DROME, LOTGI, … → HUMAN) |
| MMseqs2 RBH | `annotations/.../rbh_mmseq/ENS_<species>/rbh_mmseq_results.tsv` | 12 BLAST columns, `pident` is a fraction, no lengths (coverage computed from FASTA lengths); query IDs carry `:pep`. Replaces eross RBH |
| DIAMOND | `annotations/.../diamond/{UNIPROT_sprot,ENS_*}/diamond_results.tsv` | Columns 1–4 unchanged; expected new columns 5–17: `pident length mismatch gapopen qstart qend sstart send bitscore qlen slen qcovhsp scovhsp` |
| Ensembl Compara (release 113) | `moop/ensembl_compara/` (to download) | Each Ensembl species' human orthologs with type, for chain links |
| HGNC | `moop/hgnc/hgnc_complete_set.txt`, `withdrawn.txt` | HGNC ID / ENSG / UniProt → current symbol + name; `gene_group` for family names |
| PANTHER | InterProScan TSV (family only); JSON requested (`-f tsv,json`) for subfamily (`model-ac`) | Last tier |
| NCBI taxonomy | `moop/ncbi_taxonomy/` | Only needed for OMA tree selection |

## 2. Closest human gene (computed for every gene set, native-named or not)

Tiers (score in the moop table; 1 = strongest):

1. OMA pairwise ortholog to HUMAN
2. OMA HOG co-ortholog with HUMAN
3. MMseqs2 RBH to Ensembl human (passing coverage/bitscore filters)
4. Chain: target ↔ other species (OMA pair or MMseqs2 RBH) → that species' human ortholog (OMA reference pairs or Compara)
5. DIAMOND best hit to human (non-reciprocal, filtered)
6. Swiss-Prot hit → its human link
7. PANTHER family/subfamily → human members

Human IDs are resolved through HGNC to the **current** symbol and approved name (OMA's HUMAN is
Ensembl 102: headers carry the HGNC ID but no symbol; 367 symbols changed since then). The
relationship type (1:1, many:1, …) is always kept in the evidence.

## 3. Names

**Native RefSeq/Ensembl gene sets:** name and description are kept **exactly as provided**. They
are only replaced when uninformative (our definition, §5). The replacement uses the rules below.

**All other gene sets (and uninformative native names):**

| Evidence | Relationship | Symbol | Description |
|---|---|---|---|
| Tier 1–2 (OMA pair / HOG) | 1:1 | `ABC1` | HGNC name |
| | many:1 | `ABC1` | HGNC name + ` (1 of 3)`, numbered by bitscore to the human protein; counts only tier 1–2 orthologs |
| | 1:many, many:many | HGNC `gene_group` if shared, else `ABC1/ABC2` | `… family protein` |
| After tiers 1–2: best hit by **bitscore** among MMseqs2 human RBH, DIAMOND Swiss-Prot/other species (passing filters) | human best, RBH, human gene not claimed by a tier 1–2 ortholog | `ABC1` | HGNC name |
| | human best but claimed (probable paralog), or plain best hit | `ABC1-like` | `…-like` |
| | other species best | its symbol + `-like` (species shown) | `…-like` |
| PANTHER | family | `<family> family` | `<family> family protein` |
| nothing informative | | gene ID | gene ID |

- Plain / numbered names only for true orthologs. `-like` = similarity. `family` = family.
- Never double `-like`: if the name already contains `-like`, keep it as is.
- Hit without a usable symbol: use the human symbol through its chain (`ABCA1-like`), otherwise
  no symbol (gene keeps its ID) and description + `-like`.
- "Very strong" non-human hit (initial cutoffs): qcov ≥ 80 and scov ≥ 80, pident ≥ 50 or
  bitscore ≥ 200, E ≤ 1e-50.
- One decision per gene: all candidates from all isoforms, best by tier then bitscore; the gene
  and all its isoforms get that name; MAINID = the isoform that gave it.

## 4. Outputs

- **GFF** (gene and every mRNA): `closestHGNC`, `closestHumanSym`, `closestHumanDesc`,
  `closestHumanEvidence` (multiple human genes as comma lists in matching order, GFF3-escaped).
- **`geneNames.tsv`**: existing 5 columns (`ID MAINID GroupId Desc Note`; Note = where the name
  came from) + the 4 closestHuman columns. Kept clean for quick lookup.
- **moop TSV, type `Closest Human Gene`**: `Gene`, `HGNC:nnnn` (links to genenames.org),
  `SYMBOL: name [evidence]`, score = tier.
- Existing Homolog/Ortholog tables unchanged, plus new parsers below.

## 5. Name cleanup and "uninformative" (new module)

Applies to non-native names and to deciding whether a native name is uninformative.
- Strip: Swiss-Prot `OS= OX= GN= PE= SV=`, `[Source:…]`, `LOW QUALITY PROTEIN:`, `isoform X…`,
  `transcript variant …`, `(Fragment)`, `, partial`, `precursor`.
- Uninformative descriptions: current list (uncharacterized, hypothetical, novel protein,
  predicted gene, unknown function, si:/zgc:, …).
- Placeholder symbols: `LOC\d+`, `CG\d+`, `CR\d+` (but not HGNC `CR1`/`CR2`), `Gm\d+`, `si:`,
  `zgc:`, `wu:`, C. elegans sequence names (`F54D5.1`), Ensembl protein IDs used as symbols.
  Every rule is checked against HGNC approved symbols so real genes are never flagged.
- Test the hit's own symbol and ID, not only its description.

## 6. Problems found in current output (86 gene sets, 1.85 M genes, Sep 2026)

| Problem | Genes |
|---|---:|
| Swiss-Prot `OS=/OX=/GN=` left in `geneNames.tsv` | 674,414 |
| Native names with `isoform X` (kept by decision: native names unchanged) | 160,051 |
| Native `LOW QUALITY PROTEIN:` (kept by decision) | 21,964 |
| Placeholder descriptions still used | 11,172 homology + 27,701 native |
| `(Fragment)` in homology names | 6,520 |
| Ensembl protein ID used as symbol (human hits without symbol) | 2,042 |
| Within one source, first informative isoform wins, not best-scoring (`assign_gene_names.pl`) | all multi-isoform genes |
| `is_placeholder_symbol` defined but unused; `^CR\d+$` would flag human CR1/CR2 | — |

Current names come from Swiss-Prot 49.7%, Ensembl human RBBH 42.4%, PANTHER 6.2%, RefSeq
Nematostella RBBH 1.8%, OMA ~0% (few OMA runs so far).

## 7. Build list

1. Download Ensembl Compara release-113 human orthologs → `moop/ensembl_compara/`.
2. New parsers (analysis → moop TSV):
   - `parse_MMSEQS_RBH_to_MOOP_TSV.pl`: one Homologs table per Ensembl species.
   - `parse_OMA_HOG_to_MOOP_TSV.pl`: rewrite from `HierarchicalGroups.orthoxml` (the existing
     draft reads `HOGFasta/`, root-level families only, and does not compile).
3. Naming module (cleanup, placeholders, `-like`).
4. Naming script: tiers, closest human, outputs of §4.
5. GFF/FASTA writer with the new attributes.
6. `process_one_geneset.sh` (branch): swap the naming section, call the new parsers; keep
   parsing, loading and copying.
7. Later: PANTHER subfamily from InterProScan JSON.

Open: whether eross RBBH tables stay on the gene page as Homolog tables (they are dropped from
naming).
