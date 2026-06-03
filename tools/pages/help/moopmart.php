<?php
/**
 * MOOPMART HELP - Content File
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
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-shopping-cart me-2"></i>MOOPmart — Gene List Builder</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-0">
            MOOPmart lets you build a custom list of genes or transcripts across one or more assemblies, then enrich it with annotations and export as TSV or FASTA. It is the main tool for bulk data download and cross-assembly comparisons.
          </p>
        </div>
      </div>

      <!-- Step 1 -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">1</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Select organisms</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-2">Choose which organisms and assemblies to include. Each row in the list represents one <strong>organism → assembly → gene set</strong> combination.</p>
          <ul class="text-muted mb-0">
            <li>Click a row to select or deselect it.</li>
            <li>Use <strong>All</strong> / <strong>None</strong> to select or clear everything at once.</li>
            <li>Use the filter box to narrow by organism name, common name, assembly accession, or gene set name.</li>
            <li>Toggle <strong>Details</strong> to show assembly and gene set information per row — the filter still searches hidden detail even when the toggle is off.</li>
          </ul>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">2</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Build your list</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-3">Use one or more of these methods to define the features you want. Methods are combined — results include features that match <em>any</em> active criterion.</p>

          <h6 class="fw-semibold mb-2">By feature ID</h6>
          <p class="text-muted mb-3">Paste a list of gene or transcript IDs — one per line or comma-separated. Exact matches only. Useful when you already have a list of IDs from another tool or publication.</p>

          <h6 class="fw-semibold mb-2">By shared feature name</h6>
          <p class="text-muted mb-3">Enter a gene name (e.g. <code>BRCA1</code>) to find all features across selected assemblies that share that name. Good for cross-assembly comparisons of a known gene.</p>

          <h6 class="fw-semibold mb-2">By annotation description</h6>
          <p class="text-muted mb-3">Partial text match on annotation descriptions. For example, entering <code>kinase</code> finds any feature whose annotation description contains that word — such as <em>serine/threonine-protein kinase</em>. Case-insensitive.</p>

          <h6 class="fw-semibold mb-2">By annotation ID</h6>
          <p class="text-muted mb-3">Find features annotated with a specific database term. Enter the ID directly — for example <code>GO:0006351</code> (transcription by RNA polymerase II) or <code>IPR011009</code> (protein kinase-like domain). The annotation type is detected automatically.</p>

          <h6 class="fw-semibold mb-2">By chromosomal location</h6>
          <p class="text-muted mb-0">Enter a chromosome or scaffold name and optional start/end coordinates to retrieve all features whose coordinates overlap that region. Only available when exactly one assembly is selected in step 1.</p>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">3</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Design your output</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-3">This step is collapsed by default — expand it to change format or columns. The defaults (TSV wide with standard columns) work well for most purposes.</p>

          <h6 class="fw-semibold mb-2">Format</h6>
          <ul class="text-muted mb-3">
            <li><strong>TSV Wide</strong> — one row per feature; all annotation values for a feature are joined with <code>; </code> in a single cell. Best for spreadsheet analysis.</li>
            <li><strong>TSV Long</strong> — one row per annotation; features appear multiple times (once per annotation). Better for filtering in Excel or R.</li>
            <li><strong>FASTA</strong> — sequence output. Choose gene body, transcript (mRNA), CDS (coding sequence), or protein. Header lines include the feature ID and organism.</li>
          </ul>

          <h6 class="fw-semibold mb-2">Columns (TSV only)</h6>
          <p class="text-muted mb-0">Check or uncheck which columns to include — feature ID, organism, assembly, gene set, feature name, description, annotation type, annotation ID, annotation description, and more.</p>
        </div>
      </div>

      <!-- Step 4 -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">4</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Preview &amp; Download</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-2"><strong>Preview</strong> fetches the first ~100 rows so you can check the output before committing to a full download. <strong>Download</strong> runs the full query and saves the file.</p>
          <p class="text-muted mb-0">Large queries (many organisms or broad description searches) may take a few seconds. The result count shown after preview gives you an idea of how large the full download will be.</p>
        </div>
      </div>

      <!-- Tips -->
      <div class="card shadow-sm mb-4 border-0" style="background:#f0f9ff;">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3"><i class="fa fa-lightbulb me-2" style="color:#0891b2;"></i>Tips</h5>
          <ul class="text-muted mb-0">
            <li>Start with <strong>Preview</strong> to verify the query returns what you expect before downloading a large file.</li>
            <li>You can link to MOOPmart from a gene set page — the organism, assembly, and gene set are pre-filled automatically.</li>
            <li>Use <strong>TSV Long</strong> format if you plan to pivot or filter by annotation type in Excel.</li>
            <li>FASTA output is ready to load directly into BLAST, MUSCLE, or other bioinformatics tools.</li>
            <li>To open a TSV in Excel: use <em>File → Open</em> and select the downloaded file — Excel parses tab-separated files automatically.</li>
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
