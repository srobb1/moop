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
| `species_tree.nwk` | from the NCBI taxonomy, when it is fully resolved; otherwise OrthoFinder infers it |
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
