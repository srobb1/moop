#!/usr/bin/bash
#SBATCH --job-name=iprscan_geneset
#SBATCH --cpus-per-task=8
#SBATCH --mem=24gb
#SBATCH --time=12:00:00
#SBATCH --partition=compute
#SBATCH --output=/n/sci/SCI-004223-SBGENOMES/slurm_logs/interproscan/%x_%A_%a.out
#SBATCH --error=/n/sci/SCI-004223-SBGENOMES/slurm_logs/interproscan/%x_%A_%a.err
#
# One chunk of an InterProScan run for an arbitrary protein FASTA. Meant to
# be submitted as an array job by run_interproscan_geneset.sh, which also
# combines the per-chunk results afterward. Not meant to be sbatch'd directly.
#
# Usage: sbatch --array=1-100 run_interproscan_geneset.sbatch <query_fasta> <tmp_dir>

IPRSCAN_VER=5.78-109.0   # as the annotation pipeline; PANTHER 19.0 (model lengths, TreeGrafter data)
BASE=/n/sci/SCI-004223-SBGENOMES
IPRSCAN=$BASE/src/interproscan/interproscan-${IPRSCAN_VER}/interproscan.sh
FASTA2CHUNKS=$BASE/repos/pipeline_v1/accessory/fasta2chunks   # was $BASE/pipeline/accessory (moved)

QUERY_FASTA=$1
TMP_DIR=$2
[ -s "$QUERY_FASTA" ] || { echo "ERROR: missing $QUERY_FASTA"; exit 1; }
[ -x "$FASTA2CHUNKS" ] || { echo "ERROR: fasta2chunks not found at $FASTA2CHUNKS"; exit 1; }
[ -x "$IPRSCAN" ] || { echo "ERROR: InterProScan not found at $IPRSCAN"; exit 1; }

mkdir -p "$TMP_DIR/in_fasta_chunks"
mkdir -p "$TMP_DIR/out_${SLURM_ARRAY_TASK_ID}_of_${SLURM_ARRAY_TASK_MAX}"

ml simr python perl
export PATH
PATH=$(echo "$PATH" | awk -F':' '{ for (i = 1; i <= NF; i++) { print $i } }' | grep -v sm2699 | awk '{printf "%s:", $0}')
export JAVA_HOME=$BASE/src/jdk-21.0.2
export PATH=$PATH:$JAVA_HOME/bin

FASTA_CHUNK_FILE=$TMP_DIR/in_fasta_chunks/${SLURM_ARRAY_TASK_ID}_of_${SLURM_ARRAY_TASK_MAX}.faa
"$FASTA2CHUNKS" \
  -c "$SLURM_ARRAY_TASK_ID" \
  -n "$SLURM_ARRAY_TASK_MAX" \
  -f "$QUERY_FASTA" > "$FASTA_CHUNK_FILE" \
  || { echo "ERROR: fasta2chunks failed"; exit 1; }
## InterProScan exits 0 on an empty FASTA ("no sequences to analyse"): a broken chunking step
## must fail the task, not pass as done (2026-09-30: every task of a run "completed" in 20 s)
[ -s "$FASTA_CHUNK_FILE" ] || { echo "ERROR: empty chunk $FASTA_CHUNK_FILE"; exit 1; }

"$IPRSCAN" --cpu "$SLURM_CPUS_PER_TASK" \
  -i "$FASTA_CHUNK_FILE" \
  --output-dir "$TMP_DIR/out_${SLURM_ARRAY_TASK_ID}_of_${SLURM_ARRAY_TASK_MAX}" \
  --disable-precalc \
  --goterms \
  --enable-tsv-residue-annot \
  --pathways --output-tsv-version \
  -f TSV,JSON   # naming v2 needs both: TSV for families and domains, JSON for TreeGrafter graft points
