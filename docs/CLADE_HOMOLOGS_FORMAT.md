# homologs.sqlite — clade comparative genomics (OrthoFinder), the format MOOP expects

**Status: format 1 live 2026-11-12 (`lib/clade_homologs.php`, the gene page's Comparative genomics
card). Format 2 (sequence evidence per ortholog pair, §Format 2) read by the site since 2026-11-12;
compute ships /2 files with `CLADE_FORMAT2_OK=1`.** A file without evidence is still written as /1 and
reads exactly as before.

One file per clade (one OrthoFinder run over MOOP gene sets) lets a gene page list its orthologs and
its whole gene family in that clade at once, each linked to its own gene page.

## Words — keep these apart (decided 2026-11-12)

A gene page already has **annotation** types that sound the same but mean something else. Every
place the clade data appears says what it is, how it was made, and what it is compared with:

| On the page | What it is | Method | Compared with |
|---|---|---|---|
| Annotations → **Orthologs** | OMA pairwise / groups / HOGs, eggNOG | published orthology resources | reference species **outside** the site |
| Annotations → **Homologs** | best sequence matches | BLAST / DIAMOND, one-way | Ensembl / UniProt proteomes **outside** the site |
| Annotations → **RBBH Homolog** | reciprocal best hits | MMseqs2 | Ensembl proteomes **outside** the site |
| **Comparative genomics** card → *Orthologs in <clade>* | OrthoFinder pairwise orthologs, with type 1:1 / 1:many / many:1 / many:many | gene trees | MOOP organisms **in the clade** |
| **Comparative genomics** card → *Gene family in <clade>* | the rest of the orthogroup / N0 HOG: orthologs **and paralogs** descended from one ancestral gene | gene trees | MOOP organisms **in the clade** |

- The clade data is **never** called plain "homologs" on a page (that word belongs to the
  similarity annotations), and an orthogroup is never called "orthologs" (it includes paralogs).
- It is its own card, not an annotation type, so it never mixes into the annotation tables.
- A HOG / orthogroup id in search or exports reads "gene family (OrthoFinder, <clade>)".
- The file name `homologs.sqlite` stays: visitors never see it, and compute already builds to it.
- The annotation types' descriptions (`metadata/annotation_config.json`) were rewritten to say
  method and scope the same way. Decision **B** of
`MOOP_INTEGRATION_BRIEF.md`: one shared table per clade, not per-organism annotation copies, so a
new run replaces one file and no `organism.sqlite` is rebuilt. Same pattern as `crosswalk.sqlite`
(`docs/CROSSWALK_FORMAT.md`).

## Where

```
organisms/.clades/<clade>/homologs.sqlite        e.g. organisms/.clades/planarians/homologs.sqlite
```

- `<clade>` = the run's folder name (`[A-Za-z0-9_-]`): `planarians`, `hexacorallia`, `edwardsiidae`,
  `ray_finned_fish`, later `bats`.
- The folder starts with a dot **on purpose**: everything that lists organisms skips dot-folders
  (`.retired`, `.cache`). A `_clades/` would show up as an organism.
- nginx already denies `organisms/` to the web; pages open the file read-only.
- **Who builds it:** compute (a script in `config/build_and_load_db/`); `copy2moop` ships it. Build to a
  temporary name and rename into place only when complete and `PRAGMA integrity_check` = `ok`.

## Schema

```sql
CREATE TABLE member (                 -- one row per member gene set (the run's members.tsv)
  organism     TEXT NOT NULL,         -- MOOP organism folder, e.g. Schmidtea_mediterranea
  assembly     TEXT NOT NULL,         -- MOOP assembly folder
  gene_set     TEXT NOT NULL,         -- MOOP gene set folder, e.g. schMedS3h1_WBPS19
  protein_md5  TEXT NOT NULL,         -- md5 of the protein FASTA AS MOOP SHIPS IT for that gene set
                                      -- (not of the one-protein-per-gene file derived from it) --
                                      -- the site can hash its own copy and flag a stale clade
  genes        INTEGER NOT NULL,      -- genes from this gene set in the run
  PRIMARY KEY (organism, gene_set)
);

CREATE TABLE gene (                   -- every gene in the run
  id           INTEGER PRIMARY KEY,
  organism     TEXT NOT NULL,
  gene_set     TEXT NOT NULL,         -- must be a member row
  gene         TEXT NOT NULL,         -- feature.feature_uniquename of the GENE (what parent.php takes)
  gene_lc      TEXT NOT NULL,         -- lower(gene)
  orthogroup   TEXT,                  -- OG0001234; NULL if unassigned
  protein      TEXT                   -- /2 only: the protein OrthoFinder and DIAMOND used for this gene
);
CREATE INDEX g_gene ON gene (gene_lc, organism);
CREATE INDEX g_og   ON gene (orthogroup);

CREATE TABLE hog (                    -- the gene's HOG at each node of the species tree
  gene_id      INTEGER NOT NULL,      -- gene.id
  node         TEXT NOT NULL,         -- N0 (whole clade), N1, ...
  hog          TEXT NOT NULL          -- N0.HOG0001234
);
CREATE INDEX h_gene ON hog (gene_id);
CREATE INDEX h_hog  ON hog (hog);

CREATE TABLE node (                   -- the species tree, so the page can say "Tricladida", not "N3"
  node         TEXT PRIMARY KEY,
  parent       TEXT,                  -- NULL for N0
  label        TEXT                   -- taxon name if known, else NULL
);

CREATE TABLE pair (                   -- OrthoFinder pairwise orthologs (Orthologues/)
  gene_a       INTEGER NOT NULL,      -- gene.id
  gene_b       INTEGER NOT NULL,      -- gene.id, a DIFFERENT organism
  kind         TEXT NOT NULL,         -- '1:1' | '1:many' | 'many:1' | 'many:many', read from gene_a's side,
                                      -- FROM PARTNER COUNTS (build-time assertion since compute dc5d9bb3)
  -- /2 only (§Format 2): sequence evidence for the pair
  support      TEXT NOT NULL,         -- 'reciprocal_best' | 'co_ortholog' | 'weak' | 'no_hit'
  evalue       REAL,                  -- best-scoring DIAMOND alignment of the two proteins, either direction;
  bitscore     REAL,                  --   all four NULL for no_hit
  pident       REAL,
  cov_a        REAL,                  -- fraction of gene_a's protein in that alignment (0-1)
  cov_b        REAL                   -- fraction of gene_b's protein
);
CREATE INDEX p_a ON pair (gene_a);
-- Store BOTH directions (a->b and b->a, kind mirrored) so a lookup is one indexed read.

CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT);
```

`meta` must hold:

| key | value |
|---|---|
| `format` | `clade_homologs/1` or `clade_homologs/2` — the site skips (and logs) any other value |
| `clade` | same as the folder name |
| `label` | display name, e.g. `Planarians (Tricladida)` |
| `taxon_id` | NCBI taxon of the clade, if any |
| `method`, `method_version` | e.g. `OrthoFinder` / `3.1.2`, or `FastOMA` / … — shown on the card. (Older files have only `orthofinder_version`; still read.) |
| `run_date`, `built_at` | ISO 8601 |
| `genes`, `orthogroups`, `hogs_n0`, `pairs` | counts (informational; the site checks they are > 0) |

Gene trees are not part of format 1 or 2 (they stay in `results_path` on compute). If wanted later: a
separate file per orthogroup beside the SQLite, named in a new table — format `clade_homologs/3`.

## Format 2: sequence evidence per ortholog pair (agreed with compute 2026-11-12)

OrthoFinder calls orthologs from gene trees; in big families it can pair genes that share little
sequence (PARP16 in Nematostella: 43 Scolanthus "orthologs", 1 with a real match). Format 2 adds a
DIAMOND check of every pair. **Nothing is deleted**: every pair stays, labelled, so the threshold can
change by a rebuild, not a rerun.

- `support` — "supported" = E ≤ `evidence_evalue` and the alignment covers ≥ `evidence_min_cov` of
  BOTH proteins; no identity cutoff.
  - `reciprocal_best` — supported, and each protein is the other's best hit in that species (ties count)
  - `co_ortholog` — supported, not reciprocal best (a duplicated copy matches better: still an ortholog)
  - `weak` — a hit, not supported
  - `no_hit` — no hit at E ≤ `evidence_search_evalue` in either direction
- Both directions stored, as before: the mirrored row swaps `cov_a`/`cov_b` and mirrors `kind`;
  `support`, `evalue`, `bitscore`, `pident` are the same in both rows.

Extra `meta` keys in /2:

| key | example |
|---|---|
| `evidence_tool`, `evidence_version`, `evidence_mode` | `DIAMOND`, `2.1.13`, `blastp --sensitive -k 25` |
| `evidence_search_evalue` | `1e-5` — the search cutoff; no hit at this E-value = `no_hit` |
| `evidence_evalue`, `evidence_min_cov` | the "supported" cut, e.g. `1e-5` (or `1e-10`), `0.5` |
| `evidence_rule` | one sentence explaining the labels — printed on the card as is |
| `results_path` | the full OrthoFinder Results folder on compute (trees, raw tables: not shipped) |
| `pairs_reciprocal_best`, `pairs_co_ortholog`, `pairs_weak`, `pairs_no_hit` | counts, both directions |

On the site: `reciprocal_best` and `co_ortholog` are listed as orthologs, with a badge (tooltip:
E-value, identity, coverage of each protein); `weak` and `no_hit` fold into "N without sequence
support" under each species. The download (gene page, Comparative genomics) adds `support`, `evalue`,
`bitscore`, `pident`, `gene1_coverage`, `gene2_coverage`. A /1 file has no badges and nothing folded.

## Other gene sets of a species — through the crosswalk (decided 2026-11-12)

Orthology is computed on **one gene set per species** (the `member` table), whatever the tool. Putting
several gene sets of one species into a run would be wrong: copies of the same gene would become each
other's "paralogs" and real 1:1 orthologs would turn into many:many.

Every OTHER gene set of a member organism reaches the card through that organism's
**`crosswalk.sqlite`** (RosettaStone; `docs/CROSSWALK_FORMAT.md`) — the same mapping the images and the
anatomy search use. Nothing extra goes in the clade file. Requirements:

- the organism has a `crosswalk.sqlite` on the site, and the clade's member gene set is one of its
  **hubs** (hub names are MOOP gene set names);
- the other gene sets are crosswalk **sources** configured with `gene_set` + `assembly` (site config
  `crosswalk[<organism>]['sources']`), so their ids can link to gene pages.

On the page: a gene of another gene set gets the card of the reference gene it maps to, with *"Orthologs
are computed on gene set X. This gene is the same gene as Y there (matched by sequence)."*, and every
gene page with the card shows *"The same gene in other gene sets: …"*. Unmapped genes: no card.
(A format with alias tables was specified and dropped the same day — one mapping, used everywhere,
is simpler. The name `clade_homologs/2` now means the evidence format above.)

## What the site will do with it

- **Gene-page card "Comparative genomics"** (built 2026-11-12), one part per clade (`meta.label`):
  *Orthologs in <label>* — a table, one row per OTHER organism: "Orthologs of this gene" from `pair`
  (1:1 first, then genes with a real symbol, then the rest; first 10 shown, kind badge each) and
  "Other family members (not orthologs of this gene)" — the rest of the N0 HOG in that species —
  expandable. Below it, "Paralogs in <this species>: N genes" (the page's own species' share of the
  HOG), expandable. Each gene: id (linked), symbol if it has a real one, short description (an
  id-only label is hidden). A note says the method and scope (N gene sets on this site). An 80-gene family across
  18 planarians reads as 18 short rows. Each gene links to its own gene page; the site builds the
  links (no URLs in the file).
- **Permissions:** a gene is listed, and counted, only if the visitor may see **its own gene set**
  (`moop_dataset_visible()` — organism, assembly, gene set). Clades mix many gene sets; any can be
  private.
- **Staleness check (housekeeping, hourly, report-only):** for each member, md5 of the site's protein
  FASTA vs `member.protein_md5`; plus a sample of member gene IDs looked up in that organism's
  `organism.sqlite` (an md5 match proves the file is the same, not that the IDs still exist).
  Either failing → the dashboard names the clade and the gene set.
- A gene in several clades (planarians ⊂ a larger flatworm run, say) gets one part per clade.

## The HOG-ID annotation (optional, compute side, unchanged loader)

So a whole group is searchable and appears in MOOPmart exports: one `*.moop.tsv` per member gene set,
Accession = the N0 HOG ID, **`## Annotation Accession URL:` left EMPTY**. A relative URL such as
`parent.php?organism=X&uniquename=` only works by accident — the browser resolves it against the
current page, so it works from `tools/` and breaks everywhere else — and an absolute one would bake
in `/moop/`, which changes when the site moves to its own address. Searching the HOG ID already
finds every member gene.

## Clades and organism groups

Independent. MOOP's groups (`metadata/organism_assembly_groups.json`) are editorial ("Bats", "Corals")
and the group/taxonomy check only suggests. A clade is defined by its run's member list; its display
name is `meta.label`.
