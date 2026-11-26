# User-facing page audit — is the help good, and is the page clear?

Status: **not started.** Raised again by the user 2026-07-24 at the end of the pipeline
session: *"we still need to go through all the pages to make sure the help is good. and the
pages are clear."*

This is a **launch-readiness** item, not polish. It was the stated next priority before the
database/pipeline work displaced it, and it should resume once the reload is done
(`notes/archive/PIPELINE_RELOAD_PLAN.md`).

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
  `notes/archive/SEARCH_FEATURE_LEVEL_INCONSISTENCY.md`. Deliberately deferred; it is a semantics
  decision, and the user's stated preference is that everything should bubble up to the
  gene. This affects what the results table *means*, so it should be settled before writing
  help that describes it.
- **The shared organism/gene-set scope selector is duplicated three times and has drifted** —
  `notes/archive/SHARED_SCOPE_SELECTOR_PLAN.md`. The same control behaving differently on different
  pages is a clarity problem as much as a code one.
- **Per-tool overview `(i)` and a use-case router page** — `notes/archive/USE_CASES_AND_HELP_ROUTER_PLAN.md`.
  The per-tool overview is the small piece to do first.

---

## Related

`notes/archive/USE_CASES_AND_HELP_ROUTER_PLAN.md`, `notes/archive/SHARED_SCOPE_SELECTOR_PLAN.md`,
`notes/archive/SEARCH_FEATURE_LEVEL_INCONSISTENCY.md`, `notes/ADMIN_UI_FOLLOWUPS.md`
(admin side, separate).

---

## Findings from the organism-page pass (2026-07-31, driven in headless Chrome)

`tools/organism.php?organism=Anoura_caudifer` — 200, 0.37 s, no console errors, no failed
requests. All six visible help popups open and read accurately: How to search · search
results · taxonomy lineage counts · member of groups · `assembly` (glossary) · Assemblies.

**Fixed in this pass**
- Filter badge shoved the button icon off centre on every page — `.btn .badge` (0,2,0) beat
  `.badge-filter` (0,1,0), so the badge rejoined the flex row instead of floating over the
  corner. `25c6c8c`.
- No note under the search box on a single-organism page, so the scope filter was an
  unlabelled icon and nothing said assemblies or gene sets existed. `72aa12d`.
- Clear-source-filter X removed, matching the scope filter. `9b2d66f`.

**⏭️ OPEN — candidates for the one-pass audit script**
- **Glossary terms inside modals appear un-glossed.** `js/modules/glossary.js` inits on
  DOMContentLoaded over the whole document, so terms in markup present at load *should* be
  wired — but Bootstrap popovers raised from inside a `.modal` commonly render behind it, or
  under its backdrop, unless given `container` + a z-index above the modal. NOT yet
  diagnosed; check whether the popover is missing, or present and invisible.
- **No page-level `(i)`** on the organism page. Every popup explains a *field*; none says
  what the page is for. Same gap on `assembly`, `gene_set`, `groups`, `multi_organism`,
  `parent`, `index`, `retrieve_selected_sequences`, `jbrowse2`.
- **No sense of scale.** Taxonomy, groups and assembly chips, but never "45,518 genes" — a
  visitor cannot tell whether this organism holds anything for them without searching blind.
- **Scope badge number may not be intelligible.** Unticking 98 of 147 boxes produced a badge
  reading `16`; confirm what that counts and whether a user could work it out.
- Site banner is a marine-invertebrate plate on a bat page (site-wide, may be deliberate).

### The audit script the user asked for (2026-07-31)
Rather than fixing these page by page, walk every user-facing page once and report:
help triggers that open nothing · glossary terms that are not wired · popovers that render
behind a modal · pages with no page-level `(i)` · icon-only controls with no title/aria ·
console errors and non-200 requests. Driving it in headless Chrome is what caught the badge
bug today; reading the markup would not have.

### About-box text: three data-level findings (2026-07-31, not fixed)

Style was fixed (teal bar, 70ch measure, ragged right — `911b8e6` and follow-up). These are
in the DATA and were left alone:

1. **We truncate the Wikipedia text ourselves, and need not.** `lib/wikipedia_functions.php`
   already passes `exintro=true`, which asks Wikipedia for the intro section only — exactly
   "the whole first section". It then cuts that to 500 characters and appends "...", so
   Nematostella ends mid-word at "...where its slender colum...". Removing the cut yields
   the full lead. The same four lines are duplicated at :77, :168, :256 and :365.
2. **The cut is byte-based, not character-based.** `strlen`/`substr` on UTF-8 can sever a
   multi-byte character and emit a broken byte; `mb_strlen`/`mb_substr` are the correct
   calls if a limit is kept at all.
3. **Source attribution is baked into the stored text.** The description string ends
   `<br><br><small class="text-muted">…`, so the markup and the attribution travel with the
   data rather than being rendered from `text_src`. That is why hard breaks appear in prose
   the browser should be free to reflow.


---

## Merged: Use-case router page, and per-tool "what is this" overviews

*(Was `notes/USE_CASES_AND_HELP_ROUTER_PLAN.md`, merged here 2026-11-25; the original is in `notes/archive/`.)*

⭐ **START HERE 2026-08-06 — use cases are the next task, and the groundwork is done.**

What exists now, so none of it gets rebuilt:

- **The page finder is BUILT and live** — `help.php?topic=finding-the-right-page`, registered
  as the first help topic. It answers "which PAGE do I want": 10 cards, 6 with direct links,
  4 with written directions, task-first filter. §2 below described it; it is no longer a plan.
- **Every page declares its own purpose** via `page_purpose()` (`lib/help_ui.php`), rendered
  visibly or `hidden`, and read off the RENDERED page by
  `scripts/extract_page_purposes.php --write` into `docs/page_purposes.json`. The finder
  renders from that, so the sentences cannot drift.
  ⚠️ Write purposes to read IN A LIST — no "this assembly"; the reader is not standing on it.
- **Seed material is gathered** — see §2b: the four task/tool pairs, a worked example in the
  right shape, three operational tips (one a performance fact stated nowhere else), and the
  gene-set granularity point.

So the remaining question is NOT "what is a use case" but **how use cases differ from the
finder**. The finder answers *which page*; a use case answers *how do I do X*, and may span
several pages. Decide whether they are:
  (a) a second card list on the finder page (one search box, two card types), or
  (b) their own topic that links into the finder.
The user's earlier framing — "a searchable page that is easy to use" — and the fact that the
finder already has a working task-first filter both point at (a).

⚠️ Do NOT re-survey the help section. It was reviewed and cut on 2026-08-05: eight general
topics down to two (the finder, and `search-and-filter` — which the user says repeats the
page and is the next deletion candidate). The deleted pages' content was harvested first.

---


Status: **ideas recorded, not built.** Raised by the user 2026-07-23 while converting the
tool help. Two related but separable pieces.

---

### 1. Per-tool overview (i) — small, do first

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

### 2. A use-case router page — bigger, discuss first

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

### 2b. ⭐ SEED CONTENT — harvested from help/multi-organism-analysis.php (2026-08-05)

That page was deleted in the general-help cleanup, but its **"Which Tool to Use"** table is
the router in embryo — the only place on the site that answered "which tool for this job?".
Cross-tool comparison cannot live in any single tool's `(i)`, which is precisely the gap the
router fills. Preserved here verbatim so it is not lost with the page:

| Task | Tool |
|---|---|
| Find genes matching a keyword or annotation term across many organisms | Annotation Search |
| Build a gene list by ID, name, description, or GO term across multiple assemblies, then export TSV/FASTA sequences | MOOPmart |
| Compare a sequence against multiple genome assemblies | BLAST Search |
| Retrieve sequences by ID from a single assembly | Retrieve Sequences |

Four cards' worth. The router needs more, and these are phrased tool-first ("here is the
tool") where the router should be task-first ("here is what you want to do") — but the four
task descriptions themselves are already good use-case wording and can be lifted directly.

#### More seed material from the same page (harvested 2026-08-05 before deletion)

**A worked example, ready to become a use-case card.** This is the shape the router wants —
a concrete task with a concrete payoff, not a tool description:

> Select 10 bat assemblies, search by feature name for TP53, and download a TSV with all
> matching features and their annotations across every assembly — in one click. (MOOPmart)

**Three operational tips. The third is a real finding stated nowhere else on the site:**

- Start with a small selection of organisms to test a query before expanding to all of them.
- Annotation Search returns up to 2,500 results per organism — use specific terms or limit
  annotation types to stay under it. (The cap IS surfaced in the UI now, naming which
  organisms capped; this tip is the advice, not the warning.)
- ⭐ **For large cross-organism exports, MOOPmart is more efficient than exporting search
  results: it runs a single optimised query rather than one per organism.** That is a
  performance fact about how the two tools differ, and it is not documented anywhere else —
  it belongs wherever "MOOPmart vs Annotation Search" is finally answered.

**One granularity point worth keeping somewhere:** Annotation Search's scope step selects
organism → assembly → gene set, so an organism with two gene sets can have one included and
the other excluded. Coarser organism-level pickers cannot express that.

Gaps these four do NOT cover, worth adding when the router is built: downloading a whole
genome or GFF (Downloads), viewing a gene in its genomic context (Genome Browser), finding
what annotation sources exist for an organism, and getting every annotation for one gene
(the gene page).

---

### 3. "Send your use case to admin" button — needs a decision

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

Related: `notes/archive/GROUP_TAXON_CHECKER_PLAN.md` (same "admin reviews a queue" shape for
piece 3), the help toolkit in `lib/help_ui.php`.

---

### 4. Surface annotation-type availability in MOOPmart's "By Annotation" filter — NEXT

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


---

## Merged: Glossary — candidate terms, and the boundary with existing help

*(Was `notes/GLOSSARY_TERM_CANDIDATES.md`, merged here 2026-11-25; the original is in `notes/archive/`.)*

*(Status as of 2026-08-04. The glossary has grown a lot since; check `metadata/glossary.json` before adding a candidate.)*

Status: **candidates gathered 2026-08-04 from rendered pages; nothing added yet.**
Raised by the user: *"this is important for users."*

Related: `USER_PAGE_HELP_AUDIT.md`, `PAGE_BY_PAGE_AUDIT_PLAN.md` §P.

---

### What already exists

- **`metadata/glossary.json` — 10 terms**: annotation, annotation type, feature, gene set,
  assembly, GO term, mRNA, CDS, protein, gene.
- **`admin/manage_glossary.php`** — a working admin page with add / edit / delete, CSRF and
  POST-redirect-GET, linked from the dashboard. **Terms go in through the UI; do not hand-edit
  the JSON.**
- **`gloss()`** (`lib/glossary.php:73`) wraps a term; `js/modules/glossary.js` inits every
  `.gloss[data-bs-toggle="popover"]` site-wide, once, so a page never has to wire anything.
- **24 `gloss()` call sites** across 6 files: `parent`, `moopmart`, `organism`, `search`, plus
  two help modals.

### ⛔ The boundary: annotation TYPES are already documented — do not duplicate them

`metadata/annotation_config.json` carries a `description` per annotation type
(`annotation_types`, 10 entries; mirrored in `analysis_descriptions`). Those are what the
`(i)` beside each annotation section on the gene page opens. Already covered, and adding
them to the glossary would break the non-redundancy rule the help work is governed by:

> Orthologs · Homologs · Domains · Gene Ontology · Gene Families · AI Annotations ·
> Mapping · Aliases · Publications

**The gap is one level down.** Types are described; the *source* names that appear inside
those tables are not, anywhere:

> **PANTHER · RBBH · EggNOG · InterPro · Pfam · Swiss-Prot · ProtNLM**

A reader is told what a Domain is, then shown "PANTHER" with no way to learn what produced
the call. That is the highest-value gap in the whole glossary question.

---

### Candidates, measured

Method: rendered `document.body.innerText` from 12 user-facing pages in headless Chrome,
word-boundary matched. Counts are **default page state only** — see the caveat below.

#### Tier 1 — source and tool names (nothing defines these)

| term | hits | pages |
|---|---|---|
| PANTHER | 5 | search, gene, moopmart, downloads, jbrowse2 |
| RBBH | 6 | gene_set, gene |
| ProtNLM | 3 | gene |
| EggNOG | 3 | gene |
| InterPro, Pfam, Swiss-Prot | 1 each | gene |

PANTHER has the widest spread of any jargon term on the site — **five pages** — and is
completely opaque. ProtNLM is the one with the biggest gap between what it looks like and
what it is: a machine-generated protein name, which nothing on the page says. Its *type*
("AI Annotations") is described; its *name* is not.

#### Tier 2 — structure, where mRNA/CDS/protein are already defined but their neighbours are not

**transcript** (7 hits, 4 pages) · **isoform** · **exon** · **intron** · **UTR** ·
**strand** · **flanking** · **upstream / downstream** · **genomic**

`mRNA` is defined but `transcript` is not, and the two are used interchangeably in places —
that pair is worth disambiguating before adding anything new. **UTR** and **flanking** carry
specific meaning in Retrieve Sequences: they determine what you actually get in the file.

#### Tier 3 — identifiers and navigation

**accession** (used constantly as a concept, defined nowhere) · **taxon** · **lineage** ·
**taxon ID**

#### Tier 4 — BLAST results vocabulary

**e-value** · **bit score** · **HSP** · **query / subject** · **percent identity** ·
**coverage** · **alignment**

⚠️ **Under-counted by the scan** — these live in BLAST *results* and expanded annotation
tables, and the scan only visited pages in their default state. "Not found" in the raw
output means "not in the default view", never "absent from the site". Re-scan with a real
BLAST result and expanded tables before drawing conclusions about this tier.

#### One to decide deliberately

**genome** — 16 hits across **8 pages**, the most widespread term measured. Arguably plain
English, but `assembly` is already defined and MOOP uses the two near-interchangeably in
places. That is exactly the pair a glossary exists to separate.

---

### Automating the `gloss()` wrapping — review list, NOT a codemod

Confirmed with the user 2026-08-04: this can be automated **with review**, not without.
Blind wrapping of every occurrence is wrong in four ways that need a human:

1. **First occurrence per page** is the convention. Wrapping all ten "protein"s on a page
   gives ten dotted underlines and reads as a fault.
2. **Occurrences inside links and buttons** — a popover there fights the click.
3. **Proper noun vs concept** — "Gene Ontology" the database against "gene ontology" the
   idea; only one wants a definition.
4. **Terms inside a longer name** already meaningful in context ("Gene Ontology annotations"
   does not want *domain* glossed separately inside it).

So the tool emits a worklist — `file:line`, surrounding context, proposed `gloss()` wrap —
and a human ticks. Mechanical to find, cheap to review, and it cannot silently reword a
page. Scratch harness for the finding pass:
`scratchpad/audit/glossterms.js` (session-local, disposable).

### Who decides what, and why wrapping has no admin screen

Three surfaces, and only one of them needs a developer:

| | where | who |
|---|---|---|
| **Which terms exist, and their wording** | Admin → Manage Glossary | admin, anytime, no code |
| **Terms rendered from DATA** (annotation Source column) | follows the glossary automatically | admin, anytime, no code |
| **Terms in static page text** | a `gloss()` call in the template | developer |

The middle row is the one worth knowing about. Because
`lib/parent_functions.php` calls `gloss_terms_in()` on the source name rather than
hard-coding anything, **adding a term in Manage Glossary immediately starts glossing it on
every gene page, and deleting one stops it.** No deploy. `gloss()` falls back to the escaped
plain word for unknown terms, so neither direction can break a page.

⛔ **There is deliberately no admin UI for wrapping words in page text.** Doing that means
writing to `.php` files inside the document root, which is precisely what the no-exec guard
in `docs/nginx/moop-security.conf` exists to prevent — one file-write bug there is a
persistent webshell (CLAUDE.md §11). Definitions are data and belong in the admin UI;
wrapping is source and does not.

### ⭐ The rule: gloss labels and controls, not prose

From reviewing the finder's first output together, 2026-08-04. Most candidates were **no**,
and for one consistent reason:

- **Yes** — form labels, column headers, field names, mode selectors, instructional text
  where the term is load-bearing. `moopmart.php:382` "Annotation type" is a field label whose
  choice depends on understanding the word.
- **No** — descriptive prose. `index.php:14` "Browse genes, genomes, and annotations" is the
  first line a visitor reads; dotted underlines there make the page look broken. Section
  headings in caps are the same.

A word in a label is one the reader is being asked to *act on*. A word in a sentence is one
they are just *reading*, and underlining it interrupts.

⚠️ **This makes most of the 158 not worth doing.** `gene`, `annotation`, `assembly` and
`feature` are 90 of them, and they are words the audience arrives knowing — they appear
mostly in prose. The genuine gaps were the source acronyms, and those are now handled
automatically wherever they appear. Prefer wrapping a new label as the page is written over
running a sweep.

### Suggested order

1. **Tier 1 sources** — biggest gap, smallest list, and they are proper nouns so the
   definitions are factual rather than editorial.
2. **`transcript` vs `mRNA`** — resolve the overlap with an existing term before growing.
3. Tier 3 identifiers, then Tier 2 structure.
4. Re-scan with BLAST results rendered, then Tier 4.
5. Build the review-list tool once there are enough terms for wrapping to be worth
   automating; below ~20 terms, wrapping by hand as pages are touched is cheaper.
