# Gene-page expression section — one gene × chosen experiments

**Status:** idea captured 2026-07-14 (user). **Design revised 2026-09-14** (user): the reader chooses
which experiments to show, and the section is built from the same modules as the Expression
Explorer. Not started. **Blocked on the precompute** in `EXPRESSION_DATA_LAYER_PLAN.md` — choosing
fewer experiments does not remove that (see below).

Related: `EXPRESSION_EXPLORER_PLAN.md` (the other page built on these modules) and
`RNASEQ_NOTES_REVIEW_2026-09-14.md` (what is out of date in the older notes, and the bug that drops
track-sheet metadata).

## Idea

On a gene's page, the reader picks a few RNA-seq experiments and sees this gene's expression in each
of them. The choice follows them from gene to gene. "Open in Explorer" takes the gene and the same
experiments to the Explorer, where more genes can be added and compared as a heatmap.

## One set of modules, two pages

**The gene page is the Explorer with its gene list fixed to one gene** — a matrix with one row. So the
catalog, the data request, the picker and the charts are built once and shared; each page only
supplies the gene list. They are specified here because the gene page ships first; the Explorer
note describes only what it adds.

```
Shared
  lib/expression/Catalog.php         experiments → samples (strand pairs merged, access-filtered)
  api/expression/matrix.php          { organism, assembly, gene_set, gene_ids[], sample_ids[] } → genes × samples
  js/modules/expression-picker.js    choose experiments
  js/modules/expression-chart.js     1 gene → one chart per experiment; many genes → heatmap

Gene page section     gene_ids = [this gene]              + "Open in Explorer"
Expression Explorer   gene_ids = pasted or handed-off list
```

File names are proposals, following the layout the Explorer note already chose.

## Why the precompute is still required

A live read costs ~1 s **per file** when cold, because the udc cache warms per file (data-layer §5).
Choosing experiments cuts the number of files, not that cost. Even reading only the strand that
matches the gene:

| Experiment | Samples = files read | Live, cold |
|---|---|---|
| Dunn Lab | 12 | ~12 s |
| Smith Lab hourly series | 78 | ~1.3 min |
| Yanai Lab | 123 | ~2 min |
| All 17 | ~646 | ~11 min |

So the section reads one precomputed row per gene (`expression.sqlite`, data-layer §6b), and live
`bigWigSummary` stays a prototype and spot-check. A side benefit: that one row holds every sample, so
the picker can mark experiments with **no data for this gene** at no extra cost.

## Shared component 1 — the catalog: experiments → samples

Built from the track JSONs (data-layer §2). Four things the older notes did not account for:

- **The unit is the sample, not the track.** Nvec's 966 RNA-seq tracks are ~646 samples: 320
  `.pos.bw`/`.neg.bw` pairs plus 326 unstranded files (review §3.2). For a stranded sample the matrix
  API returns the value from the file matching the gene's strand (`feature_coords.tsv` column 6).
- **Pair strands by filename, never by display name.** Filenames pair consistently; display names use
  `.pos`/`.neg`, ` +`/` -`, or nothing — Zilberman's pair is two tracks both named `PRJNA122153.rs.1`.
- **Sample ids are catalog ids, not filenames** — 232 Leach filenames contain colons.
- **Access is filtered here, on the server.** Gibson Lab SHE (28 tracks) is `COLLABORATOR`; the other
  938 are `Public` — mixed case. Use `required_access_level_value()` (`lib/functions_access.php`,
  which normalizes case), the same per-track check the JBrowse config applies
  (`lib/jbrowse/config_functions.php:98`), plus `has_assembly_access()`. The matrix API re-applies
  it: never trust a sample list sent by the browser.

### What each track must tell the catalog

To draw a chart the catalog needs, **per track**: sample, strand, factor values (stage, genotype, …)
and replicate. **Per experiment**: a short label, which factor goes on the x-axis and which is colour,
and the order of the values. Today that is scattered across metadata fields, sheet columns the parser
drops, and display names — differently in every experiment. Surveyed 2026-09-14 from the track JSONs
and the live registered Google Sheet (the committed `metadata/track_sheets_reformatted.xlsx` is an
older copy):

| Tracks | Experiment | Strand | Where the grouping is today |
|---|---|---|---|
| 246 | Yanai Lab — mid-developmental transition | pairs | **Nowhere.** Names are `Metazome_NV_timecourse_sample_0001`; no stage, condition or tissue in the JSON **or the sheet**. Each row does carry an SRR `accession` (dropped by the parser). |
| 156 | Smith Lab — hourly embryonic series | pairs | Stage in the sheet (`egg, 0hpf`, `embryo, 12hpf`, …; dropped) and the name (`0hpf-A-1`). `condition` is "time post fertilization" on every row — an axis title, not a value. |
| 136 | WLeach — light removal | unstranded | `condition`, 34 values, regime and time in one string (`dark:dark T1`); replicate letter only in the name |
| 96 | WLeach — light colour | unstranded | `condition`, 24 values (`blue light:dark T1`); replicate only in the name |
| 66 | WLeach — in-situ time course | unstranded | `condition` is only `individual`/`pooled`; time and replicate only in the name (`individual 10AM:T3 repA`) |
| 36 | Gibson Lab CYC — three tissues | pairs | `tissue` (3); sex in the sheet stage (dropped) and the name (`F-g-1`, `M-mus-3`) |
| 36 | Gibson Lab EBA — sex-specific genes | pairs | `tissue` (3); sex in the sheet stage (dropped) |
| 36 | GibsonLab RZ1989 — foxA mutant | pairs | Genotype only in the name (`foxAp4_48hpf_1 +`); stage in the name and the sheet (`embryo, 48hpf`, dropped). The live sheet now names this source `GibsonLab EMH`. |
| 28 | Gibson Lab SHE — knockdowns (COLLABORATOR) | pairs | `condition` (7: `Control`, `hox1a-sh1`, …) |
| 26 | Smith Lab — reference transcriptome | pairs | Stage in the sheet (dropped) and the name (`0hpf-1`) |
| 24 | GibsonLab RZ1989 — tissues | pairs | `tissue` (4) |
| 20 | Bazzini Lab | pairs | `condition` (10), time and treatment in one string (`2h_a_amanitin`); the sheet's stage repeats the time (`embryo, 2hpf`, dropped); no `experiment` at all |
| 16 | Gibson Lab KAR — development | pairs | Stage in the sheet (dropped) and the name (`POST-GASTRULA-2`) |
| 16 | Lotan Lab — metal response | unstranded | `condition` (5: `Hg`, `Cu`, `Cd`, …) |
| 14 | Technau Lab | pairs | Stage in the sheet (`embryo, 24 hpf, gastrulae (24h)`, dropped) and the name (`RNA_gastrula`) |
| 12 | Dunn Lab | unstranded | Stage in the sheet (`embryo, 2hpf zygote`, dropped) and the name (`zygote_2hfp-2`, sic) |
| 2 | Zilberman Lab | one pair | Nothing; a single sample |

What that means:

- **Fixing the parser (review §4) gives five experiments their grouping outright** — both Smith sets,
  KAR, Technau, Dunn — and half of it to three more (foxA's stage, CYC's and EBA's sex).
- **It does nothing for the largest experiment.** Yanai's time points are not recorded anywhere MOOP
  reads. The per-sample SRR accessions (project `PRJNA287810`, `PMID:26886793`) probably lead to them
  through ENA run metadata — unverified; otherwise ask the lab.
- **Most experiments have two factors** — genotype × stage, treatment × time, light regime × time,
  sex × tissue — often packed into one string or split between a field and the name. A chart wants
  one on the x-axis and the other as colour.
- **Even the stage field is composite.** Values read `embryo, 24 hpf, gastrulae (24h)` — life stage,
  time and a label in one cell — so the parser fix recovers the information, but not yet an axis.
- **Do not parse display names in code.** Every experiment would need its own parser, and the names
  carry typos (`2hfp`, `UnkownSex`, `tentancle`). Record the facts instead: per-track columns in the
  track sheet (sample, strand, replicate, one value per factor), and a small per-experiment file
  (short label, x-axis factor, colour factor, value order), e.g. `metadata/expression_experiments.json`.
  This is the metadata contract the tracks-server curation idea already calls for.
- **Open:** new sheet columns, or clean the existing `developmental-stage` / `tissue` / `condition`
  columns so each holds one value? Decide with whoever maintains the sheets.

## Shared component 2 — the matrix API

`POST api/expression/matrix.php { organism, assembly, gene_set, gene_ids[], sample_ids[] }` →

```json
{ "samples": ["s001", "s002", "s003"],
  "genes":   { "NV2g025931000.1": [12.4, 0.0, null] } }
```

- One number per sample, in the order of `samples`. `null` = no data, `0.0` = measured zero — the
  distinction data-layer §6b keeps on purpose. Charts must never draw `null` as zero.
- **Per gene set, not only per assembly** — gene ids belong to a gene set. (Data-layer §6e's
  `expression.sqlite` path stops at the assembly; add the gene set.)
- Gene ids are gene uniquenames: `feature_coords.tsv` column 2 (`NV2g025931000.1`) matches
  `feature.feature_uniquename` of type `gene`. But `feature_coords.tsv` has **no gene rows** — 35,881
  transcript rows for 24,526 genes — so the precompute must derive each gene's region (span of its
  transcripts, or the exon union from `genes.gff`; data-layer §6c).
- Production reads only the precomputed file. The live path can sit behind the same endpoint, capped,
  for the prototype and the spot-check (data-layer §6g).
- **It hides where the numbers come from.** Whether values end up as bigWig coverage or pipeline
  count/TPM tables (review §5) changes the precompute, not either page.

## Shared component 3 — the experiment picker

- Lists the experiments this user may see: short label (the `experiment` field is a whole sentence,
  and Bazzini has none), sample count, and a no-data mark for the current gene.
- **Remembers the choice across gene pages**, per assembly, in `localStorage` — the same per-viewer
  convenience as the collapsed nav sidebar (`js/modules/parent-nav.js:305`): wrapped in try/catch, and
  the page works without it. Someone who cares about the Smith series picks it once and sees it on
  every gene.
- **Open: what a first-time visitor sees.** Most visitors never open a picker, so the default *is* the
  public view. (a) Nothing chosen, with a prompt; (b) a default set flagged in the per-experiment
  file; (c) the 2026-07-14 plan's strip with one summary row per experiment. Leaning (b).

## Shared component 4 — the chart

One module, two views, chosen by the number of genes:

- **One gene (gene page):** one small chart per chosen experiment, **each on its own y-axis**. x = the
  experiment's x-axis factor in its order, colour = the second factor, dots = replicates, a bar or
  line for the mean (a line for time series). Normalization across labs and pipelines is unverified
  (data-layer §6c), so nothing on the page may invite "higher in experiment A than in B".
- **Many genes (Explorer):** a heatmap — see `EXPRESSION_EXPLORER_PLAN.md`.
- Log-scale toggle. Stored values are raw means, scaled at display time (data-layer §6c).
- A "show numbers" table under each chart (DataTables, so export comes with it).
- **No chart library is bundled** — `js/vendor/` holds jQuery, jQuery UI, Bootstrap, DataTables with
  its Buttons/ColReorder extensions, and JSZip — and third-party code is self-hosted, never loaded from
  a CDN. Both views are rectangles and dots, so write them as SVG with no dependency. Vendor a library
  only if clustering or dendrograms become a requirement.

## The section on the gene page

- A card in `tools/pages/parent.php` using the existing section markup (`collapse-section`,
  `section-eyebrow`). `js/modules/parent-nav.js` builds the sidebar from the sections, so it appears
  there without extra work.
- Key it on `$ancestor_feature_uniquename`: the page walks up to the gene (`tools/parent.php:122-147`,
  parent types from `feature_types.parents`), so a page opened on an mRNA still shows its gene.
- **Render nothing** for gene sets without expression data — today all but two assemblies. No empty
  card.
- "Open in Explorer" posts the gene and the chosen experiments, the way Primer Maker hands off to Primer
  BLAST (`tools/pages/primer_maker.php:978`).

## Coverage today

RNA-seq tracks are registered for **one** of 85 organisms (Nvec, 966 tracks; Scolanthus has a single
track). NV2 is public, so public visitors would see the section. About 9 more organisms have RNA-seq
in the track sheets that was never registered — and registering it now would lose its stage and
accession columns (review §3.1, §4).

## Build order (both pages)

1. **Fix the parser** (review §4) — fixed in code 2026-09-14; the Nvec tracks are not yet
   regenerated. Record sample, strand, replicate and factors; regenerate. Settle Yanai's time points.
   ⚠️ Do not regenerate with the admin "Sync" button: with its default "Force regenerate" box it
   also runs `--clean`, which deletes the NV2 gene track, and any sheet run creates every
   unregistered row (201 combos for Nvec). Force only the already-registered track ids.
2. **Ask the data owners** whether gene count tables exist, and whether the Leach STAR set is
   unstranded (review §3.2, §5). The answer decides what step 4 builds.
3. **Catalog + picker.** No network; demoable against the track JSONs.
4. **Precompute + matrix API** (data-layer build order, steps 2–3).
5. **Gene-page section** with the one-gene chart.
6. **Explorer** — gene-list input and the heatmap view (`EXPRESSION_EXPLORER_PLAN.md`).

## Depends on

- `EXPRESSION_DATA_LAYER_PLAN.md` — catalog source, coordinates, precompute, `expression.sqlite`.
- `RNASEQ_NOTES_REVIEW_2026-09-14.md` — the metadata-dropping bug (§4), coverage vs. counts (§5).
- Sibling: `EXPRESSION_EXPLORER_PLAN.md`.
