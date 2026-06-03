<?php
/**
 * ORGANISM DATA ORGANIZATION - Technical Help Documentation
 *
 * Technical guide covering database schema, file organization, and data structure.
 *
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
?>

<div class="container mt-5">
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Back to Help
    </a>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-database me-2"></i>Organism Data Organization (Technical)</span>
    </div>
    <div class="card-body py-2">
      <p class="text-muted small mb-0">How MOOP organizes and stores organism data — database schema, file layout, and hierarchical structure.</p>
    </div>
  </div>

  <!-- Quick Navigation -->
  <div class="alert alert-light border mb-4">
    <strong>On this page:</strong>
    <ul class="mb-0 mt-1">
      <li><a href="#core-concepts">Core Concepts: Organisms, Assemblies, Gene Sets, Features, Annotations</a></li>
      <li><a href="#file-organization">File Organization</a></li>
      <li><a href="#json-metadata">JSON Metadata Files</a></li>
      <li><a href="#database-schema">Database Schema</a></li>
      <li><a href="#hierarchical-structure">Feature Hierarchy</a></li>
      <li><a href="#annotation-system">Annotation System</a></li>
      <li><a href="#example-queries">Example SQLite Queries</a></li>
    </ul>
  </div>

  <!-- Section 1: Core Concepts -->
  <section id="core-concepts" class="mt-4">
    <h4 class="fw-semibold mb-3"><i class="fa fa-layer-group me-2"></i>Core Concepts</h4>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header" style="background-color:#0f766e; color:white;">
            <strong>Organism</strong>
          </div>
          <div class="card-body">
            <p class="mb-1"><strong>Definition:</strong> A biological species or strain</p>
            <p class="mb-2"><strong>Example:</strong> <em>Anoura caudifer</em></p>
            <ul class="mb-0 small">
              <li>One SQLite database per organism</li>
              <li>Stores all features and annotations across all assemblies</li>
              <li>Path: <code>organisms/Organism_Name/organism.sqlite</code></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header" style="background-color:#d97706; color:white;">
            <strong>Assembly</strong>
          </div>
          <div class="card-body">
            <p class="mb-1"><strong>Definition:</strong> A specific genome sequence build</p>
            <p class="mb-2"><strong>Example:</strong> GCA_004027475.1</p>
            <ul class="mb-0 small">
              <li>Contains the genome FASTA and its BLAST index</li>
              <li>Multiple assemblies per organism allowed</li>
              <li>Path: <code>organisms/Organism_Name/Assembly_ID/</code></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header" style="background-color:#e11d48; color:white;">
            <strong>Gene Set</strong>
          </div>
          <div class="card-body">
            <p class="mb-1"><strong>Definition:</strong> A named set of gene annotations for one assembly</p>
            <p class="mb-2"><strong>Example:</strong> SIMR_2025-01-24</p>
            <ul class="mb-0 small">
              <li>Contains transcript, CDS, and protein FASTA files</li>
              <li>Contains the GFF annotation file</li>
              <li>Multiple gene sets per assembly allowed (e.g. different annotation versions)</li>
              <li>Path: <code>organisms/Organism_Name/Assembly_ID/Gene_Set_Name/</code></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header bg-secondary text-white">
            <strong>Feature</strong>
          </div>
          <div class="card-body">
            <p class="mb-1"><strong>Definition:</strong> A genomic element (gene, mRNA, exon, protein)</p>
            <p class="mb-2"><strong>Example:</strong> <code>g24397</code> (gene), <code>g24397.t1</code> (mRNA)</p>
            <ul class="mb-0 small">
              <li>Stored in the organism SQLite database</li>
              <li>Has a unique identifier (<code>feature_uniquename</code>)</li>
              <li>Belongs to a gene set</li>
              <li>Can have parent/child relationships (gene → mRNA → exon)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header" style="background-color:#6366f1; color:white;">
            <strong>Annotation</strong>
          </div>
          <div class="card-body">
            <p class="mb-1"><strong>Definition:</strong> A functional hit from computational analysis</p>
            <p class="mb-2"><strong>Examples:</strong> BLAST hit, InterPro domain, GO term, ortholog</p>
            <ul class="mb-0 small">
              <li>Links a feature to an external database entry</li>
              <li>Has an accession, description, and score</li>
              <li>References an annotation source (NCBI, InterPro, etc.)</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Section 2: File Organization -->
  <section id="file-organization" class="mt-5">
    <h4 class="fw-semibold mb-3"><i class="fa fa-folder-tree me-2"></i>File Organization</h4>

    <div class="alert alert-light border">
      <strong>Root directory:</strong> <code>organisms/</code> (symlinked from <code>/data/moop/organisms/</code>)
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <pre class="bg-light p-3 rounded border mb-0"><code>organisms/
├── Organism_Name/
│   ├── organism.sqlite              ← SQLite database (features, annotations for ALL assemblies)
│   ├── organism.json                ← Display metadata (genus, species, common name, image)
│   ├── annotation_sources_cache.json  ← Cached annotation counts (auto-regenerated)
│   │
│   ├── Assembly_ID/                 ← One directory per assembly
│   │   ├── genome.fa                ← Reference genome FASTA
│   │   ├── genome.fa.fai            ← FASTA index (samtools)
│   │   ├── genome.fa.n*             ← BLAST nucleotide index files
│   │   ├── genome.json              ← Assembly metadata (source, date, notes)
│   │   │
│   │   └── Gene_Set_Name/           ← One directory per gene set
│   │       ├── transcript.nt.fa     ← Spliced mRNA sequences
│   │       ├── transcript.nt.fa.n*  ← BLAST index
│   │       ├── cds.nt.fa            ← Coding sequences
│   │       ├── cds.nt.fa.n*         ← BLAST index
│   │       ├── protein.aa.fa        ← Protein sequences
│   │       ├── protein.aa.fa.p*     ← BLAST protein index files
│   │       ├── genomic.gff          ← GFF3 annotation file
│   │       └── geneset.json         ← Gene set metadata (source, date, notes)
│   │
│   └── Another_Assembly_ID/
│       └── [same structure]
│
└── Another_Organism/
    └── [same structure]</code></pre>
      </div>
    </div>

    <h5 class="fw-semibold mb-3">File Types</h5>
    <div class="table-responsive">
      <table class="table table-sm table-bordered">
        <thead class="table-light">
          <tr><th>File</th><th>Location</th><th>Purpose</th></tr>
        </thead>
        <tbody>
          <tr><td><code>organism.sqlite</code></td><td>Organism level</td><td>All features and annotations for this organism</td></tr>
          <tr><td><code>organism.json</code></td><td>Organism level</td><td>Display metadata: genus, species, common name, image path</td></tr>
          <tr><td><code>genome.fa</code></td><td>Assembly level</td><td>Complete reference genome (chromosomes/scaffolds)</td></tr>
          <tr><td><code>genome.json</code></td><td>Assembly level</td><td>Assembly metadata: source, date added, notes</td></tr>
          <tr><td><code>transcript.nt.fa</code></td><td>Gene set level</td><td>Spliced mRNA sequences (exons only)</td></tr>
          <tr><td><code>cds.nt.fa</code></td><td>Gene set level</td><td>Coding sequences (start codon to stop codon)</td></tr>
          <tr><td><code>protein.aa.fa</code></td><td>Gene set level</td><td>Translated protein sequences</td></tr>
          <tr><td><code>genomic.gff</code></td><td>Gene set level</td><td>GFF3 annotation file used by JBrowse2</td></tr>
          <tr><td><code>geneset.json</code></td><td>Gene set level</td><td>Gene set metadata: source, date added, notes</td></tr>
          <tr><td><code>*.n* / *.p*</code></td><td>Same dir as FASTA</td><td>BLAST database index files (auto-generated)</td></tr>
        </tbody>
      </table>
    </div>

    <div class="alert alert-info">
      <i class="fa fa-info-circle me-1"></i>
      <strong>Configuration note:</strong> FASTA file naming patterns are configured in <code>config/config_editable.json</code> under <code>sequence_types</code>, so they can vary between sites.
    </div>
  </section>

  <!-- Section: JSON Metadata Files -->
  <section id="json-metadata" class="mt-5">
    <h4 class="fw-semibold mb-3"><i class="fa fa-file-code me-2"></i>JSON Metadata Files</h4>
    <p class="text-muted mb-4">Three small JSON files sit alongside the data files at each level of the hierarchy. They store display and provenance metadata that isn't in the SQLite database.</p>

    <!-- organism.json -->
    <div class="card mb-4">
      <div class="card-header text-white" style="background-color:#0f766e;">
        <code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">organism.json</code>
        <span class="ms-2 small opacity-75">— organisms/Organism_Name/organism.json</span>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Controls how the organism is displayed across the site. Also defines which feature types are treated as parent features (shown on feature detail pages) vs child features.</p>
        <div class="row g-3">
          <div class="col-lg-6">
            <pre class="bg-light p-3 rounded border mb-0" style="font-size:0.8rem;"><code>{
  "genus": "Anoura",
  "species": "caudifer",
  "common_name": "Tailed Tailless Bat",
  "taxon_id": "27642",
  "subclassification": {
    "type": null,
    "value": null
  },
  "feature_types": {
    "parents": ["gene"],
    "children": ["mRNA", "transcript"]
  }
}</code></pre>
          </div>
          <div class="col-lg-6">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light"><tr><th>Field</th><th>Notes</th></tr></thead>
              <tbody>
                <tr><td><code>genus</code></td><td>Shown in italics throughout the site</td></tr>
                <tr><td><code>species</code></td><td>Combined with genus for display</td></tr>
                <tr><td><code>common_name</code></td><td>Shown in parentheses alongside the scientific name</td></tr>
                <tr><td><code>taxon_id</code></td><td>NCBI Taxonomy ID — used to fetch the taxonomy tree node and Wikipedia image</td></tr>
                <tr><td><code>subclassification</code></td><td>Optional strain or subspecies info (<code>type</code>: label, <code>value</code>: the value)</td></tr>
                <tr><td><code>feature_types.parents</code></td><td>Feature types shown as top-level entries on feature detail pages (typically <code>"gene"</code>)</td></tr>
                <tr><td><code>feature_types.children</code></td><td>Feature types shown as children under a parent (typically <code>"mRNA"</code>, <code>"transcript"</code>)</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- genome.json -->
    <div class="card mb-4">
      <div class="card-header text-white" style="background-color:#d97706;">
        <code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">genome.json</code>
        <span class="ms-2 small opacity-75">— organisms/Organism_Name/Assembly_ID/genome.json</span>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Provenance metadata for the assembly. Shown on the assembly detail page under the info box.</p>
        <div class="row g-3">
          <div class="col-lg-6">
            <pre class="bg-light p-3 rounded border mb-0" style="font-size:0.8rem;"><code>{
  "accession": "GCA_004027475.1",
  "name": "",
  "source": "GenBank",
  "date_added": "2026-05-28"
}</code></pre>
          </div>
          <div class="col-lg-6">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light"><tr><th>Field</th><th>Notes</th></tr></thead>
              <tbody>
                <tr><td><code>accession</code></td><td>Assembly accession — should match the directory name</td></tr>
                <tr><td><code>name</code></td><td>Optional human-readable name shown alongside the accession</td></tr>
                <tr><td><code>source</code></td><td>Where the genome came from (e.g. "GenBank", "RefSeq", "in-house")</td></tr>
                <tr><td><code>date_added</code></td><td>ISO date when this assembly was loaded into MOOP (YYYY-MM-DD)</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- geneset.json -->
    <div class="card mb-4">
      <div class="card-header text-white" style="background-color:#e11d48;">
        <code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">geneset.json</code>
        <span class="ms-2 small opacity-75">— organisms/Organism_Name/Assembly_ID/Gene_Set_Name/geneset.json</span>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Provenance metadata for the gene set. Shown on the gene set detail page under the info box.</p>
        <div class="row g-3">
          <div class="col-lg-6">
            <pre class="bg-light p-3 rounded border mb-0" style="font-size:0.8rem;"><code>{
  "accession": "SIMR_2025-01-24",
  "name": "",
  "source": "other",
  "date_added": "2026-05-28"
}</code></pre>
          </div>
          <div class="col-lg-6">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light"><tr><th>Field</th><th>Notes</th></tr></thead>
              <tbody>
                <tr><td><code>accession</code></td><td>Gene set name — should match the directory name</td></tr>
                <tr><td><code>name</code></td><td>Optional human-readable label</td></tr>
                <tr><td><code>source</code></td><td>Annotation pipeline or source (e.g. "NCBI", "Maker", "other")</td></tr>
                <tr><td><code>date_added</code></td><td>ISO date when this gene set was loaded (YYYY-MM-DD)</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="alert alert-info mb-0">
      <i class="fa fa-info-circle me-1"></i>
      <strong>Site-level metadata</strong> (organism groups, taxonomy tree, annotation config) is documented in the
      <a href="help.php?topic=organism-setup-and-searches">Setup &amp; Searches</a> help page under "Metadata Configuration Files".
    </div>
  </section>

  <!-- Section 3: Database Schema -->
  <section id="database-schema" class="mt-5">
    <h4 class="fw-semibold mb-3"><i class="fa fa-sitemap me-2"></i>Database Schema</h4>
    <p class="text-muted">Each organism has one SQLite database. The core tables are:</p>

    <div class="card mb-3">
      <div class="card-header" style="background-color:#0f766e; color:white;"><code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">organism</code> — species metadata</div>
      <div class="card-body">
        <table class="table table-sm mb-1">
          <thead class="table-light"><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td><code>organism_id</code></td><td>INTEGER PK</td><td>Auto-increment</td></tr>
            <tr><td><code>genus</code></td><td>TEXT</td><td>e.g. "Anoura"</td></tr>
            <tr><td><code>species</code></td><td>TEXT</td><td>e.g. "caudifer"</td></tr>
            <tr><td><code>common_name</code></td><td>TEXT</td><td>Display name</td></tr>
            <tr><td><code>taxon_id</code></td><td>INTEGER</td><td>NCBI Taxonomy ID (optional)</td></tr>
          </tbody>
        </table>
        <small class="text-muted">Typically 1 row (one organism per SQLite file)</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header" style="background-color:#d97706; color:white;"><code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">genome</code> — assembly metadata</div>
      <div class="card-body">
        <table class="table table-sm mb-1">
          <thead class="table-light"><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td><code>genome_id</code></td><td>INTEGER PK</td><td>Auto-increment</td></tr>
            <tr><td><code>organism_id</code></td><td>INTEGER FK</td><td>→ organism.organism_id</td></tr>
            <tr><td><code>genome_name</code></td><td>TEXT</td><td>Assembly name</td></tr>
            <tr><td><code>genome_accession</code></td><td>TEXT</td><td>e.g. "GCA_004027475.1"</td></tr>
            <tr><td><code>genome_description</code></td><td>TEXT</td><td>Optional description</td></tr>
          </tbody>
        </table>
        <small class="text-muted">One row per assembly</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header" style="background-color:#e11d48; color:white;"><code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">gene_set</code> — gene set metadata</div>
      <div class="card-body">
        <table class="table table-sm mb-1">
          <thead class="table-light"><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td><code>gene_set_id</code></td><td>INTEGER PK</td><td>Auto-increment</td></tr>
            <tr><td><code>genome_id</code></td><td>INTEGER FK</td><td>→ genome.genome_id</td></tr>
            <tr><td><code>gene_set_name</code></td><td>TEXT</td><td>e.g. "SIMR_2025-01-24"</td></tr>
            <tr><td><code>gene_set_description</code></td><td>TEXT</td><td>Optional description</td></tr>
          </tbody>
        </table>
        <small class="text-muted">One row per gene set; UNIQUE(genome_id, gene_set_name)</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header bg-secondary text-white"><code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">feature</code> — genomic elements (genes, mRNAs, exons, proteins)</div>
      <div class="card-body">
        <table class="table table-sm mb-1">
          <thead class="table-light"><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td><code>feature_id</code></td><td>INTEGER PK</td><td>Auto-increment</td></tr>
            <tr><td><code>feature_uniquename</code></td><td>TEXT UNIQUE</td><td>The ID used for searches and retrieval</td></tr>
            <tr><td><code>feature_type</code></td><td>TEXT</td><td>"gene", "mRNA", "exon", "protein", etc.</td></tr>
            <tr><td><code>feature_name</code></td><td>TEXT</td><td>Display name (e.g. "Insulin")</td></tr>
            <tr><td><code>feature_description</code></td><td>TEXT</td><td>Searchable text description</td></tr>
            <tr><td><code>organism_id</code></td><td>INTEGER FK</td><td>→ organism.organism_id (denormalized for speed)</td></tr>
            <tr><td><code>gene_set_id</code></td><td>INTEGER FK</td><td>→ gene_set.gene_set_id</td></tr>
            <tr><td><code>parent_feature_id</code></td><td>INTEGER FK</td><td>Self-reference: → feature.feature_id (gene → mRNA → exon)</td></tr>
          </tbody>
        </table>
        <small class="text-muted">Thousands to millions of rows. Note: genome is reached through gene_set, not directly.</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header" style="background-color:#ff9800; color:white;"><code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">annotation_source</code> — external databases</div>
      <div class="card-body">
        <table class="table table-sm mb-1">
          <thead class="table-light"><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td><code>annotation_source_id</code></td><td>INTEGER PK</td><td>Auto-increment</td></tr>
            <tr><td><code>annotation_source_name</code></td><td>TEXT</td><td>e.g. "InterProScan (Pfam)"</td></tr>
            <tr><td><code>annotation_source_version</code></td><td>TEXT</td><td>Version or date run</td></tr>
            <tr><td><code>annotation_accession_url</code></td><td>TEXT</td><td>URL template with <code>{ID}</code> placeholder</td></tr>
            <tr><td><code>annotation_type</code></td><td>TEXT</td><td>"Domains", "Gene Ontology", "Homologs", etc.</td></tr>
          </tbody>
        </table>
        <small class="text-muted">Typically 5–20 rows per database</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header bg-danger text-white"><code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">annotation</code> — annotation records</div>
      <div class="card-body">
        <table class="table table-sm mb-1">
          <thead class="table-light"><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td><code>annotation_id</code></td><td>INTEGER PK</td><td>Auto-increment</td></tr>
            <tr><td><code>annotation_accession</code></td><td>TEXT</td><td>External ID (e.g. "IPR003236", "GO:0005179")</td></tr>
            <tr><td><code>annotation_description</code></td><td>TEXT</td><td>Searchable description from the source database</td></tr>
            <tr><td><code>annotation_source_id</code></td><td>INTEGER FK</td><td>→ annotation_source.annotation_source_id</td></tr>
          </tbody>
        </table>
        <small class="text-muted">Thousands to hundreds of thousands of rows</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header" style="background-color:#6366f1; color:white;"><code style="background:rgba(0,0,0,0.2);color:white;padding:2px 6px;border-radius:3px;">feature_annotation</code> — links features to annotations</div>
      <div class="card-body">
        <table class="table table-sm mb-1">
          <thead class="table-light"><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td><code>feature_annotation_id</code></td><td>INTEGER PK</td><td>Auto-increment</td></tr>
            <tr><td><code>feature_id</code></td><td>INTEGER FK</td><td>→ feature.feature_id</td></tr>
            <tr><td><code>annotation_id</code></td><td>INTEGER FK</td><td>→ annotation.annotation_id</td></tr>
            <tr><td><code>score</code></td><td>REAL</td><td>e-value, bit score, or confidence value</td></tr>
            <tr><td><code>date</code></td><td>TEXT</td><td>When this annotation was loaded</td></tr>
          </tbody>
        </table>
        <small class="text-muted">Hundreds of thousands to millions of rows (many annotations per feature)</small>
      </div>
    </div>

    <!-- ER Diagram -->
    <h5 class="fw-semibold mt-4 mb-3">Entity Relationship Diagram</h5>
    <div class="card">
      <div class="card-body">
        <svg viewBox="0 0 1050 580" class="w-100" style="max-height:560px; font-family:monospace;">
          <defs>
            <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
              <path d="M0,0 L0,6 L8,3 z" fill="#555"/>
            </marker>
          </defs>

          <!-- organism -->
          <rect x="20" y="20" width="160" height="110" rx="4" fill="#d1fae5" stroke="#0f766e" stroke-width="2"/>
          <rect x="20" y="20" width="160" height="28" rx="4" fill="#0f766e"/>
          <text x="100" y="39" fill="white" text-anchor="middle" font-size="12" font-weight="bold">organism</text>
          <text x="30" y="64" font-size="11">organism_id (PK)</text>
          <text x="30" y="80" font-size="11">genus</text>
          <text x="30" y="96" font-size="11">species</text>
          <text x="30" y="112" font-size="11">common_name</text>

          <!-- genome -->
          <rect x="240" y="20" width="175" height="125" rx="4" fill="#fef3c7" stroke="#d97706" stroke-width="2"/>
          <rect x="240" y="20" width="175" height="28" rx="4" fill="#d97706"/>
          <text x="327" y="39" fill="white" text-anchor="middle" font-size="12" font-weight="bold">genome</text>
          <text x="250" y="64" font-size="11">genome_id (PK)</text>
          <text x="250" y="80" font-size="11">organism_id (FK)</text>
          <text x="250" y="96" font-size="11">genome_name</text>
          <text x="250" y="112" font-size="11">genome_accession</text>
          <text x="250" y="128" font-size="11">genome_description</text>

          <!-- gene_set -->
          <rect x="480" y="20" width="175" height="110" rx="4" fill="#ffe4e6" stroke="#e11d48" stroke-width="2"/>
          <rect x="480" y="20" width="175" height="28" rx="4" fill="#e11d48"/>
          <text x="567" y="39" fill="white" text-anchor="middle" font-size="12" font-weight="bold">gene_set</text>
          <text x="490" y="64" font-size="11">gene_set_id (PK)</text>
          <text x="490" y="80" font-size="11">genome_id (FK)</text>
          <text x="490" y="96" font-size="11">gene_set_name</text>
          <text x="490" y="112" font-size="11">gene_set_description</text>

          <!-- feature -->
          <rect x="720" y="20" width="200" height="158" rx="4" fill="#e0e7ff" stroke="#6366f1" stroke-width="2"/>
          <rect x="720" y="20" width="200" height="28" rx="4" fill="#6366f1"/>
          <text x="820" y="39" fill="white" text-anchor="middle" font-size="12" font-weight="bold">feature</text>
          <text x="730" y="64" font-size="11">feature_id (PK)</text>
          <text x="730" y="80" font-size="11">feature_uniquename</text>
          <text x="730" y="96" font-size="11">feature_type</text>
          <text x="730" y="112" font-size="11">feature_name</text>
          <text x="730" y="128" font-size="11">organism_id (FK)</text>
          <text x="730" y="144" font-size="11">gene_set_id (FK)</text>
          <text x="730" y="160" font-size="11">parent_feature_id (self)</text>

          <!-- annotation_source -->
          <rect x="20" y="340" width="200" height="125" rx="4" fill="#fef3c7" stroke="#f59e0b" stroke-width="2"/>
          <rect x="20" y="340" width="200" height="28" rx="4" fill="#f59e0b"/>
          <text x="120" y="359" fill="white" text-anchor="middle" font-size="12" font-weight="bold">annotation_source</text>
          <text x="30" y="384" font-size="11">annotation_source_id (PK)</text>
          <text x="30" y="400" font-size="11">annotation_source_name</text>
          <text x="30" y="416" font-size="11">annotation_type</text>
          <text x="30" y="432" font-size="11">annotation_accession_url</text>
          <text x="30" y="448" font-size="11">annotation_source_version</text>

          <!-- annotation -->
          <rect x="340" y="340" width="195" height="110" rx="4" fill="#fee2e2" stroke="#dc2626" stroke-width="2"/>
          <rect x="340" y="340" width="195" height="28" rx="4" fill="#dc2626"/>
          <text x="437" y="359" fill="white" text-anchor="middle" font-size="12" font-weight="bold">annotation</text>
          <text x="350" y="384" font-size="11">annotation_id (PK)</text>
          <text x="350" y="400" font-size="11">annotation_accession</text>
          <text x="350" y="416" font-size="11">annotation_description</text>
          <text x="350" y="432" font-size="11">annotation_source_id (FK)</text>

          <!-- feature_annotation -->
          <rect x="660" y="340" width="200" height="125" rx="4" fill="#ddd6fe" stroke="#7c3aed" stroke-width="2"/>
          <rect x="660" y="340" width="200" height="28" rx="4" fill="#7c3aed"/>
          <text x="760" y="359" fill="white" text-anchor="middle" font-size="12" font-weight="bold">feature_annotation</text>
          <text x="670" y="384" font-size="11">feature_annotation_id (PK)</text>
          <text x="670" y="400" font-size="11">feature_id (FK)</text>
          <text x="670" y="416" font-size="11">annotation_id (FK)</text>
          <text x="670" y="432" font-size="11">score</text>
          <text x="670" y="448" font-size="11">date</text>

          <!-- Arrows: organism → genome -->
          <line x1="180" y1="75" x2="238" y2="75" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
          <text x="205" y="68" font-size="10" fill="#555">1:N</text>

          <!-- genome → gene_set -->
          <line x1="415" y1="75" x2="478" y2="75" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
          <text x="436" y="68" font-size="10" fill="#555">1:N</text>

          <!-- gene_set → feature -->
          <line x1="655" y1="75" x2="718" y2="75" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
          <text x="672" y="68" font-size="10" fill="#555">1:N</text>

          <!-- annotation_source → annotation -->
          <line x1="220" y1="404" x2="338" y2="404" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
          <text x="262" y="397" font-size="10" fill="#555">1:N</text>

          <!-- annotation → feature_annotation -->
          <line x1="535" y1="404" x2="658" y2="404" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
          <text x="578" y="397" font-size="10" fill="#555">1:N</text>

          <!-- feature → feature_annotation (vertical) -->
          <line x1="820" y1="178" x2="820" y2="250" stroke="#555" stroke-width="1.5"/>
          <line x1="820" y1="250" x2="760" y2="250" stroke="#555" stroke-width="1.5"/>
          <line x1="760" y1="250" x2="760" y2="338" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
          <text x="775" y="244" font-size="10" fill="#555">1:N</text>

          <!-- Legend -->
          <text x="20" y="530" font-size="11" font-weight="bold">Legend:</text>
          <text x="20" y="548" font-size="11">PK = Primary Key  |  FK = Foreign Key  |  1:N = One-to-Many</text>
          <text x="20" y="564" font-size="11">self = self-referential (parent_feature_id points to feature_id in same table)</text>
        </svg>
      </div>
    </div>
  </section>

  <!-- Section 4: Feature Hierarchy -->
  <section id="hierarchical-structure" class="mt-5">
    <h4 class="fw-semibold mb-3"><i class="fa fa-tree me-2"></i>Feature Hierarchy</h4>

    <p class="text-muted">Features form a parent-child tree representing biological structure. The <code>parent_feature_id</code> column points to the parent feature in the same table.</p>

    <div class="card mb-3">
      <div class="card-body">
        <pre class="bg-light p-3 rounded border mb-0"><code>Gene (feature_type = "gene")
├── mRNA_1 (feature_type = "mRNA", parent_feature_id → gene)
│   ├── Exon_1 (feature_type = "exon", parent_feature_id → mRNA_1)
│   ├── Exon_2
│   └── CDS   (feature_type = "CDS")
│       └── Protein (feature_type = "protein")
│
└── mRNA_2 (alternative isoform)
    ├── Exon_1
    ├── Exon_3
    └── CDS
        └── Protein</code></pre>
      </div>
    </div>

    <p class="text-muted">On feature detail pages, MOOP traverses this tree to show all children of a gene (mRNAs, exons, proteins) and to auto-expand parent IDs in Sequence Retrieval.</p>
  </section>

  <!-- Section 5: Annotation System -->
  <section id="annotation-system" class="mt-5">
    <h4 class="fw-semibold mb-3"><i class="fa fa-tags me-2"></i>Annotation System</h4>

    <p class="text-muted">Annotations are normalized across three tables to avoid duplication — the same InterPro domain can link to thousands of features with a single <code>annotation</code> row.</p>

    <div class="card mb-4">
      <div class="card-body">
        <pre class="bg-light p-3 rounded border mb-0"><code>feature (g24397)
    ↓  feature_annotation (many rows, one per hit)
    ├─ feature_id=g24397, annotation_id=101, score=1e-45
    ├─ feature_id=g24397, annotation_id=202, score=1e-20
    └─ feature_id=g24397, annotation_id=303, score=0.95
         ↓  annotation
         ├─ id=101, accession=NP_000207.1, source_id=1
         ├─ id=202, accession=IPR003236,   source_id=2
         └─ id=303, accession=GO:0005179,  source_id=3
              ↓  annotation_source
              ├─ id=1, name="NCBI Protein BLAST",   type="Homologs"
              ├─ id=2, name="InterProScan (InterPro)", type="Domains"
              └─ id=3, name="Gene Ontology",          type="Gene Ontology"</code></pre>
      </div>
    </div>
  </section>

  <!-- Example Queries -->
  <section id="example-queries" class="mt-5">
    <h4 class="fw-semibold mb-3"><i class="fa fa-terminal me-2"></i>Example SQLite Queries</h4>
    <p class="text-muted mb-3">Run these against <code>organisms/Organism_Name/organism.sqlite</code> using <code>sqlite3</code> on the server or any SQLite browser.</p>

    <div class="card mb-3">
      <div class="card-header py-2 bg-light fw-semibold">Count genes and transcripts in a gene set</div>
      <div class="card-body p-0">
        <pre class="m-0 p-3 bg-dark text-light rounded-bottom" style="font-size:0.8rem;"><code>SELECT
    gs.gene_set_name,
    SUM(CASE WHEN f.feature_type = 'gene' THEN 1 ELSE 0 END) AS genes,
    SUM(CASE WHEN f.feature_type = 'mRNA' THEN 1 ELSE 0 END) AS transcripts
FROM gene_set gs
JOIN feature f ON f.gene_set_id = gs.gene_set_id
GROUP BY gs.gene_set_id, gs.gene_set_name;</code></pre>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2 bg-light fw-semibold">Look up a feature by ID and get its gene set and assembly</div>
      <div class="card-body p-0">
        <pre class="m-0 p-3 bg-dark text-light rounded-bottom" style="font-size:0.8rem;"><code>SELECT
    f.feature_uniquename,
    f.feature_type,
    f.feature_name,
    gs.gene_set_name,
    g.genome_accession
FROM feature f
JOIN gene_set gs ON f.gene_set_id = gs.gene_set_id
JOIN genome g    ON gs.genome_id  = g.genome_id
WHERE f.feature_uniquename = 'g24397';</code></pre>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2 bg-light fw-semibold">Search feature descriptions for a keyword</div>
      <div class="card-body p-0">
        <pre class="m-0 p-3 bg-dark text-light rounded-bottom" style="font-size:0.8rem;"><code>SELECT feature_uniquename, feature_type, feature_name, feature_description
FROM feature
WHERE feature_description LIKE '%kinase%'
  AND feature_type = 'gene'
LIMIT 50;</code></pre>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2 bg-light fw-semibold">Get all annotations for a feature (with source info)</div>
      <div class="card-body p-0">
        <pre class="m-0 p-3 bg-dark text-light rounded-bottom" style="font-size:0.8rem;"><code>SELECT
    ans.annotation_type,
    ans.annotation_source_name,
    a.annotation_accession,
    a.annotation_description,
    fa.score
FROM feature f
JOIN feature_annotation fa ON fa.feature_id        = f.feature_id
JOIN annotation        a   ON a.annotation_id       = fa.annotation_id
JOIN annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
WHERE f.feature_uniquename = 'g24397'
ORDER BY ans.annotation_type, fa.score;</code></pre>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2 bg-light fw-semibold">Find all features annotated with a specific GO term</div>
      <div class="card-body p-0">
        <pre class="m-0 p-3 bg-dark text-light rounded-bottom" style="font-size:0.8rem;"><code>SELECT DISTINCT f.feature_uniquename, f.feature_name, f.feature_type
FROM feature f
JOIN feature_annotation fa ON fa.feature_id  = f.feature_id
JOIN annotation        a   ON a.annotation_id = fa.annotation_id
WHERE a.annotation_accession = 'GO:0006351'  -- transcription by RNA pol II
  AND f.feature_type = 'gene';</code></pre>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2 bg-light fw-semibold">Annotation count by type for a gene set</div>
      <div class="card-body p-0">
        <pre class="m-0 p-3 bg-dark text-light rounded-bottom" style="font-size:0.8rem;"><code>SELECT
    ans.annotation_type,
    COUNT(DISTINCT a.annotation_id)  AS unique_annotations,
    COUNT(DISTINCT f.feature_id)     AS annotated_genes
FROM gene_set gs
JOIN feature           f   ON f.gene_set_id         = gs.gene_set_id
JOIN feature_annotation fa ON fa.feature_id          = f.feature_id
JOIN annotation         a  ON a.annotation_id        = fa.annotation_id
JOIN annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
WHERE gs.gene_set_name = 'SIMR_2025-01-24'
  AND f.feature_type   = 'gene'
GROUP BY ans.annotation_type
ORDER BY annotated_genes DESC;</code></pre>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2 bg-light fw-semibold">Get all mRNA children of a gene</div>
      <div class="card-body p-0">
        <pre class="m-0 p-3 bg-dark text-light rounded-bottom" style="font-size:0.8rem;"><code>SELECT child.feature_uniquename, child.feature_type, child.feature_name
FROM feature parent
JOIN feature child ON child.parent_feature_id = parent.feature_id
WHERE parent.feature_uniquename = 'g24397'
  AND child.feature_type = 'mRNA';</code></pre>
      </div>
    </div>

  </section>

  <!-- Summary -->
  <section id="summary" class="mt-4 mb-5">
    <div class="alert alert-success">
      <h6 class="fw-bold"><i class="fa fa-lightbulb me-1"></i>Key Takeaways</h6>
      <ul class="mb-0">
        <li><strong>One organism = one SQLite database</strong> containing all features and annotations across all assemblies</li>
        <li><strong>One assembly = one directory</strong> with <code>genome.fa</code> and its BLAST/FASTA indices at the assembly level</li>
        <li><strong>One gene set = one subdirectory</strong> inside the assembly, containing transcript/CDS/protein FASTAs and the GFF file</li>
        <li><strong>Features belong to a gene set</strong>, not directly to an assembly — the assembly is reached through the gene set</li>
        <li><strong>Feature hierarchy</strong> (gene → mRNA → exon) is encoded via <code>parent_feature_id</code></li>
        <li><strong>Annotations are normalized</strong> — one annotation row can link to many features</li>
      </ul>
    </div>

    <div class="mt-3">
      <a href="help.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i>Back to Help
      </a>
    </div>
  </section>

</div>
