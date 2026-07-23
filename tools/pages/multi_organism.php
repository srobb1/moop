      <div class="container mt-5">
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#0891b2;">
          <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Multi-Organism Search</span>
          <span class="badge bg-white" style="font-size:0.65em; opacity:0.9; color:#0891b2;">search across selected organisms</span>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <div class="mb-2 fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-search me-1"></i> Search Gene IDs and Annotations <?= help_modal_trigger('search-help', '', 'How to search') ?></div>
          <form id="multiOrgSearchForm">
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
      $context = createToolContext('multi_organism_search', ['use_onclick_handler' => true]);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-5">
      <div class="card-header bg-search-results">
        <span class="fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-list me-1"></i> Search Results <?= help_modal_trigger('search-results-help', '', 'Understanding your search results') ?></span>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

  <!-- Organisms Section -->
  <div class="row mb-5" id="organismsSection">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header text-white d-flex align-items-center justify-content-between flex-wrap gap-2" style="background-color:#0f766e;">
          <div class="d-flex align-items-center gap-2">
            <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Selected Organisms</span>
            <?= field_help(
                'Untick an organism to leave it out of the search. Click a card to open that organism\'s own page, where you can search it alone.',
                'Selected organisms'
            ) ?>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <input type="text" id="organismFilter" class="form-control form-control-sm" placeholder="Filter organisms..." style="width:180px; font-size:0.8rem;">
            <div class="btn-group" role="group">
              <button type="button" class="btn btn-sm btn-outline-light selectAllOrganisms" style="font-size:0.75rem;">Select All</button>
              <button type="button" class="btn btn-sm btn-outline-light deselectAllOrganisms" style="font-size:0.75rem;">Deselect All</button>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <?php
            $organism_list = is_array($organisms) ? $organisms : [$organisms];
            foreach ($organism_list as $organism): 
                $organism_data_result = loadOrganismAndGetImagePath($organism, $images_path, $absolute_images_path);
                $organism_info = $organism_data_result['organism_info'];
                $image_src = $organism_data_result['image_path'];
                $show_image = !empty($image_src);
                $genus = $organism_info['genus'] ?? '';
                $species = $organism_info['species'] ?? '';
                $common_name = $organism_info['common_name'] ?? '';
            ?>
              <div class="col-md-6 col-lg-4 organism-card-col" data-filter-text="<?= htmlspecialchars(strtolower($organism . ' ' . $genus . ' ' . $species . ' ' . $common_name)) ?>">
                <div class="organism-selector-card position-relative" data-organism="<?= htmlspecialchars($organism) ?>">
                  <!-- Selection bar with checkbox -->
                  <label class="organism-selection-bar">
                    <input type="checkbox" class="organism-checkbox" data-organism="<?= htmlspecialchars($organism) ?>" checked>
                    <span class="organism-selection-icon"></span>
                  </label>
                  <!-- Clickable card that links to organism page -->
                  <a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism) ?>"
                     class="text-decoration-none organism-card-link">
                    <div class="card h-100 shadow-sm organism-card">
                      <div class="card-body text-center">
                        <div class="organism-image-container mb-3">
                          <?php if ($show_image): ?>
                            <img src="<?= $image_src ?>"
                                 alt="<?= htmlspecialchars($organism) ?>"
                                 class="organism-card-image"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                          <?php endif; ?>
                          <div class="organism-card-icon <?= $show_image ? 'display-none' : '' ?>" style="display: <?= $show_image ? 'none' : 'flex' ?>;">
                            <i class="fa fa-dna fa-4x text-primary"></i>
                          </div>
                        </div>
                        <h5 class="card-title mb-2">
                          <em><?= htmlspecialchars($genus . ' ' . $species) ?></em>
                        </h5>
                        <?php if ($common_name): ?>
                        <p class="text-muted mb-0"><?= htmlspecialchars($common_name) ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                </a>
              </div>
                </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /.container -->

<?php /* Shared results help — ONE home for the explanation, included by every page
        that renders a results table. Opened by the trigger on the section header above. */ ?>
<?php include_once __DIR__ . '/../../includes/search_results_modal.php'; ?>

<?php /* Shared search-box help — ONE home, included by every page with a search
        box. 'multi' pages search several organisms at once and get the organism
        selection card plus the per-organism phrasing of the result cap. */ ?>
<?php $search_help_scope = 'multi';
      include __DIR__ . '/../../includes/search_help_modal.php'; ?>
