#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;

# Generate transcript2gene.txt, protein2gene.txt and cds2gene.txt from a GFF3.
#
# The companion make_t2g_from_fasta.pl derives the gene by STRIPPING SUFFIXES off
# the sequence id (.pN, then .tN, then .N) because a FASTA carries no structure.
# That is a guess, and it is wrong whenever an id happens to end in a number that
# is not an isoform marker. A GFF states the relationship outright --
# transcript's Parent IS the gene -- so this script never guesses.
#
#   gene        ID=bkew.kc1.004708_0_1
#   transcript  ID=bkew.kc1.004708_0_1.5;Parent=bkew.kc1.004708_0_1
#                  ^ transcript2gene.txt: bkew.kc1.004708_0_1.5 -> bkew.kc1.004708_0_1
#
# Usage:
#   make_t2g_from_gff.pl --gff genes.gff [--outdir DIR] [--force-coding] [--quiet]
#
# Output (<id>\tgene, tab separated, no header -- same shape the FASTA version
# writes, which is what parse_transcript2gene_to_MOOP_TSV.pl reads):
#   transcript2gene.txt   transcript id
#   cds2gene.txt          the coding transcripts
#   protein2gene.txt      the coding transcripts
#
# IDS ARE WRITTEN BARE, EXACTLY AS THE GFF SPELLS THEM. Do NOT add :cds or :pep
# here. MOOP's naming is applied downstream, by parse_transcript2gene_to_MOOP_TSV.pl
#   my $uniquename = "$cds_id:cds";     (line ~152)
#   my $uniquename = "$prot_id:pep";    (line ~177)
# and by rename_t2g_fasta.pl on the FASTA copies in MOOP's own data directory.
# Suffixing here would produce "...:pep:pep" and match nothing.
#
# That split is deliberate and worth preserving: these files live in the source
# genomes tree, which is the canonical record and must keep the depositor's
# identifiers. MOOP's naming belongs to MOOP's copies, never to the original.
#
# cds2gene.txt and protein2gene.txt therefore hold the SAME id list -- one ORF call
# yields both a CDS and a protein, and the two are told apart by the suffix the
# parser adds, not by anything in these files.
#
# NON-CODING TRANSCRIPTS GET NO :cds / :pep ROW. A transcript is treated as coding
# only if the GFF gives it at least one CDS feature. Emitting them for everything
# put a 'cds' entry in the annotated-types list for a whole organism and rendered
# an empty CDS card on every transcript page. If a GFF has no CDS features at all
# (a transcript-only assembly), this script writes transcript2gene.txt alone and
# says so; pass --force-coding to emit :cds/:pep for every transcript anyway.

my ($gff, $outdir, $force_coding, $quiet);
$outdir = '.';

GetOptions(
    'gff=s'         => \$gff,
    'outdir=s'      => \$outdir,
    'force-coding'  => \$force_coding,
    'quiet'         => \$quiet,
) or die usage();

die usage() unless $gff;
die "Cannot read GFF: $gff\n" unless -s $gff;

mkdir $outdir unless -d $outdir;

# Which 3rd-column types count as a transcript. 'transcript' is what gffread emits
# with --keep-genes; 'mRNA' is the GFF3 spec term; the rest are non-coding classes
# that still belong in transcript2gene.txt -- they are real features with real
# annotations, they simply have no protein.
my %IS_TRANSCRIPT = map { $_ => 1 } qw(
    transcript mRNA ncRNA lnc_RNA lncRNA rRNA tRNA snRNA snoRNA miRNA
    misc_RNA pseudogenic_transcript primary_transcript
);

my (%tx2gene, %has_cds, @tx_order);
my ($line_no, $skipped_no_id, $skipped_no_parent) = (0, 0, 0);

open my $in, '<', $gff or die "Cannot open $gff: $!\n";
while (my $line = <$in>) {
    $line_no++;
    next if $line =~ /^\s*#/;      # headers and directives
    next if $line !~ /\S/;
    chomp $line;

    my @col = split /\t/, $line;
    next unless @col >= 9;
    my ($type, $attrs) = ($col[2], $col[8]);

    if ($IS_TRANSCRIPT{$type}) {
        my $id     = attr($attrs, 'ID');
        my $parent = attr($attrs, 'Parent');
        if (!defined $id)     { $skipped_no_id++;     next; }
        # A transcript with no Parent is its own gene. Common in transcriptome GFFs
        # that carry no gene line -- recording the transcript as its own gene keeps
        # the hierarchy valid rather than dropping the row.
        if (!defined $parent) { $skipped_no_parent++; $parent = $id; }
        # Parent may list several ids; the first is the gene.
        $parent =~ s/,.*$//;
        if (!exists $tx2gene{$id}) { push @tx_order, $id; }
        $tx2gene{$id} = $parent;
    }
    elsif ($type eq 'CDS') {
        my $parent = attr($attrs, 'Parent');
        next unless defined $parent;
        # One CDS may be shared by several transcripts.
        foreach my $p (split /,/, $parent) { $has_cds{$p} = 1; }
    }
}
close $in;

die "No transcripts found in $gff.\n"
  . "Looked for column-3 types: " . join(', ', sort keys %IS_TRANSCRIPT) . "\n"
  unless @tx_order;

my $coding = scalar grep { $has_cds{$_} } @tx_order;
if (!$coding && !$force_coding) {
    warn "NOTE: $gff has no CDS features, so no transcript is coding.\n"
       . "      Writing transcript2gene.txt only -- no cds2gene.txt or protein2gene.txt.\n"
       . "      Pass --force-coding to emit :cds/:pep for every transcript anyway.\n"
      unless $quiet;
}

write_map("$outdir/transcript2gene.txt", \@tx_order, sub { $_[0] });

if ($coding || $force_coding) {
    my @coding_tx = $force_coding ? @tx_order : grep { $has_cds{$_} } @tx_order;
    # Bare ids -- the parser appends :cds / :pep. See the header.
    write_map("$outdir/cds2gene.txt",     \@coding_tx, sub { $_[0] });
    write_map("$outdir/protein2gene.txt", \@coding_tx, sub { $_[0] });
}

unless ($quiet) {
    printf "Parsed %s: %d transcript(s), %d coding, %d gene(s)\n",
        $gff, scalar(@tx_order), ($force_coding ? scalar(@tx_order) : $coding),
        scalar(keys %{{ map { $tx2gene{$_} => 1 } @tx_order }});
    warn "WARNING: $skipped_no_id transcript line(s) had no ID= and were skipped\n"
        if $skipped_no_id;
    warn "NOTE: $skipped_no_parent transcript(s) had no Parent= and were recorded as their own gene\n"
        if $skipped_no_parent;
}

sub write_map {
    my ($path, $ids, $decorate) = @_;
    open my $out, '>', $path or die "Cannot write $path: $!\n";
    foreach my $id (@$ids) {
        print $out $decorate->($id), "\t", $tx2gene{$id}, "\n";
    }
    close $out or die "Cannot close $path: $!\n";
    print "Written: $path (" . scalar(@$ids) . " rows)\n" unless $quiet;
}

# Pull one attribute out of a GFF3 column 9. Values are percent-encoded per the
# spec, and an id containing %3D or %2C would otherwise be split wrongly.
sub attr {
    my ($attrs, $key) = @_;
    foreach my $field (split /;/, $attrs) {
        $field =~ s/^\s+|\s+$//g;
        next unless $field =~ /^\Q$key\E=(.*)$/;
        my $val = $1;
        $val =~ s/%([0-9A-Fa-f]{2})/chr(hex($1))/ge;
        return $val;
    }
    return undef;
}

sub usage {
    return <<"USAGE";
Usage: $0 --gff <genes.gff> [--outdir DIR] [--force-coding] [--quiet]

  --gff           GFF3 to read (required)
  --outdir        where to write the files (default: current directory)
  --force-coding  emit :cds/:pep for every transcript even when the GFF has no
                  CDS features (transcript-only assemblies)
  --quiet         suppress progress output

Writes transcript2gene.txt, and cds2gene.txt + protein2gene.txt when the GFF
has CDS features. Format is "<id>\\t<gene>", matching make_t2g_from_fasta.pl.
USAGE
}
