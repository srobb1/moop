#!/bin/bash
# Second round of find commands for files not found in round 1.
# Run on genomes server: ssh genomes 'bash -s' < scripts/find_missing_tracks2.sh > genomes_find_results2.txt

echo "=== Montipora_capitata ==="
find /var/other_data/organisms/coral/Montipora_capitata -type f \
  \( -name 'Mcap.clean.putative_function.gff' \
  -o -name 'kc_mcap_v1.putative_function.gff' \
  -o -name 'Montipora_capitata_HIv3.names.gff' \)

echo "=== Lampetra_richardsoni ==="
find /var/other_data/organisms/lamprey/Lric -type f \
  \( -name 'lpt.domains.gff' \
  -o -name 'lric.domains.gff' \
  -o -name 'lpt.evidence.gff' \
  -o -name 'lric.evidence.gff' \)

echo "=== Mormoops_blainvillei ==="
find /var/other_data/organisms/bat/Mormoops_blainvillei -type f \
  \( -name 'SI.exonerte.gff' -o -name 'SI.exonerate.gff' -o -name 'MBL*.gff' \)

echo "=== Macroglossus_sobrinus ==="
find /var/other_data/organisms/bat/Macroglossus_sobrinus -type f \
  \( -name 'AASC.exonerate.gff' -o -name 'MSO*.gff' \)

echo "=== Scolanthus_callimorphus ==="
find /var/other_data/organisms/Scolanthus -type f -name 'sc_ucne_blat.gff' 2>/dev/null
find /var/other_data/organisms/scolanthus -type f -name 'sc_ucne_blat.gff' 2>/dev/null

echo "=== Phyllostomus_discolor ==="
find /var/other_data/organisms/bat/Phyllostomus_discolor -type f \
  \( -name 'PDI1.putative_function.all.gff' -o -name 'PDI1*.all.gff' \)
