# KEGG pathway analysis for all gene sets: brainstorm (compute side, 2026-11-13)

Status: ideas only, nothing built. The open question is the main goal (gene-page annotation, comparisons across
species, or enrichment for expression results), which decides where to start.

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
