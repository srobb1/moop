## find longest cds and make that transcipt the referece sequence. 
## makes genome.fa, transcript.nt.fa, protein.aa.fa, cds.nt.fa, protein2gene, transcript2gene, cds2gene, genes.gff
## cp orginal fasta files and transdecoder files into src_dat
## for the planarians with no genemes that Eric build transcriptomes. 
## they all have this pattern of naming and file types

ORG=Camerata_robusta
PREFIX=crob.kc3

GS=${ORG}/${PREFIX}.ref/${PREFIX}
GS_PATH=~/sciproj/SBGENOMES/genomes/v2/${GS}
ANNOT=~/sciproj/SBGENOMES/dev/smr_dev/moop/annotations/SBGENOMES_2026-05-21/${GS}
cd "$GS_PATH"

perl ~/sciproj/SBGENOMES/dev/smr_dev/moop/moop-pipeline/config/build_and_load_db/scripts/select_longest_orf_geneset.pl --transcripts src_data/${PREFIX}.nt --pep src_data/${PREFIX}.nt.transdecoder.pep --cds src_data/${PREFIX}.nt.transdecoder.cds --annotations "$ANNOT/interproscan/iprscan_results.tsv" --outdir .
