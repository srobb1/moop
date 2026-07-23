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
