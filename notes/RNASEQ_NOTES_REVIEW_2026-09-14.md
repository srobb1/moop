# Review of the RNA-seq / expression tool notes — 2026-09-14

Status: **review only.** Nothing was changed in code. Every number below was measured against
the live tree on 2026-09-14 (track JSONs, `metadata/track_sheets_reformatted.xlsx`, the Nvec
data directory, and the registration code), not copied from the notes.

---

## 1. The notes

There are four notes, all written on **2026-07-14**, and **none of them has been built.**

| Note | What it covers |
|---|---|
| `notes/EXPRESSION_DATA_LAYER_PLAN.md` | The shared base layer. Reads bigWigs from the tracks server, scores genes offline with `bigWigAverageOverBed`, saves the results in a per-assembly `expression.sqlite`. No UI. |
| `notes/EXPRESSION_GENE_PAGE_PLAN.md` | One gene × all tracks, as a section on the gene page. Live reads would take ~16 min per page, so it needs the precomputed results first. |
| `notes/EXPRESSION_EXPLORER_PLAN.md` | A standalone tool: a gene list × chosen experiments → heatmap or bar chart. |
| `notes/SRA_RNASEQ_DISCOVERY_PLAN.md` | Finding public RNA-seq for an organism's taxon ID through the ENA API. Only feeds new data in; displays nothing. |

Related notes that touch the same work:

- `notes/NEW_TOOL_SURVEY_AND_RECOMMENDATIONS.md` (2026-08-06) — lists "Expression browser" as a
  gap, "planned". Ranks orthology first.
- `notes/JBROWSE_43_OPPORTUNITIES.md` — mentions JBrowse's hierarchical tree sidebar for
  multiwiggle clusterings as relevant, and searchable faceted track metadata.
- Memory: tracks-server curation idea — per-track metadata has no pipeline / normalization /
  aligner / strandedness record.
- Memory: launch readiness (2026-07-22) — named the Expression Explorer as the recommended
  one "delight" feature for launch.

---

## 2. What still holds

- **Nothing contradicts the core design:** precompute per assembly, zero bigWig reads in the
  page request, store raw values and scale them only for display.
- **Coordinates from `feature_coords.tsv`** — still correct.
  `organisms/Nematostella_vectensis/GCA_033964005.1/NV2/feature_coords.tsv` has 107,643 rows,
  24,526 distinct genes; 71,762 rows are `:cds`/`:pep` duplicates to skip. Chromosome names
  (`chr1`, `chr14`, `chrUn3`, …) match the bigWigs.
- **Track JSONs as the experiment catalog** — still correct. 17 (source, experiment) groups for
  Nvec RNA-seq.
- **Exon blocks are available** for an exon-only average: `NV2/genes.gff` has 287,079 exon lines.
- **The security warnings stand** — never echo `bigWigSummary`'s stderr (it contains the JWT);
  pass `-udcDir` explicitly because php-fpm has PrivateTmp.
- **The prerequisites are all still missing:**
  - `bigWigAverageOverBed` is not installed (only `/usr/local/bin/bigWigSummary`).
  - `generateTrackToken()` in `lib/jbrowse/track_token.php` still sets `'iat' => time()`, so
    the udc cache key problem is unfixed.
  - `/var/www/moop-cache/udc` does not exist.
  - No `tools/expression.php`, `api/expression/`, `scripts/build_expression_matrix.php` or
    `js/modules/expression.js`.

---

## 3. What is wrong or out of date

### 3.1 Expression data is registered for only ONE assembly

Registered RNA-seq bigWig tracks (`metadata/jbrowse2-configs/tracks/*/*/bigwig/*.json`):

| Assembly | RNA-seq tracks |
|---|---|
| Nematostella_vectensis / GCA_033964005.1 | 966 |
| Scolanthus_callimorphus / Scal100 | 1 |

No other assembly has a `bigwig/` directory at all.

The track sheets (`metadata/track_sheets_reformatted.xlsx`) list RNA-seq for about **9 more
organisms that were never registered in MOOP**:

| Sheet | RNA-seq rows | + Overlay rows |
|---|---|---|
| Nematostella_vectensis_GCA_033964005.1 | 1,452 | 181 |
| Nothobranchius_furzeri_GCF_001465895.1 | 312 | 74 |
| Montipora_capitata_Mcap_2019 | 239 | 36 |
| Entosphenus_tridentatus_JAAVTP000000000.2 | 141 | 22 |
| Lampetra_richardsoni_LPT | 141 | 22 |
| Petromyzon_marinus_GCF_010993605.1 | 141 (+34 "RNAseq") | 20 (+4) |
| Congeria_kusceri_GCA_027627225.1 | 30 | 9 |
| Chamaeleo_calyptratus_CCA1 | 27 | 7 |
| Schmidtea_mediterranea_GCA_000691995.1 | 2 | — |

**Consequence:** as things stand, the expression feature would serve 1 of 85 organisms. Every
note is written around Nvec's numbers without saying so.

Note also the technique label is inconsistent in the sheets (`RNASeq` vs `RNAseq`) — a catalog
reader filtering on `technique == "RNASeq"` exactly would miss the Nothobranchius and some
Petromyzon rows once they are registered.

### 3.2 966 tracks is not 966 samples, and the strand rule misses a third of them

The data-layer note (§4.3) says: *pick `.pos.bw` / `.neg.bw` by the gene's strand.* Breakdown of
the 966 Nvec RNA-seq files by filename:

| Filename pattern | Files | Stranded? |
|---|---|---|
| `*.pos.bw` | 320 | yes — pairs with `.neg.bw` |
| `*.neg.bw` | 320 | yes |
| `*.Signal.Unique.str1.out.bw` (Leach_2019, STAR) | 298 | **no `str2` partner** |
| `PRJNA189768.NN.bw` | 28 | unstranded |

- The 298 Leach_2019 files have **no `str2` counterpart anywhere in the track sheet** (checked:
  596 `Unique.str1` matches = 298 rows × FILENAME + TRACK_PATH; zero `str2`, zero
  `UniqueMultiple`). STAR's unstranded mode writes only `str1`, so these are probably
  unstranded — **unverified**.
- So the real count is roughly **646 samples**, not 966 columns. The pos/neg rule covers only
  640 files.
- Which files pair up is recorded **only in filenames** (and the track `name`, e.g.
  `"S1 body_wall +"`). There is no pairing field in the metadata.

This affects the storage design in data-layer §6b (one column per track) and the gene-page
display (a sample should show one strand-resolved number, not two).

### 3.3 The grouping metadata has gaps

From the 966 Nvec RNA-seq track JSONs (`metadata.google_sheets_metadata`):

| Field | Present |
|---|---|
| technique, institute, source | 966 / 966 |
| experiment | 946 / 966 — the 20 Bazzini Lab tracks have none |
| summary | 744 |
| condition | 580 |
| tissue | 132 |
| **neither tissue nor condition** | **290** |

- `experiment` values are whole sentences ("To analyze the transcriptome profile in each tissue
  type. To identify tissue-specific genes in the oral disk region."), not short labels — they
  will not work as tree node labels without a short name.
- **232 filenames contain colons** (the Leach_2019 set, e.g.
  `dark:dark_time_point_10_..._SAMN11960172.Signal.Unique.str1.out.bw`).
- `access_level` is mixed case: `Public` 938, `COLLABORATOR` 28. The catalog reader must use the
  normalized lookup — this exact mismatch caused the earlier fail-open track-access bug.

The 17 groups, for reference:

| Tracks | Source | Access | Experiment (truncated) |
|---|---|---|---|
| 246 | Yanai Lab | Public | The mid-developmental transition and the evolution of animal body plan |
| 156 | Smith Lab | Public | RNAseq, high-density embryonic time series (hourly 0-19h) |
| 136 | WLeach | Public | Transcriptional remodeling upon light removal in a model cnidarian |
| 96 | WLeach | Public | Decoupling behavioral and transcriptional responses to color |
| 66 | WLeach | Public | Global temporal gene expression of Nematostella vectensis in-situ |
| 36 | Gibson Lab CYC | Public | Compare RNA expression among three tissues |
| 36 | Gibson Lab EBA | Public | Identify sex specific genes in nematostella |
| 36 | GibsonLab RZ1989 | Public | Transcripts differentially expressed in foxA mutant vs wt |
| 28 | Gibson Lab SHE | COLLABORATOR | Differential gene expression profiling |
| 26 | Smith Lab | Public | A quantitative reference transcriptome for Nvec early development |
| 24 | GibsonLab RZ1989 | Public | Transcriptome profile in each tissue type |
| 20 | Bazzini Lab arb | Public | *(none)* |
| 16 | Gibson Lab KAR | Public | Changing expression levels of each transcript during development |
| 16 | Lotan Lab | Public | Early and late response of Nvec transcriptome to heat |
| 14 | Technau Lab | Public | Evolutionary conservation of the eumetazoan gene regulatory landscape |
| 12 | Dunn Lab | Public | Differential transcript abundance through time |
| 2 | Zilberman Lab | Public | Genome-wide evolutionary analysis of eukaryotic DNA methylation |

### 3.4 Stale reasoning inside the notes

- **`organism.sqlite` size:** data-layer §5 says Nvec's is 722 MB. It is now **395 MB** (the
  2026-07-30 reload). The argument for a separate file is unchanged.
- **§6e says `organisms/` is SELinux read-only** to the web server. That is wrong — read-only was
  reverted and `organisms/` is writable by design (CLAUDE.md §11). The conclusion still holds for
  a different reason: `expression.sqlite` is a regenerable cache, and caches belong under
  `cache_path`, not in the data tree.
- **§5 contradicts §6b.** §5 says store the table "sparse (skip no-signal cells)". §6b says
  encode no-data as NaN and measured-zero as 0.0, precisely because a sparse table cannot tell
  them apart. §6b is the right one; §5 should be corrected.
- **Explorer note:** says to fan out with `proc_open()` "the same pattern `AnnotationSearch`
  already uses". It does not — annotation search fans out **in JavaScript**, one request per
  organism, concurrency 5 (`js/modules/annotation-search.js:559`; MOOPmart does the same at
  `js/modules/moopmart.js:850`).
- **Explorer note names Chart.js** for rendering. **No chart library is bundled** — `js/vendor/`
  holds only jQuery, jQuery UI, Bootstrap, DataTables and JSZip. Any library must be vendored
  (CSP is `script-src 'self'`, no CDNs), and a heatmap needs more than stock Chart.js provides.
- **Memory file** for this plan still described the early `(gene_id, track_id, value)` sparse
  table in one section and the per-gene BLOB design in another. (Memory has been updated with this
  review.)

### 3.5 The SRA note's provenance claim

`SRA_RNASEQ_DISCOVERY_PLAN.md` says the align → quantify → bigWig chain "is a standard Galaxy
workflow and is exactly what already produces the ~1051 Nvec bigWigs." The file paths suggest
**several different pipelines**, not one:

| Path component | Files | Looks like |
|---|---|---|
| `bulk_align/MOLNG-*` | 608 | Stowers core run IDs |
| `Leach_2019/GPL23802/PRJNA546501/...Signal.Unique.str1.out.bw` | 298 | STAR, study/BioProject layout |
| `RNAseq/MOLNG-2707/...` | 60 | another core layout |

That matters because the normalization question (data-layer §6c) depends on how many pipelines
there were.

Everything else in the SRA note still holds. Phase 1 (an ENA "public RNA-seq for this organism"
panel) is cheap and useful on its own; the taxon ID is stored (`organism.json` →
`"taxon_id": "45351"` for Nvec).

---

## 4. A bug this review turned up — fixed in code 2026-09-14, tracks not yet regenerated

*Fix:* `GoogleSheetsParser::normalizeColumnName()` (hyphens and spaces → underscores) and
`GoogleSheetsParser::METADATA_FIELDS` (every field any track type publishes), with a smoke-test
group that fails if a track type ever lists a field the parser drops. Against the live Nvec sheet,
tracks carrying a developmental stage went from 0 to 1,516. Existing track JSONs only change when
regenerated — see the warning in `EXPRESSION_GENE_PAGE_PLAN.md`, build order step 1.

**Track registration silently throws away sheet metadata.** Two separate drops, both in
`lib/jbrowse/GoogleSheetsParser.php`:

1. **Header name mismatch.** Headers are only lowercased (`array_map('strtolower', $header)`,
   lines 76 and 215). The sheet column `DEVELOPMENTAL-STAGE` therefore becomes
   `developmental-stage`, but `cleanTrackData()` (line 382) reads `$row['developmental_stage']`
   — with an underscore. It never matches, so the field is always empty.
2. **Fields never passed through.** `cleanTrackData()` (lines 360–387) returns only technique,
   institute, source, experiment, developmental_stage, tissue, condition, summary. It never
   passes `citation`, `project`, `accession`, `date` or `analyst` — even though
   `lib/jbrowse/TrackTypes/BigWigTrack.php:217-222` lists all of them as fields to store.

How much data is being lost, from the Nvec sheet (1,452 RNA-seq rows):

| Sheet column | Filled rows | In the track JSONs |
|---|---|---|
| DEVELOPMENTAL-STAGE | 1,138 | **0** |
| ACCESSION | 1,270 | **0** |
| CITATION | 1,108 | **0** |
| PROJECT | 1,452 | **0** |
| DATE | 914 | **0** |
| ANALYST | 914 | **0** |

Why it matters:

- **Developmental stage** is the grouping for five experiments (both Smith sets, including the
  156-track hourly series; Gibson KAR; Technau; Dunn) and half of it for three more — exactly what
  the expression picker needs. It does **not** help the largest, Yanai (246 tracks): its rows have
  no stage even in the sheet. *(Corrected 2026-09-14 — an earlier version of this note said it did.
  Per-experiment survey in `EXPRESSION_GENE_PAGE_PLAN.md`.)*
- **Accession** is what would link a track to ENA/SRA (the SRA discovery note).
- **It hurts JBrowse today**, not just the expression plans — the faceted track selector can only
  search metadata that reaches the config.

The fix is small (normalize `-` and spaces to `_` in header names; pass the missing fields
through), but existing tracks would need to be regenerated to pick the fields up. The other
TrackTypes classes (`GFFTrack`, `BEDTrack`, `GTFTrack`, `PAFTrack`, …) list the same fields, so
they are affected the same way.

---

## 5. A design question the notes never weigh: coverage vs. counts

The whole plan works out expression from **mean bigWig coverage** over a gene. The pipelines
that produced those bigWigs very probably also produced **gene count / TPM tables** (STAR
`--quantMode GeneCounts`, featureCounts, salmon, …).

| | Mean bigWig coverage (current plan) | Pipeline count / TPM tables |
|---|---|---|
| Exon-aware | only if scored with BED12 blocks | yes |
| Library-size normalized | unknown, likely inconsistent across pipelines | TPM/CPM are, by definition |
| Cost to build | ~966 passes over HTTPS, est. ~100 GB / ~7 h | read small text files |
| Tied to an annotation | **no** — can be rescored against any gene set | **yes** — whatever gene set was used then; if it isn't NV2, the IDs won't match |
| Available for new data (SRA plan) | yes | yes, if the pipeline emits them |

Neither is obviously better — but it is the biggest open design choice, and it should be settled
**before** building the expensive precompute. First step: ask the data owners which count tables
exist for the 17 experiments and which annotation they were quantified against.

---

## 6. Smaller technical points

- **BED12 / introns (data-layer open question).** As I recall from `bigWigAverageOverBed`'s usage
  text, it scores "each bed, which may have introns" — i.e. it honours BED12 exon blocks, so an
  exon-only average costs the same single pass. Its output columns are
  `name size covered sum mean0 mean`; `covered = 0` gives a clean NaN signal, and `mean0` vs
  `mean` is "uncovered bases count as zero" vs "covered bases only". **Confirm all of this once
  it is installed.**
- **Sanity gate (data-layer §6g).** Comparing precomputed values against live `bigWigSummary`
  may not match exactly: `bigWigSummary`'s default mean is over covered bases and, I believe, can
  be answered from zoom-level summaries, which are approximate. Compare against `mean` (not
  `mean0`) and allow a tolerance, or the gate will raise false alarms.
- **Token rounding (data-layer §4.2).** Do **not** round `iat` inside `generateTrackToken()`
  for everyone — JBrowse calls it on every track request. Rounding carelessly also hands out
  tokens that are about to expire (a token minted at :59 with `exp = rounded iat + 3600` lives one
  minute). Add a separate minting path for server-side reads, e.g. `iat = start of the hour`,
  `exp = iat + 2 h`, and keep the verifier's 60 s leeway in mind.
- **Bandwidth estimate (data-layer §6a)** is still unmeasured — bigWig sizes and MOOP → tracks
  throughput are both guesses. It remains the first thing to measure.

---

## 7. Where this sits in priorities

- The launch-readiness plan (2026-07-22) named the **Expression Explorer** as the one recommended
  delight feature.
- The new-tool survey (2026-08-06) ranked **orthology** first; the **primer tools** have shipped
  since.
- **Best argument for doing expression now:** Nvec **NV2 is one of the two public gene sets**, so
  a gene-page expression section would be seen by public visitors, on the organism being
  showcased. That favours the **gene-page section** over the standalone Explorer as the first
  deliverable.
- **Best argument against:** it serves 1 of 85 organisms until the ~9 other organisms' RNA-seq
  tracks are registered — and registering them would currently lose their stage/accession
  metadata (§4).

---

## 8. Suggested order, if picked up

1. **Fix the metadata-dropping bug** (§4) and regenerate tracks — helps JBrowse now, and the
   expression tree needs developmental stage.
2. **Ask the data owners** about count tables and strandedness of the Leach_2019 set (§3.2, §5).
3. **Correct the four notes** with §3's findings.
4. Then build per `EXPRESSION_DATA_LAYER_PLAN.md`'s own order: catalog reader → live read path →
   install `bigWigAverageOverBed` and measure one file → precompute → gene-page section.

## 9. Open question for you

Should I (a) fold these corrections into the four notes, (b) fix the `GoogleSheetsParser`
metadata bug first, or both?
