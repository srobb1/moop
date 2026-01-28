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
  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <h2><i class="fa fa-database"></i> Organism Data Organization</h2>
  <p class="lead text-muted">Technical documentation on how MOOP organizes and stores organism data.</p>

  <!-- Quick Navigation -->
  <div class="alert alert-light border">
    <strong>On this page:</strong>
    <ul class="mb-0">
      <li><a href="#core-concepts">Core Concepts: Organisms, Assemblies, Features</a></li>
      <li><a href="#database-schema">Database Schema</a></li>
      <li><a href="#file-organization">File Organization</a></li>
      <li><a href="#hierarchical-structure">Hierarchical Structure</a></li>
      <li><a href="#annotation-system">Annotation System</a></li>
    </ul>
  </div>

  <!-- Section 1: Core Concepts -->
  <section id="core-concepts" class="mt-5">
    <h3><i class="fa fa-layer-group"></i> Core Concepts: Organisms, Assemblies, Features</h3>
    
    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-primary bg-opacity-10">
            <h5 class="mb-0">Organism</h5>
          </div>
          <div class="card-body">
            <p><strong>Definition:</strong> A biological species</p>
            <p><strong>Example:</strong> <em>Homo sapiens</em>, <em>Anoura caudifer</em></p>
            <ul class="mb-0">
              <li>One SQLite database per organism</li>
              <li>Contains one or more assemblies</li>
              <li>Stores all metadata and features</li>
              <li>Located at: <code>/data/moop/organisms/Organism_Name/organism.sqlite</code></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-info bg-opacity-10">
            <h5 class="mb-0">Assembly</h5>
          </div>
          <div class="card-body">
            <p><strong>Definition:</strong> A specific genome sequence build</p>
            <p><strong>Example:</strong> GRCh38 (human reference), GCA_004027475.1</p>
            <ul class="mb-0">
              <li>One version of an organism's genome</li>
              <li>Contains FASTA files and BLAST databases</li>
              <li>Multiple assemblies per organism allowed</li>
              <li>Located at: <code>/data/moop/organisms/Organism_Name/Assembly_ID/</code></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-success bg-opacity-10">
            <h5 class="mb-0">Feature</h5>
          </div>
          <div class="card-body">
            <p><strong>Definition:</strong> A genomic element (gene, mRNA, exon, protein domain)</p>
            <p><strong>Example:</strong> GENE_12345, insulin gene, exon_001</p>
            <ul class="mb-0">
              <li>Stored in SQLite database</li>
              <li>Has unique ID (uniquename)</li>
              <li>Linked to one assembly</li>
              <li>Can have child features (hierarchy)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-warning bg-opacity-10">
            <h5 class="mb-0">Annotation</h5>
          </div>
          <div class="card-body">
            <p><strong>Definition:</strong> Functional hit from computational analysis</p>
            <p><strong>Examples:</strong> BLAST hit, protein domain, ortholog</p>
            <ul class="mb-0">
              <li>Links features to external resources</li>
              <li>Has accession, description, score</li>
              <li>References annotation source (NCBI, InterPro, etc.)</li>
              <li>Stored in SQLite database</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Section 2: Directory Structure -->
  <section id="file-organization" class="mt-5">
    <h3><i class="fa fa-folder-tree"></i> File Organization</h3>

    <div class="alert alert-info">
      <strong><i class="fa fa-info-circle"></i> Root Directory:</strong> <code>/data/moop/organisms/</code>
    </div>

    <div class="card">
      <div class="card-body">
        <pre class="bg-light p-3 rounded border"><code>/data/moop/organisms/
├── Organism_Name_1/
│   ├── organism.sqlite              ← SQLite database for this organism
│   │                                  (contains all features, annotations, metadata)
│   ├── Assembly_ID_1/
│   │   ├── genome.fa                ← Reference genome (nucleotides)
│   │   ├── transcript.nt.fa         ← mRNA/transcript sequences
│   │   ├── cds.nt.fa                ← Coding sequence (nucleotides)
│   │   ├── protein.aa.fa            ← Protein sequences
│   │   ├── genome.fa.nhr            ← BLAST database files (nucleotide)
│   │   ├── genome.fa.nin            ← BLAST database files
│   │   ├── protein.aa.fa.phr        ← BLAST database files (protein)
│   │   └── protein.aa.fa.pin        ← BLAST database files
│   └── Assembly_ID_2/
│       └── [same structure...]
│
├── Organism_Name_2/
│   ├── organism.sqlite
│   └── Assembly_ID_1/
│       └── [same structure...]
│
└── [More organisms...]</code></pre>
      </div>
    </div>

    <h4 class="mt-4">File Types Explained</h4>
    <table class="table table-sm">
      <thead class="table-light">
        <tr>
          <th>File</th>
          <th>Purpose</th>
          <th>Format</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>organism.sqlite</code></td>
          <td>SQLite database with all features, annotations, and metadata</td>
          <td>Binary SQLite DB</td>
        </tr>
        <tr>
          <td><code>genome.fa</code></td>
          <td>Complete reference genome sequences</td>
          <td>FASTA (nucleotides)</td>
        </tr>
        <tr>
          <td><code>transcript.nt.fa</code></td>
          <td>mRNA/transcript sequences</td>
          <td>FASTA (nucleotides)</td>
        </tr>
        <tr>
          <td><code>cds.nt.fa</code></td>
          <td>Coding sequences (exons combined)</td>
          <td>FASTA (nucleotides)</td>
        </tr>
        <tr>
          <td><code>protein.aa.fa</code></td>
          <td>Protein sequences (translated from CDS)</td>
          <td>FASTA (amino acids)</td>
        </tr>
        <tr>
          <td><code>*.nhr, *.nin, *.nsq</code></td>
          <td>BLAST database indices (nucleotide)</td>
          <td>Binary BLAST index</td>
        </tr>
        <tr>
          <td><code>*.phr, *.pin, *.psq</code></td>
          <td>BLAST database indices (protein)</td>
          <td>Binary BLAST index</td>
        </tr>
      </tbody>
    </table>

    <h4 class="mt-4"><i class="fa fa-lightbulb"></i> Configuration Note</h4>
    <p>File naming patterns are <strong>not hardcoded</strong>. They're configured in <code>config/config_editable.json</code> under the <code>sequence_types</code> key, allowing flexibility for different naming conventions across organisms.</p>
  </section>

  <!-- Section 3: Database Schema -->
  <section id="database-schema" class="mt-5">
    <h3><i class="fa fa-sitemap"></i> Database Schema</h3>

    <p>Each organism has one SQLite database with the following table structure:</p>

    <h4 class="mt-4">Database Tables</h4>

    <!-- Organism Table -->
    <div class="card mb-3">
      <div class="card-header bg-primary text-white">
        <code>organism</code> - Species metadata
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Column</th>
              <th>Type</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>organism_id</code></td>
              <td>INTEGER</td>
              <td>Primary key, auto-increment</td>
            </tr>
            <tr>
              <td><code>genus</code></td>
              <td>TEXT</td>
              <td>Genus name (e.g., "Homo")</td>
            </tr>
            <tr>
              <td><code>species</code></td>
              <td>TEXT</td>
              <td>Species epithet (e.g., "sapiens")</td>
            </tr>
            <tr>
              <td><code>subtype</code></td>
              <td>TEXT</td>
              <td>Optional subspecies or strain</td>
            </tr>
            <tr>
              <td><code>common_name</code></td>
              <td>TEXT</td>
              <td>Display name (e.g., "Human")</td>
            </tr>
            <tr>
              <td><code>taxon_id</code></td>
              <td>INTEGER</td>
              <td>NCBI Taxonomy ID (optional)</td>
            </tr>
          </tbody>
        </table>
        <p class="mb-0"><em>Typically 1 row per database (one organism per SQLite file)</em></p>
      </div>
    </div>

    <!-- Genome Table -->
    <div class="card mb-3">
      <div class="card-header bg-info text-white">
        <code>genome</code> - Assembly/build metadata
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Column</th>
              <th>Type</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>genome_id</code></td>
              <td>INTEGER</td>
              <td>Primary key, auto-increment</td>
            </tr>
            <tr>
              <td><code>organism_id</code></td>
              <td>INTEGER</td>
              <td>Foreign key → organism.organism_id</td>
            </tr>
            <tr>
              <td><code>genome_name</code></td>
              <td>TEXT</td>
              <td>Assembly name (e.g., "GRCh38", "assembly_v1")</td>
            </tr>
            <tr>
              <td><code>genome_accession</code></td>
              <td>TEXT</td>
              <td>Assembly accession ID (e.g., "GCA_000001405.28")</td>
            </tr>
            <tr>
              <td><code>genome_description</code></td>
              <td>TEXT</td>
              <td>Description of this assembly</td>
            </tr>
          </tbody>
        </table>
        <p class="mb-0"><em>Multiple rows per database (one row per assembly)</em></p>
      </div>
    </div>

    <!-- Feature Table -->
    <div class="card mb-3">
      <div class="card-header bg-success text-white">
        <code>feature</code> - Genomic elements (genes, mRNAs, exons)
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Column</th>
              <th>Type</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>feature_id</code></td>
              <td>INTEGER</td>
              <td>Primary key, auto-increment</td>
            </tr>
            <tr>
              <td><code>feature_uniquename</code></td>
              <td>TEXT</td>
              <td>Unique identifier (UNIQUE constraint) - used for searches</td>
            </tr>
            <tr>
              <td><code>feature_type</code></td>
              <td>TEXT</td>
              <td>Type: "gene", "mRNA", "exon", "protein", etc.</td>
            </tr>
            <tr>
              <td><code>feature_name</code></td>
              <td>TEXT</td>
              <td>Display name (e.g., "Insulin", "Insulin-1")</td>
            </tr>
            <tr>
              <td><code>feature_description</code></td>
              <td>TEXT</td>
              <td>Text description (searchable)</td>
            </tr>
            <tr>
              <td><code>genome_id</code></td>
              <td>INTEGER</td>
              <td>Foreign key → genome.genome_id (which assembly)</td>
            </tr>
            <tr>
              <td><code>organism_id</code></td>
              <td>INTEGER</td>
              <td>Foreign key → organism.organism_id (denormalized for speed)</td>
            </tr>
            <tr>
              <td><code>parent_feature_id</code></td>
              <td>INTEGER</td>
              <td>Self-reference for hierarchy (gene → mRNA → exon)</td>
            </tr>
          </tbody>
        </table>
        <p class="mb-0"><em>Thousands to millions of rows per database</em></p>
      </div>
    </div>

    <!-- Annotation Source Table -->
    <div class="card mb-3">
      <div class="card-header bg-warning text-white">
        <code>annotation_source</code> - External databases/sources
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Column</th>
              <th>Type</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>annotation_source_id</code></td>
              <td>INTEGER</td>
              <td>Primary key, auto-increment</td>
            </tr>
            <tr>
              <td><code>annotation_source_name</code></td>
              <td>TEXT</td>
              <td>Source name (e.g., "NCBI", "InterPro", "UniProt")</td>
            </tr>
            <tr>
              <td><code>annotation_source_version</code></td>
              <td>TEXT</td>
              <td>Version info (e.g., "2024-01-15", "v5.2")</td>
            </tr>
            <tr>
              <td><code>annotation_accession_url</code></td>
              <td>TEXT</td>
              <td>URL template with {ID} placeholder (e.g., "https://www.ncbi.nlm.nih.gov/protein/{ID}")</td>
            </tr>
            <tr>
              <td><code>annotation_source_url</code></td>
              <td>TEXT</td>
              <td>Website URL for the source</td>
            </tr>
            <tr>
              <td><code>annotation_type</code></td>
              <td>TEXT</td>
              <td>Type: "homolog", "ortholog", "domain", "pathway", "go_term"</td>
            </tr>
          </tbody>
        </table>
        <p class="mb-0"><em>Typically 5-20 rows per database</em></p>
      </div>
    </div>

    <!-- Annotation Table -->
    <div class="card mb-3">
      <div class="card-header" style="background-color: #fd7e14; color: white;">
        <code>annotation</code> - Annotation records
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Column</th>
              <th>Type</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>annotation_id</code></td>
              <td>INTEGER</td>
              <td>Primary key, auto-increment</td>
            </tr>
            <tr>
              <td><code>annotation_accession</code></td>
              <td>TEXT</td>
              <td>External ID (e.g., "NM_000207.1", "IPR003236")</td>
            </tr>
            <tr>
              <td><code>annotation_description</code></td>
              <td>TEXT</td>
              <td>Text from external source (searchable)</td>
            </tr>
            <tr>
              <td><code>annotation_source_id</code></td>
              <td>INTEGER</td>
              <td>Foreign key → annotation_source.annotation_source_id</td>
            </tr>
          </tbody>
        </table>
        <p class="mb-0"><em>Thousands of rows per database</em></p>
      </div>
    </div>

    <!-- Feature Annotation Table -->
    <div class="card mb-3">
      <div class="card-header" style="background-color: #6f42c1; color: white;">
        <code>feature_annotation</code> - Links features to annotations
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Column</th>
              <th>Type</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>feature_annotation_id</code></td>
              <td>INTEGER</td>
              <td>Primary key, auto-increment</td>
            </tr>
            <tr>
              <td><code>feature_id</code></td>
              <td>INTEGER</td>
              <td>Foreign key → feature.feature_id</td>
            </tr>
            <tr>
              <td><code>annotation_id</code></td>
              <td>INTEGER</td>
              <td>Foreign key → annotation.annotation_id</td>
            </tr>
            <tr>
              <td><code>score</code></td>
              <td>REAL</td>
              <td>e-value, bit score, or confidence value</td>
            </tr>
            <tr>
              <td><code>date</code></td>
              <td>TEXT</td>
              <td>When this annotation was calculated/loaded</td>
            </tr>
          </tbody>
        </table>
        <p class="mb-0"><em>Hundreds of thousands to millions of rows (many annotations per feature)</em></p>
      </div>
    </div>

    <!-- ER Diagram -->
    <h4 class="mt-5"><i class="fa fa-diagram-project"></i> Entity Relationship Diagram</h4>
    <div class="card">
      <div class="card-body">
        <svg viewBox="0 0 1000 700" class="w-100" style="max-height: 600px;">
          <!-- Title -->
          <text x="500" y="25" font-size="20" font-weight="bold" text-anchor="middle">MOOP Database Schema - Entity Relationship Diagram</text>

          <!-- ORGANISM Table -->
          <rect x="50" y="50" width="180" height="140" fill="#cfe2ff" stroke="#0d6efd" stroke-width="2"/>
          <rect x="50" y="50" width="180" height="30" fill="#0d6efd"/>
          <text x="140" y="70" font-weight="bold" fill="white" text-anchor="middle" font-size="14">organism</text>
          <text x="60" y="95" font-family="monospace" font-size="12">organism_id (PK)</text>
          <text x="60" y="115" font-family="monospace" font-size="12">genus</text>
          <text x="60" y="135" font-family="monospace" font-size="12">species</text>
          <text x="60" y="155" font-family="monospace" font-size="12">common_name</text>
          <text x="60" y="175" font-family="monospace" font-size="12">taxon_id</text>

          <!-- GENOME Table -->
          <rect x="350" y="50" width="180" height="140" fill="#d1e7dd" stroke="#198754" stroke-width="2"/>
          <rect x="350" y="50" width="180" height="30" fill="#198754"/>
          <text x="440" y="70" font-weight="bold" fill="white" text-anchor="middle" font-size="14">genome</text>
          <text x="360" y="95" font-family="monospace" font-size="12">genome_id (PK)</text>
          <text x="360" y="115" font-family="monospace" font-size="12">organism_id (FK)</text>
          <text x="360" y="135" font-family="monospace" font-size="12">genome_name</text>
          <text x="360" y="155" font-family="monospace" font-size="12">genome_accession</text>
          <text x="360" y="175" font-family="monospace" font-size="12">genome_description</text>

          <!-- FEATURE Table -->
          <rect x="680" y="50" width="200" height="160" fill="#e7d4f5" stroke="#6f42c1" stroke-width="2"/>
          <rect x="680" y="50" width="200" height="30" fill="#6f42c1"/>
          <text x="780" y="70" font-weight="bold" fill="white" text-anchor="middle" font-size="14">feature</text>
          <text x="690" y="95" font-family="monospace" font-size="12">feature_id (PK)</text>
          <text x="690" y="115" font-family="monospace" font-size="12">feature_uniquename</text>
          <text x="690" y="135" font-family="monospace" font-size="12">feature_type</text>
          <text x="690" y="155" font-family="monospace" font-size="12">feature_name</text>
          <text x="690" y="175" font-family="monospace" font-size="12">genome_id (FK)</text>
          <text x="690" y="195" font-family="monospace" font-size="12">parent_feature_id (self-ref)</text>

          <!-- ANNOTATION_SOURCE Table -->
          <rect x="50" y="350" width="200" height="140" fill="#fff3cd" stroke="#ff9800" stroke-width="2"/>
          <rect x="50" y="350" width="200" height="30" fill="#ff9800"/>
          <text x="150" y="370" font-weight="bold" fill="white" text-anchor="middle" font-size="14">annotation_source</text>
          <text x="60" y="395" font-family="monospace" font-size="12">annotation_source_id (PK)</text>
          <text x="60" y="415" font-family="monospace" font-size="12">annotation_source_name</text>
          <text x="60" y="435" font-family="monospace" font-size="12">annotation_source_version</text>
          <text x="60" y="455" font-family="monospace" font-size="12">annotation_accession_url</text>
          <text x="60" y="475" font-family="monospace" font-size="12">annotation_type</text>

          <!-- ANNOTATION Table -->
          <rect x="350" y="350" width="180" height="120" fill="#f8d7da" stroke="#dc3545" stroke-width="2"/>
          <rect x="350" y="350" width="180" height="30" fill="#dc3545"/>
          <text x="440" y="370" font-weight="bold" fill="white" text-anchor="middle" font-size="14">annotation</text>
          <text x="360" y="395" font-family="monospace" font-size="12">annotation_id (PK)</text>
          <text x="360" y="415" font-family="monospace" font-size="12">annotation_accession</text>
          <text x="360" y="435" font-family="monospace" font-size="12">annotation_description</text>
          <text x="360" y="455" font-family="monospace" font-size="12">annotation_source_id (FK)</text>

          <!-- FEATURE_ANNOTATION Table -->
          <rect x="680" y="350" width="200" height="140" fill="#d1ecf1" stroke="#0c5460" stroke-width="2"/>
          <rect x="680" y="350" width="200" height="30" fill="#0c5460"/>
          <text x="780" y="370" font-weight="bold" fill="white" text-anchor="middle" font-size="14">feature_annotation</text>
          <text x="690" y="395" font-family="monospace" font-size="12">feature_annotation_id (PK)</text>
          <text x="690" y="415" font-family="monospace" font-size="12">feature_id (FK)</text>
          <text x="690" y="435" font-family="monospace" font-size="12">annotation_id (FK)</text>
          <text x="690" y="455" font-family="monospace" font-size="12">score</text>
          <text x="690" y="475" font-family="monospace" font-size="12">date</text>

          <!-- Relationships -->
          <!-- organism -> genome -->
          <line x1="230" y1="120" x2="350" y2="120" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>
          <text x="280" y="110" font-size="12" fill="#333">1:N</text>

          <!-- genome -> feature -->
          <line x1="530" y1="120" x2="680" y2="120" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>
          <text x="600" y="110" font-size="12" fill="#333">1:N</text>

          <!-- annotation_source -> annotation -->
          <line x1="250" y1="420" x2="350" y2="420" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>
          <text x="290" y="410" font-size="12" fill="#333">1:N</text>

          <!-- annotation -> feature_annotation -->
          <line x1="530" y1="420" x2="680" y2="420" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>
          <text x="600" y="410" font-size="12" fill="#333">1:N</text>

          <!-- feature -> feature_annotation -->
          <line x1="780" y1="210" x2="780" y2="350" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>
          <text x="800" y="280" font-size="12" fill="#333">1:N</text>

          <!-- Legend -->
          <text x="50" y="650" font-weight="bold" font-size="14">Legend:</text>
          <text x="50" y="680" font-size="12">PK = Primary Key | FK = Foreign Key | 1:N = One-to-Many Relationship</text>

          <!-- Arrow marker definition -->
          <defs>
            <marker id="arrowhead" markerWidth="10" markerHeight="10" refX="5" refY="5" orient="auto">
              <polygon points="0 0, 10 5, 0 10" fill="#333"/>
            </marker>
          </defs>
        </svg>
      </div>
    </div>
  </section>

  <!-- Section 4: Hierarchical Structure -->
  <section id="hierarchical-structure" class="mt-5">
    <h3><i class="fa fa-tree"></i> Hierarchical Structure: Feature Relationships</h3>

    <p>Features can have parent-child relationships, representing biological hierarchy:</p>

    <div class="card">
      <div class="card-body">
        <pre class="bg-light p-3 rounded border"><code>Gene (parent)
├── mRNA_001 (transcript, parent_feature_id = gene_id)
│   ├── Exon_001 (parent_feature_id = mRNA_001)
│   ├── Exon_002
│   ├── Exon_003
│   └── CDS (coding sequence)
│       └── Protein (translated from CDS)
│
└── mRNA_002 (alternative spliceform)
    ├── Exon_001 (different exon structure)
    ├── Exon_004
    └── CDS
        └── Protein (different translation)</code></pre>
      </div>
    </div>

    <h4 class="mt-3">Implementation</h4>
    <p>The <code>parent_feature_id</code> column in the <code>feature</code> table stores the feature_id of the parent. This enables:</p>
    <ul>
      <li><strong>Traversal:</strong> Find all children of a gene, all parent features of an exon</li>
      <li><strong>Hierarchy Display:</strong> Show indented trees on feature detail pages</li>
      <li><strong>Batch Operations:</strong> Download all sequences in a transcript</li>
    </ul>

    <h4 class="mt-3">Example Query</h4>
    <div class="alert alert-light border-left border-primary">
      <strong>Find all mRNAs of a gene:</strong>
      <pre class="mb-0"><code class="language-sql">SELECT * FROM feature
WHERE parent_feature_id IN (
    SELECT feature_id FROM feature
    WHERE feature_id = ? AND feature_type = 'gene'
)
AND feature_type = 'mRNA';</code></pre>
    </div>
  </section>

  <!-- Section 5: Annotation System -->
  <section id="annotation-system" class="mt-5">
    <h3><i class="fa fa-tags"></i> Annotation System</h3>

    <h4>Data Flow: From Feature to Annotation</h4>

    <div class="card">
      <div class="card-body">
        <pre class="bg-light p-3 rounded border"><code>Feature (GENE_12345)
    ↓
feature_annotation (many rows)
    ├─ Links to annotation_id=1, score=1e-45, date=2024-12-02
    ├─ Links to annotation_id=2, score=1e-20, date=2024-12-02
    └─ Links to annotation_id=3, score=0.95, date=2024-12-02
        ↓
    annotation (the actual annotations)
        ├─ annotation_id=1, accession=NP_000207.1, source_id=1
        ├─ annotation_id=2, accession=IPR003236, source_id=2
        └─ annotation_id=3, accession=GO:0005179, source_id=3
            ↓
        annotation_source (where they came from)
            ├─ source_id=1, name=NCBI, url_template=https://ncbi.nlm.nih.gov/protein/{ID}
            ├─ source_id=2, name=InterPro, url_template=https://ebi.ac.uk/interpro/{ID}
            └─ source_id=3, name=Gene Ontology, url_template=http://amigo.geneontology.org/{ID}</code></pre>
      </div>
    </div>

    <h4 class="mt-3">Why This Structure?</h4>

    <div class="row mt-3">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header bg-success bg-opacity-10">
            <h6 class="mb-0"><i class="fa fa-check"></i> Advantages</h6>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li><strong>Reusability:</strong> One annotation can link to multiple features</li>
              <li><strong>Efficiency:</strong> Store identical annotations once</li>
              <li><strong>Flexibility:</strong> Easy to add new annotation sources</li>
              <li><strong>Queryable:</strong> Search by score, date, source</li>
              <li><strong>Scalability:</strong> Millions of feature_annotation rows</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header bg-info bg-opacity-10">
            <h6 class="mb-0"><i class="fa fa-search"></i> Query Examples</h6>
          </div>
          <div class="card-body">
            <p><strong>Find all features with BLAST hits:</strong></p>
            <pre class="mb-3 p-2 bg-light rounded" style="font-size: 11px;"><code>SELECT f.* FROM feature f
JOIN feature_annotation fa
JOIN annotation a
WHERE a.annotation_source_id = 1</code></pre>

            <p><strong>Get protein domains for a gene:</strong></p>
            <pre class="mb-0 p-2 bg-light rounded" style="font-size: 11px;"><code>SELECT a.* FROM annotation a
JOIN feature_annotation fa
WHERE fa.feature_id = ?
AND a.annotation_source_id = 2</code></pre>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Summary -->
  <section id="summary" class="mt-5 mb-5">
    <div class="alert alert-success">
      <h5><i class="fa fa-lightbulb"></i> Key Takeaways</h5>
      <ul class="mb-0">
        <li><strong>One organism = one SQLite database</strong> containing all data for that species</li>
        <li><strong>One assembly = one directory</strong> with FASTA files and BLAST indices</li>
        <li><strong>Features have hierarchy</strong> (gene → mRNA → exon) via parent_feature_id</li>
        <li><strong>Annotations are normalized</strong> to reduce storage and enable reuse</li>
        <li><strong>Everything is queryable</strong> through SQLite: search features, filter by annotation source, sort by score</li>
      </ul>
    </div>

    <!-- Back to Help Link -->
    <div class="mt-4">
      <a href="help.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Help
      </a>
    </div>
  </section>

</div>

<style>
.card {
  margin-bottom: 1rem;
}

table.table-sm {
  font-size: 0.9rem;
}

code {
  background-color: #f5f5f5;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: 'Courier New', monospace;
}

pre {
  overflow-x: auto;
}

.border-left {
  border-left: 4px solid !important;
}
</style>
