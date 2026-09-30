#!/usr/bin/env python3
"""panther_placements.py -- where TreeGrafter places each protein on its PANTHER family tree, and
which human genes that makes it closest to. Input for gene naming (--panther-placements).

  python3 scripts/panther_placements.py --json interproscan_results.json[.gz] \
      --trees moop/panther/treegrafter/<release>/PANTHER<release>_data \
      --hmm-lengths moop/panther/hmm_lengths.tsv --taxonomy-dir moop/ncbi_taxonomy \
      --taxid <ncbi-taxon-id from metadata.yaml> --out panther_placements.tsv

InterProScan's JSON (not its TSV) gives, per PANTHER match, TreeGrafter's graft point: the node
of the family tree (a PTN id) the protein attaches to. PANTHER's TreeGrafter data give the tree
(PAINT_Annotations: PTN id -> tree node; Tree_MSF/<family>.tree: the Newick tree, speciation
and duplication events and taxa in NHX tags, and which gene each leaf is).

The human genes are found where the protein joins the human lineage:
  - speciation there -> "ortholog_1" (one human gene) or "co-orthologs" (several: copies from a
    duplication on the human side after the split); duplication there -> "paralog_family"
  - a graft inside a lineage the species cannot belong to (a vertebrate node for a mollusc --
    PANTHER has few invertebrates, so this is common) is moved up to the first speciation node
    of the species' own lineage (from NCBI taxonomy). Moving up only ever adds human genes.
  - "no_human": no human gene in reach; "lineage_not_in_tree": the family tree has no node of
    the species' lineage (a family PANTHER builds for vertebrates only); "no_graft": no graft
    point in the JSON (family-level match only)
One row per protein and PANTHER match, with the match's E-value and how much of the protein and
of the family model it covers (so naming can decide how far to trust it).
"""
import argparse, csv, datetime, gzip, json, os, re, sys

from interproscan_json import read_results

# PANTHER taxon names that are not NCBI names, and the NCBI name each stands for
PANTHER_TAXON_ALIAS = {
    'Opisthokonts': 'Opisthokonta', 'Unikonts': 'Amorphea', 'Eubacteria': 'Bacteria',
    'Cyanobacteria': 'Cyanobacteriota', 'Mesangiosperma': 'Mesangiospermae', 'BEP_clade': 'BOP clade',
}
ALWAYS_ON_LINEAGE = {'LUCA'}


def open_text(path):
    return gzip.open(path, 'rt') if path.endswith('.gz') else open(path)


def species_lineage(taxonomy_dir, taxid):
    """every name (all name classes) of the taxon and its ancestors"""
    parent = {}
    with open(os.path.join(taxonomy_dir, 'nodes.dmp')) as handle:
        for line in handle:
            fields = line.split('\t|\t')
            parent[fields[0]] = fields[1]
    merged = {}
    merged_file = os.path.join(taxonomy_dir, 'merged.dmp')
    if os.path.exists(merged_file):
        with open(merged_file) as handle:
            for line in handle:
                fields = line.rstrip('\t|\n').split('\t|\t')
                merged[fields[0]] = fields[1]
    taxid = merged.get(taxid, taxid)
    if taxid not in parent:
        sys.exit(f'ERROR: taxon {taxid} is not in {taxonomy_dir}/nodes.dmp')
    lineage_ids = []
    current = taxid
    while True:
        lineage_ids.append(current)
        if parent[current] == current:
            break
        current = parent[current]
    wanted = set(lineage_ids)
    names, scientific = set(), {}
    with open(os.path.join(taxonomy_dir, 'names.dmp')) as handle:
        for line in handle:
            fields = line.rstrip('\t|\n').split('\t|\t')
            if fields[0] in wanted:
                names.add(fields[1])
                if fields[3] == 'scientific name':
                    scientific[fields[0]] = fields[1]
    return names, [scientific.get(lineage_id, lineage_id) for lineage_id in reversed(lineage_ids)]


def on_lineage(taxon, lineage_names):
    """a PANTHER node taxon is on the species' lineage: "A-B" (the common ancestor of A and B)
    when A or B is"""
    if taxon in ALWAYS_ON_LINEAGE:
        return True
    return any(PANTHER_TAXON_ALIAS.get(part, part) in lineage_names for part in taxon.split('-'))


def load_paint(data_dir):
    """PTN id -> (family, tree node)"""
    node_of = {}
    with open(os.path.join(data_dir, 'PAINT_Annotations', 'PAINT_Annotatations_TOTAL.txt')) as handle:
        for line in handle:
            fields = [field.strip() for field in line.rstrip('\n').split('\t')]
            if len(fields) < 3:
                continue
            node, ptn = fields[0], fields[-1]
            family, name = node.split(':', 1)
            if name != 'root':                       # root repeats AN0's PTN
                node_of[ptn] = (family, name)
    return node_of


class FamilyTree:
    def __init__(self, path):
        with open(path) as handle:
            newick = handle.readline().strip()
            self.leaf = {}                           # node -> (species code, gene ids)
            for line in handle:
                match = re.match(r'(AN\d+):([A-Z0-9]+)\|(.*?);?$', line.strip())
                if match:
                    self.leaf[match.group(1)] = (match.group(2), match.group(3))
        self.parent, self.children, self.event, self.taxon = {}, {}, {}, {}
        label_pattern = re.compile(r'(AN\d+)?(?::[\d.eE+-]+)?(\[&&NHX:[^\]]*\])?')
        open_groups = [[]]
        position = 0
        while position < len(newick):
            character = newick[position]
            if character == '(':
                open_groups.append([])
                position += 1
                continue
            if character == ',':
                position += 1
                continue
            if character == ';':
                break
            closing = character == ')'
            if closing:
                position += 1
            match = label_pattern.match(newick, position)
            label, nhx = match.group(1), match.group(2)
            position = match.end() if match.end() > position else position + 1
            if nhx:
                tags = dict(item.split('=', 1) for item in nhx[6:-1].split(':') if '=' in item)
                label = tags.get('ID', label)
                if tags.get('Ev', '').startswith('1>0'):
                    self.event[label] = 'duplication'
                elif tags.get('Ev', '').startswith('0>1'):
                    self.event[label] = 'speciation'
                self.taxon[label] = tags.get('S', '')
            if closing:
                for child in open_groups.pop():
                    self.parent[child] = label
            if label:
                open_groups[-1].append(label)
        for child, node in self.parent.items():
            self.children.setdefault(node, []).append(child)

    def human_below(self, node):
        found, todo = [], [node]
        while todo:
            current = todo.pop()
            if current in self.leaf:
                if self.leaf[current][0] == 'HUMAN':
                    found.append(self.leaf[current][1])
            todo.extend(self.children.get(current, []))
        return found

    def kind(self, node):
        return 'leaf' if node in self.leaf else self.event.get(node, '?')

    def node_taxon(self, node):
        return self.leaf[node][0] if node in self.leaf else self.taxon.get(node, '')


def classify(tree, graft, lineage_names):
    """(placement, joining node, human gene id strings, moved up?)"""
    def lineage_speciation(node):
        return tree.event.get(node) == 'speciation' and on_lineage(tree.taxon.get(node, ''), lineage_names)

    def lineage_below(node):
        todo = [node]
        while todo:
            current = todo.pop()
            if lineage_speciation(current):
                return True
            todo.extend(tree.children.get(current, []))
        return False

    node, moved = graft, False
    if not lineage_below(node):
        # grafted inside another lineage: up to the first speciation node of the species' own
        while tree.parent.get(node) and not lineage_speciation(node):
            node = tree.parent[node]
        moved = node != graft
        if not lineage_speciation(node):
            return 'lineage_not_in_tree', node, tree.human_below(node), moved
    if tree.event.get(node) == 'duplication':        # grafted onto a duplication older than the split
        return 'paralog_family', node, tree.human_below(node), moved
    humans = tree.human_below(node)
    while not humans and tree.parent.get(node):       # no human gene on the protein's side: go up
        node = tree.parent[node]
        humans = tree.human_below(node)
    if not humans:
        return 'no_human', node, [], moved
    if tree.event.get(node) == 'duplication':
        return 'paralog_family', node, humans, moved
    return ('ortholog_1' if len(humans) == 1 else 'co-orthologs'), node, humans, moved


def human_gene_id(gene_ids):
    """a human leaf's gene: HGNC:n, else its Ensembl gene, else its UniProt accession"""
    fields = dict(item.split('=', 1) for item in gene_ids.split('|') if '=' in item)
    if 'HGNC' in fields:
        return 'HGNC:' + fields['HGNC']
    if 'Ensembl' in fields:
        return fields['Ensembl'].split('.')[0]
    return 'UniProtKB:' + fields.get('UniProtKB', gene_ids)


def coverage(match, protein_length, hmm_length):
    protein_positions, model_positions = set(), set()
    for location in match.get('locations', []):
        protein_positions.update(range(location['start'], location['end'] + 1))
        if location.get('hmmStart') and location.get('hmmEnd'):
            model_positions.update(range(location['hmmStart'], location['hmmEnd'] + 1))
    family = match['signature']['accession']
    protein_cov = round(100 * len(protein_positions) / protein_length) if protein_length else ''
    model_cov = min(100, round(100 * len(model_positions) / hmm_length[family])) if hmm_length.get(family) else ''
    return protein_cov, model_cov


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--json', required=True)
    parser.add_argument('--trees', required=True, help='PANTHER<release>_data from the TreeGrafter download')
    parser.add_argument('--hmm-lengths', required=True)
    parser.add_argument('--taxonomy-dir', required=True)
    parser.add_argument('--taxid', required=True)
    parser.add_argument('--out', required=True)
    args = parser.parse_args()

    lineage_names, lineage = species_lineage(args.taxonomy_dir, args.taxid)
    node_of = load_paint(args.trees)
    hmm_length = {}
    with open(args.hmm_lengths) as handle:
        for line in handle:
            fields = line.split()
            if len(fields) == 2 and not line.startswith('#') and fields[1].isdigit():
                hmm_length[fields[0]] = int(fields[1])
    trees = {}

    def family_tree(family):
        if family not in trees:
            path = os.path.join(args.trees, 'Tree_MSF', f'{family}.tree')
            trees[family] = FamilyTree(path) if os.path.exists(path) else None
        return trees[family]

    # the JSON is read one protein result at a time (a gene set's is gigabytes)
    _, results = read_results(args.json)
    release = ''
    columns = ['protein', 'panther_match', 'match_name', 'evalue', 'protein_cov_pct', 'model_cov_pct', 'graft_point',
               'graft_node', 'graft_event', 'graft_taxon', 'joining_node', 'joining_event', 'joining_taxon',
               'moved_to_lineage', 'placement', 'human_genes']
    rows = []
    for result in results:
        protein_length = len(result.get('sequence', ''))
        protein_ids = [xref['id'] for xref in result.get('xref', [])]
        for match in result.get('matches', []):
            library = match['signature']['signatureLibraryRelease']
            if library['library'] != 'PANTHER':
                continue
            release = library['version']
            protein_cov, model_cov = coverage(match, protein_length, hmm_length)
            graft = match.get('graftPoint') or ''
            common = [match.get('accession', ''), (match.get('name') or '').replace('\t', ' '), str(match.get('evalue', '')),
                      str(protein_cov), str(model_cov), graft]
            tree = family_tree(node_of[graft][0]) if graft in node_of else None
            if tree is None:
                placed = [''] * 7 + ['no_graft', '']
            else:
                family, graft_node = node_of[graft]
                placement, node, humans, moved = classify(tree, graft_node, lineage_names)
                placed = [f'{family}:{graft_node}', tree.kind(graft_node), tree.node_taxon(graft_node),
                          f'{family}:{node}', tree.kind(node), tree.node_taxon(node), 'yes' if moved else 'no',
                          placement, ';'.join(sorted(set(human_gene_id(gene) for gene in humans)))]
            for protein_id in protein_ids:
                rows.append([protein_id] + common + placed)
    rows.sort()
    with open(args.out, 'w') as out:
        out.write(f'# PANTHER placements (TreeGrafter graft points traced to human genes), written '
                  f'{datetime.date.today().isoformat()} by {os.path.abspath(__file__)}\n')
        out.write(f'# InterProScan JSON: {os.path.abspath(args.json)}; PANTHER {release}; trees: {os.path.abspath(args.trees)}\n')
        out.write(f'# species lineage (NCBI taxon {args.taxid}): {"; ".join(lineage)}\n')
        out.write('\t'.join(columns) + '\n')
        for row in rows:
            out.write('\t'.join(row) + '\n')
    counts = {}
    for row in rows:
        counts[row[14]] = counts.get(row[14], 0) + 1
    print(f'{args.out}: {len(rows)} PANTHER matches; ' + ', '.join(f'{key} {value}' for key, value in sorted(counts.items())))


if __name__ == '__main__':
    main()
