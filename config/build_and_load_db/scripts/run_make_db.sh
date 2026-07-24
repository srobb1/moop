
REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")

ORGANISMS=(
  Amphimedon_queenslandica
  Anoura_caudifer
  Antrozous_pallidus
  Artibeus_jamaicensis
  Bradypodion_pumilum
  Bradypodion_ventrale
  Bradyrhizobium_diazoefficiens
  Carollia_perspicillata
  Chamaeleo_calyptratus
  Craseonycteris_thonglongyai
  Cynopterus_brachyotis
  Desmodus_rotundus
  Eidolon_dupreanum
  Eidolon_helvum
  Eonycteris_spelaea
  Eptesicus_fuscus
  Furcifer_pardalis
  Hipposideros_armiger
  Hipposideros_galeritus
  Lasiurus_borealis
  Lasiurus_cinereus
  Leptonycteris_nivalis
  Leptonycteris_yerbabuenae
  Macroglossus_sobrinus
  Macrotus_californicus
  Macrotus_waterhousii
  Medicago_sativa
  Medicago_truncatula
  Megaderma_lyra
  Micronycteris_hirsuta
  Miniopterus_natalensis
  Miniopterus_schreibersii
  Mollosus_mollosus
  Montipora_capitata
  Mormoops_blainvillei
  Murina_aurata_feae
  Musonycteris_harrisoni
  Myotis_brandtii
  Myotis_davidii
  Myotis_lucifugus
  Myotis_myotis
  Myotis_septentrionalis
  Nematostella_vectensis
  Noctilio_leporinus
  Nothobranchius_furzeri
  Nycticeius_humeralis
  Parastichopus_parvimensis
  Petromyzon_marinus
  Phyllostomus_discolor
  Phyllostomus_hastatus
  Pipistrellus_kuhlii
  Pteronotus_parnellii
  Pteropus_alecto
  Pteropus_giganteus
  Pteropus_rufus
  Pteropus_vampyrus
  Ptychodera_flava
  Rhinolophus_ferrumequinum
  Rousettus_aegyptiacus
  Rousettus_madagascariensis
  Schmidtea_lugubris
  Schmidtea_nova
  Scolanthus_callimorphus
  Sturnira_hondurensis
  Tadarida_brasiliensis
  Tonatia_saurophila
)

## Submit a Slurm job for each organism
for ORG in "${ORGANISMS[@]}"; do
  echo "Submitting Slurm job for $ORG"
  sbatch "$REPO/scripts/moop_process_genome_data.sbatch" "$ORG"
done


