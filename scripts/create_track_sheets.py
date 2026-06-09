#!/usr/bin/env python3
"""
Parse metadata.contents.txt and scp_commands.sh to build reformatted
track sheets as a multi-tab XLSX (one sheet per organism/assembly).

New column format matches nvec_tracks_updated.tsv:
  TRACK_ID, NAME, technique, CATEGORY, institute, source, experiment,
  developmental-stage, tissue, condition, summary, citation, project,
  accession, date, analyst, SCIPRJ, ACCESS, biosample, NGS_file, MLONG,
  FILENAME, TRACK_PATH, ASSEMBLY_1, ASSEMBLY_2, BED1_PATH, BED2_PATH, MAF

TRACK_PATH is filled from the scp_commands.sh destination paths.
"""

import re
import csv
import io
import os
import sys
from collections import defaultdict

# --- try to import openpyxl ---
try:
    import openpyxl
    from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
    from openpyxl.utils import get_column_letter
except ImportError:
    print("ERROR: openpyxl not installed. Run: python3 -m pip install --user openpyxl")
    sys.exit(1)

# ──────────────────────────────────────────────────────────────
# New column order (matches nvec_tracks_updated.tsv)
# ──────────────────────────────────────────────────────────────
NEW_HEADERS = [
    "TRACK_ID", "NAME", "technique", "CATEGORY", "institute", "source",
    "experiment", "developmental-stage", "tissue", "condition", "summary",
    "citation", "project", "accession", "date", "analyst", "SCIPRJ",
    "ACCESS", "biosample", "NGS_file", "MLONG", "FILENAME", "TRACK_PATH",
    "ASSEMBLY_1", "ASSEMBLY_2", "BED1_PATH", "BED2_PATH", "MAF",
]

# Old CSV header → new header (for columns that just rename)
OLD_TO_NEW = {
    "label":               "TRACK_ID",
    "key":                 "NAME",
    "technique":           "technique",
    "category":            "CATEGORY",
    "institute":           "institute",
    "source":              "source",
    "experiment":          "experiment",
    "developmental-stage": "developmental-stage",
    "condition":           "condition",
    "summary":             "summary",
    "citation":            "citation",
    "accession":           "accession",
    "date":                "date",
    "analyst":             "analyst",
    "project":             "SCIPRJ",    # old "project" = SCI number → SCIPRJ
    "filename":            "FILENAME",
    "private tracks":      "ACCESS",    # old column 17
}


# ──────────────────────────────────────────────────────────────
# Parse scp_commands.sh
# ──────────────────────────────────────────────────────────────
def parse_scp_commands(filepath):
    """
    Returns:
      org_map  : {code -> {'organism': str, 'assembly': str, 'dest_dir': str}}
      file_map : {code -> {filename -> full_dest_path}}
    """
    org_map = {}
    file_map = defaultdict(dict)
    current_code = None

    with open(filepath, 'r') as f:
        for line in f:
            line = line.rstrip('\n')

            # Comment: # ACA1_v1 → /var/www/.../Organism/Assembly/gff
            m = re.match(
                r'^# (\S+) → (/var/www/privatehtml/moop/data/tracks/([^/]+)/([^/]+)/gff)',
                line
            )
            if m:
                current_code = m.group(1)
                dest_dir      = m.group(2)
                organism      = m.group(3)
                assembly      = m.group(4)
                org_map[current_code] = {
                    'organism': organism,
                    'assembly': assembly,
                    'dest_dir': dest_dir,
                }
                continue

            # Reset current_code on TRACKS_DEST (unmapped) comment
            m2 = re.match(r'^# (\S+) → TRACKS_DEST', line)
            if m2:
                current_code = None
                continue

            # Active scp line: scp src tracks:/dest/path/file.gff [# comment]
            m3 = re.match(r'^scp .+ tracks:(/[^\s#]+)', line)
            if m3 and current_code:
                dest_path = m3.group(1).rstrip()
                filename  = os.path.basename(dest_path)
                file_map[current_code][filename] = dest_path

    return org_map, file_map


# ──────────────────────────────────────────────────────────────
# Parse metadata.contents.txt
# ──────────────────────────────────────────────────────────────
def parse_metadata_contents(filepath):
    """
    Parses blocks delimited by:
        ######
        /path/to/CODE/includes/xxx_metadata.csv
        ######
        label,key,...   ← header
        row...
        #### END ######

    Returns {code -> [list of row dicts keyed by old header names]}
    """
    metadata = defaultdict(list)
    current_code = None
    header = None
    in_data = False   # True after the second "######" (CSV content started)
    path_seen = False # True after we've seen the path line

    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        for line in f:
            line = line.rstrip('\n')

            if line.startswith('#### END'):
                # End of block
                current_code = None
                header = None
                in_data = False
                path_seen = False
                continue

            if line == '######':
                if not path_seen:
                    # First delimiter — next line will be the path
                    path_seen = False  # wait for path
                    in_data = False
                else:
                    # Second delimiter — CSV data starts next
                    in_data = True
                continue

            # Path line (between the two ######)
            if not in_data and not path_seen:
                m = re.search(r'/data/([^/]+)/includes/', line)
                if m:
                    current_code = m.group(1)
                    path_seen = True
                continue

            # CSV data lines
            if in_data and current_code:
                if not line.strip():
                    continue
                try:
                    reader = csv.reader(io.StringIO(line))
                    row = next(reader)
                except Exception:
                    continue

                if all(c.strip() == '' for c in row):
                    continue

                if header is None:
                    # First non-empty line is the header
                    header = [c.strip().lower() for c in row]
                    continue

                row_dict = {}
                for i, val in enumerate(row):
                    if i < len(header) and header[i]:
                        row_dict[header[i]] = val.strip()
                metadata[current_code].append(row_dict)

    return metadata


# ──────────────────────────────────────────────────────────────
# Convert one old row dict → new row dict
# ──────────────────────────────────────────────────────────────
def convert_row(old_row, file_path_map):
    """
    file_path_map: {filename -> full_dest_path} for this organism code
    """
    new = {col: '' for col in NEW_HEADERS}

    for old_col, new_col in OLD_TO_NEW.items():
        val = old_row.get(old_col, '')
        if val:
            new[new_col] = val

    # Fill TRACK_PATH from scp file map
    filename = new.get('FILENAME', '').strip()
    if filename:
        dest = file_path_map.get(filename, '')
        # Also try stripping subdirectory from filename if stored with subdir
        if not dest:
            bare = os.path.basename(filename)
            dest = file_path_map.get(bare, '')
        new['TRACK_PATH'] = dest

    return new


# ──────────────────────────────────────────────────────────────
# XLSX styling helpers
# ──────────────────────────────────────────────────────────────
HEADER_FILL   = PatternFill("solid", fgColor="0891B2")   # teal accent
HEADER_FONT   = Font(bold=True, color="FFFFFF", size=10)
BODY_FONT     = Font(size=9)
FREEZE_ROW    = 2   # freeze row 1 (header)

def style_header_row(ws, n_cols):
    for col in range(1, n_cols + 1):
        cell = ws.cell(row=1, column=col)
        cell.fill  = HEADER_FILL
        cell.font  = HEADER_FONT
        cell.alignment = Alignment(horizontal='center', vertical='center', wrap_text=False)

def auto_width(ws, headers):
    for i, h in enumerate(headers, start=1):
        col_letter = get_column_letter(i)
        # Width: header text + 4, capped at 40
        ws.column_dimensions[col_letter].width = min(len(h) + 4, 40)


# ──────────────────────────────────────────────────────────────
# Main
# ──────────────────────────────────────────────────────────────
def main():
    base = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    meta_file = os.path.join(base, 'metadata.contents.txt')
    scp_file  = os.path.join(base, 'scp_commands.sh')
    out_file  = os.path.join(base, 'metadata', 'track_sheets_reformatted.xlsx')

    if not os.path.exists(meta_file):
        print(f"ERROR: {meta_file} not found"); sys.exit(1)
    if not os.path.exists(scp_file):
        print(f"ERROR: {scp_file} not found"); sys.exit(1)

    print("Parsing scp_commands.sh …")
    org_map, file_map = parse_scp_commands(scp_file)
    print(f"  {len(org_map)} organisms with valid new paths")

    print("Parsing metadata.contents.txt …")
    metadata = parse_metadata_contents(meta_file)
    print(f"  {len(metadata)} organism code blocks found")

    # Suppress openpyxl's Excel 31-char sheet name warning (Google Sheets supports 100)
    import warnings
    warnings.filterwarnings('ignore', category=UserWarning,
                            message='Title is more than 31 characters')

    wb = openpyxl.Workbook()
    wb.remove(wb.active)   # remove default empty sheet

    matched = 0
    skipped_pub = []
    skipped_no_meta = []
    used_tab_names = {}   # tab_name -> count, for dedup
    toc_entries = []      # (tab_name, organism, assembly, n_rows)

    # --- Sheets for organisms in scp_map (skip _pub codes) ---
    for code in sorted(org_map.keys()):
        # Skip public/published variants
        if '_pub' in code.lower() or code.lower().endswith('_pub'):
            skipped_pub.append(code)
            continue

        info = org_map[code]
        organism = info['organism']
        assembly = info['assembly']

        # Tab name: Organism_Assembly (up to 100 chars for Google Sheets)
        tab_name = f"{organism}_{assembly}"[:100]

        # Deduplicate tab names (shouldn't happen after skipping _pub, but just in case)
        if tab_name in used_tab_names:
            used_tab_names[tab_name] += 1
            tab_name = f"{tab_name[:95]}_{used_tab_names[tab_name]}"
        else:
            used_tab_names[tab_name] = 1

        rows = metadata.get(code, [])
        fp_map = file_map.get(code, {})

        if not rows and not fp_map:
            skipped_no_meta.append(code)
            continue

        ws = wb.create_sheet(title=tab_name)

        # Header row
        ws.append(NEW_HEADERS)
        style_header_row(ws, len(NEW_HEADERS))
        ws.freeze_panes = 'A2'

        # Metadata rows (converted from old format)
        for old_row in rows:
            if all(v == '' for v in old_row.values()):
                continue
            new_row = convert_row(old_row, fp_map)
            ws.append([new_row.get(col, '') for col in NEW_HEADERS])

        # Any files in scp_map that have NO metadata row yet
        # (i.e., their filename doesn't appear in any row's FILENAME)
        used_files = set()
        for old_row in rows:
            fn = old_row.get('filename', '').strip()
            if fn:
                used_files.add(os.path.basename(fn))

        for fn, dest in sorted(fp_map.items()):
            bare = os.path.basename(fn)
            if bare in used_files:
                continue
            # Stub row with just FILENAME + TRACK_PATH
            new_row = {col: '' for col in NEW_HEADERS}
            new_row['FILENAME']   = bare
            new_row['TRACK_PATH'] = dest
            ws.append([new_row.get(col, '') for col in NEW_HEADERS])

        auto_width(ws, NEW_HEADERS)

        # Style body rows
        for row in ws.iter_rows(min_row=2):
            for cell in row:
                cell.font = BODY_FONT

        toc_entries.append((tab_name, organism, assembly,
                            ws.max_row - 1))   # -1 for header
        matched += 1
        print(f"  ✓ {tab_name}  ({len(rows)} metadata rows, {len(fp_map)} scp files)")

    # ── Add Nematostella vectensis sheet from its pre-updated TSV ──
    nvec_tsv = os.path.join(base, 'Nematostella_vectensis_updated.tsv')
    if os.path.exists(nvec_tsv):
        nvec_tab  = 'Nematostella_vectensis_GCA_033964005.1'
        nvec_base = '/var/www/privatehtml/moop/data/tracks/Nematostella_vectensis/'

        # Column name mapping for Nvec TSV → new headers
        NVEC_MAP = {
            'label':               'TRACK_ID',
            'key':                 'NAME',
            'technique':           'technique',
            'category':            'CATEGORY',
            'institute':           'institute',
            'source':              'source',
            'experiment':          'experiment',
            'developmental-stage': 'developmental-stage',
            'tissue':              'tissue',
            'condition':           'condition',
            'summary':             'summary',
            'citation':            'citation',
            'project':             'project',
            'accession':           'accession',
            'date':                'date',
            'analyst':             'analyst',
            'filename':            'FILENAME',
            'sciprj':              'SCIPRJ',
            'access_level':        'ACCESS',
            'biosample':           'biosample',
            'ngs_file':            'NGS_file',
            'mlong':               'MLONG',
        }

        ws_nvec = wb.create_sheet(title=nvec_tab)
        ws_nvec.append(NEW_HEADERS)
        style_header_row(ws_nvec, len(NEW_HEADERS))
        ws_nvec.freeze_panes = 'A2'

        with open(nvec_tsv, 'r', encoding='utf-8', errors='replace') as f:
            reader = csv.DictReader(f, delimiter='\t')
            nvec_rows = 0
            for old_row in reader:
                new = {col: '' for col in NEW_HEADERS}
                for old_col, val in old_row.items():
                    mapped = NVEC_MAP.get(old_col.strip().lower())
                    if mapped and val:
                        new[mapped] = val.strip()

                # Derive TRACK_PATH from filename
                fn = new.get('FILENAME', '').strip()
                if fn and ('/' in fn or fn.startswith('GCA_')):
                    new['TRACK_PATH'] = nvec_base + fn.lstrip('/')

                ws_nvec.append([new.get(col, '') for col in NEW_HEADERS])
                nvec_rows += 1

        auto_width(ws_nvec, NEW_HEADERS)
        for row in ws_nvec.iter_rows(min_row=2):
            for cell in row:
                cell.font = BODY_FONT

        toc_entries.append((nvec_tab, 'Nematostella_vectensis', 'GCA_033964005.1', nvec_rows))
        matched += 1
        print(f"  ✓ {nvec_tab}  ({nvec_rows} rows from updated TSV)")
    else:
        print(f"  WARNING: {nvec_tsv} not found — Nematostella not included")

    # ── Build Table of Contents as the FIRST sheet (after all organism sheets collected) ──
    toc = wb.create_sheet(title="Table of Contents", index=0)

    toc.append(["Tab", "Organism", "Assembly", "Track rows"])
    hdr_fill = PatternFill("solid", fgColor="0891B2")
    hdr_font = Font(bold=True, color="FFFFFF", size=11)
    for cell in toc[1]:
        cell.fill = hdr_fill
        cell.font = hdr_font
        cell.alignment = Alignment(horizontal='center')
    toc.freeze_panes = 'A2'
    toc.column_dimensions['A'].width = 55
    toc.column_dimensions['B'].width = 30
    toc.column_dimensions['C'].width = 22
    toc.column_dimensions['D'].width = 12

    link_font    = Font(color="0891B2", underline="single", size=10)
    body_font_toc = Font(size=10)

    for row_i, (tab_name, organism, assembly, n_rows) in enumerate(toc_entries, start=2):
        cell = toc.cell(row=row_i, column=1, value=tab_name)
        cell.hyperlink = f"#{tab_name}!A1"
        cell.font = link_font
        toc.cell(row=row_i, column=2, value=organism.replace('_', ' ')).font = body_font_toc
        toc.cell(row=row_i, column=3, value=assembly).font = body_font_toc
        toc.cell(row=row_i, column=4, value=n_rows).font = body_font_toc

    print(f"\n✓ Table of Contents written ({len(toc_entries)} entries)")
    print(f"\nCreated {matched} organism sheets + 1 TOC = {matched + 1} total")
    if skipped_pub:
        print(f"Skipped (_pub): {', '.join(skipped_pub)}")
    if skipped_no_meta:
        print(f"Skipped (no metadata + no scp files): {', '.join(skipped_no_meta)}")

    wb.save(out_file)
    print(f"\nSaved → {out_file}")
    return out_file


if __name__ == '__main__':
    main()
