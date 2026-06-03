<?php
/**
 * GETTING STARTED TUTORIAL - Content File
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

  <div class="row justify-content-center">
    <div class="col-lg-9">

      <div class="card shadow-sm mb-4">
        <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-rocket me-2"></i>Getting Started with MOOP</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-0">
            MOOP is a platform for exploring genome assemblies, genes, transcripts, and functional annotations across multiple organisms. This guide walks through the basics of getting oriented.
          </p>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">The Home Page</h5>
          <p class="text-muted mb-3">The home page has three ways to find and select organisms:</p>

          <h6 class="fw-semibold mb-1">Quick Search</h6>
          <p class="text-muted mb-3">A search bar at the top of the page that searches across organisms, groups, assemblies, and gene sets by name or accession. As you type, a dropdown shows matching results — click any entry to go directly to that page. Example chips below the bar show you what kinds of things you can search for.</p>

          <h6 class="fw-semibold mb-1">Browse by Group</h6>
          <p class="text-muted mb-3">A collapsible strip of group chips (e.g. Bats, Planaria). Click any chip to open that group's page, which shows all organisms in the group and lets you run searches scoped to that group.</p>

          <h6 class="fw-semibold mb-1">Browse &amp; Select</h6>
          <p class="text-muted mb-2">A collapsible panel with three tabs for building a custom organism selection:</p>
          <ul class="text-muted mb-0">
            <li><strong>Organism Select</strong> — a flat alphabetical list of all accessible organisms. Check the ones you want.</li>
            <li><strong>Taxon Select</strong> — filter organisms by taxonomic group. Useful for finding all organisms within a particular lineage.</li>
            <li><strong>Tree Select</strong> — an interactive taxonomy tree. Click any node to select all organisms below it. Your selection carries over between tabs.</li>
          </ul>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Organism, Assembly, and Gene Set Pages</h5>
          <p class="text-muted mb-3">Clicking through from the home page or a search result brings you to a feature page — a dedicated view for a specific scope of data:</p>
          <ul class="text-muted mb-0">
            <li><strong>Organism page</strong> — overview of all assemblies for one organism; search is scoped to that organism</li>
            <li><strong>Assembly page</strong> — overview of one genome assembly and its gene sets; search is scoped to that assembly</li>
            <li><strong>Gene Set page</strong> — gene and transcript counts, annotation summary by type (GO, Domains, Homologs, etc.), and download links; search is scoped to that gene set</li>
          </ul>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">The Tool Box</h5>
          <p class="text-muted mb-3">On any organism, assembly, or gene set page you'll see the <strong>Tool Box</strong> — a set of tools pre-configured with your current context. The available tools are:</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered text-muted mb-0">
              <thead class="table-light">
                <tr><th>Tool</th><th>What it does</th></tr>
              </thead>
              <tbody>
                <tr><td><strong>Annotation Search</strong></td><td>Find genes and features by keyword, annotation description, GO term, or ID. Supports multi-organism searches with fine-grained organism and annotation type filters.</td></tr>
                <tr><td><strong>BLAST Search</strong></td><td>Compare a DNA or protein sequence against genome assemblies to find similar sequences.</td></tr>
                <tr><td><strong>MOOPmart</strong></td><td>Build a gene list by ID, name, description, annotation term, or genomic location — then export as TSV or FASTA. The primary tool for bulk data download.</td></tr>
                <tr><td><strong>Retrieve Sequences</strong></td><td>Look up specific feature IDs and download their genomic, transcript, CDS, or protein sequences.</td></tr>
                <tr><td><strong>Downloads</strong></td><td>Browse and batch-download genome FASTA, GFF, and other files organized by organism → assembly → gene set.</td></tr>
                <tr><td><strong>View in Genome Browser</strong></td><td>Open JBrowse2 pre-loaded with the current assembly and its gene tracks.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">A Typical Workflow</h5>
          <ol class="text-muted mb-0">
            <li class="mb-2"><strong>Pick organisms</strong> — use a group card or the taxonomy tree.</li>
            <li class="mb-2"><strong>Search</strong> — use Annotation Search to find genes by keyword or GO term. Results show which features matched and which annotation sources they came from.</li>
            <li class="mb-2"><strong>Drill down</strong> — click a result to open the gene's detail page, which shows all annotations, sequences, and links to JBrowse.</li>
            <li class="mb-2"><strong>Export</strong> — use MOOPmart to bulk-export genes and annotations as TSV or FASTA, or Retrieve Sequences for specific IDs.</li>
          </ol>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Access Levels</h5>
          <p class="text-muted mb-2">Not all organisms and assemblies are visible to all users:</p>
          <ul class="text-muted mb-0">
            <li><strong>Public</strong> — can see and search public assemblies without logging in</li>
            <li><strong>Collaborator</strong> — logged-in user with access to specific organisms or assemblies granted by the administrator</li>
            <li><strong>Admin</strong> — full access to all data and the admin panel</li>
          </ul>
        </div>
      </div>

      <div class="mb-4">
        <a href="help.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-arrow-left me-1"></i>Back to Help
        </a>
      </div>
    </div>
  </div>
</div>
