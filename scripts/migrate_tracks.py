#!/usr/bin/env python3
"""
migrate_tracks.py — generate a shell script to move track files from the old
per-assembly layout to the new OrganismName/Accession/files layout.

Usage:
  python3 migrate_tracks.py tracks.old.files.txt            # generate shell script
  python3 migrate_tracks.py tracks.old.files.txt --dry-run  # human-readable preview

  Redirect output to a file, review it, then run it:
    python3 migrate_tracks.py tracks.old.files.txt > migrate_tracks.sh
    bash migrate_tracks.sh

Directories with None accessions are skipped — fill them in below and re-run.
"""

import argparse, sys
from pathlib import Path

# ── Config ────────────────────────────────────────────────────────────────────

DEST_BASE  = '/var/www/privatehtml/moop/data/tracks'
OLD_PREFIX = '/var/www/privatehtml/simrbase_tracks/jb_gh/jbrowse/data'

# old_dir_name -> (OrganismName, Accession)
# Set accession to None to skip that directory (a warning is printed).
ORGANISM_MAP = {
    # ── Cnidarians ──────────────────────────────────────────────────────────
    'MCAP_v1':                        ('Montipora_capitata',       None),               # TODO accession
    'Nvec200_pub':                    ('Nematostella_vectensis',   'GCA_932526225.1'),
    'Nvec_v10':                       ('Nematostella_vectensis',   None),               # TODO: Nemve1
    'NV2_v1':                         ('Nematostella_vectensis',   None),               # TODO: NV2
    'Scal100_v1':                     ('Scolanthus_callimorphus',  None),               # TODO accession
    'Scal100_pub':                    ('Scolanthus_callimorphus',  None),               # TODO accession
    'starlet_pub':                    ('Nematostella_vectensis',   'GCA_932526225.1'),
    'wormanemone_pub':                ('Scolanthus_callimorphus',  None),               # TODO accession

    # ── Acorn Worm ──────────────────────────────────────────────────────────
    'Pfla_v1':                        ('Ptychodera_flava',         None),               # TODO accession

    # ── Flatworms ───────────────────────────────────────────────────────────
    'SmedSxl_v31':                    ('Schmidtea_mediterranea',   'GCA_000691995.1'),
    'SmedSxl_dd_g4':                  ('Schmidtea_mediterranea',   None),               # TODO: dd_Smes_G4
    'SmedSxl_schMedS3_h1_internal':   ('Schmidtea_mediterranea',   None),               # TODO: schMedS3_h1

    # ── Jawed Fish ──────────────────────────────────────────────────────────
    'Amex_v10':                       ('Astyanax_mexicanus',       'GCA_000372685.1'),
    'AstMex2_v1':                     ('Astyanax_mexicanus',       'GCF_000372685.2'),
    'AstMex2_v2':                     ('Astyanax_mexicanus',       'GCA_000372685.2'),
    'DanRer11':                       ('Danio_rerio',              'GCF_000002035.6'),
    'DanRer11_ens':                   ('Danio_rerio',              None),               # TODO: GCRz11 Ensembl
    'Drer_pub_v10':                   ('Danio_rerio',              'GCA_000002035.3'),
    'Drer_v10':                       ('Danio_rerio',              'GCA_000002035.3'),
    'NfurGRZ-RIMD11':                 ('Nothobranchius_furzeri',   'GCA_043380555.1'),
    'Nfur_v10':                       ('Nothobranchius_furzeri',   'GCF_001465895.1'),
    'Nfur_pub_v10':                   ('Nothobranchius_furzeri',   None),               # TODO: Nfu_20150522 pub
    'Nfur_v10_OLD':                   ('Nothobranchius_furzeri',   None),               # TODO: old assembly
    'killifish_pub':                  ('Nothobranchius_furzeri',   None),               # TODO: pub accession

    # ── Lampreys ────────────────────────────────────────────────────────────
    'ETRm_v1':                        ('Entosphenus_tridentatus',  None),               # TODO: male germline
    'ETRm_pub':                       ('Entosphenus_tridentatus',  None),               # TODO: pub accession
    'kPetMar1_v1':                    ('Petromyzon_marinus',       'GCF_010993605.1'),
    'kPetMar1_pub':                   ('Petromyzon_marinus',       'GCF_010993605.1'),
    'Lric_v1':                        ('Lampetra_richardsoni',     None),               # TODO accession
    'Lric_pub':                       ('Lampetra_richardsoni',     None),               # TODO accession
    'Pmar_v11':                       ('Petromyzon_marinus',       None),               # TODO: gPmar100
    'Pmar_pub_v11':                   ('Petromyzon_marinus',       None),               # TODO: pub accession

    # ── Mollusca ────────────────────────────────────────────────────────────
    'COKUS1KC_v1':                    ('Congeria_kusceri',         None),               # TODO accession
    'Pcan_v10':                       ('Pomacea_canaliculata',     None),               # TODO: old assembly
    'Pcan_refseq_v1':                 ('Pomacea_canaliculata',     'GCF_003073045.1'),

    # ── Reptiles ────────────────────────────────────────────────────────────
    'CCA1_v1':                        ('Chamaeleo_calyptratus',    None),               # TODO: CCA1
    'CCA1C_v1':                       ('Chamaeleo_calyptratus',    None),               # TODO: CCA1C
    'CCA2C_v1':                       ('Chamaeleo_calyptratus',    None),               # TODO: CCA2C

    # ── Mouse ───────────────────────────────────────────────────────────────
    'Mmus_v10':                       ('Mus_musculus',             None),               # TODO: GRCm39?

    # ── Plant ───────────────────────────────────────────────────────────────
    'MTR1_v1':                        ('Medicago_truncatula',      'GCF_003473485.1'),
}


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('files', help='Path to tracks.old.files.txt')
    ap.add_argument('--dry-run', action='store_true',
                    help='Check existence and print a human-readable preview; do not output a shell script')
    args = ap.parse_args()

    lines = Path(args.files).read_text(encoding='utf-8').splitlines()

    commands        = []    # (old_path, new_path)
    skipped_acc     = {}    # dir_name -> count of files skipped (no accession)
    unmapped        = set()
    src_missing     = []    # source files that don't exist on disk
    dest_exists     = []    # destination files that already exist (would overwrite)
    parse_errors    = []

    for line in lines:
        line = line.strip()
        if not line:
            continue

        if not line.startswith(OLD_PREFIX + '/'):
            parse_errors.append(f'unexpected prefix: {line}')
            continue

        rel   = line[len(OLD_PREFIX) + 1:]
        parts = rel.split('/', 2)
        if len(parts) < 3:
            parse_errors.append(f'no subdir: {line}')
            continue

        dir_name, subdir, filename = parts
        if subdir != 'files':
            parse_errors.append(f'subdir "{subdir}" != "files": {line}')
            continue

        if dir_name not in ORGANISM_MAP:
            unmapped.add(dir_name)
            continue

        organism, accession = ORGANISM_MAP[dir_name]
        if accession is None:
            skipped_acc[dir_name] = skipped_acc.get(dir_name, 0) + 1
            continue

        dest_dir = f'{DEST_BASE}/{organism}/{accession}/files'
        new_path = f'{dest_dir}/{filename}'
        commands.append((line, new_path))

    # ── Existence checks ─────────────────────────────────────────────────────
    if not Path(DEST_BASE).exists():
        print(f'ERROR: DEST_BASE does not exist: {DEST_BASE}', file=sys.stderr)
        sys.exit(1)

    for old, new in commands:
        if not Path(old).exists():
            src_missing.append(old)
        if Path(new).exists():
            dest_exists.append(new)

    # ── Group by dest dir ────────────────────────────────────────────────────
    from collections import defaultdict
    by_dest = defaultdict(list)
    for old, new in commands:
        by_dest[str(Path(new).parent)].append((old, new))

    # ── Dry-run: human-readable preview ──────────────────────────────────────
    if args.dry_run:
        print(f'DRY RUN — no files will be moved\n')
        print(f'DEST_BASE : {DEST_BASE}  ({"EXISTS" if Path(DEST_BASE).exists() else "MISSING"})')
        print(f'Files list: {args.files}\n')

        for dest_dir in sorted(by_dest):
            org_acc  = dest_dir.replace(DEST_BASE + '/', '', 1)
            dir_ok   = '(dir exists)' if Path(dest_dir).exists() else '(will create)'
            print(f'  {org_acc}  {dir_ok}')
            for old, new in sorted(by_dest[dest_dir]):
                src_flag  = '' if Path(old).exists() else '  [SRC MISSING]'
                dest_flag = '  [DEST EXISTS — would overwrite]' if Path(new).exists() else ''
                print(f'    {Path(old).name}{src_flag}{dest_flag}')
        print()

        print(f'Summary:')
        print(f'  Files to move   : {len(commands)}')
        print(f'  Assemblies      : {len(by_dest)}')
        print(f'  Src missing     : {len(src_missing)}')
        print(f'  Dest conflicts  : {len(dest_exists)}')
        print(f'  Skipped (no acc): {sum(skipped_acc.values())} files across {len(skipped_acc)} dirs')
        print(f'  Unmapped dirs   : {len(unmapped)}')

        if skipped_acc:
            print(f'\nDirectories skipped — fill in accession in ORGANISM_MAP to include:')
            for d, n in sorted(skipped_acc.items()):
                print(f'  {d}: {n} files')

        if unmapped:
            print(f'\nUnmapped directories — add to ORGANISM_MAP:')
            for d in sorted(unmapped):
                print(f'  {d}')

        if parse_errors:
            print(f'\nParse errors:')
            for e in parse_errors:
                print(f'  {e}')

        return

    # ── Shell script output ───────────────────────────────────────────────────
    print('#!/usr/bin/env bash')
    print('# Generated by scripts/migrate_tracks.py')
    print('# Review before running. Uses mv — destructive.')
    print(f'# Source files missing from disk  : {len(src_missing)}')
    print(f'# Destination conflicts (overwrite): {len(dest_exists)}')
    print()
    print('set -euo pipefail')
    print()

    for dest_dir in sorted(by_dest):
        org_acc = dest_dir.replace(DEST_BASE + '/', '', 1)
        print(f'# ── {org_acc} ──')
        print(f'mkdir -p {dest_dir!r}')
        for old, new in sorted(by_dest[dest_dir]):
            print(f'mv {old!r} {new!r}')
        print()

    # Summary to stderr
    print(f'\nSummary:', file=sys.stderr)
    print(f'  Files to move   : {len(commands)}', file=sys.stderr)
    print(f'  Assemblies      : {len(by_dest)}', file=sys.stderr)
    print(f'  Src missing     : {len(src_missing)}', file=sys.stderr)
    print(f'  Dest conflicts  : {len(dest_exists)}', file=sys.stderr)
    print(f'  Skipped (no acc): {sum(skipped_acc.values())} files in {len(skipped_acc)} dirs', file=sys.stderr)
    print(f'  Unmapped dirs   : {len(unmapped)}', file=sys.stderr)

    if skipped_acc:
        print(f'\nDirectories skipped (no accession in ORGANISM_MAP):', file=sys.stderr)
        for d, n in sorted(skipped_acc.items()):
            print(f'  {d}: {n} files', file=sys.stderr)

    if unmapped:
        print(f'\nUnmapped directories:', file=sys.stderr)
        for d in sorted(unmapped):
            print(f'  {d}', file=sys.stderr)


if __name__ == '__main__':
    main()
