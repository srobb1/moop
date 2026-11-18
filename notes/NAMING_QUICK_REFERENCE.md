# Gene naming -- quick reference (print me)

Summary of [GENE_NAMING_METHODS.md](GENE_NAMING_METHODS.md) (as of 2026-11-17, checked against `assign_gene_names_v2.pl`).
The Methods file has the reasons and the numbers; this page has the rules.

**Two outputs per gene, decided separately:**
**STEPS** decide the gene's **name** (1-9, first step that gives a name wins).
**TIERS** decide the gene's **closest human gene** (1-7, strongest tier with evidence wins).
Exception: a gene named after a human gene has that gene as its closest human gene.

**Hit words used below.** *Full-length* = E ≤ 1e-10 and ≥ 80% of both proteins aligned. *Normal* = E ≤ 1e-10 and
≥ 50% of both proteins. *Top hit* = highest bitscore. *Tie* = another gene scores within 5% of the top hit. No
identity cutoff anywhere.

## A. Naming steps (the name)

| Step | Evidence | Name looks like | Tag starts |
|---|---|---|---|
| 1 | a curator's name (file); never checked | as given | `TAS` |
| 2 | the gene set's own RefSeq/Ensembl name, if informative; or the config's naming species (`use_for_names`): its OMA 1:1/many:1 ortholog, else full-length RBH, else (not `same_species`) full-length DIAMOND top hit, else its hits file | see "Step 2 names" below | `SRC` / `ISO` / `ISS`, then the species tag (`smed`) |
| 3 | **OMA ortholog of a human gene** (supported) | `ALPHA: alpha synthase`; copies `(1 of 4)`; several human genes: `<HGNC group> family member`, else `<PANTHER family> family member` | `ISO` |
| 4 | transposable-element Pfam domain | `PIF/Harbinger transposase domain-containing protein` | `ISM\|te` |
| 5 | PANTHER tree places the gene with exactly one human gene, which is also its closest human by similarity (tiers 3-5) | `SYMBOL: name` | `ISO\|tree` |
| 6 | top human hit full-length, no tie, tree does not disagree | `SYMBOL-like: name-like`; a tie: `<group> family member` | `ISS` |
| 7 | top Swiss-Prot hit is another species' protein, full-length, no tie; the gene has **no** full-length human hit and no human hit scores higher | `Protein name-like (Species)`, no symbol | `ISS\|bh\|sp` |
| 8 | PANTHER family, match covers ≥ 75% of the family model (≥ 80% by protein residues if no InterProScan JSON) | `<family> family member`; repeat-built family: `<repeat> domain-containing protein` | `ISM\|pthr`, `ISM\|rpt` |
| 9 | InterPro domain or repeat, match covers ≥ 50% of the domain model (where known: not SMART, CDD, PROSITE) | `<domain> domain-containing protein` | `ISM\|ipr` |
| - | nothing above | `None` in geneNames.tsv: **no name and no description** in the GFF and database; reason "no hits" or "hits did not pass the naming tests" | - |

**Rules that change the order:**
- **Transposon family:** ≥ 5 OMA many:1 copies of one human gene that carry a TE domain → step 4 runs *before* step 3.
- **OMA family not named:** a co-ortholog family that step 3 cannot name (no family-by-descent HGNC group, no PANTHER
  family it fully belongs to) **skips steps 5, 6 and 7** and goes to step 8/9: they would pick one member by score.
- **Step 2 passed over** (not `same_species`): the partner's name has no symbol and the gene's own OMA human ortholog is
  1:1 with `sim+` and `pthr+` → step 3 names it.
- **Read-through:** a gene whose best hits to both halves of a human read-through lie on different stretches of its
  protein (a possible fused model) is not named after either.

**Step 2 names** (naming species, config `closest_species`):

| Config | Name | Example |
|---|---|---|
| `same_species: true` (another annotation of this species) | the partner's name as is | `foxC1: forkhead box C1 [SRC\|rbh\|smed]` |
| `copy_names: true` (a curated species naming its relatives) | `SYM: name (label)`, no `-like` | `RPL23: ribosomal protein L23 (Schmidtea mediterranea) [ISS\|bh\|smed]` |
| neither | `SYM-like: name-like (label)` | |

Only steps 3 and 5 make a **plain** human name (orthology); step 2 copies keep the partner's plain name. Step 6 and 7
names always end in `-like`.

### The -like cases (steps 6 and 7), for a gene steps 3 and 5 did not name

| When this happens | The gene gets |
|---|---|
| top human hit full-length, no tie, tree agrees or silent | `SYMBOL-like` (step 6) |
| tie between human paralogs, one is a full-length reciprocal best hit | `SYMBOL-like` after that one (`tie-rbh`) |
| tie between human paralogs, no reciprocal best hit decides | their HGNC group (family by descent) or PANTHER family name (`tie-grp`) |
| top human hit full-length, but the tree places the gene with OTHER human genes | no -like name; family or domain (steps 8-9) |
| no full-length human hit; another species' Swiss-Prot protein is the top hit and full-length | `Protein-like (Species)` (step 7) |
| otherwise | family (8), domain (9), or no name |

A weaker full-length hit never replaces a partial top hit. Human first: a full-length human top hit names the gene
even if a mouse or fly protein scores a little higher.

## B. Closest human gene -- tiers

| Tier | Evidence | Orthology? |
|---|---|---|
| 1 | OMA pairwise ortholog to human (supported) | yes |
| 2 | OMA HOG co-ortholog with human (supported; only when the OMA run fixes a species tree) | yes |
| 3 | MMseqs2 reciprocal best hit to a human protein (normal filter) | no |
| 4 | through another species: our gene's OMA ortholog in a reference species → its OMA human ortholog; or our reciprocal best hit to an Ensembl species → that gene's **Ensembl Compara** human ortholog | via another species |
| 5 | DIAMOND best hit to a human protein (Ensembl human or a human Swiss-Prot entry; normal filter) | no |
| 6 | DIAMOND Swiss-Prot hit in another species → its Ensembl gene → **Ensembl Compara** human ortholog | via another species |
| 7 | DIAMOND Swiss-Prot hit in another species → its PANTHER subfamily → the human genes in it | family only |

- Tiers 4 and 6: one chain is used (OMA chains first, then highest bitscore); every human gene it reaches is kept,
  so several genes = a family entry, never one picked by score. Chains are checked like OMA calls (set aside when
  nothing supports them).
- Several human genes at any tier = one family entry (`<HGNC group> family`, or `A/B-family`).
- **Named after a human gene → that gene is the closest human** (tier 3 if its hit is reciprocal, else 5).

## C. Closest gene in another species (config `closest_species`, e.g. Smed for flatworms)

Rank 1 OMA ortholog → 2 MMseqs2 reciprocal best hit (normal) → 3 DIAMOND best hit (normal) → 4 its hits file.
Shown in its own column; it names the gene only with `use_for_names: true` (step 2; one species per gene set).

## D. Tag key (end of every name)

`ISO` orthology · `ISS` similarity (-like) · `ISM` family/domain model · `TAS` curator · `SRC` the gene set's own name, or another annotation of this species
`1to1`, `Nto1` (N copies carry the name), `mto1` (many:1, one copy named), `fam` OMA family · `tree` PANTHER tree (step 5), then `rbh` / `via` / `bh`
`rbh` reciprocal best hit · `bh` best hit · `sp` Swiss-Prot · `tie-rbh` / `tie-grp` paralog tie · `smed`, `nvec`, ... naming species (step 2)
`pthr` PANTHER family · `rpt` repeat-built family · `ipr` InterPro domain · `te` transposable element (on `ISO`: the ortholog carries a TE domain)
Marks: `+` agrees · `~` partly · `C` contradicts · `-` no evidence · `X` set aside · `R` rejected
`sim+ / sim~ / sim-` named gene is the best / a / not a human hit · `pthr+ / pthrC` PANTHER family agrees / conflicts
`hog` OMA HOG agrees · `tree+ / treeC` PANTHER tree agrees / conflicts
`omaX` OMA call set aside (unsupported) · `omaC` OMA call withheld (conflict) · `omaR` OMA many:1 set rejected

## E. Words

**Ortholog / co-ortholog:** an orthology call (OMA, or the PANTHER tree). **Homolog (-like):** similar along its
length, orthology not shown -- "may be a paralog". **Family homolog:** the evidence stops at a family.
**Domain homolog:** a shared domain. Benchmarks (Methods §9) are tests that CHECK names; they are not part of naming.
