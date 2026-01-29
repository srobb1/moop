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
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations <i class="fa fa-info-circle search-instructions-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.8em;" data-instruction="<strong>How to Search</strong><br><br><strong>Single Word Searches:</strong><br>Enter a single keyword to find all matching annotations. Example: &quot;kinase&quot;<br><br><strong>Multi-Word Searches:</strong><br>Enter multiple words separated by spaces to find records containing ANY of the terms. Example: &quot;kinase domain&quot; finds records with kinase OR domain<br><br><strong>Exact Phrase Searches:</strong><br>Use quotes to search for exact phrases. Example: &quot;&quot;ABC transporter&quot;&quot; finds only exact matches<br><br><strong>Minimum Character Requirement:</strong><br>Terms with fewer than 3 characters are automatically ignored. Example: &quot;go transcription&quot; searches only &quot;transcription&quot;<br><br><strong>Search Types:</strong><br>&bull; <strong>Gene IDs:</strong> Search by gene name, UniProt ID, or other identifiers<br>&bull; <strong>Annotations:</strong> Search across all annotation types<br><br><strong>Results Limit:</strong><br>Results are capped at 2,500 - use more specific terms to refine your search<br><br><strong>Filtering:</strong><br>Click the filter button to limit search to specific annotation sources"></i></h4>
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
        <h4 class="mb-0"><i class="fa fa-list"></i> Search Results <i class="fa fa-info-circle search-results-help-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 0.9em;" data-instruction="<strong>Using Your Results</strong><br><br><strong>Filter Results:</strong><br>Use the search boxes above each column header to filter results. Type to narrow down results by specific values.<br><br><strong>Sort Results:</strong><br>Click any column header to sort ascending or descending. Click again to reverse the sort order.<br><br><strong>Select and Export:</strong><br>Use the checkboxes to select specific rows, then click export buttons at the bottom:<br>&bull; <strong>Copy:</strong> Copy selected rows to clipboard<br>&bull; <strong>CSV:</strong> Download as comma-separated values file<br>&bull; <strong>Excel:</strong> Download as Excel spreadsheet<br>&bull; <strong>PDF:</strong> Download as PDF document<br>&bull; <strong>Print:</strong> Print selected results<br><br><strong>Column Visibility:</strong><br>Click the &quot;Column Visibility&quot; button to show or hide specific columns based on your needs.<br><br><strong>View Details:</strong><br>Click on any gene or annotation link to view detailed information on the gene/parent feature page."></i></h4>
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
    
    // Fall back to Wikipedia image if no local image
    if (!$show_image && !empty($organism_info['wikipedia_image'])) {
        $image_src = $organism_info['wikipedia_image'];
        $show_image = true;
        $image_info = ['caption' => 'Image from Wikipedia', 'link' => $organism_info['wikipedia_url'] ?? ''];
    }
    
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
           <h2 class="fw-bold mb-1" style="color: #0f766e;">
             <?= htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) ?>
           </h2>
           <p class="lead text-muted mb-4" style="font-size: 1.1rem; font-style: italic;">
             <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> 
                 <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>
           </p>
           
           <!-- Metadata Section -->
           <div class="organism-metadata-section mb-4">
             <?php if (!empty($organism_info['taxon_id'])): ?>
               <h6 class="text-muted mb-3" style="font-weight: 600;">NCBI Taxon ID</h6>
               <div class="chip-container">
                 <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($organism_info['taxon_id']) ?>" 
                    target="_blank" 
                    class="taxon-id-chip">
                   <?= htmlspecialchars($organism_info['taxon_id']) ?>
                   <i class="fa fa-external-link-alt fa-xs"></i>
                 </a>
               </div>
             <?php endif; ?>
 
             <?php if (!empty($organism_info['subclassification']['type']) && !empty($organism_info['subclassification']['value'])): ?>
               <div class="metadata-item">
                 <label class="text-muted small d-block mb-1"><?= htmlspecialchars($organism_info['subclassification']['type']) ?></label>
                 <div><?= htmlspecialchars($organism_info['subclassification']['value']) ?></div>
               </div>
             <?php endif; ?>
           </div>

          <!-- Taxonomic Breadcrumb -->
          <?php
          $taxonomy_tree_file = $config->getPath('metadata_path') . '/taxonomy_tree_config.json';
          if (!empty($organism_info['taxon_id']) && isAssemblyInTaxonomyTree($organism_name, '', $taxonomy_tree_file)): 
              $lineage = fetch_taxonomy_lineage($organism_info['taxon_id']);
               if (!empty($lineage)): 
                   $lineage_with_counts = getTaxonomyLineageWithCounts($lineage, $taxonomy_tree_data['tree'], $taxonomy_user_access);
                   ?>
                                 <div class="mt-4 pt-3 border-top">
                   <h6 class="text-muted mb-3" style="font-weight: 600;">
                     Taxonomy Lineage
                     <i class="fa fa-info-circle taxonomy-lineage-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 1em;" data-instruction="<strong>Taxonomy Lineage Counts:</strong><br>The numbers next to each taxonomic rank show how many organisms within that taxonomic group are available in <?= htmlspecialchars($config->getString('siteTitle')) ?>. <strong>Click a rank</strong> to view all organisms in that group."></i>
                   </h6>
                   <div class="breadcrumb clear-initial-trail">
                     <?php 
                     foreach ($lineage_with_counts as $item) {
                         $name = htmlspecialchars($item['name']);
                         $count = isset($item['count']) ? $item['count'] : 0;
                         $badge = $count > 0 ? " <span class=\"badge\">$count</span>" : '';
                         
                         // Create link to groups page with taxonomy_rank parameter
                         $taxonomy_url = "/$site/tools/groups.php?taxonomy_rank=" . urlencode($item['name']);
                         echo "<div><a href=\"$taxonomy_url\">$name$badge</a></div>";
                     }
                     ?>
                   </div>
                 </div>
              <?php endif;
          endif; ?>

          <!-- Groups This Organism Belongs To -->
          <?php
          $organism_groups = getGroupsForOrganism($organism_name, $group_data);
          if (!empty($organism_groups)): ?>
            <div class="mt-4 pt-3 border-top">
              <h6 class="text-muted mb-3" style="font-weight: 600;">
                Member of Groups
                <i class="fa fa-info-circle member-groups-trigger" style="cursor: pointer; margin-left: 0.5rem; font-size: 1em;" data-instruction="<strong>Group Membership Counts:</strong><br>The numbers next to each group show how many organisms are members of that group. Groups are collections of organisms organized by research focus, taxonomy, or other criteria. <strong>Click a group</strong> to view all organisms in that group."></i>
              </h6>
              <div class="chip-container">
                <?php foreach ($organism_groups as $group_name => $group_info): ?>
                  <a href="/<?= $site ?>/tools/groups.php?group=<?= urlencode($group_name) ?>" 
                     class="group-chip">
                    <?= htmlspecialchars($group_name) ?>
                    <span class="badge"><?= htmlspecialchars($group_info['count']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Compact Assemblies List -->
          <?php
          $organism_data = $config->getPath('organism_data');
          $db_path = getOrganismDatabase($organism_name, $organism_data);
          $compact_accessible_assemblies = [];
          
          if (!empty($db_path)) {
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
          }
          ?>
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
