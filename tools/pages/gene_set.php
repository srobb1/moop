<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center"><?= htmlspecialchars($gene_set_name) ?></h1>
        </div>
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations <i class="fa fa-info-circle search-instructions-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.8em;" data-help-type="basic"></i></h4>
          <form id="geneSetSearchForm">
            <div class="row align-items-center">
              <div class="col">
                <div class="d-flex gap-2 align-items-center">
                  <input type="text" class="form-control" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
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
      <div class="card-header bg-search-results text-white">
        <h4 class="mb-0"><i class="fa fa-list"></i> Search Results <i class="fa fa-info-circle search-results-help-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.9em;" data-help-type="results"></i></h4>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

  <!-- Gene Set Header -->
  <div class="row mb-4" id="geneSetHeader">
    <div class="col-12">
      <div class="feature-header assembly-header-custom shadow">
        <h1 class="mb-0 assembly-heading">
          <?= htmlspecialchars($gene_set_name) ?>
          <span class="badge bg-gene-set ms-2">Gene Set</span>
        </h1>
        <?php if (!empty($gene_set_info['gene_set_description'])): ?>
        <div class="feature-info-item">
          <span class="text-light-muted"><?= htmlspecialchars($gene_set_info['gene_set_description']) ?></span>
        </div>
        <?php endif; ?>
        <div class="feature-info-item">
          <strong>Assembly:</strong>
          <span class="feature-value">
            <a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>" class="link-light-bordered">
              <?= htmlspecialchars($genome_name ?: $genome_accession) ?><?php if ($genome_name && $genome_name !== $genome_accession): ?> (<?= htmlspecialchars($genome_accession) ?>)<?php endif; ?>
            </a>
          </span>
        </div>
        <div class="feature-info-item">
          <strong>Organism:</strong>
          <span class="feature-value">
            <a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>" class="link-light-bordered">
              <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>
            </a>
            <?php if (!empty($organism_info['common_name'])): ?>
              (<?= htmlspecialchars($organism_info['common_name']) ?>)
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Gene and mRNA Counts -->
  <div class="row g-4 mb-5" id="geneSetStats">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h2 class="fw-bold feature-color-gene mb-3">Genes</h2>
            <h2 class="fw-bold feature-color-gene"><?= number_format($gene_set_info['gene_count'] ?? 0) ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h2 class="fw-bold feature-color-mrna mb-3">Transcripts</h2>
            <h2 class="fw-bold feature-color-mrna"><?= number_format($gene_set_info['mrna_count'] ?? 0) ?></h2>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Downloads Section -->
  <?php if (!empty($fasta_files)): ?>
  <div class="row mb-4" id="geneSetDownloads">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4"><i class="fa fa-download"></i> Download Sequence Files</h3>
          <div class="row g-3">
            <?php foreach ($fasta_files as $file_info):
              $colorInfo = getColorClassOrStyle($file_info['color'] ?? '');
            ?>
              <div class="col-6 col-md-3">
                <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&genome_directory=<?= urlencode($genome_name ?: $genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&type=<?= urlencode($file_info['seq_type'] ?? '') ?>"
                   class="btn <?= $colorInfo['class'] ?> w-100 py-4 fw-bold text-white"
                   style="border-radius: 0.75rem; font-size: 1rem; <?= $colorInfo['style'] ?>"
                   download>
                  <i class="fa fa-download me-2"></i><?= htmlspecialchars($file_info['label']) ?>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
