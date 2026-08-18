#!/usr/bin/bash
# Run cd-hit-est on a transcript FASTA to see how it clusters isoforms,
# for comparison against select_longest_orf_geneset.pl's per-gene pick.
#SBATCH --job-name=cdhit             # Job name
#SBATCH --nodes=1                    # Run all processes on a single node
#SBATCH --ntasks=1                   # Run a single task
#SBATCH --partition=compute          # use computer partition
#SBATCH --cpus-per-task=32           # Number of CPU cores per task
#SBATCH --mem=200gb                  # Job memory request
#SBATCH --time=02:00:00              # Time limit hrs:min:sec
#SBATCH --output=logs/cdhit_%j.out
#SBATCH --error=logs/cdhit_%j.err
#SBATCH --mail-type=ALL
#SBATCH --mail-user=ejr@stowers.org
#
# Before running you need to make a logs directory. The script can't make it

# Check if the user forgot to provide an argument
if [ -z "${1}" ]; then
    echo "Error: No file provided."
    echo "Usage: $0 <transcript FASTA>"
    exit 1
fi

FASTA=$1

# Check if the file actually exists
if [ ! -f "$FASTA" ]; then
    echo "Error: File '$FASTA' does not exist."
    exit 1
fi

# Check if the file is empty
if [ ! -s "$FASTA" ]; then
    echo "Error: File '$FASTA' is empty."
    exit 1
fi

# Absolute path, captured before any cd
FASTA_ABS=$(readlink -f "$FASTA")

# Get the directory name from the file path
FASTA_DIR=$(dirname "$FASTA")

# Get just the file name without the folder path
FASTA_FILE=$(basename "$FASTA")

# Change directory into the folder where the FASTA file lives, so
# cdhit_output/ ends up alongside transdecoder_output/, mirroring it
cd "$FASTA_DIR"

OUTDIR=cdhit_output/$FASTA_FILE
mkdir -p $OUTDIR
cd $OUTDIR

module purge
module load cdhit/4.8.1

cd-hit-est -c .95 -M 0 -T 0 -aS .3 -d 0 -i "$FASTA_ABS" -o ${FASTA_FILE}.cdhit

# send stats to log file
sstat -j $SLURM_JOB_ID.batch --format=JobID,MaxVMSize
