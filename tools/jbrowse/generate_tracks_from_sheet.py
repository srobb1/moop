#!/usr/bin/env python3
"""
Google Sheets to JBrowse2 Track Generator

This script reads track metadata from a Google Sheet and automatically generates
JBrowse2 track configurations.

Features:
- Auto-detects track types from file extensions and categories
- Supports multi-BigWig tracks (combo tracks)
- Color grouping system (blues, reds, purples, etc.)
- Access level control (PUBLIC, COLLABORATOR, ADMIN)
- Checks for existing tracks to avoid duplicates

Usage:
    python3 generate_tracks_from_sheet.py <sheet_url_or_id> [options]

Example:
    python3 generate_tracks_from_sheet.py \
        "1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo" \
        --gid 1977809640 \
        --organism Nematostella_vectensis \
        --assembly GCA_033964005.1

Required Columns in Google Sheet:
    - track_id: [REQUIRED] Unique track identifier (used as trackId in JBrowse2)
    - name: [REQUIRED] Display name (shown in JBrowse2 UI)
    - category: [REQUIRED] Track category (purely descriptive/organizational)
    - TRACK_PATH: [REQUIRED] File path or URL to track file
    
TRACK_PATH Format:
    - Absolute path: /data/moop/data/tracks/sample.bw
    - Relative path: data/tracks/sample.bw (prepends MOOP_ROOT)
    - HTTP URL: http://server.edu/tracks/sample.bw (used as-is)
    - HTTPS URL: https://server.edu/tracks/sample.bw (used as-is)
    - AUTO (for reference/annotations): Script auto-resolves to:
      * Reference: /data/moop/data/genomes/{organism}/{assembly}/reference.fasta
      * Annotations: /data/moop/data/genomes/{organism}/{assembly}/annotations.gff3.gz
    
Optional Columns:
    - access_level: PUBLIC, COLLABORATOR, or ADMIN (default: PUBLIC)
    - description: Track description
    - technique: Technique used (e.g., RNA-seq, ChIP-seq)
    - condition: Experimental condition
    - tissue: Tissue/organ type
    - #any_column: Columns starting with # are ignored
    - ...any other columns for your own metadata

Synteny Track Columns (in addition to above):
    - ASSEMBLY1: [REQUIRED] First assembly name (target genome)
    - ASSEMBLY2: [REQUIRED] Second assembly name (query genome)
    - BED1_PATH: [Required for MCScan] BED file for assembly 1
    - BED2_PATH: [Required for MCScan] BED file for assembly 2

Track Types (auto-detected from file extension):
    - .bw, .bigwig → BigWig quantitative signal track
    - .bam → BAM alignment track (requires .bai index)
    - .cram → CRAM alignment track (requires .crai index)
    - .vcf.gz → VCF variant track (requires .tbi index)
    - .gff, .gff3, .gff.gz → GFF gene annotation track
    - .gtf → GTF gene annotation track (Ensembl format)
    - .bed.gz → BED feature track (requires .tbi index)
    - .paf → PAF long-read alignment track
    - .maf, .maf.gz → MAF multiple alignment (requires jbrowse-plugin-mafviewer)
    - .pif.gz → Whole genome synteny (requires ASSEMBLY1/ASSEMBLY2)
    - .anchors → MCScan ortholog synteny (requires ASSEMBLY1/ASSEMBLY2/BED files)

Optional Combo Track Format:
    # Combo Track Name
    ## colorgroup: Group Name
    label	key	technique	category	filename
    ## colorgroup: Group Name 2
    label	key	technique	category	filename
    ### end
"""

import sys
import argparse
import csv
import re
import json
import os
import subprocess
import gzip
from datetime import datetime
from urllib.request import urlopen
from pathlib import Path

# Color definitions - expanded with more groups and better coverage
COLORS = {
    # Original groups (preserved)
    'blues': ['Navy', 'Blue', 'RoyalBlue', 'SteelBlue', 'DodgerBlue', 'DeepSkyBlue', 
              'CornflowerBlue', 'SkyBlue', 'LightSkyBlue', 'LightSteelBlue', 'LightBlue'],
    'purples': ['Indigo', 'Purple', 'DarkViolet', 'DarkSlateBlue', 'DarkOrchid', 
                'Fuchsia', 'SlateBlue', 'MediumSlateBlue', 'MediumOrchid', 
                'MediumPurple', 'Orchid', 'Plum', 'Thistle', 'Lavender'],
    'yellows': ['DarkKhaki', 'Gold', 'Khaki', 'PeachPuff', 'Yellow', 'PaleGoldenrod', 
                'Moccasin', 'PapayaWhip', 'LightGoldenrodYellow', 'LemonChiffon', 'LightYellow'],
    'cyans': ['Teal', 'LightSeaGreen', 'CadetBlue', 'DarkTurquoise', 'Turquoise', 
              'Aqua', 'Aquamarine', 'PaleTurquoise', 'LightCyan'],
    'pinks': ['MediumVioletRed', 'DeepPink', 'PaleVioletRed', 'HotPink', 'LightPink', 'Pink'],
    'greens': ['DarkGreen', 'DarkOliveGreen', 'ForestGreen', 'SeaGreen', 'Olive', 
               'OliveDrab', 'MediumSeaGreen', 'LimeGreen', 'Lime', 'MediumSpringGreen', 
               'DarkSeaGreen', 'MediumAquamarine', 'YellowGreen', 'LawnGreen', 
               'LightGreen', 'GreenYellow'],
    'reds': ['DarkRed', 'Red', 'Firebrick', 'Crimson', 'IndianRed', 'LightCoral', 
             'Salmon', 'DarkSalmon', 'LightSalmon'],
    'oranges': ['OrangeRed', 'Tomato', 'DarkOrange', 'Coral', 'Orange'],
    'browns': ['Maroon', 'Brown', 'SaddleBrown', 'Sienna', 'Chocolate', 'DarkGoldenrod', 
               'Peru', 'RosyBrown', 'Goldenrod', 'SandyBrown', 'Tan', 'Burlywood', 
               'Wheat', 'NavajoWhite', 'Bisque', 'BlanchedAlmond', 'Cornsilk'],
    'grays': ['Gainsboro', 'LightGray', 'Silver', 'DarkGray', 'Gray', 'DimGray', 
              'LightSlateGray', 'SlateGray', 'DarkSlateGray'],
    'diffs': ['Turquoise', 'Coral', 'MediumVioletRed', 'Red', 'Gold', 'Sienna', 
              'SeaGreen', 'SkyBlue', 'BlueViolet', 'MistyRose', 'LightSlateGray'],
    
    # New expanded groups for better coverage
    'rainbow': ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', 
                '#46f0f0', '#f032e6', '#bcf60c', '#fabebe', '#008080', '#e6beff', 
                '#9a6324', '#fffac8', '#800000', '#aaffc3', '#808000', '#ffd8b1', 
                '#000075', '#808080'],  # 20 distinct colors
    
    'warm': ['#8B0000', '#DC143C', '#FF0000', '#FF4500', '#FF6347', '#FF7F50', 
             '#FFA500', '#FFD700', '#FFFF00', '#ADFF2F', '#7FFF00', '#00FF00'],  # 12 warm colors
    
    'cool': ['#00008B', '#0000CD', '#0000FF', '#1E90FF', '#00BFFF', '#00CED1', 
             '#00FFFF', '#00FA9A', '#00FF7F', '#3CB371', '#2E8B57', '#006400'],  # 12 cool colors
    
    'earth': ['#8B4513', '#A0522D', '#D2691E', '#CD853F', '#DEB887', '#F4A460', 
              '#D2B48C', '#BC8F8F', '#F5DEB3', '#FFE4C4', '#FFDEAD', '#FFE4B5', 
              '#FAEBD7', '#FAF0E6', '#FFF8DC', '#FFFACD'],  # 16 earthy tones
    
    'pastels': ['#FFB3BA', '#FFDFBA', '#FFFFBA', '#BAFFC9', '#BAE1FF', '#E0BBE4', 
                '#FFDFD3', '#FEC8D8', '#D5AAFF', '#B4F8C8', '#A0C4FF', '#FDFFB6', 
                '#FFD6A5', '#CAFFBF', '#FFC6FF', '#FFFFFC'],  # 16 pastels
    
    'vibrant': ['#FF006E', '#FB5607', '#FFBE0B', '#8338EC', '#3A86FF', '#06FFA5', 
                '#FF1654', '#247BA0', '#F72585', '#4361EE', '#4CC9F0', '#7209B7', 
                '#F77F00', '#06D6A0', '#EF476F', '#FFD166'],  # 16 vibrant colors
    
    'monoblues': ['#03045E', '#023E8A', '#0077B6', '#0096C7', '#00B4D8', '#48CAE4', 
                  '#90E0EF', '#ADE8F4', '#CAF0F8'],  # 9 shades of blue
    
    'monogreens': ['#004B23', '#006400', '#007200', '#008000', '#38B000', '#70E000', 
                   '#9EF01A', '#CCFF33'],  # 8 shades of green
    
    'monoreds': ['#641220', '#6E1423', '#85182A', '#A11D33', '#A71E34', '#C9184A', 
                 '#FF4D6D', '#FF758F', '#FF8FA3'],  # 9 shades of red
    
    'monopurples': ['#240046', '#3C096C', '#5A189A', '#7209B7', '#9D4EDD', '#C77DFF', 
                    '#E0AAFF', '#F0D9FF'],  # 8 shades of purple
    
    'neon': ['#FF10F0', '#39FF14', '#FFFF00', '#FF3503', '#00F5FF', '#FE019A', 
             '#BC13FE', '#FF073A', '#FF6600', '#00FFFF', '#FF00FF', '#CCFF00'],  # 12 neon colors
    
    'sea': ['#001F3F', '#003D5C', '#005A7A', '#007899', '#0095B7', '#00B3D5', 
            '#33C1E3', '#66CFF0', '#99DDFC', '#CCEBFF'],  # 10 ocean blues
    
    'forest': ['#013220', '#2D6A4F', '#40916C', '#52B788', '#74C69D', '#95D5B2', 
               '#B7E4C7', '#D8F3DC'],  # 8 forest greens
    
    'sunset': ['#03071E', '#370617', '#6A040F', '#9D0208', '#D00000', '#DC2F02', 
               '#E85D04', '#F48C06', '#FAA307', '#FFBA08'],  # 10 sunset colors
    
    'galaxy': ['#0B0C10', '#1F2833', '#45A29E', '#66FCF1', '#C5C6C7', '#7B2CBF', 
               '#9D4EDD', '#C77DFF', '#E0AAFF'],  # 9 space colors
    
    'contrast': ['#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', 
                 '#FF00FF', '#00FFFF'],  # 8 maximum contrast
    
    'grayscale': ['#000000', '#1A1A1A', '#333333', '#4D4D4D', '#666666', '#808080', 
                  '#999999', '#B3B3B3', '#CCCCCC', '#E6E6E6', '#F2F2F2', '#FFFFFF'],  # 12 grays
}

# Color group metadata for suggestions
COLOR_GROUP_INFO = {
    'blues': {'count': 11, 'best_for': 'samples, replicates', 'type': 'sequential'},
    'purples': {'count': 14, 'best_for': 'larger groups, time series', 'type': 'sequential'},
    'yellows': {'count': 11, 'best_for': 'expression data', 'type': 'sequential'},
    'cyans': {'count': 9, 'best_for': 'water/aquatic samples', 'type': 'sequential'},
    'pinks': {'count': 6, 'best_for': 'small groups', 'type': 'sequential'},
    'greens': {'count': 16, 'best_for': 'large groups, plant data', 'type': 'sequential'},
    'reds': {'count': 9, 'best_for': 'treatments, stress', 'type': 'sequential'},
    'oranges': {'count': 5, 'best_for': 'small groups', 'type': 'sequential'},
    'browns': {'count': 17, 'best_for': 'large groups, earthy themes', 'type': 'sequential'},
    'grays': {'count': 9, 'best_for': 'controls, baselines', 'type': 'sequential'},
    'diffs': {'count': 11, 'best_for': 'distinct samples', 'type': 'qualitative'},
    'rainbow': {'count': 20, 'best_for': 'maximum variety', 'type': 'qualitative'},
    'warm': {'count': 12, 'best_for': 'upregulated/active', 'type': 'sequential'},
    'cool': {'count': 12, 'best_for': 'downregulated/inactive', 'type': 'sequential'},
    'earth': {'count': 16, 'best_for': 'natural/soil samples', 'type': 'sequential'},
    'pastels': {'count': 16, 'best_for': 'subtle differences', 'type': 'qualitative'},
    'vibrant': {'count': 16, 'best_for': 'presentations, posters', 'type': 'qualitative'},
    'monoblues': {'count': 9, 'best_for': 'intensity gradients', 'type': 'sequential'},
    'monogreens': {'count': 8, 'best_for': 'growth/abundance', 'type': 'sequential'},
    'monoreds': {'count': 9, 'best_for': 'severity/danger', 'type': 'sequential'},
    'monopurples': {'count': 8, 'best_for': 'epigenetic data', 'type': 'sequential'},
    'neon': {'count': 12, 'best_for': 'high contrast needs', 'type': 'qualitative'},
    'sea': {'count': 10, 'best_for': 'marine organisms', 'type': 'sequential'},
    'forest': {'count': 8, 'best_for': 'vegetation data', 'type': 'sequential'},
    'sunset': {'count': 10, 'best_for': 'time progression', 'type': 'sequential'},
    'galaxy': {'count': 9, 'best_for': 'dark backgrounds', 'type': 'sequential'},
    'contrast': {'count': 8, 'best_for': 'accessibility', 'type': 'qualitative'},
    'grayscale': {'count': 12, 'best_for': 'black & white', 'type': 'sequential'},
}


def get_color(color_group, index):
    """Get color from group at index, with smart error handling"""
    # Handle exact color: exact=ColorName
    if color_group.startswith('exact='):
        return color_group.replace('exact=', '')
    
    # Handle specific color from group: blues3
    match = re.match(r'([a-z]+)(\d+)', color_group)
    if match:
        group = match.group(1)
        color_index = int(match.group(2))
        if group in COLORS:
            if color_index < len(COLORS[group]):
                return COLORS[group][color_index]
            else:
                print(f"⚠ Color index {color_index} out of range for '{group}' (max: {len(COLORS[group])-1})")
                return 'Brown'
        else:
            print(f"⚠ Unknown color group: '{group}'")
            return 'Black'
    
    # Handle color group: blues, reds, etc.
    if color_group in COLORS:
        if index < len(COLORS[color_group]):
            return COLORS[color_group][index]
        else:
            # Suggest better groups
            return handle_color_overflow(color_group, index)
    
    print(f"⚠ Unknown color specification: '{color_group}' - using Black")
    return 'Black'


def handle_color_overflow(color_group, needed_index):
    """Handle when color group has too few colors, suggest alternatives"""
    needed_count = needed_index + 1
    available_count = len(COLORS[color_group])
    
    print(f"\n{'='*70}")
    print(f"⚠ COLOR GROUP TOO SMALL")
    print(f"{'='*70}")
    print(f"Group '{color_group}' only has {available_count} colors")
    print(f"but you need at least {needed_count} colors for this track group.")
    print()
    
    # Find suitable alternatives
    suitable_groups = []
    for group_name, colors in COLORS.items():
        if len(colors) >= needed_count:
            info = COLOR_GROUP_INFO.get(group_name, {})
            suitable_groups.append({
                'name': group_name,
                'count': len(colors),
                'type': info.get('type', 'unknown'),
                'best_for': info.get('best_for', 'general use')
            })
    
    # Sort by color count (descending) and then by name
    suitable_groups.sort(key=lambda x: (-x['count'], x['name']))
    
    print("✓ SUGGESTED COLOR GROUPS:")
    print("-" * 70)
    print(f"{'Group':<15} {'Colors':<8} {'Type':<12} {'Best For'}")
    print("-" * 70)
    
    # Show top 10 suggestions
    for group in suitable_groups[:10]:
        print(f"{group['name']:<15} {group['count']:<8} {group['type']:<12} {group['best_for']}")
    
    if len(suitable_groups) > 10:
        print(f"\n... and {len(suitable_groups) - 10} more options")
    
    print()
    print("USAGE:")
    best = suitable_groups[0]['name'] if suitable_groups else 'rainbow'
    print(f"  ## {best}: Your Group Name")
    print(f"{'='*70}\n")
    
    # Return a fallback color
    return 'Brown'


def suggest_color_groups(num_files):
    """Suggest appropriate color groups for number of files"""
    suitable = []
    for group_name, colors in COLORS.items():
        if len(colors) >= num_files:
            info = COLOR_GROUP_INFO.get(group_name, {})
            suitable.append({
                'name': group_name,
                'count': len(colors),
                'type': info.get('type', 'unknown'),
                'best_for': info.get('best_for', 'general use')
            })
    
    suitable.sort(key=lambda x: (abs(x['count'] - num_files), x['name']))
    return suitable[:5]  # Top 5 suggestions


def parse_maf_samples(maf_path):
    """
    Parse MAF file to extract all unique sample/assembly IDs.
    
    Args:
        maf_path: Path to MAF file (.maf or .maf.gz)
        
    Returns:
        set: Set of sample IDs found in MAF file, or None if error
    """
    samples = set()
    
    try:
        # Handle both .gz and uncompressed files
        if maf_path.endswith('.gz'):
            open_func = gzip.open
            mode = 'rt'
        else:
            open_func = open
            mode = 'r'
        
        with open_func(maf_path, mode) as f:
            for line in f:
                line = line.strip()
                # MAF alignment lines start with 's'
                # Format: s species.chrom start size strand total_size sequence
                if line.startswith('s '):
                    parts = line.split()
                    if len(parts) >= 2:
                        # Extract species name (before the first dot)
                        full_id = parts[1]
                        species = full_id.split('.')[0]
                        samples.add(species)
        
        return samples if samples else None
        
    except Exception as e:
        print(f"  ⚠ Error parsing MAF file: {e}")
        return None


def download_sheet_as_tsv(sheet_id, gid='0'):
    """Download Google Sheet as TSV"""
    url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/export?format=tsv&gid={gid}"
    print(f"Downloading sheet from: {url}")
    
    try:
        response = urlopen(url)
        content = response.read().decode('utf-8')
        return content
    except Exception as e:
        print(f"Error downloading sheet: {e}")
        sys.exit(1)


def parse_sheet(tsv_content):
    """Parse TSV content into tracks and combo tracks"""
    lines = tsv_content.strip().split('\n')
    
    # Get header and filter out columns starting with #
    header_line = lines[0]
    all_columns = header_line.split('\t')
    
    # Keep only columns that don't start with #
    valid_columns = [col for col in all_columns if not col.startswith('#')]
    valid_column_indices = [i for i, col in enumerate(all_columns) if not col.startswith('#')]
    
    # Create filtered reader
    filtered_lines = []
    for line in lines:
        cells = line.split('\t')
        filtered_cells = [cells[i] if i < len(cells) else '' for i in valid_column_indices]
        filtered_lines.append('\t'.join(filtered_cells))
    
    reader = csv.DictReader(filtered_lines, delimiter='\t')
    
    regular_tracks = []
    combo_tracks = {}
    
    in_combo = False
    combo_name = None
    combo_group = None
    combo_color = None
    
    for row in reader:
        # Skip completely empty rows
        if not any(row.values()):
            continue
            
        # Check if this is a combo track marker or comment line
        if not row.get('track_id') or row['track_id'].startswith('#'):
            # Try to reconstruct original line for combo markers
            line = '\t'.join(row.values()) if row else ''
            
            # Check for combo track markers
            if line.startswith('###'):
                in_combo = False
                combo_name = None
                continue
            elif line.startswith('# ') and not line.startswith('## '):
                combo_name = line[2:].strip()
                in_combo = True
                combo_tracks[combo_name] = {'groups': {}, 'tracks': []}
                continue
            elif line.startswith('## '):
                # Parse color group: ## blues: Group Name
                match = re.match(r'##\s*(\S+):\s*(.+)', line)
                if match:
                    combo_color = match.group(1)
                    combo_group = match.group(2).strip()
                    if combo_name:
                        combo_tracks[combo_name]['groups'][combo_group] = {
                            'color': combo_color,
                            'tracks': []
                        }
                continue
        
        # Skip rows missing required fields
        if not row.get('track_id') or not row.get('name') or not row.get('TRACK_PATH'):
            continue
        
        # Process data rows
        if in_combo and combo_name and combo_group:
            # Add to combo track
            combo_tracks[combo_name]['groups'][combo_group]['tracks'].append(row)
            # ALSO add to regular tracks so individual tracks get created
            regular_tracks.append(row)
        elif not in_combo:
            regular_tracks.append(row)
    
    return regular_tracks, combo_tracks


def determine_track_type(row):
    """
    Determine track type from file extension only.
    
    Track type drives which bash script (add_bigwig_track.sh, add_bam_track.sh, etc.)
    will be called to create the track metadata.
    
    Args:
        row: Dictionary with track data, must contain 'TRACK_PATH' key
        
    Returns:
        str: Track type, 'auto' for AUTO keyword, or None if unknown
        
    Supported types:
        'bigwig', 'bam', 'cram', 'vcf', 'gff', 'gtf', 'bed', 'paf', 
        'synteny_pif', 'synteny_mcscan', 'fasta', 'auto'
        
    Extension Points:
        To add new track types:
        1. Add new elif branch with extensions
        2. Return unique type string
        3. Add handling in generate_single_track() or generate_synteny_track()
        4. Create corresponding bash script (e.g., add_XXX_track.sh)
    """
    track_path = row.get('TRACK_PATH', '')
    category = row.get('category', '').lower()
    
    # Handle AUTO keyword - these are handled by assembly setup scripts
    # Reference genome and annotations are automatically configured
    if track_path.upper() == 'AUTO':
        return 'auto'
    
    # Check for synteny tracks (require two assemblies)
    if row.get('ASSEMBLY1') and row.get('ASSEMBLY2'):
        # PIF.GZ: Pairwise Indexed PAF for whole genome synteny
        if track_path.endswith('.pif.gz'):
            return 'synteny_pif'
        # Anchors: MCScan ortholog pairs
        elif track_path.endswith('.anchors'):
            return 'synteny_mcscan'
    
    # Extract file extension (handles .gz, .bw, etc.)
    ext = Path(track_path).suffix.lower()
    
    # BigWig: Quantitative signal tracks (RNA-seq, ChIP-seq coverage)
    if ext in ['.bw', '.bigwig']:
        return 'bigwig'
    
    # BAM: Binary alignment files (mapped reads)
    elif ext in ['.bam']:
        return 'bam'
    
    # CRAM: Compressed alignment files (more efficient than BAM)
    elif ext in ['.cram']:
        return 'cram'
    
    # PAF: Pairwise mApping Format (minimap2 long-read alignments)
    elif ext in ['.paf']:
        return 'paf'
    
    # GTF: Gene annotations (Ensembl format)
    elif ext in ['.gtf']:
        return 'gtf'
    
    # Compressed files: Need to check filename for type
    elif ext in ['.gz']:
        # VCF: Variant calls (SNPs, indels)
        if track_path.endswith('.vcf.gz'):
            return 'vcf'
        # GFF: Gene annotations (can be gzipped)
        elif track_path.endswith('.gff.gz') or track_path.endswith('.gff3.gz'):
            return 'gff'
        # BED: Generic genomic features (must be bgzipped and tabix-indexed)
        elif track_path.endswith('.bed.gz'):
            return 'bed'
        # MAF: Multiple alignment format (requires plugin)
        elif track_path.endswith('.maf.gz'):
            return 'maf'
        # PIF.GZ handled above in synteny section
        return None
    
    # MAF: Multiple alignment format (uncompressed)
    elif ext in ['.maf']:
        return 'maf'
    
    # GFF/GFF3: Gene annotations (uncompressed)
    elif ext in ['.gff', '.gff3']:
        return 'gff'
    
    # BED: Generic genomic features (uncompressed - less common)
    elif ext in ['.bed']:
        # Note: JBrowse2 prefers bgzipped BED with tabix index
        return 'bed'
    
    # FASTA: Reference sequences
    elif ext in ['.fa', '.fasta', '.fna']:
        return 'fasta'
    
    return None


def assembly_exists(organism, assembly, moop_root):
    """Check if assembly is already configured in JBrowse2"""
    metadata_dir = Path(moop_root) / 'metadata' / 'jbrowse2-configs' / 'assemblies'
    assembly_file = metadata_dir / f"{organism}_{assembly}.json"
    return assembly_file.exists()


def setup_assembly(organism, assembly, moop_root, dry_run=False):
    """
    Setup assembly if it doesn't exist yet.
    Runs setup_jbrowse_assembly.sh and add_assembly_to_jbrowse.sh
    
    Returns:
        bool: True if setup successful or already exists, False on error
    """
    print("=" * 70)
    print("ASSEMBLY SETUP")
    print("=" * 70)
    
    # Check if already configured
    if assembly_exists(organism, assembly, moop_root):
        print(f"✓ Assembly already configured: {organism}/{assembly}")
        print()
        return True
    
    print(f"⚠ Assembly not found: {organism}/{assembly}")
    print(f"→ Running assembly setup scripts...")
    print()
    
    if dry_run:
        print("[DRY RUN] Would run:")
        print(f"  1. setup_jbrowse_assembly.sh /data/moop/organisms/{organism}/{assembly}")
        print(f"  2. add_assembly_to_jbrowse.sh {organism} {assembly}")
        print()
        return True
    
    # Paths
    organisms_path = Path("/data/moop/organisms") / organism / assembly
    tools_dir = Path(moop_root) / "tools" / "jbrowse"
    setup_script = tools_dir / "setup_jbrowse_assembly.sh"
    add_script = tools_dir / "add_assembly_to_jbrowse.sh"
    
    # Check if organism directory exists
    if not organisms_path.exists():
        print(f"✗ Organism directory not found: {organisms_path}")
        print(f"  Please ensure genome files are in place:")
        print(f"  - {organisms_path}/genome.fa")
        print(f"  - {organisms_path}/genomic.gff (optional)")
        print()
        return False
    
    # Phase 1: Setup genome files
    print("Phase 1: Preparing genome files...")
    try:
        result = subprocess.run(
            [str(setup_script), str(organisms_path)],
            check=True,
            capture_output=True,
            text=True
        )
        print(result.stdout)
        print("✓ Genome files prepared")
    except subprocess.CalledProcessError as e:
        print(f"✗ Failed to setup genome files:")
        print(e.stderr)
        return False
    
    # Phase 2: Register assembly
    print()
    print("Phase 2: Registering assembly in JBrowse2...")
    try:
        result = subprocess.run(
            [str(add_script), organism, assembly, "--access-level", "PUBLIC"],
            check=True,
            capture_output=True,
            text=True
        )
        print(result.stdout)
        print("✓ Assembly registered")
    except subprocess.CalledProcessError as e:
        print(f"✗ Failed to register assembly:")
        print(e.stderr)
        return False
    
    print()
    print("✓ Assembly setup complete!")
    print("=" * 70)
    print()
    return True


def track_exists(track_id, metadata_dir):
    """Check if track already exists"""
    track_file = Path(metadata_dir) / f"{track_id}.json"
    return track_file.exists()


def is_remote_track(track_path):
    """Check if track is remote (HTTP/HTTPS URL)"""
    return track_path.startswith('http://') or track_path.startswith('https://')


def resolve_track_path(track_path, moop_root, organism=None, assembly=None, track_type=None):
    """
    Resolve track path to absolute path or URL.
    
    Args:
        track_path: Track path from sheet (can be AUTO, URL, or absolute path)
        moop_root: MOOP root directory
        organism: Organism name (needed for AUTO resolution)
        assembly: Assembly name (needed for AUTO resolution)
        track_type: Track type (needed for AUTO resolution)
        
    Returns:
        Tuple of (resolved_path, is_remote)
        
    Note:
        - AUTO: Only for reference genome (fasta) and annotations (gff)
        - All other tracks MUST be absolute paths or URLs
        - No file copying - tracks are used in place
    """
    # Handle AUTO keyword - auto-resolve reference and annotation paths
    if track_path.upper() == 'AUTO':
        if not organism or not assembly:
            raise ValueError("AUTO requires --organism and --assembly to be specified")
        
        if track_type == 'fasta':
            # Reference genome
            auto_path = f"/data/moop/data/genomes/{organism}/{assembly}/reference.fasta"
            return auto_path, False
        elif track_type == 'gff':
            # Annotations
            auto_path = f"/data/moop/data/genomes/{organism}/{assembly}/annotations.gff3.gz"
            return auto_path, False
        else:
            raise ValueError(f"AUTO only supported for fasta and gff tracks, not {track_type}")
    
    # Remote URL - use as-is
    if is_remote_track(track_path):
        return track_path, True
    
    # Absolute path - use as-is
    elif track_path.startswith('/'):
        return track_path, False
    
    # Relative path - ERROR (all track paths must be absolute)
    else:
        raise ValueError(f"Track path must be absolute or URL, got relative path: {track_path}\n"
                        f"  Please specify full path like: /data/moop/data/tracks/{organism}/{assembly}/bigwig/{track_path}")


def verify_track_exists(track_path, is_remote):
    """Verify track file exists (for local files only)"""
    if is_remote:
        # For remote tracks, we can't easily verify without making HTTP request
        # Just return True and let JBrowse2 handle 404s
        return True
    else:
        # For local files, check existence
        return Path(track_path).exists()


def clean_orphaned_tracks(organism, assembly, track_ids_in_sheet, moop_root, dry_run=False):
    """
    Remove track JSON files that are not in the Google Sheet.
    
    Args:
        organism: Organism name
        assembly: Assembly ID  
        track_ids_in_sheet: Set of track IDs from Google Sheet
        moop_root: MOOP root directory
        dry_run: If True, only show what would be removed
        
    Returns:
        Number of tracks removed
    """
    metadata_dir = Path(moop_root) / 'metadata' / 'jbrowse2-configs' / 'tracks'
    
    if not metadata_dir.exists():
        return 0
    
    # Pattern to match: tracks containing organism and assembly in their metadata
    # We need to read each JSON to check if it belongs to this organism/assembly
    removed_count = 0
    checked_count = 0
    
    print()
    print("=" * 70)
    print(f"Checking for orphaned tracks: {organism} / {assembly}")
    print("=" * 70)
    
    for json_file in metadata_dir.glob('*.json'):
        checked_count += 1
        try:
            with open(json_file) as f:
                metadata = json.load(f)
                
            # Check if this track belongs to our organism/assembly
            assembly_names = metadata.get('assemblyNames', [])
            expected_assembly = f"{organism}_{assembly}"
            
            if expected_assembly in assembly_names:
                track_id = metadata.get('trackId', '')
                
                # Check if this track is in our sheet
                if track_id and track_id not in track_ids_in_sheet:
                    if dry_run:
                        print(f"  [DRY RUN] Would remove: {track_id} ({json_file.name})")
                    else:
                        print(f"  Removing orphaned track: {track_id}")
                        json_file.unlink()
                    removed_count += 1
                    
        except Exception as e:
            print(f"  ⚠ Error reading {json_file.name}: {e}")
            continue
    
    print(f"Checked {checked_count} track files, removed {removed_count} orphaned tracks")
    print("=" * 70)
    print()
    
    return removed_count


def validate_track_file(track_path, track_type, organism=None, assembly=None):
    """
    Enhanced validation for track files with helpful error messages.
    
    Args:
        track_path: Path to track file (absolute path or URL)
        track_type: Type of track (bigwig, bam, vcf, gff, etc.)
        organism: Organism name (for error messages)
        assembly: Assembly ID (for error messages)
        
    Returns:
        Tuple of (success: bool, error_message: str or None)
    """
    # Skip AUTO tracks
    if track_path.upper() == 'AUTO':
        return (True, None)
    
    # Remote URLs - can't fully validate
    if track_path.startswith('http://') or track_path.startswith('https://'):
        return (True, None)
    
    # Check file exists
    if not os.path.exists(track_path):
        return (False, f"File not found: {track_path}")
    
    # Check file extension matches type
    ext = os.path.splitext(track_path)[1].lower()
    expected_exts = {
        'bigwig': ['.bw', '.bigwig'],
        'bam': ['.bam'],
        'cram': ['.cram'],
        'vcf': ['.vcf', '.vcf.gz'],
        'gff': ['.gff', '.gff3', '.gff.gz', '.gff3.gz'],
        'gtf': ['.gtf', '.gtf.gz'],
        'bed': ['.bed', '.bed.gz'],
        'paf': ['.paf', '.paf.gz'],
        'maf': ['.maf', '.maf.gz']
    }
    
    if track_type in expected_exts:
        if ext not in expected_exts[track_type]:
            expected = ', '.join(expected_exts[track_type])
            return (False, f"File extension {ext} doesn't match type {track_type}. Expected: {expected}")
    
    # Check for required index files
    if track_type == 'bam':
        bai_path = track_path + '.bai'
        if not os.path.exists(bai_path):
            # Try alternate .bai location
            alt_bai = track_path.replace('.bam', '.bai')
            if not os.path.exists(alt_bai):
                return (False, f"BAI index missing: {bai_path}\n  Run: samtools index {track_path}")
    
    if track_type == 'cram':
        crai_path = track_path + '.crai'
        if not os.path.exists(crai_path):
            return (False, f"CRAI index missing: {crai_path}\n  Run: samtools index {track_path}")
    
    if track_type == 'vcf' and track_path.endswith('.gz'):
        tbi_path = track_path + '.tbi'
        if not os.path.exists(tbi_path):
            return (False, f"TBI index missing: {tbi_path}\n  Run: tabix -p vcf {track_path}")
    
    if track_type == 'gff' and track_path.endswith('.gz'):
        tbi_path = track_path + '.tbi'
        if not os.path.exists(tbi_path):
            return (False, f"TBI index missing: {tbi_path}\n  Run: tabix -p gff {track_path}")
    
    # File is valid
    return (True, None)


def parse_maf_samples(maf_path):
    """
    Extract unique sample/species names from a MAF file.
    
    MAF format has sequence lines like:
        s species.chromosome start size strand total_seq ACTG...
    
    This function extracts the unique species/sample names (part before the dot).
    
    Args:
        maf_path: Path to .maf or .maf.gz file
        
    Returns:
        List of unique sample names, or None if parsing fails
    """
    samples = set()
    
    try:
        # Handle both .maf and .maf.gz files
        if maf_path.endswith('.gz'):
            import gzip
            open_func = gzip.open
            mode = 'rt'
        else:
            open_func = open
            mode = 'r'
        
        with open_func(maf_path, mode) as f:
            for line in f:
                # MAF sequence lines start with 's '
                # Format: s species.chr start size strand total sequence
                if line.startswith('s '):
                    parts = line.split()
                    if len(parts) >= 2:
                        # Extract species/sample name (before the first dot)
                        full_name = parts[1]
                        sample_name = full_name.split('.')[0]
                        samples.add(sample_name)
                        
                # Stop after reading enough to get sample names
                # (avoids reading massive files completely)
                if len(samples) >= 20:  # reasonable upper limit
                    break
        
        result = sorted(list(samples))
        if result:
            print(f"   Found {len(result)} samples in MAF: {', '.join(result)}")
        return result
    
    except Exception as e:
        print(f"⚠ Could not parse MAF file {maf_path}: {e}")
        return None


def generate_single_track(row, organism, assembly, moop_root, default_color='DodgerBlue', dry_run=False, force_track_ids=None):
    """Generate a single track using appropriate script
    
    Args:
        force_track_ids: None (check exists), [] (force all), or ['id1', 'id2'] (force specific)
    """
    track_id = row.get('track_id', '')
    name = row.get('name', '')
    track_path = row.get('TRACK_PATH', '')
    category = row.get('category', 'Uncategorized')
    access = row.get('access_level', 'PUBLIC')
    description = row.get('description', '')
    technique = row.get('technique', '')
    
    if not track_id or not name or not track_path:
        print(f"⚠ Skipping incomplete row: track_id={track_id}, name={name}, TRACK_PATH={track_path}")
        return 'skipped'
    
    # Determine track type first (needed for AUTO path resolution)
    track_type = determine_track_type(row)
    
    if not track_type:
        print(f"⚠ Skipping {track_id}: Unknown track type (check file extension)")
        return False
    
    # Skip AUTO tracks - they are handled by assembly setup scripts
    # Reference genome is added by add_assembly_to_jbrowse.sh
    # Annotations are auto-added by assembly.php if annotations.gff3.gz exists
    if track_type == 'auto':
        print(f"→ Skipping {track_id} ({name}): AUTO tracks are configured by assembly setup")
        return 'skipped'
    
    # Resolve path (absolute, relative, URL, or AUTO)
    resolved_path, is_remote = resolve_track_path(track_path, moop_root, organism, assembly, track_type)
    
    # Enhanced validation for local files
    if not is_remote:
        success, error_msg = validate_track_file(resolved_path, track_type, organism, assembly)
        if not success:
            print(f"✗ Validation failed for {track_id}: {error_msg}")
            return False
    
    # Log remote vs local
    if is_remote:
        print(f"→ Creating {track_type} track (remote): {name}")
        print(f"  URL: {resolved_path}")
    else:
        print(f"→ Creating {track_type} track (local): {name}")
        print(f"  ✓ File validated: {resolved_path}")
    
    # Check if track exists and if we should skip
    metadata_dir = Path(moop_root) / 'metadata' / 'jbrowse2-configs' / 'tracks'
    
    # Determine if we should force regenerate
    should_force = False
    if force_track_ids is not None:  # --force was used
        if len(force_track_ids) == 0:  # --force with no args = force all
            should_force = True
        elif track_id in force_track_ids:  # --force TRACK_ID
            should_force = True
    
    if track_exists(track_id, metadata_dir):
        if should_force:
            print(f"→ Regenerating existing track: {track_id}")
        else:
            print(f"✓ Track exists: {track_id}")
            return True
    
    if dry_run:
        print(f"  [DRY RUN] Would create: {track_type} track '{name}' from {resolved_path}")
        return True
    
    # Build command based on track type
    script_dir = Path(moop_root) / 'tools' / 'jbrowse'
    
    if track_type == 'bigwig':
        cmd = [
            'bash', str(script_dir / 'add_bigwig_track.sh'),
            resolved_path, organism, assembly,
            '--name', name,
            '--track-id', track_id,
            '--category', category,
            '--access', access,
            '--color', default_color,
            '--force'  # Skip overwrite prompts
        ]
        if description:
            cmd.extend(['--description', description])
        if technique:
            cmd.extend(['--technique', technique])
    
    elif track_type == 'bam':
        cmd = [
            'bash', str(script_dir / 'add_bam_track.sh'),
            resolved_path, organism, assembly,
            '--name', name,
            '--track-id', track_id,
            '--category', category,
            '--access', access,
            '--force'
        ]
        if description:
            cmd.extend(['--description', description])
    
    elif track_type == 'vcf':
        cmd = [
            'bash', str(script_dir / 'add_vcf_track.sh'),
            resolved_path, organism, assembly,
            '--name', name,
            '--track-id', track_id,
            '--category', category,
            '--access', access,
            '--force'
        ]
        if description:
            cmd.extend(['--description', description])
    
    elif track_type == 'gff':
        cmd = [
            'bash', str(script_dir / 'add_gff_track.sh'),
            resolved_path, organism, assembly,
            '--name', name,
            '--track-id', track_id,
            '--category', category,
            '--access', access,
            '--force'
        ]
        if description:
            cmd.extend(['--description', description])
    
    elif track_type == 'cram':
        cmd = [
            'bash', str(script_dir / 'add_cram_track.sh'),
            '-a', assembly,
            '-t', track_id,
            '-n', name,
            '-f', resolved_path,
            '-c', category,
            '-l', access.lower()
        ]
        if is_remote:
            cmd.extend(['-r', resolved_path])
    
    elif track_type == 'paf':
        cmd = [
            'bash', str(script_dir / 'add_paf_track.sh'),
            '-a', assembly,
            '-t', track_id,
            '-n', name,
            '-f', resolved_path,
            '-c', category,
            '-l', access.lower()
        ]
        if is_remote:
            cmd.extend(['-r', resolved_path])
    
    elif track_type == 'gtf':
        cmd = [
            'bash', str(script_dir / 'add_gtf_track.sh'),
            '-a', assembly,
            '-t', track_id,
            '-n', name,
            '-f', resolved_path,
            '-c', category,
            '-l', access.lower()
        ]
        if is_remote:
            cmd.extend(['-r', resolved_path])
        # Add text indexing for GTF files
        cmd.append('-i')
    
    elif track_type == 'bed':
        cmd = [
            'bash', str(script_dir / 'add_bed_track.sh'),
            '-a', assembly,
            '-t', track_id,
            '-n', name,
            '-f', resolved_path,
            '-c', category,
            '-l', access.lower()
        ]
        if is_remote:
            cmd.extend(['-r', resolved_path])
    
    elif track_type == 'maf':
        # MAF (Multiple Alignment Format) - requires plugin and sample metadata
        print(f"  → Processing MAF file...")
        
        # Parse samples from MAF file
        samples_dict = {}
        if not is_remote:
            print(f"  → Analyzing MAF file for sample IDs...")
            sample_ids = parse_maf_samples(resolved_path)
            
            if sample_ids:
                print(f"     Found {len(sample_ids)} samples: {', '.join(sorted(sample_ids))}")
                
                # Create default sample config (user can customize colors in sheet)
                default_colors = COLORS['rainbow']  # Use rainbow for variety
                for idx, sample_id in enumerate(sorted(sample_ids)):
                    color = default_colors[idx % len(default_colors)]
                    samples_dict[sample_id] = {
                        'id': sample_id,
                        'label': sample_id,
                        'color': f'rgba({color},0.7)' if not color.startswith('rgb') else color
                    }
            else:
                print(f"  ⚠ Could not parse samples from MAF file")
                return False
        else:
            # For remote files, require SAMPLES column
            samples_str = row.get('SAMPLES', row.get('samples', ''))
            if not samples_str:
                print(f"  ⚠ Remote MAF files require 'SAMPLES' column with comma-separated sample IDs")
                return False
            
            sample_ids = [s.strip() for s in samples_str.split(',')]
            default_colors = COLORS['rainbow']
            for idx, sample_id in enumerate(sample_ids):
                color = default_colors[idx % len(default_colors)]
                samples_dict[sample_id] = {
                    'id': sample_id,
                    'label': sample_id,
                    'color': f'rgba({color},0.7)'
                }
        
        # Convert samples dict to JSON
        samples_json = json.dumps(list(samples_dict.values()))
        
        cmd = [
            'bash', str(script_dir / 'add_maf_track.sh'),
            '-f', resolved_path,
            '-a', assembly,
            '-t', track_id,
            '-n', name,
            '-s', samples_json,
            '-c', category,
            '-l', access.lower()
        ]
        if is_remote:
            cmd.append('-r')
        
        print(f"  ⚠ Note: Requires jbrowse-plugin-mafviewer plugin to be installed")
        print(f"     Install with: jbrowse add-plugin https://unpkg.com/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js")
    
    elif track_type in ['synteny_pif', 'synteny_mcscan']:
        # Synteny tracks handled by separate function
        print(f"  → Synteny track - use generate_synteny_track()")
        return False
    
    else:
        print(f"  ⚠ Unsupported track type: {track_type}")
        return False
    
    try:
        result = subprocess.run(cmd, capture_output=True, text=True)
        if result.returncode == 0:
            print(f"  ✓ Created: {name}")
            return True
        else:
            print(f"  ✗ Error creating {name}: {result.stderr}")
            return False
    except Exception as e:
        print(f"  ✗ Exception: {e}")
        return False


def generate_synteny_track(row, moop_root, dry_run=False):
    """
    Generate a synteny track (requires two assemblies).
    
    Synteny tracks show genomic relationships between two genomes.
    
    Args:
        row: Dictionary with track data including ASSEMBLY1, ASSEMBLY2
        moop_root: Root directory of MOOP installation
        dry_run: If True, don't actually create tracks
        
    Returns:
        bool: True if successful, False otherwise
        
    Required columns:
        - track_id: Unique identifier
        - name: Display name
        - ASSEMBLY1: First assembly name
        - ASSEMBLY2: Second assembly name
        - TRACK_PATH: Path to synteny file (.pif.gz or .anchors)
        
    Optional columns (for MCScan):
        - BED1_PATH: BED file for assembly 1
        - BED2_PATH: BED file for assembly 2
    """
    track_id = row.get('track_id', '')
    name = row.get('name', '')
    track_path = row.get('TRACK_PATH', '')
    assembly1 = row.get('ASSEMBLY1', '')
    assembly2 = row.get('ASSEMBLY2', '')
    category = row.get('category', 'Synteny')
    
    if not all([track_id, name, track_path, assembly1, assembly2]):
        print(f"⚠ Skipping incomplete synteny row: track_id={track_id}, assemblies={assembly1},{assembly2}")
        return 'skipped'
    
    # Determine synteny type first
    track_type = determine_track_type(row)
    
    if track_type not in ['synteny_pif', 'synteny_mcscan']:
        print(f"⚠ Not a synteny track: {track_type}")
        return False
    
    # Resolve path
    resolved_path, is_remote = resolve_track_path(track_path, moop_root, organism, assembly, track_type)
    
    # Verify local files exist
    if not is_remote and not verify_track_exists(resolved_path, is_remote):
        print(f"✗ File not found: {resolved_path}")
        return False
    
    # Check if track exists
    metadata_dir = Path(moop_root) / 'metadata' / 'jbrowse2-configs' / 'tracks'
    if track_exists(track_id, metadata_dir):
        print(f"✓ Synteny track exists: {track_id}")
        return True
    
    if is_remote:
        print(f"→ Creating {track_type} synteny track (remote): {name}")
        print(f"  Assemblies: {assembly1} <-> {assembly2}")
        print(f"  URL: {resolved_path}")
    else:
        print(f"→ Creating {track_type} synteny track (local): {name}")
        print(f"  Assemblies: {assembly1} <-> {assembly2}")
    
    if dry_run:
        print(f"  [DRY RUN] Would create: {track_type} track '{name}'")
        return True
    
    # Build command based on synteny type
    script_dir = Path(moop_root) / 'tools' / 'jbrowse'
    
    if track_type == 'synteny_pif':
        # PIF.GZ whole genome synteny
        cmd = [
            'bash', str(script_dir / 'add_synteny_track.sh'),
            '-1', assembly1,
            '-2', assembly2,
            '-t', track_id,
            '-n', name,
            '-f', resolved_path,
            '-c', category
        ]
        if is_remote:
            cmd.extend(['-r', resolved_path])
    
    elif track_type == 'synteny_mcscan':
        # MCScan anchors - requires BED files
        bed1_path = row.get('BED1_PATH', '')
        bed2_path = row.get('BED2_PATH', '')
        
        if not bed1_path or not bed2_path:
            print(f"  ✗ Error: MCScan tracks require BED1_PATH and BED2_PATH columns")
            return False
        
        # Resolve BED paths (BED files don't need track_type for resolution)
        resolved_bed1, is_remote_bed1 = resolve_track_path(bed1_path, moop_root, organism, assembly, 'bed')
        resolved_bed2, is_remote_bed2 = resolve_track_path(bed2_path, moop_root, organism, assembly, 'bed')
        
        # Verify BED files exist
        if not is_remote_bed1 and not Path(resolved_bed1).exists():
            print(f"  ✗ BED1 file not found: {resolved_bed1}")
            return False
        if not is_remote_bed2 and not Path(resolved_bed2).exists():
            print(f"  ✗ BED2 file not found: {resolved_bed2}")
            return False
        
        cmd = [
            'bash', str(script_dir / 'add_mcscan_track.sh'),
            '-1', assembly1,
            '-2', assembly2,
            '-t', track_id,
            '-n', name,
            '-f', resolved_path,
            '-b', resolved_bed1,
            '-b', resolved_bed2,
            '-c', category
        ]
        if is_remote:
            cmd.extend(['-r', resolved_path, '-r', resolved_bed1, '-r', resolved_bed2])
    
    else:
        print(f"  ⚠ Unknown synteny type: {track_type}")
        return False
    
    try:
        result = subprocess.run(cmd, capture_output=True, text=True)
        if result.returncode == 0:
            print(f"  ✓ Created synteny track: {name}")
            return True
        else:
            print(f"  ✗ Error creating {name}: {result.stderr}")
            return False
    except Exception as e:
        print(f"  ✗ Exception: {e}")
        return False


def generate_combo_track(combo_name, combo_data, organism, assembly, moop_root, dry_run=False):
    """Generate a multi-BigWig combo track"""
    track_id = combo_name.lower().replace(' ', '_').replace(',', '')
    
    # Check if track exists
    metadata_dir = Path(moop_root) / 'metadata' / 'jbrowse2-configs' / 'tracks'
    if track_exists(track_id, metadata_dir):
        print(f"✓ Combo track exists: {combo_name}")
        return True
    
    print(f"→ Creating multi-BigWig track: {combo_name}")
    
    # Build bigwig arguments
    bigwig_args = []
    
    for group_name, group_data in combo_data['groups'].items():
        color_group = group_data['color']
        tracks = group_data['tracks']
        
        for idx, track in enumerate(tracks):
            track_path = track.get('TRACK_PATH', '')
            name = track.get('name', '')
            color = get_color(color_group, idx)
            
            # Determine track type and resolve path
            track_type = determine_track_type(track)
            resolved_path, is_remote = resolve_track_path(track_path, moop_root, organism, assembly, track_type)
            
            bigwig_args.extend([
                '--bigwig', f"{resolved_path}:{name}:{color}"
            ])
    
    if dry_run:
        print(f"  [DRY RUN] Would create combo track with {len(bigwig_args)//2} files")
        return True
    
    # Run multi-BigWig script
    script_dir = Path(moop_root) / 'tools' / 'jbrowse'
    cmd = [
        'bash', str(script_dir / 'add_multi_bigwig_track.sh'),
        organism, assembly,
        '--name', combo_name,
        '--track-id', track_id,
        '--category', 'Multi-BigWig',
        '--access', 'PUBLIC'
    ] + bigwig_args
    
    try:
        result = subprocess.run(cmd, capture_output=True, text=True)
        if result.returncode == 0:
            print(f"  ✓ Created combo track: {combo_name}")
            return True
        else:
            print(f"  ✗ Error: {result.stderr}")
            return False
    except Exception as e:
        print(f"  ✗ Exception: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(
        description='Generate JBrowse2 tracks from Google Sheets',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__
    )
    parser.add_argument('sheet_id', nargs='?', help='Google Sheet ID or full URL')
    parser.add_argument('--gid', default='0', help='Sheet GID (default: 0)')
    parser.add_argument('--organism', help='Organism name')
    parser.add_argument('--assembly', help='Assembly ID')
    parser.add_argument('--moop-root', default='/data/moop', help='MOOP root directory')
    parser.add_argument('--dry-run', action='store_true', help='Show what would be created without doing it')
    parser.add_argument('--regenerate', action='store_true', help='Regenerate configs after adding tracks')
    parser.add_argument('--force', nargs='*', metavar='TRACK_ID', 
                       help='Force regenerate tracks. No args = all tracks, or specify track IDs')
    parser.add_argument('--clean', action='store_true', 
                       help='Remove track JSONs not in sheet (for this organism/assembly)')
    parser.add_argument('--list-colors', action='store_true', help='List all available color groups and exit')
    parser.add_argument('--suggest-colors', type=int, metavar='N', help='Suggest color groups for N files')
    
    args = parser.parse_args()
    
    # Handle color listing
    if args.list_colors:
        print("=" * 80)
        print("AVAILABLE COLOR GROUPS")
        print("=" * 80)
        print()
        
        # Sort by count (descending)
        sorted_groups = sorted(COLORS.items(), key=lambda x: -len(x[1]))
        
        print(f"{'Group':<15} {'Colors':<8} {'Type':<12} {'Best For'}")
        print("-" * 80)
        
        for group_name, colors in sorted_groups:
            info = COLOR_GROUP_INFO.get(group_name, {})
            count = len(colors)
            gtype = info.get('type', 'general')
            best_for = info.get('best_for', 'general use')
            print(f"{group_name:<15} {count:<8} {gtype:<12} {best_for}")
        
        print()
        print("=" * 80)
        print("USAGE EXAMPLES:")
        print("-" * 80)
        print("  ## blues: Sample Group          # Use blues color group")
        print("  ## exact=OrangeRed: Group       # Use specific color")
        print("  ## reds3: Group                 # Use 4th color from reds (0-indexed)")
        print("=" * 80)
        return
    
    # Handle color suggestions
    if args.suggest_colors:
        num = args.suggest_colors
        print("=" * 80)
        print(f"COLOR GROUP SUGGESTIONS FOR {num} FILES")
        print("=" * 80)
        print()
        
        suggestions = suggest_color_groups(num)
        
        if not suggestions:
            print("⚠ No color groups have enough colors!")
            print(f"Maximum available: {max(len(c) for c in COLORS.values())} colors")
            print()
            print("TIP: Use 'rainbow' (20 colors) for maximum coverage")
        else:
            print(f"{'Group':<15} {'Colors':<8} {'Type':<12} {'Best For'}")
            print("-" * 80)
            for s in suggestions:
                print(f"{s['name']:<15} {s['count']:<8} {s['type']:<12} {s['best_for']}")
            
            print()
            print("RECOMMENDED:")
            print(f"  ## {suggestions[0]['name']}: Your Group Name")
        
        print("=" * 80)
        return
    
    # Require sheet_id, organism, and assembly for track generation
    if not args.sheet_id or not args.organism or not args.assembly:
        parser.error('sheet_id, --organism, and --assembly are required (unless using --list-colors or --suggest-colors)')
    
    # Extract sheet ID from URL if needed
    sheet_id = args.sheet_id
    if 'docs.google.com' in sheet_id:
        match = re.search(r'/d/([a-zA-Z0-9-_]+)', sheet_id)
        if match:
            sheet_id = match.group(1)
    
    print("=" * 70)
    print("Google Sheets to JBrowse2 Track Generator")
    print("=" * 70)
    print(f"Sheet ID: {sheet_id}")
    print(f"Organism: {args.organism}")
    print(f"Assembly: {args.assembly}")
    if args.dry_run:
        print("Mode: DRY RUN (no changes will be made)")
    print("=" * 70)
    print()
    
    # Check and setup assembly if needed
    if not setup_assembly(args.organism, args.assembly, args.moop_root, dry_run=args.dry_run):
        print("✗ Assembly setup failed. Cannot proceed with track loading.")
        sys.exit(1)
    
    # Download sheet
    tsv_content = download_sheet_as_tsv(sheet_id, args.gid)
    
    # Parse tracks
    regular_tracks, combo_tracks = parse_sheet(tsv_content)
    
    print(f"Found {len(regular_tracks)} regular tracks")
    print(f"Found {len(combo_tracks)} combo tracks")
    print()
    
    # Check for duplicate track_ids
    track_id_map = {}
    duplicates = []
    for track in regular_tracks:
        track_id = track.get('track_id', '')
        track_path = track.get('TRACK_PATH', '')
        if track_id in track_id_map:
            duplicates.append({
                'track_id': track_id,
                'path1': track_id_map[track_id],
                'path2': track_path
            })
        else:
            track_id_map[track_id] = track_path
    
    if duplicates:
        print("⚠ WARNING: Duplicate track_ids found with different paths!")
        print("-" * 70)
        for dup in duplicates:
            print(f"  track_id: {dup['track_id']}")
            print(f"    Path 1: {dup['path1']}")
            print(f"    Path 2: {dup['path2']}")
        print("-" * 70)
        print("Please ensure each track has a unique track_id")
        print()
    
    # Process regular tracks
    # Separate regular tracks from synteny tracks
    synteny_tracks = []
    regular_tracks_only = []
    
    for track in regular_tracks:
        track_type = determine_track_type(track)
        if track_type in ['synteny_pif', 'synteny_mcscan']:
            synteny_tracks.append(track)
        else:
            regular_tracks_only.append(track)
    
    print("Processing regular tracks...")
    success_count = 0
    failed_tracks = []
    skipped_tracks = []
    
    # Prepare force track IDs list
    force_track_ids = None
    if args.force is not None:  # --force flag was used
        if len(args.force) == 0:  # --force with no args
            force_track_ids = []  # Empty list means force all
            print(f"→ Forcing regeneration of ALL tracks")
        else:  # --force TRACK_ID1 TRACK_ID2
            force_track_ids = args.force
            print(f"→ Forcing regeneration of specific tracks: {', '.join(force_track_ids)}")
        print()
    
    # Collect all track IDs from sheet (for cleanup)
    sheet_track_ids = set()
    
    for track in regular_tracks_only:
        track_id = track.get('track_id', 'unknown')
        name = track.get('name', 'unknown')
        sheet_track_ids.add(track_id)
        
        result = generate_single_track(track, args.organism, args.assembly, args.moop_root, 
                                       dry_run=args.dry_run, force_track_ids=force_track_ids)
        
        if result == 'skipped':
            skipped_tracks.append({'track_id': track_id, 'name': name, 'reason': result})
        elif result:
            success_count += 1
        else:
            failed_tracks.append({'track_id': track_id, 'name': name})
    
    print(f"Regular tracks: {success_count}/{len(regular_tracks_only)} created")
    if skipped_tracks:
        print(f"  ⚠ Skipped: {len(skipped_tracks)}")
    if failed_tracks:
        print(f"  ✗ Failed: {len(failed_tracks)}")
    print()
    
    # Process synteny tracks
    if synteny_tracks:
        print("Processing synteny tracks...")
        synteny_success = 0
        failed_synteny = []
        
        for track in synteny_tracks:
            track_id = track.get('track_id', 'unknown')
            name = track.get('name', 'unknown')
            result = generate_synteny_track(track, args.moop_root, dry_run=args.dry_run)
            
            if result == 'skipped':
                skipped_tracks.append({'track_id': track_id, 'name': name, 'reason': 'incomplete'})
            elif result:
                synteny_success += 1
            else:
                failed_synteny.append({'track_id': track_id, 'name': name})
        
        print(f"Synteny tracks: {synteny_success}/{len(synteny_tracks)} created")
        if failed_synteny:
            print(f"  ✗ Failed: {len(failed_synteny)}")
        print()
    
    # Process combo tracks
    print("Processing combo tracks...")
    combo_success = 0
    failed_combos = []
    
    for combo_name, combo_data in combo_tracks.items():
        if generate_combo_track(combo_name, combo_data, args.organism, args.assembly, args.moop_root, dry_run=args.dry_run):
            combo_success += 1
        else:
            failed_combos.append(combo_name)
    
    print(f"Combo tracks: {combo_success}/{len(combo_tracks)} created")
    if failed_combos:
        print(f"  ✗ Failed: {len(failed_combos)}")
    print()
    
    # Clean orphaned tracks if requested
    if args.clean:
        # Add combo track IDs to the set
        for combo_name in combo_tracks.keys():
            combo_track_id = combo_name.lower().replace(' ', '_').replace(',', '')
            sheet_track_ids.add(combo_track_id)
        
        removed_count = clean_orphaned_tracks(args.organism, args.assembly, sheet_track_ids, 
                                               args.moop_root, dry_run=args.dry_run)
        if removed_count > 0:
            print(f"✓ Cleaned {removed_count} orphaned track(s)")
            print()
    
    # Regenerate configs
    if args.regenerate and not args.dry_run:
        print("Regenerating JBrowse2 configs...")
        script_path = Path(args.moop_root) / 'tools' / 'jbrowse' / 'generate-jbrowse-configs.php'
        result = subprocess.run(['php', str(script_path)], capture_output=True, text=True)
        if result.returncode == 0:
            print("✓ Configs regenerated")
        else:
            print(f"✗ Error regenerating configs: {result.stderr}")
    
    print()
    print("=" * 70)
    print("SUMMARY")
    print("=" * 70)
    print(f"Regular tracks: {success_count}/{len(regular_tracks)} created")
    print(f"Combo tracks: {combo_success}/{len(combo_tracks)} created")
    
    # Report issues
    if skipped_tracks or failed_tracks or failed_combos:
        print()
        print("ISSUES:")
        print("-" * 70)
        
        if skipped_tracks:
            print(f"\n⚠ SKIPPED TRACKS ({len(skipped_tracks)}):")
            for track in skipped_tracks:
                print(f"  - {track['track_id']}: {track['name']}")
                print(f"    Reason: Missing required fields")
        
        if failed_tracks:
            print(f"\n✗ FAILED TRACKS ({len(failed_tracks)}):")
            for track in failed_tracks:
                print(f"  - {track['track_id']}: {track['name']}")
                print(f"    Check errors above for details")
        
        if failed_combos:
            print(f"\n✗ FAILED COMBO TRACKS ({len(failed_combos)}):")
            for combo_name in failed_combos:
                print(f"  - {combo_name}")
                print(f"    Check errors above for details")
    else:
        print("\n✓ All tracks processed successfully!")
    
    print("=" * 70)


if __name__ == '__main__':
    main()
