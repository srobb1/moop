<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center"><?= htmlspecialchars($assembly_info['genome_name']) ?></h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations <i class="fa fa-info-circle search-instructions-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.8em;" data-help-type="basic"></i></h4>
          <form id="assemblySearchForm">
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
      $context = createToolContext('assembly', [
          'organism' => $organism_name,
          'assembly' => $assembly_accession,
          'display_name' => $assembly_info['genome_name']
      ]);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-info text-white">
        <h4 class="mb-0"><i class="fa fa-list"></i> Search Results <i class="fa fa-info-circle search-results-help-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.9em;" data-help-type="results"></i></h4>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

  <!-- Assembly Header Section with Info -->
  <div class="row mb-4" id="assemblyHeader">
    <?php 
    $image_data = getOrganismImageWithCaption($organism_info, $images_path, $absolute_images_path);
    $image_src = $image_data['image_path'];
    $image_info = ['caption' => $image_data['caption'], 'link' => $image_data['link']];
    $show_image = !empty($image_src);
    $image_alt = htmlspecialchars($organism_info['common_name'] ?? $organism_name);
    ?>
    
    <?php if ($show_image): ?>
      <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
          <img src="<?= $image_src ?>" 
               class="card-img-top" 
               alt="<?= $image_alt ?>">
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
        <h1 class="mb-0 assembly-heading">
          <?= htmlspecialchars($assembly_info['genome_name']) ?>
          <span class="badge bg-assembly ms-2">Assembly</span>
        </h1>
        
        <div class="feature-info-item">
          <strong>Accession:</strong> <span class="feature-value text-monospace"><?= htmlspecialchars($assembly_info['genome_accession']) ?></span>
        </div>
        <div class="feature-info-item">
          <strong>Organism:</strong> <span class="feature-value"><em><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>" class="link-light-bordered"><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></a></em></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Gene and mRNA Counts Section -->
  <div class="row g-4 mb-5" id="assemblyStats">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h2 class="fw-bold feature-color-gene mb-3">Genes</h2>
            <h2 class="fw-bold feature-color-gene"><?= number_format($assembly_info['gene_count'] ?? 0) ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h2 class="fw-bold feature-color-mrna mb-3">Transcripts</h2>
            <h2 class="fw-bold feature-color-mrna"><?= number_format($assembly_info['mrna_count'] ?? 0) ?></h2>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Assembly Downloads Section -->
  <?php
  $fasta_files = getAssemblyFastaFiles($organism_name, $assembly_accession);
  ?>
  
  <?php if (!empty($fasta_files)): ?>
  <div class="row mb-4" id="assemblyDownloads">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4"><i class="fa fa-download"></i> Download Sequence Files</h3>
          <div class="row g-3">
            <?php foreach ($fasta_files as $type => $file_info): ?>
              <?php 
                $colorInfo = getColorClassOrStyle($file_info['color'] ?? '');
              ?>
              <div class="col-6 col-md-3">
                <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_accession) ?>&type=<?= urlencode($type) ?>" 
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


