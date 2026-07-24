#!/usr/bin/env perl
# make_geneset_mappings.pl
#
# For every geneset directory under genomes_v2:
#   1. Generate genes.gtf from genes.gff via gffread (if GTF is absent)
#   2. Generate transcript2gene.txt (if absent)
#   3. Generate protein2gene.txt (if absent)
#
# Annotation source priority: GFF3 → GTF → FASTA header heuristics
# Both GFF3 and GTF are parsed when available; their mappings are merged.
#
# Usage:
#   module load gffread
#   perl make_geneset_mappings.pl [options] [DIR]
#
# DIR may be given positionally as shorthand for --base-dir, to scope a run
# to a single organism/assembly/geneset instead of the whole tree.
#
# Options:
#   --base-dir DIR         Root directory to scan (default: /n/sci/SCI-004223-SBGENOMES/genomes_v2)
#   --dry-run              Show what would be done without writing any files
#   --force                Overwrite existing transcript2gene.txt / protein2gene.txt
#   --skip-gtf             Do not generate GTF files
#   --verbose              Print detailed per-geneset progress
#   --protein-coding-only  Only include protein-coding transcripts/proteins (mRNA features;
#                          skips lncRNA, tRNA, rRNA, etc.; skips pseudo genes)

use strict;
use warnings;
use Getopt::Long;

my $BASE_DIR            = '/n/sci/SCI-004223-SBGENOMES/genomes_v2';
my $DRY_RUN             = 0;
my $VERBOSE             = 0;
my $FORCE               = 0;
my $SKIP_GTF            = 0;
my $GFFREAD             = 'gffread';
my $PROTEIN_CODING_ONLY = 0;

GetOptions(
    'base-dir=s'          => \$BASE_DIR,
    'dry-run'             => \$DRY_RUN,
    'verbose'             => \$VERBOSE,
    'force'               => \$FORCE,
    'skip-gtf'            => \$SKIP_GTF,
    'gffread=s'           => \$GFFREAD,
    'protein-coding-only' => \$PROTEIN_CODING_ONLY,
) or die usage();

# Allow a bare positional path as shorthand for --base-dir, e.g.:
#   perl make_geneset_mappings.pl /path/to/genomes_v2/SomeOrganism
if (@ARGV) {
    die usage() if @ARGV > 1;
    $BASE_DIR = shift @ARGV;
}

check_gffread() unless $SKIP_GTF;

my %stats = (
    gtf_generated  => 0,
    gtf_exists     => 0,
    t2g_written    => 0,
    p2g_written    => 0,
    t2g_exists     => 0,
    p2g_exists     => 0,
    t2g_no_data    => 0,
    p2g_no_data    => 0,
    inactive       => 0,
    errors         => 0,
);

# Walk the hierarchy. --base-dir can point at any level (root, organism, assembly,
# or geneset). A directory is treated as a geneset if it contains data files;
# otherwise we descend up to 3 levels.
find_genesets($BASE_DIR, 3);

sub find_genesets {
    my ($dir, $depth) = @_;
    return unless -d $dir;
    # Geneset dirs contain annotation and/or sequence files with these specific names.
    # genome.fa lives at the assembly level and must not trigger a false match.
    if (   -f "$dir/genes.gff"  || -f "$dir/genes.gtf"
        || -f "$dir/transcript.nt.fa" || -f "$dir/protein.aa.fa"
        || -f "$dir/cds.nt.fa") {
        process_geneset($dir);
        return;
    }
    return if $depth <= 0;
    find_genesets($_, $depth - 1) for sort glob("$dir/*");
}

print "\n=== Summary ===\n";
printf "GTF generated:                  %d\n", $stats{gtf_generated};
printf "GTF already existed:            %d\n", $stats{gtf_exists};
printf "transcript2gene written:        %d\n", $stats{t2g_written};
printf "transcript2gene already existed:%d\n", $stats{t2g_exists};
printf "transcript2gene no data:        %d\n", $stats{t2g_no_data};
printf "protein2gene written:           %d\n", $stats{p2g_written};
printf "protein2gene already existed:   %d\n", $stats{p2g_exists};
printf "protein2gene no data:           %d\n", $stats{p2g_no_data};
printf "Inactive (skipped):             %d\n", $stats{inactive};
printf "Errors:                         %d\n", $stats{errors};

# ─────────────────────────────────────────────────────────────────────────────

sub is_active {
    my ($dir) = @_;
    my $meta = "$dir/metadata.yaml";
    return 1 unless -f $meta;
    open my $fh, '<', $meta or return 1;
    while (<$fh>) {
        if (/^\s*active\s*:\s*(\S+)/) {
            my $val = lc($1);
            $val =~ s/\s+$//;
            close $fh;
            return $val ne 'false';
        }
    }
    close $fh;
    return 1;
}

sub process_geneset {
    my ($dir) = @_;

    unless (is_active($dir)) {
        print "  SKIP (inactive): $dir\n";
        $stats{inactive}++;
        return;
    }

    my $gff      = "$dir/genes.gff";
    my $gtf      = "$dir/genes.gtf";
    my $tx_fa    = "$dir/transcript.nt.fa";
    my $prot_fa  = "$dir/protein.aa.fa";
    my $t2g_file = "$dir/transcript2gene.txt";
    my $p2g_file = "$dir/protein2gene.txt";

    print "Processing: $dir\n";

    # 1. Generate GTF
    if (!$SKIP_GTF && -f $gff) {
        if (-f $gtf) {
            print "  GTF already exists: $gtf\n";
            $stats{gtf_exists}++;
        } else {
            generate_gtf($gff, $gtf);
        }
    }

    my $need_t2g = $FORCE ? (-f $tx_fa ? 1 : 0)   : (!-f $t2g_file && -f $tx_fa);
    my $need_p2g = $FORCE ? (-f $prot_fa ? 1 : 0)  : (!-f $p2g_file && -f $prot_fa);

    if (!$need_t2g && -f $t2g_file) {
        print "  transcript2gene already exists: $t2g_file\n";
        $stats{t2g_exists}++;
    }
    if (!$need_p2g && -f $p2g_file) {
        print "  protein2gene already exists: $p2g_file\n";
        $stats{p2g_exists}++;
    }

    return unless $need_t2g || $need_p2g;

    # Nothing to derive mappings from
    unless (-f $gff || -f $gtf || -f $tx_fa || -f $prot_fa) {
        print "  SKIP (no annotation data): $dir\n";
        $stats{t2g_no_data}++ if $need_t2g;
        $stats{p2g_no_data}++ if $need_p2g;
        return;
    }

    my %tx2gene;    # many key variants → gene_id
    my %prot2gene;  # protein_id → gene_id

    # Parse GFF3
    if (-f $gff) {
        parse_gff3($gff, \%tx2gene, \%prot2gene);
        vprint("  GFF3: %d tx keys, %d prot keys\n",
               scalar keys %tx2gene, scalar keys %prot2gene);
    }

    # Parse GTF (always supplement; handles cases where GFF3 and FASTA use different ID sets)
    if (-f $gtf) {
        my $before = scalar keys %tx2gene;
        parse_gtf($gtf, \%tx2gene, \%prot2gene);
        vprint("  GTF:  %d new tx keys, %d prot keys total\n",
               (scalar keys %tx2gene) - $before, scalar keys %prot2gene);
    }

    # 2. transcript2gene
    if ($need_t2g) {
        my @entries = get_fasta_entries($tx_fa);
        if (@entries) {
            my %mapping = map_entries(\@entries, \%tx2gene, 'transcript');
            if (%mapping) {
                write_mapping($t2g_file, \%mapping);
                printf "  transcript2gene: %d entries\n", scalar keys %mapping;
                $stats{t2g_written}++;
            } else {
                warn "  WARNING: empty transcript2gene for $dir\n";
                $stats{errors}++;
            }
        } else {
            $stats{t2g_skipped}++;
        }
    }

    # 3. protein2gene  (check prot2gene first, fall back to tx2gene - many genomes share IDs)
    if ($need_p2g) {
        my @entries = get_fasta_entries($prot_fa);
        if (@entries) {
            my %combined = (%tx2gene, %prot2gene);  # prot2gene wins on collision
            my %mapping  = map_entries(\@entries, \%combined, 'protein');
            if (%mapping) {
                write_mapping($p2g_file, \%mapping);
                printf "  protein2gene:    %d entries\n", scalar keys %mapping;
                $stats{p2g_written}++;
            } else {
                warn "  WARNING: empty protein2gene for $dir\n";
                $stats{errors}++;
            }
        } else {
            $stats{p2g_skipped}++;
        }
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# GTF generation

sub check_gffread {
    my $rc = system("command -v '$GFFREAD' >/dev/null 2>&1");
    if ($rc != 0) {
        die "ERROR: gffread ('$GFFREAD') not found in PATH.\n" .
            "  Run 'module load gffread' before running this script,\n" .
            "  or pass --gffread /path/to/gffread, or use --skip-gtf to skip GTF generation.\n";
    }
}

sub generate_gtf {
    my ($gff, $gtf) = @_;
    print "  Generating GTF: $gtf\n";
    if ($DRY_RUN) { $stats{gtf_generated}++; return; }
    my $rc = system("$GFFREAD -T -F '$gff' -o '$gtf'");
    if ($rc != 0 || !-f $gtf) {
        warn "  ERROR: gffread failed for $gff\n";
        $stats{errors}++;
    } else {
        $stats{gtf_generated}++;
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# GFF3 parser
# Builds tx2gene with multiple key variants per transcript, and prot2gene from CDS.

sub parse_gff3 {
    my ($file, $tx2gene, $prot2gene) = @_;

    my %gene_table;   # raw gene ID (e.g. gene-LOC123) → clean gene_id
    my %mrna_to_gene; # raw mRNA/transcript ID → gene_id  (for CDS parent lookup)

    open my $fh, '<', $file or do { warn "Cannot open $file: $!\n"; return; };

    # Two-pass: collect genes first, then transcripts and CDS
    # Single pass is fine if we process genes before transcripts, but GFF3 order
    # isn't guaranteed. Use two passes.
    my @lines;
    while (<$fh>) {
        next if /^#/;
        chomp;
        push @lines, $_;
    }
    close $fh;

    # Pass 1: gene features
    for my $line (@lines) {
        my @f = split /\t/, $line, 9;
        next unless @f == 9 && lc($f[2]) eq 'gene';
        my $raw_id = gff_attr($f[8], 'ID') or next;
        (my $gene_id = $raw_id) =~ s/^gene-//;
        $gene_table{$raw_id} = $gene_id;
    }

    # Pass 1b: collect data needed for --protein-coding-only filtering
    my (%has_cds, %pseudo_genes);
    if ($PROTEIN_CODING_ONLY) {
        for my $line (@lines) {
            my @f = split /\t/, $line, 9;
            next unless @f == 9;
            my ($feat2, $attrs2) = ($f[2], $f[8]);
            if ($feat2 eq 'CDS') {
                # Every transcript that parents a CDS is protein-coding
                my $parent = gff_attr($attrs2, 'Parent') or next;
                $has_cds{$parent} = 1;
            }
            elsif (lc($feat2) eq 'gene') {
                my $id  = gff_attr($attrs2, 'ID') or next;
                my $ps  = gff_attr($attrs2, 'pseudo');
                my $bio = gff_attr($attrs2, 'gene_biotype') // gff_attr($attrs2, 'biotype');
                $pseudo_genes{$id} = 1
                    if (defined $ps  && $ps  eq 'true')
                    || (defined $bio && $bio =~ /pseudo/i);
            }
        }
    }

    # Pass 2: mRNA/transcript and CDS features
    for my $line (@lines) {
        my @f = split /\t/, $line, 9;
        next unless @f == 9;
        my ($feat, $attrs) = ($f[2], $f[8]);

        my $is_rna_feat = ($feat =~ /RNA|transcript/i
                       && $feat !~ /^(?:exon|CDS|region|gene|pseudogene|mobile_genetic_element|UTR|sequence_feature|start_codon|stop_codon)$/i);

        if ($is_rna_feat) {
            my $raw_id = gff_attr($attrs, 'ID') or next;
            # In protein-coding-only mode: must have a CDS child and not be pseudo
            if ($PROTEIN_CODING_ONLY) {
                next unless $has_cds{$raw_id};
                my $parent = gff_attr($attrs, 'Parent');
                next if $parent && $pseudo_genes{$parent};
            }
            my $gene_id = _resolve_gff_gene($attrs, \%gene_table);
            next unless $gene_id;

            # Store under all useful key variants
            _store_tx($tx2gene, $raw_id, $gene_id);
            $mrna_to_gene{$raw_id} = $gene_id;

            for my $attr_key (qw(Name transcript_id)) {
                my $v = gff_attr($attrs, $attr_key);
                _store_tx($tx2gene, $v, $gene_id) if $v && $v ne $raw_id;
            }
        }

        elsif ($feat eq 'CDS') {
            my $raw_id  = gff_attr($attrs, 'ID');
            my $parent  = gff_attr($attrs, 'Parent') or next;

            # Gene lookup: mRNA parent first, then direct gene parent (prokaryotes)
            my $gene_id = $mrna_to_gene{$parent}
                       // $tx2gene->{$parent}
                       // $gene_table{$parent};
            unless ($gene_id) {
                (my $clean_parent = $parent) =~ s/^gene-//;
                $gene_id = $gene_table{"gene-$clean_parent"} // $clean_parent
                    if $parent =~ /^gene-/;
            }
            next unless $gene_id;

            # protein_id attribute (NCBI/RefSeq style)
            my $prot_id = gff_attr($attrs, 'protein_id');
            $prot2gene->{$prot_id} = $gene_id if $prot_id;

            # Name attribute (often same as protein_id; also store for coverage)
            my $name = gff_attr($attrs, 'Name');
            if ($name && $name ne ($prot_id // '')) {
                $prot2gene->{$name} = $gene_id;
            }

            # Raw CDS ID stripped of cds- prefix
            if ($raw_id && !$prot_id) {
                (my $clean = $raw_id) =~ s/^cds-//;
                $prot2gene->{$clean} = $gene_id if $clean;
            }
        }
    }
}

sub _resolve_gff_gene {
    my ($attrs, $gene_table) = @_;
    # Priority 1: geneID attribute (Schmidtea nova/lugubris ONThybrid style)
    my $g = gff_attr($attrs, 'geneID');
    return $g if $g;
    # Priority 2: gene_id attribute (Ptychodera style)
    $g = gff_attr($attrs, 'gene_id');
    return $g if $g;
    # Priority 3: resolve Parent against gene table
    my $parent = gff_attr($attrs, 'Parent');
    return undef unless $parent;
    return $gene_table->{$parent} if exists $gene_table->{$parent};
    # Strip gene- prefix if not already looked up
    (my $clean = $parent) =~ s/^gene-//;
    return exists $gene_table->{"gene-$clean"} ? $gene_table->{"gene-$clean"}
                                               : $clean;
}

sub _store_tx {
    my ($tx2gene, $id, $gene_id) = @_;
    return unless $id;
    $tx2gene->{$id} = $gene_id;
    # Also store without rna- prefix (NCBI: ID=rna-NM_..., FASTA: >NM_...)
    if ($id =~ s/^rna-//) {
        $tx2gene->{$id} //= $gene_id;
    }
}

sub gff_attr {
    my ($attrs, $key) = @_;
    if ($attrs =~ /(?:^|;)\s*\Q$key\E=([^;]+)/) {
        my $v = $1;
        $v =~ s/\s+$//;
        return $v if length $v;
    }
    return undef;
}

# ─────────────────────────────────────────────────────────────────────────────
# GTF parser

sub parse_gtf {
    my ($file, $tx2gene, $prot2gene) = @_;
    open my $fh, '<', $file or do { warn "Cannot open $file: $!\n"; return; };
    my @lines = grep { !/^#/ } <$fh>;
    close $fh;
    chomp @lines;

    # In protein-coding-only mode: collect transcript IDs that appear on CDS lines.
    # A transcript that has a CDS is by definition protein-coding.
    my %has_cds_gtf;
    if ($PROTEIN_CODING_ONLY) {
        for my $line (@lines) {
            my @f = split /\t/, $line, 9;
            next unless @f == 9 && $f[2] eq 'CDS';
            my $tx_id = gtf_attr($f[8], 'transcript_id') or next;
            $has_cds_gtf{$tx_id} = 1;
            (my $clean = $tx_id) =~ s/^rna-//;
            $has_cds_gtf{$clean} = 1 if $clean ne $tx_id;
        }
    }

    for my $line (@lines) {
        my @f = split /\t/, $line, 9;
        next unless @f == 9;
        my $attrs   = $f[8];
        my $gene_id = gtf_attr($attrs, 'gene_id') or next;
        my $tx_id   = gtf_attr($attrs, 'transcript_id');
        my $prot_id = gtf_attr($attrs, 'protein_id');
        if ($tx_id) {
            next if $PROTEIN_CODING_ONLY && !$has_cds_gtf{$tx_id};
            $tx2gene->{$tx_id} //= $gene_id;
            (my $clean = $tx_id) =~ s/^rna-//;
            $tx2gene->{$clean} //= $gene_id if $clean ne $tx_id;
        }
        # protein_id only appears on CDS lines, so it's always protein-coding
        $prot2gene->{$prot_id} //= $gene_id if $prot_id;
    }
}

sub gtf_attr {
    my ($attrs, $key) = @_;
    return $1 if $attrs =~ /\b\Q$key\E\s+"([^"]+)"/;
    return undef;
}

# ─────────────────────────────────────────────────────────────────────────────
# FASTA utilities

sub get_fasta_entries {
    my ($file) = @_;
    my @entries;
    return () unless -f $file;
    open my $fh, '<', $file or do { warn "Cannot open $file: $!\n"; return (); };
    while (<$fh>) {
        next unless /^>(\S+)(.*)/;
        push @entries, [ $1, $2 ];  # [id, rest_of_header]
    }
    close $fh;
    return @entries;
}

sub map_entries {
    my ($entries, $mapping, $type) = @_;
    my %result;
    my $heuristic_count = 0;

    for my $entry (@$entries) {
        my ($id, $desc) = @$entry;
        my $gene = lookup_id($id, $mapping);

        unless (defined $gene) {
            # Try extracting gene from FASTA header description
            $gene = gene_from_desc($desc);
        }

        unless (defined $gene) {
            # Heuristic fallback based on ID structure
            $gene = infer_gene($id, $type);
            $heuristic_count++;
        }

        $result{$id} = $gene;
    }

    if ($heuristic_count > 0) {
        vprint("    %d/%d IDs used heuristic gene derivation\n",
               $heuristic_count, scalar @$entries);
    }
    return %result;
}

sub lookup_id {
    my ($id, $mapping) = @_;
    return $mapping->{$id} if exists $mapping->{$id};

    # Strip type prefix: transcript:XXX, protein:XXX, cds:XXX
    (my $clean = $id) =~ s/^\w+://;
    return $mapping->{$clean} if $clean ne $id && exists $mapping->{$clean};

    # Strip rna- prefix
    (my $no_rna = $id) =~ s/^rna-//;
    return $mapping->{$no_rna} if $no_rna ne $id && exists $mapping->{$no_rna};

    # Add rna- prefix (Parastichopus: FASTA has rna-XM_ but mapping key is XM_)
    return $mapping->{"rna-$id"} if exists $mapping->{"rna-$id"};

    return undef;
}

sub gene_from_desc {
    my ($desc) = @_;
    return undef unless defined $desc && length $desc;
    # Scolanthus: "gene=GENEID ..."
    return $1 if $desc =~ /(?:^|\s)gene=(\S+)/;
    # NCBI prokaryote: "[locus_tag=AAV28_RS00430]"
    return $1 if $desc =~ /\[locus_tag=([^\]]+)\]/;
    return undef;
}

sub infer_gene {
    my ($id, $type) = @_;
    # Strip type prefix
    $id =~ s/^\w+://;

    if ($type eq 'protein') {
        # Turritopsis: prefix.gN.tN.pN → prefix.gN
        return $1 if $id =~ /^(.+\.[gG]\d+)\.[tT]\d+\.[pP]\d+$/;
        # Generic: strip .pN protein suffix
        $id =~ s/\.[pP]\d+$//;
        # Strip .tN transcript suffix
        $id =~ s/\.[tT]\d+$//;
    }

    # WBPS/ONThybrid planarian: prefixcT0000001 → prefixcG0000001
    # Matches: (anything ending in c/n)(T or t)(7+ digits)
    if ($id =~ /^(.+[cCnN])([Tt])(\d{7,})$/) {
        my $g_char = ($2 eq 'T') ? 'G' : 'g';
        return "$1${g_char}$3";
    }

    # Generic: strip trailing .N version
    $id =~ s/\.\d+$//;
    return $id;
}

# ─────────────────────────────────────────────────────────────────────────────
# Output

sub write_mapping {
    my ($file, $mapping) = @_;
    if ($DRY_RUN) {
        print "  [dry-run] would write: $file\n";
        return;
    }
    open my $fh, '>', $file or do { warn "Cannot write $file: $!\n"; $stats{errors}++; return; };
    for my $id (sort keys %$mapping) {
        print $fh "$id\t$mapping->{$id}\n";
    }
    close $fh;
}

sub vprint { printf STDERR @_ if $VERBOSE; }

sub usage {
    return <<'END';
Usage: perl make_geneset_mappings.pl [options] [DIR]

DIR is an optional positional shorthand for --base-dir (scope the run to
one organism/assembly/geneset instead of the whole tree).

Options:
  --base-dir DIR    Root directory to scan [default: /n/sci/SCI-004223-SBGENOMES/genomes_v2]
  --dry-run         Show actions without writing files
  --force           Regenerate existing transcript2gene.txt / protein2gene.txt
  --skip-gtf        Do not generate GTF files
  --verbose         Detailed per-geneset progress (to stderr)
  --gffread PATH    Path to gffread binary [default: gffread]

Prerequisites:
  module load gffread   (or ensure gffread is in PATH)
END
}
