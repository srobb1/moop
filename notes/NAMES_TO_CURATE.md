# Gene names that need a human (running list)

Started 2026-11-24. Automatic names come from a best match (NV2: RefSeq names first). That works for most
genes but fails where a gene family split differently in the organism's lineage: the best match names
a gene after a relative it is not an ortholog of. These need a curated name.

**Who owns what:** this is a WEB-side hand-off list. How names are assigned (and how a curated name
overrides the automatic one) belongs to the pipeline — `notes/GENE_NAMING_METHODS.md`,
`config/build_and_load_db/**` (compute). Add a row here when you see a wrong or misleading name;
compute/Sofia decide the fix.

| Organism / gene set | Gene | Shown name | Why it misleads | Suggested / curated name | Status |
|---|---|---|---|---|---|
| Nematostella vectensis / NV2 | NV2g003593000.1 | paired box protein Pax-6 [SRC\|1to1\|nvec] | Not a Pax gene: the protein has a homeodomain and NO paired domain; InterPro IPR052488 and PANTHER PTHR46639 = Dmbx (diencephalon/mesencephalon homeobox); best human hit DMBX1; the 2 neighbouring genes are Dmbx too. The name is RefSeq's (XP_048589240.1), copied as `SRC`; `1to1` = NV2 vs RefSeq gene (OMA), NOT vs human PAX6. (Also: cnidarians have PaxA-D, no Pax6.) | Dmbx-like -- check the published Nvec homeobox names | Checked 11-24: RefSeq name confirmed as the source |

**Pipeline question (compute):** a `SRC` name is kept even when the gene's OWN evidence (PANTHER family,
InterPro, best human hit) points to a different family. Should step 2 check a source name against that evidence and
flag or drop a clash? This one would have been caught.

**Families to scan for the same problem** (lineage-specific splits, name by best hit): Pax, Hox/ParaHox,
Wnt, Sox, Fox, T-box, nuclear receptors — in cnidarians, planarians and other non-model organisms.
