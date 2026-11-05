# Organism Display System

## Overview
The organism display system reads from `organism.json` files in each organism directory and generates formatted display pages.

## Files
- **`/tools/organism.php`** - Controller: loads the organism, checks access, prepares the data
- **`/tools/pages/organism.php`** - Display content for the organism page

## Usage

### Viewing an Organism
Navigate to: `/moop/tools/organism.php?organism=<organism_name>`

Example: `/moop/tools/organism.php?organism=Lasiurus_cinereus`

Organisms are also linked from the group, assembly, gene-set, and feature pages.

### Editing organism.json
Use **Admin → Manage Organisms → Status** for an interactive editor, rather than editing the
file by hand. The **New Organism Setup Checklist** documents every field.

## JSON Format

**You only need four fields.** MOOP derives the rest — see "What MOOP fills in" below.

```json
{
  "genus": "Lasiurus",
  "species": "cinereus",
  "common_name": "Hoary bat",
  "taxon_id": "257879"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `genus` | yes | |
| `species` | yes | |
| `common_name` | yes | |
| `taxon_id` | yes | NCBI Taxonomy ID; drives the lineage, description, and image lookup |
| `feature_types` | written by loader | e.g. `{"parents":["gene"],"children":["mRNA","transcript","protein"]}` — what the gene page treats as top-level. Omitted, MOOP falls back to `["gene","pseudogene"]` |
| `subclassification` | written by loader | Optional strain/subspecies shown on the organism page, e.g. `{"type":"strain","value":"CC7"}`; `{"type":null,"value":null}` when unused |
| `images` | optional override | See below |
| `html_p` | optional override | See below |

Every organism on this deployment carries the four required fields plus `feature_types` and
`subclassification` — the last two are written by the loader, not typed by hand.

## What MOOP fills in

Leave `images` and `html_p` out and MOOP supplies both. Add either one *only* to override it —
supplying a field switches that piece off entirely; MOOP does not merge its own content with yours.

**Image** — `getOrganismImage()` in `lib/functions_display.php`, in order:
1. `images[]` from `organism.json`, if present
2. A cached NCBI taxonomy image at `images/ncbi_taxonomy/{taxon_id}.jpg`, **if the file already
   exists** — this step does not download anything
3. Wikipedia — fetched on demand and cached in `images/wikimedia/`, so it downloads once

**Description** — omit `html_p` and MOOP writes one: a summary built from the taxonomic lineage
(via `taxon_id`) plus the Wikipedia extract, credited with a link back to the article.

### Overriding
```json
{
  "genus": "Lasiurus",
  "species": "cinereus",
  "common_name": "Hoary bat",
  "taxon_id": "257879",

  "images": [
    { "file": "Lasiurus_cinereus.jpg", "caption": "Image of a Hoary bat" }
  ],
  "html_p": [
    { "text": "<u>Diet:</u> Hoary bats feed primarily on moths...", "style": "", "class": "fs-5" }
  ]
}
```

## Display Features

The organism display page includes:
- **Header Section**: Image, common name, scientific name, taxon ID
- **Description**: Formatted text about the organism
- **Resources**: Links to available genome data files
- Responsive Bootstrap 5 design
- Consistent styling with group pages
- Back navigation button

## Notes

- Images you supply yourself go in `/moop/images/`. Auto-fetched ones are cached under
  `images/ncbi_taxonomy/` and `images/wikimedia/` — leave those to MOOP.
- Prefer **Admin → Manage Organisms → Status** over hand-editing `organism.json`.
