#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;

# What does selecting one ORF per gene actually COST, in information rather than
# in lines?
#
# select_longest_orf_geneset.pl reports dropped annotation LINES, and that number
# looks alarming -- 45.2% on Bipalium_kewense. But several ORFs of one gene usually
# carry the SAME domains, so most dropped lines are duplicates of ones that survive.
# Losing a duplicate costs nothing.
#
# This compares, per gene:
#     signatures reachable via the KEPT ORF
#     signatures reachable via ALL of that gene's ORFs
# and counts the genes where the second set is larger. That is the real loss: a
# gene that ends up with fewer distinct findings than the analysis produced for it.
#
# Usage:
#   measure_orf_filter_loss.pl --pep <base>.nt.transdecoder.pep
#                              --kept protein2gene.txt
#                              --annotations iprscan_results.tsv
#                              [--signature-column 5] [--examples 10]
#
# InterProScan TSV columns are 1-based: 1 seq_id, 5 signature_id, 12 interpro_id.
# Signature is the default because it is always populated; --signature-column 12
# measures loss at InterPro-entry level instead, which is usually the number a
# biologist cares about.

my ($pep, $kept_file, $annot, $sig_col, $examples);
$sig_col  = 5;
$examples = 10;

GetOptions(
    'pep=s'              => \$pep,
    'kept=s'             => \$kept_file,
    'annotations=s'      => \$annot,
    'signature-column=i' => \$sig_col,
    'examples=i'         => \$examples,
) or die usage();
die usage() unless $pep && $kept_file && $annot;

# ORF -> gene, for EVERY ORF (from the TransDecoder headers)
my %orf2gene;
open my $ph, '<', $pep or die "Cannot open $pep: $!\n";
while (my $line = <$ph>) {
    next unless $line =~ /^>(\S+)/;
    my $id = $1;
    my ($tx) = $line =~ /GENE\.(\S+?)~~/;
    next unless defined $tx;
    (my $gene = $tx) =~ s/\.\d+$//;
    $orf2gene{$id} = $gene;
}
close $ph;
die "No ORFs parsed from $pep\n" unless %orf2gene;

# The ORFs that survived the filter
my %kept;
open my $kh, '<', $kept_file or die "Cannot open $kept_file: $!\n";
while (my $line = <$kh>) {
    chomp $line;
    my ($id) = split /\t/, $line;
    $kept{$id} = 1 if defined $id && $id ne '';
}
close $kh;

# Signatures per gene, split by whether they arrived on a kept ORF
my (%all_sig, %kept_sig, $rows, $unknown_orf);
open my $ah, '<', $annot or die "Cannot open $annot: $!\n";
while (my $line = <$ah>) {
    next if $line =~ /^#/;
    chomp $line;
    my @col = split /\t/, $line;
    next unless @col >= $sig_col;
    my ($id, $sig) = ($col[0], $col[$sig_col - 1]);
    next if !defined $id || $id eq '' || $id eq 'seq_id';
    next if !defined $sig || $sig eq '' || $sig eq '-';
    my $gene = $orf2gene{$id};
    if (!defined $gene) { $unknown_orf++; next }
    $rows++;
    $all_sig{$gene}{$sig} = 1;
    $kept_sig{$gene}{$sig} = 1 if $kept{$id};
}
close $ah;

my ($genes, $lossless, $lossy, $total_lost, @worst) = (0, 0, 0, 0);
foreach my $gene (keys %all_sig) {
    $genes++;
    my $all  = scalar keys %{ $all_sig{$gene} };
    my $keptn = exists $kept_sig{$gene} ? scalar keys %{ $kept_sig{$gene} } : 0;
    if ($keptn >= $all) { $lossless++ }
    else {
        $lossy++;
        my $lost = $all - $keptn;
        $total_lost += $lost;
        push @worst, [$gene, $all, $keptn, $lost];
    }
}

printf "\nAnnotation rows considered : %d   (%d had an ORF id not in the pep file)\n",
    $rows, ($unknown_orf || 0);
printf "Genes with any annotation  : %d\n\n", $genes;
printf "  genes losing NOTHING     : %-8d (%.1f%%)  the kept ORF carries every signature\n",
    $lossless, ($genes ? 100 * $lossless / $genes : 0);
printf "  genes losing something   : %-8d (%.1f%%)\n",
    $lossy, ($genes ? 100 * $lossy / $genes : 0);
printf "  distinct signatures lost : %d\n\n", $total_lost;

if (@worst && $examples > 0) {
    @worst = sort { $b->[3] <=> $a->[3] } @worst;
    printf "Worst affected genes (all / kept / lost):\n";
    foreach my $row (@worst[0 .. ($#worst < $examples - 1 ? $#worst : $examples - 1)]) {
        printf "  %-32s %4d / %4d / %4d\n", @{$row}[0, 1, 2, 3];
    }
    print "\n";
}

print "Read it this way: the first percentage is how much of the filtering is FREE.\n"
    . "Dropped lines that duplicate a signature already on the kept ORF cost nothing.\n"
    . "Only the second group loses findings a user could otherwise have seen.\n\n";

sub usage {
    return <<"USAGE";
Usage: $0 --pep <base>.nt.transdecoder.pep --kept protein2gene.txt
          --annotations iprscan_results.tsv
          [--signature-column 5] [--examples 10]

Measures what one-ORF-per-gene filtering costs in DISTINCT SIGNATURES per gene,
rather than in annotation lines. Column 5 is the signature id, 12 the InterPro id.
USAGE
}
