#!/usr/bin/perl
use strict;
use warnings;

# Rewrite a RefSeq cds.nt.fa so its sequence IDs match the CDS ID= values in the
# GFF -- which is what parse_RefSeq_GFF_to_MOOP_TSV.pl writes into
# feature_uniquename, and therefore what MOOP uses as the FASTA lookup key.
#
# Usage: rename_RefSeq_cds_fasta.pl <gff> <cds.nt.fa> [--dry-run]
#
# ---------------------------------------------------------------------------
# WHY THIS WAS REWRITTEN (2026-07-24)
#
# The previous version keyed the GFF->FASTA join on (locus_tag, protein_id) and
# REQUIRED both on both sides:
#
#     next unless defined $id && defined $lt && defined $pid;      # GFF side
#     if (/^>(.*\[locus_tag=([^\]]+)\].*\[protein_id=([^\]]+)\]/)  # FASTA side
#
# locus_tag is essentially a PROKARYOTIC attribute. RefSeq eukaryotic
# annotations use gene= instead. Measured on Nematostella GCF_932526225.1:
#
#     GFF:    354,481 CDS lines --      0 with locus_tag,  354,481 with protein_id
#     FASTA:   32,370 records   --      0 with [locus_tag],  32,370 with [protein_id]
#
# So the map was empty, the FASTA regex never matched, and every header fell
# through to the unchanged-passthrough branch -- with NO warning, because the
# warn lived inside the branch that never ran. Result: all 32,370 CDS features
# in that gene set had uniquename "cds-XP_..." while the FASTA still said
# "lcl|NC_064034.1_cds_XP_048580524.1_1". CDS sequence retrieval returned
# nothing for the entire gene set, silently.
#
# This version keys on protein_id (present on 100% of RefSeq CDS records, both
# sides) and uses locus_tag OR gene only to disambiguate the case the original
# comment was right to worry about: bacteria where one protein_id appears under
# several loci. It also FAILS LOUDLY -- unmatched records are counted and the
# exit status is non-zero -- so a broken join can never again look like success.
# ---------------------------------------------------------------------------

my @args = @ARGV;
my $dry_run = 0;
my @positional;
foreach my $arg (@args) {
    if ($arg eq '--dry-run') { $dry_run = 1 }
    else                     { push @positional, $arg }
}
my ($gff_file, $fasta_file) = @positional;
die "Usage: $0 <gff> <cds.nt.fa> [--dry-run]\n" unless $gff_file && $fasta_file;

# ── Build the GFF side of the join ───────────────────────────────────────────
# A spliced CDS is MANY GFF lines sharing one ID and one protein_id, so repeats
# are expected and harmless. A protein_id mapping to two DIFFERENT IDs is not --
# that is the ambiguous case, and it needs a disambiguator.
my %pid_to_id;         # protein_id            -> CDS ID (unambiguous only)
my %pid_ambiguous;     # protein_id            -> 1 if it maps to >1 CDS ID
my %pid_tag_to_id;     # "protein_id\ttag"     -> CDS ID
my %tag_to_id;         # locus_tag|gene        -> CDS ID (for records with NO protein_id)
my %tag_ambiguous;     # tag                   -> 1 if it maps to >1 CDS ID

open my $gff, '<', $gff_file or die "Cannot open $gff_file: $!\n";
while (my $line = <$gff>) {
    next if $line =~ /^#/;
    my @f = split /\t/, $line;
    next unless @f >= 9 && $f[2] eq 'CDS';

    my ($id)  = $f[8] =~ /\bID=([^;\n]+)/;
    next unless defined $id;

    my ($pid)  = $f[8] =~ /\bprotein_id=([^;\n]+)/;
    # Disambiguator: locus_tag for prokaryotes, gene for eukaryotes.
    my ($lt)   = $f[8] =~ /\blocus_tag=([^;\n]+)/;
    my ($gene) = $f[8] =~ /\bgene=([^;\n]+)/;
    my @tags   = grep { defined && length } ($lt, $gene);

    # Tag-only map, for CDS records that carry NO protein_id at all.
    # Pseudogene CDS entries are the real case: gene_biotype=pseudogene produces
    # no protein, so neither the GFF nor the FASTA has protein_id -- but both
    # still carry locus_tag. 230 of 8,515 records in one bacterial genome.
    foreach my $tag (@tags) {
        if (exists $tag_to_id{$tag} && $tag_to_id{$tag} ne $id) {
            $tag_ambiguous{$tag} = 1;
        } else {
            $tag_to_id{$tag} = $id;
        }
    }

    next unless defined $pid;

    foreach my $tag (@tags) {
        $pid_tag_to_id{"$pid\t$tag"} = $id;
    }

    if (exists $pid_to_id{$pid} && $pid_to_id{$pid} ne $id) {
        $pid_ambiguous{$pid} = 1;
    } else {
        $pid_to_id{$pid} = $id;
    }
}
close $gff;

my $gff_pids = scalar keys %pid_to_id;
print STDERR "GFF: $gff_pids distinct protein_id(s)"
    . (%pid_ambiguous ? ", " . scalar(keys %pid_ambiguous) . " ambiguous" : "")
    . "\n";
die "ERROR: no CDS protein_id found in $gff_file -- wrong file, or not a RefSeq GFF?\n"
    unless $gff_pids;

# ── Rewrite the FASTA ────────────────────────────────────────────────────────
my ($renamed, $already, $unmatched, $records) = (0, 0, 0, 0);
my @unmatched_examples;

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
    my ($current_id) = $header =~ /^>(\S+)/;
    my ($pid)        = $header =~ /\[protein_id=([^\]]+)\]/;
    my ($lt)         = $header =~ /\[locus_tag=([^\]]+)\]/;
    my ($gene)       = $header =~ /\[gene=([^\]]+)\]/;

    my @tags = grep { defined && length } ($lt, $gene);

    my $new_id;
    if (defined $pid) {
        if ($pid_ambiguous{$pid}) {
            # Needs the disambiguator -- this is the multi-locus bacterial case.
            foreach my $tag (@tags) {
                $new_id = $pid_tag_to_id{"$pid\t$tag"};
                last if defined $new_id;
            }
        } else {
            $new_id = $pid_to_id{$pid};
        }
    }

    # No protein_id (pseudogene CDS): fall back to locus_tag/gene alone.
    if (!defined $new_id) {
        foreach my $tag (@tags) {
            next if $tag_ambiguous{$tag};
            $new_id = $tag_to_id{$tag};
            last if defined $new_id;
        }
    }

    if (!defined $new_id) {
        $unmatched++;
        push @unmatched_examples, ($current_id // '?') if @unmatched_examples < 5;
        print $out "$header\n" unless $dry_run;
        next;
    }

    if (defined $current_id && $current_id eq $new_id) {
        $already++;
        print $out "$header\n" unless $dry_run;
        next;
    }

    $renamed++;
    # Keep the original header text after the new ID so provenance is not lost.
    my $rest = $header;
    $rest =~ s/^>\S+\s*//;
    print $out ($rest eq '' ? ">$new_id\n" : ">$new_id $rest\n") unless $dry_run;
}
close $in;

unless ($dry_run) {
    close $out;
    rename "$fasta_file.tmp", $fasta_file or die "Cannot rename: $!\n";
}

# ── Report, and FAIL if the join did not fully succeed ───────────────────────
printf STDERR "%s: %d record(s): %d renamed, %d already correct, %d UNMATCHED\n",
    ($dry_run ? "DRY RUN $fasta_file" : $fasta_file),
    $records, $renamed, $already, $unmatched;

if ($unmatched) {
    print STDERR "ERROR: $unmatched of $records CDS record(s) could not be matched to a\n"
               . "       GFF CDS ID and kept their original header. Those sequences will be\n"
               . "       UNREACHABLE from MOOP, because feature_uniquename will not match.\n"
               . "       Examples: " . join(', ', @unmatched_examples) . "\n";
    exit 1;
}

print STDERR "OK: every CDS record now matches a GFF CDS ID.\n";
exit 0;
