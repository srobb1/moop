#!/usr/bin/perl
# RefSeq/Ensembl genes.gff with the database's gene and mRNA IDs (notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md).
#
#   format_gff_ids.pl <genes.gff> <id_map.tsv> > genes.formatted.gff
#
# <id_map.tsv> is build_id_map.pl's, built from the same GFF (Ensembl: genes.versioned.gff). Its
# gene and mRNA rows say which GFF ID becomes which database ID:
#
#   gene  ID=gene-LOC116613690       ->  ID=116613690
#   mRNA  ID=rna-XM_048724567.1      ->  ID=XM_048724567.1          (Parent=116613690)
#   gene  ID=gene:ENSDARG00000099104.1 -> ID=ENSDARG00000099104.1
#
# Every Parent= naming a renamed feature follows it (exons, CDS, non-coding transcripts under a
# coding gene). Nothing else changes: CDS IDs and protein_id= already are the database's, features
# not in the map (non-coding genes, V/C segments, exons) keep their IDs, Name= is left to
# updateGFF.pl.
#
# A renamed line gets Alias= with every other ID a person searches by, deduplicated and never its
# new ID: the original ID; the source's Name=, gene=, locus_tag=, old_locus_tag=, gene_synonym=,
# transcript_id=; the protein_id(s) of its CDS lines; and the version-less form of every accession
# among them (XM_048724567, ENSDARG00000099104, XP_...). Symbols are never cut at a dot (F54H12.3).
#
# Dies, writing nothing, when a mapped ID is not in the GFF or a new ID is already another
# feature's ID.
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;

## attributes whose values are other names for the feature
my @ALIAS_SOURCES = qw(Name gene locus_tag old_locus_tag gene_synonym transcript_id);
## an accession whose trailing .N is a version: RefSeq (XM_123.1) or Ensembl (ENSDART00000164359.1)
my $VERSIONED_ACCESSION = qr/^(?:[A-Z]{2}_\d+|ENS[A-Z]*\d{6,})\.\d+$/;

## the map's gene/mRNA rows: original GFF ID => database ID
sub read_map {
  my ($file) = @_;
  my %new_id;
  open my $in, '<', $file or die "Cannot read $file: $!\n";
  my $header = <$in>;
  die "$file: not an id_map.tsv\n" unless defined $header && $header =~ /^moop_id\ttype\tparent_moop_id\toriginal_gff_id/;
  while (my $line = <$in>) {
    chomp $line;
    my ($moop_id, $type, undef, $original) = split /\t/, $line;
    next unless $type eq 'gene' || $type eq 'mRNA';
    next unless defined $original && $original ne '';
    $new_id{$original} = $moop_id;
  }
  close $in;
  return \%new_id;
}

## "key=value;..." as an ordered list of [key, value]
sub attributes {
  my ($column) = @_;
  my @pairs;
  foreach my $pair (split /;/, $column) {
    my $eq = index($pair, '=');
    if ($eq < 1) {
      push @pairs, [$pair, undef];
      next;
    }
    push @pairs, [substr($pair, 0, $eq), substr($pair, $eq + 1)];
  }
  return @pairs;
}

sub main {
  my ($gff_file, $map_file) = @ARGV;
  die "Usage: $0 <genes.gff> <id_map.tsv> > out.gff\n" unless defined $map_file;
  my $new_id = read_map($map_file);

  ## pass 1: every ID in the file, and the protein_ids under each CDS parent
  my (%in_gff, %proteins_of);
  open my $in, '<', $gff_file or die "Cannot read $gff_file: $!\n";
  while (my $line = <$in>) {
    next if $line =~ /^#/;
    chomp $line;
    my @field = split /\t/, $line;
    next unless @field >= 9;
    my %attr = map { defined $_->[1] ? ($_->[0] => $_->[1]) : () } reverse attributes($field[8]);
    $in_gff{ $attr{ID} } = 1 if defined $attr{ID};
    if ($field[2] eq 'CDS' && defined $attr{protein_id} && defined $attr{Parent}) {
      foreach my $parent (split /,/, $attr{Parent}) {
        push @{ $proteins_of{$parent} }, $attr{protein_id};
      }
    }
  }
  close $in;

  my @missing = grep { !$in_gff{$_} } sort keys %$new_id;
  die "format_gff_ids.pl: " . scalar(@missing) . " mapped ID(s) not in $gff_file, e.g. "
    . join(', ', @missing[0 .. ($#missing < 4 ? $#missing : 4)]) . "\n" if @missing;
  my %renamed_away = map { $_ => 1 } grep { $new_id->{$_} ne $_ } keys %$new_id;
  foreach my $original (sort keys %$new_id) {
    my $target = $new_id->{$original};
    die "format_gff_ids.pl: $original -> $target, but $target is already another feature's ID\n"
      if $target ne $original && $in_gff{$target} && !$renamed_away{$target};
  }

  ## pass 2: write
  my ($renamed, $reparented) = (0, 0);
  open $in, '<', $gff_file or die "Cannot read $gff_file: $!\n";
  while (my $line = <$in>) {
    if ($line =~ /^#/) {
      print $line;
      next;
    }
    chomp $line;
    my @field = split /\t/, $line;
    if (@field < 9) {
      print "$line\n";
      next;
    }
    my @pairs = attributes($field[8]);
    my %attr = map { defined $_->[1] ? ($_->[0] => $_->[1]) : () } reverse @pairs;
    my $original = $attr{ID};
    my $new = defined $original ? $new_id->{$original} : undef;

    my $parent_changed = 0;
    foreach my $pair (@pairs) {
      next unless defined $pair->[1];
      if ($pair->[0] eq 'ID' && defined $new) {
        $pair->[1] = $new;
      }
      elsif ($pair->[0] eq 'Parent') {
        my @parents = split /,/, $pair->[1];
        foreach my $parent (@parents) {
          next unless exists $new_id->{$parent};
          $parent = $new_id->{$parent};
          $parent_changed = 1;
        }
        $pair->[1] = join(',', @parents);
      }
    }
    $reparented++ if $parent_changed;

    if (defined $new) {
      $renamed++;
      my @aliases = ($original);
      foreach my $key (@ALIAS_SOURCES) {
        push @aliases, split(/,/, $attr{$key}) if defined $attr{$key};
      }
      push @aliases, @{ $proteins_of{$original} // [] };
      push @aliases, split(/,/, $attr{Alias}) if defined $attr{Alias};
      my @versionless;
      foreach my $alias ($new, @aliases) {
        push @versionless, $1 if $alias =~ $VERSIONED_ACCESSION && $alias =~ /^(.+)\.\d+$/;
      }
      my %seen = ($new => 1);
      my @alias_list = grep { $_ ne '' && !$seen{$_}++ } (@aliases, @versionless);
      @pairs = grep { $_->[0] ne 'Alias' } @pairs;
      push @pairs, ['Alias', join(',', @alias_list)] if @alias_list;
    }

    $field[8] = join(';', map { defined $_->[1] ? "$_->[0]=$_->[1]" : $_->[0] } @pairs);
    print join("\t", @field), "\n";
  }
  close $in;
  print STDERR "format_gff_ids.pl: $renamed line(s) renamed, $reparented re-parented\n";
}

main();
