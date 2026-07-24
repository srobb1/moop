
THIS_ORG=$1

# Parsing Scripts
REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers

# INTERPROSCAN
DATADIR=~/sciproj/SBGENOMES/genomes/$THIS_ORG/current/analysis/interproscan
VERSION=`cat $DATADIR/version.txt`

if [ -e iprscan.tsv ]; then
   echo "cleaning up sym links for: iprscan_results.tsv "
   rm iprscan.tsv 
fi

if [ -e $DATADIR/iprscan_results.tsv.gz ];then
  zcat $DATADIR/iprscan_results.tsv.gz > iprscan.tsv
else
  ln -s $DATADIR/iprscan_results.tsv iprscan.tsv
fi

perl $SCRIPTS/get_interproscan.moop.pl iprscan.tsv $VERSION

