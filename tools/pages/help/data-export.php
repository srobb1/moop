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
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">

      <div class="card shadow-sm mb-4">
        <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-download me-2"></i>Exporting Data</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-0">
            MOOP provides several ways to get data out, depending on what you need. The right tool depends on whether you want a bulk gene list, specific sequences, genome files, or a single feature's annotations.
          </p>
        </div>
      </div>

      <!-- MOOPmart -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Bulk Gene Lists and Annotations — MOOPmart</h5>
          <p class="text-muted mb-2"><strong>MOOPmart</strong> is the primary tool for bulk export. It lets you build a custom gene list across one or more assemblies by ID, name, annotation description, GO term, or genomic location — then export as:</p>
          <ul class="text-muted mb-3">
            <li><strong>TSV (Wide)</strong> — one row per gene, all annotations joined in a single cell. Easy to open in Excel.</li>
            <li><strong>TSV (Long)</strong> — one row per annotation, genes repeated. Better for filtering by annotation type in R or Excel.</li>
            <li><strong>FASTA</strong> — gene body, transcript, CDS, or protein sequences in standard FASTA format. Ready to load into BLAST, MUSCLE, etc.</li>
          </ul>
          <p class="text-muted mb-0">A <strong>Preview</strong> button lets you check the first ~100 rows before running a full download. Access MOOPmart from the Tool Box on any organism, assembly, or gene set page, or from the tip on any gene set page.</p>
        </div>
      </div>

      <!-- Retrieve Sequences -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Specific Sequences by ID — Retrieve Sequences</h5>
          <p class="text-muted mb-2">When you have a list of specific gene or transcript IDs and want their sequences, use <strong>Retrieve Sequences</strong>. It returns all available sequence types for each ID:</p>
          <ul class="text-muted mb-2">
            <li>Genomic (chromosomal sequence spanning the feature, including introns)</li>
            <li>Transcript (spliced mRNA)</li>
            <li>CDS (coding sequence, start to stop)</li>
            <li>Protein (translated amino acid sequence)</li>
          </ul>
          <p class="text-muted mb-0">You can also request subsequences using coordinate ranges — e.g. <code>g24397.t1:1-500</code>. Parent gene IDs are auto-expanded to all child transcripts.</p>
        </div>
      </div>

      <!-- Downloads page -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Genome Files — Downloads</h5>
          <p class="text-muted mb-2">The <strong>Downloads</strong> page lets you browse and batch-download genome files organized by organism → assembly → gene set. Available files typically include genome FASTA, GFF annotation files, and indexed sequence databases.</p>
          <p class="text-muted mb-0">Check the files you want at any level (individual files, a whole gene set, or an entire assembly) and click <strong>Download Selected</strong> to get a zip of everything at once.</p>
        </div>
      </div>

      <!-- Search results export -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Exporting Search Results</h5>
          <p class="text-muted mb-2">Result tables from Annotation Search, in-page searches, and BLAST have built-in export buttons below each table:</p>
          <ul class="text-muted mb-0">
            <li><strong>Copy</strong> — copy selected rows to clipboard</li>
            <li><strong>CSV</strong> — comma-separated values; import into any spreadsheet or database</li>
            <li><strong>Excel</strong> — download as an Excel workbook</li>
            <li><strong>PDF</strong> — formatted PDF of the result table</li>
            <li><strong>Print</strong> — send to printer</li>
          </ul>
        </div>
      </div>

      <!-- Feature page download -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">All Annotations for One Gene — Feature Detail Page</h5>
          <p class="text-muted mb-2">On any gene detail page (the parent page), the Annotations section has a <strong>Download All Annotations</strong> button. This downloads a single CSV containing every annotation for the gene and all its children (mRNAs, CDS, proteins, etc.) — no need to export each annotation type separately.</p>
          <p class="text-muted mb-2">The CSV includes: Feature Uniquename, Feature Type, Annotation Type, Annotation ID, Description, Score, and Source.</p>
          <p class="text-muted mb-0">The same data is available programmatically via <code>/api/download_annotations.php?organism=…&uniquename=…</code>.</p>
        </div>
      </div>

      <!-- Tips -->
      <div class="card shadow-sm mb-4 border-0" style="background:#f0f9ff;">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3"><i class="fa fa-lightbulb me-2" style="color:#0891b2;"></i>Choosing the Right Export Method</h5>
          <div class="table-responsive">
            <table class="table table-sm table-bordered text-muted mb-0">
              <thead class="table-light">
                <tr><th>I want to…</th><th>Use</th></tr>
              </thead>
              <tbody>
                <tr><td>Download genes matching a keyword with their annotations</td><td>MOOPmart — description search</td></tr>
                <tr><td>Download all genes in a gene set</td><td>MOOPmart — select the gene set, leave filters blank</td></tr>
                <tr><td>Get sequences for a specific list of gene IDs</td><td>Retrieve Sequences</td></tr>
                <tr><td>Download the genome FASTA or GFF file</td><td>Downloads page</td></tr>
                <tr><td>Export my search result table</td><td>CSV / Excel buttons below the result table</td></tr>
                <tr><td>Get all annotations for one gene</td><td>Download All Annotations on the gene's detail page</td></tr>
              </tbody>
            </table>
          </div>
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
