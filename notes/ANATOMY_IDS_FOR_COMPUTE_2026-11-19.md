# Anatomy (PAGE) record IDs vs the Schmidtea crosswalk — questions for compute (2026-11-19)

From the web side. The anatomy search shows, for each PAGE curation record, the gene its ID maps
to through `organisms/Schmidtea_mediterranea/crosswalk.sqlite` (built 2026-11-11, compute side).
77,432 records / 38,997 distinct IDs; **8,648 IDs (13,569 records) reach no gene** — the records are
still listed, the gene column is empty. Formats are not the problem: the crosswalk holds other IDs
of the same sets written the same way. These IDs are simply absent.

**The main question for each set below: was the ID in the FASTA you ran, and found no match — or
was it never in the input?**

## 1. dd_Smed_v6 transcripts not in the crosswalk (some on the site, some not -- see correction)
Stückemann 2017 (PMID 28171748), 635 IDs, e.g. `dd_Smed_v6_10020_0_1`, `dd_Smed_v6_10040_0_1`.
~~All 635 are loaded in the `ddv6` gene set~~ — **WRONG, corrected 11-19:** 635 was the number of
on-site IDs among ALL 8,648 unmapped IDs, which happened to equal Stückemann's count. Some are loaded
(`dd_Smed_v6_10020_0_1` → gene `dd_Smed_v6_10020_0`), some are not (`dd_Smed_v6_10040_0_1`, as compute
found). Per-ID column `accession_is_a_feature_in_organism_sqlite` in the TSV below.
(Plass 2018 names dd_v6 *genes*, `dd_Smed_v6_101_0`; the web now goes gene → its transcripts, which
fixed 157 of 172 gut records. 187 Plass IDs still fail because their transcripts are absent too.)

## 2. dd_Smed_v4 transcripts not in the crosswalk
Fincher 2018 (PMID 29674431), 1,616 IDs, e.g. `dd_Smed_v4_10005_0_1`, `dd_Smed_v4_101_0_1`.
Also Wurtzel 2017 (345 IDs, `dd_Smed_v4_10020_0_1`) and Scimone 2018 (53). The crosswalk has 22,811
other v4 transcripts (`dd_Smed_v4_258_0_1`).

## 3. SmedASXL (asexual transcriptome) IDs not in the crosswalk
Currie 2016 (PMID 27034770), 2,019 IDs, e.g. `SmedASXL_000017`, `SmedASXL_000036`; Zhu 2015 (869),
He 2017 (189). The crosswalk's `SmedAsxl_ww_GCZZ01` source has `SmedASXL_042310` etc. — low numbers
missing? (both examples are < 000100)

## 4. OX_Smed 1.0 IDs
Kao 2013 (PMID 24238224), 429 IDs, e.g. `OX_Smed_1.0.00005`. The crosswalk's `ox_Smed_v2` source has
`OX_Smed_1.0.19997`.

## 5. ⚠️ Contig names are AMBIGUOUS — one name, several assemblies, different genes
`Contig…` IDs come from several papers' own assemblies (Forsthoefel 2012, Fincher 2018, Rouhana 2017,
Roberts-Galbraith 2016, Wang 2010), and the crosswalk holds the same name under up to four sources
(GPL15192, newmark_ests, uc_Smed_v2, be_Smed_v2) that map it to DIFFERENT genes:

    Contig1038   GPL15192 -> h1SMcG0018533    newmark_ests / uc_Smed_v2 -> h1SMcG0000584
    Contig1040   GPL15192 -> h1SMcG0020431    newmark_ests / uc_Smed_v2 -> h1SMcG0017819
    Contig1050   GPL15192 -> h1SMcG0004398    newmark_ests / uc_Smed_v2 -> h1SMcG0022157

The web looks an ID up by name alone, so a record can show a gene from the wrong assembly.
**Question:** which source does each paper's Contig ID belong to? With a paper → source table
(PMID → crosswalk source), the web can look each record up in its own source only. Also: 1,348
Fincher 2018 Contig IDs (e.g. `Contig10252`) are in no source at all — which assembly are they?

## Full list
`notes/ANATOMY_UNMAPPED_IDS_2026-11-19.tsv` — paper, PMID, ID, record count, whether the ID is a
feature in organism.sqlite. Mapped = in the 11-11 crosswalk by name, or a gene whose transcript is.
**No gene-name column:** MOOP's copy of the PAGE curations (anatomy_expression.sqlite, table
`curation`) stores no gene name -- only accession, term, evidence, stage, specimen, PMID. If the PAGE
source files carry one, the check would have to read them there.
