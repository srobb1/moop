## get go obo

REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers

curl -OL http://purl.obolibrary.org/obo/go.obo
perl  /home/smr/sciproj/SBOMA/mapGO/get_id_and_names.pl go.obo > go.tsv

OMAX=/n/sci/SCI-004219-SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/combinedModels/orthologs/oma_x
OMAY=/n/sci/SCI-004219-SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/combinedModels/orthologs/oma_y

cat $OMAY/HUMAN_MOUSE_CHICK_ANOCA/OMA.2.6.0/Output/Map-SeqNum-ID.txt | grep CCA3 > CCA3Y.Map-SeqNum-ID.txt
cat $OMAX/HUMAN_MOUSE_CHICK_ANOCA/OMA.2.6.0/Output/Map-SeqNum-ID.txt | grep CCA3 > CCA3X.Map-SeqNum-ID.txt
cat $OMAY/HUMAN_MOUSE_CHICK_ANOCA/OMA.2.6.0/Output/gene_function.gaf  | grep CCA3 > CCA3Y.gene_function.gaf
cat $OMAX/HUMAN_MOUSE_CHICK_ANOCA/OMA.2.6.0/Output/gene_function.gaf  | grep CCA3 > CCA3X.gene_function.gaf
cat CCA3X.Map-SeqNum-ID.txt CCA3Y.Map-SeqNum-ID.txt > Map-SeqNum-ID.txt
cat CCA3X.gene_function.gaf CCA3Y.gene_function.gaf > gene_function.gaf

# parse_OMA2GO_to_MOOP_TSV.pl: this script will try to open Map-SeqNum-ID.txt and gene_function.gaf
MYORG=CCA3X
perl $SCRIPTS/parse_OMA2GO_to_MOOP_TSV.pl $MYORG Jul_2024 > $MYORG.OMA2GO.moop.tsv
MYORG=CCA3Y
perl $SCRIPTS/parse_OMA2GO_to_MOOP_TSV.pl $MYORG Jul_2024 > $MYORG.OMA2GO.moop.tsv
