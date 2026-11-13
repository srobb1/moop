#!/usr/bin/bash
# One-time (and per FastOMA version) set-up, on a node with internet: the OMAmer database, the FastOMA
# pipeline (Nextflow) and a conda environment with FastOMA's software. The cluster jobs then need no internet.
# Not FastOMA's container: this cluster's Singularity runs only images owned by hpc_admins
# ("limit container groups"), so a container we pull ourselves cannot run. FastOMA's conda environment
# installs FastOMA from its own source ("pip: .[report]"), so it is built here, inside the pipeline's folder.
#   bash fetch_fastoma.sh
set -euo pipefail
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
FASTOMA_CONF=${FASTOMA_CONF:-$HERE/fastoma.conf}
[ -r "$FASTOMA_CONF" ] || { echo "ERROR: no settings file $FASTOMA_CONF (copy fastoma.conf.example)" >&2; exit 1; }
source "$FASTOMA_CONF"
command -v module >/dev/null || source /etc/profile.d/modules.sh
module load "$NEXTFLOW_MODULE"
export NXF_HOME=$FASTOMA_ROOT/nextflow_home
mkdir -p "$(dirname "$OMAMER_DB")" "$NXF_HOME"

if [ -s "$OMAMER_DB" ]; then
  echo "OMAmer database: $OMAMER_DB (already here)"
else
  echo "downloading $OMAMER_URL"
  curl -fL -C - --retry 5 -o "$OMAMER_DB.part" "$OMAMER_URL"
  mv "$OMAMER_DB.part" "$OMAMER_DB"
  echo "downloaded $(date +%F) from $OMAMER_URL" > "$OMAMER_DB.SOURCE.txt"
fi

nextflow pull dessimozlab/FastOMA -r "v$FASTOMA_VERSION"
# the Nextflow plugins the pipeline declares (nextflow.config "plugins"): the jobs run offline
for plugin in $(sed -n "s/^[[:space:]]*id[[:space:]]*'\\([^']*\\)'.*/\\1/p" "$NXF_HOME/assets/dessimozlab/FastOMA/nextflow.config"); do
  nextflow plugin install "$plugin"
done

ENV=$FASTOMA_ROOT/conda_env_$FASTOMA_VERSION
# FastOMA's environment-conda.yml lists Anaconda's "defaults" channel, which is rate-limited (and under
# Anaconda's terms of use); every package is on conda-forge / bioconda, so build from those only, then
# install FastOMA itself (its pyproject: Python < 3.13) with pip from the pipeline's source.
if [ ! -x "$ENV/bin/fastoma-infer-roothogs" ]; then
  module load "$CONDA_MODULE"
  rm -rf "$ENV"
  conda create -y -p "$ENV" --override-channels -c conda-forge -c bioconda \
    "python=3.11" pip omamer mafft fasttree mmseqs2 papermill jupyter matplotlib seaborn pyparsing networkx
  "$ENV/bin/pip" install --no-cache-dir "$NXF_HOME/assets/dessimozlab/FastOMA[report]"
fi
"$ENV/bin/omamer" --version >/dev/null 2>&1 && "$ENV/bin/fastoma-infer-roothogs" --help >/dev/null 2>&1 \
  || { echo "ERROR: the conda environment $ENV does not run omamer / fastoma-infer-roothogs" >&2; exit 1; }
"$ENV/bin/conda" list -p "$ENV" > "$ENV.packages.txt" 2>/dev/null || true
echo "ready: FastOMA $FASTOMA_VERSION, $(du -h "$OMAMER_DB" | cut -f1) OMAmer database, conda environment $ENV"
