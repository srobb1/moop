#!/usr/bin/env python3
"""Set up an OrthoFinder run for every MOOP organism in one clade of the NCBI taxonomy.

    make_clade_set.py Actinopterygii --label ray_finned_fish          # write the run folder
    make_clade_set.py Chiroptera --list                               # only show who is in it
    make_clade_set.py 9397 --label bats --exclude Pteropus_rufus      # a taxon ID works too
    make_clade_set.py Hexacorallia --label hexacorallia --outgroup Turritopsis_dohrnii
    make_clade_set.py Actinopterygii --label ray_finned_fish --outgroup Lepisosteus_oculatus=<proteins.fa>

Members: every organism in MOOP's build folder (MOOP_DATA/<Organism>/organism.sqlite, taxon_id)
whose NCBI lineage contains the clade. MOOP IDs only: nothing outside MOOP is added.

One gene set per organism, chosen in this order:
  1. geneset_choices.tsv in this folder ("Organism<TAB>gene_set"), for organisms you decide by hand;
     several lines for one organism make each gene set its own member (<Organism>_<gene_set>)
  2. the organism's RosettaStone reference (REFERENCE_GENESETS in RS_CONFS/*_moop.conf), if it names one
  3. the organism's only gene set in MOOP
Anything else (several gene sets, no choice) stops the script and lists the options.

Proteins: one per gene, the longest (ties: smallest protein ID), from the gene set's protein.aa.fa
in MOOP's build folder. FASTA headers are MOOP gene IDs, so OrthoFinder's groups are MOOP genes;
gene_protein.tsv records which protein stood in for each gene.

Writes <ROOT>/<label>/: input/<Organism>.fa, members.tsv, gene_protein.tsv, species_tree.nwk (from
the NCBI taxonomy, only when it is fully resolved; otherwise OrthoFinder infers the tree) and
run_orthofinder.sbatch. Refuses to overwrite an existing input/ unless --force.

Outgroups (--outgroup, repeatable): species just outside the clade. They go into OrthoFinder (input/ and the
species tree) so it can root each gene tree and tell an old duplication from one inside the clade, but they are
not members: they are listed in outgroups.tsv, not members.tsv, get no DIAMOND searches, and
build_clade_homologs.py leaves them out of MOOP's file (meta 'outgroups' names them). An outgroup is either a
MOOP organism folder (gene set chosen as for members, MOOP gene IDs) or Genus_species=<protein FASTA>
(any FASTA, e.g. an OMA DB/<CODE>.fa; first word of the header = ID; an OMA <CODE>.splice next to it
keeps the longest protein of each gene; taxon from the NCBI name).
"""
import argparse
import glob
import hashlib
import os
import re
import sqlite3
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))


def read_conf():
    """orthofinder.conf (bash) next to this script, or $ORTHOFINDER_CONF: let bash read it"""
    path = os.environ.get("ORTHOFINDER_CONF") or os.path.join(HERE, "orthofinder.conf")
    if not os.access(path, os.R_OK):
        sys.exit(f"ERROR: no settings file {path} (copy orthofinder.conf.example to orthofinder.conf)")
    keys = ["OF_ROOT", "MOOP_DATA", "RS_CONFS"]
    script = 'source "$1" >/dev/null || exit 1; shift; for k in "$@"; do printf "%s\\t%s\\n" "$k" "${!k}"; done'
    out = subprocess.run(["bash", "-c", script, "read_conf", path] + keys,
                         capture_output=True, text=True, check=True).stdout
    conf = dict(line.split("\t", 1) for line in out.splitlines())
    missing = [k for k in keys if not conf.get(k)]
    if missing:
        sys.exit(f"ERROR: {path} does not set {', '.join(missing)}")
    return conf


CONF = read_conf()
ROOT = CONF["OF_ROOT"]                      # run folders, taxonomy/, geneset_choices.tsv
TAXDIR = os.path.join(ROOT, "taxonomy")
MOOP_DATA = CONF["MOOP_DATA"]
RS_CONFS = CONF["RS_CONFS"]
CHOICES = os.path.join(ROOT, "geneset_choices.tsv")


def die(msg):
    sys.exit(f"ERROR: {msg}")


def dmp_rows(path):
    with open(path) as fh:
        for line in fh:
            yield line.rstrip("\t|\n").split("\t|\t")


def load_taxonomy():
    parent, rank = {}, {}
    for row in dmp_rows(os.path.join(TAXDIR, "nodes.dmp")):
        parent[int(row[0])], rank[int(row[0])] = int(row[1]), row[2]
    merged = {int(r[0]): int(r[1]) for r in dmp_rows(os.path.join(TAXDIR, "merged.dmp"))}
    sci, by_name = {}, {}
    for row in dmp_rows(os.path.join(TAXDIR, "names.dmp")):
        taxid, name, cls = int(row[0]), row[1], row[3]
        if cls == "scientific name":
            sci[taxid] = name
        if cls in ("scientific name", "genbank common name", "common name"):
            by_name.setdefault(name.lower(), set()).add((taxid, cls))
    return parent, rank, merged, sci, by_name


def lineage(taxid, parent):
    """taxids from the root down to taxid"""
    out = [taxid]
    while out[-1] in parent and parent[out[-1]] != out[-1]:
        out.append(parent[out[-1]])
    return out[::-1]


def resolve_clade(spec, parent, merged, sci, by_name):
    if spec.isdigit():
        taxid = merged.get(int(spec), int(spec))
        if taxid not in parent:
            die(f"taxon ID {spec} is not in the NCBI taxonomy")
        return taxid
    hits = by_name.get(spec.lower(), set())
    exact = {t for t, cls in hits if cls == "scientific name"} or {t for t, _ in hits}
    if len(exact) != 1:
        found = ", ".join(f"{t} ({sci.get(t, '?')})" for t in sorted(exact)) or "nothing"
        die(f"clade '{spec}' matches {found}; give the taxon ID instead")
    return exact.pop()


def moop_organisms():
    for db in sorted(glob.glob(os.path.join(MOOP_DATA, "*", "organism.sqlite"))):
        org = os.path.basename(os.path.dirname(db))
        con = sqlite3.connect(f"file:{db}?mode=ro", uri=True)
        row = con.execute("SELECT taxon_id FROM organism").fetchone()
        sets = [r[0] for r in con.execute(
            "SELECT gs.gene_set_name FROM gene_set gs ORDER BY gs.gene_set_name")]
        con.close()
        yield org, db, (int(row[0]) if row and row[0] else None), sets


def rs_references():
    """organism folder -> list of REFERENCE_GENESETS from its RosettaStone MOOP config"""
    refs = {}
    for conf in sorted(glob.glob(os.path.join(RS_CONFS, "*_moop.conf"))):
        species_dir, names = None, None
        with open(conf) as fh:
            for line in fh:
                m = re.match(r'\s*(SPECIES_DIR|REFERENCE_GENESETS)=("?)(.*)\2\s*$', line)
                if not m:
                    continue
                if m.group(1) == "SPECIES_DIR":
                    species_dir = m.group(3)
                else:
                    names = [n.split("/")[-1] for n in m.group(3).split()]
        if species_dir and names:
            refs[os.path.basename(species_dir.rstrip("/"))] = (names, os.path.basename(conf))
    return refs


def read_choices():
    choices = {}
    if os.path.exists(CHOICES):
        with open(CHOICES) as fh:
            for line in fh:
                if line.strip() and not line.startswith("#"):
                    org, gs = line.rstrip("\n").split("\t")[:2]
                    choices.setdefault(org, []).append(gs)
    return choices


class NeedChoice(Exception):
    pass


def choose_geneset(org, sets, choices, refs):
    if org in choices:
        bad = [gs for gs in choices[org] if gs not in sets]
        if bad:
            raise NeedChoice(f"geneset_choices.tsv says {', '.join(bad)}, but MOOP has {', '.join(sets)}")
        return [(gs, "geneset_choices.tsv") for gs in choices[org]]
    if org in refs:
        names, conf = refs[org]
        if len(names) == 1 and names[0] in sets:
            return [(names[0], f"RosettaStone reference ({conf})")]
        if len(names) > 1:
            raise NeedChoice(f"RosettaStone config {conf} has several references: {', '.join(names)}")
    if len(sets) == 1:
        return [(sets[0], "only gene set")]
    raise NeedChoice(f"several gene sets: {', '.join(sets)}")


def gene_of_proteins(db, geneset):
    con = sqlite3.connect(f"file:{db}?mode=ro", uri=True)
    rows = con.execute(
        "SELECT f.feature_id, f.parent_feature_id, f.feature_type, f.feature_uniquename "
        "FROM feature f JOIN gene_set gs USING (gene_set_id) WHERE gs.gene_set_name = ?",
        (geneset,)).fetchall()
    con.close()
    by_id = {r[0]: r for r in rows}
    out = {}
    for fid, _, ftype, name in rows:
        if ftype != "protein":
            continue
        node, seen = by_id[fid], set()
        while node and node[2] != "gene" and node[1] is not None and node[0] not in seen:
            seen.add(node[0])
            node = by_id.get(node[1])
        if node and node[2] == "gene":
            out[name] = node[3]
    return out


def read_fasta(path):
    seqs, name, buf = {}, None, []
    with open(path) as fh:
        for line in fh:
            if line.startswith(">"):
                if name is not None:
                    seqs[name] = "".join(buf)
                name, buf = line[1:].split()[0], []
            else:
                buf.append(line.strip())
    if name is not None:
        seqs[name] = "".join(buf)
    return seqs


def md5sum(path):
    h = hashlib.md5()
    with open(path, "rb") as fh:
        for block in iter(lambda: fh.read(1 << 20), b""):
            h.update(block)
    return h.hexdigest()


def longest_per_gene(seqs, gene_of):
    """{gene: (protein, seq)} keeping the longest protein (ties: smallest ID); and the proteins with no gene"""
    best, orphans = {}, 0
    for pid, seq in seqs.items():
        gene = gene_of.get(pid)
        if gene is None:
            orphans += 1
            continue
        key = (-len(seq), pid)
        if gene not in best or key < best[gene][0]:
            best[gene] = (key, pid, seq)
    return {g: (pid, seq) for g, (_, pid, seq) in best.items()}, orphans


def write_fasta(path, best):
    with open(path, "w") as fa:
        for gene in sorted(best):
            seq = best[gene][1]
            fa.write(f">{gene}\n")
            for i in range(0, len(seq), 60):
                fa.write(seq[i:i + 60] + "\n")


def moop_fasta(org, gs):
    fastas = glob.glob(os.path.join(MOOP_DATA, org, "*", gs, "protein.aa.fa"))
    if len(fastas) != 1:
        die(f"{org}/{gs}: expected one protein.aa.fa, found {len(fastas)}")
    return fastas[0]


def resolve_outgroups(specs, clade, moop, choices, refs, parent, merged, by_name):
    """--outgroup values -> [(name, taxid, source fasta, {gene: (protein, seq)}, note)]"""
    out = []
    for spec in specs:
        if "=" in spec:
            name, fasta = spec.split("=", 1)
            fasta = os.path.abspath(fasta)
            if not os.path.exists(fasta):
                die(f"--outgroup {name}: {fasta} not found")
            hits = {t for t, cls in by_name.get(name.replace("_", " ").lower(), set()) if cls == "scientific name"}
            if len(hits) != 1:
                die(f"--outgroup {name}: not one NCBI scientific name '{name.replace('_', ' ')}' (found {len(hits)})")
            taxid = hits.pop()
            seqs = read_fasta(fasta)
            splice = os.path.splitext(fasta)[0] + ".splice"
            gene_of = {pid: pid for pid in seqs}
            if os.path.exists(splice):            # OMA: one line per gene, its proteins joined by ';'
                with open(splice) as fh:
                    for line in fh:
                        ids = [x for x in line.strip().split(";") if x]
                        for pid in ids:
                            gene_of[pid] = min(ids)
            best, _ = longest_per_gene(seqs, gene_of)
            note = f"{len(best)} genes from {len(seqs)} proteins" + (" (OMA .splice)" if os.path.exists(splice) else "")
        else:
            name = spec
            if spec not in moop:
                die(f"--outgroup {spec}: not a MOOP organism folder (or give Genus_species=<protein FASTA>)")
            db, taxid, sets = moop[spec]
            if taxid is None:
                die(f"--outgroup {spec}: no taxon_id in organism.sqlite")
            try:
                picked = choose_geneset(spec, sets, choices, refs)
            except NeedChoice as e:
                die(f"--outgroup {spec}: {e}")
            if len(picked) != 1:
                die(f"--outgroup {spec}: several gene sets chosen in geneset_choices.tsv")
            gs = picked[0][0]
            fasta = moop_fasta(spec, gs)
            best, _ = longest_per_gene(read_fasta(fasta), gene_of_proteins(db, gs))
            note = f"MOOP {gs}, {len(best)} genes"
        if not re.fullmatch(r"[A-Za-z0-9_-]+", name):
            die(f"--outgroup name '{name}' may contain only letters, digits, _ -")
        taxid = merged.get(taxid, taxid)
        if taxid not in parent:
            die(f"--outgroup {name}: taxon {taxid} is not in the NCBI taxonomy")
        if clade in lineage(taxid, parent):
            die(f"--outgroup {name}: taxon {taxid} is inside the clade; add it as a member instead")
        if not best:
            die(f"--outgroup {name}: no proteins")
        out.append((name, taxid, fasta, best, note))
    return out


def species_tree(members, parent):
    """Newick from the NCBI lineages, or (None, reason) when a node has more than two children."""
    children = {}
    for name, taxid in members:
        path = lineage(taxid, parent)
        for a, b in zip(path, path[1:]):
            children.setdefault(a, set()).add(b)
        children.setdefault(path[-1], set()).add(("leaf", name))
    root = lineage(members[0][1], parent)[0]

    def build(node):
        if isinstance(node, tuple):
            return node[1]
        kids = [build(k) for k in sorted(children.get(node, ()), key=str)]
        if len(kids) == 1:
            return kids[0]
        if len(kids) > 2:
            raise ValueError(f"NCBI taxon {node} has {len(kids)} children here")
        return "(" + ",".join(kids) + ")"

    try:
        return build(root) + ";", None
    except ValueError as e:
        return None, str(e)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("clade", help="NCBI scientific name (e.g. Actinopterygii, Chiroptera) or taxon ID")
    ap.add_argument("--label", help="run folder name under orthofinder_v2 (required unless --list)")
    ap.add_argument("--list", action="store_true", help="show the members and gene sets; write nothing")
    ap.add_argument("--exclude", nargs="*", default=[], help="organism folders to leave out")
    ap.add_argument("--outgroup", action="append", default=[], metavar="ORGANISM or Genus_species=FASTA",
                    help="species just outside the clade, for rooting only (repeatable; see above)")
    ap.add_argument("--display-label", help='name shown in MOOP, e.g. "Planarians (Tricladida)" (clade.tsv)')
    ap.add_argument("--force", action="store_true", help="replace an existing input/ folder")
    ap.add_argument("--cpus", type=int, default=32)
    ap.add_argument("--mem", default="128gb")
    ap.add_argument("--time", default="2-00:00:00")
    ap.add_argument("--sbatch-only", action="store_true",
                    help="only rewrite <label>/run_orthofinder.sbatch of an existing run folder")
    args = ap.parse_args()
    if not args.list and not args.label:
        die("--label is required (or use --list)")
    if args.label and not re.fullmatch(r"[A-Za-z0-9_.-]+", args.label):
        die("--label may contain only letters, digits, _ . -")
    if args.sbatch_only:
        out = os.path.join(ROOT, args.label)
        if not os.path.exists(os.path.join(out, "members.tsv")):
            die(f"{out} is not a run folder (no members.tsv)")
        with open(os.path.join(out, "members.tsv")) as fh:
            n = sum(1 for _ in fh) - 1
        write_sbatch(out, args, f"{args.clade} ({args.label}): {n} MOOP gene sets")
        print(f"rewrote {out}/run_orthofinder.sbatch")
        return

    parent, rank, merged, sci, by_name = load_taxonomy()
    clade = resolve_clade(args.clade, parent, merged, sci, by_name)
    print(f"clade: {sci.get(clade, '?')} (taxon {clade}, {rank.get(clade, '?')})")

    choices, refs = read_choices(), rs_references()
    members, skipped, undecided = [], [], []
    moop = {}
    for org, db, taxid, sets in moop_organisms():
        moop[org] = (db, taxid, sets)
        if taxid is None:
            skipped.append(f"{org}: no taxon_id in organism.sqlite")
            continue
        taxid = merged.get(taxid, taxid)
        if taxid not in parent:
            skipped.append(f"{org}: taxon {taxid} is not in the NCBI taxonomy")
            continue
        if clade not in lineage(taxid, parent):
            continue
        if org in args.exclude:
            skipped.append(f"{org}: --exclude")
            continue
        try:
            picked = choose_geneset(org, sets, choices, refs)
        except NeedChoice as e:
            undecided.append(f"{org}: {e}")
            continue
        for gs, why in picked:
            # several gene sets of one organism (e.g. two unnamed species under one genus folder):
            # each is its own member, named <Organism>_<gene_set>
            name = org if len(picked) == 1 else f"{org}_{re.sub(r'[^A-Za-z0-9_-]', '_', gs)}"
            members.append((name, org, db, taxid, gs, why, [x for x in sets if x not in dict(picked)]))
    for s in skipped:
        print(f"  skipped {s}")
    if undecided:
        print(f"  NEEDS A CHOICE (add 'Organism<TAB>gene_set' lines to {CHOICES}):")
        for u in undecided:
            print(f"    {u}")
    if not members:
        die("no MOOP organism in this clade")
    for name, _, _, taxid, gs, why, others in members:
        print(f"  {name:32s} {gs:28s} {why}" + (f"  (not used: {', '.join(others)})" if others else ""))
    outgroups = resolve_outgroups(args.outgroup, clade, moop, choices, refs, parent, merged, by_name)
    for name, taxid, _, _, note in outgroups:
        print(f"  outgroup {name:23s} taxon {taxid:<10} {sci.get(taxid, '?')}: {note}")
    if args.list:
        return
    if undecided:
        die("some organisms need a gene set choice (see above)")
    if len(members) < 2:
        die("fewer than 2 organisms: nothing to compare")

    out = os.path.join(ROOT, args.label)
    inp = os.path.join(out, "input")
    if os.path.exists(inp) and not args.force:
        die(f"{inp} exists (use --force to replace it)")
    if os.path.isdir(os.path.join(out, "results")):
        die(f"{out}/results exists: this run was already started; use a new --label")
    os.makedirs(inp, exist_ok=True)
    for old in glob.glob(os.path.join(inp, "*.fa")):
        os.remove(old)
    names = [m[0] for m in members] + [o[0] for o in outgroups]
    if len(set(names)) != len(names):
        die(f"a name is used twice: {', '.join(sorted({n for n in names if names.count(n) > 1}))}")

    with open(os.path.join(out, "members.tsv"), "w") as mt, \
            open(os.path.join(out, "gene_protein.tsv"), "w") as gp:
        mt.write("member\tmoop_organism\ttaxon_id\tgene_set\tchosen_by\tgenes\tproteins_in_fasta\t"
                 "proteins_without_gene\tprotein_fasta\tprotein_fasta_md5\n")
        gp.write("member\tgene\tprotein\tlength\n")
        for name, org, db, taxid, gs, why, _ in members:
            fasta = moop_fasta(org, gs)
            seqs = read_fasta(fasta)
            best, orphans = longest_per_gene(seqs, gene_of_proteins(db, gs))
            if not best:
                die(f"{org}/{gs}: no protein in {fasta} links to a MOOP gene")
            write_fasta(os.path.join(inp, f"{name}.fa"), best)
            for gene in sorted(best):
                pid, seq = best[gene]
                gp.write(f"{name}\t{gene}\t{pid}\t{len(seq)}\n")
            mt.write(f"{name}\t{org}\t{taxid}\t{gs}\t{why}\t{len(best)}\t{len(seqs)}\t{orphans}\t"
                     f"{fasta}\t{md5sum(fasta)}\n")
            print(f"  wrote {name}.fa: {len(best)} genes (from {len(seqs)} proteins"
                  + (f", {orphans} without a MOOP gene" if orphans else "") + ")")

    og_path = os.path.join(out, "outgroups.tsv")
    if os.path.exists(og_path):
        os.remove(og_path)
    if outgroups:
        with open(og_path, "w") as fh:
            fh.write("outgroup\ttaxon_id\ttaxon_name\tgenes\tsource_fasta\tsource_fasta_md5\n")
            for name, taxid, fasta, best, note in outgroups:
                write_fasta(os.path.join(inp, f"{name}.fa"), best)
                fh.write(f"{name}\t{taxid}\t{sci.get(taxid, '')}\t{len(best)}\t{fasta}\t{md5sum(fasta)}\n")
                print(f"  wrote {name}.fa (outgroup): {note}")

    tree_path = os.path.join(out, "species_tree.nwk")
    tree, why_not = species_tree([(m[0], m[3]) for m in members] + [(o[0], o[1]) for o in outgroups], parent)
    if os.path.exists(tree_path):
        os.remove(tree_path)
    if tree:
        with open(tree_path, "w") as fh:
            fh.write(tree + "\n")
        print(f"  species tree (NCBI): {tree}")
    else:
        print(f"  no species tree written ({why_not}): OrthoFinder will infer and root it")

    label = args.display_label or f"{args.label.replace('_', ' ').capitalize()} ({sci.get(clade, clade)})"
    with open(os.path.join(out, "clade.tsv"), "w") as fh:
        fh.write(f"clade\t{args.label}\ntaxon_id\t{clade}\ntaxon_name\t{sci.get(clade, '')}\nlabel\t{label}\n")
    print(f"  label: {label} (clade.tsv)")
    write_sbatch(out, args, f"{sci.get(clade, clade)} (taxon {clade}): {len(members)} MOOP gene sets"
                 + (f" + outgroup {', '.join(o[0] for o in outgroups)}" if outgroups else ""))
    print(f"\nready: {out}\n  submit: sbatch {out}/run_orthofinder.sbatch")


def write_sbatch(out, args, what):
    runner = os.path.join(os.path.dirname(os.path.abspath(__file__)), "run_orthofinder.sh")
    with open(os.path.join(out, "run_orthofinder.sbatch"), "w") as fh:
        fh.write(f"""#!/usr/bin/bash
#SBATCH --job-name=of_{os.path.basename(out)}
#SBATCH --cpus-per-task={args.cpus}
#SBATCH --mem={args.mem}
#SBATCH --time={args.time}
#SBATCH --output={out}/orthofinder_%j.log
## OrthoFinder for {what}; see members.tsv. Made by {os.path.abspath(__file__)}.
## Runs in /scratch and copies the result to results/Results_<date>_<job>/ (see {runner}).
## To reuse an earlier run's DIAMOND results:  RESUME=1 sbatch {out}/run_orthofinder.sbatch
bash {runner} {out}
""")


if __name__ == "__main__":
    main()
