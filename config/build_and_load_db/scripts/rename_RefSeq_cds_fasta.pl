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
my %pid_tag_ambiguous; # "protein_id\ttag"     -> 1 if THAT KEY maps to >1 CDS ID
my %tag_to_id;         # locus_tag|gene        -> CDS ID (for records with NO protein_id)
my %tag_ambiguous;     # tag                   -> 1 if it maps to >1 CDS ID
my %pid_ids;           # protein_id            -> { CDS ID => 1 }
my %id_span;           # CDS ID                -> "min_start\tmax_end\tstrand"

open my $gff, '<', $gff_file or die "Cannot open $gff_file: $!\n";
while (my $line = <$gff>) {
    next if $line =~ /^#/;
    my @f = split /\t/, $line;
    next unless @f >= 9 && $f[2] eq 'CDS';

    my ($id)  = $f[8] =~ /\bID=([^;\n]+)/;
    next unless defined $id;

    # Genomic span per CDS ID, for the coordinate fallback further down. A spliced
    # CDS is MANY lines sharing one ID, so accumulate the extremes rather than
    # taking the first line's coordinates.
    my ($seg_start, $seg_end, $seg_strand) = ($f[3], $f[4], $f[6]);
    if (exists $id_span{$id}) {
        my ($have_min, $have_max, $have_strand) = split /\t/, $id_span{$id};
        $have_min = $seg_start if $seg_start < $have_min;
        $have_max = $seg_end   if $seg_end   > $have_max;
        $id_span{$id} = join("\t", $have_min, $have_max, $have_strand);
    }
    else {
        $id_span{$id} = join("\t", $seg_start, $seg_end, $seg_strand);
    }

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

    $pid_ids{$pid}{$id} = 1;

    # THE GUARD THIS HASH WAS MISSING. Its two siblings -- %tag_to_id above and
    # %pid_to_id below -- both detect ambiguity. This one, the map used PRECISELY
    # when a protein_id is already known to be ambiguous, overwrote silently:
    # last line wins.
    #
    # Amphimedon_queenslandica GCF_000090795.2: protein NP_001266236.1 is annotated
    # at two loci, both `gene=Gro`, neither carrying a locus_tag. Both GFF lines
    # wrote the key "NP_001266236.1\tGro", the second won, and so BOTH FASTA records
    # were renamed cds-NP_001266236.1-2 while cds-NP_001266236.1 vanished entirely.
    # makeblastdb -parse_seqids then rejected the file, which stopped the organism's
    # build before the whole-database steps -- with an error naming neither the
    # record nor the cause.
    foreach my $tag (@tags) {
        my $pid_tag_key = "$pid\t$tag";
        if (exists $pid_tag_to_id{$pid_tag_key} && $pid_tag_to_id{$pid_tag_key} ne $id) {
            $pid_tag_ambiguous{$pid_tag_key} = 1;
        }
        else {
            $pid_tag_to_id{$pid_tag_key} = $id;
        }
    }

    if (exists $pid_to_id{$pid} && $pid_to_id{$pid} ne $id) {
        $pid_ambiguous{$pid} = 1;
    } else {
        $pid_to_id{$pid} = $id;
    }
}
close $gff;

# ── Coordinate index, for the one case the tag disambiguator cannot split ────
# Built ONLY for protein_ids where the tag is known to fail, so this hash is empty
# for every gene set that is already resolved correctly and the fallback below can
# never fire there. Measured across all 72 live gene sets (12 GB of GFF): 25
# ambiguous protein_ids in total, 24 of them in Bradyrhizobium_diazoefficiens where
# locus_tag resolves every one, and exactly 1 -- Amphimedon's -- where it does not.
my %pid_span_to_id;
my %pid_span_ambiguous;
foreach my $pid (keys %pid_ids) {
    next unless $pid_ambiguous{$pid};
    foreach my $cds_id (keys %{ $pid_ids{$pid} }) {
        next unless defined $id_span{$cds_id};
        my $span_key = "$pid\t" . $id_span{$cds_id};
        if (exists $pid_span_to_id{$span_key} && $pid_span_to_id{$span_key} ne $cds_id) {
            $pid_span_ambiguous{$span_key} = 1;
        }
        else {
            $pid_span_to_id{$span_key} = $cds_id;
        }
    }
}

# The FASTA carries [location=join(<2106..2136,...,<5084..5191)] or
# [location=complement(join(598..705,...,1307..>1569))]. Reduce either to the same
# (min, max, strand) triple the GFF side was reduced to. The `<` and `>` partial
# markers carry no coordinate information and drop out with the digit scan.
sub location_span {
    my ($location) = @_;
    return () unless defined $location && length $location;
    my $strand = ($location =~ /complement/) ? '-' : '+';
    my @coords = ($location =~ /(\d+)/g);
    return () unless @coords;
    my ($min, $max) = ($coords[0], $coords[0]);
    foreach my $coord (@coords) {
        $min = $coord if $coord < $min;
        $max = $coord if $coord > $max;
    }
    return ($min, $max, $strand);
}

# Every ID written to the FASTA must be unique, because makeblastdb -parse_seqids
# rejects duplicates -- and MOOP needs -parse_seqids, since blastdbcmd retrieval by
# feature_uniquename is how sequence retrieval works. Catching a collision HERE
# names both records; catching it at makeblastdb names neither and stops the build
# after the database has already been loaded.
my %assigned_id;
sub claim_id {
    my ($claim, $header, $file) = @_;
    if (exists $assigned_id{$claim}) {
        die "ERROR: two records would be written as '$claim' in $file\n"
          . "  first : $assigned_id{$claim}\n"
          . "  second: $header\n"
          . "  makeblastdb -parse_seqids rejects duplicate sequence IDs. Fix the\n"
          . "  GFF->FASTA join; do not de-duplicate the FASTA.\n";
    }
    $assigned_id{$claim} = $header;
    return;
}

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
            # Tag disambiguator FIRST, and unchanged -- this is the multi-locus
            # bacterial case it was written for (Bradyrhizobium_diazoefficiens: 24
            # ambiguous protein_ids, all resolved here by locus_tag). Every record
            # this path already resolves keeps resolving through it identically,
            # which is precisely what makes the fallback below safe to add.
            foreach my $tag (@tags) {
                my $pid_tag_key = "$pid\t$tag";
                next if $pid_tag_ambiguous{$pid_tag_key};
                $new_id = $pid_tag_to_id{$pid_tag_key};
                last if defined $new_id;
            }
            # Only when the tag cannot tell them apart: use position, which always
            # can. Two CDS models sharing one protein_id necessarily sit at
            # different coordinates -- being at different loci is what makes them
            # two models in the first place.
            if (!defined $new_id) {
                my ($location) = $header =~ /\[location=([^\]]+)\]/;
                my ($min, $max, $strand) = location_span($location);
                if (defined $min) {
                    my $span_key = "$pid\t$min\t$max\t$strand";
                    $new_id = $pid_span_to_id{$span_key}
                        unless $pid_span_ambiguous{$span_key};
                }
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

    # Uniqueness is asserted even under --dry-run, so a dry run is a real check and
    # not just a preview.
    if (!defined $new_id) {
        $unmatched++;
        push @unmatched_examples, ($current_id // '?') if @unmatched_examples < 5;
        claim_id($current_id // '?', $header, $fasta_file);
        print $out "$header\n" unless $dry_run;
        next;
    }

    if (defined $current_id && $current_id eq $new_id) {
        $already++;
        claim_id($new_id, $header, $fasta_file);
        print $out "$header\n" unless $dry_run;
        next;
    }

    $renamed++;
    claim_id($new_id, $header, $fasta_file);
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
