# Planosphere in MOOP: PAGE expression search, Rosetta Stone, ontologies

Status: **design for review** (2026-11-09). Nothing built yet.
Context: [[project_planosphere_assessment]] memory; data and old code located on 2026-11-09.

## The rule this plan is built around

**Everything here must be easy to keep up to date** (Sofia, 2026-11-09). Concretely:

- Each piece is loaded **from the file you already maintain**, in the format it already has. No
  new format to keep, no hand step that only one person remembers.
- Each piece is a **separate, rebuildable file**, rebuilt on its own: a new PLANA release does not
  touch the curations, new curations do not touch the Rosetta Stone.
- A rebuild **never breaks the live site**: build into a temporary file, check it, then swap
  (the pattern of `gene_track_build_index()`).
- MOOP **tells you** when something is out of date (weekly, report-only, on the admin dashboard)
  and shows a **review list** of what it could not use. It never silently drops or rewrites data.
- One loader per piece, run by an admin button **or** from the command line, so nothing depends
  on a browser session.

## What exists (measured 2026-11-09)

| Piece | Source of truth | Size | Today on Planosphere |
|---|---|---|---|
| PAGE curations | `planosphere/PAGE` repo, `curation/OUTPUT/term_annotations_2024-05-24.txt` (9 columns) | 80,789 rows, 11 MB | triples in Blazegraph |
| Rosetta Stone | `RosettaStone_v2/smr_reformat_for_blaze_load/*.mapping` (3 columns: seq_id, ref_id, seq_id_source) | 10.4 M rows over 5 reference sets, ~30 source transcriptomes/arrays | triples in Blazegraph |
| Gene names / GO for the references | same folder: `*.names.tsv`, `smed_20140614.GO.tsv` | small | triples |
| PLANA (anatomy, life cycle) | `purl.obolibrary.org/obo/plana.owl` / `.obo`, release 2023-03-13 | 800 KB | Owlery + Blazegraph |
| BSPO (spatial terms) | `purl.obolibrary.org/obo/bspo.obo` | small | used by **627** curations |
| ECO (evidence) | `purl.obolibrary.org/obo/eco.obo` | ~7 MB | Blazegraph |

**IDs line up with simrbase.** Rosetta reference IDs are real MOOP features in
`Schmidtea_mediterranea`: `h1SMcG0000008` (gene) and `h1SMcT0000008.1` (mRNA) in
`schMedS3h1_WBPS19`, `SMED30004124` in `smed_20140614`; `dd_Smed_v6_*` in `ddv6`.
Haplotype 2 (`S2F19H2_WBPS19_*`) is **not** loaded in simrbase (open question 1).

## Storage — three small stores, none inside organism.sqlite

`Schmidtea_mediterranea/organism.sqlite` is already **2 GB**. Adding 10 M mapping rows would grow it
by roughly a third and tie its rebuilds to Rosetta Stone updates — against the lean-schema rule
(CLAUDE.md §9). So:

1. **One SQLite file PER ONTOLOGY** (decided 2026-11-09, Sofia): `<cache>/ontologies/plana.sqlite`,
   `bspo.sqlite`, `eco.sqlite`, + `sources/` (the downloaded TSVs). Site-wide, not per organism —
   PLANA serves every planarian species; organisms connect through curations, not ontologies.
   Each is built, checked and swapped alone (a bad ECO download cannot block PLANA), a big one
   later (GO, UBERON) is just another file, removing one = deleting its file. A short config list
   names the ontologies, their source files, and which the term search offers; adding one = one
   entry. The search box queries the offered files and merges (same idea as the ATTACH batches
   used for cross-organism ID lookup). Visitors open them READ-ONLY; builds run only in
   housekeeping's background CLI run (the admin button just requests a run, takes no input);
   sources are fixed GitHub addresses; every row validated (id shape, field whitelist, length)
   before it is stored; all text HTML-escaped on display. Each file holds:
   `term(id, label, definition, obsolete, replaced_by, ontology)`,
   `synonym(term_id, text, kind)` (exact/related/narrow/broad),
   `closure(term_id, ancestor_id, relation)` — **every** inferred link, all depths, all
   relationships, computed once per release by a real OWL reasoner (this replaces **Owlery**),
   `meta(ontology, release, loaded_at)`.
2. **`crosswalk.sqlite`** (per organism, next to organism.sqlite; name chosen 2026-11-09 — the
   visible tool name is a per-site setting: "Rosetta Stone Transcript Mapper" on simrbase) —
   **hub = schMedS3 h1 genes (`h1SMcG…`), AND h2 genes** (measured 2026-11-09: 1,063,847
   accessions reach 17,288 h1 genes; 97.5% map to exactly one, ~23k to two, ~4k to 3+, all kept;
   **17,901 accessions reach an h2 gene but no h1 gene** — `in-s-h2-not-s-h1` — so cross-mapping
   goes through h1 OR h2, as the old page's `ref_source` did. Only h1 genes link to MOOP gene pages
   until h2 is loaded (open question 1); h2 IDs link to WormBase ParaSite). Tables:
   `map(accession, accession_lc, source, ref_id, ref_set)` indexed on `accession_lc` and `ref_id`,
   plus `source(name, label, url_template)` (the link tables now hard-coded in the PHP page).
   Generic on purpose: cuttingclass's old Trinity IDs go in the same format (same pblat method,
   `planosphere/RosettaStone/code`).
3. **`anatomy_expression.sqlite`** (per organism; named 2026-11-09 — not "expression", which is
   MOOP's RNA-seq heatmap tool: this is WHERE a gene is expressed (anatomy term, from a paper), not
   HOW MUCH; 21,005 curations are themselves RNA-seq evidence. Visitors see "PAGE: Planarian Anatomy
   Gene Expression". Future phenotype (PLANP) curations get their own `phenotype.sqlite` — different
   shape: perturbation -> phenotype) — PAGE curations as rows (all 9 columns, dates
   normalised), plus `curation_feature(curation_id, feature_uniquename)` resolved through the
   crosswalk at load, so a gene page lists its curations with one indexed lookup.

Each has a `meta` table: source file / URL, its git commit or release, row counts, loaded_at.

## The term tree: a reasoner, run once per release (not hand-written rules)

Owlery answers "classes that are `R some X`" with full OWL reasoning: any depth of is_a, is_a
combined with R, chained relations, AND PLANA's logical definitions (defined classes, inferred
subclasses nobody asserted). Hand-written traversal rules would miss the last part. So at load
time run **ROBOT + ELK** (the OBO community's standard tools) over plana.owl and store every
inferred link. Same answers as Owlery, no Owlery server. Step 4 proves it on real searches.

**ECO closure too (2026-11-09, commit 74717f5):** `ontology/eco-closure.tsv`, 91,305 links, so the evidence filter's "any kind of in situ" (ECO:0001836 → colorimetric ECO:0001839 + FISH ECO:0001047) comes from ECO, not a hand list. The Action also rebuilds when an expected file is missing.

**Option C — DONE 2026-11-09 (Sofia's idea):** live in planosphere/PAGE, first run green, `ontology/plana-closure.tsv` = 40,347 links for release 2023-03-13. Details: the reasoned file lives in a GitHub repo
(PAGE repo, `ontology/`), produced by a **GitHub Actions** workflow that runs ROBOT +
`relation-graph` on GitHub's machines weekly / on demand, commits a plain TSV (term, related term,
relationship) only when PLANA has a new release, with the release in the commit message. MOOP
loads it like the curations. No Java on any of our servers; versioned; automatic. I write the
workflow, Sofia commits it.

Earlier options: **A** install Java + robot.jar on this server once (then a PLANA
update is one button) — leaning A; or **B** on the compute side, which writes the inferred list
for MOOP to load. Java is not on this server today (checked 2026-11-09). PLANA is small: seconds,
~1 GB RAM.

**Relationships PLANA uses** (owl:onProperty counts in release 2023-03-13). All are stored; the
search offers a chosen subset (setting, not code):

| Relationship | ID | Uses | Role |
|---|---|---|---|
| contained in | RO_0001018 | 797 | anatomy grouping (most used) |
| existence overlaps | RO_0002490 | 605 | life-cycle stage filter |
| part of | BFO_0000050 | 334 | anatomy grouping |
| overlaps | RO_0002131 | 164 | anatomy (partly inside) |
| occurs in | BFO_0000067 | 118 | processes |
| existence starts / ends during | RO_0002496 / RO_0002497 | 80 / 22 | life-cycle stage filter |
| develops from / into | RO_0002202 / RO_0002203 | 45 / 36 | development |
| derives from | RO_0001000 | 22 | lineage |
| has part | BFO_0000051 | 8 | anatomy (inverse) |
| BSPO spatial (anterior to, adjacent to, …) | BSPO_00150xx etc. | 1–14 each | position — not offered for "everything in X" |

**Search offers (Sofia, 2026-11-09): is a, part of, contained in, develops from, derives from.**
(The old page's four + derives from; not overlaps.) Life-stage filter uses the existence relations.

## What the visitor gets

1. **Term autocomplete** (local, no EBI dependency): exact label match first, then exact synonym
   ("brain" → *cephalic ganglia*), then prefix, then contains; obsolete terms hidden but resolved
   through `replaced_by`. PLANA + BSPO in one box.
2. **PAGE search (by term)** — term + relation (exact / is a / part of / contained in / develops
   from) + life-cycle stage + specimen + evidence → curations whose term is in the closure. Results:
   published accession, mapped reference gene(s) linked to MOOP gene pages, term, evidence,
   paper. Export TSV/FASTA through the existing results table. Search by **publication** and by
   **transcript** are the same page with a different first filter.
3. **Gene page: "Expression (PAGE)" section** — the curations that map to this gene.
4. **Rosetta Stone tool** — paste IDs → table (query ID, source, reference gene, reference
   transcripts, other mapped IDs from the sources you tick, description); download TSV. Same
   columns as today's page.
5. **Search finds old IDs** — the main search's ID check also looks up `crosswalk.accession_lc`
   (exact, indexed; only after the normal ID lookup finds nothing, so it costs nothing otherwise).
   Gene pages show "Also known as" with source names.

## Keeping it current (the part that matters most)

| Piece | How it is updated | How MOOP notices | Review list |
|---|---|---|---|
| PLANA / BSPO / ECO | Admin → button, or `php scripts/load_ontologies.php` | weekly: compare the purl's `versionIRI` (first 4 KB) with `meta` | curations on obsolete terms with no replacement |
| PAGE curations | commit to the PAGE repo; button / `scripts/load_expression.php <url or file>` | weekly: the repo's latest commit touching the curation file | unknown term, bad ECO, accession not mappable, PMID in Accession, duplicates (after date normalising), non-PLANA prefixes counted |
| Rosetta Stone | new mapping files from the compute side; `scripts/load_crosswalk.php <folder>` | file checksums in `meta` vs the source folder | reference IDs that are not features in the organism DB |

Curations are **never rewritten**: a renamed or merged term is followed at query time through
`replaced_by`; anything unresolvable is listed for a curator.

## Build order (each step is usable on its own)

1. **ontology.sqlite + autocomplete** (PLANA, BSPO, ECO), update button, weekly check, a test page.
   First visible piece; fixes what is broken on Planosphere today.
2. **anatomy_expression.sqlite + PAGE search page + gene-page section**, with the review list.
3. **crosswalk.sqlite + Rosetta Stone page + search aliases + "Also known as"**.
4. **Check against Planosphere** while it still runs: the same searches, same results (Owlery vs
   closure). Planosphere was unreachable on 2026-11-09; this step waits for it.
5. **The site decision** (landing page → simrbase, or a third MOOP). Nothing above depends on it.

## Open questions for Sofia

1. Load **haplotype 2** (`S2F19H2_WBPS19`) into simrbase? Without it, H2 IDs still appear in Rosetta
   results as text/links to WormBase ParaSite, just not as MOOP gene pages.
2. Which **source databases** should the Rosetta Stone offer? All ~30, or a curated list (some, like
   `ncbi_smed_ests` with 10 rows, may not be worth a checkbox)?
3. The two curations whose Accession is the paper's PMID (26711341, 28292427): intentional?
4. ~~Which relationships the search offers~~ — decided: is a, part of, contained in, develops from,
   derives from. Still open: where the reasoner runs (A: Java on this server, one-button updates —
   recommended; B: compute side, two steps).
5. Where the per-organism stores live: next to `organism.sqlite` (proposed; backed up and moved
   with the organism) — or under `cache_path` (regenerable, but then not in the backups)?

## Not now (noted)

- An AI / text-mining assistant that drafts curations from new papers for a curator to approve —
  it would produce rows in the same 9-column table, so this design does not change.
- Community submissions in MOOP (a form) — the GitHub submission template keeps working meanwhile.
