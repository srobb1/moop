# One ID per feature: align GFF, FASTA and database IDs (RefSeq, Ensembl, bacteria)

**Status:** PLAN, not started (written 2026-10-20). Most of the work is in the loading
pipeline (`config/build_and_load_db/`, run on the compute box); the web side follows.

## The problem

For 26 gene sets the database's feature IDs never appear in the gene set's own GFF. The
FASTAs match the database 100%, so **the GFF is the odd one out**:

| | database / FASTA | MOOP's genes.gff `ID=` |
|---|---|---|
| gene | `5505848` (NCBI GeneID) | `gene-LOC5505848` (or `gene-ANAPC13`) |
| mRNA | `XM_001626535.3` | `rna-XM_001626535.3` |
| protein / CDS | `XP_001626585.1` | `cds-XP_001626585.1` |

Every reader of the GFF therefore has to translate, and each does it its own way or not at
all — which is why the same bug keeps surfacing in new places:

- the gene page and Primer Maker: an ID ladder in `lib/gff_gene_model.php` (rna-, gene:, GeneID);
- `feature_coords.tsv` is keyed by GFF ID, so the **Data Exporter's chr/start/stop/strand
  are empty** for these gene sets and its coordinate filter misses (found 2026-10-20);
- JBrowse text search, BLAST link-outs, the `.fai` name lookups — each with its own matching.

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

Also: Schmidtea_lugubris schLug1 — protein FASTA 0% (genes match the GFF); Schmidtea_nova
— matches. Medicago_truncatula matches **nothing**, FASTA included — a different problem,
the same one behind its orphan annotations (`bug_orphan_annotations_three_dbs`); handle it
separately.

## Principles

1. **One ID per feature, identical everywhere** — database uniquename, GFF `ID=`, FASTA
   header, `feature_coords.tsv`, JBrowse, URLs. Not negotiable: it is the bug.
2. **The ID is unique and stable across annotation releases.** Human-friendliness comes from
   what is displayed (symbol, name) and from searchable aliases.
3. **Original data stays original.** Only MOOP's working copy of a gene set (`genes.gff`
   and the FASTAs in `organisms/`) is rewritten; the `$GENOMES` tree keeps the depositor's
   IDs (`feedback_original_data_stays_original`).
4. **Nothing is lost:** every original ID is kept as a GFF3 `Alias=`.

## ID rules

**RefSeq**

| feature | ID | from | Alias kept |
|---|---|---|---|
| gene | `LOC<GeneID>` — e.g. `LOC5505848`, also for genes with a symbol | `Dbxref=GeneID:` | `gene-LOC5505848` / `gene-ANAPC13`, the bare GeneID |
| mRNA / transcript | the accession: `XM_001626535.3` | `ID` minus `rna-` | `rna-XM_001626535.3` |
| protein | the accession: `XP_001626585.1` | `ID` minus `cds-` (eukaryotes) | `cds-XP_001626585.1` |

Why `LOC<GeneID>` and not the bare number (today's database ID) or the symbol:
- a bare number is not self-describing in a mixed export, and spreadsheets treat it as a
  number; `LOC<GeneID>` is text, still derived from the GeneID, so stable and unique, and is
  NCBI's own form for unnamed genes;
- NCBI's symbol-based IDs (`gene-ANAPC13`) change when a gene is renamed between releases and
  get `-2` suffixes when a symbol repeats. The symbol remains the displayed name.
- Checked 2026-10-20 (NCBI E-utilities, db=gene): a plain search for `LOC<GeneID>` finds
  the gene both when that IS its symbol (`LOC5505848`, Nematostella) and when it has a real
  symbol (`LOC105288252` -> ANAPC13, Pteropus vampyrus). Only a symbol-field search
  (`LOC105288252[sym]`) misses the latter. MOOP's own link-outs should still use the number
  (`https://www.ncbi.nlm.nih.gov/gene/<GeneID>`), which is always exact.

Why drop `gene-` / `rna-` / `cds-`: they are NCBI's GFF scaffolding, not identifiers; the
accession after them is already unique, and it is what people cite and what the FASTA uses.

**Prokaryotes (RefSeq PGAP):** the CDS and its protein share the accession (`cds-WP_…` and
`WP_…`; today's DB stores the CDS as `cds-WP_011083461.1`). Stripping would give two
features one ID, so the CDS takes MOOP's existing suffix convention instead:
`WP_011083461.1:cds` (as `rename_t2g_fasta.pl` does for transcript2gene sets). Generally:
strip a prefix unless two features would then share an ID; the gate (below) enforces it.

**Ensembl:** `gene:FBgn…` → `FBgn…`, `transcript:FBtr…` → `FBtr…` (stable IDs, unique).
⚠️ A standing note says **do not strip Ensembl's `CDS:`** (`project_fasta_names_and_fai`)
— find out why before writing the Ensembl rule; it may be the same CDS/protein collision.

## Steps

1. **Pipeline: an ID normaliser**, a sibling of `scripts/strip_id_prefix.pl` (which already
   rewrites `ID`, `Parent` and FASTA headers together in MOOP's working copy). Applies the
   rules above to genes.gff (`ID`, every `Parent`, and `geneID=` where present) and to
   transcript / CDS / protein FASTA headers; writes each original ID into `Alias=`.
   Runs after the source is detected (`GFF_SOURCE` = refseq | ensembl, process_one_geneset.sh)
   and before the database is loaded.
2. **Pipeline gate** (fails the gene set, like the loader's hierarchy gate):
   - every database feature is found by exact ID in genes.gff AND in its FASTA;
   - every `Parent=` resolves to an `ID=` in the file;
   - no two features share an ID; feature counts by type are unchanged by the rewrite;
   - every original ID survives as an Alias.
   (The 2026-10-20 measurement above, made strict.) A rename once silently discarded whole
   annotation files (vagum, 21,199 annotations) — this is what the gate is for.
3. **Loader takes its uniquenames from the normalised GFF** — one source; the RefSeq/Ensembl
   parsers stop deriving IDs on their own (`parse_RefSeq_GFF_to_MOOP_TSV.pl`,
   `parse_Ensembl_GFF_to_MOOP_TSV.pl`). Annotation files keyed by the old IDs (XP_… for
   protein-level tools) keep matching for mRNA/protein; gene-level ones need the GeneID →
   LOC mapping — check every parser that joins on gene ID.
4. **Rollout:** Nematostella RS_101 first. Compare before/after: gene page, Data Exporter
   coordinates (must now fill), JBrowse search, BLAST link-outs, search results, annotation
   counts. Then the other RefSeq sets, Drosophila (Ensembl) once its rule is settled,
   Bradyrhizobium.
5. **Web side, after reload:**
   - aliases resolve: an old ID or URL (`5505848`, `gene-LOC5505848`) still opens the gene —
     the gene page / ID search look the alias up and redirect to the canonical ID;
   - regenerate `feature_coords.tsv` and JBrowse gene tracks (+ text index over Name and Alias);
   - retire the per-tool ID ladders one at a time (`lib/gff_gene_model.php` candidates, .fai
     lookups), each only after the health check is clean for every gene set;
   - **health check** (housekeeping, dashboard card): per gene set, % of database IDs found
     exactly in genes.gff, feature_coords.tsv and the FASTAs. A new download that does not
     line up is flagged the day it arrives.
6. **Later — aliases and mapping tables** (wanted): generate a per-gene-set mapping table
   (canonical ID ↔ every alias ↔ source) from the GFF `Alias` attributes; offer it as a
   download and use it for search. The aliases are written at the one moment both IDs are
   known, so the table is a by-product, not a second source of truth.

## Why change IDs now

IDs live in URLs, bookmarks, collaborators' notes and exported files. Before launch almost
nobody outside has links, so this is the cheapest it will ever be — and step 5's alias
redirect keeps the few old links working.

## Not decided

- The Ensembl `CDS:` question above.
- Whether non-RefSeq/Ensembl sources ("other", Helixer, BRAKER, lifted) need anything beyond
  today's `strip_id_prefix.pl` — the 43 matching gene sets suggest not.
- Display: show the symbol first and the ID second everywhere (gene page title already does
  `symbol · ID`).

## Touch points

Pipeline: `scripts/process_one_geneset.sh`, `scripts/strip_id_prefix.pl` (pattern),
`scripts/rename_RefSeq_cds_fasta.pl`, `scripts/rename_Ensembl_cds_fasta.pl`,
`analysis_parsers/parse_RefSeq_GFF_to_MOOP_TSV.pl`, `analysis_parsers/parse_Ensembl_GFF_to_MOOP_TSV.pl`,
`data_loaders/load_genes_sqlite.pl`. Web: `lib/gff_gene_model.php`, `lib/moopmart_functions.php`
(coords), `lib/jbrowse/gene_set_functions.php` (feature_coords, text index), `tools/parent.php`,
`api/feature_search.php`, `lib/housekeeping.php` (health check).
