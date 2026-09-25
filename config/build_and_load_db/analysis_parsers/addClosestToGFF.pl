#!/usr/bin/perl
use strict;
use warnings;

# Add the closest human gene from geneNames.tsv (naming v2, columns 6-9) to every gene and mRNA
# of a GFF3, without touching names:
#
#   addClosestHumanToGFF.pl genes.gff geneNames.tsv > genes.closest.gff
#
#   closestHGNC=HGNC:20773;closestHumanSym=TUBB8;closestHumanDesc=tubulin beta 8 class VIII;
#   closestHumanEvidence=OMA ortholog (1:1)
#
# Several human genes (a family) are comma-separated in matching order. Values are GFF3-escaped
# (; = & , inside a value). Runs after updateGFF.pl on renamed gene sets, and on native
# RefSeq/Ensembl gene sets whose names are kept.
#
# An mRNA is matched to geneNames.tsv by its ID, with or without the "transcript:" / "rna-"
# prefix that Ensembl and RefSeq GFFs use. A gene gets the value of its mRNAs (through Parent=),
# since the gene ids in geneNames.tsv (e.g. RefSeq GeneID numbers) need not be the GFF gene ids;
# a gene whose id is itself in geneNames.tsv (GroupId) is matched directly.

my $usage = "usage: $0 genes.gff geneNames.tsv > out.gff\n";
my $gff   = shift or die $usage;
my $names = shift or die $usage;

my (%closest_by_id, %closest_by_group);
open my $names_fh, '<', $names or die "cant open $names $!\n";
my $header = <$names_fh>;
die "$names has no closest-human columns (naming v2 geneNames.tsv expected)\n"
  unless defined $header and $header =~ /\tclosestHGNC\t/;
while (my $line = <$names_fh>) {
  chomp $line;
  my ($id, $main, $group, $desc, $note, $hgnc, $symbol, $human_desc, $evidence) = split /\t/, $line, -1;
  next unless defined $hgnc and $hgnc ne '';
  my $closest = { hgnc => $hgnc, symbol => $symbol, desc => $human_desc, evidence => $evidence };
  $closest_by_id{$id} = $closest;
  $closest_by_group{$group} //= $closest;
}
close $names_fh;

# pass 1: which gene does each mRNA belong to, and what is its closest human
my %closest_for_gene;
open my $gff_fh, '<', $gff or die "cant open $gff $!\n";
while (my $line = <$gff_fh>) {
  next if $line =~ /^#/;
  my @fields = split /\t/, $line;
  next unless @fields >= 9 and $fields[2] eq 'mRNA';
  my ($id)     = $fields[8] =~ /(?:^|;)ID=([^;]+)/;
  my ($parent) = $fields[8] =~ /(?:^|;)Parent=([^;,]+)/;
  next unless defined $id and defined $parent;
  my $closest = closest_for_id($id) or next;
  $closest_for_gene{$parent} //= $closest;
}
close $gff_fh;

# pass 2: write
my ($genes_done, $mrnas_done) = (0, 0);
open $gff_fh, '<', $gff or die "cant open $gff $!\n";
while (my $line = <$gff_fh>) {
  chomp $line;
  my @fields = split /\t/, $line;
  if ($line !~ /^#/ and @fields >= 9 and ($fields[2] eq 'gene' or $fields[2] eq 'mRNA')) {
    my ($id) = $fields[8] =~ /(?:^|;)ID=([^;]+)/;
    my $closest;
    if (defined $id and $fields[2] eq 'gene') {
      $closest = $closest_for_gene{$id} // $closest_by_group{$id} // $closest_by_group{strip_prefix($id)};
      $genes_done++ if $closest;
    } elsif (defined $id) {
      $closest = closest_for_id($id);
      $mrnas_done++ if $closest;
    }
    if ($closest) {
      $fields[8] =~ s/;?closest(?:HGNC|HumanSym|HumanDesc|HumanEvidence)=[^;]*//g;
      $fields[8] .= join('', ';closestHGNC=', escape_list($closest->{hgnc}),
                             ';closestHumanSym=', escape_list($closest->{symbol}),
                             ';closestHumanDesc=', escape_list($closest->{desc}),
                             ';closestHumanEvidence=', escape_value($closest->{evidence}));
      $line = join("\t", @fields);
    }
  }
  print "$line\n";
}
close $gff_fh;
warn "closest human added to $genes_done genes and $mrnas_done mRNAs\n";

sub strip_prefix {
  my ($id) = @_;
  $id =~ s/^(?:transcript:|gene:|rna-|gene-)//;
  return $id;
}

sub closest_for_id {
  my ($id) = @_;
  return $closest_by_id{$id} // $closest_by_id{strip_prefix($id)};
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

# comma-separated list from geneNames.tsv (commas inside an item are already %2C)
sub escape_list {
  my ($list) = @_;
  my @items;
  foreach my $item (split /,/, $list // '') {
    push @items, escape_value($item);
  }
  return join(',', @items);
}
