#!/usr/bin/perl
use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin";
use OmaHogOrthologs qw(read_id_map target_ids);

# OMA GO terms (Output/gene_function.gaf) for one species -> moop TSV (Gene Ontology) on STDOUT.
#
#   parse_OMA2GO_to_MOOP_TSV.pl CODE OMA_VERSION go.tsv Map-SeqNum-ID.txt gene_function.gaf [id_map.tsv]
#
# gene_function.gaf names genes two ways:
#   CODE:00002        the run's target species: sequence number, looked up in Map-SeqNum-ID.txt
#   CODE000002        a reference genome (its exported annotations and OMA's predictions): the
#                     OMA entry id itself
# Only rows of exactly CODE are taken (NEMVE does not take NEMVEC rows). With the optional id map
# (a gene set that IS a reference genome, run through the template's reference run), entry ids are
# translated to the gene set's own protein ids (OmaHogOrthologs::read_id_map).
# go.tsv (from mapGO/get_OMA_GO_terms.sh): GO id, name, definition, namespace.

my $usage = "usage: $0 CODE OMA_VERSION go.tsv Map-SeqNum-ID.txt gene_function.gaf [id_map.tsv]\n";
my $code        = shift or die $usage;
my $oma_version = shift or die $usage;
my $go_file     = shift or die $usage;
my $map_file    = shift or die $usage;
my $gaf_file    = shift or die $usage;
my $id_map      = read_id_map(shift);

my %go;
open my $go_fh, '<', $go_file or die "cant open $go_file $!\n";
while (my $line = <$go_fh>) {
  chomp $line;
  my ($go_id, $name, $definition, $namespace) = split /\t/, $line;
  $go{$go_id} = { name => $name, definition => $definition // '', namespace => $namespace // '' };
}
close $go_fh;

# sequence number -> entry id (first word of the FASTA header)
my %entry_of_number;
open my $map_fh, '<', $map_file or die "cant open $map_file $!\n";
while (my $line = <$map_fh>) {
  next if $line =~ /^#/;
  chomp $line;
  my ($species, $number, $header) = split /\t/, $line;
  next unless defined $header and $species eq $code;
  my ($entry) = $header =~ /^(\S+)/;
  $entry_of_number{$number} = $entry;
}
close $map_fh;

my $date = `date '+%Y-%m-%d' -r '$gaf_file'`;
$date =~ s/\s+//g;
print "## Annotation Source: OMA2GO
## Annotation Source Version: $oma_version
## Annotation Source URL: https://omabrowser.org/oma/home/
## Annotation Accession URL: https://www.ebi.ac.uk/QuickGO/term/
## Annotation Type: Gene Ontology
## Annotation Creation Date: $date
## ID\tGO_ID\tGO_DESCRIPTION\tNAMESPACE
";

my (%reported, $rows, $unmapped);
open my $gaf_fh, '<', $gaf_file or die "cant open $gaf_file $!\n";
while (my $line = <$gaf_fh>) {
  next if $line =~ /^!/;
  chomp $line;
  my @fields = split /\t/, $line;
  my ($gene, $go_id) = @fields[1, 4];
  next unless defined $gene and defined $go_id;
  my $entry;
  if ($gene =~ /^\Q$code\E:0*(\d+)$/) {
    $entry = $entry_of_number{$1};
  } elsif ($gene =~ /^\Q$code\E\d+$/) {
    $entry = $gene;
  } else {
    next;
  }
  if (!defined $entry) {
    $unmapped++;
    next;
  }
  next unless exists $go{$go_id};
  foreach my $own_id (target_ids($id_map, $entry)) {
    next if $reported{$own_id}{$go_id}++;
    print join("\t", $own_id, $go_id, "$go{$go_id}{name}: $go{$go_id}{definition}", $go{$go_id}{namespace}), "\n";
    $rows++;
  }
}
close $gaf_fh;
warn sprintf("OMA2GO %s: %d rows%s\n", $code, $rows // 0, $unmapped ? ", $unmapped GAF rows with no Map-SeqNum-ID entry" : '');
