#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;

# Generate transcript2gene.txt and/or protein2gene.txt from FASTA files.
#
# Gene IDs are derived by stripping isoform suffixes from sequence IDs:
#   protein:    strip .pN, then .tN, then .N  (e.g. prefix.gcN.1.p1 -> prefix.gcN)
#   transcript: strip .N                      (e.g. prefix.gcN.1    -> prefix.gcN)
#
# If the protein header contains a "GENE.X~~Y" field (EvidentialGene format),
# that is used in preference to the suffix-stripping rule.
#
# Usage:
#   make_t2g_from_fasta.pl [--transcript <fasta>] [--protein <fasta>] [--outdir <dir>]
#
# Output files are written to <outdir> (default: current directory):
#   transcript2gene.txt
#   protein2gene.txt

my ($tx_fasta, $prot_fasta, $outdir);
$outdir = '.';

GetOptions(
    'transcript=s' => \$tx_fasta,
    'protein=s'    => \$prot_fasta,
    'outdir=s'     => \$outdir,
) or die usage();

die usage() unless $tx_fasta || $prot_fasta;

mkdir $outdir unless -d $outdir;

if ($tx_fasta) {
    my $out = "$outdir/transcript2gene.txt";
    open my $fh,  '<', $tx_fasta or die "Cannot open $tx_fasta: $!\n";
    open my $out_fh, '>', $out   or die "Cannot write $out: $!\n";
    my $n = 0;
    while (<$fh>) {
        next unless /^>(\S+)/;
        my $id   = $1;
        my $gene = tx_to_gene($id);
        print $out_fh "$id\t$gene\n";
        $n++;
    }
    close $fh;
    close $out_fh;
    print "Written: $out ($n transcripts)\n";
}

if ($prot_fasta) {
    my $out = "$outdir/protein2gene.txt";
    open my $fh,  '<', $prot_fasta or die "Cannot open $prot_fasta: $!\n";
    open my $out_fh, '>', $out     or die "Cannot write $out: $!\n";
    my $n = 0;
    while (<$fh>) {
        next unless /^>(\S+)(.*)/;
        my ($id, $desc) = ($1, $2);
        my $gene;
        # EvidentialGene: GENE.X~~Y in description encodes transcript; strip to gene
        if ($desc =~ /GENE\.(\S+)~~/) {
            $gene = tx_to_gene($1);
        } else {
            $gene = prot_to_gene($id);
        }
        print $out_fh "$id\t$gene\n";
        $n++;
    }
    close $fh;
    close $out_fh;
    print "Written: $out ($n proteins)\n";
}

sub tx_to_gene {
    my ($id) = @_;
    $id =~ s/\.\d+$//;
    return $id;
}

sub prot_to_gene {
    my ($id) = @_;
    $id =~ s/\.p\d+$//;   # strip protein isoform marker
    $id =~ s/\.t\d+$//;   # strip transcript marker (e.g. Turritopsis .t1)
    $id =~ s/\.\d+$//;    # strip remaining numeric suffix
    return $id;
}

sub usage {
    return "Usage: $0 [--transcript <fasta>] [--protein <fasta>] [--outdir <dir>]\n"
         . "  At least one of --transcript or --protein is required.\n";
}
