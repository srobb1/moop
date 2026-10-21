#!/usr/bin/perl
# Compare a gene set's id_map.tsv (build_id_map.pl) with the features loaded in organism.sqlite.
# Step 1 of notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md: the map must reproduce today's database
# (uniquename, type, parent) exactly, except the plan's listed fixes.
#
#   compare_id_map_to_db.pl <id_map.tsv> <organism.sqlite> <assembly> <gene_set> <details.tsv>
#
# Prints one summary line (tab-separated) on stdout:
#   in_both  same_parent  parent_differs  type_differs  only_map  only_db  by-type detail
# and writes every difference to <details.tsv>: kind, uniquename, map type, map parent, db type, db parent.
#
# A parent the map names but does not itself contain counts as no parent: the loader makes such a
# feature a root, so that is what the database holds.
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;
use DBI;

sub main {
  my ($map_file, $db_file, $assembly, $gene_set, $details_file) = @ARGV;
  die "Usage: $0 <id_map.tsv> <organism.sqlite> <assembly> <gene_set> <details.tsv>\n" unless defined $details_file;

  my %map;
  open my $in, '<', $map_file or die "Cannot read $map_file: $!\n";
  <$in>;
  while (my $line = <$in>) {
    chomp $line;
    my ($id, $type, $parent) = split /\t/, $line;
    $map{$id} = [$type, $parent // ''];
  }
  close $in;
  foreach my $id (keys %map) {
    $map{$id}[1] = '' unless $map{$id}[1] ne '' && exists $map{ $map{$id}[1] };
  }

  my $dbh = DBI->connect("dbi:SQLite:dbname=$db_file", '', '', { RaiseError => 1, ReadOnly => 1 });
  ## the gene set by name; the assembly only breaks a tie (genome_accession is the metadata's
  ## accession, e.g. GCA_000002035.4, not the folder name GRCz11)
  my $sets = $dbh->selectall_arrayref(q{
    SELECT gs.gene_set_id, g.genome_accession FROM gene_set gs JOIN genome g ON g.genome_id = gs.genome_id
    WHERE gs.gene_set_name = ?
  }, undef, $gene_set);
  if (@$sets > 1) {
    my @same_assembly;
    foreach my $set (@$sets) {
      push @same_assembly, $set if $set->[1] eq $assembly;
    }
    $sets = \@same_assembly;
  }
  die "$db_file: gene set '$gene_set' ($assembly) found " . scalar(@$sets) . " times\n" unless @$sets == 1;
  my $rows = $dbh->selectall_arrayref(q{
    SELECT f.feature_uniquename, f.feature_type, COALESCE(p.feature_uniquename, '')
    FROM feature f
    LEFT JOIN feature p ON p.feature_id = f.parent_feature_id
    WHERE f.gene_set_id = ?
  }, undef, $sets->[0][0]);
  $dbh->disconnect;
  my %db;
  foreach my $row (@$rows) {
    $db{ $row->[0] } = [$row->[1], $row->[2]];
  }

  my ($both, $same, $parent_differs, $type_differs, $only_map, $only_db) = (0, 0, 0, 0, 0, 0);
  my %by_kind_type;
  open my $out, '>', $details_file or die "Cannot write $details_file: $!\n";
  print $out join("\t", qw(kind uniquename map_type map_parent db_type db_parent)), "\n";
  foreach my $id (sort keys %map) {
    my ($map_type, $map_parent) = @{ $map{$id} };
    if (!exists $db{$id}) {
      $only_map++;
      $by_kind_type{"only_map:$map_type"}++;
      print $out join("\t", 'only_map', $id, $map_type, $map_parent, '', ''), "\n";
      next;
    }
    $both++;
    my ($db_type, $db_parent) = @{ $db{$id} };
    my $ok = 1;
    if ($db_type ne $map_type) {
      $type_differs++;
      $by_kind_type{"type:$db_type>$map_type"}++;
      $ok = 0;
    }
    if ($db_parent ne $map_parent) {
      $parent_differs++;
      $by_kind_type{"parent:$map_type"}++;
      $ok = 0;
    }
    if ($ok) {
      $same++;
    } else {
      print $out join("\t", 'differs', $id, $map_type, $map_parent, $db_type, $db_parent), "\n";
    }
  }
  foreach my $id (sort keys %db) {
    next if exists $map{$id};
    $only_db++;
    $by_kind_type{"only_db:$db{$id}[0]"}++;
    print $out join("\t", 'only_db', $id, '', '', @{ $db{$id} }), "\n";
  }
  close $out;

  my @detail;
  foreach my $key (sort keys %by_kind_type) {
    push @detail, "$key=$by_kind_type{$key}";
  }
  print join("\t", $both, $same, $parent_differs, $type_differs, $only_map, $only_db, join(' ', @detail)), "\n";
}

main();
