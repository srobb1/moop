<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#e11d48;">
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><?= htmlspecialchars($gene_set_name) ?></span>
          <span class="badge bg-white text-gene-set" style="font-size:0.65em; opacity:0.9;">search limited to this gene set</span>
        </div>
        <div class="card-body bg-search-light">
          <div class="mb-2 fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-search me-1"></i> Search Gene IDs and Annotations <i class="fa fa-info-circle search-instructions-trigger" style="cursor:pointer; margin-left:0.4rem; font-size:0.85em;" data-help-type="basic"></i></div>
          <form id="geneSetSearchForm">
            <div class="row align-items-center">
              <div class="col">
                <div class="d-flex gap-2 align-items-center">
                  <input type="text" class="form-control moop-input" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
                  <button type="submit" class="btn btn-icon btn-search" id="searchBtn" title="Search" data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Tools Column -->
    <div class="col-lg-4">
      <?php
      $context = createToolContext('gene_set', [
          'organism'     => $organism_name,
          'assembly'     => $genome_accession,
          'gene_set'     => $gene_set_name,
          'display_name' => $gene_set_name,
      ]);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-search-results">
        <span class="fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-list me-1"></i> Search Results <i class="fa fa-info-circle search-results-help-trigger" style="cursor:pointer; margin-left:0.4rem; font-size:0.85em;" data-help-type="results"></i></span>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

  <style>
    @media (min-width: 768px) {
      .gene-set-stats-panel {
        border-top: none !important;
        border-left: 1px solid #dee2e6;
      }
    }
  </style>

  <!-- Gene Set Header -->
  <div class="row mb-4" id="geneSetHeader">
    <div class="col-12">
      <div class="feature-header gene-set-header-custom shadow">
        <h1><?= htmlspecialchars($gene_set_name) ?> <span class="badge bg-white text-gene-set ms-1" style="font-size:0.7em; vertical-align:middle; opacity:0.85;">Gene Set</span></h1>
        <div class="feature-overview-body" style="padding:0;">
          <div class="d-flex flex-column flex-md-row align-items-stretch">

            <!-- Info -->
            <div class="flex-grow-1" style="padding:0.5rem 1.1rem 0.6rem;">
              <?php if (!empty($gene_set_info['gene_set_description'])): ?>
              <p class="text-muted small mb-2"><?= htmlspecialchars($gene_set_info['gene_set_description']) ?></p>
              <?php endif; ?>
              <dl class="feature-info-grid mb-0">
                <dt>Assembly</dt>
                <dd><a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>"><?= htmlspecialchars($genome_name ?: $genome_accession) ?><?php if ($genome_name && $genome_name !== $genome_accession): ?> (<?= htmlspecialchars($genome_accession) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                <dt>Organism</dt>
                <dd><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>"><em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em><?php if (!empty($organism_info['common_name'])): ?> (<?= htmlspecialchars($organism_info['common_name']) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                <?php if (!empty($gene_set_meta['source'])): ?>
                <dt>Source</dt>
                <dd><?= htmlspecialchars($gene_set_meta['source']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($gene_set_meta['date_added'])): ?>
                <dt>Date added</dt>
                <dd><?= htmlspecialchars($gene_set_meta['date_added']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($gene_set_meta['note'])): ?>
                <dt>Note</dt>
                <dd><?= htmlspecialchars($gene_set_meta['note']) ?></dd>
                <?php endif; ?>
              </dl>
              <?php
              $gs_dir = $config->getPath('organism_data') . "/$organism_name/$genome_accession/$gene_set_name";
              $gff_name = genes_gff_filename();
              $has_gff = file_exists("$gs_dir/$gff_name") && filesize("$gs_dir/$gff_name") > 0;
              $has_downloads = !empty($fasta_files) || $has_gff;
              ?>
              <?php if ($has_downloads): ?>
              <div class="mt-2 pt-2 d-flex flex-wrap gap-2" style="border-top: 1px solid #dee2e6;">
                <?php foreach ($fasta_files as $f):
                    $colorInfo = getColorClassOrStyle($f['color'] ?? '');
                ?>
                <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&type=<?= urlencode($f['seq_type']) ?>"
                   class="btn btn-sm <?= $colorInfo['class'] ?> fw-semibold text-white"
                   style="border-radius: 16px; <?= $colorInfo['style'] ?>"
                   download>
                  <i class="fa fa-download me-1"></i><?= htmlspecialchars($f['label']) ?>
                </a>
                <?php endforeach; ?>
                <?php if ($has_gff): ?>
                <a href="/<?= $site ?>/api/download_file.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&filename=<?= urlencode(genes_gff_filename()) ?>"
                   class="btn btn-sm fw-semibold text-white"
                   style="border-radius: 16px; background-color: #475569; border-color: #475569;"
                   download>
                  <i class="fa fa-download me-1"></i>GFF3
                </a>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Gene / Transcript counts -->
            <div class="gene-set-stats-panel flex-shrink-0 d-flex border-top" style="min-width:200px;">
              <div class="flex-fill d-flex flex-column align-items-center justify-content-center p-3 border-end">
                <div class="fw-bold feature-color-gene mb-1" style="font-size:1.5rem; line-height:1;"><?= number_format($gene_set_info['gene_count'] ?? 0) ?></div>
                <div class="text-muted fw-semibold" style="font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase;">Genes</div>
              </div>
              <div class="flex-fill d-flex flex-column align-items-center justify-content-center p-3">
                <div class="fw-bold feature-color-mrna mb-1" style="font-size:1.5rem; line-height:1;"><?= number_format($gene_set_info['mrna_count'] ?? 0) ?></div>
                <div class="text-muted fw-semibold" style="font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase;">Transcripts</div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Annotation Summary -->
  <?php if (!empty($annot_type_totals)): ?>
  <div class="row mb-5" id="annotationSummary">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center" style="background-color:#e11d48;">
          <i class="fas fa-tag me-2 text-white"></i>
          <span class="text-uppercase fw-semibold text-white" style="letter-spacing:0.1em; font-size:0.8rem;">Annotation Summary</span>
        </div>
        <div class="card-body p-0">
          <div class="d-flex flex-wrap">
            <?php foreach ($annot_type_totals as $type => $total): ?>
            <div class="text-center p-3" style="min-width:120px; border-right:1px solid #dee2e6; border-bottom:1px solid #dee2e6;">
              <div class="fw-bold text-secondary" style="font-size:1.2rem; line-height:1;"><?= number_format($total) ?></div>
              <div class="text-muted fw-semibold mt-1" style="font-size:0.7rem; letter-spacing:0.06em; text-transform:uppercase;"><?= htmlspecialchars($type) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Moopmart tip -->
  <div class="row mb-5">
    <div class="col-12">
      <p class="text-muted small mb-0">
        <i class="fas fa-lightbulb me-1" style="color:#0891b2;"></i>
        <strong>Tip:</strong> To download some or all of the annotations for this gene set, visit
        <a href="/<?= $site ?>/tools/moopmart.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>">MOOPmart</a>.
        Build a gene list from this gene set and then select the annotations you want to bulk download.
      </p>
    </div>
  </div>


</div>
