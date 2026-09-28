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
  4 full-length similarity to one human gene (`-like`); 5 a PANTHER family; 6 an InterPro
  domain or a transposable-element domain; otherwise no name. The step is the Score of the
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
| MMseqs2 reciprocal best hits (RBH) | `mmseqs easy-rbh` (commit 7e28409), defaults, against every protein of the Ensembl proteomes | closest human tiers 3–4; naming step 4; support of OMA names |
| DIAMOND hits | DIAMOND 2.1.6 `blastp --ultra-sensitive`, E ≤ 1e-5, 17-column output with query and subject coverage; against Ensembl human (all isoforms) and UniProtKB/Swiss-Prot **[TODO: production `--max-target-seqs`: 50 for human, 5 otherwise]** | closest human tiers 5–7; naming step 4; support of OMA names |
| Ensembl Compara homologies | same Ensembl release as the proteome hit | closest human tiers 4 and 6 |
| UniProtKB/Swiss-Prot cross-references | Ensembl gene, HGNC id and PANTHER family and subfamily per entry | closest human tiers 6–7; PANTHER family of each human gene (support of OMA names) |
| PANTHER | PANTHER 19.0 family HMMs, via InterProScan 5.78-109.0 (the gene set's own results); family model lengths from the same release's HMM file | naming step 5; support of OMA names |
| InterPro domains, repeats | the gene set's InterProScan results + InterPro `entry.list` (entry types and names) | naming step 6; repeat-built families (step 5) |
| Pfam transposable-element domains | the gene set's InterProScan results (Pfam, as shipped with InterProScan 5.78) | transposable-element names (§5, step 6) |
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

## 3. Hit filters and gene identity

A similarity hit (MMseqs2 or DIAMOND) is used only if the search reports **coverage** of
both proteins and a bitscore. An E-value alone says two proteins share something — often a
single domain — not that they are the same kind of protein; a DIAMOND table without coverage
columns is not used at all.

| Filter | E-value | Query and target coverage | Used for |
|---|---|---|---|
| **support** | ≤ 1e-5 | any | which human gene a protein is most similar to (§5 step 4); support of OMA names (§5.2) |
| **normal** | ≤ 1e-10 | each ≥ 50% | evidence for the closest human gene (§6) |
| **full-length** | ≤ 1e-10 | each ≥ 80% | a `-like` name (§5, step 4) |

**Human genes are compared by identity, never by name text.** Every human hit is resolved to
its HGNC record — by HGNC id, then Ensembl gene id, then UniProt accession, following
withdrawn ids to their replacement. Ensembl proteins state their HGNC gene in their
description (`[Source:HGNC Symbol;Acc:HGNC:9455]`), including genes on alternate haplotypes
and patches whose own Ensembl id HGNC does not list; that accession is used when the Ensembl
id is not found. A human gene with no HGNC record is keyed by its Ensembl gene id. All
isoforms of one human gene are one entry; for each human gene the protein's best hit (by
bitscore, then E-value), its best full-length hit, and whether any hit was a reciprocal best
hit are kept.

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
and other locus ids (`AGAP001331-PA-RELATED`), `EG:114D9.1 PROTEIN-RELATED`. None of these
patterns matches an HGNC approved symbol or name (checked September 2026).

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
a trailing `PRECURSOR` or full stop. Native names (§5, step 2) are kept exactly as the source
gives them.

## 5. Naming — the first step that yields a name wins

The principle: **a plain name only from a supported orthology call; `-like` for full-length
similarity to one human gene; a family or domain name when the evidence stops there;
otherwise no name.**

| Step | Source | Condition | Name form | Tag |
|---|---|---|---|---|
| 1 | **Human-curated names** | a curator's file lists the gene | exactly as given — the only step not checked for informativeness | `TAS` |
| 2 | **Native name** | RefSeq/Ensembl gene sets | the source's own name, kept unless uninformative | `SRC` |
| 2 | **Naming species** (optional, per gene set) | its OMA 1:1 or many:1 ortholog, else its hits file; informative | `NAME` for another annotation of the same species; otherwise `NAME-like (label)` | `SRC` / `ISO` / `ISS` |
| 3 | **OMA orthology to human** | closest human gene from OMA (§6, tier 1 or 2), supported (§5.2) | see below | `ISO` |
| 6* | **Transposable element** | a transposable-element Pfam domain (see below) | `<class> transposase domain-containing protein` | `ISM\|te` |
| 4 | **Full-length human similarity** | see below | `SYMBOL-like: approved name-like`, or `<HGNC group> family member` | `ISS` |
| 5 | **PANTHER family** | the match covers ≥ 80% of the family's model; informative | `<family> family member`; a repeat-built family: `<repeat>-containing protein` | `ISM\|pthr`, `ISM\|rpt` |
| 6 | **InterPro domain** | the gene's best InterPro *Domain* or *Repeat* entry, informative | `<domain> domain-containing protein` | `ISM\|ipr` |
| 7 | — | nothing above | `None` (the gene keeps its own transcript id as name and description) | — |

\* Transposable-element names are applied after step 3 and before step 4, and are recorded
as step 6 (a domain-based name).

**Step 3 — OMA orthology.**
- One human gene, 1:1: `SYMBOL: approved name`.
- One human gene shared by several genes of this gene set (many:1, a lineage-specific
  duplication): every copy is an ortholog of that gene and carries the same name. The number of
  copies is in the tag (`4to1`) and the provenance, not in the name.
- Several human genes (1:many, many:many — typically duplications in the human lineage, such
  as the vertebrate genome duplications): named after the most specific HGNC gene group all
  members share (`EPH receptors family member`), with **no symbol** — a symbol is what users
  search as a gene's identity, and a family has none. **No member is singled out.** A gene that
  predates a duplication is equally related to every copy; the best BLAST score only
  identifies the slowest-evolving copy. If the members share no HGNC group, the gene goes
  directly to step 5 — not to step 4, which would pick a member by score after all. HGNC
  groups are sometimes defined by a shared domain (`EF-hand domain containing`); the name
  still reads `… family member`.
- **Pairwise 1:1, HOG several.** OMA's pairwise file can pair a gene 1:1 with one human copy of
  a vertebrate duplication (HDAC1 of HDAC1/HDAC2) while OMA's HOG, computed on the fixed
  species tree, makes it co-ortholog of every copy. The HOG is then the more complete call: the
  gene is treated as a co-ortholog family, as above.
- **Supported OMA calls only** (§5.2). An OMA human ortholog that no other evidence backs is
  set aside; the next step names the gene, and its tag carries `omaX`.

**Transposable elements.** A gene with the catalytic or signature domain of a transposable
element (a Pfam match InterProScan reports, i.e. past Pfam's own threshold; table below) is named
for its element class. This comes before
step 4 and 5, which would otherwise name it after a human gene domesticated from such an
element (HARBI1, ZBED1). It replaces an OMA name only when OMA pairs ≥ 5 genes of this gene
set with the same human gene (many:1): a transposon family next to one domesticated human
gene. A 1:1 OMA ortholog carrying such a domain keeps its OMA name and is flagged `te`.

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

**Step 4 — full-length human similarity.** Human genes are compared by identity and
bitscore (§3), over every hit with E ≤ 1e-10:
1. The human gene the protein is **most** similar to, at any coverage, must itself have a
   full-length hit (both coverages ≥ 80%). A weaker full-length hit to another gene never
   names the protein (a WD40 protein hitting WDR90 at 562 bits over part of WDR90 is not
   named `CFAP52-like` from a 119-bit full-length hit).
2. If another human gene scores within 5% of its bitscore, the paralogs are a **tie**. A
   reciprocal best hit (MMseqs2) to exactly one of the tied genes, itself full-length, decides
   (tag `tie-rbh`); otherwise the tied genes' shared HGNC group names the gene
   (`Heat shock 70kDa proteins family member`, tag `tie-grp`); otherwise step 4 gives no name.
3. The name is **always** `-like`, reciprocal or not: sequence similarity, even reciprocal, is
   not an orthology call (§9). The symbol is the human gene's HGNC symbol, or none; it is
   never taken from another gene.

**Step 5 — PANTHER family.** A family names the gene only when the gene's PANTHER match covers
≥ 80% of the family's HMM (model coverage: the protein residues in the family's match
regions, merged, over the model length). Protein coverage is not required: a multidomain
protein that contains the whole family model is a member. Below the threshold the match is
usually one shared domain (a SET domain matching the KMT5A family at 38% of its model), which
step 6 names honestly. Among qualifying families the lowest E-value wins. The name is
InterPro's curated name when the family is integrated into an InterPro *Family* entry
(`BONUS, ISOFORM C-RELATED` → `TRIM45/56/19-like`), else PANTHER's own name, cleaned (§4).
**Repeat-built families:** when repeat units (InterPro *Repeat* entries, and the C2H2 zinc
finger, which InterPro types as a *Domain*) cover ≥ 25% of the family match, model coverage
says nothing — any protein with such repeats fills the model (a mollusc C2H2 protein fills
`KRAB AND ZINC FINGER DOMAIN-CONTAINING`, though KRAB is a tetrapod innovation). The gene is
named for the repeat covering most of the match instead (`Zinc finger C2H2-type
domain-containing protein`, tag `rpt`).

**Step 6 — InterPro domain.** The name states the one thing known: a domain. This is
UniProt's convention for such proteins (`SET domain-containing protein`). Only InterPro
entries of type *Domain* or *Repeat* are used (families are step 5; homologous superfamilies
are too broad; sites are not domains), and not entries of unknown function (DUF, UPF,
uncharacterised). Every match InterProScan reports has already passed its member database's
curated threshold (Pfam's per-family gathering thresholds, SMART, CDD, PROSITE profiles); no
E-value floor is added, because an E-value depends on domain length and a single floor would
remove short domains (zinc fingers, repeats) however real — the reason Pfam uses per-family
bit-score thresholds. PROSITE patterns are not used (a short regular expression with no score
or threshold). **[TODO: with the InterProScan JSON, require a match to cover enough of its
domain model (hmmStart/hmmEnd/hmmLength, hmmBounds).]** The gene's best match is chosen by the
lowest E-value, then entries without one (PROSITE profiles), then by accession. The name is InterPro's curated entry name, with
"comma + space" removed (`Zinc finger, RING-type` → `Zinc finger RING-type domain-containing
protein`; `1,2-lyase` is untouched) and `-containing protein` appended when the name already
ends in domain or repeat. When the gene has a human homolog that could not name it (partial),
the provenance says so and the tag carries `sim~`.

**Hits to other species never name a gene.** A transferred name may belong to a
lineage-specific paralog (`solute carrier family 37 member 4a` in fish), which cannot be
detected and would be wrong in the new organism. Other-species hits remain available as
homolog tables in the database.

### 5.1 Evidence tags

Every name ends in a tag, modelled on the Gene Ontology evidence codes, so a reader sees the
kind of evidence next to the name: `ALPHA: alpha synthase [ISO|1to1|sim+|pthr=]`. The tag
contains no colon. The full reasoning is in the Gene Name Source table (§7).

| Part | Meaning |
|---|---|
| `ISO` | inferred from sequence orthology (OMA) |
| `ISS` | inferred from sequence similarity (`-like`) |
| `ISM` | inferred from a sequence model (PANTHER family, InterPro domain, TE domain) |
| `TAS` | human-curated name |
| `SRC` | the gene set's own name, or another annotation of the same species |
| `1to1`, `Nto1`, `mto1`, `fam` | OMA relationship: one-to-one; N genes of this gene set share the human gene; many-to-one; co-ortholog family |
| `rbh`, `bh` | the `-like` hit is a reciprocal best hit, or a best hit |
| `tie-rbh`, `tie-grp` | a paralog tie decided by a reciprocal best hit, or named for the group |
| `pthr`, `rpt`, `ipr`, `te` | PANTHER family; repeat-built family; InterPro domain; transposable element |
| `sim+`, `sim~`, `sim-` | the named human gene is the protein's best human hit; a hit but not the best; no hit (E ≤ 1e-5). On an `ISM` name, `sim~`: the gene has only a partial human homolog |
| `pthr=`, `pthrX` | the gene's PANTHER family is the same as / different from the named human gene's |
| `hog` | OMA's HOG agrees with the call |
| `te` (on `ISO`) | the ortholog carries a transposable-element domain |
| `omaX` | an OMA human ortholog was set aside for lack of support (§5.2) |

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

## 6. Closest gene

### 6.1 Closest human gene — tiers (the tier is the score in the database table)

| Tier | Evidence |
|---|---|
| 1 | OMA pairwise ortholog to HUMAN (supported, §5.2) |
| 2 | OMA HOG co-ortholog with HUMAN (only when the OMA run fixes a species tree; supported) |
| 3 | MMseqs2 reciprocal best hit to the Ensembl human proteome (normal filter, §3) |
| 4 | via another species: OMA ortholog in a reference species → that species' OMA human ortholog; or MMseqs2 RBH to an Ensembl species → its Ensembl Compara human ortholog |
| 5 | DIAMOND best hit to a human protein (Ensembl human, or a human Swiss-Prot entry; normal filter, §3) |
| 6 | DIAMOND Swiss-Prot hit in another species → its Ensembl gene → Ensembl Compara human ortholog |
| 7 | DIAMOND Swiss-Prot hit in another species → its PANTHER subfamily → the human Swiss-Prot genes in that subfamily |

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
   members share one, else `A/B-family` for up to three members (with `(N genes)` when not
   every member has a symbol), else `family of N genes`. The evidence reads `…, family of N`;
   no gene id is given.

### 6.3 Closest gene in other species

Any other species can be added per gene set (for example *Nematostella vectensis* for
corals). The species is identified by its OMA code; its closest gene is:

1. the gene's OMA relationship to that species, best type first (1:1, many:1, 1:many,
   many:many); several genes are reported as a family, as in §6.2;
2. if OMA gives nothing, the best hit (lowest E-value) in the optional hits file.

The hits file is supplied by the user; the pipeline cannot verify its species. Scores in
the database table: 1 = OMA, 2 = hits file.

## 7. Outputs

| File | Content |
|---|---|
| `geneNames.tsv` | `ID MAINID GroupId Desc Note`; Desc is the name with its evidence tag; Note records the source, evidence type, ids and score of the name. A gene with no name keeps its own transcript id as name and description |
| `gene_name_source.<kind>.moop.tsv` | database annotation type "Gene Name Source" — the provenance of every name, one row per gene and isoform. Accession = what the name came from, description = why, in words (`Ortholog of human ALPHA (OMA, 1:1); ALPHA is its best human similarity hit; same PANTHER family (PTHR00001)`), score = the naming step (1–6). One source per kind of accession link: HGNC gene, HGNC gene group, Ensembl gene, PANTHER family, InterPro domain, Pfam (transposable element), naming species (NCBI), human-curated, the gene set's own name |
| `closest_<species>.tsv` | per id: gene id, symbol, description, evidence |
| `closest_<species>[.ensembl\|.family].moop.tsv` | database annotation type "Closest Gene", one source per file so each has one link: human — `Closest human gene (HGNC)` (genenames.org), `Closest human gene (Ensembl, no HGNC record)` (Ensembl), `Closest human gene family` (no link); other species — `Closest <species> gene`, `Closest <species> gene family`. A row for the gene and each isoform; score = tier (human) or 1 = OMA, 2 = hits file |
| `genes.gff` | attributes `closestHGNC`, `closestHumanSym`, `closestHumanDesc`, `closestHumanEvidence`; `closest<Tag>Id/Sym/Desc/Evidence` for other species |

## 8. Reproducibility

Identical inputs give byte-identical outputs: every choice between equal candidates is
made by the ordering rules above, never by the order in which data were read (verified by
running with different Perl hash seeds). Per-gene-set inputs (curated names, closest
species, a naming species) are declared in `geneset_config.yaml`, which is version-controlled
with the code. Reference data (HGNC, Ensembl Compara, UniProt, NCBI Taxonomy, InterPro entry
list, PANTHER model lengths) are fetched and versioned by `update_reference_data.sh`.

Each naming rule is covered by an automated end-to-end test (`tests/naming_end_to_end.pl`: a
synthetic gene set of 25 genes, each made to hit one rule, asserting the exact name, tag,
provenance and closest genes, plus checks of the informative-name rules; 68 checks), run on every change to the code. Each rule was also
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

- **Fragmentary gene models.** A protein that is a fragment of a real gene aligns over its
  own length but covers under half of its human homolog, and fails the normal and full-length
  filters; likewise it covers under 80% of its PANTHER family's model. Such genes are left
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
