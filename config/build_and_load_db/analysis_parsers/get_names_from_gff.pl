#!/usr/bin/perl
use strict;
use warnings;
use URI::Escape;
use FindBin;
use lib "$FindBin::Bin";
use GeneNameInformativeness qw(is_informative_name);

# Unified gene-names extractor. Auto-detects GFF format from gene-line attributes:
#   ensembl : ID=gene:   -> uses Name= and description= from gene lines
#   refseq  : ID=gene-   or Dbxref=GeneID: -> uses gene= and product= from CDS lines
#   generic : not supported for name extraction (use assign_gene_names.pl instead)
#
# Output columns: ID  MAINID  GroupId  Desc  Note
#
# Optional 2nd arg: a homology-derived geneNames.tsv (assign_gene_names.pl's
# output, built from the SAME homology sources as every other gene set --
# RBBH/OMA/Swiss-Prot/PANTHER, all built unconditionally earlier in the
# pipeline regardless of GFF source). When a gene's own RefSeq/Ensembl name is
# judged uninformative (GeneNameInformativeness::is_informative_name), that
# gene's rows are replaced with the homology row for the whole group -- same
# Desc/Note, same per-id MAINID convention (SELF for the selected id, the
# selected id itself for every other id in the group) that assign_gene_names.pl
# already uses. Genes with an informative native name are untouched. Coverage
# stays total either way: this never drops a gene to a blank row, so a
# consumer that treats a missing id as "no name any more" (updateFASTA.pl)
# can't be handed a partial file.

my $gff           = shift or die "Usage: $0 genomic.gff [homology_geneNames.tsv]\n";
my $homology_file = shift;

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

# RefSeq's product= (and gene=, harmlessly) arrives percent-encoded, same as
# Ensembl's description= -- but only description= was ever decoded here, so
# every RefSeq desc shipped with literal "%2C" etc. into geneNames.tsv. Decode
# both the same way parse_GFF3_to_MOOP_TSV.pl::emit_refseq already does.
sub decode_attr {
    my ($v) = @_;
    return $v unless defined $v;
    $v =~ s/%([0-9A-Fa-f]{2})/chr(hex($1))/ge;
    return $v;
}

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
            $gene_sym{$gn_id}  = defined $sym ? decode_attr($sym) : $gn_id;
            $gene_desc{$gn_id} = '';  # filled below from MAINID protein product
        }
        elsif ($type eq 'mRNA') {
            my ($tx_id) = $attrs =~ /\btranscript_id=([^;]+)/;
            my ($gn_id) = $attrs =~ /\bGeneID:([^;,]+)/;
            next unless defined $tx_id && defined $gn_id;
            my ($prod)  = $attrs =~ /\bproduct=([^;]+)/;
            $tx_to_gene{$tx_id} = $gn_id;
            $tx_desc{$tx_id}    = defined $prod ? decode_attr($prod) : '';
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
            $prot_desc{$prot_id}     = defined $prod ? decode_attr($prod) : '';
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

# ── Optional homology fallback (assign_gene_names.pl's output) ────────────────
# Columns: ID  MAINID  GroupId  Desc  Note. Desc/Note are constant across a
# group; MAINID is 'SELF' on the row for the selected id and that id's own
# value everywhere else -- read straight out of any row rather than
# recomputed, so this can't disagree with what assign_gene_names.pl decided.
my %homology; # group_id -> { selected_id, desc, note }
if (defined $homology_file) {
    open my $HOM, '<', $homology_file or die "Can't open homology names file: $homology_file $!\n";
    my $header = <$HOM>; # ID MAINID GroupId Desc Note
    while (my $line = <$HOM>) {
        chomp $line;
        next unless length $line;
        my ($id, $mainid, $group_id, $desc, $note) = split /\t/, $line;
        next unless defined $group_id;
        my $h = $homology{$group_id} //= {};
        $h->{desc} = $desc;
        $h->{note} = $note;
        $h->{selected_id} = $id if defined $mainid && $mainid eq 'SELF';
    }
    close $HOM;
}

print join("\t", qw(ID MAINID GroupId Desc Note)), "\n";

for my $gn_id (sort keys %groups) {
    my $sym     = $gene_sym{$gn_id}  // $gn_id;
    my $gn_desc = $gene_desc{$gn_id} // '';
    my $main    = $main_id{$gn_id}   // '';

    my $hom = $homology{$gn_id};
    my $use_homology = $hom && defined $hom->{selected_id}
        && !is_informative_name($gn_id, $sym, $gn_desc);

    if ($use_homology) {
        my $sel = $hom->{selected_id};

        # assign_gene_names.pl never emits a row for the bare gene id -- its
        # rows come from isoforms.tsv, which only lists transcript/protein/CDS
        # children. But the "gene" feature itself still needs a name (the
        # native branch below prints one), so add it here the same way,
        # self-pointing like the native gene row does.
        print join("\t", $gn_id, $gn_id, $gn_id, $hom->{desc}, $hom->{note}), "\n";

        for my $id (sort keys %{$groups{$gn_id}}) {
            my $mainid = ($id eq $sel) ? 'SELF' : $sel;
            print join("\t", $id, $mainid, $gn_id, $hom->{desc}, $hom->{note}), "\n";
        }
        next;
    }

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
