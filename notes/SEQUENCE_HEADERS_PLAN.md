# Sequence headers: one standard, shipped in safe pieces

Written 2026-10-27. Status: **piece 0 done (9fccbe42); pieces 1–6 not started.**

## Why

Sequence Retrieval's genomic output now names what each sequence belongs to:

```
>XP_036181976.1 gene=118665088 gene_name=PIGG transcript=XM_036326083.1 upstream_500bp NW_023416313.1:43032-43081(+)
```

Every other sequence MOOP shows or exports uses whatever header the source FASTA file had:
`>NV2t025877001.1:pep` (nothing) or `>XP_036181816.1 GPI ethanolamine ... [Myotis myotis]`
(a description but no gene). When someone pulls several IDs, or a whole gene set, they cannot
tell which sequences belong to the same gene without going back to the site. The goal is
**one header shape everywhere**.

## The standard

```
>ID gene=G gene_name=N transcript=T [part chr:start-end(strand)] <the source file's own description>
```

- **ID stays the first word.** BLAST, makeblastdb, samtools and every script key on it.
  Sequence Retrieval's found/not-found marking parses it too (`^>(\S+)`).
- **key=value pairs, then the source file's own description**, which is kept: the depositor's
  text ("GPI ethanolamine phosphate transferase 2 isoform X2 [Myotis myotis]") is information.
- **Omit what would repeat the ID:** no `transcript=` on an mRNA, no `gene=` on a gene, no
  `gene_name=` when the "name" is just another ID (Nematostella's gene names are transcript
  IDs). This rule already exists in `moop_genomic_anchors()`.
- `[part location]` only on genomic records (`genomic`, `upstream_500bp`, `downstream_500bp`,
  `exon3`).
- **Whole-file downloads (Downloads page) are NOT touched.** Those are the deposited files and
  stay byte-identical (see the "original data stays original" rule).

## Ground rules: nothing broken during a tour, nothing slow

The working tree **is** the live site. A half-edited file is live the moment it is saved.

1. **One piece = one commit**, revertible on its own with `git revert`. No piece depends on a
   later one to be correct.
2. **Build and verify in a side-by-side copy, not the live tree** (never `git stash`; see
   memory `reference_live_tree_before_after`). Copy files in only once the piece is finished,
   then `chmod o+r` them and check real URLs straight away (the test suites run as the repo
   owner and cannot see a 640 file that 500s the site).
3. **Both suites green** (`php tests/smoke_tests.php`, `node tests/js_smoke_tests.js`, checking
   the exit code, not piped output) plus the live-URL check below.
4. **Measure memory, not just time.** php-fpm's `memory_limit` is 128 MB per request, and
   several users at once multiply it. The 2026-10-23 capacity test found RAM, not CPU, runs
   out first. Record the peak for the largest gene set (Myotis myotis RS_100) before and after
   every piece that touches bulk output. **No piece may raise peak memory**; a slow or failed
   export is how users decide the site is bad and do not come back.
5. **Freeze before a meeting or tour:** nothing lands in the hour before. Run the check below
   once, then leave the site alone.

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
```
Everything should print 0 / 200. `git status` should be clean.

## Pieces

Each piece is shippable alone. **1 and 2 change nothing users see except fixing a bug**, so they
are the safe ones to do before a tour; 3–6 change output and want a quiet day.

### ✅ Piece 0: Sequence Retrieval genomic flanks (done, 9fccbe42)

Flanks for transcript/protein/CDS IDs (isoform's own span), `gene=`/`gene_name=`/`transcript=`
on genomic records, flank boxes only for gene sets on a genome, the `exon_coords.tsv`
`transcript:` prefix fix, readable genomic badge, one FASTA line width (80).

### Piece 1: Data Exporter protein/mRNA/CDS export fails on large gene sets 🔴 bug, do first

**Live today:** exporting proteins for a whole gene set returns **HTTP 502 in under a second,
with no file**. Seen on Nematostella NV2 (24,526 genes) and Myotis myotis RS_100 (20,154).
Small sets (Bradyrhizobium, 8,237) work. Genomic exports work because they stream.

**Cause** (`api/moopmart_export.php` ~line 373): for a protein export it calls
`extractSequencesForAllTypes()` with **all three** sequence types, so it reads proteins **and**
mRNAs **and** CDSs for the whole gene set into memory, then writes only the proteins.

| Protein export | Time | Peak memory |
|---|---|---|
| NV2, all three types (today) | 1.83 s | **440 MB** |
| NV2, proteins only | 0.32 s | 92 MB |
| Myotis, all three types (today) | 4.59 s | **969 MB** |
| Myotis, proteins only | 0.76 s | 231 MB, still over 128 MB |

**Fix:** pass only the requested type, **and** work in batches of genes (try 2,000), writing
each batch to the output before starting the next, the way the genomic path already streams.
Expected: 5–6× faster, with peak memory set by the batch size, not the gene set.

**Verify:** whole-gene-set protein, mRNA and CDS exports for NV2, Myotis and Bradyrhizobium
return 200, and **the record count equals the number of records in that gene set's FASTA file**
(a complete count is the proof nothing was dropped at a batch boundary). Record peak memory per
export. Check the preview (`fasta_preview`, first 10) still works.

Effort: ~2 h. Risk: low; same output, produced differently.

### Piece 2: the shared header builder (no visible change)

- Generalise `moop_genomic_anchors()` (`lib/genomic_regions.php`) into one context lookup for
  any sequence ID: `gene`, `gene_name`, `transcript`. It is already batched (5,000 IDs per
  query; SQLite's 32,766-parameter cap broke one big query at 61k IDs).
- One formatter: `moop_fasta_with_context($fasta, $context)` adds the key=value pairs after the
  ID and keeps the rest of the original header. One place builds the header shape; every
  caller below uses it.
- Smoke tests: header shape, the omit-if-repeats rules, ID still first, description kept, an
  ID with no context left exactly as it was.

Effort: ~1–2 h. Risk: none; nothing calls it yet.

### Piece 3: the pages that show a few sequences

Sequence Retrieval's Protein/mRNA/CDS boxes, the gene page's sequence boxes, and Retrieve
Selected (from search results). All go through `extractSequencesForAllTypes()` and
`tools/sequences_display.php`, so the context is added in one place.

- Cost: a few IDs → a few ms. No memory concern.
- **Check before shipping:** "Design primers from this sequence" builds its link from the
  displayed sequence; confirm it still works with the longer header. Check the FASTA download
  buttons on each page.

Effort: ~2 h. Risk: low–medium (visible change on three pages; screenshot each).

### Piece 4: Data Exporter protein/mRNA/CDS headers

Needs piece 1 (the batched export) first.

- **Do not** run a separate lookup per output ID: measured at **+22–25%** export time (NV2 +0.5 s
  on 2.1 s; Myotis +1.0 s on 4.2 s).
- Instead, make the exporter's existing gene → descendants query
  (`expandFeaturesToAllSequenceTypes()`) keep which gene each row came from (today it keeps only
  ID → type and drops the link). Then the cost is just the header rewrite: **~3%** estimated
  (0.05 s NV2, 0.13 s Myotis, measured in isolation; time the full export to confirm).
- Rewrite per batch (piece 1), never the whole gene set at once: rewriting a 49 MB string
  holds two copies.

Effort: ~2 h. Risk: medium (bulk output format). Measure time and peak memory before/after.

### Piece 5: Data Exporter genomic and exon headers

- gene/upstream/downstream: add `gene_name=` (these records are per gene, so no `gene=`/
  `transcript=` needed).
- exons: today `>G1.exon1 chr:s-e(strand)`, its own format.
  **Decision needed:** keep the `G1.exon1` ID (scripts may depend on it) and only add
  `gene_name=`, or move to `>G1 gene_name=N exon1 chr:...`? Recommended: **keep the ID**, add
  context only. Changing an ID breaks people's downstream scripts silently.

Effort: ~1 h. Risk: low–medium.

### Piece 6: gene page genomic/flank panels (JS)

`js/modules/gene-model-viewer.js` builds its own headers (`>label type genomic chr:...`). Bring
them to the same shape. The page already knows the gene and its name, so it needs no new lookup.

Effort: ~1 h. Risk: low (one page; screenshot).

## Open questions for Sofia

1. Is the header shape above right, including keeping the source file's description at the end?
2. Exon IDs (piece 5): keep `G1.exon1` as is? (recommended)
3. Whole-file downloads stay untouched? (recommended: yes)

## Total

About a day of work, spread over six independent commits. Pieces 1 and 2 are safe before a
tour; 3–6 want a quiet day and a screenshot check per page.
