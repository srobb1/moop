#!/usr/bin/env python3
"""
copy_evidence_to_tracks.py — generate scp commands to copy evidence GFF files
from genomes server to tracks server.

Reads evidence.txt (listing of files on genomes) and outputs a shell script
of scp + mkdir commands to run from cerebro.

Usage:
  python3 scripts/copy_evidence_to_tracks.py [--evidence evidence.txt] [--out scp_evidence.sh]

  # Then on cerebro:
  #   ssh -MNf genomes && ssh -MNf tracks
  #   bash scp_evidence.sh
"""

import argparse
import os
import sys
from collections import defaultdict

TRACKS_BASE = '/var/www/privatehtml/moop/data/tracks'

# (tracks_organism, assembly, geneset_or_None)
# geneset=None → files go to assembly/gff/evidence/ directly
MAPPING = {
    'Anoura_caudifer':            ('Anoura_caudifer',             'GCA_004027475.1',               'SIMR_2025-01-24'),
    'Antrozous_pallidus':         ('Antrozous_pallidus',          'GCA_007922775.1',               'SIMR_2025-01-24'),
    'Artibeus_jamaicensis':       ('Artibeus_jamaicensis',        'GCF_021234435.1',               'RS_2023_03'),
    'Carollia_perspicillata':     ('Carollia_perspicillata',      'GCA_004027735.1',               'SIMR_2025-01-24'),
    'Craseonycteris_thonglongyai':('Craseonycteris_thonglongyai', 'GCA_004027555.1',               'SIMR_2025-01-24'),
    'Cynopterus_brachyotis':      ('Cynopterus_brachyotis',       'GCA_009793145.1',               'SIMR_2025-01-24'),
    'Desmodus_rotundus':          ('Desmodus_rotundus',           'GCF_022682495.1',               'RS_2023_02'),
    'Eidolon_dupreanum':          ('Eidolon_dupreanum',           'ASM46528v1',                    'SIMR_2025-01-24'),
    'Eidolon_helvum':             ('Eidolon_helvum',              'GCA_000465285.1',               'SIMR_2025-01-24'),
    'Eonycteris_spelaea':         ('Eonycteris_spelaea',          'GCA_003508835.1',               'SIMR_2025-01-24'),
    'Eptesicus_fuscus':           ('Eptesicus_fuscus',            'GCF_027574615.1',               'RS_2023_03'),
    'Hipposideros_armiger':       ('Hipposideros_armiger',        'GCF_001890085.2',               'RS_100'),
    'Hipposideros_galeritus':     ('Hipposideros_galeritus',      'GCA_004027415.1',               'SIMR_2025-01-24'),
    'Lasiurus_borealis':          ('Lasiurus_borealis',           'GCA_004026805.1',               'SIMR_2025-01-24'),
    'Lasiurus_cinereus':          ('Lasiurus_cinereus',           'GCA_011751095.1',               'SIMR_2025-01-23'),
    'Leptonycteris_nivalis':      ('Leptonycteris_nivalis',       'Lnivalis_consensus_genome',     'SIMR_2025-01-24'),
    'Leptonycteris_yerbabuenae':  ('Leptonycteris_yerbabuenae',  'Lyerbabuenae_genome',            'SIMR_2025-01-24'),
    'Macroglossus_sobrinus':      ('Macroglossus_sobrinus',       'GCA_004027375.1',               'SIMR_2025-01-24'),
    'Macrotus_californicus':      ('Macrotus_californicus',       'GCA_007922815.1',               'SIMR_2025-01-24'),
    'Macrotus_waterhousii':       ('Macrotus_waterhousii',        'Mwaterhousii_consensus_genome', 'SIMR_2025-01-24'),
    'Megaderma_lyra':             ('Megaderma_lyra',              'GCA_004026885.1',               'SIMR_2025-01-24'),
    'Micronycteris_hirsuta':      ('Micronycteris_hirsuta',       'GCA_004026765.1',               'SIMR_2025-01-24'),
    'Miniopterus_natalensis':     ('Miniopterus_natalensis',      'GCF_001595765.1',               'RS_100'),
    'Miniopterus_schreibersii':   ('Miniopterus_schreibersii',    'GCA_004026525.1',               'SIMR_2025-01-24'),
    'Mollosus_mollosus':          ('Molossus_molossus',           'GCF_014108415.1',               'RS_100'),
    'Mormoops_blainvillei':       ('Mormoops_blainvillei',        'GCA_004026545.1',               'SIMR_2025-01-24'),
    'Murina_aurata_feae':         ('Murina_feae',                 'GCA_004026665.1',               'SIMR_2025-01-24'),
    'Musonycteris_harrisonii':    ('Musonycteris_harrisoni',      'Mharrisoni_consensus_genome',   'SIMR_2025-01-24'),
    'Myotis_brandtii':            ('Myotis_brandtii',             'GCF_000412655.1',               'RS_101'),
    'Myotis_davidii':             ('Myotis_davidii',              'GCF_000327345.1',               'RS_101'),
    'Myotis_lucifugus':           ('Myotis_lucifugus',            'GCF_000147115.1',               'RS_102'),
    'Myotis_myotis':              ('Myotis_myotis',               'GCF_014108235.1',               'RS_100'),
    'Myotis_septentrionalis':     ('Myotis_septentrionalis',      'myse_ont_racon_pilon_HiC',      'SIMR_2025-01-24'),
    'Noctilio_leporinus':         ('Noctilio_leporinus',          'GCA_004026585.1',               'SIMR_2025-01-24'),
    'Nycticeius_humeralis':       ('Nycticeius_humeralis',        'GCA_007922795.1',               'SIMR_2025-01-24'),
    'Phyllostomus_discolor':      ('Phyllostomus_discolor',       'GCF_004126475.2',               'RS_101'),
    'Phyllostomus_hastatus':      ('Phyllostomus_hastatus',       'GCF_019186645.2',               'RS_100'),
    'Pipistrellus_kuhlii':        ('Pipistrellus_kuhlii',         'GCF_014108245.1',               'RS_101'),
    'Pipistrellus_pipistrellus':  ('Pipistrellus_pipistrellus',   'GCA_004026625.1',               'SIMR_2025-01-24'),
    'Pteropus_alecto':            ('Pteropus_alecto',             'GCF_000325575.1',               'RS_102'),
    'Pteropus_rufus':             ('Pteropus_rufus',              'Pteropus_rufus_HiC',            'SIMR_2025-01-24'),
    'Pteropus_vampyrus':          ('Pteropus_vampyrus',           'GCF_000151845.1',               'RS_101'),
    'Rhinolophus_ferrumequinum':  ('Rhinolophus_ferrumequinum',   'GCF_004115265.2',               'RS_2023_02'),
    'Rousettus_aegyptiacus':      ('Rousettus_aegyptiacus',       'GCF_014176215.1',               'Release-101'),
    'Rousettus_madagascariensis': ('Rousettus_madagascariensis',  'GCA_028533395.1',               'SIMR_2025-01-24'),
    'Sturnira_hondurensis':       ('Sturnira_hondurensis',        'GCF_014824575.3',               'Release-100.20210611'),
    'Tadarida_brasiliensis':      ('Tadarida_brasiliensis',       'GCA_004025005.1',               'SIMR_2025-01-24'),
    'Tonatia_saurophila':         ('Tonatia_saurophila',          'GCA_004024845.1',               'SIMR_2025-01-24'),
}

NOT_IN_TRACKS = {'Aeorestes_cinereus', 'Pteronotus_parnellii', 'Pteropus_giganteus'}

# Skip PVA1 — use PVA2 only for Pteropus_vampyrus
SKIP_SOURCE_PATTERNS = ['/Pteropus_vampyrus/PVA1/']


def dest_dir(tracks_org, assembly, geneset):
    if geneset:
        return f'{TRACKS_BASE}/{tracks_org}/{assembly}/{geneset}/gff/evidence'
    else:
        return f'{TRACKS_BASE}/{tracks_org}/{assembly}/gff/evidence'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--evidence', default='evidence.txt')
    ap.add_argument('--out',      default='scp_evidence.sh')
    args = ap.parse_args()

    commands = []       # (dest_dir, src_path, dest_path)
    skipped_no_tracks = defaultdict(list)
    skipped_pattern   = []

    with open(args.evidence) as fh:
        for line in fh:
            src = line.strip()
            if not src:
                continue

            # Skip directories and tarballs
            if not src.endswith('.gff'):
                continue

            # Skip explicitly excluded source patterns
            if any(pat in src for pat in SKIP_SOURCE_PATTERNS):
                skipped_pattern.append(src)
                continue

            # Extract organism from path: /var/other_data/organisms/bat/{Organism}/...
            parts = src.split('/')
            try:
                bat_idx = parts.index('bat')
                organism = parts[bat_idx + 1]
            except (ValueError, IndexError):
                print(f'# PARSE ERROR: {src}', file=sys.stderr)
                continue

            if organism in NOT_IN_TRACKS:
                skipped_no_tracks[organism].append(src)
                continue

            if organism not in MAPPING:
                print(f'# UNMAPPED: {organism}: {src}', file=sys.stderr)
                continue

            tracks_org, assembly, geneset = MAPPING[organism]
            ddir = dest_dir(tracks_org, assembly, geneset)
            fname = os.path.basename(src)
            commands.append((ddir, src, f'{ddir}/{fname}'))

    # Collect unique dest dirs for mkdir
    unique_dirs = sorted({c[0] for c in commands})

    with open(args.out, 'w') as fh:
        fh.write('#!/usr/bin/env bash\n')
        fh.write('# Evidence GFF copy: genomes → tracks\n')
        fh.write('# Run from cerebro with SSH ControlMaster open:\n')
        fh.write('#   ssh -MNf genomes && ssh -MNf tracks\n')
        fh.write('#   bash scp_evidence.sh\n\n')
        fh.write('set -euo pipefail\n\n')

        fh.write('# ── Create destination directories ──────────────────────────────────────\n')
        for d in unique_dirs:
            fh.write(f'ssh tracks mkdir -p {d}\n')
        fh.write('\n')

        # Group by organism for readability
        by_org = defaultdict(list)
        for ddir, src, dest in commands:
            org = src.split('/bat/')[1].split('/')[0]
            by_org[org].append((src, dest))

        fh.write('# ── Copy files ──────────────────────────────────────────────────────────\n')
        for org in sorted(by_org):
            tracks_org = MAPPING[org][0]
            fh.write(f'\n# {org}' + (f' → {tracks_org}' if tracks_org != org else '') + '\n')
            for src, dest in sorted(by_org[org]):
                fh.write(f'scp genomes:{src} tracks:{dest}\n')

    # Summary
    total = sum(len(v) for v in by_org.values())
    print(f'Wrote {args.out}', file=sys.stderr)
    print(f'  scp commands : {total}', file=sys.stderr)
    print(f'  dest dirs    : {len(unique_dirs)}', file=sys.stderr)
    print(f'  skipped PVA1 : {len(skipped_pattern)}', file=sys.stderr)
    if skipped_no_tracks:
        print(f'\nSkipped (not in tracks):', file=sys.stderr)
        for org, files in sorted(skipped_no_tracks.items()):
            print(f'  {org}: {len(files)} files', file=sys.stderr)


if __name__ == '__main__':
    main()
