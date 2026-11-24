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
| Nematostella vectensis / NV2 | NV2g003593000.1 | paired box protein Pax-6 [SRC\|1to1\|nvec] | Pax6 is a bilaterian gene; cnidarians have PaxA–D, none a 1:1 Pax6. The paired domain makes PAX6 the best hit. Also: why is it called `1to1`? | One of PaxA–D — check orthologs / Matus et al. 2007 (https://pubmed.ncbi.nlm.nih.gov/?term=Matus+Pax+Nematostella+2007) | Sofia checking RefSeq name (11-24) |

**Families to scan for the same problem** (lineage-specific splits, name by best hit): Pax, Hox/ParaHox,
Wnt, Sox, Fox, T-box, nuclear receptors — in cnidarians, planarians and other non-model organisms.
