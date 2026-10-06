# Gene naming -- quick reference (print me)

Summary of [GENE_NAMING_METHODS.md](GENE_NAMING_METHODS.md) (as of 2026-10-06, `assign_gene_names_v2.pl`). The Methods
file has the reasons and the numbers; this page has the rules.

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
| 1 | a curator's name (file) | as given | `TAS` |
| 2 | the gene set's own RefSeq/Ensembl name; or a naming species from the config (`use_for_names`) | `NAME`, or `NAME-like (species)` | `SRC` / `ISO` / `ISS` |
| 3 | **OMA ortholog of a human gene** (supported) | `ALPHA: alpha synthase`; copies `(1 of 4)`; several human genes: `<HGNC group> family member` | `ISO` |
| 4 | transposable-element Pfam domain | `PIF/Harbinger transposase domain-containing protein` | `ISM\|te` |
| 5 | PANTHER tree places the gene with exactly one human gene, which is also its closest human by similarity (tiers 3-5) | `SYMBOL: name` | `ISO\|tree` |
| 6 | top human hit full-length, no tie, tree does not disagree | `SYMBOL-like: name-like`; a tie: `<group> family member` | `ISS` |
| 7 | top Swiss-Prot hit is another species' protein, full-length, no human gene scores higher, no tie | `Protein name-like (Species)`, no symbol | `ISS\|bh\|sp` |
| 8 | PANTHER family, match covers ≥ 75% of the family model | `<family> family member` | `ISM\|pthr` |
| 9 | InterPro domain or repeat | `<domain> domain-containing protein` | `ISM\|ipr` |
| - | nothing above | `None` + reason: "no hits" or "hits did not pass the naming tests" | - |

Only steps 3 and 5 give a **plain** human name (orthology). Step 6 and 7 names always end in `-like`.

### The -like cases (steps 6 and 7), for a gene steps 3 and 5 did not name

| When this happens | The gene gets |
|---|---|
| top human hit full-length, no tie, tree agrees or silent | `SYMBOL-like` (step 6) |
| tie between human paralogs, one is a full-length reciprocal best hit | `SYMBOL-like` after that one (`tie-rbh`) |
| tie between human paralogs, no reciprocal best hit decides | their HGNC group or PANTHER family name (`tie-grp`) |
| top human hit full-length, but the tree places the gene with OTHER human genes | no -like name; family or domain (steps 8-9) |
| top human hit partial or none; another species' Swiss-Prot protein is the top hit and full-length | `Protein-like (Species)` (step 7) |
| otherwise | family (8), domain (9), or no name |

A weaker full-length hit never replaces a partial top hit. Human first: a full-length human top hit names the gene
even if a mouse or fly protein scores a little higher.

## B. Closest human gene -- tiers

| Tier | Evidence | Orthology? |
|---|---|---|
| 1 | OMA pairwise ortholog to human (supported) | yes |
| 2 | OMA HOG co-ortholog with human (supported) | yes |
| 3 | MMseqs2 reciprocal best hit to a human protein (normal filter) | no |
| 4 | through another species: our gene's OMA ortholog in a reference species → its OMA human ortholog; or our reciprocal best hit to an Ensembl species → that gene's **Ensembl Compara** human ortholog | via another species |
| 5 | DIAMOND best hit to a human protein (normal filter) | no |
| 6 | DIAMOND Swiss-Prot hit in another species → its Ensembl gene → **Ensembl Compara** human ortholog | via another species |
| 7 | DIAMOND Swiss-Prot hit in another species → its PANTHER subfamily → the human genes in it | family only |

- Tiers 4 and 6: one chain is used (OMA chains first, then highest bitscore); every human gene it reaches is kept,
  so several genes = a family entry, never one picked by score. Chains are checked like OMA calls (set aside when
  nothing supports them).
- Several human genes at any tier = one family entry (`<HGNC group> family`, or `A/B-family`).
- **Named after a human gene → that gene is the closest human** (tier 3 if its hit is reciprocal, else 5).

## C. Closest gene in another species (config `closest_species`, e.g. Smed for flatworms)

Rank 1 OMA ortholog → 2 MMseqs2 reciprocal best hit (normal) → 3 DIAMOND best hit (normal) → 4 its hits file.
Shown in its own column; it names the gene only with `use_for_names: true` (step 2).

## D. Tag key (end of every name)

`ISO` orthology · `ISS` similarity (-like) · `ISM` family/domain model · `TAS` curator · `SRC` the gene set's own name
`1to1`, `4to1` copies, `fam` OMA family · `rbh` reciprocal best hit · `bh` best hit · `tie-rbh` / `tie-grp` paralog tie
`sim+ / sim~ / sim-` named gene is the best / a / not a human hit · `pthr+ / pthrC` PANTHER family agrees / conflicts
`hog` OMA HOG agrees · `tree+ / treeC` PANTHER tree agrees / conflicts · `te` transposable element · `sp` Swiss-Prot
`omaX` OMA call set aside (unsupported) · `omaC` OMA call withheld (conflict) · `omaR` OMA many:1 set rejected

## E. Words

**Ortholog / co-ortholog:** an orthology call (OMA, or the PANTHER tree). **Homolog (-like):** similar along its
length, orthology not shown -- "may be a paralog". **Family homolog:** the evidence stops at a family.
**Domain homolog:** a shared domain. Benchmarks (Methods §9) are tests that CHECK names; they are not part of naming.
