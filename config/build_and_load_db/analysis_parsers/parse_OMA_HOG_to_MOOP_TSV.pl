#!/usr/bin/perl
use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin";
use OmaHogOrthologs qw(read_hog_orthologs parse_oma_header read_export_sources find_export_readme
                       write_ortholog_tables read_hgnc_symbols);

# OMA HOG orthologs of the target species -> moop TSVs (Orthologs), one per partner species and
# id database.
#
#   parse_OMA_HOG_to_MOOP_TSV.pl Output/HierarchicalGroups.orthoxml TARGET_CODE OMA_VERSION [hgnc_complete_set.txt]
#
# Writes <PARTNER>.<Namespace>.oma_hog.moop.tsv for every other species in the run. Orthology
# comes from the HOG tree (OmaHogOrthologs.pm): the target gene and the partner gene meet at a
# speciation node. Each row carries the relationship (1:1, 1:many, many:1, many:many;
# target:partner) and the HOG id in the description, since the Score column only holds numbers.
# Partner ids, links and source releases as in parse_OMA_pairs_to_MOOP_TSV.pl.

my $usage = "usage: $0 HierarchicalGroups.orthoxml TARGET_CODE OMA_VERSION [hgnc_complete_set.txt]\n";
my $orthoxml    = shift or die $usage;
my $target      = shift or die $usage;
my $oma_version = shift or die $usage;
my $hgnc_file   = shift;

my $result = read_hog_orthologs($orthoxml, $target);
my $genes  = $result->{genes};

my @rows;
foreach my $partner (sort keys %{$result->{pairs}}) {
  foreach my $target_gene (keys %{$result->{pairs}{$partner}}) {
    foreach my $partner_gene (keys %{$result->{pairs}{$partner}{$target_gene}}) {
      my $pair = $result->{pairs}{$partner}{$target_gene}{$partner_gene};
      my $type = $result->{type}{$partner}{$target_gene}{$partner_gene};
      push @rows, [ $partner, $genes->{$target_gene}{prot_id}, parse_oma_header($genes->{$partner_gene}{header}),
                    " ($type, $pair->{hog})" ];
    }
  }
}

my $date = `date '+%Y-%m-%d' -r '$orthoxml'`;
$date =~ s/\s+//g;
my @written = write_ortholog_tables(
  kind => 'oma_hog', label => 'OMA HOG orthologs', version => $oma_version, date => $date,
  sources => read_export_sources(find_export_readme($orthoxml)),
  hgnc => read_hgnc_symbols($hgnc_file), rows => \@rows);
warn "wrote @written\n";
