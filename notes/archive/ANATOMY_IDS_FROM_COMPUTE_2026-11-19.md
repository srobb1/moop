# Anatomy (PAGE) IDs vs the Schmidtea crosswalk — answers from compute (2026-11-19)

Reply to `notes/archive/ANATOMY_IDS_FOR_COMPUTE_2026-11-19.md`.

**Short answer:** most unmapped IDs **were in the input and aligned to the genome**, but where neither
S3 reference (schMedS3h1 / schMedS3h2) has a gene model, so there is no gene to map them to. A
smaller group was never in the input. Nothing is overwritten: the crosswalk keeps every ID per
source (see section 5); the Contig mix-ups come from looking IDs up by name alone.

A new Schmidtea crosswalk was built 2026-11-18 (whole transcripts aligned, overlaps counted
on the CDS only; commit b8837e3f). It ships with the next copy of Schmidtea_mediterranea. The
reasons below hold for both the 11-11 and the 11-18 builds; the counts are from 11-18.

## What happened to each input transcript (counted against S3h1; "linked" = linked to any hub)

| set | in input | linked | aligned, no S3 gene there | exon/UTR only, no CDS | CDS overlap < 100 bp | did not align |
|---|---|---|---|---|---|---|
| ddv6 (MOOP gene set) | 30,816 | 22,231 | 7,914 | 301 | 140 | 230 |
| dd_Smed_v4 | 38,633 | 22,325 | 14,343 | 445 | 823 | 697 |
| SmedAsxl_ww_GCZZ01 | 81,283 | 16,988 | 54,023 | 1,961 | 5,733 | 2,578 |
| ox_Smed_v2 | 21,411 | 13,994 | 7,182 | 139 | 66 | 30 |

Mapping rule: a transcript maps to a reference gene when its CDS overlaps the gene's CDS by
≥ 100 bp or matches one CDS exon exactly. Non-coding transcripts are not mapped (coding genes only).

## 1. dd_Smed_v6 — two different cases
- **In the input, no S3 gene there.** `dd_Smed_v6_10020_0_1` is in the MOOP `ddv6` set and aligned
  uniquely (2_h1:173,884,549, MAPQ 60), but no S3h1 or S3h2 gene model is at that position.
- **Never in the input.** The crosswalk uses the MOOP `ddv6` gene set (30,816 transcripts), not the
  full dd_Smed_v6 transcriptome (41,745; excluded so the IDs are not mapped twice). So 10,929 v6
  transcripts are not in the crosswalk at all, e.g. `dd_Smed_v6_10040_0_1`.
  ⚠️ `dd_Smed_v6_10040_0_1` is also **not in organism.sqlite** (neither it nor its gene
  `dd_Smed_v6_10040_0`). So "all 635 are loaded in ddv6" does not hold here. Please check how
  that was tested; a gene-level match may have been counted.
  Compute can add the full v6 transcriptome as a query if you want these 10,929 mapped.
- The 187 Plass 2018 gene IDs whose transcripts are absent are likely the same two cases.

## 2. dd_Smed_v4
Both examples were in the input and aligned, with no S3 gene there:
`dd_Smed_v4_10005_0_1` (2_h1:230,846,233) and `dd_Smed_v4_101_0_1` (1_h1:20,086,613).

## 3. SmedASXL
The guess about low numbers is reversed: low numbers map well, high numbers do not.

| SmedASXL_ number | linked |
|---|---|
| 000000–019999 | 73–84 % |
| 020000 and up | 2–3 % |

The high-numbered transcripts mostly align where there is no gene; they are probably short or
non-coding. Examples: `SmedASXL_000017` did not align to the genome; `SmedASXL_000036` aligned
(1_h1:220,411,622) with no S3 gene there.

## 4. OX_Smed_1.0
`OX_Smed_1.0.N` is only a rename of `ox_Smed_v2_N` from the ox_Smed_v2 FASTA. That FASTA has 21,411
of the numbers up to 23,545; `ox_Smed_v2_00005` is not one of them, so `OX_Smed_1.0.00005` was
**never in the input**. The Kao 2013 IDs that fail are probably all numbers missing from v2.

## 5. Contig names — two assemblies share the names; the crosswalk keeps them apart
**Nothing is overwritten.** Every crosswalk row carries its source (`map.accession, source,
ref_id, hub`), and every build step keys on source + ID. The same name from two sources is
two rows:

    newmark_ests | Contig1038     | h1SMcG0000584
    uc_Smed_v2   | Contig1038     | h1SMcG0000584
    GPL15192     | Contig1038     | h1SMcG0018533
    GPL10652     | Contig1038_SE3 | h1SMcG0018533

**The two assemblies:**

| assembly | crosswalk sources | Contig numbers |
|---|---|---|
| A: Newmark ESTs | `newmark_ests`, `uc_Smed_v2` (the same sequences under two names; they agree on 25,020 of 25,132) | Contig1 – Contig55949 |
| B: "SE3" | microarray platforms `GPL10652` (written `ContigN_SE3`), `GPL15192`, `GPL15193` (they agree with each other: 4,978/5,153 and 296/296) | Contig1 – Contig7727 |

A and B agree on **0** genes for the same name: the same number is a different gene in each.

**What the web needs:**
1. **Look IDs up by source + ID:** `WHERE accession_lc = ? AND source = ?`, using a paper →
   source table. Never take the first row of a name-only lookup for a `Contig` ID.
2. **Contig numbers above 7727 are always assembly A.** Look them up in `newmark_ests`; either
   A source gives the same gene. This covers the 1,348 Fincher 2018 IDs (e.g. `Contig10252`);
   that ID maps in the 11-18 build (newmark_ests / uc_Smed_v2 → h1SMcG0008136).
3. **Contig7727 and below are ambiguous.** The paper's methods have to say which assembly it used.
   A check from the data: for each paper, look up its Contig IDs in A and in B and compare the
   genes with the gene names in the PAGE records; the right assembly should agree far more often.
   Compute can run that check given the TSV of unmapped IDs (paper, ID, record gene name).

## Possible compute-side changes (not done; to decide)
- Add the full dd_Smed_v6 transcriptome as a query → maps the 10,929 v6 transcripts missing from
  the MOOP `ddv6` set.
- Add schMedA2h1 as a third reference → covers genes that S3 lacks ("no S3 gene there", the
  largest group above), at the cost of one more reference to keep up to date.
- Small fix in `build_crosswalk.sh` step 3 (fallback): it checks "does this ID already map?" by
  ID alone, not source + ID. Rarely matters.

Please send the full TSV of unmapped IDs (paper, ID, record count, and the gene name the
record gives, if any). Compute will then count each case per paper and run the Contig check.
