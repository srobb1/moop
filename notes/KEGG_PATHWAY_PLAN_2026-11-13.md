# KEGG pathway analysis for all gene sets: brainstorm (compute side, 2026-11-13)

Status: plan, nothing built. **User's decision (2026-11-13): first KEGG annotations on the gene page, then a
way to see how many genes of each pathway a gene set has.** See "Plan" at the end; the rest is the brainstorm.

## What we already have

- Every annotation run has eggNOG-mapper 2.1.12 (`<run>/eggnog_mapper/eggnog_mapper_results.tsv.gz`) with the
  columns `KEGG_ko`, `KEGG_Pathway`, `KEGG_Module`, `KEGG_Reaction`, `KEGG_rclass` and `KEGG_TC`.
  - Acropora_millepora RS_101: 19,397 of 36,461 proteins have a KO.
  - These are per protein (all isoforms), so they need collapsing to one per gene.
- MOOP loads eggNOG as orthologs (`EggNOG`) and GO terms (`EggNOG (EggNOG2GO)`), but not the KEGG columns.
- KofamScan is not installed on the cluster, and there is no KEGG or KOfam reference data under `reference_data/`.

## Step 1: assigning KOs to genes

| Option | Pros | Cons |
|---|---|---|
| A. eggNOG's KOs (have them) | Free; every gene set now | Copied from the nearest eggNOG ortholog: weaker for distant groups (planarians, cnidarians); can over-assign |
| B. KofamScan (KEGG's own models for each KO, each with its own score cutoff) | KEGG's standard; tuned cutoff per KO; reproducible | Install + ~15 GB KOfam database; ~1-3 CPU-hours per proteome, ~100 gene sets |
| C. BlastKOALA / GhostKOALA (KEGG's web servers) | Most accurate | Web only, rate-limited: not workable for ~100 gene sets |

Leaning: **B as the main source, A as a cross-check.** This fits "no name beats a wrong name": KofamScan's
per-KO cutoffs refuse weak calls. Where A and B agree the evidence is strong; where they disagree, the gene can be
flagged. Run it on one protein per gene, as OrthoFinder does (the longest), so isoforms aren't counted twice.
It could become part of the annotation pipeline, or run on our side as the canonical DIAMOND was.

## Step 2: uses

1. **Gene pages:** each gene's KO, pathways and modules, linking to KEGG. Cheap: load them as a new
   annotation source.
2. **Pathway completeness per gene set:** for each KEGG module, the fraction of its steps present. Across all
   gene sets this gives a species x pathway matrix. Tools: anvi'o `estimate-metabolism`, KEGGDecoder, or a
   small script over KEGG's module definitions. Combines with the clades, e.g. what planarians lack that other
   flatworms have, or what all bats share.
3. **Enrichment on gene lists** (differentially expressed genes, a clade-specific family, genes missing in one
   species): which pathways are over-represented. Same approach as the goatools GO work. The background should
   be the gene set's genes that have a KO, not all genes.
4. **Clade presence/absence:** combine KOs with the OrthoFinder families (N0 HOGs) to tell "the family is lost
   in this species" from "the gene is there but has no KO".

## Caveats

- **Not found does not mean absent.** KEGG is built around vertebrates, model organisms and microbes, so
  planarian or cnidarian genes often get no KO even when the pathway is there. Fragmented gene sets make it
  worse (Montipora: 4,020 proteins under 100 aa). Show completeness beside a quality measure (BUSCO, which the
  gene-set pages already have).
- **Licensing:** KEGG's REST API is free for academic use, but its pathway images and full data may not be
  republished without a licence. Show KO and pathway IDs and names and link out to KEGG; do not host the maps.
- **Versioning:** take one dated snapshot of KEGG's pathway and module definitions (KEGG REST `list`/`link`)
  and record its date in meta, as the Ensembl releases are recorded.
- **Non-animal gene sets:** Bradyrhizobium and Medicago would need KEGG's prokaryote and plant pathways, so keep
  them apart from the animal matrix.

## Suggested order

1. Load eggNOG's KOs, pathways and modules into MOOP (already computed: quick value on the gene pages).
2. Install KofamScan. Test it on Danio, Acropora, Smed (S3h1) and one bat, against eggNOG's calls.
   Decide whether to run it on everything.
3. Build the completeness matrix and enrichment from whichever source wins.

## Plan (decided 2026-11-13)

### Part 1: KEGG on the gene page (eggNOG's KOs, already computed)

- **Reference snapshot** (once, dated; refreshed now and then): KEGG REST `list/ko`, `list/pathway`, `list/module`,
  `link/pathway/ko`, `link/module/ko`, plus the pathway classes (`get br:br08901`), into
  `reference_data/kegg/<date>/`. Record the date as the source version.
- **Parser** (new, in `analysis_parsers/`): eggNOG `KEGG_ko` per protein -> per gene (the union over its proteins;
  most genes have one KO).
  - eggNOG lists each pathway twice (`ko00010` and `map00010`): keep `map`.
  - Recompute the pathways and modules from the KOs with the snapshot, rather than taking eggNOG's columns, so
    every gene set uses the same KEGG version.
- **Into organism.sqlite** as annotation sources, like the other annotations:
  - `KEGG Orthology (eggNOG)`: `K00844` "HK; hexokinase", linking to `https://www.kegg.jp/entry/K00844`
  - `KEGG Pathway (eggNOG)`: `map00010` "Glycolysis / Gluconeogenesis", linking to `https://www.kegg.jp/pathway/map00010`
  - `KEGG Module (eggNOG)`, optional
  - "(eggNOG)" in the names keeps room for a later KofamScan source beside it.
- **Web side:** a KEGG card or rows on the gene page. Links only: no hosted KEGG images (licensing).

### Part 2: genes per pathway in each gene set

For each gene set x pathway (pathway = its KOs in the snapshot):

| column | meaning |
|---|---|
| pathway, name, class | `map00010`, "Glycolysis / Gluconeogenesis", "Metabolism; Carbohydrate metabolism" |
| kos_total | KOs in the pathway (KEGG's reference) |
| kos_found | of those, KOs with at least one gene in this gene set |
| genes | genes in this gene set with any of those KOs |
| coverage | kos_found / kos_total |
| ko_list | the KOs found, so the site can build a KEGG Mapper link that colours them on KEGG's own map |

- **Where:** a table in organism.sqlite (or a per-gene-set TSV), built at load time from the Part 1 annotations.
  The web side lists it on the gene-set page, and each pathway links to its genes (an annotation search).
- **Across gene sets:** the same table stacked gives the species x pathway matrix (later: comparisons, clades).
- **Show beside it:** the gene set's BUSCO (low coverage in a fragmented set means "not annotated", not "absent").
  Possibly also hide or mark pathway classes that don't apply (e.g. plant- or microbe-only maps for animals).

### Part 3: coloured pathway maps per gene set (user, 2026-11-13: "color all the pieces an organism, assembly, gene set has")

KEGG draws the maps; we only link to kegg.jp with the KOs to colour (no hosted images: no licensing problem).

1. One pathway, one gene set: `https://www.kegg.jp/pathway/map00010+K00844+K01810+...` highlights those KOs.
   Built by the site from Part 2's `ko_list`, a link on every pathway row.
2. Own colours (KEGG Color Mapper): a colour per KO. E.g. dark green = several genes, light green = one gene; or
   two gene sets on one map: this one only / the other only / both (blue / orange / purple), useful with the clades.
3. Whole gene set at once (KEGG Mapper Reconstruct): takes the full gene<TAB>KO list and shows every pathway and
   module with counts and coloured maps. Offer the gene set's gene-KO list as a download to paste there; a direct
   button (form post to KEGG) to be tested -- KEGG may not accept posts from another site.

Data side: Part 1 gives the per-gene KOs; Part 2 keeps the KOs found per pathway; add an export of the gene-KO
list per gene set (TSV, MOOP gene IDs).

### Not decided yet

- Union of KOs over a gene's proteins, or the KOs of its longest protein only (union is the default above).
- Table in organism.sqlite vs a TSV; the web agent should say which suits them.
- KofamScan: after Part 2, test on 3-4 gene sets against eggNOG and decide whether to add it as a second source.
