<div class="container mt-5">

  <!-- Header and Tools Row -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#0f766e;">
          <?= page_title($group_name) ?>
          <span class="badge bg-white" style="font-size:0.65em; opacity:0.9; color:#0f766e;">search limited to this group</span>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <div class="mb-2 fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-search me-1"></i> Search Gene IDs and Annotations <?= help_modal_trigger('search-help', '', 'How to search') ?></div>
          <form id="groupSearchForm">
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
      $context = createToolContext('group', ['use_onclick_handler' => true]);
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

  <?php if ($group_info): ?>
    <!-- Group Description Section -->
    <div class="row mb-5" id="groupDescription">
      <?php if (!empty($group_info['images'])): ?>
        <?php foreach ($group_info['images'] as $image): ?>
          <?php if (!empty($image['file'])): ?>
            <div class="col-md-4 mb-3">
              <div class="card">
                <img src="/<?= $images_path ?>/<?= htmlspecialchars($image['file']) ?>" class="card-img-top" alt="<?= htmlspecialchars($group_name) ?>">
                <?php if (!empty($image['caption'])): ?>
                  <div class="card-body">
                    <p class="card-text small text-muted"><?= $image['caption'] ?></p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php elseif (!empty($group_info['wikipedia_image'])): ?>
        <!-- Wikipedia image for taxonomy groups - cached locally -->
        <?php $cached_image = getGroupImagePath($group_info, $absolute_images_path); ?>
        <?php if (!empty($cached_image)): ?>
          <div class="col-md-4 mb-3">
            <div class="card">
              <img src="<?= htmlspecialchars($cached_image) ?>" class="card-img-top" alt="<?= htmlspecialchars($group_name) ?>">
              <div class="card-body">
                <p class="card-text small text-muted">Image from <a href="<?= htmlspecialchars($group_info['wikipedia_url']) ?>" target="_blank">Wikipedia</a></p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      
      <div class="<?= (!empty($group_info['images'][0]['file']) || !empty($group_info['wikipedia_image'])) ? 'col-md-8' : 'col-12' ?>">
        <div class="card shadow-sm">
          <div class="card-header text-white" style="background-color:#0f766e;">
            <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">About <?= htmlspecialchars($group_name) ?></span>
          </div>
          <div class="card-body">
            <?php if (!empty($group_info['html_p'])): ?>
              <?php foreach ($group_info['html_p'] as $paragraph): ?>
                <p class="<?= htmlspecialchars($paragraph['class'] ?? '') ?>" style="<?= htmlspecialchars($paragraph['style'] ?? '') ?>">
                  <?= $paragraph['text'] ?>
                </p>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Organisms Section -->
  <div class="row mb-5" id="organismsSection">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header text-white d-flex align-items-center justify-content-between flex-wrap gap-2" style="background-color:#0f766e;">
          <div class="d-flex align-items-center gap-2">
            <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Organisms in <?= htmlspecialchars($group_name) ?></span>
            <?php if (!empty($group_organisms)): ?>
              <span class="organism-count-badge"><span class="oc-n">0</span> of <span class="oc-t">0</span></span>
            <?= field_help(
                'Each card says whether that organism is In search or Not in search — click the bar to switch it. '
                . 'Clicking the card itself opens that organism\'s own page instead.',
                'Selected organisms'
            ) ?>
            <?php endif; ?>
          </div>
          <?php if (!empty($group_organisms)): ?>
            <button type="button" class="btn btn-sm btn-light toggleAllOrganisms" style="font-size:0.75rem;">
              <span class="toggle-all-label">Deselect all</span>
            </button>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (empty($group_organisms)): ?>
            <div class="alert alert-info mb-0">
              <i class="fa fa-info-circle"></i> No organisms are currently available in this group.
            </div>
          <?php else: ?>
            <div class="row g-3" id="organismsGrid">
              <?php foreach ($group_organisms as $organism => $assemblies): ?>
                <?php
                  $organism_json_path = "$organism_data/$organism/organism.json";
                  $organism_info = [];
                  if (file_exists($organism_json_path)) {
                    $organism_info = loadJsonFile($organism_json_path, []);
                    
                    // Handle improperly wrapped JSON (extra outer braces)
                    if ($organism_info && !isset($organism_info['genus']) && !isset($organism_info['common_name'])) {
                      $keys = array_keys($organism_info);
                      if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]]['genus'])) {
                        $organism_info = $organism_info[$keys[0]];
                      }
                    }
                  }
                  $genus = $organism_info['genus'] ?? '';
                  $species = $organism_info['species'] ?? '';
                  $common_name = $organism_info['common_name'] ?? '';
                  
                  $image_src = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
                  $show_image = !empty($image_src);
                ?>
                <div class="col-md-6 col-lg-4">
                   <div class="organism-selector-card position-relative" data-organism="<?= htmlspecialchars($organism) ?>">
                     <!-- Selection bar with checkbox -->
                     <label class="organism-selection-bar">
                       <input type="checkbox" class="organism-checkbox" data-organism="<?= htmlspecialchars($organism) ?>" checked>
                       <span class="organism-selection-icon"></span>
                    <span class="organism-selection-text"><span class="sel-on">In search</span><span class="sel-off">Not in search</span></span>
                     </label>
                     <!-- Clickable card that links to organism page -->
<a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism) ?>&group=<?= urlencode($group_name) ?>" 
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
                        <span class="organism-card-go">View organism <i class="fa fa-arrow-right"></i></span>
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
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<?php /* Shared results help — ONE home for the explanation, included by every page
        that renders a results table. Opened by the trigger on the section header above. */ ?>
<?php include_once __DIR__ . '/../../includes/search_results_modal.php'; ?>

<?php /* Shared search-box help — ONE home, included by every page with a search
        box. 'multi' pages search several organisms at once and get the organism
        selection card plus the per-organism phrasing of the result cap. */ ?>
<?php $search_help_scope = 'multi';
      include __DIR__ . '/../../includes/search_help_modal.php'; ?>
