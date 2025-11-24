SCRIPT_DIR=/var/www/html/moop/config/build_and_load_db
FILES_DIR_BASE=/home/smr/sciproj/SBGENOMES/genomes


#for ORG in Anoura_caudifer Lasiurus_cinereus Montipora_capitata; do
for ORG in  Montipora_capitata; do
  
  DB=${ORG}.genes.sqlite

  if [ -e $DB ]; then
    rm $DB
  fi
 
  sqlite3 $DB < create_schema_sqlite.sql



  perl $SCRIPT_DIR/import_genes_sqlite.pl $DB $FILES_DIR_BASE/${ORG}/current/easygdb/features.tsv

  echo "Loading Annotations for: $ORG"

  ## load annotations

  ## ORTHOLOGS
  echo "Loading OMA Orthologs"
  FILES="$FILES_DIR_BASE/${ORG}/current/easygdb/*.oma_orthologs.tsv"
  for i in `ls ${FILES}` ;do echo $i ; perl $SCRIPT_DIR/load_annotations_fast.pl $DB $i ;done
  
  echo "Loading EGGNOG Orthologs"
  FILES=($FILES_DIR_BASE/${ORG}/current/easygdb/eggnog_orthologs.tsv) 
  for i in  ${FILES[@]} ;do echo $i ; perl $SCRIPT_DIR/load_annotations_fast.pl $DB $i ;done
 
  echo "Loading Blast homologs"
  FILES="$FILES_DIR_BASE/${ORG}/current/easygdb/*.blast_homologs.tsv"
  for i in `ls ${FILES}` ;do echo $i ; perl $SCRIPT_DIR/load_annotations_fast.pl $DB $i ;done

  ## domains and intpro2go and panther2go
  echo "Loading Domains and IPRSCAN2GO and PANTHER2GO"
  FILES="$FILES_DIR_BASE/${ORG}/current/easygdb/*.iprscan.tsv"
  for i in `ls ${FILES}` ;do echo $i ; perl $SCRIPT_DIR/load_annotations_fast.pl $DB $i ;done

  ## AI NAMES
  echo "loading Protnlm"
  FILE="$FILES_DIR_BASE/${ORG}/current/easygdb/protnlm.tsv"
  perl $SCRIPT_DIR/load_annotations_fast.pl $DB $FILE

  ## OTHER GO
  echo "loading Eggnog2GO"
  FILES=($FILES_DIR_BASE/${ORG}/current/easygdb/EggNOG2GO.eggnog.reduced $FILES_DIR_BASE/${ORG}/current/easygdb/oma.go.tsv)
  for i in  ${FILES[@]} ;do echo $i ; perl $SCRIPT_DIR/load_annotations_fast.pl $DB $i ;done

  ## REBUILD FTS5 INDEXES FOR FAST SEARCH
  echo "Rebuilding FTS5 search indexes..."
  sqlite3 $DB << EOF
INSERT INTO feature_fts(feature_fts, rank) VALUES('rebuild', -1);
INSERT INTO annotation_fts(annotation_fts, rank) VALUES('rebuild', -1);
EOF
  echo "✓ FTS5 indexes rebuilt successfully"

done
