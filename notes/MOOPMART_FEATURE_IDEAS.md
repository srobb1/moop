# MOOPmart feature ideas

Ideas raised while polishing MOOPmart. Not built.

---

## "Select all features in the selected organisms" (user, 2026-07-23)

In the **By Feature IDs** section of Step 2, add a control — a button or checkbox —
that builds the list from **every** feature in the selected organisms, without the user
having to paste any IDs. "Select all features in selected organisms."

**Show a gene count next to it**, so the user knows how many features they are asking
for before they run it — e.g. "Select all features (12,431)".

### Why the count matters here specifically

This is the same huge-query risk the organism Select-All modal already guards
(none-by-default + a "this can take a while" warning). "All features × all organisms"
with no filter is the largest query MOOPmart can produce. So this control must not make
that *too* frictionless:

- The count is not just informational — it is the brake. A user who sees "(2,300,000)"
  will think twice; one who sees "(430)" proceeds happily.
- Consider mirroring the Select-All-organisms pattern: if the count is large, confirm
  ("this will export N features across M organisms and can take a while").
- The count depends on the current organism selection, so it recomputes on selection
  change — same trigger as the annotation availability counts already wired in
  `js/modules/moopmart.js` (`refreshAnnotationCounts`), and could reuse a similar
  lightweight endpoint that returns a feature total for the selected organisms.

### Interaction with the AND filter model

Step 2 sections combine with AND. "All features" is really "no By-Feature-IDs
constraint" — so if the user also fills another section (By Annotation, By Location),
"all features" should not override it; the other filters still apply. Cleanest framing:
the checkbox means "do not filter by ID" (the default today), and the count shows how
many features the *rest* of the current criteria would return — turning the count into a
live preview-size readout for the whole Step 2, not just this section. Decide whether it
counts "all features" or "features matching the other filled sections" before building;
the second is more useful and more honest about query size.

Related: the organism Select-All warning (`tools/pages/moopmart.php` #mm-select-all-modal),
`notes/SEARCH_COLD_CACHE_PLAN.md` (query cost), the annotation-count wiring in
`js/modules/moopmart.js`.

---

## Make MOOPmart's sequence types agnostic too (user, 2026-07-23 — "eventually, not now")

The feature-expansion side of MOOPmart is already type-agnostic as of 2026-07-23: its FASTA
export now calls `expandFeaturesToAllSequenceTypes()` (a pure parent/child graph walk) instead
of `buildTypedIdsForGenes()`, which hardcoded gene -> mRNA -> CDS -> protein as three
sequential queries. Verified equivalent on 25 genes: 87 features either way, zero difference.

**What is still type-coupled is the OUTPUT SELECTOR** — `$fasta_mode` and the
`sequence_types` config:

- `api/moopmart_export.php` splits on `$genomic_modes` (gene / upstream / downstream / exons,
  handled by `moopmartStreamGenomicFasta`) versus the pre-built FASTA modes
  (protein / transcript / cds), and then writes exactly one of them:
  `$extract_result['content'][$fasta_mode]`.
- `_fasta_key_for_type()` in `lib/extract_search_helpers.php` is the remaining hardcoded map
  from DB `feature_type` to a `sequence_types` config key: mRNA|transcript -> transcript,
  CDS|cds -> cds, protein|polypeptide -> protein. It returns null for anything else, which is
  what makes "gene" and "exon" harmless to include in an expansion.

So a new level in the hierarchy would be walked correctly today, but would have no FASTA file
and no picker entry. Making that agnostic means driving the mode list from `sequence_types`
(admin config) plus what the gene-set directory actually contains, rather than from a literal
list in code — i.e. the picker becomes "which of the sequence files this gene set has do you
want", and `_fasta_key_for_type()` becomes a lookup into the same config instead of a static
array.

Worth pairing with `scripts/check_sequence_id_match.sh`, which already reports, per gene set
and per type, whether a FASTA's keys match the database — the same inventory the picker would
need.
