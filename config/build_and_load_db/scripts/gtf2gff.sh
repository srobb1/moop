GTF=$1
BASE=`basename $1 .gtf`
module load gffread
gffread $GTF -o ${BASE}.gff --keep-genes
