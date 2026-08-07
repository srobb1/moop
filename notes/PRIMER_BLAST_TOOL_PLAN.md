# The primer tools

Status: **both tools are BUILT and live** (2026-08-07). Primer BLAST checks pairs; Primer
Maker designs them. primer3 is installed. Read the RESUME section immediately below for where
things stand and what is left — the sections further down are the original planning and the
product spec, which are still the reference for the tails work but no longer describe status.

Raised by the user 2026-08-03 while fixing the short-sequence BLAST bugs.

⭐ **SCOPE GREW 2026-08-06 — the user wants primer *design*, not only primer checking.**
Their words: *"i think a primer making tool might be good. i have some perl code using primer3
to make primers and rtpcr primers. maybe we can update that code."*

So the tool has two halves, and this note only ever specced the second:

| | What it does | Status |
|---|---|---|
| **Design** | gene/sequence in → Primer3 → candidate primer pairs out; separate RT-PCR mode | NEW — user has Perl for it |
| **Check** | primer pair in → BLAST → pair the hits, compute product size, flag specificity | the rest of this note |

They chain naturally: design candidates with Primer3, then run each candidate pair through the
specificity check below. That is exactly NCBI Primer-BLAST's shape.

**RT-PCR primers are the differentiator.** Designing across an exon-exon junction (so the primer
pair cannot amplify contaminating genomic DNA) needs exon coordinates — and MOOP has them, in
`feature_coords.tsv` and the GFF. A generic primer site cannot do this for these 85 organisms.

⚠️ **Two blockers before any build:**
- **The Perl is not on this host.** `/home/smr/moop-dbtools/` holds only `loaders/` and
  `parsers/`; a filesystem sweep for `*primer*` found nothing but this note. Get the code first —
  it decides whether this is a port, a wrapper, or a rewrite.
- **`primer3_core` is not installed** here (nor `primer3`). It is a package install, not a
  blocker in principle, but it is a new external binary — so it also needs the
  `escapeshellarg()` treatment (CLAUDE.md §8) and a note in the environment check
  (`housekeeping_environment_check`, which already verifies the other CLI tools).

Ranked #2 of the new-tool candidates in `notes/NEW_TOOL_SURVEY_AND_RECOMMENDATIONS.md` —
UCSC ships in-silico PCR as one of its five main tools, so the demand is not niche.

---

# ⏭️ RESUME MONDAY — Primer Maker is a working first draft (2026-08-07, end of day)

**primer3 is INSTALLED** on this host: `/usr/local/bin/primer3_core` (2.6.1) with tables in
`/usr/local/share/primer3_config/`. MOOP auto-detects both — nothing configured.
`php setup-check.php` reports `[PASS] primer3_core (parameters in …)`.

**What works end to end, driven and verified — not assumed:**

- `tools/primer_maker.php` — ONE page for every primer kind (standard / qPCR / RT-PCR /
  sequencing), because the difference is Primer3 PARAMETERS, not workflow.
- **RT-PCR really constrains the junction.** Verified on `XM_001635385.3`: junctions at 156
  and 475, designed reverse primer spans 473-492 — across 475. Junction positions come from
  `exon_coords.tsv` via `ExonMap`, which is what this morning's work bought.
- **Two ways in from a gene page.** The toolbox link, and per-sequence "Design primers from
  this sequence" buttons in the Sequences section (transcript → RT-PCR, CDS → standard).
  Hand-off is BY ID, not by posting the sequence — an earlier version shipped a 12 kB hidden
  field per sequence type.
- **Isoform handling**: one transcript prefills silently; several give a picker with lengths
  and nothing pre-chosen; transcripts with no sequence are listed but disabled.
- **Chaining**: a Check button per results row POSTs to Primer BLAST and it RUNS. Confirmed:
  "specific, spans 1 intron — cDNA 374 bp vs gDNA 3,469 bp".

### ⏭️ Monday, in rough order

1. **5′ cloning tails (T4P and "other").** The one substantial piece left. ⚠️ THE ORDERING IS
   THE SPEC, not an accident — see PRODUCT SPEC §5 below: design on the bare template,
   compute every statistic on the untailed primer, check specificity untailed, and only THEN
   append and report BOTH forms. Tails must never reach Tm calculation or the genome check.
   Also worth doing: search the tail alone against the assembly once and warn if it matches.
2. **Genomic template — WIRING, not new capability.** ⚠️ I called this the biggest gap and
   that was wrong; the user pointed out MOOP already gets genomic sequence three ways, and
   none of them needs writing:

   - **`api/get_sequence.php`** — takes `organism, assembly, seqname, start, end, strand` and
     returns JSON. This is what the gene page's own gene-structure FASTA button already uses
     (`js/modules/gene-model-viewer.js:399`), and the gene model already knows the locus and
     each isoform span.
   - **Retrieve Sequences supports SLICES** — `g24397.t1:1-500`, and `:1..500`, ` 1-500`,
     ` 1..500` are all equivalent. It also returns genomic and flanking, not just transcript.
   - **The gene page's "FASTA" button** under the gene structure already downloads "the
     genomic sequence — gene locus plus each isoform span".

   So the work is a hand-off, and there are three candidate shapes. Decide Monday:
   (a) a "Design primers" button beside the gene-structure FASTA button, same pattern as the
       Sequences-section buttons, calling `api/get_sequence.php` for the locus — smallest and
       most direct for the gene page;
   (b) teach Primer Maker to accept a slice spec and resolve it the way Retrieve Sequences
       does, which also serves people who want flanking sequence around a locus;
   (c) just document a use case — take a slice from Retrieve Sequences, paste it in — which
       costs no code and may be enough for the first release.

   ⭐ Whichever is chosen, note the gene-structure button is the one already sitting next to
   what a user is looking at when they think "I want primers for this gene".
3. **Should Retrieve Sequences offer the hand-off too?** It shares
   `tools/sequences_display.php`; the button is opted into by the gene page alone
   (`$enable_primer_design`). User raised this and it is unresolved.
4. **Multi-record input.** Only the first FASTA record is used. The results table and the
   junction logic both need to say WHICH sequence a row came from before that changes.
5. Dash-separated pasted sequence as an alternative junction marker (`runPrimer3_web.pl`
   trick), for users without a registered transcript.
6. Port the rest of the Perl properly — ⚠️ `/home/smr/primer3_tab` targets primer3 **1.x**:
   `PRIMER_SEQUENCE_ID`→`SEQUENCE_ID`, `SEQUENCE`→`SEQUENCE_TEMPLATE`,
   `PRIMER_SELF_ANY`→`PRIMER_LEFT_0_SELF_ANY_TH`. Transliterating finds primers but never the
   record they belong to. Build against real 2.6.1 output.

### 🪤 Traps this cost time on today — do not rediscover them

- **`tools/parent.php` takes `uniquename`, NOT `feature`.** Wrong-parameter URLs return an
  error page, which looks exactly like "the toolbox is not rendering".
- **`createToolContext()` used to silently DROP any context key not on a hardcoded list.**
  Fixed by deriving the list from every tool's `context_params` — but that is the shape to
  watch: declaring a param in `tools_config.php` is now enough, and used not to be.
- **`primer3_core` EXITS 0 EVEN WHEN IT FAILS.** A bad thermodynamic path gives
  `PRIMER_ERROR=Unable to open file …/dangle.dh` on stdout with status 0. Read the OUTPUT.
- **`/tmp` is mounted `noexec` here.** A binary built there tests as non-executable
  (`[ -x ]` is false) even though `make` succeeded. It broke the installer once and a test once.
- **`buildInput()` must stay free of ConfigManager** or the smoke suite stops being hermetic —
  and the fatal was hidden by the summary line until the exit code was checked.

---

# ✅ PHASE 2 COMPLETE — split primers across exons (2026-08-07)

**Shipped.** A primer sitting across an exon-exon junction now maps back through the exon
structure to genomic blocks, so it gets a Browser link and draws as two boxes either side of
the intron. Before this it got no link at all — the picture explaining *why* it is a good
RT-PCR primer was the one picture the user never saw.

| file | what |
|---|---|
| `lib/primer/ExonMap.php` | transcript range → genomic blocks; `mapProduct()` places a whole cDNA product |
| `lib/primer/PrimerBlast.php` | `cleanSubjectId()` — one definition of the `ref\|ACC\|` strip, was duplicated |
| `tools/primer_blast.php` | maps every cDNA product ONCE per request, not once per row |
| `tools/pages/primer_blast.php` | blocks in the session track, Browser link on cDNA, junction badge |
| `admin/api/generate_exon_coords.php` | regeneration endpoint |
| `admin/pages/manage_blast_linkouts.php` | exon-index column beside the feature-index one |
| `scripts/backfill_exon_coords.php` | bulk backfill |
| `scripts/design_exon_testers.php` | generates fixture primers with KNOWN exon behaviour |

**Backfill done:** 70 gene sets generated in 3.4 min, 0 failures; all 72 now have the index,
862.9 MB total.

### Verification — three independent levels, because the arithmetic looks right when it isn't

1. **Unit** — 53 new assertions in `tests/primer_smoke_tests.php` (109 total). Falsified
   against a mapper with the strand handling removed: 5 minus-strand assertions go red,
   exit 1. A test that has never failed is not evidence.
2. **Ground truth against real sequence** — 30 Nematostella transcripts (15 plus, 15 minus),
   787 ranges, 757 of them junction-spanning: genomic sequence pulled at the mapped blocks,
   reverse-complemented on minus-strand transcripts, compared to the transcript subsequence.
   **0 mismatches, 0 length disagreements.**
3. **Driven live** — real POSTs to the page, checking the rendered verdict and the decoded
   `sessionTracks` JSON, not a source diff.

⚠️ **The minus strand is where this breaks silently.** Ignoring strand puts the primer up to
**70 kb from the truth** on a real Nematostella transcript, and the drawing still looks
entirely reasonable. Measured, not assumed — see the negative control above.

### Tester primers — VERIFIED, Nematostella GCF_932526225.1 / RS_101

Each was *placed* from the exon index, so the answer was known before the tool ran; every one
was then confirmed against the live page. Regenerate for any gene set with
`php scripts/design_exon_testers.php <gene-set-path> [--fasta]`.

```
>junction_plus_F     CTTGCCTCAGGTGAGCCATG    XM_001626548.3 (+), F straddles a junction 10+10
>junction_plus_R     TGTTCCTCTGACCAGCTCAC    → cDNA 224 bp, NO gDNA product, junction badge ✓

>junction_minus_F    CAAGTCCTAGTTGACTTGAC    XM_032374171.2 (−), the REVERSED mapping
>junction_minus_R    ATCATTGTCTTGATGCACTC    → cDNA 245 bp, NO gDNA product, junction badge ✓

>intron_span_F       AGAGTTCGCAGGCTCATCAG    XM_001626548.3 (+), both primers internal
>intron_span_R       TGACTTCAGCATACAGACTG    → cDNA 274 vs gDNA 1,255 bp, "Spans 2 introns" ✓

>single_exon_F       TCGTGAAGGACTGTGGGTAC    XM_001626548.3 (+), negative control
>single_exon_R       TGCGCTGTAACATGAGAAAC    → cDNA and gDNA both 318 bp, "no intron" ✓
```

`junction_minus` is the one to keep: it is the only fixture that fails if the minus-strand
walk regresses. Its forward primer splits **638932-638941 | 637585-637594**, exactly 10+10 at
the junction, with the genomic strand flipped to −1.

The original pair from phase 1 still works and is worth keeping for continuity:

```
>NvCofilin_F   GCCGCACCTCTAATCAATTC   → cDNA 494 bp, gDNA 6,389 bp, now "Spans 2 introns"
>NvCofilin_R   TAGGTGCTTCGTCACTACAC
>NvJunction_F  CCTTCAGCCATTATGTCGAA   → cDNA 349 bp, NO gDNA, NOW HAS A BROWSER LINK
>NvJunction_R  TAGGTGCTTCGTCACTACAC
```

⚠️ **`scripts/design_exon_testers.php` does NOT check specificity** — it never runs BLAST. On
Amphimedon it produced a junction pair landing in a three-member paralog family, which is a
realistic case but not a clean fixture. Always confirm a generated pair on the page.

### UI follow-ups

- ✅ **Step 3 "Search options" balance** (user, 2026-08-07; screenshot
  `primerblast-options.png`). "Mismatches allowed" and "Largest product to report" each held a
  bordered form control while "PCR input" held bare radios, so the three cards did not read as
  a set. The radios are now wrapped in a `.form-control` div — **the same class the sibling
  inputs use**, so the frame is identical by construction rather than matched by eye and left
  to drift. Nothing in `css/` overrides `.form-control`, so that is Bootstrap's rule set
  unchanged.

### Decisions worth not re-litigating

- **The verdicts now state fact, not inference.** With the index present, "Likely spans an
  intron" becomes "Spans 2 introns" and "Usually means a primer spans an exon junction"
  becomes "The forward primer sits across an exon junction". Both fall back to the old
  hedged wording when a gene set has no index — most did until the backfill.
- **`ExonMap::toGenomicBlocks()` requires STRICT containment** and returns `[]` otherwise. A
  BLAST hit cannot run off its own subject, so an out-of-range request means the index and
  the FASTA disagree (stale index, CDS-only FASTA, poly-A tail). The right answer there is no
  picture, not a picture shifted by an unknown offset.
- **`mapProduct()` is all-or-nothing.** One primer placed and the other missing would draw as
  a lone box that reads like a finding.
- **The amplicon parent is drawn as a single span, not as its exon blocks.** Considered and
  left out: the parent already carries the primers as subfeatures, and adding grey exon
  blocks under coloured primer boxes muddied the one thing the picture is for. Revisit only
  if someone asks to see the amplicon's exon structure.
- **Index files are `chmod 0660`.** They are written both by apache (registration, the admin
  button) and by the deploying user (the backfill); the default `0640` leaves whichever did
  NOT create it unable to overwrite, so Regenerate fails on exactly the files a backfill
  produced. Found on the one pre-existing file, which was `0640`.
---

# 🚧 BUILD STATUS — phase 1 engine started 2026-08-06

**Written and green** (56 assertions, `php tests/primer_smoke_tests.php`; the two existing suites
still exit 0):

| file | what |
|---|---|
| `lib/primer/PrimerInput.php` | four input shapes, pairing rules, validation — nothing dropped silently |
| `lib/primer/PrimerBlast.php` | `blastn-short` invocation, `-outfmt 6` parsing, strand from `sstart > send` |
| `lib/primer/PrimerPairs.php` | products from hits: opposite strands + facing, sizes, counts |
| `tests/primer_smoke_tests.php` | hermetic, no framework, no site data |

**Verified against the known-answer wallaby pair** (`WALLABY_T2T_chr2_003547.1`):

```
GENOME         7,742 bp  chr2:75263833-75271574   maxMM=0     ← matches the hand-measured value
               8,468 bp  chr2:75263833-75272300   maxMM=1  [self-pairing]
TRANSCRIPTOME    520 bp  …003547.1:101-620        maxMM=0     ← matches the expected cDNA product
```

⭐ **The engine found a product the manual analysis missed.** The 8,468 bp genomic product is
**self-pairing** — the reverse primer pairing with a second, opposite-orientation hit of *itself*.
The earlier by-hand `awk` pass only considered forward×reverse combinations, so it never saw this.
That is a real potential amplification, and it vindicates considering **all** hits on a subject
rather than only cross-primer ones. Same-primer products are now formed and flagged
(`self_pairing`).

**Tests earned their keep immediately**, catching two defects: a call to a `pairsFromDelimited()`
that was never written (fatal on any CSV input), and synthesised names `primer_1`/`primer_2` being
suffix-matched as forward/reverse — which gave the right pairing by luck while skipping the
"paired by order" warning. Grouping on names we invent is circular; bare input now goes straight
to adjacency.

**Page shipped too** — named **`primer_blast`**, not `primers`, because a `primer_maker` page will
sit beside it later (user, 2026-08-06). ⚠️ This **revises** the earlier "ONE tool with modes"
conclusion in the PRODUCT SPEC: separate pages per task, **sharing `lib/primer/`**. Chaining
(design → check) happens by handing data between pages, not by one page holding both modes — which
is what the shared engine makes cheap.

| file | what |
|---|---|
| `tools/primer_blast.php` | controller |
| `tools/pages/primer_blast.php` | stepped form mirroring `blast.php` + results |
| `js/primer-blast.js` | source-list filter/selection (names are a contract with `includes/source-list.php`) |
| `config/tools_config.php` | registered as `primer_blast` |

**Verified live** (`http://172.16.2.52/moop/tools/primer_blast.php`, real POST, not a source diff):

```
Genome: 2 products     7,742 bp  chr2:75,263,833–75,271,574   0 mm  forward + reverse
                       8,468 bp  chr2:75,263,833–75,272,300   1 mm  the reverse primer with itself
  primer hits: forward 14, reverse 307
  2,134 combinations over the 50,000 bp limit · 3,760 too short · 6 over the mismatch limit
Transcriptome: 1 product   520 bp  WALLABY_T2T_chr2_003547.1:101–620   0 mm
  → "Spans at least one intron. Genomic 7,742 bp vs cDNA 520 bp…"
```

### Three defects found by DRIVING the page — none visible in the source

1. 🚨 **`lib/primer/` was created `750 smr:smr`, so php-fpm could not traverse it → site 500.**
   The FILES were all `644`; the **directory** blocked them. Exactly CLAUDE.md §11's traverse-bit
   gotcha, and worth adding to the checklist: **when adding a new `lib/` subdirectory, `chmod 755`
   the directory, not just the files.** Existing `lib/*` dirs are `755 smr:apache`.
2. **`display-template.php` requires `$display_config`**, not `$data['page_script']` — title +
   `content_file` + `page_script` live there. Setting only `$data` dies with a bare 58-byte
   `Error: display-template.php requires $display_config array`.
3. **`prepareSourceSelection()` never sees the radio.** It reads `organism`/`assembly` hidden
   fields that `blast.php`'s JS mirrors from the radio on submit; the radio itself posts
   `selected_source`. Without those hidden fields the page rendered fine, accepted the primers,
   echoed them back — **and silently ran no search**. `primer_blast.php` now reads
   `$_POST['selected_source']` directly, still treating it as a lookup key only (path resolved
   server-side from the access-checked source, per `blast.php`'s rule).

### And one correctness fix the live run exposed

The intron verdict originally compared `products[0]` of each database — i.e. the **smallest**
product. With two genomic products that is a coin flip: **a self-pairing artefact smaller than the
real amplicon would silently become the number the verdict is computed from**, giving a confidently
wrong answer. Added `PrimerPairs::primaryProduct()` — prefers a genuine forward+reverse product,
then fewest mismatches, then smallest — with tests that fail on the old behaviour.

⏭️ **Next:** help modal (`help_modal_trigger`) for the page; JBrowse linkouts per product row
(route 1 of §Seeing the products); then phase 2 once `primer3_core` is installed.

---

# 🗺️ BUILD ORDER (dependency-ordered, agreed 2026-08-06)

| Phase | What | Blocked on |
|---|---|---|
| **1. Primer BLAST (check)** | paste primer pairs → pick assembly/gene set → BLAST vs genome **and** transcriptome → group by target, opposite strands, both product sizes, pair count | **nothing** — foundation measured & working |
| **2. Regular designer** | paste a sequence → Primer3 → spreadsheet-ready table + oligo tails | ⛔ `primer3_core` **not installed** |
| **3. Exon file** | `genes.gff` → flat per-gene-set exon file, on the `feature_coords.tsv` precedent | nothing |
| **4. RT-PCR designer** | transcript → junction offsets → Primer3 `SEQUENCE_OVERLAP_JUNCTION_LIST` | phases 2 + 3 |
| **5. Chaining** | design output → check, no copy-paste round trip | phases 1 + 2 |

**Why phase 1 first:** it is the only phase with no external blocker, it delivers spec item 1 as a
usable tool on its own, and it exercises the riskiest technical unknown — the BLAT→BLAST filter
re-tuning — which is now measured (see §MEASURED below).

⏭️ **The one thing to do in parallel: install `primer3_core`** (2.3.6+). It blocks phases 2 and 4,
and it is a package install, not a code task. Add it to `housekeeping_environment_check` alongside
the other CLI tools once present.

⭐ **Exon coordinates are a DESIGN concern only.** User, 2026-08-06: *"the only reason to think
directly about exons is when designing primers, especially rt pcr primers."* Measurement agrees
(see §MEASURED): all three exon-related verification answers — same exon, different exons, and
junction-spanning — fall out of comparing a genome BLAST with a transcriptome BLAST, both against
databases that already exist.

➡️ **So phase 3 serves phase 4 and nothing else.** Verification never touches it. Do not let the
exon file become a prerequisite for shipping the checker; the only thing that genuinely needs to
know where a junction *is* is Primer3, when asked to deliberately design across one.

---

# 📥📤 INPUT RULES AND VENDOR OUTPUT FORMAT (2026-08-06)

User: *"we should probably allow for a user to paste in primers or upload a file of primers. we
need to detect if the primers are one per fasta record or if they are both in one record with
NNNNNNNN separator. the upload can be a tsv or csv file as well. we need rules. we need to see how
companies like IDT want their primer orders organized so that we use similar formats. we need to
make our primer generator to have output that can be used in this as input."*

## Mirror `tools/blast.php` — it already solved this page's shape

Confirmed by reading it: numbered `.step-badge` cards inside `.card-header.tool-header`, a
`.fasta-textarea-ids` textarea for the query, then Database / Advanced Parameters sections. The
primer page should read as a sibling, not a stranger.

⭐ **And `tools/blast.php:62-95` already carries the hard-won primer knowledge — read it before
touching short-query settings.** Its comments record two failures that exactly match what was
measured here:
- E-value: *"a real 20nt primer [went] from 566 hits to 26"* when an explicit `10` defeated
  `blastn-short`'s built-in default of 1000. **Short queries need the threshold RAISED.**
- DUST: left on, *"a primer containing a simple repeat is masked to nothing and returns ZERO hits
  with no warning: ATATATATATATATATATAT goes 202 -> 0, poly-A 2236 -> 0."*

  ⚠️ **CORRECTION (user, 2026-08-06).** An earlier draft of this note justified `-dust no` by
  claiming *"primers are routinely AT-rich."* **That is wrong** and the user was right to push
  back. A well-designed primer is **40–60% GC by definition** — it must be, to carry a GC clamp and
  a Tm near 60 °C. What DUST masks is **low-complexity / simple-repeat** sequence (`ATATATAT…`,
  poly-A), which is a different property from AT-richness, and a good primer is neither.

  `-dust no` is still correct, for three real reasons:
  1. It is **blastn-short's own upstream default** — turning it on is a deviation, not a baseline.
  2. **Masking hurts short queries disproportionately.** DUST's window is comparable to the length
     of a 20-mer, so masking a few bases guts the query, where on a 1 kb query it is a rounding
     error.
  3. **A checker must report a bad primer as bad, not as "no hits."** Users paste primers they did
     not design well — that is precisely what the checking mode is for. Silently returning zero
     hits for a low-complexity primer hides the actual diagnosis. Flag the low complexity in
     validation and still run the search.
- It also splits `$blast_program_selected` (what the user picked) from `$blast_program` (what runs),
  because without it the re-rendered form disagreed with the search that had just run. The primer
  page inherits that requirement.

## Input detection rules

Accept **paste** or **file upload**, and detect the shape rather than making the user declare it:

| # | Shape | Detection | Pairing rule |
|---|---|---|---|
| 1 | FASTA, **one primer per record** | `>` present, no long N-run | name suffix `_F`/`_R`, `_fwd`/`_rev`, `_left`/`_right` (case-insensitive) **first**; fall back to **adjacency** (records 1&2, 3&4…) |
| 2 | FASTA, **pair in one record**, N-separated | `>` present **and** a run of **≥3 N** inside a record | split on the N-run; left fragment = forward, right = reverse |
| 3 | **TSV / CSV** | tab or comma delimited, ≥2 columns | header-driven: `name`, `forward`/`left`/`F`, `reverse`/`right`/`R` |
| 4 | **Bare sequences**, one per line | no `>`, no delimiter | adjacency pairing; synthesise names (`primer_1_F` …) |

🚨 **Do NOT conflate `N` with `-`.** In this codebase a **dash** already means *exon junction*
(`runPrimer3_web.pl:148-159`, the design side) while **N** means *primer separator* (the check
side, per §"What a user does today"). Two different meanings on two different pages of the same
tool — the parser must never treat them interchangeably, and the UI should say which is which.

**Validation, applied to every parsed primer:**
- Characters: `ACGT` only, plus IUPAC ambiguity codes if we choose to allow them; **reject anything
  else with the offending record named** — never silently drop (the recurring failure shape in
  this codebase, cf. `[[bug_silent_write_failures]]`).
- Length: warn outside ~15–40 nt. Below ~15 the BLAST noise floor makes specificity meaningless
  (measured: 4,087 raw hits for two 20-mers); above 40 IDT recommends extra purification.
- **Odd number of primers → warn, do not guess.** Adjacency pairing on an odd count silently
  mis-pairs everything after the gap.
- Duplicate names → warn; vendor forms key on name.
- Strip whitespace and digits (people paste sequences with position numbers).

## Vendor output format — make the designer's output orderable as-is

Researched 2026-08-06. **IDT bulk oligo entry takes four columns:**

```
Sequence name <tab> Sequence <tab> Scale <tab> Purification
```

- **Scale:** `25 nmol`, `100 nmol`, `250 nmol` (larger scales exist for bulk).
- **Purification:** Standard Desalt, PAGE, HPLC.
- ⚠️ **Purification requires a scale of ≥ 100 nmol** — 25 nmol + PAGE is an invalid order. Encode
  this as a constraint in the UI, do not let the user emit an order that will be rejected.
- IDT recommends extra purification for oligos > 40 nt, and for cloning specifically — relevant
  because **tailed** primers cross 40 nt easily (a 20-mer + 13-base tail = 33; a 25-mer + tail = 38).

⚠️ **Unverified:** the exact upload *template* and accepted file types (xlsx vs. tab-delimited vs.
CSV) could not be confirmed — idtdna.com's ordering FAQ redirect-looped when fetched. The four
columns and the values above are confirmed; **check the live bulk-entry page before finalising the
export**, and consider offering both a tab-delimited paste block and a CSV download.

**Design consequences:**
- The designer's results table should carry `name`, `sequence`, `scale`, `purification` as real
  columns (defaults user-settable, per-row overridable) so **"copy the table → paste into IDT"**
  works with no reshaping. That is the user's stated requirement, and it also satisfies the
  spreadsheet requirement in the PRODUCT SPEC — one export serves both.
- **Export the TAILED sequence** in the vendor block (that is what is ordered), with the untailed
  core in a separate column — see PRODUCT SPEC §5.
- Generate vendor-safe unique names (`<seqid>_<set>_F` / `_R`); no spaces, and flag collisions.
- Other vendors (Thermo, Sigma, Twist, GenScript) use the same conceptual columns with different
  headers, so keep the column set in **config**, not hardcoded, and treat IDT as the first profile
  rather than the only one.

---

# 🧪 PRIMER PARAMETERS — researched + measured 2026-08-06

⚠️ **Read this section for PHASE 1 first.** The user's reason for asking was **hit evaluation**, not
design defaults: *"i was talking about good primer design for our primer blast. we need to know
about these to evaluate primer hits in the genome too. for example the dust parameter."* The design
tables further down are for phases 2/4 — §"HOW PRIMER PROPERTIES DRIVE HIT EVALUATION" immediately
below is the phase-1 material.

## ✅✅ FINAL PHASE-1 SPEC (user, 2026-08-06) — BUILD THIS, ignore the elaborations below

*"i think we nix all complicated calculations. where do we land in the genome, allow the user to
adjust our default mismatch count value. and report how many hits, how big they are, mismatch
count. i think we make an option to do just genome blast or transcript blast. if we do transcript
blast we should also do genome blast to make sure there are not other fragments that are able to be
amplified with standard pcr."*

**Inputs**
- Primers — paste or upload, four shapes auto-detected (see §INPUT RULES).
- Scope — assembly (genome) and/or gene set (transcriptome).
- **Mismatch tolerance — a default value the user can adjust. This is the ONLY knob.**
- Mode — **genome only**, or **transcript**.

**🔑 Mode rule: transcript mode ALWAYS runs the genome BLAST too.** The user's reason, and it is
the right one: a transcript-only check cannot see genomic sites that would still amplify under
standard PCR. Genome-only is a valid standalone mode; transcript-only is **not** offered.

**Processing** — `blastn-short`, `-dust no`, E-value 1000, `-outfmt 6`. Keep hits within the user's
mismatch tolerance. Group by target, require opposite strands, compute product size.

**Output, per primer pair**
- **how many** products form
- **where** each lands (target + coordinates)
- **how big** each is
- **mismatch count** for each primer of each product

### 👁️ Seeing the products (user, 2026-08-06)

*"it would be nice to view in the genome browser, or to use a tool jbrowse has to generate images
from the browser by giving it a genome and gff files, or we draw our own so a user can see them."*

Three routes, cheapest first:

1. **Link each product into JBrowse2 — nearly free, do this first.** MOOP already registers a
   `genome_browser` tool (`config/tools_config.php`) taking a `loc` context param and pointing at
   `jbrowse2.php`. A product at `chr2:75,263,833-75,271,574` is a link away, and every product row
   can carry one. ⏭️ `notes/…JBROWSE_43…`/`[[plan_jbrowse_43_followups]]` already wants `&highlight=`
   on BLAST and gene links — the same mechanism, so build it once and both benefit.
2. **Draw our own — and there is precedent in this repo.** `lib/blast_results_visualizer.php`
   (~1,900 lines) already renders BLAST hits; a primer-product diagram is its natural sibling.
   ⭐ **This is probably what the user actually wants to see**, because the useful picture is the
   amplicon drawn against the **exon structure** — which exons it spans, where the introns fall,
   why genomic and cDNA sizes differ. A generic browser view shows the locus; a purpose-drawn
   diagram answers the RT-PCR question directly.
3. **`@jbrowse/img` (JBrowse's static image export)** — heaviest: a Node CLI, server-side render
   cost per image, another dependency to install and keep current. Worth it only if *downloadable*
   static figures are wanted (for a paper or a lab notebook). Defer.

⚠️ Note route 2 needs exon coordinates — the same flat exon file as phase 3. So the *diagram* is
gated on phase 3 even though the numbers are not. Route 1 is not gated on anything.

🚫 **NIXED — do not build these.** Effective-Tm modelling; 3′-mismatch-position rules; likelihood
ranking/sorting; product-size bounds; "hide unlikely products" checkboxes; annotation labels like
"unlikely to prime". The mismatch tolerance is the single control, and the user reads the sizes and
judges. Everything below this block that describes such machinery is **recorded thinking, not
requirements** — it stays for context (and for phase 2+ if it ever earns its place), but the spec
is what is written here.

⚠️ Retained from that thinking because they are facts, not machinery: a hard floor is still needed
(4,087 raw hits from two 20-mers), the count must be **pairs not primer hits**, per-primer hit
counts are worth showing beside the pair count, and nothing may be dropped silently.

## PHASE 1 OUTPUT — earlier framing (superseded in part by the FINAL SPEC above)

*"maybe we just report predicted fragment size, how many potential hits and their sizes. yes, we
should be testing against specificity in our genome."*

**That is the whole deliverable.** Per primer pair, per selected assembly:

| what | why |
|---|---|
| **Predicted fragment size** — the intended product | the answer they came for |
| **How many potential products form** | the specificity verdict (count **pairs**, not primer hits) |
| **The size of every one of them** | ⭐ this is what makes the count *interpretable* |

🔑 **The sizes are the interpretation, not decoration.** An off-target product of a very different
size is often tolerable — it resolves on a gel and can be purified away. An off-target of *similar*
size to the intended product is a real problem, because the user cannot tell the two apart. A bare
count ("3 products") cannot distinguish those two situations; a list of sizes can, instantly.

Plus, from the two-database design (§MEASURED): the **cDNA** size alongside the genomic size, which
yields the exon classification for free.

⚠️ **Scope discipline — this supersedes the more elaborate ideas above.** Effective-Tm modelling of
off-targets and a user-supplied annealing temperature are **NOT phase 1**; they are refinements to
revisit only if reporting sizes proves insufficient. The guards (§below) exist to decide *which
products are worth listing*, not to build a scoring system. Report products and their sizes; let the
user judge.

## ⭐ HOW PRIMER PROPERTIES DRIVE HIT EVALUATION (phase 1)

A BLAST hit is not the question. **"Would this site actually prime?"** is the question, and primer
chemistry answers it. Five rules, in order of how much they matter:

**1. 3′-end integrity dominates — everything else is secondary.** Polymerase extends from the 3′
end, so a mismatch in the last few bases essentially abolishes priming, while a mismatch at the 5′
end barely matters. Two alignments with *identical* length and mismatch counts can therefore be a
real off-target and a non-event.
➡️ **Evaluate mismatch POSITION, not just count**: reject a hit with any mismatch within the last
~3–5 bases of the 3′ end; tolerate 5′ mismatches much more freely.
⚠️ This is why the naive "3′-anchored" filter measured earlier returned **948 hits** — it required
only `qend==20` while still allowing a mismatch *anywhere*, including at the 3′ end. The rule is
*no mismatch NEAR the 3′ end*, combined with an overall length/identity floor — never either alone.

**2. Low complexity explains the hit count — so report it, do not hide it.** This is the user's
DUST point. A low-complexity primer (`ATATATAT…`, poly-A) genuinely will prime in thousands of
places; that is a property of the primer, not a BLAST artifact.
➡️ Run with `-dust no` so the hits are visible, **then diagnose**: *"this primer is low-complexity,
which is why it has 4,000 hits"* — not a bare hit count the user must interpret, and never the
silent zero that DUST-on produces (`ATATAT…` 202 → 0, poly-A 2236 → 0, per `tools/blast.php:85-93`).
Compute complexity ourselves at validation time and say so up front.

**3. Effective Tm of the ALIGNMENT decides whether an off-target amplifies.** A site with 2
mismatches has a lower Tm than the perfect match; whether it primes depends on the annealing
temperature the user will actually run. This is the principle Primer-BLAST uses to rank off-targets.
➡️ At minimum, report mismatch count and position per off-target so the user can judge. Optionally
let them enter their annealing temperature and flag only off-targets plausible at it.

**4. A pair needs a PLAUSIBLE product size, or it is not a pair.** Two hits on the same target on
opposite strands 800 kb apart do not amplify.
➡️ Bound the pairing rule with a maximum product size (standard PCR realistically ≲ 3–5 kb; the
measured true pair was 7,742 bp genomic **because it spans introns** — so the bound must apply to
the **cDNA** product, or be generous on genomic and rely on the transcriptome comparison).
⚠️ Getting this bound wrong is the difference between "1 pair" and "0 pairs" on a perfectly good
RT-PCR primer set.

**5. Primer length sets the noise floor.** Shorter primers get more hits by chance, so the same raw
count means different things for an 18-mer and a 25-mer. Measured: two 20-mers → **4,087** raw hits.
➡️ Judge multiplicity relative to length; do not use one absolute hit-count threshold for all
primers.

**Together these say the checker's filter is not one number.** It is: overall identity/length floor
**+** 3′-end integrity **+** product-size bound, with complexity and length reported as context for
the count. `primerChecker`'s two guards (too short, too many mismatches) are the floor only — the
3′ rule and the product bound are what make the answer trustworthy.

### 🚨 OVER-REPORT, NEVER UNDER-REPORT — the governing rule (user, 2026-08-06)

*"i'd rather report more potential fragments than too few."*

**This overrides the filtering design above.** The asymmetry is real: a spurious listed product
costs a moment's judgement; a **missed** off-target costs a failed experiment and is invisible —
the user never learns it was there.

➡️ **So ANNOTATE, do not EXCLUDE.** The 3′ rule and the product bound stop being filters and become
**labels on rows that are still shown**:

| product | size | note |
|---|---|---|
| intended | 320 bp | ✅ |
| chr4:1.2 Mb | 340 bp | ⚠️ **similar size to intended** — would co-migrate on a gel |
| chr7:88 Mb | 2,100 bp | mismatch 2 bp from 3′ end — unlikely to prime |
| chr2:41 Mb | 47,000 bp | exceeds efficient amplification length |

That serves the stated preference *and* the earlier requirement that nothing be silently hidden —
better than reporting a count of what was removed, because the user can see the actual rows and
overrule the judgement.

**Revised structure:**
1. **A hard floor stays** — 4,087 raw hits for two 20-mers is unusable. Set it **generously** (a
   low length/identity minimum), and **always report how many fell below it**.
2. **Everything above the floor is listed**, each row annotated with why it is or is not likely to
   prime, and flagged when its size is close to the intended product.
3. **Sort by likelihood**, so the plausible products lead without the rest being hidden.
4. **The checkbox becomes "hide unlikely products", default OFF** — i.e. show everything by
   default, and let the user collapse. That is the inverse of the earlier draft below, and this
   version wins.

### ☑️ The guards are OPTIONS, not fixed rules (user, 2026-08-06)

⚠️ Superseded in part by the rule immediately above: default to **showing** and annotating rather
than filtering. The toggle semantics below still apply, inverted — off means "show everything".

*"we should make those guards an option. a check box, consider 3′ rule and the product bound."*
Right, and not merely as a preference — **each guard hides a legitimate technique**:

- **3′ rule off** — allele-specific / ARMS-PCR *deliberately* places a mismatch at the 3′ end to
  discriminate alleles. Forcing the guard would hide exactly the hits such a user needs to see.
- **Product bound off** — long-range PCR routinely targets products well beyond a few kb.
- **Both off** — "show me everything, I will judge", which is a reasonable stance for a specialist.

**Rules for implementing toggles here:**
- **Default both ON** — the defaults should serve the common case, and the common case is a normal
  PCR primer pair.
- 🚨 **Always report what a guard removed, as a count.** *"1,412 hits hidden by the 3′ rule"*, never
  a silently shorter list. This codebase's recurring defect is exactly the silent filter
  (`[[bug_silent_write_failures]]`), and a specificity tool that quietly hides off-targets is
  worse than one that shows too many.
- ⚡ **Toggling must NOT re-run BLAST.** Cache the raw hits per search and re-filter, so the
  checkboxes are instant. ⚠️ Bound the cache: 4,087 hits came from *two* 20-mers, so 50 pairs is
  ~100k hits — cache server-side keyed to the search, do not ship it all to the browser.

### ⚠️ "Hopefully the designer didn't return bad primers" — true for chemistry, NOT for specificity

The user's hope holds for what Primer3 can see: Tm, GC, length, GC clamp, hairpins, self- and
hetero-dimers. Primer3 will not return a primer that fails those.

🔑 **But Primer3 only ever sees the template you hand it.** It has no knowledge of the rest of the
genome, so a Primer3-approved pair can be perfectly designed *and* amplify five other loci. **That
gap is the entire reason Primer-BLAST exists**, and it is why the chaining step (spec item 4) earns
its place rather than being a convenience.

➡️ Two consequences:
- Do **not** relax the guards on the chained path just because the primers came from our designer.
  The designer certifies chemistry; only the checker can certify specificity.
- When a designed pair *fails* the check, say so plainly and explain why — *"Primer3 accepted this
  pair on the template; against the whole genome it forms 3 products."* That is a genuinely useful
  result, not an embarrassment, and it is the moment the tool proves its worth.

---

## Design parameters (phases 2 and 4 — not needed for the checker)

For design defaults **and** for the help text later.

## Published guidelines

| Parameter | Standard PCR | qPCR / RT-qPCR |
|---|---|---|
| Length | **18–24 nt** (optimum 18–22) | 18–22 nt |
| Tm | **55–65 °C** | **60–63 °C, ideally 60** |
| Tm difference between the pair | ≤ 5 °C | **≤ 3 °C** |
| GC content | **40–60 %** | 40–60 % |
| GC clamp | G/C within the **last 5 bases** of the 3′ end… | same |
| …but | **avoid > 3 G/C in the last 5 bases** — over-strong 3′ binding causes non-specific priming | same |
| Amplicon | 100–1000 bp | **70–200 bp** |
| Secondary structure | keep self-complementarity and **3′ self-complementarity** low — hairpins, self-dimers, hetero-dimers | same |
| RT-qPCR specific | — | **span an exon–exon junction** to defeat gDNA contamination |

The user's own primers sit right inside this: GC clamp present, Tm ≈ 57 °C.

## ⚠️ The two existing tools disagree with each other, and with the guidelines

| Setting | `getExonsForPrimers_inGene.pl` | `primer3.pl` (web) | Guideline |
|---|---|---|---|
| `PRIMER_OPT_SIZE` | 22 | 20 | 18–22 ✅ both fine |
| `PRIMER_MIN_SIZE` | 18 ✅ | **10** 🚨 | 18 — a 10-mer cannot be specific |
| `PRIMER_MAX_SIZE` | 25 | 25 | ✅ |
| `PRIMER_OPT_TM` | 60 ✅ | **55** ⚠️ | 60 for qPCR |
| Tm range | 57–63 ✅ | 50–65 ⚠️ | 55–65 |
| `PRIMER_MAX_GC` | **80** 🚨 | 60 ✅ | 60 |
| `PRIMER_GC_CLAMP` | 1 ✅ | 1 ✅ | ✅ |
| Product size | 100–150 ✅ | ladder from template length | 70–200 for qPCR |

**Consolidation must pick one set.** `PRIMER_MIN_SIZE=10` is the dangerous one — it lets Primer3
return a 10-mer that will prime everywhere. `PRIMER_MAX_GC=80` is wrong but rarely binding on these
genomes (see below). Neither tool constrains **pair Tm difference** or **3′ self-complementarity**,
both of which the guidelines call out; Primer3 can enforce both
(`PRIMER_PAIR_MAX_DIFF_TM`, `PRIMER_MAX_SELF_END`) and the port should set them.

## ⭐ MEASURED: base composition of MOOP's genomes — why "design in genes" matters here

User: *"people usually make primers around genes, often IN genes to make sure the GC% is high
enough for a good primer."* Measured on this host (~20 Mb sampled per file):

| organism | genome | transcript | CDS | CDS gain |
|---|---|---|---|---|
| *Notamacropus eugenii* | 41.2 % | 47.3 % | **49.3 %** | +8.1 |
| *Amphimedon queenslandica* | 36.2 % | 40.6 % | **41.5 %** | +5.3 |
| *Bipalium kewense* | 33.2 % | 33.2 % | **34.8 %** | +1.6 |

**The practice is confirmed by the data: CDS is GC-richer than genome in all three**, by +5 to +8
points typically — which is exactly the difference between "outside the 40–60 % window" and "inside
it". So:

➡️ **The designer should default to transcript/CDS sequence, not arbitrary genomic windows.** MOOP
already ships `transcript.nt.fa` and `cds.nt.fa` per gene set, so this costs nothing.

⚠️ **But it is not enough everywhere.** *Bipalium* CDS at **34.8 %** is still well below the 40 %
floor — designing there will push Primer3 against the GC and Tm minimums no matter what. That is
very likely why the user's primers came out at **≈57 °C** (the RT-PCR script's `PRIMER_MIN_TM`)
rather than the `PRIMER_OPT_TM` of 60: **the floor was binding, not the optimum.**

➡️ **So report when a constraint was binding.** "No primers found", "primers found at the Tm floor",
and "primers found comfortably inside the window" are three different messages, and on an AT-rich
organism the middle one will be common. Generic primer sites cannot say this; MOOP knows the
organism's composition and can. Worth surfacing per-organism GC as context on the design page.

## 🎯 Uniqueness is the point — but not always

User: *"you need primers to be unique in a genome, unless your point is for them to not be unique
and to amplify lots of fragments."*

Both are legitimate goals, so **the checker must report multiplicity neutrally and let the user
judge**, rather than treating >1 as failure:

- **Default framing:** one pair forming at one locus = specific ✅; more than one = ⚠️ review, with
  every locus listed so the user can see whether they are paralogues, a gene family, or junk.
- **Deliberate multi-target design is a real use case** — gene families, degenerate primers, repeat
  families. Do not word the UI so that this reads as an error the user must fix.
- This is exactly why the count must be **pairs, not primer hits** (see §MEASURED): 15 stray hits
  for one primer meant *one* pair. Reporting raw hit counts would make every specific primer look
  promiscuous.

⭐ **User, 2026-08-06: *"sometimes one primer can be more abundant if the 2nd of the pair is more
unique. the combination is what works together to make your fragment."*** The measured test is a
textbook instance: p2 = **15** genomic hits, p1 = **1**, pairs = **1**. Specificity rested entirely
on p1.

➡️ **So show per-primer hit counts ALONGSIDE the pair count — not instead of, and not hidden.**
The pair count is the verdict; the per-primer counts say *where the specificity comes from*, which
is actionable in a way the verdict alone is not:

```
p1   1 hit    ┐
p2  15 hits   ├─  1 pair  ✅ specific — but carried entirely by p1
```

A user who then redesigns p1 (for Tm, for a tail, to move it) **loses the only thing making the
assay specific**, and nothing in a bare "1 pair ✅" would warn them. Flag the asymmetric case
explicitly: *"specificity depends on p1 alone — keep it if you revise this pair."*

⚠️ It also changes what a redesign loop should do. If a pair fails specificity, the useful advice is
**"replace the promiscuous primer, keep the unique one"** — which requires knowing which is which.

---

# ⭐⭐ PRODUCT SPEC — the user's own words, 2026-08-06

This supersedes the sketch further down. Three capabilities, **one tool**, because the user wants
them to chain:

**1. Primer BLAST (check).** *"a user can load primers to blast against a genome to check product
size and if they get more than one hit in the genome."* Two answers: **product size**, and
**is this pair specific** — more than one place in the genome where a valid pair forms is the
warning. This is `primerChecker`'s job, already implemented (see §primerChecker below).

**2. Primer designer (regular).** *"a user can upload a seq to design regular primers and get
good output for copy and pasting into a spreadsheet."* ⭐ **The copy-paste-into-a-spreadsheet
output is a stated requirement, not a nicety.** `parsePrimer3.pl` already emits tab-delimited
rows for exactly this reason — that instinct is right and must survive the port. Reuse MOOP's
shared results table so the user also gets TSV/CSV export for free.
⚠️ Per CLAUDE.md §9b, key those columns **by name, not position** — the results-table work on
2026-07-23 found four defects caused by positional column identity.

**3. RT-PCR primer maker.** Junction-spanning design, from a transcript (junctions computed) or a
pasted dash-separated sequence. This is `RT-PCR_primers` + the dash trick from `runPrimer3_web.pl`.

**4. Chaining — the requirement that decides the architecture.** *"if a user chooses, they can
design primers and then check against a genome to see the product sizes and to see if they match
in a lot of places."* Design output must feed the checker without a copy-paste round trip.

➡️ **So this is ONE tool with modes (`tools/primers.php`), not three pages.** That settles the
"where does it live" open question at the bottom of this note: a mode on `blast.php` cannot host
a designer, and three separate pages cannot chain without duplicating state.

**5. Oligo tails — 5′ cloning adapters.** *"we can also add the T4P or 'other' oligo additions to
the primer generation."*

**What they are** (user, 2026-08-06): *"oligos that are attached to the primer sequence and sent
in with the primer order. we add those when we pcr amplify a sequence so that they are in the new
amplified dna to help with cloning."* So they are **5′ tails ordered as part of the oligo**, which
end up incorporated into the amplicon for downstream cloning. `CATTACCATCCCG` (left) /
`CCAATTCTACCCG` (right) are one such pair; the UI should name the purpose ("5′ cloning tail /
adapter") and offer a **named, configurable list** plus a free-text "other" — not two hardcoded
strings buried in a CGI.

⭐ **The existing workflow already has the right ORDER OF OPERATIONS, and it is the spec.** User,
2026-08-06: *"when i ran the checker, i didn't include the t4p, only the primer without it, which
is why i find the primer, and get all the stats then append the oligo and report both."* So:

```
design on the bare template → compute all stats on the untailed primer
    → check specificity on the untailed primer → THEN append the tail → report BOTH
```

Points 2 and 3 below are therefore **properties to PRESERVE**, not bugs to fix — they fall out of
that ordering for free, and any port that appends tails earlier would break them silently. Point 1
is a small gap in one output surface; point 4 is genuinely missing.

1. **Report both forms everywhere.** The HTML table already does this correctly — `$fields[9]`
   untailed alongside `$fields[10]` tailed. The **FASTA block below it emits only the untailed
   form**, with its tailed branch commented out (§t4p). So the download is simply incomplete
   relative to the table the user is looking at, not contradicting it. Fix by making one code path
   produce both forms and every surface — table, FASTA, TSV — render from it.
2. ✅ **Tm and GC are computed on the ANNEALING (untailed) portion** — correct, and inherent to
   the ordering above. The tail does not base pair with the template in early cycles, so a Tm over
   the full tailed oligo would be wrong for the reaction. Primer3 designs on the template and never
   sees the tail. **Keep it that way**; never feed a tailed sequence back into a Tm calculation.
3. 🚨 **Tails MUST be stripped before any genome specificity check.** This is the trap in the
   design→check chaining (spec item 4). As the user put it: *"the adaptor is not in the genome,
   well it should not be."* Exactly — so a tailed oligo aligned against the genome can only ever
   produce a partial, lower-identity hit, inflating mismatch counts and potentially dropping a
   perfectly good primer below the score cutoff. The checker must always receive the untailed core.

   ⭐ **And the "should not be" is worth actually testing — a free QC step the original code never
   does.** Search the tail *by itself* against the selected assembly once, and warn if it matches.
   A tail that happens to occur in the genome is a real mispriming risk, and this matters most for
   the free-text "other" option, where the user is supplying a sequence nobody has vetted against
   *these* 85 organisms. Cheap: one short query, run once per tail per assembly, not per primer.
4. ⚠️ **Report both product sizes.** The amplicon that comes out of the tube is the genomic
   product **plus both tails**; the existing code only ever reports the untailed product size
   (`$fields[4]` is never adjusted). Both numbers matter — the insert size and the actual amplicon
   — so give both rather than silently picking one.

Also worth surfacing: total oligo length after tailing, since vendors cap synthesis length and
price per base — a tailed primer can quietly cross a threshold the untailed one did not.

**Open questions this spec raises:**
- **Scope of the check** — one assembly, or "check against every organism"? The latter inherits
  the ~98-assembly fan-out cost (`notes/QUERY_PERFORMANCE.md`), and `primerChecker`'s README
  already warns its web version times out on a single genome unless the FASTA is chunked.
  **Start with one assembly; treat cross-organism as a later mode.**
- **"More than one hit" needs a definition.** A pair that forms a valid product at 2 loci is a
  real specificity failure; a single primer with extra hits whose partner never co-occurs is not.
  `primerChecker` already groups by target and requires opposite strands, so the count of targets
  yielding a valid pair is the right number to report.
- Which alignment engine — see the BLAT-vs-BLAST decision below.

---

# 📦 WHERE IT LIVES — recommend the moop repo (decided 2026-08-06)

User asked: new repo, `moop-dbtools`, or the moop repo? And added the requirement that the tool
*"can interface with our data or just use any inputted sequence."*

**Recommend: the moop repo.**

- **The web tool IS MOOP app code.** `tools/primers.php` + `tools/pages/primers.php` + a JS module
  + an entry in `config/tools_config.php`. The app repo is defined as *"everything needed to set
  up a new MOOP site from scratch"* — put the tool elsewhere and a fresh install silently lacks it.
- **Not `moop-dbtools`.** That repo is the **offline loading pipeline** (GFF/InterProScan/DIAMOND
  → SQLite), run on the cluster. The primer tool loads nothing into SQLite; it reads files that
  already exist, during a web request. Different lifecycle, different host, different language.
- **Not a new repo.** It would add a deploy step, a version-skew surface, and a second place to
  run tests, for code with no independent consumer. The genuine argument for separation — "others
  could use it standalone" — is **already served by the three existing public repos**, which stay
  exactly where they are as reference implementations and provenance.

**The precompute follows an exact existing precedent.** The flat exon file is the same class of
artifact as `feature_coords.tsv`, which is built by `lib/jbrowse/gene_set_functions.php:523` from
the sorted GFF3 at JBrowse registration (step 4 of the registration sequence), with
`admin/api/generate_feature_coords.php` as the manual regeneration endpoint and a status column on
`admin/manage_blast_linkouts.php`. **Generate the exon file the same way, in the same place, on the
same trigger** — one gene-set directory, one lifecycle, one regeneration control. Splitting it into
`moop-dbtools` would put two halves of one artifact class in two repos.

## Layering that satisfies "our data OR any pasted sequence"

This is an architecture requirement, not a repo one. Keep the engine ignorant of MOOP:

```
lib/primer/…        engine. Takes a plain sequence (+ optional exon offsets, + optional
                    tail config) and returns primer pairs / check results.
                    Knows nothing about organisms, gene sets or SQLite.
   ▲            ▲
   │            │
pasted input    MOOP data adapter — resolves organism/assembly/gene set → transcript
                sequence from transcript.nt.fa + junction offsets from the exon file
```

**MOOP data is an optional input source, never a dependency.** That keeps the paste-any-sequence
path working with zero MOOP context, makes the engine unit-testable without site data (matching
the hermetic-tests rule, CLAUDE.md §12), and means the two front doors of the spec are one code
path with two adapters.

---

# ⭐ CODE REVIEW — the user's three existing repos (2026-08-06)

Both were located and read. They are **public** on GitHub.

| Repo | Scripts | What it is |
|---|---|---|
| `srobb1/RT-PCR_primers` | `getExonsForPrimers_inGene.pl` (102), `parsePrimer3.pl` (86) | CLI. Gene names + a BioPerl SeqFeature::Store SQLite DB → junction-spanning primers |
| `srobb1/primer3_tab` | `runPrimer3.pl` (186), `runPrimer3_web.pl` (249), `writePrimer3input.pl` (36), `parsePrimer3.pl` (86) | CLI + a **CGI web form**. Plain FASTA in → tab-delimited primers out |

A local working copy at **`/home/smr/primer3_tab`** additionally holds the **live** version
copied off the old webserver — see §"the drift" below.

## 🔑 The two repos are two halves of ONE idea

`runPrimer3_web.pl:148-159`: if a pasted sequence contains `-`, it splits on the dashes,
accumulates fragment lengths, and emits `SEQUENCE_OVERLAP_JUNCTION_LIST`. That is **the same
junction mechanism** as the RT-PCR script — only with the user marking exon boundaries by hand
instead of them being computed from a database.

That also explains the orphan comment in the RT-PCR script (`## for some reason primer3 is not
liking the '-'`, plus the commented-out `$seq_dash = join('-',@exons)`): a leftover of this dash
convention.

**So the MOOP tool is one engine with two front doors** — paste a sequence with dashes, *or*
give a transcript ID and let MOOP compute the junctions. Identical code path after that.

## ✅ What MOOP can reuse instead of BioPerl + SQLite — VERIFIED on this host

The SeqFeature::Store exists only to answer three questions, and every gene-set directory
already answers all three:

- **Spliced transcript sequence → `transcript.nt.fa`.** Verified, not assumed: a 24-exon
  **minus-strand** wallaby mRNA (`WALLABY_T2T_chr2_003547.1`) has GFF exon lengths summing to
  **4,598**, its FASTA record is **4,598**, and its first 60 bases are **exactly the reverse
  complement of the highest-coordinate exon**. So the file is spliced, reverse-complemented,
  and in transcript order — precisely what the script builds by hand, already built and already
  BLAST-indexed.
- **Junction offsets → `genes.gff`**, same directory, `Parent=`-linked exon lines (242,692 in
  that gene set). Cumulative exon lengths, one sequential pass.
- **Nothing else.** No genome slicing, no `bp_seqfeature_load.pl`, no BioPerl, no new database.

⚠️ **`feature_coords.tsv` canNOT supply this.** It is 6 columns — id, parent, seqid, start,
end, strand — one row per feature, **no exon rows and no type column**. The GFF is the source.

⚠️ **Use `transcript.nt.fa`, never `cds.nt.fa`.** The former is exon-based (includes UTRs) and
matches what the script concatenates today; the latter is CDS-only. Exon-derived offsets against
a CDS sequence put every junction in the wrong place.

**Consequence: the entire first half of `getExonsForPrimers_inGene.pl` disappears**, and defect
#1 below disappears with it. What remains is small enough to be a rewrite in PHP rather than a
port.

## 🐛 Defects found

**In `getExonsForPrimers_inGene.pl`:**

1. **`$strand` is computed at lines 42-43 and never used again** (grep: zero later references).
   Exons are therefore always sorted by genomic start and sequence always fetched plus-strand,
   so for a minus-strand gene the template is the reverse complement of the real mRNA.
   The primer *pair* survives this — PCR is double-stranded, and junction offsets map correctly
   under reverse complement — but **`SEQUENCE_INCLUDED_REGION`, set to the last 25%, then targets
   the 5′ end of the real transcript**, inverting the intended 3′ bias for oligo-dT-primed cDNA.
   Silent, and it hits roughly half of all genes.
2. **`SEQUENCE_INCLUDED_REGION` fights the junction list.** Primer3 requires a returned pair to
   overlap a listed junction *and* sit inside the included region; any gene whose junctions all
   fall before the final 25% returns nothing, with no explanation. (Confirm against the 2.3.6
   manual — `scripts/primer3_manual.htm`, committed in `primer3_tab`.)
3. 🚨 **`print "$f_name has only one exon" if @exons == 0;` — misreports, and hides the dangerous
   case.** `@exons == 0` is *no* exons matched. A genuine single-exon gene gives `@exons == 1`;
   `pop @overlaps` then empties the list, so the record carries an **empty**
   `SEQUENCE_OVERLAP_JUNCTION_LIST=` and Primer3 designs primers with **no junction constraint** —
   primers that will happily amplify genomic DNA, printed indistinguishably from real RT-PCR
   primers. The `@exons == 0` case meanwhile emits an empty `SEQUENCE_TEMPLATE=`.
4. **`get_features_by_name` can return several features** (gene + mRNA, paralogs); all are pushed
   and all written under the same `SEQUENCE_ID`. In the parser `$count` resets on each
   `SEQUENCE_ID=` line, so the second block silently overwrites the first.
5. **The exon filter `next unless $parent_id eq $f_load_id`** assumes the looked-up feature is the
   exons' direct parent — true for mRNA names, false for gene names (whose exons hang off the
   mRNA). Undocumented precondition; the README does say transcript names, the code does not
   enforce it. ⚠️ Note MOOP's own parent linkage had exactly this class of bug —
   `[[project_db_loader_hierarchy_bugs]]`, `parent_feature_id` holding the string `'NULL'`.
6. Hardcoded `/home/smr/src/primer3-2.3.6/src/`, with an older `/rhome/robb/...` commented above
   it — this has already bitten once.
7. Backticks interpolating `$gene_file` unquoted; `primer3_core`'s exit status never checked (a
   failed run yields an empty `.primer3out` and a header-only table); `open IN, $gene_file` is a
   2-arg open.
8. Dead/noise: `$product` computed and unused; a stray `SEQUENCE_INCLUDED_REGION` printed to
   stdout mixed into the results stream.
9. All Primer3 settings hardcoded (product size 100–150, Tm, GC) — must be parameters.

**In `parsePrimer3.pl` (byte-identical in both repos — `diff -q` confirms, two copies already
positioned to drift):**

10. **`use strict` but no `use warnings`**, and `$primer{$id}{1}{SEQUENCE}` shares key `1` with
    primer set 1. When Primer3 finds no primers for a gene, key `1` exists holding only the
    sequence, so the loop prints **a row of empty columns instead of "none found"** — silently,
    because warnings are off.

**Three writers, three different Primer3 tag conventions:**

| File | ID tag | Sequence tag |
|---|---|---|
| `writePrimer3input.pl` | `PRIMER_SEQUENCE_ID` | `SEQUENCE` (1.x) |
| `runPrimer3_web.pl` | `PRIMER_SEQUENCE_ID` (1.x) | `SEQUENCE_TEMPLATE` (2.x) — **mixed** |
| `getExonsForPrimers_inGene.pl` | `SEQUENCE_ID` | `SEQUENCE_TEMPLATE` (2.x) |

The committed `parsePrimer3.pl` anchors `^SEQUENCE_TEMPLATE=` but leaves `/SEQUENCE_ID=(.+)/`
**unanchored**, so it matches `PRIMER_SEQUENCE_ID=` only as a substring — by luck. Settle on one
convention and test which 2.3.6 actually accepts.

**In the CGI (`runPrimer3_web.pl`) — matters because it is the closest thing to a MOOP page:**

11. **Temp files collide.** `/tmp/$time.in_primer3` with `$time = time()`, opened **`>>`**
    (append): two users in the same second have their sequences merged into one run and one sees
    the other's primers. The handle is opened inside the loop and closed once, after it.
    Predictable `/tmp` names are also a symlink-attack surface.
12. **Reflected XSS** — the FASTA header `$id` is printed into HTML unescaped (`:171`), and again
    through the results table (`:232`).
13. Hardcoded `/usr/lib/cgi-bin/primer3/parsePrimer3.pl`; `primer3_core` taken from `PATH` here
    but absolute in the other repo.
14. **`runPrimer3.pl:79` rewrites the user's input file IN PLACE** —
    `` `perl -p -i -e 's/\r/\n/g' $file` `` — a destructive surprise plus a shell-injection vector.

## The drift: `/home/smr/primer3_tab` vs. what is committed

The live copy off the old webserver adds **`scripts/primer3.pl` (282 lines, untracked)** — a
newer evolution of `runPrimer3_web.pl` — and modifies `parsePrimer3.pl`.

Gained in the live version:
- `$primer3_binary` / `$parsePrimer3_binary` hoisted to variables at the top (still absolute:
  `/usr/bin/primer3_core`, `/var/www/cgi-bin/parsePrimer3.pl`).
- `PRIMER_NUM_RETURN` (capped at 25) and `PRIMER_SALT_CORRECTIONS` exposed in the form.
- **A "t4p" checkbox that prepends adapter tails** — `CATTACCATCCCG` to left primers,
  `CCAATTCTACCCG` to right. ⚠️ Ask what assay this is for; it is undocumented and is the kind of
  lab-specific detail a general tool should make explicit rather than inherit silently.
- FASTA output of the primers, below the table.

⚠️ **Lost in the live version: the dash → `SEQUENCE_OVERLAP_JUNCTION_LIST` logic is GONE.** The
junction feature — the whole RT-PCR mechanism — exists in the *committed* `runPrimer3_web.pl` and
not in the newer live `primer3.pl`. Do not treat the live file as strictly newer-and-better.

🚨 **`unlink` of the two `/tmp` files is COMMENTED OUT** in the live version, so a public web form
has been accumulating temp files indefinitely.

The `parsePrimer3.pl` edits track the tag change (`SEQUENCE_ID`→`PRIMER_SEQUENCE_ID`,
`^SEQUENCE_TEMPLATE=`→`^SEQUENCE=`) and move `SEQ_LENGTH` off the real sequence length onto the
`PRIMER_PRODUCT_SIZE_RANGE` maximum. ⚠️ But `primer3.pl` still **writes** `SEQUENCE_TEMPLATE=`,
which `^SEQUENCE=` cannot match — so that branch is now dead code and the `SEQLength` column no
longer means sequence length. Also `PRIMER_PAIR_(\d*_)?PRODUCT_SIZE` → `PRIMER_PAIR_(\d*)_PRODUCT_SIZE`
now **requires** the numbered form, which is right for `PRIMER_NUM_RETURN>1` and wrong for legacy
unnumbered output.

## Where the live code actually lives (confirmed by the user 2026-08-06)

On **`genomes`** (the current simrbase host — NOT `simrbasenew`, whose `/var/www/cgi-bin/` is
empty), `/var/www/cgi-bin/` holds:

```
parsePrimer3.pl  primer3.pl  primer3.plBAK  retrieve_seq.pl  web_blast.pl  web_retrieve.pl
```

The user confirms **only `parsePrimer3.pl` + `primer3.pl` serve the primer3 app** — which is
exactly the pair copied into `/home/smr/primer3_tab`, so that copy is complete.

### ✅ `primer3.plBAK` — diffed 2026-08-06, settled

The BAK was copied to `/home/smr/primer3_tab/scripts/` and diffed. It is a **small delta
immediately before the salt-correction change** (267 lines vs. the live 282), not an older
lineage:

- live adds `PRIMER_SALT_CORRECTIONS` + its form field;
- live fixes the primer FASTA header — BAK emitted `>primer_<set><orient>_len<n>` with **no
  sequence ID**, so a multi-sequence run produced identically-named records. Live prepends
  `$fields[0]`. A real fix; keep it.

**The junction logic is NOT in the BAK either.** Confirmed across all three:

| file | `SEQUENCE_OVERLAP_JUNCTION_LIST` |
|---|---|
| `runPrimer3_web.pl` (committed) | ✅ has it |
| `primer3.plBAK` (live, previous) | ❌ |
| `primer3.pl` (live, current) | ❌ |

So the dash→junction feature survives **only in git**, in the committed `runPrimer3_web.pl`.
Nothing is lost — but the live server has not been running it, and the BAK does not explain when
it went. No further archaeology needed; the feature is recoverable from the repo.

### t4p is wired into the table but not the FASTA

Present in **both** BAK and live: the t4p checkbox rewrites `$fields[10]` so the HTML **table**
shows tail+primer alongside the bare primer in `$fields[9]`. **That table is correct** — it
reports both forms, which is exactly the intent (stats computed on the untailed primer, tail
appended at report time; see the PRODUCT SPEC §5).

The gap is only in the **FASTA block below the table**, which emits `$fields[9]` alone — its t4p
branch is **commented out** in live and absent in BAK. So the download is incomplete relative to
the table, not contradicting it. In MOOP, have one code path emit both forms and every surface
(table, FASTA, TSV) render from that, so the surfaces cannot drift apart.

`retrieve_seq.pl`, `web_blast.pl`, `web_retrieve.pl` are the pre-MOOP CGI ancestors of today's
BLAST and Retrieve Sequences tools. Not needed for the primer work — but they are the only
surviving record of the old interface, so read before deleting.

## ⭐ Third repo: `srobb1/primerChecker` — this IS the "check" half, and it is further along than this plan assumed

2,564 lines, the largest of the three, with an `old/` directory of five superseded versions.
Commit history shows real field use, not a sketch: *"reworked code to look in current transcript
for primer match in cDNA"*, *"updated for smed and cca. smed needed the change for mrna check and
with cca i saw that we needed to allow for p1 and p2 to hit in the same exon"*.

| Script | Lines | Role |
|---|---|---|
| `primerChecker.pl` | 599 | the engine |
| `primerChecker_web.pl` | 499 | CGI front end |
| `getTranscriptInfo_fromGFF.pl` | 51 | **GFF → exon structure, no BioPerl** |
| `getTranscriptInfo_fromSFF.pl` | 59 | same, via SeqFeature::Store |
| `primerChecker_splitFasta.pl` | 73 | chunk the genome so the web run doesn't time out |

**What it does** (`primerChecker.pl`): BLAT both primers against the genome
(`-tileSize=7 -minScore=10` — short-sequence tuning, the BLAT analogue of `blastn-short`), parse
PSL, drop short/high-mismatch hits, then per target require **two primers on opposite strands**,
sort by start, and compute product size. Then the part this plan never specced:

- **Exon scoring per primer** (`getExonInfo`): `1` = primer wholly inside one exon, `0.5` = primer
  overlaps a junction, `0` = not in an exon. This is a junction-spanning **verifier** — the exact
  mirror of what Primer3 does on the design side.
- **Genomic product size *and* cDNA product size**, reported as `$product_size|$cDNA_product_size`.
  The two differing IS the intron-spanning answer, computed rather than eyeballed.
- Hand-rolled interval arithmetic (`range_create/check/overlap`) for all of the above.

⭐ **`getTranscriptInfo_fromGFF.pl` already writes the flat exon file this plan concluded MOOP
needs** — `transcript_exons_info.txt`, one line per transcript:

```
name <tab> ref <tab> strand <tab> start,end <tab> e1s,e1e;e2s,e2e;e3s,e3e...
```

That is effectively the `exon_coords.tsv` proposed above as the in-pattern alternative to a
per-gene-set database. **The user already built it, BioPerl-free, from GFF.** Strong confirmation
of the direction — and it means the "no new SQLite" conclusion is not a compromise.

### ✅ DECIDED 2026-08-06: move from BLAT to BLAST

The user chose BLAT originally because *"it was fast and easy to parse"* — both good reasons, and
both already satisfied on MOOP's side by BLAST. **User is OK with moving to BLAST.** BLAT is also
not installed on simrbasenew (checked).

What MOOP already has that answers the original two reasons:

- **Short-query tuning exists**: `-task blastn-short` with E-value 1000, DUST off, word size 7 is
  implemented and verified in `tools/blast.php`, and `executeBlastSearch()` passes the full
  advanced option set including `-strand` and `-perc_identity`.
- **Parsing is solved**: use **`-outfmt 6`** for this path rather than the HTML-oriented
  `parseBlastResults()`. Tabular BLAST is as trivially parseable as PSL and gives exactly the
  columns the checker needs — `length`, `mismatch`, `qstart qend sstart send`.

⭐ **Bonus: moving to BLAST deletes the chunking hack.** `primerChecker`'s README requires
*"split the fasta into smaller fastas or the website will timeout while blat is running"*, which is
why `primerChecker_splitFasta.pl` exists. MOOP already builds and maintains per-assembly BLAST
databases (Admin → Organism Checklist → Build BLAST Index), so that whole workaround —
and the script — goes away.

**Two things the migration must get right, neither automatic:**

1. 🚨 **Re-tune the hit filters, and verify rather than assume.** BLAT with `-minScore=10` is a
   seeded aligner biased to near-identical matches; `blastn-short` at E-value 1000 will return far
   more marginal hits. The specificity answer — *"do they get more than one hit in the genome"* —
   is a **direct function of that filter**, so a looser engine silently inflates the very number
   the user is asking for. Port `primerChecker`'s two existing guards (drop alignments that are too
   short; drop those with too many mismatches) onto `length`/`mismatch` and re-calibrate.
2. ⚠️ **Strand is represented differently.** BLAT has an explicit strand column; in `-outfmt 6` the
   subject strand is implied by `sstart > send`. The pairing rule ("one primer on +, one on −") is
   the core of the checker, so this mapping is load-bearing — get it wrong and every pair either
   passes or fails.

### ✅ MEASURED 2026-08-06 — the BLAST foundation works, and the filter question is answered

Foundation on this host: `blastn`/`makeblastdb` at `/usr/local/bin`, **72 genome BLAST DBs**, 90
`transcript.nt.fa` DBs, 89 `cds.nt.fa`. `primer3_core` **absent** — blocks the design half, not
this one.

Test: two synthetic 20-mers cut from a real wallaby transcript (`WALLABY_T2T_chr2_003547.1`,
24 exons, minus strand) — p1 = transcript[100:120], p2 = revcomp(transcript[600:620]), so the
**expected cDNA product is 520 bp**. BLASTed against the genome with the existing short-query
settings (`-task blastn-short -evalue 1000 -dust no -word_size 7`, `-outfmt 6`):

| filter | p1 hits | p2 hits |
|---|---|---|
| none | 83 | **4,004** |
| `length>=18 && mismatch<=1` | 1 | **15** |
| `length==20 && mismatch==0` | **1** | **1** |
| 3′-anchored (`qend==20 && mismatch<=1`) | 10 | 948 |

**4,087 raw hits from two 20-mers.** That is the noise floor at E-value 1000, and it confirms the
warning above: the engine change is not a drop-in, and nothing may be rendered before filtering
(4k rows must never reach the browser).

The recovered pair, at `length==20 && mismatch==0`:

```
p1  chr2  75271574 → 75271555   (sstart > send  ⇒ MINUS)
p2  chr2  75263833 → 75263852   (sstart < send  ⇒ PLUS)
genomic product = 75271574 − 75263833 + 1 = 7,742 bp
cDNA product    = 520 bp   ⇒ spans introns, correctly detected
```

🔑 **THE KEY FINDING: count PAIRS, not primer hits.** At the loose `length>=18 && mismatch<=1`
setting p2 has **15** genomic hits — but p1 has only one, so **exactly one pair forms**. Reporting
"15 hits" would be alarming and wrong; reporting "1 pair" is the true answer to *"do they get more
than one hit in the genome."* The spec's definition is therefore not a nicety, it is what makes the
number meaningful — and it holds even when the filter is loose.

⭐ **Consequence: `primerChecker`'s architecture is the load-bearing part, not its aligner.**
Group by target → require opposite strands → require a plausible product size. That survives the
engine swap untouched, which is exactly why porting the logic rather than adding BLAT is right.

⚠️ **Do not simply adopt `length==20 && mismatch==0`.** It returned precisely the true pair here,
but a specificity checker exists to surface *mispriming*, which is a 1–2 mismatch phenomenon. Treat
the filter as a **tunable sensitivity knob** with the pairing requirement as the safety net — the
table above shows the pair survives at every setting while the noise does not.
⚠️ And note the naive 3′-anchored filter was **worse than useless** (948 hits): 3′-end integrity
matters biologically, but only in combination with a length requirement, never instead of one.

### ⭐⭐ MEASURED 2026-08-06 — check against BOTH genome and transcriptome, and the exon file is NOT needed for phase 1

Same two primers, now also BLASTed against `transcript.nt.fa` (the per-gene-set transcriptome DB,
90 of them exist):

```
p1  WALLABY_T2T_chr2_003547.1  101 → 120  (+)
p2  WALLABY_T2T_chr2_003547.1  620 → 601  (−)
cDNA product = 620 − 101 + 1 = 520 bp      ← exactly the expected value
```

| | genome DB | transcriptome DB |
|---|---|---|
| raw hits (2× 20-mers) | 4,087 | **1,033** |
| loose filter `len>=18,mm<=1` | p1=1, p2=**15** | p1=1, p2=**1** |
| product size returned | **genomic** 7,742 bp | **cDNA** 520 bp |
| specificity question answered | genome-wide mispriming | **which transcripts/isoforms/paralogs amplify** |

🔑 **The two together give intron-spanning for free: genomic 7,742 ≠ cDNA 520 ⇒ spans introns.**

⭐ **User, 2026-08-06: *"the values will be different if the primers are in different exons."***
That is the whole diagnostic, and the checker should report the **classification**, not just two
numbers the user has to compare by eye:

| genomic vs cDNA product | means | RT-PCR verdict |
|---|---|---|
| **equal** | both primers sit inside **one exon** | ⚠️ cannot distinguish cDNA from contaminating gDNA — same product either way |
| **genomic > cDNA** | primers in **different exons**, ≥1 intron between them | ✅ gDNA gives a larger product, or none if the intron is big — discriminates **by size** |
| primer itself **spans a junction** | full-length hit in the **transcriptome**, none in the genome | ✅✅ strongest — will not anneal to gDNA **at all** |

**All three fall out of phase 1 for free.** ⚠️ An earlier draft of this note claimed the third case
needed the exon file. **That was wrong** — corrected by the user: *"but it would get good hits in
the transcriptome fasta."* Exactly so, and the two-database comparison detects it directly.

✅ **VERIFIED 2026-08-06.** A 20-mer centred on a real exon junction of
`WALLABY_T2T_chr2_003547.1` (10 bp either side of the junction at transcript position 451):

```
vs TRANSCRIPTOME:  length=20  mismatch=0  qstart=1 qend=20   at 442–461   ← full-length, perfect
vs GENOME (chr2):  best hits length=16, and at unrelated loci (474.7 Mb, 521.9 Mb, 100.6 Mb …) —
                   nothing full-length, and nothing at the transcript's own locus (~75.26–75.27 Mb)
```

🔑 **The signature is therefore: full-length perfect hit in the transcriptome + no full-length
genomic hit ⇒ the primer spans an exon junction.** Two BLAST runs against databases that already
exist. No exon file, no coordinate arithmetic.

➡️ **So the exon file is needed ONLY for RT-PCR *design*** — telling Primer3 where junctions are so
it can deliberately target them (`SEQUENCE_OVERLAP_JUNCTION_LIST`). It is not needed for any part of
verification, which makes phase 1 substantially more valuable standalone than first scoped.

⚠️ Judge "no genomic hit" carefully all the same: the test above still returned **16 bp** genomic
hits at unrelated loci. The rule is *no **full-length** genomic hit*, not *no hits at all* — and
never report a junction-spanning primer as "not found in the genome", which reads as failure when it
is the best possible outcome.
`primerChecker` derives this from BLAT-against-genome **plus** `transcript_exons_info.txt`; MOOP can
get it from **two BLAST runs against databases that already exist**, with no exon file at all.

➡️ **So phase 1 does NOT depend on building the exon-coordinate infrastructure.** That moves off
the critical path. The exon file is still needed later, but only for the finer-grained answers:
*which* exon each primer sits in, and junction-spanning at `getExonInfo`'s 0.5 resolution — i.e.
RT-PCR **design**, not pair verification.

Also note the transcriptome is **both cheaper and quieter** (1,033 vs 4,087 hits; the loose filter
leaves 1 hit per primer instead of 15), because the search space is far smaller. And it answers a
question the genome cannot: here, exactly one transcript gets a valid pair — no paralog or isoform
cross-amplification. That is a real result to show the user, not a diagnostic.

⚠️ Scope note: `genome.fa` is **per assembly**, `transcript.nt.fa` is **per gene set**. The scope
selector must offer both levels — see `notes/SHARED_SCOPE_SELECTOR_PLAN.md`.

✅ **The migration is differentially testable, and should be.** The working BLAT implementation
still exists, so run both engines over the same primers against the same assembly and compare
pairs, product sizes and hit counts. Per CLAUDE.md §12, that is real evidence in a way a test that
has never failed is not.

⚠️ **Scope the check to ONE assembly first.** A "check against every organism" mode inherits the
~98-assembly fan-out cost in `notes/QUERY_PERFORMANCE.md`, and the original tool could not even
manage one genome without chunking.

### Bugs in the GFF reader, which matter for MOOP's data specifically

- **`last if $line =~ /FASTA/;` is unanchored.** It is meant to stop at the `##FASTA` directive,
  but any line merely *containing* "FASTA" (an attribute, a Note, a source name) truncates parsing
  silently — everything after is lost with no error.
- **Only `mRNA` and `nontranslating_CDS` are captured as transcripts.** MOOP's GFFs also carry
  `lncRNA`, `transcript`, `tRNA` and `pseudogene` (Amphimedon: 23,543 mRNA but also 6,675 lncRNA,
  453 transcript, 106 tRNA). Their exons still autovivify a `$genes{$ref}{$parent_id}` entry, so
  the output loop emits rows with undefined name/start/strand — uninitialized warnings and
  malformed lines. **Widen the type list before running this over MOOP data.**
- Backticked `rm`/`blat`/`cat` with interpolated filenames — same shell-injection class as the
  other repos; `rm -f $primersFile.*.blatout` is commented out, so chunk outputs accumulate.

## 🧩 The three repos together ARE the whole tool

| Repo | Half |
|---|---|
| `primer3_tab` | **design** from a pasted sequence (+ the dash→junction trick) |
| `RT-PCR_primers` | **design** from a gene ID, junctions computed from annotation |
| `primerChecker` | **verify** a pair against genome *and* cDNA, with exon scoring |

Together that is NCBI Primer-BLAST's shape — design, then specificity-check — which is precisely
what this note set out to build. **Nothing needs inventing; it needs consolidating.** One engine,
three entry points, sharing one exon-structure source (`genes.gff` → flat exon file) and one
sequence source (`transcript.nt.fa`).

## What this means for the MOOP build

- **Rewrite, do not port.** MOOP's version wants PHP, `escapeshellarg()` (§8), real temp-file
  handling, and `transcript.nt.fa` + `genes.gff` in place of BioPerl. Keep the repos as the
  reference implementation and provenance record; do not try to make one codebase serve both.
- **One engine, two front doors** (pasted dashes / transcript ID), per the finding above.
- **Carry the good parts across:** the progressive product-size ladder
  (`75%-len 50%-len 25%-len 100-len`), `PRIMER_NUM_RETURN`, salt correction, and FASTA output of
  primers are all worth keeping.
- **Fix #3 explicitly in the port:** a single-exon transcript must be *refused* with a stated
  reason, never silently given unconstrained primers.

The short-sequence *search* now works correctly (BLASTn-short selectable, E-value 1000,
DUST off, advanced options actually reaching BLAST). What is still manual is the part the
user actually cares about: **given two primers, where will they amplify and how big is the
product?**

---

## What a user does today

Paste both primers as one record with an N spacer, run BLASTn-short, then read coordinates
off the alignments and do the arithmetic by hand:

```
>my-primer-pair
GCTTGAGCTGTTATCTGTGC
NNNNNNNNNNNNNNNNNNN
GCGGTGCTTCTGGGCTGAGT
```

```
Sbjct  201 → 220   (plus)     forward primer
Sbjct  520 → 501   (minus)    reverse primer
                              product = 520 − 201 + 1 = 320 bp
```

That works — verified end to end on 2026-08-03 — but the user has to spot which hits share
a subject, check the strands are opposite, check the primers face each other, and subtract.
Across a gene family with a dozen shared subjects that is tedious and easy to get wrong.

---

## What the tool would do

Two boxes (forward, reverse) rather than a hand-built FASTA. Then, server-side:

1. Run one BLASTn-short per primer, or one joined query — both are proven to work.
2. **Pair the hits**: group by subject, keep pairs where one primer is on `plus` and the
   other on `minus`, and where they face each other (forward start < reverse start).
3. **Compute product size** per pair: largest coordinate − smallest + 1.
4. **Rank by specificity**: one product = a clean pair; several = report them all, biggest
   concern first. This is the answer the user is actually after.
5. Flag the failure modes explicitly, because they look like success on a plain BLAST page:
   same-strand pairs, primers facing away, and a primer with a perfect match on a subject
   its partner never hits.

Optional later: melting temperature, GC content, product sequence retrieval (MOOP already
has the machinery — `feature_coords.tsv` plus the FASTA handlers), and a "check against
every organism" mode for cross-species primers.

The reference implementation to look at is NCBI **Primer-BLAST**, which does exactly this
pairing-and-specificity step on top of blastn.

---

## What already exists to build on

- `-task blastn-short` handling in `tools/blast.php` (E-value 1000, DUST off, word size 7)
- `executeBlastSearch()` in `lib/blast_functions.php` now passes the full advanced option
  set, including `-strand` and `-perc_identity`, so a primer tool can constrain searches
- `parseBlastResults()` returns `query_frame`/`hit_frame` and both coordinate pairs per HSP
- `formatBlastAlignment()` renders wrapped alignments with correct ascending/descending
  coordinates, so a product view can reuse it
- `includes/blast_short_help_modal.php` already documents the manual method; the tool would
  replace most of its third section

---

## Open questions

- **Scope of the search.** One gene set, one organism, or every organism? Cross-organism is
  the expensive case and the same fan-out cost as search (`notes/QUERY_PERFORMANCE.md`).
- **Which database.** Primers are usually designed against genomic sequence, but MOOP's
  BLAST databases include CDS and protein. A primer tool probably wants genome by default,
  which is what `applyBlastProgramDefaults()` already tries to auto-select.
- **Where it lives.** A mode on `tools/blast.php`, or its own `tools/primer_blast.php`
  registered in `config/tools_config.php`? A separate page is easier to make simple, which
  matters given the clean-page rule.

---

## Related

`includes/blast_short_help_modal.php` (the manual method it would automate),
`notes/USER_PAGE_HELP_AUDIT.md`, `notes/QUERY_PERFORMANCE.md` (fan-out cost).
