#!/bin/bash
# Set up the reference run of a template: OMA on the template's reference genomes alone, with the
# backbone species tree from species_trees.tsv. Gene sets that ARE a reference genome
# (make_oma_db_files.pl writes REFERENCE_GENOME.txt for them, e.g. Nematostella RefSeq = NEMVE,
# Drosophila FlyBase = DROME) take their orthologs from this run instead of a run of their own.
#
#   bash make_reference_run.sh [TEMPLATE_DIR]      (default: $OMA_TEMPLATES/$DEFAULT_TEMPLATE, from oma.conf)
#   cd TEMPLATE_DIR/reference_run && sbatch oma-part1.sh     # then, when it has finished:
#   sbatch oma-part3.sh
#
# Part 2 is not needed: the template's precomputed all-against-all already covers every pair of
# reference genomes. Run once per template.

set -euo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
OMA_CONF=${OMA_CONF:-$HERE/oma.conf}
[ -r "$OMA_CONF" ] || { echo "ERROR: no settings file $OMA_CONF (copy oma.conf.example to oma.conf)" >&2; exit 1; }
# shellcheck disable=SC1090
source "$OMA_CONF"
OMA_TEMPLATES=${OMA_TEMPLATES:-$OMA_V2/OMA_TEMPLATE_RUNS}
TEMPLATE_DIR=${1:-$OMA_TEMPLATES/$DEFAULT_TEMPLATE}
TEMPLATE_DIR=$(cd "$TEMPLATE_DIR" && pwd)
RUN_DIR="$TEMPLATE_DIR/reference_run"

if [ ! -d "$TEMPLATE_DIR/OMA.2.7.0/DB" ]; then
  echo "ERROR: no $TEMPLATE_DIR/OMA.2.7.0/DB (not a template directory)" >&2
  exit 1
fi
if [ -e "$RUN_DIR" ]; then
  echo "ERROR: $RUN_DIR already exists; remove it first to start over" >&2
  exit 1
fi

# backbone tree: the newick line after "Reference backbone" in species_trees.tsv
BACKBONE=$(awk '/Reference backbone/ {getline; sub(/^#[[:space:]]*/, ""); print; exit}' "$HERE/species_trees.tsv")
if [ -z "$BACKBONE" ]; then
  echo "ERROR: no backbone tree found in $HERE/species_trees.tsv" >&2
  exit 1
fi
# every leaf must be a genome in the template, and every genome a leaf
LEAVES=$(echo "$BACKBONE" | grep -oE '[A-Za-z0-9]+' | sort | tr '\n' ' ')
GENOMES=$(ls "$TEMPLATE_DIR/OMA.2.7.0/DB/"*.fa | xargs -n1 basename | sed 's/\.fa$//' | sort | tr '\n' ' ')
if [ "$LEAVES" != "$GENOMES" ]; then
  echo "ERROR: backbone leaves ($LEAVES) are not this template's genomes ($GENOMES)" >&2
  exit 1
fi

echo "Copying $TEMPLATE_DIR/OMA.2.7.0 to $RUN_DIR"
cp -r "$TEMPLATE_DIR/OMA.2.7.0" "$RUN_DIR"
mkdir -p "$RUN_DIR/slurm"
sed -i "s|^SpeciesTree := .*|SpeciesTree := '$BACKBONE';|" "$RUN_DIR/parameters.drw"
grep -q "^SpeciesTree := '(" "$RUN_DIR/parameters.drw" \
  || { echo "ERROR: could not set SpeciesTree in $RUN_DIR/parameters.drw" >&2; exit 1; }
printf '\n# ---- reference run ----\nReference run: the template genomes only, backbone species tree; made %s\n' "$(date '+%Y-%m-%d')" \
  >> "$RUN_DIR/README.exportedAllAll"

echo "SpeciesTree: $BACKBONE"
echo "Next: cd $RUN_DIR && sbatch oma-part1.sh   (then sbatch oma-part3.sh when it has finished)"
