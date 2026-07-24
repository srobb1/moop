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
    while (my $line = <$fh>) {
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9 && $f[2] eq 'gene';
        if    ($f[8] =~ /\bID=gene:/)                                 { $fmt = 'ensembl'; last }
        elsif ($f[8] =~ /\bID=gene-/ || $f[8] =~ /\bDbxref=GeneID:/) { $fmt = 'refseq';  last }
        last;  # first gene line matched neither — generic
    }
    close $fh;
    return $fmt;
}
