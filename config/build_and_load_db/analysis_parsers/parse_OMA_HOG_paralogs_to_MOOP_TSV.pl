#!/usr/bin/perl
use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin";
use OmaHogOrthologs qw(read_hog_orthologs read_id_map write_paralog_table read_gene_names);

# OMA HOG paralogs of the target species -> moop TSV (Paralogs): the species' own genes in the
# same HOG, which meet at a duplication node (paralogGroup) of the HOG tree.
#
#   parse_OMA_HOG_paralogs_to_MOOP_TSV.pl Output/HierarchicalGroups.orthoxml TARGET_CODE OMA_VERSION [geneNames.tsv|-] [id_map.tsv]
#
# Writes <TARGET>.oma_hog_paralogs.moop.tsv, one row per gene and paralog. Each row shows the
# paralog's name from geneNames.tsv (so this runs after naming), the species of the run that
# share the duplication and the HOG id; the Score is the number of species sharing it.

my $usage = "usage: $0 HierarchicalGroups.orthoxml TARGET_CODE OMA_VERSION [geneNames.tsv|-] [id_map.tsv]\n";
my $orthoxml    = shift or die $usage;
my $target      = shift or die $usage;
my $oma_version = shift or die $usage;
my $names       = read_gene_names(shift);
my $id_map      = read_id_map(shift);

my $result = read_hog_orthologs($orthoxml, $target);
warn "$result->{same_species_at_speciation} orthologGroups hold $target genes in two children (not written as paralogs)\n"
  if $result->{same_species_at_speciation};

my $date = `date '+%Y-%m-%d' -r '$orthoxml'`;
$date =~ s/\s+//g;
my @written = write_paralog_table(target => $target, version => $oma_version, date => $date,
                                  id_map => $id_map, names => $names, result => $result);
warn "wrote @written\n";
