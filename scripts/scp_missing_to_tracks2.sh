#!/bin/bash
# Second round: copy files found in genomes_find_results2.txt to tracks server.
# Run from cerebro.

# ── Montipora_capitata / Mcap_2019 ───────────────────────────────────────────
# Metadata FILENAME: published/Mcap.clean.gff (actual file is Mcap.clean.putative_function.gff)
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Montipora_capitata/Mcap_2019/gff/published
scp genomes:/var/other_data/organisms/coral/Montipora_capitata/Mcap/transcriptomes/published/Mcap.clean.putative_function.gff \
    tracks:/var/www/privatehtml/moop/data/tracks/Montipora_capitata/Mcap_2019/gff/published/Mcap.clean.gff

# ── Lampetra_richardsoni / LPT ───────────────────────────────────────────────
# Evidence (metadata FILENAME: lric.evidence.gff; actual: lpt.evidence.gff)
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Lampetra_richardsoni/LPT/gff
scp genomes:/var/other_data/organisms/lamprey/Lric/genomes/LPT/analysis/MAKER/lpt.evidence.gff \
    tracks:/var/www/privatehtml/moop/data/tracks/Lampetra_richardsoni/LPT/gff/lric.evidence.gff
# Domains (metadata FILENAME: lric.domains.gff; actual: lpt.domains.gff)
scp genomes:/var/other_data/organisms/lamprey/Lric/genomes/LPT/analysis/MAKER/lpt.domains.gff \
    tracks:/var/www/privatehtml/moop/data/tracks/Lampetra_richardsoni/LPT/gff/lric.domains.gff

# ── Mormoops_blainvillei / GCA_004026545.1 ───────────────────────────────────
# Metadata FILENAME: SI.exonerte.gff (typo for SI.exonerate.gff)
ssh tracks mkdir -p /var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff
scp genomes:/var/other_data/organisms/bat/Mormoops_blainvillei/MBL1/analysis/exonerate/SI.exonerate.gff \
    tracks:/var/www/privatehtml/moop/data/tracks/Mormoops_blainvillei/GCA_004026545.1/gff/SI.exonerte.gff
