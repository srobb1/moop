#!/usr/bin/perl
use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin";
use OmaHogOrthologs qw(parse_oma_header read_export_sources find_export_readme write_ortholog_tables read_hgnc_symbols);

# OMA pairwise orthologs between the target and one partner species -> moop TSV (Orthologs).
#
#   parse_OMA_pairs_to_MOOP_TSV.pl Output/PairwiseOrthologs/<A>-<B>.txt THISORG OTHERORG THISORG_FIRST OMA_VERSION [hgnc_complete_set.txt]
#
# THISORG_FIRST is 1 when the target is species A of the file name, else 0.
# Writes <OTHERORG>.<Namespace>.oma_pairs.moop.tsv: each partner gene is shown with the id of
# the database its annotation came from (Ensembl, FlyBase, RefSeq, else UniProt), one file per
# database so the accession links resolve; the partner's source release (README.exportedAllAll
# of the run) is in the version line. The relationship (1:1, 1:many, many:1, many:many;
# target:partner) is in the description, since the Score column only holds numbers. With the
# optional HGNC table, HUMAN rows get the current HGNC symbol and name.

my $usage = "usage: $0 pairs.txt THISORG OTHERORG THISORG_FIRST OMA_VERSION [hgnc_complete_set.txt]\n";
my $pairs_file    = shift or die $usage;
my $this_org      = shift or die $usage;
my $other_org     = shift or die $usage;
my $this_first    = shift;
my $oma_version   = shift or die $usage;
my $hgnc_file     = shift;
die $usage unless defined $this_first and $this_first =~ /^[01]$/;

my @rows;
open my $pairs_fh, '<', $pairs_file or die "cant open pairs file $pairs_file $!\n";
while (my $line = <$pairs_fh>) {
  chomp $line;
  next if $line =~ /^#/ or $line !~ /\S/;
  my ($number_1, $number_2, $header_1, $header_2, $type) = split /\t/, $line;
  my ($this_header, $other_header) = $this_first ? ($header_1, $header_2) : ($header_2, $header_1);
  unless ($this_first) {
    my ($left, $right) = split /:/, $type;
    $type = "$right:$left";
  }
  my ($this_id) = $this_header =~ /^(\S+)/;
  push @rows, [ $other_org, $this_id, parse_oma_header($other_header), " ($type)" ];
}
close $pairs_fh;

my $date = `date '+%Y-%m-%d' -r '$pairs_file'`;
$date =~ s/\s+//g;
my @written = write_ortholog_tables(
  kind => 'oma_pairs', label => 'OMA pairwise orthologs', version => $oma_version, date => $date,
  sources => read_export_sources(find_export_readme($pairs_file)),
  hgnc => read_hgnc_symbols($hgnc_file), rows => \@rows);
warn "wrote @written\n";
