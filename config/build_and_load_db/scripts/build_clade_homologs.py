#!/usr/bin/env python3
"""Build homologs.sqlite (format clade_homologs/1, docs/CLADE_HOMOLOGS_FORMAT.md) from one OrthoFinder run.

    build_clade_homologs.py --run <run folder> --out <data>/.clades/<clade>/homologs.sqlite \
        [--taxonomy <NCBI taxdump folder>] [--label "Planarians (Tricladida)"] [--results <Results_ folder>]

<run folder> is a clade run set up by make_clade_set.py (orthofinder_v2): members.tsv (member, MOOP organism,
gene set, protein FASTA as MOOP ships it + its md5), clade.tsv (clade, taxon, label; optional) and results/.
The newest complete results/Results_* is used (complete = Statistics_Overall.tsv written) unless --results.

What goes in (all IDs are MOOP gene IDs, the FASTA headers of the run's input):
  member  the members.tsv rows; protein_md5 = md5 of MOOP's protein.aa.fa for that gene set
  gene    every gene of the run, with its orthogroup (NULL when OrthoFinder left it unassigned)
  hog     each gene's HOG at every node (Phylogenetic_Hierarchical_Orthogroups/N*.tsv). A 2-species run has
          no HOG files (one node): its N0 HOGs are then its orthogroups, N0.HOG<n> for OG<n>, and
          meta hog_source says so
  node    the rooted species tree (Species_Tree/SpeciesTree_rooted_node_labels.txt); label = NCBI name of the
          lowest common taxon of the node's members (with --taxonomy), else NULL
  pair    Orthologues/<A>.tsv in both directions, kind read from A's side; plus 1:1 pairs for the orthogroups
          that are single-copy in every member (OrthoFinder 3 leaves those out of Orthologues/)
  meta    format, clade, label, taxon_id, orthofinder_version, run_date, built_at, counts

Built next to --out under a temporary name, checked (integrity_check = ok, counts > 0) and renamed into place.
"""
import argparse
import csv
import datetime
import glob
import gzip
import os
import re
import sqlite3
import sys

FORMAT = "clade_homologs/1"      # without pair evidence (--diamond none)
FORMAT_EVIDENCE = "clade_homologs/2"   # pair.support/evalue/bitscore/pident/cov_a/cov_b, gene.protein


def die(msg):
    sys.exit(f"ERROR: {msg}")


def split_genes(cell):
    return [g for g in (cell or "").split(", ") if g]


def read_tsv(path):
    with open(path, newline="") as fh:
        rows = list(csv.reader(fh, delimiter="\t"))
    return rows[0], rows[1:]


def kv_file(path):
    out = {}
    if os.path.exists(path):
        for line in open(path):
            if line.strip() and not line.startswith("#"):
                k, _, v = line.rstrip("\n").partition("\t")
                out[k] = v
    return out


def pick_results(run, explicit):
    if explicit:
        cands = [explicit]
    else:
        cands = sorted(glob.glob(os.path.join(run, "results", "Results_*")), key=os.path.getmtime, reverse=True)
    for r in cands:
        if os.path.exists(os.path.join(r, "Comparative_Genomics_Statistics", "Statistics_Overall.tsv")) or \
                (is_fastoma(r) and os.path.exists(os.path.join(r, "orthologs.tsv.gz"))):
            return r
    die(f"no complete results in {run}/results (OrthoFinder: Statistics_Overall.tsv; FastOMA: RootHOGs.tsv + orthologs.tsv.gz)")


def read_members(run):
    hdr, rows = read_tsv(os.path.join(run, "members.tsv"))
    col = {h: i for i, h in enumerate(hdr)}
    name_col = "member" if "member" in col else "organism"          # older members.tsv: member = organism
    org_col = "moop_organism" if "moop_organism" in col else "organism"
    members = {}
    for r in rows:
        fasta = r[col["protein_fasta"]]
        parts = fasta.rstrip("/").split("/")                         # .../data/<Org>/<assembly>/<gene_set>/protein.aa.fa
        if len(parts) < 4 or parts[-1] != "protein.aa.fa" or parts[-2] != r[col["gene_set"]]:
            die(f"members.tsv: cannot read the assembly from {fasta}")
        members[r[col[name_col]]] = {
            "organism": r[col[org_col]], "assembly": parts[-3], "gene_set": r[col["gene_set"]],
            "taxon_id": int(r[col["taxon_id"]]), "protein_md5": r[col["protein_fasta_md5"]],
            "genes_expected": int(r[col["genes"]]),
        }
    return members


def parse_newick_nodes(text):
    """(node label, parent label or None, [leaf names under it]) for each internal node of a labelled newick"""
    text = re.sub(r":[0-9.eE+-]+", "", text.strip().rstrip(";"))
    pos, out = 0, []

    def parse(parent):
        nonlocal pos
        if text[pos] == "(":
            pos += 1
            kids = []
            while True:
                kids.append(parse(None))
                if text[pos] == ",":
                    pos += 1
                    continue
                if text[pos] == ")":
                    pos += 1
                    break
            m = re.match(r"[^,();]*", text[pos:])
            label = m.group(0)
            pos += len(label)
            leaves = [l for k in kids for l in k[1]]
            for k in kids:
                if k[0] is not None:
                    k[2]["parent"] = label
            rec = {"node": label, "parent": None, "leaves": leaves}
            out.append(rec)
            return (label, leaves, rec)
        m = re.match(r"[^,();]*", text[pos:])
        pos += len(m.group(0))
        return (None, [m.group(0)], {})

    parse(None)
    return out


def load_taxonomy(taxdir):
    parent, sci = {}, {}
    for line in open(os.path.join(taxdir, "nodes.dmp")):
        f = line.split("\t|\t")
        parent[int(f[0])] = int(f[1])
    for line in open(os.path.join(taxdir, "names.dmp")):
        f = line.rstrip("\t|\n").split("\t|\t")
        if f[3] == "scientific name":
            sci[int(f[0])] = f[1]
    return parent, sci


def lca(taxids, parent):
    def lineage(t):
        out = [t]
        while out[-1] in parent and parent[out[-1]] != out[-1]:
            out.append(parent[out[-1]])
        return out[::-1]
    paths = [lineage(t) for t in taxids]
    common = None
    for level in zip(*paths):
        if len(set(level)) != 1:
            break
        common = level[0]
    return common


def kind(n_self, n_other):
    if n_self == 1:
        return "1:1" if n_other == 1 else "1:many"
    return "many:1" if n_other == 1 else "many:many"


def is_fastoma(results):
    return os.path.exists(os.path.join(results, "RootHOGs.tsv"))


def file_date(path):
    return datetime.datetime.fromtimestamp(os.path.getmtime(path)).astimezone().isoformat(timespec="seconds")


def orthofinder_version(R):
    """(version, run date) from OrthoFinder's Log.txt"""
    version, run_date = None, None
    log = os.path.join(R, "Log.txt")
    if os.path.exists(log):
        for line in open(log):
            m = re.match(r"(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d) : Started OrthoFinder version (\S+)", line)
            if m:
                run_date, version = m.group(1).replace(" ", "T"), m.group(2)
    return version, run_date


def fastoma_version(R):
    """FastOMA's release from the Nextflow log run_fastoma.sh copies ("... [v0.5.1]"), else the orthoxml header"""
    log = os.path.join(R, "nextflow.log")
    if os.path.exists(log):
        for line in open(log, errors="replace"):
            m = re.search(r"dessimozlab/FastOMA.*\[v?([0-9][0-9.]*)\]", line)
            if m:
                return m.group(1)
    with open(os.path.join(R, "FastOMA_HOGs.orthoxml"), errors="replace") as fh:
        m = re.search(r'origin="FastOMA ([^"]+)"', fh.read(2000))
    return m.group(1) if m else ""


def load_fastoma(R, run, members, tax):
    """FastOMA results -> the same as load_orthofinder. Everything is read through the orthoxml's internal gene
    ids, which are unique: FastOMA's tsv files give protein ids only, and two gene sets can share them (two
    Trinity transcriptomes both have TRINITY_DN0_c0_g1).
    genes: every protein of the run's input (one per gene: the ids are our MOOP gene ids), with its RootHOG
           (a top-level group of the orthoxml, as RootHOGs.tsv) as orthogroup; None for a gene in no group
           of two or more
    hogs:  N0 = the RootHOG; deeper nodes = the nested orthologGroups, at their TaxRange node
    nodes: species_tree_checked.nwk, the root N0, the others N1, N2, ... from the root down
    pairs: orthologs_by_id.tsv.gz (internal ids; config/fastoma/fastoma_pairs_by_id.py writes it)"""
    import gzip
    import xml.etree.ElementTree as ET
    genes = {}
    for m in members:
        fa = os.path.join(run, "in_folder", "proteome", f"{m}.fa")
        if not os.path.exists(fa):
            die(f"{fa} missing")
        with open(fa) as fh:
            for line in fh:
                if line.startswith(">"):
                    genes[(m, line[1:].split()[0])] = None

    ns = "{http://orthoXML.org/2011/}"
    doc = ET.parse(os.path.join(R, "FastOMA_HOGs.orthoxml")).getroot()
    owner = {}                                   # internal gene id -> (member, gene)
    for species in doc.findall(f"{ns}species"):
        m = species.get("name")
        if m not in members:
            die(f"orthoxml species {m} is not in members.tsv")
        for gene in species.iter(f"{ns}gene"):
            key = (m, gene.get("protId"))
            if key not in genes:
                die(f"orthoxml gene {key} is not an input gene")
            owner[gene.get("id")] = key

    # RootHOGs: the top-level groups (RootHOGs.tsv is written from the same groups, id up to the first "_")
    groups = doc.find(f"{ns}groups")
    hogs, og_counts = [], {}
    for i, top in enumerate(list(groups) if groups is not None else []):
        rh = (top.get("id") or f"HOG:R{i}").split("_")[0]
        keys = list(dict.fromkeys(owner[ref.get("id")] for ref in top.iter(f"{ns}geneRef")))
        if len(keys) < 2:
            continue
        for key in keys:
            genes[key] = rh
            og_counts.setdefault(rh, {})
            og_counts[rh][key[0]] = og_counts[rh].get(key[0], 0) + 1
            hogs.append((key[0], key[1], "N0", f"N0.{rh}"))

    tree = parse_newick_nodes(open(os.path.join(R, "species_tree_checked.nwk")).read())
    ordered = sorted(tree, key=lambda n: -len(n["leaves"]))      # the root has every leaf
    nid = {n["node"]: f"N{i}" for i, n in enumerate(ordered)}
    node_rows = []
    for n in ordered:
        label = None
        if tax:
            t = lca([members[l]["taxon_id"] for l in n["leaves"]], tax[0])
            label = tax[1].get(t)
        node_rows.append((nid[n["node"]], nid.get(n["parent"]) if n["parent"] else None, label))

    for i, group in enumerate(doc.iter(f"{ns}orthologGroup")):
        taxrange = None
        for prop in group.findall(f"{ns}property"):
            if prop.get("name") == "TaxRange":
                taxrange = prop.get("value")
        node = nid.get(taxrange)
        if node is None or node == "N0":
            continue
        hog = f"{node}.{group.get('id') or 'HOG' + str(i)}"
        for ref in group.iter(f"{ns}geneRef"):
            key = owner[ref.get("id")]
            hogs.append((key[0], key[1], node, hog))
    hogs = list(dict.fromkeys(hogs))
    hog_source = "N0 = FastOMA RootHOGs; deeper nodes = FastOMA's nested HOGs (orthoxml TaxRange)"

    by_id = os.path.join(R, "orthologs_by_id.tsv.gz")
    if not os.path.exists(by_id):
        die(f"{by_id} missing: run config/fastoma/fastoma_pairs_by_id.py with FastOMA's python first")
    pairs = {}
    with gzip.open(by_id, "rt") as fh:
        for line in fh:
            a, b = line.rstrip("\n").split("\t")[:2]
            (a_sp, a_g), (b_sp, b_g) = owner[a], owner[b]
            if a_sp != b_sp:
                pairs[(a_sp, a_g, b_sp, b_g)] = None
    note = f"{len(pairs)} from orthologs_by_id.tsv.gz"
    return genes, og_counts, hogs, hog_source, node_rows, pairs, note


def load_orthofinder(R, members, tax):
    """OrthoFinder 3 results -> genes, og_counts, hogs, hog_source, node_rows, pairs, a note on the pairs"""
    # --- genes and orthogroups
    genes = {}                                   # (member, gene) -> orthogroup or None
    hdr, rows = read_tsv(os.path.join(R, "Orthogroups", "Orthogroups.tsv"))
    sp = hdr[1:]
    unknown = set(sp) - set(members)
    if unknown:
        die(f"species in the results but not in members.tsv: {', '.join(sorted(unknown))}")
    og_counts = {}
    for r in rows:
        og_counts[r[0]] = {}
        for s, cell in zip(sp, r[1:]):
            gl = split_genes(cell)
            og_counts[r[0]][s] = len(gl)
            for g in gl:
                genes[(s, g)] = r[0]
    ufile = os.path.join(R, "Orthogroups", "Orthogroups_UnassignedGenes.tsv")
    if os.path.exists(ufile):
        uh, urows = read_tsv(ufile)
        for r in urows:
            for s, cell in zip(uh[1:], r[1:]):
                for g in split_genes(cell):
                    genes.setdefault((s, g), None)
    # --- HOGs
    # OrthoFinder 3 builds its final orthogroups (Orthogroups.tsv) from the N0 HOGs and keeps no N0.tsv:
    # Phylogenetic_Hierarchical_Orthogroups/ has N1.tsv, N2.tsv, ... only (a 2-species run has none).
    # So N0, the whole clade, is the orthogroup: N0.HOG<n> for OG<n>. Deeper nodes are OrthoFinder's HOGs.
    hogs = []                                    # (member, gene, node, hog)
    hog_files = sorted(glob.glob(os.path.join(R, "Phylogenetic_Hierarchical_Orthogroups", "N*.tsv")))
    for f in hog_files:
        node = os.path.basename(f)[:-4]
        h, hrows = read_tsv(f)
        for r in hrows:
            for s, cell in zip(h[3:], r[3:]):
                for g in split_genes(cell):
                    hogs.append((s, g, node, r[0]))
    if any(node == "N0" for _, _, node, _ in hogs):
        hog_source = "OrthoFinder HOGs"
    else:
        for (s, g), og in genes.items():
            if og:
                hogs.append((s, g, "N0", "N0.HOG" + og[2:]))
        hog_source = ("N0 = the orthogroups (OrthoFinder 3 makes them from the N0 HOGs and writes no N0.tsv)"
                      + (f"; {', '.join(sorted({n for _, _, n, _ in hogs if n != 'N0'}, key=lambda x: int(x[1:])))} "
                         "= OrthoFinder HOGs" if hog_files else ""))

    # --- species tree nodes
    tree_file = os.path.join(R, "Species_Tree", "SpeciesTree_rooted_node_labels.txt")
    nodes = parse_newick_nodes(open(tree_file).read())
    node_rows = []
    for n in nodes:
        label = None
        if tax:
            t = lca([members[l]["taxon_id"] for l in n["leaves"]], tax[0])
            label = tax[1].get(t)
        node_rows.append((n["node"], n["parent"], label))

    # --- pairs
    # Collect the pairs first; the kind comes afterwards from the partner counts. A row of Orthologues/
    # is not one orthology relation: OrthoFinder can list one gene's 13 orthologs as 13 rows of one gene
    # each (Danio 100004171, a histone cluster), so a row's own gene counts would call each of them 1:1.
    pairs = {}                                   # (a member, a gene, b member, b gene) -> kind
    for f in glob.glob(os.path.join(R, "Orthologues", "*.tsv")):
        a_sp = os.path.basename(f)[:-4]
        if a_sp not in members:
            continue
        h, prow = read_tsv(f)                    # Orthogroup, Species, <a_sp>, Orthologs
        for r in prow:
            b_sp, a_genes, b_genes = r[1], split_genes(r[2]), split_genes(r[3])
            for a in a_genes:
                for b in b_genes:
                    pairs[(a_sp, a, b_sp, b)] = None
    from_files = len(pairs)
    sc_file = os.path.join(R, "Orthogroups", "Orthogroups_SingleCopyOrthologues.txt")
    added_sc = 0
    if os.path.exists(sc_file):
        og_genes = {}
        for (s, g), og in genes.items():
            if og:
                og_genes.setdefault(og, []).append((s, g))
        for og in (l.strip() for l in open(sc_file) if l.strip()):
            for a_sp, a in og_genes[og]:
                for b_sp, b in og_genes[og]:
                    if a_sp != b_sp and (a_sp, a, b_sp, b) not in pairs:
                        pairs[(a_sp, a, b_sp, b)] = None
                        added_sc += 1
    note = f"{from_files} from Orthologues/, {added_sc} 1:1 from single-copy orthogroups"
    return genes, og_counts, hogs, hog_source, node_rows, pairs, note


def read_diamond(path):
    """DIAMOND tsv(.gz) from run_clade_diamond.sh -> (best bitscore per query,
    {(query, subject): (evalue, bitscore, query coverage, subject coverage, pident)} of the best-scoring HSP)"""
    best, hsp = {}, {}
    opener = gzip.open if path.endswith(".gz") else open
    with opener(path, "rt") as fh:
        for line in fh:
            q, s, e, bits, qs, qe, ss, se, ql, sl, pid = line.rstrip("\n").split("\t")[:11]
            bits = float(bits)
            if bits > best.get(q, 0.0):
                best[q] = bits
            if (q, s) not in hsp or bits > hsp[(q, s)][1]:
                hsp[(q, s)] = (float(e), bits, (int(qe) - int(qs) + 1) / int(ql),
                               (abs(int(se) - int(ss)) + 1) / int(sl), float(pid))
    return best, hsp


def pair_evidence(pairs, ddir, max_evalue, min_cov):
    """Sequence evidence for every pair, from <ddir>/<A>__<B>.tsv.gz both ways.
    -> {pair key: (support, evalue, bitscore, pident, cov_a, cov_b)}; the values are those of the best-scoring
    alignment of the two proteins in either direction (None when DIAMOND found none). support:
      reciprocal_best  passes the rule, and each protein is the other's best hit (ties count)
      co_ortholog      passes the rule but is not reciprocal best (e.g. one side duplicated)
      weak             a hit that fails the rule
      no_hit           no DIAMOND hit either way"""
    by_sp = {}
    for key in pairs:
        a_sp, _, b_sp, _ = key
        if a_sp < b_sp:
            by_sp.setdefault((a_sp, b_sp), []).append(key)
    ev, counts = {}, {}
    for (a_sp, b_sp), keys in sorted(by_sp.items()):
        files = [os.path.join(ddir, f"{x}__{y}.tsv.gz") for x, y in ((a_sp, b_sp), (b_sp, a_sp))]
        missing = [f for f in files if not os.path.exists(f)]
        if missing:
            die(f"DIAMOND results missing: {', '.join(missing)} (run run_clade_diamond.sh)")
        best_a, ab = read_diamond(files[0])
        best_b, ba = read_diamond(files[1])
        for key in keys:
            _, a, _, b = key
            h1 = ab.get((a, b))                                   # a -> b: cov = (a, b)
            h2 = ba.get((b, a))                                   # b -> a: cov = (b, a)
            h2 = h2 and (h2[0], h2[1], h2[3], h2[2], h2[4])
            h = max((x for x in (h1, h2) if x), key=lambda x: x[1], default=None)
            if h is None:
                val = ("no_hit", None, None, None, None, None)
            else:
                ok = h[0] <= max_evalue and h[2] >= min_cov and h[3] >= min_cov
                rbh = (h1 is not None and h1[1] >= best_a[a]) and \
                      (ba.get((b, a)) is not None and ba[(b, a)][1] >= best_b[b])
                support = ("reciprocal_best" if rbh else "co_ortholog") if ok else "weak"
                val = (support, h[0], h[1], h[4], round(h[2], 3), round(h[3], 3))
            ev[key] = val
            ev[(b_sp, b, a_sp, a)] = val[:4] + (val[5], val[4])     # mirror: swap cov_a / cov_b
            counts[val[0]] = counts.get(val[0], 0) + 2
    return ev, counts


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--run", required=True, help="clade run folder (members.tsv, results/)")
    ap.add_argument("--out", required=True, help="output homologs.sqlite, e.g. <build data>/.clades/<clade>/homologs.sqlite")
    ap.add_argument("--results", help="a specific OrthoFinder Results_* folder (default: newest complete)")
    ap.add_argument("--taxonomy", help="NCBI taxdump folder (nodes.dmp, names.dmp) for node labels")
    ap.add_argument("--label", help="display name (default: clade.tsv label, else the clade folder name)")
    ap.add_argument("--clade", help="clade name in MOOP (default: the run folder name), e.g. planarians_fastoma "
                                    "for a FastOMA run shown beside the OrthoFinder one")
    ap.add_argument("--diamond", help="DIAMOND results of run_clade_diamond.sh (default: <run>/diamond when "
                                      "it has diamond.tsv); 'none' builds without pair evidence")
    ap.add_argument("--max-evalue", type=float, default=1e-5,
                    help="support rule: E-value (default 1e-5; coverage of both proteins does most of the work, "
                         "and a stricter E-value would mark real short proteins weak)")
    ap.add_argument("--min-cov", type=float, default=0.5,
                    help="support rule: fraction of BOTH proteins the alignment covers (default 0.5)")
    args = ap.parse_args()

    run = os.path.abspath(args.run)
    clade = args.clade or os.path.basename(run)
    if not re.fullmatch(r"[A-Za-z0-9_-]+", clade):
        die(f"clade folder name '{clade}' must be [A-Za-z0-9_-]")
    if os.path.basename(os.path.dirname(os.path.abspath(args.out))) != clade:
        die(f"--out must be .../{clade}/homologs.sqlite (the folder is the clade)")
    info = kv_file(os.path.join(run, "clade.tsv"))
    members = read_members(run)
    R = pick_results(run, args.results)
    print(f"clade {clade}: {len(members)} members, results {R}")

    tax = load_taxonomy(args.taxonomy) if args.taxonomy else None
    if is_fastoma(R):
        genes, og_counts, hogs, hog_source, node_rows, pairs, pair_note = load_fastoma(R, run, members, tax)
        method, version, run_date = "FastOMA", fastoma_version(R), file_date(os.path.join(R, "RootHOGs.tsv"))
    else:
        genes, og_counts, hogs, hog_source, node_rows, pairs, pair_note = load_orthofinder(R, members, tax)
        method, (version, run_date) = "OrthoFinder", orthofinder_version(R)
    n_assigned = sum(1 for og in genes.values() if og)
    for m, d in members.items():
        n = sum(1 for (s, _) in genes if s == m)
        if n != d["genes_expected"]:
            die(f"{m}: {n} genes in the results, members.tsv says {d['genes_expected']}")

    # every a->b also as b->a
    fixed = 0
    for (a_sp, a, b_sp, b) in list(pairs):
        if (b_sp, b, a_sp, a) not in pairs:
            pairs[(b_sp, b, a_sp, a)] = None
            fixed += 1
    # kind of a->b, read from a's side: how many orthologs a has in b's species, and b in a's
    n_partners = {}                              # (member, gene, other member) -> partner count
    for (a_sp, a, b_sp, b) in pairs:
        n_partners[(a_sp, a, b_sp)] = n_partners.get((a_sp, a, b_sp), 0) + 1
    for key in pairs:
        a_sp, a, b_sp, b = key
        pairs[key] = kind(n_partners[(b_sp, b, a_sp)], n_partners[(a_sp, a, b_sp)])
    # check: a gene's kinds agree with its partner count, and a->b mirrors b->a
    mirror = {"1:1": "1:1", "1:many": "many:1", "many:1": "1:many", "many:many": "many:many"}
    for (a_sp, a, b_sp, b), k in pairs.items():
        many_right = k.endswith("many")
        if many_right != (n_partners[(a_sp, a, b_sp)] > 1) or pairs[(b_sp, b, a_sp, a)] != mirror[k]:
            die(f"pair kind check failed: {a_sp} {a} -> {b_sp} {b} is {k}")
    print(f"pairs: {pair_note}, {fixed} added as mirrors; {len(pairs)} total")
    # diagnostic: species pairs that both have exactly one gene in an orthogroup but no pair row
    gaps = 0
    pair_ogs = {(a_sp, b_sp, genes[(a_sp, a)]) for (a_sp, a, b_sp, b) in pairs}
    for og, c in og_counts.items():
        ones = [s for s, n in c.items() if n == 1]
        for a_sp in ones:
            for b_sp in ones:
                if a_sp != b_sp and (a_sp, b_sp, og) not in pair_ogs:
                    gaps += 1
    if gaps:
        print(f"note: {gaps} (species pair, orthogroup) cases with one gene on each side but no ortholog pair "
              f"({method} did not call them orthologs)")

    # --- sequence evidence on each pair (kept on every pair; nothing is dropped)
    ddir = args.diamond or os.path.join(run, "diamond")
    if args.diamond != "none" and (args.diamond or os.path.exists(os.path.join(ddir, "diamond.tsv"))):
        evidence, sup_counts = pair_evidence(pairs, ddir, args.max_evalue, args.min_cov)
        dinfo = kv_file(os.path.join(ddir, "diamond.tsv"))
        print("pair support: " + ", ".join(f"{k} {v}" for k, v in sorted(sup_counts.items())))
    else:
        evidence, sup_counts, dinfo = {}, {}, {}
        print("pair support: none (no DIAMOND results; run run_clade_diamond.sh)")
    # the protein that stood in for each gene (make_clade_set.py's gene_protein.tsv; first column = member)
    gene_protein = {}
    gp_file = os.path.join(run, "gene_protein.tsv")
    if evidence and os.path.exists(gp_file):
        _, gp_rows = read_tsv(gp_file)
        gene_protein = {(r[0], r[1]): r[2] for r in gp_rows}
        missing = sum(1 for k in genes if k not in gene_protein)
        if missing:
            print(f"note: {missing} genes have no row in gene_protein.tsv (protein left empty)")

    # --- write
    out = os.path.abspath(args.out)
    os.makedirs(os.path.dirname(out), exist_ok=True)
    tmp = out + f".tmp{os.getpid()}"
    if os.path.exists(tmp):
        os.remove(tmp)
    con = sqlite3.connect(tmp)
    con.executescript("""
CREATE TABLE member (organism TEXT NOT NULL, assembly TEXT NOT NULL, gene_set TEXT NOT NULL,
  protein_md5 TEXT NOT NULL, genes INTEGER NOT NULL, PRIMARY KEY (organism, gene_set));
CREATE TABLE gene (id INTEGER PRIMARY KEY, organism TEXT NOT NULL, gene_set TEXT NOT NULL,
  gene TEXT NOT NULL, gene_lc TEXT NOT NULL, orthogroup TEXT""" + (", protein TEXT" if evidence else "") + """);
CREATE TABLE hog (gene_id INTEGER NOT NULL, node TEXT NOT NULL, hog TEXT NOT NULL);
CREATE TABLE node (node TEXT PRIMARY KEY, parent TEXT, label TEXT);
CREATE TABLE pair (gene_a INTEGER NOT NULL, gene_b INTEGER NOT NULL, kind TEXT NOT NULL""" + (""",
  support TEXT NOT NULL, evalue REAL, bitscore REAL, pident REAL, cov_a REAL, cov_b REAL""" if evidence else "") + """);
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT);
""")
    for m, d in sorted(members.items()):
        n = sum(1 for (s, _) in genes if s == m)
        con.execute("INSERT INTO member VALUES (?,?,?,?,?)",
                    (d["organism"], d["assembly"], d["gene_set"], d["protein_md5"], n))
    gid = {}
    for i, ((s, g), og) in enumerate(sorted(genes.items()), start=1):
        gid[(s, g)] = i
        row = (i, members[s]["organism"], members[s]["gene_set"], g, g.lower(), og)
        if evidence:
            row += (gene_protein.get((s, g)),)
        con.execute(f"INSERT INTO gene VALUES ({','.join('?' * len(row))})", row)
    con.executemany("INSERT INTO hog VALUES (?,?,?)", ((gid[(s, g)], node, h) for s, g, node, h in hogs))
    con.executemany("INSERT INTO node VALUES (?,?,?)", node_rows)
    if evidence:
        con.executemany("INSERT INTO pair VALUES (?,?,?,?,?,?,?,?,?)",
                        ((gid[(a_sp, a)], gid[(b_sp, b)], k) + evidence[(a_sp, a, b_sp, b)]
                         for (a_sp, a, b_sp, b), k in pairs.items()))
    else:
        con.executemany("INSERT INTO pair VALUES (?,?,?)",
                        ((gid[(a_sp, a)], gid[(b_sp, b)], k) for (a_sp, a, b_sp, b), k in pairs.items()))
    con.executescript("""
CREATE INDEX g_gene ON gene (gene_lc, organism);
CREATE INDEX g_og ON gene (orthogroup);
CREATE INDEX h_gene ON hog (gene_id);
CREATE INDEX h_hog ON hog (hog);
CREATE INDEX p_a ON pair (gene_a);
""")
    n_og = sum(1 for og in og_counts)
    hogs_n0 = len({h for _, _, node, h in hogs if node == "N0"})
    meta = {
        "format": FORMAT_EVIDENCE if evidence else FORMAT,
        "clade": clade,
        "label": args.label or info.get("label") or clade,
        "taxon_id": info.get("taxon_id", ""),
        "method": method,
        "method_version": version or "",
        "run_date": run_date or "",
        "built_at": datetime.datetime.now().astimezone().isoformat(timespec="seconds"),
        "genes": str(len(genes)),
        "orthogroups": str(n_og),
        "hogs_n0": str(hogs_n0),
        "pairs": str(len(pairs)),
        "hog_source": hog_source,
        "results": f"{os.path.basename(run)}/{os.path.basename(R.rstrip('/'))}",   # no cluster paths in a shipped file
    }
    if method == "OrthoFinder":
        meta["orthofinder_version"] = version or ""   # format 1 had only this; the site still reads it
    if evidence:
        prog = (dinfo.get("program") or "diamond").split()
        meta["evidence_tool"] = "DIAMOND"
        meta["evidence_version"] = prog[1] if len(prog) > 1 else ""
        meta["evidence_mode"] = dinfo.get("mode", "")
        meta["evidence_search_evalue"] = dinfo.get("evalue", "")
        meta["evidence_evalue"] = f"{args.max_evalue:g}"
        meta["evidence_min_cov"] = f"{args.min_cov:g}"
        meta["evidence_rule"] = (
            f"supported = E-value <= {args.max_evalue:g} and the alignment covers >= {args.min_cov * 100:g}% of "
            f"both proteins (no identity cutoff); reciprocal_best = supported and each protein is the other's best "
            f"hit in that species; co_ortholog = supported, not reciprocal best; weak = a hit that is not "
            f"supported; no_hit = no DIAMOND hit (E <= {dinfo.get('evalue', '?')}) either way")
        meta["results_path"] = os.path.abspath(R)
        for k in ("reciprocal_best", "co_ortholog", "weak", "no_hit"):
            meta[f"pairs_{k}"] = str(sup_counts.get(k, 0))
    con.executemany("INSERT INTO meta VALUES (?,?)", meta.items())
    con.commit()
    ok = con.execute("PRAGMA integrity_check").fetchone()[0]
    con.close()
    bad = [k for k in ("genes", "orthogroups", "hogs_n0", "pairs") if int(meta[k]) <= 0]
    if ok != "ok" or bad:
        os.remove(tmp)
        die(f"check failed: integrity_check={ok}, zero counts: {bad or 'none'}")
    os.chmod(tmp, 0o664)
    os.replace(tmp, out)
    print(f"wrote {out}: {len(genes)} genes ({n_assigned} in orthogroups), {n_og} orthogroups, "
          f"{hogs_n0} N0 HOGs, {len(node_rows)} nodes, {len(pairs)} pairs; label '{meta['label']}'")


if __name__ == "__main__":
    main()
