<?php
/**
 * ORGANISM DISPLAY - Content File
 * 
 * Pure display content - no HTML structure, scripts, or styling.
 * 
 * Layout system (layout.php) handles:
 * - HTML structure (<!DOCTYPE>, <html>, <head>, <body>)
 * - All CSS and resources
 * - All scripts and inline variables
 * - Navbar and footer
 * 
 * This file has access to variables passed from organism_display.php:
 * - $organism_name
 * - $organism_info
 * - $config
 * - $site
 * - $images_path
 * - $absolute_images_path
 */
?>

<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center"><em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em></h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations <i class="fa fa-info-circle search-instructions-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.8em;" data-instruction="<strong>Search Tips:</strong><br>&bull; <strong>Exact phrases:</strong> Use quotes like &quot;ABC transporter&quot; for exact matches<br>&bull; <strong>Multiple terms:</strong> Enter multiple keywords separated by spaces (e.g., kinase domain)<br>&bull; <strong>Short terms:</strong> Terms with fewer than 3 characters are automatically ignored<br>&bull; <strong>Gene IDs:</strong> Search by gene name, UniProt ID, or other identifiers<br>&bull; <strong>Annotations:</strong> Search across all annotation types in this organism<br>&bull; <strong>Results limit:</strong> Results are capped at 2,500 per organism - use filters or more specific terms to refine<br>&bull; <strong>Advanced filtering:</strong> Click the filter button to limit search to specific annotation sources"></i></h4>
          <form id="organismSearchForm">
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
      $context = createToolContext('organism', [
          'organism' => $organism_name,
          'display_name' => $organism_info['common_name'] ?? $organism_name
      ]);
      $context['referrer_page'] = $_GET['referrer_page'] ?? '';
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-4">
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

  <!-- Organism Header Section -->
  <div class="row mb-4" id="organismHeader">
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
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="fw-bold mb-2">
            <?= htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) ?>
          </h1>
          <h3 class="text-muted mb-3">
            <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> 
                <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>
          </h3>
          
          <?php if (!empty($organism_info['taxon_id'])): ?>
            <p class="mb-3">
              <strong>Taxon ID:</strong> 
              <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($organism_info['taxon_id']) ?>" 
                 target="_blank" 
                 class="text-decoration-none">
                <?= htmlspecialchars($organism_info['taxon_id']) ?>
                <i class="fa fa-external-link-alt fa-xs"></i>
              </a>
            </p>
          <?php endif; ?>

          <?php if (!empty($organism_info['subclassification']['type']) && !empty($organism_info['subclassification']['value'])): ?>
            <p class="mb-3">
              <strong><?= htmlspecialchars($organism_info['subclassification']['type']) ?>:</strong> 
              <?= htmlspecialchars($organism_info['subclassification']['value']) ?>
            </p>
          <?php endif; ?>

          <!-- Compact Assemblies List -->
          <?php
          $group_data = getGroupData();
          $organism_data = $config->getPath('organism_data');
          $db_path = verifyOrganismDatabase($organism_name, $organism_data);
          $compact_accessible_assemblies = [];
          
          foreach ($group_data as $data) {
              if ($data['organism'] === $organism_name) {
                  if (has_assembly_access($organism_name, $data['assembly'])) {
                      $assembly_info = getAssemblyStats($data['assembly'], $db_path);
                      if (!empty($assembly_info)) {
                          $compact_accessible_assemblies[] = [
                              'accession' => $data['assembly'],
                              'genome_name' => $assembly_info['genome_name'] ?? '',
                              'genome_accession' => $assembly_info['genome_accession'] ?? $data['assembly']
                          ];
                      }
                  }
              }
          }
          ?>
          
          <?php if (!empty($compact_accessible_assemblies)): ?>
            <div class="mt-4 pt-3 border-top">
              <p class="mb-2"><strong>Available Genomes:</strong></p>
              <div>
                <div class="row g-2">
                  <?php foreach ($compact_accessible_assemblies as $assembly_item): ?>
                    <div class="col-12">
                      <div class="row g-1">
                        <div class="col-auto">
                          <a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_item['accession']) ?>" 
                             target="_blank"
                             class="text-decoration-none fw-500 text-monospace" 
                             style="font-size: 0.9rem;">
                            <?= htmlspecialchars($assembly_item['genome_name']) ?>|<?= htmlspecialchars($assembly_item['genome_accession']) ?>
                            <i class="fa fa-external-link-alt fa-xs"></i>
                          </a>
                        </div>
                      </div>
                      <div class="row g-1 mt-1">
                        <?php $fasta_files = getAssemblyFastaFiles($organism_name, $assembly_item['accession']); ?>
                        <?php if (!empty($fasta_files)): ?>
                          <?php foreach ($fasta_files as $type => $file_info): ?>
                            <?php 
                              $colorInfo = getColorClassOrStyle($file_info['color'] ?? '');
                            ?>
                            <div class="col-auto">
                              <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_item['accession']) ?>&type=<?= urlencode($type) ?>" 
                                 class="btn btn-xs <?= $colorInfo['class'] ?> text-white text-decoration-none"
                                 <?php if ($colorInfo['style']): ?>style="<?= $colorInfo['style'] ?>; font-size: 0.75rem; padding: 0.25rem 0.5rem;"<?php else: ?>style="font-size: 0.75rem; padding: 0.25rem 0.5rem;"<?php endif; ?>
                                 download title="<?= htmlspecialchars($file_info['label']) ?>">
                                <i class="fa fa-download fa-xs"></i> <?= htmlspecialchars($type) ?>
                              </a>
                            </div>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Description Section -->
  <div id="organismContent">
  <?php if (!empty($organism_info['html_p']) && is_array($organism_info['html_p'])): ?>
    <div class="row mb-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="card-title mb-4">About <?= htmlspecialchars($organism_info['common_name'] ?? $organism_name) ?></h3>
            <div class="organism-text">
              <?php foreach ($organism_info['html_p'] as $paragraph): ?>
                <p class="<?= htmlspecialchars($paragraph['class'] ?? '') ?>" 
                   style="<?= htmlspecialchars($paragraph['style'] ?? '') ?>">
                  <?= $paragraph['text'] ?>
                </p>
              <?php endforeach; ?>
            </div>
            
            <?php if (!empty($organism_info['text_src'])): ?>
              <div class="mt-3 text-muted">
                <small>
                  <?php if (filter_var($organism_info['text_src'], FILTER_VALIDATE_URL)): ?>
                    Source: <a href="<?= htmlspecialchars($organism_info['text_src']) ?>" target="_blank">Link</a>
                  <?php else: ?>
                    Source: <?= htmlspecialchars($organism_info['text_src']) ?>
                  <?php endif; ?>
                </small>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Assemblies Section -->
  <?php
  // Get accessible assemblies for this organism
  $group_data = getGroupData();
  $accessible_assemblies = [];
  
  foreach ($group_data as $data) {
      if ($data['organism'] === $organism_name) {
          if (has_assembly_access($organism_name, $data['assembly'])) {
              $accessible_assemblies[] = $data['assembly'];
          }
      }
  }
  ?>
  
  <?php if (!empty($accessible_assemblies)): ?>
  <div class="row mb-5">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4 assembly-title">Available Assemblies</h3>
          <div class="row g-3">
            <?php foreach ($accessible_assemblies as $assembly): ?>
              <?php $fasta_files = getAssemblyFastaFiles($organism_name, $assembly); ?>
              <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm organism-card">
                  <div class="card-body text-center">
                    <a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly) ?>" 
                       target="_blank"
                       class="text-decoration-none">
                      <h5 class="card-title mb-3 assembly-card-title">
                        <?= htmlspecialchars($assembly) ?> <i class="fa fa-external-link-alt"></i>
                      </h5>
                    </a>
                    <?php if (!empty($fasta_files)): ?>
                      <div class="mt-3 pt-2 border-top">
                        <?php foreach ($fasta_files as $type => $file_info): ?>
                          <?php 
                            $colorInfo = getColorClassOrStyle($file_info['color'] ?? '');
                          ?>
                          <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly) ?>&type=<?= urlencode($type) ?>" 
                             class="btn btn-sm <?= $colorInfo['class'] ?> w-100 mb-2 text-white"
                             <?php if ($colorInfo['style']): ?>style="<?= $colorInfo['style'] ?>"<?php endif; ?>
                             download>
                            <i class="fa fa-download"></i> <?= htmlspecialchars($file_info['label']) ?>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  </div><!-- End organismContent -->
</div>
