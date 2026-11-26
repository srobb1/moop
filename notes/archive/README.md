# notes/archive — finished, superseded or merged notes

Moved here 2026-11-25 (notes review). These are history, not instructions. **Status lines at the top
of an archived note are often stale** — several still say "not started" for features that were built
(sequence headers, the Planosphere Rosetta pages, the shared scope selector). Read the
code, not the header.

| Why kept | Notes |
|---|---|
| Code or docs cite them for the reasoning behind a design | PRIMER_BLAST_TOOL_PLAN, PLANOSPHERE_PAGE_ROSETTA_PLAN, SHARED_SCOPE_SELECTOR_PLAN, PAGE_BY_PAGE_AUDIT_PLAN, RESULTS_TABLE_TOOLBAR_REVIEW, GROUP_TAXON_CHECKER_PLAN, ONTOLOGY_BROWSER_PLAN (docs/ONTOLOGIES.md) |
| Built, and the only written record of its standard | SEQUENCE_HEADERS_PLAN (FASTA header standard) |
| Measurements and why they were wrong | IT_REQUEST_RAM_INCREASE (the disk reading off by ~60x), IT_REQUEST_SEND, STORAGE_AND_RAM_TESTING, SCHEMA_AND_COLD_CACHE_REVIEW, SEARCH_RANKING_LITERAL_TIER |
| History: why things are the way they are | BUG_HUNT_2026-10-21, NAMING_V2_REVIEW_2026-09-25/28/29/30, NAMING_ANNOTATIONS_FOR_THE_SITE_2026-10-01, COKUS_REVIEW, CLOSEST_SPECIES_NOTES, RNASEQ_NOTES_REVIEW, EXPRESSION_DATA_LAYER_PLAN, EXPRESSION_EXPLORER_PLAN, EXPRESSION_GENE_PAGE_PLAN (bigWig route dropped 10-09), NEW_TOOL_SURVEY_AND_RECOMMENDATIONS, PIPELINE_RELOAD_PLAN, ANNOTATION_LOADING_DATA_DEFECTS, ANNOTATION_SOURCE_NAMING_RULES, ANATOMY_IDS_FROM_COMPUTE_2026-11-19 |
| Open lines copied to the work list Inbox | NEXT_SESSION_CHECKLIST, DOWNLOAD_FILENAMES_REOPENED, PIPELINE_CHECKS_FROM_WEB_2026-10-21 (plus RESULTS_TABLE_TOOLBAR_REVIEW above) |
| Planning drafts behind a finished page | TOUR_DRAFT_2026-11-24 |

**Deleted 2026-11-25 (29 files):** notes whose full text lives elsewhere (the three clade notes, now in
docs/CLADE_HOMOLOGS_FORMAT.md; five notes appended whole to kept notes), features built and documented
(gene images, annotation scores), finished checklists, answered requests, six session handoffs and two
one-day plans. Every one is still in git: `git show c8ddce1c:notes/archive/<name>`.

**Left in notes/ on purpose although finished:** NAMING_V2_PLAN, NAMING_STATEMENTS_ON_SITE_PLAN,
ASSEMBLY_NAME_IS_ID_PREFIX_FROM_WEB_2026-11-20, EXPRESSION_COUNT_TABLES_PLAN and
KEGG_PATHWAY_PLAN_2026-11-13 — files in compute's pipeline (`config/build_and_load_db/`) cite them by
path, and the web side does not edit compute's files. Move them when compute updates those comments.
