<?php
/**
 * SEQUENCE RETRIEVAL HELP - Content File
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
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-dna me-2"></i>Sequence Retrieval</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-0">
            Sequence Retrieval lets you look up sequences by feature ID and download them in multiple formats — genomic, transcript, CDS, and protein. Enter one or more IDs and retrieve all available sequence types at once.
          </p>
        </div>
      </div>

      <!-- Step 1 -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">1</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Select organism and assembly</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-2">Use the organism list to choose which assembly to retrieve sequences from. Filter by group, organism name, or assembly accession using the filter box.</p>
          <p class="text-muted mb-0">The <strong>Currently selected</strong> panel confirms your active assembly.</p>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">2</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Enter feature or gene IDs</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-3">Paste IDs into the text box — one per line or comma-separated. You can mix gene IDs and transcript IDs in the same list.</p>

          <h6 class="fw-semibold mb-2">Parent ID auto-expansion</h6>
          <p class="text-muted mb-3">
            If you enter a <strong>gene ID</strong> (the parent), the tool automatically expands it to include all child transcript IDs. For example, entering <code>g24397</code> retrieves sequences for <code>g24397.t1</code>, <code>g24397.t2</code>, etc. To retrieve only a specific transcript, enter its ID directly.
          </p>

          <h6 class="fw-semibold mb-2">Subsequence ranges</h6>
          <p class="text-muted mb-2">Append a coordinate range to extract only part of a sequence. All four formats are equivalent:</p>
          <div class="bg-light p-3 rounded mb-3">
            <code>g24397.t1:1-500</code><br>
            <code>g24397.t1:1..500</code><br>
            <code>g24397.t1 1-500</code><br>
            <code>g24397.t1 1..500</code>
          </div>
          <p class="text-muted mb-3">Coordinates are 1-based. Start must be less than or equal to end.</p>

          <div class="alert alert-info py-2 mb-0">
            <i class="fa fa-info-circle me-1"></i>
            <strong>Note:</strong> If you enter ranged child IDs (e.g. <code>g24397.t1:1-500</code>), the parent gene will <em>not</em> be auto-expanded — the tool assumes you are making a deliberate targeted request.
          </div>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
          <span class="step-badge me-2">3</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Retrieve sequences</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-3">Click <strong>Retrieve Sequences</strong>. Results appear below for each feature ID, grouped by sequence type:</p>
          <ul class="text-muted mb-3">
            <li><strong>Genomic</strong> — the chromosomal/scaffold sequence spanning the feature, including introns</li>
            <li><strong>Transcript</strong> — the spliced mRNA sequence (exons only)</li>
            <li><strong>CDS</strong> — the coding sequence from start codon to stop codon</li>
            <li><strong>Protein</strong> — the translated amino acid sequence</li>
          </ul>
          <p class="text-muted mb-2">Not all sequence types are available for every feature — availability depends on what BLAST databases have been indexed for the assembly.</p>
          <p class="text-muted mb-0">Use the <strong>Copy</strong> button next to each sequence to copy it to your clipboard, or <strong>Download</strong> to save as a FASTA file.</p>
        </div>
      </div>

      <!-- ID display panel -->
      <div class="card shadow-sm mb-4">
        <div class="card-header py-2" style="background:#f8f9fa;">
          <span class="fw-semibold text-dark"><i class="fa fa-list-check me-2"></i>IDs to Search panel</span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted mb-2">After submitting, the <strong>IDs to Search</strong> panel shows which IDs were found (green) and which were not found (red) in the selected assembly's databases.</p>
          <ul class="text-muted mb-0">
            <li><span class="badge" style="background:#d4edda; color:#155724;">green</span> — ID was found and sequences are available</li>
            <li><span class="badge" style="background:#f8d7da; color:#721c24;">red</span> — ID was not found — check spelling or try a different assembly</li>
          </ul>
        </div>
      </div>

      <!-- Tips -->
      <div class="card shadow-sm mb-4 border-0" style="background:#f0f9ff;">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3"><i class="fa fa-lightbulb me-2" style="color:#0891b2;"></i>Tips</h5>
          <ul class="text-muted mb-0">
            <li>Use gene IDs (parents) to get all transcripts at once; use transcript IDs to target a specific isoform.</li>
            <li>Coordinate ranges are useful for extracting promoter regions — e.g. if a gene starts at position 50,000, request <code>scaffold1 49500-50000</code> to get the 500 bp upstream.</li>
            <li>You can also reach this tool from any feature detail page — the organism and assembly are pre-filled.</li>
            <li>If a sequence type is missing (e.g. no Protein shown), it means the protein BLAST database for that gene set has not been built yet — contact the site administrator.</li>
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
