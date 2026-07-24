#!/usr/bin/perl
use strict;
use warnings;

# Build features.tsv for transcriptome-only genesets (no genome, no GFF).
# Uses transcript2gene.txt for gene/transcript structure and geneNames.tsv for names.
# Output format matches parse_GFF3_to_MOOP_TSV.pl — no feature_coords.tsv
# since there is no genome assembly.
#
# protein2gene.txt / cds2gene.txt (optional 4th/5th args) extend the lineage
# with 'protein' and 'CDS' features, mirroring the gene -> mRNA -> CDS -> protein
# chain parse_GFF3_to_MOOP_TSV.pl builds when a GFF exists. Without a GFF,
# the *2gene.txt files are all we have to reconstruct that lineage, so each one
# present is used to add its level. Protein/CDS IDs are matched to a transcript
# by stripping a trailing ".p<N>" ORF suffix (TransDecoder-style naming) and
# looking up the result among that gene's known transcripts; anything whose
# stripped ID isn't one of them is parented directly to the gene instead. A CDS
# entry sharing its raw ID with a protein entry (common — CDS and protein are
# often the same ORF call, just nucleotide vs. translated) is disambiguated
# with a ":cds" uniquename suffix and the protein is parented to it, so the two
# don't collide in the feature table. This is what lets protein-keyed
# annotations (Diamond/EggNOG/InterProScan/ProtNLM all key by protein ID)
# resolve to a feature at all in genesets with no GFF/GTF.
#
# Usage: parse_transcript2gene_to_MOOP_TSV.pl transcript2gene.txt geneNames.tsv metadata.yaml [protein2gene.txt] [cds2gene.txt]

my $USAGE = "Usage: $0 transcript2gene.txt geneNames.tsv metadata.yaml [protein2gene.txt] [cds2gene.txt]\n";
my $t2g_file   = shift or die $USAGE;
my $names_file = shift or die $USAGE;
my $meta_file  = shift or die $USAGE;
my $p2g_file   = shift;  # optional
my $c2g_file   = shift;  # optional

# ── Metadata header ───────────────────────────────────────────────────────────
my ($genus, $species, $commonname, $taxon_id,
    $genome_accession, $genome_name, $source, $details) = ('') x 8;
open my $META, '<', $meta_file or die "Can't open $meta_file: $!\n";
while (my $line = <$META>) {
    chomp $line;
    if    ($line =~ /^genus:\s+(\S+)/)         { $genus             = $1 }
    elsif ($line =~ /^species:\s+(\S+)/)        { $species           = $1 }
    elsif ($line =~ /^common-name:\s+(.+)$/)    { $commonname        = $1 }
    elsif ($line =~ /^ncbi-taxon-id:\s+(\S+)/)  { $taxon_id          = $1 }
    elsif ($line =~ /^genome-accession:\s+(\S+)/)   { $genome_accession = $1 }
    elsif ($line =~ /^simrbase-prefix:\s+(\S+)/){ $genome_name       = $1 }
    elsif ($line =~ /^genome-name:\s+(\S+)/)    { $genome_name     ||= $1 }
    elsif ($line =~ /^source:\s+(\S+)/)         { $source            = $1 }
    elsif ($line =~ /^details:\s+(.+)$/)        { $details           = $1 }
}
close $META;

print "## Genus: $genus\n";
print "## Species: $species\n";
print "## Common Name: $commonname\n";
print "## NCBI Taxon ID: $taxon_id\n";
print "## Genome Accession: $genome_accession\n";
print "## Genome Name: $genome_name\n";
print "## Genome Description: Assembly is from $source. $details\n";
print "## This_Uniquename\tThis_Type\tParent_Uniquename\tParent_Type\tThis_Name\tThis_Description\n";

# ── geneNames.tsv ────────────────────────────────────────────────────────────
# Columns: ID  MAINID  GroupId  Desc  Note
# Desc format: "SYMBOL: description text"
# isoforms.tsv (and so geneNames.tsv's ID column) is built from protein2gene.txt
# by the sbatch pipeline, so ID here is a protein_id, not a transcript_id — we
# roll up best name/desc to the gene level for mRNA rows, and use the per-ID
# values directly for protein rows.
my (%gene_name, %gene_desc, %id_name, %id_desc);
open my $NAMES, '<', $names_file or die "Can't open $names_file: $!\n";
<$NAMES>;  # skip header
while (my $line = <$NAMES>) {
    chomp $line;
    my ($id, undef, $gene_id, $desc) = split /\t/, $line;
    next unless defined $id && defined $gene_id;
    $desc //= '';

    my ($symbol, $description) = ('', $desc);
    if ($desc =~ /^(\S+): (.+)$/) {
        ($symbol, $description) = ($1, $2);
    }

    $id_name{$id} = $symbol;
    $id_desc{$id} = $description;
    $gene_name{$gene_id} ||= $symbol      if $symbol;
    $gene_desc{$gene_id} ||= $description if $description;
}
close $NAMES;

# ── transcript2gene.txt ───────────────────────────────────────────────────────
my %gene_txs;
open my $T2G, '<', $t2g_file or die "Can't open $t2g_file: $!\n";
while (my $line = <$T2G>) {
    chomp $line;
    next if $line =~ /^#/;
    my ($tx_id, $gene_id) = split /\t/, $line;
    next unless defined $tx_id && defined $gene_id && $tx_id ne '' && $gene_id ne '';
    push @{ $gene_txs{$gene_id} }, $tx_id;
}
close $T2G;

# ── protein2gene.txt / cds2gene.txt (both optional) ───────────────────────────
my %gene_prots = load_id2gene($p2g_file);
my %gene_cds   = load_id2gene($c2g_file);

sub load_id2gene {
    my ($file) = @_;
    my %gene_ids;
    return %gene_ids unless $file && -f $file;
    open my $FH, '<', $file or die "Can't open $file: $!\n";
    while (my $line = <$FH>) {
        chomp $line;
        next if $line =~ /^#/;
        my ($id, $gene_id) = split /\t/, $line;
        next unless defined $id && defined $gene_id && $id ne '' && $gene_id ne '';
        push @{ $gene_ids{$gene_id} }, $id;
    }
    close $FH;
    return %gene_ids;
}

# Resolves a protein/CDS raw ID to its transcript by stripping a trailing
# ".p<N>" ORF suffix; falls back to the gene itself if that isn't one of the
# gene's known transcripts.
sub resolve_parent {
    my ($raw_id, $gene_id, $tx_known) = @_;
    (my $base = $raw_id) =~ s/\.p\d+$//i;
    return $tx_known->{$base}
        ? ($base, 'mRNA')
        : ($gene_id, 'gene');
}

# ── Emit features ─────────────────────────────────────────────────────────────
for my $gene_id (sort keys %gene_txs) {
    my $gname = $gene_name{$gene_id} // '';
    my $gdesc = $gene_desc{$gene_id} // '';
    print join("\t", $gene_id, 'gene', '', '', $gname, $gdesc), "\n";

    for my $tx_id (sort @{ $gene_txs{$gene_id} }) {
        print join("\t", $tx_id, 'mRNA', $gene_id, 'gene', $gname, $gdesc), "\n";
    }

    my %tx_known = map { $_ => 1 } @{ $gene_txs{$gene_id} };

    # A CDS entry sharing its raw ID with a protein entry (the common case —
    # both are the same ORF call) is stored under a ":cds" uniquename and the
    # protein is parented to it instead of straight to the mRNA/gene.
    my %cds_uniquename;
    for my $cds_id (sort @{ $gene_cds{$gene_id} || [] }) {
        my ($parent_id, $parent_type) = resolve_parent($cds_id, $gene_id, \%tx_known);
        my $cname = $id_name{$cds_id} // $gname;
        my $cdesc = $id_desc{$cds_id} // $gdesc;
        my $uniquename = "$cds_id:cds";
        $cds_uniquename{$cds_id} = $uniquename;
        print join("\t", $uniquename, 'CDS', $parent_id, $parent_type, $cname, $cdesc), "\n";
    }

    for my $prot_id (sort @{ $gene_prots{$gene_id} || [] }) {
        my $pname = $id_name{$prot_id} // $gname;
        my $pdesc = $id_desc{$prot_id} // $gdesc;
        my ($parent_id, $parent_type) = exists $cds_uniquename{$prot_id}
            ? ($cds_uniquename{$prot_id}, 'CDS')
            : resolve_parent($prot_id, $gene_id, \%tx_known);

        # Protein gets a ":pep" uniquename, exactly as CDS gets ":cds" above.
        #
        # On the T2G path the transcript, CDS and protein FASTAs all key on the
        # SAME identifier -- the type is decided by which file you read. Emitting
        # the protein under its raw id therefore produced a row whose uniquename
        # equalled its own parent transcript's, which the loader collapsed into a
        # single self-parented row (parent_feature_id = feature_id): an infinite
        # loop for anything walking up the tree, and gene sets with no protein
        # rows at all, so their sequences were unreachable.
        #
        # rename_t2g_fasta.pl applies the matching ":cds"/":pep" suffixes to the
        # FASTA copies, which keeps the invariant that feature_uniquename IS the
        # FASTA lookup key. The transcript deliberately stays bare.
        my $uniquename = "$prot_id:pep";

        # Defensive: never emit a feature that is its own parent, whatever the
        # source data says. This is what makes the walk terminate.
        if ($uniquename eq $parent_id) {
            warn "WARNING: $uniquename resolves to itself as parent; "
               . "parenting to gene $gene_id instead\n";
            ($parent_id, $parent_type) = ($gene_id, 'gene');
        }

        print join("\t", $uniquename, 'protein', $parent_id, $parent_type, $pname, $pdesc), "\n";
    }
}
