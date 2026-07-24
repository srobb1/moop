## once diamond blast has been completed 
## diamond blastp --ultra-sensitive --evalue 1e-5 --query query.fa --db dbfile --out tophit.tsv --max-target-seqs 1 --outfmt 6 qseqid sseqid stitle evalue
## we also always make a db_version.txt for every db we use, and we keep it in the results directory. This file is read here to add the db_version info to the metadata section of the moop file that is used to load into the moop sqlite db
## if the db is from a specific organism we can tell based on the name of the results directory ENS_danio_rerio. I use the organisms name in the metadata section of this output file


THIS_ORG=$1

# Parsing Scripts
REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers

# BLAST HOMOLOGS
DATADIR=~/sciproj/SBGENOMES/genomes/$THIS_ORG/current/analysis/diamond_blast
THIS_ORG=`realpath $DATADIR | perl -p -e 's/.+genomes\/([^\/+])\//$1/'`


## SWISSPROT BLAST 
VERSION=`head -1 $DATADIR/UNIPROT_sprot/db_version.txt`
if [ -e tophit.tsv ];then
  rm tophit.tsv
fi

if [ -e $DATADIR/UNIPROT_sprot/tophit.tsv.gz ];then
  echo "results are compressed"
  zcat $DATADIR/UNIPROT_sprot/tophit.tsv.gz > tophit.tsv
else
  # ~/sciproj/SBGENOMES/genomes/Montipora_capitata/current/analysis/diamond_blast/UNIPROT_sprot/tophit.tsv
  echo "results are not compressed"
  if [ -e tophit.tsv ] ; then unlink tophit.tsv ; fi
  ln -s $DATADIR/UNIPROT_sprot/tophit.tsv tophit.tsv
fi

ls -l tophit.tsv
perl $SCRIPTS/get_diamond_blast.moop.pl tophit.tsv 'UniProtKB/Swiss-Prot' $VERSION https://www.uniprot.org https://www.uniprot.org/uniprotkb/  


## ENSEMBL BLAST


for RESULTS in $DATADIR/ENS_* ; do
  if [ -e tophit.tsv ];then
    rm tophit.tsv
  fi
  if [ -e $RESULTS/tophit.tsv.gz ];then
    echo "results are compressed"
    zcat $RESULTS/tophit.tsv.gz > tophit.tsv
  elif [ -e $RESULTS/tophit.tsv ];then
    echo "results are not compressed"
    if [ -e tophit.tsv ] ; then unlink tophit.tsv ; fi
    ln -s $RESULTS/tophit.tsv tophit.tsv
  else
    echo "$RESULTS/tophit.tsv does not exist"
  fi

  ORGSTRING=`realpath $RESULTS/tophit.tsv | perl -p -e 's/.+ENS_([^\/]+).+/$1/'`
  ORG=`echo $ORGSTRING | perl -pe 's/^(\w)/\u$1/' |  perl -pe 's/_(\w)/ \L$1/g'` 
  VERSION=`head -1 $RESULTS/db_version.txt`
  echo $RESULTS
  ls -l tophit.tsv
  perl $SCRIPTS/get_diamond_blast.moop.pl tophit.tsv "Ensembl $ORG" $VERSION https://www.ensembl.org/ https://www.ensembl.org/Multi/Search/Results?q= 
done
