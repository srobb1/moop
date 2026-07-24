#!/bin/bash

# set our woring director to where we curently are
CWD=`pwd`
THIS_ORG=$1
echo "MAKING $THIS_ORG EGGNOG MOOP FILES: $CWD"

# Parsing Scripts
REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers

# INTERPROSCAN
DATADIR=~/sciproj/SBGENOMES/genomes/$THIS_ORG/current/analysis/eggnog_mapper
MAPPERVERSION=`cat $DATADIR/version.txt | grep emapper | perl -p -e 's/.*(emapper-\S+).*/$1/'`
DBVERSION=`cat $DATADIR/version.txt | grep 'eggNOG DB version:' | perl -p -e 's/.*?eggNOG DB version: (\S+).*/$1/'`
VERSION="$MAPPERVERSION DB_$DBVERSION" 

# ~/sciproj/SBGENOMES/genomes/Montipora_capitata/current/analysis/eggnog_mapper/emapper.annotations
echo "perl $SCRIPTS/parse_EggNOG_to_MOOP_TSV.pl $DATADIR/emapper.annotations $VERSION"
perl $SCRIPTS/parse_EggNOG_to_MOOP_TSV.pl $DATADIR/emapper.annotations $VERSION

source /home/smr/miniconda3/etc/profile.d/conda.sh
conda activate goatools
python3 $SCRIPTS/reduce_eggnog_go.moop.py EggNOG2GO.eggnog.moop.tsv
