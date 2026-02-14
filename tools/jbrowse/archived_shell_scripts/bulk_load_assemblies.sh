#!/bin/bash

################################################################################
# Bulk Load Assemblies into JBrowse2
#
# Automates loading multiple genome assemblies for JBrowse2 from a manifest file
# or by auto-discovering organisms in a directory.
#
# Usage:
#   ./bulk_load_assemblies.sh <manifest_file> [OPTIONS]
#   ./bulk_load_assemblies.sh --auto-discover [OPTIONS]
#
# Examples:
#   # Load from manifest file
#   ./bulk_load_assemblies.sh /tmp/assemblies_to_load.txt
#
#   # Auto-discover and load all organisms
#   ./bulk_load_assemblies.sh --auto-discover --organisms /organisms
#
#   # Load and build JBrowse2
#   ./bulk_load_assemblies.sh /tmp/assemblies.txt --build
#
#   # Load, build, and test
#   ./bulk_load_assemblies.sh /tmp/assemblies.txt --build --test
#
################################################################################

set -e

# ============================================================================
# CONFIGURATION
# ============================================================================

MOOP_ROOT="/data/moop"
ORGANISMS_DIR="/organisms"
GENOMES_DIR="$MOOP_ROOT/data/genomes"
JBROWSE_DIR="$MOOP_ROOT/jbrowse2"
TOOLS_DIR="$MOOP_ROOT/tools/jbrowse"
SETUP_SCRIPT="$TOOLS_DIR/setup_jbrowse_assembly.sh"
ADD_SCRIPT="$TOOLS_DIR/add_assembly_to_jbrowse.sh"

# Options
DO_BUILD=0
DO_TEST=0
AUTO_DISCOVER=0
LOG_FILE="/tmp/jbrowse2_bulk_load_$(date +%s).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
TOTAL_ASSEMBLIES=0
PREPARED=0
REGISTERED=0
FAILED=0

# ============================================================================
# FUNCTIONS
# ============================================================================

log_info() {
    echo -e "${BLUE}ℹ${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}⚠${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}✗${NC} $1" | tee -a "$LOG_FILE"
}

print_usage() {
    cat << EOF
Bulk Load Assemblies into JBrowse2

USAGE:
  $(basename $0) <manifest_file> [OPTIONS]
  $(basename $0) --auto-discover [OPTIONS]

ARGUMENTS:
  manifest_file    File with one assembly path per line
                   Can include options on same line:
                   /organisms/MyOrg/MyAssembly --genome-file custom.fa

OPTIONS:
  --auto-discover           Auto-discover organisms in /organisms/
  --organisms PATH          Path to organisms directory (default: /organisms)
  --build                   Run npm build after loading all assemblies
  --test                    Run tests after build
  --log FILE                Log file (default: /tmp/jbrowse2_bulk_load_<timestamp>.log)
  --help                    Show this help message

MANIFEST FILE FORMAT:
  # Comments are allowed
  /organisms/Anoura_caudifer/GCA_004027475.1
  /organisms/Montipora_capitata/HIv3 --genome-file scaffold.fa --gff-file genes.gff
  /organisms/Bradypodion_pumilum/ASM356671v1

AUTO-DISCOVER:
  Finds all directories in /organisms/{organism}/{assembly}/ that contain
  organism.sqlite and genome files, then loads them.

EXAMPLES:
  # Load from manifest
  $(basename $0) /tmp/assemblies.txt

  # Load and build
  $(basename $0) /tmp/assemblies.txt --build

  # Load, build, and test
  $(basename $0) /tmp/assemblies.txt --build --test

  # Auto-discover from /organisms
  $(basename $0) --auto-discover

  # Auto-discover from custom location
  $(basename $0) --auto-discover --organisms /data/genomes

EOF
}

check_dependencies() {
    log_info "Checking dependencies..."

    local missing=0

    if [ ! -x "$SETUP_SCRIPT" ]; then
        log_error "Setup script not found or not executable: $SETUP_SCRIPT"
        missing=1
    else
        log_success "Setup script found"
    fi

    if [ ! -x "$ADD_SCRIPT" ]; then
        log_error "Add assembly script not found or not executable: $ADD_SCRIPT"
        missing=1
    else
        log_success "Add assembly script found"
    fi

    if ! command -v samtools &> /dev/null; then
        log_error "samtools not found"
        missing=1
    else
        log_success "samtools found"
    fi

    if ! command -v bgzip &> /dev/null; then
        log_error "bgzip not found"
        missing=1
    else
        log_success "bgzip found"
    fi

    if ! command -v tabix &> /dev/null; then
        log_error "tabix not found"
        missing=1
    else
        log_success "tabix found"
    fi

    if ! command -v jbrowse &> /dev/null; then
        log_error "jbrowse CLI not found"
        missing=1
    else
        log_success "jbrowse CLI found"
    fi

    if [ $missing -eq 1 ]; then
        return 1
    fi
    return 0
}

read_manifest() {
    local manifest_file="$1"

    log_info "Reading manifest file: $manifest_file"

    if [ ! -f "$manifest_file" ]; then
        log_error "Manifest file not found: $manifest_file"
        return 1
    fi

    # Read manifest and collect assembly paths with options
    local assemblies=()
    while IFS= read -r line; do
        # Skip empty lines and comments
        [[ -z "$line" || "$line" =~ ^# ]] && continue

        assemblies+=("$line")
        TOTAL_ASSEMBLIES=$((TOTAL_ASSEMBLIES + 1))
    done < "$manifest_file"

    log_success "Found $TOTAL_ASSEMBLIES assemblies in manifest"

    # Return assemblies as array (via global)
    MANIFEST_ENTRIES=("${assemblies[@]}")
}

auto_discover_organisms() {
    local org_dir="$1"

    log_info "Auto-discovering organisms in: $org_dir"

    if [ ! -d "$org_dir" ]; then
        log_error "Organisms directory not found: $org_dir"
        return 1
    fi

    local assemblies=()
    for organism_path in "$org_dir"/*/*/; do
        if [ ! -d "$organism_path" ]; then
            continue
        fi

        # Check if this looks like an organism/assembly path
        if [ ! -f "$organism_path/organism.sqlite" ]; then
            continue
        fi

        # Extract organism and assembly names
        local assembly=$(basename "$organism_path")
        local organism=$(basename $(dirname "$organism_path"))

        # Check for genome file (could be genome.fa or other name)
        if ls "$organism_path"/genome* > /dev/null 2>&1; then
            assemblies+=("$organism_path")
            TOTAL_ASSEMBLIES=$((TOTAL_ASSEMBLIES + 1))
        fi
    done

    if [ $TOTAL_ASSEMBLIES -eq 0 ]; then
        log_error "No assemblies found with organism.sqlite in $org_dir"
        return 1
    fi

    log_success "Found $TOTAL_ASSEMBLIES assemblies"

    MANIFEST_ENTRIES=("${assemblies[@]}")
}

load_assembly() {
    local entry="$1"
    local organism_path=""
    local setup_opts=""

    # Parse entry: path [--option value ...]
    read -r organism_path setup_opts <<< "$entry"

    if [ ! -d "$organism_path" ]; then
        log_error "Assembly path not found: $organism_path"
        FAILED=$((FAILED + 1))
        return 1
    fi

    # Extract organism and assembly from path
    local assembly=$(basename "$organism_path")
    local organism=$(basename $(dirname "$organism_path"))

    echo ""
    echo "────────────────────────────────────────────────────────────────"
    log_info "Loading: $organism / $assembly"
    echo "────────────────────────────────────────────────────────────────"

    # Phase 1: Prepare files
    log_info "Phase 1: Preparing files..."
    if "$SETUP_SCRIPT" "$organism_path" $setup_opts >> "$LOG_FILE" 2>&1; then
        log_success "Files prepared"
        PREPARED=$((PREPARED + 1))
    else
        log_error "File preparation failed"
        FAILED=$((FAILED + 1))
        return 1
    fi

    # Phase 2: Register in JBrowse2
    log_info "Phase 2: Registering in JBrowse2..."
    if "$ADD_SCRIPT" "$organism" "$assembly" >> "$LOG_FILE" 2>&1; then
        log_success "Registered in JBrowse2"
        REGISTERED=$((REGISTERED + 1))
    else
        log_error "JBrowse2 registration failed"
        FAILED=$((FAILED + 1))
        return 1
    fi

    return 0
}

build_jbrowse2() {
    log_info "Building JBrowse2..."

    if [ ! -d "$JBROWSE_DIR" ]; then
        log_error "JBrowse2 directory not found: $JBROWSE_DIR"
        return 1
    fi

    cd "$JBROWSE_DIR"
    if npm run build >> "$LOG_FILE" 2>&1; then
        log_success "JBrowse2 build complete"
        return 0
    else
        log_error "JBrowse2 build failed"
        return 1
    fi
}

test_api() {
    log_info "Testing API endpoints..."

    # Start server if not running
    if ! pgrep -f "php -S" > /dev/null; then
        log_info "Starting PHP server..."
        cd "$MOOP_ROOT"
        php -S 127.0.0.1:8888 > /tmp/php_server.log 2>&1 &
        sleep 2
    fi

    # Test each assembly
    local failed=0
    for entry in "${MANIFEST_ENTRIES[@]}"; do
        read -r organism_path _ <<< "$entry"
        local assembly=$(basename "$organism_path")
        local organism=$(basename $(dirname "$organism_path"))

        log_info "Testing: $organism/$assembly"

        local response=$(curl -s "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=$organism&assembly=$assembly&access_level=Public" 2>/dev/null)

        if echo "$response" | jq . > /dev/null 2>&1; then
            log_success "$organism/$assembly API OK"
        else
            log_error "$organism/$assembly API failed"
            failed=$((failed + 1))
        fi
    done

    if [ $failed -eq 0 ]; then
        log_success "All API tests passed"
        return 0
    else
        log_error "$failed API tests failed"
        return 1
    fi
}

print_summary() {
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    log_success "Bulk load complete!"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    echo "Summary:"
    echo "  Total assemblies: $TOTAL_ASSEMBLIES"
    echo "  Files prepared: $PREPARED"
    echo "  Registered: $REGISTERED"
    echo "  Failed: $FAILED"
    echo ""
    echo "Log file: $LOG_FILE"
    echo ""

    if [ $FAILED -eq 0 ]; then
        log_success "All assemblies loaded successfully!"
        echo ""
        echo "Next steps:"
        echo "  1. Start server:"
        echo "     cd $MOOP_ROOT && php -S 127.0.0.1:8888 &"
        echo ""
        echo "  2. View in browser:"
        echo "     http://127.0.0.1:8888/jbrowse2/"
        echo ""
    else
        log_error "Some assemblies failed to load. Check log for details."
    fi
}

# ============================================================================
# MAIN
# ============================================================================

main() {
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "    Bulk Load Assemblies into JBrowse2"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    echo "Log file: $LOG_FILE"
    echo ""

    # Parse arguments
    local manifest_file=""

    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                print_usage
                exit 0
                ;;
            --auto-discover)
                AUTO_DISCOVER=1
                shift
                ;;
            --organisms)
                ORGANISMS_DIR="$2"
                shift 2
                ;;
            --build)
                DO_BUILD=1
                shift
                ;;
            --test)
                DO_TEST=1
                shift
                ;;
            --log)
                LOG_FILE="$2"
                shift 2
                ;;
            *)
                if [ -z "$manifest_file" ]; then
                    manifest_file="$1"
                    shift
                else
                    log_error "Unknown option: $1"
                    print_usage
                    exit 1
                fi
                ;;
        esac
    done

    # Check dependencies
    check_dependencies || exit 1
    echo ""

    # Read assemblies
    if [ $AUTO_DISCOVER -eq 1 ]; then
        auto_discover_organisms "$ORGANISMS_DIR" || exit 1
    else
        if [ -z "$manifest_file" ]; then
            log_error "Either manifest_file or --auto-discover required"
            print_usage
            exit 1
        fi
        read_manifest "$manifest_file" || exit 1
    fi
    echo ""

    # Load each assembly
    for entry in "${MANIFEST_ENTRIES[@]}"; do
        load_assembly "$entry"
    done

    # Build if requested
    if [ $DO_BUILD -eq 1 ]; then
        echo ""
        build_jbrowse2 || log_warn "Build failed - continuing anyway"
    fi

    # Test if requested
    if [ $DO_TEST -eq 1 ]; then
        echo ""
        test_api || log_warn "Some tests failed"
    fi

    # Print summary
    print_summary

    if [ $FAILED -eq 0 ]; then
        return 0
    else
        return 1
    fi
}

main "$@"
exit $?
