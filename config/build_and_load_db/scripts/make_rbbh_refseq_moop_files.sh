## this can be run after rbbh has been completed 
## see /n/sci/SCI-003939-SBNVEC/genomes/Nvec200/aligned/tcs_v2/analysis/rbbh_2026_02_09/notes.sh 

THIS_ORG=$1
RESULTS_DIR=$2
ORG=$3  #jaNemVect1

REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers

RESULTS=$RESULTS_DIR/$ORG
VERSION=$ORG #`cat $RESULTS/version.txt`
echo "$RESULTS"
echo "$VERSION"
perl $SCRIPTS/parse_RBBH_to_MOOP_TSV.pl $RESULTS "RefSeq $ORG" $VERSION https://www.ncbi.nlm.nih.gov/ https://www.ncbi.nlm.nih.gov/search/all/?term=
