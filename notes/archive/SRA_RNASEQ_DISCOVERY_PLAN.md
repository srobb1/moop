# Public RNA-seq (SRA/ENA) discovery for the Expression browser — idea/plan

**Status:** idea captured 2026-07-14 (user, thinking out loud). Not started. Pairs with the
Expression Explorer plan.

## Idea

Given an organism's **taxon ID** (MOOP already stores it), let a user discover the public
RNA-seq experiments that exist for that organism in SRA, browse/triage them, and feed the
chosen ones into the pipeline that produces Expression-browser tracks.

## Two halves — keep them separate

### 1. Discovery / metadata — EASY (a single API call)

Given `taxon_id`, list public RNA-seq runs with metadata + download links. No compute.

- **ENA Portal API (EBI) — preferred, clean JSON:**
  ```
  https://www.ebi.ac.uk/ena/portal/api/search?result=read_run
    &query=tax_tree(<TAXID>) AND library_strategy="RNA-Seq"
    &fields=run_accession,study_accession,sample_accession,scientific_name,
            instrument_platform,library_layout,read_count,fastq_ftp,
            sample_title,study_title,first_public
    &format=json
  ```
  ENA mirrors all of SRA; `tax_tree()` includes sub-taxa (`tax_eq()` = exact); returns run
  accessions PLUS fastq FTP links.
- **NCBI E-utilities alternative:** `esearch db=sra term='txid<TAXID>[Organism] AND "rna seq"[Strategy]'`
  then `efetch rettype=runinfo&retmode=csv` for a run table. Needs an API key + email; messier shape.
- MOOP can already make these outbound calls: `httpd_can_network_connect` is on (2026-07-14) and it
  already fetches NCBI-taxonomy / Wikipedia images, so there's precedent + a caching pattern to reuse.

This half is worth building **on its own** as a curation aid: "here are the public RNA-seq
experiments for this organism — study, sample title, platform, read count, links — pick what's
worth processing." Read-only, cheap, no pipeline required.

### 2. Quantification — HARD (real compute, already done off-box)

No API/tool gives expression **against MOOP's assembly + gene set** — nobody else has the user's
assembly. Pre-computed sets (recount3, ARCHS4) are human/mouse against standard references, useless
for MOOP's non-model organisms (Nematostella, planaria, bats). So selected fastq MUST be aligned +
quantified locally: `fasterq-dump` → trim → HISAT2/STAR (align to the assembly) →
featureCounts/StringTie (quantify against the gene-set GTF/GFF) → counts/TPM + bigWig. This is a
standard Galaxy workflow and is exactly what already produces the ~1051 Nvec bigWigs. GBs per run —
not an API call. Leave it where it lives; the discovery panel just hands it accessions.

## Honest wrinkle

SRA sample metadata is messy — tissue / developmental stage / treatment are free-text and
inconsistent. So the *semantic* labeling ("adult tentacle" vs "larva") that the Expression browser
needs usually wants a human eye. Frame the feature as **find + triage**, not "auto-build the
expression browser."

## Suggested scope

- **Phase 1:** in-MOOP "Public RNA-seq for this organism" panel — taxon_id → ENA API (cached) →
  browsable, filterable list. Standalone-useful.
- **Phase 2:** wire selected accessions into the existing off-box align→bigWig pipeline (Galaxy),
  output landing in the Expression Explorer.

## Scope boundary (2026-07-14)

This plan is **external data acquisition only** — find public RNA-seq that exists in the world, triage
it, and hand accessions to the align→bigWig pipeline. It is **not** where the display/query work
lives. Everything about *reading bigWigs we already have* moved to its own docs:

- `EXPRESSION_DATA_LAYER_PLAN.md` — the shared substrate (catalog, coords, bigWig reads,
  precomputed `expression.sqlite`). **New tracks produced by this plan land here.**
- `EXPRESSION_GENE_PAGE_PLAN.md` — 1 gene × many tracks (gene-page section)
- `EXPRESSION_EXPLORER_PLAN.md` — many genes × few experiments (standalone tool)

This plan feeds their **inputs**; it does not define how the data is stored or shown.
