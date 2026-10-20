#!/usr/bin/perl
# BUSCO (protein mode) for one gene set -> busco.json, the fixed shape the gene set page reads.
#
#   parse_BUSCO_to_MOOP_JSON.pl <busco_dir> <protein.aa.fa> <out.json>
#
# The contract is notes/BUSCO_GENE_SET_SPEC.md. MOOP never parses BUSCO's own files: their wording
# and JSON keys change between BUSCO versions, so this script reads the short_summary*.txt BUSCO
# wrote (every version has one) and emits one shape. A BUSCO upgrade changes this script, not MOOP.
#
# Input layout (annotation pipeline, BUSCO 5.4.4, checked 2026-10-20 on annotations/sbgenomes_2):
#   <busco_dir>/<x>_auto/short_summary.generic.eukaryota_odb10.<...>.txt    broad lineage
#   <busco_dir>/<x>_auto/short_summary.specific.<lineage>.<...>.txt         lineage BUSCO picked
#   <busco_dir>/<x>_met/short_summary.specific.metazoa_odb10.<...>.txt      fixed lineage (-l metazoa_odb10)
#   <busco_dir>/<x>_*/busco_job_cmd.txt                                     IN_FASTA=<the FASTA it ran on>
# Summaries directly in <busco_dir> are read too. run_*/ subdirectories are not.
#
# One entry in "runs" per lineage, most specific first (auto-selected, fixed, generic). When the
# auto run picked the same lineage as the fixed run (metazoa for most invertebrates), the two are
# the same result: the auto-selected one is kept (user, 2026-10-20). If they ever disagree both are
# kept and a warning says so.
#
# Every run is checked before anything is written -- counts add up to n, complete = single +
# duplicated, the percentages add up to 100 -- and the script dies rather than write a file that
# does not add up. The output is written to <out.json>.tmp and renamed, so a failure never leaves
# a half-written busco.json.
#
# Warnings (stderr, and in "warnings" in the file so the page can say so):
#   - BUSCO ran on a different FASTA than this gene set's protein.aa.fa (busco_job_cmd.txt)
#   - protein.aa.fa is newer than the BUSCO results: the gene models changed after BUSCO ran
# protein_fasta_md5 records the proteins the file was made against.
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;
use JSON::PP;
use Digest::MD5;
use File::Basename qw(basename dirname);
use Cwd qw(abs_path);
use POSIX qw(strftime);

## role -> display order (most specific first)
my %ROLE_ORDER = ('auto-selected' => 0, 'fixed' => 1, 'generic' => 2);
## rounding slack for the percentage checks: BUSCO rounds each value to one decimal
my $PERCENT_SLACK = 0.25;

sub main {
  my ($busco_dir, $protein_fasta, $out_json) = @ARGV;
  die "Usage: $0 <busco_dir> <protein.aa.fa> <out.json>\n"
    unless defined $out_json;
  die "$busco_dir: not a directory\n" unless -d $busco_dir;
  die "$protein_fasta: missing or empty\n" unless -s $protein_fasta;

  my @summaries = sort(glob("$busco_dir/short_summary.*.txt"), glob("$busco_dir/*/short_summary.*.txt"));
  die "$busco_dir: no short_summary.*.txt\n" unless @summaries;

  my @warnings;
  my $protein_mtime = (stat $protein_fasta)[9];
  my $protein_path  = abs_path($protein_fasta);
  my %checked_fasta;
  my $oldest_summary;

  my @runs;
  foreach my $summary (@summaries) {
    my $run = parse_summary($summary);
    $run->{role} = role_of($summary, $run->{lineage});
    push @runs, $run;

    ## which FASTA did this BUSCO run use? (once per run directory)
    my $run_dir = dirname($summary);
    if (!$checked_fasta{$run_dir}++) {
      my $used = fasta_from_job_cmd("$run_dir/busco_job_cmd.txt");
      if (defined $used) {
        my $used_path = abs_path($used) // $used;
        if ($used_path ne $protein_path) {
          push @warnings, "BUSCO ran on $used, not this gene set's $protein_fasta";
        }
      }
    }
    $oldest_summary = (stat $summary)[9]
      if !defined $oldest_summary || (stat $summary)[9] < $oldest_summary;
  }
  if ($oldest_summary < $protein_mtime) {
    push @warnings, sprintf('protein.aa.fa (%s) is newer than the BUSCO results (%s): the gene models changed after BUSCO ran',
                            ymd($protein_mtime), ymd($oldest_summary));
  }

  @runs = drop_duplicate_lineages(\@runs, \@warnings);
  @runs = sort { $ROLE_ORDER{$a->{role}} <=> $ROLE_ORDER{$b->{role}} or $b->{n} <=> $a->{n} } @runs;

  my (%seen_warning, @unique_warnings);
  foreach my $warning (@warnings) {
    next if $seen_warning{$warning}++;
    push @unique_warnings, $warning;
    print STDERR "WARNING: BUSCO: $warning\n";
  }

  my $doc = {
    protein_fasta_md5 => md5_of($protein_fasta),
    runs              => \@runs,
    warnings          => \@unique_warnings,
  };
  my $tmp = "$out_json.tmp";
  open my $out, '>', $tmp or die "cant write $tmp $!\n";
  print $out JSON::PP->new->pretty->canonical->encode($doc);
  close $out or die "cant write $tmp $!\n";
  rename $tmp, $out_json or die "cant rename $tmp to $out_json $!\n";

  my @described;
  foreach my $run (@runs) {
    push @described, sprintf('%s %s C:%.1f%%', $run->{role}, $run->{lineage}, $run->{complete});
  }
  printf "BUSCO: %d lineage(s) -> %s (%s)\n", scalar @runs, $out_json, join(', ', @described);
}

## One short_summary*.txt -> a run. Dies if anything is missing or the numbers do not add up.
sub parse_summary {
  my ($file) = @_;
  open my $in, '<', $file or die "cant open $file $!\n";
  my %run;
  my %count;
  while (my $line = <$in>) {
    if ($line =~ /^# BUSCO version is:\s*(\S+)/) {
      $run{version} = $1;
    } elsif ($line =~ /^# The lineage dataset is:\s*(\S+)(.*)/) {
      $run{lineage} = $1;
      my $rest = $2;
      $run{dataset_date} = $1 if $rest =~ /Creation date:\s*([0-9-]+)/;
    } elsif ($line =~ /^# BUSCO was run in mode:\s*(\S+)/) {
      $run{mode} = $1;
    } elsif ($line =~ /C:([\d.]+)%\[S:([\d.]+)%,D:([\d.]+)%\],F:([\d.]+)%,M:([\d.]+)%.*?n:(\d+)/) {
      @run{qw(complete single duplicated fragmented missing n)} = ($1, $2, $3, $4, $5, $6);
    } elsif ($line =~ /^\s*(\d+)\s+(.*?)\s*$/) {
      my ($number, $label) = ($1, $2);
      if    ($label =~ /^Complete and single-copy BUSCOs/) { $count{single}     = $number }
      elsif ($label =~ /^Complete and duplicated BUSCOs/)  { $count{duplicated} = $number }
      elsif ($label =~ /^Complete BUSCOs/)                 { $count{complete}   = $number }
      elsif ($label =~ /^Fragmented BUSCOs/)               { $count{fragmented} = $number }
      elsif ($label =~ /^Missing BUSCOs/)                  { $count{missing}    = $number }
    }
  }
  close $in;

  foreach my $field (qw(version lineage mode complete single duplicated fragmented missing n)) {
    die "$file: no $field found -- has BUSCO's summary format changed?\n" unless defined $run{$field};
  }
  foreach my $field (qw(complete single duplicated fragmented missing)) {
    die "$file: no $field count found -- has BUSCO's summary format changed?\n" unless defined $count{$field};
    $run{"${field}_n"} = $count{$field} + 0;
    $run{$field} += 0;
  }
  $run{n} += 0;

  ## the checks: fail rather than write numbers that do not add up
  my $n = $run{n};
  die "$file: complete ($count{complete}) != single ($count{single}) + duplicated ($count{duplicated})\n"
    unless $count{complete} == $count{single} + $count{duplicated};
  die "$file: complete + fragmented + missing ($count{complete} + $count{fragmented} + $count{missing}) != n ($n)\n"
    unless $count{complete} + $count{fragmented} + $count{missing} == $n;
  die "$file: complete % ($run{complete}) != single % + duplicated % ($run{single} + $run{duplicated})\n"
    if abs($run{complete} - $run{single} - $run{duplicated}) > $PERCENT_SLACK;
  die "$file: complete + fragmented + missing % does not add up to 100\n"
    if abs($run{complete} + $run{fragmented} + $run{missing} - 100) > $PERCENT_SLACK;

  $run{date} = ymd((stat $file)[9]);   # BUSCO does not record when it ran; the summary's own date
  return \%run;
}

## auto run: "generic" summary = the broad lineage, "specific" = the one BUSCO picked. A placement that
## got no further than eukaryota writes it as "specific", but it is the broad lineage all the same.
## Anything else was run with a fixed lineage (-l).
sub role_of {
  my ($file, $lineage) = @_;
  my $run_dir = basename(dirname($file));
  my $job_cmd = dirname($file) . '/busco_job_cmd.txt';
  my $auto = $run_dir =~ /_auto$/ || (-s $job_cmd && slurp($job_cmd) =~ /--auto-lineage/);
  return 'fixed'   unless $auto;
  return 'generic' if basename($file) =~ /^short_summary\.generic\./ || $lineage =~ /^eukaryota_/;
  return 'auto-selected';
}

## Same lineage twice (auto picked metazoa, and metazoa was also run fixed): keep one.
sub drop_duplicate_lineages {
  my ($runs, $warnings) = @_;
  my %by_lineage;
  foreach my $run (@$runs) {
    push @{ $by_lineage{$run->{lineage}} }, $run;
  }
  my @kept;
  foreach my $lineage (sort keys %by_lineage) {
    my @same = sort { $ROLE_ORDER{$a->{role}} <=> $ROLE_ORDER{$b->{role}} } @{ $by_lineage{$lineage} };
    my $first = $same[0];
    my $differ = 0;
    foreach my $other (@same[1 .. $#same]) {
      $differ++ unless same_result($first, $other);
    }
    if ($differ) {
      push @$warnings, "$lineage was run more than once with different results; all are kept";
      push @kept, @same;
    } else {
      push @kept, $first;
    }
  }
  return @kept;
}

sub same_result {
  my ($one, $two) = @_;
  foreach my $field (qw(n complete_n single_n duplicated_n fragmented_n missing_n)) {
    return 0 unless $one->{$field} == $two->{$field};
  }
  return 1;
}

## busco_job_cmd.txt: "IN_FASTA=<path>", or failing that the "busco -i <path>" line
sub fasta_from_job_cmd {
  my ($file) = @_;
  return undef unless -s $file;
  my $text = slurp($file);
  return $1 if $text =~ /^IN_FASTA=(\S+)/m;
  return $1 if $text =~ /\bbusco\b.*?\s-i\s+(\S+)/;
  return undef;
}

sub md5_of {
  my ($file) = @_;
  open my $in, '<', $file or die "cant open $file $!\n";
  binmode $in;
  my $md5 = Digest::MD5->new->addfile($in)->hexdigest;
  close $in;
  return $md5;
}

sub slurp {
  my ($file) = @_;
  open my $in, '<', $file or die "cant open $file $!\n";
  local $/;
  my $text = <$in>;
  close $in;
  return $text;
}

sub ymd {
  my ($epoch) = @_;
  return strftime('%Y-%m-%d', localtime $epoch);
}

main();
