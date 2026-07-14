# Expression data layer — the shared substrate

**Status:** feasibility **VERIFIED on this box 2026-07-14** (real reads against the live tracks
server). Not built yet.

This is the **foundation doc**. It has no UI. It defines where expression signal comes from and how
it gets into a queryable form. Two display features sit on top of it, and both depend on this being
right:

- `EXPRESSION_GENE_PAGE_PLAN.md` — **1 gene × many tracks** (gene/parent page section)
- `EXPRESSION_EXPLORER_PLAN.md` — **many genes × few experiments** (standalone tool)

Distinct from `SRA_RNASEQ_DISCOVERY_PLAN.md`, which is about *acquiring* new data from outside
(ENA/SRA → fastq → align → bigWig). That one **feeds** this layer; it is not part of it.

---

## 1. Can we read the bigWigs on the tracks server? Yes — proven

`bigWigSummary` on the MOOP box reads a remote bigWig through the normal token URL:

```
NV2g008251000.1  chr14:104516-105159 (-)  rc=0   1.05s  -> -0.0230214   (cold: TLS + header + index)
NV2g008252000.1  chr14:115370-121524 (+)  rc=255 0.15s  -> "no data in region"
NV2g008253000.1  chr14:144988-148223 (+)  rc=0   0.07s  -> -0.120114    (warm)
NV2g008254000.1  chr14:145121-148223 (-)  rc=0   0.05s  -> -0.120114    (warm)
```

### Correction to a stale note
Earlier notes said *"MOOP can't reach tracks:8080 — firewalled."* **Wrong.**
`https://172.16.2.31:8080/moop/` answers from MOOP in ~50 ms with a 403 from `tracks.php` (a real
reply — it's rejecting a token-less request, as designed). Use **`https://`**; plain `http://` to
:8080 returns 400 (it's a TLS port). What MOOP genuinely *can't* do is **push files** to tracks —
that still goes via cerebro.

### Auth needs no new code
Reuse the rewrite `addTokenToAdapterUrls()` already does (`api/jbrowse2/config.php`): split the raw
URI on `/data/tracks/`, mint a file-bound JWT, rebuild. Kent tools accept that URL directly.

```php
$marker    = '/data/tracks/';
$pos       = strpos($uri, $marker);
$base      = substr($uri, 0, $pos);
$file_path = substr($uri, $pos + strlen($marker));
$token     = generateTrackToken($org, $asm, $file_path);   // lib/jbrowse/track_token.php
$url       = $base . '/api/jbrowse2/tracks.php?file=' . urlencode($file_path)
           . '&token=' . urlencode($token);
```

---

## 2. The catalog: the JBrowse track JSONs

`metadata/jbrowse2-configs/tracks/{Organism}/{Assembly}/bigwig/*.json` **is** the experiment catalog —
no new metadata store needed. Each file carries both halves:

- `adapter.bigWigLocation.uri` → the bigWig to read
- `metadata.google_sheets_metadata` → `technique`, `source` (lab), `experiment`, `condition`,
  `tissue`, `summary` → the grouping tree
- `metadata.access_level` → honor it; filter tracks the user may not see

Nvec (`GCA_033964005.1`): **1051 tracks — 966 RNASeq**, 46 ChIPSeq, 32 small_RNASeq, 5 BisulfiteSeq,
2 BACSeq. The 966 RNASeq fall into **17 experiment groups** (GibsonLab foxAp4 vs WT, WLeach circadian,
Yanai mid-developmental, Smith hourly embryonic, …).

**Shared component #1: a catalog reader** — scan the JSONs, filter `technique == "RNASeq"` + access
level, group `source → experiment → condition/tissue`. Pure local file work, no network. Both display
features use this exact tree (the explorer as a selector, the gene page as row grouping).

---

## 3. Coordinates: NOT in SQLite

**Gotcha.** The `feature` table has *no* coordinate columns (only `feature_id, feature_name,
feature_description, organism_id, feature_type, feature_uniquename, parent_feature_id, gene_set_id`).
Coordinates live in the file the BLAST-linkout work generates:

`organisms/{Organism}/{Assembly}/{GeneSet}/feature_coords.tsv`
→ `transcript_id, gene_id, chrom, start, end, strand` (107,643 rows / **24,526 genes** for Nvec NV2)

- Rows with `:cds` / `:pep` suffixes repeat the same span — skip for gene-level signal.
- **Chrom names match** the bigWigs (`chr14` in both) — no mapping layer needed.
- Kent tools take **0-based BED** coords.

---

## 4. Gotchas — each one verified, each one bites

1. **php-fpm runs with `PrivateTmp=yes`.** It gets its own throwaway `/tmp`, so kent's default
   `/tmp/udcCache` is invisible/ephemeral there — and the existing `/tmp/udcCache` is `smr`-owned,
   which `apache` can't write anyway. **Always pass `-udcDir` explicitly**, at
   `/var/www/moop-cache/udc` (already `apache:apache` + `httpd_sys_rw_content_t` from the `cache_path`
   work).

2. **The JWT poisons the udc cache key.** Kent hashes the *full URL — token included* — into the cache
   dir name (observed: `…/tracks.phpQ3Ffile…/511ca06569e74be3b65fb88b241f45ed20f998ff/`). Every mint
   carries a fresh `iat`, so **every call is a new URL = a new cache dir**: the cache never hits across
   requests, every read pays the ~1 s cold cost, and the cache dir grows without bound.
   **Fix:** round `iat`/`exp` to the hour so a given file yields a byte-identical URL for an hour
   (1.05 s → 0.05 s, bounded dirs). Prune via a `housekeeping.php` task, mirroring the temp-file one.
   Within a batch, mint **one token per file** and reuse it for every region.

3. **`.neg.bw` holds negative values** (`-0.0230`, `-0.120`). `abs()` before use, and pick
   `.pos.bw` / `.neg.bw` by the gene's `strand`. Most tracks are stranded pairs.

4. **"No data in region" is `rc=255`, message on stderr** — not the documented `"n/a"` on stdout.
   Treat any non-zero rc as `null`, and **never echo the message**: it contains the full URL
   *including the JWT*, which would leak the token into logs/UI.

5. **Only `bigWigSummary` is installed** (`/usr/local/bin/`). No `bigWigInfo` (`rc=127`), and — the
   one that matters — **no `bigWigAverageOverBed`**.

---

## 5. The decisive constraint: precompute, don't query live

`bigWigSummary` is **one call per (gene × region)**, and — critically — **udc warms per FILE**, so
every *new* track is a cold ~1 s read. That kills live querying for the gene page outright:

| Shape | Calls | Live cost |
|---|---|---|
| Explorer: 20 genes × 24 tracks | 480 | ~50 s serial — too slow |
| **Gene page: 1 gene × 966 tracks** | 966, **all cold** | **~16 min — disqualified** |

**`bigWigAverageOverBed` inverts this.** It scores a **whole BED of regions against one file in a
single pass**, so the *entire* matrix costs **one pass per track (966)** — not 966 × 24,526 reads.
That makes the full precompute a tractable offline batch, and it is the step that unlocks the gene
page.

**Shared component #2: `expression.sqlite`, precomputed per assembly.**

- Table `(gene_id, track_id, value)`, indexed on `gene_id`.
- Stored **outside** `organism.sqlite` (already 722 MB). Sparse (skip no-signal cells) + rounded to a
  few significant figures.
- Rebuilt only when tracks change; parallelize the per-track passes at ~8.
- ⚠️ **Unmeasured:** `bigWigAverageOverBed` isn't installed, so per-file pass time is a guess.
  **Install it and time a single file before committing to a 966-track run.**

**Payoff:** both display features become a **single indexed SELECT — zero bigWig reads in the request
path.** The gene page renders in milliseconds; the explorer's whole fan-out/query-cap problem simply
evaporates. Live `bigWigSummary` (§1) drops back to being a **prototype and spot-check tool**, not the
production path.

---

---

## 6. Precompute design — thoughts, decisions, open questions

### 6a. Bandwidth is the real cost, not CPU

The 966 bigWigs live on the tracks box; the precompute reads them **over HTTPS byte-range**. Scoring
24,526 gene regions spread across the whole genome means effectively pulling most of each file's data
section. Order-of-magnitude: if a bigWig averages ~200 MB and we touch half of it, that's ~100 MB ×
966 ≈ **~100 GB across the network**, all funnelled through the one MOOP box. At the ~3.8 MB/s
observed during the tracks-proxy validation, that's **≈ 7 hours** — an overnight batch, not a
coffee break. (That 3.8 MB/s was a proxied stream and may not be the ceiling — **measure the real
throughput on one file first**; the whole estimate hinges on it.)

**This reframes the "where do we run it?" question.** Cheapest fix is to not move the bytes at all:

- **Best — run the precompute where the bigWigs are local**, i.e. on the tracks box or on whatever
  pipeline/Galaxy host generated them, and ship back the resulting `expression.sqlite` (~100 MB, one
  file). Turns ~100 GB of network into ~100 MB. We can't *push* code to tracks (see §1), so this needs
  a human hand — but it's one script, run once per assembly.
- **Acceptable — run it on MOOP overnight.** No coordination needed, just slow. Fine for a first pass.
- Either way it's a **one-time-per-assembly** cost, incremental thereafter (§6d).

### 6b. Storage: per-gene float32 BLOB, not 24 M rows

The obvious `(gene_id, track_id, value)` table is 24,526 × 966 ≈ **23.7 M rows** — with an index,
plausibly ~1 GB. Better shape, given both query patterns:

> **One row per gene**, `value` = a packed **float32 array** of length `n_tracks`, with a separate
> `track` table fixing each track's **column ordinal**.

- **Size:** 966 × 4 bytes ≈ 3.9 KB/gene → 24,526 genes ≈ **~95 MB**. An order of magnitude smaller.
- **Gene page** (1 gene × all tracks) = **one row read**, unpack, done. Perfect fit.
- **Explorer** (few genes × few tracks) = N row reads, slice the columns you want. Also fine.
- **Cost:** no SQL aggregation across tracks — you unpack in PHP. For these two features, that's a
  non-issue.
- **Encode no-data as NaN, measured-zero as 0.0** — a sparse/absent-row scheme can't tell "gene not
  covered by this experiment" from "expressed at zero", and that distinction is exactly what a
  heatmap must not get wrong (§4.4: rc=255 → no data).

### 6c. Store RAW means; normalize at display time

Two traps here, and I'd rather bank the raw number and keep options open:

- **Cross-library comparability.** Raw mean coverage is *not* comparable between tracks from different
  libraries/depths. Some of these are STAR `Signal.Unique.str1.out.bw` (STAR can emit RPM-normalized
  signal), others come from different pipelines (`MOLNG-1180.29.neg.bw`). **We do not currently know
  that normalization is consistent across the 966** — worth auditing per `source` before anyone reads
  biology into a cross-experiment comparison. Record whatever we learn in the `track` table.
- **Gene-body mean dilutes signal.** Averaging across the whole span includes **introns**, so a gene
  with large introns looks lower-expressed than it is. The fix is to average over the **exon union**
  — but `feature_coords.tsv` only has gene/transcript *spans*, not exons; exon blocks would have to
  come from `genes.gff` as a BED12.
  **Open question:** does `bigWigAverageOverBed` honor BED12 exon blocks, or does it score the whole
  span? Verify before choosing. If it does, use BED12 from `genes.gff` — same one-pass cost, much
  better number. If not, gene-body mean is a documented approximation.

So: **persist raw mean + the region model used**, and do log-scaling / per-gene z-scoring in the
display layer, where it's cheap and revisable.

### 6d. Invalidation and incremental rebuild

- Store a **fingerprint** of the track set (sorted file paths + sizes/mtimes) in the DB, mirroring the
  existing fingerprint pattern used elsewhere in the codebase. Stale fingerprint → rebuild needed;
  surface it on the admin dashboard rather than silently serving stale numbers.
- **Adding a track** appends a new column ordinal. With the BLOB layout that means rewriting every
  gene row — but that's ~95 MB of local writes, i.e. seconds. Only the *new* track's bigWig gets read
  over the network. Cheap. Don't over-engineer this.

### 6e. Where the file lives — it can NOT go under `organisms/`

`organisms/` is now **SELinux read-only** to the web server (except `organism.json`), per today's
hardening. `expression.sqlite` is derived and fully regenerable, so it belongs in the **cache tree**:

`{cache_path}/expression/{Organism}/{Assembly}/expression.sqlite` → `/var/www/moop-cache/expression/…`

Route it through `lib/cache_paths.php` like the other generated caches. Same dir also hosts the
`-udcDir` (§4.1).

### 6f. Should track registration write to `expression.sqlite`? (user question, 2026-07-14)

**Likely yes — and there's a precedent to copy.** `feature_coords.tsv` is already generated
*proactively at registration* (mtime-smart, no lazy fallback by design) for the BLAST linkouts. The
same instinct applies here, and the economics are good:

- Registering **one** track = **one** bigWig pass = **1/966th** of the batch. Minutes, not hours.
- It keeps the matrix continuously correct instead of drifting until someone remembers to rebuild —
  which is the failure mode the fingerprint in §6d exists to catch.
- It slots into the JBrowse track manager where the track's metadata is already in hand.

**Design notes / caveats to settle:**
- **Don't do it synchronously in the request.** A bigWig pass is minutes; background it and report
  progress (§6f-build), or queue it and let the admin trigger the run.
- **Column append = full BLOB rewrite** (§6d) — ~95 MB of local writes, seconds. Acceptable per
  registration, but if tracks are registered in **bulk** (the normal case — these arrived 966 at a
  time), do **one** rewrite at the end of the batch, not 966 of them. Registration hook should
  *enqueue*, and a single pass should drain the queue.
- **Bulk import must not become 966 sequential foreground jobs.** Keep the standalone batch script
  (§6f-build) as the path for bulk; the registration hook is for the *incremental* one-off case.
- Deregistering a track should mark its column dead (or trigger a compaction), not silently leave a
  stale ordinal.

**Open:** is the JBrowse track manager the right owner, or should it just mark the matrix stale
(§6d) and let an admin "Build Expression Matrix" action do the work? The former is nicer UX; the
latter is simpler and harder to get wrong. Leaning **enqueue-at-registration + drain in one pass**.

### 6f-build. How it gets built

Not in a web request. Follow the pattern already established by BLAST indexes:

- A **CLI script** (`scripts/build_expression_matrix.php`) does the work — runnable standalone,
  which is also what makes 6a's "run it where the data is local" possible.
- An **admin button** ("Build Expression Matrix", per assembly) shells it off in the background and
  reports **step-level progress** (track 412/966), not just a spinner.

### 6g. Sanity gate before trusting any of it

The precomputed values must be **spot-checked against the live `bigWigSummary` path** (§1) on a
handful of genes — same gene, same track, same number. That is the whole reason to build the live
path first even though it can't serve production.

---

## Build order

1. **Catalog reader** (§2) — no network, independently demoable.
2. **Live read path** (§1 + §4) — stable-window tokens, `-udcDir`, `abs()`, rc→null. Verify against
   the numbers in §1. Prototype/fallback, and the sanity gate for step 3.
3. **Precompute** (§5, §6) — install `bigWigAverageOverBed`; **measure one file's throughput and
   settle the BED12 question** before committing to a 966-track run; then batch → `expression.sqlite`.
   Spot-check against step 2 (§6g).
4. Hand off to `EXPRESSION_GENE_PAGE_PLAN.md` and `EXPRESSION_EXPLORER_PLAN.md`.

## Open questions (answer before building step 3)

- Real per-file throughput from MOOP → tracks (drives the ~7 h estimate in §6a).
- Does `bigWigAverageOverBed` honor **BED12 exon blocks**? (Decides gene-body vs exon-union mean.)
- Is signal **normalization consistent** across the 966 tracks, or per-`source`?
- Run the batch on MOOP (slow, zero coordination) or where the bigWigs are local (fast, needs a hand)?
