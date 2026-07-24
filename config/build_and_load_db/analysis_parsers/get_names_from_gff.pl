#!/usr/bin/perl
use strict;
use warnings;
use URI::Escape;

# Unified gene-names extractor. Auto-detects GFF format from gene-line attributes:
#   ensembl : ID=gene:   -> uses Name= and description= from gene lines
#   refseq  : ID=gene-   or Dbxref=GeneID: -> uses gene= and product= from CDS lines
#   generic : not supported for name extraction (use assign_gene_names.pl instead)
#
# Output columns: ID  MAINID  GroupId  Desc  Note

my $gff = shift or die "Usage: $0 genomic.gff\n";

my $format = detect_format($gff);
if ($format eq 'generic') {
    warn "WARNING: GFF format not recognised as Ensembl or RefSeq. No names extracted.\n";
    print join("\t", qw(ID MAINID GroupId Desc Note)), "\n";
    exit 0;
}

my %gene_sym;     # gene_id -> symbol
my %gene_desc;    # gene_id -> description
my %tx_to_gene;   # transcript_id -> gene_id
my %tx_desc;      # transcript_id -> product desc  (RefSeq)
my %canonical_tx; # transcript_id -> 1             (Ensembl canonical)
my %prot_to_tx;   # protein_id -> transcript_id
my %prot_desc;    # protein_id -> product desc     (RefSeq)
my %groups;       # gene_id -> { id -> type }
my %cds_len;      # protein_id -> cumulative CDS bp

open my $GFF, '<', $gff or die "Can't open GFF: $!\n";
while (my $line = <$GFF>) {
    chomp $line;
    next if $line =~ /^#/;
    my @f = split /\t/, $line;
    next unless @f >= 9;
    my ($type, $start, $end, $attrs) = @f[2, 3, 4, 8];

    if ($format eq 'ensembl') {
        if ($type eq 'gene') {
            my ($gn_id)    = $attrs =~ /\bID=gene:([^;]+)/;
            next unless defined $gn_id;
            my ($name)     = $attrs =~ /\bName=([^;]+)/;
            my ($raw_desc) = $attrs =~ /\bdescription=([^;]+)/;
            my $desc = '';
            if (defined $raw_desc) {
                $desc = uri_unescape($raw_desc);
                $desc =~ s/\s*\[Source:[^\]]+\]//g;
            }
            $gene_sym{$gn_id}  = $name // $gn_id;
            $gene_desc{$gn_id} = $desc;
        }
        elsif ($type eq 'mRNA') {
            my ($tx_id) = $attrs =~ /\bID=transcript:([^;]+)/;
            my ($gn_id) = $attrs =~ /\bParent=gene:([^;]+)/;
            next unless defined $tx_id && defined $gn_id;
            $tx_to_gene{$tx_id}   = $gn_id;
            $canonical_tx{$tx_id} = 1 if $attrs =~ /\bEnsembl_canonical\b/;
            $groups{$gn_id}{$tx_id} = 'mRNA';
        }
        elsif ($type eq 'CDS') {
            my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
            my ($tx_id)   = $attrs =~ /\bParent=transcript:([^;]+)/;
            next unless defined $prot_id && defined $tx_id;
            my $gn_id = $tx_to_gene{$tx_id};
            unless (defined $gn_id) { warn "No gene for transcript $tx_id\n"; next }
            my $cds_id = "CDS:$prot_id";
            $groups{$gn_id}{$tx_id}   = 'mRNA';
            $groups{$gn_id}{$prot_id} = 'protein';
            $groups{$gn_id}{$cds_id}  = 'CDS';
            $prot_to_tx{$prot_id}    = $tx_id;
            $cds_len{$prot_id}      += ($end - $start + 1);
        }
    }
    elsif ($format eq 'refseq') {
        if ($type eq 'gene') {
            my ($gn_id) = $attrs =~ /\bGeneID:([^;,]+)/;
            next unless defined $gn_id;
            my ($sym)   = $attrs =~ /\bgene=([^;]+)/;
            $gene_sym{$gn_id}  = $sym // $gn_id;
            $gene_desc{$gn_id} = '';  # filled below from MAINID protein product
        }
        elsif ($type eq 'mRNA') {
            my ($tx_id) = $attrs =~ /\btranscript_id=([^;]+)/;
            my ($gn_id) = $attrs =~ /\bGeneID:([^;,]+)/;
            next unless defined $tx_id && defined $gn_id;
            my ($prod)  = $attrs =~ /\bproduct=([^;]+)/;
            $tx_to_gene{$tx_id} = $gn_id;
            $tx_desc{$tx_id}    = $prod // '';
            $groups{$gn_id}{$tx_id} = 'mRNA';
        }
        elsif ($type eq 'CDS') {
            my ($tx_id)   = $attrs =~ /\bParent=rna-([^;]+)/;   # undef for prokaryotes
            my ($gn_id)   = $attrs =~ /\bGeneID:([^;,]+)/;
            my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
            next unless defined $gn_id && defined $prot_id;
            my ($prod) = $attrs =~ /\bproduct=([^;]+)/;
            my $cds_id = "cds-$prot_id";
            if (defined $tx_id) {
                $groups{$gn_id}{$tx_id} = 'mRNA';
                $tx_to_gene{$tx_id}    = $gn_id;
            }
            $groups{$gn_id}{$prot_id} = 'protein';
            $groups{$gn_id}{$cds_id}  = 'CDS';
            $prot_to_tx{$prot_id}    = $tx_id // '';
            $prot_desc{$prot_id}     = $prod  // '';
            $cds_len{$prot_id}      += ($end - $start + 1);
        }
    }
}
close $GFF;

# Select MAINID (best representative protein) per gene
my %main_id;
for my $gn_id (keys %groups) {
    my @proteins = grep { $groups{$gn_id}{$_} eq 'protein' } keys %{$groups{$gn_id}};
    my @ranked;
    if ($format eq 'ensembl') {
        @ranked = sort {
            (($canonical_tx{$prot_to_tx{$b}//''}//0) <=> ($canonical_tx{$prot_to_tx{$a}//''}//0))
            || (($cds_len{$b}//0) <=> ($cds_len{$a}//0))
            || ($a cmp $b)
        } @proteins;
    }
    else {
        @ranked = sort {
            (($b =~ /^NP_/) <=> ($a =~ /^NP_/))   # curated NP_ before predicted XP_
            || (($cds_len{$b}//0) <=> ($cds_len{$a}//0))
            || ($a cmp $b)
        } @proteins;
    }
    $main_id{$gn_id} = $ranked[0] if @ranked;
}

# Fill RefSeq gene desc from MAINID protein (gene lines carry no product)
if ($format eq 'refseq') {
    for my $gn_id (keys %gene_desc) {
        if (!$gene_desc{$gn_id} && defined $main_id{$gn_id}) {
            $gene_desc{$gn_id} = $prot_desc{$main_id{$gn_id}} // '';
        }
    }
}

my $src = $format eq 'ensembl' ? 'Ensembl' : 'RefSeq';

print join("\t", qw(ID MAINID GroupId Desc Note)), "\n";

for my $gn_id (sort keys %groups) {
    my $sym     = $gene_sym{$gn_id}  // $gn_id;
    my $gn_desc = $gene_desc{$gn_id} // '';
    my $main    = $main_id{$gn_id}   // '';

    print join("\t", $gn_id, $gn_id, $gn_id, "$sym: $gn_desc", $src), "\n";

    for my $id (sort keys %{$groups{$gn_id}}) {
        my $type   = $groups{$gn_id}{$id};
        my $mainid = ($id eq $main) ? 'SELF' : $main;

        if ($type eq 'mRNA') {
            my $desc = $format eq 'refseq' ? ($tx_desc{$id} || $gn_desc) : $gn_desc;
            print join("\t", $id, $mainid, $gn_id, "$sym: $desc", $src), "\n";
        }
        elsif ($type eq 'protein') {
            my $tx_id = $prot_to_tx{$id} // '';
            my $desc  = $format eq 'refseq'
                ? ($prot_desc{$id} || $tx_desc{$tx_id} || $gn_desc)
                : $gn_desc;
            print join("\t", $id, $mainid, $gn_id, "$sym: $desc", $src), "\n";
        }
        elsif ($type eq 'CDS') {
            (my $prot_id = $id) =~ s/^(?:cds-|CDS:)//;
            my $tx_id = $prot_to_tx{$prot_id} // '';
            my $desc  = $format eq 'refseq'
                ? ($prot_desc{$prot_id} || $tx_desc{$tx_id} || $gn_desc)
                : $gn_desc;
            print join("\t", $id, $mainid, $gn_id, "$sym: $desc", $src), "\n";
        }
    }
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
        last;
    }
    close $fh;
    return $fmt;
}
