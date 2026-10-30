#!/usr/bin/perl
use strict;
use warnings;

# Usage: perl make_names_from_refseq_gff.pl genomic.gff > geneNames.tsv
# Output: ID  MAINID  GroupId  ParentId  Type  Desc  Note
#
# Representative (main_id) selection priority:
#   1. Curated RefSeq protein (NP_) preferred over predicted (XP_)
#   2. Longest CDS (sum of all CDS exon lengths for that protein_id)
#   3. Alphabetical as final tiebreaker
#
# Desc = "sym: product" from each feature's own GFF attributes
# If gene has no desc, use MAINID protein's desc
# If mRNA/protein/CDS has no desc, inherit from parent

my $gff = shift or die "Usage: $0 genomic.gff\n";

my %gene_sym;    # gene_id -> symbol
my %gene_desc;   # gene_id -> desc
my %tx_to_gene;  # transcript_id -> gene_id
my %tx_desc;     # transcript_id -> desc
my %prot_to_tx;  # protein_id -> transcript_id
my %prot_desc;   # protein_id -> desc
my %groups;      # gene_id -> { id -> type }
my %cds_len;     # protein_id -> cumulative CDS length

open my $GFF, '<', $gff or die "Can't open GFF: $gff $!\n";
while (my $line = <$GFF>) {
    chomp $line;
    next if $line =~ /^#/;
    my @f = split /\t/, $line;
    next unless @f >= 9;
    my ($type, $start, $end, $attrs) = @f[2,3,4,8];

    if ($type eq 'gene') {
        my ($gene_id) = $attrs =~ /Dbxref=GeneID:([^;,]+)/;
        next unless defined $gene_id;
        my ($sym) = $attrs =~ /\bgene=([^;]+)/;
        $gene_sym{$gene_id}  = $sym // $gene_id;
        $gene_desc{$gene_id} = '';  # RefSeq gene lines have no product; filled below from MAINID
    }
    elsif ($type eq 'mRNA') {
        my ($tx_id)   = $attrs =~ /transcript_id=([^;]+)/;
        my ($gene_id) = $attrs =~ /Dbxref=GeneID:([^;,]+)/;
        next unless defined $tx_id && defined $gene_id;
        my ($prod) = $attrs =~ /product=([^;]+)/;
        $tx_to_gene{$tx_id} = $gene_id;
        $tx_desc{$tx_id}    = $prod // '';
        $groups{$gene_id}{$tx_id} = 'mRNA';
    }
    elsif ($type eq 'CDS') {
        my ($tx_id)      = $attrs =~ /Parent=rna-([^;]+)/;   # undef for prokaryotes (Parent=gene-)
        my ($gene_id)    = $attrs =~ /\bGeneID:([^;,]+)/;
        my ($protein_id) = $attrs =~ /protein_id=([^;]+)/;
        next unless defined $gene_id && defined $protein_id;
        my $cds_id = "cds-$protein_id";
        my ($prod) = $attrs =~ /product=([^;]+)/;

        if (defined $tx_id) {
            # Eukaryotic: gene -> mRNA -> CDS
            $groups{$gene_id}{$tx_id} = 'mRNA';
            $tx_to_gene{$tx_id}       = $gene_id;
        }
        $groups{$gene_id}{$protein_id} = 'protein';
        $groups{$gene_id}{$cds_id}     = 'CDS';
        $prot_to_tx{$protein_id}       = $tx_id // '';
        $prot_desc{$protein_id}        = $prod // '';
        $cds_len{$protein_id}         += ($end - $start + 1);
    }
}
close $GFF;

# Calculate MAINID (best representative protein) per gene
my %main_id;
for my $gene_id (keys %groups) {
    my @proteins = grep { $groups{$gene_id}{$_} eq 'protein' } keys %{$groups{$gene_id}};
    my @ranked = sort {
        (($b =~ /^NP_/) <=> ($a =~ /^NP_/))               # NP_ (curated) before XP_ (predicted)
        || (($cds_len{$b} // 0) <=> ($cds_len{$a} // 0))  # longer CDS first
        || ($a cmp $b)                                      # alphabetical tiebreaker
    } @proteins;
    $main_id{$gene_id} = $ranked[0] if @ranked;
}

# Fill gene desc from MAINID protein if gene has no desc
for my $gene_id (keys %gene_desc) {
    if ($gene_desc{$gene_id} eq '' && defined $main_id{$gene_id}) {
        $gene_desc{$gene_id} = $prot_desc{ $main_id{$gene_id} } // '';
    }
}

print join("\t", qw(ID MAINID GroupId Desc Note)), "\n";

for my $gene_id (sort keys %groups) {
    my $sym     = $gene_sym{$gene_id}  // $gene_id;
    my $gn_desc = $gene_desc{$gene_id} // '';
    my $main    = $main_id{$gene_id}   // '';

    print join("\t", $gene_id, $gene_id, $gene_id, "$sym: $gn_desc", 'RefSeq'), "\n";

    for my $id (sort keys %{$groups{$gene_id}}) {
        my $type   = $groups{$gene_id}{$id};
        my $mainid = ($id eq $main) ? 'SELF' : $main;

        if ($type eq 'mRNA') {
            my $desc = $tx_desc{$id} || $gn_desc;
            print join("\t", $id, $mainid, $gene_id, "$sym: $desc", 'RefSeq'), "\n";
        }
        elsif ($type eq 'protein') {
            my $tx_id = $prot_to_tx{$id} // '';
            my $desc  = $prot_desc{$id} || $tx_desc{$tx_id} || $gn_desc;
            print join("\t", $id, $mainid, $gene_id, "$sym: $desc", 'RefSeq'), "\n";
        }
        elsif ($type eq 'CDS') {
            (my $prot_id = $id) =~ s/^cds-//;
            my $tx_id = $prot_to_tx{$prot_id} // '';
            my $desc  = $prot_desc{$prot_id} || $tx_desc{$tx_id} || $gn_desc;
            print join("\t", $id, $mainid, $gene_id, "$sym: $desc", 'RefSeq'), "\n";
        }
    }
}
