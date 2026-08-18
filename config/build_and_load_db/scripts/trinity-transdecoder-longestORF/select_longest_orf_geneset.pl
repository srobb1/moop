#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;

# Reduce a TransDecoder transcriptome to ONE transcript and ONE ORF per gene, and
# write everything a MOOP gene set needs from it.
#
# Input is TransDecoder's own output directory, unmodified. TransDecoder always
# names its output from the transcript file it was run on, so given the dir this
# script finds the set itself:
#   <base>                        assembled transcripts (the file TransDecoder ran on)
#   <base>.transdecoder.pep       ORF proteins   ids like  tx.p1
#   <base>.transdecoder.cds       ORF CDS        same ids
#
# Selection: for each GENE, the ORF with the longest CDS wins, and the transcript
# that ORF came from becomes the gene's representative. So 1 gene = 1 mRNA = 1 CDS,
# which is what lets each gene be a single clean reference sequence.
#
# The gene a transcript belongs to comes from --t2g <file>, a transcript\tgene
# mapping made independently of this script (e.g. make_t2g_from_fasta.pl). This
# is the authoritative grouping -- naming conventions differ across assemblies,
# so nothing here should have to guess a gene id from a transcript id's shape.
# If --t2g is omitted, the fallback is the trailing ".N" isoform marker stripped
# off the transcript id (the convention this pipeline has used so far), but
# that only works while every input follows that one convention.
#
# The transcript comes from TransDecoder's own "GENE.<tx>~~<orf>" field,
# cross-checked against the coordinate suffix "<tx>:start-end(strand)" in the
# same header; a header where those two disagree is reported rather than
# guessed at.
#
# transcript.nt.fa, cds.nt.fa, protein.aa.fa and protein.stops.aa.fa are all
# keyed by the SAME id -- the representative transcript's id ($tx) -- not
# TransDecoder's own ORF id (tx.p1, tx.p2, ...), so a gene's transcript, CDS
# and protein records line up directly across the three files. MOOP's
# :pep/:cds naming is applied later, to MOOP's own copies.
#
# protein.aa.fa has TransDecoder's trailing "*" stop-codon symbols stripped,
# for tools that choke on them; protein.stops.aa.fa is the same sequences
# with the "*" left in, for anything that wants to see the stop explicitly.
#
# Usage:
#   select_longest_orf_geneset.pl --dir /path/to/transdecoder_output \
#                                 [--t2g transcript2gene.txt] \
#                                 [--outdir .] [--ref-suffix .ref]

my ($tx_fa, $pep_fa, $cds_fa, $td_dir, $t2g_file, $outdir, $ref_suffix);
$outdir     = '.';
$ref_suffix = '.ref';

GetOptions(
    'dir=s'         => \$td_dir,
    't2g=s'         => \$t2g_file,
    'outdir=s'      => \$outdir,
    'ref-suffix=s'  => \$ref_suffix,
) or die usage();
die usage() unless $td_dir;
die "Not a directory: $td_dir\n" unless -d $td_dir;
die "Cannot read $t2g_file\n" if $t2g_file && !-s $t2g_file;

opendir(my $dh, $td_dir) or die "Cannot open $td_dir: $!\n";
my @pep_files = grep { /\.transdecoder\.pep$/ } readdir $dh;
closedir $dh;
die "No *.transdecoder.pep file found in $td_dir\n" unless @pep_files;
die "Multiple *.transdecoder.pep files found in $td_dir (@pep_files) -- "
  . "point --dir at a directory with just one TransDecoder output set\n"
    if @pep_files > 1;

(my $base = $pep_files[0]) =~ s/\.transdecoder\.pep$//;
$pep_fa = "$td_dir/$pep_files[0]";
$cds_fa = "$td_dir/$base.transdecoder.cds";
$tx_fa  = "$td_dir/$base";
foreach my $f ($tx_fa, $pep_fa, $cds_fa) { die "Cannot read $f\n" unless -s $f }
mkdir $outdir unless -d $outdir;

my %tx_seq  = read_fasta($tx_fa);
my %pep     = read_fasta($pep_fa);
my %cds     = read_fasta($cds_fa);
my %t2g     = $t2g_file ? read_t2g($t2g_file) : ();
die "No usable transcript\\tgene pairs in $t2g_file\n" if $t2g_file && !%t2g;

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
my (%best, $no_cds, $no_gene);
foreach my $id (sort keys %orf) {
    my $tx = $orf{$id}{tx};
    my $gene;
    if ($t2g_file) {
        $gene = $t2g{$tx};
        if (!defined $gene) { $no_gene++; next; }
    } else {
        ($gene = $tx) =~ s/\.\d+$//;          # strip the isoform marker
    }
    my $len = defined $cds{$id} ? length $cds{$id} : 0;
    if (!$len) { $no_cds++; next; }
    if (!exists $best{$gene} || $len > $best{$gene}{len}) {
        $best{$gene} = { orf => $id, tx => $tx, len => $len };
    }
}
die "No ORFs had a CDS sequence in $cds_fa\n" unless %best;

# ── Write the filtered gene set ──────────────────────────────────────────────
my @genes = sort keys %best;
open my $o_tx,    '>', "$outdir/transcript.nt.fa"    or die $!;
open my $o_pep,   '>', "$outdir/protein.aa.fa"       or die $!;
open my $o_peps,  '>', "$outdir/protein.stops.aa.fa" or die $!;
open my $o_cds,   '>', "$outdir/cds.nt.fa"           or die $!;
open my $o_t2g, '>', "$outdir/transcript2gene.txt" or die $!;
open my $o_p2g, '>', "$outdir/protein2gene.txt"    or die $!;
open my $o_c2g, '>', "$outdir/cds2gene.txt"        or die $!;
open my $o_gen, '>', "$outdir/genome.fa"        or die $!;
open my $o_gff, '>', "$outdir/genes.gff"        or die $!;
open my $o_gtf, '>', "$outdir/genes.gtf"        or die $!;

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

    write_fa($o_tx, $tx, $seq);
    if (defined $pep{$orf_id}) {
        write_fa($o_peps, $tx, $pep{$orf_id});
        (my $pep_clean = $pep{$orf_id}) =~ s/\*//g;
        write_fa($o_pep, $tx, $pep_clean);
    }
    write_fa($o_cds, $tx, $cds{$orf_id}) if defined $cds{$orf_id};
    write_fa($o_gen, $seqid, $seq);

    print $o_t2g "$tx\t$gene\n";
    print $o_p2g "$tx\t$gene\n";
    print $o_c2g "$tx\t$gene\n";

    print $o_gff join("\t", $seqid, 'transdecoder', 'gene',       1,  $len, '.', '+',     '.',
                      "ID=$gene;Name=$gene"), "\n";
    print $o_gff join("\t", $seqid, 'transdecoder', 'mRNA',       1,  $len, '.', '+',     '.',
                      "ID=$tx;Parent=$gene"), "\n";
    print $o_gff join("\t", $seqid, 'transdecoder', 'exon',       1,  $len, '.', '+',     '.',
                      "Parent=$tx"), "\n";
    print $o_gff join("\t", $seqid, 'transdecoder', 'CDS',       $s,  $e,   '.', $strand, '0',
                      "ID=$orf_id;Parent=$tx;cds_fasta_id=$tx;protein_fasta_id=$tx"), "\n";

    print $o_gtf join("\t", $seqid, 'transdecoder', 'gene',       1,  $len, '.', '+',     '.',
                      qq{gene_id "$gene";}), "\n";
    print $o_gtf join("\t", $seqid, 'transdecoder', 'transcript', 1,  $len, '.', '+',     '.',
                      qq{gene_id "$gene"; transcript_id "$tx";}), "\n";
    print $o_gtf join("\t", $seqid, 'transdecoder', 'exon',       1,  $len, '.', '+',     '.',
                      qq{gene_id "$gene"; transcript_id "$tx"; exon_number "1";}), "\n";
    print $o_gtf join("\t", $seqid, 'transdecoder', 'CDS',       $s,  $e,   '.', $strand, '0',
                      qq{gene_id "$gene"; transcript_id "$tx"; protein_id "$tx";}), "\n";
}
close $o_tx;
close $o_pep;
close $o_peps;
close $o_cds;
close $o_t2g;
close $o_p2g;
close $o_c2g;
close $o_gen;
close $o_gff;
close $o_gtf;

my $total_orfs = scalar keys %orf;
my $kept       = scalar @genes;
printf "Genes: %d   ORFs in input: %d   kept: %d   dropped: %d\n",
    $kept, $total_orfs, $kept, $total_orfs - $kept;
print "Written to $outdir/: transcript.nt.fa cds.nt.fa protein.aa.fa protein.stops.aa.fa\n";
print "                     transcript2gene.txt protein2gene.txt cds2gene.txt\n";
print "                     genome.fa genes.gff genes.gtf\n";
warn "NOTE: $bad_header ORF header(s) could not be parsed and were skipped\n" if $bad_header;
warn "NOTE: $no_cds ORF(s) had no CDS sequence\n"                             if $no_cds;
warn "NOTE: $no_gene ORF(s) had a transcript missing from $t2g_file and were skipped\n" if $no_gene;
warn "NOTE: $missing_tx representative transcript(s) missing from $tx_fa\n"   if $missing_tx;
if (!$t2g_file) {
    print "\nNOTE: gene grouping was guessed by stripping the transcript id's "
        . "trailing \".N\" -- pass --t2g <transcript2gene.txt> to group by an\n"
        . "      authoritative mapping instead.\n\n";
}

sub read_t2g {
    my ($file) = @_;
    my %g;
    open my $in, '<', $file or die "Cannot open $file: $!\n";
    while (my $line = <$in>) {
        chomp $line;
        next unless $line =~ /\S/;
        my ($tx, $gene) = split /\t/, $line;
        next unless defined $tx && defined $gene && length $gene;
        $g{$tx} = $gene;
    }
    close $in;
    return %g;
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
Usage: $0 --dir /path/to/transdecoder_output [--t2g transcript2gene.txt]
          [--outdir DIR] [--ref-suffix .ref]

Finds the *.transdecoder.pep/.cds and their source transcript fasta in --dir
(TransDecoder's own naming makes this unambiguous), reduces the transcriptome
to one transcript and one ORF per gene (longest CDS wins), and writes the
FASTAs, mapping files, reference genome and GFF3 a MOOP gene set needs.

--t2g gives an authoritative transcript\\tgene mapping to group by; without it,
gene ids are guessed by stripping the transcript id's trailing ".N".
USAGE
}
