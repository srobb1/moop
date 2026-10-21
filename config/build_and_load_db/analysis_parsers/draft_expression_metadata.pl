#!/usr/bin/perl
# Draft an expression bundle's samples.tsv and experiment.json for one project, for a person to edit.
#
#   draft_expression_metadata.pl --project <ID> --alignments <dir> --out <dir>
#       [--sheet <tracks.tsv>] [--master <MASTER_DATA_DESCRIPTION.txt>] [--ena <ena.tsv>]
#
#   --project     MOLNG-2127, PRJNA189768, ... (a path component of the project's FASTQs)
#   --alignments  STAR output, one folder per sample: <dir>/<N>/<N>.Log.out
#   --sheet       the track sheet exported as TSV (TRACK_ID <project>.<N>[.pos|.neg].bw)
#   --master      MASTER_DATA_DESCRIPTION.txt ("## Project: <ID>" blocks of "## Key: value")
#   --ena         ENA read_run report for a public project, fetched beforehand (this host's Perl
#                 has no HTTPS):
#                   curl -s 'https://www.ebi.ac.uk/ena/portal/api/filereport?accession=<PRJNA>&result=read_run&fields=run_accession,sample_accession,sample_title,study_title,library_strategy&format=tsv' > ena.tsv
#
# The contract is notes/EXPRESSION_BUNDLE_SPEC.md (samples.tsv, experiment.json). This writes a
# DRAFT: group labels, display order and the experiment label are a person's decisions.
#
# WHO A SAMPLE IS comes from the reads STAR aligned, never from the order of a list. Measured
# 2026-10-21 on the Nvec work dir: in MOLNG-1901 and MOLNG-2127, 22 of 24 samples carried another
# sample's label, because sample N's reads were the FASTQs in alphabetical order while its label
# followed the sequencing core's report order. So each sample's name is looked up from its own
# FASTQs (the readFilesIn line of <N>.Log.out):
#   1. in-house runs: the core's Sample_Report.csv beside the FASTQs (file -> sample name);
#   2. public runs:   the ENA report, by the SRR/ERR/DRR accession in the FASTQ name;
#   3. otherwise:     the sheet's NAME, marked unverified in review.tsv.
# The sheet supplies descriptive fields (stage, tissue, condition, biosample, track files), and
# every place it disagrees with the reads is listed in review.tsv.
#
# Writes into --out:
#   samples.tsv      only if absent (it is meant to be edited by hand)
#   experiment.json  only if absent (same)
#   review.tsv       always: one row per check that needs a person to look
#
# LAYOUT: constants at file level, all work in main(), main(); is the last line (the file-scope
# trap, tests/check_perl_file_scope.pl).
use strict;
use warnings;
use JSON::PP;
use Getopt::Long;
use File::Basename qw(basename dirname);

## MASTER_DATA_DESCRIPTION.txt keys as typed there -> the key read here
my %MASTER_KEY_ALIASES = (
  'citaion'              => 'citation',
  'expermiment'          => 'experiment',
  'developmental-stagei' => 'developmental-stage',
);
## experiment.json access levels the site knows (spec: anything else fails closed)
my %ACCESS_LEVELS = (PUBLIC => 1, COLLABORATOR => 1, IP_IN_RANGE => 1, ADMIN => 1);
## the sheet stops being a track list at this header (notes/EXPRESSION_COUNT_TABLES_PLAN.md)
my $SHEET_END = qr/^#####\s*Combo Tracks/;
## a run accession in a FASTQ name or a sheet cell
my $RUN_ACCESSION = qr/^([SED]RR\d+)/;
## an RNA-seq library, by ENA library_strategy ("RNA-Seq") or sheet technique ("RNASeq",
## "poly-A Stranded RNA-Seq", ...); ChIP-seq, ATAC-seq and the rest are left out of the bundle
my $RNA_ASSAY = qr/^(?!.*chip).*rna.?seq/i;
## samples.tsv columns, in order; the spec's first, then extra ones the site keeps but never reads
my @SAMPLE_COLUMNS = qw(sample_id group replicate stage tissue condition run_accession biosample track_keys
                        name name_source technique sheet_name aligned_fastq);
## experiment.json keys, in the spec's order
my @EXPERIMENT_KEYS = qw(label summary lab citation project_accession assay access_level detect_threshold contact);
## a sample with no replicate number sorts after its numbered replicates; one with no sheet row
## after every sample that has one
my $NO_REPLICATE = 1e9;
my $NOT_IN_SHEET = 1e9;

sub main {
  my %opt;
  GetOptions(\%opt, 'project=s', 'alignments=s', 'out=s', 'sheet=s', 'master=s', 'ena=s')
    or die usage();
  die usage() unless $opt{project} && $opt{alignments} && $opt{out};
  my $project = $opt{project};

  my @samples = star_samples($opt{alignments}, $project);
  die "No STAR sample under $opt{alignments} has FASTQs with '/$project/' in their path\n" unless @samples;

  my $sheet  = $opt{sheet}  ? read_sheet($opt{sheet}, $project)   : { rows => {}, tracks => {}, order => {} };
  my $master = $opt{master} ? read_master($opt{master}, $project) : {};
  my $ena    = $opt{ena}    ? read_ena($opt{ena})                 : {};

  my @review;
  my %report_cache;
  my @rna_samples;
  foreach my $sample (@samples) {
    name_sample($sample, $project, $sheet, $ena, \%report_cache, \@review);
    my $assay = assay_of($sample, $ena);
    if ($assay eq '' || $assay =~ $RNA_ASSAY) {
      push @rna_samples, $sample;
    } else {
      push @review, [$sample->{sample_id}, 'skipped_not_rnaseq', $sample->{name}, $assay,
                     'not an RNA-seq library, so not part of the expression bundle'];
    }
  }
  die "No RNA-seq sample in $project\n" unless @rna_samples;

  my @ordered = display_order(\@rna_samples);
  my $experiment = draft_experiment($project, $master, $ena, $sheet, \@ordered, \@review);

  -d $opt{out} or mkdir $opt{out} or die "Cannot create $opt{out}: $!\n";
  my @written;
  push @written, 'samples.tsv'     if write_if_absent("$opt{out}/samples.tsv", samples_tsv(\@ordered));
  push @written, 'experiment.json' if write_if_absent("$opt{out}/experiment.json", experiment_json($experiment));
  my $review_text = "sample_id\tcheck\tfrom_reads\tfrom_sheet\tnote\n";
  foreach my $entry (@review) {
    $review_text .= join("\t", @$entry) . "\n";
  }
  write_file("$opt{out}/review.tsv", $review_text);

  my (%by_source, %by_check);
  foreach my $sample (@ordered) {
    $by_source{ $sample->{name_source} }++;
  }
  foreach my $entry (@review) {
    $by_check{ $entry->[1] }++;
  }
  printf STDERR "%s: %d samples, names from %s\n", $project, scalar(@ordered), counts_text(\%by_source);
  printf STDERR "review.tsv: %s\n", %by_check ? counts_text(\%by_check) : 'nothing to check';
  printf STDERR "wrote %s\n", @written ? join(', ', @written)
    : 'nothing new (samples.tsv and experiment.json already exist; delete them to redraft)';
}

## What kind of library this is: ENA's library_strategy for a public run, else the sheet's
## technique, else '' (unknown, kept).
sub assay_of {
  my ($sample, $ena) = @_;
  my $record = $ena->{ $sample->{run_accession} };
  return $record->{library_strategy} if $record && ($record->{library_strategy} // '') ne '';
  return $sample->{technique} // '';
}

sub usage {
  return "Usage: $0 --project <ID> --alignments <STAR dir> --out <dir> [--sheet tracks.tsv] "
       . "[--master MASTER_DATA_DESCRIPTION.txt] [--ena ena.tsv]\n";
}

## Name one sample from its own reads, fill its sheet fields, and record every disagreement.
sub name_sample {
  my ($sample, $project, $sheet, $ena, $report_cache, $review) = @_;
  my $n  = $sample->{n};
  my $id = "$project.$n";
  $sample->{sample_id} = $id;

  my (@basenames, %aligned, %core_names, %runs);
  foreach my $file (@{ $sample->{files} }) {
    my $base = basename($file);
    push @basenames, $base;
    $aligned{$base} = 1;
    ## 1. the sequencing core's report beside these FASTQs
    my $dir = dirname($file);
    $report_cache->{$dir} //= read_sample_report("$dir/Sample_Report.csv");
    my $core_name = $report_cache->{$dir}{$base};
    $core_names{$core_name} = 1 if defined $core_name;
    ## 2. the run accession in the FASTQ name
    $runs{$1} = 1 if $base =~ $RUN_ACCESSION;
  }
  $sample->{aligned_fastq} = join(';', @basenames);
  $sample->{run_accession} = join(';', sort keys %runs);
  my $row = $sheet->{rows}{$n};
  push @$review, [$id, 'no_sheet_row', '', '', 'no sheet track for this sample'] if %{ $sheet->{rows} } && !$row;

  my @core_names = sort keys %core_names;
  if (@core_names > 1) {
    push @$review, [$id, 'core_names_disagree', join(';', @core_names), '',
                    "this sample's FASTQs have different names in Sample_Report.csv"];
  }
  my $ena_record = (keys %runs == 1) ? $ena->{ $sample->{run_accession} } : undef;
  if (@core_names == 1) {
    $sample->{name}        = $core_names[0];
    $sample->{name_source} = 'core_report';
  } elsif ($ena_record) {
    $sample->{name}        = ena_name($ena_record);
    $sample->{name_source} = 'ena';
    $sample->{biosample}   = $ena_record->{sample_accession} // '';
  } elsif ($row && $row->{NAME} ne '') {
    $sample->{name}        = strip_strand($row->{NAME});
    $sample->{name_source} = 'sheet_unverified';
    push @$review, [$id, 'name_from_sheet_only', '', $sample->{name},
                    "no core report or ENA record for these reads: the name is the sheet's, not checked against the reads"];
  } else {
    $sample->{name}        = $id;
    $sample->{name_source} = 'none';
    push @$review, [$id, 'no_name', '', '', 'no source names these reads'];
  }

  if ($row) {
    $sample->{sheet_name}  = strip_strand($row->{NAME});
    $sample->{track_keys}  = join(';', @{ $sheet->{tracks}{$n} });
    ## which sheet row DESCRIBES these reads: the track's own, unless the sheet labels the track
    ## with another sample's name -- then that row's stage/condition/... belong to that other
    ## sample, and the row named like these reads is the one that describes them
    my $described_by = $n;
    if ($sample->{name_source} eq 'core_report' && $sample->{sheet_name} ne $sample->{name}) {
      my $named_row = $sheet->{by_name}{ $sample->{name} };
      $described_by = $named_row if defined $named_row;
      push @$review, [$id, 'sheet_name_differs', $sample->{name}, $sample->{sheet_name},
                      'the sheet labels this track with another name than the core gives its reads; '
                      . (defined $named_row ? "stage/tissue/condition taken from the sheet row named '$sample->{name}' ($project.$named_row)"
                                            : 'no sheet row has the reads\' name, so stage/tissue/condition are left empty')];
      $row = defined $named_row ? $sheet->{rows}{$named_row} : undef;
    }
    $sample->{sheet_position} = $sheet->{order}{$described_by};
  }
  if ($row) {
    $sample->{stage}      = $row->{'developmental-stage'};
    $sample->{tissue}     = $row->{tissue};
    $sample->{condition}  = $row->{condition};
    $sample->{technique}  = $row->{technique};
    $sample->{biosample}  ||= $row->{biosample};
  }
  $row = $sheet->{rows}{$n};
  if ($row) {
    my ($sheet_run) = ($row->{accession} // '') =~ $RUN_ACCESSION;
    if (defined $sheet_run && $sample->{run_accession} ne '' && $sheet_run ne $sample->{run_accession}) {
      push @$review, [$id, 'sheet_accession_differs', $sample->{run_accession}, $sheet_run,
                      'the sheet gives another run accession than the reads STAR aligned'];
    }
    my @not_aligned;
    foreach my $sheet_file (split /[,;\s]+/, ($row->{NGS_file} // '')) {
      next if $sheet_file eq '';
      push @not_aligned, $sheet_file unless $aligned{ basename($sheet_file) };
    }
    if (@not_aligned) {
      push @$review, [$id, 'sheet_fastq_differs', $sample->{aligned_fastq}, join(';', @not_aligned),
                      'the sheet names FASTQs this sample was not aligned from'];
    }
  }
  ($sample->{group}, $sample->{replicate}) = split_group($sample->{name});
}

## Every <dir>/<N>/<N>.Log.out whose readFilesIn has a FASTQ under /<project>/, by N.
sub star_samples {
  my ($dir, $project) = @_;
  opendir my $dh, $dir or die "Cannot read $dir: $!\n";
  my @numbers;
  foreach my $entry (readdir $dh) {
    push @numbers, $entry if $entry =~ /^\d+$/;
  }
  closedir $dh;
  my @samples;
  foreach my $n (sort { $a <=> $b } @numbers) {
    my $log = "$dir/$n/$n.Log.out";
    next unless -f $log;
    my @files = read_files_in($log);
    die "$log: no readFilesIn line\n" unless @files;
    my $in_project = 0;
    foreach my $file (@files) {
      $in_project = 1 if index($file, "/$project/") >= 0;
    }
    push @samples, { n => $n, files => \@files } if $in_project;
  }
  return @samples;
}

## The FASTQs STAR read, from the "readFilesIn ... ~RE-DEFINED" line (mates are space-separated,
## lanes comma-separated).
sub read_files_in {
  my ($log) = @_;
  open my $fh, '<', $log or die "Cannot read $log: $!\n";
  my @files;
  while (my $line = <$fh>) {
    next unless $line =~ /^readFilesIn\s+(.+?)\s+~RE-DEFINED/;
    foreach my $file (split /[,\s]+/, $1) {
      push @files, $file if $file ne '';
    }
    last;
  }
  close $fh;
  return @files;
}

## Sample_Report.csv: FASTQ file name -> sample name. The core has used two headers
## ("Output,...,SampleName,..." and "output,...,sample name,..."); columns are found by name.
sub read_sample_report {
  my ($file) = @_;
  my %names;
  open my $fh, '<', $file or return \%names;
  my $header = <$fh>;
  return \%names unless defined $header;
  $header =~ s/\r?\n$//;
  my @columns;
  foreach my $column (split /,/, $header) {
    (my $key = lc $column) =~ s/\s+//g;
    push @columns, $key;
  }
  my $output_col = column_index(\@columns, 'output');
  my $name_col   = column_index(\@columns, 'samplename');
  return \%names unless defined $output_col && defined $name_col;
  while (my $line = <$fh>) {
    $line =~ s/\r?\n$//;
    my @cells = split /,/, $line;
    next unless defined $cells[$name_col] && $cells[$output_col];
    $names{ $cells[$output_col] } = $cells[$name_col];
  }
  close $fh;
  return \%names;
}

## The track sheet, this project's rows: per sample N its first row (column => value), its track
## files in sheet order, and the sheet row where it first appears (the curated order).
sub read_sheet {
  my ($file, $project) = @_;
  open my $fh, '<', $file or die "Cannot read $file: $!\n";
  my $header = <$fh>;
  die "$file: empty\n" unless defined $header;
  $header =~ s/\r?\n$//;
  my @columns = split /\t/, $header, -1;
  die "$file: no TRACK_ID column -- not the track sheet?\n" unless defined column_index(\@columns, 'TRACK_ID');
  my (%rows, %tracks, %order, %seen_track, %by_name);
  my $row_number = 1;
  while (my $line = <$fh>) {
    $row_number++;
    $line =~ s/\r?\n$//;
    last if $line =~ $SHEET_END;
    next if $line =~ /^#/;
    my $row = row_hash(\@columns, [split /\t/, $line, -1]);
    next unless $row->{TRACK_ID} =~ /^\Q$project\E\.(\d+)(?:\.(?:pos|neg))?\.bw$/;
    my $n = $1;
    $rows{$n}  //= $row;
    $order{$n} //= $row_number;
    $by_name{ strip_strand($row->{NAME}) } //= $n;
    push @{ $tracks{$n} }, $row->{TRACK_ID} unless $seen_track{ $row->{TRACK_ID} }++;
  }
  close $fh;
  return { rows => \%rows, tracks => \%tracks, order => \%order, by_name => \%by_name };
}

## MASTER_DATA_DESCRIPTION.txt: the "## Key: value" lines of this project's block.
sub read_master {
  my ($file, $project) = @_;
  open my $fh, '<', $file or die "Cannot read $file: $!\n";
  my (%fields, $in_block);
  while (my $line = <$fh>) {
    $line =~ s/\r?\n$//;
    if ($line =~ /^##\s*Project:\s*(\S+)/) {
      $in_block = ($1 eq $project);
      next;
    }
    next unless $in_block && $line =~ /^##\s*([A-Za-z-]+):\s*(.*?)\s*$/;
    my ($key, $value) = (lc $1, $2);
    $key = $MASTER_KEY_ALIASES{$key} // $key;
    $fields{$key} //= $value;
  }
  close $fh;
  return \%fields;
}

## ENA read_run report: run accession -> its columns.
sub read_ena {
  my ($file) = @_;
  open my $fh, '<', $file or die "Cannot read $file: $!\n";
  my $header = <$fh>;
  die "$file: empty\n" unless defined $header;
  $header =~ s/\r?\n$//;
  my @columns = split /\t/, $header, -1;
  die "$file: no run_accession column\n" unless defined column_index(\@columns, 'run_accession');
  my %runs;
  while (my $line = <$fh>) {
    $line =~ s/\r?\n$//;
    my $record = row_hash(\@columns, [split /\t/, $line, -1]);
    $runs{ $record->{run_accession} } = $record;
  }
  close $fh;
  return \%runs;
}

sub column_index {
  my ($columns, $name) = @_;
  foreach my $index (0 .. $#$columns) {
    return $index if $columns->[$index] eq $name;
  }
  return undef;
}

sub row_hash {
  my ($columns, $cells) = @_;
  my %row;
  foreach my $index (0 .. $#$columns) {
    $row{ $columns->[$index] } = $cells->[$index] // '';
  }
  return \%row;
}

sub counts_text {
  my ($counts) = @_;
  my @parts;
  foreach my $key (sort keys %$counts) {
    push @parts, "$key $counts->{$key}";
  }
  return join(', ', @parts);
}

## ENA sample titles end with " (<study title>)"; drop it.
sub ena_name {
  my ($record) = @_;
  my $name  = $record->{sample_title} // '';
  my $study = $record->{study_title} // '';
  $name =~ s/\s*\(\Q$study\E\)\s*$// if $study ne '';
  return $name;
}

sub strip_strand {
  my ($name) = @_;
  $name //= '';
  $name =~ s/\.(?:pos|neg)$//;
  return $name;
}

## "zygote (2 hours post fertilization) replicate 2" -> ("zygote (2 hours post fertilization)", 2);
## "hox1a-sh2-1" -> ("hox1a-sh2", 1); "Wildtype1" -> ("Wildtype", 1); "6h_wild_type" -> ("6h wild type", "").
sub split_group {
  my ($name) = @_;
  my ($group, $replicate) = ($name, '');
  if ($name =~ /^(.*?)[\s,_-]*\breplicate[\s_-]*(\d+)$/i) {
    ($group, $replicate) = ($1, $2);
  } elsif ($name =~ /^(.+?)[-_ ](\d+)$/) {
    ($group, $replicate) = ($1, $2);
  } elsif ($name =~ /^(.*[A-Za-z)])(\d+)$/) {
    ($group, $replicate) = ($1, $2);
  }
  $group =~ s/_/ /g;
  return ($group, $replicate);
}

## Groups in the order they first appear (the position of the sheet row that describes each
## sample, then sample number), replicates together.
sub display_order {
  my ($samples) = @_;
  my @by_position = sort {
    ($a->{sheet_position} // $NOT_IN_SHEET) <=> ($b->{sheet_position} // $NOT_IN_SHEET)
      or $a->{n} <=> $b->{n}
  } @$samples;
  my (%group_rank, %position);
  foreach my $index (0 .. $#by_position) {
    my $sample = $by_position[$index];
    $position{ $sample->{n} } = $index;
    $group_rank{ $sample->{group} } //= scalar keys %group_rank;
  }
  return sort {
    $group_rank{ $a->{group} } <=> $group_rank{ $b->{group} }
      or replicate_number($a) <=> replicate_number($b)
      or $position{ $a->{n} } <=> $position{ $b->{n} }
  } @$samples;
}

sub replicate_number {
  my ($sample) = @_;
  return $sample->{replicate} eq '' ? $NO_REPLICATE : $sample->{replicate};
}

## experiment.json draft: the master block first, then ENA, then the sheet (only when every sample agrees).
sub draft_experiment {
  my ($project, $master, $ena, $sheet, $samples, $review) = @_;
  my $study_title = '';
  foreach my $record (values %$ena) {
    $study_title = $record->{study_title} // '';
    last;
  }
  my $lab      = $master->{source} || sheet_consensus($sheet, $samples, 'source');
  my $summary  = $master->{experiment} || $study_title || sheet_consensus($sheet, $samples, 'experiment');
  my $short    = $master->{summary} || sheet_consensus($sheet, $samples, 'summary') || $summary || $project;
  my $citation = $master->{citation} || sheet_consensus($sheet, $samples, 'citation');
  $citation = '' if $citation =~ /^\?+$/;
  my $bioproject = $master->{bioproject} || ($project =~ /^PRJ[A-Z]{2}\d+$/ ? $project : '');
  my $access = sheet_consensus($sheet, $samples, 'ACCESS');
  if (!$ACCESS_LEVELS{$access}) {
    push @$review, ['', 'access_level', '', $access,
                    'sheet ACCESS is not one value every sample shares; drafted COLLABORATOR'];
    $access = 'COLLABORATOR';
  }
  return {
    label             => ucfirst($short) . ($lab ne '' ? " ($lab)" : ''),
    summary           => $summary,
    lab               => $lab,
    citation          => $citation,
    project_accession => $bioproject,
    assay             => 'bulk_rna',
    access_level      => $access,
    detect_threshold  => 1,
    contact           => '',
  };
}

## A sheet column's value when every sample with a sheet row has the same non-empty one, else ''.
sub sheet_consensus {
  my ($sheet, $samples, $column) = @_;
  my %values;
  foreach my $sample (@$samples) {
    my $row = $sheet->{rows}{ $sample->{n} } or next;
    $values{ $row->{$column} // '' } = 1;
  }
  my @values = keys %values;
  return (@values == 1 && $values[0] ne '') ? $values[0] : '';
}

sub samples_tsv {
  my ($samples) = @_;
  my $text = join("\t", @SAMPLE_COLUMNS) . "\n";
  foreach my $sample (@$samples) {
    my @cells;
    foreach my $column (@SAMPLE_COLUMNS) {
      my $value = $sample->{$column} // '';
      $value =~ s/[\t\r\n]+/ /g;
      push @cells, $value;
    }
    $text .= join("\t", @cells) . "\n";
  }
  return $text;
}

sub experiment_json {
  my ($experiment) = @_;
  my $json = JSON::PP->new->allow_nonref;
  my @lines;
  foreach my $key (@EXPERIMENT_KEYS) {
    push @lines, '  ' . $json->encode($key) . ': ' . $json->encode($experiment->{$key});
  }
  return "{\n" . join(",\n", @lines) . "\n}\n";
}

sub write_if_absent {
  my ($path, $text) = @_;
  return 0 if -e $path;
  write_file($path, $text);
  return 1;
}

sub write_file {
  my ($path, $text) = @_;
  open my $fh, '>', "$path.tmp" or die "Cannot write $path.tmp: $!\n";
  print $fh $text;
  close $fh or die "Cannot write $path.tmp: $!\n";
  rename "$path.tmp", $path or die "Cannot rename $path.tmp: $!\n";
}

main();
