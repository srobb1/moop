<?php
/**
 * llms.txt — the agent guide: how an AI assistant (or a script) uses this site's data.
 *
 * Served at {site}/llms.txt (nginx: location = <prefix>/llms.txt -> llms.php; also llm.txt) and
 * written by the llms.txt convention (llmstxt.org): Markdown, an H1, a one-line summary, then
 * sections of links. Every link is complete (moop_public_url()), so the text works pasted anywhere,
 * and follows the site when it moves (public_url, link_prefix).
 *
 * It documents api/v1/ only. Those names are a PROMISE (api/v1/catalog.php): once listed here they
 * never change, so add an endpoint here only when it is meant to stay. The people's version of this
 * page is the help topic 'ai-assistants' (tools/pages/help/ai-assistants.php) -- change both together.
 *
 * Opt-in per site (setting ai_guide, moop_ai_guide()): off -> 404; another site's guide -> 302 there.
 * The same text for every visitor (what a visitor may OPEN is decided by each endpoint), so it is
 * cacheable. Not translated: agents read English, and the API's field names are English anyway.
 */

include_once __DIR__ . '/tools/tool_init.php';

$guide = moop_ai_guide();
if (!$guide) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}
if (!$guide['here']) {
    header('Location: ' . $guide['llms'], true, 302);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$config = ConfigManager::getInstance();
$title  = $config->getString('siteTitle', 'MOOP');
$api    = fn(string $path) => moop_public_url('api/v1/' . $path);
$page   = fn(string $path) => moop_public_url($path);

echo <<<TXT
# {$title}

> Genome assemblies, genes, transcripts, proteins and their functional annotations (GO terms,
> protein domains, orthologs in related species, predicted subcellular location, expression) for
> many organisms, with a read-only JSON API for AI assistants and scripts. Without a login, and
> from outside the institute network, you see the public data only.

This guide is for an AI assistant working for a person. Use the JSON API below rather than the web
pages: a gene page is ~0.5 MB of HTML, its record here is a few KB.

Can you use it? You need to open addresses you build yourself (filling in the {organism} and {id}
parts below) -- for example with curl or a short script. A coding agent on the person's computer (such
as Claude Code) can. Many chat apps cannot: they only open addresses already in the conversation. If
that is you, tell the person now, plainly: to work with {$title} they should switch to a coding agent
such as Claude Code; meanwhile they can paste a full address here, or open the gene page, choose
Download, save the gene as Markdown and attach it. Do not guess answers in place of the data.

Rules:

- Plain GET requests, no login, no token. JSON unless a link says otherwise (format=md, format=tsv).
- Start with the catalog: every other call takes `organism`, `assembly` and `gene_set` values from it.
  Never guess them.
- An organism often has several gene sets, and none is marked "newest" or "best". If the person did not
  name one, ask which to use before you start: list the organism's gene sets with what the catalog gives
  for each -- assembly, `kind` (genome or transcripts), number of genes, `percent_of_genes_with` (GO,
  domains, orthologs, KEGG) and BUSCO -- in a short table. Do not pick one from its name or ID. If the
  person wants all of them, answer per gene set.
- 404 means "not found or not public" -- the same answer either way, on purpose. Do not retry it, and do
  not try other names to find out what exists.
- Lists are capped and say so (a total next to the items shown). Report the cap; do not page around it.
- Do not read anything into a gene name's capitals. Names follow their source's convention (human-style
  capitals, or the species' own, e.g. lower case in zebrafish), and capitals may or may not mean the name
  was inferred from a human gene. Two genes can share a name (zebrafish `txn` and `TXN` are two
  co-orthologs of human TXN). Identify genes by ID; the gene record's naming field says where each name
  came from.
- When you use the data, cite this site and the original sources (InterPro, UniProt, ...). Each record
  gives the source of every annotation.
- These api/v1 links are stable: they will not be renamed or removed.

## Please be polite

This is a research group's server, shared with the people using it in a browser. Use it the way a careful
person would:

- One request at a time. Wait for each answer before sending the next; never send requests in parallel.
- Ask for what the person asked for -- not the whole site. Do not crawl, mirror or harvest.
- Use the list calls: one request with up to 200 IDs, not 200 requests with one ID each.
- For a whole gene set or genome, download its FASTA or GFF file once (links in the catalog) instead of
  asking gene by gene.
- Keep what you fetched; do not ask for the same thing twice in one task.
- Stay well inside the limits: the downloads, sequences and gene-list calls allow about 20 requests a
  minute per address, the other calls about 10 a second. On HTTP 429, stop and wait at least a minute.
- Send a User-Agent that names your tool; a script should include a contact address.
- Never try to get around a limit or a login (other addresses, other names, guessing IDs).

## Start here

- [Catalog]({$api('catalog.php')}): every organism, assembly and gene set you may open, with complete links to each page and every download (genome, protein, transcript and CDS FASTA, GFF). Also, to choose a gene set and to know what you can ask: per assembly its `kind` (genome or transcripts); per gene set the number of genes, `percent_of_genes_with` (GO, domains, orthologs, KEGG), BUSCO, `annotation_types` (every type with its gene count) and `expression_experiments` (RNA-seq); per organism `curated_anatomy` (curated "expressed in" records from papers). Read it once per task and keep it.
- [Help for people]({$page('help.php?topic=ai-assistants')}): the same, written for the person using the assistant, with example questions.

## Assemblies

- [Assembly statistics]({$api('assembly.php?organism={organism}')}): for each assembly (or `assembly=` one): kind (genome or assembled transcripts), size, sequences, N50/L50, N90, longest, contig N50, GC and gap share; and per gene set the gene and transcript counts and BUSCO completeness.

## Find genes

- [Keyword search]({$api('search.php?organism={organism}&q={words}')}): genes by name, description or annotation -- "piwi", "heat shock protein", an ID; `"double quotes"` for a phrase. The site's own Search, rolled up to genes, each with up to 3 `matched_in` reasons. `in=names` keeps only name and description matches (fewer, closer). `organism` takes up to 10; with one, `assembly` and `gene_set` narrow it. Capped: if it says it stopped, narrow the search -- do not page around it. `format=tsv`.
- [From human genes]({$api('human_genes.php?organism={organism}&human={TP53},{PIWIL1}')}): the genes here for human genes (HGNC symbols or HGNC:ids, up to 200), with their evidence, strongest first: OMA orthologs, then MOOP's "closest human gene" -- as an ortholog, or as a homolog by similarity only, which the evidence says. Report which. Nothing found is not proof of absence: the gene may be linked to a paralog, or be named by a curator from functional studies with no closest human gene (Smed "p53") -- then try keyword search with the name. `organism` takes up to 20. `format=tsv`.
- [Neighbouring genes]({$api('nearby_genes.php?organism={organism}&id={id}&n=5')}): the genes upstream and downstream of a gene (along ITS strand), `n` per side or every one within `distance` bases. Or the genes in a region the person gives: `assembly`, `gene_set`, `seqname`, `start`, `end` (`flank=` adds bases). MOOP holds no peaks or QTLs -- the person supplies coordinates. Not on transcriptomes. `format=tsv`.

## Expression

- [Genes by RNA-seq expression]({$api('expression_genes.php?organism={organism}&assembly={assembly}&gene_set={gene_set}')}): only for gene sets with `expression_experiments` in the catalog. Without `experiment` and `group` it lists the experiments and their groups (conditions, stages, tissues); with them, the genes detected in that group, highest first, or `sort=specific` for the genes that mark it (ratio to the next-highest group). `group` may be the start of a name ("Stage 6"). `n` up to 2,000. Values are in the experiment's unit (TPM, RPKM ...). One gene's expression is in its gene record.
- [Genes curated in an anatomy term]({$api('anatomy_genes.php?organism={organism}&term={PLANA:0000099}')}): only for organisms with `curated_anatomy`. Genes reported in papers as expressed in the term or any part of it (find the term with ontology_search `o=PLANA`), per reference gene set, most-curated first, with evidence (in situ, single-cell ...), stages and PubMed ids. `ev=` narrows the evidence kind. `format=tsv`.

## Genes

- [Gene record]({$api('gene.php?organism={organism}&id={id}')}): one gene as a record -- location, transcripts and proteins, name and how it was named, annotations (GO, domains, predicted subcellular location, ...), expression calls, orthologs and gene family in related species, other IDs. `id` may be a gene, transcript, CDS or protein ID; the record is always the gene. `format=md` gives Markdown to read or quote. `go_sources=panther,oma` limits GO terms to those sources (eggnog, panther, oma, interpro). HTTP 300 = the ID is in more than one gene set: pick one of the `choices`.
- [Find an ID]({$api('id_search.php?q={id}')}): which organism / assembly / gene set holds an ID (or IDs starting with it).
- [All annotations of a gene]({$api('annotations.php?organism={organism}&uniquename={id}')}): the full table as CSV (the gene record's lists are capped at 100 per type).
- [Translate IDs]({$api('translate.php?organism={organism}&ids={id1},{id2}')}): the same genes under their other IDs (other assemblies, older transcriptomes, array probes). Gene or transcript IDs; a gene ID is translated through its transcripts (`via` says which). Up to 500 IDs; `format=tsv`. With `organism` alone: which ID sources exist.
- [Orthologs for a gene list]({$api('orthologs.php?organism={organism}&ids={id1},{id2}')}): orthologs in related species, up to 200 IDs; `family=1` adds the rest of each gene family; `format=tsv`.

## Protein domains and KEGG pathways

- [Annotations for a gene list]({$api('gene_annotations.php?organism={organism}&ids={id1},{id2}&type=domains')}): chosen kinds of annotation for up to 200 genes: `type=domains`, `pathways`, `go`, `protein_features` (DeepLoc's predicted location IN THE CELL, signal peptides, transmembrane helices -- not tissue), `closest_genes` (closest human gene, ortholog or homolog), `orthologs` (OMA / eggNOG, every species -- long), or `all`; comma-separate several. `go_sources=` as for the gene record. `format=tsv` (one row per annotation) or `format=wide` (one row per gene, a column per type: a table to hand the person). The usual recipe: build the list with search, domain, GO, pathway, expression or anatomy calls, then annotate it here.
- [Genes with protein domains]({$api('domain_genes.php?organism={organism}&domains={PF00085}')}): genes carrying any of the domains (Pfam PF..., InterPro IPR..., SMART, CDD, PROSITE; up to 50). `name=thioredoxin` alone does NOT return genes: it lists the domains whose name contains the word, with how many genes each has per gene set -- one word also catches superfamilies and loose relatives (thioredoxin: 58 domains). Pick the accession(s) that mean what the person asked (often the Pfam entry and its InterPro entry), say which you chose, then ask with `domains=`. `match=all` keeps genes with every listed domain. `organism` takes up to 20 names, comma-separated; with one organism, `assembly` and `gene_set` narrow it. At most 2,000 genes per gene set; `format=tsv`.
- [KEGG pathways of a gene set]({$api('pathways.php?organism={organism}&assembly={assembly}&gene_set={gene_set}')}): every pathway with its gene count and KOs; find a pathway's id by name here. Without `gene_set`: every gene set of the organism, counts per gene set.
- [Genes in a KEGG pathway]({$api('pathway_genes.php?organism={organism}&assembly={assembly}&gene_set={gene_set}&pathway={map04142}')}): genes in the pathway(s) or module(s) (`04142`, `ko04142` and `map04142` all work; modules `M00001`). `name=lysosome` alone lists the matching pathways and modules with gene counts, as for domains; then ask with `pathway=`. Same options as domain_genes. KEGG assignments come from eggNOG; `score` above 1 means eggNOG offered several KOs for the gene.

## Sequences

- [Sequences for a list of IDs]({$api('sequences.php?organism={organism}&ids={id1},{id2}:1-60&type=protein')}): FASTA (or `format=json`) for up to 200 IDs; `type=protein`, `transcript`, `cds` or `genomic`. A range `ID:start-end` gives a piece (1-based; amino acids on a protein). A gene gives its transcripts' (or proteins') sequences. `type=genomic` with `upstream=500&downstream=500` adds the flanks (up to 100,000 bp each, read 5'->3' along the gene); `flanks_only=1` gives only the flanks. Not on assemblies of transcripts (the assembly call says `kind`). At most 20 MB per call.
- [Download a file]({$api('download.php?organism={organism}&assembly={assembly}&gene_set={gene_set}&filename=protein.aa.fa')}): a whole FASTA or GFF file. Take these links from the catalog rather than building them.
- [Genome region]({$api('region.php?organism={organism}&assembly={assembly}&seqname={scaffold}&start={start}&end={end}')}): a piece of the genome sequence; `strand=-` for the reverse complement. Gene records give the coordinates.

## Ontologies and GO

- [Search terms]({$api('ontology_search.php?o=GO&q={text}')}): find ontology terms by name or ID (GO, PLANA, ...; `o=GO,PLANA` for several).
- [Child terms]({$api('ontology_children.php?o=GO&id={GO:0005515}')}): the terms directly below one; `parts=1` adds part-of.
- [Genes with a GO term]({$api('go_term_genes.php?term={GO:0005515}&organism={organism}&assembly={assembly}&gene_set={gene_set}')}): in one gene set; `sub=1` includes more specific terms; `go_sources=` as for the gene record.

## Not in the API -- send the person to the page

- [Search]({$page('tools/search.php')}): the same keyword search, for a person to browse and filter.
- [BLAST]({$page('tools/blast.php')}): sequence similarity search (queued; runs in the browser).
- [Gene page]({$page('tools/parent.php?organism={organism}&uniquename={id}')}): the gene, for a person to read; the gene record links to it.

TXT;
