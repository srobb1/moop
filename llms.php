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
> many organisms, with a read-only JSON API for AI assistants and scripts. Without a login you see
> the public data only.

This guide is for an AI assistant working for a person. Use the JSON API below rather than the web
pages: a gene page is ~0.5 MB of HTML, its record here is a few KB.

Rules:

- Plain GET requests, no login, no token. JSON unless a link says otherwise (format=md, format=tsv).
- Start with the catalog: every other call takes `organism`, `assembly` and `gene_set` values from it.
  Never guess them.
- 404 means "not found or not public" -- the same answer either way, on purpose. Do not retry it.
- Lists are capped and say so (a total next to the items shown). Report the cap; do not page around it.
- Be gentle: requests are rate-limited per address, the downloads and gene-list calls most of all
  (about 20 a minute). On HTTP 429, wait and slow down. Do not crawl the web pages.
- Cite the site, and the data source the record names, when you use the data.
- These api/v1 links are stable: they will not be renamed or removed.

## Start here

- [Catalog]({$api('catalog.php')}): every organism, assembly and gene set you may open, with complete links to each page and every download (genome, protein, transcript and CDS FASTA, GFF).
- [Help for people]({$page('help.php?topic=ai-assistants')}): the same, written for the person using the assistant, with example questions.

## Genes

- [Gene record]({$api('gene.php?organism={organism}&id={id}')}): one gene as a record -- location, transcripts and proteins, name and how it was named, annotations (GO, domains, predicted subcellular location, ...), expression calls, orthologs and gene family in related species, other IDs. `id` may be a gene, transcript, CDS or protein ID; the record is always the gene. `format=md` gives Markdown to read or quote. `go_sources=panther,oma` limits GO terms to those sources (eggnog, panther, oma, interpro). HTTP 300 = the ID is in more than one gene set: pick one of the `choices`.
- [Find an ID]({$api('id_search.php?q={id}')}): which organism / assembly / gene set holds an ID (or IDs starting with it).
- [All annotations of a gene]({$api('annotations.php?organism={organism}&uniquename={id}')}): the full table as CSV (the gene record's lists are capped at 100 per type).
- [Translate IDs]({$api('translate.php?organism={organism}&ids={id1},{id2}')}): the same genes under their other IDs (other assemblies, older transcriptomes, array probes). Gene or transcript IDs; a gene ID is translated through its transcripts (`via` says which). Up to 500 IDs; `format=tsv`. With `organism` alone: which ID sources exist.
- [Orthologs for a gene list]({$api('orthologs.php?organism={organism}&ids={id1},{id2}')}): orthologs in related species, up to 200 IDs; `family=1` adds the rest of each gene family; `format=tsv`.

## Sequences

- [Download a file]({$api('download.php?organism={organism}&assembly={assembly}&gene_set={gene_set}&filename=protein.aa.fa')}): a whole FASTA or GFF file. Take these links from the catalog rather than building them.
- [Genome region]({$api('region.php?organism={organism}&assembly={assembly}&seqname={scaffold}&start={start}&end={end}')}): a piece of the genome sequence; `strand=-` for the reverse complement. Gene records give the coordinates.

## Ontologies and GO

- [Search terms]({$api('ontology_search.php?o=GO&q={text}')}): find ontology terms by name or ID (GO, PLANA, ...; `o=GO,PLANA` for several).
- [Child terms]({$api('ontology_children.php?o=GO&id={GO:0005515}')}): the terms directly below one; `parts=1` adds part-of.
- [Genes with a GO term]({$api('go_term_genes.php?term={GO:0005515}&organism={organism}&assembly={assembly}&gene_set={gene_set}')}): in one gene set; `sub=1` includes more specific terms; `go_sources=` as for the gene record.

## Not in the API -- send the person to the page

- [Search]({$page('tools/search.php')}): keyword search across organisms.
- [BLAST]({$page('tools/blast.php')}): sequence similarity search (queued; runs in the browser).
- [Gene page]({$page('tools/parent.php?organism={organism}&uniquename={id}')}): the gene, for a person to read; the gene record links to it.

TXT;
