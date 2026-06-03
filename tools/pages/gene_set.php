<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#e11d48;">
          <div>
            <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><?= htmlspecialchars($gene_set_name) ?></span>
            <div style="font-size:0.7rem; opacity:0.8; margin-top:0.1rem;">Search limited to this gene set</div>
          </div>
          <span class="badge bg-white text-gene-set" style="font-size:0.65em; opacity:0.9;">Gene Set</span>
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

  <!-- Gene Set Header -->
  <div class="row mb-4" id="geneSetHeader">
    <div class="col-12">
      <div class="feature-header gene-set-header-custom shadow">
        <h1><?= htmlspecialchars($gene_set_name) ?> <span class="badge bg-white text-gene-set ms-1" style="font-size:0.7em; vertical-align:middle; opacity:0.85;">Gene Set</span></h1>
        <div class="feature-overview-body">
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
          $has_gff = file_exists("$gs_dir/genomic.gff") && filesize("$gs_dir/genomic.gff") > 0;
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
            <a href="/<?= $site ?>/api/download_file.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&filename=genomic.gff"
               class="btn btn-sm fw-semibold text-white"
               style="border-radius: 16px; background-color: #475569; border-color: #475569;"
               download>
              <i class="fa fa-download me-1"></i>GFF3
            </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
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


</div>
