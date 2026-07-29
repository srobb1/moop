#!/usr/bin/perl
use strict;
use warnings;
use File::Basename qw(dirname);

# Strip a redundant per-organism prefix from the feature IDs in MOOP's WORKING COPY
# of a gene set -- genes.gff plus the FASTAs derived from it.
#
# Usage: strip_id_prefix.pl <prefix> <genes.gff> [fasta ...]
#
# ---------------------------------------------------------------------------
# WHY
#
# Bradypodion_ventrale's Helixer IDs look like
#
#     Bradypodion_ventrale_JAWDJE010000001.1_000001.1
#
# i.e. <organism>_<seqid>_<serial>. The seqid is ALREADY GFF column 1, so the
# organism name is pure repetition -- and it is what pushes the ID past a hard
# limit. makeblastdb -parse_seqids refuses local IDs longer than 50 characters
# (NCBI's limit, kMaxLocalIdLength, not ours), and MOOP's own ":pep"/":cds"
# suffix adds 4 more:
#
#     Bradypodion_ventrale_JAWDJE010000001.1_003560.1        47   transcript, built fine
#     Bradypodion_ventrale_JAWDJE010000001.1_003560.1:pep    51   REFUSED
#
# The whole organism's build died there, before the whole-database steps, so it
# also had no FTS index. Its sister species Bradypodion_pumilum lands on exactly
# 50 -- working today with zero characters to spare.
#
# Measured on ventrale: 22,019 records, 22,019 distinct IDs before AND after
# stripping, max length 51 -> 30. No collisions, and the prefix never occurs in
# sequence data.
#
# ---------------------------------------------------------------------------
# WHAT THIS DOES NOT TOUCH
#
# The $GENOMES tree. Depositor and assembler IDs stay exactly as delivered,
# forever; this rewrites only the copies MOOP made for itself, in the same place
# and for the same reason it appends ":pep"/":cds". If a target is a SYMLINK --
# which is how genes.gff and the FASTAs are wired on the refseq and ensembl paths
# -- writing through it would edit the source tree. So a symlink is dereferenced
# into a real local file FIRST, and the link is replaced. Nothing under $GENOMES
# is ever opened for writing.
#
# Opt-in per gene set via `moop-strip-id-prefix:` in metadata.yaml. Absent means
# no prefix, which means this script is never invoked and IDs are untouched --
# that is what keeps a run over everything byte-identical for the other ~90 gene
# sets. Auto-detecting a shared prefix was rejected deliberately: EDU1_, TBR1_,
# MSE1_ and friends are shared prefixes too, so detection would silently re-ID 58
# gene sets that are working.
#
# The `moop-` namespace is load-bearing, not decoration: everything else in
# metadata.yaml describes the DATA (genus, accession, download URL, curator),
# while this describes something MOOP does TO its own copy. Same distinction as
# ":pep"/":cds".
# ---------------------------------------------------------------------------

my ($prefix, @files) = @ARGV;
die "Usage: $0 <prefix> <genes.gff> [fasta ...]\n" unless defined $prefix && @files;
die "ERROR: refusing to strip an empty prefix\n" unless length $prefix;

# The GFF attributes that carry a feature ID. `namesrc` matters and is easy to
# miss: the ID sits mid-string inside a pipe-delimited provenance value,
# "Ensembl_Homo_sapiens|RBBH_Homolog|<id>|ENSP00000476446.1|6". `Name` matters
# because an unnamed gene falls back to its own ID. Column 1 is the seqid and is
# NOT prefixed -- it must never be rewritten.
my @ID_ATTRS = qw(ID Parent Name namesrc);

my $MAX_LOCAL_ID = 50;   # NCBI makeblastdb -parse_seqids

## A symlink here points into $GENOMES. Replace it with a real file before any
## write, so the source tree cannot be modified even by accident.
sub materialize {
    my ($path) = @_;
    return 0 unless -l $path;
    my $tmp = "$path.deref.$$";
    open my $src, '<', $path      or die "Cannot read $path: $!\n";
    open my $dst, '>', $tmp       or die "Cannot write $tmp: $!\n";
    while (my $line = <$src>) { print $dst $line }
    close $src;
    close $dst or die "Cannot finish $tmp: $!\n";
    unlink $path or die "Cannot remove symlink $path: $!\n";
    rename $tmp, $path or die "Cannot rename $tmp -> $path: $!\n";
    return 1;
}

sub strip_gff {
    my ($path, $quoted) = @_;
    my ($lines, $changed) = (0, 0);
    open my $in,  '<', $path        or die "Cannot open $path: $!\n";
    open my $out, '>', "$path.tmp"  or die "Cannot write $path.tmp: $!\n";
    while (my $line = <$in>) {
        if ($line =~ /^#/) { print $out $line; next }
        chomp(my $row = $line);
        my @field = split /\t/, $row, 9;
        if (@field < 9) { print $out $line; next }
        $lines++;
        my @attrs = split /;/, $field[8];
        my $touched = 0;
        foreach my $attr (@attrs) {
            my $eq = index($attr, '=');
            next if $eq < 1;
            my $key = substr($attr, 0, $eq);
            next unless grep { $_ eq $key } @ID_ATTRS;
            my $value = substr($attr, $eq + 1);
            my $n = ($value =~ s/$quoted//g);
            next unless $n;
            $attr = "$key=$value";
            $touched = 1;
        }
        $changed++ if $touched;
        $field[8] = join(';', @attrs);
        print $out join("\t", @field), "\n";
    }
    close $in;
    close $out or die "Cannot finish $path.tmp: $!\n";
    rename "$path.tmp", $path or die "Cannot rename into $path: $!\n";
    return ($lines, $changed);
}

sub strip_fasta {
    my ($path, $quoted) = @_;
    my (%before, %after);
    my ($records, $longest) = (0, 0);
    open my $in,  '<', $path        or die "Cannot open $path: $!\n";
    open my $out, '>', "$path.tmp"  or die "Cannot write $path.tmp: $!\n";
    while (my $line = <$in>) {
        if ($line !~ /^>/) { print $out $line; next }
        $records++;
        chomp(my $header = $line);
        my ($id) = $header =~ /^>(\S+)/;
        $before{$id} = 1 if defined $id;
        # Only the ID token. The description after the first space is free text and
        # is left exactly as delivered.
        my $rest = $header;
        $rest =~ s/^>\S+//;
        my $new = defined $id ? $id : '';
        $new =~ s/^$quoted//;
        $after{$new} = 1;
        $longest = length($new) if length($new) > $longest;
        print $out ">$new$rest\n";
    }
    close $in;
    close $out or die "Cannot finish $path.tmp: $!\n";

    # Guards. A prefix that collapses two IDs into one would corrupt the gene set
    # silently, and it is exactly the class this whole exercise is about.
    my $n_before = scalar keys %before;
    my $n_after  = scalar keys %after;
    if ($n_after != $n_before) {
        unlink "$path.tmp";
        die "ERROR: $path -- stripping '$prefix' collapsed IDs: "
          . "$n_before distinct before, $n_after after. Nothing written.\n";
    }

    rename "$path.tmp", $path or die "Cannot rename into $path: $!\n";
    return ($records, $n_after, $longest);
}

my $quoted = qr/\Q$prefix\E/;
my @report;

foreach my $file (@files) {
    unless (-e $file) {
        print STDERR "  skip (absent): $file\n";
        next;
    }
    my $was_link = materialize($file);
    if ($file =~ /\.gff$/) {
        my ($lines, $changed) = strip_gff($file, $quoted);
        push @report, sprintf("  %-22s %d/%d feature line(s) rewritten%s",
            $file, $changed, $lines, $was_link ? "  [dereferenced]" : "");
    }
    else {
        my ($records, $distinct, $longest) = strip_fasta($file, $quoted);
        die "ERROR: $file -- longest ID is still $longest characters after stripping "
          . "'$prefix'; makeblastdb -parse_seqids allows $MAX_LOCAL_ID.\n"
            if $longest > $MAX_LOCAL_ID;
        push @report, sprintf("  %-22s %d record(s), %d distinct, longest ID %d%s",
            $file, $records, $distinct, $longest, $was_link ? "  [dereferenced]" : "");
    }
}

print STDERR "Stripped ID prefix '$prefix':\n";
print STDERR "$_\n" foreach @report;

# Record what was ACTUALLY done, beside the files it was done to. The annotation
# loader reads this rather than re-reading metadata.yaml, so the two cannot drift:
# whatever normalization the IDs received, the join applies the same one. Two
# independent sources of truth for "what was renamed" is how a rename quietly
# stops matching -- see load_annotations_sqlite.pl and Bipalium_vagum.
my $dir = dirname($files[0]);
open my $mf, '>', "$dir/.id_prefix_stripped"
    or die "Cannot write $dir/.id_prefix_stripped: $!\n";
print $mf "$prefix\n";
close $mf;

exit 0;
