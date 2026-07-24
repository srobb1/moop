#!/usr/bin/perl
use strict;
use warnings;

my ($prot_file, $cds_file) = @ARGV;
die "Usage: $0 <protein.aa.fa> <cds.nt.fa>\n" unless $prot_file && $cds_file;

# Build transcript_id -> CDS:protein_id map from protein FASTA.
# Ensembl-format headers: >XP_019850188.1 pep ... transcript:XM_019994629.1 ...
my %tx2cds;
open my $prot, '<', $prot_file or die "Cannot open $prot_file: $!\n";
while (<$prot>) {
    next unless /^>/;
    chomp;
    my ($prot_id) = /^>(\S+)/;
    my ($tx_id)   = /\btranscript:(\S+)/;
    next unless defined $prot_id && defined $tx_id;
    $tx2cds{$tx_id} = "CDS:$prot_id";
}
close $prot;

open my $in,  '<', $cds_file       or die "Cannot open $cds_file: $!\n";
open my $out, '>', "$cds_file.tmp" or die "Cannot write $cds_file.tmp: $!\n";
while (<$in>) {
    if (/^>(\S+)(.*)/) {
        my ($tx_id, $rest) = ($1, $2);
        my $cds_id = $tx2cds{$tx_id};
        if (defined $cds_id) {
            print $out ">$cds_id $tx_id$rest\n";
        } else {
            warn "WARNING: no protein for transcript $tx_id, keeping original header\n";
            print $out $_;
        }
    } else {
        print $out $_;
    }
}
close $in;
close $out;
rename "$cds_file.tmp", $cds_file or die "Cannot rename: $!\n";
