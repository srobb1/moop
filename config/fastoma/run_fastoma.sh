#!/usr/bin/bash
# Run FastOMA for one run folder made by setup_fastoma_run.py (called by its run_fastoma.sbatch).
#
#   run_fastoma.sh <run folder>
#
# This job only runs Nextflow; Nextflow submits FastOMA's steps as Slurm jobs (at most QUEUE_SIZE at once),
# each in the conda environment fetch_fastoma.sh built (not FastOMA's container: this cluster runs only
# containers owned by hpc_admins). Needs fetch_fastoma.sh to have run once. Nextflow's work folder is on scratch ($WORK_ROOT/<clade>) and is kept until the run has
# finished, so submitting again resumes where it stopped (-resume).
# When FastOMA has finished: checks the main outputs are there, packs the one-file-per-group FASTA folders
# into per_group_fasta.tar.gz, copies the result to <run folder>/results/Results_<date>_<job>, and removes
# the work folder.
set -euo pipefail
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
FASTOMA_CONF=${FASTOMA_CONF:-$HERE/fastoma.conf}
[ -r "$FASTOMA_CONF" ] || { echo "ERROR: no settings file $FASTOMA_CONF (copy fastoma.conf.example)" >&2; exit 1; }
# shellcheck disable=SC1090
source "$FASTOMA_CONF"
OUT=$(cd "${1:?usage: run_fastoma.sh <run folder>}" && pwd)
CLADE=$(basename "$OUT")
JOB=${SLURM_JOB_ID:-$$}
WORK=$WORK_ROOT/$CLADE
log() { echo "[$(date '+%F %T')] $*"; }

[ -s "$OUT/in_folder/species_tree.nwk" ] || { echo "ERROR: $OUT/in_folder not set up (setup_fastoma_run.py)"; exit 1; }
[ -s "$OMAMER_DB" ] || { echo "ERROR: no OMAmer database $OMAMER_DB (run fetch_fastoma.sh once)"; exit 1; }
command -v module >/dev/null || source /etc/profile.d/modules.sh
module load "$NEXTFLOW_MODULE"
module load "$CONDA_MODULE"      # Nextflow activates the environment with conda, in every FastOMA job
export NXF_HOME=$FASTOMA_ROOT/nextflow_home
export NXF_OFFLINE=true          # pipeline and conda environment were set up by fetch_fastoma.sh
ENV=$FASTOMA_ROOT/conda_env_$FASTOMA_VERSION
[ -x "$ENV/bin/omamer" ] || { echo "ERROR: no conda environment $ENV (run fetch_fastoma.sh once)"; exit 1; }

mkdir -p "$WORK"
cd "$WORK"
cat > cluster.config <<CONF
executor {
  name      = 'slurm'
  queueSize = $QUEUE_SIZE
}
conda {
  enabled = true
}
process {
  conda = '$ENV'
}
CONF

log "FastOMA $FASTOMA_VERSION on $(ls "$OUT/in_folder/proteome" | wc -l) proteomes; work in $WORK"
nextflow run dessimozlab/FastOMA -r "v$FASTOMA_VERSION" \
  -profile slurm -c cluster.config \
  --input "$OUT/in_folder" --omamer_db "$OMAMER_DB" \
  --output_folder "$WORK/out" --report \
  -w "$WORK/work" -resume

for f in OrthologousGroups.tsv RootHOGs.tsv FastOMA_HOGs.orthoxml orthologs.tsv.gz species_tree_checked.nwk; do
  [ -e "$WORK/out/$f" ] || { echo "ERROR: FastOMA finished without $f; work folder kept: $WORK"; exit 1; }
done
log "completed"

dest="$OUT/results/Results_$(date +%b%d)_$JOB"
mkdir -p "$dest"
# outputs can be links into work/: copy the files themselves; the per-group FASTA folders go in one tarball
( cd "$WORK/out" && tar -czhf "$dest/per_group_fasta.tar.gz" OrthologousGroupsFasta RootHOGsFasta 2>/dev/null || true )
rsync -rL --exclude OrthologousGroupsFasta --exclude RootHOGsFasta "$WORK/out/" "$dest/"
cp "$WORK"/.nextflow.log "$dest/nextflow.log" 2>/dev/null || true
log "copied to $dest ($(find "$dest" -type f | wc -l) files, $(du -sh "$dest" | cut -f1))"
cd /
rm -rf "$WORK"

# MOOP's file, as clade <clade>_fastoma (shown beside the OrthoFinder run of the same clade): pairs by the
# orthoxml's internal ids first (FastOMA's own pair file has protein ids only, which two gene sets can share)
"$ENV/bin/python" -I "$HERE/fastoma_pairs_by_id.py" "$dest/FastOMA_HOGs.orthoxml" "$dest/orthologs_by_id.tsv.gz"
python3 -I "$HERE/../build_and_load_db/scripts/build_clade_homologs.py" --run "$OUT" --results "$dest" \
  --clade "${CLADE}_fastoma" --taxonomy "$OF_ROOT/taxonomy" --out "$MOOP_DATA/.clades/${CLADE}_fastoma/homologs.sqlite"
log "done; ship it with: copy2moop_v2.sh --clades (or --all)"
