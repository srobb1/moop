# Naming annotations on the site — plan (2026-10-01)

Site-side companion to `notes/NAMING_ANNOTATIONS_FOR_THE_SITE_2026-10-01.md` (on branch
`naming-v2`; not on `main` yet). That note says what the pipeline loads. This one says what the
site has to change so the four new annotation types appear **only** where intended.

⚠️ **Read "FINAL DESIGN" at the bottom first.** The middle sections describe an earlier
design (a role per annotation type) that was superseded the same day.

Status: **plan only, nothing built.** No live database carries the new types yet (checked
Congeria on 2026-10-01: Orthologs, Homologs, Domains, Gene Ontology, Gene Families,
AI Annotations, RBBH Homolog — nothing else).

## The requirement

| Type | Searchable | Tables on gene page | Overview card |
|---|---|---|---|
| `Gene Statement` | no | no | **yes** — the point of the work |
| `Gene Name Source` | no | no | to decide (overlaps the Identity statement) |
| `Closest Gene` | no | no | to decide (one line?) |
| `Paralogs` | to decide | to decide | to decide |

## The trap: doing nothing shows them everywhere

A new `annotation_type` is not ignored by the site. `syncAnnotationTypes()`
(`lib/functions_json.php:224`) adds any type it finds in a database to
`metadata/annotation_config.json` with `'enabled' => true`. From there it is drawn as a table
on the gene page, offered in the source pickers, and searched. The pipeline note's advice to
"leave `Gene Statement` out of `annotation_config.json`" cannot be followed as written — the
housekeeping refresh puts it back.

So the site needs an explicit rule, and it has to exist **before** the first database with
these types is copied to the web host.

## One rule, one place — REVISED after discussion 2026-10-01

Two settings per annotation TYPE, stored in `metadata/annotation_config.json` and edited on
**Manage Annotations** (user's request: the admin page is where the rule is added to or
taken from):

- `shown_as`: `table` (today's behaviour) | `overview` (overview card only) | `hidden`
- `searchable`: `true` | `false`

Every consumer reads them through ONE helper pair in `lib/functions_json.php`
(`moop_annotation_type_role($type)`, `moop_annotation_type_searchable($type)`). No consumer
keeps its own list.

**No new database column** (the user asked). Reasons:
- The web opens every `organism.sqlite` read-only, so the admin page could not change it; a
  change of mind would mean reloading or rewriting 85 databases from the other machine.
- 85 databases would each carry their own copy of the answer and could disagree.
- It is a display/search policy of the SITE, not a property of the data — and the schema is
  kept lean on purpose (CLAUDE.md §9).

**Search is enforced when the query runs, not when the index is built.** Every row is already
tagged with its type in the index (`atype…z`), so the query can include or exclude a type
with no rebuild. That is what makes a switch on the admin page take effect immediately, in
both directions, on every database, whichever machine built its index. Leaving unsearchable
types out of the index is then only a size optimisation — measure first (step 4), and if it is
worth it, have `scripts/rebuild_fts_indexes.sh` read the same config rather than a second list.

**Safe default for a type nobody has decided about.** `syncAnnotationTypes()` currently adds
a new type as enabled everywhere. Change it to add new types as `hidden` + not searchable,
flagged `new`, with a notice on the admin dashboard ("1 new annotation type needs a decision").
The four naming types get sensible built-in defaults so a first load is correct even before
anyone opens the page. ⚠️ Trade-off to confirm with the user: a genuinely new searchable type
(say a new domain database under a new type name) would stay invisible until an admin switches
it on. The notice is what keeps that from being silent.

A clickable sketch of both the admin switches and the overview card was published for the
user to react to before anything is built (Claude artifact "MOOP Naming Statements Mockup").

## Every place a type surfaces (verified by reading the code, 2026-10-01)

| # | Where | What it does today | Change |
|---|---|---|---|
| 1 | `tools/parent.php:96` builds `$analysis_order` | every enabled type becomes a table section, for the gene and for each transcript card | keep `table` roles only |
| 2 | `tools/pages/parent.php:280` gene row badge | sums every type on the gene | count `table` roles only |
| 3 | `lib/parent_functions.php:629` child row badge | sums every type on the transcript | same (Name Source and Closest Gene sit on transcripts too) |
| 4 | `tools/parent.php:472` "isoforms share annotations" sentence | signature over every type | `table` roles only, or it will always say "same" once every isoform carries the same Closest Gene |
| 5 | `build_fts_index.sql` | indexes every `feature_annotation` row | **do not index** non-`table` types (see Search) |
| 6 | `searchFeaturesAndAnnotations()` (`lib/database_queries.php:920`) | quota arm per type + a top-up arm over all types + a general path | runtime guard as well (see Search) |
| 7 | `getAnnotationSourcesByType()` (`:1375`), `tools/get_annotation_sources_grouped.php` | source picker for Annotation Search | drop non-`table` types |
| 8 | `annotation_sources_cache.json` — written by `make_annotation_sources_cache.pl` **and** by housekeeping | feeds the pickers, `tools/gene_set.php:80`, `tools/moopmart.php:98` | filter where it is READ (one PHP reader helper), so a cache written by either producer is safe |
| 9 | MOOPmart / Data Exporter (`tools/moopmart.php`) | lists every type's sources as filters and attributes | to decide — exporting statements in bulk may be wanted |
| 10 | `api/download_annotations.php` (the gene page's CSV) | every type | to decide — recommended: include statements, since the download is "everything about this gene" |
| 11 | `admin/manage_annotations.php` | reorder / enable / describe each type | show the three types with their role, not as reorderable table types |
| 12 | Gene page help modal (`tools/pages/parent.php:534+`) | explains badges and sections from `$analysis_order` | add a short entry for the naming statements |
| 13 | Overview "Copy" summary (`tools/pages/parent.php:76`) | title, badges, organism, location | append the statements, in order, as plain lines |

Not affected: name/ID search (`feature_search` holds feature name and description only — the
gene NAME stays searchable, which is wanted), BLAST, JBrowse, health checks.

## Search

Superseded in part by the revised section above: the runtime guard (2) is now the PRIMARY
mechanism and the index exclusion (1) an optional optimisation. Kept for the reasoning.

Two layers, because either alone has a hole.

1. **Leave them out of the index** (`build_fts_index.sql`: add
   `WHERE ans.annotation_type NOT IN (...)`). This is the real fix. It also keeps the index
   small: Congeria alone gains ~157,000 Gene Statement rows, ~87,000 Gene Name Source rows and
   ~24,000 Closest Gene rows against 43,768 genes, and every megabyte of index competes for
   page cache (CLAUDE.md §9 — cold reads are what make search slow).
   ⚠️ This file is pipeline-side and is being edited on `naming-v2`. The exclusion list there
   and the role registry here must name the same types; a smoke test should assert it.
2. **Runtime guard** in `searchFeaturesAndAnnotations()`: drop non-`table` types from the
   per-type arms (`moop_curated_annotation_types()`), and add
   `NOT {annotation_type_code} : atype…z` to the top-up arm and the general path. Needed
   because a database indexed before step 1 landed would otherwise leak statements into
   results with no error.

## Overview card — first proposal, for discussion

The card today: feature id bar, an `<h1>` title (description, else name, else
"No description available"), type badges, then organism / assembly / gene set / location.

Proposed, top to bottom:

1. **Title** — unchanged when the gene has a name. When it has none, the title area shows
   the No-name statement instead of "No description available".
2. **Identity line** directly under the title: the Identity statement text, with its accession
   as a link (HGNC, InterPro, PANTHER, Pfam). One line; max 91 characters.
3. **"How this gene was named"** — statements 2–9 as a compact labelled list, sorted by
   `score`, each kind with a short fixed label derived from the source name
   (Support, Copies, Alignment, Domains, Tree, Cautions, Features, Expression).
   - Cautions styled as a caution (the one place colour means state).
   - Long texts (Copies ≤ 249, Cautions ≤ 571 characters) clamp to two lines with "more".
   - Collapsed or expanded by default — to decide.
4. **Closest human gene** as one line with its link, if we show it at all.
5. A small "names from HGNC release `<annotation_source_version>`" footnote.

Statements are keyed by GENE. The gene page also renders for a transcript opened directly
(`parent.php` resolves up to the parent) — confirm statements still show in that case.

## Order of work

1. Role function + smoke tests (pure, hermetic). Nothing visible changes.
2. Apply the role at the consumers in the table (rows 1–8, 11). Still nothing visible changes
   on today's data — verifiable as a byte-identical gene page before/after.
3. Index exclusion in `build_fts_index.sql`, coordinated with `naming-v2`.
4. Load ONE organism (Congeria) with the new types on the web host. Check: gene page shows no
   new tables, pickers show no `MOOP-NAMING-*` sources, searching a statement phrase finds
   nothing, database size before/after.
5. Build the overview card display together, on real data.
6. Decide rows 9, 10 and the open questions; update help text.

Steps 1–3 must be on `main` and live before step 4.

## Open questions

- `Gene Name Source`: show, or hide now that Identity statements exist?
- `Closest Gene`: a line in the overview card, a normal table type, or hidden?
- `Paralogs`: a normal searchable table type like Orthologs, or something else?
- MOOPmart and the gene-page CSV: include statements or not?
- Statements list: open by default, or collapsed behind "How this gene was named"?
- Database size: measure Congeria before and after loading. If the three types add a lot,
  `Gene Name Source` is the first candidate to stop loading (it duplicates Identity).
- `HGNC:21625` is URL-encoded to `HGNC%3A21625` in the link — check genenames.org accepts it.

## Related, same file

`tools/pages/parent.php:517` still fatals on gene sets with no GFF (17 organisms; see
`notes/SESSION_HANDOFF_2026-09-17.md`). The overview work touches the same template; fix it
first so the new card can be checked on those organisms too.

---

## FINAL DESIGN 2026-10-01 (evening): self-contained naming tables

Decided with the user; the pipeline side is being built on `naming-v2`. This supersedes the
"role per annotation type" design above for Gene Statement and Gene Name Source. The sections
above are kept for the reasoning and for the list of places an annotation type surfaces.

**What goes where**
- `Gene Statement` and `Gene Name Source` → their own tables, **gene rows only** (never a
  transcript). They are not annotations, so search, the gene-page tables, the badges, the
  pickers and MOOPmart cannot see them by construction — nothing has to remember to hide them.
- `Closest Gene` and `Paralogs` → stay ordinary annotation types (searchable, tables).

**The tables — self-contained, nothing shared with the annotation tables**

| Table | One row per | Columns |
|---|---|---|
| `gene_naming` | gene and kind | `gene_naming_id`, `feature_id` (the gene), `kind`, `sort_order`, `naming_text`, `accession` (NULL where none), `link_kind` (NULL when the accession has no link). `UNIQUE (feature_id, kind)` |
| `gene_naming_link` | database | `link_kind` (hgnc, hgnc_group, interpro, panther, pfam, ensembl, ncbi), `source_url`, `accession_url` (prefix; link = prefix + accession) |
| `gene_naming_run` | gene set | `gene_set_id`, `data_version` (HGNC release date), `run_date`, `details` (from naming_versions.txt) |

`kind` is one of: identity, no_name, support, copies, identical, protein, alignment, domains, tree, cautions,
features, expression, pipeline_name, name_source (2026-10-06: `protein`, order 5, the protein the name rests on; later kinds moved down one). `sort_order` is 1–9 for statements and the naming step for
name_source (0 = none). Identity is ONE kind; which database it links to is in `link_kind`.

The card's query:

    SELECT n.kind, n.sort_order, n.naming_text, n.accession, l.accession_url
    FROM gene_naming n
    LEFT JOIN gene_naming_link l ON l.link_kind = n.link_kind
    WHERE n.feature_id = ?
    ORDER BY n.sort_order;

`UNIQUE (feature_id, kind)` serves that lookup; no further index is needed.

**Why not reuse `annotation_source` for the URL, version and date** (considered and declined;
the user was content either way). It works, but it ties the statements back to the annotation
machinery they were just separated from:
- `scripts/delete_gene_set.sh:71` and the `naming-v2` loader's reload path both delete every
  source not referenced by `annotation`. A naming source would be referenced only by
  `gene_naming`, so both would delete it — for every gene set in the organism.
- Four queries in `lib/database_queries.php` (`:887`, `:1357`, `:1382`, `:1463`) list sources
  or types without requiring annotations, so empty naming sources would appear in the pickers,
  in Manage Annotations, and would shrink the search pool's per-type slices.
- Each HGNC release would leave old source rows behind (sources are unique on name + version).

**Still to do on the pipeline side for this design**
- `delete_gene_set.sh` must delete the gene set's `gene_naming` and `gene_naming_run` rows
  along with its features.
- A reload replaces a gene set's `gene_naming` rows and its `gene_naming_run` row.
- Load `Closest Gene` at ONE level (the transcript, like other annotations). Loaded for the
  gene and each transcript, it draws the same table twice on a gene page.

**Site work that remains** (replaces steps 1–3 of "Order of work" above)
1. Card query and rendering in `tools/parent.php` / `tools/pages/parent.php`, tolerant of a
   database with no `gene_naming` table yet — 84 of 85 will not have one at first.
2. Statements in the overview "Copy" text; decide whether the gene-page CSV includes them.
3. Gene page help text.
4. Load Congeria alone; measure file size and a few cold searches before and after.

**Separate, small, not blocking naming:** the four queries above already list sources that
have no annotations. Congeria shows two today (`ProtNLM`, `EggNOG`, count 0) in the Annotation
Search picker, and every empty type shrinks the real types' slices in the search pool
(`:887`). Make each list only sources that have annotations.

**Display** is still open — see the clickable sketch (Claude artifact "MOOP Naming Statements
Mockup"), which uses real Congeria Identity and Name Source sentences. Open: how much of the
point-by-point list to show beside the Name Source sentence, since they overlap; what the
title says for a gene with no name; open or collapsed by default.

## Display decided 2026-10-01 (user, from the sketch)

- **Layout:** the "full statement list" card — gene name, the short Identity sentence with its
  link, then the labelled point-by-point statements in `sort_order`
  (Support, Copies, Alignment, Domains, Tree, Cautions, Features, Expression).
- **Name Source is not shown**, and the user intends **not to load it** (`kind = 'name_source'`).
  The typed statements carry the same evidence in the form the user prefers. The sentence is
  still written to the `gene_name_source.*.moop.tsv` files and `naming_decisions.tsv`, so it
  can be loaded later without re-running naming.
  - Site code must therefore not assume a `name_source` row exists, and should ignore one if
    a database happens to have it.
  - Worth one check before it is dropped for good: on a gene with a long sentence (the
    Sushi-domain example, COKUS1KC_0000001), confirm every fact in the Name Source sentence
    also appears in one of that gene's statements.
- Still open: what the title says for a gene with no name; list open or collapsed by default.

## Display decided 2026-10-06 (user) — the remaining open questions

- **Unnamed gene** (`kind = 'no_name'`): the title reads **"Unnamed gene"**, and the no_name
  statement sits under it where the Identity line would be.
- **Named gene:** the provenance code is **dropped** from the title
  (`Sushi/SCR/CCP domain-containing protein [ISM|ipr|sim~|omaR]` → the name alone). "Codes are
  hard to remember on a quick look" — the human-readable reason is the Identity sentence,
  directly under the title.
- **Copy** (the overview's plain-text summary): **includes the statements** — the Identity /
  no-name sentence, then each statement as a labelled line (`Support: …`), in `sort_order`.
- **On load:** the statement list is **open**; the lower **Annotations section starts
  collapsed** (with a count in its header) — "I really don't want to overwhelm users". This
  applies on every organism, not only those with statements. A "Jump to" click on a section
  inside it must open it.
