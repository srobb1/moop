<?php
/**
 * DATA EXPORT TUTORIAL - Content File
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
      <h1 class="fw-bold mb-4"><i class="fa fa-download"></i> Exporting Data</h1>

      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Download and Export Your Results</h3>
          <p class="text-muted mb-4">
            MOOP provides flexible export options to download your results in various formats 
            for use in external tools and further analysis.
          </p>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Available Export Formats</h4>
          <p class="text-muted mb-3">
            Different tools support different export formats:
          </p>
          <ul class="text-muted">
            <li><strong>CSV (Comma-Separated Values):</strong> Import into spreadsheets or databases</li>
            <li><strong>FASTA:</strong> Standard format for sequence data, compatible with most bioinformatics tools</li>
            <li><strong>Excel (.xlsx):</strong> For spreadsheet analysis with formatting</li>
            <li><strong>JSON:</strong> For programmatic access and scripting</li>
            <li><strong>GFF3:</strong> For genomic features and annotations</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">How to Export Results</h4>
          <p class="text-muted mb-3">
            Most MOOP results pages have an Export button or menu:
          </p>
          <ol class="text-muted">
            <li>Click the <strong>Export</strong> or <strong>Download</strong> button in the results section</li>
            <li>Select your desired format from the dropdown menu</li>
            <li>Choose whether to download all results or only selected rows</li>
            <li>Click the format button to download the file</li>
          </ol>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Exporting Sequences</h4>
          <p class="text-muted mb-3">
            When exporting sequence data:
          </p>
          <ul class="text-muted">
            <li><strong>FASTA format</strong> includes sequence headers and sequence data</li>
            <li>You can select the specific sequences you want to export</li>
            <li>FASTA files can be used with BLAST, alignment tools, and other bioinformatics software</li>
            <li>Consider the file size - large exports may take time to download</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Exporting Annotations</h4>
          <p class="text-muted mb-3">
            For annotation data, you can export as:
          </p>
          <ul class="text-muted">
            <li><strong>CSV/Excel:</strong> Easy to open and manipulate in spreadsheet software</li>
            <li><strong>GFF3:</strong> Standard format for genomic feature annotations</li>
            <li>You can filter which annotations to include before export</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Export Tips</h4>
          <ul class="text-muted">
            <li>Start with small exports to test the format before downloading large datasets</li>
            <li>Check file encoding (UTF-8 is standard) before importing to other tools</li>
            <li>CSV files can use different delimiters - verify your tool's import settings</li>
            <li>Keep track of export dates if updating analyses over time</li>
            <li>For large exports, consider exporting in batches rather than all at once</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Using Exported Data</h4>
          <p class="text-muted mb-3">
            Common uses for exported MOOP data:
          </p>
          <ul class="text-muted">
            <li>BLAST searches in other tools (NCBI, local BLAST)</li>
            <li>Phylogenetic analysis (MEGA, RAxML, IQTree)</li>
            <li>Multiple sequence alignment (ClustalW, MUSCLE)</li>
            <li>Statistical analysis (R, Python, Excel)</li>
            <li>Visualization (Jalview, IGV, custom scripts)</li>
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
