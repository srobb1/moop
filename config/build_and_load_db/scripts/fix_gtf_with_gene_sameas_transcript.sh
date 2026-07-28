## example, need to codify this

cp genes.gtf genes.gtfBAK
perl -p -e 's/(gene_\w+ .+?)\.\d+\"/$1"/g' genes.gtfBAK > genes.gtf
sh ~/sciproj/SBGENOMES/dev/smr_dev/moop/moop-pipeline/config/build_and_load_db/scripts/gtf2gff.sh genes.gtf 
