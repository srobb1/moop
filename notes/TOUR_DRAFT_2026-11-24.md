# DRAFT — "A quick tour of SIMRbase", extended (2026-11-24)

Replaces the body of the content page `tour` (metadata/pages/tour.json, Manage Pages) once Sofia approves it.
Steps 1–2 and the closing "Questions?" are the existing text, unchanged. Steps 3–6 are rewritten around
noggin-1 (the old page used FoxA2 for step 4); steps 7–12 are new.

Notes for review (not part of the page):
- Example gene: noggin-1, NV2g007394000.1 (NV2 gene set on Nvec200, GCA_033964005.1; public). Checked 11-24
  as an inside user: search, gene page (Images, Expression, Orthologs, KEGG map04350), GO term, Expression and
  Primer Maker pre-filled, Downloads all load. STILL TO CHECK as a PUBLIC visitor (view-as) before it goes live
  — the Smed check found the "Orthologs in related species" card hidden for public.
- New screenshots needed: 3 (search), 4 (gene page), and 7–11. Take them AFTER next week's RNA-seq work (work
  list Inbox item), so the expression card is current. Until then the new steps go live without pictures, or wait.
- Rosetta Stone step uses a PLANARIAN ID on purpose: for Nvec the crosswalk only links NV2 and RefSeq, which
  the gene page already shows; planarian papers are where old IDs really bite.
- No numbers that will change (TPM values, term counts) are written into the text.

---

A short walk through SIMRbase, using the starlet sea anemone *Nematostella vectensis* and one of its genes,
*noggin-1*, as the example. Everything shown here is open to anyone — no account needed. Each step links
straight to the page it describes, already filled in.

### 1. Find your organism

Start typing in the search box on the [home page](./): a species name (*Nematostella*), a common name (sea anemone), a group (Cnidaria) or an assembly accession. Pick the organism from the list to open its page.

![The home page search box, with Nematostella typed in and the organism, its two assemblies and its two gene sets listed below](images/pages/tour/1-home.png)

**Know the gene ID already?** Type it into **Gene ID lookup** at the top right of every page and go straight to the gene.

### 2. The organism page

[Everything about one species](tools/organism.php?organism=Nematostella_vectensis): its assemblies and gene sets, where it sits in the tree of life, and the **Toolbox** of things you can do with its data.

![The Nematostella vectensis organism page: gene search at the top left, the Toolbox at the top right, and the organism's photo, taxonomy and groups below](images/pages/tour/2-organism.png)

An organism can have more than one **assembly** (a version of the genome sequence), and each assembly has one or more **gene sets** (a set of gene predictions on it). *Nematostella* has two: the RefSeq assembly jaNemVect1.1 with gene set RS_101, and Nvec200 with gene set NV2.

### 3. Search for a gene

The search box on the organism page searches that organism's genes by ID, name, or any word in their annotations. Put a phrase in quotes to find those words together: [`"noggin-1"`](tools/search.php?q=%22noggin-1%22&organism=Nematostella_vectensis) fills in the box — click **Search** and it finds *noggin-1* in both gene sets.

`[screenshot to take: 3-search.png]`

Each row is one transcript — a gene with four isoforms appears four times. Click an ID to open its gene. Tick rows to download their sequences, or export the whole table as CSV, Excel or FASTA. To search several organisms at once, use the **Genes** tab on the home page.

### 4. The gene page

[One gene](tools/parent.php?organism=Nematostella_vectensis&uniquename=NV2g007394000.1) and everything we know about it. Noggin is a secreted protein that binds BMPs and blocks their signal; this page shows what that looks like in the data:

- its **name** and the evidence it was named from ([how genes are named](help.php?topic=gene-names));
- where it sits in the genome, and its structure with every isoform;
- its **protein domains**, **GO terms** and **KEGG pathway** (TGF-beta signaling — where BMPs belong);
- its **orthologs in related species** and its gene family;
- its **expression** in RNA-seq experiments, and an **in situ** picture of where it is expressed in the animal.

`[screenshot to take: 4-gene.png]`

The list on the left jumps to each section. The **Toolbox** on the right opens the gene in the genome browser, runs BLAST with its sequence, or designs primers for it — the next steps below.

### 5. What does the gene do? Follow a GO term

Each GO term on the gene page links to that term's page. [*Negative regulation of BMP signaling pathway*](tools/ontology_term.php?o=GO&id=GO%3A0030514) shows how many genes in each gene set carry it; click a number to list them — *noggin-1* is among *Nematostella*'s. It is the quickest way from one gene to others that may do the same job. **Anatomy & Gene Function** in the Tools menu browses these terms from the top.

`[screenshot to take: 5-go-term.png]`

### 6. When and where is it expressed?

The [RNA-seq Expression Explorer](tools/expression.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&genes=NV2g007394000.1) opens with *noggin-1* already in it: its expression across each experiment's samples, as a heatmap. Add more gene IDs to compare them side by side.

`[screenshot to take: 6-expression.png]`

### 7. See it in the genome

**Genome browser** in the gene page's Toolbox opens [the gene in JBrowse](jbrowse2.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1): its exons and isoforms on the chromosome, with its neighbours. Drag to move, scroll to zoom, and add tracks from the track list.

`[screenshot to take: 7-jbrowse.png]`

### 8. BLAST

[Find sequences like yours](tools/blast.php?organism=Nematostella_vectensis): paste a DNA or protein sequence, choose a BLAST program, then choose which organisms and which database (genome, transcripts or proteins) to search. **Sample Protein** and **Sample Nucleotide** fill in an example so you can try it. From a gene page, the Toolbox's **BLAST** fills in that gene's sequence for you.

![The BLAST page: step 1 paste a sequence, step 2 choose a BLAST program, step 3 choose the organism and database](images/pages/tour/5-blast.png)

### 9. Design primers

[Primer Maker](tools/primer_maker.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2&feature=NV2g007394000.1) opens with *noggin-1*'s transcript filled in: choose what the primers are for (RT-PCR, qPCR, cloning) and get primer pairs. Then **Primer BLAST** checks a pair against the whole genome, to see whether it would bind anywhere else.

`[screenshot to take: 9-primers.png]`

### 10. Got an ID from a paper?

Papers use IDs from many older gene sets. The **Rosetta Stone** (Tools menu) tells you which current gene an ID belongs to. Planarians are where this matters most: [paste `EU296635`](tools/crosswalk.php?organism=Schmidtea_mediterranea&ids=EU296635), a GenBank ID from a 2008 paper, and it finds the current gene. The gene page's **Same gene in this species** section lists every other ID a gene is known by.

`[screenshot to take: 10-rosetta.png]`

### 11. Download the data

[Downloads](tools/downloads.php?organism=Nematostella_vectensis) has every assembly's genome and every gene set's proteins, coding sequences (CDS), transcripts and gene models (GFF). Open an organism, tick what you want, and download several files at once.

![The Downloads page for Nematostella vectensis: the jaNemVect1.1 assembly opened to show genome.fa, and gene set RS_101 with cds.nt.fa, protein.aa.fa, transcript.nt.fa and genes.gff with their sizes](images/pages/tour/6-downloads.png)

For many genes at once — a list of IDs with their annotations, orthologs or sequences — use the **Data Exporter** in the Tools menu.

### 12. Ask an AI assistant

An AI assistant can look things up on this site for you: "which *Nematostella* genes are in the TGF-beta pathway?", "design primers for noggin-1". [How to set one up](help.php?topic=ai-assistants).

### Questions?

Use **Send us a message** at the bottom of any page — for a question, a data request, or something that looks wrong. A person reads every message. For more, see [What do you want to do?](help.php?topic=finding-the-right-page) in Help.
