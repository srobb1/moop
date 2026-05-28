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
      <div class="feature-header gene-set-header-custom shadow">
        <h1 class="mb-0 gene-set-heading">
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
        <?php if (!empty($gene_set_meta['source'])): ?>
        <div class="feature-info-item">
          <strong>Source:</strong> <span class="feature-value"><?= htmlspecialchars($gene_set_meta['source']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($gene_set_meta['date_added'])): ?>
        <div class="feature-info-item">
          <strong>Date added:</strong> <span class="feature-value"><?= htmlspecialchars($gene_set_meta['date_added']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($gene_set_meta['note'])): ?>
        <div class="feature-info-item">
          <strong>Note:</strong> <span class="feature-value"><?= htmlspecialchars($gene_set_meta['note']) ?></span>
        </div>
        <?php endif; ?>
        <?php
        // Check for GFF
        $gs_dir = $config->getPath('organism_data') . "/$organism_name/$genome_accession/$gene_set_name";
        $has_gff = file_exists("$gs_dir/genomic.gff") && filesize("$gs_dir/genomic.gff") > 0;
        $has_downloads = !empty($fasta_files) || $has_gff;
        ?>
        <?php if ($has_downloads): ?>
        <div class="feature-info-item" style="border-top: 1px solid rgba(255,255,255,0.25); margin-top: 0.5rem; padding-top: 1rem;">
          <div class="chip-container">
            <?php foreach ($fasta_files as $f):
                $colorInfo = getColorClassOrStyle($f['color'] ?? '');
            ?>
            <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&type=<?= urlencode($f['seq_type']) ?>"
               class="btn <?= $colorInfo['class'] ?> fw-semibold text-white"
               style="border-radius: 16px; font-size: 0.9rem; padding: 6px 14px; <?= $colorInfo['style'] ?>"
               download>
              <i class="fa fa-download me-1"></i><?= htmlspecialchars($f['label']) ?>
            </a>
            <?php endforeach; ?>
            <?php if ($has_gff): ?>
            <a href="/<?= $site ?>/api/download_file.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&filename=genomic.gff"
               class="btn fw-semibold text-white"
               style="border-radius: 16px; font-size: 0.9rem; padding: 6px 14px; background-color: #475569; border-color: #475569;"
               download>
              <i class="fa fa-download me-1"></i>GFF3
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
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


</div>
