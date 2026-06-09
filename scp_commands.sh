#!/usr/bin/env bash
# SCP commands to copy GFF files from genomes to tracks
# Run from cerebro. Set up SSH ControlMaster first:
#   ssh -MNf genomes && ssh -MNf tracks
#
# Dry-run to check paths before copying:
#   bash scp_commands.sh --dry-run   (or grep "^scp" and review)

set -euo pipefail

# Create destination directories
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff/LOC
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff/combined_transcripts
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Congeria_kusceri/GCA_027627225.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Danio_rerio/GCF_000002035.5/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAVTP000000000.2/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAXLI000000000.2/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Montipora_capitata/Mcap_2019/gff/kc_mcap_v1
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/ensembl/104
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/PM_new_annotations_2023
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/RefSeq_annotations
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/liftoff_apollo
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pomacea_canaliculata/GCF_003073045.1/gff/organisms/snail/Pcan/genomes/refseq_v1/analysis/qRTPCRprimers
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pteropus_rufus/Pteropus_rufus_HiC/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff/MAKER_082015
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff/smedgd_dump
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Scolanthus_callimorphus/Scal100/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff

# ACA1_v1 → /var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/ACA1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/ACA1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/IMPA1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Anoura_caudifer/GCA_004027475.1/gff/TREH.exonerate.gff

# ACI1_v1 → /var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/ACI1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/ACI1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/KHK.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/SLC2A2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/SLC2A2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_cinereus/GCA_011751095.1/gff/TREH.exonerate.gff

# AJA1_v1 → /var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/AJA1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/AJA1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Artibeus_jamaicensis/GCF_021234435.1/gff/TREH.exonerate.gff

# AME1_v1 → TRACKS_DEST/AME1_v1/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/cavefish/Amex/genomes/AMEX_1.1/AME1.putative_function.gff tracks:TRACKS_DEST/AME1_v1/gff/AME1.putative_function.gff
# UNRESOLVED: AME1.putativefunction.gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/cavefish/Amex/AME2/AME2.putative_function.gff tracks:TRACKS_DEST/AME1_v1/gff/AME2.putative_function.gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/cavefish/Amex/genomes/AMEX_1.1/AMEX_1.1.GCA_019721115.1.gff tracks:TRACKS_DEST/AME1_v1/gff/AMEX_1.1.GCA_019721115.1.gff

# APA1_v1 → /var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/APA1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/APA1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/KHK.exonerate.gff  # AMBIGUOUS(6 candidates)
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/LEP.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/LEP.exonerate.gff  # AMBIGUOUS(5 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/MC4R.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/MC4R.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/SLC2A2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/SLC2A3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A4.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/SLC2A4.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/TAS1R2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Antrozous_pallidus/GCA_007922775.1/gff/TREH.exonerate.gff  # AMBIGUOUS(30 candidates)

# CBR1_v1 → /var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/CBR1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/CBR1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/KHK.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/TAS1R3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Cynopterus_brachyotis/CBR1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Cynopterus_brachyotis/GCA_009793145.1/gff/TREH.exonerate.gff

# CCA1_v1 → /var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff
# UNRESOLVED: CCA1.putative_function.gff
scp genomes:/var/other_data/organisms/chameleon/Chamaeleo_calyptratus/CCA1/analysis/LOC/CCA1.gff tracks:/var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff/LOC/CCA1.gff
scp genomes:/var/other_data/organisms/chameleon/Chamaeleo_calyptratus/CCA1/analysis/combined_transcripts/Chameleon_CombinedTranscripts.gff tracks:/var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff/combined_transcripts/Chameleon_CombinedTranscripts.gff
scp genomes:/var/other_data/organisms/chameleon/Chamaeleo_calyptratus/CCA1/evidence/dovetail.est2genome.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff/dovetail.est2genome.evidence.gff

# COKUS1KC_v1 → /var/www/privatehtml/moop/data/tracks/Congeria_kusceri/GCA_027627225.1/gff
scp genomes:/var/other_data/organisms/cavemollusk/Congeria_kusceri/genomes/COKUS1/COKUS1KC.names.gff tracks:/var/www/privatehtml/moop/data/tracks/Congeria_kusceri/GCA_027627225.1/gff/COKUS1KC.names.gff  # AMBIGUOUS(2 candidates)

# CPE1_v1 → /var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/CPE1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/CPE1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/KHK.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/MC4R.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/MC4R.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/SLC2A1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Carollia_perspicillata/GCA_004027735.1/gff/TAS1R2.exonerate.gff

# CTH1_v1 → /var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/CTH1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/CTH1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/GCG.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/SLC2A2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/SLC2A2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/SLC2A3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/SLC2A3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Craseonycteris_thonglongyai/GCA_004027555.1/gff/TREH.exonerate.gff  # AMBIGUOUS(30 candidates)

# DRO1_v1 → /var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/AACS.exonerate.gff  # AMBIGUOUS(17 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/ACACB.exonerate.gff  # AMBIGUOUS(22 candidates)
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/DRO1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/DRO1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/GYG2.exonerate.gff  # AMBIGUOUS(33 candidates)
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/HAR1.GCF_001890085.1_ASM189008v1_genomic.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/HAR1.GCF_001890085.1_ASM189008v1_genomic.filtered.gff
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/KHK.exonerate.gff  # AMBIGUOUS(6 candidates)
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/MC4R.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/MC4R.exonerate.gff  # AMBIGUOUS(6 candidates)
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/SLC2A1.exonerate.gff  # AMBIGUOUS(8 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/SLC2A5.exonerate.gff  # AMBIGUOUS(9 candidates)
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Desmodus_rotundus/DRO1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Desmodus_rotundus/GCF_022682495.1/gff/TAS1R2.exonerate.gff

# DanRer11_ens → /var/www/privatehtml/moop/data/tracks/Danio_rerio/GCF_000002035.5/gff
scp genomes:/var/other_data/organisms/zebrafish/Drer/genomes/GRCz11_ens/Danio_rerio.GRCz11.99.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Danio_rerio/GCF_000002035.5/gff/Danio_rerio.GRCz11.99.gff3

# EDU1_v1 → /var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/AACS.exonerate.gff  # AMBIGUOUS(17 candidates)
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/EDU1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/EDU1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/INSR.exonerate.gff  # AMBIGUOUS(29 candidates)
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/LEP.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/LEP.exonerate.gff  # AMBIGUOUS(5 candidates)
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/TAS1R2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_dupreanum/EDU1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_dupreanum/ASM46528v1/gff/TAS1R3.exonerate.gff

# EFU1_v1 → /var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/EFU1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/EFU1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/HAR1.GCF_001890085.1_ASM189008v1_genomic.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/HAR1.GCF_001890085.1_ASM189008v1_genomic.filtered.gff
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/IMPA1.exonerate.gff  # AMBIGUOUS(8 candidates)
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/ITGAD.exonerate.gff  # AMBIGUOUS(13 candidates)
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eptesicus_fuscus/EFU1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eptesicus_fuscus/GCF_027574615.1/gff/TAS1R1.exonerate.gff

# EHE1_v1 → /var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/AACS.exonerate.gff  # AMBIGUOUS(17 candidates)
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/ACACB.exonerate.gff
# UNRESOLVED: EHE1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/FSTL1.exonerate.gff  # AMBIGUOUS(11 candidates)
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/GCG.exonerate.gff  # AMBIGUOUS(8 candidates)
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/MC4R.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/MC4R.exonerate.gff  # AMBIGUOUS(6 candidates)
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/SLC2A1.exonerate.gff  # AMBIGUOUS(8 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A4.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/SLC2A4.exonerate.gff  # AMBIGUOUS(4 candidates)
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eidolon_helvum/EHE1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/TAS1R3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eidolon_helvum/GCA_000465285.1/gff/TREH.exonerate.gff  # AMBIGUOUS(30 candidates)

# ESP1_v1 → /var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/ACACB.exonerate.gff
# UNRESOLVED: ESP1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/GCG.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/MC4R.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/MC4R.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/SLC2A1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/SLC2A4.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/SLC2A4.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/TAS1R3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Eonycteris_spelaea/ESP1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Eonycteris_spelaea/GCA_003508835.1/gff/TREH.exonerate.gff

# ETRf_pub → TRACKS_DEST/ETRf_pub/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRf/analysis/MAKER/etrf.domains.gff tracks:TRACKS_DEST/ETRf_pub/gff/etrf.domains.gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRf/analysis/MAKER/etrf.evidence.gff tracks:TRACKS_DEST/ETRf_pub/gff/etrf.evidence.gff
# UNRESOLVED: etrf.genes.gff

# ETRf_v1 → /var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAXLI000000000.2/gff
scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRf/analysis/MAKER/etrf.domains.gff tracks:/var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAXLI000000000.2/gff/etrf.domains.gff
scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRf/analysis/MAKER/etrf.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAXLI000000000.2/gff/etrf.evidence.gff
# UNRESOLVED: etrf.genes.gff

# ETRm_pub → TRACKS_DEST/ETRm_pub/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRm/analysis/MAKER/etrm.domains.gff tracks:TRACKS_DEST/ETRm_pub/gff/etrm.domains.gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRm/analysis/MAKER/etrm.evidence.gff tracks:TRACKS_DEST/ETRm_pub/gff/etrm.evidence.gff  # AMBIGUOUS(2 candidates)
# UNRESOLVED: etrm.genes.gff

# ETRm_v1 → /var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAVTP000000000.2/gff
scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRm/analysis/MAKER/etrm.domains.gff tracks:/var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAVTP000000000.2/gff/etrm.domains.gff
scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRm/analysis/MAKER/etrm.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAVTP000000000.2/gff/etrm.evidence.gff  # AMBIGUOUS(2 candidates)
# UNRESOLVED: etrm.genes.gff

# HAR1_v1 → /var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff/FSTL1.exonerate.gff  # AMBIGUOUS(11 candidates)
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff/GYG1.exonerate.gff  # AMBIGUOUS(50 candidates)
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/HAR1.GCF_001890085.1_ASM189008v1_genomic.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff/HAR1.GCF_001890085.1_ASM189008v1_genomic.filtered.gff
# UNRESOLVED: HAR1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff/IGF1.exonerate.gff  # AMBIGUOUS(51 candidates)
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff/PLPPR1.exonerate.gff  # AMBIGUOUS(41 candidates)
scp genomes:/var/other_data/organisms/bat/Hipposideros_armiger/HAR1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_armiger/GCF_001890085.2/gff/TAS1R1.exonerate.gff  # AMBIGUOUS(51 candidates)

# HGA1_v1 → /var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/GYG2.exonerate.gff
# UNRESOLVED: HGA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Hipposideros_galeritus/HGA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Hipposideros_galeritus/GCA_004027415.1/gff/TAS1R1.exonerate.gff

# LBO1_v1 → /var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/KHK.exonerate.gff
# UNRESOLVED: LBO1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/SLC2A1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/SLC2A2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/SLC2A2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Lasiurus_borealis/LBO1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Lasiurus_borealis/GCA_004026805.1/gff/TAS1R2.exonerate.gff

# LNI1_v1 → /var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/LNI1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/LNI1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_nivalis/LNI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_nivalis/Lnivalis_consensus_genome/gff/TREH.exonerate.gff

# LYE1_v1 → /var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/IMPA1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/LYE1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/LYE1.putative_function.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Leptonycteris_yerbabuenae/LYE1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Leptonycteris_yerbabuenae/Lyerbabuenae_genome/gff/TREH.exonerate.gff

# Lric_pub → TRACKS_DEST/Lric_pub/gff
# UNRESOLVED: lric.domains.gff
# UNRESOLVED: lric.evidence.gff
# UNRESOLVED: lric.genes.gff

# Lric_v1 → /var/www/privatehtml/moop/data/tracks/Lampetra_richardsoni/LPT/gff
# UNRESOLVED: lric.domains.gff
# UNRESOLVED: lric.evidence.gff
# UNRESOLVED: lric.genes.gff

# MAU1_v1 → /var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/GHRL.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/GHRL.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/INSR.exonerate.gff
# UNRESOLVED: MAU1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/SLC2A3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/SLC2A3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Murina_aurata_feae/MAU1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Murina_feae/GCA_004026665.1/gff/TAS1R2.exonerate.gff

# MBL1_v1 → /var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/GHRL.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/GHRL.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/LEP.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/LEP.exonerate.gff
# UNRESOLVED: MBL1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/PLPPR1.exonerate.gff
# UNRESOLVED: SI.exonerte.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/SLC2A1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/TREH.exonerate.gff

# MBR1_v1 → /var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff
scp genomes:/var/other_data/organisms/bat/Myotis_brandtii/MBR1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_brandtii/MBR1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_brandtii/MBR1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_brandtii/MBR1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/ITGAD.exonerate.gff  # AMBIGUOUS(13 candidates)
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/LEPR.exonerate.gff  # AMBIGUOUS(20 candidates)
# UNRESOLVED: MBR1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Myotis_brandtii/MBR1/MBR1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/MBR1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Artibeus_jamaicensis/AJA1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/SI.exonerate.gff  # AMBIGUOUS(15 candidates)
scp genomes:/var/other_data/organisms/bat/Myotis_brandtii/MBR1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_brandtii/MBR1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_brandtii/GCF_000412655.1/gff/TAS1R3.exonerate.gff

# MCA1_v1 → /var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/IMPA1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/MC4R.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/MC4R.exonerate.gff
# UNRESOLVED: MCA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/TAS1R2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_californicus/MCA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_californicus/GCA_007922815.1/gff/TREH.exonerate.gff

# MCAP_v1 → /var/www/privatehtml/moop/data/tracks/Montipora_capitata/Mcap_2019/gff
scp genomes:/var/other_data/organisms/coral/Montipora_capitata/Mcap/transcriptomes/kc_mcap_v1/kc_mcap_v1.putative_function.gff tracks:/var/www/privatehtml/moop/data/tracks/Montipora_capitata/Mcap_2019/gff/kc_mcap_v1/kc_mcap_v1.putative_function.gff
# UNRESOLVED: published/Mcap.clean.gff

# MDA1_v1 → /var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/GCG.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/IGF1.exonerate.gff
# UNRESOLVED: MDA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/MDA1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/MDA1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/SLC2A1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_davidii/MDA1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_davidii/GCF_000327345.1/gff/TAS1R3.exonerate.gff

# MHA1_v1 → /var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/IMPA1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/LEPR.exonerate.gff
# UNRESOLVED: MHA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Musonycteris_harrisonii/MHA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Musonycteris_harrisoni/Mharrisoni_consensus_genome/gff/TREH.exonerate.gff

# MHI1_v1 → /var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/LEPR.exonerate.gff
# UNRESOLVED: MHI1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Micronycteris_hirsuta/MHI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Micronycteris_hirsuta/GCA_004026765.1/gff/TREH.exonerate.gff

# MLU1_v1 → /var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/GCG.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/MLU1.ens.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/MLU1.ens.filtered.gff
# UNRESOLVED: MLU1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/MLU1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/MLU1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/TAS1R2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/TAS1R3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_lucifugus/MLU1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_lucifugus/GCF_000147115.1/gff/TREH.exonerate.gff

# MLY1_v1 → /var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff/IGF1.exonerate.gff
# UNRESOLVED: MLY1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Megaderma_lyra/GCA_004026885.1/gff/TAS1R1.exonerate.gff

# MMO1_v1 → /var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/LEPR.exonerate.gff
# UNRESOLVED: MMO1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Mollosus_mollosus/MMO1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Molossus_molossus/GCF_014108415.1/gff/TAS1R1.exonerate.gff

# MMY1_v1 → /var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff
scp genomes:/var/other_data/organisms/bat/Myotis_myotis/MMY1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff/GCG.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_myotis/MMY1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_myotis/MMY1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_myotis/MMY1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff/LEPR.exonerate.gff
# UNRESOLVED: MMY1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Myotis_myotis/MMY1/MMY1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff/MMY1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Myotis_myotis/MMY1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_myotis/MMY1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_myotis/GCF_014108235.1/gff/TAS1R1.exonerate.gff

# MNA1_v1 → /var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/GHRL.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/GHRL.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/GYG2.exonerate.gff  # AMBIGUOUS(33 candidates)
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/LEPR.exonerate.gff
# UNRESOLVED: MNA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/MNA1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/MNA1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_natalensis/GCF_001595765.1/gff/TREH.exonerate.gff

# MSC1_v1 → /var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/LEPR.exonerate.gff
# UNRESOLVED: MSC1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/TAS1R1oma.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/TAS1R1oma.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_schreibersii/MSC1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Miniopterus_schreibersii/GCA_004026525.1/gff/TREH.exonerate.gff

# MSE1_v1 → /var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/GCG.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/GHRL.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/GHRL.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/LEPR.exonerate.gff
# UNRESOLVED: MSE1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Myotis_septentrionalis/MSE1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Myotis_septentrionalis/myse_ont_racon_pilon_HiC/gff/TAS1R1.exonerate.gff

# MSO1_v1 → /var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff
scp genomes:/var/other_data/organisms/bat/Macroglossus_sobrinus/MSO1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff/AACS.exonerate.gff
# UNRESOLVED: AASC.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macroglossus_sobrinus/MSO1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macroglossus_sobrinus/MSO1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff/IGF1.exonerate.gff
# UNRESOLVED: MSO1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Macroglossus_sobrinus/MSO1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macroglossus_sobrinus/MSO1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macroglossus_sobrinus/MSO1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macroglossus_sobrinus/GCA_004027375.1/gff/TREH.exonerate.gff

# MWA1_v1 → /var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/IMPA1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/LEPR.exonerate.gff
# UNRESOLVED: MWA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/TAS1R3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Macrotus_waterhousii/MWA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Macrotus_waterhousii/Mwaterhousii_consensus_genome/gff/TREH.exonerate.gff

# NHU1_v1 → /var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/KHK.exonerate.gff  # AMBIGUOUS(6 candidates)
# UNRESOLVED: NHU1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Nycticeius_humeralis/NHU1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Nycticeius_humeralis/GCA_007922795.1/gff/TAS1R2.exonerate.gff

# NLE1_v1 → /var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/GHRL.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/GHRL.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/LEP.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/LEP.exonerate.gff
# UNRESOLVED: NLE1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Noctilio_leporinus/NLE1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Noctilio_leporinus/GCA_004026585.1/gff/TREH.exonerate.gff

# Nfur_pub_v10 → TRACKS_DEST/Nfur_pub_v10/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/killifish/Nfur/transcriptomes/ensembl/104/Nothobranchius_furzeri.Nfu_20140520.104_fixed_scaffolds.gff3 tracks:TRACKS_DEST/Nfur_pub_v10/gff/ensembl/104/Nothobranchius_furzeri.Nfu_20140520.104_fixed_scaffolds.gff3

# Nfur_v10 → /var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff
scp genomes:/var/other_data/organisms/killifish/Nfur/genomes/Nfu_20150522/analysis/nfingb_tracks/Nfu_20150522.conserved_nc_elements.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Nfu_20150522.conserved_nc_elements.gff3  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/killifish/Nfur/genomes/Nfu_20150522/analysis/nfingb_tracks/Nfu_20150522.dispersed_repeats.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Nfu_20150522.dispersed_repeats.gff3  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/killifish/Nfur/genomes/Nfu_20150522/analysis/nfingb_tracks/Nfu_20150522.mirna.smr.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Nfu_20150522.mirna.smr.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/genomes/Nfu_20150522/analysis/nfingb_tracks/Nfu_20150522.tandem_repeats.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Nfu_20150522.tandem_repeats.gff3  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/killifish/Nfur/transcriptomes/ensembl/104/Nothobranchius_furzeri.Nfu_20140520.104_fixed_scaffolds.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/ensembl/104/Nothobranchius_furzeri.Nfu_20140520.104_fixed_scaffolds.gff3

# PAL1_v1 → /var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff/GYG1.exonerate.gff  # AMBIGUOUS(50 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff/IGF1.exonerate.gff  # AMBIGUOUS(51 candidates)
scp genomes:/var/other_data/organisms/bat/Pteropus_alecto/PAL1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff/INS.exonerate.gff
# UNRESOLVED: PAL1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_alecto/PAL1/PAL1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff/PAL1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_alecto/PAL1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_alecto/GCF_000325575.1/gff/TAS1R1.exonerate.gff  # AMBIGUOUS(51 candidates)

# PDI1_v1 → /var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/INSR.exonerate.gff
# UNRESOLVED: PDI1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/PDI1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/PDI1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_discolor/PDI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_discolor/GCF_004126475.2/gff/TREH.exonerate.gff

# PGI1_v1 → /var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff
scp genomes:/var/other_data/organisms/bat/Miniopterus_natalensis/MNA1/analysis/exonerate/GHRL.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff/GHRL.exonerate.gff  # AMBIGUOUS(7 candidates)
scp genomes:/var/other_data/organisms/bat/Pteropus_giganteus/PGI1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_giganteus/PGI1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_giganteus/PGI1/analysis/exonerate/KHK.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff/KHK.exonerate.gff
# UNRESOLVED: PGI1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_giganteus/PGI1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_giganteus/PGI1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_medius/GCF_902729225.1/gff/TAS1R1.exonerate.gff

# PHA1_v1 → /var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/MGAM.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/MGAM.exonerate.gff
# UNRESOLVED: PHA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Phyllostomus_hastatus/PHA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Phyllostomus_hastatus/GCF_019186645.2/gff/TREH.exonerate.gff

# PKU1_v1 → /var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/GHRL.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/GHRL.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/INS.exonerate.gff
# UNRESOLVED: PKU1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/PKU1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/PKU1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/SLC2A3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/SLC2A3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_kuhlii/PKU1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_kuhlii/GCF_014108245.1/gff/TAS1R2.exonerate.gff

# PPA1_v1 → /var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/IMPA1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/PLIN1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/PLPPR1.exonerate.gff
# UNRESOLVED: PPA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/SLC2A1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/SLC2A5.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/SLC2A5.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/TAS1R3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteronotus_parnellii/PPA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteronotus_mesoamericanus/GCF_021234165.1/gff/TREH.exonerate.gff

# PPI1_v1 → /var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/INSR.exonerate.gff
# UNRESOLVED: PPA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/SLC2A3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/SLC2A3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/TAS1R3.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pipistrellus_pipistrellus/PPI1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pipistrellus_pipistrellus/GCA_004026625.1/gff/TREH.exonerate.gff

# PRU1_v1 → /var/www/privatehtml/moop/data/tracks/Pteropus_rufus/Pteropus_rufus_HiC/gff
scp genomes:/var/other_data/organisms/bat/Pteropus_rufus/PRU1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_rufus/Pteropus_rufus_HiC/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_rufus/PRU1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_rufus/Pteropus_rufus_HiC/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_rufus/PRU1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_rufus/Pteropus_rufus_HiC/gff/PLPPR1.exonerate.gff
# UNRESOLVED: PRU1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_rufus/PRU1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_rufus/Pteropus_rufus_HiC/gff/TAS1R1.exonerate.gff

# PVA1_v1 → /var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/AACS.exonerate.gff  # AMBIGUOUS(17 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/ACACB.exonerate.gff  # AMBIGUOUS(22 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/FASN.exonerate.gff  # AMBIGUOUS(16 candidates)
scp genomes:/var/other_data/organisms/bat/Craseonycteris_thonglongyai/CTH1/analysis/exonerate/GCG.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/GCG.exonerate.gff  # AMBIGUOUS(8 candidates)
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/GYG1.exonerate.gff  # AMBIGUOUS(50 candidates)
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/IGF1.exonerate.gff  # AMBIGUOUS(51 candidates)
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/INSR.exonerate.gff  # AMBIGUOUS(29 candidates)
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/LEP.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/LEP.exonerate.gff  # AMBIGUOUS(5 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/PLIN1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/PLIN1.exonerate.gff  # AMBIGUOUS(14 candidates)
scp genomes:/var/other_data/organisms/bat/Anoura_caudifer/ACA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/PLPPR1.exonerate.gff  # AMBIGUOUS(41 candidates)
scp genomes:/var/other_data/organisms/bat/Pteropus_vampyrus/PVA1/PVA1.ens.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/PVA1.ens.filtered.gff
# UNRESOLVED: PVA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Pteropus_vampyrus/PVA1/PVA1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/PVA1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Carollia_perspicillata/CPE1/analysis/exonerate/SLC2A1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/SLC2A1.exonerate.gff  # AMBIGUOUS(8 candidates)
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/SLC2A2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/SLC2A2.exonerate.gff  # AMBIGUOUS(7 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/SLC2A3.exonerate.gff  # AMBIGUOUS(7 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/SLC2A4.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/SLC2A4.exonerate.gff  # AMBIGUOUS(4 candidates)
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/TAS1R1.exonerate.gff  # AMBIGUOUS(51 candidates)
scp genomes:/var/other_data/organisms/bat/Antrozous_pallidus/APA1/analysis/exonerate/TAS1R2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Pteropus_vampyrus/GCF_000151845.1/gff/TAS1R2.exonerate.gff  # AMBIGUOUS(13 candidates)

# Pcan_refseq_v1 → /var/www/privatehtml/moop/data/tracks/Pomacea_canaliculata/GCF_003073045.1/gff
scp genomes:/var/other_data/organisms/snail/Pcan/genomes/refseq_v1/analysis/qRTPCRprimers/primers.2025052758.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Pomacea_canaliculata/GCF_003073045.1/gff/organisms/snail/Pcan/genomes/refseq_v1/analysis/qRTPCRprimers/primers.2025052758.gff3

# RAE1_v1 → /var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/PLPPR1.exonerate.gff
# UNRESOLVED: RAE1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/RAE1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/RAE1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rousettus_aegyptiacus/RAE1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_aegyptiacus/GCF_014176215.1/gff/TREH.exonerate.gff

# RFE1_v1 → /var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff
scp genomes:/var/other_data/organisms/bat/Rhinolophus_ferrumequinum/RFE1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rhinolophus_ferrumequinum/RFE1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rhinolophus_ferrumequinum/RFE1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff/PLPPR1.exonerate.gff
# UNRESOLVED: RFE1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Rhinolophus_ferrumequinum/RFE1/RFE1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff/RFE1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Rhinolophus_ferrumequinum/RFE1/analysis/exonerate/SLC2A2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff/SLC2A2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rhinolophus_ferrumequinum/RFE1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Rhinolophus_ferrumequinum/RFE1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rhinolophus_ferrumequinum/GCF_004115265.2/gff/TREH.exonerate.gff

# RMA1_v1 → /var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/ACACB.exonerate.gff  # AMBIGUOUS(22 candidates)
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/GYG1.exonerate.gff  # AMBIGUOUS(50 candidates)
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/GYG2.exonerate.gff  # AMBIGUOUS(33 candidates)
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/IGF1.exonerate.gff  # AMBIGUOUS(51 candidates)
scp genomes:/var/other_data/organisms/bat/Rousettus_madagascariensis/RMA1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/PLPPR1.exonerate.gff  # AMBIGUOUS(41 candidates)
# UNRESOLVED: RMA1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Megaderma_lyra/MLY1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/TAS1R1.exonerate.gff  # AMBIGUOUS(51 candidates)
scp genomes:/var/other_data/organisms/bat/Rousettus_madagascariensis/RMA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Rousettus_madagascariensis/GCA_028533395.1/gff/TREH.exonerate.gff

# SCR1_v1 → TRACKS_DEST/SCR1_v1/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/yeast/Schizosaccharomyces_cryophilus/SCR1/SCR1.putative_function.gff tracks:TRACKS_DEST/SCR1_v1/gff/SCR1.putative_function.gff

# SHO1_v1 → /var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/IMPA1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/IMPA1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/INS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/ITGAD.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/ITGAD.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/LEPR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/LEPR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/MC4R.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/MC4R.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/PLPPR1.exonerate.gff
# UNRESOLVED: SHO1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/SHO1.refseq.filtered.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/SHO1.refseq.filtered.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/SI.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/SI.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Sturnira_hondurensis/SHO1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Sturnira_hondurensis/GCF_014824575.3/gff/TREH.exonerate.gff

# SOC1_v1 → TRACKS_DEST/SOC1_v1/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/yeast/Schizosaccharomyces_octosporus/SOC1/SOC1.putative_function.gff tracks:TRACKS_DEST/SOC1_v1/gff/SOC1.putative_function.gff

# SOS1_v1 → TRACKS_DEST/SOS1_v1/gff
# UNRESOLVED: SOS1.putative_function.all.gff

# Scal100_pub → TRACKS_DEST/Scal100_pub/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/nematostella/Scal/genomes/Scal100/aligned/NY_Scal100_v1/NY_Scal100_v1.20200813.gff tracks:TRACKS_DEST/Scal100_pub/gff/NY_Scal100_v1.20200813.gff
# UNRESOLVED: aligned/ucne/sc_ucne_blat.gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/nematostella/Scal/genomes/Scal100/aligned/ucne/sc_ucne.gff tracks:TRACKS_DEST/Scal100_pub/gff/sc_ucne.gff

# Scal100_v1 → /var/www/privatehtml/moop/data/tracks/Scolanthus_callimorphus/Scal100/gff
scp genomes:/var/other_data/organisms/nematostella/Scal/genomes/Scal100/aligned/NY_Scal100_v1/NY_Scal100_v1.20200813.gff tracks:/var/www/privatehtml/moop/data/tracks/Scolanthus_callimorphus/Scal100/gff/NY_Scal100_v1.20200813.gff
# UNRESOLVED: aligned/ucne/sc_ucne_blat.gff
scp genomes:/var/other_data/organisms/nematostella/Scal/genomes/Scal100/aligned/ucne/sc_ucne.gff tracks:/var/www/privatehtml/moop/data/tracks/Scolanthus_callimorphus/Scal100/gff/sc_ucne.gff

# SmedSxl_schMedS3_h1_internal → /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff
# UNRESOLVED: RosettaStone/genome_based_mapping/dd_Smed_v4.sorted.gff
# UNRESOLVED: RosettaStone/genome_based_mapping/dd_Smed_v6.sorted.gff
# UNRESOLVED: RosettaStone/genome_based_mapping/fgm_screen.sorted.gff
# UNRESOLVED: RosettaStone/genome_based_mapping/smed_20140614.sorted.gff
# UNRESOLVED: RosettaStone/genome_based_mapping/smest.sorted.gff
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_GPL14150_gene_models.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_GPL14150_gene_models.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_PRJNA12585.WBPS16.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_PRJNA12585.WBPS16.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_S2F19H1_WBPS19.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_S2F19H1_WBPS19.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_S2F19H2_WBPS19.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_S2F19H2_WBPS19.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_SmedAsxl_pearson_GAKN01.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_SmedAsxl_pearson_GAKN01.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_SmedAsxl_ww_GCZZ01.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_SmedAsxl_ww_GCZZ01.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_SmedSxl_ww_GDAG01.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_SmedSxl_ww_GDAG01.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_be_Smed_v2.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_be_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_bo_Smed_v1.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_bo_Smed_v1.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smed_v4.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_dd_Smed_v4.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smed_v6.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_dd_Smed_v6.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smes_v1.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_dd_Smes_v1.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smes_v2.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_dd_Smes_v2.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_ka_Smed_v1.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_ka_Smed_v1.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_mu_Smed_v1.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_mu_Smed_v1.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_newmark_ests.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_newmark_ests.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_ox_Smed_v2.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_ox_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_smed_20140614.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_smed_20140614.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_smed_ncbi_20240424.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_smed_ncbi_20240424.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_to_Smed_v2.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_to_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_uc_Smed_v2.markedup.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/RosettaStone/version_2/h2_uc_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/archived/schMedS3_h1.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/schMedS3_h1.gff  # AMBIGUOUS(2 candidates)
# UNRESOLVED: schMedS3_h1/analysis/RTPCR/primers.202404223.gff3

# SmedSxl_schMedS3_h2_internal → TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_GPL14150_gene_models.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_GPL14150_gene_models.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_PRJNA12585.WBPS16.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_PRJNA12585.WBPS16.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_S2F19H1_WBPS19.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_S2F19H1_WBPS19.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_S2F19H2_WBPS19.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_S2F19H2_WBPS19.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_SmedAsxl_pearson_GAKN01.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_SmedAsxl_pearson_GAKN01.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_SmedAsxl_ww_GCZZ01.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_SmedAsxl_ww_GCZZ01.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_SmedSxl_ww_GDAG01.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_SmedSxl_ww_GDAG01.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_be_Smed_v2.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_be_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_bo_Smed_v1.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_bo_Smed_v1.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smed_v4.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_dd_Smed_v4.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smed_v6.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_dd_Smed_v6.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smes_v1.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_dd_Smes_v1.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_dd_Smes_v2.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_dd_Smes_v2.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_ka_Smed_v1.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_ka_Smed_v1.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_mu_Smed_v1.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_mu_Smed_v1.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_newmark_ests.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_newmark_ests.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_ox_Smed_v2.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_ox_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_smed_20140614.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_smed_20140614.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_smed_ncbi_20240424.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_smed_ncbi_20240424.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_to_Smed_v2.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_to_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/analysis/RosettaStone/version_2/20240429/h2_uc_Smed_v2.markedup.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/RosettaStone/version_2/h2_uc_Smed_v2.markedup.gff  # AMBIGUOUS(2 candidates)
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h2/archive/schMedS3_h2.gff tracks:TRACKS_DEST/SmedSxl_schMedS3_h2_internal/gff/schMedS3_h2.gff  # AMBIGUOUS(2 candidates)

# SmedSxl_smed_chr_ref_v1_internal → TRACKS_DEST/SmedSxl_smed_chr_ref_v1_internal/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/smed_chr_ref_v1/analysis/liftover/smes_v2_hconf_chr_ref_c1_genes_no_unmap_parents.gff3 tracks:TRACKS_DEST/SmedSxl_smed_chr_ref_v1_internal/gff/liftover/smes_v2_hconf_chr_ref_c1_genes_no_unmap_parents.gff3
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/smed_chr_ref_v1/analysis/repeatmasking/smed_chr_ref_v1.all_repeatmasking.gff tracks:TRACKS_DEST/SmedSxl_smed_chr_ref_v1_internal/gff/repeatmasking/smed_chr_ref_v1.all_repeatmasking.gff

# SmedSxl_smed_chr_ref_v1_pub → TRACKS_DEST/SmedSxl_smed_chr_ref_v1_pub/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/smed_chr_ref_v1/analysis/liftover/smes_v2_hconf_chr_ref_c1_genes_no_unmap_parents.gff3 tracks:TRACKS_DEST/SmedSxl_smed_chr_ref_v1_pub/gff/liftover/smes_v2_hconf_chr_ref_c1_genes_no_unmap_parents.gff3
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/smed_chr_ref_v1/analysis/repeatmasking/smed_chr_ref_v1.all_repeatmasking.gff tracks:TRACKS_DEST/SmedSxl_smed_chr_ref_v1_pub/gff/repeatmasking/smed_chr_ref_v1.all_repeatmasking.gff

# SmedSxl_v31 → /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/SmedSxl_v3.1/analysis/MAKER_082015/SmedSxl_genome_v3.1.maker_v5.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff/MAKER_082015/SmedSxl_genome_v3.1.maker_v5.gff
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/SmedSxl_v3.1/smedgd_dump/ejr_smedgd_v314.20210618.dump.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff/smedgd_dump/ejr_smedgd_v314.20210618.dump.gff

# TBR1_v1 → /var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/AACS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/AACS.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/ACACB.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/ACACB.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/FASN.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/FASN.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/FSTL1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/FSTL1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Aeorestes_cinereus/ACI1/analysis/exonerate/INS.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/INS.exonerate.gff  # AMBIGUOUS(24 candidates)
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/TAS1R1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/TAS1R3.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/TAS1R3.exonerate.gff
# UNRESOLVED: TBR1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Tadarida_brasiliensis/TBR1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tadarida_brasiliensis/GCA_004025005.1/gff/TREH.exonerate.gff

# TSA1_v1 → /var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff
scp genomes:/var/other_data/organisms/bat/Tonatia_saurophila/TSA1/analysis/exonerate/GYG1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff/GYG1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tonatia_saurophila/TSA1/analysis/exonerate/GYG2.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff/GYG2.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tonatia_saurophila/TSA1/analysis/exonerate/IGF1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff/IGF1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tonatia_saurophila/TSA1/analysis/exonerate/INSR.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff/INSR.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tonatia_saurophila/TSA1/analysis/exonerate/PLPPR1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff/PLPPR1.exonerate.gff
scp genomes:/var/other_data/organisms/bat/Tonatia_saurophila/TSA1/analysis/exonerate/TAS1R1.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff/TAS1R1.exonerate.gff
# UNRESOLVED: TBR1.putative_function.all.gff
scp genomes:/var/other_data/organisms/bat/Tonatia_saurophila/TSA1/analysis/exonerate/TREH.exonerate.gff tracks:/var/www/privatehtml/moop/data/tracks/Tonatia_saurophila/GCA_004024845.1/gff/TREH.exonerate.gff

# kPetMar1_pub → /var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_cross_species_tx_alns.chr.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_cross_species_tx_alns.chr.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_genomic.chr.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_genomic.chr.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_same_species_tx_alns.chr.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_same_species_tx_alns.chr.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/MAKER/pmz.domains.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/pmz.domains.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/MAKER/pmz.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/pmz.evidence.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/MAKER/pmz.genes.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/pmz.genes.gff

# kPetMar1_v1 → /var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/PM_new_annotations_2023/PM.gene_annot_2023.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/PM_new_annotations_2023/PM.gene_annot_2023.gff3
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_cross_species_tx_alns.chr.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_cross_species_tx_alns.chr.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_genomic.chr.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_genomic.chr.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_same_species_tx_alns.chr.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/RefSeq_annotations/GCF_010993605.1_kPetMar1.pri_same_species_tx_alns.chr.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/liftoff_apollo/ApolloAnnotations_lifted_to_kPetMar.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/liftoff_apollo/ApolloAnnotations_lifted_to_kPetMar.gff3
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/MAKER/pmz.domains.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/pmz.domains.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/MAKER/pmz.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/pmz.evidence.gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/MAKER/pmz.genes.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/pmz.genes.gff

# killifish_pub → TRACKS_DEST/killifish_pub/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/killifish/Nfur/transcriptomes/ensembl/104/Nothobranchius_furzeri.Nfu_20140520.104_fixed_scaffolds.gff3 tracks:TRACKS_DEST/killifish_pub/gff/ensembl/104/Nothobranchius_furzeri.Nfu_20140520.104_fixed_scaffolds.gff3

# smedsxlv31_pub → TRACKS_DEST/smedsxlv31_pub/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/SmedSxl_v3.1/analysis/MAKER_082015/SmedSxl_genome_v3.1.maker_v5.gff tracks:TRACKS_DEST/smedsxlv31_pub/gff/MAKER_082015/SmedSxl_genome_v3.1.maker_v5.gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/SmedSxl_v3.1/smedgd_dump/ejr_smedgd_v314.20210618.dump.gff tracks:TRACKS_DEST/smedsxlv31_pub/gff/smedgd_dump/ejr_smedgd_v314.20210618.dump.gff

# wormanemone_pub → TRACKS_DEST/wormanemone_pub/gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/nematostella/Scal/genomes/Scal100/aligned/NY_Scal100_v1/NY_Scal100_v1.20200813.gff tracks:TRACKS_DEST/wormanemone_pub/gff/NY_Scal100_v1.20200813.gff
# UNRESOLVED: aligned/ucne/sc_ucne_blat.gff
# UNMAPPED_ORG: scp genomes:/var/other_data/organisms/nematostella/Scal/genomes/Scal100/aligned/ucne/sc_ucne.gff tracks:TRACKS_DEST/wormanemone_pub/gff/sc_ucne.gff
