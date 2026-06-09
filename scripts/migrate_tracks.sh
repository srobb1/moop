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
ORG[MCAP_v1]='Montipora_capitata';          ACC[MCAP_v1]='Mcap_2019'
ORG[Nvec200_pub]='Nematostella_vectensis';  ACC[Nvec200_pub]='GCA_033964005.1'
ORG[Nvec_v10]='Nematostella_vectensis';     ACC[Nvec_v10]='GCA_033964005.1'
ORG[NV2_v1]='Nematostella_vectensis';       ACC[NV2_v1]='MISSING'          # intentionally skipped
ORG[Scal100_v1]='Scolanthus_callimorphus';  ACC[Scal100_v1]='Scal100'
ORG[Scal100_pub]='Scolanthus_callimorphus'; ACC[Scal100_pub]='MISSING'      # intentionally skipped
ORG[starlet_pub]='Nematostella_vectensis';  ACC[starlet_pub]='GCA_033964005.1'
ORG[wormanemone_pub]='Scolanthus_callimorphus'; ACC[wormanemone_pub]='MISSING' # intentionally skipped

# Acorn Worm
ORG[Pfla_v1]='Ptychodera_flava';            ACC[Pfla_v1]='GCA_001465055.1'

# Flatworms
ORG[SmedSxl_v31]='Schmidtea_mediterranea';             ACC[SmedSxl_v31]='GCA_000691995.1'
ORG[SmedSxl_dd_g4]='Schmidtea_mediterranea';           ACC[SmedSxl_dd_g4]='GCA_002600895.1'
ORG[SmedSxl_schMedS3_h1_internal]='Schmidtea_mediterranea'; ACC[SmedSxl_schMedS3_h1_internal]='GCA_045838265.1'

# Jawed Fish
ORG[Amex_v10]='Astyanax_mexicanus';         ACC[Amex_v10]='GCA_000372685.1'
ORG[AstMex2_v1]='Astyanax_mexicanus';       ACC[AstMex2_v1]='GCF_000372685.2'
ORG[AstMex2_v2]='Astyanax_mexicanus';       ACC[AstMex2_v2]='GCA_000372685.2'
ORG[DanRer11]='Danio_rerio';                ACC[DanRer11]='GCF_000002035.6'
ORG[DanRer11_ens]='Danio_rerio';            ACC[DanRer11_ens]='GCF_000002035.5'  # GRCz10 Ensembl
ORG[Drer_pub_v10]='Danio_rerio';            ACC[Drer_pub_v10]='GCA_000002035.3'
ORG[Drer_v10]='Danio_rerio';               ACC[Drer_v10]='GCA_000002035.3'
ORG[NfurGRZ-RIMD11]='Nothobranchius_furzeri'; ACC[NfurGRZ-RIMD11]='GCA_043380555.1'
ORG[Nfur_v10]='Nothobranchius_furzeri';     ACC[Nfur_v10]='GCF_001465895.1'
ORG[Nfur_pub_v10]='Nothobranchius_furzeri'; ACC[Nfur_pub_v10]='MISSING'     # intentionally skipped
ORG[Nfur_v10_OLD]='Nothobranchius_furzeri'; ACC[Nfur_v10_OLD]='MISSING'     # intentionally skipped
ORG[killifish_pub]='Nothobranchius_furzeri'; ACC[killifish_pub]='MISSING'    # intentionally skipped

# Lampreys
ORG[ETRm_v1]='Entosphenus_tridentatus';     ACC[ETRm_v1]='JAAVTP000000000.2'
ORG[ETRm_pub]='Entosphenus_tridentatus';    ACC[ETRm_pub]='MISSING'          # intentionally skipped
ORG[kPetMar1_v1]='Petromyzon_marinus';      ACC[kPetMar1_v1]='GCF_010993605.1'
ORG[kPetMar1_pub]='Petromyzon_marinus';     ACC[kPetMar1_pub]='GCF_010993605.1'
ORG[Lric_v1]='Lampetra_richardsoni';        ACC[Lric_v1]='LPT'
ORG[Lric_pub]='Lampetra_richardsoni';       ACC[Lric_pub]='MISSING'          # intentionally skipped
ORG[Pmar_v11]='Petromyzon_marinus';         ACC[Pmar_v11]='GCA_002833325.1'
ORG[Pmar_pub_v11]='Petromyzon_marinus';     ACC[Pmar_pub_v11]='MISSING'      # intentionally skipped

# Mollusca
ORG[COKUS1KC_v1]='Congeria_kusceri';        ACC[COKUS1KC_v1]='GCA_027627225.1'
ORG[Pcan_v10]='Pomacea_canaliculata';       ACC[Pcan_v10]='GCF_003073045.1'
ORG[Pcan_refseq_v1]='Pomacea_canaliculata'; ACC[Pcan_refseq_v1]='GCF_003073045.1'

# Reptiles
ORG[CCA1_v1]='Chamaeleo_calyptratus';       ACC[CCA1_v1]='CCA1'
ORG[CCA1C_v1]='Chamaeleo_calyptratus';      ACC[CCA1C_v1]='CCA1C'
ORG[CCA2C_v1]='Chamaeleo_calyptratus';      ACC[CCA2C_v1]='CCA2C'

# Mouse
ORG[Mmus_v10]='Mus_musculus';               ACC[Mmus_v10]='GCF_000001635.27'  # mm39

# Plant
ORG[MTR1_v1]='Medicago_truncatula';         ACC[MTR1_v1]='GCF_003473485.1'

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
    [[ ! -f "$line" ]] && src_flag=' [SRC MISSING]' && (( n_src_missing++ )) || true
    if [[ -f "$new_path" ]]; then
        dest_flag=' [DEST EXISTS]'
        (( n_dest_conflict++ )) || true
        if [[ $SKIP_EXISTING -eq 1 && $DRY_RUN -eq 0 ]]; then
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
