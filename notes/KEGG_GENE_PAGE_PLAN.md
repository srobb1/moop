# KEGG on the site — web-side plan (2026-11-14)

Builds on `notes/KEGG_PATHWAY_PLAN_2026-11-13.md` (compute side: Parts 1–3). Agreed with Sofia 2026-11-14.

## What is there (checked 2026-11-14)

- All 66 organisms have three sources, `annotation_source_version` "KEGG 2026-11-13; KOs from eggNOG-mapper …":
  `KEGG Orthology (eggNOG)` → type **Gene Families**; `KEGG Pathway (eggNOG)` and `KEGG Module (eggNOG)` → type
  **Pathways** (already in annotation_config.json, order 14). `score` = number of KOs eggNOG gave the gene
  (1 = one; >1 = ambiguous: 46% of KO rows in Nematostella).
- The gene page shows them in the generic tables today: links to kegg.jp work, but Score reads "2"/"2.0" with no
  meaning, the pathway class is glued into the name ("TNF signaling pathway (Environmental Information
  Processing; Signal transduction)"), and some genes are long (Nematostella: avg 4 pathways/gene, 252 genes >30,
  max 126).
- **Link format:** the compute plan's `https://www.kegg.jp/pathway/map00010+K00844` is a **404**. Working:
  `https://www.kegg.jp/kegg-bin/show_pathway?map=map04668&multi_query=K04404+%23ff6666%0AK12793+%2366aaff`
  (one KO per line, each with its own colour). Returns 200; that the colours actually draw still needs one
  look in a browser.
- **Cost:** genes+KOs of ONE pathway in an organism: 28 ms (live is fine). All 428 pathways of an organism:
  4 s → a cached file per gene set (cache_path, rebuilt when organism.sqlite changes), not a DB table
  (CLAUDE.md §9 lean schema).

## Plan

1. **Score label** — a `kegg_alternatives` kind in `metadata/annotation_scores.json` (site data, no code):
   1 → "one KO", >1 → "ambiguous (N KOs)", with a hover sentence.
2. **Gene page KEGG section** — KO(s) on top with an "ambiguous" badge; pathways grouped by class (class from
   the snapshot's `pathway_classes.json`, not parsed from the name), collapsed when many; modules below.
3. **Per pathway row on the gene page:** "View on KEGG" = the map with THIS gene's KO in one colour and every
   other KO the gene set has in that pathway in another; "Genes in this gene set" = a MOOP annotation search.
4. **Gene-set page Pathways table:** pathway, class, genes, KOs found, coverage (KOs found / KOs in the
   pathway — needs the snapshot's `ko_pathway.tsv`), a coloured-map link, a gene-list link. Sortable, filtered.
   Shown beside BUSCO (low coverage in a fragmented set = "not annotated", not "absent").
5. **Colours:** a KO with one gene / with several genes (darker); this gene (gene page); ambiguous KOs paler.

Access: every count, gene list and coloured KO comes only from gene sets the visitor can see
(`moop_dataset_visible()`); a private gene set adds nothing.

**Not now (Sofia unsure, 2026-11-14):** an organism-level view (all of an organism's gene sets combined).
Decide after the gene-set level is live.

## Reference snapshot on the site

`copy2moop_v2.sh` (compute script; Sofia OK'd the web side adding this, 2026-11-14) now copies
`$REFERENCE_DATA/kegg/current/{info.txt, pathway.tsv, module.tsv, ko_pathway.tsv, ko_module.tsv,
pathway_classes.json}` to `organisms/.kegg/` — `--kegg` alone, and as part of `--all`. Missing file = FAIL, nothing
sent. Tested here only for the missing-file and argument checks (the web server cannot reach the `moop` ssh
host); **compute: run `copy2moop_v2.sh --kegg --dry-run` once, then `--kegg`.** The site compares the
snapshot date in `info.txt` with each gene set's `annotation_source_version` and says when they differ.

## Order

1 (score label) → 2 + 3 (gene page) → 4 (gene-set table, once `organisms/.kegg/` has arrived) → 5 throughout.
