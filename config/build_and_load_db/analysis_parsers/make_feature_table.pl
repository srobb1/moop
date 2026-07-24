#!/usr/bin/perl
use strict;
use warnings;

#perl ~/sciproj/SBGENOMES/dev/smr_dev/make_feature_table.pl ../genomic.gff ../metadata.yaml gene mRNA > features.tsv

my $gff = shift;
my $metadata = shift;
my @types = @ARGV; # mRNA or cleaved_peptide_region mature_peptide_region immature_peptide_region

if (!$gff or !$metadata or !@types){
  die "perl $0 GFF_file metadata.yaml types(mRNA or cleaved_peptide_region mature_peptide_region immature_peptide_region) \n";
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


my %types; 
foreach my $type (@types){
  $types{$type}=1;
}
my %feature_types;
open GFF, $gff or die "CAn't open GFF $! \n";
while (my $line = <GFF>){
  chomp $line;
  next if $line =~ /^#/;
  next if $line !~ /ID=/;
  my ($ref,$src,$type,$score,$strand,$phase,$nine) = split "\t", $line;  
  my ($id) = $line =~ /ID=([^;]+)/;
  my $parent_id = '';
  my $parent_type = '';

  $feature_types{$id}=$type;

  if ($line =~ /Parent=/){
    ($parent_id) = $line =~ /Parent=([^;]+)/;
    $parent_type = $feature_types{$parent_id};
  }

  if (exists $types{$type}){
    my $name = $id;
    if ($line =~ /Name=([^;]+)/){
     $name = $1;
    }
    my $note = '';
    if ($line =~ /Note=([^;]+)/){
     $note = $1;
    }
    print join("\t",$id,$type,$parent_id,$parent_type,$name,$note),"\n";
  }
}


