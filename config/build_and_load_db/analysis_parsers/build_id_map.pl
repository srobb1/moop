#!/usr/bin/perl
# One gene set's ID map: every feature MOOP loads, its MOOP ID, its parent, and the IDs it had
# in the delivered files. notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md (v3) -- the ONLY code that knows
# RefSeq, Ensembl, other, lift and the prefix keys.
#
#   build_id_map.pl --gff <delivered genes.gff> --source refseq|ensembl|other|lift --out <id_map.tsv>
#       [--protein <protein.aa.fa>] [--cds <cds.nt.fa>] [--transcript <transcript.nt.fa>]
#       [--strip <prefix> [--add <prefix>]] [--lift-prefix <prefix>]
#
# --source is the shell's own classification (process_one_geneset.sh GFF_SOURCE), not detected
# here. The FASTAs are the DELIVERED ones from the datastore.
#
# Output, tab-separated, with a header:
#   moop_id  type  parent_moop_id  original_gff_id  original_fasta_id
#
# STEP 1 OF THE PLAN: the rules below reproduce today's database IDs (emit_refseq, emit_ensembl,
# emit_generic + strip_id_prefix.pl, add_id_versions.pl, rename_generic_fasta.pl) except where the
# plan lists a fix:
#   - RefSeq: an mRNA is "ID minus rna-", so NCBI's second placement of a gene (Amphimedon Gro,
#     gene-Gro-2 / rna-NM_001279307.1-2) is a gene and mRNA of its own, and an mRNA with no
#     transcript_id (mitochondrial genes) is still an mRNA;
#   - Ensembl: a CDS under any transcript-level parent counts (Danio's immune gene segments), not
#     only under "mRNA".
# A protein_id used by two CDSs belongs to the first. A CDS with no protein_id is not a feature
# (V/C gene segments, pseudogene CDS) for RefSeq/Ensembl. For other/lift every gene/mRNA/transcript
# line is a feature, coding or not -- that is what emit_generic loads today.
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;
use Getopt::Long;

## GFF attributes strip_id_prefix.pl rewrites (and so this does, for other/lift)
my %PREFIXED_ATTRIBUTES = (ID => 1, Parent => 1, Name => 1, namesrc => 1);
## transcript-level types for other/lift (emit_generic)
my %GENERIC_TRANSCRIPT = (mRNA => 1, transcript => 1);
## gene-level types a RefSeq CDS may hang under directly (prokaryotes: CDS under gene)
my %GENE_LEVEL = (gene => 1, pseudogene => 1);

sub main {
  my %opt;
  GetOptions(\%opt, 'gff=s', 'source=s', 'out=s', 'protein=s', 'cds=s', 'transcript=s',
             'strip=s', 'add=s', 'lift-prefix=s')
    or die usage();
  die usage() unless $opt{gff} && $opt{source} && $opt{out};
  my $source = $opt{source};
  die "--source must be refseq, ensembl, other or lift\n" unless $source =~ /^(?:refseq|ensembl|other|lift)$/;

  my $gff = read_gff($opt{gff});
  my @rows;
  my %stats;
  if ($source eq 'refseq') {
    @rows = refseq_rows($gff, \%opt, \%stats);
  } elsif ($source eq 'ensembl') {
    @rows = ensembl_rows($gff, \%opt, \%stats);
  } else {
    @rows = generic_rows($gff, \%opt, $source, \%stats);
  }

  ## no ID may be used by two features
  my %seen;
  my @duplicates;
  foreach my $row (@rows) {
    push @duplicates, $row->[0] if $seen{ $row->[0] }++;
  }
  die "build_id_map.pl: " . scalar(@duplicates) . " MOOP ID(s) used twice, e.g. "
    . join(', ', @duplicates[0 .. ($#duplicates < 4 ? $#duplicates : 4)]) . "\n" if @duplicates;

  open my $out, '>', "$opt{out}.tmp" or die "Cannot write $opt{out}.tmp: $!\n";
  print $out join("\t", qw(moop_id type parent_moop_id original_gff_id original_fasta_id)), "\n";
  foreach my $row (@rows) {
    print $out join("\t", map_cells($row)), "\n";
  }
  close $out or die "Cannot write $opt{out}.tmp: $!\n";
  rename "$opt{out}.tmp", $opt{out} or die "Cannot rename $opt{out}.tmp: $!\n";

  my %by_type;
  foreach my $row (@rows) {
    $by_type{ $row->[1] }++;
  }
  my @parts;
  foreach my $type (sort keys %by_type) {
    push @parts, "$type $by_type{$type}";
  }
  foreach my $key (sort keys %stats) {
    push @parts, "$key $stats{$key}";
  }
  print STDERR "build_id_map.pl ($source): ", join(', ', @parts), "\n";
}

sub usage {
  return "Usage: $0 --gff genes.gff --source refseq|ensembl|other|lift --out id_map.tsv "
       . "[--protein fa] [--cds fa] [--transcript fa] [--strip p [--add p]] [--lift-prefix p]\n";
}

## undef cells print as ''
sub map_cells {
  my ($row) = @_;
  my @cells;
  foreach my $index (0 .. 4) {
    push @cells, $row->[$index] // '';
  }
  return @cells;
}

## Every feature line: type, attributes (first value of each key), in file order. Lines sharing
## an ID (a spliced CDS) are kept as one feature, first line wins.
sub read_gff {
  my ($file) = @_;
  open my $fh, '<', $file or die "Cannot read $file: $!\n";
  my (@order, %feature, @cds_lines);
  while (my $line = <$fh>) {
    next if $line =~ /^#/;
    $line =~ s/\r?\n$//;
    my @f = split /\t/, $line;
    next unless @f >= 9;
    my %attr;
    foreach my $pair (split /;/, $f[8]) {
      my $eq = index($pair, '=');
      next if $eq < 1;
      my $key = substr($pair, 0, $eq);
      $attr{$key} //= substr($pair, $eq + 1);
    }
    my $record = { type => $f[2], attr => \%attr };
    push @cds_lines, $record if $f[2] eq 'CDS';
    my $id = $attr{ID};
    next unless defined $id;
    next if exists $feature{$id};
    $feature{$id} = $record;
    push @order, $id;
  }
  close $fh;
  return { order => \@order, feature => \%feature, cds_lines => \@cds_lines };
}

## FASTA header IDs, in file order
sub fasta_ids {
  my ($file) = @_;
  return () unless $file && -s $file;
  open my $fh, '<', $file or die "Cannot read $file: $!\n";
  my @ids;
  while (my $line = <$fh>) {
    push @ids, $1 if $line =~ /^>(\S+)/;
  }
  close $fh;
  return @ids;
}

## The coding CDSs: one per CDS ID (or per protein_id when the lines carry no ID), first line wins.
sub coding_cds {
  my ($gff) = @_;
  my (%seen, @cds);
  foreach my $line (@{ $gff->{cds_lines} }) {
    my $protein = $line->{attr}{protein_id};
    next unless defined $protein && $protein ne '';
    my $key = $line->{attr}{ID} // "protein:$protein";
    next if $seen{$key}++;
    push @cds, $line;
  }
  return @cds;
}

# --- RefSeq ---------------------------------------------------------------------------------
sub refseq_rows {
  my ($gff, $opt, $stats) = @_;
  my $feature = $gff->{feature};
  my @cds = coding_cds($gff);

  ## which genes carry a coding CDS, and through which transcript
  my (@genes, %gene_seen, %transcripts_of);
  my @placed;
  foreach my $line (@cds) {
    my $parent = $line->{attr}{Parent} // '';
    my $parent_type = $feature->{$parent} ? $feature->{$parent}{type} : '';
    my ($gene, $tx);
    if ($GENE_LEVEL{$parent_type}) {
      $gene = $parent;
    } elsif ($parent_type ne '') {
      $tx = $parent;
      $gene = $feature->{$tx}{attr}{Parent} // '';
      $stats->{'cds_under_' . $parent_type}++ if $parent_type ne 'mRNA';
    }
    if (!defined $gene || !$feature->{$gene}) {
      $stats->{cds_without_gene}++;
      next;
    }
    push @genes, $gene unless $gene_seen{$gene}++;
    push @placed, [$line, $gene, $tx];
  }

  ## gene IDs: the GeneID; a GeneID shared by several coding genes keeps NCBI's "-N" (gene-Gro-2)
  my (%geneid_of, %genes_with);
  foreach my $gene (@genes) {
    my ($geneid) = ($feature->{$gene}{attr}{Dbxref} // '') =~ /GeneID:([^,;]+)/;
    if (!defined $geneid) {
      $stats->{gene_without_geneid}++;
      $geneid = $gene;
    }
    $geneid_of{$gene} = $geneid;
    push @{ $genes_with{$geneid} }, $gene;
  }
  my %moop_gene;
  foreach my $geneid (keys %genes_with) {
    my @sharing = @{ $genes_with{$geneid} };
    if (@sharing == 1) {
      $moop_gene{ $sharing[0] } = $geneid;
      next;
    }
    $stats->{shared_geneid}++;
    my $ordinal = 1;
    foreach my $gene (@sharing) {
      my ($suffix) = $gene =~ /(-\d+)$/;
      my $first = $sharing[0];
      if ($gene ne $first && defined $suffix && $gene eq "$first$suffix") {
        $moop_gene{$gene} = "$geneid$suffix";
      } elsif ($gene eq $first) {
        $moop_gene{$gene} = $geneid;
      } else {
        $ordinal++;
        $moop_gene{$gene} = "$geneid-$ordinal";
        $stats->{shared_geneid_by_ordinal}++;
      }
    }
  }

  my %transcript_fasta = map_lookup(fasta_ids($opt->{transcript}));
  my %protein_fasta    = map_lookup(fasta_ids($opt->{protein}));
  my %cds_fasta        = refseq_cds_fasta($opt->{cds});

  my (@rows, %row_done, %protein_owner);
  foreach my $gene (@genes) {
    push @rows, [$moop_gene{$gene}, 'gene', '', $gene, ''];
  }
  foreach my $placement (@placed) {
    my ($line, $gene, $tx) = @$placement;
    my $parent_id = $moop_gene{$gene};
    my $parent_type_row = 'gene';
    if (defined $tx) {
      (my $tx_moop = $tx) =~ s/^rna-//;
      unless ($row_done{"tx $tx"}++) {
        my $tid = $feature->{$tx}{attr}{transcript_id};
        my $fasta = defined $tid && $transcript_fasta{$tid} ? $tid : '';
        push @rows, [$tx_moop, 'mRNA', $moop_gene{$gene}, $tx, $fasta];
      }
      $parent_id = $tx_moop;
    }
    my $cds_id  = $line->{attr}{ID};
    my $protein = $line->{attr}{protein_id};
    unless (defined $cds_id) {
      $stats->{cds_without_id}++;
      next;
    }
    next if $row_done{"cds $cds_id"}++;
    push @rows, [$cds_id, 'cds', $parent_id, $cds_id, $cds_fasta{$protein} // ''];
    if (exists $protein_owner{$protein}) {
      $stats->{protein_shared_by_cds}++;
      next;
    }
    $protein_owner{$protein} = $cds_id;
    push @rows, [$protein, 'protein', $cds_id, '', $protein_fasta{$protein} ? $protein : ''];
  }
  return @rows;
}

## RefSeq cds.nt.fa headers ("lcl|NC_064034.1_cds_XP_048580524.1_1 ... [protein_id=XP_...]")
## by protein_id; a protein_id with two records keeps the first (rename_RefSeq_cds_fasta.pl
## separates those -- step 2 checks the FASTA side).
sub refseq_cds_fasta {
  my ($file) = @_;
  my %by_protein;
  return %by_protein unless $file && -s $file;
  open my $fh, '<', $file or die "Cannot read $file: $!\n";
  while (my $line = <$fh>) {
    next unless $line =~ /^>(\S+)/;
    my $header_id = $1;
    my ($protein) = $line =~ /\[protein_id=([^\]]+)\]/;
    $by_protein{$protein} //= $header_id if defined $protein;
  }
  close $fh;
  return %by_protein;
}

sub map_lookup {
  my (@ids) = @_;
  my %lookup;
  foreach my $id (@ids) {
    $lookup{$id} = 1;
  }
  return %lookup;
}

# --- Ensembl --------------------------------------------------------------------------------
sub ensembl_rows {
  my ($gff, $opt, $stats) = @_;
  my $feature = $gff->{feature};
  my @protein_ids = fasta_ids($opt->{protein});

  ## versioned when every protein FASTA id is a GFF protein_id.version (add_id_versions.pl's test)
  my %protein_version;
  foreach my $line (@{ $gff->{cds_lines} }) {
    my ($protein, $version) = ($line->{attr}{protein_id}, $line->{attr}{version});
    $protein_version{$protein} //= $version if defined $protein && defined $version;
  }
  my ($n_versioned, $n_fasta) = (0, scalar @protein_ids);
  foreach my $fasta_id (@protein_ids) {
    my ($bare, $version) = $fasta_id =~ /^(.+)\.([^.]+)$/;
    $n_versioned++ if defined $bare && defined $protein_version{$bare} && $protein_version{$bare} eq $version;
  }
  my $versioned = ($n_versioned > 0 && $n_versioned == $n_fasta);
  $stats->{versioned} = $versioned ? 'yes' : 'no';

  my $moop = sub {
    my ($id) = @_;
    (my $core = $id) =~ s/^[A-Za-z_]+://;
    my $version = $feature->{$id} ? $feature->{$id}{attr}{version} : undef;
    return ($versioned && defined $version) ? "$core.$version" : $core;
  };
  my %protein_fasta = map_lookup(@protein_ids);
  my %transcript_fasta = map_lookup(fasta_ids($opt->{transcript}));
  my %cds_fasta = map_lookup(fasta_ids($opt->{cds}));

  my (@rows, %done, %protein_owner);
  foreach my $line (coding_cds($gff)) {
    my $tx = $line->{attr}{Parent} // '';
    my $gene = $feature->{$tx} ? ($feature->{$tx}{attr}{Parent} // '') : '';
    if (!$feature->{$tx} || !$feature->{$gene}) {
      $stats->{cds_without_gene}++;
      next;
    }
    $stats->{'cds_under_' . $feature->{$tx}{type}}++ if $feature->{$tx}{type} ne 'mRNA';
    my ($gene_moop, $tx_moop) = ($moop->($gene), $moop->($tx));
    push @rows, [$gene_moop, 'gene', '', $gene, ''] unless $done{"gene $gene"}++;
    unless ($done{"tx $tx"}++) {
      my $fasta = $transcript_fasta{$tx_moop} ? $tx_moop : '';
      push @rows, [$tx_moop, 'mRNA', $gene_moop, $tx, $fasta];
    }
    my $protein = $line->{attr}{protein_id};
    my $version = $line->{attr}{version};
    my $protein_moop = ($versioned && defined $version) ? "$protein.$version" : $protein;
    next if exists $protein_owner{$protein_moop};
    $protein_owner{$protein_moop} = 1;
    my $cds_moop = "CDS:$protein_moop";
    ## Ensembl cds.nt.fa names the transcript (with or without its version)
    (my $tx_bare = $tx_moop) =~ s/\.[^.]+$//;
    my $cds_fasta = $cds_fasta{$tx_moop} ? $tx_moop : $cds_fasta{$tx_bare} ? $tx_bare : '';
    push @rows, [$cds_moop, 'CDS', $tx_moop, $line->{attr}{ID} // '', $cds_fasta];
    push @rows, [$protein_moop, 'protein', $cds_moop, '', $protein_fasta{$protein_moop} ? $protein_moop : ''];
  }
  return @rows;
}

# --- other / lift (emit_generic + strip_id_prefix.pl + rename_generic_fasta.pl) --------------
sub generic_rows {
  my ($gff, $opt, $source, $stats) = @_;
  my ($strip, $add) = ($opt->{strip} // '', $opt->{add} // '');
  if ($source eq 'lift') {
    die "a lift gene set needs --lift-prefix\n" unless defined $opt->{'lift-prefix'} && $opt->{'lift-prefix'} ne '';
  }
  my $lift = $opt->{'lift-prefix'} // '';
  $lift .= '_' if $lift ne '' && $lift !~ /_$/;
  ## the shell runs the strip/add rewrite, then the lift prefix: same order here
  my $rename = sub {
    my ($value) = @_;
    return $value unless defined $value;
    if ($strip ne '') {
      $value =~ s/^\Q$strip\E/$add/;
    } elsif ($add ne '' && $value !~ /^\Q$add\E/) {
      $value = "$add$value";
    }
    $value = "$lift$value" if $lift ne '' && $value !~ /^\Q$lift\E/;
    return $value;
  };

  my $feature = $gff->{feature};
  ## renamed ID -> its original, and renamed attributes per feature
  my (%type_of, %original, %attr_of, @order);
  foreach my $id (@{ $gff->{order} }) {
    my $new = $rename->($id);
    my %attr = %{ $feature->{$id}{attr} };
    foreach my $key (keys %PREFIXED_ATTRIBUTES) {
      $attr{$key} = $rename->($attr{$key}) if defined $attr{$key};
    }
    $type_of{$new}  = $feature->{$id}{type};
    $original{$new} = $id;
    $attr_of{$new}  = \%attr;
    push @order, $new;
  }
  my (%tx_has_cds, %tx_to_gff_cds);
  foreach my $line (@{ $gff->{cds_lines} }) {
    my $parent = $rename->($line->{attr}{Parent});
    next unless defined $parent;
    $tx_has_cds{$parent} = 1;
    $tx_to_gff_cds{$parent} //= $rename->($line->{attr}{ID}) if defined $line->{attr}{ID};
  }

  ## FASTA ids after the prefix rewrite and rename_generic_fasta.pl's ":cds"/":pep" on a clash
  my %is_transcript;
  foreach my $id (@order) {
    $is_transcript{$id} = 1 if $GENERIC_TRANSCRIPT{ $type_of{$id} };
  }
  my (%cds_ids, %cds_original);
  foreach my $delivered (fasta_ids($opt->{cds})) {
    my $id = $rename->($delivered);
    $id .= ':cds' if $is_transcript{$id};
    $cds_ids{$id} = 1;
    $cds_original{$id} = $delivered;
  }
  my (%prot_ids, %prot_original);
  foreach my $delivered (fasta_ids($opt->{protein})) {
    my $id = $rename->($delivered);
    $id .= ':pep' if $is_transcript{$id} || $cds_ids{$id};
    $prot_ids{$id} = 1;
    $prot_original{$id} = $delivered;
  }
  my %transcript_fasta;
  foreach my $delivered (fasta_ids($opt->{transcript})) {
    $transcript_fasta{ $rename->($delivered) } = $delivered;
  }

  my @rows;
  foreach my $id (@order) {
    my $type = $type_of{$id};
    next unless $type eq 'gene' || $GENERIC_TRANSCRIPT{$type};
    my $attr = $attr_of{$id};
    my $parent = $attr->{Parent};
    if (!defined $parent && $GENERIC_TRANSCRIPT{$type}) {
      my $gene_ref = $rename->($attr->{geneID});
      $parent = $gene_ref if defined $gene_ref && $gene_ref ne $id;
    }
    my $fasta = $GENERIC_TRANSCRIPT{$type} ? ($transcript_fasta{$id} // '') : '';
    push @rows, [$id, $type, $parent // '', $original{$id}, $fasta];
    next unless $GENERIC_TRANSCRIPT{$type};

    my $gff_cds = $tx_to_gff_cds{$id};
    my $has_cds = $tx_has_cds{$id} || defined $gff_cds
      || (%cds_ids  && ($cds_ids{"$id:cds"}  || $cds_ids{$id}))
      || (%prot_ids && ($prot_ids{"$id:pep"} || $prot_ids{$id}));
    next unless $has_cds;
    my $cds_id;
    if    ($cds_ids{"$id:cds"})                     { $cds_id = "$id:cds" }
    elsif (defined $gff_cds && $cds_ids{$gff_cds}) { $cds_id = $gff_cds }
    else                                            { $cds_id = "$id:cds" }
    my $prot_id;
    if    ($prot_ids{"$id:pep"})                          { $prot_id = "$id:pep" }
    elsif ($prot_ids{"$cds_id:pep"})                      { $prot_id = "$cds_id:pep" }
    elsif (defined $gff_cds && $prot_ids{$gff_cds})       { $prot_id = $gff_cds }
    elsif (defined $gff_cds && $prot_ids{"$gff_cds:pep"}) { $prot_id = "$gff_cds:pep" }
    else                                                  { $prot_id = "$id:pep" }
    my $gff_cds_original = defined $gff_cds ? ($original{$gff_cds} // '') : '';
    push @rows, [$cds_id, 'cds', $id, $gff_cds_original, $cds_original{$cds_id} // ''];
    push @rows, [$prot_id, 'protein', $cds_id, '', $prot_original{$prot_id} // ''];
  }
  return @rows;
}

main();
