#!/bin/bash
# SCP missing track files from genomes to tracks server.
# Run on cerebro (or any host with ssh access to both genomes and tracks).

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff
scp genomes:/var/other_data/organisms/chameleon/Chamaeleo_calyptratus/CCA1/evidence/dovetail.est2genome.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Chamaeleo_calyptratus/CCA1/gff/dovetail.est2genome.evidence.gff

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAVTP000000000.2/gff
scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRm/analysis/MAKER/evidence/etrm.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAVTP000000000.2/gff/etrm.evidence.gff

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAXLI000000000.2/gff
scp genomes:/var/other_data/organisms/lamprey/Etri/genomes/ETRf/analysis/MAKER/etrf.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Entosphenus_tridentatus/JAAXLI000000000.2/gff/etrf.evidence.gff

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/NV2/aligned/tcs_v2/20240221
scp genomes:/var/other_data/organisms/nematostella/Nvec/genomes/Nvec200/aligned/tcs_v2/20240221/NV2g.20240221.gff tracks:/var/www/privatehtml/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/NV2/aligned/tcs_v2/20240221/NV2g.20240221.gff

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/Isoforms_ejr/tracks/Homeostasis_consensus_renamed.fasta.transdecoder.cds.cdhit.gene.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Homeostasis_consensus_renamed.fasta.transdecoder.cds.cdhit.gene.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/Isoforms_ejr/tracks/Regeneration_consensus.gene.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Regeneration_consensus.gene.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/Isoforms_ejr/tracks/Regeneration_consensus_renamed.fasta.transdecoder.cds.cdhit.gene.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Regeneration_consensus_renamed.fasta.transdecoder.cds.cdhit.gene.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/Isoforms_ejr/tracks/Trinity_filtered.fa.transdecoder.cds.cdhit.gene.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/Trinity_filtered.fa.transdecoder.cds.cdhit.gene.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/Isoforms_ejr/tracks/regen_homeostasis.cdhit.gene.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/regen_homeostasis.cdhit.gene.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/Isoforms_ejr/tracks/regen_homeostasis_trinity.cdhit.gene.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/regen_homeostasis_trinity.cdhit.gene.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/Isoforms_ejr/tracks/renamed_nanopore_isoforms.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/renamed_nanopore_isoforms.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/MAKER/est2genome.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/est2genome.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/MAKER/medaka.protein2genome.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/medaka.protein2genome.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/MAKER/swissprot.protein2genome.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/swissprot.protein2genome.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/MAKER/zebrafish.protein2genome.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/zebrafish.protein2genome.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/analysis/refseq/GCF_001465895.1_Nfu_20140520_gnomon_model_scafRenamed.gff tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/GCF_001465895.1_Nfu_20140520_gnomon_model_scafRenamed.gff
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/transcriptomes/PPS_CKHU/refseq_r100_plus_wew_08102017_curated_renamed_PPS_CKHU.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/refseq_r100_plus_wew_08102017_curated_renamed_PPS_CKHU.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/transcriptomes/isoforms_ejr_10172017/renamed_isoforms.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/renamed_isoforms.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/transcriptomes/pacBio/pacbio_isoforms_1.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/pacbio_isoforms_1.gff3
scp genomes:/var/other_data/organisms/killifish/Nfur/wei/gff/transcriptomes/refseq_r100/ref_Nfu_20140520_top_level.renamed.gff3 tracks:/var/www/privatehtml/moop/data/tracks/Nothobranchius_furzeri/GCF_001465895.1/gff/ref_Nfu_20140520_top_level.renamed.gff3

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff
scp genomes:/var/other_data/organisms/lamprey/Pmar/genomes/PMZ/analysis/MAKER/pmz.evidence.gff tracks:/var/www/privatehtml/moop/data/tracks/Petromyzon_marinus/GCF_010993605.1/gff/pmz.evidence.gff

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Pomacea_canaliculata/GCF_003073045.1/gff
scp genomes:/var/other_data/organisms/snail/Pcan/genomes/refseq_v1/aligned/EJR_Maker/Pomc_transcripts.gff tracks:/var/www/privatehtml/moop/data/tracks/Pomacea_canaliculata/GCF_003073045.1/gff/Pomc_transcripts.gff
scp genomes:/var/other_data/organisms/snail/Pcan/genomes/refseq_v1/aligned/HK_Maker/Pca_v1.1.maker_refined.transcripts.gff tracks:/var/www/privatehtml/moop/data/tracks/Pomacea_canaliculata/GCF_003073045.1/gff/Pca_v1.1.maker_refined.transcripts.gff
scp genomes:/var/other_data/organisms/snail/Pcan/genomes/refseq_v1/aligned/Trinity_20160318/Pcan_20160318.gff tracks:/var/www/privatehtml/moop/data/tracks/Pomacea_canaliculata/GCF_003073045.1/gff/Pcan_20160318.gff

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/SmedSxl_v3.1/analysis/MAKER_082015/SmedSxl_genome_v3.1.maker_v5.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff/SmedSxl_genome_v3.1.maker_v5.gff
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/SmedSxl_v3.1/smedgd_dump/ejr_smedgd_v314.20210618.dump.gff tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/GCA_000691995.1/gff/ejr_smedgd_v314.20210618.dump.gff

ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_1a.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_1a.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_1b.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_1b.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_1c.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_1c.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_2a.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_2a.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_2b.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_2b.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_2c.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_2c.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_3a.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_3a.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_3b.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_3b.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/Irr_3c.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Irr_3c.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_1a.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_1a.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_1b.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_1b.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_1c.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_1c.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_2a.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_2a.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_2c.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_2c.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_3a.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_3a.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_3b.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_3b.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Ivankovíc_2024/WT_3c.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/WT_3c.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Neiro_2022/X1_1.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/X1_1.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Neiro_2022/X1_2.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/X1_2.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Neiro_2022/X2_1.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/X2_1.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Neiro_2022/X2_2.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/X2_2.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Neiro_2022/Xins_1.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Xins_1.bw
scp genomes:/var/other_data/organisms/planaria/Smed/SmedSxl/genomes/schMedS3_h1/analysis/Neiro_2022/Xins_2.bw tracks:/var/www/privatehtml/moop/data/tracks/Schmidtea_mediterranea/schMedS3h1/gff/Xins_2.bw

