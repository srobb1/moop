      <div class="container mt-5">
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center">Multi-Organism Search</h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations <i class="fa fa-info search-instructions-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.8em;" data-help-type="multiOrganism"></i></h4>
          <form id="multiOrgSearchForm">
            <div class="row align-items-center">
              <div class="col">
                <div class="d-flex gap-2 align-items-center">
                  <input type="text" class="form-control" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
                  <button type="submit" class="btn btn-icon btn-search" id="searchBtn">
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
      $context = createToolContext('multi_organism_search', ['organisms' => $organisms]);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-5">
      <div class="card-header bg-search-results text-white">
        <h4 class="mb-0"><i class="fa fa-list"></i> Search Results <i class="fa fa-info search-results-help-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.9em;" data-help-type="results"></i></h4>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

  <!-- Organisms Section -->
  <div class="row mb-5">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <h3 class="card-title mb-0">Selected Organisms</h3>
              <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary selectAllOrganisms">
                  Select All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary deselectAllOrganisms">
                  Deselect All
                </button>
              </div>
            </div>
          </div>
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
              <div class="col-md-6 col-lg-4">
                <div class="organism-selector-card position-relative" data-organism="<?= htmlspecialchars($organism) ?>">
                  <!-- Selection bar with checkbox -->
                  <label class="organism-selection-bar">
                    <input type="checkbox" class="organism-checkbox" data-organism="<?= htmlspecialchars($organism) ?>" checked>
                    <span class="organism-selection-icon"></span>
                  </label>
                  <!-- Clickable card that links to organism page -->
                  <a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism) ?><?php foreach($organism_list as $org): ?>&multi_search[]=<?= urlencode($org) ?><?php endforeach; ?>" 
                     class="text-decoration-none organism-card-link">
                    <div class="card h-100 shadow-sm organism-card">
                      <div class="card-body text-center">
                        <div class="organism-image-container mb-3">
                          <?php if ($show_image): ?>
                            <img src="<?= $image_src ?>" 
                                 alt="<?= htmlspecialchars($organism) ?>"
                                 class="organism-card-image"
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
</div>

</div>
