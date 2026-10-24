#!/usr/bin/env perl
## Turn one pair's jcvi output into the files the browser loads, with gene names, and
## refuse to publish anything that would load but show nothing.
##
## Usage:
##   perl label_and_check.pl --a BraPum --b BraVen --work DIR --prep DIR --outdir DIR
##        [--style name_id|id]
##
## Reads (from --work): A.B.anchors.new (screened gene pairs), A.B.anchors.simple (blocks)
##       (from --prep):  A.bed B.bed, A.labels.tsv B.labels.tsv, A.prep.txt B.prep.txt
## Writes (to --outdir): A.bed B.bed A.B.anchors A.B.anchors.simple check.txt
##
## NAMES. jcvi runs on transcript IDs (unique, no spaces). Only here, at the end, is each
## ID rewritten -- in the BEDs AND the anchors together, so they still match -- to
##   --style name_id  GPN1|BraPum_JAWDJD010000001.1_000001.1   (default; ID when no name)
##   --style id       BraPum_JAWDJD010000001.1_000001.1
## The ID stays in the label so it can still be searched in the gene track. No spaces:
## the anchors format is whitespace-separated.
##
## CHECKS (any failure -> exit 1, nothing written):
##   - every gene named in the anchors is in its own genome's BED
##   - the anchors are not empty
## (Sequence names against the assembly were already checked by prep_gene_set.pl.)
use strict;
use warnings;
use Getopt::Long;

sub main {
    my ($code_a, $code_b, $work, $prep, $outdir);
    my $style = 'name_id';
    GetOptions('a=s' => \$code_a, 'b=s' => \$code_b, 'work=s' => \$work, 'prep=s' => \$prep,
               'outdir=s' => \$outdir, 'style=s' => \$style) or die "bad arguments\n";
    die "usage: $0 --a A --b B --work DIR --prep DIR --outdir DIR [--style name_id|id]\n"
        unless $code_a && $code_b && $work && $prep && $outdir;
    die "--style must be name_id or id\n" unless $style eq 'name_id' || $style eq 'id';

    my %new_name;   # code -> { id -> displayed name }
    my %bed_rows;   # code -> [ rows ]
    foreach my $code ($code_a, $code_b) {
        my $labels = read_labels("$prep/$code.labels.tsv");
        my @rows = read_bed("$prep/$code.bed");
        foreach my $row (@rows) {
            my $id = $row->[3];
            my $label = $labels->{$id} // '';
            $new_name{$code}{$id} = ($style eq 'name_id' && $label ne '') ? "$label|$id" : $id;
        }
        $bed_rows{$code} = \@rows;
    }

    my @problems;
    my ($pairs, @anchor_lines) = rewrite_anchors("$work/$code_a.$code_b.anchors.new",
        $new_name{$code_a}, $new_name{$code_b}, \@problems);
    my ($blocks, @simple_lines) = rewrite_simple("$work/$code_a.$code_b.anchors.simple",
        $new_name{$code_a}, $new_name{$code_b}, \@problems);
    push @problems, "no gene pairs in $work/$code_a.$code_b.anchors.new" if $pairs == 0;

    if (@problems) {
        my $shown = @problems > 20 ? 20 : scalar @problems;
        warn "NOT PUBLISHED: ", scalar(@problems), " problem(s); first $shown:\n";
        foreach my $problem (@problems[0 .. $shown - 1]) { warn "  $problem\n"; }
        exit 1;
    }

    mkdir $outdir unless -d $outdir;
    foreach my $code ($code_a, $code_b) {
        open(my $fh, '>', "$outdir/$code.bed") or die "cannot write $outdir/$code.bed: $!\n";
        foreach my $row (@{ $bed_rows{$code} }) {
            my @out = @$row;
            $out[3] = $new_name{$code}{ $row->[3] };
            print {$fh} join("\t", @out), "\n";
        }
        close $fh;
    }
    write_lines("$outdir/$code_a.$code_b.anchors", @anchor_lines);
    write_lines("$outdir/$code_a.$code_b.anchors.simple", @simple_lines);
    write_lines("$outdir/check.txt",
        "pair\t$code_a\t$code_b",
        "gene_pairs\t$pairs",
        "blocks\t$blocks",
        "style\t$style",
        "genes_$code_a\t" . scalar(@{ $bed_rows{$code_a} }),
        "genes_$code_b\t" . scalar(@{ $bed_rows{$code_b} }),
        "checked\t" . scalar(localtime));
    print "$code_a.$code_b: $pairs gene pairs in $blocks blocks -> $outdir\n";
    exit 0;
}

## .anchors: "###" between blocks, then "geneA<TAB>geneB<TAB>score" lines.
sub rewrite_anchors {
    my ($file, $names_a, $names_b, $problems) = @_;
    my @out;
    my $pairs = 0;
    open(my $fh, '<', $file) or die "cannot read $file: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        if ($line =~ /^#/ || $line eq '') { push @out, $line; next; }
        my ($gene_a, $gene_b, @rest) = split /\t/, $line;
        my $new_a = lookup($names_a, $gene_a, 'A', $problems);
        my $new_b = lookup($names_b, $gene_b, 'B', $problems);
        push @out, join("\t", $new_a, $new_b, @rest);
        $pairs++;
    }
    close $fh;
    return ($pairs, @out);
}

## .anchors.simple: "startA endA startB endB score orientation", one block per line.
## jcvi may prefix the first column with a colour, "g*geneA"; the prefix is kept.
sub rewrite_simple {
    my ($file, $names_a, $names_b, $problems) = @_;
    my @out;
    my $blocks = 0;
    open(my $fh, '<', $file) or die "cannot read $file: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        if ($line =~ /^#/ || $line eq '') { push @out, $line; next; }
        my @col = split /\t/, $line;
        my $prefix = '';
        if ($col[0] =~ /^([^*\t]+\*)(.+)$/) { $prefix = $1; $col[0] = $2; }
        $col[0] = $prefix . lookup($names_a, $col[0], 'A', $problems);
        $col[1] = lookup($names_a, $col[1], 'A', $problems);
        $col[2] = lookup($names_b, $col[2], 'B', $problems);
        $col[3] = lookup($names_b, $col[3], 'B', $problems);
        push @out, join("\t", @col);
        $blocks++;
    }
    close $fh;
    return ($blocks, @out);
}

sub lookup {
    my ($names, $id, $side, $problems) = @_;
    return $names->{$id} if exists $names->{$id};
    push @$problems, "gene '$id' (genome $side) is not in its BED";
    return $id;
}

sub read_labels {
    my ($file) = @_;
    my %label;
    open(my $fh, '<', $file) or die "cannot read $file: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        my ($id, $name) = split /\t/, $line, 2;
        $name //= '';
        $name =~ s/\s+/_/g;          # never a space in a name (see NAMES above)
        $label{$id} = $name;
    }
    close $fh;
    return \%label;
}

sub read_bed {
    my ($file) = @_;
    my @rows;
    open(my $fh, '<', $file) or die "cannot read $file: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        push @rows, [ split /\t/, $line ];
    }
    close $fh;
    return @rows;
}

sub write_lines {
    my ($file, @lines) = @_;
    open(my $fh, '>', $file) or die "cannot write $file: $!\n";
    foreach my $line (@lines) { print {$fh} "$line\n"; }
    close $fh;
}

main();
