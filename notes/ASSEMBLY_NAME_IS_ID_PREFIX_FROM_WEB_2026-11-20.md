# Assembly name in the database is the gene-ID prefix, not the assembly name — from web, 2026-11-20

**For: compute (pipeline owner of `config/build_and_load_db/`). Web has not changed any pipeline file.**

## What is wrong

The `genome.genome_name` column in `organism.sqlite` should hold the **assembly's name** (e.g. `GRCz11`,
`Nvec200`, `AmpQue_v1.1`). For about half the assemblies it holds the **gene-ID prefix** instead:

- `analysis_parsers/make_feature_table.pl` (around line 52) and
  `parse_Ensembl_GFF_to_MOOP_TSV.pl` / `parse_transcript2gene_to_MOOP_TSV.pl` set
  `$genome_name` from the metadata key **`simrbase-prefix:`**, then write it as `## Genome Name:`.
- `data_loaders/load_genes_sqlite.pl` loads that into `genome.genome_name`.

The prefix is an ID convention, not a name. Two assemblies of one organism that share a gene-ID
prefix become indistinguishable:

- *Schmidtea mediterranea*: `dd_v6`, `GCA_057920485.1`, `GCA_057920495.1` are all **"SMED"**.
- *Petromyzon marinus*: `GCF_010993605.1` and `GCF_048934315.1` are both **"PMZ"**.

Visitors see these names in the organism picker on Annotation Search / Data Exporter, in BLAST and
Retrieve Sequences, and anywhere an assembly is labelled "Name (accession)".

## What we ask

**Use the assembly name.** The per-assembly `metadata.yaml` already has a `genome-name:` key (e.g.
Danio: `genome-name: GRCz11`). Please write *that* into `## Genome Name:` / `genome.genome_name`,
and keep `simrbase-prefix` for what it is (the ID prefix) — it should not reach the genome table.

Where an assembly has no real name (a depositor transcriptome, an in-house build), please give it
one in the metadata (for example `dd_v6` → "Dresden transcriptome v6") rather than leaving the prefix.
The web hides empty / "NA" names and falls back to the accession, so an empty name is better than a
wrong one.

## Also: the description is wrong for some

`genome_description` is built from `source:` ("Assembly is from NCBI-GENOMES."). For Smed `dd_v6`
— the Dresden transcriptome, not an NCBI assembly — it says NCBI-GENOMES. Worth checking `source:`
for the Smed rows at least:

| accession | description in DB |
|---|---|
| dd_v6 | Assembly is from NCBI-GENOMES. |
| GCA_057920485.1 | Assembly is from NCBI-GENOMES. |
| GCA_057920495.1 | Assembly is from NCBI-GENOMES. |
| schMedS3h1 | Assembly is from OTHER. |
| schMedS3h2 | Assembly is from OTHER. |
| smed_20140614 | Assembly is from SIMR. |

## Affected assemblies (measured on the web host's databases, 2026-11-20)

**Confirmed** — the organism's gene IDs start with the stored "name" (42 of 93 assemblies):

| organism | accession | stored name | example gene ID |
|---|---|---|---|
| Anoura_caudifer | GCA_004027475.1 | ACA1 | `ACA1_PVKU01000001.1_000001` |
| Antrozous_pallidus | GCA_007922775.1 | APA1 | `APA1_VMDP01000002.1_000001` |
| Bipalium_vagum | bvag.kc1.ref | bvag.kc1 | `bvag.kc1.000005_0_1` |
| Camerata_robusta | crob.kc3.ref | crob.kc3 | `crob.kc3.gc000000` |
| Carollia_perspicillata | GCA_004027735.1 | CPE1 | `CPE1_PVKM010000002.1_000001` |
| Chamaeleo_calyptratus | CCA3 | CCA3 | `CCA3g004837000.1` |
| Congeria_kusceri | GCA_027627225.1 | COKUS1KC | `COKUS1KC_0043292` |
| Craseonycteris_thonglongyai | GCA_004027555.1 | CTH1 | `CTH1_PVKE010000001.1_000001` |
| Cura_foremanii | cfor.kc1.ref | cfor.kc1 | `cfor.kc1.000000_0_1` |
| Cura_pinguis | cpin.kc1.ref | cpin.kc1 | `cpin.kc1.000000_0_1` |
| Cynopterus_brachyotis | GCA_009793145.1 | CBR1 | `CBR1_SSHV01000001.1_000001` |
| Diversibipalium_multilineatum | dmul.kc1.ref | dmul.kc1 | `dmul.kc1.000000_0_1` |
| Eidolon_helvum | GCA_000465285.1 | EHE1 | `EHE1_KE747934.1_000001` |
| Eonycteris_spelaea | GCA_003508835.1 | ESP1 | `ESP1_PUFA01000001.1_000001` |
| Hipposideros_galeritus | GCA_004027415.1 | HGA1 | `HGA1_PVLB01000001.1_000001` |
| Lasiurus_borealis | GCA_004026805.1 | LBO1 | `LBO1_PVJN01000001.1_000001` |
| Leptonycteris_nivalis | Lnivalis_consensus_genome | LNI1 | `LNI1_Scaffold_3_000001` |
| Leptonycteris_yerbabuenae | Lyerbabuenae_genome | LYE1 | `LYE1_Scaffold_3_000001` |
| Macroglossus_sobrinus | GCA_004027375.1 | MSO1 | `MSO1_PVKZ01000001.1_000001` |
| Macrotus_californicus | GCA_007922815.1 | MCA1 | `MCA1_VMDR010000001.1_000001` |
| Macrotus_waterhousii | Mwaterhousii_consensus_genome | MWA1 | `MWA1_Scaffold_3_000001` |
| Megaderma_lyra | GCA_004026885.1 | MLY1 | `MLY1_PVJL010000001.1_000001` |
| Micronycteris_hirsuta | GCA_004026765.1 | MHI1 | `MHI1_PVJI01000001.1_000001` |
| Miniopterus_schreibersii | GCA_004026525.1 | MSC1 | `MSC1_PVJG01000001.1_000001` |
| Mormoops_blainvillei | GCA_004026545.1 | MBL1 | `MBL1_PVJD01000001.1_000001` |
| Murina_feae | GCA_004026665.1 | MAU1 | `MAU1_PVJC01000001.1_000001` |
| Musonycteris_harrisoni | Mharrisoni_consensus_genome | MHA1 | `MHA1_Scaffold_1_000001` |
| Myotis_septentrionalis | myse_ont_racon_pilon_HiC | MSE1 | `MSE1_HiC_scaffold_1_000001` |
| Noctilio_leporinus | GCA_004026585.1 | NLE1 | `NLE1_PVIW01000001.1_000001` |
| Nycticeius_humeralis | GCA_007922795.1 | NHU1 | `NHU1_VMDQ010000001.1_000001` |
| Obama_nungara | onun.kc3.ref | onun.kc3 | `onun.kc3.gc000000` |
| Phagocata_morgani | pmor.kc3.ref | pmor.kc3 | `pmor.kc3.gc000000` |
| Phagocata_velata | pvel.kc1.ref | pvel.kc1 | `pvel.kc1.000000_1_1` |
| Procerodes_sp | prsp.kc1.ref | prsp.kc1 | `prsp.kc1.000000_1_2` |
| Pteropus_rufus | Pteropus_rufus_HiC | PRU1 | `PRU1_HiC_scaffold_1_000001` |
| Ptychodera_flava | PflaM.kc1.ref | PflaM.kc1 | `PflaM.kc1.037315` |
| Romankenkius_sp | Romankenkius_au23-003 | ro03.kc1 | `ro03.kc1.000000_10_1` |
| Romankenkius_sp | Romankenkius_au23-39 | ro39.kc1 | `ro39.kc1.000000_0_1` |
| Rousettus_madagascariensis | GCA_028533395.1 | RMA1 | `RMA1_HiC_scaffold_1_000001` |
| Spathula_sp | spsp.kc1.ref | spsp.kc1 | `spsp.kc1.000000_0_1` |
| Tadarida_brasiliensis | GCA_004025005.1 | TBR1 | `TBR1_PVIG010000001.1_000001` |
| Tonatia_saurophila | GCA_004024845.1 | TSA1 | `TSA1_PVIA01000001.1_000001` |

**Very likely** — the same three-letters-plus-number code pattern, though these organisms' gene IDs
do not carry it: AJA1 (Artibeus jamaicensis), DRO1 (Desmodus rotundus), EFU1 (Eptesicus fuscus),
HAR1 (Hipposideros armiger), LCI1 (Lasiurus cinereus), MNA1 (Miniopterus natalensis), MMO1 (Molossus
molossus), MBR1 (Myotis brandtii), MDA1 (Myotis davidii), MLU1 (Myotis lucifugus), MMY1 (Myotis
myotis), PDI1 (Phyllostomus discolor), PHA1 (Phyllostomus hastatus), PKU1 (Pipistrellus kuhlii),
PAL1 (Pteropus alecto), PVA2 (Pteropus vampyrus), RFE1 (Rhinolophus ferrumequinum).

The rest look like real assembly names (Amil_v2.1, AmpQue_v1.1, GRCz11, GRCz12tu, Nvec200,
jaNemVect1.1, BraPum1, NfurGRZ-RIMD1, ...) — presumably where `simrbase-prefix` happened to be set to
the name. Taking the name from `genome-name:` everywhere fixes both groups the same way.

## After the rerun

Web needs nothing changed: the label code (`genome_display_name()` / `assembly_label()` in
`lib/functions_access.php`) reads `genome_name` as-is. Rebuilt databases show the right names at once
(an assembly name cached in the organism cache follows on its next refresh).
