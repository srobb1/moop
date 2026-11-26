# "Follow one gene through the site" (page gene-walkthrough), 2026-11-24

PUBLISHED 11-24 as its own content page `gene-walkthrough` (title "Follow one gene through the site"; the intro on the
live page says "Twelve steps, about fifteen minutes" and links back to the short tour). The short 6-step `tour` was
restored as it was (the home page calls it "a two-minute tour") with a "Want more?" link here.

(Original heading: DRAFT — "A quick tour of SIMRbase", extended)

Replaces the body of the content page `tour` (metadata/pages/tour.json, Manage Pages) once Sofia approves it.
Steps 1–2 and the closing "Questions?" are the existing text, unchanged. Steps 3–6 are rewritten around
noggin-1 (the old page used FoxA2 for step 4); steps 7–12 are new.

Notes for review (not part of the page):
- Example gene: noggin-1, NV2g007394000.1 (NV2 gene set on Nvec200, GCA_033964005.1; public). Checked 11-24
  as an inside user: search, gene page (Images, Expression, Orthologs, KEGG map04350), GO term, Expression and
  Primer Maker pre-filled, Downloads all load. STILL TO CHECK as a PUBLIC visitor (view-as) before it goes live
  — the Smed check found the "Orthologs in related species" card hidden for public.
- Screenshots taken 11-24 as a PUBLIC visitor (view-as, preview badge and banner removed): search-noggin, gene-noggin,
  go-term, expression, jbrowse, primers, rosetta. Retake expression (and gene) if new RNA-seq data lands (work list).
- Rosetta Stone step uses a PLANARIAN ID on purpose: for Nvec the crosswalk only links NV2 and RefSeq, which
  the gene page already shows; planarian papers are where old IDs really bite.
- No numbers that will change (TPM values, term counts) are written into the text.

---

A short walk through SIMRbase, using the starlet sea anemone *Nematostella vectensis* and one of its genes,
*noggin-1*, as the example. Everything shown here is open to anyone — no account needed. Each step links
straight to the page it describes, already filled in.

### 1. Find your organism
**Page:** the home page · [`/`](./)

Start typing in the search box on the [home page](./): a species name (*Nematostella*), a common name (sea anemone), a group (Cnidaria) or an assembly accession. Pick the organism from the list to open its page.

![The home page search box, with Nematostella typed in and the organism, its two assemblies and its two gene sets listed below](images/pages/tour/1-home.png)

**Know the gene ID already?** Type it into **Gene ID lookup** at the top right of every page and go straight to the gene.

### 2. The organism page
**Page:** the organism page (pick *Nematostella vectensis* on the home page) · [`/tools/organism.php?organism=Nematostella_vectensis`](tools/organism.php?organism=Nematostella_vectensis)

[Everything about one species](tools/organism.php?organism=Nematostella_vectensis): its assemblies and gene sets, where it sits in the tree of life, and the **Toolbox** of things you can do with its data.

![The Nematostella vectensis organism page: gene search at the top left, the Toolbox at the top right, and the organism's photo, taxonomy and groups below](images/pages/tour/2-organism.png)

An organism can have more than one **assembly** (a version of the genome sequence), and each assembly has one or more **gene sets** (a set of gene predictions on it). *Nematostella* has two: the RefSeq assembly jaNemVect1.1 with gene set RS_101, and Nvec200 with gene set NV2.

### 3. Search for a gene
**Page:** **Tools → Annotation Search** (or the search box on the organism page) · [`/tools/search.php?q="noggin-1"&organism=Nematostella_vectensis`](tools/search.php?q=%22noggin-1%22&organism=Nematostella_vectensis)

The search box on the organism page searches that organism's genes by ID, name, or any word in their annotations. Put a phrase in quotes to find those words together: [`"noggin-1"`](tools/search.php?q=%22noggin-1%22&organism=Nematostella_vectensis) fills in the box — click **Search** and it finds *noggin-1* in both gene sets.

![Search results for "noggin-1": 12 transcripts in Nematostella vectensis, listed with their assembly, ID and the description noggin-1, with Copy, CSV, Excel and FASTA buttons above](images/pages/tour/search-noggin.png)

Each row is one transcript: *noggin-1* has six isoforms in the RefSeq gene set and four in NV2, so it fills several rows. Click an ID to open its gene. Tick rows to download their sequences, or export the whole table as CSV, Excel or FASTA. To search several organisms at once, use the **Genes** tab on the home page.

### 4. The gene page
**Page:** the gene page (click a gene ID in the search results) · [`/tools/parent.php?organism=Nematostella_vectensis&uniquename=NV2g007394000.1`](tools/parent.php?organism=Nematostella_vectensis&uniquename=NV2g007394000.1)

[One gene](tools/parent.php?organism=Nematostella_vectensis&uniquename=NV2g007394000.1) and everything we know about it. Noggin is a secreted protein that binds BMPs and blocks their signal; this page shows what that looks like in the data:

- its **name** and the evidence it was named from ([how genes are named](help.php?topic=gene-names));
- where it sits in the genome, and its structure with every isoform;
- its **protein domains**, **GO terms** and **KEGG pathway** (TGF-beta signaling — where BMPs belong);
- its **orthologs in related species** and its gene family;
- its **expression** in RNA-seq experiments, and an **in situ** picture of where it is expressed in the animal.

![The gene page for NV2g007394000.1, noggin-1: the naming evidence (best human hit NOG, the Noggin domain, a signal peptide), its location chr13:5,579,481-5,593,290, the list of sections on the left and the Toolbox on the right](images/pages/tour/gene-noggin.png)

The list on the left jumps to each section. The **Toolbox** on the right opens the gene in the genome browser, runs BLAST with its sequence, or designs primers for it — the next steps below.

### 5. Which pathway is it in? Follow KEGG
**Page:** the gene page's **Pathways** section (click **Pathways** in its Jump to list) · [`/tools/parent.php?organism=Nematostella_vectensis&uniquename=NV2g007394000.1`](tools/parent.php?organism=Nematostella_vectensis&uniquename=NV2g007394000.1)

On the gene page, click **Pathways** in the Jump to list on the left, then open **Environmental Information Processing**: *noggin-1* is in the **TGF-beta signaling pathway**, where BMPs belong. Two links sit beside it:

- **View on KEGG** opens KEGG's own map of the pathway, on KEGG's website, with its boxes coloured: orange for *noggin-1*, green for the pathway's other genes in this gene set, blue-grey for genes found only in the species' other gene set, and white for genes not found. [Open noggin-1's coloured map](api/kegg_map.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&pathway=map04350&gene=NV2t007394002.1)
- **Genes in this gene set** gives you the pathway's gene list: it opens a search that lists every gene in the pathway — BMPs, their receptors, the SMADs, other BMP blockers such as chordin, and *noggin-1* itself. Tick genes, or export the whole list as CSV, Excel or FASTA. **Genes in this organism** does the same across all of *Nematostella*'s gene sets. [Open the list](tools/search.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&q=map04350&source=KEGG+Pathway+%28eggNOG%29)

![The gene page's Pathways section for noggin-1: a colour legend (this gene, in this gene set, only in another gene set of this species, not found), then TGF-beta signaling pathway with View on KEGG, Genes in this gene set and Genes in this organism](images/pages/tour/pathways.png)

![The gene list for the TGF-beta signaling pathway (map04350) in gene set NV2: transcripts such as SMAD4-like, noggin and chordin, each matched in KEGG Pathway, with Copy, CSV, Excel and FASTA buttons](images/pages/tour/kegg-list.png)

*Pathway data and maps: KEGG, Kanehisa Laboratories — Kanehisa M, et al. (2025) KEGG: biological systems database as a model of the real world. Nucleic Acids Research 53(D1):D672–D677. [doi:10.1093/nar/gkae909](https://doi.org/10.1093/nar/gkae909). Genes were assigned to KEGG Orthology (KO) groups with eggNOG-mapper — Cantalapiedra CP, et al. (2021) eggNOG-mapper v2. Molecular Biology and Evolution 38(12):5825–5829. [doi:10.1093/molbev/msab293](https://doi.org/10.1093/molbev/msab293).*

Every pathway of a gene set, with its **Map** and **Genes** links, is in the **KEGG Pathways** table on the gene set's own page: [NV2's pathways](tools/gene_set.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2).

Gene Ontology (GO) terms work in a similar way: in **Tools → Anatomy & Gene Function**, choose **GO (Gene Ontology)**, search for a term, and its page counts the genes that carry it in every organism here — for example [*negative regulation of BMP signaling pathway*](tools/ontology_term.php?o=GO&id=GO%3A0030514).

### 6. When and where is it expressed?
**Page:** **Tools → RNA-seq Expression Explorer** · [`/tools/expression.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&genes=NV2g007394000.1`](tools/expression.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&genes=NV2g007394000.1)

The [RNA-seq Expression Explorer](tools/expression.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&genes=NV2g007394000.1) opens with *noggin-1* already filled in and all three *Nematostella* experiments ticked. Click **Show heatmap** to see its level in each condition, from zygote to polyp. Add more gene IDs to compare them side by side.

![The RNA-seq Expression Explorer: a heatmap row for NV2g007394000.1 across three experiments — a development time course, heavy-metal exposure, and gastrula, planula and adult](images/pages/tour/expression.png)

### 7. See it in the genome
**Page:** **Tools → Genome Browser** (or **View in Genome Browser** in the gene page's Toolbox) · [`/jbrowse2.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&loc=chr13:5579481-5593290`](jbrowse2.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&loc=chr13:5579481-5593290)

**Genome browser** in the gene page's Toolbox opens [the gene in JBrowse](jbrowse2.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&loc=chr13:5579481-5593290): its exons and isoforms on the chromosome, with its neighbours. Drag to move, scroll to zoom, and add tracks from the track list.

![The genome browser at chr13:5,579,475-5,593,284 on Nvec200, showing the noggin-1 gene model in the Genes (NV2) track, with the list of available tracks on the right](images/pages/tour/jbrowse.png)

### 8. BLAST
**Page:** **Tools → BLAST Search** · [`/tools/blast.php?organism=Nematostella_vectensis`](tools/blast.php?organism=Nematostella_vectensis)

[Find sequences like yours](tools/blast.php?organism=Nematostella_vectensis): paste a DNA or protein sequence, choose a BLAST program, then choose which organisms and which database (genome, transcripts or proteins) to search. **Sample Protein** and **Sample Nucleotide** fill in an example so you can try it. From a gene page, the Toolbox's **BLAST** fills in that gene's sequence for you.

![The BLAST page: step 1 paste a sequence, step 2 choose a BLAST program, step 3 choose the organism and database](images/pages/tour/5-blast.png)

### 9. Design primers
**Page:** **Tools → Primer Maker**, then **Tools → Primer BLAST** · [`/tools/primer_maker.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&feature=NV2g007394000.1`](tools/primer_maker.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&feature=NV2g007394000.1)

[Primer Maker](tools/primer_maker.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&feature=NV2g007394000.1) opens with *noggin-1* chosen: pick one of its four transcripts, click **Load this sequence**, choose what the primers are for (PCR, qPCR, RT-PCR or sequencing), then **Design primers** for a table of primer pairs. Then **Primer BLAST** checks a pair against the whole genome, to see whether it would bind anywhere else.

![Primer Maker results: five primer pairs for transcript NV2t007394002.1, with each primer's sequence, length, melting temperature, GC content and the product size](images/pages/tour/primers.png)

### 10. Got an ID from a paper?
**Page:** **Tools → Rosetta Stone ID Translator** · [`/tools/crosswalk.php?organism=Schmidtea_mediterranea&ids=EU296635`](tools/crosswalk.php?organism=Schmidtea_mediterranea&ids=EU296635)

Papers use IDs from many older gene sets. The **Rosetta Stone** (Tools menu) tells you which current gene an ID belongs to. Planarians are where this matters most: [paste `EU296635`](tools/crosswalk.php?organism=Schmidtea_mediterranea&ids=EU296635), a GenBank ID, click **Translate**, and it finds the current gene. The gene page's **Same gene in this species** section lists every other ID a gene is known by.

![The Rosetta Stone: EU296635 translated to the current genes h1SMcG0012012 and h2SMcG0012220 (one on each haplotype of the genome), with the older ID SMED30022468](images/pages/tour/rosetta.png)

### 11. Download the data
**Page:** **Tools → Downloads**, and **Tools → MOOPmart: Data Exporter** · [`/tools/downloads.php?organism=Nematostella_vectensis`](tools/downloads.php?organism=Nematostella_vectensis)

[Downloads](tools/downloads.php?organism=Nematostella_vectensis) has every assembly's genome and every gene set's proteins, coding sequences (CDS), transcripts and gene models (GFF). Open an organism, tick what you want, and download several files at once.

![The Downloads page for Nematostella vectensis: the jaNemVect1.1 assembly opened to show genome.fa, and gene set RS_101 with cds.nt.fa, protein.aa.fa, transcript.nt.fa and genes.gff with their sizes](images/pages/tour/6-downloads.png)

For many genes at once — a list of IDs with their annotations, orthologs or sequences — use the **Data Exporter** in the Tools menu.

### 12. Ask an AI assistant
**Page:** **Use this site with an AI assistant**, at the bottom of every page · [`/help.php?topic=ai-assistants`](help.php?topic=ai-assistants)

An AI assistant can look things up on this site for you: "which *Nematostella* genes are in the TGF-beta pathway?", "design primers for noggin-1". [How to set one up](help.php?topic=ai-assistants).

### Questions?

Use **Send us a message** at the bottom of any page — for a question, a data request, or something that looks wrong. A person reads every message. For more, see [What do you want to do?](help.php?topic=finding-the-right-page) in Help.
