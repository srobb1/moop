# RosettaStone

Maps a species' gene sets and other sequence sets (transcriptome assemblies, ESTs, older gene
models, microarray probes, ...) onto the gene models of one or more reference genomes. Then
builds MOOP's `crosswalk.sqlite`, so that a MOOP search for any of those IDs, or their aliases,
finds the gene.

Code only, and organism-neutral: everything comes from a config file, and nothing is written
here. It reads the genomes datastore directly (`genomes/v2/<Species>/<assembly>/<geneset>/`).
`examples/schmidtea_mediterranea/` is a complete worked example.

```
rosettastone/
├── rosettastone.conf.example     copy to rosettastone.conf (git-ignored) and edit
├── run_pipeline.sh               runs the pipeline for one species (--plan: show what it would do)
├── submit_slurm.sh               the same on SLURM: one array task per set
├── scripts/
│   ├── list_genesets.sh          a species' gene sets: active? real genome or placeholder?
│   ├── lib.sh                    shared helpers (config, datastore, references, skip-if-done)
│   ├── 01_prepare_reference.sh   per reference: genome index, gene models, exons, transcript->gene
│   ├── 02_align_transcripts.sh   query sets -> every reference (minimap2 splice:hq) -> sorted GFF
│   ├── 02a_align_probes.sh       probe sets -> every reference (pblat, >=95% identity) -> BED
│   ├── 03_overlaps.sh            bedtools intersect with each reference's exons + count_overlaps.py
│   ├── count_overlaps.py         longest overlap and exact-exon matches per gene/sequence
│   ├── 04_filter_mappings.sh     -> filtered/<ref>_mappings.txt
│   └── 05_make_aliases.sh        -> aliases/aliases.txt
├── crosswalk/
│   └── build_crosswalk.sh        mappings + aliases -> crosswalk.sqlite
└── examples/
    └── schmidtea_mediterranea/   rosettastone.conf + alias_rules.sh
```

## How it runs

One run covers one species. You choose the **reference** gene set(s): the genomes and gene
models everything is mapped onto. Every other **active** gene set of that species is then
**mapped** onto them, using its `transcript.nt.fa`. So are any extra sets from `QUERY_DIR` and
`PROBE_DIR`. The references' own transcripts are mapped too, which links two references' genes
to each other (e.g. two haplotypes).

The work is per set and per reference, and each output is redone only when its inputs have
changed. Adding a gene set to the datastore and rerunning therefore aligns only the new set,
then rebuilds the combined tables and the crosswalk.

```bash
cd config/rosettastone
scripts/list_genesets.sh /n/sci/SCI-004223-SBGENOMES/genomes/v2/Schmidtea_mediterranea
cp rosettastone.conf.example rosettastone.conf       # set SPECIES_DIR, REFERENCE_GENESETS, OUT_ROOT
./run_pipeline.sh -c rosettastone.conf --plan        # what will be mapped onto what
./submit_slurm.sh -c rosettastone.conf               # on SLURM (or ./run_pipeline.sh -c ... locally)
```

Other species from the same config, overriding keys from the environment:

```bash
SPECIES_DIR=$GENOMES/Danio_rerio REFERENCE_GENESETS=RS_2025_04 ./submit_slurm.sh -c rosettastone.conf
```

`submit_slurm.sh` submits three jobs: step 01; an array with one task per set (steps 02, 02a
and 03); and steps 04, 05 and the crosswalk once every task has succeeded. `--dry-run`
prints the commands and writes nothing. Logs go to `$OUT_DIR/runs/<timestamp>/`.
`run_pipeline.sh -s 04,05,crosswalk` runs only those steps; naming sets after the options
limits steps 02–03 to those sets. Each run records what it resolved in
`$OUT_DIR/rosettastone.plan.txt`.

### Placeholder genomes

Some datastore "genomes" are placeholders: a `genome.fa` made of the gene set's own
transcripts, for gene sets that have no real assembly (transcriptome assemblies). Nothing can
be mapped onto them, so they can never be references. Their transcripts are still mapped onto
the real references as a set. `list_genesets.sh` shows which is which. How it decides:

1. a gene set's `metadata.yaml` decides when it says so: `genome-type: transcriptome`
   or `genome: false` marks a placeholder; `genome-type: genome` or `genome: true` a real genome;
2. otherwise the genome is a placeholder when most `transcript.nt.fa` IDs are also contig names
   in `genome.fa` (a `.ref` suffix is ignored). In genomes/v2 (2026-11-10) this finds 22
   placeholders, each with 100% of transcripts as contigs, and 80 real genomes with 0%.

A species whose active gene sets are all placeholders has nothing to map onto, and the run
stops with that message.

## Configuration

| Key | What |
|---|---|
| `SPECIES_DIR` | the species folder in the datastore, or a MOOP build folder (`build_and_load_db/v2/data/<Species>`; see MOOP mode below) |
| `REFERENCE_GENESETS` | reference gene sets, `assembly/geneset` or just `geneset`. Empty = the only active gene set on a real genome. |
| `REFERENCE_HUBS` | `geneset=hub ...`: the name MOOP's crosswalk config uses for a reference (default: gene set name) |
| `REFERENCE_TRANSCRIPT_SOURCES` | `geneset=name ...`: the source name for a reference's own transcripts (default: `<gene set>_transcripts`) |
| `MAP_GENESETS`, `EXCLUDE_GENESETS` | which gene sets to map: `active` (default), `all`, `none` or a list; minus exclusions |
| `REFERENCES` | extra references by hand (bash array of `"name \| genome \| gene models \| hub [\| transcript2gene]"`) |
| `QUERY_FILES` | extra query sets by file, `name=path ...` (e.g. a gene set not loaded in MOOP) |
| `QUERY_DIR`, `QUERY_EXCLUDE` | extra sets not in the datastore: `<set>.nt` per set; names to skip |
| `PROBE_DIR` | probe sets (short, unspliced; pblat), `<set>.nt` |
| `UNSTRANDED_SETS` | sets whose sequences aren't reliably oriented |
| `ALIAS_RULES`, `ALIAS_TABLES`, `ALIAS_SOURCE_RENAME` | where aliases come from (below) |
| `OUT_DIR` or `OUT_ROOT` | output folder; with `OUT_ROOT` it is `OUT_ROOT/<species folder>` |
| `CROSSWALK_ORGANISM`, `CROSSWALK_OUT` | MOOP organism folder (default: species folder name); output file |
| `TRANSFER_HUBS` | mapped sets to publish as hubs of their own (below) |
| `CROSSWALK_FALLBACK_*` | optional older mapping for IDs the genome mapping misses (below) |
| `THREADS`, `MODULES` | CPU threads (`submit_slurm.sh --cpus` sets it); modules to load |

Environment variables override any key except `REFERENCES`. Set names come from the gene set
folder or the `.nt` file name. They may contain only letters, digits, `_`, `.` and `-`, and
must be unique; the run stops if two sets share a name.

**Aliases** (step 05). These are the other names people use for a sequence. Each is
`alias<TAB>accession<TAB>set<TAB>type`, where `accession` is the ID as it appears in `set`'s
FASTA. They come from:
- `ALIAS_TABLES`: ready-made tables.
- `ALIAS_RULES`: an optional bash file of organism-specific rules that derive aliases from
  FASTA headers (see `scripts/05_make_aliases.sh` and the example).

`ALIAS_SOURCE_RENAME="old=new"` fixes up set names. An alias of an alias also resolves.

**MOOP mode (recommended).** Point `SPECIES_DIR` at the organism's MOOP build folder (it holds
`organism.sqlite`). Every gene set loaded in MOOP is then a gene set here, under MOOP's own name, and
transcript->gene pairs are read from `organism.sqlite`, so every ID is exactly what MOOP loaded. Hubs and
sources are named after their MOOP gene set (`<gene set>`, `<gene set>_transcripts`); `REFERENCE_HUBS` and
`REFERENCE_TRANSCRIPT_SOURCES` are refused. Before a new crosswalk replaces the old one,
`crosswalk/check_crosswalk.py` checks it against `organism.sqlite`: a hub or source whose IDs belong to a MOOP
gene set but is named differently fails the run (the result is kept as `crosswalk.sqlite.failed`). A set that is
not loaded in MOOP can still be added with `REFERENCES` / `QUERY_FILES`. The check can also be run by hand:
`python3 crosswalk/check_crosswalk.py crosswalk.sqlite organism.sqlite`.

**Transfer hubs.** List a mapped set in `TRANSFER_HUBS` when people still cite it as the
reference (e.g. smed_20140614). Each of its IDs becomes a hub ID that inherits every mapping
of the reference gene it overlaps best: most exact exon matches, then longest overlap. Ties
are all kept.

**Fallback.** `CROSSWALK_FALLBACK_MAPPING` is an older direct mapping onto a transfer hub. It
is used only for the IDs in `CROSSWALK_FALLBACK_IDS` that the genome mapping doesn't reach.

## Outputs (under `OUT_DIR`)

| Path | Step |
|---|---|
| `refdb/<ref>/` `genome.fa` (link) `.mmi`, `anno.gtf`, `junctions.bed`, `exons.gtf`, `t2g.tsv` | 01 |
| `alignments/<ref>/<set>.sam`, `.sorted.gff` | 02 |
| `probe_alignments/<ref>/<set>.psl`, `.bed` | 02a |
| `overlaps/<ref>/…`, `mappings/<ref>/<set>.{stranded,unstranded,probe}.overlaps.txt` | 03 |
| `filtered/<ref>_mappings.txt`: gene_id, target_id, transcriptome (set), max_overlap, exon_overlap | 04 |
| `aliases/aliases.txt` | 05 |
| `crosswalk/crosswalk.sqlite` | crosswalk |
| `rosettastone.plan.txt`, `runs/<timestamp>/` | what was run; SLURM logs |

A mapping is kept when the aligned sequence overlaps an exon of the gene by >=100 bp or
matches at least one exon exactly. All probe overlaps are kept. Very short sequences (under
~100 bp, e.g. tRNAs) rarely align with minimap2; give them as probe sets instead.

## The crosswalk on its own

`crosswalk/build_crosswalk.sh -c rosettastone.conf` reads everything from `OUT_DIR`.
Options override the config, so it can also run without one (`--ref NAME HUB MAPPINGS T2G`,
`--transfer-hub`, `--probe-set`, `--aliases`, `-o`, `--organism`; see its `--help`). There is
one hub per reference plus one per transfer hub, and hub names must match MOOP's crosswalk
config. `--tsv-dir DIR` also writes each hub as a TSV.

## Origin and changes from RosettaStone v2

Ported from RosettaStone v2 (ejr, 2024; S. mediterranea, two haplotypes). v2 also needed
`sortMapping.pl` and Blaze. It was checked against v2's own outputs on 2026-11-10:
- Step 04 reproduces both of v2's mapping tables exactly.
- Steps 02, 02a and 03 match on test subsets (200 transcripts, 100 probes), except for the
  exon fix below.
- Step 05 matches, except for the fixes below.
- The crosswalk loses no mapping and gains only the fixes below.

What changed:

- **Organism-neutral, datastore-driven.** Any species in the genomes datastore. Any number
  of references, where v2 had exactly two haplotypes. Placeholder genomes are detected. Hub
  names come from the config. Reference transcripts and genes are recognised from the gene
  models (`t2g.tsv`), not by an ID pattern. Transfer hubs, probe sets, fallback and alias
  rules are all configured. The planarian-specific rules are in `examples/`.
- **Exons shared by two genes** (01). v2 de-duplicated exons on coordinates only, so a gene
  that shared an exon with another gene lost it: ~110 genes per haplotype in schMedS3. Now
  de-duplicated on the whole line.
- **Non-coding reference transcripts** (crosswalk). Reference transcripts that gffread drops
  (WormBase `nontranslating_CDS`) are still recognised, and now get a gene-level row.
  Because gffread drops them, they are still not overlap targets.
- **Published names** (05). Space-separated alias tables were dropped. Now read.
- **Transfer-hub IDs overlapping several genes.** v2 took whichever gene sorted last. Now the
  best-overlapping gene, ties kept, and the ID's own fallback mappings are kept too.
- **S. mediterranea GPL14150 gene-model names** (example rules). These aliases were built but
  never used; now they are.
- **Robustness.** Outputs are written atomically and redone when inputs change, where v2 used
  `finished_*` marker files. Step 04 is awk, so R/tidyverse is no longer needed. Only the sets
  now in `QUERY_DIR`/`PROBE_DIR` are combined. The crosswalk needs sqlite3 >= 3.25.

Needs: minimap2, gffread, paftools.js (k8), AGAT, pblat, bedops (psl2bed), bedtools, perl
(example alias rules), python3, awk, sqlite3. Module names are set in `MODULES`.
