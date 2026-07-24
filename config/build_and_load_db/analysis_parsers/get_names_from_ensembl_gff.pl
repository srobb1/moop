#!/usr/bin/perl
use strict;
use warnings;
use URI::Escape;

# Usage: perl make_names_from_ensembl_gff.pl genomic.gff > geneNames.tsv
# Output: ID  MAINID  GroupId  ParentId  Type  Desc  Note
#
# Representative (main_id) selection priority:
#   1. Protein from Ensembl_canonical transcript (curated representative)
#   2. Longest CDS (sum of all CDS exon lengths for that protein_id)
#   3. Alphabetical as final tiebreaker
#
# Desc = "sym: description" from each feature's own GFF attributes
# Gene desc comes from description= on the gene line (URL decoded, [Source:...] stripped)
# If gene has no desc, use MAINID protein's inherited desc
# mRNA/protein/CDS have no own desc in Ensembl GFF; all inherit from gene

my $gff = shift or die "Usage: $0 genomic.gff\n";

my %gene_sym;       # gene_id -> symbol
my %gene_desc;      # gene_id -> description
my %tx_to_gene;     # transcript_id -> gene_id
my %canonical_tx;   # transcript_id -> 1 if Ensembl_canonical
my %prot_to_tx;     # protein_id -> transcript_id
my %groups;         # gene_id -> { id -> type }
my %cds_len;        # protein_id -> cumulative CDS length

open my $GFF, '<', $gff or die "Can't open GFF: $gff $!\n";
while (my $line = <$GFF>) {
    chomp $line;
    next if $line =~ /^#/;
    my @f = split /\t/, $line;
    next unless @f >= 9;
    my ($type, $start, $end, $attrs) = @f[2,3,4,8];

    if ($type eq 'gene') {
        my ($gene_id)  = $attrs =~ /\bID=gene:([^;]+)/;
        next unless defined $gene_id;
        my ($name)     = $attrs =~ /\bName=([^;]+)/;
        my ($raw_desc) = $attrs =~ /\bdescription=([^;]+)/;
        my $desc = '';
        if (defined $raw_desc) {
            $desc = uri_unescape($raw_desc);
            $desc =~ s/\s*\[Source:[^\]]+\]//g;  # strip [Source:MGI Symbol%3BAcc:...] etc
        }
        $gene_sym{$gene_id}  = $name // $gene_id;
        $gene_desc{$gene_id} = $desc;
    }
    elsif ($type eq 'mRNA') {
        my ($tx_id) = $attrs =~ /\bID=transcript:([^;]+)/;
        my ($gn_id) = $attrs =~ /\bParent=gene:([^;]+)/;
        next unless defined $tx_id && defined $gn_id;
        $tx_to_gene{$tx_id}   = $gn_id;
        $canonical_tx{$tx_id} = 1 if $attrs =~ /Ensembl_canonical/;  # flag for MAINID selection
        $groups{$gn_id}{$tx_id} = 'mRNA';
    }
    elsif ($type eq 'CDS') {
        my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
        my ($tx_id)   = $attrs =~ /\bParent=transcript:([^;]+)/;
        next unless defined $prot_id && defined $tx_id;
        my $gene_id = $tx_to_gene{$tx_id};
        unless (defined $gene_id) {
            warn "No gene found for transcript $tx_id\n";
            next;
        }
        my $cds_id = "CDS:$prot_id";

        $groups{$gene_id}{$tx_id}   = 'mRNA';
        $groups{$gene_id}{$prot_id} = 'protein';
        $groups{$gene_id}{$cds_id}  = 'CDS';
        $prot_to_tx{$prot_id}       = $tx_id;
        $cds_len{$prot_id}         += ($end - $start + 1);
    }
}
close $GFF;

# Calculate MAINID (best representative protein) per gene
my %main_id;
for my $gene_id (keys %groups) {
    my @proteins = grep { $groups{$gene_id}{$_} eq 'protein' } keys %{$groups{$gene_id}};
    my @ranked = sort {
        (($canonical_tx{ $prot_to_tx{$b} // '' } // 0) <=> ($canonical_tx{ $prot_to_tx{$a} // '' } // 0))  # Ensembl_canonical first
        || (($cds_len{$b} // 0) <=> ($cds_len{$a} // 0))                                                    # longer CDS first
        || ($a cmp $b)                                                                                        # alphabetical tiebreaker
    } @proteins;
    $main_id{$gene_id} = $ranked[0] if @ranked;
}

print join("\t", qw(ID MAINID GroupId Desc Note)), "\n";

for my $gene_id (sort keys %groups) {
    my $sym     = $gene_sym{$gene_id}  // $gene_id;
    my $gn_desc = $gene_desc{$gene_id} // '';
    my $main    = $main_id{$gene_id}   // '';

    print join("\t", $gene_id, $gene_id, $gene_id, "$sym: $gn_desc", 'Ensembl'), "\n";

    for my $id (sort keys %{$groups{$gene_id}}) {
        my $type   = $groups{$gene_id}{$id};
        my $mainid = ($id eq $main) ? 'SELF' : $main;

        if ($type eq 'mRNA') {
            # Ensembl mRNAs have no description of their own; inherit from gene
            print join("\t", $id, $mainid, $gene_id, "$sym: $gn_desc", 'Ensembl'), "\n";
        }
        elsif ($type eq 'protein') {
            # Ensembl proteins have no description of their own; inherit from gene
            print join("\t", $id, $mainid, $gene_id, "$sym: $gn_desc", 'Ensembl'), "\n";
        }
        elsif ($type eq 'CDS') {
            (my $prot_id = $id) =~ s/^CDS://;
            print join("\t", $id, $mainid, $gene_id, "$sym: $gn_desc", 'Ensembl'), "\n";
        }
    }
}
