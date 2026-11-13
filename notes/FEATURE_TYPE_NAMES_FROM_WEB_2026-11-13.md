# Feature type names differ between gene sets — note for the compute side (web, 2026-11-13)

**Status:** worked around on the web side; probably wants a fix upstream (loader / GFF normalisation,
`config/build_and_load_db/**`, compute-owned). Nothing here is urgent; it is a source of silent misses.

## What happened

The navbar / home-page ID lookup (`api/feature_search.php`) searched only `gene, mRNA, protein,
polypeptide`. `SMED30004304` (pcna, smed_20140614) is stored as `feature_type = 'transcript'`, so it —
and every transcript id in 6 organisms, 307,926 features — could never be found. CDS ids were not
searched anywhere. Fixed on the web side by dropping the type filter (an exact-id lookup has no reason to
filter by type). Earlier, the same class broke annotation search and transcript counts; the web side now
asks the database which types it has (`moop_transcript_types()`, `moop_annotation_levels()`,
`lib/feature_types.php`) instead of assuming `mRNA`. Every new place that assumes a type name is a new miss.

## What the databases hold (organism.sqlite, feature_type counts)

| Kind | Organisms | Detail |
|---|---|---|
| 1. A whole gene set types protein-coding transcripts `transcript`, not `mRNA` | Schmidtea_polychroa, Turritopsis_dohrnii, Schmidtea_mediterranea (smed_20140614: 243,645 `transcript`; its other gene sets use `mRNA`) | Same biology, different word: whatever the depositor's GFF said |
| 2. A few `transcript` rows beside `mRNA` in one gene set | Notamacropus_eugenii 3,505 · Parastichopus_parvimensis 2,567 · Petromyzon_marinus 49 | Probably non-coding transcripts. `decision_noncoding_genes_jbrowse_only` says non-coding genes stay in GFF/JBrowse, NOT in organism.sqlite — check whether these slipped through |
| 3. Same type, two spellings | Danio_rerio: `CDS` 45,910 and `cds` 94,624 | Case differs inside ONE database |

## Suggested upstream fix (for compute to decide)

- Normalise at load time: protein-coding transcript -> `mRNA`, coding sequence -> `cds` (one case), keep
  the depositor's original type in a column or attribute if it matters. Original IDs stay original
  (`feedback_original_data_stays_original`) — this is about the TYPE word only.
- Kind 2: decide whether non-coding `transcript` rows belong in organism.sqlite at all.
- Kind 3: find which input gave `CDS` vs `cds` for Danio (two GFF sources? a later add?).
- A loader check that lists the distinct `feature_type` values per gene set and fails/warns on anything
  outside the agreed set would stop new variants arriving silently.

The web side will keep reading types from the database either way; normalising just removes a class of
surprise for every new page and tool.
