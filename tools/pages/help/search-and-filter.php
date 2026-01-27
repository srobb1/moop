<?php
/**
 * SEARCH & FILTER TUTORIAL - Content File
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
      <h1 class="fw-bold mb-4"><i class="fa fa-search"></i> Search & Filter</h1>

      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Finding What You Need</h3>
          <p class="text-muted mb-4">
            MOOP provides powerful search and filtering capabilities to help you find specific sequences and annotations.
          </p>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Basic Search</h4>
          <p class="text-muted mb-3">
            Use the Search tool to find sequences by:
          </p>
          <ul class="text-muted">
            <li>Gene name or ID</li>
            <li>Sequence description</li>
            <li>Annotation text</li>
            <li>Keyword matching</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Advanced Filtering</h4>
          <p class="text-muted mb-3">
            Refine your results with advanced filters:
          </p>
          <ul class="text-muted">
            <li><strong>Annotation Sources:</strong> Click the filter icon to select specific annotation sources to search within</li>
            <li><strong>Annotation Type:</strong> Show only certain annotation categories</li>
            <li><strong>Length:</strong> Filter sequences by length range</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Filtering Examples</h5>
          <p class="text-muted mb-3">
            Here are some common filtering scenarios:
          </p>
          <ul class="text-muted">
            <li><strong>Search for Gene Ontology terms:</strong> Uncheck all annotation sources except "Gene Ontology" or "GO", then search for terms like "kinase" or "DNA binding", or search by GO ID like <code>GO:0016301</code> (kinase activity)</li>
            <li><strong>Find protein domain annotations:</strong> Filter to show only "InterPro", "Pfam", or "SMART" sources, then search for domain names or IDs like <code>PF00001</code> (7 transmembrane receptor)</li>
            <li><strong>Look for sequences of specific length:</strong> Use the length filter to find small regulatory RNAs or large proteins within your search results</li>
            <li><strong>Compare across annotation databases:</strong> Search with different annotation sources selected to see which databases have information about your gene</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Filtering Organisms in Group Searches</h5>
          <p class="text-muted mb-3">
            When searching within a group, you can control which organisms are included:
          </p>
          <ul class="text-muted">
            <li><strong>Selection bars:</strong> Each organism card has a checkbox at the top</li>
            <li><strong>Check/uncheck:</strong> Toggle individual organisms on or off to include/exclude them from the search</li>
            <li><strong>Select All / Deselect All:</strong> Quickly manage all organisms in the group</li>
            <li><strong>Search scope:</strong> Only checked organisms will be searched when you run a query</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Working with Results</h4>
          <p class="text-muted mb-3">
            Once you have search results:
          </p>
          <ul class="text-muted">
            <li><strong>Sort columns:</strong> Click column headers to sort by any field</li>
            <li><strong>Search within results:</strong> Use the search box at the top of the table</li>
            <li><strong>Download results:</strong> Export your results in multiple formats: CSV, Excel (.xls), or FASTA</li>
            <li><strong>View details:</strong> Click on a result row to see full information</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Simple View vs. Expanded View</h5>
          <p class="text-muted mb-3">
            MOOP provides two ways to view your search results:
          </p>
          <ul class="text-muted">
            <li><strong>Simple View:</strong> Shows basic match information with sequence name, description, and top annotation
              <ul>
                <li>Use when you want a quick overview of results</li>
                <li>Easier to scan through many results</li>
                <li>Ideal for identifying which sequences match your search</li>
              </ul>
            </li>
            <li><strong>Expanded View:</strong> Shows ALL matching annotations for each sequence with detailed information
              <ul>
                <li>Use when you need to see exactly where your search terms appeared</li>
                <li>Shows every matching annotation and which search keywords matched</li>
                <li>Useful for understanding why a sequence matched your search</li>
              </ul>
            </li>
          </ul>

          <p class="text-muted">
            Click the <strong>"Expand All Matches"</strong> button to toggle between views. The simple view is shown by default for better performance with large result sets.
          </p>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Exporting Results</h4>
          <p class="text-muted mb-3">
            Save your search results for further analysis:
          </p>
          <ul class="text-muted">
            <li><strong>CSV format:</strong> Import into spreadsheets or databases for analysis</li>
            <li><strong>Excel (.xls):</strong> Open directly in spreadsheet applications with formatting</li>
            <li><strong>FASTA format:</strong> Use in bioinformatics tools like BLAST or sequence alignment software</li>
            <li><strong>Select rows:</strong> Choose specific results to export, or export all results</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Search Tips</h4>
          <ul class="text-muted">
            <li>Use quotation marks for exact matches: <code>"exact phrase"</code></li>
            <li>Search for partial matches by entering part of a word or ID (e.g., <code>gene</code> will find "gene1", "gene2", etc.)</li>
            <li>Combine multiple filters for more precise results</li>
            <li>Download results as Excel, CSV, or FASTA to save your findings</li>
            <li>Remember to deselect organisms you don't need to speed up searches</li>
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
