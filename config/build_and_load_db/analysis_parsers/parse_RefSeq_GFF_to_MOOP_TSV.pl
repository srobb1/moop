#!/usr/bin/perl
use strict;
use warnings;

#perl ~/sciproj/SBGENOMES/dev/smr_dev/make_feature_table.pl ../genomic.gff ../metadata.yaml gene mRNA > features.tsv

my $gff = shift;
my $metadata = shift;
#my @types = @ARGV; # mRNA or cleaved_peptide_region mature_peptide_region immature_peptide_region

if (!$gff or !$metadata){# or !@types){
  die "perl $0 GFF_file metadata.yaml"; # types(mRNA or cleaved_peptide_region mature_peptide_region immature_peptide_region) \n";
}
# metatdata.yaml
#genus: Aeorestes
#species: cinereus
#ncbi-taxon-id: 257879
#common-name: Hoary bat
#simrbase-prefix: ACI1
#source: DNAzoo
#source_url: https://www.dnazoo.org/assemblies/Aeorestes_cinereus
#download_url: https://www.dropbox.com/s/qsl2jt1auep874n/L.cinereus_Cryan_1219_p1.0_HiC.fasta.gz?dl=0
#accession: GCA_011751095.1
#assembly_name: xyz
#sciproj: /n/sci/SCI-004106-SBBATS/genomes/Aeorestes_cinereus
#author: smr
#date-added: 2025-01-23
#details: gene models were called using helixer by smr. Now called Lasiurus cinereus at NCBI.

my $genus = '';
my $species = '';
my $commonname = '';
my $taxon_id = '';
my $genome_accession = '';
my $genome_name = '';
my $genome_description = '';
my $source = '';
my $details = '';

open METADATA, $metadata or die "Can't open Metadata file $! \n";
while (my $line = <METADATA>){
  chomp $line;
  if ($line =~ /^genus: (\S+)/){
    $genus = $1;
  }elsif($line =~ /species: (\S+)/){
    $species = $1;
  }elsif($line =~ /common-name: (.+)$/){
    $commonname = $1;
  }elsif($line =~ /ncbi-taxon-id: (\S+)/){
    $taxon_id = $1;
  }elsif($line =~ /accession: (\S+)/){
    $genome_accession = $1;
  }elsif($line =~ /simrbase-prefix: (\S+)/){
    $genome_name = $1;
  }elsif($line =~ /source: (\S+)/){
    $source = $1
  }elsif($line =~ /details: (.+)$/){
    $details = $1;
  }
}
print "## Genus: $genus 
## Species: $species
## Common Name: $commonname
## NCBI Taxon ID: $taxon_id
## Genome Accession: $genome_accession
## Genome Name: $genome_name
## Genome Description: Assembly is from $source. $details 
## This_Uniquename\tThis_Type\tParent_Uniquename\tParent_Type\tThis_Name\tThis_Description\n";


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
my %printed_gene;
open GFF, $gff or die "CAn't open GFF $! \n";
my $gene_id = '';
my $mrna_id = '';
my $protein_id = '';
while (my $line = <GFF>){
  chomp $line;
  next if $line =~ /^#/;
  my ($ref,$src,$type,$score,$strand,$phase,$nine) = split "\t", $line;

  if($line =~ /\tgene\t.*Dbxref=GeneID:([^;]+)/){
    $gene_id = $1;
    $mrna_id = '';  # reset per gene; stays empty for prokaryotes (no mRNA feature)
  }elsif ($line =~ /\tmRNA\t.*transcript_id=([^;]+)/){
    $mrna_id = $1;
  }elsif ($line =~ /\tCDS\t.*protein_id=([^;]+)/){
    $protein_id = $1;
    if(!exists $printed{$protein_id}){
      $printed{$protein_id}++;
      my ($name) = ($line =~ /\bgene=([^;]+)/) ? ($1) : ($line =~ /locus_tag=([^;]+)/) ? ($1) : ('');
      my ($note) = $line =~ /product=([^;]+)/;
      $note //= '';
      $note =~ s/%([0-9A-Fa-f]{2})/chr(hex($1))/ge;
      my $parent_id   = $mrna_id || $gene_id;
      my $parent_type = $mrna_id ? 'mRNA' : 'gene';
      print join("\t",$gene_id,'gene','','',$name,$note),"\n" unless $printed_gene{$gene_id}++;
      print join("\t",$mrna_id,'mRNA',$gene_id,'gene',$name,$note),"\n" if $mrna_id;
      print join("\t","cds-$protein_id",'cds',$parent_id,$parent_type,$name,$note),"\n";
      print join("\t",$protein_id,'protein',$parent_id,$parent_type,$name,$note),"\n";
    }
  }
}


