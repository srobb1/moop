#!/usr/bin/env python3
"""
update_sheet_paths.py — update track sheet filenames to new server paths.

Usage:
  python3 update_sheet_paths.py --sheet SHEET_URL_OR_ID [--gid GID] \
                                 --paths paths.txt --out updated.tsv

  --sheet   Google Sheet URL or bare sheet ID
  --gid     Sheet tab GID (default: 0)
  --paths   Flat file list from the new track server
              (generate with: ssh tracks find /path/to/assembly/ -type f | sort)
  --prefix  Prefix to strip from paths.txt entries to get relative paths
              (default: auto-detected as the longest common prefix)
  --col     Name of the filename column to update (default: auto-detected)
  --out     Output TSV file (default: stdout)
"""

import argparse
import re
import sys
import urllib.request
from pathlib import Path
from collections import defaultdict


# ── Column name candidates (case-insensitive) ─────────────────────────────────
FILENAME_COL_NAMES = ['filename', 'track_path', 'file', 'filepath', 'path', 'file_path']

ACCESS_COL_NAMES = ['access', 'access_level', 'permission', 'permissions',
                    'visibility', 'private', 'track_access', 'access level',
                    'private tracks']

ACCESS_VALUE_MAP = {
    'private':      'COLLABORATOR',
    'restricted':   'COLLABORATOR',
    'login':        'COLLABORATOR',
    'logged_in':    'COLLABORATOR',
    'collaborator': 'COLLABORATOR',
    'public':       'PUBLIC',
    'ip_in_range':  'IP_IN_RANGE',
    'ip':           'IP_IN_RANGE',
    'admin':        'ADMIN',
}


def download_sheet(sheet_id, gid):
    url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/export?format=tsv&gid={gid}"
    try:
        with urllib.request.urlopen(url) as r:
            return r.read().decode('utf-8')
    except Exception as e:
        sys.exit(f"ERROR: Could not download sheet: {e}")


def parse_sheet_id(sheet_arg):
    m = re.search(r'/d/([a-zA-Z0-9-_]+)', sheet_arg)
    return m.group(1) if m else sheet_arg


def parse_gid(sheet_arg, default='0'):
    m = re.search(r'[#&]gid=(\d+)', sheet_arg)
    return m.group(1) if m else default


def load_paths(paths_file, prefix_override=None):
    """Load paths.txt and return (path_set, by_basename, by_molng_bare)."""
    raw = []
    with open(paths_file, encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                raw.append(line)

    # Auto-detect common prefix to strip (e.g. "Nematostella_vectensis/GCA_033964005.1/")
    if prefix_override:
        prefix = prefix_override.rstrip('/') + '/'
    else:
        # Find the deepest common prefix that ends at a '/'
        if raw:
            common = raw[0]
            for p in raw[1:]:
                while not p.startswith(common):
                    common = common[:common.rfind('/', 0, -1) + 1]
                    if not common:
                        break
            prefix = common
        else:
            prefix = ''

    rel_paths = []
    for p in raw:
        if '/trash/' in p:
            continue
        rel = p[len(prefix):] if p.startswith(prefix) else p
        if rel:
            rel_paths.append(rel)

    path_set = set(rel_paths)

    by_basename = defaultdict(list)
    for p in rel_paths:
        by_basename[Path(p).name].append(p)

    # Index bare names after stripping project prefixes like "MOLNG-3006_"
    # e.g. "MOLNG-3006_WT_48hpf_1.pos.bw" → bare "WT_48hpf_1.pos.bw" → path
    by_bare = defaultdict(list)
    for p in rel_paths:
        name = Path(p).name
        m = re.match(r'^[A-Z]+-\d+_(.+)$', name)
        if m:
            by_bare[m.group(1)].append(p)

    return path_set, by_basename, by_bare


def resolve(fn, path_set, by_basename, by_bare):
    fn = fn.strip()
    if not fn:
        return fn

    # Exact match
    if fn in path_set:
        return fn

    basename = Path(fn).name
    parent   = str(Path(fn).parent)

    # Project prefix from parent dir: e.g. parent="RNAseq/MOLNG-3006" → prepend "MOLNG-3006_"
    m = re.search(r'([A-Z]+-\d+)$', parent)
    if m:
        prefixed = m.group(1) + '_' + basename
        c = by_basename.get(prefixed, [])
        if c:
            return c[0]

    # Simple basename lookup
    c = by_basename.get(basename, [])
    if len(c) == 1:
        return c[0]
    if len(c) > 1:
        return c[0]

    # Bare name (strip project prefix from basename itself)
    c = by_bare.get(basename, [])
    if len(c) == 1:
        return c[0]

    return fn  # not found — keep original


def update_cell(val, path_set, by_basename, by_bare):
    """Update filename(s) in a cell. Handles plain paths and Color:label(file) format."""
    if re.search(r'\([^)]+\.[a-zA-Z0-9]+\)', val):
        return re.sub(
            r'\(([^)]+)\)',
            lambda m: '(' + resolve(m.group(1), path_set, by_basename, by_bare) + ')',
            val
        )
    return resolve(val, path_set, by_basename, by_bare)


def find_filename_col(header, col_override=None):
    """Return index of filename column, or None."""
    if col_override:
        col_lower = col_override.lower()
        for i, h in enumerate(header):
            if h.lower() == col_lower:
                return i
        sys.exit(f"ERROR: Column '{col_override}' not found in header: {header}")

    for name in FILENAME_COL_NAMES:
        for i, h in enumerate(header):
            if h.lower() == name:
                return i
    return None


def find_access_col(header):
    """Return index of access column, or None."""
    for name in ACCESS_COL_NAMES:
        for i, h in enumerate(header):
            if h.lower() == name:
                return i
    return None


def normalize_access(val):
    """Map legacy/freeform access values to canonical MOOP access levels."""
    return ACCESS_VALUE_MAP.get(val.strip().lower(), val)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--sheet',  default=None,  help='Google Sheet URL or sheet ID')
    ap.add_argument('--tsv',    default=None,  help='Local TSV file (use instead of --sheet for sheets that need login)')
    ap.add_argument('--gid',    default=None,  help='Sheet tab GID (default: from URL or 0)')
    ap.add_argument('--paths',  required=True, help='Flat paths.txt from new track server')
    ap.add_argument('--prefix', default=None,  help='Prefix to strip from paths.txt (auto-detected if omitted)')
    ap.add_argument('--col',    default=None,  help='Filename column name (auto-detected if omitted)')
    ap.add_argument('--out',    default='-',   help='Output TSV file (default: stdout)')
    args = ap.parse_args()

    if not args.sheet and not args.tsv:
        sys.exit("ERROR: Provide --sheet or --tsv")

    if args.tsv:
        print(f"Reading local TSV {args.tsv}...", file=sys.stderr)
        with open(args.tsv, encoding='utf-8') as f:
            content = f.read()
    else:
        sheet_id = parse_sheet_id(args.sheet)
        gid      = args.gid or parse_gid(args.sheet)
        print(f"Downloading sheet {sheet_id} (gid={gid})...", file=sys.stderr)
        content = download_sheet(sheet_id, gid)
    lines   = content.splitlines()

    print(f"Loading paths from {args.paths}...", file=sys.stderr)
    path_set, by_basename, by_bare = load_paths(args.paths, args.prefix)
    print(f"  {len(path_set)} usable paths loaded", file=sys.stderr)

    # Find header and filename column
    header_idx = None
    header     = []
    for i, line in enumerate(lines):
        if line and not line.startswith('#'):
            header_idx = i
            raw_header = line.split('\t')
            raw_header[0] = re.sub(r'^\xef\xbb\xbf', '', raw_header[0])  # strip BOM
            header = [h.strip().lower() for h in raw_header]
            break

    if header_idx is None:
        sys.exit("ERROR: No header row found")

    fn_col = find_filename_col(header, args.col)
    if fn_col is None:
        sys.exit(f"ERROR: No filename column found. Header: {header}\nUse --col to specify.")

    print(f"  Filename column: '{header[fn_col]}' (index {fn_col})", file=sys.stderr)

    acc_col = find_access_col(header)
    if acc_col is not None:
        print(f"  Access column:  '{header[acc_col]}' (index {acc_col}) → renaming to 'access_level', normalizing values", file=sys.stderr)
    else:
        print(f"  Access column:  not found (no access normalization)", file=sys.stderr)

    # Process rows
    updated_rows = []
    changed = 0
    missing = set()

    for i, line in enumerate(lines):
        raw = line.rstrip('\n')

        if not raw or raw.startswith('#'):
            updated_rows.append(raw)
            continue

        cols = raw.split('\t')

        if i == header_idx:
            if acc_col is not None:
                hcols = raw.split('\t')
                hcols[acc_col] = 'access_level'
                updated_rows.append('\t'.join(hcols))
            else:
                updated_rows.append(raw)
            continue

        if len(cols) <= fn_col:
            updated_rows.append(raw)
            continue

        orig    = cols[fn_col].strip()
        updated = update_cell(orig, path_set, by_basename, by_bare)

        if updated != orig:
            changed += 1
        elif orig:
            # Check if it genuinely resolved (already correct) or truly missing
            if orig not in path_set:
                # Extract individual filenames from multi-track cells
                if re.search(r'\([^)]+\)', orig):
                    for m in re.finditer(r'\(([^)]+)\)', orig):
                        fn = m.group(1).strip()
                        if fn and resolve(fn, path_set, by_basename, by_bare) == fn and fn not in path_set:
                            missing.add(fn)
                else:
                    missing.add(orig)

        cols[fn_col] = updated

        if acc_col is not None and len(cols) > acc_col:
            cols[acc_col] = normalize_access(cols[acc_col])

        updated_rows.append('\t'.join(cols))

    # Write output
    out_fh = open(args.out, 'w', encoding='utf-8') if args.out != '-' else sys.stdout
    for row in updated_rows:
        out_fh.write(row + '\n')
    if args.out != '-':
        out_fh.close()

    # Report
    print(f"\nDone:", file=sys.stderr)
    print(f"  Paths updated:  {changed}", file=sys.stderr)
    print(f"  Output lines:   {len(updated_rows)}", file=sys.stderr)

    if missing:
        print(f"\nMissing from paths.txt ({len(missing)} unique filenames):", file=sys.stderr)
        for fn in sorted(missing):
            print(f"  {fn}", file=sys.stderr)
    else:
        print("  No missing files.", file=sys.stderr)


if __name__ == '__main__':
    main()
# (appending nothing — file is complete)
