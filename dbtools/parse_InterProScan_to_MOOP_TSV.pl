#!/usr/bin/perl
use strict;
use warnings;

sub usage {
    my ($msg) = @_;
    print STDERR "ERROR: $msg\n" if $msg;
    print STDERR <<'EOF';
Usage: parse_InterProScan_to_MOOP_TSV.pl <interproscan.tsv> [--version VERSION_STRING]

Required arguments:
  interproscan.tsv    InterProScan output file (TSV format)

Optional arguments:
  --version VERSION   Explicitly provide InterProScan version (e.g., "5.72-103.0")
                     If not provided, script will attempt to detect automatically

Input format:
  InterProScan TSV output with columns:
  seq_id, protein_md5, protein_length, analysis, signature_id, signature_desc,
  start, end, score, status, date, interpro_id, interpro_desc, go_terms, pathway_terms

Output files created:
  - <analysis>.iprscan.moop.tsv (one file per analysis type)
  - InterPro2GO.iprscan.moop.tsv (GO terms from InterPro)
  - PANTHER2GO.iprscan.moop.tsv (GO terms from PANTHER)

Features:
  - Automatically detects InterProScan version (or accepts explicit version)
  - Downloads GO.obo file if not present (cached locally)
  - Processes domain hits, gene families, and Gene Ontology terms
  - Generates MOOP-format TSV with metadata headers

Examples:
  # Automatic version detection
  perl parse_InterProScan_to_MOOP_TSV.pl proteins_interpro.tsv

  # Explicit version provided
  perl parse_InterProScan_to_MOOP_TSV.pl proteins_interpro.tsv --version 5.72-103.0

  # Get version from interproscan.sh and save to file (run on analysis machine)
  interproscan.sh --version > interproscan.version
  # Then use the version string in the next run on a different machine

Requirements:
  - curl or wget (to download GO.obo if needed)
  - If --version not provided: InterProScan installed and in PATH
EOF
    exit 1;
}

# Parse arguments
usage("Missing required arguments") unless @ARGV >= 1;

my $iprscan_tsv = shift;
my $version;

# Check for optional --version argument
while (@ARGV) {
    my $arg = shift;
    if ($arg eq '--version') {
        $version = shift;
        usage("--version requires a version string") unless $version;
    } else {
        usage("Unknown argument: $arg");
    }
}

# Validate input file
usage("InterProScan file does not exist: $iprscan_tsv") unless -e $iprscan_tsv;
usage("InterProScan file is not readable: $iprscan_tsv") unless -r $iprscan_tsv;

my %annot;
my $GOTSV = 'go.tsv';

# Try to get InterProScan version
if (!$version) {
    # Try to read from interproscan.version file
    if (-e 'interproscan.version') {
        open my $fh, '<', 'interproscan.version' or do {
            print STDERR "WARNING: Found interproscan.version file but could not read it\n";
        };
        if ($fh) {
            chomp(my $file_version = <$fh>);
            close $fh;
            if ($file_version) {
                $version = $file_version;
                print STDERR "Read InterProScan version from interproscan.version: $version\n";
            }
        }
    }
    
    # If still no version, try to detect from interproscan.sh command
    if (!$version) {
        $version = get_interproscan_version();
    }
    
    # If still no version, use none_provided
    if (!$version) {
        $version = 'none_provided';
        print STDERR "WARNING: Could not determine InterProScan version\n";
        print STDERR "  - interproscan.sh not found or not in PATH\n";
        print STDERR "  - interproscan.version file not found\n";
        print STDERR "  - --version argument not provided\n";
        print STDERR "  Using: '$version'\n";
        print STDERR "  To provide version explicitly:\n";
        print STDERR "    1. On analysis machine: interproscan.sh --version > interproscan.version\n";
        print STDERR "    2. Copy interproscan.version to this machine\n";
        print STDERR "    3. Or use: perl parse_InterProScan_to_MOOP_TSV.pl file.tsv --version VERSION\n";
    }
}

print STDERR "Using InterProScan version: $version\n";

# Get or download GO reference file
setup_go_reference($GOTSV);

# Parse GO.obo file if available
my %go = parse_go_reference($GOTSV);

# Get file modification date
my $date = get_file_date($iprscan_tsv);

# Parse InterProScan TSV
print STDERR "Parsing InterProScan results from $iprscan_tsv...\n";
parse_interproscan_file($iprscan_tsv, \%annot, \%go);

# Generate output files
print STDERR "Generating MOOP-format output files...\n";
generate_output_files(\%annot, \%go, $version, $date);

print STDERR "Done! Output files created in current directory.\n";

# ============================================================================
# SUBROUTINES
# ============================================================================

sub get_interproscan_version {
    my $version_output = `interproscan.sh -version 2>/dev/null`;
    if ($version_output && $version_output =~ /interproscan\s+([\d\.-]+)/i) {
        return $1;
    }
    return undef;
}

sub setup_go_reference {
    my ($gotsv) = @_;
    
    if (-e $gotsv) {
        print STDERR "Using cached $gotsv file...\n";
        return;
    }
    
    if (-e 'go.obo') {
        print STDERR "Found go.obo file. Parsing...\n";
        parse_go_obo_to_tsv();
        return;
    }
    
    print STDERR "Downloading GO.obo file (first run)...\n";
    my $curl_result = system('curl -sOL http://purl.obolibrary.org/obo/go.obo 2>/dev/null');
    
    if ($curl_result == 0 && -e 'go.obo') {
        print STDERR "Downloaded go.obo successfully. Parsing...\n";
        parse_go_obo_to_tsv();
    } else {
        print STDERR "WARNING: Could not download GO.obo. GO term descriptions will be limited.\n";
    }
}

sub parse_go_obo_to_tsv {
    open GO, 'go.obo' or die "Cannot open go.obo: $!\n";
    open OUT, '> go.tsv' or die "Cannot open go.tsv for writing: $!\n";
    
    my ($id, $name, $desc, $namespace);
    
    while (my $line = <GO>) {
        if ($line =~ /^\[Term\]/) {
            if ($id && $name) {
                print OUT join("\t", $id, $name, $desc // '-', $namespace // 'unknown'), "\n";
            }
            ($id, $name, $desc, $namespace) = (undef, undef, undef, undef);
        } elsif ($line =~ /^id:\s+(.+)/) {
            $id = $1;
        } elsif ($line =~ /^name:\s+(.+)/) {
            $name = $1;
        } elsif ($line =~ /^namespace:\s+(.+)/) {
            $namespace = $1;
        }
    }
    
    # Don't forget last term
    if ($id && $name) {
        print OUT join("\t", $id, $name, $desc // '-', $namespace // 'unknown'), "\n";
    }
    
    close GO;
    close OUT;
}

sub parse_go_reference {
    my ($gotsv) = @_;
    my %go;
    
    return %go unless -e $gotsv;
    
    open GO, $gotsv or do {
        print STDERR "WARNING: Could not open $gotsv\n";
        return %go;
    };
    
    while (my $line = <GO>) {
        chomp $line;
        next unless $line;
        
        my ($id, $name, $desc, $namespace) = split "\t", $line;
        next unless $id;
        
        $go{$id}{name} = $name // '';
        $go{$id}{desc} = $desc // '';
        $go{$id}{namespace} = $namespace // '';
    }
    close GO;
    
    return %go;
}

sub get_file_date {
    my ($file) = @_;
    my $date = `date '+%Y-%m-%d' -r $file 2>/dev/null`;
    chomp $date;
    
    if (!$date) {
        print STDERR "WARNING: Could not get file date. Using current date.\n";
        $date = `date '+%Y-%m-%d'`;
        chomp $date;
    }
    
    return $date;
}

sub parse_interproscan_file {
    my ($file, $annot_ref, $go_ref) = @_;
    my %annot = %$annot_ref;
    my %go = %$go_ref;
    
    open TSV, $file or usage("Cannot open file: $file");
    
    # Skip header
    my $header = <TSV>;
    chomp $header;
    
    if (!$header || $header !~ /seq_id/) {
        close TSV;
        usage("Invalid InterProScan file: missing 'seq_id' column");
    }
    
    my $line_count = 0;
    my $skip_count = 0;
    
    while (my $line = <TSV>) {
        $line_count++;
        chomp $line;
        next if !$line;
        
        my @fields = split "\t", $line;
        if (@fields < 15) {
            print STDERR "WARNING: Skipping line $line_count (incomplete record)\n";
            $skip_count++;
            next;
        }
        
        my ($t_id, $protein_md5, $protein_length, $analysis, $signature_id, 
            $signature_desc, $start, $end, $score, $status, $date, 
            $interpro_id, $interpro_desc, $go_terms, $pathway_terms) = @fields;
        
        if (!$t_id || !$analysis) {
            print STDERR "WARNING: Skipping line $line_count (missing seq_id or analysis)\n";
            $skip_count++;
            next;
        }
        
        # Store main annotation
        $annot{$analysis}{$t_id}{id} = $signature_id;
        $annot{$analysis}{$t_id}{desc} = $signature_desc;
        $annot{$analysis}{$t_id}{score} = $score;
        
        # Store InterPro annotation if available
        if ($interpro_id && $interpro_id ne '-') {
            my $ipr_analysis = 'InterPro';
            $annot{$ipr_analysis}{$t_id}{id} = $interpro_id;
            $annot{$ipr_analysis}{$t_id}{desc} = $interpro_desc;
            $annot{$ipr_analysis}{$t_id}{score} = '-';
        }
        
        # Process GO terms
        if ($go_terms && $go_terms ne '-') {
            my @go_terms_list = split /\|/, $go_terms;
            foreach my $go_term (@go_terms_list) {
                next unless $go_term;
                
                my $go_analysis;
                if ($go_term =~ /PANTHER/) {
                    $go_term =~ s/\(\S+\)//;
                    $go_analysis = 'PANTHER2GO';
                } else {
                    $go_term =~ s/\(InterPro\)//;
                    $go_analysis = 'InterPro2GO';
                }
                
                my $go_name = $go{$go_term}{name} // 'Unknown';
                my $go_desc = $go{$go_term}{desc} // '';
                my $go_namespace = $go{$go_term}{namespace} // 'unknown';
                
                $annot{$go_analysis}{$t_id}{id} = $go_term;
                $annot{$go_analysis}{$t_id}{desc} = "$go_name: $go_desc";
                $annot{$go_analysis}{$t_id}{score} = $go_namespace;
            }
        }
    }
    close TSV;
    
    print STDERR "Parsed $line_count lines, skipped $skip_count\n";
    
    # Copy back to reference
    %$annot_ref = %annot;
}

sub generate_output_files {
    my ($annot_ref, $go_ref, $version, $date) = @_;
    my %annot = %$annot_ref;
    my %go = %$go_ref;
    
    # Generate analysis-specific files
    foreach my $analysis (sort keys %annot) {
        next if $analysis =~ /GO$/;  # Skip GO files for now
        
        my $filename = "$analysis.iprscan.moop.tsv";
        my $annotation_type = determine_annotation_type($analysis);
        my $annotation_url = determine_annotation_url($analysis);
        
        print STDERR "Writing: $filename\n";
        open OUT, ">$filename" or die "Cannot open $filename for writing: $!\n";
        
        print OUT "## Annotation Source: InterProScan ($analysis)\n";
        print OUT "## Annotation Source Version: $version\n";
        print OUT "## Annotation Accession URL: $annotation_url\n";
        print OUT "## Annotation Source URL: https://www.ebi.ac.uk/interpro/\n";
        print OUT "## Annotation Type: $annotation_type\n";
        print OUT "## Annotation Creation Date: $date\n";
        print OUT join("\t", "## Gene", "${analysis}_accession", "Description", "Score"), "\n";
        
        foreach my $transcript (sort keys %{$annot{$analysis}}) {
            my $id = $annot{$analysis}{$transcript}{id} // '-';
            my $desc = $annot{$analysis}{$transcript}{desc} // '-';
            my $score = $annot{$analysis}{$transcript}{score} // '-';
            
            # Format FunFam IDs
            if ($analysis eq 'FunFam' && $id =~ /G3DSA/) {
                $id =~ s/G3DSA:(.+):FF:(\d+)/$1\/funfam\/$2/;
            }
            
            print OUT join("\t", $transcript, $id, $desc, $score), "\n";
        }
        
        close OUT;
    }
    
    # Generate GO files
    foreach my $go_analysis ('InterPro2GO', 'PANTHER2GO') {
        next unless exists $annot{$go_analysis};
        
        my $filename = "$go_analysis.iprscan.moop.tsv";
        print STDERR "Writing: $filename\n";
        
        open OUT, ">$filename" or die "Cannot open $filename for writing: $!\n";
        
        print OUT "## Annotation Source: InterProScan ($go_analysis)\n";
        print OUT "## Annotation Source Version: $version\n";
        print OUT "## Annotation Accession URL: https://amigo.geneontology.org/amigo/term/\n";
        print OUT "## Annotation Source URL: https://www.ebi.ac.uk/interpro/\n";
        print OUT "## Annotation Type: Gene Ontology\n";
        print OUT "## Annotation Creation Date: $date\n";
        print OUT join("\t", "## Gene", "GO_term", "Description", "Namespace"), "\n";
        
        foreach my $transcript (sort keys %{$annot{$go_analysis}}) {
            my $id = $annot{$go_analysis}{$transcript}{id} // '-';
            my $desc = $annot{$go_analysis}{$transcript}{desc} // '-';
            my $score = $annot{$go_analysis}{$transcript}{score} // '-';
            
            print OUT join("\t", $transcript, $id, $desc, $score), "\n";
        }
        
        close OUT;
    }
}

sub determine_annotation_type {
    my ($analysis) = @_;
    
    my %types = (
        'PANTHER' => 'Gene Families',
        'NCBIfam' => 'Gene Families',
        'Gene3D' => 'Gene Families',
        'FunFam' => 'Gene Families',
        'PIRSF' => 'Gene Families',
        'PRINTS' => 'Gene Families',
        'SFLD' => 'Gene Families',
        'SUPERFAMILY' => 'Gene Families',
        'Hamap' => 'Gene Families',
    );
    
    return $types{$analysis} // 'Domains';
}

sub determine_annotation_url {
    my ($analysis) = @_;
    
    my %urls = (
        'Gene3D' => 'https://www.ebi.ac.uk/interpro/entry/cathgene3d/',
        'FunFam' => 'https://www.cathdb.info/version/latest/superfamily/',
    );
    
    return $urls{$analysis} // 'https://www.ebi.ac.uk/interpro/entry/' . lc($analysis) . '/';
}
