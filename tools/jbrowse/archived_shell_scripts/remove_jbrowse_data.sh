#!/bin/bash
################################################################################
# Remove JBrowse2 Data - Tracks, Assemblies, or Organisms
#
# This script removes JBrowse2 metadata and optionally data files.
# It can remove individual tracks, entire assemblies, or whole organisms.
#
# Usage:
#   Remove single track:
#     ./remove_jbrowse_data.sh --track TRACK_ID --organism Org --assembly Asm
#
#   Remove assembly (all tracks):
#     ./remove_jbrowse_data.sh --assembly Asm --organism Org
#
#   Remove organism (all assemblies):
#     ./remove_jbrowse_data.sh --organism Org
#
# Options:
#   --dry-run       Show what would be removed without doing it
#   --remove-data   Also delete genome and track data files (default: keep)
#   --yes           Skip confirmation prompts
#
# Examples:
#   # Remove single track (metadata only)
#   ./remove_jbrowse_data.sh --track my_track_id --organism Nematostella_vectensis --assembly GCA_033964005.1
#
#   # Remove all Nematostella tracks and metadata (keep data files)
#   ./remove_jbrowse_data.sh --organism Nematostella_vectensis --assembly GCA_033964005.1
#
#   # Remove all Nematostella data completely (including data files)
#   ./remove_jbrowse_data.sh --organism Nematostella_vectensis --remove-data
#
#   # Clean up for fresh test (metadata only, keep data)
#   ./remove_jbrowse_data.sh --organism Nematostella_vectensis --assembly GCA_033964005.1 --yes
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

# Get MOOP root from site_config.php
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOOP_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Default values
DRY_RUN=0
REMOVE_DATA=0
YES=0
TRACK_ID=""
ORGANISM=""
ASSEMBLY=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --track) TRACK_ID="$2"; shift 2 ;;
        --organism) ORGANISM="$2"; shift 2 ;;
        --assembly) ASSEMBLY="$2"; shift 2 ;;
        --dry-run) DRY_RUN=1; shift ;;
        --remove-data) REMOVE_DATA=1; shift ;;
        --yes) YES=1; shift ;;
        --help|-h)
            head -n 35 "$0" | tail -n +2 | sed 's/^# \?//'
            exit 0
            ;;
        *) log_error "Unknown option: $1"; exit 1 ;;
    esac
done

# Validate input
if [ -z "$ORGANISM" ]; then
    log_error "Missing required: --organism"
    echo "Usage: $0 --organism ORGANISM [--assembly ASSEMBLY] [--track TRACK_ID] [options]"
    exit 1
fi

# Determine removal scope
if [ -n "$TRACK_ID" ]; then
    SCOPE="track"
    if [ -z "$ASSEMBLY" ]; then
        log_error "Removing a track requires --assembly"
        exit 1
    fi
elif [ -n "$ASSEMBLY" ]; then
    SCOPE="assembly"
else
    SCOPE="organism"
fi

# Display header
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "    Remove JBrowse2 Data"
echo "════════════════════════════════════════════════════════════════"
echo ""

if [ $DRY_RUN -eq 1 ]; then
    log_warn "DRY RUN MODE - No changes will be made"
    echo ""
fi

# Show what will be removed
case $SCOPE in
    track)
        echo "Scope: Remove single track"
        echo "  Track ID: $TRACK_ID"
        echo "  Organism: $ORGANISM"
        echo "  Assembly: $ASSEMBLY"
        ;;
    assembly)
        echo "Scope: Remove all tracks for assembly"
        echo "  Organism: $ORGANISM"
        echo "  Assembly: $ASSEMBLY"
        ;;
    organism)
        echo "Scope: Remove all assemblies for organism"
        echo "  Organism: $ORGANISM"
        ;;
esac

echo ""
echo "What will be removed:"
echo "────────────────────────────────────────────────────────────────"

# Build list of what will be removed
METADATA_TRACKS_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/tracks"
METADATA_ASSEMBLIES_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/assemblies"
CONFIGS_DIR="$MOOP_ROOT/jbrowse2/configs"
DATA_GENOMES_DIR="$MOOP_ROOT/data/genomes"
DATA_TRACKS_DIR="$MOOP_ROOT/data/tracks"

items_to_remove=()
data_items_to_remove=()

case $SCOPE in
    track)
        # Find the track JSON (currently flat, will be hierarchical later)
        TRACK_JSON="$METADATA_TRACKS_DIR/${TRACK_ID}.json"
        if [ -f "$TRACK_JSON" ]; then
            items_to_remove+=("$TRACK_JSON")
            echo "  - Track metadata: ${TRACK_ID}.json"
        else
            log_warn "Track not found: $TRACK_JSON"
        fi
        ;;
        
    assembly)
        # Track metadata for this assembly
        if [ -d "$METADATA_TRACKS_DIR/$ORGANISM/$ASSEMBLY" ]; then
            # Hierarchical structure
            for json in "$METADATA_TRACKS_DIR/$ORGANISM/$ASSEMBLY"/*/*.json; do
                [ -f "$json" ] || continue
                items_to_remove+=("$json")
                echo "  - Track: $(basename "$json")"
            done
        elif [ -d "$METADATA_TRACKS_DIR" ]; then
            # Fall back to flat structure
            for json in "$METADATA_TRACKS_DIR"/*.json; do
                [ -f "$json" ] || continue
                # Check if track belongs to this organism/assembly
                if grep -q "\"${ORGANISM}_${ASSEMBLY}\"" "$json" 2>/dev/null; then
                    items_to_remove+=("$json")
                    echo "  - Track: $(basename "$json")"
                fi
            done
        fi
        
        # Assembly metadata
        ASSEMBLY_JSON="$METADATA_ASSEMBLIES_DIR/${ORGANISM}_${ASSEMBLY}.json"
        if [ -f "$ASSEMBLY_JSON" ]; then
            items_to_remove+=("$ASSEMBLY_JSON")
            echo "  - Assembly metadata: ${ORGANISM}_${ASSEMBLY}.json"
        fi
        
        # Cached configs
        CONFIG_DIR="$CONFIGS_DIR/${ORGANISM}_${ASSEMBLY}"
        if [ -d "$CONFIG_DIR" ]; then
            items_to_remove+=("$CONFIG_DIR")
            echo "  - Cached configs: ${ORGANISM}_${ASSEMBLY}/"
        fi
        
        # Data files (if --remove-data)
        if [ $REMOVE_DATA -eq 1 ]; then
            GENOME_DIR="$DATA_GENOMES_DIR/${ORGANISM}/${ASSEMBLY}"
            TRACKS_DATA_DIR="$DATA_TRACKS_DIR/${ORGANISM}/${ASSEMBLY}"
            
            if [ -d "$GENOME_DIR" ]; then
                data_items_to_remove+=("$GENOME_DIR")
                echo "  - Genome data: ${ORGANISM}/${ASSEMBLY}/ [DATA FILES]"
            fi
            
            if [ -d "$TRACKS_DATA_DIR" ]; then
                data_items_to_remove+=("$TRACKS_DATA_DIR")
                echo "  - Track data: ${ORGANISM}/${ASSEMBLY}/ [DATA FILES]"
            fi
        fi
        ;;
        
    organism)
        # All assemblies for this organism
        ASSEMBLIES=()
        
        # Find all assembly metadata files
        if [ -d "$METADATA_ASSEMBLIES_DIR" ]; then
            for json in "$METADATA_ASSEMBLIES_DIR/${ORGANISM}_"*.json; do
                [ -f "$json" ] || continue
                ASSEMBLIES+=($(basename "$json" .json | sed "s/^${ORGANISM}_//"))
            done
        fi
        
        echo "  Found ${#ASSEMBLIES[@]} assemblies for $ORGANISM"
        
        for asm in "${ASSEMBLIES[@]}"; do
            echo ""
            echo "  Assembly: $asm"
            
            # Track metadata
            if [ -d "$METADATA_TRACKS_DIR" ]; then
                for json in "$METADATA_TRACKS_DIR"/*.json; do
                    [ -f "$json" ] || continue
                    if grep -q "\"${ORGANISM}_${asm}\"" "$json" 2>/dev/null; then
                        items_to_remove+=("$json")
                        echo "    - Track: $(basename "$json")"
                    fi
                done
            fi
            
            # Assembly metadata
            ASSEMBLY_JSON="$METADATA_ASSEMBLIES_DIR/${ORGANISM}_${asm}.json"
            if [ -f "$ASSEMBLY_JSON" ]; then
                items_to_remove+=("$ASSEMBLY_JSON")
                echo "    - Assembly metadata"
            fi
            
            # Cached configs
            CONFIG_DIR="$CONFIGS_DIR/${ORGANISM}_${asm}"
            if [ -d "$CONFIG_DIR" ]; then
                items_to_remove+=("$CONFIG_DIR")
                echo "    - Cached configs"
            fi
            
            # Data files (if --remove-data)
            if [ $REMOVE_DATA -eq 1 ]; then
                GENOME_DIR="$DATA_GENOMES_DIR/${ORGANISM}/${asm}"
                TRACKS_DATA_DIR="$DATA_TRACKS_DIR/${ORGANISM}/${asm}"
                
                if [ -d "$GENOME_DIR" ]; then
                    data_items_to_remove+=("$GENOME_DIR")
                    echo "    - Genome data [DATA FILES]"
                fi
                
                if [ -d "$TRACKS_DATA_DIR" ]; then
                    data_items_to_remove+=("$TRACKS_DATA_DIR")
                    echo "    - Track data [DATA FILES]"
                fi
            fi
        done
        ;;
esac

echo "────────────────────────────────────────────────────────────────"
echo ""

# Show summary
total_items=$((${#items_to_remove[@]} + ${#data_items_to_remove[@]}))

if [ $total_items -eq 0 ]; then
    log_warn "Nothing to remove - no matching items found"
    exit 0
fi

echo "Total items to remove: $total_items"
echo "  Metadata: ${#items_to_remove[@]} items"
if [ $REMOVE_DATA -eq 1 ]; then
    echo "  Data files: ${#data_items_to_remove[@]} directories [PERMANENT]"
else
    log_info "Data files will be preserved (use --remove-data to delete)"
fi
echo ""

# Confirmation
if [ $DRY_RUN -eq 0 ] && [ $YES -eq 0 ]; then
    log_warn "This will remove the items listed above"
    if [ $REMOVE_DATA -eq 1 ]; then
        echo ""
        log_error "⚠ WARNING: --remove-data specified - DATA FILES WILL BE DELETED"
        log_error "⚠ This includes genome files and track data files"
        log_error "⚠ THIS CANNOT BE UNDONE"
    fi
    echo ""
    read -p "Continue? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        log_info "Cancelled"
        exit 0
    fi
fi

# Perform removal
if [ $DRY_RUN -eq 0 ]; then
    echo ""
    log_info "Removing items..."
    
    removed_count=0
    
    # Remove metadata
    for item in "${items_to_remove[@]}"; do
        if [ -f "$item" ]; then
            rm -f "$item"
            log_success "Removed: $(basename "$item")"
            ((removed_count++))
        elif [ -d "$item" ]; then
            rm -rf "$item"
            log_success "Removed: $(basename "$item")/"
            ((removed_count++))
        fi
    done
    
    # Remove data files
    if [ $REMOVE_DATA -eq 1 ]; then
        for item in "${data_items_to_remove[@]}"; do
            if [ -d "$item" ]; then
                rm -rf "$item"
                log_success "Removed data: $(basename "$(dirname "$item")")/$(basename "$item")"
                ((removed_count++))
            fi
        done
    fi
    
    echo ""
    log_success "Removed $removed_count items"
else
    echo ""
    log_info "[DRY RUN] No changes made"
fi

echo ""
echo "════════════════════════════════════════════════════════════════"

if [ $DRY_RUN -eq 0 ]; then
    case $SCOPE in
        track)
            echo "✓ Track removed"
            ;;
        assembly)
            echo "✓ Assembly removed: ${ORGANISM}_${ASSEMBLY}"
            if [ $REMOVE_DATA -eq 0 ]; then
                echo ""
                echo "Data files preserved in:"
                echo "  - data/genomes/${ORGANISM}/${ASSEMBLY}/"
                echo "  - data/tracks/${ORGANISM}/${ASSEMBLY}/"
            fi
            echo ""
            echo "Next steps:"
            echo "  1. Run: php tools/jbrowse/generate-jbrowse-configs.php"
            echo "  2. Refresh JBrowse2 in browser"
            ;;
        organism)
            echo "✓ Organism removed: ${ORGANISM}"
            if [ $REMOVE_DATA -eq 0 ]; then
                echo ""
                echo "Data files preserved in:"
                echo "  - data/genomes/${ORGANISM}/"
                echo "  - data/tracks/${ORGANISM}/"
            fi
            echo ""
            echo "Next steps:"
            echo "  1. Run: php tools/jbrowse/generate-jbrowse-configs.php"
            echo "  2. Refresh JBrowse2 in browser"
            ;;
    esac
fi

echo "════════════════════════════════════════════════════════════════"
echo ""
