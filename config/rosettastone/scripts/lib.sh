# Shared helpers for the RosettaStone scripts. Source it; do not run it.

RS_HOME=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
NAME_RE='^[A-Za-z0-9_.-]+$'

die()  { echo "ERROR: $*" >&2; exit 1; }
log()  { echo "[$(date '+%F %T')] $*" >&2; }

# read_config FILE: source FILE (values already set in the environment win), then work out the
# references and the sets to map (datastore + folders).
read_config() {
    local conf=$1 key
    [ -n "$conf" ] || die "no config file given (-c <file>); see rosettastone.conf.example"
    [ -r "$conf" ] || die "cannot read config $conf"
    local -A env_val=()
    for key in $(sed -n 's/^[[:space:]]*\([A-Z0-9_]*\)=.*/\1/p' "$conf"); do
        if [ -n "${!key+x}" ] && ! declare -p "$key" 2>/dev/null | grep -q '^declare -a'; then
            env_val[$key]=${!key}
        fi
    done
    # shellcheck disable=SC1090
    source "$conf"
    for key in "${!env_val[@]}"; do printf -v "$key" '%s' "${env_val[$key]}"; done
    declare -p REFERENCES 2>/dev/null | grep -q '^declare -a' || REFERENCES=()
    DS_QUERY=()
    [ -n "${SPECIES_DIR:-}" ] && load_datastore
    load_references
    load_sets
}

# ---------------------------------------------------------------- datastore
# Layout: SPECIES_DIR/<assembly>/genome.fa and SPECIES_DIR/<assembly>/<geneset>/ with
# metadata.yaml, genes.gtf or genes.gff, transcript.nt.fa and transcript2gene.txt.

# ds_genesets DIR: "<assembly>/<geneset><TAB><1 if active: true>" for every gene set in DIR
ds_genesets() {
    local m gs
    for m in "$1"/*/*/metadata.yaml; do
        [ -e "$m" ] || continue
        gs=${m%/metadata.yaml}; gs=${gs#"$1"/}
        printf '%s\t%s\n' "$gs" "$(grep -qE '^active:[[:space:]]*true[[:space:]]*$' "$m" && echo 1 || echo 0)"
    done
}

# ds_genome_kind <assembly>/<geneset>: "genome" or "transcriptome" (a placeholder genome.fa
# made of the gene set's own transcripts, which cannot be mapped onto). metadata.yaml decides
# when it says so ("genome-type: transcriptome|genome", or "genome: false|true"); otherwise
# the genome is a placeholder if most transcript IDs are also contig names (".ref" ignored).
ds_genome_kind() {
    local gs=$SPECIES_DIR/$1 asm m v
    asm=$(dirname "$gs"); m=$gs/metadata.yaml
    v=$(sed -n 's/^genome-type:[[:space:]]*\([A-Za-z]*\).*/\1/p' "$m" | head -1 | tr A-Z a-z)
    case $v in transcriptome|placeholder) echo transcriptome; return ;; genome) echo genome; return ;; esac
    v=$(sed -n 's/^genome:[[:space:]]*\([A-Za-z]*\).*/\1/p' "$m" | head -1 | tr A-Z a-z)
    case $v in false|no) echo transcriptome; return ;; true|yes) echo genome; return ;; esac
    [ -s "$asm/genome.fa" ] && [ -s "$gs/transcript.nt.fa" ] || { echo genome; return; }
    { if [ -s "$asm/genome.fa.fai" ] && [ "$asm/genome.fa.fai" -nt "$asm/genome.fa" ]; then
          cut -f1 "$asm/genome.fa.fai"
      else
          grep '>' "$asm/genome.fa" | sed 's/^>//; s/[[:space:]].*//'
      fi; echo "//"; grep '>' "$gs/transcript.nt.fa"
    } | awk '$0 == "//" {t = 1; next}
             !t {sub(/\.ref$/, ""); c[$0]; next}
             { id = substr($1, 2); n++; if (id in c) hit++ }
             END { print (n > 0 && hit > n / 2) ? "transcriptome" : "genome" }'
}

# ds_resolve SPEC: "<assembly>/<geneset>" for SPEC given as either that or a unique <geneset>
ds_resolve() {
    local spec=$1 hits
    if [ -e "$SPECIES_DIR/$spec/metadata.yaml" ]; then echo "$spec"; return; fi
    hits=$(ds_genesets "$SPECIES_DIR" | cut -f1 | awk -F/ -v g="$spec" '$2 == g')
    [ -n "$hits" ] || die "gene set '$spec' not found in $SPECIES_DIR (try scripts/list_genesets.sh)"
    [ "$(echo "$hits" | wc -l)" -eq 1 ] || die "gene set '$spec' is ambiguous in $SPECIES_DIR: $(echo $hits)"
    echo "$hits"
}

load_datastore() {
    [ -d "$SPECIES_DIR" ] || die "SPECIES_DIR $SPECIES_DIR is not a directory"
    local species; species=$(basename "$SPECIES_DIR")
    CROSSWALK_ORGANISM=${CROSSWALK_ORGANISM:-$species}
    if [ -z "${OUT_DIR:-}" ] && [ -n "${OUT_ROOT:-}" ]; then OUT_DIR=$OUT_ROOT/$species; fi

    local all; all=$(ds_genesets "$SPECIES_DIR")
    [ -n "$all" ] || die "no gene sets (*/*/metadata.yaml) in $SPECIES_DIR"
    local -a refs=() maps=()
    local spec gs name asm annot hub r kv
    # references: REFERENCE_GENESETS, or the only active gene set
    if [ -n "${REFERENCE_GENESETS:-}" ]; then
        for spec in $REFERENCE_GENESETS; do
            gs=$(ds_resolve "$spec")
            [ "$(ds_genome_kind "$gs")" = genome ] \
                || die "$gs cannot be a reference: its genome.fa is a placeholder made of its transcripts (it is still mapped as a set)"
            refs+=("$gs")
        done
    else
        for gs in $(echo "$all" | awk -F'\t' '$2 == 1 {print $1}'); do
            [ "$(ds_genome_kind "$gs")" = genome ] && refs+=("$gs")
        done
        [ ${#refs[@]} -gt 0 ] || die "$species has no active gene set on a real genome (only transcriptome placeholders); nothing to map onto"
        [ ${#refs[@]} -eq 1 ] || die "set REFERENCE_GENESETS: $species has ${#refs[@]} active gene sets on real genomes ($(echo ${refs[*]}))"
    fi
    local -A hubmap=()
    for kv in ${REFERENCE_HUBS:-}; do hubmap[${kv%%=*}]=${kv#*=}; done
    for gs in "${refs[@]}"; do
        name=${gs#*/}; asm=${gs%%/*}
        annot=$SPECIES_DIR/$gs/genes.gtf; [ -s "$annot" ] || annot=$SPECIES_DIR/$gs/genes.gff
        hub=${hubmap[$name]:-$name}
        REFERENCES+=("$name | $SPECIES_DIR/$asm/genome.fa | $annot | $hub | $SPECIES_DIR/$gs/transcript2gene.txt")
    done
    # gene sets to map: MAP_GENESETS = active (default) | all | none | list; minus EXCLUDE_GENESETS
    case ${MAP_GENESETS:-active} in
        active) mapfile -t maps < <(echo "$all" | awk -F'\t' '$2 == 1 {print $1}') ;;
        all)    mapfile -t maps < <(echo "$all" | cut -f1) ;;
        none)   maps=() ;;
        *)      for spec in $MAP_GENESETS; do maps+=("$(ds_resolve "$spec")"); done ;;
    esac
    local -A excl=()
    for spec in ${EXCLUDE_GENESETS:-}; do excl[$(ds_resolve "$spec")]=1; done
    for gs in "${maps[@]}"; do
        [ -z "${excl[$gs]:-}" ] || continue
        DS_QUERY+=("${gs#*/}|$SPECIES_DIR/$gs/transcript.nt.fa")
    done
}

# load_references: parse REFERENCES ("name | genome | gene models | hub [| transcript2gene]")
#   into REF_NAMES (in order) and REF_GENOME/REF_ANNOT/REF_HUB/REF_T2G[name]
load_references() {
    REF_NAMES=(); declare -gA REF_GENOME=() REF_ANNOT=() REF_HUB=() REF_T2G=()
    local line name genome annot hub t2g
    for line in "${REFERENCES[@]}"; do
        IFS='|' read -r name genome annot hub t2g <<< "$line"
        read -r name <<< "$name"; read -r genome <<< "$genome"; read -r annot <<< "$annot"
        read -r hub <<< "$hub"; read -r t2g <<< "$t2g"
        [[ "$name" =~ $NAME_RE ]] || die "REFERENCES: bad reference name '$name' in: $line"
        [ -z "${REF_HUB[$name]+x}" ] || die "reference '$name' is listed twice"
        hub=${hub:-$name}
        [[ "$hub" =~ $NAME_RE ]] || die "REFERENCES: bad hub name '$hub' in: $line"
        REF_NAMES+=("$name"); REF_GENOME[$name]=$genome; REF_ANNOT[$name]=$annot
        REF_HUB[$name]=$hub; REF_T2G[$name]=$t2g
    done
}

# load_sets: QUERY_NAMES + QUERY_FASTA[name] (datastore gene sets, then QUERY_DIR/*.nt minus
# QUERY_EXCLUDE) and PROBE_NAMES + PROBE_FASTA[name] (PROBE_DIR/*.nt)
load_sets() {
    QUERY_NAMES=(); PROBE_NAMES=(); declare -gA QUERY_FASTA=() PROBE_FASTA=()
    local x name fa f
    for x in "${DS_QUERY[@]}"; do
        name=${x%%|*}; fa=${x#*|}
        add_set QUERY "$name" "$fa"
    done
    if [ -n "${QUERY_DIR:-}" ]; then
        [ -d "$QUERY_DIR" ] || die "QUERY_DIR $QUERY_DIR is not a directory"
        for f in "$QUERY_DIR"/*.nt; do
            [ -e "$f" ] || continue
            name=$(basename "$f" .nt)
            [[ " ${QUERY_EXCLUDE:-} " == *" $name "* ]] && continue
            add_set QUERY "$name" "$f"
        done
    fi
    if [ -n "${PROBE_DIR:-}" ]; then
        [ -d "$PROBE_DIR" ] || die "PROBE_DIR $PROBE_DIR is not a directory"
        for f in "$PROBE_DIR"/*.nt; do
            [ -e "$f" ] || continue
            add_set PROBE "$(basename "$f" .nt)" "$f"
        done
    fi
}
add_set() {   # add_set QUERY|PROBE NAME FASTA
    local kind=$1 name=$2 fa=$3
    [[ "$name" =~ $NAME_RE ]] || die "set name '$name' ($fa) may contain only letters, digits, _ . -"
    [ -z "${QUERY_FASTA[$name]+x}${PROBE_FASTA[$name]+x}" ] \
        || die "two sets are both called '$name': ${QUERY_FASTA[$name]:-${PROBE_FASTA[$name]}} and $fa (use QUERY_EXCLUDE or EXCLUDE_GENESETS)"
    if [ "$kind" = QUERY ]; then QUERY_NAMES+=("$name"); QUERY_FASTA[$name]=$fa
    else PROBE_NAMES+=("$name"); PROBE_FASTA[$name]=$fa; fi
}

# load_config FILE: read_config, then check what the pipeline needs.
load_config() {
    local r
    read_config "$1"
    [ -n "${OUT_DIR:-}" ] || die "$1: OUT_DIR (or OUT_ROOT with SPECIES_DIR) is not set"
    [ ${#REF_NAMES[@]} -gt 0 ] || die "$1: no reference (REFERENCE_GENESETS or REFERENCES)"
    for r in "${REF_NAMES[@]}"; do
        [ -n "${REF_GENOME[$r]}" ] && [ -n "${REF_ANNOT[$r]}" ] \
            || die "$1: reference $r needs a genome and gene models"
    done
    [ ${#QUERY_NAMES[@]} -gt 0 ] || [ ${#PROBE_NAMES[@]} -gt 0 ] || die "$1: no sets to map"
    THREADS=${THREADS:-8}
    UNSTRANDED_SETS=${UNSTRANDED_SETS:-}
    export RS_HOME OUT_DIR THREADS
}

load_modules() {
    [ -n "${MODULES:-}" ] || return 0
    if type module >/dev/null 2>&1; then
        module purge >/dev/null 2>&1 || true
        # shellcheck disable=SC2086
        module load $MODULES
    else
        log "no 'module' command; using tools on PATH"
    fi
}

need() { local t; for t in "$@"; do command -v "$t" >/dev/null || die "$t not found on PATH"; done; }

# is_unstranded SET
is_unstranded() { [[ " $UNSTRANDED_SETS " == *" $1 "* ]]; }

# finish TMP FINAL: move a completed output into place (outputs never exist half-written,
# so "output exists" reliably means "step done").
finish() { mv -f "$1" "$2"; }

# uptodate OUT IN...: true if OUT exists, is non-empty and is newer than every IN
uptodate() {
    local out=$1 in; shift
    [ -s "$out" ] || return 1
    for in in "$@"; do [ "$out" -nt "$in" ] || return 1; done
}

# parse_common_args "$@": sets CONF and SETS (the remaining arguments: limit to these sets)
parse_common_args() {
    CONF=${RS_CONF:-}; SETS=()
    while [ $# -gt 0 ]; do
        case $1 in
            -c) CONF=$2; shift 2 ;;
            -h|--help) sed -n '2,/^set -/p' "$0" | sed '$d; s/^# \{0,1\}//' >&2; exit 0 ;;
            -*) die "unknown option $1" ;;
            *) SETS+=("$1"); shift ;;
        esac
    done
}

# wanted SET: true if no sets were named on the command line, or SET was named
wanted() {
    [ ${#SETS[@]} -eq 0 ] && return 0
    local s; for s in "${SETS[@]}"; do [ "$s" = "$1" ] && return 0; done; return 1
}

# print_plan: what this configuration will do (references, sets, outputs)
print_plan() {
    local r s
    echo "species dir : ${SPECIES_DIR:-(none)}"
    echo "organism    : ${CROSSWALK_ORGANISM:-(not set)}"
    echo "output      : $OUT_DIR"
    echo "references  : ${#REF_NAMES[@]}"
    for r in "${REF_NAMES[@]}"; do
        printf '  %-28s hub %s\n      genome %s\n      genes  %s\n' "$r" "${REF_HUB[$r]}" "${REF_GENOME[$r]}" "${REF_ANNOT[$r]}"
    done
    echo "query sets  : ${#QUERY_NAMES[@]} (each aligned to every reference)"
    for s in "${QUERY_NAMES[@]}"; do
        printf '  %-28s %s%s\n' "$s" "${QUERY_FASTA[$s]}" "$(is_unstranded "$s" && echo '  [unstranded]')"
    done
    echo "probe sets  : ${#PROBE_NAMES[@]}"
    for s in "${PROBE_NAMES[@]}"; do printf '  %-28s %s\n' "$s" "${PROBE_FASTA[$s]}"; done
    echo "transfer hubs: ${TRANSFER_HUBS:-(none)}"
    for s in ${TRANSFER_HUBS:-}; do
        [ -n "${QUERY_FASTA[$s]+x}" ] || echo "  WARNING: transfer hub $s is not one of the query sets"
    done
}
