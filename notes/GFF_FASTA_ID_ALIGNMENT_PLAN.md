# One ID per feature: RefSeq/Ensembl genes.gff and FASTAs match the database

**Status:** PLAN, fourth version 2026-10-21 (compute box). Steps 1–3 done (`39e17bb`, `51fc630`,
`5becc4c`, `e8cfeb4`); Nematostella RS_101 shipped aligned, web checks in progress (see "Step 3
results"); step 4 (rollout) next. This version replaces the third ("one ID map per gene set, crossed once"): a review of that
design against the pipeline code found it would have to re-learn every special case the working
code already handles (naming order, Ensembl versioning, gene-level annotation IDs, prefix/lift
rewrites of every GFF line, non-coding FASTA records) to fix a problem that exists in RefSeq and
Ensembl genes.gff only. See "Why not the full redesign".

**Goal:** for every gene set, the database, genes.gff and the FASTAs carry the same IDs and the
same names, so a retrieved FASTA record, the GFF/JBrowse and the gene page all say the same thing.
**Approach:** leave the working code alone; add one small GFF ID formatter and the existing name
step for the two sources whose files need it (RefSeq, Ensembl); add a gate that proves it for
every gene set.

## The problem

**IDs.** For 28 gene sets (RefSeq and Ensembl) the database's gene and mRNA IDs never appear in
genes.gff. The FASTAs already match the database (`rename_RefSeq_cds_fasta.pl` /
`rename_Ensembl_cds_fasta.pl` fix the CDS FASTA), so **the GFF is the odd one out**:

| | database / FASTA | genes.gff `ID=` |
|---|---|---|
| gene | `5505848` (NCBI GeneID) | `gene-LOC5505848` / `gene-ANAPC13` |
| mRNA | `XM_001626535.3` | `rna-XM_001626535.3` |
| CDS | `cds-XP_001626585.1` | `cds-XP_001626585.1` — already matches |
| protein | `XP_001626585.1` | (`protein_id=` on CDS lines) — matches |

Ensembl is the same shape: `gene:ENSDARG….1` / `transcript:ENSDART….1` in the (versioned) GFF,
`ENSDARG….1` / `ENSDART….1` in the database. Every GFF reader on the web translates on its own
(Data Exporter chr/start/stop empty for these sets; the ID ladder in `lib/gff_gene_model.php`;
JBrowse search; BLAST link-outs).

Measured 2026-10-20 (web host): gene/mRNA in GFF 0% for Amphimedon, Artibeus, Bradyrhizobium,
Danio 20260404, Desmodus, Drosophila BDGP6.54, Eptesicus, Hipposideros, Miniopterus, Molossus,
the four Myotis, Nematostella RS_101, Nothobranchius, Petromyzon, both Phyllostomus,
Pipistrellus, Pteronotus, the three Pteropus, Rhinolophus, Rousettus, Sturnira (and Medicago,
out of scope: nothing matches there, FASTA included).

**Names.** RefSeq and Ensembl keep the source's own GFF and FASTA headers; only the closest genes
are added to the GFF. Naming v2 replaces an uninformative native name ("uncharacterized
protein", a bare LOC/CG symbol) in `geneNames.tsv`, and so in the database and on the gene page,
but the FASTA headers still show the source's name. Other/lift gene sets already get
`updateFASTA.pl` and match.

## What changes

All in `process_one_geneset.sh`, RefSeq and Ensembl branches only. Naming, the analyses,
`isoforms.tsv`, `geneNames.tsv`, the annotation files and the loader are untouched; other and
lift gene sets are untouched; database IDs do not change.

1. **FASTA names** — run `updateFASTA.pl --keep-unlisted --by-gene` with `geneNames.tsv` on protein, CDS and
   transcript FASTAs for RefSeq/Ensembl, **after** the existing renames (then every header ID is a
   database ID, and `geneNames.tsv` already has a row for each: gene, mRNA, `cds-…`/`CDS:…`,
   protein — checked on Nematostella, Danio, Bradyrhizobium). No new code. The transcript FASTA
   is a symlink into the datastore today; write a real file, never through the link.
   - **Records that are not database features keep the source's header** (decided 2026-10-21).
     `updateFASTA.pl` clears the header of any ID not in `geneNames.tsv`; RefSeq/Ensembl
     transcript FASTAs hold non-coding records the database does not have (Nematostella 10,651
     `XR_`, Danio lincRNAs, Bradyrhizobium tRNAs). For RefSeq/Ensembl "in `geneNames.tsv`" is
     "in the database" — measured: Nematostella 116,341 of 116,341 database features, Danio
     163,177 of 163,177 — so a `--keep-unlisted` switch (RefSeq/Ensembl only; other/lift keep
     today's clearing) leaves those headers as delivered. The gate's name check (FASTA name =
     database name for every database feature) catches any database feature kept by mistake.
   - **51 CDS copies are database features without a `geneNames.tsv` row**: Bradyrhizobium 50
     (`cds-WP_…-2`, a second copy of one protein, mostly transposases) and Amphimedon *Gro*
     (`cds-NP_001266236.1-2`). Listed exception for now; naming them means a `geneNames.tsv`
     row for each copy (naming code), a separate decision.

2. **GFF ID formatter** (new, small: `scripts/format_gff_ids.pl`) — rewrites `ID=` and `Parent=`
   of the gene and mRNA lines (and every `Parent=` that points at them: exons, CDS, non-coding
   children of coding genes) to the database IDs, keeping the original ID as `Alias=`. The
   ID rules are `build_id_map.pl`'s (step 1 shows they reproduce the databases); the formatter
   only reads the map. Runs on the GFF as it is at the end of the GFF steps (Ensembl: the
   versioned GFF, see Ensembl below), **after features.tsv is built** — `emit_ensembl` and
   `_coords_ensembl` read `ID=gene:`/`ID=transcript:`. genes.gtf is built after the formatter.
   Features not in the map (non-coding genes, V/C gene segments, exons' own IDs) keep their IDs.

   **`Alias=`** (comma-separated; each entry indexed by JBrowse once the index covers `Alias`)
   carries every other ID people search by, read from the line's own attributes, deduplicated
   against the new `ID=` and `Name=`:

   | | gene line | mRNA line |
   |---|---|---|
   | RefSeq | original ID (`gene-LOC116613690`), `gene=` symbol (`LOC116613690`, `mnmE`), `locus_tag=`, `old_locus_tag=`, `gene_synonym=` entries | original ID (`rna-XM_…`), accession without version (`XM_048724567`), its protein accession(s) with and without version (`XP_…`, from the CDS lines' `protein_id=`) |
   | Ensembl | original ID (`gene:ENSDARG….1`), ID without version (`ENSDARG00000099104`), source `Name=` (`rpl24`) | original ID (`transcript:ENSDART….1`), ID without version, source `Name=` (`rpl24-203`), protein ID with and without version |

   The source's own symbol stays searchable even where naming v2 gives the gene a different name.
   Bradyrhizobium measured: 8,347 genes with `locus_tag`, 7,980 with `old_locus_tag`, 7 with
   `gene_synonym`.

3. **GFF names** (decided 2026-10-21: yes, as its own step after the ID fix) — run `updateGFF.pl`
   on RefSeq/Ensembl too, after the formatter (then the gene IDs equal `geneNames.tsv`'s GroupId),
   so JBrowse shows the database's name. Accepted: CDS lines lose `Name=` (`XP_…` stays in
   `protein_id=` and the mRNA's `Alias=`); an mRNA's `Name=` is the gene symbol, as its database
   row already is (`emit_refseq`/`emit_ensembl` give every row the gene's name). RefSeq symbols
   carry no isoform number (that is in the product, "isoform X1"); Ensembl transcript names do
   (`rpl24-203`) and move to `Alias=`. The gate compares GFF `Name=` with the database name too;
   step 2 shows every difference before anything ships.

4. **The gate** (new, read-only: `scripts/check_id_alignment.pl`) — per gene set, protein-coding
   features only (genes.gff also holds tRNA, lncRNA, pseudogenes, V/C segments the database does
   not load):
   - every database gene and mRNA is an `ID=` in genes.gff, every CDS an `ID=`, every protein a
     `protein_id=`; every coding gene/mRNA/CDS in genes.gff is in the database;
   - every database protein / CDS / mRNA has a record in the protein / CDS / transcript FASTA;
     every protein and CDS FASTA record is in the database;
   - FASTA header name = database name for every database feature;
   - prints a sample of each difference; the listed exceptions below are allowed by name.
   Runs on every gene set, transcriptome-only sets included; also a housekeeping health card
   on the web later.

5. **Web** — JBrowse text index `Name,ID` → `Name,ID,Alias` so the old `gene-LOC…` IDs stay
   searchable (`admin/api/jbrowse_register_gene_set.php:42`, `jbrowse_text_index.php:29`,
   `jbrowse_reprep_gff.php:30`, `admin/pages/manage_jbrowse.php:535`); re-register JBrowse for
   the reshipped gene sets. The web's prefix stripping (`generateFeatureCoordsIndex()`, the
   `gff_gene_model.php` ladder, …) stays until the gate is clean everywhere; removing it is
   optional later cleanup.

### Ensembl

The delivered Ensembl GFF has no version in its IDs, only a `version=` tag.
`add_id_versions.pl` writes `genes.versioned.gff` with the version in the IDs; naming,
`isoforms.tsv` and every analysis use that copy. It stays. `build_id_map.pl` takes the
versioned GFF as input for the formatter (it currently applies the version itself from the
delivered GFF — rerun step 1 for Danio on the versioned input; its versioning rule must then be
a no-op, not `….1.1`).

### Listed exceptions (gate allows by name; the database does not have them)

- **Amphimedon *Gro*** (GeneID 100616087), placed twice by NCBI: second gene/mRNA/CDS
  `…-2`. The database has one gene and mRNA with both CDSs.
- **Mitochondrial mRNAs without `transcript_id=`**: 13 each in Amphimedon, Artibeus, Desmodus,
  Hipposideros, Myotis myotis, Nothobranchius, Petromyzon, Pteropus alecto. The database hangs
  the CDS on the gene.
- **Danio immune gene segments**: 150 proteins in the protein FASTA under C/D/J/V segments, not
  in the database.

Adding them to the database is a separate, later decision (it means reloads).

## Steps

1. **Prove the ID rules on existing data** — DONE 2026-10-21, results below.
2. **On copies** (`dev/smr_dev/id_map_step2/`, read-only on the build): Nematostella RS_101,
   Amphimedon RS_2026_04, Danio 20260404, Bradyrhizobium RS_2025_04_07. Write the gate; run it on
   today's files (expect the known ID failures); run the FASTA name step and the formatter on
   copies; the gate must pass. Check: FASTA sequences and IDs byte-identical to today's, only
   headers' descriptions change; features.tsv unchanged; GFF changes only on `ID=`/`Parent=`/
   `Alias=` of the mapped lines. Run the gate on every gene set (read-only) for the baseline.
3. **Wire into `process_one_geneset.sh`**; run Nematostella end to end; ship; web step 5 for it;
   check gene page, Data Exporter coordinates (must now fill), JBrowse search by ID and by old
   `gene-LOC…` alias, BLAST link-outs, FASTA retrieval names.
4. **Roll out** to the RefSeq/Ensembl gene sets (no database reload: genes.gff, FASTAs, JBrowse
   re-registration). Re-ship everything the gate passes; web-host databases can be older copies
   of the compute box's, so never assume a web copy already matches.

## Step 1 results (2026-10-21)

`analysis_parsers/build_id_map.pl` (the ID rules), `scripts/compare_id_map_to_db.pl` (map vs
organism.sqlite, both directions: missing either side, wrong parent, wrong type),
`scripts/id_map_step1.sh` (read-only). Run on the live active list
(`scripts/list_active_genesets.sh`, `active: true` in metadata.yaml — `active_genesets.tsv` in
`build_and_load_db/v2` is a stale snapshot): `dev/smr_dev/id_map_step1/live/summary.tsv`, one
`.diff.tsv` per gene set.

| Result | Gene sets (97) |
|---|---|
| **Identical** (every feature, type and parent, both directions) | **56**, plus Rousettus and Sturnira, identical under the gene-set names their databases still use (Release-101, Release-100.20210611). RefSeq, other (Congeria, Nvec NV2, Bradypodion with the prefix rewrite) and lift (Parastichopus). |
| **Only the listed exceptions** | **9**: Amphimedon (*Gro* + 13 mitochondrial mRNAs), Artibeus, Desmodus, Hipposideros, Myotis myotis, Nothobranchius, Petromyzon, Pteropus alecto (13 mitochondrial mRNAs each), Danio 20260404 (its 150 immune-segment proteins with their genes, mRNAs and CDSs: 595 rows). |
| **Database older than its files** | **14**: the 12 land-planarian `*.kc*` sets (database still holds TransDecoder `….p1:cds` CDSs), Notamacropus T2T_SIMR_ANNOT and Schmidtea polychroa Spol_FtD1. Rerun after their rebuild. |
| **Not in the database yet** | **2**: Danio GRCz12tu RS_2025_04, Phagocata morgani pmor_cc. |
| **No organism.sqlite** | **14**. |

Drosophila FB_Rel_6.54 (NCBI annotation in the BDGP6.54 folder) is `active: false`; not in this run.

## Gate baseline on today's files (2026-10-21)

`scripts/check_id_alignment.pl`, read-only, on the built files in `build_and_load_db/v2/data`
(details: scratchpad `gate_baseline/`). Counts are features that fail the check.

| Gene set | features | gff_missing | gff_extra | fasta_missing | fasta_extra | fasta_name | tx FASTA not in db |
|---|---|---|---|---|---|---|---|
| Nematostella RS_101 (refseq) | 116,341 | 51,601 (every gene + mRNA) | 51,601 | 0 | 0 | 97,110 (all: delivered headers) | 10,651 |
| Amphimedon RS_2026_04 (refseq) | 90,829 | 43,744 | 43,759 (+15: *Gro*, 13 mito mRNAs) | 0 | 0 | 70,614 | 7,131 |
| Danio 20260404 (ensembl) | 163,177 | 71,357 | 71,802 (+445: the immune segments) | 0 | 300 (150 proteins + 150 CDS of the segments) | 137,730 | 13,966 |
| Bradyrhizobium RS_2025_04_07 (refseq) | 24,811 | 8,287 | 8,287 | 2 | 230 | 16,522 | 60 |
| Bradypodion ventrale (other, prefix) | 88,076 | 0 | 0 | 0 | 0 | 0 | 0 |
| Parastichopus (lift) | 164,629 | 0 | 0 | 3,041 | 0 | 95,740 | 6,461 |

The RefSeq/Ensembl ID failures are the problem this plan fixes; the extras are the listed
exceptions. New findings:

- **Bradyrhizobium**: 230 pseudogene CDSs (`cds-AAV28_RS…`, no `protein_id`) are in the CDS FASTA
  but not the database; `cds-WP_110115815.1` and `…-3` (one protein placed three times) have no
  CDS record — the delivered CDS FASTA has one copy. Both: listed exceptions with reasons.
- **Parastichopus, 3,041 proteins with no sequence**: lifted transcripts whose CDS has no
  `protein_id` (1,455) or whose `protein_id` has no record in the delivered protein FASTA
  (1,587); `emit_generic` gives them a synthesised `:pep`. Listed exception class (liftover, no
  protein), or stop loading proteins that have no sequence — a separate decision.
- **Parastichopus names carry the lift prefix — a naming bug in today's database.**
  `strip_id_prefix.pl`'s prepend mode prefixes every `Name=`, real symbols included, so the
  database and genes.gff say `parpar1_MOAP1`, `parpar1_T`, `parpar1_HARBI1` (15,540 genes,
  31,619 mRNAs, 31,596 CDS and proteins) while the FASTAs say `MOAP1`. Only gene set with
  `moop-lift-prefix`. Fix: prefix `Name=` only when it is the feature's own ID; rebuild and
  reload Parastichopus. (Strip mode, Bradypodion ventrale/pumilum, is anchored on the long
  prefix and does not touch symbols.)
- **Parastichopus, 1,336 non-coding `transcript` records** have a name in the FASTA header but
  none in the database: `updateGFF.pl` names `gene` and `mRNA` lines only, `updateFASTA.pl` names
  every ID in `geneNames.tsv`.

## Step 2 results (2026-10-21)

`scripts/id_map_step2.sh` (read-only on datastore, build tree and databases; writes
`dev/smr_dev/id_map_step2/run2/`): `build_id_map.pl` (Ensembl on `genes.versioned.gff`) →
`format_gff_ids.pl` on today's built genes.gff → `updateGFF.pl` → `updateFASTA.pl --keep-unlisted
--by-gene` on the three built FASTAs → the gate with `id_alignment_exceptions.tsv` → byte checks.

| Gene set | IDs (gff/fasta missing, extra) | FASTA ids + sequences | genes.gff apart from ID/Parent/Alias/names | names |
|---|---|---|---|---|
| Nematostella RS_101 | 0 | identical | identical | **gate passes** |
| Danio 20260404 | 0 (+445 GFF, +300 FASTA listed) | identical | identical | **gate passes** |
| Amphimedon RS_2026_04 | 0 (+15 listed) | identical | identical | 8,469 differ from the database — see below |
| Bradyrhizobium RS_2025_04_07 | 0 (+2, +230 listed) | identical | identical | 14,285 FASTA, 7,185 GFF differ from the database — see below |

**Amphimedon and Bradyrhizobium: the databases predate the parser.** Both were loaded in July,
before `03eff13` (2026-09-14) made `emit_refseq` take names from `geneNames.tsv`: Amphimedon's
database has a per-isoform description ("…isoform X3"), Bradyrhizobium's has the locus tag as
the gene name. Against features.tsv rebuilt with today's parser on the copies, every FASTA header
and every GFF gene `Name=` matches except the listed CDS copies (Amphimedon 70,613/70,614,
Bradyrhizobium 16,473/16,522 FASTA; GFF 20,215/20,215 and 8,287/8,287). They pass once reloaded.

Changes made to get here:
- **`updateFASTA.pl --by-gene`**: `geneNames.tsv` has a row per isoform with the isoform's own
  product ("…isoform X5", "…transcript variant X1"), and naming v2 replaces only the gene row's
  name; the database gives every row of a RefSeq/Ensembl gene the gene row's name. `--by-gene`
  makes each record take its gene's row (GroupId), so the FASTA says what the database says.
  Without it Nematostella had 25,280 headers differing in isoform wording and ~3,400 where the gene
  was renamed but the isoform kept the native product.
- **`updateGFF.pl` names a gene from its own row** (ID = GroupId) when the names file has one,
  else the group's last row as before. A protein shared by two genes gave 42 Bradyrhizobium genes
  the other gene's name. Byte-identical output to before on all 55 other/lift gene sets with a build (Phagocata morgani pmor_cc: no build yet).
- **The gate** treats a database name/description of `None` as empty (naming writes `None`).

Consequences to know (decisions, not bugs in this work):
- **Isoform descriptions.** RefSeq/Ensembl FASTA headers, like the database, now carry the gene's
  description on every isoform (Amphimedon's July database still has per-isoform ones). If
  isoform-level descriptions are wanted on the gene page, that is a parser change (`emit_refseq`
  per row) plus dropping `--by-gene`.
- **Bradyrhizobium gene names are GeneIDs.** `geneNames.tsv` gives 7,185 of its 8,287 genes the
  GeneID as symbol (`46487910: pyruvate, water dikinase regulatory protein`); the July database
  shows the locus tag (`AAV28_RS00020`). Today's parser and these files would show `46487910`.
  The locus tag stays searchable through `Alias=`. Fixing the symbol is a naming change
  (`get_names_from_gff.pl`: locus_tag when there is no gene=).
- **`updateGFF.pl` removes every source `Note=`** (Nematostella 22,479 lines, e.g. NCBI's "The
  sequence of the model RefSeq transcript was modified…"); it does so for other/lift today.

## Parastichopus lift-prefix fix (2026-10-21)

`strip_id_prefix.pl` prepend mode now prefixes `Name=` only when it is the feature's own ID and
leaves `namesrc` alone. Checked on a copy (`dev/smr_dev/id_map_step2/Parastichopus_lift_fix/`,
datastore genes.gff → `updateGFF.pl` → `strip_id_prefix.pl --add parpar1_` →
`parse_GFF3_to_MOOP_TSV.pl`): genes.gff identical apart from `Name`/`namesrc`; features.tsv
identical IDs, types and parents; 112,962 names lose exactly the `parpar1_` prefix (`MOAP1`, not
`parpar1_MOAP1`); the 51,674 unnamed features keep their prefixed ID as name. Not reloaded: any
pipeline run on Parastichopus reruns naming (it has no `closest_human.tsv` yet — named before
naming v2) and reloads, so the fix lands with its naming v2 build after OMA part 2.

**The 3,041 Parastichopus proteins with no sequence are really missing from the delivered
`protein.aa.fa`**, on purpose: all 3,041 are in the folder's `protein.aa.fa.bak` (2026-06-03,
44,298 records), the CDS FASTA and the transcript FASTA, and every one has internal stop codons
(`.`); the current `protein.aa.fa` (2026-06-30, 41,257) is the filtered copy. 3,033 are nuclear
lifted models whose reading frame the liftover broke; 8 are mitochondrial (ATP6, ATP8, COX1–3,
ND3–5), translated with the standard instead of the invertebrate mitochondrial code — real
proteins. Listed exception; the database still loads a protein row for each.

## Step 3 results (2026-10-21)

**Wiring** (`5becc4c`, `process_one_geneset.sh`, GFF path): RefSeq/Ensembl only, after features.tsv
(it and feature_coords.tsv read the source IDs): `build_id_map.pl` (`--gff $SRC_GFF`: delivered, or
`genes.versioned.gff` for Ensembl) → `format_gff_ids.pl` → `updateGFF.pl` → `updateFASTA.pl
--keep-unlisted --by-gene` on the three FASTAs. `restore_unchanged` and genes.gtf moved below it, so
an unchanged rerun keeps every file, mtime and index. genes.gff is re-derived from the source on every
run, so it is never formatted twice. The gate runs after the load on every GFF gene set, warns only
(databases loaded before `03eff13` fail on names until reloaded); details in
`id_alignment.details.tsv`. Neither it nor `id_map.tsv` is in copy2moop's file list.

The v2 build tree runs the repo's `scripts/` through a symlink, so a commit here is the next build's
code. Added `v2/id_alignment_exceptions.tsv` → repo (the gate reads it from `$REPO`).

**Nematostella RS_101** (v2 build, no reload; backup of the old files in
`dev/smr_dev/id_map_step3/before/`): gate clean on 116,341 features (10,651 non-coding transcripts
not in the database, headers kept); organism.sqlite, features.tsv, feature_coords.tsv unchanged; FASTA
IDs and sequences unchanged; output byte-identical to the step-2 copy; a second run rebuilt nothing.
transcript.nt.fa is now a real file. 18,765 non-coding genes (tRNA, rRNA, lncRNA, sn/snoRNA) keep
`gene-` IDs, as planned. Shipped (copy2moop, 40 files).

Web checks on Nematostella: gene page good, FASTA sequences in the new header format, exon retrieval
from the gene structure diagram works. **Still to check:** BLAST link-outs, JBrowse search (by new ID,
and by `gene-LOC…` once the text index includes `Alias` — web agent on it), Data Exporter
coordinates.

**Congeria COKUS1KC** (other; regression check): no file changed, gate clean; shipped (busco.json only).

**Gate fixes found on the way** (`e8cfeb4`): an unnamed other/lift CDS/protein has its mRNA's ID as
database description and a bare `>id` header — no description, not a mismatch (Congeria: 46,444 false
`fasta_name` → 0). The 3,041 Parastichopus internal-stop models are missing *proteins*, now listed by
protein ID (`…:pep`). Parastichopus still fails on names only (95,740: the `parpar1_` name bug in its
database), fixed with its rebuild.

**Open:**
- FASTA headers carry naming's evidence tag, as the database does: `>XP_001617561.3 LOC5495712 late
  histone H2B.L4 [SRC] RefSeq`. Keep, or strip the `[SRC]`/`[ISO|…]` tag from FASTA headers?
- Isoform-level descriptions (`emit_refseq` per row, drop `--by-gene`)?
- Bradyrhizobium symbols are GeneIDs (locus_tag when there is no `gene=`?).
- `updateGFF.pl` drops the source `Note=` (Nematostella 22,479 lines).
- Gate not yet on the transcriptome-only (T2G) path.
- Drift between this doc and step 2: the formatter's mRNA `Alias=` also carries `gene=`,
  `locus_tag=`, source `Name=` and `transcript_id=` (wider than the table above); the step-2 harness
  never compared features.tsv (step 3 did: unchanged); the gate baseline covered 6 gene sets, not
  all; the Danio versioned-input rerun is done (gate clean).

**Next — step 4:** run the other RefSeq/Ensembl gene sets (no reload: genes.gff, FASTAs, indexes),
gate each, ship, re-register JBrowse. Amphimedon and Bradyrhizobium need a reload to pass on names.

## Why not the full redesign (third version)

It replaced the FASTA renamers, `strip_id_prefix.pl`, the features.tsv emitters and the
loader's ID matching with one ID map for all ~92 gene sets. Reviewing it against the code:

- naming attaches names by the delivered IDs, so any ID rewrite must come after it, and
  `updateFASTA.pl` deletes a description it cannot look up;
- Ensembl naming runs on `genes.versioned.gff`, so `add_id_versions.pl` cannot go;
- gene-level annotation files are keyed by IDs that are neither the GFF's nor the FASTA's
  (RefSeq GeneID, Ensembl versioned gene), so the loader join needed a third ID column;
- features.tsv names follow three different rules today (RefSeq/Ensembl from geneNames.tsv,
  other/lift per row from the named GFF);
- `strip_id_prefix.pl` rewrites `ID`/`Parent`/`Name`/`namesrc` on every GFF line (exons, UTRs,
  non-coding), which a coding-only map would have undone.

Every one of these is already handled by the working code. The misalignment is in RefSeq and
Ensembl genes.gff only, so the fix is there. Simplifying the rest can happen later piece by piece,
with the gate as the safety net.

## Touch points

Pipeline: `scripts/process_one_geneset.sh` (RefSeq/Ensembl branches, ~1132–1183; step 3),
`analysis_parsers/build_id_map.pl` (reads `genes.versioned.gff` for Ensembl, unchanged code),
`scripts/format_gff_ids.pl` (new), `scripts/check_id_alignment.pl` (new),
`id_alignment_exceptions.tsv` (new), `scripts/id_map_step2.sh` (new, step-2 harness),
`analysis_parsers/updateFASTA.pl` (`--keep-unlisted`, `--by-gene`), `analysis_parsers/updateGFF.pl`
(gene named from its own row), `scripts/strip_id_prefix.pl` (lift prefix leaves symbols alone).
Web: the JBrowse text-index default (step 5).
