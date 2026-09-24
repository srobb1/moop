#!/usr/bin/perl
use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin";
use OmaHogOrthologs qw(read_hog_orthologs parse_oma_header best_accession);

# OMA HOG orthologs of the target species -> one moop TSV (Orthologs) per partner species.
#
#   parse_OMA_HOG_to_MOOP_TSV.pl Output/HierarchicalGroups.orthoxml TARGET_CODE OMA_VERSION [hgnc_complete_set.txt]
#
# Writes <PARTNER>.oma_hog.moop.tsv for every other species in the run (HUMAN, MOUSE, ...).
# Orthology comes from the HOG tree (OmaHogOrthologs.pm): the target gene and the partner gene
# meet at a speciation node. Each row carries the relationship (1:1, 1:many, many:1,
# many:many; target:partner) and the HOG id in the description, since the moop Score column
# only holds numbers.
#
# Accession is the partner's UniProt accession when OMA lists one, otherwise its first protein
# id; the accession URL is EBI search, which resolves UniProt, Ensembl and RefSeq ids alike.
# With the optional HGNC table, HUMAN rows get the current HGNC symbol ("TUBB8: tubulin beta 8
# class VIII") from the HGNC id in OMA's header.

my $usage = "usage: $0 HierarchicalGroups.orthoxml TARGET_CODE OMA_VERSION [hgnc_complete_set.txt]\n";
my $orthoxml    = shift or die $usage;
my $target      = shift or die $usage;
my $oma_version = shift or die $usage;
my $hgnc_file   = shift;

my %hgnc_symbol;
if (defined $hgnc_file) {
  open my $hgnc_fh, '<', $hgnc_file or die "cant open $hgnc_file $!\n";
  my $header = <$hgnc_fh>;
  chomp $header;
  my @columns = split /\t/, $header;
  my %column_index;
  foreach my $index (0 .. $#columns) {
    $column_index{$columns[$index]} = $index;
  }
  die "no hgnc_id/symbol/name columns in $hgnc_file\n"
    unless defined $column_index{hgnc_id} and defined $column_index{symbol} and defined $column_index{name};
  while (my $line = <$hgnc_fh>) {
    chomp $line;
    my @fields = split /\t/, $line;
    $hgnc_symbol{$fields[$column_index{hgnc_id}]} = { symbol => $fields[$column_index{symbol}], name => $fields[$column_index{name}] };
  }
  close $hgnc_fh;
}

my $result = read_hog_orthologs($orthoxml, $target);
my $genes  = $result->{genes};

my $date = `date '+%Y-%m-%d' -r '$orthoxml'`;
$date =~ s/\s+//g;

foreach my $partner (sort keys %{$result->{pairs}}) {
  my $out_file = "$partner.oma_hog.moop.tsv";
  open my $out_fh, '>', $out_file or die "cant write $out_file $!\n";
  print $out_fh "## Annotation Source: OMA HOG orthologs ($partner)
## Annotation Source Version: $oma_version
## Annotation Source URL: https://omabrowser.org/oma/home/
## Annotation Accession URL: https://www.ebi.ac.uk/ebisearch/search?query=
## Annotation Type: Orthologs
## Annotation Creation Date: $date
";
  print $out_fh join("\t", "## Gene", "${partner}_ORTHOLOG", "Description", "Score"), "\n";

  my $rows = 0;
  foreach my $target_gene (sort keys %{$result->{pairs}{$partner}}) {
    foreach my $partner_gene (sort keys %{$result->{pairs}{$partner}{$target_gene}}) {
      my $pair    = $result->{pairs}{$partner}{$target_gene}{$partner_gene};
      my $type    = $result->{type}{$partner}{$target_gene}{$partner_gene};
      my $header  = parse_oma_header($genes->{$partner_gene}{header});
      my $accession = best_accession($header);

      my $label = $header->{description};
      if ($header->{hgnc_id} ne '' and exists $hgnc_symbol{$header->{hgnc_id}}) {
        my $current = $hgnc_symbol{$header->{hgnc_id}};
        $label = "$current->{symbol}: $current->{name}";
      }
      $label = $accession if $label eq '';
      $label .= " ($type, $pair->{hog})";

      print $out_fh join("\t", $genes->{$target_gene}{prot_id}, $accession, $label, '-'), "\n";
      $rows++;
    }
  }
  close $out_fh;
  warn sprintf("wrote %s: %d rows for %d %s genes\n", $out_file, $rows, scalar(keys %{$result->{pairs}{$partner}}), $target);
}
