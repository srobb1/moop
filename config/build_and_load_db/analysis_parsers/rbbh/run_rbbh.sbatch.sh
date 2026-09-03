#!/usr/bin/bash
#SBATCH --job-name=HC-RBBH
#SBATCH --cpus-per-task=32           # Number of CPU cores per task
#SBATCH --nodes=1                   # Run all processes on a single node
#SBATCH --ntasks=1                  # Run 1 task
#SBATCH --mem=32gb                   # Job memory request
#SBATCH --time=24:00:00              # Time limit hrs:min:sec
#SBATCH --output=slurm_logs/%x_%j.out
#SBATCH --error=slurm_logs/%x_%j.err

THIS_ORG=$1
ORG=$2

TMPDIR=/scratch/${USER}/${SLURM_JOB_ID}
DIR=`pwd`/${ORG}
cd $DIR

module load diamond/2.1.6
source ~/miniconda3/etc/profile.d/conda.sh
conda activate rbbh

RBBH=/n/sci/SCI-004223-SBGENOMES/shared_scripts/bin/reciprocal_alignment.py
FA1=${THIS_ORG}.fa
FA2=${ORG}.fa
ISO1=${THIS_ORG}.isoforms
ISO2=${ORG}.isoforms

$RBBH $FA1 $FA2 --protein2gene1 $ISO1 --protein2gene2 $ISO2 -o ${FA1}.${FA2}.results.tsv

