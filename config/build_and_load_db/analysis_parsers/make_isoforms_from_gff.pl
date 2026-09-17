#!/usr/bin/perl
use strict;
use warnings;

# Unified isoforms builder. Auto-detects GFF format from gene-line attributes:
#   ensembl : ID=gene:   (colon prefix)
#   refseq  : ID=gene-   or Dbxref=GeneID:
#   generic : anything else (parse mRNA/transcript ID= and Parent=)
#
# Output columns: [semicolon-sep children]  None  gene_id

my $gff           = shift or die "Usage: $0 genomic.gff [protein_coding_tag]\n";
my $proteinCoding = shift // '';

my $format = detect_format($gff);

my %groups;
open my $GFF, '<', $gff or die "Can't open GFF $gff: $!\n";
while (my $line = <$GFF>) {
    chomp $line;
    next if $line =~ /^#/;

    if ($format eq 'ensembl') {
        next unless $line =~ /\tmRNA\t/;
        next if $proteinCoding && $line !~ /$proteinCoding/;
        my ($tx_id, $gn_id) = $line =~ /\bID=transcript:([^;]+).*\bParent=gene:([^;]+)/;
        $groups{$gn_id}{$tx_id}++ if defined $tx_id && defined $gn_id;
    }
    elsif ($format eq 'refseq') {
        if ($line =~ /\tCDS\t.*\bParent=rna-([^;]+).*\bGeneID:([^;,]+).*\bprotein_id=([^;]+)/) {
            # eukaryotic: gene -> mRNA -> CDS
            my ($tx_id, $gn_id, $prot_id) = ($1, $2, $3);
            $groups{$gn_id}{$tx_id}         = 'transcript';
            $groups{$gn_id}{$prot_id}       = 'protein';
            $groups{$gn_id}{"cds-$prot_id"} = 'cds';
        }
        elsif ($line =~ /\tCDS\t.*\bParent=gene-[^;]+.*\bGeneID:([^;,]+).*\bprotein_id=([^;]+)/) {
            # prokaryotic: gene -> CDS (no mRNA layer)
            my ($gn_id, $prot_id) = ($1, $2);
            $groups{$gn_id}{$prot_id}       = 'protein';
            $groups{$gn_id}{"cds-$prot_id"} = 'cds';
        }
    }
    else {
        # generic: any mRNA or transcript feature
        next unless $line =~ /\t(?:mRNA|transcript)\t/;
        next if $proteinCoding && $line !~ /$proteinCoding/;
        my ($tx_id) = $line =~ /\bID=([^;]+)/;
        my ($gn_id) = $line =~ /\bParent=([^;]+)/;      # standard GFF3 parent link
        ($gn_id)    = $line =~ /\bgeneID=([^;]+)/ unless defined $gn_id;  # fallback (e.g. Schmidtea)
        $groups{$gn_id}{$tx_id}++ if defined $tx_id && defined $gn_id;
    }
}
close $GFF;

for my $gene (sort keys %groups) {
    my @children = sort keys %{$groups{$gene}};
    print join("\t", join(';', @children), 'None', $gene), "\n";
}

sub detect_format {
    my $file = shift;
    open my $fh, '<', $file or die "Can't open $file: $!\n";
    my $fmt = 'generic';
    my $source = '';
    while (my $line = <$fh>) {
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        $source ||= $f[1] if @f >= 9;
        next unless @f >= 9 && $f[2] eq 'gene';
        if    ($f[8] =~ /\bID=gene:/)                                 { $fmt = 'ensembl'; last }
        elsif ($f[8] =~ /\bID=gene-/ || $f[8] =~ /\bDbxref=GeneID:/) { $fmt = 'refseq';  last }
        last;  # first gene line matched neither — generic
    }
    close $fh;

    # LiftOn/Liftoff output matches the refseq id-namespace pattern above (real
    # RefSeq accessions, carried over by the liftover) but the "refseq" branch
    # below requires Parent=rna-/GeneID:/protein_id= all on the SAME CDS line --
    # LiftOn gives that to only the minority of CDS lines whose liftover kept a
    # usable protein, so this branch would (and did, for Parastichopus: 3,391
    # isoforms.tsv lines instead of 21,055 genes) silently cover a fraction of
    # the gene set using ids ("XM_...", "cds-XP_...") that don't match what the
    # rest of this fix uses ("rna-XM_...", "gene-LOC..."), so assign_gene_names.pl's
    # join against the homology files matches nothing and geneNames.tsv comes out
    # as a bare header. Mirrors the same override in process_one_geneset.sh's
    # shell-level detector and parse_GFF3_to_MOOP_TSV.pl::detect_format; all three
    # are duplicated the same way the original ensembl/refseq detection already
    # was, and must be kept in sync. See notes/LIFTOVER_GENESET_CRITERIA.md.
    if ($fmt eq 'refseq' && ($source =~ /^lift(on|off)$/i || !_any_cds_has_id($file))) {
        $fmt = 'generic';
    }
    return $fmt;
}

sub _any_cds_has_id {
    my ($file) = @_;
    open my $fh, '<', $file or die "Can't open $file: $!\n";
    my $found = 0;
    while (my $line = <$fh>) {
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9 && $f[2] eq 'CDS';
        if ($f[8] =~ /\bID=/) { $found = 1; last }
    }
    close $fh;
    return $found;
}
