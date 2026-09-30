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
mkdir -p "$TMP_DIR"

echo "Submitting InterProScan array for $ORG/$ASSEMBLY/$GENESET (blocks until done)..."
N_CHUNKS=100
sbatch --wait --array=1-$N_CHUNKS \
  "$SCRIPT_DIR/run_interproscan_geneset.sbatch" "$QUERY_FASTA" "$TMP_DIR"

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

## one JSON document, as a single InterProScan run writes: the chunks' "results" joined
python3 - "$OUT_DIR/interproscan_results.json" "${JSON_CHUNKS[@]}" <<'PY'
import json, sys
out_path, chunks = sys.argv[1], sys.argv[2:]
merged = None
for chunk in chunks:
    with open(chunk) as fh:
        doc = json.load(fh)
    if merged is None:
        merged = {k: v for k, v in doc.items() if k != "results"}
        merged["results"] = []
    merged["results"].extend(doc["results"])
with open(out_path, "w") as fh:
    json.dump(merged, fh)
print(f"JSON: {len(merged['results'])} proteins from {len(chunks)} chunks", file=sys.stderr)
PY

echo "$IPRSCAN_VER" > "$OUT_DIR/interproscan_version.txt"
gzip -f "$OUT_DIR/interproscan_results.tsv" "$OUT_DIR/interproscan_results.json"

echo "Done: $OUT_DIR/interproscan_results.tsv.gz"
echo "      $OUT_DIR/interproscan_results.json.gz"
