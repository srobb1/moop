#!/usr/bin/env python3
"""Does organism.sqlite hold exactly what its annotation files say? Source by source.

For each annotation source (name, version): the (feature, accession) pairs the moop files hold -- each
gene id resolved as load_annotations_sqlite.pl resolves it (first feature of that uniquename, then
:pep / :cds, then the .id_prefix_stripped rewrite; then floated up to its transcript, or the root of
its parent chain) -- against the pairs the database links to that source.

A source fails when:
  MERGED TYPES            two files of different annotation types share its name and version (the
                          database keeps one source per name and version, so their rows mix: the
                          eross RBBH / DIAMOND homologs merge found 2026-10-06 in 60 organisms)
  MISMATCH                pairs in a file are missing from the database, or the database has pairs
                          no file holds
  FILE NOT LOADED         a file's source is not in the database at all
  DB SOURCE WITH NO FILE  the database has a source no file of these gene sets supplies (an old load)
Gene ids that resolve to no feature are counted (the loader skips them too), not failed.

Only the files the build loads count: the load_files patterns of setup_new_moopdb_and_load_data.sh,
read from that script (the eross RBBH pattern only with LOAD_EROSS_RBBH=1). Gene statement files load
into gene_naming, not annotation, and are left out. Read-only: the database is opened with mode=ro.

usage: audit_files_vs_db.py organism.sqlite GENE_SET_DIR [GENE_SET_DIR ...] [--report OUT.tsv]
       (the gene set directories of this organism the build loaded)
exit:  0 every source matches; 1 at least one fails; 2 usage or database error
"""
import collections
import fnmatch
import glob
import gzip
import os
import re
import sqlite3
import sys

SETUP = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'setup_new_moopdb_and_load_data.sh')


def load_patterns():
    """The load_files patterns, in the order the setup script uses them; the eross one only when asked."""
    patterns = []
    for line in open(SETUP):
        match = re.match(r'\s*load_files "([^"]+)"(?:\s+"[^"]*"(?:\s+"([^"]*)")?)?', line)
        if not match:
            continue
        if 'eross' in line and os.environ.get('LOAD_EROSS_RBBH', '0') != '1':
            continue
        patterns.append((match.group(1), match.group(2)))
    return patterns


def loaded(name, patterns):
    if name.startswith('gene_statement.'):
        return False
    return any(fnmatch.fnmatch(name, pattern) and not (exclude and fnmatch.fnmatch(name, exclude))
               for pattern, exclude in patterns)


def header_of(path):
    info = {}
    opener = gzip.open if path.endswith('.gz') else open
    with opener(path, 'rt', errors='replace') as fh:
        for number, line in enumerate(fh):
            if not line.startswith('#') or number > 40:
                break
            for key, label in (('source', '## Annotation Source:'), ('version', '## Annotation Source Version:'),
                               ('type', '## Annotation Type:'), ('naming', '## Naming Kind:')):
                if line.startswith(label):
                    info[key] = line.split(':', 1)[1].strip()
    return info


def rows_of(path):
    opener = gzip.open if path.endswith('.gz') else open
    with opener(path, 'rt', errors='replace') as fh:
        for line in fh:
            if line.startswith('#') or not line.strip():
                continue
            fields = [field.strip() for field in line.rstrip('\n').split('\t')]
            if len(fields) >= 2:
                yield fields[0], fields[1]


def main(argv):
    report_path = None
    if '--report' in argv:
        at = argv.index('--report')
        report_path = argv[at + 1]
        argv = argv[:at] + argv[at + 2:]
    if len(argv) < 2:
        sys.stderr.write(__doc__)
        return 2
    db_path, gene_set_dirs = argv[0], argv[1:]
    patterns = load_patterns()
    if not patterns:
        sys.stderr.write(f'no load_files patterns found in {SETUP}\n')
        return 2

    try:
        db = sqlite3.connect(f'file:{db_path}?mode=ro', uri=True)
        # as load_annotations_sqlite.pl: the first feature of a uniquename; an annotation floats up to its
        # transcript (mRNA/transcript), else to the root of its parent chain (find_annotation_target)
        feature, feature_type, parent = {}, {}, {}
        for fid, uniquename, ftype, parent_fid in db.execute(
                'select feature_id, feature_uniquename, feature_type, parent_feature_id from feature'):
            if uniquename is not None and uniquename not in feature:
                feature[uniquename] = fid
            feature_type[fid] = ftype or ''
            if parent_fid is not None:
                parent[fid] = parent_fid
        sources = {(name.strip(), (version or '').strip()): (sid, atype) for sid, name, version, atype in db.execute(
            'select annotation_source_id, annotation_source_name, annotation_source_version, annotation_type from annotation_source')}
        db_pairs = collections.defaultdict(set)
        for sid, fid, accession in db.execute('select a.annotation_source_id, fa.feature_id, a.annotation_accession '
                                              'from feature_annotation fa join annotation a using(annotation_id)'):
            db_pairs[sid].add((fid, accession))
    except sqlite3.Error as error:
        sys.stderr.write(f'audit_files_vs_db: cannot read {db_path}: {error}\n')
        return 2

    target_cache = {}

    def target(fid):
        if fid in target_cache:
            return target_cache[fid]
        current, last, seen = fid, fid, set()
        while current is not None:
            if current in seen:
                target_cache[fid] = fid
                return fid
            seen.add(current)
            if feature_type.get(current) in ('mRNA', 'transcript'):
                target_cache[fid] = current
                return current
            last, current = current, parent.get(current)
        target_cache[fid] = last
        return last

    file_pairs = collections.defaultdict(set)
    file_types = collections.defaultdict(set)
    file_names = collections.defaultdict(list)
    unresolved = collections.Counter()
    for gene_set_dir in gene_set_dirs:
        strip = add = ''
        manifest = os.path.join(gene_set_dir, '.id_prefix_stripped')
        if os.path.isfile(manifest):
            strip, add = (open(manifest).readline().rstrip('\n').split('\t') + ['', ''])[:2]
        for path in sorted(glob.glob(os.path.join(gene_set_dir, '*.moop.tsv')) + glob.glob(os.path.join(gene_set_dir, '*.moop.txt'))):
            if not loaded(os.path.basename(path), patterns):
                continue
            info = header_of(path)
            if 'naming' in info or 'source' not in info or 'type' not in info:
                continue
            key = (info['source'], info.get('version', ''))
            file_types[key].add(info['type'])
            file_names[key].append(os.path.relpath(path, os.path.dirname(gene_set_dir.rstrip('/'))))
            for uid, accession in rows_of(path):
                candidates = [uid, uid + ':pep', uid + ':cds']
                if (strip or add) and uid.startswith(strip):
                    rewritten = add + uid[len(strip):]
                    candidates += [rewritten, rewritten + ':pep', rewritten + ':cds']
                fid = next((feature[c] for c in candidates if c in feature), None)
                if fid is None:
                    unresolved[key] += 1
                else:
                    file_pairs[key].add((target(fid), accession))

    rows, failed = [], collections.Counter()
    for key in sorted(set(file_types) | set(sources)):
        sid, db_type = sources.get(key, (None, ''))
        have = db_pairs.get(sid, set()) if sid else set()
        want = file_pairs.get(key, set())
        missing, extra = len(want - have), len(have - want)
        types = file_types.get(key, set())
        if sid is None:
            status = 'FILE NOT LOADED'
        elif not types:
            status = 'DB SOURCE WITH NO FILE'
        elif len(types) > 1:
            status = 'MERGED TYPES'
        elif missing or extra:
            status = 'MISMATCH'
        else:
            status = 'ok'
        if status != 'ok':
            failed[status] += 1
        rows.append([key[0], key[1], '|'.join(sorted(types)), db_type, ', '.join(file_names.get(key, [])) or '-',
                     len(want), unresolved.get(key, 0), len(have), missing, extra, status])

    if report_path:
        with open(report_path, 'w') as out:
            out.write('\t'.join(['source', 'version', 'file_types', 'db_type', 'files', 'file_pairs', 'unresolved_ids',
                                 'db_pairs', 'missing_from_db', 'db_not_in_files', 'status']) + '\n')
            for row in rows:
                out.write('\t'.join(map(str, row)) + '\n')
    for row in rows:
        if row[-1] != 'ok':
            print(f'{row[-1]}: "{row[0]}" ({row[1]}) -- files {row[4]} ({row[2] or "none"}): {row[5]} pairs, '
                  f'database ({row[3] or "none"}): {row[7]}; missing {row[8]}, extra {row[9]}')
    total_unresolved = sum(unresolved.values())
    print(f'audit_files_vs_db: {len(rows)} sources, {len(rows) - sum(failed.values())} match their files'
          + (f'; FAILED: {dict(failed)}' if failed else '')
          + (f'; {total_unresolved} file rows name no feature (skipped by the loader too)' if total_unresolved else ''))
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
