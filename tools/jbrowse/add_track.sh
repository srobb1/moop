#!/bin/bash
################################################################################
# Add Track to JBrowse2 (Master Script with Auto-Detection)
#
# This script automatically detects track type from file extension and calls
# the appropriate specialized script.
#
# Usage:
#   ./add_track.sh <track_file> <organism> <assembly> [options]
#
# Examples:
#   ./add_track.sh data.bw Anoura_caudifer GCA_004027475.1 --name "RNA-seq"
#   ./add_track.sh aligns.bam Anoura_caudifer GCA_004027475.1 --category "DNA"
#   ./add_track.sh variants.vcf.gz Anoura_caudifer GCA_004027475.1
#
# Supported Formats:
#   .bw, .bigwig       -> BigWig (quantitative data)
#   .bam               -> BAM (alignments)
#   .vcf, .vcf.gz      -> VCF (variants)
#
# All options are passed through to the specific handler script.
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
log_header() { echo -e "${CYAN}$1${NC}"; }

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Usage
if [ $# -lt 3 ]; then
    cat << EOF
Usage: $0 <track_file> <organism> <assembly> [options]

This script auto-detects track type from file extension and processes
it appropriately for JBrowse2.

Supported Formats:
  .bw, .bigwig    BigWig files (RNA-seq, ChIP-seq coverage)
  .bam            BAM files (sequence alignments)
  .vcf, .vcf.gz   VCF files (variants, SNPs, indels)

Examples:
  $0 coverage.bw Anoura_caudifer GCA_004027475.1 --name "RNA-seq Coverage"
  $0 align.bam Anoura_caudifer GCA_004027475.1 --category "Alignments"
  $0 snps.vcf.gz Anoura_caudifer GCA_004027475.1 --tissue "liver"

Common Options:
  --name <name>           Track display name
  --category <category>   Track category/group
  --access <level>        Access level (Public, Collaborator, ALL)
  --description <text>    Track description

Metadata Options (for Google Sheets integration):
  --technique <text>           Experimental technique
  --institute <text>           Institution
  --source <text>              Data source
  --experiment <text>          Experiment ID
  --developmental-stage <text> Development stage
  --tissue <text>              Tissue type
  --condition <text>           Experimental condition
  --summary <text>             Summary
  --citation <text>            Citation
  --project <text>             Project name
  --accession <text>           Accession number
  --date <date>                Date
  --analyst <text>             Analyst name

For more options, see the specific track handler scripts:
  add_bigwig_track.sh
  add_bam_track.sh
  add_vcf_track.sh
EOF
    exit 1
fi

TRACK_FILE="$1"
ORGANISM="$2"
ASSEMBLY="$3"
shift 3

echo ""
log_header "═══════════════════════════════════════════════════════════════"
log_header "    JBrowse2 Track Addition (Auto-Detection)"
log_header "═══════════════════════════════════════════════════════════════"
echo ""

# Validate file exists
if [ ! -f "$TRACK_FILE" ]; then
    log_error "Track file not found: $TRACK_FILE"
    exit 1
fi

# Get file extension and basename
FILENAME=$(basename "$TRACK_FILE")
EXTENSION="${FILENAME##*.}"
FULL_EXT=""

# Handle double extensions like .vcf.gz
if [[ "$FILENAME" =~ \.vcf\.gz$ ]]; then
    FULL_EXT="vcf.gz"
elif [[ "$FILENAME" =~ \.fasta\.gz$ ]]; then
    FULL_EXT="fasta.gz"
elif [[ "$FILENAME" =~ \.gff3?\.gz$ ]]; then
    FULL_EXT="gff.gz"
else
    FULL_EXT="$EXTENSION"
fi

log_info "Detected file: $FILENAME"
log_info "Extension: .$FULL_EXT"

# Auto-detect track type and call appropriate script
case "$FULL_EXT" in
    bw|bigwig|bigWig)
        log_info "Track type: BigWig (Quantitative)"
        HANDLER="$SCRIPT_DIR/add_bigwig_track.sh"
        ;;
    bam)
        log_info "Track type: BAM (Alignments)"
        HANDLER="$SCRIPT_DIR/add_bam_track.sh"
        ;;
    vcf|vcf.gz)
        log_info "Track type: VCF (Variants)"
        HANDLER="$SCRIPT_DIR/add_vcf_track.sh"
        ;;
    cram)
        log_error "CRAM format detected"
        log_error "CRAM files are not yet supported. Convert to BAM first:"
        log_error "  samtools view -b -o output.bam input.cram"
        exit 1
        ;;
    bed|bed.gz)
        log_error "BED format detected"
        log_error "BED files are not yet supported in JBrowse2 via this script"
        log_error "Consider converting to GFF3 or adding manually"
        exit 1
        ;;
    gff|gff3|gff.gz|gff3.gz)
        log_error "GFF format detected"
        log_error "GFF files should be added during assembly setup"
        log_error "Use: setup_jbrowse_assembly.sh"
        exit 1
        ;;
    *)
        log_error "Unsupported file format: .$FULL_EXT"
        log_error ""
        log_error "Supported formats:"
        log_error "  - BigWig: .bw, .bigwig"
        log_error "  - BAM: .bam"
        log_error "  - VCF: .vcf, .vcf.gz"
        log_error ""
        log_error "If this is a valid track file, you may need to:"
        log_error "  1. Rename it with the correct extension"
        log_error "  2. Convert it to a supported format"
        log_error "  3. Add support for this format (contact developers)"
        exit 1
        ;;
esac

# Check handler script exists
if [ ! -f "$HANDLER" ]; then
    log_error "Handler script not found: $HANDLER"
    log_error "Required scripts may not be installed properly"
    exit 1
fi

if [ ! -x "$HANDLER" ]; then
    log_error "Handler script is not executable: $HANDLER"
    log_error "Run: chmod +x $HANDLER"
    exit 1
fi

# Auto-detect category from filename patterns if not provided
AUTO_CATEGORY=""
if ! echo "$@" | grep -q "\-\-category"; then
    LOWER_FILENAME=$(echo "$FILENAME" | tr '[:upper:]' '[:lower:]')
    
    # RNA-seq patterns
    if echo "$LOWER_FILENAME" | grep -qE "(rna|rnaseq|transcript|expression)"; then
        AUTO_CATEGORY="Transcriptomics"
    # ChIP-seq patterns
    elif echo "$LOWER_FILENAME" | grep -qE "(chip|chipseq|histone|h3k|atac)"; then
        AUTO_CATEGORY="Epigenomics"
    # DNA-seq patterns
    elif echo "$LOWER_FILENAME" | grep -qE "(dna|dnaseq|wgs|genome|reseq)"; then
        AUTO_CATEGORY="Genomics"
    # Variant patterns
    elif echo "$LOWER_FILENAME" | grep -qE "(snp|variant|indel|mutation)"; then
        AUTO_CATEGORY="Variants"
    # Alignment patterns
    elif echo "$LOWER_FILENAME" | grep -qE "(align|mapping|map)"; then
        AUTO_CATEGORY="Alignments"
    fi
    
    if [ -n "$AUTO_CATEGORY" ]; then
        log_info "Auto-detected category: $AUTO_CATEGORY"
        set -- "$@" "--category" "$AUTO_CATEGORY"
    fi
fi

# Call the appropriate handler
log_info "Calling handler: $(basename "$HANDLER")"
echo ""

"$HANDLER" "$TRACK_FILE" "$ORGANISM" "$ASSEMBLY" "$@"

# Check exit code
if [ $? -eq 0 ]; then
    echo ""
    log_header "═══════════════════════════════════════════════════════════════"
    log_success "Track successfully added via auto-detection!"
    log_header "═══════════════════════════════════════════════════════════════"
    echo ""
else
    echo ""
    log_error "Track addition failed"
    exit 1
fi
