#!/usr/bin/bash
## Submits the 100-task InterProScan array for one genomes/v2 gene-set,
## blocks until it finishes (sbatch --wait), then combines/sorts the chunk
## results into the ANNOTATIONS layout moop_process_genome_data_v2.sbatch
## reads (interproscan/interproscan_results.tsv.gz), the annotation pipeline's names.
## Also merges the chunks' JSON into interproscan_results.json.gz: the TSV carries only
## the PANTHER family (PTHR...), the JSON's TreeGrafter block the subfamily
## (PTHR...:SF...) that naming v2 is to use (notes/NAMING_V2_PLAN.md).
##
## This can take hours — run it in tmux/screen or with nohup, not directly
## in a shell you might disconnect from.
##
## Usage: bash run_interproscan_geneset.sh <organism> <assembly> <geneset>
##        MERGE_ONLY=1 bash run_interproscan_geneset.sh <organism> <assembly> <geneset>
##          (no new array: combine the chunk results an earlier run left in the temp dir,
##           e.g. after the combine step failed)

set -euo pipefail

ORG=${1:-}; ASSEMBLY=${2:-}; GENESET=${3:-}
[ -n "$GENESET" ] || { echo "Usage: $0 <organism> <assembly> <geneset>"; exit 1; }

IPRSCAN_VER=5.78-109.0   # as the annotation pipeline; PANTHER 19.0 (model lengths, TreeGrafter data)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ANNOTATIONS_DIR=$(bash "$SCRIPT_DIR/pick_annotations_run.sh")
QUERY_FASTA=$(bash "$SCRIPT_DIR/make_clean_query_fasta.sh" "$ORG" "$ASSEMBLY" "$GENESET")

OUT_DIR=$ANNOTATIONS_DIR/$ORG/$ASSEMBLY/$GENESET/interproscan
mkdir -p "$OUT_DIR"

JOB_TAG="${ORG}_${ASSEMBLY}_${GENESET}"
TMP_DIR=/scratch/$USER/tmp/interproscan/$JOB_TAG
## start clean: chunk results left by an earlier run (another InterProScan version, or a failed
## run) would otherwise be counted and merged below as if this run had written them
N_CHUNKS=100
if [ "${MERGE_ONLY:-0}" = 1 ]; then
  echo "MERGE_ONLY: combining the chunk results already in $TMP_DIR (no new array)"
else
  rm -rf "$TMP_DIR"
  mkdir -p "$TMP_DIR"
  echo "Submitting InterProScan array for $ORG/$ASSEMBLY/$GENESET (blocks until done)..."
  sbatch --wait --array=1-$N_CHUNKS \
    "$SCRIPT_DIR/run_interproscan_geneset.sbatch" "$QUERY_FASTA" "$TMP_DIR"
fi

## a chunk without output would otherwise just be missing from the combined files
shopt -s nullglob
TSV_CHUNKS=("$TMP_DIR"/out_*/*.tsv)
JSON_CHUNKS=("$TMP_DIR"/out_*/*.json)
shopt -u nullglob
if [ ${#TSV_CHUNKS[@]} -ne $N_CHUNKS ] || [ ${#JSON_CHUNKS[@]} -ne $N_CHUNKS ]; then
  echo "ERROR: expected $N_CHUNKS TSV and JSON chunk files, found ${#TSV_CHUNKS[@]} TSV and ${#JSON_CHUNKS[@]} JSON in $TMP_DIR"
  exit 1
fi

cat "$TMP_DIR"/out_*/*.tsv > "$TMP_DIR/comb_chunks_result.tsv"

tsv_header="seq_id\tprotein_md5\tprotein_length\tanalysis\tsignature_id\tsignature_desc\tstart\tend\tscore\tstatus\tdate\tinterpro_id\tinterpro_desc\tgo_terms\tpathway_terms"
sort -t $'\t' -k 1,1 "$TMP_DIR/comb_chunks_result.tsv" > "$TMP_DIR/sorted_comb_chunks_result.tsv"
{ echo -e "$tsv_header"; cat "$TMP_DIR/sorted_comb_chunks_result.tsv"; } > "$OUT_DIR/interproscan_results.tsv"

## one JSON document, as a single InterProScan run writes: the chunks' "results" joined. Streamed,
## one chunk in memory at a time: loading all 100 at once (2.2 GB of JSON for Congeria's 43,768
## proteins) was killed for memory in a 16 GB session.
python3 "$SCRIPT_DIR/merge_interproscan_json.py" --out "$OUT_DIR/interproscan_results.json.gz" "${JSON_CHUNKS[@]}"

echo "$IPRSCAN_VER" > "$OUT_DIR/interproscan_version.txt"
gzip -f "$OUT_DIR/interproscan_results.tsv"

echo "Done: $OUT_DIR/interproscan_results.tsv.gz"
echo "      $OUT_DIR/interproscan_results.json.gz"
