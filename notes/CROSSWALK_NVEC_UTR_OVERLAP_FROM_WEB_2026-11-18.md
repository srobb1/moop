# Nematostella crosswalk: NV2 transcripts linked to unrelated RefSeq genes through UTR overlap (web → compute, 2026-11-18)

## What the site shows

The NV2 gene page for `NV2g015027000.1` shows the Hexacorallia ortholog card **twice**, with two
unrelated gene families: Sox (N0.HOG0013668) and NOXRED1 (N0.HOG0007655). OrthoFinder ran on RS_101 only;
NV2 genes reach orthogroups through the crosswalk (NV2 → RS_101 gene → its orthogroup). The crosswalk links
`NV2t015027001.1` to **two** RS_101 genes, so the page shows two orthogroups.

## What we found

| | Coordinates | Notes |
|---|---|---|
| `NV2t015027001.1` (NV2, GCA_033964005.1) | chr3:17741138-17749564 (-), **one 8.4 kb exon** | protein 204 aa |
| `5511097` LOC5511097 sox-3 (RS_101, GCF_932526225.1) | NC_064036.1:17478097-17479535 (-), 1.4 kb, one exon | protein XP_032236265.1, 204 aa, **identical to the NV2 protein** |
| `5511108` LOC5511108 NOXRED1 (RS_101) | NC_064036.1:17482048-17505171 (-), multi-exon | proteins 363-713 aa, unrelated to Sox |
| `NV2t015028001.1` (the next NV2 gene) | chr3:17757728-17761200 (-) | 507 aa; by position the likely NV2 match for NOXRED1 |

- The **Sox-3 link is right** (identical protein).
- The **NOXRED1 link looks like an artefact**: the NV2 model is one 8.4 kb exon, ~7 kb more than RefSeq's
  Sox-3, all UTR. That tail runs into the 3' end of the neighbouring NOXRED1 (same strand, next to Sox-3), so
  any genome overlap of whole transcripts/exons links them, although the coding sequences never overlap.

## How common

979 of the 28,017 NV2 transcripts in the crosswalk (3.5%) link to more than one RS_101 gene: 943 to two,
34 to three, 2 to four. Some may be real NV2 merges of two genes, but this case suggests many are UTR overlaps.
List them from the site's file:

```sql
-- organisms/Nematostella_vectensis/crosswalk.sqlite
SELECT accession, group_concat(ref_id) FROM map WHERE source = 'NV2'
 GROUP BY accession HAVING count(DISTINCT ref_id) > 1;
```

## Suggestion (for whoever builds the Nematostella crosswalk)

The website does not know how this crosswalk was made (NV2 and RS_101 are on different assemblies, so it
must involve a mapping between them). Suggested rule: **link two transcripts only when their CODING sequence
overlaps (CDS, not exons/UTRs)**, ideally on the same strand, with a minimum overlap fraction. If CDS
coordinates are not available across assemblies, a protein check (identical or best reciprocal hit) on the
candidate pairs would separate the two cases here (Sox-3 identical, NOXRED1 no similarity).

A transcript that still links to more than one gene after that is probably a real merged model: worth keeping,
but useful to flag (e.g. a column or a list), so the site can say "this NV2 gene spans two RefSeq genes".

## On the web side meanwhile

Nothing changed. The site could label each card with the RefSeq gene it came through
("via LOC5511097, sox-3") so two cards do not look like a duplicate run; it would follow the fixed file
automatically.
