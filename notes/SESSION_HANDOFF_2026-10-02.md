# Where we stopped, Friday 2026-10-02 (~16:40)

## 1. Code: branch `cokus-review-2026-10-02` in moop-pipeline, committed 09f6ccd (not pushed, not merged)
Committed 2026-10-02 at the user's request. Details: `notes/COKUS_REVIEW_2026-10-02.md`, `notes/GENE_NAMING_METHODS.md`.
- Fixes: statements follow the named gene; ortholog/co-ortholog wording; DIAMOND and EggNOG source versions; homolog
  tables keep the top hit; the loader no longer wipes gene statements (`*.domains.moop.tsv` glob); colon in a
  Swiss-Prot name; trailing space in HGNC names; `COLUMNS` variable and `peptide.dmnd` path in the DIAMOND scripts.
- New rules (user agreed): tier-4 chains checked like direct OMA pairs; OMA chain before RBH + Compara;
  "Identical proteins" statement (kind `identical`, order 4, scaffold names, no lengths);
  naming step 7 = Swiss-Prot protein of another species (steps are now 1-9).
- Agreed principles: no identity cutoff anywhere (E-value + coverage of both proteins; ties by rank);
  E <= 1e-10 for a name, 1e-5 for support; no closest species used for naming; EggNOG never names.
- Tests: `perl tests/naming_end_to_end.pl` -> 197 checks pass.
- The site card still needs: the `identical` kind with the label "Identical proteins", and steps 1-9.

## 2. Congeria (COKUS1KC): built and audited, NOT copied to moop
`build_and_load_db/v2/data/Congeria_kusceri/organism.sqlite` (log `slurm_logs/MOOP_COKUS_insession_2026-10-02i.out`);
the July database is `organism.sqlite.before_2026-10-02`. Analysis folder:
`dev/smr_dev/moop/annotations/SBGENOMES_2026-05-21/Congeria_kusceri/GCA_027627225.1/COKUS1KC` (README.consolidated.txt).
Rebuild (in a session; sbatch is refused for Claude):
`cd build_and_load_db/v2 && SLURM_SUBMIT_DIR=$PWD ANNOTATIONS=<...>/SBGENOMES_2026-05-21 SKIP_COPY=1 bash -l -c 'bash scripts/moop_process_genome_data_v2.sbatch --reload Congeria_kusceri'`

## 3. Zebrafish test: Danio_rerio / GRCz11 / 20260404 (Ensembl gene set, 46,060 proteins)
Why: native ZFIN names and Ensembl Compara human orthologs are a truth set; close to human; teleost duplication.
Analysis folder: `.../SBGENOMES_2026-05-21/Danio_rerio/GRCz11/20260404/` (scripts and logs are in it).
- InterProScan: user's screen on cerebro213 (job iprscan-danio), chunks in
  `/scratch/smr/tmp/interproscan/Danio_rerio_GRCz11_20260404/` (22 of 100 at 16:31; overnight).
  When 100 TSV + 100 JSON exist: `MERGE_ONLY=1 bash scripts/run_interproscan_geneset.sh Danio_rerio GRCz11 20260404`
  (pick SBGENOMES_2026-05-21). A `temp/` folder in build_and_load_db/v2 is InterProScan scratch; delete after.
- DIAMOND 17 columns: human + 12 others done. Six small ones running in the user's second session
  (`bash -l .../diamond_insession.sh`, resumes). Swiss-Prot: first try killed for memory (16 GB); retry with
  `--block-size 0.4` started 16:24 (`diamond_sprot_retry.sh`) -- CHECK that `diamond/UNIPROT_sprot/diamond_results.tsv.gz` exists.
- MMseqs RBH (18 Ensembl proteomes): `THREADS=16 bash -l .../mmseqs_rbh_insession.sh` (resumes, safe to run twice).
  Queued behind Swiss-Prot in Claude's session, which is cut off about 17:05 -- CHECK `rbh_mmseq/ENS_*/rbh_mmseq_results.tsv`.
- EggNOG: not needed.
- Then: build with `--reload Danio_rerio` as in section 2, and score Pipeline_name against the native names and
  the closest human gene against Compara.

## 4. OMA runs
- Running / queued (user's jobs): schMedS3h2 part 2 running; Spol_FtD1, spsp.kc1 and six new runs queued, part 3 chained.
- New run folders made today, part 1 finished cleanly: Furcifer_pardalis FURPAR, Bradypodion_pumilum BRAPUM,
  B. ventrale BRAVEN, Notamacropus T2T_helixer NOTEUG, Scolanthus SCOCAL, Amphimedon AMPQUE.
- Part 2 is now `oma-part2.n68.sh` (68 CPUs per task) in the template and the new folders.
- Still to do: mapGO for Phagocata velata, Procerodes, Romankenkius (`cd mapGO && ./get_OMA_GO_terms.sh <PREFIX>`).
- Next batch: Parastichopus, Ptychodera, Petromyzon x2, Nothobranchius; then Notamacropus T2T_SIMR_ANNOT; bats (49) last.
  Bradyrhizobium and Medicago x2: deferred (do not fit the animal template).
  Set up with `OMA_TEMPLATE_RUNS/setup_runs_2026-10-02.sh` (edit its list).
- Cluster: 350 CPUs per user while classes run (compute-busy), 500 otherwise; interactive sessions count; weekends are clear.

## 5. Closest-species searches ("extra BLASTs"): planned, not run
Plan: `notes/CLOSEST_SPECIES_NOTES_2026-10-02.md`. Proteomes: `dev/smr_dev/moop/closest_species/README.md`.
Open: which Schmidtea mediterranea annotation is the flatworms' partner; koala or opossum for the wallaby.

## 6. Open questions for the user
1. Push and merge the branch into main?
2. Which Smed annotation for the flatworms; koala or opossum.
3. Copy Congeria to moop once the site card knows the new statement kind and step numbers.
