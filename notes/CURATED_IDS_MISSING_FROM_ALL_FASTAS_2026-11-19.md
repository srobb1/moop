# Curated anatomy IDs missing from every FASTA — 2026-11-19

*Schmidtea mediterranea* anatomy records (Planosphere/PAGE curations) whose ID is in **neither** the
crosswalk `map` (reaches a gene) **nor** `unlinked` (in our data, reaches no gene). These sequences were
never in any FASTA the crosswalk was built from. To link them, find each set's source file and add it
to the crosswalk run (compute side).

- Full list: `CURATED_IDS_MISSING_FROM_ALL_FASTAS_2026-11-19.tsv` (id_set, accession, records, papers)
- Re-run after a new crosswalk: `python3 -I scripts/curated_ids_missing_report.py > notes/....tsv`
  (summary on stderr; ~2 s; read-only)
- Crosswalk used: `built_at` 2026-11-19T15:02 (the CDS-only build with `unlinked`)

## Where the 38,992 distinct curated IDs stand

| | IDs |
|---|---:|
| Reach a gene (`map`, or a gene ID via its transcripts) | 30,015 |
| In our data, no gene (`unlinked`) — the site says why | 8,289 |
| **Missing from every FASTA** — this list | **688** (1,066 records) |

## Missing, by ID set — what to look for

| ID set | IDs | Records | Papers (IDs each) | Where to look |
|---|---:|---:|---|---|
| dd_Smed_v6 | 235 | 270 | Stückemann 2017 (200), Plass 2018 (35) | IDs not in our dd_Smed_v6 FASTA — a different dd_v6 release? PlanMine |
| dd_Smed_v4 | 50 | 76 | Fincher 2018 (32), Wurtzel 2017 (20), Scimone 2018 (3) | Same question for dd_Smed_v4; PlanMine |
| Contig… | 66 | 89 | Roberts-Galbraith 2016 (64), Rouhana 2017 (2) | Per-paper contig assembly (the Contig source question already open with compute) |
| SmedASXL | 51 | 64 | Currie 2016 (40), Zhu 2015 (10), He 2017 (2) | Pearson lab asexual transcriptome — a version not in GAKN01? |
| OX_Smed | 44 | 44 | Kao 2013 (44) | The missing ox_Smed_v2 numbers (already on your list) |
| GCR… | 35 | 42 | Saberi 2016 (35) | Saberi 2016 supplement |
| microRNA | 53 | 153 | González-Estévez 2009 (32), Friedländer 2009 (10), Li 2017, Sasidharan 2017 | miRBase — **not** a crosswalk job: miRNAs are never in a mRNA/CDS FASTA and the crosswalk links protein-coding genes only |
| Gene name, not an ID | 126 | 270 | Rink 2011 (45; the slc family), 50 more papers | Not a FASTA problem: needs a name → gene lookup (or the paper's GenBank accessions) |
| Clone ID (H./NB./D./PL) | 11 | 22 | Reddien / Sánchez Alvarado lab clone libraries | Clone → sequence (GenBank ESTs) |
| GenBank / ENA accession | 6 | 16 | Pascual-Carreras 2020/2021, Gurley 2008/2010, Petersen 2008 | Download from GenBank and add |
| SMU… | 6 | 10 | Stubenhaus 2016, Trost 2018, Lander 2016 | ? |
| mk (lcl\|mk…) | 1 | 6 | Rouhana 2012 | Local ID from that paper |
| Old genome model (SMED_, v31.) | 2 | 2 | — | Old SmedGD genome models |
| **Data error: a PMID in the ID field** | 2 | 2 | 26711341, 28292427 | Fix the curation (Planosphere) |

Full per-paper counts: run the script; it prints them.

## Found on the way — the site says something untrue (not fixed)

170 curated IDs are **dd_Smed_v6 gene IDs** (`dd_Smed_v6_101_0`) whose transcripts are all in `unlinked`.
They are in our data. But the anatomy table and Rosetta Stone hover say *"It is not in the reference data
used to link IDs to genes"*, because `moop_crosswalk_unlinked()` looks up the gene ID only. It should
fall back to the gene's transcripts, as `moop_crosswalk_gene_links()` already does for `map`. Small fix;
decide separately.
