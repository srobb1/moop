# Use-case router page, and per-tool "what is this" overviews

Status: **ideas recorded, not built.** Raised by the user 2026-07-23 while converting the
tool help. Two related but separable pieces.

---

## 1. Per-tool overview (i) — small, do first

The user wants each tool to open with **one overview (i)** that is "a card for each step,
and maybe a short opening why." This is the exact pattern now shipped on MOOPmart's page
header (`help_modal('mm-help')`): a short intro sentence, then one numbered card per step
using the `.step-badge` circle so the help mirrors the page.

**Named target: Annotation Search (`tools/pages/search.php`).** It has a stepped layout
(scope → sources → search) but no single "what is this tool and how do I use it" overview
— its help is field-level (glossary terms, the search-box modal, the results modal). Add
an overview (i) on the page header, same shape as MOOPmart:
- short "why": what Annotation Search is for, and when to reach for it vs MOOPmart.
- a card per step, numbered with `.step-badge` via help_modal()'s `num` key.

Cheap and self-contained; a good next help task.

---

## 2. A use-case router page — bigger, discuss first

The user's words: *"a page that says if you want to do this, go here, if you want to do
this, go there… a searchable page that is easy to use."* Not tool-first (here are our
tools) but **task-first** (here is what you want to do → here is the tool for it).

Shape:
- A list of **use cases**, each: a plain-language task ("Find all genes with a given GO
  term across several organisms", "Download protein FASTA for a list of gene IDs",
  "BLAST a sequence against one assembly") → which tool, with a deep link straight into
  it, ideally pre-scoped.
- **Searchable / filterable**, like the organism filter: type "GO term" or "download" and
  the matching cards narrow. Same client-side filter pattern already used on the group and
  multi-organism pages (`data-filter-text`).
- Easy to skim — cards, not prose.

Why it matters for launch: a first-time biologist does not know MOOPmart from Annotation
Search from BLAST. A task-first router removes that gap, and it is the natural home for the
"which tool?" guidance currently scattered across tool help (MOOPmart-vs-Search appears in
several places today — this could be its ONE home).

⚠️ Non-redundancy watch: if this page describes what each tool does, that description must
have ONE home. Prefer linking into each tool's own overview (i) (piece 1) rather than
restating it here. The router says "for THIS task, go THERE"; the tool's own overview says
"here is how THIS tool works."

---

## 3. "Send your use case to admin" button — needs a decision

The user wants a button: *"send your use case to admin to make a new card or tutorial or
info block."* A visitor who could not find how to do their task submits it, and the admin
turns it into a use-case card / help card / tutorial.

This is a genuinely good feedback loop — the people who hit gaps are the ones who know
where they are. But it needs decisions before building:

- **Transport.** MOOP sends no email today. Options: (a) `mailto:` the admin_email — zero
  infra, but opens the user's mail client and many browsers have no handler; (b) a stored
  queue — an `admin/` page listing submissions, written to a JSON/SQLite under the
  site-data dir — no email, admin checks a page; (c) real SMTP — most work, needs config.
  Recommend (b): it fits MOOP's "state in a file the admin reviews" pattern (cf. login
  attempts, housekeeping status) and needs no new infrastructure.
- **Abuse / spam.** A public unauthenticated POST that reaches an admin is a spam vector.
  Needs the CSRF token (already global) at minimum, and rate limiting; Turnstile exists in
  the codebase (currently off) and could gate it.
- **Loop back.** The payoff is the admin turning a submission into a card. If use cases
  (piece 2) live in a metadata JSON, "promote this submission to a use-case card" is one
  admin action — worth designing the two together so the submission and the card share a
  shape.

Recommend building pieces 1 and 2 first (pure UI, no new infra), and treating piece 3 as a
follow-up once the use-case data model exists for a submission to slot into.

Related: `notes/GROUP_TAXON_CHECKER_PLAN.md` (same "admin reviews a queue" shape for
piece 3), the help toolkit in `lib/help_ui.php`.

---

## 4. Surface annotation-type availability in MOOPmart's "By Annotation" filter — NEXT

User concern 2026-07-23: filtering By Annotation with a type + keyword can silently
under-deliver when the selected organisms have **few or no** annotations of that type —
the user has no signal, and it quietly shrinks their list.

**The data already exists.** MOOPmart's Step 3 "Annotation types to include in TSV" panel
already shows per-source count badges and hides zero-count sources, fed by
`tools/get_annotation_sources_grouped.php` and driven by `refreshAnnotationCounts()` in
`js/modules/moopmart.js` (marks `.mm-ann-zerocount`, recomputes on organism-selection
change). The By Annotation FILTER dropdown (Step 2) is a plain native `<select>` with no
counts — that is the gap.

**This is the real substance behind the earlier "make the two dropdowns match" question.**
A native `<select>` cannot render the badge PILLS (option elements are plain-text only), and
the badge colour adds little to a single-select picker anyway. But the COUNT is what the
user actually wants, and native options CAN carry it: render each option as
`Source name (1,234)`, and disable or grey a `(0)`.

Plan:
- Extend `refreshAnnotationCounts()` to also rewrite the `.mm-ann-src-select` option labels
  from the same `counts` map it already builds. ⚠️ The dropdown is cloned per criterion row
  (`$ann_dropdown` HTML reused when adding rows), so update ALL current selects and re-apply
  after a row is added.
- Recompute on organism-selection change (same trigger already wired).
- Zero-count options: append " (0)" and `disabled`, or a muted style, so the user sees the
  type exists but is empty for this selection — not just an absent option.

Contained JS change, no new endpoint. Good first task next session.
