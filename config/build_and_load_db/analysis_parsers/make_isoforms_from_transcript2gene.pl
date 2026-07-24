#!/usr/bin/perl
use strict;
use warnings;

# Build isoforms.tsv from a transcript2gene.txt mapping file.
# Output format matches make_isoforms_from_gff.pl:
#   tx1;tx2;...	None	gene_id

my $t2g_file = shift or die "Usage: $0 transcript2gene.txt\n";

my %groups;
open my $fh, '<', $t2g_file or die "Can't open $t2g_file: $!\n";
while (my $line = <$fh>) {
    chomp $line;
    next if $line =~ /^#/;
    my ($tx_id, $gene_id) = split /\t/, $line;
    next unless defined $tx_id && defined $gene_id && $tx_id ne '' && $gene_id ne '';
    $groups{$gene_id}{$tx_id}++;
}
close $fh;

for my $gene (sort keys %groups) {
    my @children = sort keys %{ $groups{$gene} };
    print join("\t", join(';', @children), 'None', $gene), "\n";
}
