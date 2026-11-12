# OMA reference set and moop naming: pros, cons and supporting steps

2026-09-30. Shared version: https://claude.ai/code/artifact/4c16fd27-213f-4b07-9f83-4e98fe83ec71

See also `README.md` (reference set and species trees) and `HOWTO_NEW_SPECIES.md` (running a new organism).

## Summary

Keep the 10-species default OMA set for all datastore organisms. It spans every major branch between human and each target, so OMA can find human orthologs and transfer GO terms across the whole datastore. Its weak spots are fast-evolving targets (flatworms first) and lineages with no close reference. The fix is not a bigger default set but a closest-species DIAMOND and RBH run beside OMA, used as supporting evidence in the naming decision.

## The default set

Ten genomes, 408,705 proteins, run with OMA standalone 2.7.0 in `OMA_TEMPLATE_RUNS/BRAFL_CALMI_CAPTE_DROME_HUMAN_LEPOC_LOTGI_MONBE_MOUSE_NEMVE`.

| Code | Species | Group | Source | Proteins | Role for naming |
| --- | --- | --- | --- | --- | --- |
| HUMAN | *Homo sapiens* | Mammal | Ensembl 102 (GRCh38) | 103,200 | The naming anchor. |
| MOUSE | *Mus musculus* | Mammal | Ensembl 104 (GRCm39) | 67,158 | Experimental GO; confirms mammal groups. |
| LEPOC | *Lepisosteus oculatus* (spotted gar) | Ray-finned fish | Ensembl 74 | 18,893 | Diverged before the teleost genome duplication; links zebrafish duplicate pairs to one human gene. |
| CALMI | *Callorhinchus milii* (elephant shark) | Cartilaginous fish | Ensembl Vertebrates 51 | 49,321 | Slow-evolving jawed vertebrate; places early vertebrate gene families. |
| BRAFL | *Branchiostoma floridae* (amphioxus) | Cephalochordate | RefSeq GCF_000003815.2 | 43,041 | Diverged before the two early vertebrate genome duplications; maps one invertebrate gene to a human family of up to four paralogs. |
| DROME | *Drosophila melanogaster* | Insect | Ensembl 106 | 30,719 | Best-annotated invertebrate; strong GO. |
| LOTGI | *Lottia gigantea* (limpet) | Mollusc | Sbi1_4 filtered models | 23,514 | Slow-evolving spiralian; bridge for molluscs and flatworms. |
| CAPTE | *Capitella teleta* | Annelid | Ensembl Metazoa 27 | 31,325 | Second slow-evolving spiralian; bridge for annelids and flatworms. |
| NEMVE | *Nematostella vectensis* | Cnidarian | RefSeq GCF_932526225.1 | 32,370 | Keeps many ancestral animal genes; reference for corals and hydrozoans. |
| MONBE | *Monosiga brevicollis* | Choanoflagellate | v1.0 | 9,164 | Outgroup that roots the gene families. |

The species tree is fixed per target lineage in `species_trees.tsv`; the backbone is `(MONBE,(NEMVE,((DROME,(LOTGI,CAPTE)),(BRAFL,(CALMI,(LEPOC,(HUMAN,MOUSE)))))))`. `make_oma_db_files.pl` places the target by its NCBI lineage, so OMA builds its hierarchical orthologous groups (HOGs) on the known phylogeny.

## How OMA feeds the naming pipeline

OMA is the only source of a plain (non `-like`) human name besides curators, the gene set's own names and the PANTHER tree step. `assign_gene_names_v2.pl` (branch `naming-v2`) uses the OMA run in four places:

- **Naming step 3.** A supported OMA human ortholog gives `SYMBOL: approved name` (1:1 or many:1), or an HGNC-group or PANTHER family name for co-orthologs. Tag `ISO`.
- **Closest human gene, tiers 1, 2 and 4.** Tier 1 is the OMA pairwise ortholog, tier 2 the HOG co-ortholog, and tier 4 goes through a reference species' OMA ortholog to its human ortholog.
- **Closest gene in other species.** A `closest_species` entry in `geneset_config.yaml` reads the OMA ortholog to its `oma_code` (for example NEMVE for corals) before any hits file.
- **GO terms.** `mapGO/` transfers GO from the references; nearly all curated (non-IEA) GO sits on HUMAN, MOUSE and DROME. The other references improve GO transfer by improving the orthology, not by adding annotations.

Every OMA call is checked before it names a gene. A call with no human similarity and no shared PANTHER family is set aside (`omaX`). A call whose best human hit is another gene **and** whose PANTHER family differs is withheld (`omaC`). So the default set decides which orthologs OMA can find; the naming rules decide which of them are trusted.

## Pros

- **Every target has a nearby reference.** Each lineage row in `species_trees.tsv` (tetrapod, ray-finned fish, cartilaginous fish, cyclostome, tunicate, cephalochordate, ambulacrarian, mollusc, annelid, flatworm, ecdysozoan, cnidarian, sponge) has a reference on or next to its branch.
- **Duplications are bridged.** Amphioxus (before the two early vertebrate duplications) and gar (before the teleost duplication) let OMA's HOGs place one invertebrate or fish gene against a human paralog family. The naming rules then give a family name instead of picking one member by score.
- **Slow-evolving references.** Gar, elephant shark, amphioxus, Lottia, Capitella and Nematostella keep more ancestral genes and diverge less than their fast relatives. Orthology through them is more reliable.
- **A fixed tree for every run.** HOGs are computed against a known phylogeny, so tier 2 (HOG co-orthologs) and the `hog` support mark are available for every gene set.
- **One set for ~80 gene sets.** The 55 reference pairs are computed once (the shared all-vs-all archive). Each new organism only adds its own comparisons against the 10 references, and results are comparable across the datastore.
- **GO where it matters.** HUMAN, MOUSE and DROME carry the curated GO; the rest supply orthology to transfer it.

## Cons and weak spots

| Weak spot | Effect on naming | Affected gene sets | Action |
| --- | --- | --- | --- |
| Flatworms evolve fast; the nearest references (Lottia, Capitella) are distant | Fewer OMA pairs pass support; more genes fall to `-like`, PANTHER or domain names, or none | 24 flatworm gene sets (`flatworm_genesets.tsv`) | Closest-species DIAMOND and RBH against *Schmidtea mediterranea* (see below) |
| Drosophila is the only ecdysozoan, and it is fast-evolving with many gene losses | Weak bridge for insects, crustaceans, nematodes | None in the datastore today | Add a slow ecdysozoan (*Priapulus caudatus*, *Daphnia*) only if such gene sets arrive |
| No ambulacrarian (sea urchin, acorn worm) | The `ambulacrarian` row compares the target with amphioxus alone | None today | Add *Strongylocentrotus purpuratus* only if echinoderm gene sets arrive |
| Gar annotation is from 2013 (Ensembl 74, 18,893 proteins, few isoforms) | A gene missing from gar can look like a lineage loss and weaken HOGs for fish | *Danio rerio* (2 sets), other fish | Check for a newer gar annotation before the remaining runs |
| Primate or rodent targets sit inside (HUMAN, MOUSE) | The `tetrapod` tree would be wrong for them | None today | Give such a target its own tree row |
| OMA's human is Ensembl 102; DIAMOND's human is Ensembl 113 | Human genes new or changed since 102 are missed by OMA | All | Both resolve through current HGNC; update the reference on the next template rebuild |
| All-vs-all cost: HUMAN (103,200) and MOUSE (67,158) include isoforms | Longer runs | All | Keep the `.splice` files so OMA picks one isoform per gene |
| The target is itself a reference (NV2 vs NEMVE, fly vs DROME) | The same genome would be in OMA twice | Nematostella, Drosophila | Already handled: ≥ 50% identical proteins → use the template's reference run |

## What to avoid

- **More mammals or vertebrates in the default set.** Human and mouse already anchor those groups. Each extra genome adds all-vs-all compute for every one of the ~80 runs, and none of it improves which human gene a target is matched to.
- **The target's close relatives in the default set** (a planarian for the flatworms, a coral for the corals). They help one lineage and cost every run. Put them in a custom set or a `closest_species` entry for that lineage instead.
- **Growing the set to rescue fast-evolving targets.** More distant references do not make a flatworm gene easier to place. Closest-species evidence and better gene models do.
- **Changing the set mid-rollout.** The default set is what makes results comparable across gene sets. Change it once, for a clear reason, and rerun, rather than drifting run by run.
- **Relaxing the naming filters to recover names lost to a weak reference.** A missing name is better than a wrong one. Repair split or fragmentary models with mender first; keep the support (`omaX`) and conflict (`omaC`) rules.
- **Letting another species name a gene without review.** `use_for_names: true` in Montipora replaced about 2,200 human names. Keep it off until the results are checked.

## What helps: closest-species DIAMOND and RBH

For a lineage the default set covers poorly, search the gene set against one well-annotated close relative, and report that relative's closest gene beside the human one. The first use is the flatworms against *Schmidtea mediterranea*. This adds evidence and provenance; it does not name genes unless you turn that on after review.

```
EVIDENCE                              WHAT IT FEEDS (assign_gene_names_v2.pl)

OMA run (default set)          --->   Gene name               (OMA step 3)
                               --->   Closest human gene      (OMA tiers 1, 2, 4)
                               --->   Closest gene, other sp. (OMA via oma_code)
                               --->   GO terms                (mapGO)

Human DIAMOND and RBH          --->   Gene name               (support of OMA names; -like)
                               --->   Closest human gene      (hits tiers 3, 5-7)

Closest-species DIAMOND, RBH   --->   Closest gene, other sp. (hits, when OMA gives nothing)
  (new; e.g. Schmidtea)        - - >  Gene name               (only with use_for_names: true;
                                                               off by default, review first)
```

The new closest-species searches feed the other-species closest gene; they reach the gene name only through `use_for_names`.

**Scripts** (moop-pipeline `config/build_and_load_db/scripts/`, commit 80fa3c9c on `naming-v2`). Both take the same arguments and run by hand or under `sbatch`:

```
bash closest_species_diamond.sh GENESET_PROTEINS.fa PARTNER_PROTEINS.fa OUT_DIR LABEL [THREADS]
bash closest_species_rbh.sh     GENESET_PROTEINS.fa PARTNER_PROTEINS.fa OUT_DIR LABEL [THREADS]
```

| Script | Method | Output | What it adds |
| --- | --- | --- | --- |
| `closest_species_diamond.sh` | DIAMOND 2.1.6 `--ultra-sensitive`, E ≤ 1e-5, `--max-target-seqs 5`, the pipeline's 17 columns (with query and subject coverage) | `OUT_DIR/diamond_results.tsv.gz` | Best hits, with the coverage the full-length rule needs; a second partner gene shows paralog ties |
| `closest_species_rbh.sh` | MMseqs2 14-7e284 `easy-rbh`, defaults, the pipeline's 12 columns + `qlen tlen qcov tcov` | `OUT_DIR/rbh_mmseq_results.tsv` | Reciprocal best hits: a stronger one-to-one pairing than a best hit |

Both also write `db_version.txt` (LABEL and the partner FASTA's md5), a version file and `command.txt`, so every result says which partner it came from.

**How to use them in the pipeline** (moop-pipeline `naming-v2`, commit 6101d61f):

1. Run both scripts for each gene set, with `GENESET_DIR/protein.aa.fa` as the query and the partner's proteins (for flatworms, a *Schmidtea* gene set such as `smed_20140614`) as the partner. Give each its own `OUT_DIR`. Skip the partner's own gene sets.
2. Add a `closest_species` entry to `geneset_config.yaml` that points at the two output directories, with `use_for_names: false`, and check it with `geneset_config.pl --check`:

   ```
         closest_species:
           - species: Schmidtea mediterranea
             tag: Smed
             label: planarian
             rbh: /path/to/OUT_DIR_rbh
             diamond: /path/to/OUT_DIR_diamond
             use_for_names: false
   ```

3. Reprocess the gene set. Each gene gets a closest *Schmidtea* gene (`closest_Smed.tsv`, GFF `closestSmedId/Sym/Desc/Evidence`, database table "Closest Schmidtea mediterranea gene"), next to its closest human gene, and `naming_decisions.tsv` gets a `Closest_Smed` column.

The closest gene is chosen strongest first; the rank is the database score:

| Rank | Evidence | Filter |
| --- | --- | --- |
| 1 | OMA ortholog (needs `oma_code`, so the species must be in the OMA run) | OMA's own |
| 2 | MMseqs2 reciprocal best hit (`rbh:`) | E ≤ 1e-10, ≥ 50% of both proteins |
| 3 | DIAMOND best hit (`diamond:`) | same |
| 4 | best hit in a moop hits file (`hits:`) | E-value only (no coverage) |

**If the species is ever used for names** (`use_for_names: true`, after review): after OMA, a reciprocal best hit covering ≥ 80% of both proteins names the gene; then, for another species only, the DIAMOND best hit if that best hit is itself full-length. A weaker full-length hit is never used instead. The decision table's `Pipeline_name` column (in every run) shows the name each gene would get without *Schmidtea*, so the effect can be judged first.

**Descriptions.** A partner protein's description is its FASTA title, read from the DIAMOND table. The *Schmidtea* gene sets in the datastore have ids only in their FASTA headers (`SMED30000001`, `h2SMcT0000005.1`), so the closest *Schmidtea* gene is reported by id, with no description, and cannot name a gene.

**Still open:**

- [ ] A source of *Schmidtea* gene names or descriptions (for example a curated planarian name list) if the closest gene should carry more than an id.
- [ ] For the flatworms, consider a custom OMA set that adds a *Schmidtea* proteome (as for *Danio malabaricus*), in its own template subdirectory. Then `oma_code` gives OMA orthologs to *Schmidtea*, and RBH/DIAMOND only fill the gaps.
- [ ] Run the scripts on the flatworm gene sets and review the `Closest_Smed` columns.

**Keep human naming human.** Hits to *Schmidtea* never name a gene with `use_for_names: false`. They make the human call easier to judge: when the planarian ortholog carries a human-style name that agrees with the OMA or `-like` name, a reader can trust that name more; when they disagree, the planned `Closest_<tag>` columns of the decision table will show it.

## When to change the set

Change the default set only when a new group of gene sets needs a reference it lacks. Otherwise use a custom set or a closest species.

| Situation | Do this |
| --- | --- |
| A few gene sets in a poorly covered lineage (flatworms today) | Closest-species DIAMOND and RBH; optionally a custom OMA set in its own template subdirectory |
| A new lineage with several gene sets (insects, echinoderms) | Add one slow-evolving reference for it and rebuild the template once |
| A target that is itself a reference | Use the template's reference run (already automatic) |
| A primate or rodent target | Add its own row to `species_trees.tsv` |
| A newer gar or human annotation | Rebuild the template once and rerun, rather than mixing versions across runs |
