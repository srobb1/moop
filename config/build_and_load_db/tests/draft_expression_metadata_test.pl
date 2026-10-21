#!/usr/bin/perl
use strict;
use warnings;
use File::Temp qw(tempdir);
use FindBin;
use JSON::PP;

# draft_expression_metadata_test.pl [path/to/draft_expression_metadata.pl]
#
# Hermetic test of the expression metadata drafter: a made-up STAR folder, core report, track
# sheet, master description and ENA report in a temp dir; asserts who each sample is, where its
# descriptive fields come from, the display order and what review.tsv flags. Exit 0 = all pass.
#
# Why it exists: on 2026-10-21, 22 of 24 Nvec samples in two projects carried another sample's
# label because labels followed a list's order instead of the reads. The fixture reproduces that
# shift (track 1 holds ctrl-2's reads but the sheet calls it ctrl-1) and pins the fix.

my $script = shift // "$FindBin::Bin/../analysis_parsers/draft_expression_metadata.pl";
my ($passed, $failed) = (0, 0);
sub check {
  my ($ok, $what, $detail) = @_;
  if ($ok) { $passed++; }
  else { $failed++; print "FAIL: $what\n" . (defined $detail ? "      got: $detail\n" : ''); }
}

my $dir = tempdir(CLEANUP => 1);
sub write_file {
  my ($path, $text) = @_;
  (my $parent = $path) =~ s{/[^/]+$}{};
  system('mkdir', '-p', $parent) == 0 or die "mkdir $parent\n";
  open my $fh, '>', $path or die "cant write $path: $!\n";
  print $fh $text;
  close $fh;
}
sub read_file {
  my ($path) = @_;
  open my $fh, '<', $path or return '';
  local $/;
  my $text = <$fh>;
  close $fh;
  return $text;
}
sub read_tsv {
  my ($path) = @_;
  my @lines = split /\n/, read_file($path);
  my @columns = split /\t/, shift(@lines) // '', -1;
  my @rows;
  foreach my $line (@lines) {
    my @cells = split /\t/, $line, -1;
    my %row;
    foreach my $index (0 .. $#columns) {
      $row{ $columns[$index] } = $cells[$index] // '';
    }
    push @rows, \%row;
  }
  return @rows;
}
sub sample_ids {
  my (@rows) = @_;
  my @ids;
  foreach my $row (@rows) {
    push @ids, $row->{sample_id};
  }
  return "@ids";
}
sub star_log {
  my ($n, @files) = @_;
  write_file("$dir/alignments/$n/$n.Log.out",
    "STAR version=2.7.3a\nSTAR --readFilesIn " . join(',', @files) . " --genomeDir g\n"
    . 'readFilesIn                   ' . join(',', @files) . "        ~RE-DEFINED\n");
}
sub run_drafter {
  my (@args) = @_;
  my $command = join(' ', 'perl', $script, @args, '2>&1');
  my $output = `$command`;
  check($? == 0, "drafter runs: @args", $output);
  return $output;
}

# ---- in-house project PROJX: reads in alphabetical index order, the sheet labels shifted
my $raw = "$dir/raw/PROJX/FLOWCELL";
star_log(1, "$raw/n_1_AAA.fastq.gz");                      # ctrl-2's reads, sheet says ctrl-1
star_log(2, "$raw/n_1_CCC.fastq.gz");                      # kd-1, labelled right
star_log(3, "$raw/n_1_GGG.fastq.gz");                      # ctrl-1's reads, sheet says ctrl-2
star_log(5, "$raw/n_1_TTT.fastq.gz");                      # a ChIP input: not in the bundle
star_log(7, "$dir/raw/OTHER/FC/n_1_AAA.fastq.gz");          # another project
mkdir "$dir/alignments/29_counts";                          # a rerun folder: not a sample
write_file("$raw/Sample_Report.csv",
  "output,order,sample name,illumina index\n"
  . "n_1_GGG.fastq.gz,PROJX,ctrl-1,GGG\nn_1_AAA.fastq.gz,PROJX,ctrl-2,AAA\n"
  . "n_1_CCC.fastq.gz,PROJX,kd-1,CCC\nn_1_TTT.fastq.gz,PROJX,input-1,TTT\n");
my @sheet_columns = qw(TRACK_ID NAME technique developmental-stage tissue condition accession ACCESS NGS_file source);
my @sheet_rows = (
  [qw(PROJX.1.pos.bw ctrl-1.pos RNASeq), 'planula', '', 'control-A', '', 'PUBLIC', 'n_1_GGG.fastq.gz', 'Lab X'],
  [qw(PROJX.1.neg.bw ctrl-1.neg RNASeq), 'planula', '', 'control-A', '', 'PUBLIC', 'n_1_GGG.fastq.gz', 'Lab X'],
  [qw(PROJX.2.pos.bw kd-1.pos RNASeq),   'planula', '', 'knockdown', '', 'PUBLIC', 'n_1_CCC.fastq.gz', 'Lab X'],
  [qw(PROJX.3.pos.bw ctrl-2.pos RNASeq), 'planula', '', 'control-B', '', 'PUBLIC', 'n_1_AAA.fastq.gz', 'Lab X'],
  [qw(PROJX.5.bw input-1 ChIPSeq),       'planula', '', 'input',     '', 'COLLABORATOR', '', 'Lab X'],
);
my $sheet = join("\t", @sheet_columns) . "\r\n";
foreach my $row (@sheet_rows) {
  $sheet .= join("\t", @$row) . "\r\n";
}
$sheet .= "# combo block\tignored\r\n##### Combo Tracks: Updated 2026\r\nPROJX.9.bw\tafter the end\r\n";
write_file("$dir/sheet.tsv", $sheet);
write_file("$dir/master.txt",
  "## Project: OTHER\n## Summary: not this one\n\n"
  . "## Project: PROJX\n## Source: Lab X\n## Summary: knockdown in planula\n## Citaion: PMID:123\n"
  . "## Experiment: A knockdown experiment\n$raw/n_1_AAA.fastq.gz\n");

run_drafter('--project PROJX', "--alignments $dir/alignments", "--sheet $dir/sheet.tsv",
            "--master $dir/master.txt", "--out $dir/out_x");
my @samples = read_tsv("$dir/out_x/samples.tsv");
my $ids = sample_ids(@samples);
check($ids eq 'PROJX.3 PROJX.1 PROJX.2', 'RNA samples only, ctrl group first (its describing row comes first), replicates in order', $ids);
my %by_id;
foreach my $sample (@samples) {
  $by_id{ $sample->{sample_id} } = $sample;
}
check(($by_id{'PROJX.1'}{name} // '') eq 'ctrl-2', 'sample 1 is named from its reads (core report), not the sheet', $by_id{'PROJX.1'}{name});
check(($by_id{'PROJX.1'}{name_source} // '') eq 'core_report', 'name source recorded');
check(($by_id{'PROJX.1'}{group} // '') eq 'ctrl' && ($by_id{'PROJX.1'}{replicate} // '') eq '2', 'ctrl-2 -> group ctrl, replicate 2');
check(($by_id{'PROJX.1'}{condition} // '') eq 'control-B', "sample 1's condition comes from the sheet row named ctrl-2", $by_id{'PROJX.1'}{condition});
check(($by_id{'PROJX.3'}{condition} // '') eq 'control-A', "sample 3's condition comes from the sheet row named ctrl-1", $by_id{'PROJX.3'}{condition});
check(($by_id{'PROJX.1'}{track_keys} // '') eq 'PROJX.1.pos.bw;PROJX.1.neg.bw', 'track files stay with the sample number', $by_id{'PROJX.1'}{track_keys});
check(($by_id{'PROJX.1'}{sheet_name} // '') eq 'ctrl-1', 'the sheet name is kept for comparison');

my @review = read_tsv("$dir/out_x/review.tsv");
my %checks;
foreach my $entry (@review) {
  $checks{"$entry->{sample_id} $entry->{check}"} = 1;
}
check($checks{'PROJX.1 sheet_name_differs'} && $checks{'PROJX.3 sheet_name_differs'}, 'both shifted samples are flagged');
check(!$checks{'PROJX.2 sheet_name_differs'}, 'the right label is not flagged');
check($checks{'PROJX.1 sheet_fastq_differs'}, "the sheet's FASTQ for track 1 is not what STAR aligned");
check($checks{'PROJX.5 skipped_not_rnaseq'}, 'the ChIP library is skipped and listed');
check(sample_ids(@review) !~ /PROJX\.9\b/ && !$by_id{'PROJX.9'}, 'rows after the Combo Tracks header are ignored');

my $experiment = decode_json(read_file("$dir/out_x/experiment.json"));
check($experiment->{label} eq 'Knockdown in planula (Lab X)', 'label from the master summary and lab', $experiment->{label});
check($experiment->{citation} eq 'PMID:123', 'a misspelt master key (Citaion) is still read', $experiment->{citation});
check($experiment->{access_level} eq 'PUBLIC', 'access level: the RNA samples agree on PUBLIC', $experiment->{access_level});
check(read_file("$dir/out_x/experiment.json") =~ /^\{\n  "label"/, 'experiment.json keys in the spec order');

## a person's edits are never overwritten
write_file("$dir/out_x/samples.tsv", "edited by hand\n");
my $output = run_drafter('--project PROJX', "--alignments $dir/alignments", "--sheet $dir/sheet.tsv", "--out $dir/out_x");
check(read_file("$dir/out_x/samples.tsv") eq "edited by hand\n", 'an existing samples.tsv is left alone');
check(scalar($output =~ /nothing new/), 'and the run says so', $output);

# ---- public project PRJNA1: names from ENA, by the run accession in the FASTQ name
star_log(11, "$dir/raw/PRJNA1/SRR100.fastq.gz");
star_log(12, "$dir/raw/PRJNA1/SRR101.fastq.gz");
star_log(13, "$dir/raw/PRJNA1/SRR102.fastq.gz");
write_file("$dir/ena.tsv",
  "run_accession\tsample_accession\tsample_title\tstudy_title\tlibrary_strategy\n"
  . "SRR101\tSAMN2\tgastrula (24 hpf) replicate 1 (A study)\tA study\tRNA-Seq\n"
  . "SRR100\tSAMN1\tgastrula (24 hpf) replicate 2 (A study)\tA study\tRNA-Seq\n"
  . "SRR102\tSAMN3\tH3K4me3 gastrula (A study)\tA study\tChIP-Seq\n");
run_drafter('--project PRJNA1', "--alignments $dir/alignments", "--ena $dir/ena.tsv", "--out $dir/out_p");
my @public = read_tsv("$dir/out_p/samples.tsv");
my $public_ids = sample_ids(@public);
check($public_ids eq 'PRJNA1.12 PRJNA1.11', 'ENA ChIP-Seq run skipped; replicate 1 first', $public_ids);
check(($public[0]{group} // '') eq 'gastrula (24 hpf)' && ($public[0]{replicate} // '') eq '1',
      'ENA title -> group without the study title and replicate', "$public[0]{group} / $public[0]{replicate}");
check(($public[0]{biosample} // '') eq 'SAMN2' && ($public[0]{run_accession} // '') eq 'SRR101', 'run and biosample from ENA');
my $public_experiment = decode_json(read_file("$dir/out_p/experiment.json"));
check($public_experiment->{project_accession} eq 'PRJNA1', 'a BioProject id is the project accession');
check($public_experiment->{access_level} eq 'COLLABORATOR', 'no sheet: access defaults to COLLABORATOR', $public_experiment->{access_level});

print "$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
