#!/usr/bin/perl
use strict;
use warnings;

# Append a type suffix to every sequence id in a transcript2gene-path FASTA, so
# that the three sequence files stop sharing one identifier.
#
# Usage: rename_t2g_fasta.pl <fasta> <suffix> [--dry-run]
#
#   rename_t2g_fasta.pl cds.nt.fa     :cds
#   rename_t2g_fasta.pl protein.aa.fa :pep
#
# transcript.nt.fa is deliberately NOT renamed: the transcript keeps its bare id,
# matching every other path, where the mRNA row also keeps the plain id.
#
# ---------------------------------------------------------------------------
# WHY THIS EXISTS
#
# On the T2G path all three FASTAs key on the SAME identifier -- the sequence
# type is decided by WHICH FILE you read, not by the id:
#
#     transcript.nt.fa   >bkew.kc1.000000_0_1.16
#     cds.nt.fa          >bkew.kc1.000000_0_1.16      (39,065 records in each)
#     protein.aa.fa      >bkew.kc1.000000_0_1.16
#
# MOOP requires feature_uniquename to be unique per gene set AND to be the FASTA
# lookup key. One shared id cannot satisfy both: the three rows collapse into
# one, which is how those gene sets ended up with a single self-parented feature
# (parent_feature_id = feature_id) and no protein rows at all -- their protein
# sequences existed on disk but were unreachable.
#
# Suffixing the CDS and protein copies gives three distinct ids that still point
# at real sequences, which is exactly what rename_generic_fasta.pl does on the
# GFF path. Unlike that script this one suffixes unconditionally: on the T2G
# path we know every id is shared, and there is no GFF to derive conflicts from.
#
# Run this on the COPIES in the geneset data dir, never on the source FASTAs.
# ---------------------------------------------------------------------------

my @positional;
my $dry_run = 0;
foreach my $arg (@ARGV) {
    if ($arg eq '--dry-run') { $dry_run = 1 }
    else                     { push @positional, $arg }
}
my ($fasta_file, $suffix) = @positional;
die "Usage: $0 <fasta> <suffix> [--dry-run]\n"
    unless $fasta_file && defined $suffix && length $suffix;

die "ERROR: suffix should start with ':' (got '$suffix')\n" unless $suffix =~ /^:/;
die "ERROR: no such file: $fasta_file\n" unless -s $fasta_file;

my ($records, $renamed, $already) = (0, 0, 0);

open my $in, '<', $fasta_file or die "Cannot open $fasta_file: $!\n";
my $out;
unless ($dry_run) {
    open $out, '>', "$fasta_file.tmp" or die "Cannot write $fasta_file.tmp: $!\n";
}

while (my $line = <$in>) {
    if ($line !~ /^>/) {
        print $out $line unless $dry_run;
        next;
    }

    $records++;
    chomp(my $header = $line);
    my ($id) = $header =~ /^>(\S+)/;

    unless (defined $id) {
        warn "WARNING: header with no id at record $records, left unchanged\n";
        print $out "$header\n" unless $dry_run;
        next;
    }

    # Idempotent: the pipeline may re-run over an already-renamed copy, and
    # double-suffixing would break the match just as surely as not suffixing.
    if ($id =~ /\Q$suffix\E$/) {
        $already++;
        print $out "$header\n" unless $dry_run;
        next;
    }

    $renamed++;
    my $rest = $header;
    $rest =~ s/^>\Q$id\E\s*//;
    print $out ($rest eq '' ? ">$id$suffix\n" : ">$id$suffix $rest\n") unless $dry_run;
}
close $in;

unless ($dry_run) {
    close $out;
    rename "$fasta_file.tmp", $fasta_file or die "Cannot rename: $!\n";
}

printf STDERR "%s: %d record(s): %d suffixed with '%s', %d already suffixed\n",
    ($dry_run ? "DRY RUN $fasta_file" : $fasta_file),
    $records, $renamed, $suffix, $already;

if ($records == 0) {
    print STDERR "ERROR: no FASTA records found in $fasta_file\n";
    exit 1;
}

exit 0;
