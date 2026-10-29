#!/usr/bin/perl
# The gate of notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md: does a gene set's genes.gff and FASTAs carry the
# same IDs and names as its features in organism.sqlite? Read-only.
#
#   check_id_alignment.pl <organism> <organism.sqlite> <assembly> <gene_set> <source> <build_dir> <details.tsv> [exceptions.tsv]
#
# <source> is process_one_geneset.sh's GFF_SOURCE (refseq, ensembl, other, lift). <build_dir> holds
# genes.gff, protein.aa.fa, cds.nt.fa and transcript.nt.fa as shipped. exceptions.tsv
# (id_alignment_exceptions.tsv: organism, gene_set, id, reason) lists features allowed to differ
# (the plan's listed exceptions); this gene set's are counted, not failed. Differences that follow
# from how the database is built (second placements, CDSs without a protein_id, immune gene segments,
# mitochondrial mRNAs without transcript_id) are recognised from genes.gff (rule_exceptions) and
# counted the same way, so the file holds only the one-offs no rule describes.
#
# Checks, protein-coding features only (genes.gff also holds tRNA, lncRNA, pseudogenes and V/C
# segments the database does not load):
#   gff_missing    a database gene/mRNA (every source) or CDS/protein (refseq/ensembl, whose GFF CDS
#                  lines carry the IDs) that is not an ID= / protein_id= in genes.gff
#   gff_extra      a coding gene/mRNA in genes.gff (one with a CDS below it), or for refseq/ensembl
#                  a CDS ID or protein_id, that is not in the database
#   fasta_missing  a database protein / CDS / mRNA with no record in its FASTA
#   fasta_extra    a protein or CDS FASTA record that is not in the database (transcript FASTA
#                  records that are not are counted as tx_fasta_not_db: non-coding, not a failure)
#   fasta_name     a database feature whose FASTA header does not carry its database name and
#                  description, in updateFASTA.pl's shape ">id NAME DESCRIPTION SOURCE"
#   gff_name       a database gene/mRNA whose GFF Name= is not its database name
#
# Prints one summary line on stdout (check=count ...), writes every difference to <details.tsv>
# (check, id, type, detail), exits 1 when any check failed.
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;
use DBI;
use URI::Escape qw(uri_unescape);

my @CHECKS = qw(gff_missing gff_extra fasta_missing fasta_extra fasta_name gff_name);
my %FASTA_OF = (protein => 'protein.aa.fa', cds => 'cds.nt.fa', mRNA => 'transcript.nt.fa');

## the database's types, folded: transcript is an mRNA here, cds/CDS one CDS type
sub kind_of {
  my ($type) = @_;
  return 'mRNA' if $type eq 'mRNA' || $type eq 'transcript';
  return 'cds'  if lc $type eq 'cds';
  return $type;
}

## text as a person reads it: unescaped, one space between words
sub plain {
  my ($text) = @_;
  $text = uri_unescape($text // '');
  $text =~ s/\s+/ /g;
  $text =~ s/^ | $//g;
  return $text;
}

sub read_db {
  my ($db_file, $assembly, $gene_set) = @_;
  my $dbh = DBI->connect("dbi:SQLite:dbname=$db_file", '', '', { RaiseError => 1, ReadOnly => 1 });
  ## the gene set by name; the assembly only breaks a tie (as compare_id_map_to_db.pl)
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
    SELECT feature_uniquename, feature_type, COALESCE(feature_name, ''), COALESCE(feature_description, '')
    FROM feature WHERE gene_set_id = ?
  }, undef, $sets->[0][0]);
  $dbh->disconnect;
  my %db;
  foreach my $row (@$rows) {
    my ($id, $type, $name, $desc) = @$row;
    ## naming writes "None" for no name/description (updateFASTA.pl reads it as empty)
    $name = '' if $name eq 'None';
    $desc = '' if $desc eq 'None';
    $db{$id} = { kind => kind_of($type), name => plain($name), desc => plain($desc) };
  }
  return \%db;
}

## genes.gff: every ID= with its type and first Name=, every protein_id=, and which genes, mRNAs
## and CDSs are coding
sub read_gff {
  my ($gff_file, $carries_cds_ids) = @_;
  my (%type_of, %name_of, %parents_of, %protein, %coding, %protein_of, %has_transcript_id);
  open my $in, '<', $gff_file or die "Cannot read $gff_file: $!\n";
  while (my $line = <$in>) {
    next if $line =~ /^#/;
    chomp $line;
    my @field = split /\t/, $line;
    next unless @field >= 9;
    my %attr;
    foreach my $pair (split /;/, $field[8]) {
      my ($key, $value) = split /=/, $pair, 2;
      $attr{$key} //= $value if defined $value;
    }
    my $id = $attr{ID};
    my @parents = defined $attr{Parent} ? split(/,/, $attr{Parent}) : ();
    if (defined $id) {
      $type_of{$id} //= $field[2];
      $name_of{$id} //= plain($attr{Name}) if defined $attr{Name};
      push @{ $parents_of{$id} }, @parents;
      $protein_of{$id} //= $attr{protein_id} if $field[2] eq 'CDS';
      $has_transcript_id{$id} = 1 if defined $attr{transcript_id};
    }
    ## refseq/ensembl: coding means a protein_id (pseudogene and V/C segment CDSs have none);
    ## other/lift CDS lines rarely carry one, so any CDS counts
    if ($field[2] eq 'CDS' && (defined $attr{protein_id} || !$carries_cds_ids)) {
      $protein{ $attr{protein_id} } = 1 if defined $attr{protein_id};
      $coding{$id} = 1 if defined $id;
      foreach my $parent (@parents) {
        $coding{$parent} = 1;
      }
    }
  }
  close $in;
  ## a coding mRNA makes its gene coding (bacteria: the CDS sits on the gene directly)
  foreach my $id (keys %coding) {
    foreach my $parent (@{ $parents_of{$id} // [] }) {
      $coding{$parent} = 1;
    }
  }
  return { type_of => \%type_of, name_of => \%name_of, protein => \%protein, coding => \%coding,
           parents_of => \%parents_of, protein_of => \%protein_of, has_transcript_id => \%has_transcript_id };
}

## Differences that follow from how the database is built, recognised from genes.gff itself, so a new
## RefSeq/Ensembl gene set needs no rows in id_alignment_exceptions.tsv (which keeps the one-offs no
## rule describes). id => reason. Rules, worked out on 2026-10-21 (notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md):
##   second placement   NCBI places a coding gene, mRNA or CDS more than once and suffixes the copies -2, -3..
##                      (refseq): the database has one feature per GeneID, transcript and protein
##   no protein_id      a CDS without one (pseudogene, RefSeq immune gene segment): not loaded
##   gene segment       an Ensembl C/D/J/V gene segment carries a CDS and protein: none of the segment,
##                      its gene, CDS and protein is loaded
##   mitochondrial      a RefSeq mRNA without transcript_id: the database hangs its CDS on the gene
sub rule_exceptions {
  my ($gff, $source) = @_;
  my %rule;
  return \%rule unless $source eq 'refseq' || $source eq 'ensembl';
  my ($type_of, $parents_of, $protein_of) = @$gff{qw(type_of parents_of protein_of)};
  foreach my $id (keys %$type_of) {
    my $type = $type_of->{$id};
    if ($source eq 'refseq' && $gff->{coding}{$id} && $id =~ /^(.+)-\d+$/ && ($type_of->{$1} // '') eq $type) {
      $rule{$id} = "second placement of $1";
    }
    elsif ($type eq 'CDS' && !defined $protein_of->{$id}) {
      $rule{$id} = 'CDS without protein_id (pseudogene or gene segment): not loaded';
    }
    elsif ($source eq 'refseq' && $type eq 'mRNA' && !$gff->{has_transcript_id}{$id}) {
      $rule{$id} = 'mRNA without transcript_id (mitochondrial): the database hangs its CDS on the gene';
    }
    elsif ($type =~ /^[CDJV]_gene_segment$/) {
      $rule{$id} = 'immune gene segment: not loaded';
      foreach my $gene (@{ $parents_of->{$id} // [] }) {
        $rule{$gene} = 'gene of an immune gene segment: not loaded';
      }
    }
  }
  ## the CDS and protein under a segment
  foreach my $id (keys %$protein_of) {
    my @segment = grep { my $parent = $_; ($type_of->{$parent} // '') =~ /^[CDJV]_gene_segment$/ } @{ $parents_of->{$id} // [] };
    next unless @segment;
    $rule{$id} = 'CDS of an immune gene segment: not loaded';
    $rule{ $protein_of->{$id} } = 'protein of an immune gene segment: not loaded' if defined $protein_of->{$id};
  }
  return \%rule;
}

## a FASTA's records: id => the header after the id ('' for a bare header)
sub read_fasta {
  my ($fasta_file) = @_;
  my %header;
  return undef unless -s $fasta_file;
  open my $in, '<', $fasta_file or die "Cannot read $fasta_file: $!\n";
  while (my $line = <$in>) {
    next unless $line =~ /^>(\S+)\s*(.*)$/;
    $header{$1} = plain($2);
  }
  close $in;
  return \%header;
}

## updateFASTA.pl's header for a named feature is "NAME DESCRIPTION SOURCE", NAME being the id when
## there is no symbol; with no description it is bare
sub header_matches {
  my ($rest, $name, $desc) = @_;
  return $rest eq '' if $desc eq '';
  my $label = $name ne '' ? quotemeta($name) : '\S+';
  return $rest =~ /^$label \Q$desc\E(?: |$)/;
}

sub main {
  my ($organism, $db_file, $assembly, $gene_set, $source, $dir, $details_file, $exceptions_file) = @ARGV;
  die "Usage: $0 <organism> <organism.sqlite> <assembly> <gene_set> <source> <build_dir> <details.tsv> [exceptions.tsv]\n"
    unless defined $details_file;
  my $carries_cds_ids = ($source eq 'refseq' || $source eq 'ensembl');

  ## this gene set's rows of id_alignment_exceptions.tsv (organism, gene_set, id, reason)
  my %exception;
  if (defined $exceptions_file) {
    open my $in, '<', $exceptions_file or die "Cannot read $exceptions_file: $!\n";
    while (my $line = <$in>) {
      next if $line =~ /^#/;
      chomp $line;
      my ($row_organism, $row_gene_set, $id, $reason) = split /\t/, $line;
      next unless defined $id && $row_organism eq $organism && $row_gene_set eq $gene_set;
      $exception{$id} = $reason // '';
    }
    close $in;
  }

  my $db  = read_db($db_file, $assembly, $gene_set);
  my $gff = read_gff("$dir/genes.gff", $carries_cds_ids);
  my $rule = rule_exceptions($gff, $source);
  my %fasta;
  foreach my $kind (keys %FASTA_OF) {
    $fasta{$kind} = read_fasta("$dir/$FASTA_OF{$kind}");
  }

  my (%count, %excepted_count, %rule_count);
  open my $out, '>', $details_file or die "Cannot write $details_file: $!\n";
  print $out join("\t", qw(check id type detail)), "\n";
  my $report = sub {
    my ($check, $id, $type, $detail) = @_;
    if (exists $exception{$id}) {
      $excepted_count{$check}++;
      return;
    }
    if (exists $rule->{$id}) {
      $rule_count{$check}++;
      return;
    }
    $count{$check}++;
    print $out join("\t", $check, $id, $type, $detail // ''), "\n";
  };

  foreach my $id (sort keys %$db) {
    my $feature = $db->{$id};
    my $kind = $feature->{kind};

    if ($kind eq 'gene' || $kind eq 'mRNA' || ($kind eq 'cds' && $carries_cds_ids)) {
      $report->('gff_missing', $id, $kind) unless exists $gff->{type_of}{$id};
    }
    elsif ($kind eq 'protein' && $carries_cds_ids) {
      $report->('gff_missing', $id, $kind) unless $gff->{protein}{$id};
    }

    if (($kind eq 'gene' || $kind eq 'mRNA') && $feature->{name} ne '' && exists $gff->{name_of}{$id}) {
      $report->('gff_name', $id, $kind, "gff '$gff->{name_of}{$id}' db '$feature->{name}'")
        unless $gff->{name_of}{$id} eq $feature->{name};
    }

    my $records = $fasta{$kind} or next;
    if (!exists $records->{$id}) {
      $report->('fasta_missing', $id, $kind);
      next;
    }
    ## a feature with no symbol is named by an id (its own or its mRNA's); updateFASTA.pl then
    ## labels the header with the id it looked up, which can be the delivered one. An unnamed
    ## other/lift CDS/protein has its mRNA's id as description too (emit_generic), and a bare
    ## ">id" header: no description either (Congeria COKUS1KC, 23,222 CDS + 23,222 proteins).
    my $symbol = exists $db->{ $feature->{name} } ? '' : $feature->{name};
    my $desc   = exists $db->{ $feature->{desc} } ? '' : $feature->{desc};
    $report->('fasta_name', $id, $kind, "header '$records->{$id}' db '$feature->{name}' / '$feature->{desc}'")
      unless header_matches($records->{$id}, $symbol, $desc);
  }

  foreach my $id (sort keys %{ $gff->{coding} }) {
    my $type = $gff->{type_of}{$id} // next;
    my $kind = kind_of($type);
    next unless $kind eq 'gene' || $kind eq 'mRNA' || ($kind eq 'cds' && $carries_cds_ids);
    $report->('gff_extra', $id, $type) unless exists $db->{$id};
  }
  if ($carries_cds_ids) {
    foreach my $id (sort keys %{ $gff->{protein} }) {
      $report->('gff_extra', $id, 'protein_id') unless exists $db->{$id};
    }
  }

  my $tx_not_db = 0;
  foreach my $kind (sort keys %fasta) {
    my $records = $fasta{$kind} or next;
    foreach my $id (sort keys %$records) {
      next if exists $db->{$id};
      if ($kind eq 'mRNA') {
        $tx_not_db++;
        next;
      }
      $report->('fasta_extra', $id, $kind);
    }
  }
  close $out;

  my @summary;
  foreach my $check (@CHECKS) {
    my $text = "$check=" . ($count{$check} // 0);
    $text .= "(+$rule_count{$check} by rule)" if $rule_count{$check};
    $text .= "(+$excepted_count{$check} listed)" if $excepted_count{$check};
    push @summary, $text;
  }
  my @no_file = grep { !$fasta{$_} } sort keys %FASTA_OF;
  push @summary, "tx_fasta_not_db=$tx_not_db";
  push @summary, 'no_fasta=' . join(',', map { $FASTA_OF{$_} } @no_file) if @no_file;
  print join("\t", scalar(keys %$db), join(' ', @summary)), "\n";
  exit(%count ? 1 : 0);
}

main();
