#!/usr/bin/env python3
"""eggNOG-mapper's KEGG KOs -> MOOP annotation files (KEGG Orthology, KEGG Pathway, KEGG Module).

    parse_KEGG_to_MOOP_TSV.py <eggnog_mapper_results.tsv[.gz]> --kegg <REFERENCE_DATA>/kegg/current \
        [--eggnog-version "emapper-2.1.12 DB_5.0.2"] [--keep-global] [--outdir .]

Writes, in --outdir (rows per protein, as the other eggNOG files; the loader attaches them to the protein):
  KEGG_Orthology.kegg.moop.tsv   protein  K00844    "HK; hexokinase [EC:2.7.1.1]"                     1
  KEGG_Pathway.kegg.moop.tsv     protein  map00010  "Glycolysis / Gluconeogenesis (Metabolism; ...)"  1
  KEGG_Module.kegg.moop.tsv      protein  M00001    "Glycolysis (Embden-Meyerhof pathway), ..."       1

The score (feature_annotation.score is a number) is how many alternative KOs eggNOG gave the protein: 1 = one KO,
4 = one of four. It is on the pathway and module rows too, so the site can mark all of them as ambiguous.

- The KOs come from eggNOG (column KEGG_ko, "ko:K00844,ko:K12407"). eggNOG gives every KO of the matched eggNOG
  family, so a protein with several KOs has alternatives, not several functions: its score is 4 for "one of
  four", and the site can mark those as ambiguous. Such a protein gets only the pathways and modules that ALL its KOs are in
  (true whichever KO is right); --all-alternatives gives it every pathway of any of its KOs.
- Pathways and modules are worked out again from the KOs with the KEGG snapshot (fetch_kegg_reference.sh), not
  taken from eggNOG's columns, so every gene set uses the same KEGG version. Only the generic map pathways are
  used (map00010, not ko00010: same map).
- The "Global and overview maps" (map01100 Metabolic pathways, map01200 Carbon metabolism, ...) are left out of the
  gene rows unless --keep-global: they would tag most metabolic genes and say little about any one of them.
- KOs that the snapshot no longer lists (retired since eggNOG's database was built) are dropped and counted.
"""
import argparse
import gzip
import json
import os
import re
import sys
import time


def read_pairs(path):
    with open(path) as fh:
        for line in fh:
            parts = line.rstrip("\n").split("\t")
            if len(parts) >= 2:
                yield parts[0], parts[1]


def pathway_classes(path):
    """map id -> 'Class; Subclass' from br08901 (json)"""
    out = {}

    def walk(node, trail):
        name = node.get("name", "")
        kids = node.get("children")
        if kids:
            for k in kids:
                walk(k, trail + [name] if name and name != "br08901" else trail)
        else:
            m = re.match(r"(\d{5})\s+", name)
            if m:
                out["map" + m.group(1)] = "; ".join(trail)
    walk(json.load(open(path)), [])
    return out


def load_kegg(kdir):
    ko_name = dict(read_pairs(os.path.join(kdir, "ko.tsv")))
    pw_name = dict(read_pairs(os.path.join(kdir, "pathway.tsv")))
    md_name = dict(read_pairs(os.path.join(kdir, "module.tsv")))
    ko_pw, ko_md = {}, {}
    for ko, p in read_pairs(os.path.join(kdir, "ko_pathway.tsv")):
        if p.startswith("path:map"):
            ko_pw.setdefault(ko[3:], set()).add(p[5:])
    for ko, m in read_pairs(os.path.join(kdir, "ko_module.tsv")):
        ko_md.setdefault(ko[3:], set()).add(m[3:])
    classes = pathway_classes(os.path.join(kdir, "pathway_classes.json"))
    return ko_name, pw_name, md_name, ko_pw, ko_md, classes


def header(source, version, acc_url, src_url, atype, date, cols):
    return (f"## Annotation Source: {source}\n## Annotation Source Version: {version}\n"
            f"## Annotation Accession URL: {acc_url}\n## Annotation Source URL: {src_url}\n"
            f"## Annotation Type: {atype}\n## Annotation Creation Date: {date}\n"
            + "\t".join(["## Gene"] + cols) + "\n")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("eggnog", help="eggnog_mapper_results.tsv or .tsv.gz")
    ap.add_argument("--kegg", required=True, help="KEGG snapshot folder (fetch_kegg_reference.sh), e.g. .../kegg/current")
    ap.add_argument("--eggnog-version", default="", help='e.g. "emapper-2.1.12 DB_5.0.2" (goes into the source version)')
    ap.add_argument("--keep-global", action="store_true", help="keep the Global and overview maps in the gene rows")
    ap.add_argument("--all-alternatives", action="store_true",
                    help="a protein with several KOs gets the pathways of any of them (default: of all of them)")
    ap.add_argument("--outdir", default=".")
    args = ap.parse_args()

    kdir = os.path.realpath(args.kegg)
    snapshot = os.path.basename(kdir)
    ko_name, pw_name, md_name, ko_pw, ko_md, classes = load_kegg(kdir)
    version = f"KEGG {snapshot}" + (f"; KOs from eggNOG-mapper {args.eggnog_version}" if args.eggnog_version else "; KOs from eggNOG-mapper")
    date = time.strftime("%Y-%m-%d", time.localtime(os.path.getmtime(args.eggnog)))

    opener = gzip.open if args.eggnog.endswith(".gz") else open
    col = None
    n_prot = n_ko_prot = n_multi = 0
    retired, skipped_global, dropped_partial = set(), 0, 0
    ko_rows, pw_rows, md_rows = [], [], []
    with opener(args.eggnog, "rt") as fh:
        for line in fh:
            if line.startswith("#query"):
                col = {h: i for i, h in enumerate(line[1:].rstrip("\n").split("\t"))}
                continue
            if line.startswith("#") or not line.strip():
                continue
            if col is None:
                sys.exit("ERROR: no '#query' header line before the results (not an eggNOG-mapper 2 annotations file?)")
            f = line.rstrip("\n").split("\t")
            n_prot += 1
            cell = f[col["KEGG_ko"]]
            if cell in ("-", ""):
                continue
            kos = []
            for k in cell.split(","):
                k = k.strip()
                k = k[3:] if k.startswith("ko:") else k
                if k in ko_name:
                    kos.append(k)
                else:
                    retired.add(k)
            kos = sorted(set(kos))
            if not kos:
                continue
            prot = f[col["query"]]
            n_ko_prot += 1
            n_multi += len(kos) > 1
            for k in kos:
                ko_rows.append((prot, k, ko_name[k], str(len(kos))))
            via_pw, via_md = {}, {}
            for k in kos:
                for p in ko_pw.get(k, ()):
                    via_pw.setdefault(p, []).append(k)
                for m in ko_md.get(k, ()):
                    via_md.setdefault(m, []).append(k)
            if len(kos) > 1 and not args.all_alternatives:
                n_pw, n_md = len(via_pw), len(via_md)
                via_pw = {p: ks for p, ks in via_pw.items() if len(ks) == len(kos)}
                via_md = {m: ks for m, ks in via_md.items() if len(ks) == len(kos)}
                dropped_partial += n_pw - len(via_pw) + n_md - len(via_md)
            for p, ks in sorted(via_pw.items()):
                cls = classes.get(p, "")
                if cls.endswith("Global and overview maps") and not args.keep_global:
                    skipped_global += 1
                    continue
                name = pw_name.get(p, p)
                pw_rows.append((prot, p, f"{name} ({cls})" if cls else name, str(len(kos))))
            for m, ks in sorted(via_md.items()):
                md_rows.append((prot, m, md_name.get(m, m), str(len(kos))))

    files = [
        ("KEGG_Orthology.kegg.moop.tsv", "KEGG Orthology (eggNOG)", "https://www.kegg.jp/entry/", "Gene Families",
         ["KEGG_KO", "Description", "Alternative_KOs"], ko_rows),
        ("KEGG_Pathway.kegg.moop.tsv", "KEGG Pathway (eggNOG)", "https://www.kegg.jp/pathway/", "Pathways",
         ["KEGG_Pathway", "Description", "Alternative_KOs"], pw_rows),
        ("KEGG_Module.kegg.moop.tsv", "KEGG Module (eggNOG)", "https://www.kegg.jp/module/", "Pathways",
         ["KEGG_Module", "Description", "Alternative_KOs"], md_rows),
    ]
    for name, source, acc_url, atype, cols, rows in files:
        path = os.path.join(args.outdir, name)
        with open(path + ".tmp", "w") as out:
            out.write(header(source, version, acc_url, "https://www.kegg.jp/", atype, date, cols))
            for r in rows:
                out.write("\t".join(r) + "\n")
        os.replace(path + ".tmp", path)
        print(f"{name}: {len(rows)} rows")
    print(f"proteins {n_prot}, with a KO {n_ko_prot} ({100 * n_ko_prot / max(n_prot, 1):.0f}%), with several KOs "
          f"(eggNOG alternatives) {n_multi}; KEGG snapshot {snapshot}")
    if retired:
        print(f"note: {len(retired)} KOs not in the snapshot (retired) dropped, e.g. {', '.join(sorted(retired)[:5])}")
    if dropped_partial:
        print(f"note: {dropped_partial} pathway/module rows left out: not shared by all of a protein's alternative KOs "
              "(--all-alternatives keeps them)")
    if skipped_global:
        print(f"note: {skipped_global} protein-pathway rows for Global and overview maps left out (--keep-global keeps them)")


if __name__ == "__main__":
    main()
