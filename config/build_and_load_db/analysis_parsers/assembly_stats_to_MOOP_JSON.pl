#!/usr/bin/perl
# Assembly statistics for one genome -> assembly_stats.json, the fixed shape the organism page reads.
#
#   assembly_stats_to_MOOP_JSON.pl <genome.fa> <out.json> [genome.fa.fai]
#
# The contract, with the exact definition of every field, is notes/ASSEMBLY_STATS_SPEC.md.
#
# ONE PER ASSEMBLY, called once per GENE SET: process_one_geneset.sh runs this for every gene set,
# and an assembly can carry several. So the script decides for itself whether there is work to
# do: when <out.json> already exists, records exactly the byte size and mtime genome.fa has now
# (fasta_bytes, fasta_mtime), and was written by this $FORMAT_VERSION, it says so and exits 0
# without reading the genome. The first gene set of an assembly computes the file; the others find
# it current. A different genome.fa or a change to a definition here (bump $FORMAT_VERSION) makes
# the next run recompute it.
#
# Why size + mtime EQUAL, not "the file is newer than the genome": a genome replaced with
# rsync -a / cp -p keeps its ORIGINAL, older mtime, so "newer than" would keep the old genome's
# figures for good, silently (review of 45b1ea5, 2026-10-20). Any replacement changes the size or
# the mtime, older or newer.
#
# The genome is streamed once (genomes reach 3.4 Gb -- never slurped): lengths, N runs, GC and
# the md5 all come from the same pass. Every figure is checked before anything is written, and
# against <genome.fa.fai> when given; the script dies rather than write a file that does not add
# up. The output is written to <out.json>.tmp and renamed, so a failure never leaves a
# half-written file.
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl). Core modules only (cpanfile).
use strict;
use warnings;
use JSON::PP;
use Digest::MD5;
use POSIX qw(strftime);

## bump when a definition in notes/ASSEMBLY_STATS_SPEC.md changes: every file is then recomputed
## 2: fasta_bytes + fasta_mtime added, and currency is decided by them (2026-10-20)
my $FORMAT_VERSION = 2;
## a run of at least this many N splits a scaffold into contigs (QUAST's default)
my $MIN_SPLIT_GAP = 10;

sub main {
  my ($genome, $out_json, $fai) = @ARGV;
  die "Usage: $0 <genome.fa> <out.json> [genome.fa.fai]\n" unless defined $out_json;
  die "$genome: missing or empty\n" unless -s $genome;

  if (is_current($genome, $out_json)) {
    print "Assembly stats: $out_json is current (same size and mtime as $genome, format $FORMAT_VERSION) -- not recomputed\n";
    return;
  }

  ## stat BEFORE reading: if the genome is replaced mid-read, the recorded size/mtime are the old
  ## file's, so the next run sees a mismatch and recomputes
  my ($fasta_bytes, $fasta_mtime) = (stat $genome)[7, 9];
  my $scan = scan_genome($genome);
  my %stats = (
    version      => $FORMAT_VERSION,
    fasta_md5    => $scan->{md5},
    fasta_bytes  => $fasta_bytes,
    fasta_mtime  => $fasta_mtime,
    date         => strftime('%Y-%m-%d', localtime),
    sequences    => scalar @{ $scan->{record_lengths} },
    total_length => $scan->{total_length},
    gap_bases    => $scan->{gap_bases},
  );
  die "$genome: no sequences\n" unless $stats{sequences} >= 1 and $stats{total_length} > 0;

  my $scaffold = length_stats($scan->{record_lengths}, $stats{total_length});
  @stats{qw(n50 l50 n90 l90 longest)} = @{$scaffold}{qw(n50 l50 n90 l90 longest)};
  $stats{gap_pct} = round1(100 * $stats{gap_bases} / $stats{total_length});

  ## contigs: omitted when there are none (a genome of nothing but N)
  my $contig_total = 0;
  foreach my $contig_length (@{ $scan->{contig_lengths} }) {
    $contig_total += $contig_length;
  }
  if (@{ $scan->{contig_lengths} }) {
    my $contig = length_stats($scan->{contig_lengths}, $contig_total);
    $stats{contigs}    = scalar @{ $scan->{contig_lengths} };
    $stats{contig_n50} = $contig->{n50};
    $stats{contig_l50} = $contig->{l50};
  }
  ## GC: omitted when there is no A/C/G/T at all
  $stats{gc_pct} = round1(100 * $scan->{gc_bases} / $scan->{acgt_bases}) if $scan->{acgt_bases};

  check(\%stats, $scaffold, $contig_total, $scan, $genome);
  check_against_fai(\%stats, $fai) if defined $fai and length $fai;

  my $tmp = "$out_json.tmp";
  open my $out, '>', $tmp or die "cant write $tmp $!\n";
  print $out JSON::PP->new->pretty->canonical->encode(\%stats);
  close $out or die "cant write $tmp $!\n";
  rename $tmp, $out_json or die "cant rename $tmp to $out_json $!\n";

  printf "Assembly stats: %s sequences, %s bp, N50 %s (L50 %s)%s -> %s\n",
    $stats{sequences}, $stats{total_length}, $stats{n50}, $stats{l50},
    (defined $stats{contig_n50} ? ", contig N50 $stats{contig_n50}" : ''), $out_json;
}

## <out.json> exists, was written with this $FORMAT_VERSION, and records the size and mtime the
## genome has now (stat follows the genome.fa symlink to the real file).
sub is_current {
  my ($genome, $out_json) = @_;
  return 0 unless -s $out_json;
  my $existing = eval {
    open my $in, '<', $out_json or die;
    local $/;
    my $text = <$in>;
    close $in;
    JSON::PP->new->decode($text);
  };
  return 0 unless ref $existing eq 'HASH';
  return 0 unless defined $existing->{version} and $existing->{version} == $FORMAT_VERSION;
  my ($bytes, $mtime) = (stat $genome)[7, 9];
  return 0 unless defined $existing->{fasta_bytes} and $existing->{fasta_bytes} == $bytes;
  return 0 unless defined $existing->{fasta_mtime} and $existing->{fasta_mtime} == $mtime;
  return 1;
}

## One pass over the FASTA. Contigs: a record is split at every run of >= $MIN_SPLIT_GAP N;
## shorter runs stay inside the contig. N runs at either end of a record are trimmed, whatever
## their length. Bases removed from contigs (split runs + end runs) are counted so the check can
## prove contigs + removed == total.
sub scan_genome {
  my ($genome) = @_;
  my %scan = (
    record_lengths => [], contig_lengths => [],
    total_length => 0, gap_bases => 0, gc_bases => 0, acgt_bases => 0, removed_bases => 0,
  );
  my $md5 = Digest::MD5->new;

  ## per-record state
  my $in_record     = 0;
  my $record_length = 0;
  my $contig_length = 0;   # bases of the open contig, short internal N runs included
  my $pending_n     = 0;   # the N run we are inside, not yet known to be internal or trailing
  my $started       = 0;   # has this record had a non-N base yet?

  my $end_record = sub {
    return unless $in_record;
    push @{ $scan{record_lengths} }, $record_length;
    $scan{total_length} += $record_length;
    push @{ $scan{contig_lengths} }, $contig_length if $started;
    $scan{removed_bases} += $pending_n;   # trailing N run (or a record of nothing but N)
    ($record_length, $contig_length, $pending_n, $started) = (0, 0, 0, 0);
  };

  open my $in, '<', $genome or die "cant open $genome $!\n";
  while (my $line = <$in>) {
    $md5->add($line);
    if (substr($line, 0, 1) eq '>') {
      $end_record->();
      $in_record = 1;
      next;
    }
    $line =~ s/[\r\n]+\z//;
    next if $line eq '';
    die "$genome: sequence before the first '>' header\n" unless $in_record;

    $record_length     += length $line;
    $scan{gap_bases}   += ($line =~ tr/Nn//);
    $scan{gc_bases}    += ($line =~ tr/GCgc//);
    $scan{acgt_bases}  += ($line =~ tr/ACGTacgt//);

    while ($line =~ /([Nn]+|[^Nn]+)/g) {
      my $segment = $1;
      my $length  = length $segment;
      if ($segment =~ /^[Nn]/) {
        $pending_n += $length;
        next;
      }
      if ($pending_n) {
        if (!$started) {
          $scan{removed_bases} += $pending_n;                      # leading run: trimmed
        } elsif ($pending_n >= $MIN_SPLIT_GAP) {
          push @{ $scan{contig_lengths} }, $contig_length;         # internal long run: split
          $contig_length = 0;
          $scan{removed_bases} += $pending_n;
        } else {
          $contig_length += $pending_n;                            # internal short run: kept
        }
        $pending_n = 0;
      }
      $contig_length += $length;
      $started = 1;
    }
  }
  close $in;
  $end_record->();

  $scan{md5} = $md5->hexdigest;
  return \%scan;
}

## N50/L50/N90/L90 and longest of a list of lengths, against <total>: sort longest first; Nx is
## the length at which the running sum first reaches >= x% of the total, Lx how many it took.
sub length_stats {
  my ($lengths, $total) = @_;
  my @sorted = sort { $b <=> $a } @$lengths;
  my %stats = (longest => $sorted[0]);
  my $running = 0;
  my $count = 0;
  foreach my $length (@sorted) {
    $running += $length;
    $count++;
    if (!defined $stats{n50} and $running * 2 >= $total) {
      @stats{qw(n50 l50)} = ($length, $count);
    }
    if (!defined $stats{n90} and $running * 10 >= $total * 9) {
      @stats{qw(n90 l90)} = ($length, $count);
      last;
    }
  }
  return \%stats;
}

sub check {
  my ($stats, $scaffold, $contig_total, $scan, $genome) = @_;
  my @problems;
  push @problems, "l50 $stats->{l50} > l90 $stats->{l90}"                 if $stats->{l50} > $stats->{l90};
  push @problems, "l90 $stats->{l90} > sequences $stats->{sequences}"     if $stats->{l90} > $stats->{sequences};
  push @problems, "n50 $stats->{n50} < n90 $stats->{n90}"                 if $stats->{n50} < $stats->{n90};
  push @problems, "gap_bases $stats->{gap_bases} > total_length"          if $stats->{gap_bases} > $stats->{total_length};
  push @problems, "contig length $contig_total + removed N $scan->{removed_bases} != total_length $stats->{total_length}"
    unless $contig_total + $scan->{removed_bases} == $stats->{total_length};
  push @problems, "contig_l50 $stats->{contig_l50} > contigs $stats->{contigs}"
    if defined $stats->{contigs} and $stats->{contig_l50} > $stats->{contigs};
  die "$genome: assembly stats do not add up -- not written:\n  " . join("\n  ", @problems) . "\n" if @problems;
}

## the same figures from the .fai, computed independently: catches reading the wrong file
sub check_against_fai {
  my ($stats, $fai) = @_;
  return unless -s $fai;
  my @lengths;
  open my $in, '<', $fai or die "cant open $fai $!\n";
  while (my $line = <$in>) {
    my (undef, $length) = split /\t/, $line, 3;
    push @lengths, $length if defined $length and $length =~ /^\d+$/;
  }
  close $in;
  my $total = 0;
  foreach my $length (@lengths) {
    $total += $length;
  }
  my $from_fai = length_stats(\@lengths, $total);
  my %expected = (sequences => scalar @lengths, total_length => $total, n50 => $from_fai->{n50}, l50 => $from_fai->{l50});
  my @differ;
  foreach my $field (sort keys %expected) {
    push @differ, "$field: FASTA $stats->{$field}, .fai $expected{$field}" unless $stats->{$field} == $expected{$field};
  }
  die "assembly stats disagree with $fai -- not written (stale .fai, or the wrong file?):\n  "
    . join("\n  ", @differ) . "\n" if @differ;
}

sub round1 {
  my ($value) = @_;
  return 0 + sprintf('%.1f', $value);
}

main();
