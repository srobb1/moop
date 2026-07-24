# User-facing page audit — is the help good, and is the page clear?

Status: **not started.** Raised again by the user 2026-07-24 at the end of the pipeline
session: *"we still need to go through all the pages to make sure the help is good. and the
pages are clear."*

This is a **launch-readiness** item, not polish. It was the stated next priority before the
database/pipeline work displaced it, and it should resume once the reload is done
(`notes/PIPELINE_RELOAD_PLAN.md`).

---

## The direction, already decided

From the 2026-07-22 decision — do not re-litigate:

- User-needed help goes **on the page**, via the proven `(i)` popover / `help_modal()`
  pattern already shipped on MOOPmart, BLAST, Search, Downloads, Groups, Gene Set and
  Retrieve Sequences.
- Reasoning and background go to `docs/`, not onto the page.
- The separate **help section is to be removed**.
- The governing rule is **non-redundancy**: the same content in two places drifts, and the
  user cannot tell which one is lying.

`docs/` also needs pages a *prospective admin* can read to understand what setup and
maintenance actually involve.

---

## Pages to walk

Each needs two questions answered: **is the help right**, and **is the page itself clear
without help** (a page that needs a paragraph to be usable is a page-design problem, not a
help problem).

| Page | Has `(i)` help today | Notes |
|---|---|---|
| `index.php` | — | First thing a visitor sees. Nothing has been done here. |
| `search.php` | yes | Also has `includes/search_help_modal.php` |
| `moopmart.php` | yes | Most complete example; use it as the reference |
| `blast.php` | yes | |
| `organism.php` | no | |
| `assembly.php` | no | |
| `gene_set.php` | yes | |
| `groups.php` | yes | |
| `multi_organism.php` | no | |
| `parent.php` | no | The gene page — highest-traffic page after search |
| `downloads.php` | yes | |
| `retrieve_sequences.php` | yes | |
| `retrieve_selected_sequences.php` | no | |
| `jbrowse2.php` | no | |
| `about.php` | n/a | Content page — check it is accurate, not that it has help |
| `login.php` / `access_denied.php` | n/a | Check the wording is not intimidating |

**No `(i)` help at all:** `organism.php`, `assembly.php`, `multi_organism.php`,
`parent.php`, `retrieve_selected_sequences.php`, `jbrowse2.php`, `index.php`. `parent.php`
and `index.php` are the two that matter most — the gene page is where users land from
search, and the index is where they land from nowhere.

---

## What to check on each page

1. **Can a biologist who has never seen MOOP tell what this page is for in one sentence?**
   If not, the page needs an overview `(i)`, not more prose in the body.
2. **Is every input labelled in the user's language?** Not the database's. "Gene set" and
   "assembly" are MOOP concepts a visitor does not arrive with.
3. **Does the empty state teach?** A page with nothing selected is the most common first
   view and usually the least designed.
4. **Are the error and zero-result states honest and actionable?** "No results" should say
   what was searched and suggest the next move.
5. **Is anything intimidating that need not be?** The user's stated goal is
   *"easy to use and understand and no intimidation."*
6. **Is any help text duplicated between the page and `docs/`?** If so, delete one — see the
   non-redundancy rule above.

---

## Known page-level issues already recorded elsewhere

- **Search returns different results depending on which level of the hierarchy matched** —
  `notes/SEARCH_FEATURE_LEVEL_INCONSISTENCY.md`. Deliberately deferred; it is a semantics
  decision, and the user's stated preference is that everything should bubble up to the
  gene. This affects what the results table *means*, so it should be settled before writing
  help that describes it.
- **The shared organism/gene-set scope selector is duplicated three times and has drifted** —
  `notes/SHARED_SCOPE_SELECTOR_PLAN.md`. The same control behaving differently on different
  pages is a clarity problem as much as a code one.
- **Per-tool overview `(i)` and a use-case router page** — `notes/USE_CASES_AND_HELP_ROUTER_PLAN.md`.
  The per-tool overview is the small piece to do first.

---

## Related

`notes/USE_CASES_AND_HELP_ROUTER_PLAN.md`, `notes/SHARED_SCOPE_SELECTOR_PLAN.md`,
`notes/SEARCH_FEATURE_LEVEL_INCONSISTENCY.md`, `notes/ADMIN_UI_FOLLOWUPS.md`
(admin side, separate).
