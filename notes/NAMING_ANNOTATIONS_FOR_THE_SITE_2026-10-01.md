# Naming annotations: what the pipeline loads, for the site code (2026-10-01)

> **SUPERSEDED IN PART, same day.** Gene statements and the gene name source are no longer
> annotation types. They are loaded into their own tables (`gene_naming`, `gene_naming_link`,
> `gene_naming_run`): see "As built" just below and `notes/NAMING_STATEMENTS_ON_SITE_PLAN.md`
> (FINAL DESIGN). The sections after "As built" describe the earlier annotation-table design
> and are kept for the texts, counts and wording rules, which have not changed. `Closest Gene`
> and `Paralogs` are still ordinary annotation types.

## As built (naming-v2)

**Tables** (`config/build_and_load_db/data_loaders/create_schema_sqlite.sql`)

| Table | One row per | Columns |
|---|---|---|
| `gene_naming` | gene and kind | `gene_naming_id`, `feature_id` (the gene), `kind`, `sort_order`, `naming_text`, `accession` (NULL where none), `link_kind` (NULL when the accession has no link); `UNIQUE (feature_id, kind)`; `feature_id` cascades on delete |
| `gene_naming_link` | link database | `link_kind` (hgnc, hgnc_group, interpro, panther, pfam, ...), `source_url`, `accession_url` (link = `accession_url` + accession) |
| `gene_naming_run` | gene set | `gene_set_id`, `data_version` (the date of the data: the HGNC release), `run_date` (the day naming ran), `details` (naming_versions.txt) |

`kind`: `identity`, `no_name`, `support`, `copies`, `alignment`, `domains`, `tree`, `cautions`,
`features`, `expression`, and `name_source` (the long evidence sentence).

`sort_order`: 1 to 9 for the statements. For `name_source` it is the naming step (0 = none,
3 to 8), NOT a place in the series: select it by `kind`, do not sort it in with the statements.

The card's query:

    SELECT n.kind, n.sort_order, n.naming_text, n.accession, l.accession_url
    FROM gene_naming n
    LEFT JOIN gene_naming_link l ON l.link_kind = n.link_kind
    WHERE n.feature_id = ? AND n.kind <> 'name_source'
    ORDER BY n.sort_order;

- An accession with no link (`tree`: a PANTHER subfamily such as `PTHR12493:SF0`) is stored with
  `link_kind` NULL: show it as plain text, no `<a href>`.
- Rows exist for the gene alone (a feature with no parent), never for a transcript.
- Congeria: 200,673 rows for 43,768 genes; links hgnc, hgnc_group, interpro, panther, pfam.
- A database built before these tables existed is not loaded into: the loader stops and asks
  for a rebuild from the current schema. (Every database is rebuilt from scratch.)
- A load replaces the gene set's `gene_naming` rows and its `gene_naming_run` row.
  `delete_gene_set.sh` removes them through the cascades (it sets `PRAGMA foreign_keys = ON`);
  `gene_naming_link` is shared by all gene sets and stays.

**Files.** `gene_statement.*.moop.tsv` and `gene_name_source.*.moop.tsv` keep their names but are
"naming" files, not annotation files: header lines `## Naming Kind:`, `## Naming Link:`,
`## Naming Data Version:`, `## Naming Source URL:`, `## Naming Accession URL:`, `## Naming Run Date:`,
columns Gene, Accession, Naming_Text, Sort_Order. The loader recognises them by `## Naming Kind:`.
They have gene rows alone. The `MOOP-NAMING-*` source names no longer exist.

**Closest Gene** is an annotation type as before, now on the TRANSCRIPT alone (it was on the gene
and each transcript, which drew the same table twice on a gene page). A reload replaces the gene
set's rows of this type. **Paralogs** unchanged.

---

For planning the gene page (`tools/parent.php`, `tools/pages/parent.php`, `lib/parent_functions.php`),
annotation search and the source pickers. Written from the naming code on branch `naming-v2`
(`assign_gene_names_v2.pl`, `load_annotations_sqlite.pl`) and a Congeria run
(43,768 genes; counts below are from it).

## The four new annotation types

`annotation_source.annotation_type` is an exact string, the same in every file of the type.

| annotation_type | What it is | Attached to | Files |
|---|---|---|---|
| `Gene Statement` | short typed sentences about the gene, for the overview card | gene | `gene_statement.*.moop.tsv` |
| `Gene Name Source` | why the gene has its name, one long sentence | gene AND transcript (one row each) | `gene_name_source.*.moop.tsv` |
| `Closest Gene` | the closest human gene (always), and the closest gene of each configured species | gene AND transcript | `closest_*.moop.tsv` |
| `Paralogs` | the species' own genes in the same OMA HOG | transcript | `<CODE>.oma_hog_paralogs.moop.tsv` |

Row layout is the usual one: feature id, accession, description, score ->
`feature.feature_uniquename`, `annotation.annotation_accession`, `annotation.annotation_description`,
`feature_annotation.score`.

## Gene Statement

One source per statement kind. `score` is the statement's place in the series, NOT a score: sort by it.
A gene has between 1 and 9 statements (most have 2 to 4). Identity and No name are alternatives (both
order 1): a gene has one or the other.

| Order (score) | annotation_source_name | accession | Accession URL | Congeria rows | Text length (median / max) |
|---|---|---|---|---|---|
| 1 | `MOOP-NAMING-IDENTITY-HGNC` | `HGNC:21625` | `https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/` | 5,884 | 47 / 91 |
| 1 | `MOOP-NAMING-IDENTITY-HGNC-GROUP` | `865` (HGNC gene group id) | `https://www.genenames.org/data/genegroup/#!/group/` | 1,120 | |
| 1 | `MOOP-NAMING-IDENTITY-INTERPRO` | `IPR000436` | `https://www.ebi.ac.uk/interpro/entry/InterPro/` | 9,460 | |
| 1 | `MOOP-NAMING-IDENTITY-PANTHER` | `PTHR19325` | `https://www.ebi.ac.uk/interpro/entry/panther/` | 2,944 | |
| 1 | `MOOP-NAMING-IDENTITY-PFAM` | `PF13359` | `https://www.ebi.ac.uk/interpro/entry/pfam/` | 761 | |
| 1 | `MOOP-NAMING-NO-NAME` | `no_name` | none | 23,599 | |
| 2 | `MOOP-NAMING-SUPPORT` | `support` | none | 7,527 | 216 / 276 |
| 3 | `MOOP-NAMING-COPIES` | `copies` | none | 1,420 | 75 / 249 |
| 4 | `MOOP-NAMING-ALIGNMENT` | `alignment` | none | 17,493 | 80 / 381 |
| 5 | `MOOP-NAMING-DOMAINS` | `domains` | none | 17,551 | |
| 6 | `MOOP-NAMING-TREE` | `PTHR19325:SF575` (PANTHER subfamily) | none | 8,871 | |
| 7 | `MOOP-NAMING-CAUTIONS` | `cautions` | none | 13,859 | 22 / 571 |
| 8 | `MOOP-NAMING-FEATURES` | `features` | none | 29,289 | |
| 9 | `MOOP-NAMING-EXPRESSION` | `expression` | none | 17,127 | |

Other Identity sources can appear with other inputs, same pattern `MOOP-NAMING-IDENTITY-<KIND>`:
`ENSEMBL`, `NCBI` (a naming species), `CURATED`, `NATIVE` (the gene set's own name), `NOLINK`. Treat any
source starting with `MOOP-NAMING-IDENTITY` as the Identity statement.

- The kind is in the source name. The accession is a real id where there is one (Identity, Tree) and
  the kind word otherwise; it is never meant to be shown for the kind-word rows.
- The site builds a link as accession URL + urlencode(accession). `HGNC:21625` becomes
  `HGNC%3A21625`; check once on the live site that genenames.org accepts it.
- Example texts: Identity "Homolog of human ANO1; orthology not shown (may be a paralog); by full-length
  human hit". No name "No hits: no similarity hit in any database searched, ..." or "Hits did not pass
  the naming tests (found: ...)". Cautions "A short protein, 70 aa". Features "Predicted: location
  extracellular (DeepLoc 2: ...)". Expression "Expressed: a transcript matches it in its own
  transcriptome (...)" (positive statements alone; nothing says "not expressed").
- Wording rules the text follows: no confidence words (Strong / Moderate / Weak), no "only".

## Gene Name Source

Same accessions and links as the Identity split, but one long evidence sentence, and a row for the
transcript as well as the gene. `score` = the naming step that gave the name (0 = none, 3 to 8).

| annotation_source_name | Accession URL | Congeria rows |
|---|---|---|
| `Gene name source: HGNC gene` | genenames gene report | 11,768 |
| `Gene name source: HGNC gene group` | genenames group | 2,240 |
| `Gene name source: InterPro domain` | InterPro entry | 18,920 |
| `Gene name source: PANTHER family` | InterPro PANTHER entry | 5,888 |
| `Gene name source: transposable element domain (Pfam)` | InterPro Pfam entry | 1,522 |
| `Gene name source: none` (accession `None`) | none | 47,198 |

## Closest Gene

- Human, in every run: `Closest human gene (HGNC)` (19,296 rows; genenames link),
  `Closest human gene (Ensembl, no HGNC record)` (10 rows; Ensembl link), `Closest human gene family`
  (5,120 rows; no link, accession like `CR1L/CR1/C4BPA-family`).
- Another species when it is a `closest_species` in `geneset_config.yaml`: `Closest <species> gene` and
  `Closest <species> gene family`. The Congeria test has none.
- `score` = the tier of the evidence (1 = OMA ortholog ... larger = weaker kind of evidence).

## Versions and dates

- `annotation_source_version` of the statement, name-source and closest-human sources is ONE DATE: the
  HGNC release the names were made with (e.g. `2026-09-24`). It is the date of the data.
- `annotation_date` (from "Annotation Creation Date") is the day the naming ran: use it to point to the
  code version.
- `naming_versions.txt` beside the files lists every reference release and search (HGNC, OMA template,
  Ensembl Compara, UniProt, each closest species' searches). It is not loaded.

## Loading behaviour

- Loaded by `setup_new_moopdb_and_load_data.sh` with the other annotation files.
- Reload: for `Gene Statement`, `Gene Name Source` and `Closest Gene` the loader first deletes the gene
  set's existing rows of the type, then annotations and sources left with no rows
  (`load_annotations_sqlite.pl`, `%REPLACED_ON_RELOAD`). So a gene never shows two Identity statements
  after a second naming run, and an old source version does not linger. Other gene sets and other types
  are untouched.
- Identical texts are stored once in `annotation` (the loader keys on accession + description + source).

## What the site code has to handle

1. Display. Statements are keyed by GENE; most other annotations are keyed by transcript. On the gene
   page they are in `$all_annotations[$feature_id]['Gene Statement']` (the controller already fetches
   the gene's annotations). The plan: show them in the overview card, not as a table.
2. Order. `getAllAnnotationsForFeatures()` orders by feature and type alone. Sort statements by score.
3. `annotation_config.json`. The page loops over `$analysis_order`, so a type missing from the config is
   not drawn. Leave `Gene Statement` out of it if it is shown in the card, or it is drawn twice. Decide
   the same for `Gene Name Source`, `Closest Gene`, `Paralogs`.
4. Gene row count. The hierarchy's green badge sums every type on the gene; skip `Gene Statement` there.
5. Search. `build_fts_index.sql` indexes every annotation with a type tag: the type lower-cased with
   spaces, hyphens and underscores removed, between `atype` and `z`. Tags: `atypegenestatementz`,
   `atypegenenamesourcez`, `atypeclosestgenez`, `atypeparalogsz`. Exclude on the tag, or leave the type
   out of the index build.
6. Pickers (Annotation Search, MOOPmart): filter on `annotation_source.annotation_type` to hide the
   `MOOP-NAMING-*` sources.
7. Long texts. Copies can list many gene ids (max 249 characters), Cautions joins several clauses with
   "; " (max 571). Identity is short (max 91).
8. Unnamed genes. Show the No name statement where the card prints "No description available".

## Not decided
- Whether `Gene Name Source` stays on the gene page once the Identity statements are shown (both are
  loaded now; the user will choose on the page).
- Whether the name-source and closest-gene sources move to the `MOOP-NAMING-` naming scheme.
