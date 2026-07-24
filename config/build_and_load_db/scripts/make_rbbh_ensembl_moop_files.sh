## once rbbh  has been completed 
## see /n/sci/SCI-004219-SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/combinedModels/rbbh/run_rbbh.sh 
#RBBH=/n/sci/SCI-004223-SBGENOMES/shared_scripts/bin/reciprocal_alignment.py
#FA1=CCA3.fa
#FA2=${ORG}.fa
#ISO1=CCA3.isoforms
#ISO2=${ORG}.isoforms
#$RBBH $FA1 $FA2 --protein2gene1 $ISO1 --protein2gene2 $ISO2 -o ${FA1}.${FA2}.results.tsv

## should rework the running of rbbh scripts to use this:
## we also always make a db_version.txt for every db we use, and we keep it in the results directory. This file is read here to add the db_version info to the metadata section of the moop file that is used to load into the moop sqlite db
## if the db is from a specific organism we can tell based on the name of the results directory ENS_danio_rerio. I use the organisms name in the metadata section of this output file


THIS_ORG=$1
RESULTS_DIR=$2
ORG=$3

REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers
VERSION=release-113

PRETTY_ORG="${ORG#ENS_}"
PRETTY_ORG="${PRETTY_ORG//_/ }"
PRETTY_ORG="${PRETTY_ORG^}"

#for ORG in HUMAN ANOLE MOUSE CHICK; do
  RESULTS=$RESULTS_DIR/$ORG
  echo "$RESULTS"
  perl $SCRIPTS/parse_RBBH_to_MOOP_TSV.pl $RESULTS "Ensembl $PRETTY_ORG" $VERSION https://www.ensembl.org/ https://www.ensembl.org/Multi/Search/Results?q=
#done
