# Gene naming v2: closest human gene + tiered names

Status (2026-09-24): built on branch `naming-v2`; tested on Chamaeleo calyptratus (legacy OMA
run, DIAMOND without coverage columns). Waiting on: the first OMA run on the 10-species
template (Congeria), the DIAMOND coverage columns, and InterProScan JSON output.

Goal: every gene gets the **best name we can support** and, separately, its **closest human gene**
(HGNC symbol, name and the evidence behind it). Users want the human gene; the site should show it
simply, e.g. a "Closest Human Gene" table at the top of the gene page.

The analysis → moop TSV parsing, loading, and copy-to-MOOP steps stay as they are. Naming in
`process_one_geneset.sh` is replaced. No backwards compatibility with `assign_gene_names.pl` /
`GeneNameInformativeness.pm` is needed (both are no longer called).

---

## 1. Inputs

| Input | Where | Notes |
|---|---|---|
| OMA pairwise orthologs | `$OMA_BASE/<org>/<asm>/<gs>/Output/PairwiseOrthologs/<A>-<B>.txt` | Relation type 1:1 / 1:many / many:1 / many:many. Independent of species tree |
| OMA HOGs | `Output/HierarchicalGroups.orthoxml` | Co-orthologs where target and HUMAN meet at a speciation node. Used only when `parameters.drw` has a fixed `SpeciesTree` |
| OMA reference↔HUMAN pairs | same run, `PairwiseOrthologs/<REF>-HUMAN.txt` | Second link of a chain (MOUSE, DROME, LOTGI, … → HUMAN) |
| MMseqs2 RBH | `$ANNOTATIONS/.../rbh_mmseq/ENS_<species>/rbh_mmseq_results.tsv` | 12 BLAST columns, `pident` is a fraction, no lengths (coverage from FASTA lengths); query IDs carry `:pep`. eross RBH is not used for naming (its tables stay on the gene page) |
| DIAMOND | `$ANNOTATIONS/.../diamond/{UNIPROT_sprot,ENS_*}/diamond_results.tsv` | Columns 1–4 unchanged; new columns 5–17 expected: `pident length mismatch gapopen qstart qend sstart send bitscore qlen slen qcovhsp scovhsp`. Without them, E-value only |
| Reference proteomes | `$REF_DB/ENS_<species>/current/*.pep.all.fa.gz` | Gene ids, symbols, descriptions, lengths of MMseqs2 targets |
| Ensembl Compara | `$REFERENCE_DATA/ensembl_compara/release-<N>/homo_sapiens.orthologs.tsv.gz` | Human orthologs of every species, per release. Ensembl stores each pair once, so the human file covers all species |
| UniProt Swiss-Prot cross-references | `$REFERENCE_DATA/uniprot/sprot_xrefs.tsv.gz` | Per entry: taxon, gene name, HGNC, Ensembl genes/proteins, PANTHER (sub)families |
| HGNC | `$REFERENCE_DATA/hgnc/hgnc_complete_set.txt`, `withdrawn.txt` | HGNC id / ENSG / UniProt → current symbol + name; `gene_group` for family names |
| NCBI taxonomy | `$REFERENCE_DATA/ncbi_taxonomy/names.dmp` | Common names for Swiss-Prot species ("turkey"); also OMA species-tree lineages |
| PANTHER | `PANTHER.iprscan.moop.tsv` (family only); JSON requested (`-f tsv,json`) for subfamily (`model-ac`) | Naming fallback |

`$REFERENCE_DATA` (`dev/smr_dev/moop`) is refreshed by `scripts/update_reference_data.sh`, which
`run_all_v2.sh` runs once before any job: a Compara file for every main-Ensembl release in
`$REF_DB` (Ensembl Genomes releases such as bacteria/plants are skipped), HGNC when newer,
UniProt when a new release is out, NCBI taxonomy when its md5 changed (checked at most monthly).

## 2. Closest human gene (every gene set, native-named or not)

Tiers (Score of the moop table; 1 = strongest):

1. OMA pairwise ortholog to HUMAN
2. OMA HOG co-ortholog with HUMAN
3. MMseqs2 RBH to Ensembl human (filtered)
4. Via another species: OMA ortholog in a reference species → its OMA HUMAN ortholog, or MMseqs2
   RBH → Ensembl Compara human ortholog (same Ensembl release as the hit)
5. DIAMOND best hit to a human protein (Ensembl human or a Swiss-Prot HUMAN entry, filtered)
6. DIAMOND Swiss-Prot hit in another species → its Ensembl gene → Ensembl Compara
7. DIAMOND Swiss-Prot hit in another species → its PANTHER subfamily → the human Swiss-Prot genes in it

Tiers 1–2 list every co-ortholog; later tiers the single best-scoring link. Human ids resolve
through HGNC to the **current** symbol and approved name (OMA's HUMAN is Ensembl 102: headers carry
the HGNC id but no symbol; 367 symbols changed since then). Genes without an HGNC entry keep their
Ensembl gene id. The relationship type (1:1, many:1, …) is always in the evidence.

## 3. Names

**Native RefSeq/Ensembl gene sets:** name and description are kept **exactly as provided**. Only
uninformative names (§5) are replaced, using the rules below.

**All other gene sets (and uninformative native names):**

| Evidence | Relationship | Name |
|---|---|---|
| Curated (per gene set) | | as given, wins over everything |
| Same species, other annotation (per gene set) | OMA 1:1 / many:1, else hits file | that annotation's name, if informative |
| Tier 1–2 (OMA pair / HOG) | 1:1 | `ABC1: <HGNC name>` |
| | many:1 | `ABC1: <HGNC name> (1 of 3)`, numbered by bitscore to the human protein; counts only tier 1–2 orthologs |
| | 1:many, many:many | most specific shared HGNC `gene_group` (fewest members): `ABC1/ABC2: <group> family member`; no symbol when more than 3 human genes |
| Best hit by **bitscore** (MMseqs2 RBH, DIAMOND, extra hits) | human reciprocal hit, human gene not another gene's tier 1–2 ortholog | `ABC1: <HGNC name>` |
| | human, claimed by another gene (probable paralog), or not reciprocal | `ABC1-like: <name>-like` |
| | other species (must be "very strong" if any human hit exists) | `acr-like: acrosin-like (turkey)` |
| PANTHER | family | `<PANTHER family, as PANTHER writes it> family member` |
| nothing informative | | `None` (the gene keeps its id) |

- Plain / numbered names only for true orthologs. `-like` = similarity. `family` = family.
- `-like` is not added to a name that already ends in `-like` (or `-like protein N`); a trailing
  bracketed part stays last: `BCL2-like 12-like (proline rich)`.
- Hit without a usable symbol: the closest human symbol is used (`ABCA1-like`), else no symbol.
- Filters: normal E ≤ 1e-10, both coverages ≥ 50%; "very strong" E ≤ 1e-50, both coverages ≥ 80%,
  identity ≥ 50% or bitscore ≥ 200.
- One decision per gene: all candidates from all isoforms; the gene and all its isoforms get that
  name; MAINID = the isoform that gave it.

## 4. Outputs

- **GFF** (gene and every mRNA): `closestHGNC`, `closestHumanSym`, `closestHumanDesc`,
  `closestHumanEvidence` (several human genes as comma lists in matching order, GFF3-escaped).
  Written by `addClosestHumanToGFF.pl`; native GFFs get a real copy instead of the symlink.
- **`geneNames.tsv`**: `ID MAINID GroupId Desc Note` as before (Note = where the name came from) +
  `closestHGNC closestHumanSym closestHumanDesc closestHumanEvidence`. Native gene sets also keep
  `geneNames.native.tsv` (the source's own names).
- **`closest_human.moop.tsv`**, type `Closest Human Gene`: a row for the gene and every isoform:
  `HGNC:nnnn` (genenames.org link), `SYMBOL: name [evidence]`, score = tier.
- **Ortholog / homolog tables** (one per partner species, so users can pick theirs):
  - OMA groups, pairs and HOGs: `<PARTNER>.<Namespace>.{oma_orthologs,oma_pairs,oma_hog}.moop.tsv`.
    Each partner gene is shown with the id of the database its annotation came from, one file
    per database so the accession links resolve: `Ensembl` (ENS…P; HUMAN MOUSE CALMI LEPOC),
    `FlyBase` (FBpp; DROME), `RefSeq` (XP_; BRAFL NEMVE), `UniProt` (CAPTE LOTGI MONBE), and
    `OMA` for the few genes with none of these. The version line carries the partner's source
    release from the run's `README.exportedAllAll` (e.g. "HUMAN Ensembl 102; GRCh38"). The
    relationship (pairs, HOGs), HOG id or OMA group id is in the description; HUMAN rows show
    the current HGNC symbol.
  - `Ensembl_<Species>.MMseqs.RBBH.moop.tsv`, next to the eross `Ensembl_<Species>.RBBH.moop.tsv`.

Per-gene-set settings in `build_naming_args()` (`process_one_geneset.sh`): `CURATED_NAMES`
(Chamaeleo Apollo names; always win), `SAME_SPECIES_CODE` / `SAME_SPECIES_HITS` (NV2: another
annotation of the same species, Nematostella RefSeq = OMA reference NEMVE; its name is used when
informative -- OMA 1:1/many:1 ortholog first, else the RBBH file -- otherwise the gene falls
through to the human name), and `EXTRA_HITS` (Montipora RBBH to Nematostella RefSeq, labelled
"sea anemone").

## 5. Name cleanup and "uninformative" (`GeneNamingV2.pm`)

Applies to non-native names and to deciding whether a native name is uninformative.
- Strip: Swiss-Prot `OS= OX= GN= PE= SV=`, `[Source:…]`, `LOW QUALITY PROTEIN:`, `isoform X…`,
  `transcript variant …`, `(Fragment)`, `, partial`, `precursor`, `;` sub-name lists.
- Uninformative descriptions: uncharacterized, hypothetical, novel protein, predicted gene,
  unknown function, si:/zgc:, a description that only repeats an id or symbol, …
- Placeholder symbols: `LOC\d+`, `CG\d+`, `CR\d{4,}` (not human `CR1`/`CR2`), `Gm\d+`, RIKEN
  `…Rik`, `si:`/`zgc:`/`wu:`, C. elegans sequence names (`F54D5.1`), yeast ORF names, Ensembl ids
  (11 digits, not human `ENSAP1`) and RefSeq accessions used as symbols. Every rule is checked
  against HGNC approved symbols.
- The hit's own symbol and id are tested, not only its description.

## 6. Problems found in current output (86 gene sets, 1.85 M genes, Sep 2026)

| Problem | Genes |
|---|---:|
| Swiss-Prot `OS=/OX=/GN=` left in `geneNames.tsv` | 674,414 |
| Native names with `isoform X` (kept by decision: native names unchanged) | 160,051 |
| Native `LOW QUALITY PROTEIN:` (kept by decision) | 21,964 |
| Placeholder descriptions still used | 11,172 homology + 27,701 native |
| `(Fragment)` in homology names | 6,520 |
| Ensembl protein id used as symbol (`rbbh/getDesc_ENS_FA.pl` falls back to the id) | 2,042 |
| Within one source, first informative isoform wins, not best-scoring (`assign_gene_names.pl`) | all multi-isoform genes |
| OMA pairwise relationship stored in the numeric Score column (dropped on load); partners with non-Ensembl/RefSeq ids (LOTGI, MONBE, CAPTE) got no table | — |

Current names come from Swiss-Prot 49.7%, Ensembl human RBBH 42.4%, PANTHER 6.2%, RefSeq
Nematostella RBBH 1.8%, OMA ~0% (few OMA runs so far).

## 7. Still to do

- First end-to-end check on a 10-species-template OMA run (HOG tier, fixed tree).
- Use the DIAMOND coverage columns once delivered (the code already reads them).
- PANTHER subfamily for the target's own genes from InterProScan JSON.
