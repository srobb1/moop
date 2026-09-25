# Gene naming and closest-gene assignment — Methods

Draft for publication. Describes `assign_gene_names_v2.pl` as of 2026-09-25 (branch
`naming-v2`). Every threshold below is quoted from the code; if the code changes, change
this file with it. Items marked **[TODO]** are facts this document cannot state from the
code alone.

---

## 1. Overview

Each gene receives two independent outputs:

1. **A gene name** — one description (and symbol where available) chosen from a fixed
   order of evidence (§5). A gene is named once; all of its isoforms carry the same name.
2. **A closest gene in human, and optionally in other chosen species** (§6) — reported
   whatever step named the gene, with the evidence and a numeric tier.

The two are deliberately separate: a gene may be named from its RefSeq record or a
curator's file and still report the human gene it is closest to.

**Unit of decision.** All evidence from all isoforms of a gene is pooled and one decision is
made per gene. The isoform whose evidence was used is recorded as the gene's main id
(`MAINID`); when the isoform file nominates a selected isoform, that one is used instead.

## 2. Evidence sources

| Source | Method | Used for |
|---|---|---|
| OMA pairwise orthologs | OMA standalone 2.7.0, 10-species template (§2.1) | closest human tier 1; naming step 3; closest other species |
| OMA hierarchical orthologous groups (HOGs) | same run; only when `parameters.drw` fixes a species tree | closest human tier 2 |
| OMA orthologs of reference species | same run | closest human tier 4 |
| MMseqs2 reciprocal best hits (RBH) | `easy-rbh` against Ensembl proteomes **[TODO: parameters]** | closest human tiers 3–4; naming step 4 |
| DIAMOND best hits | `blastp`, best hit per query, against UniProtKB/Swiss-Prot and Ensembl proteomes **[TODO: production parameters]** | closest human tiers 5–7; naming step 4 (only with coverage columns) |
| Ensembl Compara homologies | same Ensembl release as the proteome hit | closest human tiers 4 and 6 |
| UniProtKB/Swiss-Prot cross-references | Ensembl gene and PANTHER subfamily per entry | closest human tiers 6–7 |
| PANTHER | via InterProScan **[TODO: version]** | naming step 5 |
| InterPro domains | the gene set's InterProScan results + InterPro `entry.list` (entry types and names) | naming step 6 |
| HGNC | complete set + withdrawn ids | all human symbols and approved names |
| NCBI Taxonomy | GenBank common names | species of other-species hits (evidence only; no name comes from another species) |

Reference versions in the current build: HGNC downloaded 2026-09-24; UniProt release
2026_03; NCBI Taxonomy 2026-09-24; InterPro release 110.0; Ensembl release 113 (human proteome and Compara;
release 116 for *Lepisosteus oculatus*). Every output table records the versions it used
in its `## Annotation Source Version` header.

### 2.1 OMA template

Gene sets are run through OMA standalone 2.7.0 together with ten precomputed genomes:
*Branchiostoma floridae* (BRAFL; RefSeq GCF_000003815.2), *Callorhinchus milii* (CALMI;
Ensembl Vertebrates 51), *Capitella teleta* (CAPTE; Ensembl Metazoa 27), *Drosophila
melanogaster* (DROME; Ensembl 106), *Homo sapiens* (HUMAN; Ensembl 102, GRCh38),
*Lepisosteus oculatus* (LEPOC; Ensembl 74), *Lottia gigantea* (LOTGI; Sbi1_4 filtered
models), *Monosiga brevicollis* (MONBE; v1.0), *Mus musculus* (MOUSE; Ensembl 104, GRCm39)
and *Nematostella vectensis* (NEMVE; RefSeq GCF_932526225.1, annotation release 101).

A gene set that **is** one of these genomes (≥ 50% of its proteins identical in sequence to
one of them) is not run again — that would place the same genome in OMA twice. Its
orthologs are taken from the template's own reference run, with every OMA id mapped back to
the gene set's protein ids by identical sequence.

Note the human release in OMA (Ensembl 102) differs from the human proteome used for
BLAST-style searches (Ensembl 113). Both resolve to HGNC through the current HGNC set, so
the reported human gene is always a current HGNC record where one exists.

## 3. Hit filters

A similarity hit (MMseqs2 or DIAMOND) is used only if the search reports **coverage** of
both proteins. An E-value alone says two proteins share something — often a single domain —
not that they are the same kind of protein; a DIAMOND table without coverage columns is not
used at all.

| Filter | E-value | Query and target coverage | Used for |
|---|---|---|---|
| **normal** | ≤ 1e-10 | each ≥ 50% | evidence for the closest human gene (§6) |
| **full-length** | ≤ 1e-10 | each ≥ 80% | a gene name (§5, step 4) |

## 4. Informative names

A candidate name is used only if it is informative. A description is **uninformative** if,
after cleaning, it is empty, is `None`, or matches any of:

`uncharacterized protein|LOC…` · `hypothetical protein` · `predicted protein` ·
`unnamed protein product` · `unknown protein|function` · `predicted gene[,] N` ·
`novel protein|gene|transcript` · `dubious open reading frame` · `unlikely to encode a
functional protein` · exactly `putative protein` · exactly `protein` · zebrafish clone names
(`si:`, `zgc:`, `wu:`)

— or if it only repeats the hit's own id or symbol (`CG12345`, `F13H8.2 protein`), is itself a
placeholder symbol, has no letters or digits (PANTHER's `-`), or is `protein` followed by
another species' locus id (`PROTEIN CBG26694`, `PROTEIN CBR-CLEC-78`, `PROTEIN FAM167A`,
`EG:114D9.1 PROTEIN-RELATED`). None of these patterns matches an HGNC approved name.

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
list of names.

## 5. Naming — the first step that yields a name wins

The principle: **a plain name only from an orthology call; `-like` for full-length
similarity; a family name when the evidence stops at the family; otherwise no name.** A
missing name is preferred to a wrong one.

| Step | Source | Condition | Name form |
|---|---|---|---|
| 1 | **Human-curated names** | a curator's file lists the gene | exactly as given — the only step not checked for informativeness |
| 2 | **Native name** | RefSeq/Ensembl gene sets | the source's own name, kept unless uninformative |
| 2 | **Naming species** (optional, per gene set, enabled after review) | its OMA 1:1 or many:1 ortholog, else its hits file; informative | `NAME` for another annotation of the same species; otherwise `NAME-like (label)`, the label set per gene set (e.g. `sea anemone`) |
| 3 | **OMA orthology to human** | closest human gene from OMA (§6, tier 1 or 2) | see below |
| 4 | **Full-length human similarity** | best human hit passing the full-length filter (§3), informative | `SYMBOL-like: approved name-like` |
| 5 | **PANTHER family** | the gene's PANTHER family, informative | `<family> family member`, as PANTHER reports it |
| 6 | **InterPro domain** | the gene's best InterPro *Domain* or *Repeat* entry, informative | `<domain> domain-containing protein` |
| 7 | — | nothing above | `None` (the gene keeps its own transcript id as name and description) |

**Step 3 — OMA orthology.**
- One human gene, 1:1: `SYMBOL: approved name`.
- One human gene shared by several genes of this gene set (many:1, a lineage-specific
  duplication): every copy is an ortholog of that gene and carries the same name. The number of
  copies is recorded in the name's provenance (§7), not in the name.
- Several human genes (1:many, many:many — typically a duplication in the human lineage, such
  as the vertebrate genome duplications): named after the most specific HGNC gene group all
  members share (`EPH receptors family member`; `Solute carrier family 5 member`), with **no
  symbol** — a symbol is what users search as a gene's identity, and a family has none.
  **No member is singled out.** A gene that predates a duplication is equally related to every
  copy; the best BLAST score only identifies the slowest-evolving copy. If the members share
  no HGNC group, the gene goes directly to step 5 — not to step 4, which would pick a member
  by score after all.

**Step 4 — full-length human similarity.** Candidates are hits to human proteins (MMseqs2
reciprocal best hits, DIAMOND best hits against Ensembl human and human Swiss-Prot entries)
that pass the full-length filter and name an informative gene; the best by bitscore, then
E-value, is used. The name is **always** `-like`, reciprocal or not: sequence similarity,
even reciprocal, is not an orthology call (§9). The symbol is the human gene's HGNC symbol,
or none; it is never taken from another gene.

**Step 6 — InterPro domain.** For a gene with no homolog, ortholog or family evidence, the
name states the one thing known: a domain. This is UniProt's convention for such proteins
(`SET domain-containing protein`). Only InterPro entries of type *Domain* or *Repeat* are used
(families are step 5; homologous superfamilies are too broad; sites are not domains), and not
entries of unknown function (DUF, UPF, uncharacterised). The gene's best match is chosen by
the lowest E-value among member databases that report one (Pfam, SMART, CDD, ...), then
entries without one (PROSITE profiles), then by accession. The name is InterPro's curated entry
name, with "comma + space" removed (`Zinc finger, RING-type` → `Zinc finger RING-type
domain-containing protein`; `1,2-lyase` is untouched) and `-containing protein` appended when
the name already ends in domain or repeat. A frequent and useful case: transposon-encoded
proteins are named by their transposase or integrase domain
(`Harbinger transposase-derived nuclease domain-containing protein`).

**Hits to other species never name a gene.** A transferred name may belong to a
lineage-specific paralog (`solute carrier family 37 member 4a` in fish), which cannot be
detected and would be wrong in the new organism. Other-species hits remain available as
homolog tables in the database.

## 6. Closest gene

### 6.1 Closest human gene — tiers (the tier is the score in the database table)

| Tier | Evidence |
|---|---|
| 1 | OMA pairwise ortholog to HUMAN |
| 2 | OMA HOG co-ortholog with HUMAN (only when the OMA run fixes a species tree) |
| 3 | MMseqs2 reciprocal best hit to the Ensembl human proteome (normal filter, §3) |
| 4 | via another species: OMA ortholog in a reference species → that species' OMA human ortholog; or MMseqs2 RBH to an Ensembl species → its Ensembl Compara human ortholog |
| 5 | DIAMOND best hit to a human protein (Ensembl human, or a human Swiss-Prot entry; normal filter, §3) |
| 6 | DIAMOND Swiss-Prot hit in another species → its Ensembl gene → Ensembl Compara human ortholog |
| 7 | DIAMOND Swiss-Prot hit in another species → its PANTHER subfamily → the human Swiss-Prot genes in that subfamily |

The lowest (strongest) tier with any evidence is used.

**Human gene identity.** Every human hit is resolved to its HGNC record — by HGNC id, then
Ensembl gene id, then UniProt accession, following withdrawn ids to their replacement.
Ensembl proteins state their HGNC gene in their description (`[Source:HGNC Symbol;Acc:HGNC:9455]`),
including genes on alternate haplotypes and patches whose own Ensembl id HGNC does not
list; that accession is used when the Ensembl id is not found. A human gene with no HGNC
record is reported by its Ensembl gene id, without a symbol.

**Ordering within a tier** (so the result never depends on the order files are read):
tiers 1–2 by relationship type (1:1, many:1, 1:many, many:many; Ensembl Compara
one2one, one2many, many2many likewise), then agreement (the gene's best MMseqs2/DIAMOND
bitscore to that human gene); tiers 3–7 by bitscore, then E-value, then agreement, then
relationship type. Remaining ties go to a gene with an
HGNC record, then to ids.

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
| `geneNames.tsv` | `ID MAINID GroupId Desc Note`; Note records the source, evidence type, ids and score of the name. A gene with no name keeps its own transcript id as name and description |
| `gene_name_source.<kind>.moop.tsv` | database annotation type "Gene Name Source" — the provenance of every name, one row per gene and isoform. Accession = what the name came from, description = why, in words (`Similar to human CCND2 along its length: reciprocal best hit, 98% of this protein and 99% of CCND2 aligned, E=2e-95 (MMseqs2)`), score = the naming step (1–6). One source per kind of accession link: HGNC gene, HGNC gene group, Ensembl gene, PANTHER family, InterPro domain, naming species (NCBI), human-curated, the gene set's own name |
| `closest_<species>.tsv` | per id: gene id, symbol, description, evidence |
| `closest_<species>[.ensembl\|.family].moop.tsv` | database annotation type "Closest Gene", one source per file so each has one link: human — `Closest human gene (HGNC)` (genenames.org), `Closest human gene (Ensembl, no HGNC record)` (Ensembl), `Closest human gene family` (no link); other species — `Closest <species> gene`, `Closest <species> gene family`. A row for the gene and each isoform; score = tier (human) or 1 = OMA, 2 = hits file |
| `genes.gff` | attributes `closestHGNC`, `closestHumanSym`, `closestHumanDesc`, `closestHumanEvidence`; `closest<Tag>Id/Sym/Desc/Evidence` for other species |

## 8. Reproducibility

Identical inputs give byte-identical outputs: every choice between equal candidates is
made by the ordering rules above, never by the order in which data were read (verified by
running with different Perl hash seeds). Per-gene-set inputs (curated names, closest
species, a naming species) are declared in `geneset_config.yaml`, which is version-controlled
with the code.
Each naming rule is covered by an automated end-to-end test (`tests/naming_end_to_end.pl`: a
synthetic gene set with one gene per rule, asserting the exact name, provenance and closest
genes), run on every change to the code.

## 9. Validation

**Benchmark: *Drosophila melanogaster*** (RefSeq annotation release FB_Rel_6.54, 13,986
genes), named as if it were a new organism, without its own names and without OMA. Each name
that carries a human gene symbol was compared with the fly gene's human orthologs in Ensembl
Compara release 113 (20,391 fly–human ortholog pairs; 6,983 fly genes with a human ortholog;
FlyBase ids mapped through the GFF cross-references).

| Rule | Names | Human gene is an ortholog | Human gene is a paralog of the ortholog | Fly gene has no human ortholog |
|---|---|---|---|---|
| previous: plain name from a reciprocal best hit | 843 | 73.8% | 5.3% | 20.9% |
| previous: `-like`, E-value-only hits | 1,721 | 39.5% | 18.1% | 42.4% |
| **current: `-like`, full-length (§3), reciprocal best hits** | **4,075** | **77.6%** | **8.0%** | **14.3%** |

A reciprocal best hit, even full-length, identified the orthologous human gene in about
three quarters of cases; this is why such names carry `-like` and are not presented as
orthology. Requiring full-length alignment roughly doubled the fraction pointing at the true
ortholog relative to E-value-only hits. Caveats: Ensembl Compara is not a perfect reference
(some "no human ortholog" cases may be orthologs it missed); the fly lacked OMA results at
the time, so step 3 is not yet benchmarked; and DIAMOND without coverage columns contributed
no names. **[TODO: rerun with OMA and 17-column DIAMOND.]**

**Name sources after the simplification** (per gene; the three gene sets used in testing):

| | *D. melanogaster* | *Congeria kusceri* | *Montipora capitata* |
|---|---|---|---|
| OMA ortholog, plain | – | 11.9% | – |
| OMA family name | – | 3.5% | – |
| full-length human `-like` | 29.1% | 0.8% | 9.3% |
| PANTHER family | 48.7% | 29.5% | 41.9% |
| InterPro domain | 3.7% | 7.2% | 9.1% |
| None | 18.5% | 47.2% | 39.7% |

(*Congeria* and *Montipora* DIAMOND results lacked coverage columns at the time, so their
full-length names come from MMseqs2 alone.)

## 10. Limitations

- **Transposable elements.** The pipeline does not annotate transposons; a transposon protein
  with no other evidence is named by its transposase domain (step 6), which is accurate. A gene set's copies of
  an active element can therefore be named after the human gene domesticated from the same
  element family (in *Congeria*, 42 names such as `HARBI1: harbinger transposase derived 1`).
  Their descriptions say "transposase derived" / "transposable element derived"; no rule
  attempts to detect transposons from protein domains, which would be unreliable.
- **`-like` names point at one human paralog.** When a gene predates a human-lineage
  duplication, the best full-length hit names the slowest-evolving copy (`CCND2-like` for a
  gene equally related to CCND1-3). `-like` marks the name as similarity, not orthology; in the
  fly benchmark 8% of `-like` names pointed at a paralog of the true ortholog. Resolving this
  needs the next-best human gene for each hit (planned from the reciprocal-hit search).
- **Names without OMA** are limited to full-length similarity (`-like`), PANTHER families and domains;
  plain names from BLAST alone are not given (§9).
