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
# Output-correctness tags (these check the RESULT, not just that inputs exist):
#   NOT_LOADED    features.tsv was built but no organism.sqlite came out of it
#   NO_FEATURES   database exists but this geneset loaded zero features
#   BAD_PARENTS   parent_feature_id is text/'NULL', or a feature is its own parent
#   ORPHAN_ANNOT  annotation rows that nothing points at (organism-wide count)
#   ID_MISMATCH   database uniquenames are not present as FASTA headers, so
#                 sequence retrieval silently returns nothing
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

  # ── Output correctness ─────────────────────────────────────────────────────
  # Everything above checks that INPUTS exist. These check that the RESULT is
  # usable, which is a different question -- and the one that has bitten us.
  # Every defect found on 2026-07-24 passed the checks above and reported OK:
  #   * a gene set whose CDS FASTA ids did not match the database (0 of 32,370
  #     sequences retrievable, no error anywhere)
  #   * an organism with 306,781 annotations and zero features
  #   * 81 databases whose root features could not be found by "IS NULL"
  ORG_DB="$DATA/$org/organism.sqlite"
  if [ -s "$ORG_DB" ]; then

    q() { sqlite3 -readonly "$ORG_DB" "$1" 2>/dev/null; }

    # Features actually loaded FOR THIS GENE SET (the DB is per organism and may
    # hold several gene sets, so an organism-wide count would hide an empty one).
    nfeat=$(q "SELECT COUNT(*) FROM feature f
                 JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                WHERE gs.gene_set_name = '$gs';")
    if [ "${nfeat:-0}" -eq 0 ]; then
      log "NO_FEATURES" "loaded 0 features" "$org" "$asm" "$gs"; any_issue=true
    fi

    # parent_feature_id must be an integer or a real NULL, and never point at
    # its own row. A text 'NULL' makes every root unfindable; a self-reference
    # makes any recursive walk up the tree non-terminating.
    nbad=$(q "SELECT COUNT(*) FROM feature f
                JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
               WHERE gs.gene_set_name = '$gs'
                 AND (typeof(f.parent_feature_id) NOT IN ('integer','null')
                      OR f.parent_feature_id = f.feature_id);")
    if [ "${nbad:-0}" -gt 0 ]; then
      log "BAD_PARENTS" "$nbad bad parent_feature_id" "$org" "$asm" "$gs"; any_issue=true
    fi

    # Annotation rows nothing points at. Organism-scoped, not gene-set scoped,
    # because annotation has no gene_set column -- so this reports once per gene
    # set for the same organism, which is noisy but never wrong.
    norph=$(q "SELECT COUNT(*) FROM annotation a
                WHERE NOT EXISTS (SELECT 1 FROM feature_annotation fa
                                   WHERE fa.annotation_id = a.annotation_id);")
    if [ "${norph:-0}" -gt 0 ]; then
      log "ORPHAN_ANNOT" "$norph unattached annotations" "$org" "$asm" "$gs"; any_issue=true
    fi

    # THE ID INVARIANT: feature_uniquename IS the FASTA lookup key. When it
    # breaks nothing errors -- sequence retrieval just returns empty. Sample a
    # few ids per type and confirm they exist as FASTA headers.
    for pair in "mRNA:transcript.nt.fa" "cds:cds.nt.fa" "protein:protein.aa.fa"; do
      ftype=${pair%%:*}
      fasta="$GENESET_DATA/${pair#*:}"
      [ -s "$fasta" ] || continue

      # feature_type case varies by source (cds vs CDS), so compare lowercased.
      sample=$(q "SELECT f.feature_uniquename FROM feature f
                    JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                   WHERE gs.gene_set_name = '$gs'
                     AND lower(f.feature_type) = lower('$ftype')
                   LIMIT 200;")
      [ -n "$sample" ] || continue

      hits=$(grep '^>' "$fasta" | awk '{print substr($1,2)}' \
             | grep -Fxc -f <(printf '%s\n' "$sample") 2>/dev/null || true)
      total=$(printf '%s\n' "$sample" | grep -c .)
      if [ "${hits:-0}" -eq 0 ]; then
        log "ID_MISMATCH" "$ftype: 0/$total ids in ${pair#*:}" "$org" "$asm" "$gs"; any_issue=true
      elif [ "${hits:-0}" -lt "$total" ]; then
        log "ID_MISMATCH" "$ftype: $hits/$total ids in ${pair#*:}" "$org" "$asm" "$gs"; any_issue=true
      fi
    done

  elif [ -s "$GENESET_DATA/features.tsv" ]; then
    # features.tsv was built but no database came out of it.
    log "NOT_LOADED" "features.tsv built, no organism.sqlite" "$org" "$asm" "$gs"; any_issue=true
  fi

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
