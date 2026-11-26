# Clade outgroups + edwardsiidae dropped (compute side, 2026-11-13)

Answer to the web note on outgroups. No format change: still `clade_homologs/2`.

## edwardsiidae: dropped

Not shipped any more (both species are in hexacorallia). copy2moop does not delete on the site:
**remove organisms/.clades/edwardsiidae/ on the site.**

## Outgroups (hexacorallia, ray_finned_fish; rerun 2026-11-13, shipped)

- Outgroups are in the OrthoFinder run and its rooted NCBI species tree (`-s`), and nowhere in homologs.sqlite:
  no member, gene, hog, node or pair rows.
- New meta keys, only when a run has outgroups:
  - `outgroups`: scientific names, comma-separated, e.g. `Lepisosteus oculatus`.
  - `orthofinder_clade_node`: the clade's node in OrthoFinder's tree, e.g. `N1`.
- The clade's node is still called `N0` in the file, and its HOGs `N0.HOG<n>` (same numbers as OrthoFinder's
  `N1.tsv`). Orthogroups too small for a gene tree (OrthoFinder writes no HOG for them) get the next numbers;
  `hog_source` says from which one.
- `gene.orthogroup` stays OrthoFinder's orthogroup. With an outgroup it also spans the outgroup, so one orthogroup
  can hold several N0 HOGs: **group by N0 HOG for "same gene family in this clade", not by orthogroup.**

| clade | members | outgroup |
|---|---|---|
| hexacorallia | Acropora, Montipora (new), Nematostella, Scolanthus | Turritopsis dohrnii (hydrozoan) |
| ray_finned_fish | Danio, Nothobranchius | Lepisosteus oculatus (spotted gar, before the teleost genome duplication) |
| planarians | 18, unchanged | none (enough species) |

ray_finned_fish: `taxon_id` 7898 Actinopterygii -> 32443 Teleostei (gar is in Actinopterygii) and label
`Ray-finned fish (Actinopterygii)` -> `Teleost fish (Teleostei)`. The clade ID `ray_finned_fish` is unchanged.

## Also changed in the builder (all clades)

Orthogroups too small for a gene tree (fewer than 4 genes) get no rows in OrthoFinder's Orthologues/ unless they
are single-copy in every species of the run. With an outgroup that missed e.g. one Danio + one Nothobranchius gene
with no gar gene (about 1,400 reciprocal-best fish pairs). The builder now adds 1:1 pairs for any orthogroup with no
pairs and one gene per member present: fish +3,146 pairs, hexacorallia +3,422. planarians is not rebuilt with it yet.

## Results

| clade | pairs | reciprocal_best | co_ortholog | weak | no_hit |
|---|---|---|---|---|---|
| hexacorallia (4 members) | 459,626 | 122,278 | 129,396 | 126,182 | 81,770 |
| ray_finned_fish | 56,928 | 32,554 | 12,858 | 6,578 | 4,938 |

Montipora (new) brings many weak/no_hit pairs (short gene models: 52,760 genes, 4,020 proteins < 100 aa). A run
without it was compared: the other three species' pairs are the same or slightly better with it, so it stays.
