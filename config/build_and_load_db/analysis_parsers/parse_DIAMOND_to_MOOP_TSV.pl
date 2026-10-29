#!/usr/bin/perl
use strict;
use warnings;

my $top_hits = shift; #= '../analysis/BLASTP_UNIPROT_sprot/tophit.tsv';
my $source = shift; #'SwissProt';
my $source_version = shift; #'release-113' ;#'2024_06'; #`cat ../analysis/BLASTP_UNIPROT_sprot/db_version.txt`;
my $source_url = shift; #'https://www.ensembl.org/'; #'https://www.uniprot.org';
my $annotation_url = shift; #'https://www.ensembl.org/id/' ;#'https://www.uniprot.org/uniprotkb/';
my $annotation_type = 'Homologs'; 

my $date = `date '+%Y-%m-%d' -r $top_hits`; 
$date =~ s/\s+//g;
my $source_nospace = $source;
$source_nospace =~ s/\s+/_/g; 
$source_nospace =~ s/\//_/g; 
open OUT, ">$source_nospace.homologs.moop.tsv" or die "Can't open $source_nospace.homologs.moop.tsv for writing $! \n";

print OUT "## Annotation Source: $source\n";
print OUT "## Annotation Source Version: $source_version\n";
print OUT "## Annotation Source URL: $source_url\n";
print OUT "## Annotation Accession URL: $annotation_url\n";
print OUT "## Annotation Type: $annotation_type\n";
print OUT "## Annotation Creation Date: $date\n"; 
print OUT join("\t","## Gene", "Accession","Accession_Description","Score"),"\n";

open TH, $top_hits or die "Can't open top hits $source file:$top_hits $! \n";
my %annot;

# Columns by the header's names when the file has one: the annotation pipeline's runs in
# wrap_run_all_v2 write 17 (qseqid sseqid pident length ... bitscore qlen slen qcovhsp scovhsp
# stitle), and reading them by position put pident in the description and the alignment length in
# the score (2026-10-29). A file without a header is the older layout: qseqid sseqid stitle evalue.
my %column = (qseqid => 0, sseqid => 1, stitle => 2, evalue => 3);

while (my $line = <TH>){
  chomp $line;
  if ($line =~ /^qseqid\t/) {
    my @names = split /\t/, $line;
    %column = map { my $index = $_; ($names[$index] => $index) } 0 .. $#names;
    foreach my $name (qw(qseqid sseqid stitle evalue)) {
      die "$top_hits: no $name column in its header\n" unless defined $column{$name};
    }
    next;
  }
  my @field = split /\t/, $line;
  my ($t_id, $hit_id, $hit_desc, $score) = @field[ @column{qw(qseqid sseqid stitle evalue)} ];
  next unless defined $hit_desc;
  #ACI1_HiC_scaffold_1000_000001.1	Q9BWM5	Zinc finger protein 416 OS=Homo sapiens OX=9606 GN=ZNF416 PE=1 SV=1	3.94e-110
  #ACI1_HiC_scaffold_1_000001.1	sp|Q16342|PDCD2_HUMAN	sp|Q16342|PDCD2_HUMAN Programmed cell death protein 2 OS=Homo sapiens OX=9606 GN=PDCD2 PE=1 SV=2	2.04e-210
  # ENSAMXP00000035529.1  ENSAMXP00000035529.1 pep primary_assembly:Astyanax_mexicanus-2.0:20:11140888:11159592:1 gene:ENSAMXG00000032087.1 transcript:ENSAMXT00000039164.1 gene_biotype:protein_coding transcript_biotype:protein_coding gene_symbol:HRH2 description:histamine receptor H2 [Source:HGNC Symbol;Acc:HGNC:5183]
  # the homolog table shows each protein's top hit. The search now keeps several targets per protein
  # for gene naming (25-50 against human: 401,689 rows for Congeria's 17,355 proteins with a hit, most
  # of them isoforms of one human gene); DIAMOND lists a query's hits in its own order, best first (the
  # hit a one-target search reports), so the first is kept
  next if $annot{$t_id}++;
  $hit_id =~ s/sp\|(\S+)\|\S+/$1/;
  if ($hit_desc =~ /gene_symbol:(\S+)\s*description/){
    $hit_desc =~ s/.+gene_symbol:(\S+)\s*description:\s*(.+)/$1: $2/;
  }elsif($hit_desc =~ /description/){
    $hit_desc =~ s/.+description:\s*(.+)/$1/;
  }elsif($hit_desc =~ /^sp\|.+GN/){
    $hit_desc =~ s/^sp\|\S+\|\S+ (.+GN=(\S+).*)/$2: $1/;
  }elsif($hit_desc =~ /^sp\|/){
    $hit_desc =~ s/^sp\|\S+\|\S+ (.+)/$1/;
  }
  print OUT join("\t",$t_id,$hit_id,$hit_desc,$score),"\n";
}


