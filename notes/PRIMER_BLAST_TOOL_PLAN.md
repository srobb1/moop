# A dedicated primer BLAST tool

Status: **idea recorded, not built.** Raised by the user 2026-08-03 while fixing the
short-sequence BLAST bugs. Post-launch — this is a new tool, not a launch blocker.

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
