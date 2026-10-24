#!/usr/bin/env perl
## Make the gene-anchor synteny inputs for ONE MOOP gene set:
##
##   <code>.bed         one row per protein-coding gene (its longest-CDS transcript),
##                      named by the transcript ID, on the assembly's own sequence names
##   <code>.cds         that transcript's CDS, header = exactly the BED name
##   <code>.labels.tsv  transcript ID -> gene name to show in the browser (may be blank)
##   <code>.prep.txt    counts, so a bad gene set is visible before anything runs
##
## Usage:
##   perl prep_gene_set.pl --gene-set DIR --code BraPum --outdir OUT [--fai genome.fa.fai]
##
## WHY THIS EXISTS. jcvi requires the BED name and the FASTA header to be the SAME string,
## and carries that string into the .anchors file, which is what the browser displays.
## Every source names things differently (RefSeq: gene-LOC.. / rna-XM.. / cds-XP..;
## MOOP adds ':cds'), and the 2024 chameleon run needed a fix-up script per source to
## line them up. Here one rule covers every MOOP gene set, because MOOP's genes.gff
## links CDS -> mRNA -> gene for all of them:
##
##   a FASTA header is resolved to its mRNA by (1) dropping MOOP's ':cds'/':pep' suffix,
##   then (2) taking it as an mRNA ID, or (3) as a CDS feature ID whose Parent is the mRNA.
##
## The transcript ID (not the gene ID) is the name, as in the 2024 run, so a name in the
## synteny view is a feature the gene track and its search also know.
##
## Exits non-zero if fewer than 90% of the chosen genes have a CDS sequence, or if any
## BED row is on a sequence the assembly does not have (--fai): either would give a track
## that loads and silently shows little or nothing.
use strict;
use warnings;
use Getopt::Long;

my $MIN_SEQ_FRACTION = 0.90;

sub main {
    my ($gene_set, $code, $outdir, $fai);
    GetOptions(
        'gene-set=s' => \$gene_set,
        'code=s'     => \$code,
        'outdir=s'   => \$outdir,
        'fai=s'      => \$fai,
    ) or die "bad arguments\n";
    die "usage: $0 --gene-set DIR --code CODE --outdir OUT [--fai genome.fa.fai]\n"
        unless $gene_set && $code && $outdir;
    die "code '$code' must be letters, digits and _ only\n" unless $code =~ /^\w+$/;

    my $gff = "$gene_set/genes.gff";
    my $cds = "$gene_set/cds.nt.fa";
    foreach my $file ($gff, $cds) {
        die "missing $file\n" unless -s $file;
    }
    if (!defined $fai) {
        my $guess = "$gene_set/../genome.fa.fai";
        $fai = $guess if -s $guess;
    }

    my $model = read_gff($gff);
    my $chosen = choose_longest($model);
    my $seqs = read_cds($cds, $model, $chosen);

    my %known_seqid;
    if ($fai) {
        open(my $fh, '<', $fai) or die "cannot read $fai: $!\n";
        while (my $line = <$fh>) {
            my ($seqid) = split /\t/, $line;
            $known_seqid{$seqid} = 1;
        }
        close $fh;
    }

    mkdir $outdir unless -d $outdir;
    my @rows;
    my ($no_seq, $bad_seqid) = (0, 0);
    foreach my $mrna_id (values %$chosen) {
        if (!exists $seqs->{$mrna_id}) { $no_seq++; next; }
        my $m = $model->{mrna}{$mrna_id};
        if ($fai && !$known_seqid{ $m->{seqid} }) { $bad_seqid++; next; }
        push @rows, $mrna_id;
    }
    @rows = sort {
        $model->{mrna}{$a}{seqid} cmp $model->{mrna}{$b}{seqid}
            || $model->{mrna}{$a}{start} <=> $model->{mrna}{$b}{start}
            || $a cmp $b
    } @rows;

    open(my $bed, '>', "$outdir/$code.bed") or die "cannot write $outdir/$code.bed: $!\n";
    open(my $fa,  '>', "$outdir/$code.cds") or die "cannot write $outdir/$code.cds: $!\n";
    open(my $lab, '>', "$outdir/$code.labels.tsv") or die "cannot write labels: $!\n";
    my $labelled = 0;
    foreach my $mrna_id (@rows) {
        my $m = $model->{mrna}{$mrna_id};
        print {$bed} join("\t", $m->{seqid}, $m->{start} - 1, $m->{end}, $mrna_id, 0, $m->{strand}), "\n";
        print {$fa} ">$mrna_id\n";
        my $seq = $seqs->{$mrna_id};
        for (my $pos = 0; $pos < length $seq; $pos += 60) {
            print {$fa} substr($seq, $pos, 60), "\n";
        }
        my $label = display_name($model, $mrna_id);
        $labelled++ if $label ne '';
        print {$lab} "$mrna_id\t$label\n";
    }
    close $bed; close $fa; close $lab;

    my $genes = scalar keys %$chosen;
    my @report = (
        "gene_set\t$gene_set",
        "genes_with_coding_mrna\t$genes",
        "written\t" . scalar(@rows),
        "no_cds_sequence\t$no_seq",
        "sequence_not_in_assembly\t" . ($fai ? $bad_seqid : 'not checked (no genome.fa.fai)'),
        "with_gene_name\t$labelled",
        "cds_headers_unmatched\t$seqs->{__unmatched}",
    );
    open(my $rep, '>', "$outdir/$code.prep.txt") or die "cannot write report: $!\n";
    foreach my $item (@report) { print {$rep} "$item\n"; }
    close $rep;
    print "$code: ", join('; ', @report[1 .. $#report]), "\n";

    my $problems = 0;
    if ($genes == 0) {
        warn "ERROR $code: no mRNA with CDS in $gff\n"; $problems++;
    } elsif (@rows / $genes < $MIN_SEQ_FRACTION) {
        warn sprintf("ERROR %s: only %d of %d genes have a CDS sequence (need %d%%) -- headers in %s do not match genes.gff\n",
            $code, scalar(@rows), $genes, $MIN_SEQ_FRACTION * 100, $cds);
        $problems++;
    }
    if ($bad_seqid) {
        warn "ERROR $code: $bad_seqid genes are on sequences that $fai does not have\n"; $problems++;
    }
    exit($problems ? 1 : 0);
}

## genes.gff -> gene names, mRNAs (coords, parent, name) and CDS length per mRNA.
## An mRNA counts only if it has CDS, so non-coding transcripts drop out here.
sub read_gff {
    my ($gff) = @_;
    my (%gene, %mrna, %cds_parent, %cds_len, @order);
    open(my $fh, '<', $gff) or die "cannot read $gff: $!\n";
    while (my $line = <$fh>) {
        next if $line =~ /^#/;
        chomp $line;
        my @col = split /\t/, $line;
        next unless @col == 9;
        my ($seqid, $type, $start, $end, $strand, $attr) = @col[0, 2, 3, 4, 6, 8];
        my $attrs = parse_attributes($attr);
        if ($type eq 'gene') {
            $gene{ $attrs->{ID} } = { name => $attrs->{Name} } if defined $attrs->{ID};
        } elsif ($type eq 'mRNA') {
            next unless defined $attrs->{ID};
            my ($parent) = split /,/, ($attrs->{Parent} // $attrs->{ID});
            $mrna{ $attrs->{ID} } = {
                seqid => $seqid, start => $start, end => $end, strand => $strand,
                gene => $parent, name => $attrs->{Name},
            };
            push @order, $attrs->{ID};
        } elsif ($type eq 'CDS') {
            next unless defined $attrs->{Parent};
            foreach my $parent (split /,/, $attrs->{Parent}) {
                $cds_len{$parent} += $end - $start + 1;
                $cds_parent{ $attrs->{ID} } = $parent if defined $attrs->{ID};
            }
        }
    }
    close $fh;
    return { gene => \%gene, mrna => \%mrna, cds_parent => \%cds_parent,
             cds_len => \%cds_len, order => \@order };
}

## gene -> its mRNA with the longest CDS (ties: the first in the file).
sub choose_longest {
    my ($model) = @_;
    my %best;
    foreach my $mrna_id (@{ $model->{order} }) {
        my $len = $model->{cds_len}{$mrna_id} or next;
        my $gene_id = $model->{mrna}{$mrna_id}{gene};
        if (!exists $best{$gene_id} || $len > $model->{cds_len}{ $best{$gene_id} }) {
            $best{$gene_id} = $mrna_id;
        }
    }
    return \%best;
}

## cds.nt.fa -> sequence per CHOSEN mRNA (see the header rule at the top).
sub read_cds {
    my ($cds, $model, $chosen) = @_;
    my %wanted;
    foreach my $gene_id (keys %$chosen) { $wanted{ $chosen->{$gene_id} } = 1; }
    my %seq;
    my $unmatched = 0;
    my $current;
    open(my $fh, '<', $cds) or die "cannot read $cds: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        if ($line =~ /^>(\S+)/) {
            my $id = $1;
            $id =~ s/:(cds|pep)$//;
            my $mrna_id = exists $model->{mrna}{$id} ? $id : $model->{cds_parent}{$id};
            $unmatched++ unless defined $mrna_id;
            ## The first record wins: several CDS records can map to one mRNA.
            $current = (defined $mrna_id && $wanted{$mrna_id} && !exists $seq{$mrna_id}) ? $mrna_id : undef;
            $seq{$current} = '' if defined $current;
        } elsif (defined $current) {
            $line =~ s/\s+//g;
            $seq{$current} .= $line;
        }
    }
    close $fh;
    $seq{__unmatched} = $unmatched;
    return \%seq;
}

## The gene name to show, or '' when there is nothing better than the ID itself.
## Gene Name first (RefSeq: PIGG / LOC118665051; MOOP-named sets: GPN1), then the mRNA's.
## Not a name:
##   - a gene Name equal to an ID (MENDER: gene Name = the transcript ID)
##   - an mRNA Name that is its own ID minus a prefix (RefSeq: XM_.. for rna-XM_..)
## The prefix rule is for mRNA names only: RefSeq gene IDs are gene-<Name>
## (gene-PIGG), so applied to genes it would throw away every RefSeq gene name.
sub display_name {
    my ($model, $mrna_id) = @_;
    my $m = $model->{mrna}{$mrna_id};
    my $gene_id = $m->{gene};
    my $gene_name = exists $model->{gene}{$gene_id} ? $model->{gene}{$gene_id}{name} : undef;
    if (defined $gene_name && $gene_name ne '' && $gene_name ne $gene_id && $gene_name ne $mrna_id) {
        return $gene_name;
    }
    my $mrna_name = $m->{name};
    if (defined $mrna_name && $mrna_name ne '' && $mrna_name ne $gene_id
        && !ends_with($mrna_id, $mrna_name)) {
        return $mrna_name;
    }
    return '';
}

sub ends_with {
    my ($text, $tail) = @_;
    return length($text) >= length($tail) && substr($text, -length($tail)) eq $tail;
}

sub parse_attributes {
    my ($text) = @_;
    my %attr;
    foreach my $pair (split /;/, $text) {
        my ($key, $value) = split /=/, $pair, 2;
        next unless defined $value;
        $key =~ s/^\s+//;
        $value =~ s/%([0-9A-Fa-f]{2})/chr(hex($1))/ge;
        $attr{$key} = $value;
    }
    return \%attr;
}

main();
