#!/usr/bin/perl
use strict;
use warnings;

# Swiss-Prot flat file (uniprot_sprot.dat) on STDIN -> one TSV row per entry on STDOUT:
#   accession  taxid  gene_name  hgnc_ids  ensembl_genes  ensembl_proteins  panther_ids  secondary_accessions
# List columns are ";"-separated; Ensembl ids have their version removed (Compara uses none).
# Secondary accessions are the older accessions merged into this entry, so a DIAMOND hit made
# against an older Swiss-Prot release still finds its entry.
# Used by update_reference_data.sh to link Swiss-Prot hits to a human gene: HGNC directly for
# human entries, Ensembl gene/protein -> Ensembl Compara for other species, PANTHER subfamily.

print join("\t", qw(accession taxid gene_name hgnc_ids ensembl_genes ensembl_proteins panther_ids secondary_accessions)), "\n";

my %entry = new_entry();
my $entries = 0;
while (my $line = <STDIN>) {
  if ($line =~ m{^//}) {
    print_entry(\%entry) if @{$entry{accessions}};
    $entries++;
    %entry = new_entry();
  } elsif ($line =~ /^AC   (.+)$/) {
    # primary accession first, then secondary ones; AC may continue on several lines
    foreach my $accession (split /;\s*/, $1) {
      push @{$entry{accessions}}, $accession if $accession =~ /\S/;
    }
  } elsif ($line =~ /^OX   NCBI_TaxID=(\d+)/) {
    $entry{taxid} = $1;
  } elsif ($line =~ /^GN   Name=([^;{]+)/ and $entry{gene_name} eq '') {
    ($entry{gene_name} = $1) =~ s/\s+$//;
  } elsif ($line =~ /^DR   HGNC; (HGNC:\d+);/) {
    $entry{hgnc}{$1} = 1;
  } elsif ($line =~ /^DR   Ensembl; [^;]+; ([^;]+); ([^;.\s]+)/) {
    my ($protein, $gene) = ($1, $2);
    $protein =~ s/\.\d+$//;
    $entry{proteins}{$protein} = 1;
    $entry{genes}{$gene} = 1;
  } elsif ($line =~ /^DR   PANTHER; ([^;]+);/) {
    $entry{panther}{$1} = 1;
  }
}
die "no entries read\n" unless $entries;
warn "parsed $entries Swiss-Prot entries\n";

sub new_entry {
  return (accessions => [], taxid => '', gene_name => '', hgnc => {}, genes => {}, proteins => {}, panther => {});
}

sub print_entry {
  my ($current) = @_;
  my ($primary, @secondary) = @{$current->{accessions}};
  print join("\t", $primary, $current->{taxid}, $current->{gene_name},
             join(';', sort keys %{$current->{hgnc}}), join(';', sort keys %{$current->{genes}}),
             join(';', sort keys %{$current->{proteins}}), join(';', sort keys %{$current->{panther}}),
             join(';', @secondary)), "\n";
}
