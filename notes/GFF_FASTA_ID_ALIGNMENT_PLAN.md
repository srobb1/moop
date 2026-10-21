# One ID per feature: one ID map per gene set, built once

**Status:** PLAN, third version 2026-10-21 (compute box, after reviewing the second version
against the pipeline code and the built data). **Reviewed by the web-host agent 2026-10-21:
agreed** ("the right design"); its answers and two additions are folded in below (see "Web-host
review"). Not started.

**Priorities, in order:** (1) the IDs in the database, genes.gff and the FASTAs are
identical; (2) the code that guarantees it is simple. Rewriting existing code is preferred
to adding another patch.

**History.** v1 (2026-10-20) renamed gene IDs to `LOC<GeneID>`; dropped — it is the only
part that changes database IDs (25 reloads, every gene-keyed join, URL redirects). v2
(2026-10-21 morning) made genes.gff follow the database with a normaliser in the
strip_id_prefix slot. Its diagnosis stands; this version keeps it and changes the cure: v2
fixed the GFF but kept the eight places that each decide what an ID is. This version
replaces them with one.

## The problem

For 28 gene sets the database's gene and mRNA IDs never appear in the gene set's own
genes.gff. The FASTAs match the database, so **the GFF is the odd one out**:

| | database / FASTA | genes.gff `ID=` |
|---|---|---|
| gene | `5505848` (NCBI GeneID) | `gene-LOC5505848` / `gene-ANAPC13` |
| mRNA | `XM_001626535.3` | `rna-XM_001626535.3` |
| CDS | `cds-XP_001626585.1` | `cds-XP_001626585.1` — already matches |
| protein | `XP_001626585.1` | (`protein_id=` on CDS lines) — matches |

Ensembl is the same shape: `gene:FBgn…` / `transcript:FBtr…` in the GFF, `FBgn…` /
`FBtr…` in the database.

Every reader of the GFF translates on its own, which is why the same bug keeps surfacing:
the Data Exporter's chr/start/stop are empty for these gene sets; the gene page and Primer
Maker carry an ID ladder (`lib/gff_gene_model.php`: rna-, gene:, GeneID); JBrowse search,
BLAST link-outs and `.fai` lookups each match their own way.

### Measured

2026-10-20 (web host), sample of 200 IDs per feature type per gene set, exact match of the
database uniquename against GFF `ID=` and the FASTA `.fai` names: 43 gene sets fine, 20 have
no GFF (transcriptome-only), and these have gene/mRNA in the GFF **0%**:

| Organism | Gene set | gene in GFF % | mRNA in GFF % | mRNA in FASTA % | protein in FASTA % |
|---|---|---|---|---|---|
| Amphimedon_queenslandica | RS_2026_04 | 0 | 0 | 100 | 100 |
| Artibeus_jamaicensis | RS_2023_03 | 0 | 0 | 100 | 100 |
| Bradyrhizobium_diazoefficiens | RS_2025_04_07 | 0 | – | – | 100 |
| Danio_rerio | 20260404 (Ensembl) — *added 2026-10-21, compute box* | 0 | 0 | 100 | 100 |
| Desmodus_rotundus | RS_2023_02 | 0 | 0 | 100 | 100 |
| Drosophila_melanogaster | BDGP6.54 (Ensembl) | 0 | 0 | 100 | 100 |
| Eptesicus_fuscus | RS_2023_03 | 0 | 0 | 100 | 100 |
| Hipposideros_armiger | RS_100 | 0 | 0 | 100 | 100 |
| Medicago_truncatula | MedtrA17_geneset | 0 | 0 | **0** | **0** |
| Miniopterus_natalensis | RS_100 | 0 | 0 | 100 | 100 |
| Molossus_molossus | RS_100 | 0 | 0 | 100 | 100 |
| Myotis_brandtii | RS_101 | 0 | 0 | 100 | 100 |
| Myotis_davidii | RS_101 | 0 | 0 | 100 | 100 |
| Myotis_lucifugus | RS_102 | 0 | 0 | 100 | 100 |
| Myotis_myotis | RS_100 | 0 | 0 | 100 | 100 |
| Nematostella_vectensis | RS_101 | 0 | 0 | 100 | 100 |
| Nothobranchius_furzeri | RS_2024_12 | 0 | 0 | 100 | 100 |
| Petromyzon_marinus | RS_2025_08 | 0 | 0 | 100 | 100 |
| Phyllostomus_discolor | RS_101 | 0 | 0 | 100 | 100 |
| Phyllostomus_hastatus | RS_100 | 0 | 0 | 100 | 100 |
| Pipistrellus_kuhlii | RS_101 | 0 | 0 | 100 | 100 |
| Pteronotus_mesoamericanus | RS_2024_03 | 0 | 0 | 100 | 100 |
| Pteropus_alecto | RS_102 | 0 | 0 | 100 | 100 |
| Pteropus_medius | RS_2025_12 | 0 | 0 | 100 | 100 |
| Pteropus_vampyrus | RS_101 | 0 | 0 | 100 | 100 |
| Rhinolophus_ferrumequinum | RS_2023_02 | 0 | 0 | 100 | 100 |
| Rousettus_aegyptiacus | Release-101 | 0 | 0 | 100 | 100 |
| Sturnira_hondurensis | Release-100.20210611 | 0 | 0 | 100 | 100 |

The FASTA columns were measured database → FASTA only. The other direction is not 100%:
Danio's protein FASTA has 150 proteins that are not in the database (below).

**Out of scope here:** Medicago_truncatula matches nothing, FASTA included — the same
problem as its orphan annotations, handled separately. Schmidtea_lugubris schLug1 protein
FASTA 0% — separate check.

## Findings

From the 2026-10-21 code read on the web host (v2) and the compute-box review of the
pipeline and the built data in `moop/build_and_load_db/v2/data` (this version).

1. **The database IDs are the right IDs.** They match the FASTAs everywhere above except
   Medicago. Nothing changes in the database except the fixes listed under "Edge cases".

2. **CDS IDs keep their prefix.** The database holds both a CDS (`cds-XP_…`, `CDS:FBpp…`)
   and a protein (`XP_…`, `FBpp…`); stripping the CDS prefix collides every CDS with its
   protein (FlyBase: 30,802 collisions).

3. **Today an ID is decided in about eight places, each with its own source rules:**
   - `analysis_parsers/parse_GFF3_to_MOOP_TSV.pl`: `emit_refseq`, `emit_ensembl`,
     `emit_generic`, and the coordinate writers `_coords_refseq`, `_coords_ensembl`,
     `_coords_generic`; plus `detect_format`, a copy of the shell's own format detector
     ("must be kept in sync");
   - `scripts/rename_RefSeq_cds_fasta.pl` (315 lines), `rename_Ensembl_cds_fasta.pl`,
     `rename_generic_fasta.pl`, `strip_id_prefix.pl` (strip/add and lift prefixes),
     `add_id_versions.pl` (Ensembl `.version`);
   - `data_loaders/load_annotations_sqlite.pl`: reads the `.id_prefix_stripped` manifest and
     tries `:pep`, `:cds` and the re-prefixed form when an annotation ID does not match;
   - web: `generateFeatureCoordsIndex()` (strips `rna-|cds-|gene-|id-`, writes both forms),
     the ID ladder in `lib/gff_gene_model.php`.

   None of the emitters reads `ID=`/`Parent=` for gene or mRNA — `emit_refseq` takes
   `GeneID:` and `transcript_id=` and hangs each CDS on the last mRNA line it saw — which
   is how the GFF and the database came apart without anyone noticing.

4. **Two coordinate producers; which one wins depends on file times.** The pipeline writes
   `feature_coords.tsv` keyed by database IDs (shipped by `copy2moop_v2.sh:360`); JBrowse
   registration rebuilds it from genes.gff, keyed by GFF IDs, whenever genes.gff is newer
   than it. Both give every row the **gene's** span — that is the file's contract
   (`lib/genomic_regions.php`; a transcript's own span comes from `exon_coords.tsv`), so it
   is not a difference between them. The pipeline version is three source-specific writers
   covering coding features only; the web version is one generic reader of the final GFF.

5. **The two ID spaces are already real, just not named.** The datastore and every analysis
   (DIAMOND, MMseqs2, InterProScan, OMA, naming) use the IDs as delivered — after
   `add_id_versions.pl` for Ensembl. MOOP's shipped files use MOOP IDs. Verified on
   Bradypodion_ventrale: `isoforms.tsv` and `geneNames.tsv` carry
   `Bradypodion_ventrale_…`, while genes.gff, the FASTAs, features.tsv and the database carry
   `BraVen_…`; the annotation files carry the long form and reach the features only through
   the loader's manifest. (So the `process_one_geneset.sh` comment at ~1092, which says
   isoforms.tsv and geneNames.tsv "inherit the shortened IDs", is stale.)

6. **What genes.gff holds that is not loaded.** Non-coding genes (Nematostella: 37,996 genes
   in the GFF, 19,231 in the database: tRNA, rRNA, lncRNA, snRNA, pseudogenes) and, in the
   RefSeq bats and Nothobranchius, immunoglobulin/TCR V/C gene segments whose CDS lines
   carry no `protein_id`. Any check of "GFF == database" has to be over protein-coding
   features only.

7. **Edge cases found** (scan of the 24 RefSeq/Ensembl gene sets with a build on the compute
   box, 2026-10-21):
   - **Amphimedon *Gro*** (GeneID 100616087), placed twice by NCBI: `gene-Gro` /
     `gene-Gro-2`, `rna-NM_001279307.1` / `rna-NM_001279307.1-2`, `cds-NP_001266236.1` /
     `cds-NP_001266236.1-2`. The database has one gene and one mRNA with both CDSs under it.
     The CDS FASTA has both copies; the transcript and protein FASTAs have **one** record
     each (`NM_001279307.1`, `NP_001266236.1`). `isoforms.tsv` already lists
     `NM_001279307.1-2` under 100616087. The only protein-coding case in all RefSeq GFFs.
   - **Mitochondrial mRNAs without `transcript_id=`**: 13 in Amphimedon, 13 in Petromyzon
     (`rna-KEG45_p08`, COX2, …). The database skips the mRNA and hangs the CDS on the gene;
     the transcript FASTA has no record. The gene page draws the GFF's mRNA, which the
     database does not have. (`1a5a488` — CDS directly under a gene with no transcript child
     — is the bacterial shape and does not cover this: here the GFF has the mRNA.)
   - **Danio immune gene segments**: 150 CDS with a `protein_id` under C/D/J/V gene segments.
     The proteins are in the protein FASTA but not in the database, because `emit_ensembl`
     follows only `mRNA` parents; their annotations are dropped.
   - **CDS type spelling**: RefSeq gene sets store `cds`, Ensembl `CDS`
     (`lib/extract_search_helpers.php` accepts both).
   - **Repeat rows**: `emit_refseq` writes the mRNA/CDS rows once per CDS exon line
     (Nematostella features.tsv: 760,563 rows, 116,341 unique). The loader dedupes them.
   - **Bradyrhizobium**: CDS directly under the gene (no mRNA), and 230 pseudogene CDS with
     no `protein_id`.
   - **One `protein_id` under several CDS IDs**: 24 in Bradyrhizobium (separable by
     `locus_tag`) and Amphimedon's *Gro* (separable only by coordinates). Most of
     `rename_RefSeq_cds_fasta.pl` exists for these.

## The design: one ID map per gene set

### The boundary

```
datastore + analyses            id_map.tsv            what MOOP ships
(IDs as delivered)      ──── crossed once ────>   (MOOP IDs: genes.gff, FASTAs,
naming, DIAMOND, IPS, OMA                          features.tsv, coords, database)
```

Everything that runs on the delivered sequences stays in delivered IDs, as it does today.
Everything MOOP ships is in MOOP IDs. The map is the only bridge, and it is crossed in one
step at the end of the gene-set build.

### `id_map.tsv`

One row per feature (gene, mRNA/transcript, CDS, protein):

```
moop_id   type   parent_moop_id   original_gff_id   original_fasta_id
```

- `original_gff_id`: the `ID=` in the delivered GFF (empty for a protein, and for a CDS line
  with no `ID=`).
- `original_fasta_id`: the header in the delivered FASTA of that type (transcript FASTA for
  an mRNA, CDS FASTA for a CDS, protein FASTA for a protein; empty when that FASTA has no
  record — see "Missing sequences").

### Pieces

1. **`build_id_map.pl`** (new) — **the only code that knows RefSeq, Ensembl, other, lift and
   the prefix keys.** Reads the delivered GFF and FASTA headers (transcript2gene.txt for
   transcriptome sets), writes `id_map.tsv`. It takes the source from the shell's existing
   detector as an argument; `detect_format` is deleted.

2. **`apply_id_map_gff.pl`** (new, generic) — writes MOOP's genes.gff: `ID`, every `Parent`
   and `protein_id` replaced through the map, the original ID kept as `Alias=`; CDS lines
   that had no `ID=` get the map's one. Features not in the map (non-coding genes, V/C
   segments) are left exactly as delivered.

3. **`apply_id_map_fasta.pl`** (new, generic) — rewrites each FASTA's headers
   `original_fasta_id → moop_id`, keeping the description. Replaces
   `rename_RefSeq_cds_fasta.pl`, `rename_Ensembl_cds_fasta.pl`, `rename_generic_fasta.pl`
   and the FASTA half of `strip_id_prefix.pl`. The name step (`updateFASTA.pl`) is
   unchanged and runs after it.

4. **features.tsv = the map plus names.** One small generic writer: map rows, plus the name
   and description from geneNames.tsv looked up by the gene's original ID. Replaces
   `emit_refseq`, `emit_ensembl`, `emit_generic` and `load_gene_names`.

5. **The loader joins annotations through the map.** Annotation files stay in delivered IDs;
   `load_annotations_sqlite.pl` reads `id_map.tsv` and looks each annotation ID up as
   `original_fasta_id` (then `original_gff_id`) exactly. The `.id_prefix_stripped` manifest
   and the `:pep`/`:cds`/re-prefix candidate guessing are deleted. **The load fails loudly**
   when an annotation file's IDs match nothing in the map (beyond a listed exception) — the
   riskiest piece: a rename once dropped 21,199 Bipalium_vagum annotations silently, and today
   Medicago and Turritopsis have 0 linked annotations with no complaint.

6. **One coordinates producer: the web's.** With MOOP IDs in genes.gff, the web's generic
   reader is right as written minus its prefix stripping. The pipeline's `_coords_*` are
   deleted and copy2moop stops shipping `feature_coords.tsv`.

7. **The gate** (new), run after features.tsv, fails the gene set on any difference and
   prints a sample:
   - every map row's `moop_id` is an `ID=` in MOOP's genes.gff (protein: a `protein_id=`),
     and every coding gene/mRNA/CDS in genes.gff is a map row;
   - every protein row has a protein FASTA record; every mRNA and CDS row has a transcript /
     CDS FASTA record, or is on the missing-sequence list with its reason;
   - every FASTA header is a map row (nothing in a FASTA that the database lacks — this is
     what catches Danio's 150);
   - features.tsv's IDs and parents equal the map's; every `Parent=` resolves; no ID is
     used by two features (a multi-line CDS sharing one ID is fine).

   It is also a housekeeping health card on the web, so a misaligned gene set shows on the
   dashboard. It runs on transcriptome-only sets too from the start (cheap, and it would have
   caught the gene-less sets), even though their map builder is phase 2.

### ID rules (inside `build_id_map.pl` only)

A feature is in the map when it is a gene with at least one CDS carrying `protein_id`, or
the mRNA/transcript, CDS or protein under it. V/C gene segments and pseudogene CDS without a
`protein_id` are skipped by that rule; nothing names them.

| source | gene | mRNA / transcript | CDS | protein |
|---|---|---|---|---|
| RefSeq | `GeneID:` from `Dbxref`, plus NCBI's `-N` if the original ID ends in one | `ID` minus `rna-` | `ID` unchanged (`cds-XP_…`) | `protein_id` |
| RefSeq prokaryote (no mRNA) | as above | – (CDS under the gene) | `ID` unchanged (`cds-WP_…`) | `protein_id` |
| Ensembl | `ID` minus `gene:`, with `.version` | `ID` minus `transcript:`, with `.version` | `ID` with `.version` (`CDS:ENSDARP….6`) | `protein_id` with `.version` |
| other | `ID` (after `moop-strip-id-prefix` / `moop-add-id-prefix`) | `ID` (same) | `<tx>:cds` | `<tx>:pep` |
| lift | as other, with `moop-lift-prefix` | as other | as other | as other |

Ensembl `.version` is applied only where `add_id_versions.pl` applies it today (every
protein FASTA id is `protein_id.version`); the rule moves into the map builder and the
script is deleted. The other/lift CDS and protein IDs are today's synthesised ones, so
nothing changes for them in the database; they are now also written into genes.gff (piece
2), which gives them coordinates.

These rules reproduce today's database IDs exactly. Step 1 below proves it.

### Edge cases — decided once, here

- **The database mirrors the GFF's structure.** Mitochondrial genes get their mRNA
  (`KEG45_p08`, 13 each in Amphimedon and Petromyzon); Amphimedon's *Gro* gets its second
  gene `100616087-2` and mRNA `NM_001279307.1-2`, with `cds-NP_001266236.1-2` under it.
  Danio gets its 150 immune proteins (gene → segment as mRNA → CDS → protein).
- **A `protein_id` under two CDSs belongs to the first.** *Gro*'s `NP_001266236.1` hangs
  under `cds-NP_001266236.1`; copy 2's CDS has no protein row. The protein FASTA has one
  record and the annotations attach to it, as today.
- **Missing sequences** are listed in the map run's log with a reason, not silently allowed:
  "source transcript FASTA has no record" (the 26 mitochondrial mRNAs, `NM_001279307.1-2`).
  A missing sequence without a listed reason fails the gate.
- **CDS type** stays as stored today (`cds` for RefSeq, `CDS` for Ensembl), so it is not a
  database change. Unifying it is a separate decision.

### What is NOT done (and why)

- **No `LOC<GeneID>`**, no database ID changes, no alias-redirect work on the web. Every
  existing URL keeps working.
- **No FASTAs built from the GFF** (gffread -w/-x/-y). It would make the headers match by
  construction, but the sequences would no longer be the ones every analysis ran on
  (RefSeq mRNAs corrected against the genome, selenoproteins, partial codons).
- **No mass reload.** Only gene sets where a fix adds features are reloaded (Amphimedon,
  Petromyzon, Danio); the rest need a new genes.gff, FASTAs (same headers as today), and
  JBrowse re-registration.

## Steps

1. **Prove on existing data, before touching the pipeline.** Write `build_id_map.pl` and run
   it on every gene set with a build. Compare its (moop_id, type, parent) rows with today's
   organism.sqlite: **identical**, except the listed fixes (*Gro*, the mitochondrial mRNAs,
   Danio's 150). Any other difference is a bug in the rules or a case this plan missed.
2. **Appliers + gate on copies.** Run the two appliers, the features.tsv writer and the gate
   on copies of Nematostella RS_101, Amphimedon RS_2026_04, Danio 20260404, Bradyrhizobium
   RS_2025_04_07, Bradypodion_ventrale (prefix) and one other/lift set. The gate must pass;
   the rewritten FASTAs must equal today's built ones byte for byte except the listed fixes.
3. **Loader join** through the map, on the same copies: annotation counts per source equal
   today's, plus Danio's 150 proteins' annotations.

   **Then steps 1–3 for every gene set, not only these six** (web-host review): the change
   touches FASTA renaming and the annotation join for all ~92 gene sets, not just the 28
   misaligned ones. No rollout until every gene set passes the ID comparison, the byte-for-byte
   FASTA comparison and the per-source annotation counts.
4. **Wire into `process_one_geneset.sh`**, replacing the strip_id_prefix / lift / rename
   block (~1083–1150) and the features.tsv call. Run Nematostella end to end; ship.
5. **Web, Nematostella:** first change the JBrowse text-index default from `Name,ID` to
   `Name,ID,Alias` (`admin/api/jbrowse_register_gene_set.php:42`, `jbrowse_text_index.php:29`,
   `jbrowse_reprep_gff.php:30`, `admin/pages/manage_jbrowse.php:535`) — without it the old IDs
   kept as `Alias=` are not searchable. Then re-register JBrowse; check the gene page, Data
   Exporter coordinates (must now fill), JBrowse search by ID and by an old `gene-LOC…` alias,
   BLAST link-outs, search results.
6. **Roll out** to every gene set (Amphimedon, Petromyzon, Danio as reloads).
7. **Delete**, once the gate is clean everywhere: the three emitters, the three coords
   writers, `detect_format`, the four FASTA renamers, `strip_id_prefix.pl`,
   `add_id_versions.pl`, the loader's manifest and candidate guessing, copy2moop's
   feature_coords.tsv, the `lib/gff_gene_model.php` ID ladder, and every web prefix strip:
   `generateFeatureCoordsIndex()` and `generateExonCoordsIndex()` (`gene_set_functions.php`
   ~598, which also writes stripped duplicate rows), `lib/gene_isoforms.php` (3 places),
   `tools/primer_maker.php` ~239, and the ID matching in `api/get_gff.php` (~60).

## Web-host review (2026-10-21)

Verdict: the right design, simpler than v2 where it matters (one script holds every source
rule). Answers to the four questions:

1. **Does anything on the web need the `gene-`/`rna-` prefixes?** No code needs them: every web
   use strips a prefix if present, so all of it becomes dead code (list added to step 7). But
   JBrowse's text index covers `Name,ID` only, so the old IDs kept as `Alias=` are searchable
   only once the default becomes `Name,ID,Alias` — a required web change, now in step 5.
2. **Keep the web's coordinates writer?** Yes: one generic reader; covering non-coding features
   does no harm (lookups are by ID). Its prefix stripping and duplicate rows go once IDs align.
3. **Transcriptome path?** Phase 2 — no misalignment there today. But run the gate on
   transcriptome sets now (added to piece 7).
4. **Ship `id_map.tsv` to the web?** Not now: database IDs do not change, so every MOOP URL keeps
   working, and the old GFF IDs never appeared in MOOP URLs. `Alias=` plus the index change
   covers JBrowse.

Two additions, folded in: the loader fails loudly on annotation IDs that match nothing (piece
5), and steps 1–3 are required for every gene set before rollout, not only the six copies
(step 3).

## Touch points

Pipeline: `scripts/process_one_geneset.sh` (~980–1185), `analysis_parsers/parse_GFF3_to_MOOP_TSV.pl`,
`scripts/strip_id_prefix.pl`, `scripts/add_id_versions.pl`, `scripts/rename_RefSeq_cds_fasta.pl`,
`scripts/rename_Ensembl_cds_fasta.pl`, `scripts/rename_generic_fasta.pl`,
`data_loaders/load_annotations_sqlite.pl`, `scripts/copy2moop_v2.sh:360`.
Web: `lib/jbrowse/gene_set_functions.php` (`generateFeatureCoordsIndex`,
`generateExonCoordsIndex`), `lib/gff_gene_model.php`, `lib/gene_isoforms.php`,
`tools/primer_maker.php`, `api/get_gff.php`, `lib/moopmart_functions.php` (coords),
`lib/housekeeping.php` (health card), the JBrowse text-index default
(`admin/api/jbrowse_register_gene_set.php`, `jbrowse_text_index.php`, `jbrowse_reprep_gff.php`,
`admin/pages/manage_jbrowse.php`).
