#!/usr/bin/bash
## Submits the 100-task InterProScan array for one genomes_v2 gene-set,
## blocks until it finishes (sbatch --wait), then combines/sorts the chunk
## results into the ANNOTATIONS layout moop_process_genome_data_v2.sbatch
## expects (interproscan/iprscan_results.tsv.gz).
##
## This can take hours — run it in tmux/screen or with nohup, not directly
## in a shell you might disconnect from.
##
## Usage: bash run_interproscan_geneset.sh <organism> <assembly> <geneset>

set -euo pipefail

ORG=${1:-}; ASSEMBLY=${2:-}; GENESET=${3:-}
[ -n "$GENESET" ] || { echo "Usage: $0 <organism> <assembly> <geneset>"; exit 1; }

IPRSCAN_VER=5.76-107.0

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ANNOTATIONS_DIR=$(bash "$SCRIPT_DIR/pick_annotations_run.sh")
QUERY_FASTA=$(bash "$SCRIPT_DIR/make_clean_query_fasta.sh" "$ORG" "$ASSEMBLY" "$GENESET")

OUT_DIR=$ANNOTATIONS_DIR/$ORG/$ASSEMBLY/$GENESET/interproscan
mkdir -p "$OUT_DIR"

JOB_TAG="${ORG}_${ASSEMBLY}_${GENESET}"
TMP_DIR=/scratch/$USER/tmp/interproscan/$JOB_TAG
mkdir -p "$TMP_DIR"

echo "Submitting InterProScan array for $ORG/$ASSEMBLY/$GENESET (blocks until done)..."
sbatch --wait --array=1-100 \
  "$SCRIPT_DIR/run_interproscan_geneset.sbatch" "$QUERY_FASTA" "$TMP_DIR"

cat "$TMP_DIR"/out_*/*.tsv > "$TMP_DIR/comb_chunks_result.tsv"

tsv_header="seq_id\tprotein_md5\tprotein_length\tanalysis\tsignature_id\tsignature_desc\tstart\tend\tscore\tstatus\tdate\tinterpro_id\tinterpro_desc\tgo_terms\tpathway_terms"
sort -t $'\t' -k 1,1 "$TMP_DIR/comb_chunks_result.tsv" > "$TMP_DIR/sorted_comb_chunks_result.tsv"
{ echo -e "$tsv_header"; cat "$TMP_DIR/sorted_comb_chunks_result.tsv"; } > "$OUT_DIR/iprscan_results.tsv"

echo "$IPRSCAN_VER" > "$OUT_DIR/version.txt"
gzip -f "$OUT_DIR/iprscan_results.tsv"

echo "Done: $OUT_DIR/iprscan_results.tsv.gz"
