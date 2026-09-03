#!/usr/bin/env bash

source ~/miniconda3/etc/profile.d/conda.sh
conda activate rbbh


WDIR=/home/smr/sciproj/SBNVEC/genomes/Nvec200/aligned/tcs_v2/analysis/rbbh_2026_02_09
cd $WDIR

## SET UP
for i in jaNemVect1 HUMAN  ; do
 cd $WDIR
 if [ ! -e $WDIR/$i ];then
   mkdir $WDIR/$i
 fi
 if [ ! -e  $WDIR/$i/$i.fa ] ; then
    echo "Setting up: $i";
    if [ $i = "jaNemVect1" ]; then
      ln -s /n/sci/SCI-003939-SBNVEC/genomes/jaNemVect1.1/ncbi_dataset/data/GCF_932526225.1/protein.faa $WDIR/$i/$i.fa
		  ln -s /n/sci/SCI-003939-SBNVEC/genomes/jaNemVect1.1/ncbi_dataset/data/GCF_932526225.1/genomic.gff $WDIR/$i/$i.gff
		  zcat /n/sci/SCI-003939-SBNVEC/genomes/jaNemVect1.1/ncbi_dataset/data/GCF_932526225.1/GCF_932526225.1_jaNemVect1.1_feature_table.txt.gz $WDIR/$i/feature_table.txt
    elif  [ $i = "HUMAN" ]; then
      zcat /n/sci/SCI-004223-SBGENOMES/db/ENS_homo_sapiens/release-113/Homo_sapiens.GRCh38.pep.all.fa.gz > $WDIR/$i/$i.fa
    fi
 fi
done

THIS_ORG=Nematostella_vectensis
DATADIR=/n/sci/SCI-004223-SBGENOMES/genomes/$THIS_ORG/current/
SCRIPTS=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/build_and_load_db/analysis_parsers/rbbh/
if [ ! -e $THIS_ORG.isoforms ] ; then
  echo "Making Isoform file: $THIS_ORG.isoforms"
  perl $SCRIPTS/make_rbbh_isoforms_from_GFF.pl  $DATADIR/genomic.gff > $THIS_ORG.isoforms
fi


for i in jaNemVect1 HUMAN  ; do
  cd ${WDIR}/${i}
  if [ ! -e  $THIS_ORG.isoforms ]; then
    ln -s  ${WDIR}/$THIS_ORG.isoforms.
  fi
  if  [ ! -e $THIS_ORG.fa ]; then
    ln -s $DATADIR/protein.aa.fa $THIS_ORG.fa 
  fi
  if [ $i = "HUMAN" ]; then # ENSEMB:
    perl $SCRIPTS/make_isoforms_rbbh_ENSFASTA.pl ${i}.fa > ${i}.isoforms
    perl $SCRIPTS/getDesc_ENS_FA.pl ${i}.fa > desc.txt
  elif [ $i = "jaNemVect1" ]; then # REFSEQ
    perl $SCRIPTS/make_isoforms_rbbh_REFSEQGFF.pl ${i}.gff > ${i}.isoforms
    perl $SCRIPTS/getDesc_REFSEQ_featureTable.pl feature_table.txt > desc.txt
  fi
  sbatch $SCRIPTS/run_rbbh.sbatch.sh $THIS_ORG $i
done


