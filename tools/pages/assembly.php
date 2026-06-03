<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#d97706;">
          <div>
            <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><?= htmlspecialchars($assembly_info['genome_name']) ?></span>
            <div style="font-size:0.7rem; opacity:0.8; margin-top:0.1rem;">Search limited to this assembly</div>
          </div>
          <span class="badge bg-white text-assembly" style="font-size:0.65em; opacity:0.9;">Assembly</span>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <div class="mb-2 fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-search me-1"></i> Search Gene IDs and Annotations <i class="fa fa-info-circle search-instructions-trigger" style="cursor:pointer; margin-left:0.4rem; font-size:0.85em;" data-help-type="basic"></i></div>
          <form id="assemblySearchForm">
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
      $context = createToolContext('assembly', [
          'organism'     => $organism_name,
          'assembly'     => $assembly_accession,
          'display_name' => $assembly_info['genome_name'],
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

  <!-- Assembly Header Section with Info -->
  <?php
  list($genome_id, $genome_name, $genome_accession) = getAssemblyInfo($assembly_accession, $db_path);
  $fasta_files     = getAssemblyFastaFiles($organism_name, $genome_name);
  $genome_directory = $genome_name;
  if (empty($fasta_files)) {
      $fasta_files      = getAssemblyFastaFiles($organism_name, $genome_accession);
      $genome_directory = $genome_accession;
  }
  // Pick out the genome.fa (assembly-level, gene_set = '')
  $genome_file = null;
  foreach ($fasta_files as $ftype => $finfo) {
      if (($finfo['gene_set'] ?? '') === '') {
          $genome_file = ['type' => $ftype, 'info' => $finfo];
          break;
      }
  }
  $image_data  = getOrganismImageWithCaption($organism_info, $images_path, $absolute_images_path);
  $image_src   = $image_data['image_path'];
  $image_info  = ['caption' => $image_data['caption'], 'link' => $image_data['link']];
  $show_image  = !empty($image_src);
  $image_alt   = htmlspecialchars($organism_info['common_name'] ?? $organism_name);
  ?>

  <div class="row mb-4" id="assemblyHeader">
    <?php if ($show_image): ?>
      <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
          <img src="<?= $image_src ?>" class="card-img-top" alt="<?= $image_alt ?>">
          <?php if (!empty($image_info['caption'])): ?>
            <div class="card-body">
              <p class="card-text small text-muted">
                <?php if (!empty($image_info['link'])): ?>
                  <a href="<?= $image_info['link'] ?>" target="_blank" class="text-decoration-none">
                    <?= $image_info['caption'] ?> <i class="fa fa-external-link-alt fa-xs"></i>
                  </a>
                <?php else: ?>
                  <?= $image_info['caption'] ?>
                <?php endif; ?>
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="<?= $show_image ? 'col-md-8' : 'col-12' ?>">
      <div class="feature-header assembly-header-custom shadow">
        <h1><?= htmlspecialchars($assembly_info['genome_name']) ?> <span class="badge bg-white text-assembly ms-1" style="font-size:0.7em; vertical-align:middle; opacity:0.85;">Assembly</span></h1>
        <div class="feature-overview-body">
          <dl class="feature-info-grid mb-0">
            <dt>Accession</dt>
            <dd><?= htmlspecialchars($assembly_info['genome_accession']) ?></dd>
            <dt>Organism</dt>
            <dd><em><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>"><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?><i class="fa fa-external-link-alt link-icon"></i></a></em></dd>
            <?php if (!empty($assembly_meta['source'])): ?>
            <dt>Source</dt>
            <dd><?= htmlspecialchars($assembly_meta['source']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($assembly_meta['date_added'])): ?>
            <dt>Date added</dt>
            <dd><?= htmlspecialchars($assembly_meta['date_added']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($assembly_meta['note'])): ?>
            <dt>Note</dt>
            <dd><?= htmlspecialchars($assembly_meta['note']) ?></dd>
            <?php endif; ?>
          </dl>
          <?php if ($genome_file):
            $colorInfo = getColorClassOrStyle($genome_file['info']['color'] ?? '');
          ?>
          <div class="mt-2 pt-2" style="border-top: 1px solid #dee2e6;">
            <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_accession) ?>&genome_directory=<?= urlencode($genome_directory) ?>&gene_set=&type=<?= urlencode($genome_file['info']['seq_type'] ?? $genome_file['type']) ?>"
               class="btn btn-sm <?= $colorInfo['class'] ?> fw-semibold text-white"
               style="border-radius: 16px; <?= $colorInfo['style'] ?>"
               download>
              <i class="fa fa-download me-1"></i><?= htmlspecialchars($genome_file['info']['label']) ?>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Gene Sets Section -->
  <?php if (!empty($gene_sets)): ?>
  <div class="row mb-4" id="assemblyGeneSets">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center" style="background-color:#e11d48;">
          <i class="fas fa-layer-group me-2 text-white"></i>
          <span class="text-uppercase fw-semibold text-white" style="letter-spacing:0.1em; font-size:0.8rem;">Gene Sets</span>
          <span class="badge bg-white text-gene-set ms-2" style="font-size:0.65em;"><?= count($gene_sets) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($gene_sets as $gs): ?>
            <a href="/<?= $site ?>/tools/gene_set.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_info['genome_accession']) ?>&gene_set=<?= urlencode($gs['gene_set_name']) ?>"
               class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
              <div class="flex-grow-1">
                <div class="fw-semibold">
                  <span class="badge bg-gene-set me-2">Gene Set</span>
                  <?= htmlspecialchars($gs['gene_set_name']) ?>
                </div>
                <?php if (!empty($gs['gene_set_description'])): ?>
                <div class="small text-muted mt-1"><?= htmlspecialchars($gs['gene_set_description']) ?></div>
                <?php endif; ?>
              </div>
              <div class="d-flex gap-3 flex-shrink-0 text-center">
                <div>
                  <div class="fw-bold feature-color-gene"><?= number_format($gs['gene_count']) ?></div>
                  <div class="small text-muted">genes</div>
                </div>
                <div>
                  <div class="fw-bold feature-color-mrna"><?= number_format($gs['mrna_count']) ?></div>
                  <div class="small text-muted">transcripts</div>
                </div>
              </div>
              <i class="fas fa-chevron-right text-muted flex-shrink-0"></i>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
