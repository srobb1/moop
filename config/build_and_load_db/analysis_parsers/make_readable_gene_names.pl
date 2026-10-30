#!/usr/bin/perl
# geneNames.tsv for people (2026-10-29): one row per database feature, with its gene and type, the
# name split into symbol and description, and where the name came from in words.
#
#   make_readable_gene_names.pl features.tsv geneNames.tsv [OMA_run/DB] > geneNames.readable.tsv
#
# geneNames.tsv itself is for the pipeline (updateFASTA.pl, updateGFF.pl and the loaders read it by
# column; MAINID SELF marks the protein a gene was named from) and stays as it is. This file is the one
# to hand to a person: grep a type ("mRNA", "protein") to get one row per transcript or per protein.
#
# Columns: gene_id  type  id  symbol  description  name_from  method  evidence
#   type       gene, mRNA, CDS or protein (from features.tsv, the database's own list)
#   name_from  the gene set's own annotation (RefSeq, Ensembl, ...), MOOP, or none
#   method     how MOOP named it (OMA human ortholog, PANTHER family, ...); "Source name retained" for the source's own name
#   evidence   the accession the MOOP name rests on (human ortholog, PANTHER family, InterPro entry, ...);
#              an OMA id (HUMAN027043) is given as the protein's external id, the one the database's
#              OMA ortholog tables carry (ENSP..., else XP_/NP_), read from the OMA run's DB/<CODE>.fa
# No cell is blank: an unnamed gene has description "No name" and name_from "none"; anything else
# without a value is N/A.
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;
use File::Basename qw(dirname);

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

# OMA ids of one species code -> the protein's external id, from the OMA run's DB/<CODE>.fa headers
# ("HUMAN027043 | ENST00000302451.9; ENSP00000305465.4 | ENSG... | ..."): the Ensembl protein, else the
# RefSeq one, as getOMA_orthologs.moop.pl picks them for the database
sub read_oma_external_ids {
  my ($db_dir, $code) = @_;
  my %external;
  my $fasta = "$db_dir/$code.fa";
  return \%external unless -s $fasta;
  open my $fh, '<', $fasta or die "cant open $fasta $!\n";
  while (my $line = <$fh>) {
    next unless $line =~ /^>(\S+)\s*\|(.*)/;
    my ($oma_id, $rest) = ($1, $2);
    my @ids = map { my $field = $_; $field =~ s/^\s+|\s+$//gr } split /[|;]/, $rest;
    my ($ensembl) = grep { my $field = $_; $field =~ /^ENS\w*P\d/ } @ids;
    my ($refseq)  = grep { my $field = $_; $field =~ /^[XN]P_\d/ } @ids;
    my $chosen = $ensembl // $refseq;
    $external{$oma_id} = $chosen if defined $chosen;
  }
  close $fh;
  return \%external;
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
  my ($features_file, $names_file, $oma_db_dir) = @ARGV;
  die "usage: $0 features.tsv geneNames.tsv [OMA_run/DB] > geneNames.readable.tsv\n" unless defined $names_file;

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

  # a gene set whose ids MOOP rewrote (moop-lift-prefix, moop-strip-id-prefix) after naming: geneNames.tsv
  # has the ids from before, features.tsv those after. The same rewrite as load_annotations_sqlite.pl,
  # from the record strip_id_prefix.pl left beside features.tsv
  my $manifest = dirname($features_file) . '/.id_prefix_stripped';
  if (open my $manifest_fh, '<', $manifest) {
    my $line = <$manifest_fh> // '';
    close $manifest_fh;
    chomp $line;
    my ($strip, $add) = map { my $part = $_; $part // '' } (split /\t/, $line, 2)[0, 1];
    if (length $strip or length $add) {
      foreach my $names (\%name_of, \%gene_name) {
        foreach my $id (keys %$names) {
          (my $rewritten = $id) =~ s/^\Q$strip\E/$add/;
          $names->{$rewritten} //= $names->{$id};
        }
      }
    }
  }

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

  my %oma_external;   # species code -> {OMA id -> external id}, read when first needed
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
    my @row = ($gene, $type_of{$id}, $id, $symbol, $description, provenance(@$entry));
    if (defined $oma_db_dir and $row[7] =~ /^([A-Z0-9]{5})\d+$/) {
      my $code = $1;
      $oma_external{$code} //= read_oma_external_ids($oma_db_dir, $code);
      $row[7] = $oma_external{$code}{$row[7]} // $row[7];
    }
    # no blank cells (the readers asked, 2026-10-29): an unnamed gene says so, anything else empty is N/A
    $row[4] = 'No name' if $row[4] eq '' and $row[5] eq 'none';
    print join("\t", map { my $cell = $_; $cell eq '' ? 'N/A' : $cell } @row), "\n";
  }
}

main();
