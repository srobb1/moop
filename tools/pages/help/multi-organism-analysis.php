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
  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h1 class="fw-bold mb-4"><i class="fa fa-project-diagram"></i> Multi-Organism Analysis</h1>

      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Comparing Data Across Organisms</h3>
          <p class="text-muted mb-4">
            One of MOOP's powerful features is the ability to simultaneously analyze and compare data across multiple organisms. 
            This guide explains how to perform multi-organism analysis.
          </p>

          <h4 class="fw-semibold text-dark mt-4 mb-2">When to Use Multi-Organism Analysis</h4>
          <p class="text-muted mb-3">
            Multi-organism analysis is useful for:
          </p>
          <ul class="text-muted">
            <li>Finding conserved features across species</li>
            <li>Comparing gene content between organisms</li>
            <li>Identifying species-specific sequences</li>
            <li>Studying evolutionary relationships</li>
            <li>Building comprehensive reference datasets</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Step 1: Select Multiple Organisms</h4>
          <p class="text-muted mb-3">
            Start by selecting the organisms you want to analyze together:
          </p>
          <ul class="text-muted">
            <li>Use the Tree Select view for maximum flexibility</li>
            <li>Click organism nodes to add them to your selection</li>
            <li>You can select organisms from different groups and branches</li>
            <li>The sidebar shows your complete selection</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Step 2: Choose Your Tool</h4>
          <p class="text-muted mb-3">
            Different tools support multi-organism analysis:
          </p>
          <ul class="text-muted">
            <li><strong>Multi-Organism Search:</strong> Search across all selected organisms simultaneously</li>
            <li><strong>Comparative Registry:</strong> View and compare organism metadata side-by-side</li>
            <li><strong>Sequence Retrieval:</strong> Retrieve sequences from multiple organisms</li>
            <li><strong>BLAST Search:</strong> Search your sequence against multiple organism databases</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Step 3: Analyze Results</h4>
          <p class="text-muted mb-3">
            Results from multi-organism tools show data organized by organism:
          </p>
          <ul class="text-muted">
            <li>Results are organized in tables with organism columns</li>
            <li>You can sort and filter by any column</li>
            <li>Color coding helps distinguish organisms visually</li>
            <li>Export options let you download results for further analysis</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Tips for Multi-Organism Analysis</h4>
          <ul class="text-muted">
            <li><strong>Start small:</strong> Begin with 2-3 organisms before scaling up</li>
            <li><strong>Use filtering:</strong> Apply filters to focus on relevant results</li>
            <li><strong>Compare side-by-side:</strong> Use the table view to easily spot differences</li>
            <li><strong>Export for external tools:</strong> Download results for phylogenetic or statistical analysis</li>
            <li><strong>Use organism groups:</strong> Pre-defined groups are often good starting points</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Performance Considerations</h4>
          <p class="text-muted">
            Analyzing many organisms or large datasets may take longer. To optimize performance:
          </p>
          <ul class="text-muted">
            <li>Select only the organisms you need</li>
            <li>Use specific search terms rather than broad queries</li>
            <li>Apply filters early to reduce result sets</li>
            <li>Consider downloading large results for local analysis</li>
          </ul>
        </div>
      </div>

      <div class="mb-4">
        <a href="help.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-arrow-left"></i> Back to Help
        </a>
      </div>
    </div>
  </div>
</div>
