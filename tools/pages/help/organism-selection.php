<?php
/**
 * ORGANISM SELECTION TUTORIAL - Content File
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

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-sm mb-4">
        <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-dna me-2"></i>Selecting Organisms</span>
        </div>
        <div class="card-body py-2">
          <p class="text-muted small mb-0">MOOP provides two flexible methods for selecting which organisms you want to work with.</p>
        </div>
      </div>

      <!-- Quick Search -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-2">Quick Search</h5>
          <p class="text-muted mb-2">The search bar at the top of the home page searches across organisms, groups, assemblies, and gene sets simultaneously. As you type, a dropdown shows matching results — click any entry to navigate directly to that page.</p>
          <p class="text-muted mb-0">Example chips below the bar show the kinds of queries you can make: species names, common names, group names, assembly accessions, or gene set names.</p>
        </div>
      </div>

      <!-- Browse by Group -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-2">Browse by Group</h5>
          <p class="text-muted mb-3">Click the <strong>Browse by Group</strong> header to expand a strip of group chips. Each chip is a curator-defined organism set (e.g. Bats, Planaria, Acorn Worms). Clicking a chip opens that group's page.</p>

          <h6 class="fw-semibold mb-2">On a group page</h6>
          <ul class="text-muted mb-0">
            <li><strong>Organism cards</strong> — each organism has a card with its image, scientific name, and common name. Click the card body to open the organism's dedicated page.</li>
            <li><strong>Selection bar</strong> — a checkbox at the top of each card lets you include or exclude that organism from group-level searches. All organisms are selected by default.</li>
            <li><strong>Select All / Deselect All</strong> — quickly toggle all organisms in the group.</li>
            <li><strong>Search</strong> — runs across only the checked organisms in the group.</li>
          </ul>
        </div>
      </div>

      <!-- Browse & Select -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Browse &amp; Select — Three Tabs</h5>
          <p class="text-muted mb-3">Click the <strong>Browse &amp; Select</strong> header to expand a tabbed panel for building a custom multi-organism selection. Your selection carries over between tabs.</p>

          <h6 class="fw-semibold mb-1">Organism Select</h6>
          <p class="text-muted mb-3">A flat alphabetical list of all accessible organisms. Check the ones you want to include. Use the filter box to search by name or common name. This is the fastest tab if you know exactly which organisms you need.</p>

          <h6 class="fw-semibold mb-1">Taxon Select</h6>
          <p class="text-muted mb-3">Filter organisms by taxonomic grouping. Useful for selecting everything within a particular lineage without navigating the full tree.</p>

          <h6 class="fw-semibold mb-1">Tree Select</h6>
          <p class="text-muted mb-3">An interactive taxonomy tree organized hierarchically by evolutionary relationships. Click any internal node to select all organisms below it; click again to deselect. Use the search box to jump to a specific organism or group. Your selection is shown in the right-hand panel.</p>

          <p class="text-muted mb-0">Once you've made a selection, click a tool in the <strong>Tool Box</strong> (which appears once organisms are selected) to open it with your selection pre-loaded.</p>
        </div>
      </div>

      <div class="card shadow-sm mb-4 border-0" style="background:#f0f9ff;">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3"><i class="fa fa-lightbulb me-2" style="color:#0891b2;"></i>Tips</h5>
          <ul class="text-muted mb-0">
            <li>Quick Search is the fastest way to navigate to a specific organism or assembly — just start typing the name or accession.</li>
            <li>Selections made in Browse &amp; Select carry over between the Organism, Taxon, and Tree tabs — you can refine your selection without losing previous choices.</li>
            <li>On a group page, deselecting individual organisms before searching lets you exclude organisms you don't need without leaving the group context.</li>
            <li>Click an organism card (not the checkbox) to go directly to that organism's page for single-organism searches and assembly details.</li>
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
