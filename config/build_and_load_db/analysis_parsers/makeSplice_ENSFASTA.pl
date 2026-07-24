#!/usr/bin/perl
use strict;
use warnings;

my $fasta = shift;
open FASTA, $fasta or die "cant open FASTA: $fasta $!\n";
#>ENSP00000452345.1 pep chromosome:GRCh38:14:22450089:22450139:1 gene:ENSG00000211825.1 transcript:ENST00000390473.1 gene_biotype:TR_J_gene transcript_biotype:TR_J_gene gene_symbol:TRDJ1 description:T cell receptor delta joining 1 [Source:HGNC Symbol;Acc:HGNC:12257]
my %groups;
while(my $line = <FASTA>){
  chomp $line;
  if ($line =~ /^>(\S+).+gene:(\S+)/){
    $groups{$2}{$1}++;
  }
}
foreach my $gene (sort keys %groups){
  foreach my $transcript (sort keys  %{$groups{$gene}}){
    print join("\t",$transcript,$gene),"\n";
  }
}

