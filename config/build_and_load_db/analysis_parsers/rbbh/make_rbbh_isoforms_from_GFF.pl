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

  if ($line =~ /\tmRNA\t/){
    if (defined $proteinCoding){
      next unless $line =~ /$proteinCoding/
    }
    my ($transcript,$gene) = $line =~ /ID=([^;]+);.*Parent=([^;]+)/;
    $groups{$gene}{$transcript}++;
  }
}

foreach my $gene (sort keys %groups){
  foreach my $child (sort keys %{$groups{$gene}}){
    print join("\t",$child,$gene),"\n";
  }
}
