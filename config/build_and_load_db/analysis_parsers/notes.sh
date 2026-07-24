
CWD=`pwd`

# Parsing Scripts
SCRIPTS=/home/smr/sciproj/SBGENOMES/dev/smr_dev/moop/build_and_load_db/analysis_parsers

# OMA ORTHOLOGS
OMA_DIR=~/sciproj/SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/combinedModels/orthologs/oma_x_y/HUMAN_MOUSE_CHICK_ANOCA/OMA.2.6.0

## For OMA output: list of organsism you want to orthologs for in moop upload files
ORGS=(HOMSAP MUSMUS ANOCAR)
FASTAS=(Homo_sapiens.GRCh38.pep.all.fa Mus_musculus.GRCm39.pep.all.fa Anolis_carolinensis.AnoCar2.0v2.pep.all.fa)
DATA_DIR=$OMA_DIR/parse_output
OMA_DB_V=Jul_2024

## Make DESC Files
for FASTA in ${FASTAS[@]} ; do
  if [ ! -e $DATA_DIR/$FASTA ]; then
    gzip -c -d $DATA_DIR/$FASTA.gz $FASTA 
  fi
  if [ ! -e $FASTA.info.txt ] ; then
     perl $SCRIPTS/get_descs_ENSEMBL_FASTA.pl $DATA_DIR/$FASTA > $FASTA.info.txt
  fi
done
cat *info.txt > desc.txt


for MYORG in CCA3X CCA3Y; do
  if [ ! -d ${MYORG}_OMA ];
    mkdir -p $CWD/${MYORG}_OMA
  fi
  cd $CWD/${MYORG}_OMA
  perl $SCRIPTS/getOMA_orthologs.moop.pl $OMA_DIR/Output/OrthologousGroups.txt $MYORG 
done

for MYORG in CCA3X CCA3Y; do
  for OTHERORG in ${ORGS[@]}; do
    if [ -e  $OMA_DIR/Output/PairwiseOrthologs/${MYORG}-$OTHERORG.txt ]; then
      
      perl $SCRIPTS/getOMA_pairs.moop.pl $OMA_DIR/Output/PairwiseOrthologs/${MYORG}-$OTHERORG.txt $MYORG $OTHERORG 1
    fi
    if [ -e  $OMA_DIR/Output/PairwiseOrthologs/${OTHERORG}-${MYORG}.txt ];then 
      perl $SCRIPTS/getOMA_pairs.moop.pl $OMA_DIR/Output/PairwiseOrthologs/$OTHERORG-${MYORG}.txt $MYORG $OTHERORG 0
    fi
  done

  for ORG in ${ORGS[@]}; do 
    echo "## Annotation Source: OMA $ORG" > $ORG.oma.metadata.txt
    echo "## Annotation Source Version: $OMA_DB_V" >> $ORG.oma.metadata.txt
    echo "## Annotation Source URL: https://omabrowser.org/oma/home/" >> $ORG.oma.metadata.txt
    echo "## Annotation Accession URL: https://www.ensembl.org/Multi/Search/Results?q=" >> $ORG.oma.metadata.txt
    echo "## Annotation Type: Orthologs" >> $ORG.oma.metadata.txt
    echo "## ID Accession Accession_Description Ortholog_type" >> $ORG.oma.metadata.txt

    perl $SCRIPTS/generate_annotation_load_file.pl $MYORG-$ORG.oma_orthologs.txt desc.txt $ORG.oma.metadata.txt > to_load/$MYORG.$ORG.oma_orthologs.moop.txt
    perl $SCRIPTS/generate_annotation_load_file.pl $MYORG-$ORG.oma_pairs.txt desc.txt $ORG.oma.metadata.txt > to_load/$MYORG.$ORG.oma_pairs.moop.txt
  done
done

# BLAST HOMOLOGS
DATADIR=~/sciproj/SBGENOMES/genomes/Chamaeleo_calyptratus/current/analysis/diamond_blast
THIS_ORG=`realpath $DATADIR | perl -p -e 's/.+genomes\/([^\/+])\//$1/'`

if [ ! -d ${THIS_ORG}_HOMOLOGS ];
  mkdir -p $CWD/${THIS_ORG}_HOMOLOGS
fi
cd $CWD/${THIS_ORG}_HOMOLOGS

## SWISSPROT BLAST 
DBDIR=~/sciproj/SBGENOMES/db/UNIPROT_sprot/current
if [  -e swissprot.desc.txt ];then
  rm swissprot.desc.txt
fi

perl $SCRIPTS/get_descs_SWISSPROT_FASTA.pl $DBDIR/uniprot_sprot.fasta > swissprot.desc.txt
VERSION=`head -1 $DBDIR/relnotes.txt | perl -p -e 's/^.+(\d\d\d\d_\d\d).*/$1/'` 
echo "## Annotation Source: UniProtKB/Swiss-Prot" > swissprot.metadata.txt
echo "## Annotation Source Version: $VERSION" >> swissprot.metadata.txt
echo "## Annotation Source URL https://www.uniprot.org: " >> swissprot.metadata.txt
echo "## Annotation Accession URL: https://www.uniprot.org/uniprotkb/" >> swissprot.metadata.txt
echo "## Annotation Type: Homologs" >> swissprot.metadata.txt
echo "## ID Accession Accession_Description Score" >> swissprot.metadata.txt

perl $SCRIPTS/get_diamond_blast.moop.pl $DATADIR/UNIPROT_sprot/tophit.tsv > swissprot.moop.txt
perl $SCRIPTS/generate_annotation_load_file.pl $THIS_ORG.swissprot.moop.txt  swissprot.desc.txt swissprot.metadata.txt


## ENSEMBL BLAST
if [ -e ensembl.desc.txt ];then
  rm ensembl.desc.txt
fi

for DB in ~/sciproj/SBGENOMES/db/ENS_*/current/peptide.fa.gz; do
  VERSION=`realpath $DB | perl -p -e 's/.+(release-\d+).+/$1/'`
  ORGSTRING=`realpath $DB | perl -p -e 's/.+ENS_([^\/]+).+/$1/'`
  ORG=`echo $ORGSTRING | perl -pe 's/^(\w)/\u$1/' |  perl -pe 's/_(\w)/ \L$1/g'` 
  zcat $DB | perl get_descs_ENSEMBL_FASTA_STDIN.pl >> ensembl.desc.txt
  echo "## Annotation Source: ENSEMBL $ORG" > ensembl.metadata.txt
  echo "## Annotation Source Version: $VERSION" >> ensembl.metadata.txt
  echo "## Annotation Source URL https://www.ensembl.org/: " >> ensembl.metadata.txt
  echo "## Annotation Accession URL: https://www.ensembl.org/Multi/Search/Results?q=" >> ensembl.metadata.txt
  echo "## Annotation Type: Homologs" >> ensembl.metadata.txt
  echo "## ID Accession Accession_Description Score" >> ensembl.metadata.txt
done

for RESULTS in $DATADIR/ENS_*/tophit.tsv ; do
  ORGSTRING=`realpath $RESULTS | perl -p -e 's/.+ENS_([^\/]+).+/$1/'`
  ORG=`echo $ORGSTRING | perl -pe 's/^(\w)/\u$1/' |  perl -pe 's/_(\w)/ \L$1/g'` 
  perl $SCRIPTS/get_diamond_blast.moop.pl $RESULTS ensembl.metadata.txt > $ORGSTRING.ensembl.txt
done


# INTERPROSCAN
DATADIR=~/sciproj/SBGENOMES/genomes/Chamaeleo_calyptratus/current/analysis/interproscan
VERSION=`cat $DATADIR/version.txt`
perl $SCRIPTS/get_interproscan.moop.pl $DATADIR/iprscan_results.tsv $VERSION

