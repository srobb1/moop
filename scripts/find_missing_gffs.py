#!/usr/bin/env python3
"""
find_missing_gffs.py — find GFF files referenced in metadata.contents.txt
that are NOT in tracks.old.files.txt (i.e., were never on the old tracks
server and need to be copied from genomes).

Usage:
    python3 scripts/find_missing_gffs.py \
        --metadata metadata.contents.txt \
        --old-files tracks.old.files.txt \
        [--dest-base /var/www/privatehtml/moop/data/tracks]

Output:
    For each organism, lists GFF filenames missing from old tracks, and
    (where the org dir maps to a new OrganismName/Accession) shows the
    expected destination path on the new tracks server.
"""

import argparse
import csv
import io
import re
import sys
from collections import defaultdict

# Organism map from migrate_tracks.sh  (dir_name -> (OrganismName, Accession))
# Duplicated here so the script is self-contained.
ORG_MAP = {
    # Cnidarians
    'MCAP_HIv3_v1': ('Montipora_capitata',    'HIv3'),
    'MCAP_v1':      ('Montipora_capitata',    'Mcap_2019'),
    'Nvec200_pub':  ('Nematostella_vectensis', 'GCA_033964005.1'),
    'Nvec200_v1':   ('Nematostella_vectensis', 'GCA_033964005.1'),
    'Nvec_v10':     ('Nematostella_vectensis', 'GCA_033964005.1'),
    'NV2_v1':       ('Nematostella_vectensis', None),
    'Scal100_v1':   ('Scolanthus_callimorphus','Scal100'),
    'Scal100_pub':  ('Scolanthus_callimorphus', None),
    'starlet_pub':  ('Nematostella_vectensis', 'GCA_033964005.1'),
    'wormanemone_pub': ('Scolanthus_callimorphus', None),
    # Acorn Worm  (note: MOOP dir is PflaM.kc1, not GCA_001465055.1 — verify if same assembly)
    'Pfla_v1':      ('Ptychodera_flava',       'PflaM.kc1'),
    # Flatworms
    'SmedSxl_v31':  ('Schmidtea_mediterranea', 'GCA_000691995.1'),
    'SmedSxl_dd_g4':('Schmidtea_mediterranea', 'GCA_002600895.1'),
    'SmedSxl_schMedS3_h1_internal': ('Schmidtea_mediterranea', 'schMedS3h1'),
    'SmedSxl_schMedS3_h2_internal': ('Schmidtea_mediterranea', None),
    'SmedSxl_smed_chr_ref_v1_internal': ('Schmidtea_mediterranea', None),
    # Jawed Fish
    'AME1_v1':      ('Astyanax_mexicanus',     None),
    'Amex_v10':     ('Astyanax_mexicanus',     'GCA_000372685.1'),
    'AstMex2_v1':   ('Astyanax_mexicanus',     'GCF_000372685.2'),
    'AstMex2_v2':   ('Astyanax_mexicanus',     'GCA_000372685.2'),
    'DanRer11':     ('Danio_rerio',            'GCF_000002035.6'),
    'DanRer11_ens': ('Danio_rerio',            'GCF_000002035.5'),
    'Drer_pub_v10': ('Danio_rerio',            'GCA_000002035.3'),
    'Drer_v10':     ('Danio_rerio',            'GCA_000002035.3'),
    'NfurGRZ-RIMD11': ('Nothobranchius_furzeri','GCF_043380555.1'),
    'Nfur_v10':     ('Nothobranchius_furzeri', 'GCF_001465895.1'),
    'Nfur_pub_v10': ('Nothobranchius_furzeri', None),
    'Nfur_v10_OLD': ('Nothobranchius_furzeri', None),
    'killifish_pub':('Nothobranchius_furzeri', None),
    # Lampreys
    'ETRf_v1':      ('Entosphenus_tridentatus','JAAXLI000000000.2'),
    'ETRm_v1':      ('Entosphenus_tridentatus','JAAVTP000000000.2'),
    'ETRm_pub':     ('Entosphenus_tridentatus', None),
    'kPetMar1_v1':  ('Petromyzon_marinus',     'GCF_010993605.1'),
    'kPetMar1_pub': ('Petromyzon_marinus',     'GCF_010993605.1'),
    'Lric_v1':      ('Lampetra_richardsoni',   'LPT'),
    'Lric_pub':     ('Lampetra_richardsoni',   None),
    'Pmar_v11':     ('Petromyzon_marinus',     'GCA_002833325.1'),
    'Pmar_pub_v11': ('Petromyzon_marinus',     None),
    # Mollusca
    'COKUS1KC_v1':  ('Congeria_kusceri',       'GCA_027627225.1'),
    'Pcan_v10':     ('Pomacea_canaliculata',   'GCF_003073045.1'),
    'Pcan_refseq_v1':('Pomacea_canaliculata',  'GCF_003073045.1'),
    # Reptiles
    'BraPum1_v1':   ('Bradypodion_pumilum',    'GCA_035047305.1'),
    'BraVen1_v1':   ('Bradypodion_ventrale',   'GCA_035047345.1'),
    'CCA1_v1':      ('Chamaeleo_calyptratus',  'CCA1'),
    'CCA1C_v1':     ('Chamaeleo_calyptratus',  'CCA1C'),
    'CCA2C_v1':     ('Chamaeleo_calyptratus',  'CCA2C'),
    'CCA3_v1':      ('Chamaeleo_calyptratus',  'CCA3'),
    'CCA3H1_v1':    ('Chamaeleo_calyptratus',  'CCA3H1'),
    'CCA3H2_v1':    ('Chamaeleo_calyptratus',  'CCA3H2'),
    'FurPar1_v1':   ('Furcifer_pardalis',      'GCA_030440675.1'),
    # Mammals
    'Mmus_v10':     ('Mus_musculus',           'GCF_000001635.27'),
    # Plant
    'MTR1_v1':      ('Medicago_truncatula',    'GCF_003473485.1'),
    # Bats
    'ACA1_v1':      ('Anoura_caudifer',              'GCA_004027475.1'),
    'ACI1_v1':      ('Lasiurus_cinereus',             'GCA_011751095.1'),  # Aeorestes cinereus = Lasiurus cinereus
    'AJA1_v1':      ('Artibeus_jamaicensis',          'GCF_021234435.1'),
    'APA1_v1':      ('Antrozous_pallidus',            'GCA_007922775.1'),
    'CBR1_v1':      ('Cynopterus_brachyotis',         'GCA_009793145.1'),
    'CPE1_v1':      ('Carollia_perspicillata',        'GCA_004027735.1'),
    'CTH1_v1':      ('Craseonycteris_thonglongyai',   'GCA_004027555.1'),
    'DRO1_v1':      ('Desmodus_rotundus',             'GCF_022682495.1'),
    'EDU1_v1':      ('Eidolon_dupreanum',             'ASM46528v1'),
    'EFU1_v1':      ('Eptesicus_fuscus',              'GCF_027574615.1'),
    'EHE1_v1':      ('Eidolon_helvum',               'GCA_000465285.1'),
    'ESP1_v1':      ('Eonycteris_spelaea',            'GCA_003508835.1'),
    'HAR1_v1':      ('Hipposideros_armiger',          'GCF_001890085.2'),
    'HGA1_v1':      ('Hipposideros_galeritus',        'GCA_004027415.1'),
    'LBO1_v1':      ('Lasiurus_borealis',             'GCA_004026805.1'),
    'LNI1_v1':      ('Leptonycteris_nivalis',         'Lnivalis_consensus_genome'),
    'LYE1_v1':      ('Leptonycteris_yerbabuenae',     'Lyerbabuenae_genome'),
    'MAU1_v1':      ('Murina_feae',                   'GCA_004026665.1'),
    'MBL1_v1':      ('Mormoops_blainvillei',          'GCA_004026545.1'),
    'MBR1_v1':      ('Myotis_brandtii',               'GCF_000412655.1'),
    'MCA1_v1':      ('Macrotus_californicus',         'GCA_007922815.1'),
    'MDA1_v1':      ('Myotis_davidii',               'GCF_000327345.1'),
    'MHA1_v1':      ('Musonycteris_harrisoni',        'Mharrisoni_consensus_genome'),
    'MHI1_v1':      ('Micronycteris_hirsuta',         'GCA_004026765.1'),
    'MLU1_v1':      ('Myotis_lucifugus',              'GCF_000147115.1'),
    'MLY1_v1':      ('Megaderma_lyra',                'GCA_004026885.1'),
    'MMO1_v1':      ('Molossus_molossus',             'GCF_014108415.1'),
    'MMY1_v1':      ('Myotis_myotis',                'GCF_014108235.1'),
    'MNA1_v1':      ('Miniopterus_natalensis',        'GCF_001595765.1'),
    'MSC1_v1':      ('Miniopterus_schreibersii',      'GCA_004026525.1'),
    'MSE1_v1':      ('Myotis_septentrionalis',        'myse_ont_racon_pilon_HiC'),
    'MSO1_v1':      ('Macroglossus_sobrinus',         'GCA_004027375.1'),
    'MWA1_v1':      ('Macrotus_waterhousii',          'Mwaterhousii_consensus_genome'),
    'NHU1_v1':      ('Nycticeius_humeralis',          'GCA_007922795.1'),
    'NLE1_v1':      ('Noctilio_leporinus',            'GCA_004026585.1'),
    'PAL1_v1':      ('Pteropus_alecto',              'GCF_000325575.1'),
    'PDI1_v1':      ('Phyllostomus_discolor',         'GCF_004126475.2'),
    'PGI1_v1':      ('Pteropus_medius',               'GCF_902729225.1'),  # Pteropus giganteus = Pteropus medius
    'PHA1_v1':      ('Phyllostomus_hastatus',         'GCF_019186645.2'),
    'PKU1_v1':      ('Pipistrellus_kuhlii',           'GCF_014108245.1'),
    'PPA1_v1':      ('Pteronotus_mesoamericanus',     'GCF_021234165.1'),  # P. parnellii = P. mesoamericanus
    'PPI1_v1':      ('Pipistrellus_pipistrellus',     'GCA_004026625.1'),
    'PRU1_v1':      ('Pteropus_rufus',                'Pteropus_rufus_HiC'),
    'PVA1_v1':      ('Pteropus_vampyrus',             'GCF_000151845.1'),
    'RAE1_v1':      ('Rousettus_aegyptiacus',         'GCF_014176215.1'),
    'RFE1_v1':      ('Rhinolophus_ferrumequinum',     'GCF_004115265.2'),
    'RMA1_v1':      ('Rousettus_madagascariensis',    'GCA_028533395.1'),
    'SHO1_v1':      ('Sturnira_hondurensis',          'GCF_014824575.3'),
    'TBR1_v1':      ('Tadarida_brasiliensis',         'GCA_004025005.1'),
    'TSA1_v1':      ('Tonatia_saurophila',            'GCA_004024845.1'),
}

GFF_EXT = re.compile(r'\.(gff3?|gtf)(\.gz)?$', re.IGNORECASE)


def parse_metadata(path):
    """
    Yield (org_dir, rel_filename) for every GFF-type filename in the
    metadata.contents.txt multi-CSV file.

    File format:
        ######
        /path/to/file.csv
        ######
        CSV rows...
        ######
        /next/file.csv
        ...

    org_dir is the old JBrowse data directory name (e.g. 'ACA1_v1').
    rel_filename is the value from the filename column (may include subdir).
    """
    # States: 'sep' (just saw ######), 'path' (saw path line), 'csv' (collecting rows)
    state = 'sep'
    current_dir = None
    csv_lines = []

    with open(path, encoding='latin-1') as fh:
        for line in fh:
            line = line.rstrip('\n')
            if line == '######':
                if state == 'csv' and current_dir and csv_lines:
                    yield from _parse_csv_block(current_dir, csv_lines)
                    current_dir = None
                    csv_lines = []
                    state = 'sep'
                elif state == 'path':
                    # second ###### after path → switch to csv collection
                    state = 'csv'
                else:
                    state = 'sep'
                continue

            if state == 'sep' and line.startswith('/var/www/privatehtml/'):
                m = re.search(r'/data/([^/]+)/includes/', line)
                if m:
                    current_dir = m.group(1)
                    state = 'path'
                continue

            if state == 'csv' and current_dir:
                csv_lines.append(line)

    # flush last block
    if current_dir and csv_lines:
        yield from _parse_csv_block(current_dir, csv_lines)


def _parse_csv_block(org_dir, lines):
    text = '\n'.join(lines)
    reader = csv.reader(io.StringIO(text))
    for row in reader:
        if len(row) < 16:
            continue
        filename = row[15].strip()
        if filename and GFF_EXT.search(filename) and filename != 'filename':
            yield (org_dir, filename)


def load_old_files(path):
    """
    Build a set of (org_dir, rel_filename) pairs from tracks.old.files.txt.
    Lines look like: /var/.../jbrowse/data/<DIR>/files/<rel_filename>
    """
    present = set()
    with open(path) as fh:
        for line in fh:
            line = line.strip()
            m = re.match(r'.*/data/([^/]+)/files/(.+)$', line)
            if m:
                present.add((m.group(1), m.group(2)))
    return present


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--metadata',  default='metadata.contents.txt')
    ap.add_argument('--old-files', default='tracks.old.files.txt')
    ap.add_argument('--dest-base', default='/var/www/privatehtml/moop/data/tracks')
    args = ap.parse_args()

    old = load_old_files(args.old_files)

    # Collect missing GFFs per org_dir
    missing = defaultdict(set)
    for org_dir, rel_fn in parse_metadata(args.metadata):
        if (org_dir, rel_fn) not in old:
            missing[org_dir].add(rel_fn)

    total = 0
    unmapped_orgs = set()
    for org_dir in sorted(missing):
        fns = sorted(missing[org_dir])
        info = ORG_MAP.get(org_dir)
        if info:
            org_name, accession = info
            if accession:
                dest_prefix = f"{args.dest_base}/{org_name}/{accession}"
            else:
                dest_prefix = f"[MISSING ACCESSION] {org_dir}"
        else:
            dest_prefix = f"[UNMAPPED] {org_dir}"
            unmapped_orgs.add(org_dir)

        print(f"\n## {org_dir}  →  {dest_prefix}/gff/")
        for fn in fns:
            print(f"  {fn}")
            total += 1

    print(f"\n{'='*60}")
    print(f"Total missing GFF files: {total}")
    if unmapped_orgs:
        print(f"Unmapped org dirs (add to ORG_MAP): {sorted(unmapped_orgs)}")


if __name__ == '__main__':
    main()
