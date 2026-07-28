#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;

# Reduce a TransDecoder transcriptome to ONE transcript and ONE ORF per gene, and
# write everything a MOOP gene set needs from it.
#
# Input is TransDecoder's own output, unmodified:
#   <base>.nt                    assembled transcripts
#   <base>.nt.transdecoder.pep   ORF proteins   ids like  tx.p1
#   <base>.nt.transdecoder.cds   ORF CDS        same ids
#
# Selection: for each GENE, the ORF with the longest CDS wins, and the transcript
# that ORF came from becomes the gene's representative. So 1 gene = 1 mRNA = 1 CDS,
# which is what lets each gene be a single clean reference sequence.
#
# The gene comes from the transcript id with its trailing ".N" isoform marker
# removed -- the same rule make_t2g_from_fasta.pl uses. The transcript comes from
# TransDecoder's own "GENE.<tx>~~<orf>" field, cross-checked against the coordinate
# suffix "<tx>:start-end(strand)" in the same header; a header where those two
# disagree is reported rather than guessed at.
#
# ⚠️  WHAT FILTERING COSTS, reported at the end and worth reading before you load:
# the analysis ran on EVERY ORF of EVERY isoform. Annotations on ORFs that are not
# selected have no feature to attach to and will be dropped by the loader. Pass
# --annotations <file> to get that count as a number instead of a surprise. This is
# the one direction that cannot be undone without rebuilding, so it is stated
# loudly rather than left to be discovered later.
#
# Nothing here renames anything: ids are written exactly as TransDecoder produced
# them. MOOP's :pep/:cds naming is applied later, to MOOP's own copies.
#
# Usage:
#   select_longest_orf_geneset.pl --transcripts bkew.kc1.nt \
#                                 --pep  bkew.kc1.nt.transdecoder.pep \
#                                 --cds  bkew.kc1.nt.transdecoder.cds \
#                                 [--gff bkew.kc1.nt.transdecoder.gff3] \
#                                 [--annotations iprscan_results.tsv] \
#                                 [--outdir .] [--ref-suffix .ref]

my ($tx_fa, $pep_fa, $cds_fa, $td_gff, $annot, $outdir, $ref_suffix);
$outdir     = '.';
$ref_suffix = '.ref';

GetOptions(
    'transcripts=s' => \$tx_fa,
    'pep=s'         => \$pep_fa,
    'cds=s'         => \$cds_fa,
    'gff=s'         => \$td_gff,
    'annotations=s' => \$annot,
    'outdir=s'      => \$outdir,
    'ref-suffix=s'  => \$ref_suffix,
) or die usage();
die usage() unless $tx_fa && $pep_fa && $cds_fa;
foreach my $f ($tx_fa, $pep_fa, $cds_fa) { die "Cannot read $f\n" unless -s $f }
mkdir $outdir unless -d $outdir;

my %tx_seq  = read_fasta($tx_fa);
my %pep     = read_fasta($pep_fa);
my %cds     = read_fasta($cds_fa);

# ── Parse ORF headers ────────────────────────────────────────────────────────
my (%orf, $bad_header);
open my $ph, '<', $pep_fa or die "Cannot open $pep_fa: $!\n";
while (my $line = <$ph>) {
    next unless $line =~ /^>/;
    chomp $line;
    my ($id)          = $line =~ /^>(\S+)/;
    my ($tx_gene)     = $line =~ /GENE\.(\S+?)~~/;
    my ($ctx, $s, $e, $strand) = $line =~ /(\S+):(\d+)-(\d+)\(([+-])\)\s*$/;
    if (!defined $id || !defined $tx_gene || !defined $ctx || $ctx ne $tx_gene) {
        $bad_header++;
        next;
    }
    $orf{$id} = { tx => $tx_gene, start => $s, end => $e, strand => $strand };
}
close $ph;
die "No usable ORF headers in $pep_fa\n" unless %orf;

# ── Pick the longest CDS per gene ────────────────────────────────────────────
my (%best, $no_cds);
foreach my $id (sort keys %orf) {
    my $tx = $orf{$id}{tx};
    (my $gene = $tx) =~ s/\.\d+$//;          # strip the isoform marker
    my $len = defined $cds{$id} ? length $cds{$id} : 0;
    if (!$len) { $no_cds++; next; }
    if (!exists $best{$gene} || $len > $best{$gene}{len}) {
        $best{$gene} = { orf => $id, tx => $tx, len => $len };
    }
}
die "No ORFs had a CDS sequence in $cds_fa\n" unless %best;

# ── Write the filtered gene set ──────────────────────────────────────────────
my @genes = sort keys %best;
open my $o_tx,  '>', "$outdir/transcript.nt.fa" or die $!;
open my $o_pep, '>', "$outdir/protein.aa.fa"    or die $!;
open my $o_cds, '>', "$outdir/cds.nt.fa"        or die $!;
open my $o_t2g, '>', "$outdir/transcript2gene.txt" or die $!;
open my $o_p2g, '>', "$outdir/protein2gene.txt"    or die $!;
open my $o_c2g, '>', "$outdir/cds2gene.txt"        or die $!;
open my $o_gen, '>', "$outdir/genome.fa"        or die $!;
open my $o_gff, '>', "$outdir/genes.gff"        or die $!;

print $o_gff "##gff-version 3\n";
print $o_gff "# One reference sequence per gene: its representative transcript.\n";
print $o_gff "# Coordinates are REAL -- CDS bounds come from TransDecoder's own header.\n";

my $missing_tx = 0;
foreach my $gene (@genes) {
    my ($orf_id, $tx) = @{ $best{$gene} }{qw(orf tx)};
    my $seq = $tx_seq{$tx};
    if (!defined $seq) { $missing_tx++; next; }
    my $len   = length $seq;
    my $seqid = $tx . $ref_suffix;      # matches the existing .ref convention
    my ($s, $e, $strand) = @{ $orf{$orf_id} }{qw(start end strand)};
    $e = $len if $e > $len;

    write_fa($o_tx,  $tx,     $seq);
    write_fa($o_pep, $orf_id, $pep{$orf_id}) if defined $pep{$orf_id};
    write_fa($o_cds, $orf_id, $cds{$orf_id}) if defined $cds{$orf_id};
    write_fa($o_gen, $seqid,  $seq);

    print $o_t2g "$tx\t$gene\n";
    print $o_p2g "$orf_id\t$gene\n";
    print $o_c2g "$orf_id\t$gene\n";

    print $o_gff join("\t", $seqid, 'transdecoder', 'gene',       1,  $len, '.', '+',     '.',
                      "ID=$gene;Name=$gene"), "\n";
    print $o_gff join("\t", $seqid, 'transdecoder', 'mRNA',       1,  $len, '.', '+',     '.',
                      "ID=$tx;Parent=$gene"), "\n";
    print $o_gff join("\t", $seqid, 'transdecoder', 'exon',       1,  $len, '.', '+',     '.',
                      "Parent=$tx"), "\n";
    print $o_gff join("\t", $seqid, 'transdecoder', 'CDS',       $s,  $e,   '.', $strand, '0',
                      "ID=$orf_id;Parent=$tx"), "\n";
}
close $_ for ($o_tx, $o_pep, $o_cds, $o_t2g, $o_p2g, $o_c2g, $o_gen, $o_gff);

my $total_orfs = scalar keys %orf;
my $kept       = scalar @genes;
printf "Genes: %d   ORFs in input: %d   kept: %d   dropped: %d\n",
    $kept, $total_orfs, $kept, $total_orfs - $kept;
print "Written to $outdir/: transcript.nt.fa protein.aa.fa cds.nt.fa\n";
print "                     transcript2gene.txt protein2gene.txt cds2gene.txt\n";
print "                     genome.fa genes.gff\n";
warn "NOTE: $bad_header ORF header(s) could not be parsed and were skipped\n" if $bad_header;
warn "NOTE: $no_cds ORF(s) had no CDS sequence\n"                             if $no_cds;
warn "NOTE: $missing_tx representative transcript(s) missing from $tx_fa\n"   if $missing_tx;

# ── What filtering costs, as a number ────────────────────────────────────────
if ($annot && -s $annot) {
    my %kept_orf = map { $best{$_}{orf} => 1 } @genes;
    my ($lines, $orphan, %orphan_ids) = (0, 0);
    open my $ah, '<', $annot or die "Cannot open $annot: $!\n";
    while (my $line = <$ah>) {
        next if $line =~ /^#/ || $line !~ /\S/;
        my ($id) = split /\t/, $line;
        next unless defined $id && $id ne '' && $id ne 'seq_id';
        $lines++;
        next if $kept_orf{$id};
        $orphan++; $orphan_ids{$id} = 1;
    }
    close $ah;
    printf "\n!! FILTERING COST, measured against %s:\n", $annot;
    printf "!!   %d of %d annotation line(s) (%.1f%%) are on ORFs this filter DROPS\n",
        $orphan, $lines, ($lines ? 100 * $orphan / $lines : 0);
    printf "!!   across %d distinct ORF id(s). They will not attach to any feature.\n",
        scalar keys %orphan_ids;
    print  "!!   This is the irreversible direction -- undoing it means rebuilding.\n\n";
} else {
    print "\nNOTE: pass --annotations <iprscan_results.tsv> to measure how many\n"
        . "      annotation lines this filter would orphan, before you load.\n\n";
}

sub read_fasta {
    my ($file) = @_;
    my (%seq, $id);
    open my $in, '<', $file or die "Cannot open $file: $!\n";
    while (my $line = <$in>) {
        chomp $line;
        if ($line =~ /^>(\S+)/) { $id = $1; $seq{$id} = ''; next }
        next unless defined $id;
        $line =~ s/\s+//g;
        $seq{$id} .= $line;
    }
    close $in;
    return %seq;
}

sub write_fa {
    my ($fh, $id, $seq) = @_;
    return unless defined $seq;
    print $fh ">$id\n";
    for (my $p = 0; $p < length $seq; $p += 60) {
        print $fh substr($seq, $p, 60), "\n";
    }
}

sub usage {
    return <<"USAGE";
Usage: $0 --transcripts <base>.nt --pep <base>.nt.transdecoder.pep
          --cds <base>.nt.transdecoder.cds
          [--gff <base>.nt.transdecoder.gff3] [--annotations iprscan_results.tsv]
          [--outdir DIR] [--ref-suffix .ref]

Reduces a TransDecoder transcriptome to one transcript and one ORF per gene
(longest CDS wins) and writes the FASTAs, mapping files, reference genome and
GFF3 a MOOP gene set needs. Pass --annotations to measure what the filter drops.
USAGE
}
