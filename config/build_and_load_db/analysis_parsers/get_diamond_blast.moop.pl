#!/usr/bin/perl
use strict;
use warnings;

my $top_hits = shift; #= '../analysis/BLASTP_UNIPROT_sprot/tophit.tsv';
my $source = shift; #'SwissProt';
my $source_version = shift; #'release-113' ;#'2024_06'; #`cat ../analysis/BLASTP_UNIPROT_sprot/db_version.txt`;
my $source_url = shift; #'https://www.ensembl.org/'; #'https://www.uniprot.org';
my $annotation_url = shift; #'https://www.ensembl.org/Multi/Search/Results?q=' ;#'https://www.uniprot.org/uniprotkb/';
my $annotation_type = 'Homologs'; 

my $date = `date '+%Y-%m-%d' -r $top_hits`; 
$date =~ s/\s+//g;
my $source_nospace = $source;
$source_nospace =~ s/\s+/_/g; 
$source_nospace =~ s/\//_/g; 
open OUT, ">$source_nospace.homologs.moop.tsv" or die "Can't open $source_nospace.homologs.moop.tsv for writing $! \n";

print OUT "## Annotation Source: $source\n";
print OUT "## Annotation Source Version: $source_version\n";
print OUT "## Annotation Source URL: $source_url\n";
print OUT "## Annotation Accession URL: $annotation_url\n";
print OUT "## Annotation Type: $annotation_type\n";
print OUT "## Annotation Creation Date: $date\n"; 
print OUT join("\t","## Gene", "Accession","Accession_Description","Score"),"\n";

open TH, $top_hits or die "Can't open top hits $source file:$top_hits $! \n";
my %annot;


while (my $line = <TH>){
  chomp $line;
  #ACI1_HiC_scaffold_1000_000001.1	Q9BWM5	Zinc finger protein 416 OS=Homo sapiens OX=9606 GN=ZNF416 PE=1 SV=1	3.94e-110
  #ACI1_HiC_scaffold_1_000001.1	sp|Q16342|PDCD2_HUMAN	sp|Q16342|PDCD2_HUMAN Programmed cell death protein 2 OS=Homo sapiens OX=9606 GN=PDCD2 PE=1 SV=2	2.04e-210
  # ENSAMXP00000035529.1  ENSAMXP00000035529.1 pep primary_assembly:Astyanax_mexicanus-2.0:20:11140888:11159592:1 gene:ENSAMXG00000032087.1 transcript:ENSAMXT00000039164.1 gene_biotype:protein_coding transcript_biotype:protein_coding gene_symbol:HRH2 description:histamine receptor H2 [Source:HGNC Symbol;Acc:HGNC:5183]
  my ($t_id, $hit_id, $hit_desc, $score) = split "\t" , $line;
  $hit_id =~ s/sp\|(\S+)\|\S+/$1/;
  if ($hit_desc =~ /gene_symbol:(\S+)\s*description/){
    $hit_desc =~ s/.+gene_symbol:(\S+)\s*description:\s*(.+)/$1: $2/;
  }elsif($hit_desc =~ /description/){
    $hit_desc =~ s/.+description:\s*(.+)/$1/;
  }elsif($hit_desc =~ /^sp\|.+GN/){
    $hit_desc =~ s/^sp\|\S+\|\S+ (.+GN=(\S+).*)/$2: $1/;
  }elsif($hit_desc =~ /^sp\|/){
    $hit_desc =~ s/^sp\|\S+\|\S+ (.+)/$1/;
  }
  print OUT join("\t",$t_id,$hit_id,$hit_desc,$score),"\n";
}


