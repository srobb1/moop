#!/usr/bin/env python3
"""Set up a FastOMA run with the same inputs as an OrthoFinder clade run, so the two compare directly.

    setup_fastoma_run.py <clade>          # <clade> = an OrthoFinder run folder under OF_ROOT (planarians, ...)
    sbatch $FASTOMA_ROOT/<clade>/run_fastoma.sbatch

Writes $FASTOMA_ROOT/<clade>/:
  in_folder/proteome/<member>.fa   the OrthoFinder run's input/ (one protein per gene, MOOP gene IDs)
  in_folder/species_tree.nwk       rooted, from the NCBI taxonomy; FastOMA accepts unresolved nodes, so every
                                   clade gets one (OrthoFinder needs a fully resolved tree). Internal nodes are
                                   named after their NCBI taxon (unique, no spaces)
  members.tsv, clade.tsv           copied from the OrthoFinder run
  run_fastoma.sbatch               runs run_fastoma.sh
Settings: fastoma.conf next to this script (or $FASTOMA_CONF); the taxonomy is OF_ROOT/taxonomy.
"""
import argparse
import os
import re
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))


def read_conf():
    path = os.environ.get("FASTOMA_CONF") or os.path.join(HERE, "fastoma.conf")
    if not os.access(path, os.R_OK):
        sys.exit(f"ERROR: no settings file {path} (copy fastoma.conf.example to fastoma.conf)")
    keys = ["FASTOMA_ROOT", "OF_ROOT"]
    script = 'source "$1" >/dev/null || exit 1; shift; for k in "$@"; do printf "%s\\t%s\\n" "$k" "${!k}"; done'
    out = subprocess.run(["bash", "-c", script, "read_conf", path] + keys,
                         capture_output=True, text=True, check=True).stdout
    conf = dict(line.split("\t", 1) for line in out.splitlines())
    missing = [k for k in keys if not conf.get(k)]
    if missing:
        sys.exit(f"ERROR: {path} does not set {', '.join(missing)}")
    return conf


def load_taxonomy(taxdir):
    parent, sci = {}, {}
    with open(os.path.join(taxdir, "nodes.dmp")) as fh:
        for line in fh:
            fields = line.split("\t|\t")
            parent[int(fields[0])] = int(fields[1])
    with open(os.path.join(taxdir, "names.dmp")) as fh:
        for line in fh:
            fields = line.rstrip("\t|\n").split("\t|\t")
            if fields[3] == "scientific name":
                sci[int(fields[0])] = fields[1]
    return parent, sci


def lineage(taxid, parent):
    path = [taxid]
    while path[-1] in parent and parent[path[-1]] != path[-1]:
        path.append(parent[path[-1]])
    return path[::-1]


def species_tree(members, parent, sci):
    """rooted newick over the members (name, taxid); unresolved nodes kept; internal nodes named"""
    children = {}
    for name, taxid in members:
        path = lineage(taxid, parent)
        for a, b in zip(path, path[1:]):
            children.setdefault(a, set()).add(b)
        children.setdefault(path[-1], set()).add(("leaf", name))
    root = lineage(members[0][1], parent)[0]
    used = set(name for name, _ in members)

    def label(taxid):
        base = re.sub(r"[^A-Za-z0-9_.-]+", "_", sci.get(taxid, f"taxon{taxid}")).strip("_")
        text, n = base, 1
        while text in used:
            n += 1
            text = f"{base}_{n}"
        used.add(text)
        return text

    def build(node):
        if isinstance(node, tuple):
            return node[1]
        kids = [build(k) for k in sorted(children.get(node, ()), key=str)]
        if len(kids) == 1:
            return kids[0]
        return "(" + ",".join(kids) + ")" + label(node)

    return build(root) + ";"


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("clade", help="an OrthoFinder run folder under OF_ROOT")
    ap.add_argument("--force", action="store_true", help="replace an existing in_folder")
    ap.add_argument("--time", default="3-00:00:00", help="time limit of the job that runs Nextflow")
    args = ap.parse_args()
    conf = read_conf()
    of_run = os.path.join(conf["OF_ROOT"], args.clade)
    out = os.path.join(conf["FASTOMA_ROOT"], args.clade)
    for f in ("members.tsv", "input"):
        if not os.path.exists(os.path.join(of_run, f)):
            sys.exit(f"ERROR: {of_run} is not a clade run folder (no {f})")
    in_folder = os.path.join(out, "in_folder")
    if os.path.exists(in_folder) and not args.force:
        sys.exit(f"ERROR: {in_folder} exists (use --force to replace it)")
    if os.path.exists(in_folder):
        shutil.rmtree(in_folder)
    os.makedirs(os.path.join(in_folder, "proteome"))

    with open(os.path.join(of_run, "members.tsv")) as fh:
        hdr = fh.readline().rstrip("\n").split("\t")
        rows = [dict(zip(hdr, line.rstrip("\n").split("\t"))) for line in fh if line.strip()]
    name_col = "member" if "member" in hdr else "organism"
    members = [(r[name_col], int(r["taxon_id"])) for r in rows]
    for name, _ in members:
        src = os.path.join(of_run, "input", f"{name}.fa")
        if not os.path.exists(src):
            sys.exit(f"ERROR: {src} missing")
        shutil.copy(src, os.path.join(in_folder, "proteome", f"{name}.fa"))
    for f in ("members.tsv", "clade.tsv"):
        if os.path.exists(os.path.join(of_run, f)):
            shutil.copy(os.path.join(of_run, f), out)

    parent, sci = load_taxonomy(os.path.join(conf["OF_ROOT"], "taxonomy"))
    tree = species_tree(members, parent, sci)
    with open(os.path.join(in_folder, "species_tree.nwk"), "w") as fh:
        fh.write(tree + "\n")

    runner = os.path.join(HERE, "run_fastoma.sh")
    with open(os.path.join(out, "run_fastoma.sbatch"), "w") as fh:
        fh.write(f"""#!/usr/bin/bash
#SBATCH --job-name=fastoma_{args.clade}
#SBATCH --cpus-per-task=2
#SBATCH --mem=8gb
#SBATCH --time={args.time}
#SBATCH --output={out}/fastoma_%j.log
## FastOMA for the {len(members)} gene sets of the OrthoFinder run {of_run} (same inputs).
## This job runs Nextflow, which submits FastOMA's own jobs to Slurm. Made by {os.path.abspath(__file__)}.
bash {runner} {out}
""")
    print(f"{len(members)} proteomes, species tree: {tree}")
    print(f"ready: {out}\n  submit: sbatch {out}/run_fastoma.sbatch")


if __name__ == "__main__":
    main()
