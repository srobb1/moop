#!/usr/bin/perl
use strict;
use warnings;

my ($gff_file, $fasta_file) = @ARGV;
die "Usage: $0 <gff> <fasta>\n" unless $gff_file && $fasta_file;

# Build (locus_tag, protein_id) -> CDS ID map from GFF.
# Using both keys handles:
#   - multi-isoform eukaryotes: same locus_tag, different protein_ids
#   - multi-locus bacteria: same protein_id, different locus_tags
my %key2id;
open my $gff, '<', $gff_file or die "Cannot open $gff_file: $!\n";
while (<$gff>) {
    next if /^#/;
    my @f = split /\t/;
    next unless @f >= 9 && $f[2] eq 'CDS';
    my ($id)  = $f[8] =~ /\bID=([^;]+)/;
    my ($lt)  = $f[8] =~ /\blocus_tag=([^;]+)/;
    my ($pid) = $f[8] =~ /\bprotein_id=([^;]+)/;
    next unless defined $id && defined $lt && defined $pid;
    $key2id{"$lt\t$pid"} = $id;
}
close $gff;

open my $in,  '<', $fasta_file       or die "Cannot open $fasta_file: $!\n";
open my $out, '>', "$fasta_file.tmp" or die "Cannot write $fasta_file.tmp: $!\n";
while (<$in>) {
    if (/^>(.*\[locus_tag=([^\]]+)\].*\[protein_id=([^\]]+)\].*)/) {
        my ($rest, $lt, $pid) = ($1, $2, $3);
        my $id = $key2id{"$lt\t$pid"};
        if (defined $id) {
            print $out ">$id $rest\n";
        } else {
            warn "WARNING: no GFF CDS ID for locus_tag=$lt protein_id=$pid, keeping original header\n";
            print $out $_;
        }
    } else {
        print $out $_;
    }
}
close $in;
close $out;
rename "$fasta_file.tmp", $fasta_file or die "Cannot rename: $!\n";
