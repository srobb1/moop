# Glossary — candidate terms, and the boundary with existing help

Status: **candidates gathered 2026-08-04 from rendered pages; nothing added yet.**
Raised by the user: *"this is important for users."*

Related: `USER_PAGE_HELP_AUDIT.md`, `PAGE_BY_PAGE_AUDIT_PLAN.md` §P.

---

## What already exists

- **`metadata/glossary.json` — 10 terms**: annotation, annotation type, feature, gene set,
  assembly, GO term, mRNA, CDS, protein, gene.
- **`admin/manage_glossary.php`** — a working admin page with add / edit / delete, CSRF and
  POST-redirect-GET, linked from the dashboard. **Terms go in through the UI; do not hand-edit
  the JSON.**
- **`gloss()`** (`lib/glossary.php:73`) wraps a term; `js/modules/glossary.js` inits every
  `.gloss[data-bs-toggle="popover"]` site-wide, once, so a page never has to wire anything.
- **24 `gloss()` call sites** across 6 files: `parent`, `moopmart`, `organism`, `search`, plus
  two help modals.

## ⛔ The boundary: annotation TYPES are already documented — do not duplicate them

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

## Candidates, measured

Method: rendered `document.body.innerText` from 12 user-facing pages in headless Chrome,
word-boundary matched. Counts are **default page state only** — see the caveat below.

### Tier 1 — source and tool names (nothing defines these)

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

### Tier 2 — structure, where mRNA/CDS/protein are already defined but their neighbours are not

**transcript** (7 hits, 4 pages) · **isoform** · **exon** · **intron** · **UTR** ·
**strand** · **flanking** · **upstream / downstream** · **genomic**

`mRNA` is defined but `transcript` is not, and the two are used interchangeably in places —
that pair is worth disambiguating before adding anything new. **UTR** and **flanking** carry
specific meaning in Retrieve Sequences: they determine what you actually get in the file.

### Tier 3 — identifiers and navigation

**accession** (used constantly as a concept, defined nowhere) · **taxon** · **lineage** ·
**taxon ID**

### Tier 4 — BLAST results vocabulary

**e-value** · **bit score** · **HSP** · **query / subject** · **percent identity** ·
**coverage** · **alignment**

⚠️ **Under-counted by the scan** — these live in BLAST *results* and expanded annotation
tables, and the scan only visited pages in their default state. "Not found" in the raw
output means "not in the default view", never "absent from the site". Re-scan with a real
BLAST result and expanded tables before drawing conclusions about this tier.

### One to decide deliberately

**genome** — 16 hits across **8 pages**, the most widespread term measured. Arguably plain
English, but `assembly` is already defined and MOOP uses the two near-interchangeably in
places. That is exactly the pair a glossary exists to separate.

---

## Automating the `gloss()` wrapping — review list, NOT a codemod

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

## Who decides what, and why wrapping has no admin screen

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

## ⭐ The rule: gloss labels and controls, not prose

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

## Suggested order

1. **Tier 1 sources** — biggest gap, smallest list, and they are proper nouns so the
   definitions are factual rather than editorial.
2. **`transcript` vs `mRNA`** — resolve the overlap with an existing term before growing.
3. Tier 3 identifiers, then Tier 2 structure.
4. Re-scan with BLAST results rendered, then Tier 4.
5. Build the review-list tool once there are enough terms for wrapping to be worth
   automating; below ~20 terms, wrapping by hand as pages are touched is cheaper.
