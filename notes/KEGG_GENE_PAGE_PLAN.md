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

## Progress

**2026-11-14 — steps 1–3 built.**
- Score label: `kegg_kos` kind in `metadata/annotation_scores.json.example` ("1 KO" / "2 KOs" + explanation;
  no site has its own annotation_scores.json, so all three get it).
- `lib/kegg.php` + `api/kegg_map.php`: the gene page's Pathways table has, above it, the pathways grouped by KEGG
  class (KEGG's order), an "ambiguous" badge (score > 1), "View on KEGG" and "Genes in this gene set"
  (search.php?q=mapNNNNN&source=KEGG Pathway (eggNOG), scoped to the gene set: only that one source is ticked
  and the search runs at once — `?source=` is new on the search page, 2026-11-14). The KEGG link goes through
  api/kegg_map.php (access-checked: private gene set = 404; ~70 ms) which redirects to the coloured map.
- **KEGG's colour link, verified in a browser:** only the exact documented form colours anything:
  `show_pathway?map=map04668&multi_query=K04404+%23ff8c42,black%0d%0aK04427+...` — without `,black` and CR+LF
  the map loads with NOTHING coloured, silently. `map…` (reference) not `ko…`: a KO the gene set lacks stays white.
- Found on the way: `moop_plain_name()` accepted a trailing newline (PCRE `$`); now `\z`, test red on the old code.
  `moop_same_organism_hit_url()` built `'/' . site . '/tools/…'` (§3b) — now moop_url().
Next: step 4 (gene-set Pathways table) once `organisms/.kegg/` arrives from compute (`copy2moop_v2.sh --kegg`).

**2026-11-14 — step 4 built: gene-set page "KEGG Pathways" card.** One row per pathway: class/subclass, genes
(per GENE — a transcript counts as its parent), ambiguous genes, KOs found (only KOs IN the pathway by
`organisms/.kegg/ko_pathway.tsv`), KOs in pathway, coverage, Map (whole gene set coloured) and Genes (the search,
KEGG pathway source only). Class filter + DataTables. Names/classes from the reference (pathway.tsv,
pathway_classes.json), else split from the loaded description; without the reference, no coverage column.
Cached per gene set (`{cache}/…/kegg_pathways.json`, keyed on organism.sqlite + reference mtimes): 0.6 s cold,
0.15 s cached. Query is source-first (0.11 s vs 1.06 s gene-set-first). Nematostella NV2: 425 pathways; TNF 63 genes,
39/97 KOs. `organisms/.kegg/` is on simrbase only (copy2moop targets it); planosphere and cuttingclass have no KEGG
annotations, so no card there.
Open: organism-level view (Sofia unsure); enrichment on gene lists (compute plan use 3).
