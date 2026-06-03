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
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">

      <div class="card shadow-sm mb-4">
        <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-exchange-alt me-2"></i>BLAST Search</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-0">
            BLAST (Basic Local Alignment Search Tool) compares a query sequence against genome assemblies in MOOP to find regions of similarity. Use it to find homologs, verify annotations, or identify where an unknown sequence originates.
          </p>
        </div>
      </div>

      <!-- Step-by-step -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">1</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Paste a sequence</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-2">Paste your sequence directly into the text box. Both formats are accepted:</p>
          <ul class="text-muted mb-3">
            <li><strong>FASTA format</strong> — a header line starting with <code>&gt;</code> followed by the sequence on the next line(s)</li>
            <li><strong>Plain sequence</strong> — just the nucleotide or amino acid letters, no header needed</li>
          </ul>
          <p class="text-muted mb-0">Use the <strong>Sample Protein</strong> or <strong>Sample Nucleotide</strong> buttons to load a pre-filled example sequence if you want to try the tool before using your own data.</p>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">2</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Select a BLAST program</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-3">Choose the program that matches your query type and what you want to search against:</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered text-muted mb-0">
              <thead class="table-light">
                <tr><th>Program</th><th>Query</th><th>Database</th><th>When to use</th></tr>
              </thead>
              <tbody>
                <tr><td><strong>BLASTn</strong></td><td>DNA</td><td>DNA</td><td>Find similar nucleotide sequences; good for highly conserved or identical regions</td></tr>
                <tr><td><strong>BLASTp</strong></td><td>Protein</td><td>Protein</td><td>Compare protein sequences directly; best for finding functional homologs</td></tr>
                <tr><td><strong>BLASTx</strong></td><td>DNA</td><td>Protein</td><td>Translate a DNA query in all 6 frames and search protein databases; useful for unannotated sequences</td></tr>
                <tr><td><strong>tBLASTn</strong></td><td>Protein</td><td>DNA</td><td>Search translated genome sequences with a protein query; finds genes not yet annotated</td></tr>
                <tr><td><strong>tBLASTx</strong></td><td>DNA</td><td>DNA</td><td>Both query and database are translated; most sensitive but slowest</td></tr>
              </tbody>
            </table>
          </div>
          <p class="text-muted small mt-2 mb-0">The program you select determines which databases are available in step 3 — protein programs show protein databases, nucleotide programs show nucleotide databases.</p>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">3</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Select organism and database</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-2">Use the organism list to choose which assembly to search against. You can filter by group, organism name, or assembly accession using the filter box.</p>
          <p class="text-muted mb-2">Once an assembly is selected, the available databases appear as buttons — for example <strong>Genome</strong>, <strong>Transcript</strong>, <strong>CDS</strong>, or <strong>Protein</strong>. Select the one that matches your search intent.</p>
          <p class="text-muted mb-0">The <strong>Currently selected</strong> panel shows your active organism and assembly so you always know what you're searching.</p>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">4</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Run BLAST</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-2">Click <strong>Run BLAST</strong>. Results appear below the form and include:</p>
          <ul class="text-muted mb-3">
            <li><strong>Hit table</strong> — ranked list of matches with E-value, identity %, and alignment length</li>
            <li><strong>Visual alignment diagram</strong> — shows where hits fall along your query sequence, color-coded by bit score</li>
            <li><strong>Pairwise alignments</strong> — full alignment detail for each hit</li>
          </ul>
          <p class="text-muted mb-0">Download buttons let you save results as <strong>TXT</strong> (pairwise), <strong>TSV</strong> (tabular), or <strong>XML</strong> for downstream processing.</p>
        </div>
      </div>

      <!-- Advanced Options -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2" style="background:#f8f9fa;">
          <span class="fw-semibold text-dark"><i class="fas fa-sliders-h me-2"></i>Advanced Options</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-3">Expand <strong>Advanced Options</strong> between steps 3 and 4 to fine-tune the search:</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered text-muted mb-0">
              <thead class="table-light">
                <tr><th>Parameter</th><th>What it controls</th><th>Default</th></tr>
              </thead>
              <tbody>
                <tr><td><strong>E-value</strong></td><td>Statistical significance cutoff. Lower = more stringent; only high-confidence matches pass.</td><td>1e-3</td></tr>
                <tr><td><strong>Maximum hits</strong></td><td>How many top hits to return.</td><td>50</td></tr>
                <tr><td><strong>Scoring matrix</strong></td><td>Substitution matrix for protein searches (BLOSUM62 is standard).</td><td>BLOSUM62</td></tr>
                <tr><td><strong>Word size</strong></td><td>Seed length for initial matches. Smaller = more sensitive but slower.</td><td>11 (blastn), 3 (blastp)</td></tr>
                <tr><td><strong>Gap open / extend</strong></td><td>Cost for starting and extending a gap in an alignment.</td><td>Program-specific</td></tr>
                <tr><td><strong>Percent identity</strong></td><td>Minimum identity % threshold; hits below this are discarded.</td><td>No threshold</td></tr>
                <tr><td><strong>Filter low complexity</strong></td><td>Mask repetitive/low-complexity regions before searching.</td><td>Off</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tips -->
      <div class="card shadow-sm mb-4 border-0" style="background:#f0f9ff;">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3"><i class="fa fa-lightbulb me-2" style="color:#0891b2;"></i>Tips</h5>
          <ul class="text-muted mb-0">
            <li>For quick screening use a relaxed E-value (0.1 or 1); for careful homology analysis use 1e-6 or lower.</li>
            <li>Very short sequences (&lt;20 aa / &lt;50 nt) produce many low-confidence hits — filter by E-value or identity.</li>
            <li>BLASTx and tBLASTn cross the nucleotide/protein boundary and are the most useful programs for finding unannotated genes.</li>
            <li>If you get no hits, try relaxing the E-value threshold or switching to a more sensitive program (e.g. BLASTx instead of BLASTn).</li>
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
