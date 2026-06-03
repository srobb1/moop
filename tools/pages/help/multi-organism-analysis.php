<?php
/**
 * MULTI-ORGANISM ANALYSIS TUTORIAL - Content File
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
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-project-diagram me-2"></i>Multi-Organism Analysis</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-0">
            Several MOOP tools work across multiple organisms at once. This page explains how to select organisms for multi-organism work and which tool to use for different tasks.
          </p>
        </div>
      </div>

      <!-- Selecting organisms -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Selecting Multiple Organisms</h5>
          <p class="text-muted mb-3">Start from the home page:</p>
          <ul class="text-muted mb-0">
            <li><strong>Group page</strong> — click a group card to open a set of organisms, then use the checkboxes to include or exclude specific organisms from searches.</li>
            <li><strong>Taxonomy tree</strong> — use Tree Select to pick organisms from different branches. Click a parent node to select all organisms below it. Your selection is shown in the sidebar.</li>
            <li><strong>Multi-Organism Search page</strong> — this tool has its own organism selector built in (step 2), so you can refine your selection within the tool itself.</li>
          </ul>
        </div>
      </div>

      <!-- Tool comparison -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Which Tool to Use</h5>
          <div class="table-responsive">
            <table class="table table-sm table-bordered text-muted mb-0">
              <thead class="table-light">
                <tr><th>Task</th><th>Tool</th></tr>
              </thead>
              <tbody>
                <tr><td>Find genes matching a keyword or annotation term across many organisms</td><td><strong>Annotation Search</strong></td></tr>
                <tr><td>Build a gene list by ID, name, description, or GO term across multiple assemblies, then export TSV/FASTA sequences</td><td><strong>MOOPmart</strong></td></tr>
                <tr><td>Compare a sequence against multiple genome assemblies</td><td><strong>BLAST Search</strong></td></tr>
                <tr><td>Retrieve sequences by ID from a single assembly</td><td><strong>Retrieve Sequences</strong></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Annotation Search multi-organism -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Annotation Search — Multi-Organism Mode</h5>
          <p class="text-muted mb-2">The Annotation Search tool's step 2 lets you choose which <strong>organism → assembly → gene set</strong> combinations to include. This is more granular than organism-level selection — if an organism has two gene sets, you can include one and exclude the other.</p>
          <p class="text-muted mb-0">Results are returned per organism so you can see which organisms have matches and which don't.</p>
        </div>
      </div>

      <!-- MOOPmart multi-organism -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">MOOPmart — Cross-Assembly Export</h5>
          <p class="text-muted mb-2">MOOPmart is especially powerful for multi-organism work. Select multiple assemblies in step 1 and search by shared feature name to find genes across all of them in a single query.</p>
          <p class="text-muted mb-0">For example: select 10 bat assemblies, search by feature name for <code>TP53</code>, and download a TSV with all matching features and their annotations across every assembly — in one click.</p>
        </div>
      </div>

      <!-- Tips -->
      <div class="card shadow-sm mb-4 border-0" style="background:#f0f9ff;">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3"><i class="fa fa-lightbulb me-2" style="color:#0891b2;"></i>Tips</h5>
          <ul class="text-muted mb-0">
            <li>Start with a small selection of organisms to test your query before expanding to all of them.</li>
            <li>Annotation Search returns up to 2,500 results per organism — use specific terms or limit annotation types to stay under that limit.</li>
            <li>For large cross-organism exports, MOOPmart is more efficient than exporting search results — it runs a single optimized query rather than one per organism.</li>
            <li>Results from multi-organism searches always include the organism and assembly columns so you can tell which organism each hit came from.</li>
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
