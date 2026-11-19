#!/usr/bin/env perl
# minimap2 SAM -> GFF3 (cDNA_match + cDNA_match_part), for step 02.
#
#   minimap2_sam2gff.pl [--min-identity PCT] -i in.sam [-o out.gff]
#
# A copy of AGAT 1.2.0's agat_convert_minimap2_bam2gff.pl (NBISweden/AGAT, GPL-3.0; itself
# from Brian Haas's Sam_to_gtf.pl in PASA) with two changes:
#   1. --min-identity. AGAT hardcodes "next if $per_id < 90.0" (its comment says 80%), with
#      per_id = 100 - NM / aligned bases * 100. NM counts every inserted and deleted base, so
#      one long indel drops an alignment that is otherwise near identical: 307 of 32,301
#      Nematostella NV2 CDS alignments to RS_101 (2026-11-18), 20 of the 25 under 50% being
#      >= 95% identical outside their indels. Default 90 = AGAT's behaviour; 0 = keep all.
#   2. GFF lines are printed directly instead of through AGAT/BioPerl, so it runs with any
#      perl (no AGAT container). With --min-identity 90 the output is identical to AGAT's.
# SAM only (BAM: samtools view first). Like AGAT, only primary alignments (flag 0 or 16)
# are converted; secondary and supplementary ones are skipped.
#
# This file is licensed under the GNU General Public License v3.0 (as AGAT is), not the
# repository's MIT license: https://www.gnu.org/licenses/gpl-3.0.html
use strict;
use warnings;
use Getopt::Long;

my ($opt_in, $opt_out, $opt_help);
my $min_identity = 90;
GetOptions('i|input=s' => \$opt_in, 'o|out|output=s' => \$opt_out,
           'min-identity=f' => \$min_identity, 'h|help' => \$opt_help)
    or die "usage: $0 [--min-identity PCT] -i in.sam [-o out.gff]\n";
if ($opt_help || !defined $opt_in) {
    print STDERR "usage: $0 [--min-identity PCT] -i in.sam [-o out.gff]\n";
    exit($opt_help ? 0 : 1);
}

open my $in, '<:encoding(UTF-8)', $opt_in or die "Could not open file '$opt_in' $!";
my $out = \*STDOUT;
if (defined $opt_out) { open $out, '>', $opt_out or die "Could not write '$opt_out' $!" }
print {$out} "##gff-version 3\n";

my $counter = 0;
while (my $line = <$in>) {
    next if $line =~ /^\@/;
    $line =~ s/\n//; $line =~ s/\r//;
    my @sam = split /\t+/, $line;
    my %entry = (qname => $sam[0], flag => $sam[1], rname => $sam[2], pos => $sam[3],
                 cigar => $sam[5], seq => $sam[9]);

    my $as = '.';
    my ($as_field) = grep { /^AS:/ } @sam;
    $as = (split /:/, $as_field)[2] if $as_field;

    next if $entry{seq} eq '*';          # no sequence
    next if $entry{flag} & 0x0004;       # unmapped

    my $num_mismatches = 0;
    $num_mismatches = $1 if join("\t", @sam) =~ /NM:i:(\d+)/;

    my $strand;
    if    ($entry{flag} == 0)  { $strand = '+' }
    elsif ($entry{flag} == 16) { $strand = '-' }
    else                       { next }  # secondary, supplementary, ...
    $entry{strand} = $strand;

    my ($genome_coords, $query_coords) = get_aligned_coords(%entry);

    my $align_len = 0;
    for my $coordset (@$genome_coords) { $align_len += abs($coordset->[1] - $coordset->[0]) + 1 }
    next if $align_len == 0;

    my $per_id = sprintf('%.1f', 100 - $num_mismatches / $align_len * 100);
    next if $per_id < $min_identity;

    my $id_l1 = 'match' . ++$counter;
    my $id_l2 = "$id_l1.p1";

    my @pairs;
    while (@$genome_coords) {
        my ($g_lend, $g_rend) = @{ shift @$genome_coords };
        my ($t_lend, $t_rend) = sort { $a <=> $b } @{ shift @$query_coords };
        push @pairs, [$g_lend, $g_rend, $t_lend, $t_rend];
    }

    # blocks closer than 10 bp on the genome (small deletions) are merged, as in AGAT
    my @merged = (shift @pairs);
    my $merge_dist = 10;
    while (@pairs) {
        my $cur = shift @pairs;
        my $last = $merged[-1];
        if ($cur->[0] - $last->[1] <= $merge_dist) {
            $last->[1] = $cur->[1];
            if ($strand eq '+') { $last->[3] = $cur->[3] } else { $last->[2] = $cur->[2] }
        } else {
            push @merged, $cur;
        }
    }

    print {$out} join("\t", $entry{rname}, 'est2genome', 'cDNA_match', $merged[0][0], $merged[-1][1],
                      $as, $strand, '.', "ID=$id_l1;aligned_identity=$per_id"), "\n";
    for my $part (@merged) {
        my ($g_lend, $g_rend, $t_lend, $t_rend) = @$part;
        print {$out} join("\t", $entry{rname}, 'est2genome', 'cDNA_match_part', $g_lend, $g_rend,
                          $as, $strand, '.', "ID=$id_l2;Parent=$id_l1;Target=$entry{qname} $t_lend $t_rend"), "\n";
    }
}
close $in;
close $out if defined $opt_out;

sub get_aligned_coords {
    my %entry = @_;
    my $genome_lend = $entry{pos} - 1;
    my $query_lend = 0;
    my (@genome_coords, @query_coords);
    my $cigar = $entry{cigar};
    while ($cigar =~ /(\d+)([A-Z])/g) {
        my ($len, $code) = ($1, $2);
        die "Error, cannot parse cigar code [$code] " unless $code =~ /^[MSDNIH]$/;
        if ($code eq 'M') {
            my $genome_rend = $genome_lend + $len;
            my $query_rend = $query_lend + $len;
            push @genome_coords, [$genome_lend + 1, $genome_rend];
            push @query_coords, [$query_lend + 1, $query_rend];
            ($genome_lend, $query_lend) = ($genome_rend, $query_rend);
        } elsif ($code eq 'D' || $code eq 'N') {
            $genome_lend += $len;
        } else {            # I, S, H: query bases not on the genome
            $query_lend += $len;
        }
    }
    if ($entry{strand} eq '-') {     # query coordinates on the reverse strand
        my $read_len = length $entry{seq} or die "Error, no read length obtained from entry";
        @query_coords = map { [$read_len - $_->[0] + 1, $read_len - $_->[1] + 1] } @query_coords;
    }
    return (\@genome_coords, \@query_coords);
}
