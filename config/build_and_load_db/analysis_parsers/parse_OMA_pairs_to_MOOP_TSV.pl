#!/usr/bin/perl
use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin";
use OmaHogOrthologs qw(parse_oma_header best_accession);

# OMA pairwise orthologs between the target and one partner species -> moop TSV (Orthologs).
#
#   parse_OMA_pairs_to_MOOP_TSV.pl Output/PairwiseOrthologs/<A>-<B>.txt THISORG OTHERORG THISORG_FIRST OMA_VERSION [hgnc_complete_set.txt]
#
# THISORG_FIRST is 1 when the target is species A of the file name, else 0.
# Writes <OTHERORG>.OMA.oma_pairs.moop.tsv: one row per ortholog pair, for every partner
# species (earlier versions kept only Ensembl/RefSeq-style ids, so LOTGI, MONBE and CAPTE got
# no table). Accession is the partner's UniProt accession when OMA lists one, else its first
# protein id, linked through EBI search. The relationship (1:1, 1:many, many:1, many:many;
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

my %hgnc_symbol = defined $hgnc_file ? read_hgnc($hgnc_file) : ();

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
  my $other = parse_oma_header($other_header);
  my $accession = best_accession($other);

  my $label = $other->{description};
  if ($other->{hgnc_id} ne '' and exists $hgnc_symbol{$other->{hgnc_id}}) {
    my $current = $hgnc_symbol{$other->{hgnc_id}};
    $label = "$current->{symbol}: $current->{name}";
  }
  $label = $accession if $label eq '';
  push @rows, join("\t", $this_id, $accession, "$label ($type)", '-');
}
close $pairs_fh;

my $date = `date '+%Y-%m-%d' -r '$pairs_file'`;
$date =~ s/\s+//g;
my $out_file = "$other_org.OMA.oma_pairs.moop.tsv";
open my $out_fh, '>', $out_file or die "cant write $out_file $!\n";
print $out_fh "## Annotation Source: OMA pairwise orthologs ($other_org)
## Annotation Source Version: $oma_version
## Annotation Source URL: https://omabrowser.org/oma/home/
## Annotation Accession URL: https://www.ebi.ac.uk/ebisearch/search?query=
## Annotation Type: Orthologs
## Annotation Creation Date: $date
";
print $out_fh join("\t", "## Gene", "${other_org}_ORTHOLOG", "Description", "Score"), "\n";
foreach my $row (sort @rows) {
  print $out_fh "$row\n";
}
close $out_fh;
warn "wrote $out_file: " . scalar(@rows) . " pairs\n";

sub read_hgnc {
  my ($file) = @_;
  my %symbol_of;
  open my $fh, '<', $file or die "cant open $file $!\n";
  my $header = <$fh>;
  chomp $header;
  my @columns = split /\t/, $header;
  my %index;
  foreach my $column_number (0 .. $#columns) {
    $index{$columns[$column_number]} = $column_number;
  }
  while (my $line = <$fh>) {
    chomp $line;
    my @fields = split /\t/, $line;
    $symbol_of{$fields[$index{hgnc_id}]} = { symbol => $fields[$index{symbol}], name => $fields[$index{name}] };
  }
  close $fh;
  return %symbol_of;
}
