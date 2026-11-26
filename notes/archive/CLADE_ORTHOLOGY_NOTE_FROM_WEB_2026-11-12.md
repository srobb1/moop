# Clade orthology: outgroups, a FastOMA test, and one builder bug (website side, 2026-11-12)

Context: the "Comparative genomics" card is live on gene pages (reads `organisms/.clades/<clade>/homologs.sqlite`,
spec `docs/CLADE_HOMOLOGS_FORMAT.md`). Looking at real pages exposed problems that come mostly from how the
runs are set up, not from OrthoFinder itself.

## What the pages showed

1. **Two-species runs over-call orthologs.** edwardsiidae, Nematostella `5509156` (PARP16), orthogroup
   `OG0000010`: 1 Nematostella gene vs **43 Scolanthus genes**, all reported as `1:many` orthologs. Their
   descriptions: 20 zinc-finger C2H2 proteins, 2 PARP-domain, 2 reverse transcriptase, and **one** "poly(ADP-ribose)
   polymerase family member 16 [ISO|1to1|...]" -- most likely the only real counterpart. With two species and no
   outgroup, OrthoFinder cannot tell a lineage duplication from poor/fused gene models sharing a domain.
   Scale: of 10,435 orthogroups with exactly one Nematostella gene, **63 have >= 10 Scolanthus genes**.
2. **Builder bug -- please fix regardless of the rest.** ray_finned_fish: **78 genes** have several partners in
   the other species, every pair typed `1:1` (e.g. Danio `100004171`: 13 Nothobranchius partners, all `1:1`;
   a histone cluster). The kind must come from the partner counts on each side (1 vs n -> `1:many`), not from
   each output row. edwardsiidae is clean. Suggest a build-time assertion: a gene with a `1:1` pair has exactly
   one partner in that organism.

## Ask 1 -- add outgroups to every clade run (OrthoFinder)

Run each clade **with 1-3 outgroup species** (good gene models, just outside the clade); the builder keeps them
out of `homologs.sqlite` (no `member`/`gene` rows), so the site never shows them. They only give OrthoFinder a
rooted species tree and something to measure duplications against.

**Close outgroups first** -- a sister group just outside the clade resolves "duplicated in the clade" vs "older
family" far better than a distant one; add one more distant, well-annotated species for rooting. Suggestions only
(the choice is yours; anything already in the OMA template is convenient):

| Clade | Close outgroup(s) | Distant anchor |
|---|---|---|
| edwardsiidae (sea anemones) | Exaiptasia diaphana or another actiniarian; Acropora (= the hexacorallia set) | Hydra or human |
| ray_finned_fish | spotted gar (Lepisosteus, = OMA `LEPOC`) | human / mouse |
| planarians (Tricladida) | a non-triclad flatworm, e.g. Macrostomum (Schmidtea polychroa is inside the clade, so it cannot be the outgroup) | Schistosoma mansoni, human |
| bats (later) | other Laurasiatheria: horse, dog, cow | human |

Also: prefer **>= 4 species** per displayed clade. Is the 2-species edwardsiidae run worth keeping now that
hexacorallia (3 species + outgroups) exists? Website suggestion: replace it.

## Ask 2 -- test FastOMA on the same sets, close species first

Sofia did not love FastOMA for her **cross-species** (distant) runs, so test it where it may do better:
**closely related species**. Same inputs as the OrthoFinder runs, so the two are directly comparable:

1. **planarians (Tricladida)** -- close species, 18 gene sets; the main case.
2. **ray_finned_fish** (+ gar) -- small, quick.
3. hexacorallia only if 1-2 look promising (more distant).

Compare OrthoFinder (with outgroups) vs FastOMA on:

- **Known genes**: PARP16 (above), Danio `100004171` (histones), and a handful of planarian genes you know well
  (piwi-1, smedwi-2, tgs-1, ...): are the right partners there, and only those?
- **Over-calling**: count of families with 1 gene in one species vs >= 10 in another.
- **Single-copy recovery**: of BUSCO single-copy genes present in two species, the fraction called `1:1` (should
  be high for both tools; a tool much lower is missing real orthologs).
- **Runtime and memory**, to judge the bats (49 species) -- the reason plain OMA is out.

Whichever wins, the website file does not change shape: please write `meta.method` (`OrthoFinder` / `FastOMA`)
and `meta.method_version` instead of only `orthofinder_version`. The site will show the method in the card's
note (spec will be updated to `method` with `orthofinder_version` still accepted).

## All gene sets of a species -- nothing new to build (decided 2026-11-12)

Simpler than first proposed: **do not** add alias tables to the clade file (the `clade_homologs/2` idea is
dropped). The website reaches every other gene set of a species through that organism's **crosswalk.sqlite**,
which RosettaStone already ships. So:

- clade files stay `clade_homologs/1`, one gene set per species, plus `meta.method` / `meta.method_version`;
- for each member organism with more than one gene set, ship its crosswalk, with the clade's member gene set
  as a **hub** (e.g. Nematostella: hub RS_101, sources incl. NV2). Nematostella's crosswalk is the next one
  needed (NV2 pages show nothing until then).

Also: `.clades/hexacorallia/` and `.clades/planarians/` arrived on the server as EMPTY folders (14:22) -- a
copy still running, or one that failed?
