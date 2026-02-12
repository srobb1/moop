#!/bin/bash
################################################################################
# Index Default GFF Annotations for Text Search
#
# This script creates text search indexes for the default annotations.gff3.gz
# files so users can search for gene IDs and names in JBrowse2.
#
# Usage:
#   ./index_default_annotations.sh <organism> <assembly>
#
# Example:
#   ./index_default_annotations.sh Nematostella_vectensis GCA_033964005.1
#
# Or index all assemblies:
#   ./index_default_annotations.sh --all
#
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ${NC} $1"; }
log_success() { echo -e "${GREEN}✓${NC} $1"; }
log_warn() { echo -e "${YELLOW}⚠${NC} $1"; }
log_error() { echo -e "${RED}✗${NC} $1" >&2; }

# Default values
MOOP_ROOT="/data/moop"
GENOMES_DIR="$MOOP_ROOT/data/genomes"
TRIX_DIR="$MOOP_ROOT/data/tracks/trix"

# Check for JBrowse CLI
if ! command -v jbrowse &> /dev/null; then
    log_error "JBrowse CLI is required but not installed"
    log_error "Install with: sudo npm install -g @jbrowse/cli"
    exit 1
fi

# Parse arguments
if [ "$1" = "--all" ]; then
    INDEX_ALL=true
    log_info "Indexing all assemblies..."
else
    if [ $# -lt 2 ]; then
        echo "Usage: $0 <organism> <assembly>"
        echo "   or: $0 --all"
        echo ""
        echo "Example:"
        echo "  $0 Nematostella_vectensis GCA_033964005.1"
        echo "  $0 --all"
        exit 1
    fi
    INDEX_ALL=false
    ORGANISM="$1"
    ASSEMBLY="$2"
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "    Index Default Annotations for Text Search"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Create trix directory
mkdir -p "$TRIX_DIR"

# Function to index a single assembly
index_assembly() {
    local organism=$1
    local assembly=$2
    local assembly_name="${organism}_${assembly}"
    local gff_file="$GENOMES_DIR/${organism}/${assembly}/annotations.gff3.gz"
    
    if [ ! -f "$gff_file" ]; then
        log_warn "No annotations found for ${assembly_name}"
        return 1
    fi
    
    log_info "Indexing: ${assembly_name}"
    
    # Create temporary JBrowse directory
    TEMP_DIR="/tmp/jbrowse_index_${assembly_name}_$$"
    mkdir -p "$TEMP_DIR"
    
    # Create minimal config
    cat > "$TEMP_DIR/config.json" << EOF
{
  "assemblies": [{
    "name": "${assembly_name}",
    "sequence": {
      "type": "ReferenceSequenceTrack",
      "trackId": "ref",
      "adapter": {
        "type": "IndexedFastaAdapter",
        "fastaLocation": { "uri": "dummy.fa" },
        "faiLocation": { "uri": "dummy.fa.fai" }
      }
    }
  }],
  "tracks": []
}
EOF
    
    # Add track
    log_info "  Adding track to temporary config..."
    jbrowse add-track "$gff_file" \
        --assemblyNames "${assembly_name}" \
        --trackId "${assembly_name}-genes" \
        --name "Gene Annotations" \
        --load inPlace \
        --target "$TEMP_DIR/config.json" 2>&1 | grep -v "Warning:" || true
    
    # Create text index
    log_info "  Creating text search index..."
    jbrowse text-index \
        --out "$TEMP_DIR" \
        --perTrack \
        --attributes "ID,Name,gene,product,description" \
        --tracks="${assembly_name}-genes" 2>&1 | grep -v "Warning:" || true
    
    # Move trix files
    if [ -d "$TEMP_DIR/trix" ]; then
        log_info "  Moving index files..."
        mv "$TEMP_DIR/trix/${assembly_name}-genes"* "$TRIX_DIR/" 2>/dev/null || true
        
        # Check if files were created
        if [ -f "$TRIX_DIR/${assembly_name}-genes.ix" ]; then
            log_success "  Indexed: ${assembly_name}"
            log_success "    Files: ${assembly_name}-genes.{ix,ixx,_meta.json}"
        else
            log_error "  Indexing failed - no output files"
            rm -rf "$TEMP_DIR"
            return 1
        fi
    else
        log_error "  Indexing failed - no trix directory created"
        rm -rf "$TEMP_DIR"
        return 1
    fi
    
    # Clean up
    rm -rf "$TEMP_DIR"
    return 0
}

# Index assemblies
SUCCESS_COUNT=0
FAIL_COUNT=0

if [ "$INDEX_ALL" = true ]; then
    # Find all assemblies
    for organism_dir in "$GENOMES_DIR"/*; do
        if [ ! -d "$organism_dir" ]; then
            continue
        fi
        
        organism=$(basename "$organism_dir")
        
        for assembly_dir in "$organism_dir"/*; do
            if [ ! -d "$assembly_dir" ]; then
                continue
            fi
            
            assembly=$(basename "$assembly_dir")
            
            if index_assembly "$organism" "$assembly"; then
                ((SUCCESS_COUNT++))
            else
                ((FAIL_COUNT++))
            fi
        done
    done
else
    if index_assembly "$ORGANISM" "$ASSEMBLY"; then
        ((SUCCESS_COUNT++))
    else
        ((FAIL_COUNT++))
    fi
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "Summary:"
echo "  Indexed: $SUCCESS_COUNT assemblies"
if [ $FAIL_COUNT -gt 0 ]; then
    echo "  Failed: $FAIL_COUNT assemblies"
fi
echo ""
echo "Index files location: $TRIX_DIR"
echo ""
echo "Next steps:"
echo "  1. Update generate-jbrowse-configs.php to include text search config"
echo "  2. Run: php tools/jbrowse/generate-jbrowse-configs.php"
echo "  3. Refresh JBrowse2 to test searching"
echo ""
