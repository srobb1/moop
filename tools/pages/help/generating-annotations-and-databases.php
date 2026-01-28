<?php
/**
 * Help Page: Generating Annotations and Databases
 * 
 * Technical guide for:
 * - Generating annotations from various sources
 * - Creating organism.sqlite databases
 * - Loading data into the database
 */
?>

<div class="container mt-4 mb-5">
  <div class="row">
    <div class="col-lg-10">
      
      <h1 class="mb-4"><i class="fa fa-database"></i> Generating Annotations and Databases</h1>
      
      <div class="alert alert-info mb-4">
        <i class="fa fa-info-circle"></i>
        <strong>Overview:</strong> This guide covers the technical process of generating annotations from various sources and creating/loading organism.sqlite databases for MOOP.
      </div>

      <!-- Table of Contents -->
      <nav class="mb-4">
        <h5 class="mb-3">Quick Navigation</h5>
        <ul class="list-unstyled">
          <li><a href="#overview">Overview</a></li>
          <li><a href="#annotations">Generating Annotations</a></li>
          <li><a href="#database-creation">Creating the Database</a></li>
          <li><a href="#data-loading">Loading Data</a></li>
          <li><a href="#best-practices">Best Practices</a></li>
        </ul>
      </nav>

      <!-- Overview Section -->
      <section id="overview" class="mt-5">
        <h2><i class="fa fa-book"></i> Overview</h2>
        
        <p>Building a complete MOOP organism entry requires:</p>
        <ol>
          <li><strong>Feature Data:</strong> Genes, mRNAs, and other features from genome annotation</li>
          <li><strong>Annotations:</strong> Functional data linked to features (homologs, domains, pathways)</li>
          <li><strong>Database:</strong> SQLite file containing all structured data</li>
        </ol>

        <p class="mt-3">The typical workflow is:</p>
        <div class="card bg-light border">
          <div class="card-body">
            <p class="mb-0">
              Source Data Files (GFF, FASTA, etc.)
              <i class="fa fa-arrow-right mx-2"></i>
              Parse & Process
              <i class="fa fa-arrow-right mx-2"></i>
              Generate Annotations
              <i class="fa fa-arrow-right mx-2"></i>
              Create SQLite Database
              <i class="fa fa-arrow-right mx-2"></i>
              Load into MOOP
            </p>
          </div>
        </div>
      </section>

      <!-- Annotations Section -->
      <section id="annotations" class="mt-5">
        <h2><i class="fa fa-tags"></i> Generating Annotations</h2>
        
        <p>Annotations are functional descriptors linked to genomic features. Common annotation types include:</p>

        <div class="row mt-4">
          <div class="col-md-6 mb-3">
            <div class="card h-100">
              <div class="card-header bg-primary bg-opacity-10">
                <h5 class="mb-0">Homologs</h5>
              </div>
              <div class="card-body">
                <p><strong>Source:</strong> BLAST searches against sequence databases</p>
                <p><strong>Tools:</strong> BLAST+, OrthoMCL, InParanoid</p>
                <p class="mb-0"><strong>Output:</strong> Match coordinates, e-values, percent identity</p>
              </div>
            </div>
          </div>

          <div class="col-md-6 mb-3">
            <div class="card h-100">
              <div class="card-header bg-primary bg-opacity-10">
                <h5 class="mb-0">Protein Domains</h5>
              </div>
              <div class="card-body">
                <p><strong>Source:</strong> Protein domain databases</p>
                <p><strong>Tools:</strong> InterProScan, HMMER, PFAM</p>
                <p class="mb-0"><strong>Output:</strong> Domain names, locations, scores</p>
              </div>
            </div>
          </div>

          <div class="col-md-6 mb-3">
            <div class="card h-100">
              <div class="card-header bg-primary bg-opacity-10">
                <h5 class="mb-0">Gene Ontology</h5>
              </div>
              <div class="card-body">
                <p><strong>Source:</strong> Computational prediction or manual curation</p>
                <p><strong>Tools:</strong> Blast2GO, InterProScan, PANTHER</p>
                <p class="mb-0"><strong>Output:</strong> GO terms with evidence codes</p>
              </div>
            </div>
          </div>

          <div class="col-md-6 mb-3">
            <div class="card h-100">
              <div class="card-header bg-primary bg-opacity-10">
                <h5 class="mb-0">Pathways</h5>
              </div>
              <div class="card-body">
                <p><strong>Source:</strong> Pathway databases and inference</p>
                <p><strong>Tools:</strong> KEGG, Reactome mappers</p>
                <p class="mb-0"><strong>Output:</strong> Pathway IDs and names</p>
              </div>
            </div>
          </div>
        </div>

        <div class="alert alert-warning mt-4">
          <i class="fa fa-exclamation-triangle"></i>
          <strong>Coming Soon:</strong> Detailed instructions for generating each annotation type will be added here.
        </div>
      </section>

      <!-- Database Creation Section -->
      <section id="database-creation" class="mt-5">
        <h2><i class="fa fa-database"></i> Creating the Database</h2>
        
        <p>The <code>organism.sqlite</code> file uses SQLite with a specific schema designed for genomic data.</p>

        <h4 class="mt-4">Database Schema</h4>
        <p>The database contains the following main tables:</p>

        <div class="table-responsive mt-3">
          <table class="table table-bordered table-sm">
            <thead class="table-light">
              <tr>
                <th>Table</th>
                <th>Purpose</th>
                <th>Key Fields</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>feature</code></td>
                <td>Genomic features (genes, mRNAs, etc.)</td>
                <td>feature_id, feature_type, sequence, parent_feature_id</td>
              </tr>
              <tr>
                <td><code>annotation</code></td>
                <td>Functional annotations</td>
                <td>annotation_id, annotation_source_id, value</td>
              </tr>
              <tr>
                <td><code>feature_annotation</code></td>
                <td>Links features to annotations</td>
                <td>feature_id, annotation_id</td>
              </tr>
              <tr>
                <td><code>annotation_source</code></td>
                <td>Types and sources of annotations</td>
                <td>annotation_source_id, type, description</td>
              </tr>
              <tr>
                <td><code>assembly</code></td>
                <td>Genome assembly information</td>
                <td>assembly_id, name, accession</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="alert alert-info mt-4">
          <i class="fa fa-lightbulb"></i>
          <strong>Full Schema Reference:</strong> See the <a href="organism-data-organization.php#database-schema">Database Schema section</a> for complete field definitions and relationships.
        </div>

        <div class="alert alert-warning mt-4">
          <i class="fa fa-exclamation-triangle"></i>
          <strong>Coming Soon:</strong> Step-by-step instructions for creating the database schema and initializing tables will be added here.
        </div>
      </section>

      <!-- Data Loading Section -->
      <section id="data-loading" class="mt-5">
        <h2><i class="fa fa-upload"></i> Loading Data</h2>
        
        <p>Once the database schema is created, you need to populate it with your genomic and annotation data.</p>

        <h4 class="mt-4">Typical Data Sources</h4>
        <ul>
          <li><strong>Features:</strong> GFF3/GTF files, NCBI GenBank records</li>
          <li><strong>Sequences:</strong> FASTA files (proteins, mRNA, genomic)</li>
          <li><strong>Annotations:</strong> BLAST results, domain predictions, GO assignments</li>
        </ul>

        <h4 class="mt-4">Loading Process</h4>
        <p>The general process involves:</p>
        <ol>
          <li>Parse source files (GFF3, FASTA, etc.)</li>
          <li>Transform data into database format</li>
          <li>Insert into appropriate tables</li>
          <li>Create indexes for performance</li>
          <li>Validate data integrity</li>
        </ol>

        <div class="alert alert-warning mt-4">
          <i class="fa fa-exclamation-triangle"></i>
          <strong>Coming Soon:</strong> Code examples and tools for loading different data types will be added here.
        </div>
      </section>

      <!-- Best Practices Section -->
      <section id="best-practices" class="mt-5 mb-5">
        <h2><i class="fa fa-star"></i> Best Practices</h2>
        
        <div class="card mb-3">
          <div class="card-header bg-success bg-opacity-10">
            <h5 class="mb-0"><i class="fa fa-check"></i> Do's</h5>
          </div>
          <div class="card-body">
            <ul>
              <li>Use consistent naming conventions for features and annotations</li>
              <li>Validate data before loading into the database</li>
              <li>Document the source and version of all data</li>
              <li>Create backups before making bulk updates</li>
              <li>Index frequently queried columns for performance</li>
              <li>Use transactions for multi-step operations</li>
            </ul>
          </div>
        </div>

        <div class="card">
          <div class="card-header bg-danger bg-opacity-10">
            <h5 class="mb-0"><i class="fa fa-times"></i> Don'ts</h5>
          </div>
          <div class="card-body">
            <ul>
              <li>Don't skip data validation - garbage in = garbage out</li>
              <li>Don't modify the database schema without understanding dependencies</li>
              <li>Don't load duplicate features or annotations without deduplication</li>
              <li>Don't forget to handle missing or null values appropriately</li>
              <li>Don't overwrite production databases without a backup</li>
            </ul>
          </div>
        </div>
      </section>

    </div>
  </div>
</div>

<style>
  .structure-box {
    font-family: 'Courier New', monospace;
    white-space: pre-wrap;
    word-break: break-word;
  }
  
  .code-example {
    background-color: #f8f9fa;
    border-left: 3px solid #007bff;
  }
</style>
