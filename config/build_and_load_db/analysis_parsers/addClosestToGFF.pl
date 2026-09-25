#!/usr/bin/perl
use strict;
use warnings;

# Add the closest gene in each species (closest_<tag>.tsv from assign_gene_names_v2.pl: human
# always, plus each closest_species in geneset_config.yaml) to every gene and mRNA of a GFF3,
# without touching names:
#
#   addClosestToGFF.pl genes.gff closest_human.tsv [closest_nvec.tsv ...] > genes.closest.gff
#
#   closestHGNC=HGNC:20773;closestHumanSym=TUBB8;closestHumanDesc=tubulin beta 8 class VIII;
#   closestHumanEvidence=OMA ortholog (1:1);closestNvecId=XP_...;closestNvecSym=...
#
# The attribute names are the header of columns 3-6 of each file, so this script knows no
# species. Several genes (a family) are comma-separated in matching order. Values are
# GFF3-escaped (; = & , inside a value). Runs after updateGFF.pl on renamed gene sets, and on
# native RefSeq/Ensembl gene sets whose names are kept.
#
# An mRNA is matched by its ID, with or without the "transcript:" / "rna-" prefix that Ensembl
# and RefSeq GFFs use. A gene gets the value of its mRNAs (through Parent=), since the gene ids
# (e.g. RefSeq GeneID numbers) need not be the GFF gene ids; a gene whose id is itself a GroupId
# is matched directly.

my $usage = "usage: $0 genes.gff closest_<tag>.tsv ... > out.gff\n";
my $gff   = shift or die $usage;
my @closest_files = @ARGV or die $usage;

# per file: attribute names, and the values by mRNA id / by GroupId
my @species;
foreach my $file (@closest_files) {
  open my $fh, '<', $file or die "cant open $file $!\n";
  my $header = <$fh> // '';
  chomp $header;
  my ($id_col, $group_col, @attributes) = split /\t/, $header;
  die "$file: header must be ID GroupId and four attribute names\n"
    unless ($id_col // '') eq 'ID' and ($group_col // '') eq 'GroupId' and @attributes == 4
       and !grep { !/^closest[A-Za-z0-9]+$/ } @attributes;
  my %species = (file => $file, attributes => \@attributes, by_id => {}, by_group => {}, for_gene => {},
                 genes_done => 0, mrnas_done => 0);
  while (my $line = <$fh>) {
    chomp $line;
    my ($id, $group, @values) = split /\t/, $line, -1;
    # a family has no gene id but does have a label, so only a fully empty row is skipped
    next unless grep { defined and $_ ne '' } @values;
    $species{by_id}{$id} = \@values;
    $species{by_group}{$group} //= \@values;
  }
  close $fh;
  push @species, \%species;
}

# pass 1: which gene does each mRNA belong to, and what are its closest genes
open my $gff_fh, '<', $gff or die "cant open $gff $!\n";
while (my $line = <$gff_fh>) {
  next if $line =~ /^#/;
  my @fields = split /\t/, $line;
  next unless @fields >= 9 and $fields[2] eq 'mRNA';
  my ($id)     = $fields[8] =~ /(?:^|;)ID=([^;]+)/;
  my ($parent) = $fields[8] =~ /(?:^|;)Parent=([^;,]+)/;
  next unless defined $id and defined $parent;
  foreach my $species (@species) {
    my $values = values_for_id($species, $id) or next;
    $species->{for_gene}{$parent} //= $values;
  }
}
close $gff_fh;

# pass 2: write
open $gff_fh, '<', $gff or die "cant open $gff $!\n";
while (my $line = <$gff_fh>) {
  chomp $line;
  my @fields = split /\t/, $line;
  if ($line !~ /^#/ and @fields >= 9 and ($fields[2] eq 'gene' or $fields[2] eq 'mRNA')) {
    my ($id) = $fields[8] =~ /(?:^|;)ID=([^;]+)/;
    foreach my $species (@species) {
      next unless defined $id;
      my $values;
      if ($fields[2] eq 'gene') {
        $values = $species->{for_gene}{$id} // $species->{by_group}{$id} // $species->{by_group}{strip_prefix($id)};
        $species->{genes_done}++ if $values;
      } else {
        $values = values_for_id($species, $id);
        $species->{mrnas_done}++ if $values;
      }
      next unless $values;
      my @attributes = @{$species->{attributes}};
      foreach my $attribute (@attributes) {
        $fields[8] =~ s/;?\Q$attribute\E=[^;]*//g;
      }
      # the last is the evidence, one value; the others are lists. An empty value is left out
      # (a family has no gene id: closestHGNC is omitted, not written as "closestHGNC=")
      $fields[8] .= join('', map { ";$attributes[$_]=" . ($_ == 3 ? escape_value($values->[$_]) : escape_list($values->[$_])) }
                             grep { defined $values->[$_] and $values->[$_] ne '' } 0 .. 3);
      $line = join("\t", @fields);
    }
  }
  print "$line\n";
}
close $gff_fh;
foreach my $species (@species) {
  warn "$species->{attributes}[0] ...: added to $species->{genes_done} genes and $species->{mrnas_done} mRNAs ($species->{file})\n";
}

sub strip_prefix {
  my ($id) = @_;
  $id =~ s/^(?:transcript:|gene:|rna-|gene-)//;
  return $id;
}

sub values_for_id {
  my ($species, $id) = @_;
  return $species->{by_id}{$id} // $species->{by_id}{strip_prefix($id)};
}

# one GFF3 attribute value: escape the characters GFF3 reserves
sub escape_value {
  my ($value) = @_;
  $value //= '';
  $value =~ s/%(?![0-9A-Fa-f]{2})/%25/g;
  $value =~ s/;/%3B/g;
  $value =~ s/=/%3D/g;
  $value =~ s/&/%26/g;
  $value =~ s/,/%2C/g;
  $value =~ s/\t/%09/g;
  return $value;
}

# comma-separated list from a closest_<tag>.tsv (commas inside an item are already %2C)
sub escape_list {
  my ($list) = @_;
  my @items;
  foreach my $item (split /,/, $list // '') {
    push @items, escape_value($item);
  }
  return join(',', @items);
}
