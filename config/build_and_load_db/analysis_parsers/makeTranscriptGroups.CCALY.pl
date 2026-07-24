#!/usr/bin/perl
use strict;
use warnings;

### I dont thnk we need this anymore, try to use the make transcript groups from gff scipt instead. 


my $fasta = shift;
open FASTA, $fasta or die "cant open FASTA: $fasta $!\n";
my %groups;
while(my $line = <FASTA>){
  chomp $line;
  #CCA1t003595001.1
  if ($line =~ /^>((CCA\d+)t((\d{6}))(\d{3}\.\d+))/){
    my $gene = "${2}g${4}000.1";
    $groups{$gene}{$1}++;
  }
}
#[semicolon sep list of isoforms][selected transcript.optional][gene id.optional]
foreach my $gene (sort keys %groups){
  my @transcripts = sort keys  %{$groups{$gene}};
  my $transcripts = join(";",@transcripts);
  print join("\t",$transcripts,'None',$gene),"\n";
}
