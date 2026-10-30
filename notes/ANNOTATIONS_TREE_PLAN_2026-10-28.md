# Annotations tree switch: plan for 2026-10-28

Written 2026-10-27, end of day. Pick up here.

## Where things stand

- Today's builds (20 organisms, 22 gene sets) read `annotations/sbgenomes_2`, the tree
  `scripts/paths.sh` names in `ANNOTATIONS`. They should have read
  **`annotations/test/wrap_run_all_v2`**.
- Committed and pushed today:
  - `c19e100`: `data/` and moop assembly dirs are named by `genome-accession` (`data_assembly` in `paths.sh`)
  - `0d1cb75`: `genome.json` records `genome_kind` (`genome` | `transcripts`)
- Danio rerio was rebuilt and copied under `GCA_000002035.4` / `GCF_049306965.2` (job 5478616).

### What today's builds loaded (per gene set, from organism.sqlite)

| Group | Gene sets | Why |
|---|---|---|
| Fully loaded | Chamaeleo_calyptratus, Obama_nungara, Phagocata_morgani pmor.kc3, Phagocata_velata, Procerodes_sp, Romankenkius_sp x2, Spathula_sp | inputs were in `sbgenomes_2` |
| Copied to moop, only OMA / closest-human | Acropora_millepora, Cura_foremanii, Danio_rerio x2, Girardia_dorotocephala, Girardia_sp, Phagocata_gracilis, Phagocata_morgani pmor_cc, Turritopsis_dohrnii | no results in `sbgenomes_2` |
| Build FAILED, not copied | Camerata_robusta (5478395), Cura_pinguis (5478397), Diversibipalium_multilineatum (5478400) | EggNOG load: 0 rows matched. File ids `crob.kc3.gc000000.79.p1`, database `crob.kc3.gc000000.79:pep` |

### What `wrap_run_all_v2` holds

- All 22 gene sets above, plus Bipalium_vagum.
- Each has Swiss-Prot, EggNOG, InterProScan, SignalP, DeepTMHMM, DeepLoc, Ensembl RBH, eross RBH and BUSCO.
- The one gap: Bipalium_vagum InterProScan, still running at end of day (sm2699 jobs 5478648 and 5478745_2).
- **Every result file is gzipped.** Each result dir also has a `.done` marker.

## The plan

### 1. Read `.gz` everywhere (safe for every tree; do first)

Each reader should accept `name` or `name.gz`. Already handled: Swiss-Prot DIAMOND, the
Ensembl DIAMOND runs, InterProScan tsv/json. Still to do:

| Input | Where it is read |
|---|---|
| `eggnog_mapper/eggnog_mapper_results.tsv` | `process_one_geneset.sh` ~431-442 (`parse_EggNOG_to_MOOP_TSV.pl`) |
| `signalp6/signalp6_results.tsv` | ~485-490; naming args ~824 |
| `deeptmhmm/deeptmhmm_results.gff3` | ~497-512; naming args ~825 |
| `deeploc2/deeploc2_results.tsv` | ~522-530; naming args ~826 |
| `rbh_mmseq/*/rbh_mmseq_results.tsv` | ~624-642; `assign_gene_names_v2.pl` ~1124, ~1134, ~2668, ~4569 |
| `rbh_eross/*/rbh_eross_results.tsv` | ~556 (`make_rbbh` block) |
| missing-input checks | `check_missing_files_gff` / `_t2g` ~317-338, `check_status.sh` |

Approach:
- **Shell:** a small helper that returns whichever of `name` / `name.gz` exists. The parsers get
  `<(zcat …)` or a temp copy, following the existing `tophit.tsv` pattern.
- **Perl** (`assign_gene_names_v2.pl`, the parsers): open through a gz-aware open, e.g.
  `open my $fh, '-|', 'zcat', $file` when the name ends in `.gz`.
- Check the `*_version.txt` / `db_version.txt` reads still find their files in the new tree.
  The DIAMOND and EggNOG dirs there hold `*_version.txt`; `db_version.txt` was not checked.
- Test on one gene set (e.g. Cura_foremanii) against `wrap_run_all_v2` before any rollout.

### 2. Point the pipeline at `wrap_run_all_v2`: DECISION NEEDED

`wrap_run_all_v2` holds only these 21 organisms. Options:

- **(a) Per-gene-set fallback (recommended).** Use `wrap_run_all_v2/<org>/<asm>/<gs>` when it
  exists, else `sbgenomes_2`. Lives in `paths.sh`, next to `data_assembly`, and is used
  wherever `ANALYSIS_DIR` is set (`process_one_geneset.sh:43`, `check_status.sh:73`,
  `check_required_data.sh`, `check_annotation_links.sh`, ...). Log which tree each gene set used.
- (b) Make `wrap_run_all_v2` the default for everything. Any other organism rebuilt later gets no annotations.
- (c) Keep `sbgenomes_2` as the default and run these with
  `ANNOTATIONS=/n/sci/SCI-004223-SBGENOMES/annotations/test/wrap_run_all_v2` (the override already works).

### 3. Before rebuilding: the EggNOG id mismatch

- Check whether the `wrap_run_all_v2` EggNOG files for Camerata, Cura_pinguis and
  Diversibipalium still use TransDecoder `.p1` protein ids. If so, fix the id mapping in the
  EggNOG parse step (`.p1` -> `:pep`, the way the other protein-keyed parsers do it) before rebuilding.
- Check SignalP / DeepTMHMM / DeepLoc ids the same way. They are protein-keyed too.

### 4. Rebuild and copy (user submits; sbatch is blocked for agents)

```bash
cd /n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/build_and_load_db/v2
for o in Acropora_millepora Camerata_robusta Chamaeleo_calyptratus Cura_foremanii Cura_pinguis \
         Danio_rerio Diversibipalium_multilineatum Girardia_dorotocephala Girardia_sp Obama_nungara \
         Phagocata_gracilis Phagocata_morgani Phagocata_velata Procerodes_sp Romankenkius_sp \
         Spathula_sp Turritopsis_dohrnii; do
  MOOP_RELOAD=1 sbatch scripts/moop_process_genome_data_v2.sbatch "$o"
done
```

- Add `ANNOTATIONS=...` in front if option (c) was chosen.
- Schmidtea_mediterranea dd_v6 and Bipalium_vagum are in the tree but were not built today.
  Decide whether to include them (Bipalium_vagum only after its InterProScan finishes).
- Mind the CPU cap (350/user while classes run).

### 5. Verify after the rebuild

- Re-run the per-gene-set audit: loaded rows per source per gene set, against the inputs present
  in the tree. Expect `L` in every column for all 22 gene sets.
- Check `copy2moop_<date>.log` shows `OK` for each, and the moop dirs are named by accession.
- `genome.json` carries `genome_kind` (planarian `.kc`/`_cc` sets plus dd_v6 and SIMR_2025 = `transcripts`).

## Loose ends from today

- Delete by hand on moop: `organisms/Danio_rerio/GRCz11`, `organisms/Schmidtea_mediterranea/schMedA2h1`, `schMedA2h2`.
- `test_ejr/test.ref` metadata carries Bipalium's `genome-accession` (`bkew.kc1.ref`): a copy-paste slip?
- Danio GRCz11 `genome_name` is `NA` in the database although `metadata.yaml` has `genome-name: GRCz11`.
- Danio GRCz12tu ID-alignment warning: 23 `gff_extra` genes (`100334202-2` style), 44 `fasta_extra`.
  Details are in `data/Danio_rerio/GCF_049306965.2/RS_2025_04/id_alignment.details.tsv`.
