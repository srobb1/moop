
THIS_ORG=$1

# Parsing Scripts
REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers

# INTERPROSCAN
DATADIR=~/sciproj/SBGENOMES/genomes/$THIS_ORG/current/analysis/protnlm
perl $SCRIPTS/get_protnlm.moop.pl $DATADIR/protnlm_pred_results.tsv

