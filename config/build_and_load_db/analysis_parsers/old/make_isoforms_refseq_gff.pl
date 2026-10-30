#c!/usr/bin/perl
use strict;
use warnings;


my $gff = shift;

#NW_011888782.1  Gnomon  gene  3568  10731 . + . ID=gene-ANAPC13;Dbxref=GeneID:105288252;Name=ANAPC13;gbkey=Gene;gene=ANAPC13;gene_biotype=protein_coding
#NW_011888782.1  Gnomon  mRNA  3568  10731 . + . ID=rna-XM_011373330.2;Parent=gene-ANAPC13;Dbxref=GeneID:105288252,Genbank:XM_011373330.2;Name=XM_011373330.2;gbkey=mRNA;gene=ANAPC13;model_evidence=Supporting evidence includes similarity to: 1 mRNA%2C 12 Proteins%2C and 100%25 coverage of the annotated genomic feature by RNAseq alignments%2C including 21 samples with support for all annotated introns;product=anaphase promoting complex subunit 13%2C transcript variant X3;transcript_id=XM_011373330.2
#NW_011888782.1  Gnomon  exon  3568  3750  . + . ID=exon-XM_011373330.2-1;Parent=rna-XM_011373330.2;Dbxref=GeneID:105288252,Genbank:XM_011373330.2;gbkey=mRNA;gene=ANAPC13;product=anaphase promoting complex subunit 13%2C transcript variant X3;transcript_id=XM_011373330.2
#NW_011888782.1  Gnomon  exon  5504  5569  . + . ID=exon-XM_011373330.2-2;Parent=rna-XM_011373330.2;Dbxref=GeneID:105288252,Genbank:XM_011373330.2;gbkey=mRNA;gene=ANAPC13;product=anaphase promoting complex subunit 13%2C transcript variant X3;transcript_id=XM_011373330.2
#NW_011888782.1  Gnomon  exon  6400  6525  . + . ID=exon-XM_011373330.2-3;Parent=rna-XM_011373330.2;Dbxref=GeneID:105288252,Genbank:XM_011373330.2;gbkey=mRNA;gene=ANAPC13;product=anaphase promoting complex subunit 13%2C transcript variant X3;transcript_id=XM_011373330.2
#NW_011888782.1  Gnomon  exon  10434 10731 . + . ID=exon-XM_011373330.2-4;Parent=rna-XM_011373330.2;Dbxref=GeneID:105288252,Genbank:XM_011373330.2;gbkey=mRNA;gene=ANAPC13;product=anaphase promoting complex subunit 13%2C transcript variant X3;transcript_id=XM_011373330.2
#NW_011888782.1  Gnomon  CDS 6427  6525  . + 0 ID=cds-XP_011371632.1;Parent=rna-XM_011373330.2;Dbxref=GeneID:105288252,Genbank:XP_011371632.1;Name=XP_011371632.1;gbkey=CDS;gene=ANAPC13;product=anaphase-promoting complex subunit 13;protein_id=XP_011371632.1
#NW_011888782.1  Gnomon  CDS 10434 10559 . + 0 ID=cds-XP_011371632.1;Parent=rna-XM_011373330.2;Dbxref=GeneID:105288252,Genbank:XP_011371632.1;Name=XP_011371632.1;gbkey=CDS;gene=ANAPC13;product=anaphase-promoting complex subunit 13;protein_id=XP_011371632.1
#

my %printed; 
open GFF, $gff or die "CAn't open GFF $! \n";
my %groups;
while (my $line = <GFF>){
  chomp $line;
  next if $line =~ /^#/;
  my ($ref,$src,$type,$score,$strand,$phase,$nine) = split "\t", $line;  
  if ($line =~ /\tCDS\t.*Parent=rna-([^;]+).*\bGeneID:([^;,]+).*protein_id=([^;]+)/){
    # Eukaryotic: gene -> mRNA -> CDS
    my $protein_id    = $3;
    my $cds_id        = "cds-$protein_id";
    my $transcript_id = $1;
    my $gene_id       = $2;
    $groups{$gene_id}{$transcript_id} = 'transcript';
    $groups{$gene_id}{$protein_id}    = 'protein';
    $groups{$gene_id}{$cds_id}        = 'cds';
  } elsif ($line =~ /\tCDS\t.*Parent=gene-[^;]+.*\bGeneID:([^;,]+).*protein_id=([^;]+)/){
    # Prokaryotic: gene -> CDS (no mRNA layer)
    my $gene_id    = $1;
    my $protein_id = $2;
    my $cds_id     = "cds-$protein_id";
    $groups{$gene_id}{$protein_id} = 'protein';
    $groups{$gene_id}{$cds_id}     = 'cds';
  }
}

foreach my $gene (sort keys %groups){
  my @children = sort keys  %{$groups{$gene}};
  my $children = join(";",@children);
  print join("\t",$children,'None',$gene),"\n";
}

