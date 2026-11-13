# OrthoFinder clade runs

Links the genes of every MOOP organism in one clade (planarians, bats, ray-finned fish, ...) into
homolog groups, for the gene page's "Homologs in <clade>" panel. MOOP IDs only: proteins come from
MOOP's build folder, one per gene (the longest), with MOOP gene IDs as FASTA headers.

## Use

```bash
make_clade_set.py <clade> --list                       # who is in it, and which gene set each uses
make_clade_set.py <clade> --label <name>               # write $OF_ROOT/<name>/ and its run_orthofinder.sbatch
sbatch $OF_ROOT/<name>/run_orthofinder.sbatch          # OrthoFinder, then MOOP's homologs.sqlite
copy2moop_v2.sh --clades                               # ship it (or --all)
```

`<clade>` is an NCBI scientific name (`Actinopterygii`, `Chiroptera`, `Tricladida`, `Hexacorallia`)
or a taxon ID. Add `--display-label "Planarians (Tricladida)"` for the name shown in MOOP.

If a run stops after its searches (e.g. in its last steps), `RESUME=1 sbatch .../run_orthofinder.sbatch`
redoes only the gene trees and final files from the orthogroups it found.

## Outgroups

An outgroup is a species just outside the clade. It goes into the OrthoFinder run, and into the species tree,
so OrthoFinder can root each gene tree there and tell a duplication inside the clade (paralogs) from one
before it (separate genes all along). It is not a member: no DIAMOND searches, and it is left out of MOOP's
file (meta `outgroups` names it; the clade's node becomes N0).

```bash
make_clade_set.py Hexacorallia --label hexacorallia --outgroup Turritopsis_dohrnii            # a MOOP organism
make_clade_set.py Teleostei --label ray_finned_fish \
    --outgroup Lepisosteus_oculatus=<OMA_v2>/OMA_TEMPLATE_RUNS/<template>/OMA.2.7.0/DB/LEPOC.fa  # any protein FASTA
```

When to add one:
- the clade has few members (2-4): OrthoFinder cannot root a gene tree well from so few species, and a
  2-species run calls every within-species copy co-orthologous. Always add one here.
- the clade's members share a whole-genome duplication (teleosts): the outgroup must be from before it.
- many members (planarians, 18; bats): less needed; OrthoFinder roots well on its own.

How to pick one:
- outside the clade, but as close as you can get: the sister group, or the next branch out. Check
  `make_clade_set.py <clade> --list` and the NCBI lineage; the script refuses one inside the clade.
- a good, complete gene set (a RefSeq or an OMA reference proteome): a fragmented one adds noise.
- one is enough; two from different branches is better when the closest is poor.
- a MOOP organism keeps MOOP IDs; any other FASTA is fine too (an OMA `.splice` next to it keeps the
  longest protein per gene). The clade must be the NCBI taxon the members share (Teleostei, not
  Actinopterygii, when gar is the outgroup).

## Settings

`orthofinder.conf` next to the scripts (not in git; copy `orthofinder.conf.example`), or the file
named by `$ORTHOFINDER_CONF`:

| Key | What |
|---|---|
| `OF_ROOT` | run folders, `taxonomy/` and `geneset_choices.tsv` |
| `MOOP_DATA` | MOOP's build folder: the organisms, and `.clades/<clade>/homologs.sqlite` |
| `RS_CONFS` | the RosettaStone `*_moop.conf` files |
| `ORTHOFINDER_MODULE` | e.g. `orthofinder/3.1.2` |

`fetch_taxonomy.sh` downloads the NCBI taxonomy into `$OF_ROOT/taxonomy/`; rerun it now and then.

## Members and gene sets

Members: every MOOP organism (its `organism.sqlite` `taxon_id`) whose NCBI lineage contains the clade.
One gene set per organism, chosen in this order:

1. `$OF_ROOT/geneset_choices.tsv` (`Organism<TAB>gene_set`). Several lines for one organism make each
   gene set its own member, named `<Organism>_<gene_set>` (e.g. two unnamed species under one genus folder).
2. the organism's RosettaStone reference (`REFERENCE_GENESETS` in its `*_moop.conf`), if it names one
3. the organism's only gene set

Anything else stops `make_clade_set.py`, which lists every organism that needs a choice.

## A run folder

| File | What |
|---|---|
| `input/<member>.fa` | one protein per gene, MOOP gene IDs |
| `members.tsv` | gene set, counts, source protein FASTA and its md5 (the md5 MOOP checks for staleness) |
| `gene_protein.tsv` | which protein stood in for each gene |
| `clade.tsv` | clade, NCBI taxon, display label |
| `outgroups.tsv` | outgroups (`--outgroup`): taxon, source FASTA and its md5; their FASTAs are in `input/` too |
| `diamond/` | DIAMOND evidence between members (`run_clade_diamond.sh`; outgroups skipped) |
| `species_tree.nwk` | from the NCBI taxonomy (members + outgroups), when it is fully resolved; otherwise OrthoFinder infers it |
| `run_orthofinder.sbatch` | runs `run_orthofinder.sh` |
| `results/Results_<date>_<job>/` | OrthoFinder's tables; the one-file-per-orthogroup folders are in `per_group_files.tar.gz` |

`run_orthofinder.sh` works in `/scratch` (on `/n/sci`, OrthoFinder's last copy step can fail with
`[Errno 524]` and stop before its statistics are written, while still printing "finished"), checks the
run really completed, packs the per-orthogroup folders, copies the result back, and builds
`$MOOP_DATA/.clades/<clade>/homologs.sqlite` with `../build_and_load_db/scripts/build_clade_homologs.py`
(format: `docs/CLADE_HOMOLOGS_FORMAT.md`).

## OrthoFinder 3 notes

- `-b` does not reuse finished searches: it reruns an `-op` run's `blast_commands.txt`. To restart after
  the searches, use `-fg <Results folder>` (from groups; not listed in `-h`).
- Orthogroups that are single-copy in every species are left out of `Orthologues/`; the builder adds
  them as 1:1 pairs.
- A 2-species run writes no HOG files; the builder uses its orthogroups as N0 HOGs.
