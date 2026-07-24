#!/usr/bin/perl
use strict;
use warnings;
use URI::Escape;

# perl make_feature_table_from_ensembl_gff.pl genomic.gff metadata.yaml > features.tsv

my $gff      = shift;
my $metadata = shift;

if (!$gff or !$metadata) {
    die "perl $0 GFF_file metadata.yaml\n";
}

my $genus            = '';
my $species          = '';
my $commonname       = '';
my $taxon_id         = '';
my $genome_accession = '';
my $genome_name      = '';
my $source           = '';
my $details          = '';

open METADATA, $metadata or die "Can't open Metadata file $!\n";
while (my $line = <METADATA>) {
    chomp $line;
    if    ($line =~ /^genus: (\S+)/)        { $genus            = $1 }
    elsif ($line =~ /species: (\S+)/)        { $species          = $1 }
    elsif ($line =~ /common-name: (.+)$/)    { $commonname       = $1 }
    elsif ($line =~ /ncbi-taxon-id: (\S+)/)  { $taxon_id         = $1 }
    elsif ($line =~ /accession: (\S+)/)      { $genome_accession = $1 }
    elsif ($line =~ /simrbase-prefix: (\S+)/)  { $genome_name      = $1 }
    elsif ($line =~ /source: (\S+)/)         { $source           = $1 }
    elsif ($line =~ /details: (.+)$/)        { $details          = $1 }
}
close METADATA;

print "## Genus: $genus 
## Species: $species
## Common Name: $commonname
## NCBI Taxon ID: $taxon_id
## Genome Accession: $genome_accession
## Genome Name: $genome_name
## Genome Description: Assembly is from $source. $details 
## This_Uniquename\tThis_Type\tParent_Uniquename\tParent_Type\tThis_Name\tThis_Description\n";

# Two-pass: collect gene name/desc and tx->gene map first, then emit feature rows on CDS lines

my %gene_name;   # gene_id -> symbol
my %gene_desc;   # gene_id -> cleaned description
my %tx_to_gene;  # transcript_id -> gene_id

open my $GFF1, '<', $gff or die "Can't open GFF: $gff $!\n";
while (my $line = <$GFF1>) {
    chomp $line;
    next if $line =~ /^#/;
    my @f = split /\t/, $line;
    next unless @f >= 9;
    my ($type, $attrs) = @f[2, 8];

    if ($type eq 'gene') {
        my ($gene_id)  = $attrs =~ /\bID=gene:([^;]+)/;
        next unless defined $gene_id;
        my ($name)     = $attrs =~ /\bName=([^;]+)/;
        my ($raw_desc) = $attrs =~ /\bdescription=([^;]+)/;
        my $desc = '';
        if (defined $raw_desc) {
            $desc = uri_unescape($raw_desc);
            $desc =~ s/\s*\[Source:[^\]]+\]//g;
        }
        $gene_name{$gene_id} = $name // $gene_id;
        $gene_desc{$gene_id} = $desc;
    }
    elsif ($type eq 'mRNA') {
        my ($tx_id)  = $attrs =~ /\bID=transcript:([^;]+)/;
        my ($gn_id)  = $attrs =~ /\bParent=gene:([^;]+)/;
        next unless defined $tx_id && defined $gn_id;
        $tx_to_gene{$tx_id} = $gn_id;
    }
}
close $GFF1;

my %printed;
open my $GFF2, '<', $gff or die "Can't open GFF: $gff $!\n";
while (my $line = <$GFF2>) {
    chomp $line;
    next if $line =~ /^#/;
    my @f = split /\t/, $line;
    next unless @f >= 9;
    my ($type, $attrs) = @f[2, 8];

    if ($type eq 'CDS') {
        my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
        my ($tx_id)   = $attrs =~ /\bParent=transcript:([^;]+)/;
        next unless defined $prot_id && defined $tx_id;

        my $gene_id = $tx_to_gene{$tx_id};
        next unless defined $gene_id;

        next if exists $printed{$prot_id};
        $printed{$prot_id}++;

        my $cds_id = "CDS:$prot_id";
        my $name   = $gene_name{$gene_id} // $gene_id;
        my $note   = $gene_desc{$gene_id} // '';

        print join("\t", $gene_id, 'gene',    '',        '',      $name, $note), "\n";
        print join("\t", $tx_id,   'mRNA',    $gene_id,  'gene',  $name, $note), "\n";
        print join("\t", $cds_id,  'CDS',     $tx_id,    'mRNA',  $name, $note), "\n";
        print join("\t", $prot_id, 'protein', $tx_id,    'mRNA',  $name, $note), "\n";
    }
}
close $GFF2;
