#!/usr/bin/perl
use strict;
use warnings;

## many external databases put information about the ID in the FASTA file, we can use this info

my $fasta = shift;
open FASTA, $fasta or die "Can't open FASTA file: $fasta $! \n";
my $type = 'ENS';

## ENS
# >ENSP00000456206.2 pep chromosome:GRCh38:10:46888:49296:-1 gene:ENSG00000261456.6 transcript:ENST00000568584.6 gene_biotype:protein_coding transcript_biotype:protein_coding gene_symbol:TUBB8 description:tubulin beta 8 class VIII [Source:HGNC Symbol;Acc:HGNC:20773]
print join("\t",qw(protein_id transcript_id gene_id gene_symbol description)),"\n";
while (my $line = <FASTA>){
  chomp $line;
  next if $line !~ /^>/;
  if ($line =~  />ENS\S+/){
     $type = 'ENS';
  } 
  my $protein_id = 'None';
  my $gene_id= 'None';
  my $transcript_id = 'None';
  my $gene_symbol = 'None';
  my $description = 'None';
  if ($type eq 'ENS'){
    if ($line =~ /\spep\s/){
      ($protein_id) = $line =~ />(\S+)\.?\d* pep/;  
    }
    if ($line =~ /gene:(\S+)\.?\d*/){
      $gene_id = $1  
    }
    if ($line =~ /transcript:(\S+)\.?\d*/){
      $transcript_id = $1;  
    }
    if ($line =~ /gene_symbol:(\S+)\.?\d*/){
      $gene_symbol = $1;  
    }
    if ($line =~ /description:([^\[]+)\.?\d*/){
      $description = $1;
    }
  }
  print join("\t",$protein_id,$transcript_id,$gene_id,$gene_symbol,$description),"\n";
}
