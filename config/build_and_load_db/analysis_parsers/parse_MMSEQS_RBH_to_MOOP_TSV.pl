#!/usr/bin/perl
use strict;
use warnings;

# MMseqs2 easy-rbh results against one Ensembl reference proteome -> moop TSV (RBBH Homolog).
#
#   parse_MMSEQS_RBH_to_MOOP_TSV.pl rbh_mmseq_results.tsv ref.pep.all.fa[.gz] "Ensembl Homo sapiens" \
#       release-113 https://www.ensembl.org/ "https://www.ensembl.org/Multi/Search/Results?q="
#
# Writes <Source_with_underscores>.MMseqs.RBBH.moop.tsv, e.g. Ensembl_Homo_sapiens.MMseqs.RBBH.moop.tsv
# (matches the loader's *.RBBH.moop.tsv pattern; the eross file is Ensembl_Homo_sapiens.RBBH.moop.tsv)
#
# Input: the 12 BLAST-style columns of `mmseqs easy-rbh` with a header line
#   query target pident alnlen mismatch gapopen qstart qend tstart tend evalue bits
# Query ids may carry a ":pep" suffix (mmseqs keeps the FASTA id as given); it is removed.
#
# One row per query protein and partner GENE: easy-rbh reports every isoform of the partner
# gene that ties as best, so the best-scoring isoform (highest bits) is kept.
# Accession_Description is "SYMBOL: description" from the reference FASTA header
# (gene_symbol:, description: with [Source:...] removed). A header without gene_symbol gets
# the description alone -- never the protein id as a symbol.

my $usage = "usage: $0 rbh_mmseq_results.tsv ref.pep.all.fa[.gz] SOURCE VERSION SOURCE_URL ACCESSION_URL\n";
my $results_file = shift or die $usage;
my $ref_fasta    = shift or die $usage;
my $source       = shift or die $usage;
my $version      = shift or die $usage;
my $source_url   = shift or die $usage;
my $accession_url = shift or die $usage;

# reference headers: protein id -> gene, symbol, description
my %ref;
my $open_cmd = $ref_fasta =~ /\.gz$/ ? "gzip -dc '$ref_fasta' |" : "< $ref_fasta";
open my $fa_fh, $open_cmd or die "cant open reference fasta $ref_fasta $!\n";
while (my $line = <$fa_fh>) {
  next unless $line =~ /^>(\S+)/;
  my $protein_id = $1;
  my ($gene)   = $line =~ /\bgene:(\S+)/;
  my ($symbol) = $line =~ /\bgene_symbol:(\S+)/;
  my ($desc)   = $line =~ /\bdescription:(.+?)\s*$/;
  if (defined $desc) {
    $desc =~ s/\s*\[Source:[^\]]*\]//;
  }
  $ref{$protein_id} = { gene => $gene // $protein_id, symbol => $symbol // '', desc => $desc // '' };
}
close $fa_fh;
die "no protein headers read from $ref_fasta\n" unless %ref;

# best hit per query protein and partner gene
my %best;
my ($lines, $missing_ref) = (0, 0);
open my $rbh_fh, '<', $results_file or die "cant open $results_file $!\n";
while (my $line = <$rbh_fh>) {
  chomp $line;
  next if $line =~ /^query\t/ or $line !~ /\S/;
  my ($query, $target, $pident, $alnlen, $mismatch, $gapopen,
      $qstart, $qend, $tstart, $tend, $evalue, $bits) = split /\t/, $line;
  $lines++;
  $query =~ s/:pep$//;
  my $info = $ref{$target};
  if (!defined $info) {
    $missing_ref++;
    $info = { gene => $target, symbol => '', desc => '' };
  }
  my $partner_gene = $info->{gene};
  my $current = $best{$query}{$partner_gene};
  if (!defined $current or $bits > $current->{bits}) {
    $best{$query}{$partner_gene} = { target => $target, evalue => $evalue, bits => $bits, info => $info };
  }
}
close $rbh_fh;
warn "WARNING: $missing_ref of $lines hits have no header in $ref_fasta; their description is blank\n"
  if $missing_ref;

my $date = `date '+%Y-%m-%d' -r '$results_file'`;
$date =~ s/\s+//g;
(my $source_file = $source) =~ s/\s+/_/g;
my $out_file = "$source_file.MMseqs.RBBH.moop.tsv";

open my $out_fh, '>', $out_file or die "cant write $out_file $!\n";
print $out_fh "## Annotation Source: $source (MMseqs2 RBH)
## Annotation Source Version: $version
## Annotation Source URL: $source_url
## Annotation Accession URL: $accession_url
## Annotation Type: RBBH Homolog
## Annotation Creation Date: $date
";
print $out_fh join("\t", "## Gene", "Accession", "Accession_Description", "Score"), "\n";

my $rows = 0;
foreach my $query (sort keys %best) {
  foreach my $partner_gene (sort keys %{$best{$query}}) {
    my $hit = $best{$query}{$partner_gene};
    my $symbol = $hit->{info}{symbol};
    my $desc   = $hit->{info}{desc};
    my $label;
    if ($symbol ne '' and $desc ne '') {
      $label = "$symbol: $desc";
    } elsif ($symbol ne '') {
      $label = $symbol;
    } else {
      $label = $desc;
    }
    print $out_fh join("\t", $query, $hit->{target}, $label, $hit->{evalue}), "\n";
    $rows++;
  }
}
close $out_fh;
warn sprintf("wrote %s: %d rows for %d query proteins (%d hit lines)\n",
             $out_file, $rows, scalar(keys %best), $lines);
