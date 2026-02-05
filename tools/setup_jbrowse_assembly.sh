#!/bin/bash

################################################################################
# JBrowse2 Assembly Setup Script
# 
# Automates the setup of reference genomes for JBrowse2
# Takes an organism path and prepares it for the JBrowse2 browser
#
# Usage:
#   ./setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1 \
#                               [--genome-file genome.fa] \
#                               [--gff-file genomic.gff] \
#                               [--display-name "Anoura caudifer"]
#
# Examples:
#   # Using defaults (genome.fa, genomic.gff)
#   ./setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1
#
#   # With custom filenames
#   ./setup_jbrowse_assembly.sh /organisms/Montipora_capitata/HIv3 \
#                               --genome-file scaffold.fa \
#                               --gff-file genes.gff
#
################################################################################

set -e

# ============================================================================
# CONFIGURATION
# ============================================================================

MOOP_ROOT="/data/moop"
GENOMES_DIR="$MOOP_ROOT/data/genomes"
JBROWSE_CONFIG="$MOOP_ROOT/jbrowse2/config.json"

# Default filenames (can be overridden)
GENOME_FILE="genome.fa"
GFF_FILE="genomic.gff"
DISPLAY_NAME=""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================================================
# FUNCTIONS
# ============================================================================

log_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1" >&2
}

print_usage() {
    cat << EOF
JBrowse2 Assembly Setup Script

USAGE:
  $(basename $0) <organism_path> [OPTIONS]

ARGUMENTS:
  organism_path       Path to organism directory (e.g., /organisms/Anoura_caudifer/GCA_004027475.1)

OPTIONS:
  --genome-file FILE  Genome FASTA filename (default: genome.fa)
  --gff-file FILE     GFF annotation filename (default: genomic.gff)
  --display-name NAME Display name for JBrowse2 UI (optional)
  --help              Show this help message

EXAMPLES:
  # Default filenames
  $(basename $0) /organisms/Anoura_caudifer/GCA_004027475.1

  # Custom filenames
  $(basename $0) /organisms/Montipora_capitata/HIv3 \\
                 --genome-file scaffold.fa \\
                 --gff-file genes.gff \\
                 --display-name "Montipora capitata"

WHAT IT DOES:
  1. Creates /data/genomes/{organism}/{assembly}/ directory
  2. Creates symlinks to genome FASTA and GFF files
  3. Indexes genome with samtools faidx
  4. Compresses and indexes GFF with bgzip and tabix
  5. Verifies all files created successfully

REQUIREMENTS:
  - samtools
  - tabix (htslib)
  - Readable genome FASTA file
  - Readable GFF annotation file

EOF
}

check_dependencies() {
    log_info "Checking dependencies..."
    
    local missing=0
    
    if ! command -v samtools &> /dev/null; then
        log_error "samtools not found. Install with: sudo apt-get install samtools"
        missing=1
    else
        log_success "samtools found: $(samtools --version | head -1)"
    fi
    
    if ! command -v bgzip &> /dev/null; then
        log_error "bgzip not found. Install with: sudo apt-get install tabix"
        missing=1
    else
        log_success "bgzip found"
    fi
    
    if ! command -v tabix &> /dev/null; then
        log_error "tabix not found. Install with: sudo apt-get install tabix"
        missing=1
    else
        log_success "tabix found"
    fi
    
    if [ $missing -eq 1 ]; then
        return 1
    fi
    return 0
}

validate_inputs() {
    local organism_path="$1"
    
    log_info "Validating inputs..."
    
    # Check path argument provided
    if [ -z "$organism_path" ]; then
        log_error "Organism path required"
        print_usage
        return 1
    fi
    
    # Check path exists
    if [ ! -d "$organism_path" ]; then
        log_error "Organism path does not exist: $organism_path"
        return 1
    fi
    
    # Parse path to get organism and assembly
    ORGANISM=$(basename $(dirname "$organism_path"))
    ASSEMBLY=$(basename "$organism_path")
    
    log_success "Organism: $ORGANISM"
    log_success "Assembly: $ASSEMBLY"
    
    # Check genome file exists
    if [ ! -f "$organism_path/$GENOME_FILE" ]; then
        log_error "Genome file not found: $organism_path/$GENOME_FILE"
        return 1
    fi
    log_success "Genome file found: $GENOME_FILE"
    
    # Check GFF file exists
    if [ ! -f "$organism_path/$GFF_FILE" ]; then
        log_error "GFF file not found: $organism_path/$GFF_FILE"
        return 1
    fi
    log_success "GFF file found: $GFF_FILE"
    
    return 0
}

create_symlinks() {
    local source_dir="$1"
    local dest_dir="$2"
    
    log_info "Creating symlinks..."
    
    # Verify destination directory exists and is writable
    if [ ! -d "$dest_dir" ]; then
        log_error "Destination directory does not exist: $dest_dir"
        return 1
    fi
    
    if [ ! -w "$dest_dir" ]; then
        log_error "Destination directory is not writable: $dest_dir"
        return 1
    fi
    
    # Remove existing symlinks if they exist
    if [ -L "$dest_dir/reference.fasta" ]; then
        rm "$dest_dir/reference.fasta"
        log_warn "Removed existing symlink: reference.fasta"
    fi
    
    if [ -L "$dest_dir/annotations.gff3" ]; then
        rm "$dest_dir/annotations.gff3"
        log_warn "Removed existing symlink: annotations.gff3"
    fi
    
    # Create symlinks
    ln -s "$source_dir/$GENOME_FILE" "$dest_dir/reference.fasta"
    if [ $? -ne 0 ]; then
        log_error "Failed to create symlink: reference.fasta"
        return 1
    fi
    log_success "Created symlink: reference.fasta → $GENOME_FILE"
    
    ln -s "$source_dir/$GFF_FILE" "$dest_dir/annotations.gff3"
    if [ $? -ne 0 ]; then
        log_error "Failed to create symlink: annotations.gff3"
        return 1
    fi
    log_success "Created symlink: annotations.gff3 → $GFF_FILE"
}

index_genome() {
    local dest_dir="$1"
    
    log_info "Indexing genome with samtools..."
    
    # Verify destination directory exists
    if [ ! -d "$dest_dir" ]; then
        log_error "Destination directory does not exist: $dest_dir"
        return 1
    fi
    
    cd "$dest_dir"
    
    # Verify reference.fasta exists
    if [ ! -f "reference.fasta" ]; then
        log_error "reference.fasta not found in $dest_dir"
        return 1
    fi
    
    # Remove existing index if it exists
    if [ -f "reference.fasta.fai" ]; then
        rm "reference.fasta.fai"
        log_warn "Removed existing index: reference.fasta.fai"
    fi
    
    samtools faidx reference.fasta
    if [ $? -ne 0 ]; then
        log_error "Failed to index genome with samtools"
        return 1
    fi
    
    if [ ! -f "reference.fasta.fai" ]; then
        log_error "Index file not created: reference.fasta.fai"
        return 1
    fi
    
    log_success "Created: reference.fasta.fai"
}

compress_and_index_gff() {
    local dest_dir="$1"
    
    log_info "Compressing and indexing GFF..."
    
    # Verify destination directory exists
    if [ ! -d "$dest_dir" ]; then
        log_error "Destination directory does not exist: $dest_dir"
        return 1
    fi
    
    cd "$dest_dir"
    
    # Verify annotations.gff3 exists
    if [ ! -f "annotations.gff3" ]; then
        log_error "annotations.gff3 not found in $dest_dir"
        return 1
    fi
    
    # Remove existing files if they exist
    if [ -f "annotations.gff3.gz" ]; then
        rm "annotations.gff3.gz"
        log_warn "Removed existing file: annotations.gff3.gz"
    fi
    
    if [ -f "annotations.gff3.gz.tbi" ]; then
        rm "annotations.gff3.gz.tbi"
        log_warn "Removed existing file: annotations.gff3.gz.tbi"
    fi
    
    # Sort GFF file before compression
    # JBrowse requires sorted GFF for proper tabix indexing
    # Sort by: chromosome (column 1), then position (column 4, numeric)
    log_info "Sorting GFF file..."
    local sorted_gff="/tmp/annotations_sorted_$$.gff"
    (grep "^#" annotations.gff3; grep -v "^#" annotations.gff3 | sort -t"$(printf '\t')" -k1,1 -k4,4n) > "$sorted_gff"
    if [ $? -ne 0 ]; then
        log_error "Failed to sort GFF file"
        rm -f "$sorted_gff"
        return 1
    fi
    log_success "GFF file sorted"
    
    # Compress sorted GFF
    bgzip -c "$sorted_gff" > annotations.gff3.gz
    if [ $? -ne 0 ]; then
        log_error "Failed to compress GFF with bgzip"
        rm -f "$sorted_gff"
        return 1
    fi
    rm -f "$sorted_gff"
    log_success "Created: annotations.gff3.gz"
    
    # Verify compressed file exists
    if [ ! -f "annotations.gff3.gz" ]; then
        log_error "Compressed file not created: annotations.gff3.gz"
        return 1
    fi
    
    # Index with tabix
    tabix -p gff annotations.gff3.gz
    if [ $? -ne 0 ]; then
        log_error "Failed to index GFF with tabix"
        return 1
    fi
    
    if [ ! -f "annotations.gff3.gz.tbi" ]; then
        log_error "Index file not created: annotations.gff3.gz.tbi"
        return 1
    fi
    
    log_success "Created: annotations.gff3.gz.tbi"
}

verify_setup() {
    local dest_dir="$1"
    
    log_info "Verifying setup..."
    
    local required_files=(
        "reference.fasta"
        "reference.fasta.fai"
        "annotations.gff3"
        "annotations.gff3.gz"
        "annotations.gff3.gz.tbi"
    )
    
    local all_exist=1
    for file in "${required_files[@]}"; do
        if [ -f "$dest_dir/$file" ]; then
            log_success "Found: $file"
        else
            log_error "Missing: $file"
            all_exist=0
        fi
    done
    
    if [ $all_exist -eq 0 ]; then
        return 1
    fi
    return 0
}

print_summary() {
    local dest_dir="$1"
    
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    log_success "Assembly setup complete!"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    echo "Files created in:"
    echo "  $dest_dir"
    echo ""
    echo "File structure:"
    ls -lh "$dest_dir" | tail -n +2 | awk '{print "  " $9 " (" $5 ")"}'
    echo ""
    echo "Next steps:"
    echo "  1. Update /data/moop/jbrowse2/config.json with assembly configuration"
    echo "  2. Test in browser: http://127.0.0.1:8888/jbrowse2/"
    echo "  3. Load tracks from: $ORGANISM / $ASSEMBLY"
    echo ""
    echo "For reference, the setup included:"
    echo "  - Reference genome: reference.fasta (symlink)"
    echo "  - Genome index: reference.fasta.fai"
    echo "  - Annotations: annotations.gff3.gz (compressed)"
    echo "  - Annotations index: annotations.gff3.gz.tbi"
    echo ""
}

# ============================================================================
# MAIN
# ============================================================================

main() {
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "    JBrowse2 Assembly Setup"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    
    # Parse arguments
    ORGANISM_PATH=""
    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                print_usage
                exit 0
                ;;
            --genome-file)
                GENOME_FILE="$2"
                shift 2
                ;;
            --gff-file)
                GFF_FILE="$2"
                shift 2
                ;;
            --display-name)
                DISPLAY_NAME="$2"
                shift 2
                ;;
            *)
                if [ -z "$ORGANISM_PATH" ]; then
                    ORGANISM_PATH="$1"
                    shift
                else
                    log_error "Unknown option: $1"
                    print_usage
                    exit 1
                fi
                ;;
        esac
    done
    
    # Validate organism path provided
    if [ -z "$ORGANISM_PATH" ]; then
        log_error "Organism path is required"
        print_usage
        exit 1
    fi
    
    # Run checks and setup
    check_dependencies || exit 1
    echo ""
    
    validate_inputs "$ORGANISM_PATH" || exit 1
    echo ""
    
    log_info "Setting up directories..."
    DEST_DIR="$GENOMES_DIR/$ORGANISM/$ASSEMBLY"
    mkdir -p "$DEST_DIR"
    if [ $? -ne 0 ]; then
        log_error "Failed to create directory: $DEST_DIR"
        exit 1
    fi
    log_success "Created: $DEST_DIR"
    echo ""
    
    create_symlinks "$ORGANISM_PATH" "$DEST_DIR" || exit 1
    echo ""
    
    index_genome "$DEST_DIR"
    echo ""
    
    compress_and_index_gff "$DEST_DIR"
    echo ""
    
    verify_setup "$DEST_DIR" || exit 1
    echo ""
    
    print_summary "$DEST_DIR"
    
    return 0
}

# Run main function with all arguments
main "$@"
exit $?
