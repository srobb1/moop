# Extract ONE shared organism/gene-set scope selector

Status: **recommended, not built.** Raised by the user 2026-07-23: "should we break out
the code and use it in both places?" Yes — and the evidence is that a fix just had to be
made twice and one copy was missed.

---

## The duplication, concretely

The same organism → assembly → gene-set selector is implemented **twice**, from the same
`$scope_tree`, with different class prefixes and separate JS:

| | MOOPmart | Annotation Search |
|---|---|---|
| Markup | `tools/pages/moopmart.php` `#mm-scope-list` | `tools/pages/search.php` `#scope-org-list` |
| Row / checkbox | `.mm-scope-row` / `.mm-gs-cb` | `.scope-gs-full-row` / `.scope-gs-cb` |
| Simple/detail | `.mm-scope-detail-hidden` / `.mm-scope-row-detail` | `.scope-detail-hidden` / `.scope-row-detail` |
| JS | `js/modules/moopmart.js` | `js/search-display.js` |
| Select-all warning | `#mm-select-all-modal` | `#select-all-orgs-...` |

The PHP loops are line-for-line siblings. There may be a THIRD variant in
`js/scope-filter.js` (a nested `scope-asm-group` tree) — reconcile or retire it as part
of this.

## Why it must be unified — the proof is already here

They have **drifted**, which is what copies do:

- Search has a **filter force-reveal** (`.scope-detail-forced` + `mark.scope-hl`
  highlight) that shows and highlights a row's hidden detail when the filter matches an
  assembly/gene-set name. MOOPmart has no such thing.
- MOOPmart now has the **one-row-per-organism** simple view (commit 8436956); search
  does not, so search still shows Nematostella twice and still selects only one gene set
  on click — the exact bug just fixed on the other page.

So every scope fix or feature is two edits, and the second is easy to forget (it was).
These are the two most important data-access pages (search + export); inconsistency here
is felt directly.

**Update 2026-07-23:** the drift went BOTH ways and both were patched only on MOOPmart,
which is now the de-facto superset:
- MOOPmart's filter used to search only the simple fields in simple view, so filtering by
  assembly or gene set returned nothing despite the placeholder promising it — search.php
  already searched the full text and force-revealed the matched detail. Brought that to
  MOOPmart (commit after 8436956): full-text match + `.mm-scope-detail-forced`.
- MOOPmart got the one-row-per-organism collapse; search.php still lacks it.
So MOOPmart now has both behaviours and search.php has neither of the two fixes — the
extraction should take MOOPmart's versions as the reference and retire search.php's copy.

## Expanded scope (user, 2026-07-23): one selector for FOUR pages, single OR multi

The user wants the same component everywhere a user picks organisms/gene sets, with a
`mode: 'single' | 'multi'` argument:

| Page | Leaf | Mode | Notes |
|---|---|---|---|
| MOOPmart | gene set | **multi** | reference for collapse + filter |
| Annotation Search | gene set | **multi** | reference for force-reveal + highlight |
| Retrieve Sequences | gene set | **single** | `selected_source` radio; same org→asm→gs tree — clean fit |
| BLAST | **database** | **single** | different leaf (nucl/prot DB, and it depends on the chosen program) — the odd one |

`single` mode is not just "cap at one" — it removes behaviours: no All/None (nothing to
select-all to), no organism-row-selects-all-gene-sets collapse (you must land on one leaf),
and picking one clears the previous (radio semantics). So the component takes `mode` and
branches; the multi path is what MOOPmart/search need, the single path is radio-like.

**BLAST is the hard one** and may not fit v1: its leaf is a BLAST database, not a gene set,
and which databases exist depends on the selected BLAST *program* (blastn/blastp/…), so the
list changes reactively from another control. Treat BLAST as a later evaluation, not part of
the first cut.

### Phasing — so the site is never half-converted

1. **Phase 1 — multi.** Build the component with the `mode` arg, implement the multi path,
   convert MOOPmart then Annotation Search, verify each, delete their copies. This is the
   clear duplication and the biggest win. Commit per page.
2. **Phase 2 — single.** Implement the single path, convert Retrieve Sequences (same tree,
   just single-select). Commit.
3. **Phase 3 — evaluate BLAST.** Decide whether the database-leaf + program-dependency fit
   the component or stay bespoke. Do not force it.

Design the API for `single|multi` from the start (Phase 1) so Phases 2–3 don't re-cut it.

## What the shared component looks like

**PHP:** one partial, e.g. `includes/scope_selector.php`, that renders the list from
`$scope_tree` + `$organism_info` + `$assembly_names` + `$organism_groups`. Parameterise
the id prefix (or fix one prefix and namespace both pages to it). It already carries the
representative-row / "N gene sets" logic added on the MOOPmart side — that becomes the
one implementation.

**JS:** one module, e.g. `js/modules/scope-selector.js`, exposing an init that takes the
list element (or prefix) and an onChange callback. Fold in BOTH pages' behaviour:
- simple-view one-row-per-organism selection (from MOOPmart),
- filter with detail force-reveal + highlight (from search),
- the select-all confirmation warning (both have one; unify the copy),
- the "Details" toggle, the filter, the selected-count summary.

**CSS:** one block (move to a real stylesheet, not the per-page `<style>`).

Callers (moopmart.php, search.php) then include the partial and call the init with their
own onChange — MOOPmart updates its Step 2-4 state, search updates its selected panel.

## Sequencing / risk

This is a real refactor across two of the busiest pages, reconciling features that have
diverged, so it wants its own focused pass with browser verification on **both** pages
(simple + detail, filter, select-all warning, the org-level click, the count) — not a
squeeze at the end of a session. Recommend:

1. Build the PHP partial + JS module, superset of both behaviours.
2. Switch MOOPmart to it; verify everything still works (it is the reference for the
   collapse behaviour).
3. Switch search to it; verify the force-reveal/highlight and its selected panel.
4. Delete the two old copies and any dead `js/scope-filter.js` tree.

Until then, search.php still has the duplicate-row bug. If the extraction is deferred,
apply the MOOPmart collapse fix to search.php as an interim — but that is a THIRD copy of
the fix, which is the argument for doing the extraction instead.

## Also pending on these pages (fold into the extraction)

- **All / None → one toggle button** (user asked, 2026-07-23), like the group page's
  toggle whose label states its next action. Both selectors have the two-button pair;
  the shared component should have the single toggle, preserving the select-all warning.

Related: `notes/MOOPMART_FEATURE_IDEAS.md`, the group-page toggle (commit 1c65a6c),
`lib/help_ui.php`.
