# Agent access audit — 2026-11-17

Step 1 of the agent guide (`llms.txt`) work. Question: what can an AI agent use on SIMRbase
**anonymously, with plain GET, no CSRF token, no JavaScript**? Tested with curl from 127.0.0.1
(outside `auto_login_ip_ranges`, so a PUBLIC visitor). Decisions behind this: memory
`idea_agents_using_the_site` — guide at `simrbase.stowers.org/llms.txt`, for anyone, never moves;
agent-friendly but limited by behaviour (rate limits, capped responses), not by identity.

Public data today: **14 of 79 gene sets, 5 organisms** (`"public": true` in
`metadata/organism_assembly_groups.json`). Test gene: `gene-SMED30002742` (MACF1), smed_20140614.

## Works today (GET, anonymous, machine-readable)

| Endpoint | Gives | Rate class |
|---|---|---|
| `api/feature_search.php?q=ID` | ID lookup across accessible gene sets (JSON) | api |
| `tools/annotation_search_ajax.php?search_keywords=…&organism=…` | keyword search, one organism (JSON, capped 2,500 rows, says so) | api |
| `api/get_gff.php?organism&assembly&gene_set&uniquename` | GFF lines for a gene | heavy |
| `api/get_sequence.php?organism&assembly&seqname&start&end[&strand]` | genomic region (JSON) | heavy |
| `api/download_annotations.php?organism&uniquename` | all annotations of a gene (CSV) | heavy |
| `api/download_file.php?organism&assembly&gene_set&filename=protein.aa.fa` | whole gene-set FASTA (12 MB) | heavy |
| `api/gene_set_examples.php?organism&assembly&gene_set` | example IDs (JSON) | api |
| `api/gene_neighbor.php?…&uniquename&side=left\|right` | neighbouring gene (JSON) | api |
| `api/go_term_genes.php?term=GO:…&organism&assembly&gene_set[&sub=1]` | genes with a GO term (JSON) | heavy |
| `api/ontology_terms.php?o=PLANA&q=…` | term search (JSON) | api |
| `api/ontology_children.php?o=GO&id=GO:…` | child terms (JSON) | api |
| `api/anatomy_records.php?term=PLANA:…` | anatomy expression records (JSON) | api |
| `tools/crosswalk.php?ids=…&organism=…&download=tsv` | ID translation (TSV) | page |

HTML pages that carry their data without JS (usable but heavy, ~0.5 MB each): gene page
`tools/parent.php`, organism / assembly / gene-set pages, ontology term page.

## Restricted data stays hidden — verified

Non-public gene set (Tonatia_saurophila SIMR_2025-01-24) through 12 routes: gene page, GFF,
ID search, annotations CSV, FASTA download, region sequence, neighbour, GO genes, keyword search,
examples, crosswalk, gene-set page → all 403/404/empty; nothing leaked (crosswalk only echoes the
typed ID as "Not found").

## Gaps (step 2)

1. **robots.txt shuts agents out of all data.** `ChatGPT-User` and `Perplexity-User` (one person's
   assistant fetching on request) are in the `Disallow: /` list with the training crawlers; every
   other agent falls under `*`, which disallows `/moop/tools/` and `/moop/api/`. `Claude-User` is
   not named. → split training crawlers (keep blocked) from user-triggered fetchers (allow the
   documented links).
2. **No catalog.** Nothing lists what is public (organisms → assemblies → gene sets) as JSON;
   `tools/groups.php` redirects anonymous visitors to the home page. An agent cannot discover the
   valid `organism/assembly/gene_set` values.
3. **No compact gene record.** The gene page is ~475 KB of HTML — expensive for an agent's
   context. A JSON gene summary (name, description, location, transcripts, top annotations,
   links) would replace most uses.
4. **Exporter (MOOPmart) is POST + CSRF** (`api/moopmart_export.php`, also
   `download_search_fasta.php`): unusable by agents. A capped GET variant, or document the
   prefilled page link for a human to click.
5. **Sequence retrieval by ID** (`tools/retrieve_sequences.php?uniquenames=`) only prefills the
   form; sequences need a POST. A GET for a short ID list (capped) would cover the common ask.
6. **Search JSON is one organism per call** and up to ~1.4 MB at the cap; no `limit`/`offset`.
7. **BLAST / Primer BLAST**: POST + queue. Out of scope for v1 — guide points to the page.
8. **Turnstile** (off until launch) must not cover the documented links when it goes on.

## Built (step 2, 2026-11-17)

| api/v1 | What | Rate class |
|---|---|---|
| `catalog.php` | what this visitor can open, with complete links | api |
| `download.php` | a gene-set / assembly file (= download_file.php) | heavy |
| `gene.php?organism&id[&assembly&gene_set][&format=md][&download=1]` | the gene record: gene, location, transcripts + proteins, naming, annotations (merged across transcripts, capped 100/type), expression calls, orthologs + family, PAGE anatomy, also-known-as | api (~0.1 s) |
| `annotations.php?organism&uniquename` | full annotation CSV (= download_annotations.php) | heavy |

Gene record shares its lookup (`lib/gene_lookup.php`, `moop_gene_lookup()`) and PAGE section
(`moop_gene_page_section()`, lib/anatomy_expression.php) with the gene page -- both moved out of
tools/parent.php; 39 gene-page renders (public / network / admin) byte-identical before and after.
The gene page's overview has a Download menu (Markdown / JSON). Gaps 4–6 above still open.

## Still to build (task list, 2026-11-17)

Quick -- a thin api/v1 name over a GET endpoint that already works:
- [ ] ID search (`api/feature_search.php`)
- [ ] Rosetta Stone / ID translation (`tools/crosswalk.php … download=tsv`)
- [ ] genes with a GO term (`api/go_term_genes.php`)
- [ ] ontology term search + children (`api/ontology_terms.php`, `api/ontology_children.php`)
- [ ] genome region sequence (`api/get_sequence.php`)

New:
- [ ] keyword / annotation-ID search across organisms, with limit/offset
- [ ] gene LISTS: GO terms, orthologs, annotations for many genes at once (capped) -- the biggest gap
- [ ] sequences for a list of IDs, and subsequences (`ID:1-60`), GET, capped
- [ ] assembly statistics + BUSCO (assembly_stats.json, busco.json)
- [ ] KEGG pathways: for a gene, for a gene list, genes in a pathway
- [ ] Data Exporter as a capped GET

Then step 3: generated llms.txt (+ llm.txt), help page for people, thin SKILL.md, {simrbase} pointers.
