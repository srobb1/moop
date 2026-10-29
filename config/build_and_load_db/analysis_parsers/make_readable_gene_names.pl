#!/usr/bin/perl
# geneNames.tsv for people (2026-10-29): one row per database feature, with its gene and type, the
# name split into symbol and description, and where the name came from in words.
#
#   make_readable_gene_names.pl features.tsv geneNames.tsv > geneNames.readable.tsv
#
# geneNames.tsv itself is for the pipeline (updateFASTA.pl, updateGFF.pl and the loaders read it by
# column; MAINID SELF marks the protein a gene was named from) and stays as it is. This file is the one
# to hand to a person: grep a type ("mRNA", "protein") to get one row per transcript or per protein.
#
# Columns: gene_id  type  id  symbol  description  name_from  method  evidence
#   type       gene, mRNA, CDS or protein (from features.tsv, the database's own list)
#   name_from  the gene set's own annotation (RefSeq, Ensembl, ...), MOOP, or none
#   method     how MOOP named it (OMA human ortholog, PANTHER family, ...); "Source name retained" for the source's own name
#   evidence   the accession the MOOP name rests on (human OMA id, PANTHER family, InterPro entry, ...)
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;

my %TYPE_WORD = (gene => 'gene', mRNA => 'mRNA', transcript => 'mRNA', cds => 'CDS', CDS => 'CDS', protein => 'protein');

# the naming step that gave the name, from the first field of geneNames.tsv's Note column
my %METHOD = (
  OMA_pairwise_HUMAN  => 'OMA human ortholog',
  OMA_HOG_HUMAN       => 'OMA HOG human ortholog',
  PANTHER_TreeGrafter => 'PANTHER tree placement',
  PANTHER             => 'PANTHER family',
  InterPro            => 'InterPro domain',
  Pfam                => 'Pfam domain',
);

# a naming note "Source|Kind|protein|accession|score" in words: (name_from, method, evidence)
sub provenance {
  my ($description, $note) = @_;
  return ('none', '', '') if $description eq '' or $description eq 'None';
  # the source's own name ("RefSeq" in name_from): method "Source name retained", the same for every
  # source; no evidence (N/A, not blank)
  return ($note ne '' ? $note : 'the gene set', 'Source name retained', 'N/A') unless $note =~ /\|/;
  my ($source, $kind, undef, $accession) = split /\|/, $note;
  return ('none', '', '') if $source eq 'none';
  my $method = $METHOD{$source};
  $method //= $source =~ /^MMseqs2_RBH_(\w+)/ ? 'reciprocal best hit (MMseqs2), ' . ($1 =~ tr/_/ /r) : ($source =~ tr/_/ /r);
  $method .= ' (transposable element)' if ($kind // '') eq 'Transposable_element';
  return ('MOOP', $method, ($accession // '') eq '-' ? '' : $accession // '');
}

# "pdxp: pyridoxal phosphate phosphatase [SRC]" -> ("pdxp", "pyridoxal phosphate phosphatase")
sub symbol_and_description {
  my ($text) = @_;
  return ('', '') if $text eq '' or $text eq 'None';
  $text =~ s/\s*\[(?:ISO|ISS|ISM|TAS|SRC)(?:\|[^\[\]]*)?\]\s*$//;   # the evidence code: the method column says it
  return ($1, $2) if $text =~ /^(\S+):\s*(.*)$/;
  return ('', $text);
}

sub main {
  my ($features_file, $names_file) = @ARGV;
  die "usage: $0 features.tsv geneNames.tsv > geneNames.readable.tsv\n" unless defined $names_file;

  # geneNames.tsv: each id's name and note; a gene's name from its own row, else from the row its
  # proteins were named by (own gene sets have no gene rows: GroupId is the gene, MAINID SELF the row)
  my (%name_of, %gene_name);
  open my $names_fh, '<', $names_file or die "cant open $names_file $!\n";
  while (my $line = <$names_fh>) {
    chomp $line;
    next if $line =~ /^ID\t/;
    my ($id, $main, $group, $description, $note) = split /\t/, $line;
    next unless defined $group;
    my $entry = [$description // '', $note // ''];
    $name_of{$id} = $entry;
    $gene_name{$group} = $entry if $id eq $group or (($main // '') eq 'SELF' and !exists $gene_name{$group});
  }
  close $names_fh;

  # features.tsv: id, type, parent (database ids); a feature's gene by walking up its parents
  my (@order, %type_of, %parent_of);
  open my $features_fh, '<', $features_file or die "cant open $features_file $!\n";
  while (my $line = <$features_fh>) {
    next if $line =~ /^#/;
    chomp $line;
    my ($id, $type, $parent) = split /\t/, $line;
    next unless defined $type and $TYPE_WORD{$type} and !exists $type_of{$id};
    push @order, $id;
    $type_of{$id} = $TYPE_WORD{$type};
    $parent_of{$id} = $parent if defined $parent and $parent ne '';
  }
  close $features_fh;

  print join("\t", qw(gene_id type id symbol description name_from method evidence)), "\n";
  foreach my $id (@order) {
    my $gene = $id;
    for (my $step = 0; $step < 10 and exists $parent_of{$gene}; $step++) {
      $gene = $parent_of{$gene};
    }
    # the feature's own row (a RefSeq mRNA has its own product text), else its gene's name
    (my $bare = $id) =~ s/:(?:cds|pep)$//;
    my $entry = $name_of{$id} // $name_of{$bare} // $gene_name{$gene} // ['', ''];
    my ($symbol, $description) = symbol_and_description($entry->[0]);
    print join("\t", $gene, $type_of{$id}, $id, $symbol, $description, provenance(@$entry)), "\n";
  }
}

main();
