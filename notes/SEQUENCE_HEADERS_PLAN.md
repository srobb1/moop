# Sequence output: memory-safe batching, download-only for big lists, one header standard

Written 2026-10-27; revised the same day after a memory audit of the user-facing pages.
Status: **piece 0 done (9fccbe42, pushed). Pieces 1–8 not started. Open questions answered — see Decisions at the end.**

Two goals, in this order:

1. **Nothing a user can ask for should take the site down or make it sluggish.** Any number of
   IDs is allowed. The work is broken into batches so memory stays flat, and lists too big to
   show comfortably go straight to download.
2. **One FASTA header standard** (`gene=`, `gene_name=`, `transcript=`) on every sequence MOOP
   shows or exports.

Goal 1 comes first: it fixes live failures, and goal 2 adds work to the same code paths, so it
must sit on the batched paths, not on the ones that fail today.

---

## Why memory matters here (read this first)

- php-fpm: `memory_limit = 128M` per request, `pm.max_children = 50`. Worst case 50 × 128 MB =
  **6.4 GB** on a **15 GB** machine where ~12 GB is page cache, which is what keeps the 33 GB of
  organism databases fast (CLAUDE.md §9). A request that grabs its full 128 MB does not have to
  crash to hurt: it evicts cache and every other user's queries go cold.
- The 2026-10-23 capacity test found **RAM, not CPU, runs out first** (memory
  `project_capacity_test_2026_10_23`).
- Over the limit, php-fpm kills the request: the user gets **HTTP 502 with no explanation**,
  usually in under a second. Nothing is logged where an admin looks (the php-fpm log is
  root-only).
- Sofia's standing concern: a few users at once; anyone who meets a slow or broken page does not
  come back, and tells others. **Peak memory is a first-class requirement, measured, never
  assumed.**
- `post_max_size = 8M`, so a pasted list can be ~400k IDs. The design must not assume lists are
  small.

---

## Memory audit, 2026-10-27 (live site, real endpoints)

How tested: real POSTs to `http://172.16.2.52/moop/…` (IP-range auto-login), worst realistic
inputs, mostly Myotis myotis RS_100 (20,154 genes, 61,156 proteins) and Nematostella NV2 (24,526
genes). A 502 in under ~1 s means the memory limit. CLI peak memory measured with
`memory_get_peak_usage()` and `-d memory_limit=3G` (scratch harnesses, not committed).

### 🔴 Fails today

| Where | Input | Result | Cause |
|---|---|---|---|
| Data Exporter FASTA: protein / mRNA / CDS | whole gene set (NV2, Myotis) | **502** in 0.9 s | extracts **all three** types, keeps all in memory, writes one (`api/moopmart_export.php` ~373). Peak 440 MB (NV2), 969 MB (Myotis) |
| Data Exporter FASTA: exons | whole gene set (Myotis) | **502** in 2.8 s | `moopmartGetExonCoords()` collects every gene's exons from the GFF into one array before writing |
| Sequence Retrieval | **300 gene IDs** (Myotis) | **502** | every type for every gene + its genomic region, all in memory, rendered into one page. 200 genes = 15.7 MB page. **Pre-existing** |
| Sequence Retrieval | **400 protein IDs** (Myotis) | **502** | ⚠️ **Made worse by 9fccbe42.** A protein ID now brings its transcript's genomic span (introns included). 300 proteins = 12.5 MB page, 97% of it genomic; before that commit the same paste made roughly 0.4 MB |

### 🟡 Works, but too big for a browser

| Where | Input | Result |
|---|---|---|
| Retrieve Selected (from search results) | 2,500 mRNAs (the search cap) | 200, **18.4 MB page** in 0.35 s |
| Sequence Retrieval | 100 / 200 gene IDs | 200, 6 MB / 15.7 MB page |

The server copes, but a 6–18 MB page is slow to render and scroll, and that is the
"sluggish site" users remember.

### 🟡 Not measured yet: BLAST result size is unbounded

- **Maximum Hits** has no server-side maximum (`tools/blast.php:133`; the form says `min="1"` only),
  and `max_hsps` defaults to unlimited.
- The result is held in memory **three times**: XML (`lib/blast_functions.php` ~363), pairwise
  text (~393) and tabular (~410), then parsed and rendered.
- A repetitive query against a genome with a large Maximum Hits could exceed the limit. Testing
  it costs real BLAST CPU, so it was **deliberately not run on 2026-10-27** (meeting day). Do it
  on a quiet day: blastn, a repeat-rich query, genome DB, Maximum Hits 500 / 5,000 / 50,000.

### ✅ Checked and fine

| Where | Why fine |
|---|---|
| Data Exporter FASTA gene / upstream / downstream | streams per gene. Myotis whole set: 584 MB file, 200, 8.7 s |
| Data Exporter TSV | chunks of 250 genes, one organism at a time. Myotis: 200, 11 s (slow, not a memory risk) |
| Data Exporter preview / per-organism preview | capped (100 rows; 10 records for FASTA preview) |
| Search-results FASTA download (`api/download_search_fasta.php`) | one organism at a time, bounded by the 2,500 search cap: 2,500 mRNAs → 7,500 sequences, 18 MB, 0.2 s |
| `api/download_zip.php` | `tar` via `popen`, 64 KB chunks |
| `api/download_file.php`, `lib/fasta_download_handler.php` | `readfile()` after clearing output buffers |
| `api/get_gff.php`, `api/download_annotations.php`, `api/gene_neighbor.php` | per gene |
| `api/get_sequence.php` | 500 kb cap |
| Expression pages | read only the requested genes' rows (`moop_expression_bundle_values()`, streamed); heatmap capped at `MOOP_EXPRESSION_TOOL_MAX_GENES`. Whole-table loads are build-time only. Largest table today 2.6 MB |

### Still to audit

- BLAST (above).
- Admin pages: not user-facing and one admin at a time, so lower priority, but
  `admin/manage_js_registry.php` already raises its own limit to 512M; find out why.
- Gene page for genes with very many isoforms (each gets its own boxes).
- Anything new: **every new endpoint that returns sequences must say how its memory is bounded.**

---

## Ground rules for every piece (nothing broken during a tour)

The working tree **is** the live site. A half-edited file is live the moment it is saved.

1. **One piece = one commit**, revertible on its own with `git revert`. No piece depends on a
   later one to be correct.
2. **Build and verify in a side-by-side copy, not the live tree** (never `git stash`; memory
   `reference_live_tree_before_after`). Copy files in only when finished, `chmod o+r` them, check
   real URLs immediately (the suites run as the repo owner and cannot see a 640 file that 500s
   the site).
3. **Both suites green**, checking the exit code (`php tests/smoke_tests.php; echo $?`,
   `node tests/js_smoke_tests.js; echo $?`), plus the live-URL check below.
4. **Measure peak memory, not just time**, on the worst case (Myotis RS_100 whole gene set; a
   2,000-gene Sequence Retrieval paste) before and after. **No piece may raise peak memory.**
   Target for any batched path: **peak under 64 MB** (half the limit), whatever the input size.
5. **Prove "nothing dropped":** for every batched output, the record count must equal what the
   old path produced (or the FASTA file's own count for a whole gene set), and for a small list
   the new output must be **byte-identical** to the old one. Batch boundaries are where records
   get lost or duplicated.
6. **Freeze before a meeting or tour:** nothing lands in the hour before. Run the check once,
   then leave the site alone.

### Pre-tour check (≈30 s)

```bash
cd /var/www/html/moop
php tests/smoke_tests.php >/dev/null; echo "php suite exit $?"
node tests/js_smoke_tests.js >/dev/null; echo "js suite exit $?"
for u in "tools/parent.php?organism=Nematostella_vectensis&uniquename=NV2g025877000.1" \
         tools/retrieve_sequences.php tools/moopmart.php tools/blast.php tools/search.php \
         css/display.css js/modules/gene-model-viewer.js; do
  curl -s -o /dev/null -w "%{http_code}  $u\n" "http://172.16.2.52/moop/$u"
done
git status --short
```
Everything should print 0 / 200, and `git status` should be empty.

**Known failures to avoid in a demo until pieces 1–2 ship:** whole-gene-set protein / mRNA /
CDS / exon exports in the Data Exporter; pasting more than ~200 gene IDs or ~300 protein IDs
into Sequence Retrieval.

---

## Pieces

| # | Piece | Fixes | Visible change | Risk |
|---|---|---|---|---|
| 0 | ✅ Sequence Retrieval flanks (9fccbe42) | (done) | yes | — |
| 1 | Batched streaming engine + Data Exporter; FASTA warnings move to the page | 2 live 502s, `#` lines in FASTA | small (warnings on page) | low |
| 1b | Export slots (BLAST-queue design, never stuck); only if measurement says so | concurrent-export load | "busy" message, place in line | low–medium |
| 2 | Sequence Retrieval + Retrieve Selected: download-only above X | 2 live 502s + big pages | yes | medium |
| 3 | BLAST hit limits | unmeasured risk | small | low |
| 4 | Shared header builder | — | none | none |
| 5 | Headers on the pages that show a few sequences | — | yes | low–medium |
| 6 | Headers in Data Exporter protein/mRNA/CDS | — | yes (files) | medium |
| 7 | Headers in Data Exporter genomic + exons | — | yes (files) | low–medium |
| 8 | Gene page JS genomic/flank panels | — | yes | low |

### ✅ Piece 0: Sequence Retrieval genomic flanks (done, 9fccbe42)

Flanks for transcript/protein/CDS IDs (isoform's own span); `gene=`/`gene_name=`/`transcript=`
on genomic records; flank boxes only for gene sets on a genome; the `exon_coords.tsv`
`transcript:` prefix fix (Drosophila, Acropora RS_101 and Danio regenerated); readable genomic
badge (hex colour now honoured); one FASTA line width, `MOOP_FASTA_LINE_WIDTH = 80`;
`moop_genomic_anchors()` batched at 5,000 IDs (SQLite's 32,766-parameter cap).
**Side effect found later:** lowered Sequence Retrieval's protein-paste limit (audit table).
Piece 2 fixes it.

### Piece 1: the batched streaming engine, used first by the Data Exporter 🔴

One function, in `lib/extract_search_helpers.php` next to `extractSequencesForAllTypes()`, that
every bulk sequence path will use:

```
moop_stream_sequences($handle, string $gene_set_dir, array $ids, string $type, array $opts)
```

- **One type per call.** Never extract types the caller will not write. (That alone takes the
  Data Exporter protein export from 1.83 s / 440 MB to 0.32 s / 92 MB on NV2.)
- **Batches of IDs** (start at 500; measure 250 / 500 / 2,000 and pick the largest that keeps the
  peak under 64 MB). For each batch: expand (gene → descendants of this type, scoped to the gene
  set), extract, write to `$handle`, `unset`, then the next batch.
- Expansion happens **inside** the batch, so a gene and all its isoforms always land in the same
  batch: no isoform split across a boundary.
- **Order:** the order IDs were given (then each gene's descendants), so a small list is
  byte-identical to today's output. Check what today's order actually is before promising this;
  the diff test will show it.
- **Duplicates:** an ID given twice, or a protein given along with its gene, must not be written
  twice. Keep a `seen` set of written uniquenames across batches (strings only: ~100 bytes each,
  6 MB for 61k IDs; acceptable, but measure).
- **Ranges** (`X:1-60`): carried through per request, exactly as `extractSequencesForAllTypes()`
  does today (`$requests`, `$parent_to_children`). They must survive batching.
- **Flush after each batch** (`fflush`, `flush()`), with output buffers cleared first
  (`while (ob_get_level()) ob_end_clean();` as `download_file.php` does).
- **Errors after the headers have gone out** cannot change the HTTP status. So validate
  everything that can fail (access, gene set, file presence) **before** the first byte, and log
  mid-stream failures with `logError()` so an admin can see them.
- **No `#` lines in FASTA output** (decision 5). Remove the Data Exporter's trailing
  `# WARNING:` block and show those warnings on the page via a preflight (see Decisions).
- Time limit: big exports run for many seconds. Check `set_time_limit` and **nginx
  `fastcgi_read_timeout`** (the capacity test changed nginx timeouts on 2026-10-23); a streamed
  response that keeps sending bytes is usually fine, but confirm.

Then switch the Data Exporter to it:

- **protein / mRNA / CDS** (`api/moopmart_export.php` ~359–381): replace the all-types call with
  `moop_stream_sequences(..., $fasta_mode, ...)`.
- **exons** (`moopmartStreamGenomicFasta()` + `moopmartGetExonCoords()`): today one GFF pass
  collects every gene's exons first. The GFF is sorted by gene family, so **write each gene's
  exons as soon as its family ends** (one pass, nothing held but the current family). Do not
  "batch" by re-reading the 264 MB GFF once per batch: correct, but N passes. Keep the numbering
  rule (5'→3', each type on its own counter, first isoform only) exactly as is; the smoke tests
  for exon numbering must stay green.

Verify:

- Whole gene set, each of protein / mRNA / CDS / exons, for **NV2, Myotis, Bradyrhizobium**: 200,
  record count equals the gene set's FASTA file (`grep -c '^>'`), peak memory recorded (CLI
  harness) and under 64 MB.
- A 20-gene selection: **byte-identical** to the old output (`cmp`), all four modes.
- FASTA preview (first 10) unchanged.
- Smoke tests: a batch boundary (an ID exactly at position 500/501), a duplicate ID, a gene
  with isoforms in a list long enough to cross a boundary, a ranged ID.

Effort: half a day. Risk: low; same output, produced differently, and provable by `cmp`.

### Piece 2: Sequence Retrieval and Retrieve Selected: download-only above X

Sofia's direction (2026-10-27): **any number of IDs is allowed; do not cap what people can
collect.** Big lists go straight to download, with a clear message, so neither the server nor the
browser has to hold them.

**The rule.** Show on the page when the list is small; download-only when it is not.

- Count rule, easy to explain: **more than 50 typed IDs** → download-only. (Measured: 100 gene
  IDs already make a 6 MB page; 100 proteins 2 MB.)
- **Size safety net**, because one gene can expand to dozens of isoforms plus a genomic region
  of up to 500 kb: estimate the output size **before extracting anything**, from data already
  indexed:
  - sequence lengths from the `.fai` of `protein.aa.fa` / `transcript.nt.fa` / `cds.nt.fa`
    (`moop_fai_lookup()`, one pass per file);
  - genomic span lengths from `feature_coords.tsv` / `exon_coords.tsv` (`moop_feature_loci()`),
    plus the flanks.

  If the estimate is over ~2 MB of sequence, it goes to download too, even under 50 IDs.
  (Tune the 2 MB from measurements: page size vs. render time in a real browser.)
- Both numbers become named constants, one place, with the measurements in a comment.

**What the page shows for a big list** (cheap: database lookups only, no sequence read):

> **4,812 IDs: 4,790 found, 22 not found.** Lists of more than 50 IDs are available as a download
> only. **[Protein] [mRNA] [CDS] [Genomic region]**

- Found / not found: one batched `IN (...)` query (chunks of 5,000, the SQLite cap) against
  `feature`, scoped to the gene set. Not N single-row lookups.
- Show the not-found IDs (all of them if short; the first 50 plus "and N more" with a copyable
  full list if long). Keep the green/red ID-box marking working for small lists.
- One download button per type, as today. Each re-POSTs the ID list (the browser still has it in
  the textarea; 8 MB `post_max_size` is ~400k IDs), so nothing is stored server-side.
- Flank settings and ranges apply to the download exactly as they would on the page.
- Genomic download streams through `moop_retrieve_genomic()` in batches too: it already reads the
  genome through one open handle (`moop_genome_open()`), so batch the requests and write per
  batch instead of building `$out['fasta']`.

**Fix the existing download path too.** Today the per-type **Download** button re-runs the
**whole** extraction (every type, plus genomic) for the full list, then sends one type
(`handleSequenceDownload()` → `sendFileDownload()`, which builds the whole file as one string).
So downloading costs as much memory as viewing. Every download, small or big, must go through
piece 1's streaming engine for the one type requested.

**Retrieve Selected** (`tools/retrieve_selected_sequences.php`): same rule and the same
message. Its input is bounded by the search cap (2,500 per organism), but that already makes an
18 MB page.

**The search-results "Download FASTA"** (`api/download_search_fasta.php`) already works per
organism and is bounded; move it onto the engine only if it is free to do so. Not required.

**This also undoes 9fccbe42's side effect:** a 400-protein paste goes to download instead of
502ing.

Verify:

- 49 / 50 / 51 IDs: the boundary behaves as stated.
- A 30-ID list of big genes trips the size net.
- 2,000 and 20,000 Myotis gene IDs: the page answers fast with the summary; every download
  button returns 200 with the expected record count; peak memory under 64 MB.
- A small list's download is byte-identical to today's.
- Screenshot the summary message at desktop and phone width.

Effort: half a day to a day. Risk: medium (visible behaviour change on two pages).

### Piece 3: BLAST hit limits

First **measure** (see audit). Then, depending on the numbers:

- a server-side maximum for Maximum Hits (and maybe a default `max_hsps`), shown in the form
  (`max=` on the input **and** clamped in `tools/blast.php`, never only in HTML);
- and/or stop holding three copies of the result: write the pairwise and tabular downloads to
  files the download endpoint streams, instead of strings in memory.

Effort: 1–3 h after measuring. Risk: low.

### Piece 4: the shared header builder (no visible change)

- Generalise `moop_genomic_anchors()` (`lib/genomic_regions.php`) into one context lookup for any
  sequence ID: `gene`, `gene_name`, `transcript`. Already batched (5,000 per query).
- One formatter, `moop_fasta_with_context($fasta, $context)`: adds the key=value pairs after the
  ID, keeps the rest of the original header. One place builds the header shape.
- For bulk paths, the context must come from the walk the export already does (piece 6), not a
  second lookup.
- Smoke tests: header shape, the omit-if-repeats rules, ID still first, description kept, an ID
  with no context left exactly as it was.

Effort: 1–2 h. Risk: none; nothing calls it yet.

#### The header standard

```
>ID gene=G gene_name=N transcript=T [part chr:start-end(strand)] <the source file's own description>
```

- **ID stays the first word.** BLAST, makeblastdb, samtools and scripts key on it; Sequence
  Retrieval's found/not-found marking parses it (`^>(\S+)`).
- **key=value pairs, then the source file's own description, kept** ("GPI ethanolamine phosphate
  transferase 2 isoform X2 [Myotis myotis]" is information).
- **Omit what would repeat the ID:** no `transcript=` on an mRNA, no `gene=` on a gene, no
  `gene_name=` when the "name" is just another ID (Nematostella's gene names are transcript IDs).
  `gene_name=` only when it is one word. These rules already exist in `moop_genomic_anchors()`.
- `[part location]` only on genomic records (`genomic`, `upstream_500bp`, `downstream_500bp`,
  `exon3`).
- **Whole-file downloads (Downloads page) are not touched**: the deposited files, byte-identical
  (rule: original data stays original).

### Piece 5: headers on the pages that show a few sequences

Sequence Retrieval's Protein/mRNA/CDS boxes, the gene page's sequence boxes, Retrieve Selected.
All go through `extractSequencesForAllTypes()` and `tools/sequences_display.php`.

- Cost: a few IDs → a few ms. After piece 2 these pages only ever show small lists.
- **Check:** "Design primers from this sequence" builds its link from the displayed sequence
  (`tools/sequences_display.php` ~129–170); confirm it still works with the longer header. Check
  every page's download buttons.

Effort: ~2 h. Risk: low–medium; screenshot each page.

### Piece 6: headers in Data Exporter protein/mRNA/CDS

Needs piece 1.

- **Not** a separate lookup per output ID: measured **+22–25%** export time (NV2 +0.5 s on
  2.1 s; Myotis +1.0 s on 4.2 s).
- Instead, make the expansion in the streaming engine **keep which gene each row came from**
  (today `expandFeaturesToAllSequenceTypes()` keeps only ID → type). Then the cost is the
  header rewrite alone: **~3%** estimated (0.05 s NV2, 0.13 s Myotis, measured in isolation).
  Time the full export to confirm.
- Rewrite **per batch**, never the whole output at once.

Effort: ~2 h. Risk: medium (bulk file format). Measure time and peak memory before/after.

### Piece 7: headers in Data Exporter genomic and exons

- gene / upstream / downstream: add `gene_name=` (records are per gene, so no `gene=` or
  `transcript=`).
- exons: today `>G1.exon1 chr:s-e(strand)`. **Recommended: keep the `G1.exon1` ID** (people's
  scripts may depend on it) and add `gene_name=` only.

Effort: ~1 h. Risk: low–medium.

### Piece 8: gene page genomic/flank panels (JS)

`js/modules/gene-model-viewer.js` builds its own headers (`>label type genomic chr:...`). Bring
them to the standard. The page already knows the gene and its name, so no new lookup is needed.

Effort: ~1 h. Risk: low; screenshot.

---

## Decisions (Sofia, 2026-10-27)

1. **Header shape: yes.** `>ID gene=… gene_name=… transcript=… <source description>`, as in
   piece 4.
2. **Exon IDs: keep `G1.exon1`**, add `gene_name=` only (piece 7).
3. **Whole-file downloads: untouched.** Byte-identical to what was deposited.
4. **Download-only threshold: 50 IDs plus a ~2 MB size net**, as a starting point; tune from
   measurements.
5. **Warnings go on the page only; files stay pure FASTA.** Sofia did not know the Data Exporter
   writes `# WARNING: …` comment lines into FASTA files (`api/moopmart_export.php`, the
   `$genomic_notes` / `$skipped_datasets` block at the end of the FASTA branch). Some tools
   reject `#` lines in FASTA, so this is a (small) existing bug, not just a preference.
   - **Applies to the existing Data Exporter too**, not only new paths: do it in piece 1, since
     piece 1 rewrites that code anyway.
   - **The catch:** a streamed download cannot report back to the page once bytes have gone. So
     the warnings must be known **before** streaming. Add a cheap **preflight** request that the
     page calls first (or fold it into the existing preview endpoint), which returns them for
     display beside the Download button. Whole-gene-set reasons are cheap to know in advance:
     missing genome / `.fai` / FASTA files (`file_exists`), chromosome-name mismatch
     (`moop_gene_set_seqname_gap()`, already cached in the organism cache). Per-gene reasons
     (no coordinates, no exons) need a pass over the coordinate files: still no sequence read,
     but measure it on Myotis; if it is slow, show a count plus examples, not every ID.
   - Check the Data Exporter's **TSV** output for comment lines too and decide the same way
     (TSV comment lines break spreadsheet imports in a different way).
   - Sequence Retrieval's download-only path (piece 2) follows the same rule: the not-found list
     lives on the page, never in the file.
6. **Limit concurrent big exports: yes, but it is imperative that nothing ever gets stuck in
   the line.** Context: Tripal v2's job queue regularly left jobs stuck. So:
   - **Reuse `lib/blast_queue.php`'s design, do not invent a queue.** It was built against exactly
     this failure: no job table, no worker process, no "running" flag. All state is a `flock()`
     held by the PHP request itself; the kernel releases it the instant that process dies (OOM
     kill, crash, php-fpm restart). A leftover file means nothing; only a held lock counts.
   - **Bounded both ways:** a request waits at most N seconds, then gets a clear "the site is busy,
     try again in a minute" message (never an endless spinner); a running export is bounded too.
   - **Strict line order** (head of the line only), so a big export cannot be starved.
   - **Fails open:** if the queue directory is unusable, the export runs unqueued and the reason
     goes to the error log. A broken queue must never stop a download.
   - Probably a **separate, small slot pool** for exports (e.g. 2–4 at once), not the BLAST
     units: exports are disk- and CPU-bound, BLAST is memory-bound, and one should not wait
     behind the other. Generalise the lock helpers rather than copy them (no second copy to
     drift; CLAUDE.md §9b).
   - **Waiting must be visible.** A streamed download that is waiting for a slot looks to the
     user like a download that never starts. Show the place in line on the page, as BLAST's
     waiting page does (`api/blast_queue_status.php`), and start the download only when the slot
     is held.
   - `max_wait + max_runtime` must stay under nginx's `fastcgi_read_timeout` for the export
     endpoints, or nginx gives a 504 while the export carries on (the same rule the BLAST queue
     documents).
   - **Measure first** (after piece 1): with batching, each export's memory is small. Run 5–10
     whole-gene-set exports at once and watch page response times for everyone else. Add the
     slots only if they are needed, and size them from that measurement.
   - Make it a separate piece (**piece 1b**), after piece 1, so the batching fix ships without
     waiting on it.

## Total

Roughly 2–3 days across ten independent commits (piece 1b only if measurement calls for it). **Pieces 1 and 2 fix live failures and should
go first.** Pieces 1, 3 and 4 do not change what users see and are the safe ones near a tour;
2 and 5–8 want a quiet day and a screenshot check per page.
