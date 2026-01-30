#!/usr/bin/perl
use strict;
use warnings;

sub usage {
    my ($msg) = @_;
    print STDERR "ERROR: $msg\n" if $msg;
    print STDERR <<'EOF';
Usage: parse_DIAMOND_to_MOOP_TSV.pl <tophit.tsv> <source> <version> <source_url> <annotation_url>

Required arguments:
  tophit.tsv          DIAMOND output file (tab-delimited: qseqid, sseqid, stitle, evalue)
  source              Database source name (e.g., "UniProtKB/Swiss-Prot", "Ensembl Homo sapiens")
  version             Database version/release date (e.g., "2025-06-17")
  source_url          Database homepage URL
  annotation_url      URL prefix for individual record lookup

Expected input format (4 tab-delimited columns):
  query_id    hit_id    hit_description    evalue
  PROTEIN_001    Q9BWM5    Zinc finger protein 416 OS=Homo sapiens...    3.94e-110

Output:
  Creates: <source>.homologs.moop.tsv (spaces/slashes replaced with underscores)

Example:
  perl parse_DIAMOND_to_MOOP_TSV.pl tophit.tsv "UniProtKB/Swiss-Prot" "2025-06-17" \\
    "https://www.uniprot.org" "https://www.uniprot.org/uniprotkb/"
EOF
    exit 1;
}

# Validate arguments
usage("Missing required arguments") unless @ARGV == 5;

my ($top_hits, $source, $source_version, $source_url, $annotation_url) = @ARGV;

# Validate input file exists and is readable
unless (-e $top_hits) {
    usage("Input file does not exist: $top_hits");
}
unless (-r $top_hits) {
    usage("Input file is not readable: $top_hits");
}

# Validate URLs are not empty
usage("Source URL cannot be empty") unless $source_url;
usage("Annotation URL cannot be empty") unless $annotation_url;

# Get file modification date
my @stat = stat($top_hits);
my $file_mtime = $stat[9];
unless (defined $file_mtime) {
    die "ERROR: Could not stat file $top_hits: $!\n";
}

my ($sec, $min, $hour, $mday, $mon, $year) = localtime($file_mtime);
my $date = sprintf('%04d-%02d-%02d', $year + 1900, $mon + 1, $mday);

# Create output filename
my $source_nospace = $source;
$source_nospace =~ s/\s+/_/g;
$source_nospace =~ s/\//_/g;
my $output_file = "$source_nospace.homologs.moop.tsv";

# Open output file with proper error handling
open(my $out_fh, '>', $output_file) or 
    die "ERROR: Cannot open output file '$output_file' for writing: $!\n";

# Write metadata headers
print $out_fh "## Annotation Source: $source\n";
print $out_fh "## Annotation Source Version: $source_version\n";
print $out_fh "## Annotation Source URL: $source_url\n";
print $out_fh "## Annotation Accession URL: $annotation_url\n";
print $out_fh "## Annotation Type: Homologs\n";
print $out_fh "## Annotation Creation Date: $date\n";
print $out_fh join("\t", "## Gene", "Accession", "Accession_Description", "Score"), "\n";

# Open input file with proper error handling
open(my $in_fh, '<', $top_hits) or
    die "ERROR: Cannot open input file '$top_hits' for reading: $!\n";

my $line_num = 0;
my $record_count = 0;
my $error_count = 0;

while (my $line = <$in_fh>) {
    $line_num++;
    chomp $line;
    
    # Skip empty lines
    next if $line =~ /^\s*$/;
    
    # Split into expected 4 columns
    my @fields = split /\t/, $line;
    
    unless (@fields == 4) {
        warn "WARNING: Line $line_num has " . scalar(@fields) . " fields, expected 4. Skipping.\n";
        $error_count++;
        next;
    }
    
    my ($t_id, $hit_id, $hit_desc, $score) = @fields;
    
    # Validate required fields are not empty
    unless (defined $t_id && $t_id =~ /\S/) {
        warn "WARNING: Line $line_num has empty query ID. Skipping.\n";
        $error_count++;
        next;
    }
    
    unless (defined $hit_id && $hit_id =~ /\S/) {
        warn "WARNING: Line $line_num has empty hit ID. Skipping.\n";
        $error_count++;
        next;
    }
    
    unless (defined $score && $score =~ /\S/) {
        warn "WARNING: Line $line_num has empty score. Skipping.\n";
        $error_count++;
        next;
    }
    
    # Handle empty description (valid, just use empty string)
    $hit_desc = '' unless defined $hit_desc;
    
    # Parse description format
    # UniProt Swiss-Prot: sp|ACCESSION|ID description
    # Ensembl: ENSPXP... pep primary_assembly:... gene_symbol:SYMBOL description:DESC
    
    # Normalize Swiss-Prot format: extract accession from sp|ACC|ID pattern
    if ($hit_id =~ /^sp\|/) {
        if ($hit_id =~ /^sp\|(\S+)\|/) {
            $hit_id = $1;
        }
    }
    
    # Parse description based on format
    if ($hit_desc =~ /gene_symbol:(\S+)\s+description:/i) {
        # Ensembl format: extract gene_symbol and description
        if ($hit_desc =~ /gene_symbol:(\S+)\s+description:\s*(.+?)(?:\s*\[Source:|$)/i) {
            my ($symbol, $desc) = ($1, $2);
            $desc =~ s/\s+$//;  # trim trailing whitespace
            $hit_desc = "$symbol: $desc";
        }
    } elsif ($hit_desc =~ /description:/i) {
        # Ensembl alternative format
        if ($hit_desc =~ /description:\s*(.+?)(?:\s*\[Source:|$)/i) {
            $hit_desc = $1;
            $hit_desc =~ s/\s+$//;
        }
    } elsif ($hit_desc =~ /^sp\|.+GN=/i) {
        # Swiss-Prot format with gene name: extract gene name and description
        if ($hit_desc =~ /GN=(\S+)\s+(.+)/i) {
            my ($gene, $desc) = ($1, $2);
            $desc =~ s/\s+$//;
            $hit_desc = "$gene: $desc";
        }
    } elsif ($hit_desc =~ /^sp\|/i) {
        # Swiss-Prot format without explicit gene field
        if ($hit_desc =~ /^sp\|\S+\|\S+\s+(.+)/i) {
            $hit_desc = $1;
            $hit_desc =~ s/\s+$//;
        }
    }
    
    # Ensure no undefined values in output
    $hit_desc = '' unless defined $hit_desc;
    $score = '' unless defined $score;
    
    print $out_fh join("\t", $t_id, $hit_id, $hit_desc, $score), "\n";
    $record_count++;
}

close($in_fh) or die "ERROR: Could not close input file: $!\n";
close($out_fh) or die "ERROR: Could not close output file: $!\n";

# Print summary statistics
print STDERR "\n=== Parsing Complete ===\n";
print STDERR "Input file:     $top_hits\n";
print STDERR "Output file:    $output_file\n";
print STDERR "Lines read:     $line_num\n";
print STDERR "Records parsed: $record_count\n";
if ($error_count > 0) {
    print STDERR "Errors/warnings: $error_count (see above for details)\n";
} else {
    print STDERR "Errors/warnings: 0\n";
}
print STDERR "\nData loaded successfully!\n";


