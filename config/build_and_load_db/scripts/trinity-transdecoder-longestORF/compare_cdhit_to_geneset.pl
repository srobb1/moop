#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;

# Compare cd-hit-est's per-cluster representative pick against
# select_longest_orf_geneset.pl's per-gene pick (longest CDS wins), to see
# how often a sequence-identity clustering agrees with a gene-aware,
# ORF-length-aware one.
#
# Requires cd-hit-est to have been run with -d 0 (or any -d large enough to
# not truncate ids) -- cd-hit's default -d 20 clips ids in the .clstr file
# to 20 characters, which silently collapses distinct isoforms whose ids
# differ only after that point (true here: every id in bkew.kc1.nt is
# 21-24 characters, so the default would corrupt 100% of records).
#
# Two things are reported:
#   1. Containment: does a gene's full isoform set (per --t2g) land inside a
#      single cd-hit cluster, or does cd-hit split it across several?
#   2. Agreement: for genes that DO land in a single cluster, is cd-hit's
#      cluster representative the same transcript select_longest_orf_geneset.pl
#      picked?
# Plus a reverse check: how often does a single cd-hit cluster mix
# transcripts from more than one gene (a merge cd-hit made that the gene
# map disagrees with).
#
# Usage:
#   compare_cdhit_to_geneset.pl --clstr  bkew.kc1.nt.cdhit.clstr \
#                                --t2g    bkew.kc1_transcript2gene.txt \
#                                --geneset transcript2gene.txt

my ($clstr_file, $t2g_file, $geneset_file, $max_examples);
$max_examples = 15;

GetOptions(
    'clstr=s'        => \$clstr_file,
    't2g=s'          => \$t2g_file,
    'geneset=s'      => \$geneset_file,
    'max-examples=i' => \$max_examples,
) or die usage();
die usage() unless $clstr_file && $t2g_file && $geneset_file;
foreach my $f ($clstr_file, $t2g_file, $geneset_file) { die "Cannot read $f\n" unless -s $f }

# ── Full transcript -> gene map (every transcript, not just kept ones) ──────
my (%gene_of, %gene_tx);
open my $th, '<', $t2g_file or die "Cannot open $t2g_file: $!\n";
while (my $line = <$th>) {
    chomp $line;
    my ($tx, $gene) = split /\t/, $line;
    next unless defined $tx && defined $gene && length $gene;
    $gene_of{$tx} = $gene;
    push @{ $gene_tx{$gene} }, $tx;
}
close $th;
die "No usable pairs in $t2g_file\n" unless %gene_of;

# ── select_longest_orf_geneset.pl's chosen representative transcript per gene ──
my %our_rep_tx;
open my $gh, '<', $geneset_file or die "Cannot open $geneset_file: $!\n";
while (my $line = <$gh>) {
    chomp $line;
    my ($tx, $gene) = split /\t/, $line;
    next unless defined $tx && defined $gene;
    $our_rep_tx{$gene} = $tx;
}
close $gh;
die "No usable pairs in $geneset_file\n" unless %our_rep_tx;

# ── Parse the cd-hit .clstr file ─────────────────────────────────────────────
my (%tx_cluster, %cluster_rep, $truncation_warned);
my $cid;
open my $ch, '<', $clstr_file or die "Cannot open $clstr_file: $!\n";
while (my $line = <$ch>) {
    chomp $line;
    if ($line =~ /^>Cluster (\d+)/) { $cid = $1; next }
    next unless $line =~ />(\S+?)\.\.\.\s*(\*|at)/;
    my ($tx, $mark) = ($1, $2);
    $tx_cluster{$tx} = $cid;
    $cluster_rep{$cid} = $tx if $mark eq '*';
}
close $ch;
die "No usable entries parsed from $clstr_file\n" unless %tx_cluster;

# Sanity check: if every id in the t2g map is longer than cd-hit's default
# truncation (20 chars) but none of them are found in the .clstr file at
# their full length, the .clstr was very likely made without -d 0.
my ($checked, $found) = (0, 0);
foreach my $tx (keys %gene_of) {
    next if length($tx) <= 19;   # short ids are never truncated, uninformative here
    $checked++;
    $found++ if exists $tx_cluster{$tx};
    last if $checked >= 200;
}
if ($checked && !$found) {
    warn "WARNING: none of the first $checked long transcript ids (>19 chars) were found\n"
       . "         at full length in $clstr_file. This usually means cd-hit-est was run\n"
       . "         WITHOUT -d 0, so ids were truncated and this comparison is unreliable.\n"
       . "         Rerun cd-hit-est with -d 0 and regenerate the .clstr file.\n\n";
}

# ── Per-gene containment + agreement ────────────────────────────────────────
my ($single_cluster, $split_cluster, $agree, $disagree, $no_cluster_info) = (0, 0, 0, 0, 0);
my (@disagree_examples, @split_examples);

foreach my $gene (sort keys %our_rep_tx) {
    my @tx = @{ $gene_tx{$gene} // [] };
    my %clusters_seen;
    foreach my $t (@tx) {
        $clusters_seen{ $tx_cluster{$t} } = 1 if exists $tx_cluster{$t};
    }
    if (!%clusters_seen) { $no_cluster_info++; next }

    my @cl = keys %clusters_seen;
    if (@cl == 1) {
        $single_cluster++;
        my $cdhit_pick = $cluster_rep{ $cl[0] };
        my $our_pick   = $our_rep_tx{$gene};
        if (defined $cdhit_pick && $cdhit_pick eq $our_pick) {
            $agree++;
        } else {
            $disagree++;
            push @disagree_examples, [$gene, $our_pick, $cdhit_pick // 'NA', scalar @tx]
                if @disagree_examples < $max_examples;
        }
    } else {
        $split_cluster++;
        push @split_examples, [$gene, scalar @tx, scalar @cl]
            if @split_examples < $max_examples;
    }
}
my $n_genes_ours = scalar keys %our_rep_tx;
die "No genes from $geneset_file had any transcript with cluster info\n"
    unless $single_cluster + $split_cluster;

# ── Reverse check: clusters mixing more than one gene ───────────────────────
my %cluster_genes;
foreach my $tx (keys %tx_cluster) {
    my $gene = $gene_of{$tx};
    next unless defined $gene;
    $cluster_genes{ $tx_cluster{$tx} }{$gene} = 1;
}
my $mixed_clusters = grep { scalar keys %{ $cluster_genes{$_} } > 1 } keys %cluster_genes;

# ── Report ───────────────────────────────────────────────────────────────────
print "=== Scope ===\n";
printf "Total genes in %s: %d\n", $t2g_file, scalar keys %gene_tx;
printf "Genes in %s (kept by select_longest_orf_geneset.pl): %d\n", $geneset_file, $n_genes_ours;

print "\n=== Containment: does a gene's isoforms all land in ONE cd-hit cluster? ===\n";
printf "Single cluster:          %d (%.1f%%)\n", $single_cluster, 100 * $single_cluster / $n_genes_ours;
printf "Split across clusters:   %d (%.1f%%)\n", $split_cluster,  100 * $split_cluster  / $n_genes_ours;
printf "No cluster info found:   %d\n", $no_cluster_info;

if ($single_cluster) {
    print "\n=== Among single-cluster genes: does cd-hit's pick match select_longest_orf_geneset.pl's? ===\n";
    printf "Agree:     %d (%.1f%%)\n", $agree,    100 * $agree    / $single_cluster;
    printf "Disagree:  %d (%.1f%%)\n", $disagree, 100 * $disagree / $single_cluster;
}

if (@disagree_examples) {
    print "\n=== Sample disagreements (gene, our_pick, cdhit_pick, n_isoforms) ===\n";
    print join("\t", @$_), "\n" for @disagree_examples;
}
if (@split_examples) {
    print "\n=== Sample split-across-clusters genes (gene, n_isoforms, n_clusters) ===\n";
    print join("\t", @$_), "\n" for @split_examples;
}

printf "\n=== Reverse check ===\n";
printf "cd-hit clusters mixing transcripts from >1 gene: %d of %d clusters\n",
    $mixed_clusters, scalar keys %cluster_genes;

sub usage {
    return <<"USAGE";
Usage: $0 --clstr <cdhit.clstr> --t2g <transcript2gene.txt> --geneset <geneset transcript2gene.txt>
          [--max-examples N]

--clstr    cd-hit-est's .clstr output. MUST have been generated with -d 0
           (or -d >= longest id length) -- cd-hit's default truncates ids
           to 20 characters, which corrupts this comparison if any
           transcript id is longer than that.
--t2g      the full transcript\\tgene map (every transcript, not just kept
           ones) -- the same file passed to select_longest_orf_geneset.pl --t2g.
--geneset  the transcript2gene.txt select_longest_orf_geneset.pl wrote --
           one line per kept gene: representative_transcript\\tgene.
USAGE
}
