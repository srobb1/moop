# clade_homologs/2: pair evidence (compute side, 2026-11-12)

Answer to the web note on DIAMOND evidence. Built as you specified; the differences are marked **(differs)**.
Builder: `config/build_and_load_db/scripts/build_clade_homologs.py`. Searches: `config/orthofinder/run_clade_diamond.sh`.

## Files

- A run with DIAMOND results (`<run>/diamond/`) builds `meta.format = clade_homologs/2`.
- A run without them (or `--diamond none`) still builds `clade_homologs/1`, unchanged.
- `copy2moop_v2.sh` refuses /2 files until the site reads them: ship with `CLADE_FORMAT2_OK=1 copy2moop_v2.sh --clades`.
  **Tell us when the site accepts /2.**

## pair

```sql
CREATE TABLE pair (gene_a INTEGER NOT NULL, gene_b INTEGER NOT NULL, kind TEXT NOT NULL,
  support TEXT NOT NULL, evalue REAL, bitscore REAL, pident REAL, cov_a REAL, cov_b REAL);
```

- Both directions, as before. In the mirrored row `cov_a`/`cov_b` are swapped and `kind` is mirrored; `support`,
  `evalue`, `bitscore`, `pident` are the same in both rows.
- Values = the best-scoring alignment of the two proteins, in either search direction. NULL when there is none.
- `support`: `reciprocal_best` | `co_ortholog` | `weak` | `no_hit`:
  - supported = E-value <= `evidence_evalue` and the alignment covers >= `evidence_min_cov` of both proteins. No identity cutoff.
  - `reciprocal_best` = supported, and each protein is the other's best hit in that species (ties count).
  - `co_ortholog` = supported, but not reciprocal best.
  - `weak` = a hit that is not supported.
  - `no_hit` = no DIAMOND hit at E <= `evidence_search_evalue`, in either direction.
- `kind` comes from partner counts, as it already did since commit dc5d9bb3 (13:51 on 11-12), with a build-time assertion.
  The ray_finned_fish file built at 13:47 from the pre-commit working copy has 0 genes typed 1:1 with more than one partner.
  If the site still shows 78, it has an older copy.

## gene

New column `protein TEXT` = the protein that stood in for the gene in OrthoFinder and DIAMOND (from the run's `gene_protein.tsv`).
Only in /2 files.

## meta (new keys in /2)

| key | example | note |
|---|---|---|
| evidence_tool | DIAMOND | |
| evidence_version | 2.1.13 | |
| evidence_mode | blastp --sensitive -k 25 | **(differs)**: --sensitive, not --more-sensitive |
| evidence_search_evalue | 1e-5 | **(new)**: the search cutoff; below it a pair is `no_hit` |
| evidence_evalue | 1e-05 | the cut for "supported" (1e-5, as agreed; written by Python as 1e-05) |
| evidence_min_cov | 0.5 | fraction of both proteins |
| evidence_rule | one sentence spelling out the labels | for the card |
| results_path | /n/sci/.../orthofinder_v2/edwardsiidae/results/Results_Nov12 | full OrthoFinder results on compute |
| pairs_reciprocal_best, pairs_co_ortholog, pairs_weak, pairs_no_hit | 19546, ... | **(new)**: counts, both directions |

The existing keys stay (`method`, `method_version`, `orthofinder_version`, `results`, counts).

## Test: edwardsiidae (test build, not shipped)

46,236 pairs: reciprocal_best 19,560, co_ortholog 8,964, weak 11,424, no_hit 6,288.
PARP16 (Nematostella 5509156), 43 Scolanthus "orthologs":
- 1 reciprocal_best: NY_Scal100_v1.51033 (E 1.8e-154, 68% identity, covers 95% / 100%).
- 2 weak: 28463 and 28464, each covering part of PARP16 (probably one gene split into two models).
- 40 no_hit.

## Next on compute

`run_diamond.sbatch` in each clade folder (edwardsiidae, hexacorallia, planarians, ray_finned_fish) runs the
searches and rebuilds the clade file as /2. New OrthoFinder runs do the searches automatically (run_orthofinder.sh).
