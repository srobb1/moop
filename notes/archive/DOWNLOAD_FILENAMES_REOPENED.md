# Download filenames — REOPENED 2026-08-04

Status: **two bugs found and fixed in `lib/fasta_download_handler.php` (`30ad553`); the
rest of the producers have NOT been re-checked.** The 2026-07-31 audit marked this area
complete. It was not.

---

## What was found

Both reported from a real Downloads folder, not from reading code:

```
sequences_Anoura_caudifer_GCA_004027475.1_2026-08-04.fa
sequences_Anoura_caudifer_GCA_004027475.1_2026-08-04 (1).fa
sequences_Anoura_caudifer_GCA_004027475.1_2026-08-04 (2).fa
genes_Anoura_caudifer_GCA_004027475.1_SIMR_2025-01-24_2026-08-04.gff
```

### 1. Every sequence type downloaded to the same filename

The name was built from `$pattern` — **a variable never assigned anywhere in that file**.
It evaluated to `''`, `moop_download_filename()` dropped the empty part, and the one
component identifying WHICH sequence you asked for disappeared. Protein, CDS and transcript
all produced the identical name, so the browser appended " (1)" and " (2)" and nothing
distinguished them.

Now named by TYPE, which is the distinguishing thing, with the gene set in scope for the
same reason the GFF download already carried it:

```
protein_Anoura_caudifer_GCA_004027475.1_SIMR_2025-01-24_2026-08-04.fa
genome_Anoura_caudifer_GCA_004027475.1_2026-08-04.fa
```

### 2. Genome downloads 500'd on any large assembly

`fasta_download_handler.php` opens `ob_start()` at the top to swallow stray output from
includes, and `readfile()` at the bottom writes into whatever buffer is open — so the whole
file was accumulated in memory before a byte reached the client. `genome.fa` for
Anoura_caudifer is **2.2 GB against `memory_limit = 128M`**: fatal, bare 500, no message.

⚠️ **This is NOT the same as the download-content work done earlier**, and it does not undo
it. The `ob_start()` at line 11 exists because stray include whitespace used to end up in
the FASTA (the "lots of blank lines mixed in" problem); it is discarded at line 35 and still
does exactly that job. The buffer still open at `readfile()` is **PHP's own** — php.ini sets
`output_buffering = 4096`, so every FPM request begins inside an implicit buffer that
nothing in this file had closed. The earlier work fixed what is *in* the file; this is how
it is *sent*, and it only fails above the memory limit, which is why every small file was
fine. Confirmed by A/B on the single hunk: with the discard **200**, without it **500**,
restored **200**.

---

## 🔑 Why the "complete" audit missed both

The 2026-07-31 pass verified 13 producers **by downloading each one**, which was the right
method and still missed these. Two blind spots, both worth designing around next time:

1. **Downloading ONE file per producer cannot reveal a collision BETWEEN files from the
   same producer.** The names only clash when you fetch protein *and* CDS *and* transcript
   from the same page. A per-producer checklist is satisfied by a single download.
2. **A size-dependent failure needs a large input.** Every FASTA the audit pulled was small
   enough to fit in 128M. The bug lives entirely above that line.

So the audit's finding — "3 of the last 4 were broken; source read as correct" — was right
about *reading* being insufficient, and still under-specified the test.

---

## ⏭️ Tomorrow

- [ ] **Re-check the other producers for the same two classes**, not just for a plausible
      name: fetch at least TWO files from each producer and compare, and include one
      genuinely large file. Producers list is in the 2026-07-31 audit.
- [x] **`api/jbrowse2/tracks.php:160` `readfile()` with NO buffer handling** — **FIXED here
      2026-08-05, but NOT deployed, and LOWER PRIORITY than this entry implied.** Replaced both
      send paths with a chunked `moop_stream_file_range()` (1 MB chunks + `ob_end_clean()`).
      A second hazard was found that this entry missed: the **206 range path** did
      `echo fread($fp, $length)`, and an open-ended `Range: bytes=0-` sets `$end = filesize-1`,
      so "partial content" could mean the whole file. The range path was no safer than the
      full-file path.

      ⚠️ **MEASURED AGAINST THE LIVE TRACKS SERVER, and the practical impact is much smaller
      than "same exposure" suggested.** Driving the real JBrowse in headless Chrome over two
      sessions (bigWig + `.gff.gz`): **24 requests, all 206, zero failures, median 256 KB,
      largest 512 KB.** JBrowse uses fixed 256/512 KB blocks — 64–128× below the failure
      threshold. The old code's real failures, both confirmed live on the 1.25 GB
      `MOLNG-2707_S1-body-wall.bam`: a no-`Range` GET returns **500**, and a `Range` ≥ 64 MB
      returns **206 headers then zero bytes** (silent truncation; 32 MB still OK). Neither is
      reachable through JBrowse. It bites only a non-JBrowse client — a pasted track URL,
      `curl`, IGV, or anything fetching a whole file.

      **So: correct, worth deploying, but not urgent and not a launch blocker.** The lesson is
      the mirror of this file's own theme — that pass under-tested a size-dependent fatal, and
      this one over-claimed its blast radius by reasoning about the code instead of measuring
      what the actual client asks for. Deploy = copy to the tracks box + php-fpm reload; verify
      with a before/after `md5sum` of the same 1 KB range (must be identical).
- [ ] **Consider whether `moop_download_filename()` should refuse an empty scope part**
      rather than silently dropping it. Dropping is what turned an unassigned variable into
      three identically-named files instead of an obvious error.
- [ ] Decide whether `transcript` should read `mrna` in filenames — the UI calls it mRNA.

## Related

`plan_download_filename_audit` memory (says COMPLETE — it is not),
`bug_silent_write_failures` (same family: a failure that reports success),
`PAGE_BY_PAGE_AUDIT_PLAN.md` (five marks wrong for the same reason — items that declare
themselves finished stop being checked).
