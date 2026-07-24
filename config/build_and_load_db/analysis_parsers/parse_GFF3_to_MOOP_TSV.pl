#!/usr/bin/perl
use strict;
use warnings;
use URI::Escape;

# Unified feature-table builder. Auto-detects GFF format from gene-line attributes:
#   ensembl : ID=gene:   -> two-pass, emits gene/mRNA/CDS/protein per CDS line
#   refseq  : ID=gene-   or Dbxref=GeneID: -> single-pass state machine
#   generic : ID=/Parent= on mRNA+gene lines
#
# Output: metadata header then tab-sep feature rows (stdout)
# Side output: feature_coords.tsv in current directory (no header)
#   cols: feature_uniquename, gene_id, chr, start, end, strand

my $gff        = shift or die "Usage: $0 genomic.gff metadata.yaml [cds.nt.fa protein.aa.fa]\n";
my $metadata   = shift or die "Usage: $0 genomic.gff metadata.yaml [cds.nt.fa protein.aa.fa]\n";
my $cds_fasta  = shift;   # optional — used only by emit_generic
my $prot_fasta = shift;   # optional — used only by emit_generic

# Parse metadata (identical fields for all formats)
my ($genus, $species, $commonname, $taxon_id,
    $genome_accession, $genome_name, $source, $details) = ('') x 8;
open my $META, '<', $metadata or die "Can't open $metadata: $!\n";
while (my $line = <$META>) {
    chomp $line;
    if    ($line =~ /^genus: (\S+)/)         { $genus            = $1 }
    elsif ($line =~ /species: (\S+)/)         { $species          = $1 }
    elsif ($line =~ /common-name: (.+)$/)     { $commonname       = $1 }
    elsif ($line =~ /ncbi-taxon-id: (\S+)/)   { $taxon_id         = $1 }
    elsif ($line =~ /^genome-accession:\s*(\S+)/) { $genome_accession = $1 }
    elsif ($line =~ /^simrbase-prefix:\s*(\S+)/)  { $genome_name      = $1 }
    elsif ($line =~ /^genome-name:\s*(\S+)/)       { $genome_name    ||= $1 }
    elsif ($line =~ /source: (\S+)/)          { $source           = $1 }
    elsif ($line =~ /details: (.+)$/)         { $details          = $1 }
}
close $META;

print "## Genus: $genus
## Species: $species
## Common Name: $commonname
## NCBI Taxon ID: $taxon_id
## Genome Accession: $genome_accession
## Genome Name: $genome_name
## Genome Description: Assembly is from $source. $details
## This_Uniquename\tThis_Type\tParent_Uniquename\tParent_Type\tThis_Name\tThis_Description\n";

my $format = detect_format($gff);

if    ($format eq 'ensembl') { emit_ensembl($gff) }
elsif ($format eq 'refseq')  { emit_refseq($gff)  }
else                          { emit_generic($gff, $cds_fasta, $prot_fasta) }

open my $COORDS_FH, '>', 'feature_coords.tsv' or die "Can't write feature_coords.tsv: $!\n";
write_feature_coords($gff, $format, $COORDS_FH);
close $COORDS_FH;

# --- Ensembl: two-pass ---
sub emit_ensembl {
    my $gff = shift;
    my (%gene_name, %gene_desc, %tx_to_gene, %printed, %printed_gene);

    # Pass 1: collect gene name/desc and tx->gene map
    open my $fh, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($type, $attrs) = @f[2, 8];

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
            $gene_name{$gn_id} = $name // $gn_id;
            $gene_desc{$gn_id} = $desc;
        }
        elsif ($type eq 'mRNA') {
            my ($tx_id) = $attrs =~ /\bID=transcript:([^;]+)/;
            my ($gn_id) = $attrs =~ /\bParent=gene:([^;]+)/;
            next unless defined $tx_id && defined $gn_id;
            $tx_to_gene{$tx_id} = $gn_id;
        }
    }
    close $fh;

    # Pass 2: emit one row-set per unique protein_id
    open $fh, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($type, $attrs) = @f[2, 8];
        next unless $type eq 'CDS';

        my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
        my ($tx_id)   = $attrs =~ /\bParent=transcript:([^;]+)/;
        next unless defined $prot_id && defined $tx_id;
        my $gn_id = $tx_to_gene{$tx_id};
        next unless defined $gn_id;
        next if $printed{$prot_id}++;

        my $name   = $gene_name{$gn_id} // $gn_id;
        my $note   = $gene_desc{$gn_id} // '';
        my $cds_id = "CDS:$prot_id";

        print join("\t", $gn_id,   'gene',    '',      '',      $name, $note), "\n"
            unless $printed_gene{$gn_id}++;
        print join("\t", $tx_id,   'mRNA',    $gn_id,  'gene',  $name, $note), "\n";
        print join("\t", $cds_id,  'CDS',     $tx_id,  'mRNA',  $name, $note), "\n";
        print join("\t", $prot_id, 'protein', $cds_id, 'CDS',   $name, $note), "\n";
    }
    close $fh;
}

# --- RefSeq: single-pass state machine ---
sub emit_refseq {
    my $gff = shift;
    my (%printed_prot, %printed_gene);
    my ($gene_id, $mrna_id) = ('', '');

    open my $fh, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($type, $attrs) = @f[2, 8];

        if ($type eq 'gene') {
            ($gene_id) = $attrs =~ /\bGeneID:([^;,]+)/;
            $gene_id //= '';
            $mrna_id = '';
        }
        elsif ($type eq 'mRNA') {
            ($mrna_id) = $attrs =~ /\btranscript_id=([^;]+)/;
            $mrna_id //= '';
        }
        elsif ($type eq 'CDS') {
            my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
            my ($cds_id)  = $attrs =~ /\bID=([^;]+)/;
            next unless defined $prot_id && defined $cds_id && $gene_id;

            my ($name) = ($attrs =~ /\bgene=([^;]+)/)
                      || ($attrs =~ /\blocus_tag=([^;]+)/)
                      ? ($1) : ('');
            my ($note) = $attrs =~ /\bproduct=([^;]+)/;
            $note //= '';
            $note =~ s/%([0-9A-Fa-f]{2})/chr(hex($1))/ge;

            my $parent_id   = $mrna_id || $gene_id;
            my $parent_type = $mrna_id ? 'mRNA' : 'gene';

            print join("\t", $gene_id,   'gene',    '',         '',           $name, $note), "\n"
                unless $printed_gene{$gene_id}++;
            print join("\t", $mrna_id,   'mRNA',    $gene_id,   'gene',       $name, $note), "\n"
                if $mrna_id;
            print join("\t", $cds_id,    'cds',     $parent_id, $parent_type, $name, $note), "\n";
            print join("\t", $prot_id,   'protein', $cds_id,    'cds',        $name, $note), "\n"
                unless $printed_prot{$prot_id}++;
        }
    }
    close $fh;
}

# --- Generic: emit gene/mRNA/transcript/cds/protein rows ---
# CDS and protein uniquenames are determined from the (post-rename) FASTA files
# when provided, so the DB IDs always match the FASTA IDs.
# Lookup order for CDS uniquename (given mRNA id $tx):
#   1. "$tx:cds" exists in cds FASTA   -> renamed matching case
#   2. First GFF CDS ID for $tx exists in cds FASTA  -> non-matching, unique CDS ID
#   3. Fallback: "$tx:cds"             -> synthesised (no FASTA entry yet)
# Same logic for protein using the cds uniquename as the GFF CDS fallback.
sub emit_generic {
    my ($gff, $cds_fasta, $prot_fasta) = @_;

    # Load FASTA ID sets (empty if files not provided)
    my (%cds_ids, %prot_ids);
    if ($cds_fasta && -f $cds_fasta) {
        open my $fh, '<', $cds_fasta or die "Can't open $cds_fasta: $!\n";
        while (<$fh>) { $cds_ids{$1} = 1 if /^>(\S+)/ }
        close $fh;
    }
    if ($prot_fasta && -f $prot_fasta) {
        open my $fh, '<', $prot_fasta or die "Can't open $prot_fasta: $!\n";
        while (<$fh>) { $prot_ids{$1} = 1 if /^>(\S+)/ }
        close $fh;
    }

    # Pass 1: collect feature types and first GFF CDS ID per mRNA/transcript
    my (%feature_types, %tx_to_gff_cds);
    open my $fh, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9 && $line =~ /\bID=/;
        my ($type, $attrs) = @f[2, 8];
        my ($id)     = $attrs =~ /\bID=([^;]+)/;
        my ($parent) = $attrs =~ /\bParent=([^;]+)/;
        next unless defined $id;
        $feature_types{$id} = $type;
        if (($type eq 'CDS') && defined $parent) {
            $tx_to_gff_cds{$parent} //= $id;
        }
    }
    close $fh;

    # Pass 2: emit rows
    open $fh, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$fh>) {
        chomp $line;
        next if $line =~ /^#/;
        next unless $line =~ /\bID=/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($type, $attrs) = @f[2, 8];

        my ($id)        = $attrs =~ /\bID=([^;]+)/;
        my ($parent_id) = $attrs =~ /\bParent=([^;]+)/;
        ($parent_id)    = $attrs =~ /\bgeneID=([^;]+)/ unless defined $parent_id;
        $parent_id //= '';
        my $parent_type = $parent_id ? ($feature_types{$parent_id} // '') : '';

        next unless $type eq 'gene' || $type eq 'mRNA' || $type eq 'transcript';
        my ($name) = $attrs =~ /\bName=([^;]+)/;
        my ($note) = $attrs =~ /\bNote=([^;]+)/;
        $name //= $id;
        $note //= '';
        print join("\t", $id, $type, $parent_id, $parent_type, $name, $note), "\n";

        if ($type eq 'mRNA' || $type eq 'transcript') {
            my $gff_cds = $tx_to_gff_cds{$id};

            # Determine CDS uniquename
            my $cds_id;
            if (%cds_ids) {
                if    ($cds_ids{"$id:cds"})                        { $cds_id = "$id:cds"    }
                elsif (defined $gff_cds && $cds_ids{$gff_cds})    { $cds_id = $gff_cds     }
                else                                                { $cds_id = "$id:cds"    }
            } else {
                $cds_id = "$id:cds";
            }

            # Determine protein uniquename
            my $prot_id;
            if (%prot_ids) {
                if    ($prot_ids{"$id:pep"})                        { $prot_id = "$id:pep"   }
                elsif ($prot_ids{"$cds_id:pep"})                    { $prot_id = "$cds_id:pep" }
                elsif (defined $gff_cds && $prot_ids{$gff_cds})    { $prot_id = $gff_cds    }
                elsif (defined $gff_cds && $prot_ids{"$gff_cds:pep"}) { $prot_id = "$gff_cds:pep" }
                else                                                { $prot_id = "$id:pep"   }
            } else {
                $prot_id = "$id:pep";
            }

            print join("\t", $cds_id,  'cds',     $id,      $type,  $name, $note), "\n";
            print join("\t", $prot_id, 'protein', $cds_id,  'cds',  $name, $note), "\n";
        }
    }
    close $fh;
}

# --- feature_coords.tsv writers ---

sub write_feature_coords {
    my ($gff, $format, $fh) = @_;
    if    ($format eq 'ensembl') { _coords_ensembl($gff, $fh) }
    elsif ($format eq 'refseq')  { _coords_refseq($gff, $fh)  }
    else                          { _coords_generic($gff, $fh) }
}

sub _coords_ensembl {
    my ($gff, $fh) = @_;
    my (%gene_coords, %tx_to_gene, %printed, %printed_gene);

    open my $in, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$in>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($chr, $type, $start, $end, $strand, $attrs) = @f[0, 2, 3, 4, 6, 8];
        if ($type eq 'gene') {
            my ($gn_id) = $attrs =~ /\bID=gene:([^;]+)/;
            next unless defined $gn_id;
            $gene_coords{$gn_id} = [$chr, $start, $end, $strand];
        } elsif ($type eq 'mRNA') {
            my ($tx_id) = $attrs =~ /\bID=transcript:([^;]+)/;
            my ($gn_id) = $attrs =~ /\bParent=gene:([^;]+)/;
            $tx_to_gene{$tx_id} = $gn_id if defined $tx_id && defined $gn_id;
        }
    }
    close $in;

    open $in, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$in>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($type, $attrs) = @f[2, 8];
        next unless $type eq 'CDS';

        my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
        my ($tx_id)   = $attrs =~ /\bParent=transcript:([^;]+)/;
        next unless defined $prot_id && defined $tx_id;
        my $gn_id = $tx_to_gene{$tx_id};
        next unless defined $gn_id;
        next if $printed{$prot_id}++;

        my $c = $gene_coords{$gn_id};
        next unless defined $c;
        my $cds_id = "CDS:$prot_id";

        print $fh join("\t", $gn_id,   $gn_id, @$c), "\n" unless $printed_gene{$gn_id}++;
        print $fh join("\t", $tx_id,   $gn_id, @$c), "\n";
        print $fh join("\t", $cds_id,  $gn_id, @$c), "\n";
        print $fh join("\t", $prot_id, $gn_id, @$c), "\n";
    }
    close $in;

    # Pass 3: exon, UTR, polypeptide — look up gene via tx_to_gene
    my %EXTRA_TYPES = map { $_ => 1 }
        qw(exon five_prime_UTR three_prime_UTR five_prime_utr three_prime_utr polypeptide);
    open $in, '<', $gff or die "Can't open $gff: $!\n";
    my %extra_seen;
    while (my $line = <$in>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($type, $attrs) = @f[2, 8];
        next unless $EXTRA_TYPES{$type};

        my ($raw_id) = $attrs =~ /\bID=([^;]+)/;
        next unless defined $raw_id;
        next if $extra_seen{$raw_id}++;

        # Parent is transcript:X — strip prefix to get tx_id
        my ($parent_raw) = $attrs =~ /\bParent=([^;]+)/;
        next unless defined $parent_raw;
        (my $tx_id = $parent_raw) =~ s/^transcript://;

        my $gn_id = $tx_to_gene{$tx_id};
        next unless defined $gn_id;
        my $c = $gene_coords{$gn_id};
        next unless defined $c;
        print $fh join("\t", $raw_id, $gn_id, @$c), "\n";
    }
    close $in;
}

sub _coords_refseq {
    my ($gff, $fh) = @_;
    my (%printed, %printed_gene);
    my ($gene_id, $mrna_id) = ('', '');
    my ($chr, $start, $end, $strand) = ('', '', '', '');

    open my $in, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$in>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($f_chr, $type, $f_start, $f_end, $f_strand, $attrs) = @f[0, 2, 3, 4, 6, 8];

        if ($type eq 'gene') {
            ($gene_id) = $attrs =~ /\bGeneID:([^;,]+)/;
            $gene_id //= '';
            $mrna_id = '';
            ($chr, $start, $end, $strand) = ($f_chr, $f_start, $f_end, $f_strand);
        } elsif ($type eq 'mRNA') {
            ($mrna_id) = $attrs =~ /\btranscript_id=([^;]+)/;
            $mrna_id //= '';
        } elsif ($type eq 'CDS') {
            my ($prot_id) = $attrs =~ /\bprotein_id=([^;]+)/;
            next unless defined $prot_id && $gene_id;
            next if $printed{$prot_id}++;

            print $fh join("\t", $gene_id,        $gene_id, $chr, $start, $end, $strand), "\n"
                unless $printed_gene{$gene_id}++;
            print $fh join("\t", $mrna_id,         $gene_id, $chr, $start, $end, $strand), "\n"
                if $mrna_id;
            print $fh join("\t", "cds-$prot_id",   $gene_id, $chr, $start, $end, $strand), "\n";
            print $fh join("\t", $prot_id,          $gene_id, $chr, $start, $end, $strand), "\n";
        } elsif ($gene_id && ($type eq 'exon'
                           || $type eq 'five_prime_UTR'  || $type eq 'three_prime_UTR'
                           || $type eq 'five_prime_utr'  || $type eq 'three_prime_utr')) {
            my ($id) = $attrs =~ /\bID=([^;]+)/;
            next unless defined $id;
            next if $printed{$id}++;
            print $fh join("\t", $id, $gene_id, $chr, $start, $end, $strand), "\n";
        }
    }
    close $in;
}

sub _coords_generic {
    my ($gff, $fh) = @_;
    my %gene_coords;

    # Pass 1: collect gene coords
    open my $in, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$in>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9 && $f[2] eq 'gene';
        my ($attrs) = $f[8];
        my ($id) = $attrs =~ /\bID=([^;]+)/;
        next unless defined $id;
        $gene_coords{$id} = [@f[0, 3, 4, 6]];
    }
    close $in;

    # Pass 2: one row per FASTA database sequence (mRNA, :cds, :pep)
    open $in, '<', $gff or die "Can't open $gff: $!\n";
    while (my $line = <$in>) {
        chomp $line;
        next if $line =~ /^#/;
        my @f = split /\t/, $line;
        next unless @f >= 9;
        my ($feat_type, $attrs) = @f[2, 8];
        next unless $feat_type eq 'mRNA' || $feat_type eq 'transcript';

        my ($id)      = $attrs =~ /\bID=([^;]+)/;
        my ($gene_id) = $attrs =~ /\bParent=([^;]+)/;
        ($gene_id)    = $attrs =~ /\bgeneID=([^;]+)/ unless defined $gene_id;
        next unless defined $id && defined $gene_id;
        my $c = $gene_coords{$gene_id};
        next unless defined $c;

        print $fh join("\t", $id,       $gene_id, @$c), "\n";
        print $fh join("\t", "$id:cds", $gene_id, @$c), "\n";
        print $fh join("\t", "$id:pep", $gene_id, @$c), "\n";
    }
    close $in;
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
