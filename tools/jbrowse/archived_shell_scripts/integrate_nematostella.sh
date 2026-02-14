#!/bin/bash
################################################################################
# Quick Integration Script: Nematostella vectensis
#
# This script automates the complete integration of Nematostella vectensis
# into the MOOP JBrowse2 system.
#
# Usage:
#   ./integrate_nematostella.sh [--dry-run]
#
# Options:
#   --dry-run    Show what would be done without executing
#
# What this does:
#   1. Prepares genome files (indexes, compresses)
#   2. Registers assembly in JBrowse2
#   3. Adds BAM alignment track
#   4. Adds BigWig coverage tracks (pos/neg)
#   5. Validates everything worked
#
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ${NC} $1"; }
log_success() { echo -e "${GREEN}✓${NC} $1"; }
log_warn() { echo -e "${YELLOW}⚠${NC} $1"; }
log_error() { echo -e "${RED}✗${NC} $1" >&2; }
log_header() { echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"; echo -e "${CYAN}$1${NC}"; echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"; }

# Configuration
ORGANISM="Nematostella_vectensis"
ASSEMBLY="GCA_033964005.1"
DISPLAY_NAME="Nematostella vectensis (Starlet Sea Anemone)"
ACCESS_LEVEL="PUBLIC"

MOOP_ROOT="/data/moop"
SOURCE_DIR="$MOOP_ROOT/organisms/$ORGANISM/$ASSEMBLY"
TRACKS_DIR="$MOOP_ROOT/data/tracks/$ORGANISM/$ASSEMBLY"
TOOLS_DIR="$MOOP_ROOT/tools/jbrowse"

# Track files
BAM_FILE="$TRACKS_DIR/bam/MOLNG-2707_S3-body-wall.bam"
BIGWIG_POS="$TRACKS_DIR/bigwig/MOLNG-2707_S1-body-wall.pos.bw"
BIGWIG_NEG="$TRACKS_DIR/bigwig/MOLNG-2707_S1-body-wall.neg.bw"

# Parse options
DRY_RUN=false
if [ "$1" = "--dry-run" ]; then
    DRY_RUN=true
    log_warn "DRY RUN MODE - No changes will be made"
    echo ""
fi

# Helper function
run_or_show() {
    local cmd="$1"
    local desc="$2"
    
    echo ""
    log_info "$desc"
    echo "Command: $cmd"
    
    if [ "$DRY_RUN" = true ]; then
        log_warn "[DRY RUN] Would execute: $cmd"
        return 0
    else
        eval "$cmd"
        return $?
    fi
}

# Validate prerequisites
validate_prerequisites() {
    log_header "Validating Prerequisites"
    
    local errors=0
    
    # Check source files exist
    if [ ! -f "$SOURCE_DIR/genome.fa" ]; then
        log_error "Source genome not found: $SOURCE_DIR/genome.fa"
        errors=$((errors + 1))
    else
        log_success "Found: genome.fa"
    fi
    
    if [ ! -f "$SOURCE_DIR/genomic.gff" ]; then
        log_error "Source GFF not found: $SOURCE_DIR/genomic.gff"
        errors=$((errors + 1))
    else
        log_success "Found: genomic.gff"
    fi
    
    # Check track files exist
    if [ ! -f "$BAM_FILE" ]; then
        log_error "BAM file not found: $BAM_FILE"
        errors=$((errors + 1))
    else
        log_success "Found: $(basename $BAM_FILE)"
    fi
    
    if [ ! -f "${BAM_FILE}.bai" ]; then
        log_error "BAM index not found: ${BAM_FILE}.bai"
        errors=$((errors + 1))
    else
        log_success "Found: $(basename ${BAM_FILE}.bai)"
    fi
    
    if [ ! -f "$BIGWIG_POS" ]; then
        log_error "BigWig pos not found: $BIGWIG_POS"
        errors=$((errors + 1))
    else
        log_success "Found: $(basename $BIGWIG_POS)"
    fi
    
    if [ ! -f "$BIGWIG_NEG" ]; then
        log_error "BigWig neg not found: $BIGWIG_NEG"
        errors=$((errors + 1))
    else
        log_success "Found: $(basename $BIGWIG_NEG)"
    fi
    
    # Check tools exist
    local tools=("samtools" "bgzip" "tabix" "jq")
    for tool in "${tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            log_error "Required tool not found: $tool"
            errors=$((errors + 1))
        else
            log_success "Tool available: $tool"
        fi
    done
    
    # Check scripts exist
    if [ ! -f "$TOOLS_DIR/setup_jbrowse_assembly.sh" ]; then
        log_error "Script not found: setup_jbrowse_assembly.sh"
        errors=$((errors + 1))
    else
        log_success "Found: setup_jbrowse_assembly.sh"
    fi
    
    if [ ! -f "$TOOLS_DIR/add_assembly_to_jbrowse.sh" ]; then
        log_error "Script not found: add_assembly_to_jbrowse.sh"
        errors=$((errors + 1))
    else
        log_success "Found: add_assembly_to_jbrowse.sh"
    fi
    
    if [ ! -f "$TOOLS_DIR/add_bam_track.sh" ]; then
        log_error "Script not found: add_bam_track.sh"
        errors=$((errors + 1))
    else
        log_success "Found: add_bam_track.sh"
    fi
    
    if [ ! -f "$TOOLS_DIR/add_bigwig_track.sh" ]; then
        log_error "Script not found: add_bigwig_track.sh"
        errors=$((errors + 1))
    else
        log_success "Found: add_bigwig_track.sh"
    fi
    
    if [ $errors -gt 0 ]; then
        echo ""
        log_error "Validation failed with $errors error(s)"
        exit 1
    fi
    
    echo ""
    log_success "All prerequisites validated!"
    return 0
}

# Step 1: Prepare genome files
prepare_genome() {
    log_header "Step 1: Preparing Genome Files"
    
    run_or_show \
        "$TOOLS_DIR/setup_jbrowse_assembly.sh $SOURCE_DIR" \
        "Setting up genome reference and annotations"
    
    if [ $? -eq 0 ]; then
        log_success "Genome preparation complete"
    else
        log_error "Genome preparation failed"
        return 1
    fi
}

# Step 2: Register assembly
register_assembly() {
    log_header "Step 2: Registering Assembly in JBrowse2"
    
    run_or_show \
        "$TOOLS_DIR/add_assembly_to_jbrowse.sh $ORGANISM $ASSEMBLY --display-name \"$DISPLAY_NAME\" --access-level $ACCESS_LEVEL --alias NVE" \
        "Creating assembly metadata"
    
    if [ $? -eq 0 ]; then
        log_success "Assembly registration complete"
    else
        log_error "Assembly registration failed"
        return 1
    fi
}

# Step 3: Add BAM track
add_bam_track() {
    log_header "Step 3: Adding BAM Alignment Track"
    
    run_or_show \
        "$TOOLS_DIR/add_bam_track.sh \"$BAM_FILE\" $ORGANISM $ASSEMBLY --name \"Body Wall RNA-seq Alignments (S3)\" --category \"RNA-seq\" --access ADMIN --tissue \"body wall\" --experiment \"MOLNG-2707\" --technique \"RNA-seq\" --description \"RNA-seq alignments from body wall tissue, sample S3 (Admin only)\"" \
        "Adding BAM track metadata (ADMIN access)"
    
    if [ $? -eq 0 ]; then
        log_success "BAM track added"
    else
        log_error "BAM track addition failed"
        return 1
    fi
}

# Step 4: Add BigWig tracks
add_bigwig_tracks() {
    log_header "Step 4: Adding BigWig Coverage Tracks"
    
    # Positive strand
    echo ""
    log_info "Adding positive strand coverage..."
    run_or_show \
        "$TOOLS_DIR/add_bigwig_track.sh \"$BIGWIG_POS\" $ORGANISM $ASSEMBLY --name \"Body Wall RNA-seq Coverage (+)\" --track-id \"nv_body_wall_pos\" --category \"RNA-seq Coverage\" --access PUBLIC --color \"#1f77b4\" --tissue \"body wall\" --experiment \"MOLNG-2707\" --technique \"RNA-seq\" --description \"RNA-seq coverage on positive strand, sample S1\"" \
        "Creating positive strand track"
    
    if [ $? -ne 0 ]; then
        log_error "Positive strand track addition failed"
        return 1
    fi
    log_success "Positive strand track added"
    
    # Negative strand
    echo ""
    log_info "Adding negative strand coverage..."
    run_or_show \
        "$TOOLS_DIR/add_bigwig_track.sh \"$BIGWIG_NEG\" $ORGANISM $ASSEMBLY --name \"Body Wall RNA-seq Coverage (-)\" --track-id \"nv_body_wall_neg\" --category \"RNA-seq Coverage\" --access PUBLIC --color \"#ff7f0e\" --tissue \"body wall\" --experiment \"MOLNG-2707\" --technique \"RNA-seq\" --description \"RNA-seq coverage on negative strand, sample S1\"" \
        "Creating negative strand track"
    
    if [ $? -ne 0 ]; then
        log_error "Negative strand track addition failed"
        return 1
    fi
    log_success "Negative strand track added"
}

# Validate results
validate_results() {
    log_header "Step 5: Validating Integration"
    
    if [ "$DRY_RUN" = true ]; then
        log_warn "[DRY RUN] Skipping validation"
        return 0
    fi
    
    local errors=0
    
    # Check genome data
    if [ -f "$MOOP_ROOT/data/genomes/$ORGANISM/$ASSEMBLY/reference.fasta.fai" ]; then
        log_success "Genome indexed: reference.fasta.fai"
    else
        log_error "Missing: reference.fasta.fai"
        errors=$((errors + 1))
    fi
    
    if [ -f "$MOOP_ROOT/data/genomes/$ORGANISM/$ASSEMBLY/annotations.gff3.gz" ]; then
        log_success "Annotations compressed: annotations.gff3.gz"
    else
        log_error "Missing: annotations.gff3.gz"
        errors=$((errors + 1))
    fi
    
    if [ -f "$MOOP_ROOT/data/genomes/$ORGANISM/$ASSEMBLY/annotations.gff3.gz.tbi" ]; then
        log_success "Annotations indexed: annotations.gff3.gz.tbi"
    else
        log_error "Missing: annotations.gff3.gz.tbi"
        errors=$((errors + 1))
    fi
    
    # Check assembly metadata
    if [ -f "$MOOP_ROOT/metadata/jbrowse2-configs/assemblies/${ORGANISM}_${ASSEMBLY}.json" ]; then
        log_success "Assembly metadata created"
        
        # Validate JSON
        if jq . "$MOOP_ROOT/metadata/jbrowse2-configs/assemblies/${ORGANISM}_${ASSEMBLY}.json" > /dev/null 2>&1; then
            log_success "Assembly JSON is valid"
        else
            log_error "Assembly JSON is invalid"
            errors=$((errors + 1))
        fi
    else
        log_error "Missing: assembly metadata"
        errors=$((errors + 1))
    fi
    
    # Check track metadata
    local track_count=$(ls -1 "$MOOP_ROOT/metadata/jbrowse2-configs/tracks/"*body_wall*.json 2>/dev/null | wc -l)
    if [ $track_count -ge 3 ]; then
        log_success "Track metadata created ($track_count files)"
    else
        log_warn "Expected 3+ track files, found $track_count"
    fi
    
    if [ $errors -gt 0 ]; then
        echo ""
        log_error "Validation failed with $errors error(s)"
        return 1
    fi
    
    echo ""
    log_success "All validation checks passed!"
    return 0
}

# Print summary
print_summary() {
    log_header "Integration Complete!"
    
    echo ""
    echo "Organism: $ORGANISM"
    echo "Assembly: $ASSEMBLY"
    echo "Display Name: $DISPLAY_NAME"
    echo "Access Level: $ACCESS_LEVEL"
    echo ""
    echo "Files Created:"
    echo "  • Reference genome (indexed)"
    echo "  • Annotations (compressed + indexed)"
    echo "  • Assembly metadata JSON"
    echo "  • 3 track metadata files (BAM + 2 BigWig)"
    echo ""
    echo "What's Available in JBrowse2:"
    echo "  ✓ Reference sequence track"
    echo "  ✓ Gene annotations track (automatic)"
    echo "  ✓ RNA-seq alignment track (BAM) - ADMIN ONLY"
    echo "  ✓ RNA-seq coverage tracks (BigWig pos/neg) - PUBLIC"
    echo ""
    echo "Next Steps:"
    echo "  1. Navigate to: http://localhost:8888/moop/jbrowse2.php"
    echo "  2. Find 'Nematostella vectensis' in the assembly list"
    echo "  3. Click to open and explore the genome"
    echo "  4. Try zooming in to see gene details"
    echo "  5. Toggle tracks on/off to compare data"
    echo ""
    echo "API Test:"
    echo "  curl -s 'http://localhost:8888/api/jbrowse2/test-assembly.php?organism=$ORGANISM&assembly=$ASSEMBLY' | jq ."
    echo ""
    echo "Documentation:"
    echo "  • Walkthrough: docs/JBrowse2/WALKTHROUGH_Nematostella_vectensis.md"
    echo "  • Implementation Plan: docs/JBrowse2/IMPLEMENTATION_PLAN_Nematostella.md"
    echo ""
}

# Main execution
main() {
    echo ""
    log_header "Nematostella vectensis - JBrowse2 Integration"
    echo ""
    
    if [ "$DRY_RUN" = true ]; then
        log_warn "DRY RUN MODE ENABLED"
        echo ""
    fi
    
    # Run all steps
    validate_prerequisites || exit 1
    
    echo ""
    read -p "Proceed with integration? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_warn "Integration cancelled by user"
        exit 0
    fi
    
    prepare_genome || exit 1
    register_assembly || exit 1
    add_bam_track || exit 1
    add_bigwig_tracks || exit 1
    validate_results || exit 1
    
    echo ""
    print_summary
    
    log_success "Integration successful!"
}

# Run main
main
