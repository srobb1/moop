# Synteny pipeline (first draft)

Runs on the compute cluster. Makes synteny files that MOOP's JBrowse can show, with gene names,
from the **MOOP copies** of the gene sets. Plan and background: `notes/SYNTENY_PLAN.md`.

## Set up (once)

Edit `settings.sh`:

- `MOOP_DATA`: the MOOP copy of the organisms tree (`<organism>/<assembly>/<gene_set>/`).
  Use the MOOP copies, not `$GENOMES`. The browser uses MOOP's IDs, and the synteny files only
  line up with the gene track when they use the same IDs.
- `SYNTENY_OUT`: where results go.
- Module names, if they differ on the cluster (`jcvi/1.3.3`, `minimap2`).

Each gene set needs `genes.gff` and `cds.nt.fa`. The assembly folder above it needs `genome.fa.fai`,
and `genome.fa` too for whole-genome alignment. `feature_coords.tsv` is not used.

## Day to day: two lists

**`genomes.tsv`**: one line per genome. A short code, then the gene set path:

    BraPum	Bradypodion_pumilum/GCA_035047305.1/SIMR_2025-04-21

**`pairs.tsv`**: one line per comparison:

    ChaCal	BraPum	genes            # gene-anchor synteny (jcvi / MCScan-python): distant species
    BraPum	BraVen	genome	asm20    # whole-genome alignment (minimap2): close relatives

Then:

    bash submit_all.sh --dry-run     # what would run
    bash submit_all.sh               # one SLURM job per pair not yet done

Run it again whenever you add lines, or after the MOOP pipeline rebuilds gene sets. A finished
pair is skipped unless `genes.gff`, `cds.nt.fa` or `genome.fa` of either genome is newer than its
result; then it reruns, and `--dry-run` says why. `--force` reruns everything.

**Order:** annotation pipeline → MOOP pipeline (and copy) → this. It reads MOOP's finished files.
List each pair once, either way round. The result is shown in both genomes' browsers.

## What you get

`$SYNTENY_OUT/published/<OrgA>__<OrgB>/` (organism names sorted):

| method | files |
|---|---|
| genes | `A.bed`, `B.bed`, `A.B.anchors` (gene pairs), `A.B.anchors.simple` (blocks), `check.txt`, `*.prep.txt`, jcvi's dot plot PDF |
| genome | `A.B.<preset>.paf`, plus `.paf.stats.txt` (how much of each genome aligned, to compare presets) |

There is also `done.A.B.<method>.txt`, written last. Its presence means the pair finished.

Gene names in the browser look like `GPN1|BraPum_JAWDJD010000001.1_000001.1`. A gene with no
name keeps just its ID. The ID is kept so it can still be searched in the gene track.

## What each script does

| script | job |
|---|---|
| `prep_gene_set.pl` | `genes.gff` + `cds.nt.fa` → `code.bed`, `code.cds`, `code.labels.tsv`. Picks the longest CDS per gene and names everything by transcript ID. It matches FASTA headers to the GFF for every MOOP source (RefSeq `cds-XP_…`, MOOP `:cds`, Helixer, MENDER, MAKER), with no per-source fix-up scripts. It stops if under 90% of genes have a sequence, or if a gene is on a sequence the assembly lacks. |
| `run_pair.sh` | One pair (a SLURM job). For genes: prep (cached per genome), `catalog ortholog --no_strip_names --cscore=.99`, `synteny screen --minspan=30 --simple`, then labelling. For genome: `minimap2 -c -x <preset>`. |
| `label_and_check.pl` | Rewrites the IDs to `name\|ID` in the BEDs and the anchors together. Refuses to publish if any anchor gene is missing from its BED, or if there are no anchors. |
| `submit_all.sh` | Submits what is not done yet. |

## Not done yet (see the plan)

- Copying `published/` to MOOP (`data/tracks/synteny/`), running `jbrowse make-pif` on the PAFs,
  and generating the JBrowse tracks. That is MOOP's side, phase 2.
- Tested on MOOP's chameleon and Myotis gene sets with jcvi and minimap2 **stubbed out**.
  The first real run on the cluster is the pilot.
