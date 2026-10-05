#!/usr/bin/perl
use strict;
use warnings;

# Give an Ensembl GFF's feature IDs the version its FASTAs already carry.
#
# Usage: add_id_versions.pl <genes.gff> <protein.aa.fa> <out.gff>
#
# Exit 0: <out.gff> written with versioned IDs.
# Exit 3: not needed -- the FASTA ids already match the GFF as it is; nothing written.
# Exit 1: the FASTA ids match neither way; nothing written.
#
# ---------------------------------------------------------------------------
# WHY
#
# Ensembl's GFF3 keeps the version in a tag of its own:
#
#     ID=gene:ENSDARG00000009657;...;version=8
#     ID=CDS:ENSDARP00000005747;Parent=transcript:ENSDART00000021139;protein_id=ENSDARP00000005747;version=6
#
# while its FASTAs, and so every analysis run on them (DIAMOND, MMseqs2, InterProScan,
# OMA), use id.version:
#
#     >ENSDARP00000005747.6 pep ... gene:ENSDARG00000009657.8 transcript:ENSDART00000021139.8
#
# Nothing joins: naming finds no hits for any gene, and no annotation table attaches to a
# feature. Danio rerio GRCz11/20260404 was the first such gene set (2026-10-05). Stripping
# the version from everything else instead would lose it, and the version is what tells
# a user that a model changed between releases.
#
# ---------------------------------------------------------------------------
# WHAT IT DOES
#
# Each ID= gets its own feature's version= tag; each Parent= gets its parent's version;
# protein_id=, gene_id= and transcript_id= get the version of the feature they name (a
# protein's version is its CDS line's). Features with no version= tag are left as they
# are. The version= tag itself is kept.
#
# It runs only when the evidence is exact: every protein id in the FASTA must be some
# CDS line's protein_id.version, and none may be a bare protein_id. Anything in between
# is refused (exit 1) rather than guessed at.
#
# Like strip_id_prefix.pl, it writes only MOOP's own copy; the $GENOMES tree is never
# opened for writing.
# ---------------------------------------------------------------------------

my ($gff_file, $fasta_file, $out_file) = @ARGV;
die "Usage: $0 <genes.gff> <protein.aa.fa> <out.gff>\n" unless defined $out_file;

## the protein FASTA's ids
my @fasta_ids;
open my $fa, '<', $fasta_file or die "Cannot open $fasta_file: $!\n";
while (my $line = <$fa>) {
  push @fasta_ids, $1 if $line =~ /^>(\S+)/;
}
close $fa;
die "No sequences in $fasta_file\n" unless @fasta_ids;

## pass 1: each feature's version, by its full ID (gene:X) and its bare id (X)
my (%version_of_id, %version_of_bare, %protein_version);
open my $in, '<', $gff_file or die "Cannot open $gff_file: $!\n";
while (my $line = <$in>) {
  next if $line =~ /^#/;
  my @f = split /\t/, $line;
  next unless @f >= 9;
  my ($version) = $f[8] =~ /(?:^|;)version=([^;\s]+)/;
  next unless defined $version;
  if (my ($id) = $f[8] =~ /(?:^|;)ID=([^;\s]+)/) {
    $version_of_id{$id} = $version;
    (my $bare = $id) =~ s/^[A-Za-z_]+://;
    $version_of_bare{$bare} = $version;
  }
  if ($f[2] eq 'CDS' and my ($protein) = $f[8] =~ /(?:^|;)protein_id=([^;\s]+)/) {
    $protein_version{$protein} = $version;
  }
}
close $in;

## decide from the FASTA: bare ids already match, versioned ids match, or neither
my %versioned_protein = map { ("$_.$protein_version{$_}" => 1) } keys %protein_version;
my ($n_bare, $n_versioned) = (0, 0);
my @unmatched;
foreach my $fasta_id (@fasta_ids) {
  if    (exists $protein_version{$fasta_id})   { $n_bare++ }
  elsif (exists $versioned_protein{$fasta_id}) { $n_versioned++ }
  else  { push @unmatched, $fasta_id }
}
my $n_fasta = scalar @fasta_ids;
if ($n_versioned == 0) {
  print STDERR "add_id_versions.pl: not needed (no FASTA id is a GFF protein_id plus its version tag)\n";
  exit 3;
}
if ($n_versioned != $n_fasta) {
  print STDERR "add_id_versions.pl: refused -- of $n_fasta FASTA ids, $n_versioned are GFF protein_id.version, "
             . "$n_bare are bare protein_ids and " . scalar(@unmatched) . " match neither"
             . (@unmatched ? " (e.g. " . join(', ', @unmatched[0 .. ($#unmatched < 4 ? $#unmatched : 4)]) . ")" : '') . "\n";
  exit 1;
}

## pass 2: rewrite
sub with_version {
  my ($id, $map) = @_;
  return exists $map->{$id} ? "$id.$map->{$id}" : $id;
}

my (%ids_before, %ids_after);
open $in, '<', $gff_file or die "Cannot open $gff_file: $!\n";
open my $out, '>', $out_file or die "Cannot write $out_file: $!\n";
while (my $line = <$in>) {
  if ($line =~ /^#/) { print $out $line; next; }
  chomp $line;
  my @f = split /\t/, $line, -1;
  if (@f < 9) { print $out "$line\n"; next; }
  my @attributes;
  foreach my $pair (split /;/, $f[8]) {
    my ($key, $value) = split /=/, $pair, 2;
    if (defined $value) {
      if ($key eq 'ID') {
        $ids_before{$value} = 1;
        $value = with_version($value, \%version_of_id);
        $ids_after{$value} = 1;
      } elsif ($key eq 'Parent') {
        $value = join ',', map { with_version($_, \%version_of_id) } split /,/, $value;
      } elsif ($key eq 'protein_id') {
        $value = with_version($value, \%protein_version);
      } elsif ($key eq 'gene_id' or $key eq 'transcript_id') {
        $value = with_version($value, \%version_of_bare);
      }
      push @attributes, "$key=$value";
    } else {
      push @attributes, $pair;
    }
  }
  $f[8] = join ';', @attributes;
  print $out join("\t", @f), "\n";
}
close $in;
close $out or die "Cannot write $out_file: $!\n";

if (keys %ids_before != keys %ids_after) {
  unlink $out_file;
  die "add_id_versions.pl: " . scalar(keys %ids_before) . " distinct IDs became " . scalar(keys %ids_after) . "; nothing written\n";
}
print STDERR "add_id_versions.pl: versioned IDs written ($n_fasta of $n_fasta FASTA ids are protein_id.version; "
           . scalar(keys %ids_after) . " distinct IDs)\n";
exit 0;
