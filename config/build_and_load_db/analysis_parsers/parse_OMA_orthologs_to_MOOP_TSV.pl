#!/usr/bin/perl
use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin";
use OmaHogOrthologs qw(parse_oma_header read_export_sources find_export_readme write_ortholog_tables read_hgnc_symbols);

# OMA groups (Output/OrthologousGroups.txt) -> moop TSVs (Orthologs), one per partner species
# and id database.
#
#   parse_OMA_orthologs_to_MOOP_TSV.pl Output/OrthologousGroups.txt THISORG OMA_VERSION [hgnc_complete_set.txt]
#
# An OMA group is a set of genes, at most one per species, that are all orthologous to each
# other; every other species' gene in a group with a target gene is written as its ortholog,
# with the group id in the description. Writes <PARTNER>.<Namespace>.oma_orthologs.moop.tsv for
# every partner species (earlier versions kept only Ensembl and RefSeq ids, so partners with
# other ids got no table). Partner ids, links and source releases as in
# parse_OMA_pairs_to_MOOP_TSV.pl.
#
# Group line: OMA00001 <TAB> CALMI:CALMI020393 | ids | ... <TAB> CHACAL:CCA3t011306001.1 <TAB> ...

my $usage = "usage: $0 OrthologousGroups.txt THISORG OMA_VERSION [hgnc_complete_set.txt]\n";
my $groups_file = shift or die $usage;
my $this_org    = shift or die $usage;
my $oma_version = shift or die $usage;
my $hgnc_file   = shift;

my @rows;
open my $groups_fh, '<', $groups_file or die "cant open $groups_file $!\n";
while (my $line = <$groups_fh>) {
  next if $line =~ /^#/;
  chomp $line;
  my ($group_id, @members) = split /\t/, $line;
  my (@target_ids, @partners);
  foreach my $member (@members) {
    my ($code, $rest) = $member =~ /^([A-Za-z0-9]+):(.*)$/ or next;
    if ($code eq $this_org) {
      my ($target_id) = $rest =~ /^(\S+)/;
      push @target_ids, $target_id;
    } else {
      push @partners, [ $code, parse_oma_header($rest) ];
    }
  }
  foreach my $target_id (@target_ids) {
    foreach my $partner (@partners) {
      push @rows, [ $partner->[0], $target_id, $partner->[1], " (OMA group $group_id)" ];
    }
  }
}
close $groups_fh;

my $date = `date '+%Y-%m-%d' -r '$groups_file'`;
$date =~ s/\s+//g;
my @written = write_ortholog_tables(
  kind => 'oma_orthologs', label => 'OMA group orthologs', version => $oma_version, date => $date,
  sources => read_export_sources(find_export_readme($groups_file)),
  hgnc => read_hgnc_symbols($hgnc_file), rows => \@rows);
warn "wrote @written\n";
