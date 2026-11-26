# Links that open a new tab on SIMRbase — list and recommendation (2026-11-20)

Work list item F. How it was gathered: logged in as the test admin, fetched 54 visitor pages
(home, organism / assembly / gene set / gene pages, every tool, help topics, content pages) and
recorded every rendered `<a target="_blank">`; then grepped the JavaScript for links and forms it
builds later (search results, tables), which a page fetch cannot see. Admin pages left out —
admins working in several tabs is fine.

Result: **140 links leave the site** (GitHub, stowers.org, Planosphere, NCBI, EBI, Wikipedia,
OBO, KEGG...) — keep all of those. **62 stay on SIMRbase**, in the groups below.

## Stay on SIMRbase — decide each

| # | Where | Opens | Count | Recommendation |
|---|-------|-------|-------|----------------|
| 1 | Home page: group chips (Bats, Planaria, ...) | groups.php | 21 | **Same tab.** Home is where you start; a new tab per click piles up tabs. |
| 2 | Organism / assembly / gene set pages: tool buttons (BLAST, Retrieve, Primer BLAST, Downloads, Ontology Browser, Rosetta Stone, Expression by Anatomy) — `lib/tool_section.php` + `js/modules/tool-launch.js` | the tool | 7 / 6 / 6 | **Same tab.** Going to a tool is the next step; Back returns to the page. |
| 3 | Home page organism picker: "open organism page" icon, and Search Organisms with one organism (`js/index.js` 142, 162) | organism.php | 2 | **Same tab.** |
| 4 | Search results: gene ID links (`js/modules/shared-results-table.js` 100; home quick search `feature-search.js` 138) | gene page | — | **Keep new tab.** People open several hits from one list. |
| 5 | Data Exporter (MOOPmart) results: gene ID links (`moopmart.js` 784) | gene page | — | **Keep new tab** (same reason as 4). |
| 6 | Results table "retrieve selected sequences" (`datatable-config.js` 258) | sequences | — | **Keep new tab** — the table and its selection stay. |
| 7 | Data Exporter → Expression heatmap (`moopmart.js` 982) | expression.php | — | **Keep new tab** — the code says why: the exporter keeps its state. |
| 8 | Organism / assembly / gene set: KEGG "Map" (`api/kegg_map.php`) | KEGG's site | 3 | **Keep** — it ends up on kegg.jp. |

So: change 1–3 (about 37 links), keep 4–8. In one sentence: a new tab only when the link
leaves the site, or when it opens one item out of a list you will want to come back to.

DONE 11-20 (Sofia OK): 1, 2 and 3's organism-page icon now open in the same tab
(`tools/pages/index.php`, `lib/tool_section.php`, `js/index.js`). Kept as new tab after all:
the SELECTION tools (`js/modules/tool-launch.js`, and "Search Organisms" with one organism) --
they carry what you ticked, and PHP pages are sent no-store, so Back reloads the page and the
ticks are gone. Re-crawl: the only on-site new-tab links left are the 3 KEGG Map links.
