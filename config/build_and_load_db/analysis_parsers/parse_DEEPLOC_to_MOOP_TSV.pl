#!/usr/bin/perl
# DeepLoc 2 subcellular location -> moop TSV (annotation type "Protein Features", with SignalP and DeepTMHMM).
#
#   parse_DEEPLOC_to_MOOP_TSV.pl deeploc2_results.tsv [version]
#
# Writes DeepLoc.domains.moop.tsv (the loader's *.domains.moop.tsv pattern, as SignalP and DeepTMHMM).
# One row per protein, every protein (user, 2026-10-06):
#   Accession              the predicted location(s), as DeepLoc gives them: "Cytoplasm|Nucleus"
#   Accession_Description  each predicted location's probability, the sorting signal and the membrane type;
#                          without a sorting signal it says so -- DeepLoc then mostly predicts a default
#                          location (Cytoplasm / Nucleus), which should not read like a finding
#   Score                  the highest probability among the predicted locations
#
# Input: deeploc2_results.tsv, columns by name: Protein_ID, Localizations, Signals, Membrane types, then one
# probability column per location (Cytoplasm, Nucleus, Extracellular, ...) and per membrane type.
use strict;
use warnings;

my ($results, $version) = @ARGV;
die "Usage: $0 deeploc2_results.tsv [version]\n" unless defined $results;
$version = '2.0' unless defined $version and length $version;
my $source        = 'DeepLoc';
my $source_url    = 'https://services.healthtech.dtu.dk/services/DeepLoc-2.0/';
my $type          = 'Protein Features';
my $accession_url = '';

my $date = `date '+%Y-%m-%d' -r '$results'`;
$date =~ s/\s+//g;

open my $in, '<', $results or die "cant open DeepLoc results $results $!\n";
my $header = <$in> // die "$results is empty\n";
chomp $header;
my @columns = split /\t/, $header;
my %column = map { my $i = $_; ($columns[$i] => $i) } 0 .. $#columns;
foreach my $required ('Protein_ID', 'Localizations', 'Signals') {
  die "$results: no '$required' column\n" unless exists $column{$required};
}

open my $out, '>', 'DeepLoc.domains.moop.tsv' or die "cant write DeepLoc.domains.moop.tsv $!\n";
print $out "## Annotation Source: $source\n";
print $out "## Annotation Source Version: $version\n";
print $out "## Annotation Source URL: $source_url\n";
print $out "## Annotation Accession URL: $accession_url\n";
print $out "## Annotation Type: $type\n";
print $out "## Annotation Creation Date: $date\n";
print $out join("\t", '## Gene', 'Accession', 'Accession_Description', 'Score'), "\n";

my ($rows, $with_signal) = (0, 0);
while (my $line = <$in>) {
  chomp $line;
  next if $line =~ /^\s*$/;
  my @fields = split /\t/, $line, -1;
  my ($protein, $places, $signals) = @fields[@column{'Protein_ID', 'Localizations', 'Signals'}];
  next unless defined $protein and $protein ne '' and defined $places and $places ne '';
  my $membrane = exists $column{'Membrane types'} ? $fields[$column{'Membrane types'}] // '' : '';
  my @places = split /\|/, $places;
  my ($best, @described) = (0);
  foreach my $place (@places) {
    my $probability = exists $column{$place} ? $fields[$column{$place}] : undef;
    if (defined $probability and $probability =~ /^[0-9.eE+-]+$/) {
      push @described, sprintf('%s %.2f', $place, $probability);
      $best = $probability if $probability > $best;
    } else {
      push @described, $place;
    }
  }
  my $has_signal = defined $signals && $signals ne '' && $signals ne '-';
  $with_signal++ if $has_signal;
  my $description = join(', ', @described)
                  . ($has_signal ? "; sorting signal: $signals" : '; no sorting signal (the location is DeepLoc\'s default)')
                  . ($membrane ne '' && $membrane ne '-' ? "; membrane type: " . join(', ', split /\|/, $membrane) : '');
  print $out join("\t", $protein, $places, $description, ($best ? sprintf('%.4f', $best) : '-')), "\n";
  $rows++;
}
close $in;
close $out;
print "DeepLoc: $rows proteins ($with_signal with a sorting signal) -> DeepLoc.domains.moop.tsv\n";
