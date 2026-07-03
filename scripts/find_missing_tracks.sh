#!/bin/bash
# Find missing track files on genomes server.
# Run on genomes (or via ssh), save output as genomes_find_results.txt

echo "=== Chamaeleo_calyptratus ==="
find /var/other_data/organisms/chameleon -type f \( -name 'dovetail.est2genome.evidence.gff' -o -name 'evidence.tar.gz' -o -name 'rattlesnake_mytree.summary.bb' \)

echo "=== Danio_rerio ==="
find /var/other_data/organisms/zebrafish/Drer -type f \( -name 'Danio_rerio.GRCz11.dna.toplevel.fa' -o -name 'all_projected_on_nf.bb' -o -name 'fish_projected_on_nf.bb' \)

echo "=== Entosphenus_tridentatus ==="
find /var/other_data/organisms/lamprey/Etri -type f \( -name 'etrf.evidence.gff' -o -name 'etrm.evidence.gff' \)

echo "=== Lampetra_richardsoni ==="
find /var/other_data/organisms/lamprey -type f \( -name 'lric.domains.gff' -o -name 'lric.evidence.gff' \)

echo "=== Lasiurus_cinereus ==="
find /var/other_data/organisms/bat/Lasiurus_cinereus -type f -name 'ACI1.putative_function.gff'

echo "=== Macroglossus_sobrinus ==="
find /var/other_data/organisms/bat/Macroglossus_sobrinus -type f -name 'AASC.exonerate.gff'

echo "=== Montipora_capitata ==="
find /var/other_data/organisms/coral/Montipora_capitata -type f -name 'Mcap.clean.gff'

echo "=== Mormoops_blainvillei ==="
find /var/other_data/organisms/bat/Mormoops_blainvillei -type f -name 'SI.exonerte.gff'

echo "=== Nematostella_vectensis ==="
find /var/other_data/organisms/nematostella/Nvec -type f \( -name 'NV2g.20240221.gff' -o -name 'NVEC200.domains.renamed.gff' -o -name 'NVEC200.evidence.renamed.gff' -o -name 'NVEC200.fasta' \)

echo "=== Nothobranchius_furzeri ==="
find /var/other_data/organisms/killifish/Nfur -type f \( -name '0dpa_H3K27ac.filt.2.rpm.smoothed.101.bw' -o -name 'GCF_001465895.1_Nfu_20140520_gnomon_model_scafRenamed.gff' -o -name 'Homeostasis_consensus_renamed.fasta.transdecoder.cds.cdhit.gene.gff3' -o -name 'MAKER' -o -name 'Nfu_20150522_plus_MT.fa' -o -name 'Regeneration_consensus.gene.gff3' -o -name 'Regeneration_consensus_renamed.fasta.transdecoder.cds.cdhit.gene.gff3' -o -name 'Trinity_filtered.fa.transdecoder.cds.cdhit.gene.gff3' -o -name 'est2genome.gff3' -o -name 'medaka.protein2genome.gff3' -o -name 'pacbio_isoforms_1.gff3' -o -name 'ref_Nfu_20140520_top_level.renamed.gff3' -o -name 'refseq_r100_plus_wew_08102017_curated_renamed_PPS_CKHU.gff3' -o -name 'regen_homeostasis.cdhit.gene.gff3' -o -name 'regen_homeostasis_trinity.cdhit.gene.gff3' -o -name 'renamed_isoforms.gff3' -o -name 'renamed_nanopore_isoforms' -o -name 'renamed_nanopore_isoforms.gff3' -o -name 'swissprot.protein2genome.gff3' -o -name 'zebrafish.protein2genome.gff3' \)

echo "=== Petromyzon_marinus ==="
find /var/other_data/organisms/lamprey/Pmar -type f \( -name 'R1_8_noan.l22-34.fas.map.weighted-10000-1000-b--1.rescore.JB.bed' -o -name 'pmz.evidence.gff' \)

echo "=== Phyllostomus_discolor ==="
find /var/other_data/organisms/bat/Phyllostomus_discolor -type f \( -name 'PDI1.genome.filtered.fa' -o -name 'PDI1.putative_function.all.gff' \)

echo "=== Pomacea_canaliculata ==="
find /var/other_data/organisms/snail/Pcan -type f \( -name '2025-May' -o -name 'Pca_v1.1.maker_refined.transcripts.gff' -o -name 'Pcan_20160318.gff' -o -name 'Pomc_transcripts.gff' \)

echo "=== Pteropus_medius ==="
find /var/other_data/organisms/bat/Pteropus_giganteus -type f -name 'PGI1.putative_function.all.gff'

echo "=== Schmidtea_mediterranea ==="
find /var/other_data/organisms/planaria/Smed -type f \( -name 'Irr_1a.bw' -o -name 'Irr_1b.bw' -o -name 'Irr_1c.bw' -o -name 'Irr_2a.bw' -o -name 'Irr_2b.bw' -o -name 'Irr_2c.bw' -o -name 'Irr_3a.bw' -o -name 'Irr_3b.bw' -o -name 'Irr_3c.bw' -o -name 'SmedSxl_genome_v3.1.maker_v5.gff' -o -name 'SmedSxl_genome_v3.1.nt' -o -name 'WT_1a.bw' -o -name 'WT_1b.bw' -o -name 'WT_1c.bw' -o -name 'WT_2a.bw' -o -name 'WT_2c.bw' -o -name 'WT_3a.bw' -o -name 'WT_3b.bw' -o -name 'WT_3c.bw' -o -name 'X1_1.bw' -o -name 'X1_2.bw' -o -name 'X2_1.bw' -o -name 'X2_2.bw' -o -name 'Xins_1.bw' -o -name 'Xins_2.bw' -o -name 'dd_Smed_v4.sorted.gff' -o -name 'dd_Smed_v6.sorted.gff' -o -name 'ejr_smedgd_v314.20210618.dump.gff' -o -name 'fgm_screen.sorted.gff' -o -name 'find' -o -name 'primers.202404223.gff3' -o -name 'smed_20140614.sorted.gff' -o -name 'smest.sorted.gff' \)

echo "=== Scolanthus_callimorphus ==="
find /var/other_data/organisms/nematostella/Scal -type f -name 'sc_ucne_blat.gff'

