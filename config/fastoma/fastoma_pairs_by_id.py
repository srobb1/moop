#!/usr/bin/env python3
"""Ortholog pairs of a FastOMA run by the orthoxml's internal gene ids, which are unique across species.

    <FastOMA conda env>/bin/python fastoma_pairs_by_id.py FastOMA_HOGs.orthoxml orthologs_by_id.tsv.gz

FastOMA's own orthologs.tsv.gz gives protein ids only, and two gene sets can use the same ids (two Trinity
transcriptomes both have TRINITY_DN0_c0_g1), so a pair cannot always be placed in a species. The orthoxml
says which species each internal id belongs to; build_clade_homologs.py reads this file instead.
Uses FastOMA's own pair extraction (the code behind fastoma-helper pw-rel), so it needs FastOMA's env.
"""
import gzip
import sys

from lxml import etree
from FastOMA.zoo.hog import transform


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    orthoxml, out = sys.argv[1:]
    xml = etree.parse(orthoxml)
    n = 0
    with gzip.open(out + ".tmp", "wt") as fh:
        for a, b in transform.iter_pairwise_relations(xml, rel_type="ortholog"):
            fh.write(f"{a}\t{b}\n")
            n += 1
    import os
    os.replace(out + ".tmp", out)
    print(f"{n} ortholog pairs by internal id -> {out}")


if __name__ == "__main__":
    main()
