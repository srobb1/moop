#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;

my $dir = shift; # directory with the rbbh output $dir/*tsv
my $src = shift; # Ensembl Human
my $src_version = shift; # release-113
my $src_url = shift; # https://www.ensembl.org
my $id_url = shift; # https://www.ensembl.org/Multi/Search/Results?q=

=pod
query	best_hit	evalue	reciprocal_score	query_gene	hit_gene
CCA3t000001001.1	HUMAN069796	4.47e-67	2	CCA3g000001000.1	ENSG00000172113.9
query best_hit  evalue  reciprocal_score  query_gene
NV2t000003001.1 XP_032230053.2  0.0 1 None
NV2t000005001.1 XP_032230053.2  0.0 1 None
NV2t000006001.1 XP_048580524.1  0.0 1 None
NV2t000007001.1 XP_001626585.1  2.39e-296 1 None
NV2t000008001.1 XP_001626599.1  6.64e-63  4 NV2t000008001.1
=cut


my %desc;
open DESC, "$dir/desc.txt";
while (my $line =<DESC>){
  chomp $line;
  my ($id,$desc,$sym) = split "\t", $line;
  $desc{$id}{desc}=$desc;
  $desc{$id}{sym}=$sym;
} 


my %isoforms; 
my @isoforms = <"$dir/*isoforms">;
foreach my $isoform (@isoforms){
  open ISO, $isoform or die "cant open isoform file: $isoform $! \n";
  while (my $line = <ISO>){
    chomp $line;
    my ($iso_id,$gene_id) = split "\t",$line;
    $isoforms{$iso_id}=$gene_id;
  }
}
my @files = <"$dir/*tsv">;
my %hits;
my $date;
foreach my $file (@files){
  print $file,"\n";
  $date = `date '+%Y-%m-%d' -r $file`;
  
  my $id = '';
  open TSV, $file or die "cant open blast out in tsv format: $file $! \n";
  <TSV>;
  while (my $line = <TSV>){
    chomp $line;
    if ($line =~ /^#/){
     $id = '';
      next; 
    }elsif($id ne ''){
      next;
    }else{
      # top hit
      my @line = split "\t", $line;
      my ($id,$hit,$evalue,$reciprical_score,$query_gene,$hit_gene) = @line;
      next unless $reciprical_score == 1;
      $hits{$id}{$hit}=$evalue;
    }
  }
}
my $src_nospace = $src;
$src_nospace =~ s/\s+/_/g;
$src    =~ s/\s*$//;
$id_url =~ s/\s*$//;
$date   =~ s/\s*$//;
open OUT, ">$src_nospace.RBBH.moop.tsv" or die "Can't open >$src_nospace.RBBH.moop.tsv for writing $! \n";
print OUT "## Annotation Source: $src
## Annotation Source Version: $src_version
## Annotation Source URL: $src_url
## Annotation Accession URL: $id_url
## Annotation Type: RBBH Homolog
## Annotation Creation Date: $date\n";
print OUT join("\t","## Gene", "Accession","Accession_Description","Score"),"\n";

foreach my $id (sort keys %hits){
  foreach my $hit (sort keys %{$hits{$id}}){
    my $evalue = $hits{$id}{$hit};
    my $desc = $desc{$hit}{desc};
    my $sym = $desc{$hit}{sym};
    my $gene = $isoforms{$id};
    my $h_gene = $isoforms{$hit};
    if (defined $sym and $sym ne 'None' and $sym ne '0'){
      $desc = "$sym: $desc";
    } 
    # changing print out to be hit not hit gene
    # print OUT join("\t",$id,$h_gene,$desc,$evalue),"\n";
    print OUT join("\t",$id,$hit,$desc,$evalue),"\n";
  }
}
