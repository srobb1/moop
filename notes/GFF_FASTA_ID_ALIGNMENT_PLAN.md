# One ID per feature: make genes.gff agree with the database and the FASTAs

**Status:** PLAN, rewritten 2026-10-21 — **for review by the compute-box agent** (it knows
the pipeline history and can run the Perl). Not started. The 2026-10-20 version proposed
renaming gene IDs to `LOC<GeneID>`; this version drops that and is much smaller — see
"What changed from the first version".

**Priorities, in order:** (1) the IDs in the database, genes.gff and the FASTAs are
identical; (2) the code that guarantees it is simple. Rewriting existing code is allowed
where it makes (2) better.

## The problem

For 26 gene sets the database's feature IDs never appear in the gene set's own genes.gff.
The FASTAs match the database 100%, so **the GFF is the odd one out**:

| | database / FASTA | genes.gff `ID=` |
|---|---|---|
| gene | `5505848` (NCBI GeneID) | `gene-LOC5505848` / `gene-ANAPC13` |
| mRNA | `XM_001626535.3` | `rna-XM_001626535.3` |
| CDS | `cds-XP_001626585.1` | `cds-XP_001626585.1` — **already matches** |
| protein | `XP_001626585.1` | (no GFF feature; `protein_id=` attribute on CDS lines) — matches |

Only **gene** and **mRNA** are misaligned. Ensembl (Drosophila) is the same shape:
`gene:FBgn…` / `transcript:FBtr…` in the GFF, `FBgn…` / `FBtr…` in the database.

Every reader of the GFF translates on its own, which is why the same bug keeps surfacing:
the Data Exporter's chr/start/stop are empty for these gene sets; the gene page and Primer
Maker carry an ID ladder (`lib/gff_gene_model.php`: rna-, gene:, GeneID); JBrowse search,
BLAST link-outs and `.fai` lookups each match their own way.

### Measured 2026-10-20

Sample of 200 IDs per feature type per gene set, exact match of the database uniquename
against GFF `ID=` and the FASTA `.fai` names (`organisms/*/…/genes.gff`, `*.fa.fai`):

- **43** gene sets: database IDs found in the GFF exactly. Fine.
- **20**: no GFF (transcriptome-only). Not affected.
- **27**: database gene/mRNA IDs found in the GFF **0%**:

| Organism | Gene set | gene in GFF % | mRNA in GFF % | mRNA in FASTA % | protein in FASTA % |
|---|---|---|---|---|---|
| Amphimedon_queenslandica | RS_2026_04 | 0 | 0 | 100 | 100 |
| Artibeus_jamaicensis | RS_2023_03 | 0 | 0 | 100 | 100 |
| Bradyrhizobium_diazoefficiens | RS_2025_04_07 | 0 | – | – | 100 |
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

**Out of scope here:** Medicago_truncatula matches nothing, FASTA included — the same
problem as its orphan annotations (`bug_orphan_annotations_three_dbs`), handle separately.
Schmidtea_lugubris schLug1 protein FASTA 0% — separate check.

## Findings 2026-10-21 (code read on the web host)

1. **The database IDs are already the right IDs.** They match the FASTAs everywhere above
   (except Medicago). Nothing needs to change in the database; the GFF has to follow it.

2. **CDS IDs must keep their prefix.** The database holds both a CDS (`cds-XP_…`,
   `CDS:FBpp…`) and a protein (`XP_…`, `FBpp…`). Stripping the CDS prefix collides every
   CDS with its protein — for RefSeq eukaryotes as well as bacteria (`cds-WP_…`/`WP_…`),
   and FlyBase (30,802 collisions, see `project_fasta_names_and_fai`). The CDS already
   matches across GFF, FASTA and database, so it is simply left alone.

3. **Today's IDs are invented in about eight places, each with its own source rules:**
   - pipeline: `emit_refseq`, `emit_ensembl`, `emit_generic` and `_coords_refseq`,
     `_coords_ensembl`, `_coords_generic` in `analysis_parsers/parse_GFF3_to_MOOP_TSV.pl`;
     `rename_RefSeq_cds_fasta.pl` (315 lines), `rename_Ensembl_cds_fasta.pl`,
     `rename_generic_fasta.pl`;
   - web: `generateFeatureCoordsIndex()` in `lib/jbrowse/gene_set_functions.php` (strips
     `rna-|cds-|gene-|id-` and writes both forms), the ID ladder in `lib/gff_gene_model.php`.

   `emit_refseq` takes the gene ID from `Dbxref=GeneID:`, the mRNA ID from
   `transcript_id=`; `emit_ensembl` strips `gene:` / `transcript:` and *builds*
   `CDS:$protein_id` rather than reading it. None of them reads `ID=`/`Parent=` for gene or
   mRNA, which is how the GFF and the database came apart without anyone noticing.

4. **Two producers of `feature_coords.tsv`, and the wrong one wins.** The pipeline writes it
   keyed by database IDs (`_coords_*`, shipped by `copy2moop_v2.sh:360`). JBrowse
   registration on the web then rebuilds it from genes.gff, keyed by GFF IDs, and overwrites
   it. That overwrite is the empty-coordinates bug in the Data Exporter
   (`bug_moopmart_coords_geneid`).

5. **A real merge bug, in the database today (Amphimedon).** NCBI places *Gro*
   (GeneID 100616087) twice: `gene-Gro` and `gene-Gro-2`, mRNAs `rna-NM_001279307.1` and
   `rna-NM_001279307.1-2`. `emit_refseq` reads `GeneID:` and `transcript_id=`, neither of
   which carries NCBI's `-2`, so the database has **one** gene and **one** mRNA, with the
   second copy's CDS (`cds-NP_001266236.1-2`) hanging under the first copy's mRNA.
   Measured over all 26 RefSeq GFFs: this is the only protein-coding case. Myotis
   brandtii/davidii reuse a GeneID 47-48 times, but all on tRNA genes, which are not loaded.

6. **Where IDs can safely be rewritten.** `isoforms.tsv` and `geneNames.tsv` are built from
   the ORIGINAL GFF (`$SRC_GFF`, process_one_geneset.sh ~1020-1050) and are keyed by the
   same GeneID / XM / XP the database uses. `strip_id_prefix.pl` already rewrites
   `genes.gff` + FASTAs in MOOP's working copy at ~1104, after those and before
   `features.tsv` / `genes.gtf` / indexes. That is the slot for this change, and with the
   canonical IDs = today's database IDs, nothing built upstream of it is affected.
   (The `strip_id_prefix` comment at ~1092 says geneNames.tsv and isoforms.tsv "inherit"
   the stripped IDs, but both are built earlier from `$SRC_GFF` — **reviewer: please check**,
   see Questions.)

## The design: IDs are decided once, then copied

### The contract

After one normalising step, MOOP's working `genes.gff` satisfies:

- every gene, mRNA/transcript and CDS line's `ID=` **is** its MOOP ID, and `Parent=` uses
  MOOP IDs;
- every CDS line carries `protein_id=`, and that **is** the protein's MOOP ID;
- the transcript / CDS / protein FASTA headers are exactly those IDs;
- each original ID that changed is kept as `Alias=` (GFF3 reserved attribute; percent-
  encode `,` `;` `=` if any).

Everything downstream reads those literally. **Knowledge of RefSeq vs Ensembl vs other lives
in exactly one script.**

### ID rules (the only source-specific code)

| source | gene | mRNA / transcript | CDS | protein |
|---|---|---|---|---|
| RefSeq | GeneID from `Dbxref=GeneID:`, **plus NCBI's `-N` if the original ID ended in one** (`gene-Gro-2` → `100616087-2`) | `ID` minus `rna-` (keeps `-N`) | unchanged (`cds-XP_…`) | `protein_id=` (unchanged) |
| RefSeq prokaryote (no mRNA) | as above | – | unchanged (`cds-WP_…`) | `protein_id=` |
| Ensembl | `ID` minus `gene:` | `ID` minus `transcript:` | unchanged (`CDS:FBpp…`) | `protein_id=` |
| other / lift | unchanged (today's `strip_id_prefix.pl` / lift prefix still apply) | unchanged | unchanged | unchanged |

These reproduce today's database IDs exactly — except Amphimedon's second *Gro* copy, which
gains the gene and mRNA it should always have had.

### Pieces

1. **`normalise_ids.pl`** (new, ~100 lines) in the strip_id_prefix slot, RefSeq and Ensembl
   only. Builds an old→new map from gene/mRNA lines, rewrites `ID` and every `Parent` in
   genes.gff, adds `Alias=`. FASTAs need no change for these sources (they already match).
   Refuses if two features would get the same ID or a `Parent` would point nowhere.

2. **One generic emitter** in `parse_GFF3_to_MOOP_TSV.pl`: gene = `ID`, mRNA = `ID` +
   `Parent`, CDS = `ID` + `Parent`, protein = `protein_id`. Replaces `emit_refseq` and
   `emit_ensembl`. (Whether it can also replace `emit_generic` is a Question below.)

3. **One coordinates producer.** Once GFF IDs are database IDs, the web's
   `generateFeatureCoordsIndex()` is correct as written, minus its prefix stripping. Delete
   the pipeline's `_coords_*` writers and stop shipping `feature_coords.tsv` from
   copy2moop — or the reverse; the point is ONE (reviewer's call, see Questions).

4. **The gate** (new, ~50 lines), run after `features.tsv`, fails the gene set on any
   difference, printing a sample:
   - set of `features.tsv` gene/mRNA/CDS IDs **==** set of genes.gff gene/mRNA/CDS `ID`s
     (both directions — "==" not "⊆" is what catches a merge like Amphimedon's);
   - every `features.tsv` mRNA ID is a transcript FASTA header, CDS ID a CDS FASTA header,
     protein ID a protein FASTA header — and vice versa;
   - every `Parent=` resolves; no ID used by two features (same-ID multi-line CDS is fine).

   This is the 2026-10-20 measurement made strict, and it is what keeps the result true
   when a new download arrives.

### What is NOT done (and why)

- **No `LOC<GeneID>`.** The first version wanted it for readability. It is the only part
  that changes database IDs, and it would cost a reload of 25 databases, a check of every
  file joined on gene ID (geneNames.tsv, isoforms.tsv, naming output), and alias redirects
  for old URLs. Display already shows the symbol; NCBI links use the GeneID.
- **No alias-redirect work on the web** — no database ID changes, so every existing URL
  keeps working.
- **No database reload** except Amphimedon. The other 24 RefSeq/Ensembl gene sets need a
  new genes.gff (+ genes.gtf) shipped and JBrowse re-registered.

## Steps

1. **Prove on existing data, before touching the pipeline.** Run `normalise_ids.pl` and the
   generic emitter on copies of Nematostella RS_101, Amphimedon RS_2026_04, Drosophila
   BDGP6.54 and Bradyrhizobium RS_2025_04_07. Compare the emitted (uniquename, type, parent)
   rows with today's features.tsv / organism.sqlite: **identical** for all four except the
   Amphimedon *Gro* fix. Then all RefSeq/Ensembl gene sets.
2. **Wire into process_one_geneset.sh**: normaliser in the strip_id_prefix slot, generic
   emitter for refseq/ensembl, gate after features.tsv. Run Nematostella end to end; ship.
3. **Web, Nematostella:** re-register JBrowse; check the gene page, Data Exporter
   coordinates (must now fill), JBrowse search by ID and by an old `gene-LOC…` alias, BLAST
   link-outs, search results.
4. **Roll out** to the other RefSeq/Ensembl gene sets (Amphimedon is a reload).
5. **Delete**, each only once the gate is clean everywhere: `emit_refseq`, `emit_ensembl`,
   the losing coords producer, the web prefix stripping, the `lib/gff_gene_model.php` ID
   ladder. Add the same exact-match % to a housekeeping health card so a misaligned gene
   set shows on the dashboard.

## Questions for the compute-box reviewer

1. Is the strip_id_prefix slot (~1104) really after everything that reads `rna-`/`gene-`/
   `gene:` patterns (make_isoforms_from_gff.pl, get_names_from_gff.pl, RBBH isoform builder,
   updateGFF.pl, addClosestToGFF.pl)? Anything after it that still does?
2. The comment at ~1092 says isoforms.tsv / geneNames.tsv inherit the stripped IDs, yet both
   appear to be built earlier from `$SRC_GFF`. Is the comment stale, or is there a path
   I missed? (Matters for Bradypodion-style prefix-stripped sets.)
3. Can the generic emitter also replace `emit_generic` for "other"/lift sources? That needs
   every CDS line to have an `ID` and `protein_id` — today emit_generic falls back to the
   FASTAs and synthesises `<tx>:cds` / `<tx>:pep` (some GFFs have CDS lines with no `ID`,
   e.g. Schmidtea nova). Simplest might be: the normaliser fills those two attributes for
   other/lift, so the emitter never consults a FASTA. Worth it now, or a phase 2?
4. Does `rename_RefSeq_cds_fasta.pl` (315 lines) become unnecessary if the normaliser maps
   CDS FASTA headers (`lcl|…_cds_XP_…_1`) to the CDS `ID` from the same old→new pass?
5. Which coordinates producer to keep — pipeline (ships with the data) or web (rebuilt at
   registration)? Note the pipeline's `_coords_refseq` gives mRNA/CDS rows the **gene's**
   span, not their own.
6. Anything gene-ID-keyed downstream (naming output, expression bundles, BUSCO) that would
   notice Amphimedon's new `100616087-2` gene?

## Touch points

Pipeline: `scripts/process_one_geneset.sh` (~1020-1180), `scripts/strip_id_prefix.pl`
(pattern and slot), `analysis_parsers/parse_GFF3_to_MOOP_TSV.pl` (emitters + coords),
`scripts/rename_RefSeq_cds_fasta.pl`, `scripts/rename_Ensembl_cds_fasta.pl`,
`scripts/copy2moop_v2.sh:360`. Web: `lib/jbrowse/gene_set_functions.php`
(`generateFeatureCoordsIndex`), `lib/gff_gene_model.php`, `lib/moopmart_functions.php`
(coords), `lib/housekeeping.php` (health card).
