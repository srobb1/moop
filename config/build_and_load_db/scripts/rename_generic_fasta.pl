#!/usr/bin/perl
use strict;
use warnings;

# Appends a suffix to FASTA sequence IDs, but only when the ID would conflict
# (i.e., it already appears as an mRNA/transcript ID in the GFF or in an
# extra "conflict" FASTA).  IDs that are already unique are left as-is.
#
# Usage:
#   rename_generic_fasta.pl <fasta> <suffix> <gff> [<conflict_fasta>...]
#
# Examples:
#   rename_generic_fasta.pl cds.nt.fa     :cds genomic.gff
#   rename_generic_fasta.pl protein.aa.fa :pep genomic.gff cds.nt.fa

my ($fasta_file, $suffix, $gff_file, @extra_fastas) = @ARGV;
die "Usage: $0 <fasta> <suffix> <gff> [<conflict_fasta>...]\n"
    unless $fasta_file && defined $suffix && $gff_file;

# Collect conflict IDs: mRNA/transcript IDs from GFF
my %conflict;
open my $gff, '<', $gff_file or die "Cannot open $gff_file: $!\n";
while (<$gff>) {
    next if /^#/;
    my @f = split /\t/;
    next unless @f >= 9;
    my ($type, $attrs) = @f[2, 8];
    next unless $type eq 'mRNA' || $type eq 'transcript';
    my ($id) = $attrs =~ /\bID=([^;]+)/;
    $conflict{$id} = 1 if defined $id;
}
close $gff;

# Also collect IDs from any extra conflict FASTAs (e.g. the already-renamed CDS FASTA
# when processing the protein FASTA)
for my $ef (@extra_fastas) {
    open my $fh, '<', $ef or do { warn "Cannot open $ef: $!\n"; next };
    while (<$fh>) { $conflict{$1} = 1 if /^>(\S+)/ }
    close $fh;
}

open my $in,  '<', $fasta_file       or die "Cannot open $fasta_file: $!\n";
open my $out, '>', "$fasta_file.tmp" or die "Cannot write $fasta_file.tmp: $!\n";
while (<$in>) {
    if (/^>(\S+)(.*)/) {
        my ($id, $rest) = ($1, $2);
        if ($conflict{$id}) {
            print $out ">$id$suffix$rest\n";
        } else {
            print $out $_;
        }
    } else {
        print $out $_;
    }
}
close $in;
close $out;
rename "$fasta_file.tmp", $fasta_file or die "Cannot rename: $!\n";
