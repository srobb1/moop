# Planosphere page review — 2026-11-12 (for Sofia, 11-13)

Asked: (1) do we have everything from the current planosphere.stowers.org? (2) do we still need the
static anatomy pages now that there are ontology term pages? Read-only review; nothing changed.
Sources: the old site's Drupal dump (`.planophere_notes/dump_extract`: node, url_alias, menu_links),
the new pages (`planosphere/metadata/pages`, 53), `legacy_redirects.json` (56 aliases), live pages.

## 1. Do we have everything?

**Content: yes.** 126 published items on the old site; 56 map to a new page. The other 70 are not
visitor content:
- 39 Tripal bulk-loader job records, 12 home-page slideshow slides, 3 BLAST-database records
- test / admin pages: test, test2, test embed, pub test (x2), temp glossary test, add content, login
- download endpoints of the old tools (Download CSV Expression, Download Sequences, Download SPARQL,
  PAGE Download Results, Rosetta Stone results, PLANA DL Query) — the new tools have their own downloads
- "Blaze Rosetta Stone" (no address; superseded by the ID Translator)

**Gaps — old addresses with no redirect yet** (add to legacy_redirects.json before move day):
| old address | was | should go to |
|---|---|---|
| `welcome` | the home page | `/` |
| `planosphere-manuscripts` | Publications (main menu) | `page.php?p=publications` |
| `blast`, `blast/nucleotide/nucleotide`, `blast/nucleotide/protein`, `blast/protein/protein`, `blast/protein/nucleotide` | BLAST menu | BLAST tool |
| `find/genes` | Gene Search (menu) | search |
| `chado/analysis` | Search Analysis (menu) | probably drop (Tripal analyses list) |
| `search/worm-orders` | worm order admin | admin only — drop |

**Slideshow:** the old home page rotated 12 slides (Embryo 1–8, PLANA, piwi-1/tgs-1 coexpression).
Worth a look whether the new home page wants any of those images.

## 2. Static anatomy pages vs term pages

**Keep them — they ARE the term pages' descriptions.** A term page shows the content page whose
address is the term's name or a synonym (`moop_ob_written_page()`), in its "Description" card with
"Open as its own page". All 21 anatomy/stage pages attach to a term:
- by name: blastomere, digestive system, embryonic gut cell, integumental system, musculature system,
  nervous system, primitive ectoderm, primitive gut cell, renal system, visual system, Stage 1–8
- by synonym: definitive-pharynx → **pharynx**, neoblast-compartment → **neoblast**,
  temporary-embryonic-pharynx → **embryonic pharynx**

What to change instead:
- **Links point at the standalone copies.** The stage pages, crash course, tutorial and the anatomy
  pages link to each other as `page.php?p=nervous-system` etc. (20 pages do); the atlas already links
  to term pages (450 links site-wide). Pointing those at the term pages would put readers where the
  description, images, expression records and the ontology tree all are. A rewrite of the links
  (no content change).
- **Old addresses** (`nervoussystem` …) redirect to the static pages; could redirect to the term pages.
- **Repeated material:** the static pages carry embedded FASTA sequences of the marker genes (gene
  pages do this now) and gene tables the term page's Expression records cover. Trimming is optional.
- **embryonic-gut-cell and primitive-gut-cell are the same text** (differ only in an HTML id) on two
  different PLANA terms (0000059, 0000085). Same on the old site. Leave, or write one and point both.

## 3. A real gap: Planosphere has no images

`planosphere/organisms/Schmidtea_mediterranea/` holds only `anatomy_expression.sqlite` and
`organism.json` — no `gene_images.sqlite`, no `.images/`, no `crosswalk.sqlite`. So Planosphere's term
pages have **no Images card** (simrbase's term page for nervous system has one; 68 images are tagged
with nervous/digestive/visual/renal/gut terms). Fix: give planosphere the Smed image data — a symlink
(or bind mount) to simrbase's copy rather than copying ~8 GB — plus the crosswalk. Decision for Sofia.

## 4. Also on the list (Sofia, 11-12)

- **The dropdown menus are ugly** — restyle (Planosphere first? all sites?).
