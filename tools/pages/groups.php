<div class="container mt-5">

  <!-- Header and Tools Row -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center"><?= htmlspecialchars($group_name) ?></h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> <?= htmlspecialchars($group_name) ?>: Search Gene IDs and Annotations <i class="fa fa-info-circle search-instructions-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.8em;" data-instruction="<strong>Search Tips:</strong><br>&bull; <strong>Exact phrases:</strong> Use quotes like &quot;ABC transporter&quot; for exact matches<br>&bull; <strong>Multiple terms:</strong> Enter multiple keywords separated by spaces (e.g., kinase domain)<br>&bull; <strong>Short terms:</strong> Terms with fewer than 3 characters are automatically ignored<br>&bull; <strong>Gene IDs:</strong> Search by gene name, UniProt ID, or other identifiers<br>&bull; <strong>Annotations:</strong> Search across all annotation types in all organisms in this group<br>&bull; <strong>Select organisms:</strong> Use the checkboxes in the &quot;Organisms in Group&quot; section below to select/deselect which organisms to include in your search<br>&bull; <strong>Results limit:</strong> Results are capped at 2,500 per organism - use filters or more specific terms to refine<br>&bull; <strong>Advanced filtering:</strong> Click the filter button to limit search to specific annotation sources"></i></h4>
          <form id="groupSearchForm">
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
      // For taxonomy groups, pass organisms without group parameter (treated as multi-organism search)
      $tool_context_params = [];
      if ($is_taxonomy_group && !empty($organisms_list)) {
          $tool_context_params['organisms'] = $organisms_list;
          $tool_context_params['display_name'] = $group_name;
      } else {
          $tool_context_params['group'] = $group_name;
      }
      $context = createToolContext('group', $tool_context_params);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-5">
      <div class="card-header bg-info text-white">
        <h4 class="mb-0"><i class="fa fa-list"></i> Search Results</h4>
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
      <?php endif; ?>
      
      <div class="<?= !empty($group_info['images'][0]['file']) ? 'col-md-8' : 'col-12' ?>">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="card-title mb-4">About <?= htmlspecialchars($group_name) ?></h3>
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
        <div class="card-body">
          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <h3 class="card-title mb-1">
                  Organisms in <?= htmlspecialchars($group_name) ?> Group
                  <?php if (!empty($group_organisms)): ?>
                    <i class="fa fa-info-circle organism-instructions-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.8em;" data-instruction="Check/uncheck organisms to modify which are included in the search. Click an organism card to visit its page for organism-specific information and single-organism searches."></i>
                  <?php endif; ?>
                </h3>
              </div>
              <?php if (!empty($group_organisms)): ?>
                <div class="btn-group" role="group">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllOrganisms">
                    Select All
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllOrganisms">
                    Deselect All
                  </button>
                </div>
              <?php endif; ?>
            </div>
          </div>
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
                    $organism_info = json_decode(file_get_contents($organism_json_path), true);
                    
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
                     <div class="organism-selection-bar">
                       <input type="checkbox" class="organism-checkbox" data-organism="<?= htmlspecialchars($organism) ?>" checked>
                     </div>
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




