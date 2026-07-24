#!/usr/bin/perl
use strict;
use warnings;

my $gff = shift;
my $proteinCoding = shift; # if you have a special mRNA source or tag that you only want to include

if (!defined $proteinCoding){
  $proteinCoding = '';
}
open GFF, $gff or die "cant open GFF: $gff $!\n";
my %groups;
while(my $line = <GFF>){
  chomp $line;

  if ($line =~ /\tmRNA|transcript\t/){
    if (defined $proteinCoding){
      next unless $line =~ /$proteinCoding/
    }
    my ($transcript,$gene) = $line =~ /\tID=([^;]+);*.*;Parent=([^;]+)/;
    $groups{$gene}{$transcript}++;
  }
}

#[semicolon sep list of isoforms][selected transcript.optional][gene id.optional]
foreach my $gene (sort keys %groups){
  my @transcripts = sort keys  %{$groups{$gene}};
  my $transcripts = join(";",@transcripts);
  print join("\t",$transcripts,'None',$gene),"\n";
}
