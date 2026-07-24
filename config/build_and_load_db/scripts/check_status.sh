#!/usr/bin/bash
# check_status.sh — report processing status for every *active* geneset
# (GENOMES/<organism>/<assembly>/<geneset>/metadata.yaml contains "active: true",
# same detection as run_all_v2.sh).
# One line per issue; grep on the tag to get a specific todo list.
#
# Tags:
#   OK            fully processed and copied to moop
#   NO_INPUT      no genes.gff or transcript2gene.txt yet — nothing to process
#   NOT_BUILT     input files present but features.tsv not built yet
#   MISS_GENOME   genome.fa missing from genomes dir
#   MISS_GFF      genes.gff missing
#   MISS_FASTA    a required input FASTA is missing (detail appended)
#   MISS_METADATA metadata.yaml missing
#   MISS_DIAMOND  diamond blast results missing — need to run diamond
#   MISS_EGGNOG   eggnog results missing — need to run eggnog_mapper
#   MISS_IPRSCAN  interproscan results missing — need to run interproscan
#   MISS_PROTNLM  protnlm results missing — need to run protnlm
#   COPY_FAIL     processed but organism.sqlite not found on moop
#
# Usage:
#   bash scripts/check_status.sh             # print to stdout
#   bash scripts/check_status.sh > status.log
#   grep MISS_EGGNOG status.log              # organisms needing eggnog

REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
DATA="$REPO/data"
GENOMES=/n/sci/SCI-004223-SBGENOMES/genomes_v2
ANNOTATIONS=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/annotations/SBGENOMES_2026-05-21
REMOTE=moop
REMOTE_BASE=/var/www/html/moop/organisms

## Check every *active* geneset (same detection as run_all_v2.sh):
## GENOMES/<organism>/<assembly>/<geneset>/metadata.yaml exists and contains "active: true".
GENESETS="$REPO/active_genesets.tsv"

> "$GENESETS"
for META in "$GENOMES"/*/*/*/metadata.yaml; do
  [ -f "$META" ] || continue
  grep -qiE '^active:[[:space:]]*true[[:space:]]*$' "$META" || continue
  GENESET_DIR=$(dirname "$META")
  GENE_SET=$(basename "$GENESET_DIR")
  ASSEMBLY=$(basename "$(dirname "$GENESET_DIR")")
  THIS_ORG=$(basename "$(dirname "$(dirname "$GENESET_DIR")")")
  printf '%s\t%s\t%s\n' "$THIS_ORG" "$ASSEMBLY" "$GENE_SET" >> "$GENESETS"
done

output_lines=()
log() {
  output_lines+=("$(printf "%-14s  %-50s  %s/%s/%s" "$1" "$2" "$3" "$4" "$5")")
}

# Fetch list of organisms already copied to moop in one SSH call
copied_orgs=$(ssh -o BatchMode=yes -o ConnectTimeout=10 $REMOTE \
  "find $REMOTE_BASE -maxdepth 2 -name organism.sqlite 2>/dev/null" \
  | grep -oP 'organisms/\K[^/]+' | sort -u)

while IFS=$'\t' read -r org asm gs; do

  GENESET_DIR="$GENOMES/$org/$asm/$gs"
  GENOME_DIR="$GENOMES/$org/$asm"
  ANALYSIS_DIR="$ANNOTATIONS/$org/$asm/$gs"
  GENESET_DATA="$DATA/$org/$asm/$gs"

  # Determine mode
  HAS_GFF=false; HAS_T2G=false
  [ -s "$GENESET_DIR/genes.gff" ]           && HAS_GFF=true
  [ -s "$GENESET_DIR/transcript2gene.txt" ] && HAS_T2G=true

  if ! $HAS_GFF && ! $HAS_T2G; then
    log "NO_INPUT" "" "$org" "$asm" "$gs"
    continue
  fi

  any_issue=false

  # ── Input files ────────────────────────────────────────────────────────────
  if $HAS_GFF; then
    [ -e "$GENOME_DIR/genome.fa" ] \
      || { log "MISS_GENOME" "" "$org" "$asm" "$gs"; any_issue=true; }
    [ -s "$GENESET_DIR/protein.aa.fa" ] \
      || { log "MISS_FASTA" "protein.aa.fa" "$org" "$asm" "$gs"; any_issue=true; }
    [ -s "$GENESET_DIR/cds.nt.fa" ] \
      || { log "MISS_FASTA" "cds.nt.fa" "$org" "$asm" "$gs"; any_issue=true; }
    [ -s "$GENESET_DIR/transcript.nt.fa" ] \
      || { log "MISS_FASTA" "transcript.nt.fa" "$org" "$asm" "$gs"; any_issue=true; }
  else
    [ -s "$GENESET_DIR/protein.aa.fa" ] \
      || { log "MISS_FASTA" "protein.aa.fa" "$org" "$asm" "$gs"; any_issue=true; }
    [ -s "$GENESET_DIR/transcript.nt.fa" ] \
      || { log "MISS_FASTA" "transcript.nt.fa" "$org" "$asm" "$gs"; any_issue=true; }
  fi
  [ -s "$GENESET_DIR/metadata.yaml" ] \
    || { log "MISS_METADATA" "" "$org" "$asm" "$gs"; any_issue=true; }

  # ── Annotation files ───────────────────────────────────────────────────────
  { [ -f "$ANALYSIS_DIR/diamond_blast/UNIPROT_sprot/tophit.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/diamond_blast/UNIPROT_sprot/tophit.tsv" ]; } \
    || { log "MISS_DIAMOND" "" "$org" "$asm" "$gs"; any_issue=true; }
  [ -f "$ANALYSIS_DIR/eggnog_mapper/emapper.annotations" ] \
    || { log "MISS_EGGNOG" "" "$org" "$asm" "$gs"; any_issue=true; }
  { [ -f "$ANALYSIS_DIR/interproscan/iprscan_results.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/interproscan/iprscan_results.tsv" ]; } \
    || { log "MISS_IPRSCAN" "" "$org" "$asm" "$gs"; any_issue=true; }
  [ -f "$ANALYSIS_DIR/protnlm/protnlm_pred_results.tsv" ] \
    || { log "MISS_PROTNLM" "" "$org" "$asm" "$gs"; any_issue=true; }

  # ── Build status ───────────────────────────────────────────────────────────
  [ -s "$GENESET_DATA/features.tsv" ] \
    || { log "NOT_BUILT" "" "$org" "$asm" "$gs"; any_issue=true; }

  # ── Copy status ────────────────────────────────────────────────────────────
  if ! echo "$copied_orgs" | grep -qx "$org"; then
    log "COPY_FAIL" "" "$org" "$asm" "$gs"
    any_issue=true
  fi

  $any_issue || log "OK" "" "$org" "$asm" "$gs"

done < "$GENESETS"

printf '%s\n' "${output_lines[@]}"

# ── Summary table ──────────────────────────────────────────────────────────────
declare -A counts
for line in "${output_lines[@]}"; do
  tag=$(awk '{print $1}' <<< "$line")
  counts[$tag]=$((${counts[$tag]:-0} + 1))
done

echo ""
echo "Summary ($(wc -l < "$GENESETS") genesets):"
printf "  %-16s %s\n" "Status" "Count"
printf "  %-16s %s\n" "------" "-----"
for tag in $(printf '%s\n' "${!counts[@]}" | sort); do
  printf "  %-16s %d\n" "$tag" "${counts[$tag]}"
done
