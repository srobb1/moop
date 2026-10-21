# Pipeline checks found from the web side (2026-10-21)

**For the compute-box agent.** Found while sweeping every gene, organism, assembly and gene
set page on the web host. The pages themselves are fine (all 361 load, no errors); these are
data questions only the pipeline side can answer. Nothing here has been changed.

## 1. BUSCO: are isoforms being counted as duplicates? (please check)

The gene set page's BUSCO card shows high "complete, duplicated" figures exactly where a gene
set has many isoforms per gene:

| gene set | proteins | genes | duplicated (auto-selected lineage) |
|---|---|---|---|
| Nematostella_vectensis GCF_932526225.1 / RS_101 | 32,370 | 19,231 | **24.9%** (metazoa_odb10) |
| Nematostella_vectensis GCA_033964005.1 / NV2 | 35,881 mRNA | 24,526 | **16.0%** |
| Congeria_kusceri COKUS1KC | | | 6.8% |

BUSCO in `proteins` mode run over **every isoform** reports a gene's other isoforms as
"duplicated", so the card would overstate duplication. The usual practice is one protein per
gene (the longest isoform). Please check:

- **Which protein file BUSCO ran on** — all isoforms, or one per gene? (`mode: "proteins"` in
  busco.json; the `busco/<x>_auto` / `<x>_met` inputs per `notes/BUSCO_GENE_SET_SPEC.md`.)
- **`protein_fasta_md5` does not match the shipped file.** RS_101's busco.json records
  `44468053cfae6839dedaca9e54dd0c1e`; the shipped `protein.aa.fa` (32,370 records) is
  `869b1e4b471a39e70890a5ca9e4c841e`. The spec says the field is "the proteins the file was
  made against" — so either BUSCO ran on a different file (a longest-isoform file would
  explain this mismatch and settle the duplication question above, reassuringly), or the proteins
  changed after it ran. Nothing on the web compares the two today; if the md5 is meant to
  catch a stale result, say which file it should be compared against and the web can check it.
- **3 of 6 delivered.** The spec says the first full run (2026-10-20) covered 6 gene sets —
  Chamaeleo MENDER_20260701, bvag, cpin, crob, dmul, bkew — but the web host has busco.json
  only for Nematostella RS_101, NV2 and Congeria COKUS1KC. Not yet copied over?

## 2. Two gene-less gene sets are not in active_genesets.tsv (decide)

Four gene sets load with transcripts but **no gene features** (the gene page opens at the
transcript):

| gene set | features | in active_genesets.tsv |
|---|---|---|
| Notamacropus_eugenii T2T_SIMR_ANNOT / T2T_SIMR_ANNOT_geneset | 98,398 mRNA + protein | yes (line 53) |
| Schmidtea_polychroa Spol_FtD1 / Spol_FtD1_geneset | 24,190 mRNA | yes (line 86) |
| Schizocardium_californicum SCA / SCA_geneset | 46,657 mRNA | **no** |
| Sinorhizobium_meliloti H01_unique_genes / H01_unique_genes_geneset | 160 mRNA | **no** |

The current `process_one_geneset.sh` builds genes from `genes.gff` or `transcript2gene.txt`
and, on `--reload`, refuses a gene set with neither — so the first two are fixed by
reprocessing, provided their source has one of those files. **Schizocardium and Sinorhizobium
are not listed at all**, so a full rerun will not touch them and they stay gene-less. Is that
deliberate (retired / test data), or should they be added? (An earlier note: Sinorhizobium is
protein-only and needs a `transcript2gene.txt`.)

## 3. Verified, no action — `[Source:…]` tails

~159,000 gene descriptions in 7 organisms still end in `[Source:HGNC Symbol;Acc:…]`
(Romankenkius 63k, Notamacropus 34k, Schmidtea polychroa 19k, Turritopsis 19k,
Schmidtea mediterranea 16k, Schizocardium 8k, Sinorhizobium 3). Checked the current code:
`GeneNamingV2.pm:32` (`clean_name`) strips them, every description the naming step reads goes
through it (`assign_gene_names_v2.pl` 960, 1221, 1558, 1574, 2483), and
`tests/naming_end_to_end.pl` covers it. These come from the old naming run and go away on
reprocessing — except Schizocardium and Sinorhizobium, per item 2.
