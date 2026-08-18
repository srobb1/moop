#!/usr/bin/bash
# run Transdecoder on FASTA file
# ejr: 2023-07-12
# lmd: 2023-07-14
#SBATCH --job-name=transdecoder      # Job name
#SBATCH --nodes=1                    # Run all processes on a single node
#SBATCH --ntasks=1                   # Run a single task
#SBATCH --partition=compute          # use computer partition
#SBATCH --cpus-per-task=32            # Number of CPU cores per task
#SBATCH --mem=200gb                   # Job memory request
#SBATCH --time=02:00:00              # Time limit hrs:min:sec
#SBATCH --output=logs/transdecoder_%j.out
#SBATCH --error=logs/transdecoder_%j.err
#SBATCH --mail-type=ALL
#SBATCH --mail-user=ejr@stowers.org
#
# Before running you need to make a logs directory. The script can't make it



# Check if the user forgot to provide arguments
if [ -z "${1}" ] || [ -z "${2}" ]; then
    echo "Error: Missing argument(s)."
    echo "Usage: $0 <transcript FASTA> <transcript2gene.txt>"
    exit 1
fi

FASTA=$1
T2G=$2

# Check if the files actually exist
if [ ! -f "$FASTA" ]; then
    echo "Error: File '$FASTA' does not exist."
    exit 1
fi
if [ ! -f "$T2G" ]; then
    echo "Error: File '$T2G' does not exist."
    exit 1
fi

# Check if the files are empty
if [ ! -s "$FASTA" ]; then
    echo "Error: File '$FASTA' is empty."
    exit 1
fi
if [ ! -s "$T2G" ]; then
    echo "Error: File '$T2G' is empty."
    exit 1
fi

# Absolute paths, captured before any cd, so we can use/symlink them later
FASTA_ABS=$(readlink -f "$FASTA")
T2G_ABS=$(readlink -f "$T2G")

# Get the directory name from the file path
FASTA_DIR=$(dirname "$FASTA")
FASTA_DIR_ABS=$(dirname "$FASTA_ABS")

# Get just the file name without the folder path
FASTA_FILE=$(basename "$FASTA")

# Change directory into the folder where the FASTA file lives
cd "$FASTA_DIR"

# Update the FASTA variable to use the local file name
FASTA=$FASTA_FILE

OUTDIR=transdecoder_output/$FASTA
mkdir -p $OUTDIR

# select_longest_orf_geneset.pl's output goes in its own dir, mirroring
# transdecoder_output/ and cdhit_output/, not inside TransDecoder's own output
LONGEST_ORF_OUTDIR=$FASTA_DIR_ABS/longetORF_output/$FASTA
mkdir -p "$LONGEST_ORF_OUTDIR"

cd $OUTDIR

# TransDecoder writes its output next to whatever file it's given, so the
# source fasta has to actually be in this directory, not just named by it.
ln -sf "$FASTA_ABS" "$FASTA"

module purge
module load transdecoder/5.5.0

TransDecoder.LongOrfs -S -m 100 -t $FASTA
TransDecoder.Predict -t $FASTA --cpu 32
#rm -rf *transdecoder_dir*

# send stats to log file
sstat -j $SLURM_JOB_ID.batch --format=JobID,MaxVMSize

perl /n/sci/SCI-004340-VU/staging/scripts/select_longest_orf_geneset.pl \
    --dir . --t2g "$T2G_ABS" --outdir "$LONGEST_ORF_OUTDIR"



