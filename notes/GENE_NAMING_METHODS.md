# Gene naming and closest-gene assignment — Methods

Draft for publication. Describes `assign_gene_names_v2.pl` as of 2026-09-28 (branch
`naming-v2`). Every threshold below is quoted from the code; if the code changes, change
this file with it. Items marked **[TODO]** are facts this document cannot state from the
code alone.

---

## 1. Overview

Each gene receives two independent outputs:

1. **A gene name** — one description (and symbol where available) chosen from a fixed
   order of evidence (§5), ending in a short **evidence tag** (§5.1). A gene is named once;
   all of its isoforms carry the same name.
2. **A closest gene in human, and optionally in other chosen species** (§6) — reported
   whatever step named the gene, with the evidence and a numeric tier.

The two are deliberately separate: a gene may be named from its RefSeq record or a
curator's file and still report the human gene it is closest to.

**Principle.** A missing name is preferred to a wrong one. Filters are not relaxed to name
more genes; fragmentary gene models are left unnamed until the models are repaired.

**Unit of decision.** All evidence from all isoforms of a gene is pooled and one decision is
made per gene. The isoform whose evidence was used is recorded as the gene's main id
(`MAINID`); when the isoform file nominates a selected isoform, that one is used instead.

**Terms used below.** The two outputs are each decided by an ordered list of evidence;
the tables in §2 refer to positions in these lists.

- **Naming steps** (§5): the kinds of evidence a name can come from, tried in this order;
  the first that gives an informative name wins. 1 a curator's name; 2 the gene set's own
  (RefSeq/Ensembl) name, or a chosen naming species; **3 an OMA ortholog of a human gene**;
  4 a transposable-element domain; 5 full-length similarity to one human gene (`-like`);
  6 a PANTHER family; 7 an InterPro domain; otherwise no name. The step is the Score of the
  Gene Name Source table.
- **Closest-human tiers** (§6): the kinds of evidence for a gene's closest human gene,
  strongest first; the lowest tier with any evidence is used. **Tier 1: an OMA pairwise
  ortholog** (OMA calls the gene and a human gene orthologs directly). **Tier 2: an OMA HOG
  co-ortholog** (OMA's hierarchical orthologous groups, built on the species tree, place the
  gene with a human gene). Tier 3: a reciprocal best hit; tier 4: through another species'
  ortholog; tiers 5–7: best similarity hits (directly, or through another species' gene or
  its PANTHER subfamily). The tier is the Score of the Closest Gene table.

## 2. Evidence sources

| Source | Method | Used for |
|---|---|---|
| OMA pairwise orthologs | OMA standalone 2.7.0, 10-species template (§2.1) | closest human tier 1; naming step 3; closest other species |
| OMA hierarchical orthologous groups (HOGs) | same run; only when `parameters.drw` fixes a species tree | closest human tier 2; co-ortholog families (§5, step 3) |
| OMA orthologs of reference species | same run | closest human tier 4 |
| MMseqs2 reciprocal best hits (RBH) | `mmseqs easy-rbh` (commit 7e28409), defaults, against every protein of the Ensembl proteomes | closest human tiers 3–4; naming step 6; support of OMA names |
| DIAMOND hits | DIAMOND 2.1.6 `blastp --ultra-sensitive`, E ≤ 1e-5, 17-column output with query and subject coverage; against Ensembl human (canonical proteins, `--max-target-seqs 25`: enough genes to see the named ortholog behind its paralogs) and the other databases (`--max-target-seqs 5`) **[TODO: confirm after the pipeline rerun]** | closest human tiers 5–7; naming step 6; support of OMA names |
| Ensembl Compara homologies | same Ensembl release as the proteome hit | closest human tiers 4 and 6 |
| UniProtKB/Swiss-Prot cross-references | Ensembl gene, HGNC id and PANTHER family and subfamily per entry | closest human tiers 6–7; PANTHER family of each human gene (support of OMA names) |
| PANTHER | PANTHER 19.0 family HMMs, via InterProScan 5.78-109.0 (the gene set's own results); family model lengths from the same release's HMM file | naming step 7; support of OMA names |
| InterPro domains, repeats | the gene set's InterProScan results + InterPro `entry.list` (entry types and names) | naming step 8; repeat-built families (step 7) |
| Pfam transposable-element domains | the gene set's InterProScan results (Pfam, as shipped with InterProScan 5.78) | transposable-element names (§5, step 4) |
| PANTHER tree placements | TreeGrafter graft points from the gene set's InterProScan JSON, traced on PANTHER 19.0's TreeGrafter data (family trees with speciation/duplication events; `scripts/panther_placements.py`), with the species' NCBI lineage (`ncbi-taxon-id` in metadata.yaml) | naming step 5; `tree+`/`treeC` support of OMA and `-like` names |
| HGNC | complete set + withdrawn ids | all human symbols, approved names and gene groups |
| NCBI Taxonomy | GenBank common names | species of other-species hits (evidence only; no name comes from another species) |

Reference versions in the current build: HGNC downloaded 2026-09-24; UniProt release
2026_03; NCBI Taxonomy 2026-09-24; InterPro `entry.list` release 110.0 (the InterProScan
results are InterPro 109; accessions are stable between releases); PANTHER 19.0; Ensembl
release 113 (human proteome and Compara; release 116 for *Lepisosteus oculatus*). Every output
table records the versions it used in its `## Annotation Source Version` header.

### 2.1 OMA template

Gene sets are run through OMA standalone 2.7.0 together with ten precomputed genomes:
*Branchiostoma floridae* (BRAFL; RefSeq GCF_000003815.2), *Callorhinchus milii* (CALMI;
Ensembl Vertebrates 51), *Capitella teleta* (CAPTE; Ensembl Metazoa 27), *Drosophila
melanogaster* (DROME; Ensembl 106), *Homo sapiens* (HUMAN; Ensembl 102, GRCh38),
*Lepisosteus oculatus* (LEPOC; Ensembl 74), *Lottia gigantea* (LOTGI; Sbi1_4 filtered
models), *Monosiga brevicollis* (MONBE; v1.0), *Mus musculus* (MOUSE; Ensembl 104, GRCm39)
and *Nematostella vectensis* (NEMVE; RefSeq GCF_932526225.1, annotation release 101). The
species tree is fixed per run, so HOGs are computed against the known phylogeny.

A gene set that **is** one of these genomes (≥ 50% of its proteins identical in sequence to
one of them) is not run again — that would place the same genome in OMA twice. Its
orthologs are taken from the template's own reference run, with every OMA id mapped back to
the gene set's protein ids by identical sequence.

The human release in OMA (Ensembl 102) differs from the human proteome used for
similarity searches (Ensembl 113). Both resolve to HGNC through the current HGNC set, so
the reported human gene is always a current HGNC record where one exists.

### 2.2 What the naming step needs from the annotation pipeline

Naming reads the annotation pipeline's results as they are; it runs no searches of its own. It
needs them in this form, or the rule that depends on them is silently weaker:

| Input | Required form | Where naming looks | Without it |
|---|---|---|---|
| InterProScan | `-f TSV,JSON`: both the TSV and the JSON | `<analysis>/interproscan/interproscan_results.tsv[.gz]` and `interproscan_results.json[.gz]` | no JSON: no PANTHER tree placement (step 5, `tree+`/`treeC`), and model coverage falls back to protein residues (overestimates; no domain-fragment check); the TSV alone gives PANTHER families and InterPro domains |
| DIAMOND | 2.1.6 `blastp --ultra-sensitive`, E ≤ 1e-5, the 17 columns `qseqid sseqid stitle evalue pident length mismatch gapopen qstart qend sstart send bitscore qlen slen qcovhsp scovhsp` **with the header line** | `<analysis>/diamond/<db>/diamond_results.tsv[.gz]` + `db_version.txt` | fewer than 17 columns: the table is **not used at all** (no coverage) |
| DIAMOND targets | `--max-target-seqs 25` against human, `5` against every other database | same | 1 target: no paralog ties, no second human gene, and `sim~` / `sim-` cannot be judged |
| Human search database | **canonical** Ensembl proteins, one per gene, with Ensembl's own FASTA headers (`gene:`, `gene_symbol:`, `description:` with `[Source:HGNC Symbol;Acc:HGNC:…]`); `$REF_DB/ENS_homo_sapiens/current/peptide.fa.gz` is the FASTA the database was built from | `--ref-db` | headers without `gene:`/`Acc:HGNC`: hits cannot be resolved to HGNC genes |
| MMseqs2 RBH | `mmseqs easy-rbh` against each Ensembl proteome; the protein FASTA it searched must be `peptide.fa.gz` (or the release's `*.pep.all.fa.gz`, same ids) under `--ref-db` | `<analysis>/rbh_mmseq/ENS_<species>/rbh_mmseq_results.tsv` | a hit whose protein is not in that FASTA is skipped (counted in stats: coverage cannot be computed) |
| Reference proteomes | `--ref-db`: required whenever OMA, MMseqs2 or DIAMOND results are given (coverage of hits, human gene loci and sequences); the script stops at the start without it | `$REF_DB/ENS_<species>/current/` | — |
| Gene set metadata | `metadata.yaml` with `ncbi-taxon-id` | `$GENESET_DIR/metadata.yaml` | no taxon id: no PANTHER tree placement |
| Reference data | `scripts/update_reference_data.sh`: HGNC, UniProt cross-references, Ensembl Compara, NCBI taxonomy, InterPro `entry.list`, PANTHER model lengths, PANTHER TreeGrafter trees (`panther_trees`) | `$REFERENCE_DATA` | missing required files stop the build |

**To verify once the annotation pipeline has changed** (it will filter the human proteome to
canonical proteins, keeping every FASTA header as Ensembl writes it): that
`$REF_DB/ENS_homo_sapiens/current/peptide.fa.gz` is the canonical FASTA the DIAMOND and MMseqs2
databases were built from, that naming reads it (not the `*.pep.all.fa.gz` fallback: the
decision table's header lists the files read), and that the stats show no "MMseqs2 ... hits
skipped: target not in the protein FASTA".

With 25 human targets, `sim-` means "the named gene is not among the protein's 25 best human
genes (E ≤ 1e-5)", not "no similarity at all". Canonical proteins make those 25 targets 25 genes;
against all isoforms they would be fewer.

## 3. Hit filters and gene identity

A similarity hit (MMseqs2 or DIAMOND) is used only if the search reports **coverage** of
both proteins and a bitscore. An E-value alone says two proteins share something — often a
single domain — not that they are the same kind of protein; a DIAMOND table without coverage
columns is not used at all.

| Filter | E-value | Query and target coverage | Used for |
|---|---|---|---|
| **support** | ≤ 1e-5 | any | which human gene a protein is most similar to (§5, step 6); support of OMA names (§5.2) |
| **normal** | ≤ 1e-10 | each ≥ 50% | evidence for the closest human gene (§6) |
| **full-length** | ≤ 1e-10 | each ≥ 80% | a `-like` name (§5, step 6) |

**Human genes are compared by identity, never by name text.** Every human hit is resolved to
its HGNC record — by HGNC id, then Ensembl gene id, then UniProt accession, following
withdrawn ids to their replacement. Ensembl proteins state their HGNC gene in their
description (`[Source:HGNC Symbol;Acc:HGNC:9455]`), including genes on alternate haplotypes
and patches whose own Ensembl id HGNC does not list; that accession is used when the Ensembl
id is not found. A human gene with no HGNC record is keyed by its Ensembl gene id. All
isoforms of one human gene are one entry; for each human gene the protein's best hit (by
bitscore, then E-value), its best full-length hit, and whether any hit was a reciprocal best
hit are kept.

### 3.1 Human genes that are not genes of their own

The human proteome holds, besides one model per gene, *readthrough* genes (BIVM-ERCC5: one
transcript joining two neighbouring genes, whose protein contains most of each) and Ensembl gene
models with no HGNC record that are other models of an HGNC gene. Both score about as well as the
gene inside them, so they made false paralog ties (INO80B vs INO80B-WBP1), took the best-hit place
from the real gene (an OMA name marked `sim~` instead of `sim+`), and even gave names
(`CYP3A7-CYP3A51P readthrough-like`). For every human gene a similarity hit (DIAMOND, MMseqs2) or an
OMA partner points to:

- an **HGNC readthrough**, or an Ensembl model with no HGNC record that Ensembl calls a
  readthrough, is not a gene of its own: the hit is not used (its gene's own hit is right behind);
  in an OMA set it is not counted and the provenance says so (§5, step 3);
- an Ensembl model with **no HGNC record** that overlaps an HGNC gene on the same strand (the
  coordinates in the Ensembl FASTA header) and shares ≥ 50% of its protein's 20-residue words with
  it **is that gene** (hits and OMA partners are counted for the HGNC gene; the provenance names the
  model). Overlap alone is not enough: a gene nested in another's intron shares no sequence;
- such a model sharing ≥ 50% with **two** overlapping HGNC genes is an unnamed readthrough
  (ENSG00000258529: 76% ALG9, 94% FDXACB1) and is treated as one;
- anything else is used as it is.

Effect (Congeria, all else equal): 34 names change — false ties resolved, readthrough names
replaced (RAD51L3-RFFL readthrough → RAD51D), genes gain their OMA ortholog's name (DEPDC5, CLN3,
CCND2, SQOR, ACE, PRKCH), OMA names go from `sim~` to `sim+`; *M. capitata* 51. Where OMA's pairwise
set had been inflated by a duplicate model, the gene now reaches the HOG rule as intended (PAOX →
"Polyamine oxidases family member": OMA's HOG makes it co-ortholog of SMOX and PAOX).

## 4. Informative names

A candidate name is used only if it is informative. A description is **uninformative** if,
after cleaning, it is empty, is `None`, or matches any of:

`uncharacterized protein|LOC…` · `hypothetical protein` · `predicted protein` ·
`unnamed protein product` · `unknown protein|function` · `predicted gene[,] N` ·
`novel protein|gene|transcript` · `dubious open reading frame` · `unlikely to encode a
functional protein` · exactly `putative protein` · exactly `protein` · exactly `expressed
protein|product`, `unnamed protein|product`, `transmembrane protein` · zebrafish clone names
(`si:`, `zgc:`, `wu:`) · names that are only a `DUF…` or `UPF` + four digits id (`UNCHARACTERIZED
DUF1308`, `DUF4605 DOMAIN-CONTAINING PROTEIN`, `UPF0462 PROTEIN`; not `UPF0565 protein C2orf69 homolog`,
which names a gene)

— or if it only repeats the hit's own id or symbol (`CG12345`, `F13H8.2 protein`), is itself a
placeholder symbol, has no letters or digits (PANTHER's `-`), or is another species' clone or
locus id: `protein` followed by a locus id (`PROTEIN CBG26694`, `PROTEIN CBR-CLEC-78`,
`PROTEIN FAM167A`), a locus id followed by `protein` (`RGD1565685 PROTEIN`, `OS10G0105400
PROTEIN-RELATED`), fly cDNA clones (`LD39211P`, `GEO02494P1`, `FI19922P1-RELATED`), mosquito
and other locus ids (`AGAP001331-PA-RELATED`), `EG:114D9.1 PROTEIN-RELATED`, mouse clone- and
EST-based names (`cDNA sequence BC048562`, `RIKEN cDNA 1110002E22 gene`, `expressed sequence
AI413582`, `DNA segment, Chr 1, …`), human cDNA clone labels (`cDNA FLJ12345 fis`), Celera mouse
ids (`MCG131172, isoform CRA_a`) and *Aspergillus* locus labels (`… putative (AFU_orthologue
AFUA_1G17690)`). None of these patterns matches an HGNC approved symbol or name (all 45,083
approved names checked, September 2026).

A family or PANTHER name never contains a colon (`DUMPY: SHORTER THAN WILD-TYPE` becomes
`DUMPY - SHORTER THAN WILD-TYPE`): downstream, the text before a name's first colon is read as
its symbol.

**Placeholder symbols** are never shown as a symbol: RefSeq `LOC…`, fly `CG…`/`CR…`, mouse
`Gm…` and RIKEN clones, zebrafish clone names, *C. elegans* sequence names, yeast
systematic ORF names, and Ensembl or RefSeq accessions used as symbols. Each pattern was
checked against the HGNC approved symbols (September 2026) so that no real human gene is
matched.

**Cleaning** removes source decoration only: Swiss-Prot `OS= OX= GN= PE= SV=` fields,
Ensembl `[Source:…]`, `LOW QUALITY PROTEIN:`, isoform and transcript-variant suffixes,
`(Fragment)`, `, partial`, a trailing `precursor`, and all but the first of a `;`-separated
list of names. PANTHER family names are tidied further: `, ISOFORM X`, repeated `-RELATED`,
a trailing `PRECURSOR` or full stop — and, being written in capitals, put in sentence case word by
word: a word HGNC's approved names use takes HGNC's most frequent spelling (`dehydrogenase`,
`GTPase`, `CoA`, `tRNA`, `SET`); a word HGNC does not use is lowered from 6 letters on
(`METALLOPROTEASE`) and kept as an acronym when shorter (`NACHT`, `DOMON`); words with digits stay
(`9C`, `E2`). `SHORT-CHAIN DEHYDROGENASE/REDUCTASE FAMILY 9C` becomes `Short-chain
dehydrogenase/reductase family 9C`. The provenance keeps PANTHER's own spelling. Native names (§5, step 2) are kept exactly as the source
gives them.

## 5. Naming — the first step that yields a name wins

The whole decision, as a diagram: [NAMING_DECISION_TREE.md](NAMING_DECISION_TREE.md).

Every step is evaluated for every gene; the name is taken from the first step, in order, that
gives one (with the rules below that tie steps together). What every step found — and why each
step before the chosen one gave no name — is in `naming_decisions.tsv` (§7).

The principle: **a plain name only from a supported orthology call; `-like` for full-length
similarity to one human gene; a family or domain name when the evidence stops there;
otherwise no name.**

Every name ends in an evidence tag, e.g. `[ISO|1to1|sim+|pthr+]` (full list in §5.1). In short:

- **Evidence type** (first part; the first four are Gene Ontology evidence codes):
  - `ISO` — **I**nferred from **S**equence **O**rthology: an OMA ortholog
  - `ISS` — **I**nferred from **S**equence **S**imilarity: a `-like` name
  - `ISM` — **I**nferred from **S**equence **M**odel: a PANTHER family, InterPro domain or transposon domain
  - `TAS` — **T**raceable **A**uthor **S**tatement: a curator's name
  - `SRC` — **S**ou**RC**e: the gene set's own (RefSeq/Ensembl) name (our code; not GO)
- **What kind:**
  - `1to1` one-to-one · `Nto1` N genes here carry this human gene's name · `fam` family (co-orthologs) — the OMA relationship
  - `rbh` **r**eciprocal **b**est **h**it · `bh` **b**est **h**it
  - `tie-rbh` a paralog tie decided by the reciprocal best hit · `tie-grp` a paralog tie named for the HGNC **gr**ou**p**
  - `pthr` **P**AN**TH**E**R** family · `rpt` re**p**ea**t** · `ipr` **I**nter**Pr**o domain · `te` **t**ransposable **e**lement
- **Support:**
  - `sim+` / `sim~` / `sim-` — **sim**ilarity: the named human gene is the best human hit / a hit but not the best / not a hit
  - `pthr+` / `pthrC` — the gene's **P**AN**TH**E**R** family is the same as / **c**onflicts with the named human gene's
  - `hog` — OMA's **H**ierarchical **O**rthologous **G**roup agrees
  - `te` (on an `ISO` name) — the ortholog carries a **t**ransposable-**e**lement domain
  - `omaX` — an **OMA** ortholog was e**x**cluded (set aside: nothing supported it)
  - `omaC` — an **OMA** name was withheld: the evidence **c**onflicts with it (best human hit another gene, and a different PANTHER family)

| Step | Source | Condition | Name form | Tag |
|---|---|---|---|---|
| 1 | **Human-curated names** | a curator's file lists the gene | exactly as given — the only step not checked for informativeness | `TAS` |
| 2 | **Native name** | RefSeq/Ensembl gene sets | the source's own name, kept unless uninformative | `SRC` |
| 2 | **Naming species** (optional, per gene set) | its OMA 1:1 or many:1 ortholog; else its full-length (≥ 80% of both proteins) reciprocal best hit; else, for another species only, its DIAMOND best hit if that hit is full-length; else its hits file; informative | `NAME` for another annotation of the same species; otherwise `NAME-like (label)` | `SRC` / `ISO` / `ISS` |
| 3 | **OMA orthology to human** | closest human gene from OMA (§6, tier 1 or 2), supported (§5.2) | see below | `ISO` |
| 4 | **Transposable element** | a transposable-element Pfam domain (see below) | `<class> transposase domain-containing protein` | `ISM\|te` |
| 5 | **PANTHER tree placement** | a trusted placement joins the gene to exactly one human gene at a speciation node, and that gene is also its closest human gene by similarity (§6, tiers 3–5) | `SYMBOL: approved name` | `ISO\|tree` |
| 6 | **Full-length human similarity** | see below | `SYMBOL-like: approved name-like`, or `<HGNC group> family member` | `ISS` |
| 7 | **PANTHER family** | the match covers ≥ 75% of the family model's positions (≥ 80% by protein residues when there is no InterProScan JSON); informative | `<family> family member`; a repeat-built family: `<repeat>-containing protein` | `ISM\|pthr`, `ISM\|rpt` |
| 8 | **InterPro domain** | the gene's best InterPro *Domain* or *Repeat* entry, informative | `<domain> domain-containing protein` | `ISM\|ipr` |
| – | — | nothing above | `None` (the gene keeps its own transcript id as name and description); the Gene Name Source table says which kind: **None: no hits** (no similarity hit in any database searched, no OMA ortholog in any species, no InterProScan homology match) or **None: hits did not pass the naming tests** (with the kinds of evidence found) | — |

The steps are tried in this order; the step number is the Score of the Gene Name Source table.

**Step 3 — OMA orthology.**
- One human gene, 1:1: `SYMBOL: approved name`.
- One human gene shared by several genes of this gene set (many:1, a lineage-specific
  duplication): every copy is an ortholog of that gene and carries the same name. The number of
  copies is in the name, as Ensembl writes one-to-many orthologs (`ALPHA: alpha synthase (1 of 4)`:
  each copy is one of four), in the tag (`4to1`) and in the provenance. N is counted after every
  gene is named: the genes that **carry** the name, not the genes OMA paired with the human gene
  (a copy may be named as a transposon, withheld, or curated instead); the provenance then adds
  "OMA pairs M genes here with it, K of them named by other evidence". Copies named from one
  human gene are `Nto1` whatever OMA's pairwise type: OMA can call one copy 1:1 pairwise while
  its HOG adds the other. (Before 2026-09-30 N was OMA's pairing count, so 26 Congeria and 28
  *M. capitata* names had tags that did not match the genes carrying them; now none.)
- Several human genes (1:many, many:many — typically duplications in the human lineage, such
  as the vertebrate genome duplications): named after the most specific HGNC gene group all
  members share (`EPH receptors family member`), with **no symbol** — a symbol is what users
  search as a gene's identity, and a family has none. **No member is singled out.** A gene that
  predates a duplication is equally related to every copy; the best BLAST score only
  identifies the slowest-evolving copy. Only an HGNC group that is a **family by descent** is
  used (below); failing that, the PANTHER family all the human members belong to names it
  (`<family> family member`) — only when the gene is a whole member of that family: its own match
  covers ≥ 50% of the family's model, or it has a full-length hit (§3) to one of the human members
  (model coverage as in step 7; the full-length alternative matters for members much shorter than
  the family model: ACBP covers 36-38% of its family's model by either measure, yet aligns 99%/100%
  to DBI); failing that, the gene
  goes directly to step 7 — not to steps 5 or 6, which would pick one member after all. In
  *C. kusceri* the whole-member condition removed 24 family names, mostly Sushi-domain proteins
  and fragments (a collagen piece hitting 18% of COL1A2), which are then named by their domain.
- **Which HGNC groups are families.** HGNC groups are also made by a shared domain (`EF-hand
  domain containing`, `Sushi domain containing`) or a function (`CD molecules`, `BAF complex
  subunits`), and "family member" would claim a common ancestry those do not have. A group's
  **PANTHER coherence** is the fraction of its human genes — those whose Swiss-Prot entry names a
  PANTHER family — that are in the group's most common PANTHER family. PANTHER families are
  built as phylogenetic families (HMMs and gene trees), so a family by descent falls mostly in one:
  Tubulin beta family 1.00, Tetraspanin family 0.94, Cathepsins 0.73, Heat shock 70kDa proteins
  0.65, Kelch like 0.60; domain and function groups scatter: CD molecules 0.07, EF-hand domain
  containing 0.09, Sushi domain containing 0.16, BAF complex subunits 0.15, RING finger E3
  ubiquitin protein ligases 0.21. A group names a family only at coherence ≥ 0.6, a value chosen
  in that gap (0.8 was also tested: 1,095 *C. kusceri* names changed instead of 899, and coherent
  groups such as Cathepsins and Peroxiredoxins lost their HGNC names). A group with fewer than
  two genes in PANTHER, or with no Swiss-Prot data, cannot be judged and is used. Known edge
  case: RAB GTPases (0.26) are one family, but PANTHER splits them into many small families; RAB
  genes are named by their PANTHER family instead. The same rule applies to paralog ties
  (step 6) and to the closest-human family label (§6.2).
- **A many:1 pairing mostly rejected is not used.** When OMA pairs at least 5 genes of this gene
  set with one human gene and fewer than half pass the support and conflict checks (set aside,
  `omaX`, or withheld, `omaC`), the pairing was made through a shared domain or repeat, not
  orthology: none of them is named after that gene; the next step names them and the tag carries
  `omaR`. Congeria: 6 names (APOH ×3 — 18 Sushi-domain proteins paired with a vertebrate plasma
  protein, 15 rejected; NCAN, TNN).
- **The copies are listed.** A many:1 name's provenance names the other genes carrying it ("one of 3
  genes in this genome named after it (the others: …)"; up to 10, then "and N more"), so specific
  primers, RNAi or antibodies can be designed.
- **Domains are compared.** Every single-gene orthology, tree and `-like` name says whether the gene
  has the Pfam domains of the human gene it is named after ("has all 3 of ALPHA's Pfam domains
  (…)" / "has 2 of ALPHA's 3 Pfam domains; no Pfam match here to PF00017 SH2"): the human gene's
  domains from its reviewed UniProt entries (`uniprot/human_pfam.tsv.gz`, same UniProt release as
  the Swiss-Prot table), the gene's from its InterProScan Pfam matches (a fragment under 50% of its
  model does not count), names and clans from the Pfam release InterProScan ran
  (`pfam/pfam_names.tsv`). **A sister family of the same Pfam clan counts as the domain**: the
  mussel MYD88's TIR domain matches PF01582 TIR while human MYD88 is annotated PF13676 TIR_2 (clan
  CL0173); comparing accessions alone called 240 of Congeria's orthologs short of a domain they have.
  A domain with no match is reported as "no Pfam match", not as absent: it may be too diverged for
  Pfam's threshold. Reported only: it does not change the name. Congeria: of 4,705 single-gene
  orthology names, 4,093 (87%) have all their human gene's Pfam domains, 537 (11%) have no match to
  at least one, 75 have no Pfam data; `-like` names 70% / 23% / 7%.
- **A partial best human hit is described by its shape.** For a gene named by a domain, or not named,
  whose best human hit is partial, the provenance says what the shape suggests: ≥ 80% of this
  protein on < 50% of the human one — "possibly a fragment of a larger X-like gene (the gene model
  may be incomplete)"; ≥ 80% of the human protein within < 50% of this one — "possibly a fusion, or
  two gene models merged"; < 50% of each — "a shared domain or region — X is a distant
  relative, not this gene's identity". A domain comparison would repeat what the domain name says;
  the shape says whether the human gene is this gene's identity or a relative, and whether the
  model may be broken. Every fragment- or fusion-shaped gene, whatever its name, is listed in
  `gene_model_flags.tsv` for checking against mender. Congeria: 2,533 fragment, 122 fusion; only
  12% of the fragments are pieces of a mender merge (mender joins neighbouring annotated pieces;
  a lone fragment whose missing part was never annotated is out of its reach).
- **Alignment coverage is stated.** Every orthology name (and a tree name) says how much of this
  protein and of the human protein the best alignment to the named gene covers, and whether it is
  full-length ("aligned over 95% of this protein and 88% of ALPHA (full-length)").
- **Human genes that are not genes of their own are not counted.** OMA's set can include an
  HGNC *readthrough* (BIVM-ERCC5: one transcript joining two neighbouring genes, so its protein
  contains most of ERCC5) or an Ensembl gene with no HGNC record that is another model of the same
  gene (a "novel protein" whose 20-residue words are ≥ 50% those of an HGNC member of the set, in
  the human proteome OMA used). Counted, they turn one ortholog into a "family of 2" and the gene
  loses its name. They are left out when the set keeps a real gene; the provenance names them
  ("not counted: BIVM-ERCC5 (a readthrough joining BIVM and ERCC5)"). A set made only of such genes
  gives no OMA ortholog (the next evidence decides). A no-HGNC member with a sequence of its own
  stays: it may be a real paralog (3 of 246 in Congeria and *M. capitata*; the others share
  ≥ 50%, and all placeable ones overlap their HGNC gene's locus). **But** when this gene's best hits
  to *both* genes of a readthrough lie on different stretches of its protein (overlapping by ≤ 20
  residues; Ensembl and Swiss-Prot human hits), it may itself be two genes fused in one model: it is
  not named after either (a case for mender). None of the 57 Congeria and 66 *M. capitata* genes
  with a readthrough in their set did; each matched one half. Effect: Congeria 152 names change
  (about 95 genes gain their single ortholog's name, e.g. ERCC5, IDS, NDUFB8, BACC1; 43 an OMA
  family name; 4 lose a name that came from a readthrough, e.g. three GPCRs named
  "LINC02210-CRHR1 readthrough").
- **Pairwise 1:1, HOG several.** OMA's pairwise file can pair a gene 1:1 with one human copy of
  a vertebrate duplication (HDAC1 of HDAC1/HDAC2) while OMA's HOG, computed on the fixed
  species tree, makes it co-ortholog of every copy. The HOG is then the more complete call: the
  gene is treated as a co-ortholog family, as above.
- **Supported OMA calls only** (§5.2). An OMA human ortholog that no other evidence backs is
  set aside; the next step names the gene, and its tag carries `omaX`.
- **Conflicting OMA calls are not used for the name** (§5.2). When the gene's best human
  similarity hit is another gene **and** its PANTHER family differs from the named gene's, the
  OMA name is withheld; the next step names the gene, and its tag carries `omaC`.

**Step 4 — transposable elements.** A gene with the catalytic or signature domain of a
transposable element (a Pfam match InterProScan reports, i.e. past Pfam's own threshold; table
below) is named for its element class. It comes before the similarity and family steps, which
would otherwise name a transposon after a human gene domesticated from such an element
(`HARBI1-like`) or after a PANTHER family. It replaces an OMA name only when OMA pairs ≥ 5 genes of this gene
set with the same human gene (many:1): a transposon family next to one domesticated human
gene. The threshold of 5 was chosen by inspecting *C. kusceri* and is otherwise arbitrary: it
catches some transposon families, not all, and those it catches get a cleaner name; a genuine
lineage expansion of a domesticated gene with ≥ 5 copies would be named as a transposon. A 1:1
OMA ortholog carrying such a domain keeps its OMA name and is flagged `te`.

| Pfam | Pfam name | Class | Name |
|---|---|---|---|
| PF13359 | DDE_Tnp_4 | PIF/Harbinger DNA transposon | PIF/Harbinger transposase |
| PF13358, PF01359 | DDE_3, Transposase_1 | Tc1/mariner DNA transposon | Tc1/mariner transposase |
| PF03184 | DDE_1 | pogo (Tc1/mariner) DNA transposon | pogo transposase |
| PF13843 | DDE_Tnp_1_7 | piggyBac DNA transposon | piggyBac transposase |
| PF05699 | Dimer_Tnp_hAT | hAT DNA transposon | hAT transposase dimerisation |
| PF10551, PF20700 | MULE, Mutator | Mutator DNA transposon | Mutator transposase |
| PF02992 | Transposase_21 | En/Spm (CACTA) DNA transposon | En/Spm transposase |
| PF14214 | Helitron_like_N | Helitron rolling-circle transposon | Helitron helicase |
| PF05380 | Pao_retrotransp | Bel/Pao LTR retrotransposon | Bel/Pao retrotransposon RNase H |
| PF00665, PF24764 | rve, rva_4 | LTR retrotransposon | retrotransposon integrase |

Left out on purpose: DNA-binding helper domains (HTH_Tnp_4, the CENP-B-type HTH), reverse
transcriptase alone (telomerase has one), and RNase H-like domains Pfam names after
domesticated human genes (PF14291 ZMYM1/FAM200, PF27041 ZBED1), which Pfam does not describe as
transposases.

**Step 5 — PANTHER tree placement.** InterProScan's PANTHER step runs TreeGrafter, which
places each protein on its PANTHER family tree (the graft point, in the JSON output only).
`scripts/panther_placements.py` traces the graft point on PANTHER's own trees to the human genes:
where the protein joins the human lineage, a **speciation** node makes those human genes its
orthologs (one gene: `ortholog_1`; several, from a duplication on the human side: `co-orthologs`),
a **duplication** node makes them paralogs (`paralog_family`). PANTHER holds few invertebrates, so
TreeGrafter often grafts an invertebrate protein inside a lineage it cannot belong to (a mollusc
protein on a deuterostome or *Xenopus* node: about half of the *C. kusceri* placements); such a
graft is moved up to the first speciation node of the species' own lineage (NCBI taxonomy),
which can only add human genes, never narrow them. A placement is **trusted** only when its
PANTHER match has E ≤ 1e-10 and covers ≥ 50% of both the protein and the family model.

The tree names a gene only when a trusted placement gives exactly one human ortholog **and** that
gene is also the gene's closest human gene by similarity (MMseqs2 RBH, orthology via another
species, or the DIAMOND best hit: §6, tiers 3–5) — two methods agreeing on one gene, so the name is
plain, as for OMA. The two are not fully independent (both rest on sequence similarity; the tree's
placement is itself scored by an HMM), but they reach the gene differently: one through the whole
PANTHER family tree, the other through pairwise hits. When the closest gene is a family (a chain
through another species reaching several copies), the tree's choice of one copy is shown as a vote
(`tree+`) but does not name the gene: one method alone would be picking the copy. Like step 6, it is skipped after an OMA co-ortholog family that step 3
could not name. Elsewhere the tree is one vote: on OMA and `-like` names it adds `tree+` (it
places the gene with the named human gene) or `treeC` (with other human genes), and never changes
the name. On a 3,235-protein *C. kusceri* sample: trusted placements put the gene with OMA's named
gene 215 times out of 221; the step named 78 genes (63 previously a PANTHER family name, 9 a
`-like` name, 5 a domain, 1 none), and `tree+`/`treeC` marked 749/20 OMA and 57/8 `-like` names.
The disagreements with OMA (AQP8 → AQP1/2/4/5/6/MIP, PSMB4 → PSMB1) look like tree misplacements
more often than OMA errors, which is why the tree does not overrule OMA.

**Step 6 — full-length human similarity.** Human genes are compared by identity and
bitscore (§3).
1. **The top human hit names the gene only if all of these are true:**
   - it is the human gene with the highest bitscore (the top hit);
   - E ≤ 1e-10;
   - the alignment covers at least 80% of both proteins — the query (this gene's protein)
     and the target (the human protein; any isoform of that human gene counts);
   - its name passes the informative-name test (§4).

   If all are true, the gene is named after that human gene with `-like` appended. If any
   one is false, step 6 gives no name and naming moves on to the next step (PANTHER family).
   A weaker hit further down the list is never used instead — not when the top hit is
   partial, and not when the top hit's name is uninformative but a weaker hit has a better
   one: the weaker gene is not the one this protein is most like. Example: a protein whose
   top hit is WDR90 (562 bits) over only part of WDR90, and whose weaker hit to CFAP52
   (119 bits) is full-length, is **not** named `CFAP52-like`.
2. If another human gene scores within 5% of its bitscore, the paralogs are a **tie**. A
   reciprocal best hit (MMseqs2) to exactly one of the tied genes, itself full-length, decides
   (tag `tie-rbh`); otherwise the tied genes' shared HGNC group names the gene
   (`Heat shock 70kDa proteins family member`, tag `tie-grp`) if that group is a family by
   descent (step 3), else the PANTHER family they all belong to and the gene matches; otherwise
   step 6 gives no name.
3. The name is **always** `-like`, reciprocal or not: sequence similarity, even reciprocal, is
   not an orthology call (§9). The symbol is the human gene's HGNC symbol, or none; it is
   never taken from another gene.

**Step 7 — PANTHER family.** A family names the gene only when the gene's PANTHER match covers
≥ 75% of the family's HMM. **Model coverage** is measured on the model: the model positions
(hmmStart–hmmEnd) of all the match's locations, merged, over the family model's length, from the
InterProScan JSON (`scripts/interproscan_model_coverage.py`; the coordinates are on the family
model — none of 2,755 checked locations passes its end). Without the JSON it falls back to the
TSV's protein residues over the model length, which overestimates when the protein has
insertions: on a 3,130-protein Congeria sample, 320 of the 1,935 matches at ≥ 80% by residues
cover less than 80% of the model (PTHR10133: 100% by residues, 57% of the model); 31 go the other
way. The bar is 75% on the model (chosen 2026-09-30: on that sample 80% on the model kept 1,646 of the
1,935 matches the residue measure passed, 75% kept 1,811, 70% the same number but 126 different
matches); the residue fallback keeps its 80%. The decision table's header says which measure a run used. Protein coverage is not required: a multidomain
protein that contains the whole family model is a member. Below the threshold the match is
usually one shared domain (a SET domain matching the KMT5A family at 38% of its model), which
step 8 names honestly. Among qualifying families the lowest E-value wins. The name is
InterPro's curated name when the family is integrated into an InterPro *Family* entry
(`BONUS, ISOFORM C-RELATED` → `TRIM45/56/19-like`), else PANTHER's own name, cleaned (§4) —
except when InterPro's name describes a function or a process rather than naming a family
(words such as *regulator, organizer, assembly, signaling, immunity, development, defense,
roles, pathway, stress, apoptosis*: `Complement & Cell Adhesion Regulators`, `Cerebellin
Synaptic Organizer`, `Synovial Proliferation Regulator` for serum amyloid A, `Bacterial
Antiviral Defense Nuclease`). Such names carry roles known from other organisms, often
vertebrates; PANTHER's own name is used instead when it is informative (`Cerebellin-related`,
`Serum amyloid A`, `ATP/GTP phosphatase`), and the provenance says why. In *C. kusceri* this
changed 97 names. The same label choice applies wherever a PANTHER family names a gene (step 3
co-ortholog families, step 6 ties).
**Repeat-built families:** when repeat units (InterPro *Repeat* entries, and the C2H2 zinc
finger, which InterPro types as a *Domain*) cover ≥ 25% of the family match, model coverage
says nothing — any protein with such repeats fills the model (a mollusc C2H2 protein fills
`KRAB AND ZINC FINGER DOMAIN-CONTAINING`, though KRAB is a tetrapod innovation). The gene is
named for the repeat covering most of the match instead (`Zinc finger C2H2-type
domain-containing protein`, tag `rpt`).

**Step 8 — InterPro domain.** The name states the one thing known: a domain. This is
UniProt's convention for such proteins (`SET domain-containing protein`). Only InterPro
entries of type *Domain* or *Repeat* are used (families are step 7; homologous superfamilies
are too broad; sites are not domains), and not entries of unknown function (DUF, UPF,
uncharacterised). Every match InterProScan reports has already passed its member database's
curated threshold (Pfam's per-family gathering thresholds, SMART, CDD, PROSITE profiles); no
E-value floor is added, because an E-value depends on domain length and a single floor would
remove short domains (zinc fingers, repeats) however real — the reason Pfam uses per-family
bit-score thresholds. PROSITE patterns are not used (a short regular expression with no score
or threshold). InterProScan's thresholds are for significance, not coverage: a significant match
can be a fragment of the domain. So where the JSON gives the model coordinates (Pfam, Gene3D,
FunFam, NCBIfam, PIRSF, SFLD — not SMART, which reports every match as the whole model, nor CDD,
PROSITE or PRINTS, which report none), a match must cover **≥ 50% of its domain model** to name
the gene; a fragment is skipped and the next domain is considered. In Congeria 4% of Pfam matches
are below 50% (median 95%). The provenance states the domain model coverage. The gene's best match is chosen by the
lowest E-value, then entries without one (PROSITE profiles), then by accession. The name is InterPro's curated entry name, with
"comma + space" removed (`Zinc finger, RING-type` → `Zinc finger RING-type domain-containing
protein`; `1,2-lyase` is untouched) and `-containing protein` appended when the name already
ends in domain or repeat. When the gene has a human homolog that could not name it (partial),
the provenance says so and the tag carries `sim~`.

**Hits to other species never name a gene.** A transferred name may belong to a
lineage-specific paralog (`solute carrier family 37 member 4a` in fish), which cannot be
detected and would be wrong in the new organism. Other-species hits remain available as
homolog tables in the database.

### 5.1 Confidence and evidence tags

**No confidence word.** An earlier version opened every provenance with Strong / Moderate / Weak. It was dropped
(2026-09-30): it mixed how specific a name is with how well it is supported -- a domain name from an excellent InterPro
hit read "Weak" only because a domain cannot say which gene it is -- and among gene-level names it only summarized
doubts that are better stated. Instead:

- the **relationship** says what the name claims (below);
- every reason for doubt is a **caution**, in words: the evidence marks (best hit another gene, PANTHER family
  differs, tree elsewhere, OMA pair set aside or withheld), and two that no mark states -- a plain human name on a
  gene aligning to under half the human protein ("aligns to 48% of RNF213"; Congeria 96), a `-like` name from a
  one-way best hit ("a one-way best hit, not reciprocal"; 245). They end the provenance line ("; caution: ...") and
  form the Cautions statement (5.3);
- the methods that **agree** are named in the Support statement (5.3); a PANTHER-tree name with fewer than 3 says
  how many in its provenance ("2 methods agree on this gene"; 128) -- a count, not a caution: two agreeing methods
  is good evidence.

Wording: no "only". It judged numbers the reader can judge ("only 2 methods", "only 63 aa"); the statements give the
number and the fact ("a short protein, 63 aa", "over part of its length", "a shared domain or region").

Checked on Congeria: every gene-level name the words had rated Weak, and every Moderate one but 221, carries a caution;
the 221 are `-like` names from a clean full-length reciprocal best hit, whose relationship already says "orthology not
shown".

**The relationship opens the provenance line**, in a fixed vocabulary (the decision table's `Relationship` column), so the line says
what kind of claim the name is before why:

| Relationship | Name | The provenance line opens |
|---|---|---|
| ortholog | OMA 1:1, or one human gene on the PANTHER tree | "Ortholog of human X ..." |
| co-ortholog | one of several copies here named after one human gene ("(1 of N)"), or several human genes (OMA 1:many, many:many) | "Co-ortholog of ..." |
| homolog | `-like` (step 6) | "Homolog, orthology not shown (may be a paralog): similar to human X ..." |
| family homolog | a PANTHER family or HGNC group name (steps 6 tie, 7) | "Family homolog, orthology not shown: member of ..." |
| domain homolog | a domain, repeat or transposon-domain name (steps 4, 7 repeat, 8) | "Domain homolog: contains ..." |
| curated / source annotation / none | steps 1, 2 / no name | -- |

Orthology is claimed only where a method tested it (OMA, the PANTHER tree): similarity alone shows homology, and the
closest human gene by similarity may be a paralog of the true ortholog. Congeria: 3,953 ortholog, 3,074 co-ortholog,
500 homolog, 2,324 family homolog, 10,318 domain homolog, 23,599 none.

A gene with no name and a protein under 100 aa says so ("a short protein, 63 aa"): with nothing known about it,
the length is the most useful fact -- in Congeria only 3% of no-hit proteins under 100 aa have an ORF in the species'
own transcriptome (Trinity, >= 95% identity over >= 90% of the protein), against 61% of named genes; many are likely
not real genes (10,922 genes).

**Protein features and expression, for genes with no name** -- reported, never used to name (where a protein sits
says nothing about what it is). From the annotation run's own outputs (`process_one_geneset.sh` passes them when
present): SignalP 6 (`--signalp`: signal peptide and its kind), DeepTMHMM (`--deeptmhmm`: transmembrane helices, only
when there are some) and DeepLoc 2 (`--deeploc`: its location, only where DeepLoc found a sorting signal -- without
one the location is a default, mostly "cytoplasm", and would read like a finding; 15,440 Congeria proteins).
Optional per gene set (`transcript_hits:` in geneset_config.yaml, `--transcript-hits`): the proteins searched against
the species' own experimental transcriptome with `scripts/transcriptome_search.sh` (MMseqs2; nucleotide transcripts
searched translated, or predicted ORFs as proteins; the search pairs ids, which need not agree; its LABEL -- what the
transcriptome is, tissues and stages -- is shown). A match of >= 95% identity over >= 90% of the protein is reported as
"expressed: a transcript matches it in adult gill (Trinity)". Several transcriptomes (a tissue, a developmental stage)
are searched one by one and listed together under `transcript_hits:`; the statement names each that has the gene
("...in adult gill (Trinity) (100% identity over 100% of the protein); in veliger larvae (97% ...)"). **Positive only**: a transcriptome samples some tissues and
stages at some depth, so no match is not evidence a gene is not expressed, and is not reported. Both appear in the
decision table (`Protein_features`, `Transcript_support`) for every gene, and in the provenance of genes with no name:
"None: no hits (...); expressed: a transcript matches it in its own transcriptome (100% identity over 100% of the protein);
predicted: signal peptide (SignalP 6); 1 transmembrane helix (DeepTMHMM); location cell membrane (DeepLoc 2: signal
peptide, transmembrane domain)". Congeria (Trinity ORFs from mender's search): 2,553 no-hit genes are expressed,
1,970 of them with a predicted feature (857 a nuclear signal, 558 membrane, 421 secreted) -- candidates for genes new
to this lineage.

#### Evidence tags

**Support marks.** Every kind of support is written with the same five marks, so a mark means
the same thing wherever it appears:

| Mark | Meaning | Tags |
|---|---|---|
| `+` | agrees | `sim+` (the named human gene is the best human similarity hit), `pthr+` (same PANTHER family), `tree+` (the PANTHER tree places it with the named gene) |
| `~` | partly: similar, but not the best | `sim~` (the named gene is a hit, another human gene scores higher) |
| `C` | contradicts: the evidence points elsewhere | `pthrC` (a different PANTHER family), `treeC` (the PANTHER tree places it with other human genes), `omaC` (an OMA name withheld: `sim~` and `pthrC` together) |
| `-` | no evidence | `sim-` (no similarity hit to the named gene) |
| `X` | excluded: set aside | `omaX` (an OMA pair nothing supports) |

`~` is deliberately not `C`: a close paralog outscoring the true ortholog is common and does not by
itself contradict an orthology call; only together with a conflicting PANTHER family (`pthrC`) is
the call withheld (`omaC`, §5.2).

Every name ends in a tag, modelled on the Gene Ontology evidence codes, so a reader sees the
kind of evidence next to the name: `ALPHA: alpha synthase [ISO|1to1|sim+|pthr+]`. The tag
contains no colon. The full reasoning is in the Gene Name Source table (§7).

| Part | Meaning |
|---|---|
| `ISO` | inferred from sequence orthology (OMA) |
| `ISS` | inferred from sequence similarity (`-like`) |
| `ISM` | inferred from a sequence model (PANTHER family, InterPro domain, TE domain) |
| `TAS` | human-curated name |
| `SRC` | the gene set's own name, or another annotation of the same species |
| `1to1`, `Nto1`, `mto1`, `fam` | OMA relationship: one-to-one; N genes of this gene set carry the human gene's name (counted after naming); many-to-one, one copy named; co-ortholog family |
| `tree` (on `ISO`) | orthology from the PANTHER tree placement (step 5), followed by how the closest human gene agrees: `rbh`, `via` (another species' ortholog) or `bh` |
| `rbh`, `bh` | the `-like` hit is a reciprocal best hit, or a best hit |
| `tie-rbh`, `tie-grp` | a paralog tie decided by a reciprocal best hit, or named for the group |
| `pthr`, `rpt`, `ipr`, `te` | PANTHER family; repeat-built family; InterPro domain; transposable element |
| `sim+`, `sim~`, `sim-` | the named human gene is the protein's best human hit; a hit but not the best; no hit (E ≤ 1e-5). On an `ISM` name, `sim~`: the gene has only a partial human homolog |
| `pthr+`, `pthrC` | the gene's PANTHER family is the same as / conflicts with the named human gene's |
| `hog` | OMA's HOG agrees with the call |
| `te` (on `ISO`) | the ortholog carries a transposable-element domain |
| `omaX` | an OMA human ortholog was set aside for lack of support (§5.2) |
| `omaC` | an OMA name was withheld: best human hit another gene and a different PANTHER family (§5.2) |
| `omaR` | an OMA many:1 pairing mostly rejected (≥ 5 genes paired, fewer than half pass): not named after that gene |
| `tree+`, `treeC` | a trusted PANTHER tree placement puts the gene with the named human gene / with other human genes (step 5) |

### 5.2 Support of OMA calls

OMA is precise but not infallible. Repetitive and compositionally biased proteins, and hidden
paralogy (each lineage kept a different copy of an old duplication, which OMA can detect only
when a third species kept both), give OMA pairs that no other evidence backs. In *Congeria
kusceri*, 224 plain OMA names had no alignment to the named human gene even in a permissive
search (DIAMOND ultra-sensitive, 100 targets, E ≤ 10), and 62% of those were in a different
PANTHER family from the named gene — among them vertebrate- or mammal-specific genes (DMP1,
APOL2, KRTAP5-4). Among OMA names backed by similarity, 95% shared the named gene's PANTHER
family.

An OMA human ortholog (or co-ortholog set) is therefore used only when the gene is **also**
similar to one of those human genes (the support filter, §3) **or** shares a PANTHER family
with them (the gene's InterProScan PANTHER matches at any coverage; the human gene's
Swiss-Prot entries). Otherwise it is set aside for both the name and the closest human gene;
the next evidence decides, the name's tag carries `omaX`, and the provenance names the pair.
The pair remains in the database's OMA ortholog tables. When no human similarity search was
run, OMA calls cannot be checked and are used as they are.

**Conflicting evidence.** Support removes calls nothing backs, but hidden paralogy passes it:
paralogs share domains and PANTHER families. Two checks that OMA does not use can each speak
*against* a call: the gene's best human similarity hit is another gene (`sim~`), and its PANTHER
family differs from the named gene's (`pthrC`). Each alone is common and weak — a close paralog
can outscore the ortholog, and PANTHER families are split and renamed between releases (in
*C. kusceri*, withholding names on either one alone changed 700 names, many of them sound). Both
together are the signature of hidden paralogy or of an OMA pair made through a shared repeat or
domain, and the OMA name is then withheld: 136 of 43,768 *C. kusceri* names (97 single genes,
39 families), mostly repeat- and domain-rich proteins — 18 copies paired many:1 with APOH, and
selectins, matrilins and cadherins paired through Sushi, vWA and cadherin domains — which the
next steps name by their domain or family. The closest human gene stays the OMA partner (OMA did
make the call); the name's provenance states the conflict.

### 5.2a Definitions -- the relationship words, and when this pipeline uses each

Each word has its textbook meaning, and a rule here that decides when a gene gets it. The rules are strict on
purpose: a word is used only when the evidence tested it.

**Homolog.** Two genes descended from one ancestral gene. The umbrella term: orthologs and paralogs are both
homologs. Here, similarity is counted as a hit at E <= 1e-5 (support for another call); "similar along its length"
means a hit covering >= 80% of both proteins at E <= 1e-10.

**Ortholog.** Two genes that split when their species split (at a speciation), so they are "the same gene" in two
species -- the relationship that best predicts a shared function. Used when:
- OMA pairs the gene 1:1 with one human gene (pairwise orthology, the 10-species OMA template run), and that
  pairing is not contradicted: an OMA pair that no similarity hit or shared PANTHER family supports is set aside
  (`omaX`), and one whose best human hit is another gene AND whose PANTHER family differs is withheld (`omaC`); or
- the PANTHER tree places it with exactly one human gene: TreeGrafter's graft point, traced to the first node that
  has human genes, is a speciation node with one human gene below it (`ortholog_1`); the placement is trusted at
  E <= 1e-10 over >= 50% of the protein and >= 50% of the family model; and the closest human gene by similarity (an
  MMseqs2 reciprocal best hit, a transitive ortholog, or the best hit) is that same gene.
The name is the human gene's own: "IFT88: intraflagellar transport 88".

**Co-ortholog.** Orthologs when a gene was duplicated after the species split, in one lineage or both: every copy is
an ortholog of the other side's gene(s). Used when:
- several genes here are orthologs of one human gene (OMA many:1, or OMA's HOG; or several genes placed with one
  human gene on the tree): each carries its name with "(1 of N)" and the others are listed; or
- one gene here is an ortholog of several human genes (OMA 1:many, many:many, a HOG): named after what they share --
  their HGNC gene group when it is a family by descent (>= 60% of its members in one PANTHER family), else their
  shared PANTHER family; never after one member picked by score.

**Transitive ortholog.** Orthology inferred through a third species: this gene's ortholog in another species (OMA,
or an MMseqs2 reciprocal best hit) is itself an ortholog of a human gene (OMA, Ensembl Compara). Transitivity holds
cleanly only when no duplication lies along the chain, so it never names a gene on its own: it supports a name
("transitive ortholog through fly") and gives the closest human gene when OMA gives none (tier 4), with every human
gene a chain reaches kept together (a family when several).

**Paralog.** Two genes that split at a duplication, within one lineage. Paralogs often diverge in function, so a
paralog's name is never given. The word appears only as a warning: a `-like` name "may be a paralog"; a gene whose
PANTHER tree placement joins human genes at a duplication node (`paralog_family`) is not named by the tree; a
"paralog tie" (two human genes scoring within 95% of each other) blocks a single-gene `-like` name.

**Homolog, orthology not shown** (`-like`). Similar along its length (>= 80% of both proteins, E <= 1e-10) to one human
gene -- its best human hit, reciprocal or one-way -- with no other human gene within 95% of that score (or the tie
resolved by the one reciprocal hit). No method tested orthology, so the name says "-like": "BLTP1-like: bridge-like
lipid transfer protein family member 1-like". The closest human gene by similarity may be a paralog of the true
ortholog.

**Family homolog.** A member of a gene family; which member is not known. Used when the gene's PANTHER family match
covers >= 75% of the family's model (measured on the model, from the InterProScan JSON; >= 80% of it by protein
residues when there is no JSON) -- "<family> family member" -- or when a `-like` hit ties between human genes of one
HGNC group or PANTHER family.

**Domain homolog.** Shares a domain (or repeat) with other proteins, and nothing more is known: InterPro's best domain
or repeat match covering >= 50% of the domain's model (where the model coverage is known), named as UniProt names such
proteins: "C-type lectin-like domain-containing protein". Also a PANTHER family built of repeats, and a transposable-
element domain. It says what the protein contains, not which gene it is.

**None.** No step gave a name: "no hits", or "hits did not pass the naming tests" -- with what was found.

### 5.3 Gene statements -- what a gene page says first

The provenance line is complete but long. For a gene page, the same findings are written as a short series of
**typed statements**, each one sentence, in a fixed order; the page can show them above its evidence tables (collapsed),
with "see the data below". One moop file per type, `gene_statement.<type>.moop.tsv` (annotation type "Gene Statement",
source `MOOP-NAMING-<TYPE>`, Score = the place in the series), loaded by `setup_new_moopdb_and_load_data.sh`. The
Identity statements are split by the database of their accession, `gene_statement.identity.<kind>.moop.tsv` with source
`MOOP-NAMING-IDENTITY-<KIND>` (HGNC, HGNC-GROUP, INTERPRO, PANTHER, PFAM, ...), so each file's accessions link out to one
database. The source version of the naming sources (statements, name source, closest human gene) is one date, the HGNC
release the names were made with; `naming_versions.txt` beside the outputs lists every reference release and search
behind the run. On a reload the loader replaces a gene set's earlier rows of these types. Only
the statements that apply to a gene are written; nothing negative is said about expression or location.

| Order | Type | Says | Backed by (table on the page) |
|---|---|---|---|
| 1 | Identity | the relationship and the step: "Co-ortholog of human EPDR1; by PANTHER tree placement" | orthologs, closest human |
| 1 | No name | why no step named it: "No hits: ..." / "Hits did not pass the naming tests (found: ...)" | -- |
| 2 | Support | the methods that agree on the named human gene(s), and a shared PANTHER family: "Supported by 4 methods: MMseqs2 reciprocal best hit, transitive ortholog through fly, best human hit (partial alignment), PANTHER tree placement; the same PANTHER family (PTHR12460) as the human gene" (a transitive ortholog names up to 3 species, else "through 8 species") (Evidence_by_method; these methods share one signal, sequence similarity -- a report, not independent votes) | closest human, homologs |
| 3 | Copies | "One of 10 genes in this genome named after it (the others: ...)" | OMA |
| 4 | Alignment | how much of each protein aligns to the named human gene; for a family, domain or no name, the best human hit and its shape (fragment, fusion) | homologs |
| 5 | Domains | which of the human gene's Pfam domains this gene has (same-clan families count; a domain fragment does not): the named human gene -- of several, the one it aligns to best -- or, for a family, domain or no name, its best human hit ("Has 2 of NCAN's 5 Pfam domains; no Pfam match here to PF00008 EGF, ..."); a human gene with no Pfam domain in UniProt is said so. Congeria: 17,127 genes | InterProScan |
| 6 | Tree | where TreeGrafter places it, and whether that agrees with the name | PANTHER |
| 7 | Cautions | every reason for doubt in words: the evidence marks, other methods pointing to other human genes, few methods agreeing, a small part of the human protein, a one-way best hit, a transposon domain, a short protein | -- |
| 8 | Features | signal peptide, transmembrane helices, a DeepLoc location with its signal | SignalP, DeepTMHMM, DeepLoc |
| 9 | Expression | each own transcriptome that has it (a tissue, a stage) | transcriptome |

Example (Congeria COKUS1KC_0014560):

> Identity: Co-ortholog of human EPDR1; by PANTHER tree placement
> Support: Supported by 3 methods: transitive ortholog through choanoflagellate, best human hit (partial alignment), PANTHER tree placement; the same PANTHER family (PTHR10697) as the human gene
> Copies: One of 10 genes in this genome named after it (the others: COKUS1KC_0014561, ...)
> Alignment: Aligned over 90% of this protein and 77% of EPDR1 (partial)
> Domains: Has EPDR1's Pfam domain (PF00811 Ependymin)
> Tree: TreeGrafter places it with human EPDR1 (ortholog_1: joins at a speciation node, Eumetazoa ...); agrees with the name
> Features: Predicted: signal peptide (SignalP 6); location extracellular (DeepLoc 2: signal peptide)
> Expression: Expressed: a transcript matches it in its own transcriptome (100% identity over 100% of the protein)

## 6. Closest gene

**Definition.** The closest human gene is the human gene most closely related to this gene by
the strongest evidence available. It is an **ortholog only at tiers 1–2** (OMA's pairwise and
hierarchical orthology calls). Lower tiers report the closest relative found by other means — a
reciprocal best hit (tier 3), orthology through another species (tier 4), or similarity (tiers
5–7) — and are not orthology calls; the evidence column says which. Orthology is not transitive:
a tier-4 chain through a 1:many or many:1 link can reach a paralog, and its evidence shows the
link types. (Restricting tier 4 to 1:1 chains was tested on *C. kusceri*: of 2,046 genes with a
tier-4 closest human, 574 would have none at all, so tier 4 is kept and labelled.)

### 6.1 Closest human gene — tiers (the tier is the score in the database table)

| Tier | Evidence |
|---|---|
| 1 | OMA pairwise ortholog to HUMAN (supported, §5.2) |
| 2 | OMA HOG co-ortholog with HUMAN (only when the OMA run fixes a species tree; supported) |
| 3 | MMseqs2 reciprocal best hit to the Ensembl human proteome (normal filter, §3); not an orthology call |
| 4 | via another species: OMA ortholog in a reference species → that species' OMA human ortholog; or MMseqs2 RBH to an Ensembl species → its Ensembl Compara human ortholog |
| 5 | DIAMOND best hit to a human protein (Ensembl human, or a human Swiss-Prot entry; normal filter, §3) |
| 6 | DIAMOND Swiss-Prot hit in another species → its Ensembl gene → Ensembl Compara human ortholog |
| 7 | DIAMOND Swiss-Prot hit in another species → its PANTHER subfamily → the human Swiss-Prot genes in that subfamily |

**Orthology through another species (tiers 4 and 6) keeps the whole chain.** One chain is our
gene → one ortholog in another species → that ortholog's human orthologs. When that ortholog
predates a human duplication, the chain reaches every copy, and the gene is reported as their
family — never one copy picked by score (the same principle as for OMA's own co-ortholog sets).
Until 2026-09-30 only the best-scoring copy was kept (Congeria: 649 closest-human genes are now a
family); since the PANTHER tree step (step 5) needs a single closest gene, two tree names in a
3,235-protein Congeria sample now fall back: TTR (true transthyretin is vertebrate-specific; the
invertebrate proteins are 5-hydroxyisourate hydrolases) → "Uricase and transthyretin-related family
member", and GPR21 → its PANTHER family. A chain is ranked by the weaker of its two links.

The lowest (strongest) tier with any evidence is used. When tier 1 gives one human gene and
tier 2 (the HOG) gives several including it, the HOG's set is used (a family, §6.2). An
unsupported OMA ortholog is set aside (§5.2) and the next tier is used; its evidence text says
so.

**Ordering within a tier** (so the result never depends on the order files are read):
tiers 1–2 by relationship type (1:1, many:1, 1:many, many:many; Ensembl Compara
one2one, one2many, many2many likewise), then agreement (the gene's best MMseqs2/DIAMOND
bitscore to that human gene); tiers 3–7 by bitscore, then E-value, then agreement, then
relationship type. Remaining ties go to a gene with an HGNC record, then to ids.

### 6.2 One entry per gene: families

Orthology can place a gene with several human genes (1:many, many:many). Listing them all is
not useful (one *Acropora* gene had 42), and choosing one — by BLAST score or by a lower tier —
would claim a precision the evidence does not have (§5, step 3). Each gene therefore reports
**one** entry:

1. **One human gene:** that gene.
2. **Several human genes, or tier 7** (a PANTHER subfamily — family-level evidence even with
   a single human member): the family, as one entry — `<HGNC gene group> family` when the
   members share one that is a family by descent (§5, step 3), else `A/B-family` for up to three members (with `(N genes)` when not
   every member has a symbol), else `family of N genes`. The evidence reads `…, family of N`;
   no gene id is given.

### 6.3 Closest gene in other species

Any other species can be added per gene set (for example *Nematostella vectensis* for
corals, *Schmidtea mediterranea* for flatworms). Its closest gene is, strongest first (the
rank is the score in the database table):

1. the gene's OMA relationship to that species (its OMA code in this gene set's run), best
   type first (1:1, many:1, 1:many, many:many); several genes are reported as a family, as in §6.2;
2. an MMseqs2 reciprocal best hit to the species' proteome (normal filter, §3);
3. the DIAMOND best hit to the species' proteome (normal filter);
4. the best hit (lowest E-value) in an optional moop hits file, which carries no coverage.

The searches for ranks 2 and 3 are run by hand, once per gene set and partner, with
`scripts/closest_species_rbh.sh` and `scripts/closest_species_diamond.sh`: the same programs,
versions and settings as the human searches (MMseqs2 `easy-rbh` with coverage columns; DIAMOND
2.1.6 `--ultra-sensitive`, E ≤ 1e-5, `--max-target-seqs 5`, 17 columns). A partner protein's
description is its FASTA title, taken from the DIAMOND table. The pipeline cannot verify the
species of a partner proteome or a hits file.

**Naming species.** When the species is used for names (`use_for_names`, step 2), its OMA 1:1
or many:1 ortholog is used first; then a reciprocal best hit covering ≥ 80% of both proteins;
then, for another species only, the DIAMOND best hit, if that best hit is itself full-length (a
weaker full-length hit is never used instead, as for a human `-like` name, §5 step 6); then the
hits file. Another annotation of the same species (`same_species`) copies a name only from OMA,
a full-length reciprocal best hit, or the hits file: a best hit alone does not make two
proteins the same gene. The decision table records, for every gene, the name the steps would
give without the naming species (`Pipeline_name`, present in every run), so its effect can be judged before it
is trusted.

## 7. Outputs

| File | Content |
|---|---|
| `geneNames.tsv` | `ID MAINID GroupId Desc Note`; Desc is the name with its evidence tag; Note records the source, evidence type, ids and score of the name. A gene with no name keeps its own transcript id as name and description |
| `gene_name_source.<kind>.moop.tsv` | database annotation type "Gene Name Source" — the provenance of every name, one row per gene and isoform. Accession = what the name came from, description = why, in words (`Ortholog of human ALPHA (OMA, 1:1); ALPHA is its best human similarity hit; same PANTHER family (PTHR00001)`), score = the naming step (1–8; 0 for no name). One source per kind of accession link: HGNC gene, HGNC gene group, Ensembl gene, PANTHER family, InterPro domain, Pfam (transposable element), naming species (NCBI), human-curated, the gene set's own name — and `Gene name source: none` for genes with no name (accession `None`; the description says "None: no hits" or "None: hits did not pass the naming tests") |
| `closest_<species>.tsv` | per id: gene id, symbol, description, evidence |
| `closest_<species>[.ensembl\|.family].moop.tsv` | database annotation type "Closest Gene", one source per file so each has one link: human — `Closest human gene (HGNC)` (genenames.org), `Closest human gene (Ensembl, no HGNC record)` (Ensembl), `Closest human gene family` (no link); other species — `Closest <species> gene`, `Closest <species> gene family`. A row for the gene and each isoform; score = tier (human) or rank (other species: 1 OMA, 2 reciprocal best hit, 3 DIAMOND best hit, 4 hits file) |
| `gene_model_flags.tsv` | for people to read, not loaded: every gene whose best human hit has the shape of a fragment (≥ 80% of this protein on < 50% of the human one) or a fusion (≥ 80% of the human protein within < 50% of this one) — candidates for mender |
| `naming_decisions.tsv` | for people to read, not loaded anywhere: one row per gene — the name, the step that gave it and the full reason; the gene's best and second human hits and best PANTHER family with their scores **whatever the cutoffs**; and every step's own result (`NAMED`, `not used:` why, `passed over`/`skipped:` the rule that set it aside, `not reached;` what it would have said), plus the closest human gene, what each method points to (`Evidence_by_method`: OMA pairwise, OMA HOG, reciprocal best hit, via another species, best human hit, PANTHER tree placement and PANTHER family, each marked `+` if it includes the closest human gene, `C` if it points elsewhere, `X` if set aside — a report, not a vote, since the methods share one signal), and the closest gene in each other species (`Closest_<tag>`); and `Pipeline_name`, the name moop's own steps give with step 2 left out (no native name, no naming species) — the same as the name unless step 2 named the gene, so the columns never change between runs. With `--native`, also the gene set's own name. A `#` header records the run (date, script and git commit, command), the programs and data read (versions, file dates), the naming steps, every cutoff, the abbreviations and the columns — written from the code's own constants, so it always matches the run |
| `genes.gff` | attributes `closestHGNC`, `closestHumanSym`, `closestHumanDesc`, `closestHumanEvidence`; `closest<Tag>Id/Sym/Desc/Evidence` for other species |

## 8. Reproducibility

Identical inputs give byte-identical outputs: every choice between equal candidates is
made by the ordering rules above, never by the order in which data were read (verified by
running with different Perl hash seeds). Per-gene-set inputs (curated names, closest
species, a naming species) are declared in `geneset_config.yaml`, which is version-controlled
with the code. Reference data (HGNC, Ensembl Compara, UniProt, NCBI Taxonomy, InterPro entry
list, PANTHER model lengths, PANTHER TreeGrafter trees) are fetched and versioned by `update_reference_data.sh`.

Each naming rule is covered by an automated end-to-end test (`tests/naming_end_to_end.pl`: a
synthetic gene set of 35 genes, each made to hit one rule, asserting the exact name, tag,
provenance and closest genes, plus checks of the informative-name rules; 151 checks), run on every change to the code. Each rule was also
checked by breaking it on purpose (the threshold or the rule disabled) and confirming the
test fails.

## 9. Validation

**[TODO: rerun after the 2026-09-28 changes — fly benchmark with OMA (reference run) and
17-column DIAMOND; name-source table for *D. melanogaster*, *C. kusceri*, *M. capitata*,
*Miniopterus natalensis*.]** The figures below predate those changes.

**Benchmark: *Drosophila melanogaster*** (RefSeq annotation release FB_Rel_6.54, 13,986
genes), named as if it were a new organism, without its own names and without OMA. Each name
that carries a human gene symbol was compared with the fly gene's human orthologs in Ensembl
Compara release 113 (20,391 fly–human ortholog pairs; 6,983 fly genes with a human ortholog;
FlyBase ids mapped through the GFF cross-references).

| Rule | Names | Human gene is an ortholog | Human gene is a paralog of the ortholog | Fly gene has no human ortholog |
|---|---|---|---|---|
| previous: plain name from a reciprocal best hit | 843 | 73.8% | 5.3% | 20.9% |
| previous: `-like`, E-value-only hits | 1,721 | 39.5% | 18.1% | 42.4% |
| `-like`, full-length (§3), reciprocal best hits | 4,075 | 77.6% | 8.0% | 14.3% |

A reciprocal best hit, even full-length, identified the orthologous human gene in about
three quarters of cases; this is why such names carry `-like` and are not presented as
orthology. Requiring full-length alignment roughly doubled the fraction pointing at the true
ortholog relative to E-value-only hits. Caveat: Ensembl Compara is not a perfect reference
(some "no human ortholog" cases may be orthologs it missed).

**Paralog ties.** In *C. kusceri*, among 1,029 full-length `-like` names made before the tie
rule, another human gene scored within 5% of the named one in 416 (40%) — for example
UBE2D2/UBE2D4, ANO1/ANO2, CYP3A4/3A7/3A43. This is why ties are named for their HGNC group.
Detecting a tie requires the search to report more than one human gene: with all human
isoforms in the database, 5 targets per query showed the second human gene for 77.7% of
proteins that have one, 25 targets for 99.4% and 50 for 100% (2,000 random *C. kusceri*
proteins).

## 10. Limitations

- **Sequence only; no synteny.** Every method here rests on sequence similarity. Conserved gene order
  (synteny with a close, well-assembled relative) is the one truly independent way to separate
  orthologs from paralogs, but a synteny analysis (e.g. MCScan) for every organism is too costly to
  run routinely; with it, hidden paralogy would be caught where sequence cannot.

- **Fragmentary gene models.** A protein that is a fragment of a real gene aligns over its
  own length but covers under half of its human homolog, and fails the normal and full-length
  filters; likewise it covers under 75% of its PANTHER family's model. Such genes are left
  unnamed or named by a domain rather than named on partial evidence. In *C. kusceri* (BUSCO
  complete 84.1%, fragmented 5.3%; 27% of proteins under 100 residues), 3,496 genes with no
  closest human gene had a DIAMOND human hit covering under 50% of the human protein. The
  remedy is to repair the gene models.
- **Transposable elements.** Elements are recognised only by the Pfam domains in §5; an
  element without one, or with only a domain Pfam names after a domesticated gene, is named by
  the other steps (in *C. kusceri*, 42 copies named `ZMYM1` and 8 named `ZBED1`, each tagged
  with its copy count).
- **OMA support is one-sided.** A supported OMA call can still be wrong (hidden paralogy with
  similarity to the named gene); support only removes calls nothing else backs.
- **`-like` names point at one human paralog** when a gene predates a human-lineage
  duplication and one paralog scores more than 5% above the others. `-like` marks the name as
  similarity, not orthology; in the fly benchmark 8% of `-like` names pointed at a paralog of
  the true ortholog.
- **Names without OMA** are limited to full-length similarity (`-like`), PANTHER families,
  transposable-element classes and domains; plain names from BLAST alone are not given (§9).
