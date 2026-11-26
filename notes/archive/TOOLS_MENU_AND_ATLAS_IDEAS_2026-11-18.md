# Tools menu reorganisation + Nematostella anatomy idea (2026-11-18; decisions 1-3 MADE + BUILT 2026-11-19)

Written at the user's request before lunch. Pick up from "Decisions to make".

## The Tools menu today (simrbase, live)

```
Genome Browser
──────────────
Annotation Search
MOOPmart: Data Exporter
Expression Explorer                 RNA-seq heatmaps for a gene list (how MUCH)
Anatomical Expression Search        PAGE curations by anatomy term (WHERE, from papers; Schmidtea only today)
Rosetta Stone ID Translator
Ontology Browser
BLAST Search
Primer BLAST
Primer Maker
Retrieve Sequences
Downloads
```

13 entries, one divider, no headings, not grouped by kind. The Endo-Atlas tool (`tools/spatial_atlas.php`)
is not in it: it is only reachable from Research → EndoAtlas and from gene-page cards.

How it is built: `ConfigManager::getToolsMenu()` — the site's saved `tools_layout.menu` order (`|` = divider)
or the registry's `menu_order`; labels from the tool registry (`config/tools_config.php`, `toolLabel()`);
switched-off tools are left out. So order and dividers are configurable now; **headings, groups and
per-organism entries are not.**

## The naming problem

Three "expression" tools a visitor can confuse:

| Tool | What it is | Suggested name |
|---|---|---|
| Expression Explorer | measured RNA-seq, heatmap for a gene list | **RNA-seq Expression Explorer** |
| Anatomical Expression Search | curated "gene X is expressed in tissue Y" from papers (PAGE) | keep, or **Expression by Anatomy (curated)** |
| Endo-Atlas | predicted 3-D expression in one Nematostella larva study | **Nematostella Endo-Atlas** (or a generic "Spatial Atlas" entry that lists atlases) |

## Ideas for the menu

1. **Group with headings** (Bootstrap `dropdown-header`), e.g.
   - *Browse & search*: Genome Browser, Annotation Search, Rosetta Stone, Ontology Browser
   - *Expression*: RNA-seq Expression Explorer, Expression by Anatomy, Spatial atlases (Endo-Atlas)
   - *Sequences*: BLAST, Primer BLAST, Primer Maker, Retrieve Sequences
   - *Export*: MOOPmart, Downloads
   Needs: a `heading` item type in `tools_layout.menu` + the Manage Menu admin screen, beside `|`.
2. **Organism-specific tools** (Endo-Atlas is Nematostella-only): either an "Expression" group entry that
   says which organism, or a separate per-organism part of the menu. The Research menu already holds
   study-specific pages; the Tools menu could stay general and list "Spatial atlas" as one tool whose
   page lists the atlases (only one today).
3. **A bigger menu** (mega-menu: columns per group) once there are headings — only if the grouped list is
   still too long.
4. Register the atlas as a tool in `config/tools_config.php` (id e.g. `spatial_atlas`, `url_path`
   `/tools/spatial_atlas.php`), so the menu link follows the link prefix (CLAUDE.md §3b) — a hand-typed
   `/moop/...` URL would break when the site moves to its own address. Today `tools/spatial_atlas.php`
   with no `atlas=` gives "not found"; with one atlas it could open it, with several list them.

## Idea: Nematostella anatomy terms → PAGE curations for Nvec

So the Anatomical Expression Search could cover Nematostella too, and the Endo-Atlas could feed it:
- The atlas already labels every model vertex with a segment region (52 labels like `1_5`, from the
  authors' `vertex_dict.json`; stored in the `region` table). These could become terms in a small
  anatomy ontology (segment 1-8 × sub-segment position), and genes "predicted high in segment N" could be
  listed — but that is a *prediction*, a different evidence type from a curated in situ (ECO code would
  have to say so).
- The 87 in situ images are real observations and are the natural first curations (gene + term + ECO in situ).
- **Before making an ontology, check whether a Nematostella anatomy ontology already exists** (OBO Foundry /
  OLS); reuse beats a home-made one, and PAGE curations need stable term IDs.

## Decisions to make

**2026-11-19 (Sofia):** 1 = RNA-seq Expression Explorer + Expression by Anatomy; 2 = yes, the four groups
above, one line of text per tool, single wide list (not a mega-menu); 3 = Endo-Atlas stays in Research only.
Built opt-in: `#Heading` entries in `tools_layout.menu`, `tools_layout.menu_blurbs`, registry `menu_group`/`menu_blurb`.

1. New tool names (table above)?
2. Headings in the Tools menu: yes/no, and the groups.
3. Endo-Atlas in Tools: its own entry, or a generic "Spatial atlas" entry?
4. Nvec anatomy: look for an existing ontology first?
