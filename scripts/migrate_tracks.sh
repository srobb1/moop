#!/usr/bin/env bash
# migrate_tracks.sh — move track files from old per-assembly layout to
#   new OrganismName/Accession/files layout.
#
# Usage:
#   bash migrate_tracks.sh tracks.old.files.txt --dry-run                    # preview
#   bash migrate_tracks.sh tracks.old.files.txt                              # move all
#   bash migrate_tracks.sh tracks.old.files.txt --skip-existing              # skip already-moved files
#
# Fill in MISSING accessions below before running.

set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────

DEST_BASE='/var/www/privatehtml/moop/data/tracks'
OLD_PREFIX='/var/www/privatehtml/simrbase_tracks/jb_gh/jbrowse/data'

# ── Organism map: DIR_NAME -> "OrganismName AccessionID" ─────────────────────
# Leave accession as MISSING to skip that directory.

declare -A ORG
declare -A ACC

# Cnidarians
ORG[MCAP_HIv3_v1]='Montipora_capitata';     ACC[MCAP_HIv3_v1]='HIv3'
ORG[MCAP_v1]='Montipora_capitata';          ACC[MCAP_v1]='Mcap_2019'
ORG[Nvec200_pub]='Nematostella_vectensis';  ACC[Nvec200_pub]='GCA_033964005.1'
ORG[Nvec200_v1]='Nematostella_vectensis';   ACC[Nvec200_v1]='GCA_033964005.1'
ORG[Nvec_v10]='Nematostella_vectensis';     ACC[Nvec_v10]='GCA_033964005.1'
ORG[NV2_v1]='Nematostella_vectensis';       ACC[NV2_v1]='MISSING'
ORG[Scal100_v1]='Scolanthus_callimorphus';  ACC[Scal100_v1]='Scal100'
ORG[Scal100_pub]='Scolanthus_callimorphus'; ACC[Scal100_pub]='MISSING'
ORG[starlet_pub]='Nematostella_vectensis';  ACC[starlet_pub]='GCA_033964005.1'
ORG[wormanemone_pub]='Scolanthus_callimorphus'; ACC[wormanemone_pub]='MISSING'

# Acorn Worm
ORG[Pfla_v1]='Ptychodera_flava';            ACC[Pfla_v1]='PflaM.kc1'

# Flatworms
ORG[SmedSxl_v31]='Schmidtea_mediterranea';                      ACC[SmedSxl_v31]='GCA_000691995.1'
ORG[SmedSxl_dd_g4]='Schmidtea_mediterranea';                    ACC[SmedSxl_dd_g4]='GCA_002600895.1'
ORG[SmedSxl_schMedS3_h1_internal]='Schmidtea_mediterranea';     ACC[SmedSxl_schMedS3_h1_internal]='schMedS3h1'
ORG[SmedSxl_schMedS3_h2_internal]='Schmidtea_mediterranea';     ACC[SmedSxl_schMedS3_h2_internal]='MISSING'
ORG[SmedSxl_smed_chr_ref_v1_internal]='Schmidtea_mediterranea'; ACC[SmedSxl_smed_chr_ref_v1_internal]='MISSING'

# Jawed Fish
ORG[AME1_v1]='Astyanax_mexicanus';          ACC[AME1_v1]='MISSING'
ORG[Amex_v10]='Astyanax_mexicanus';         ACC[Amex_v10]='GCA_000372685.1'
ORG[AstMex2_v1]='Astyanax_mexicanus';       ACC[AstMex2_v1]='GCF_000372685.2'
ORG[AstMex2_v2]='Astyanax_mexicanus';       ACC[AstMex2_v2]='GCA_000372685.2'
ORG[DanRer11]='Danio_rerio';                ACC[DanRer11]='GCF_000002035.6'
ORG[DanRer11_ens]='Danio_rerio';            ACC[DanRer11_ens]='GCF_000002035.5'
ORG[Drer_pub_v10]='Danio_rerio';            ACC[Drer_pub_v10]='GCA_000002035.3'
ORG[Drer_v10]='Danio_rerio';               ACC[Drer_v10]='GCA_000002035.3'
ORG[NfurGRZ-RIMD11]='Nothobranchius_furzeri'; ACC[NfurGRZ-RIMD11]='GCF_043380555.1'
ORG[Nfur_v10]='Nothobranchius_furzeri';     ACC[Nfur_v10]='GCF_001465895.1'
ORG[Nfur_pub_v10]='Nothobranchius_furzeri'; ACC[Nfur_pub_v10]='MISSING'
ORG[Nfur_v10_OLD]='Nothobranchius_furzeri'; ACC[Nfur_v10_OLD]='MISSING'
ORG[killifish_pub]='Nothobranchius_furzeri'; ACC[killifish_pub]='MISSING'

# Lampreys
ORG[ETRf_v1]='Entosphenus_tridentatus';     ACC[ETRf_v1]='JAAXLI000000000.2'
ORG[ETRm_v1]='Entosphenus_tridentatus';     ACC[ETRm_v1]='JAAVTP000000000.2'
ORG[ETRm_pub]='Entosphenus_tridentatus';    ACC[ETRm_pub]='MISSING'
ORG[kPetMar1_v1]='Petromyzon_marinus';      ACC[kPetMar1_v1]='GCF_010993605.1'
ORG[kPetMar1_pub]='Petromyzon_marinus';     ACC[kPetMar1_pub]='GCF_010993605.1'
ORG[Lric_v1]='Lampetra_richardsoni';        ACC[Lric_v1]='LPT'
ORG[Lric_pub]='Lampetra_richardsoni';       ACC[Lric_pub]='MISSING'
ORG[Pmar_v11]='Petromyzon_marinus';         ACC[Pmar_v11]='GCA_002833325.1'
ORG[Pmar_pub_v11]='Petromyzon_marinus';     ACC[Pmar_pub_v11]='MISSING'

# Mollusca
ORG[COKUS1KC_v1]='Congeria_kusceri';        ACC[COKUS1KC_v1]='GCA_027627225.1'
ORG[Pcan_v10]='Pomacea_canaliculata';       ACC[Pcan_v10]='GCF_003073045.1'
ORG[Pcan_refseq_v1]='Pomacea_canaliculata'; ACC[Pcan_refseq_v1]='GCF_003073045.1'

# Reptiles
ORG[BraPum1_v1]='Bradypodion_pumilum';      ACC[BraPum1_v1]='GCA_035047305.1'
ORG[BraVen1_v1]='Bradypodion_ventrale';     ACC[BraVen1_v1]='GCA_035047345.1'
ORG[CCA1_v1]='Chamaeleo_calyptratus';       ACC[CCA1_v1]='CCA1'
ORG[CCA1C_v1]='Chamaeleo_calyptratus';      ACC[CCA1C_v1]='CCA1C'
ORG[CCA2C_v1]='Chamaeleo_calyptratus';      ACC[CCA2C_v1]='CCA2C'
ORG[CCA3_v1]='Chamaeleo_calyptratus';       ACC[CCA3_v1]='CCA3'
ORG[CCA3H1_v1]='Chamaeleo_calyptratus';     ACC[CCA3H1_v1]='CCA3H1'
ORG[CCA3H2_v1]='Chamaeleo_calyptratus';     ACC[CCA3H2_v1]='CCA3H2'
ORG[FurPar1_v1]='Furcifer_pardalis';        ACC[FurPar1_v1]='GCA_030440675.1'

# Mammals
ORG[Mmus_v10]='Mus_musculus';               ACC[Mmus_v10]='GCF_000001635.27'

# Plant
ORG[MTR1_v1]='Medicago_truncatula';         ACC[MTR1_v1]='GCF_003473485.1'

# Bats
ORG[ACA1_v1]='Anoura_caudifer';             ACC[ACA1_v1]='GCA_004027475.1'
ORG[ACI1_v1]='Lasiurus_cinereus';           ACC[ACI1_v1]='GCA_011751095.1'
ORG[AJA1_v1]='Artibeus_jamaicensis';        ACC[AJA1_v1]='GCF_021234435.1'
ORG[APA1_v1]='Antrozous_pallidus';          ACC[APA1_v1]='GCA_007922775.1'
ORG[CBR1_v1]='Cynopterus_brachyotis';       ACC[CBR1_v1]='GCA_009793145.1'
ORG[CPE1_v1]='Carollia_perspicillata';      ACC[CPE1_v1]='GCA_004027735.1'
ORG[CTH1_v1]='Craseonycteris_thonglongyai'; ACC[CTH1_v1]='GCA_004027555.1'
ORG[DRO1_v1]='Desmodus_rotundus';           ACC[DRO1_v1]='GCF_022682495.1'
ORG[EDU1_v1]='Eidolon_dupreanum';           ACC[EDU1_v1]='ASM46528v1'
ORG[EFU1_v1]='Eptesicus_fuscus';            ACC[EFU1_v1]='GCF_027574615.1'
ORG[EHE1_v1]='Eidolon_helvum';             ACC[EHE1_v1]='GCA_000465285.1'
ORG[ESP1_v1]='Eonycteris_spelaea';          ACC[ESP1_v1]='GCA_003508835.1'
ORG[HAR1_v1]='Hipposideros_armiger';        ACC[HAR1_v1]='GCF_001890085.2'
ORG[HGA1_v1]='Hipposideros_galeritus';      ACC[HGA1_v1]='GCA_004027415.1'
ORG[LBO1_v1]='Lasiurus_borealis';           ACC[LBO1_v1]='GCA_004026805.1'
ORG[LNI1_v1]='Leptonycteris_nivalis';       ACC[LNI1_v1]='Lnivalis_consensus_genome'
ORG[LYE1_v1]='Leptonycteris_yerbabuenae';  ACC[LYE1_v1]='Lyerbabuenae_genome'
ORG[MAU1_v1]='Murina_feae';                 ACC[MAU1_v1]='GCA_004026665.1'
ORG[MBL1_v1]='Mormoops_blainvillei';        ACC[MBL1_v1]='GCA_004026545.1'
ORG[MBR1_v1]='Myotis_brandtii';             ACC[MBR1_v1]='GCF_000412655.1'
ORG[MCA1_v1]='Macrotus_californicus';       ACC[MCA1_v1]='GCA_007922815.1'
ORG[MDA1_v1]='Myotis_davidii';             ACC[MDA1_v1]='GCF_000327345.1'
ORG[MHA1_v1]='Musonycteris_harrisoni';      ACC[MHA1_v1]='Mharrisoni_consensus_genome'
ORG[MHI1_v1]='Micronycteris_hirsuta';       ACC[MHI1_v1]='GCA_004026765.1'
ORG[MLU1_v1]='Myotis_lucifugus';            ACC[MLU1_v1]='GCF_000147115.1'
ORG[MLY1_v1]='Megaderma_lyra';              ACC[MLY1_v1]='GCA_004026885.1'
ORG[MMO1_v1]='Molossus_molossus';           ACC[MMO1_v1]='GCF_014108415.1'
ORG[MMY1_v1]='Myotis_myotis';              ACC[MMY1_v1]='GCF_014108235.1'
ORG[MNA1_v1]='Miniopterus_natalensis';      ACC[MNA1_v1]='GCF_001595765.1'
ORG[MSC1_v1]='Miniopterus_schreibersii';    ACC[MSC1_v1]='GCA_004026525.1'
ORG[MSE1_v1]='Myotis_septentrionalis';      ACC[MSE1_v1]='myse_ont_racon_pilon_HiC'
ORG[MSO1_v1]='Macroglossus_sobrinus';       ACC[MSO1_v1]='GCA_004027375.1'
ORG[MWA1_v1]='Macrotus_waterhousii';        ACC[MWA1_v1]='Mwaterhousii_consensus_genome'
ORG[NHU1_v1]='Nycticeius_humeralis';        ACC[NHU1_v1]='GCA_007922795.1'
ORG[NLE1_v1]='Noctilio_leporinus';          ACC[NLE1_v1]='GCA_004026585.1'
ORG[PAL1_v1]='Pteropus_alecto';             ACC[PAL1_v1]='GCF_000325575.1'
ORG[PDI1_v1]='Phyllostomus_discolor';       ACC[PDI1_v1]='GCF_004126475.2'
ORG[PGI1_v1]='Pteropus_medius';             ACC[PGI1_v1]='GCF_902729225.1'
ORG[PHA1_v1]='Phyllostomus_hastatus';       ACC[PHA1_v1]='GCF_019186645.2'
ORG[PKU1_v1]='Pipistrellus_kuhlii';         ACC[PKU1_v1]='GCF_014108245.1'
ORG[PPA1_v1]='Pteronotus_mesoamericanus';   ACC[PPA1_v1]='GCF_021234165.1'
ORG[PPI1_v1]='Pipistrellus_pipistrellus';   ACC[PPI1_v1]='GCA_004026625.1'
ORG[PRU1_v1]='Pteropus_rufus';              ACC[PRU1_v1]='Pteropus_rufus_HiC'
ORG[PVA1_v1]='Pteropus_vampyrus';           ACC[PVA1_v1]='GCF_000151845.1'
ORG[RAE1_v1]='Rousettus_aegyptiacus';       ACC[RAE1_v1]='GCF_014176215.1'
ORG[RFE1_v1]='Rhinolophus_ferrumequinum';   ACC[RFE1_v1]='GCF_004115265.2'
ORG[RMA1_v1]='Rousettus_madagascariensis';  ACC[RMA1_v1]='GCA_028533395.1'
ORG[SHO1_v1]='Sturnira_hondurensis';        ACC[SHO1_v1]='GCF_014824575.3'
ORG[TBR1_v1]='Tadarida_brasiliensis';       ACC[TBR1_v1]='GCA_004025005.1'
ORG[TSA1_v1]='Tonatia_saurophila';          ACC[TSA1_v1]='GCA_004024845.1'

# ── Argument parsing ──────────────────────────────────────────────────────────

DRY_RUN=0
SKIP_EXISTING=0
FILES_ARG=''

for arg in "$@"; do
    case "$arg" in
        --dry-run)       DRY_RUN=1 ;;
        --skip-existing) SKIP_EXISTING=1 ;;
        *)               FILES_ARG="$arg" ;;
    esac
done

if [[ -z "$FILES_ARG" ]]; then
    echo "Usage: bash migrate_tracks.sh tracks.old.files.txt [--dry-run]" >&2
    exit 1
fi

if [[ ! -f "$FILES_ARG" ]]; then
    echo "ERROR: file not found: $FILES_ARG" >&2
    exit 1
fi

if [[ ! -d "$DEST_BASE" ]]; then
    echo "ERROR: DEST_BASE does not exist: $DEST_BASE" >&2
    exit 1
fi

# ── Process lines ─────────────────────────────────────────────────────────────

n_ready=0
n_skipped_acc=0
n_src_missing=0
n_dest_conflict=0
n_unmapped=0
n_parse_err=0

declare -A skipped_dirs=()
declare -A unmapped_dirs=()

while IFS= read -r line || [[ -n "$line" ]]; do
    [[ -z "$line" ]] && continue

    # Must start with OLD_PREFIX/
    if [[ "$line" != "$OLD_PREFIX/"* ]]; then
        (( n_parse_err++ )) || true
        continue
    fi

    rel="${line#$OLD_PREFIX/}"       # e.g. Amex_v10/files/foo.bam
    dir_name="${rel%%/*}"            # Amex_v10
    rest="${rel#*/}"                 # files/foo.bam
    subdir="${rest%%/*}"             # files
    filename="${rest#*/}"            # foo.bam

    if [[ "$subdir" != "files" ]]; then
        (( n_parse_err++ )) || true
        continue
    fi

    # Look up organism map
    if [[ -z "${ORG[$dir_name]+x}" ]]; then
        unmapped_dirs["$dir_name"]=1
        (( n_unmapped++ )) || true
        continue
    fi

    organism="${ORG[$dir_name]}"
    accession="${ACC[$dir_name]}"

    if [[ "$accession" == "MISSING" ]]; then
        skipped_dirs["$dir_name"]=$(( ${skipped_dirs[$dir_name]:-0} + 1 ))
        (( n_skipped_acc++ )) || true
        continue
    fi

    new_path="$DEST_BASE/$organism/$accession/$filename"
    new_dir="$(dirname "$new_path")"

    # Existence checks
    src_flag=''
    dest_flag=''
    if [[ ! -f "$line" ]]; then
        (( n_src_missing++ )) || true
        src_flag=' [SRC MISSING]'
        [[ $DRY_RUN -eq 0 ]] && continue
    fi
    if [[ -f "$new_path" ]]; then
        dest_flag=' [DEST EXISTS]'
        (( n_dest_conflict++ )) || true
        if [[ $SKIP_EXISTING -eq 1 ]]; then
            continue
        fi
    fi

    if [[ $DRY_RUN -eq 1 ]]; then
        dir_status='(will create)'
        [[ -d "$new_dir" ]] && dir_status='(dir exists)'
        rel_dest="${new_path#$DEST_BASE/}"
        echo "  $(dirname "$rel_dest")  $dir_status"
        echo "    $(basename "$filename")$src_flag$dest_flag"
    else
        mkdir -p "$new_dir"
        mv "$line" "$new_path"
    fi

    (( n_ready++ )) || true

done < "$FILES_ARG"

# ── Summary ───────────────────────────────────────────────────────────────────

echo ""
if [[ $DRY_RUN -eq 1 ]]; then
    echo "DRY RUN complete — no files were moved"
else
    echo "Migration complete"
fi
echo "  Files moved/ready : $n_ready"
echo "  Src missing       : $n_src_missing"
echo "  Dest conflicts    : $n_dest_conflict"
echo "  Skipped (no acc)  : $n_skipped_acc"
echo "  Unmapped dirs     : $n_unmapped"
echo "  Parse errors      : $n_parse_err"

n_skipped_dirs=${#skipped_dirs[@]}
if [[ $n_skipped_dirs -gt 0 ]]; then
    echo ""
    echo "Skipped dirs (intentionally skipped — accession is MISSING):"
    for d in "${!skipped_dirs[@]}"; do
        echo "  $d: ${skipped_dirs[$d]} files"
    done | sort
fi

n_unmapped_dirs=${#unmapped_dirs[@]}
if [[ $n_unmapped_dirs -gt 0 ]]; then
    echo ""
    echo "Unmapped dirs (not in ORG/ACC maps — add them to the script):"
    for d in "${!unmapped_dirs[@]}"; do
        echo "  $d"
    done | sort
fi
