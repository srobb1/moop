#!/bin/bash

################################################################################
# Create Assembly Definition File for JBrowse2
#
# Creates a modular assembly definition JSON file that the dynamic API
# (/api/jbrowse2/assembly.php) will use to generate permission-aware configs.
# This complements setup_jbrowse_assembly.sh by registering the assembly
# in the metadata system (not static config.json).
#
# Usage:
#   ./add_assembly_to_jbrowse.sh <organism> <assembly_id> [OPTIONS]
#
# Examples:
#   # Basic usage - auto-detects from organism.sqlite
#   ./add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1
#
#   # With custom display name
#   ./add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1 \
#     --display-name "Anoura caudifer (GCA_004027475.1)"
#
#   # With explicit aliases (overrides auto-detection)
#   ./add_assembly_to_jbrowse.sh Anoura_caudifer GCA_004027475.1 \
#     --alias "ACA1" --alias "GCA_004027475.1"
#
################################################################################

set -e

# ============================================================================
# CONFIGURATION
# ============================================================================

MOOP_ROOT="/data/moop"
GENOMES_DIR="$MOOP_ROOT/data/genomes"
METADATA_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/assemblies"
ORGANISMS_DIR="/data/moop/organisms"

# Default FASTA URI base path (web-accessible path from browser)
# Can be overridden with --fasta-uri-base parameter
# When accessed via: http://localhost:8000/moop/... → FASTA_URI_BASE="/moop/data/genomes"
# The browser will request: /moop/data/genomes/{organism}/{assembly}/reference.fasta
FASTA_URI_BASE="/moop/data/genomes"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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
Create Assembly Definition File for JBrowse2

USAGE:
  $(basename $0) <organism> <assembly_id> [OPTIONS]

ARGUMENTS:
  organism      Organism name (e.g., Anoura_caudifer)
  assembly_id   Assembly identifier (e.g., GCA_004027475.1)

OPTIONS:
  --display-name NAME       Display name for JBrowse2 UI
  --alias NAME              Assembly alias (can be used multiple times)
  --access-level LEVEL      Default access level (Public, Collaborator, ALL)
  --fasta-uri-base URI      Base URI for FASTA files (default: /moop/data/genomes)
  --help                    Show this help message

EXAMPLES:
  # Auto-detect genome_name from organism.sqlite
  $(basename $0) Anoura_caudifer GCA_004027475.1

  # With custom display name
  $(basename $0) Anoura_caudifer GCA_004027475.1 \\
    --display-name "Anoura caudifer (GCA_004027475.1)"

  # With explicit aliases (overrides auto-detection)
  $(basename $0) Anoura_caudifer GCA_004027475.1 \\
    --alias "ACA1" --alias "GCA_004027475.1"

  # With custom FASTA URI base (for different deployments)
  $(basename $0) Anoura_caudifer GCA_004027475.1 \\
    --fasta-uri-base "/genomes"

WHAT IT DOES:
  1. Validates genome files exist in /data/genomes/{organism}/{assembly_id}/
  2. Auto-detects genome_name from /organisms/{organism}/organism.sqlite
  3. Creates assembly definition JSON in /metadata/jbrowse2-configs/assemblies/
  4. File is read by /api/jbrowse2/assembly.php for dynamic config generation
  5. Supports permission-aware track filtering

REQUIREMENTS:
  - Files prepared by setup_jbrowse_assembly.sh
  - organism.sqlite with genome table (optional, for auto-detection)
  - jq for JSON formatting (optional)

EOF
}

check_dependencies() {
    log_info "Checking dependencies..."

    if ! command -v sqlite3 &> /dev/null; then
        log_warn "sqlite3 not found - cannot auto-detect genome_name"
    else
        log_success "sqlite3 found"
    fi

    return 0
}

validate_inputs() {
    local organism="$1"
    local assembly_id="$2"

    log_info "Validating inputs..."

    if [ -z "$organism" ] || [ -z "$assembly_id" ]; then
        log_error "Organism and assembly_id are required"
        return 1
    fi

    # Check assembly directory exists
    local assembly_dir="$GENOMES_DIR/$organism/$assembly_id"
    if [ ! -d "$assembly_dir" ]; then
        log_error "Assembly directory not found: $assembly_dir"
        log_info "Run setup_jbrowse_assembly.sh first to prepare files"
        return 1
    fi
    log_success "Assembly directory found"

    # Check required files exist
    local required_files=(
        "reference.fasta"
        "reference.fasta.fai"
    )

    for file in "${required_files[@]}"; do
        if [ ! -f "$assembly_dir/$file" ]; then
            log_error "Required file not found: $file"
            return 1
        fi
    done
    log_success "All required files present"

    return 0
}

get_genome_name_from_db() {
    local organism="$1"

    if ! command -v sqlite3 &> /dev/null; then
        return 1
    fi

    local db_path="/data/moop/organisms/$organism/organism.sqlite"
    if [ ! -f "$db_path" ]; then
        return 1
    fi

    # Query the genome_name from the genome table
    local genome_name=$(sqlite3 "$db_path" "SELECT genome_name FROM genome LIMIT 1;" 2>/dev/null)

    if [ -z "$genome_name" ]; then
        return 1
    fi

    echo "$genome_name"
    return 0
}

register_assembly() {
    local organism="$1"
    local assembly_id="$2"
    local display_name="$3"
    local access_level="$4"
    local fasta_uri_base="$5"
    shift 5
    local aliases=("$@")

    log_info "Creating assembly definition file..."

    local def_file="$METADATA_DIR/${organism}_${assembly_id}.json"

    # Build JSON structure
    local assembly_name="${organism}_${assembly_id}"
    
    # Build aliases array
    local aliases_json="["
    for i in "${!aliases[@]}"; do
        aliases_json+="\"${aliases[$i]}\""
        if [ $i -lt $((${#aliases[@]} - 1)) ]; then
            aliases_json+=", "
        fi
    done
    aliases_json+="]"

    # Create assembly definition JSON
    cat > "$def_file" << EOF
{
  "name": "$assembly_name",
  "displayName": "$display_name",
  "organism": "$organism",
  "assemblyId": "$assembly_id",
  "aliases": $aliases_json,
  "defaultAccessLevel": "$access_level",
  "sequence": {
    "type": "ReferenceSequenceTrack",
    "trackId": "${assembly_name}-ReferenceSequenceTrack",
    "adapter": {
      "type": "IndexedFastaAdapter",
      "fastaLocation": {
        "uri": "$fasta_uri_base/$organism/$assembly_id/reference.fasta",
        "locationType": "UriLocation"
      },
      "faiLocation": {
        "uri": "$fasta_uri_base/$organism/$assembly_id/reference.fasta.fai",
        "locationType": "UriLocation"
      }
    }
  },
  "metadata": {
    "createdAt": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "source": "setup script",
    "description": "Automatically generated assembly definition"
  }
}
EOF

    if [ $? -ne 0 ]; then
        log_error "Failed to create assembly definition file"
        return 1
    fi

    log_success "Assembly definition created: $(basename $def_file)"
    log_info "File: $def_file"

    return 0
}

verify_definition() {
    local def_file="$1"
    
    log_info "Verifying assembly definition..."

    if [ ! -f "$def_file" ]; then
        log_error "Definition file not created: $def_file"
        return 1
    fi

    # Validate JSON
    if ! command -v jq &> /dev/null; then
        log_warn "jq not available - skipping JSON validation"
        return 0
    fi

    if ! jq . "$def_file" > /dev/null 2>&1; then
        log_error "Assembly definition is invalid JSON"
        return 1
    fi

    log_success "Definition is valid JSON"

    # Show summary
    local name=$(jq -r '.displayName' "$def_file")
    local aliases=$(jq -r '.aliases | join(", ")' "$def_file")
    log_success "Name: $name"
    log_success "Aliases: $aliases"

    return 0
}

print_summary() {
    local organism="$1"
    local assembly_id="$2"
    local display_name="$3"
    local def_file="$4"

    echo ""
    echo "════════════════════════════════════════════════════════════════"
    log_success "Assembly definition created!"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    echo "Assembly Information:"
    echo "  Organism: $organism"
    echo "  Assembly ID: $assembly_id"
    echo "  Display Name: $display_name"
    echo ""
    echo "Definition file saved to:"
    echo "  $def_file"
    echo ""
    echo "How this works:"
    echo "  1. The API (/api/jbrowse2/assembly.php) reads this definition"
    echo "  2. It generates permission-aware configs on-the-fly"
    echo "  3. Different users see different tracks based on access level"
    echo ""
    echo "Next steps:"
    echo "  1. Create track definition files in:"
    echo "     /data/moop/metadata/jbrowse2-configs/tracks/"
    echo ""
    echo "  2. Upload track data files to:"
    echo "     /data/moop/data/tracks/{organism}_{assembly}_{track_name}.{bw,bam,vcf}"
    echo ""
    echo "  3. Start web server:"
    echo "     cd /data/moop && php -S 127.0.0.1:8888 &"
    echo ""
    echo "  4. Test API:"
    echo "     curl -s 'http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=$organism&assembly=$assembly_id' | jq ."
    echo ""
    echo "  5. View in browser:"
    echo "     http://127.0.0.1:8888/jbrowse2/"
    echo ""
}

# ============================================================================
# MAIN
# ============================================================================

main() {
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "    Create Assembly Definition for JBrowse2"
    echo "════════════════════════════════════════════════════════════════"
    echo ""

    # Parse arguments
    local organism=""
    local assembly_id=""
    local display_name=""
    local access_level="Public"
    local fasta_uri_base="$FASTA_URI_BASE"
    local aliases=()

    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                print_usage
                exit 0
                ;;
            --display-name)
                display_name="$2"
                shift 2
                ;;
            --alias)
                aliases+=("$2")
                shift 2
                ;;
            --access-level)
                access_level="$2"
                shift 2
                ;;
            --fasta-uri-base)
                fasta_uri_base="$2"
                shift 2
                ;;
            *)
                if [ -z "$organism" ]; then
                    organism="$1"
                elif [ -z "$assembly_id" ]; then
                    assembly_id="$1"
                else
                    log_error "Unknown option: $1"
                    print_usage
                    exit 1
                fi
                shift
                ;;
        esac
    done

    # Validate arguments
    if [ -z "$organism" ] || [ -z "$assembly_id" ]; then
        log_error "Organism and assembly_id are required"
        print_usage
        exit 1
    fi

    # Check dependencies
    check_dependencies || exit 1
    echo ""

    # Validate inputs
    validate_inputs "$organism" "$assembly_id" || exit 1
    echo ""

    # Auto-detect genome_name if not provided via alias
    if [ ${#aliases[@]} -eq 0 ]; then
        local genome_name=$(get_genome_name_from_db "$organism")
        if [ $? -eq 0 ] && [ -n "$genome_name" ]; then
            log_success "Auto-detected genome_name: $genome_name"
            aliases+=("$genome_name")
            aliases+=("$assembly_id")
        else
            log_warn "Could not auto-detect genome_name, using assembly_id as alias"
            aliases+=("$assembly_id")
        fi
    fi

    # Use default display name if not provided
    if [ -z "$display_name" ]; then
        display_name="$organism ($assembly_id)"
    fi

    echo ""

    # Create assembly definition file
    local def_file="$METADATA_DIR/${organism}_${assembly_id}.json"
    register_assembly "$organism" "$assembly_id" "$display_name" "$access_level" "$fasta_uri_base" "${aliases[@]}" || exit 1
    echo ""

    # Verify definition
    verify_definition "$def_file" || exit 1
    echo ""

    # Print summary
    print_summary "$organism" "$assembly_id" "$display_name" "$def_file"

    return 0
}

main "$@"
exit $?
