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
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">

      <div class="card shadow-sm mb-4">
        <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-search me-2"></i>Search &amp; Filter</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-0">
            MOOP has two search interfaces: a quick search bar built into every organism, assembly, and gene set page, and the full <strong>Annotation Search</strong> tool for multi-organism queries with fine-grained filtering.
          </p>
        </div>
      </div>

      <!-- In-page search -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">In-Page Search (Organism / Assembly / Gene Set Pages)</h5>
          <p class="text-muted mb-2">Every organism, assembly, and gene set page has a search box at the top that is pre-scoped to that context. Type at least 2 characters in one word to find genes and annotations within that scope.</p>
          <p class="text-muted mb-0">Results appear in a table below. Click any row to open the gene's full detail page.</p>
        </div>
      </div>

      <!-- Annotation Search -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Annotation Search — Step-by-Step</h5>
          <p class="text-muted mb-3">The <strong>Annotation Search</strong> tool (accessible from the Tool Box on any page) supports multi-organism queries and walks you through four steps:</p>

          <div class="d-flex align-items-start mb-3">
            <span class="step-badge me-3 flex-shrink-0">1</span>
            <div>
              <strong>Enter a keyword or ID</strong>
              <p class="text-muted mb-0 mt-1">Search by gene name, annotation keyword, GO term (e.g. <code>GO:0006351</code>), database ID (e.g. <code>PF00001</code>), or free text. Use <code>"quotes"</code> to match words as a phrase, in that order.</p>
            </div>
          </div>

          <div class="d-flex align-items-start mb-3">
            <span class="step-badge me-3 flex-shrink-0">2</span>
            <div>
              <strong>Limit to specific organisms</strong>
              <p class="text-muted mb-0 mt-1">Each row in the list represents one <strong>organism → assembly → gene set</strong> combination. Click rows to select or deselect. Use the filter box to narrow by name, accession, or gene set. You must select at least one — searching every organism at once is the slowest thing the site can do.</p>
            </div>
          </div>

          <div class="d-flex align-items-start mb-3">
            <span class="step-badge me-3 flex-shrink-0">3</span>
            <div>
              <strong>Select annotation types to search</strong>
              <p class="text-muted mb-0 mt-1">Choose which annotation categories to include — Gene Ontology, Domains, Gene Families, Homologs, Orthologs, RBBH Homolog, AI Annotations. Narrowing pays off: results are capped at 2,500 per organism, so limiting to the category you actually want surfaces more of it. <code>kinase</code> across all types returns 61 Gene Ontology hits; limited to Gene Ontology, 183.</p>
            </div>
          </div>

          <div class="d-flex align-items-start">
            <span class="step-badge me-3 flex-shrink-0">4</span>
            <div>
              <strong>Search</strong>
              <p class="text-muted mb-0 mt-1">Click the Search button. Results are returned per organism and ranked by relevance.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Results -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Understanding Results</h5>

          <h6 class="fw-semibold mb-2">Feature Count vs. Annotation Match Count</h6>
          <p class="text-muted mb-3">Each organism's results show two counters: <strong>Features</strong> (unique genes/sequences matched) and <strong>Annotation Matches</strong> (total annotations matched). A single gene with three matching annotations counts as 1 feature and 3 annotation matches.</p>

          <h6 class="fw-semibold mb-2">Ranking</h6>
          <p class="text-muted mb-3">Genes whose <strong>name</strong> contains your term come first, then annotations containing it <strong>literally</strong>, then the rest by text-match score.</p>

          <h6 class="fw-semibold mb-2">How words are matched</h6>
          <ul class="text-muted mb-3">
            <li>Type the <strong>start</strong> of a word — <code>wnt</code> finds <code>wnt8b</code>;
                <code>inase</code> will not find <code>kinase</code>.</li>
            <li>Endings are handled — <code>proteins</code> finds <code>protein</code>.</li>
            <li>Far too many hits? <strong>Type the whole word.</strong> Partial words match much more.</li>
          </ul>

          <h6 class="fw-semibold mb-2">Result limit</h6>
          <p class="text-muted mb-3">MOOP returns a maximum of 2,500 results per organism. If you receive exactly 2,500, try narrowing your query with more specific terms or fewer organisms.</p>

          <h6 class="fw-semibold mb-2">Simple View vs. Expanded View</h6>
          <ul class="text-muted mb-3">
            <li><strong>Simple View</strong> (default) — one row per feature; shows the top matching annotation. Fast to scan.</li>
            <li><strong>Expanded View</strong> — one row per annotation match, with your term highlighted in <span style="background-color:#fff3cd;font-weight:bold;">amber</span> and word-ending variants in <span style="background-color:#cff4fc;font-weight:bold;">blue</span>. Use when you need to understand exactly why a gene matched.</li>
          </ul>
          <p class="text-muted mb-3">Toggle between views with the <strong>Expand All Matches</strong> button above each organism's result table.</p>

          <h6 class="fw-semibold mb-2">Table controls</h6>
          <ul class="text-muted mb-0">
            <li>Click any column header to sort by that column</li>
            <li>Use the per-column filter boxes to narrow results further</li>
            <li>Click <strong>Column Visibility</strong> to show or hide specific columns</li>
            <li>Select rows with checkboxes, then export with <strong>Copy</strong>, <strong>CSV</strong>, <strong>Excel</strong>, <strong>PDF</strong>, or <strong>Print</strong></li>
            <li>Click any gene link to open its full detail page</li>
          </ul>
        </div>
      </div>

      <!-- Tips -->
      <div class="card shadow-sm mb-4 border-0" style="background:#f0f9ff;">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3"><i class="fa fa-lightbulb me-2" style="color:#0891b2;"></i>Tips</h5>
          <ul class="text-muted mb-0">
            <li>Use <code>"zinc finger"</code> in quotes to require those words together, in that order, rather than anywhere in the same record.</li>
            <li>Deselect annotation types you don't need — with a 2,500 cap, narrowing surfaces results that would otherwise be crowded out.</li>
            <li>If you need to download many genes with their annotations, use <strong>MOOPmart</strong> instead of exporting search results — it gives you more control over output format and columns.</li>
            <li>The in-page search on organism/assembly/gene set pages is the quickest way to look up a specific gene ID.</li>
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
