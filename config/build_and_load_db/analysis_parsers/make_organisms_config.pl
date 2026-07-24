#!/usr/bin/perl
use strict;
use warnings;

# Usage: perl make_organisms_config.pl metadata.yaml parents children
#   parents  - comma-separated list, e.g. "gene"
#   children - comma-separated list, e.g. "mRNA,transcript,protein" or "protein"

my $metadata_path = shift or die "Usage: $0 metadata.yaml parents children\n";
my $parents_arg   = shift || 'gene';
my $children_arg  = shift || 'mRNA,transcript';

open META, $metadata_path or die "cant open metadata.yaml $! \n";
my ($genus, $species, $common_name, $taxon_id) = ('','','',''); 
while (my $line = <META>){
  chomp $line;
  if ($line =~ /genus:\s*(\S+)/){
    $genus = $1;
  }elsif($line =~ /species:\s*(\S+)/){
    $species = $1;
  }elsif($line =~ /common-name:\s*(.+)$/){
    $common_name = $1;
  }elsif($line =~ /ncbi-taxon-id:\s*(\S+)/){
    $taxon_id = $1;
  }
}
$common_name =~ s/\s+$//;

my $parents  = join(', ', map { "\"$_\"" } split /,/, $parents_arg);
my $children = join(', ', map { "\"$_\"" } split /,/, $children_arg);

open JSON, ">organism.json" or die "Can't open file for writting $! \n"; 
print JSON "{
    \"genus\": \"$genus\",
    \"species\": \"$species\",
    \"common_name\": \"$common_name\",
    \"taxon_id\": \"$taxon_id\",
    \"subclassification\": {
        \"type\": null,
        \"value\": null
    },
    \"feature_types\": {
        \"parents\": [$parents],
        \"children\": [$children]
    }
}";
