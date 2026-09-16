#!/usr/bin/perl
use strict;
use warnings;

my $top_hits = shift; #= '../analysis/BLASTP_UNIPROT_sprot/tophit.tsv';
my $source = 'DeepTMHMM'; #shift; #'SwissProt';
my $source_version = shift; #'release-113' ;#'2024_06'; #`cat ../analysis/BLASTP_UNIPROT_sprot/db_version.txt`;
my $source_url = "https://dtu.biolib.com/DeepTMHMM"; # shift; #'https://www.ensembl.org/'; #'https://www.uniprot.org';
my $annotation_type = 'Domains'; 
my $annotation_url = '';

my $date = `date '+%Y-%m-%d' -r '$top_hits'`;
$date =~ s/\s+//g;
my $source_nospace = $source;
$source_nospace =~ s/\s+/_/g;
$source_nospace =~ s/\//_/g;
open OUT, ">$source_nospace.domains.moop.tsv" or die "Can't open $source_nospace.domains.moop.tsv for writing $! \n";

print OUT "## Annotation Source: $source\n";
print OUT "## Annotation Source Version: $source_version\n";
print OUT "## Annotation Source URL: $source_url\n";
print OUT "## Annotation Accession URL: $annotation_url\n";
print OUT "## Annotation Type: $annotation_type\n";
print OUT "## Annotation Creation Date: $date\n";
print OUT join("\t","## Gene", "Accession","Accession_Description","Score"),"\n";

open TH, $top_hits or die "Can't open top hits $source file:$top_hits $! \n";

## Records look like:
##   # ACA1_..._000047.1 Length: 1004
##   # ACA1_..._000047.1 Number of predicted TMRs: 3
##   ACA1_..._000047.1	signal	1	22
##   ACA1_..._000047.1	outside	23	572
##   ACA1_..._000047.1	TMhelix	573	593
##   ...
##   //
##
## "Number of predicted TMRs" is DeepTMHMM's count of membrane-spanning
## segments regardless of whether they are alpha helices or beta strands
## (outer-membrane/porin-type proteins) -- it is NOT specific to TMhelix.
## Labeling every record 'TMhelix' silently mislabeled every beta-barrel
## protein (5 of 22,156 in the Anoura test set: 16-20 "TMRs" each, entirely
## Beta sheet, zero TMhelix lines). Count the actual segment lines in the
## record and pick the accession that matches what was really predicted.
##
## Flush on EITHER the next record's header line OR '//' -- not '//' alone.
## 22 of 22,156 records in the Anoura test file are not followed by a '//'
## before the next record's header starts (and the very last record in the
## file never gets one at all). Flushing only on '//' silently dropped
## exactly those records instead of emitting them.
my ($id, $tmr_count, $th_count, $bs_count);
my $flush = sub {
  return unless defined $id;
  if ($tmr_count > 0) {
    my $accession = $bs_count > $th_count ? 'Beta sheet' : 'TMhelix';
    print OUT join("\t",$id,$accession,"Number of predicted TMRs: $tmr_count",$tmr_count),"\n";
  }
  $id = undef;
};
while (my $line = <TH>){
  chomp $line;
  if ($line =~ /^#\s*(\S+)\s*.*Number of predicted TMRs:\s*(\d+)/) {
    $flush->();
    ($id, $tmr_count) = ($1, $2);
    ($th_count, $bs_count) = (0, 0);
    next;
  }
  next unless defined $id;
  if    ($line =~ /^\S+\tTMhelix\t/)    { $th_count++; next }
  elsif ($line =~ /^\S+\tBeta sheet\t/) { $bs_count++; next }
  elsif ($line =~ m{^//})               { $flush->() }
}
$flush->();  # last record may not be followed by '//' at all
close OUT;

