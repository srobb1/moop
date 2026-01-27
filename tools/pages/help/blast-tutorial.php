<?php
/**
 * BLAST TUTORIAL - Content File
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
      <h1 class="fw-bold mb-4"><i class="fa fa-exchange-alt"></i> BLAST Search</h1>

      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Sequence Comparison with BLAST</h3>
          <p class="text-muted mb-4">
            BLAST (Basic Local Alignment Search Tool) is a powerful tool for comparing your sequence of interest 
            against genomes and sequences in the MOOP database.
          </p>

          <h4 class="fw-semibold text-dark mt-4 mb-2">What is BLAST?</h4>
          <p class="text-muted mb-3">
            BLAST finds regions of similarity between sequences. It's useful for:
          </p>
          <ul class="text-muted">
            <li>Finding homologous genes in other organisms</li>
            <li>Identifying conserved sequences</li>
            <li>Detecting sequence variations or mutations</li>
            <li>Annotating unknown sequences</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Getting Started with BLAST</h4>
          <ol class="text-muted">
            <li><strong>Select organisms:</strong> Choose which organisms to search against</li>
            <li><strong>Choose BLAST type:</strong> Select nucleotide (DNA) or protein search</li>
            <li><strong>Enter your sequence:</strong> Paste your sequence in FASTA format</li>
            <li><strong>Set parameters:</strong> Adjust E-value, word size, and other options</li>
            <li><strong>Run search:</strong> Click the search button to submit your query</li>
          </ol>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Understanding BLAST Results</h4>
          <p class="text-muted mb-3">
            BLAST results are displayed in several formats:
          </p>
          <ul class="text-muted">
            <li><strong>Summary table:</strong> Overview of all matches with scores and statistics</li>
            <li><strong>Alignments:</strong> Detailed alignment views showing matches</li>
            <li><strong>Graphics:</strong> Visual representation of hit distribution</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Key BLAST Parameters</h4>
          <div class="bg-light p-3 rounded">
            <ul class="text-muted mb-0">
              <li><strong>E-value:</strong> Statistical significance threshold (lower = more significant). Default 0.05</li>
              <li><strong>Word size:</strong> Length of exact matches to trigger extension. Affects speed and sensitivity</li>
              <li><strong>Max matches:</strong> Maximum number of results to return</li>
              <li><strong>Gap penalties:</strong> Cost of opening and extending gaps in alignments</li>
            </ul>
          </div>

          <h4 class="fw-semibold text-dark mt-4 mb-2">BLAST Tips</h4>
          <ul class="text-muted">
            <li>Make sure your sequence is in the correct format (FASTA)</li>
            <li>Use appropriate BLAST type (nucleotide vs. protein)</li>
            <li>Lower E-values give more stringent results</li>
            <li>For quick screening, use high E-value; for careful analysis, use low E-value</li>
            <li>Check sequence length - very short sequences may have many false positives</li>
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
