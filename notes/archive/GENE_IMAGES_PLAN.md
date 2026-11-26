# Gene images: in situ, RNAi phenotypes and other pictures of a gene

> **2026-11-12 — design changed; read `docs/ORGANISM_SIDE_FILES.md` §2–3 first.** The two-file
> design below (a `gene_images.moop.tsv` loaded by the pipeline + a sidecar) is replaced by ONE
> per-organism `images/images.tsv` → `gene_images.sqlite`, built on the web side like
> `anatomy_expression.sqlite`, with every row naming its data set (organism/assembly/gene set,
> optional analysis) for access. First data: Schmidtea, from the old Planosphere database — Davies
> 2017 in situ images + movies (PLANA-tagged) and Zeng 2018 single-cell plots (~98,000). There are
> no cuttingclass images yet. The serving, gene-page and checker sections below still apply.

**Status:** PLAN, not started (written 2026-11-05). Nothing is built; nothing changes until a step
below is done.

## Why

A gene page shows sequences, annotations, expression and the gene model, but not what most biologists
look at first: where the gene is expressed in the animal (whole-mount *in situ* hybridization, FISH,
antibody stains), and what happens without it (RNAi phenotypes). cuttingclass's protocols and paper
are built on exactly these images, for 4 species x 11 genes (Protocol 3) plus *beta-catenin-1* and
*odf2* RNAi (Protocol 4), and simrbase has published WISH data too.

Each image needs its context to mean anything: the assay, the developmental stage or biotype, the
environment (temperature, fed or starved, irradiated), the time point (days post amputation, days
after the last RNAi feeding), which fragment was cut, the view (dorsal or ventral), and for RNAi the
construct, the control and the penetrance (12 of 15 animals). Plus one descriptive sentence and a
credit line.

## The shape: one moop.tsv for the link, one sidecar for the conditions

Decided in discussion with Sofia: **load the gene -> image link through the existing `moop.tsv`
annotation format**, so it rides the pipeline and loader that already exist. What the 4-column
format cannot hold (structured conditions, a Spanish caption) goes in a **sidecar file** keyed by the
image file name.

### 1. `<gene_set>.gene_images.moop.tsv` -- loaded into organism.sqlite as an annotation type

```
## Annotation Source: Sanchez Lab WISH
## Annotation Source Version: 2026-11
## Annotation Source URL: https://planaria.stowers.org/
## Annotation Accession URL:
## Annotation Type: Gene Images
## Annotation Creation Date: 2026-11-05
## Gene	Accession	Accession_Description	Score
TRINITY_DN3065_c0_g1	gsp_pc2_wish_intact.jpg	pc2 is expressed in the nervous system: brain and ventral nerve cords. Intact adult, ventral view.
```

- **Gene**: the gene's ID in this gene set (as every moop.tsv).
- **Accession**: the image file name, unique within the gene set.
- **Accession_Description**: the caption, one sentence, English. It is indexed by search, so
  "brain" or "eye" finds genes whose images show them.
- **Score**: empty.
- **Annotation Accession URL: left EMPTY on purpose.** An accession link is the source URL + the
  accession (`lib/parent_functions.php`), so a URL baked in here would tie the data to one site's
  path (`/moop/` vs `/cuttingclass/`) and to one gene set. The site builds image links itself (below).
- One source per lab / data set and version, as for every annotation type. Several sources may load
  into one gene set (published figures, classroom submissions...), each with its own credit.

### One file per publication (Sofia, 2026-11-05) -- it fits the format as it is

For published images, make **each publication its own annotation source**, i.e. its own moop.tsv:

```
## Annotation Source: Hands-on, classroom studies of regeneration and stem cell biology using freshwater planarians
## Annotation Source Version: 2017
## Annotation Source URL: https://doi.org/10.1525/abt.2017.79.3.208
## Annotation Accession URL:
## Annotation Type: Gene Images
## Annotation Creation Date: 2026-11-05
```

- **The credit comes free**: the source name and URL are the citation and the DOI link, shown with
  every image from that file. For a published set the sidecar's `credit` / `doi` columns can stay
  empty (they are for unpublished images).
- **A paper is one unit**: loaded together, versioned together, removed together (a source is UNIQUE
  name + version, so a corrected re-release is a new version, and the loader's per-source checks
  already stop two kinds of analysis sharing one source).
- **Grouping on the gene page** by source = by paper, so a gene imaged in two papers shows two labelled
  sets, which is how a reader thinks about them.
- **One file per publication PER GENE SET.** The loader works on one organism's database and matches
  gene IDs within it, so a paper covering four species is four files with the SAME source header
  (`Hands-on...2017.gene_images.moop.tsv` in each gene set). Same source name everywhere, so a later
  "images from this paper" page can gather them across organisms.
- Unpublished images (lab sets, classroom submissions) use a lab or data-set source as above.

What this buys, with no new loader and no new table:
- the link to the gene, and **access control**: an image follows its gene set's access rules;
- **search** over captions (the FTS index already covers annotation descriptions);
- one pipeline step, `load_annotations_sqlite.pl`, like the other ~35 annotation files.

Schema stays lean (CLAUDE.md 9): the database holds the gene -> file name -> caption link only. The
images and their detailed conditions stay files, regenerable and outside SQLite.

### 2. `images/images.tsv` -- the sidecar, read by the web side only

One row per image, keyed by file name, in the gene set's `images/` folder beside the image files:

| column | example | notes |
|---|---|---|
| `file` | `gsp_pc2_wish_intact.jpg` | must match the moop.tsv accession |
| `assay` | `WISH` | WISH, FISH, antibody, RNAi phenotype, live, other |
| `stage` | `asexual adult` | or a staging term (see open question 2) |
| `condition` | `starved 7 days` | environment: temperature, fed/starved, irradiated... |
| `time_point` | `7 dpa` | days post amputation, days after last RNAi feeding... |
| `fragment` | `trunk` | head / trunk / tail / intact |
| `view` | `ventral` | dorsal / ventral / lateral |
| `rnai_construct` | `PR-T4P beta-catenin-1` | RNAi only |
| `rnai_control` | `PR-T4P empty` | RNAi only |
| `penetrance` | `12/15` | RNAi only: animals with the phenotype / animals scored |
| `caption_es` | `pc2 se expresa en el sistema nervioso...` | other languages: `caption_<lang>` |
| `credit` | `Sánchez Lab` | who made it |
| `doi` | `10.1525/abt.2017.79.3.208` | source paper, if published |
| `license` | `CC BY 4.0` | |
| `scale_um` | `100` | scale bar length, optional |

Empty cells mean "not recorded", never "none". A row in the sidecar with no moop.tsv row, or a
moop.tsv row whose file is missing, is reported by the checker (step 5) and shown nowhere.

### 3. Files on disk

```
organisms/<Org>/<asm>/<gene_set>/
    <gene_set>.gene_images.moop.tsv    (also loaded into the DB by the pipeline)
    images/
        images.tsv
        gsp_pc2_wish_intact.jpg
        ...
```

Copied by the pipeline's copy step with the rest of the gene set. Originals as made (JPEG/PNG/TIFF
converted to JPEG/PNG at copy time if needed); the web side makes thumbnails into `cache_path`
(regenerable, so they are cache, not data).

## Web side (this repo; web-owned files)

1. **Serving, access-checked: `api/gene_image.php`.** nginx denies `organisms/` to the web on
   purpose, so images cannot be linked directly. The endpoint takes organism, assembly, gene set and
   file name; checks `has_assembly_access()` / gene-set visibility exactly as the gene page does; checks
   the file is listed in that gene set's moop rows (no path traversal: `basename()`, `realpath()` under
   the gene set, the same rule as `lib/fasta_download_handler.php`); and streams it with a long cache
   header. `?size=thumb` returns a thumbnail, made on first request into `cache_path` (PHP GD; a
   missing GD degrades to the full image, never an error). Same pattern as `api/jbrowse2/genome_file.php`.
2. **Gene page section "Images".** Rendered only when the gene has rows of type "Gene Images", so
   every page without images is byte-identical (render_compare). Thumbnails grouped by assay; click for
   a larger view with the caption (in the visitor's language when the sidecar has it), the condition
   fields that are filled in, and the credit / DOI. The plain annotation table does not also list
   these rows.
3. **Gene page nav sidebar** gains an "Images" entry when the section exists (`js/modules/parent-nav.js`).
4. **Search**: nothing to build; captions are already searchable. A later filter, "genes with
   images", is a cheap `EXISTS` on the annotation type.
5. **Checker, `scripts/check_gene_images.php`** (read-only, like the planned `check_expression.php`):
   every moop row has its file and a sidecar row; every sidecar row has a moop row; files open as images;
   no file over a size limit; condition fields drawn from the agreed vocabulary. Run before a copy,
   and by housekeeping as a dashboard count.
6. **cuttingclass Primers page**: each gene's WISH thumbnail beside its primers, once images exist.

All visitor text through `t()` (CLAUDE.md 7b). Opt-in by data: a site with no image files shows
nothing new.

## Compute side (Sofia's pipeline; compute-owned files)

1. Produce `<gene_set>.gene_images.moop.tsv` and `images/images.tsv` per gene set that has images.
2. `setup_new_moopdb_and_load_data.sh`: one more `load_files "*.gene_images.moop.tsv" "Gene Images"`
   line. The loader itself should need no change (four standard columns).
3. `copy2moop_v2.sh`: copy the `images/` folder with the gene set.

## First data set

The WISH figures behind cuttingclass's Protocol 3 tables and the paper ("Hands-on, classroom studies
of regeneration and stem cell biology using freshwater planarians"): up to 44 genes in 4 species, plus
the RNAi phenotypes of Protocol 4. The genes are already mapped to the new transcriptomes by primer
sequence (41 of 44, 4 of 5; see the cuttingclass Primers page), so the moop.tsv Gene column can be
filled directly from that mapping.

## Open questions

1. **Who adds images?** Pipeline only (as above), or also a web submission form (the cuttingclass FAQ
   invites classroom photos) whose entries an admin approves before they appear? A form is a separate,
   later piece: uploads would land in a moderation folder under `cache_path`, never in `organisms/`.
2. **Free text or a fixed vocabulary for stage / anatomy?** Fixed terms make filtering reliable.
   Planosphere already has planarian staging and anatomy ontologies (PLANA), which ties in with the
   Planosphere move.
3. **Both sites?** The feature is the same code on simrbase and cuttingclass, appearing only where
   image files exist. Agreed in principle; confirm simrbase wants it.
4. **Spanish captions** come from the sidecar (`caption_es`). Without one, Spanish visitors see the
   English caption. Is that acceptable for cuttingclass's first data set?
5. **Image size and format limits**, and whether originals should be kept at full resolution or
   reduced at copy time.

## Steps, in order

1. Agree this note (both sides).
2. Compute: write the two files for one gene set (cuttingclass *Girardia* sp., a handful of WISH images from the 2017 paper, as that paper's source)
   and load it.
3. Web: `api/gene_image.php` + thumbnails, tested for access (a restricted gene set's image must 404
   for a public visitor) and for traversal.
4. Web: the gene page section and nav entry; render_compare proves pages without images unchanged.
5. Web: `check_gene_images.php`, then the dashboard count.
6. Roll out to the rest of the first data set; then the cuttingclass Primers page thumbnails.
