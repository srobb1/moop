# Running OMA on a new species

Step by step, from a datastore gene set to GO mapping, using the default 10-species template.
For why the reference species and species trees were chosen, see [`README.md`](README.md).

**The quick way:** `setup_oma_runs.sh` does steps 1-3 for one gene set or a list, and
`submit_oma_runs.sh` submits parts 1-3 chained (step 4); run either without arguments for usage.
The steps below are what they do.

Paths below:

- `SCRIPTS`  = this folder, `config/oma/` in the moop repo. Its scripts read their paths from
  `oma.conf` here (not in git; copy `oma.conf.example`), or from the file named by `$OMA_CONF`.
- `OMA_v2`   = `/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/OMA_v2` (the run folders; `OMA_V2` in oma.conf)
- `GENOMES`  = `/n/sci/SCI-004223-SBGENOMES/genomes/v2` (the datastore)
- `TEMPLATE` = `$OMA_v2/OMA_TEMPLATE_RUNS/BRAFL_CALMI_CAPTE_DROME_HUMAN_LEPOC_LOTGI_MONBE_MOUSE_NEMVE/OMA.2.7.0`
- `PREFIX`   = the OMA code for the target, e.g. `CONKUS`: letters and digits only, and not a
  reference code (`BRAFL CALMI CAPTE DROME HUMAN LEPOC LOTGI MONBE MOUSE NEMVE`). It becomes
  `DB/<PREFIX>.fa` and the target's name in every OMA output.

Example throughout: *Congeria kusceri*, genome `GCA_027627225.1`, gene set `COKUS1KC`, PREFIX `CONKUS`.

**Before you start**, check that the gene set can use this template (see README §3):
- the organism is an animal (plants and bacteria have no species tree here);
- the datastore gene set directory has `protein.aa.fa`, `protein2gene.txt` and `metadata.yaml` with `ncbi-taxon-id`.

Whether the gene set is itself one of the reference genomes (e.g. Nematostella RefSeq = `NEMVE`,
Drosophila = `DROME`) is checked for you in step 3: `make_oma_db_files.pl` then stops, writes
`REFERENCE_GENOME.txt`, and no OMA run is needed -- the pipeline uses the template's reference run.

## 1. Make the run directory

It must mirror the datastore path `<organism>/<genome>/<geneset>`. `make_oma_db_files.pl` finds
its inputs that way, and `oma_status_check.sh` finds runs that way.

```bash
mkdir -p $OMA_v2/Congeria_kusceri/GCA_027627225.1/COKUS1KC
```

## 2. Copy in the template

```bash
cp -r $TEMPLATE/* $OMA_v2/Congeria_kusceri/GCA_027627225.1/COKUS1KC/
cd $OMA_v2/Congeria_kusceri/GCA_027627225.1/COKUS1KC
mkdir -p slurm
```

The template contains the 10 reference genomes in `DB/` and their all-against-all in `Cache/`,
so only the comparisons involving the new species are computed. `slurm/` is needed for the job
logs.

## 3. Build the DB files and set the species tree

From the run directory:

```bash
perl $SCRIPTS/make_oma_db_files.pl CONKUS
```

It reads `protein.aa.fa`, `protein2gene.txt` and `metadata.yaml` from the same
`<organism>/<genome>/<geneset>` under `GENOMES`, and:

- writes **`DB/<PREFIX>.fa`**: IDs only in the headers (OMA crashes on header descriptions).
  A single stop `*` or gap `.` at the end of a sequence is removed. Proteins with an internal
  `*` or `.` are dropped and listed on stderr;
- writes **`DB/<PREFIX>.splice`** only **if the gene set has isoforms** (one line per gene with more
  than one protein). An empty `.splice` makes every OMA process rebuild the genome cache at once,
  which crashes part 2;
- looks up the target's lineage from `ncbi-taxon-id` in the NCBI taxonomy and writes the fixed
  **`SpeciesTree`** into `parameters.drw`. For example:
  ```text
  lineage: mollusc
  SpeciesTree: (MONBE,(NEMVE,((DROME,((LOTGI,CONKUS),CAPTE)),(BRAFL,(CALMI,(LEPOC,(HUMAN,MOUSE)))))));
  ```

Check the stderr summary. The protein count should match `grep -c '^>' protein.aa.fa`, minus any
proteins reported as removed. Check that the lineage is right for the organism.

To force a lineage, e.g. when the taxonomy lookup is wrong or the organism has no taxon ID, give it
as a second argument: `make_oma_db_files.pl CONKUS mollusc`. The lineage names are the first
column of `OMA_TEMPLATE_RUNS/species_trees.tsv`. Primate and rodent targets need a custom tree
(README §2).

The script stops without writing anything if:
- it is not run from an `<organism>/<genome>/<geneset>` directory under `OMA_v2` that has `DB/` and `parameters.drw`;
- an input is missing or empty;
- the PREFIX is invalid or is a reference code;
- no lineage matches the organism;
- the tree names a genome that has no `DB/<code>.fa` (wrong template).

**Rebuilding the DB files after part 1 has run:** the script warns that an old cache exists.
Delete it, then rerun part 1:
```bash
rm -r Cache/DB/CONKUS.db* Cache/AllAll/CONKUS
```

## 4. Part 1: database conversion (1 CPU)

```bash
sbatch oma-part1.sh
```

`oma-part1.sh` refuses to start if `parameters.drw` still has the placeholder species tree,
meaning step 3 was not run.

Before going on, check that `slurm/oma1-<jobid>.log` ends with
`Database conversion successfully finished`, and that `slurm/oma1-<jobid>.err` is empty. In
particular it must **not** say `WARNING: cached genome (<PREFIX>) contains no splicing map. Will
rebuild cache.`

## 5. Part 2: all-against-all (array of 5 x 68 CPUs, 128 GB each)

The big step: 5 array tasks, each on its own node. Before submitting, check that the cluster has
room, e.g. with `sinfo` or `squeue`.

```bash
sbatch oma-part2.n68.sh     # runs made before 2026-10-02 have oma-part2.n70.sh (70 CPUs per task)
```

Logs: `slurm/oma.<task>.<jobid>.out` and `.error`.

- `print: Inappropriate ioctl for device` in `.error` is harmless.
- Bus errors, `ReadDb ... index does not match file size` or `Could not read the right number of
  characters` mean the genome cache is being rebuilt by many processes at once (see step 3).

Part 2 is done when an `.out` file says `all jobs successfully finished`, or when **every line** of
`/home/smr/src/OMA/OMA.2.7.0/bin/oma-status -i .` shows finished chunks = expected chunks (0
started) **and** no part 2 array task is still in `squeue`.

> **Do not trust the summary percentage.** `oma-status` rounds it: with 95,844 of 95,845 chunks
> finished it prints `Nr chunks finished: 95844 (100.00%)` while the last chunk is still being
> computed (Chamaeleo MENDER_20260701, 2026-09-29: CHACAL/NEMVE 940 of 941, one array task still
> running). Look at `Nr chunks started` (must be 0) and at the per-pair lines of `oma-status -i`
> (all `100.000000%`). `oma_status_check.sh` checks exactly this.

If part 2 crashes or is cancelled, remove its unfinished chunks before resubmitting. Finished
chunks are kept:
```bash
/home/smr/src/OMA/OMA.2.7.0/bin/oma-cleanup --noinput .
```

## 6. Part 3: orthology inference and HOGs (1 CPU, 100 GB)

After part 2 has finished successfully (see the note above -- every pair at 100%, no part 2 task
left in the queue):
```bash
sbatch oma-part3.sh
```
A part 3 started too early does no harm but does nothing: its `.out` says `At least 1 process
appears to be still computing the all-vs-all` and it exits after a few seconds with exit code 0
(so SLURM lists it as COMPLETED). Submit it again once part 2 is done; `oma_status_check.sh` shows
it as `exited early`.
Or submit it straight away with a dependency on part 2:
`sbatch --dependency=afterok:<part2 jobid> oma-part3.sh`.

Results go in `Output/`:

| File | Contents |
|---|---|
| `PairwiseOrthologs/<PREFIX>-HUMAN.txt` (or `HUMAN-<PREFIX>.txt`) | Target–human orthologs with relation type (1:1, 1:many, many:1, many:many) |
| `HierarchicalGroups.orthoxml` | HOGs |
| `OrthologousGroups.txt` | OMA groups (strict 1:1 cliques) |
| `gene_function.gaf` | GO terms for the target |
| `ManualSpeciesTree.nwk` | The fixed tree that was used. Check it: it replaces `EstimatedSpeciesTree.nwk` from older runs |

Log: `slurm/oma3.<jobid>.out`.

**Changing only the species tree of a finished run:** the pairwise orthologs are cached in
`Cache/ortholog_milestone.drw.gz` and do not depend on the tree. Fix `SpeciesTree` in
`parameters.drw`, move `Output/` aside and rerun part 3 only. Part 2 does not need rerunning.

## 7. Map GO terms

```bash
cd mapGO
./get_OMA_GO_terms.sh CONKUS
```
This downloads `go.obo` and writes `mapGO/<PREFIX>.GO.tsv`.

## Checking progress

`oma_status_check.sh` lists every run under `OMA_v2`, how far it has got, and the next command
to run:

```bash
$SCRIPTS/oma_status_check.sh       # all runs
$SCRIPTS/oma_status_check.sh -w    # runs waiting for a step to be launched
$SCRIPTS/oma_status_check.sh -n    # skip oma-status (faster)
```
