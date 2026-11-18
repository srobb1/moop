# Spatial expression atlas — plan (2026-11-18)

A **general** gene-page card that shows where a gene is predicted to be expressed on a 3-D model
of an animal: a mesh you can rotate, coloured per vertex by predicted expression. **Based on
EndoAtlas**, the first (and so far only) dataset. Any later atlas (mesh + one value per vertex per
gene) is another data file; there is no new code to write.

Status: **planned, nothing built.** Decided with the user 2026-11-18: general tool, rounded values
(below), cite the paper on the card.

## The first dataset: EndoAtlas

- He S, et al. *Spatial transcriptomics reveals a cnidarian segment polarity program in
  Nematostella vectensis.* Current Biology 2023. PMID 37315559, GEO GSE173786. Gibson Lab, Stowers.
- Single-cell RNA-seq of larval endomesoderm, placed on a 3-D larva model with novoSpaRc.
- Source: the authors' Dash app, `/var/www/html/.simrbase_notes/endo_atlas_dash_app_20230331.zip`.
  **This zip is the only durable copy**; the unpacked copy used in testing on 11-18 was in a session
  scratchpad. Before building, copy the build input into the site data so it can be rebuilt.
- What is in it:
  - `PREDDICTION` table: ID, GENENAME, PREDIC (comma-separated floats, **one per mesh vertex,
    8,993 per gene**) for **15,542 genes**. All IDs match the **NV2** gene set on simrbase.
  - Mesh `segmentation grid_no_top_dot.obj` (8,993 vertices / 8,928 faces), plus a grid-points obj
    (792 vertices).
  - `id_to_name.json` (14,096 named genes, e.g. Lbx `NV2g017667000.1`, Uncx `NV2g006849000.1`).
  - In situ images `assets/<id>.png` (87 genes, 14 MB).
  - Top-10 correlated genes per gene (CSV).
- The app runs locally (venv `/home/smr/endoatlas_venv`, dash 1.21 + flask/werkzeug 2.0.3) if we
  need to compare the look of the card with the original.

## Where it shows

| Place | What |
|---|---|
| Gene page (genes that have a row) | Card: rotatable 3-D mesh coloured by expression, the in situ image if there is one, top-10 correlated genes as gene-page links, the citation + PubMed/GEO links. Looks like the authors' app. |
| Research menu → EndoAtlas content page (`metadata/pages/endoatlas`) | Already written. Gets example links (Lbx, Uncx) that open the gene page with the card open, then joins the Research menu. |
| api/v1 gene record | Optional, later: "has atlas prediction", plus correlated genes. |

The card does not appear anywhere else. On a gene with no row, or any other organism, it costs one
file check and draws nothing (same as gene images and anatomy expression).

## How it is managed

1. **The file's existence turns it on.** `scripts/build_spatial_atlas.php` (CLI only; temp file,
   checks, rename; a failed build leaves the previous file, same as `lib/anatomy_expression.php`)
   turns the source into one read-only SQLite file: per-gene values, mesh, image list,
   correlated genes, and a `meta` table (atlas name, citation, PMID, GEO, source, value scale).
   No site_config switch to keep in step.
2. **Lives in the gene set's folder** it is keyed to:
   `organisms/Nematostella_vectensis/GCA_033964005.1/NV2/spatial_atlas/endoatlas.sqlite`. The IDs belong to that gene set, so retiring or replacing it takes the
   atlas with it, and the atlas can never sit on the wrong IDs. More than one atlas per gene set is
   allowed (one file each, e.g. `spatial_atlas/<name>.sqlite`).
3. **Access:** new analysis kind `atlas` in `lib/access_rules.php` (`MOOP_ANALYSIS_KINDS`), key
   `{organism}/{assembly}/{gene_set}/{atlas name}`. The gene set must be visible AND the analysis rule
   must pass, the same as expression, images and clade. Restricted from the existing admin access
   screen.
4. **Plotly.js** (Mesh3d) vendored through `scripts/vendor_assets.txt` (pinned sha256), so
   `library_advisories` watches it. Loaded only on gene pages that draw the card. No CSP change.
5. **Docs:** add §4 to `docs/ORGANISM_SIDE_FILES.md`; organism checklist shows the file is present.
6. The web only reads it, so it needs no writable-allowlist entry (CLAUDE.md §11).

## How the authors' app draws it (what the card must reproduce)

Read from `application.py` in the zip, 2026-11-18:
- **Values are in mesh vertex order.** PREDIC value *n* belongs to the *n*-th `v` line of
  `obj/segmentation grid_no_top_dot.obj`. Nothing else ties a value to a place. So the mesh and the
  values must always be shipped together, and the build checks the counts match (8,993 = 8,993).
- Faces: OBJ `f` lines, 1-based, `a/b/c` or `a//c` forms (only the vertex index is used); faces
  with more than 3 corners are fanned into triangles.
- **Axes are swapped:** plotted x = OBJ z, y = OBJ x, z = OBJ y. The same swap is used for the
  overlay points.
- Overlay: the 792 vertices of `obj/segmentation grid_grid_only3.obj` drawn as small black dots
  (size 1.5): the segment grid.
- Colour: plotly `viridis`, per-vertex intensity, colour bar titled "Expression prediction".
  Black background, camera eye (0, 1, 1.2), aspect ratio 1:1:0.8, hover off.
- A gene in `id_to_name.json` but not in PREDDICTION shows an all-zero mesh and "is not expressed
  in this study". A gene in neither is "Invalid gene name".
- In situ image: `assets/<gene id>.png` for the genes in `obj/insitu_list.txt`; otherwise
  `assets/insitu_NA.png` (we show nothing instead).
- Correlated genes: `obj/20210120_predicted_sig_top10_correlation_by_bin_51markers.csv`, columns
  `"Var1","top_genes","top_cor"` = query gene, similar gene, Pearson r; 10 rows per gene.
- Not used by the app: `obj/20220116_vertex_dict.json` (key `"x_y_z"` coordinates → region
  label like `"1_5"`, presumably segment_position), `all_points.obj`, `grild_only.obj`, `group1.obj`.
  The region labels are kept (optional table below) for a later "genes expressed in segment N" search.

## Input format — the atlas bundle (what any atlas must provide)

The build script reads one directory, the **bundle**. EndoAtlas is converted into a bundle once
(a small conversion script reads the zip); every later atlas is delivered in this form directly.
All text is UTF-8, tab-separated, one header line, `.gz` allowed on any TSV.

```
<bundle>/
  atlas.json          manifest (required)
  mesh.obj            the surface; one value per `v` line (required)
  values.tsv[.gz]     gene_id <TAB> v1,v2,...,vN  (required; N = vertices in mesh.obj)
  genes.tsv           gene_id <TAB> name      (optional; genes in the study, incl. not expressed)
  overlay.obj         points drawn as dots on top (optional; only `v` lines read)
  correlated.tsv      gene_id <TAB> other_id <TAB> r   (optional; order in file = rank)
  regions.tsv         vertex <TAB> region     (optional; vertex = 1-based OBJ index)
  images/<gene_id>.png|jpg   one picture per gene (optional), e.g. in situ
```

`atlas.json` (the `//` comments are explanation here only; the real file is plain JSON):
```json
{
  "name": "endoatlas",                       // file name + analysis key part; [a-z0-9_-]
  "title": "EndoAtlas",
  "description": "Predicted 3-D expression in the endomesoderm of the Nematostella larva",
  "organism": "Nematostella_vectensis", "assembly": "GCA_033964005.1", "gene_set": "NV2",
  "citation": "He S, et al. Spatial transcriptomics reveals a cnidarian segment polarity program in Nematostella vectensis. Current Biology 2023.",
  "pmid": "37315559", "data_url": "https://www.ncbi.nlm.nih.gov/geo/query/acc.cgi?acc=GSE173786",
  "value_label": "Expression prediction",
  "image_label": "In situ hybridisation",
  "axis_map": ["z", "x", "y"],               // plotted x,y,z taken from these OBJ axes
  "camera": {"eye": [0, 1, 1.2]}, "aspect": [1, 1, 0.8],
  "colorscale": "Viridis"
}
```

Rules the build enforces (anything it cannot use goes on a review list shown to admins, never
dropped silently, as with `lib/anatomy_expression.php`):
- every values row has exactly N numbers, all finite; otherwise the row is listed and skipped;
- every gene_id is a gene or transcript in the named gene set's `organism.sqlite` (unknown IDs listed);
- a duplicate gene_id is an error (the build stops);
- `mesh.obj` faces only refer to existing vertices; `regions.tsv` vertices are within 1..N.

## SQLite schema — `<gene set>/spatial_atlas/<name>.sqlite`

Read-only to the web. Built to a temp file, checked, renamed into place.

```sql
-- Everything from atlas.json, plus what the build measured. One row per key.
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);
--   atlas.json keys (axis_map/camera/aspect as JSON text), and:
--   format_version   '1'
--   value_encoding   'uint8' | 'uint16' | 'float32'   (see Rounding)
--   vertex_count, face_count, gene_count, overlay_count
--   built_at (ISO 8601), source_sha256 (of values file), build_review (JSON: kind -> count)

-- The surface. Exactly one row (id = 1). Little-endian binary:
--   vertices = float32 x,y,z per vertex, in OBJ order (3 * 4 * vertex_count bytes), axes NOT swapped
--              (axis_map is applied when drawing, so the stored mesh is the delivered one)
--   faces    = uint32 i,j,k per triangle, 0-based (3 * 4 * face_count bytes)
--   overlay  = float32 x,y,z per point, or NULL
CREATE TABLE mesh (
  id       INTEGER PRIMARY KEY CHECK (id = 1),
  vertices BLOB NOT NULL,
  faces    BLOB NOT NULL,
  overlay  BLOB
);

-- One row per gene in the study. vals NULL = in the study but not expressed (drawn all zero).
--   vals: vertex_count values in vertex order, encoded per meta.value_encoding:
--     uint8   1 byte each:  value = vmin + b / 255   * (vmax - vmin)
--     uint16  2 bytes LE:   value = vmin + b / 65535 * (vmax - vmin)
--     float32 4 bytes LE:   value = the number itself (vmin/vmax still filled)
--   A gene whose values are all equal stores vmin = vmax and all-zero bytes.
CREATE TABLE gene (
  gene_id TEXT PRIMARY KEY,
  name    TEXT,
  vmin    REAL,
  vmax    REAL,
  vals    BLOB
);

-- Most similar genes, by the atlas's own measure. rank 1 = most similar.
CREATE TABLE correlated (
  gene_id  TEXT NOT NULL,
  rank     INTEGER NOT NULL,
  other_id TEXT NOT NULL,
  r        REAL NOT NULL,
  PRIMARY KEY (gene_id, rank)
);

-- Pictures for a gene (in situ etc.), stored in the file so the atlas is ONE file: it moves with the
-- gene set on retire, and every picture is served through PHP after the access check.
CREATE TABLE image (
  gene_id TEXT PRIMARY KEY,
  mime    TEXT NOT NULL,          -- image/png | image/jpeg
  width   INTEGER, height INTEGER,
  data    BLOB NOT NULL
);

-- Optional: which region each vertex is in (EndoAtlas: segment labels from vertex_dict.json).
CREATE TABLE region (
  vertex INTEGER PRIMARY KEY,     -- 0-based, same order as mesh.vertices
  region TEXT NOT NULL
);
```

Size for EndoAtlas: gene ≈ 15,542 × 8,993 B ≈ 140 MB (uint8), images 14 MB, the rest under 2 MB.

Notes:
- Gene IDs are stored as delivered. EndoAtlas uses NV2 transcript-style IDs (`NV2g017667000.1`);
  the reader looks a gene page up by the gene ID **and** its transcripts' IDs, so either form works.
- `vmin`/`vmax` are the gene's own range, so the colour scale is per gene, as in the authors' app (plotly
  scales intensity to each figure's min..max). The colour bar shows the real vmin..vmax numbers.
- The mesh is sent to the browser once per atlas (cacheable endpoint); a gene's values are a
  separate small request (8,993 bytes as base64), decoded in JS.

## Rounding (decided 2026-11-18 — can be changed)

15,542 genes × 8,993 values ≈ 140 million numbers: ~560 MB as 32-bit floats.

**Stored rounded to 256 levels (one byte per vertex, ~140 MB, less compressed).** Per gene: store
that gene's min and max in the row, and each vertex as a byte `round((v - min) / (max - min) * 255)`.
The card's colour scale has far fewer than 256 visible steps, so the picture looks the same.

What it costs: a downloaded value is accurate to 1/255 of that gene's range, not the original. The
card says the values are rounded for display and links to GEO GSE173786 for the exact data.

**To change it:** the precision is a build option, written into the file's `meta` table
(`value_encoding`: `uint8` now; `uint16` or `float32` later), and the reader decodes from what the
file says. Changing it means re-running the build with a different option, not code changes.
`uint16` ≈ 280 MB, `float32` ≈ 560 MB.

## Order of work

1. Copy source into site data; build script + `lib/spatial_atlas.php` reader; check value range,
   gene count, and that every ID is in NV2.
2. Vendor plotly.js; the card (match the authors' app look), with the citation on the card.
3. Access kind `atlas`; tests in `tests/smoke_tests.php` (decode round trip, access fails closed).
4. EndoAtlas page: example links, then add to the Research menu.
5. Docs (`ORGANISM_SIDE_FILES.md` §4), and optionally the API field.
