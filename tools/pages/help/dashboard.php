<?php
/**
 * HELP DASHBOARD - Content File
 * 
 * Pure display content - card grid with all available tutorials.
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */

// Define available tutorials
$tutorials = [
    [
        'id' => 'getting-started',
        'title' => 'Getting Started',
        'description' => 'Learn the basics of MOOP and how to navigate the platform.',
        'icon' => 'fa-rocket',
        'color' => 'info',
        'category' => 'general',
    ],
    [
        'id' => 'organism-selection',
        'title' => 'Selecting Organisms',
        'description' => 'Choose organisms by group or use the interactive taxonomy tree for custom selections.',
        'icon' => 'fa-dna',
        'color' => 'info',
        'category' => 'general',
    ],
    [
        'id' => 'taxonomy-tree-management',
        'title' => 'Taxonomy Tree',
        'description' => 'Understand how the taxonomy tree works, how it\'s organized, and how to use it for organism selection and management.',
        'icon' => 'fa-tree',
        'color' => 'success',
        'category' => 'both',
    ],
    [
        'id' => 'search-and-filter',
        'title' => 'Search & Filter',
        'description' => 'Use advanced search and filtering to find specific sequences and annotations.',
        'icon' => 'fa-search',
        'color' => 'info',
        'category' => 'general',
    ],
    [
        'id' => 'blast-tutorial',
        'title' => 'BLAST Search',
        'description' => 'Learn how to use BLAST to compare sequences across organisms.',
        'icon' => 'fa-exchange-alt',
        'color' => 'info',
        'category' => 'general',
    ],
    [
        'id' => 'multi-organism-analysis',
        'title' => 'Multi-Organism Analysis',
        'description' => 'Compare and analyze data across multiple organisms simultaneously.',
        'icon' => 'fa-project-diagram',
        'color' => 'info',
        'category' => 'general',
    ],
    [
        'id' => 'data-export',
        'title' => 'Exporting Data',
        'description' => 'Download sequences and data in various formats for external analysis.',
        'icon' => 'fa-download',
        'color' => 'info',
        'category' => 'general',
    ],
    [
        'id' => 'organism-data-organization',
        'title' => 'Data Organization (Technical)',
        'description' => 'Technical guide on database schema, file organization, and data structure of organism data.',
        'icon' => 'fa-database',
        'color' => 'dark',
        'category' => 'technical',
    ],
    [
        'id' => 'generating-annotations-and-databases',
        'title' => 'Generating Annotations & Databases (Technical)',
        'description' => 'Guide for generating functional annotations and creating/loading organism.sqlite databases.',
        'icon' => 'fa-flask',
        'color' => 'dark',
        'category' => 'technical',
    ],
    [
        'id' => 'organism-setup-and-searches',
        'title' => 'Setup & Searches (Technical)',
        'description' => 'Technical guide for setting up new organisms, configuring metadata, and understanding search mechanics and the parent page.',
        'icon' => 'fa-cogs',
        'color' => 'dark',
        'category' => 'technical',
    ],
    [
        'id' => 'system-requirements',
        'title' => 'System Requirements & Planning (Technical)',
        'description' => 'Hardware sizing, performance benchmarks, resource planning, and cost estimation based on organism scale.',
        'icon' => 'fa-server',
        'color' => 'dark',
        'category' => 'technical',
    ],
    [
        'id' => 'function-registry-management',
        'title' => 'Function Registry Management (Technical)',
        'description' => 'Understand the function registry system, how registries are created and managed, and how to use them for custom functions.',
        'icon' => 'fa-list',
        'color' => 'dark',
        'category' => 'technical',
    ],
    [
        'id' => 'permission-management',
        'title' => 'Permission Management & Alerts (Technical)',
        'description' => 'Learn how to manage file permissions, fix permission issues, and understand why permissions are critical to MOOP.',
        'icon' => 'fa-lock',
        'color' => 'dark',
        'category' => 'technical',
    ],
];
?>

<div class="container mt-5">
  <h2><i class="fa fa-book"></i> Help & Tutorials</h2>
  
  <!-- Welcome Section -->
  <div class="card mb-5 border-info">
    <div class="card-header bg-info bg-opacity-10">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> Welcome to MOOP Help</h5>
    </div>
    <div class="card-body">
      <p class="mb-2">
        <strong>MOOP</strong> — to keep company, associate closely. This help section contains tutorials and guides to help you get the most out of MOOP.
      </p>
      <p class="mb-0">
        Select a tutorial below to learn about different features and how to use them effectively.
      </p>
    </div>
  </div>

  <!-- Search Section -->
  <div class="mb-5">
    <div class="input-group">
      <input type="text" id="helpSearch" class="form-control form-control-lg" placeholder="Search help topics..." aria-label="Search help topics">
      <span class="input-group-text bg-primary text-white">
        <i class="fa fa-search"></i>
      </span>
    </div>
    <small class="text-muted d-block mt-2">
      <span id="searchInfo">Showing all <?= count($tutorials) ?> topics</span>
    </small>
  </div>

  <!-- Category Filter Section -->
  <div class="mb-4">
    <div class="btn-group" role="group" aria-label="Filter by category">
      <button type="button" class="btn btn-outline-primary active" data-category="all">All Topics</button>
      <button type="button" class="btn btn-outline-info" data-category="general">General Use</button>
      <button type="button" class="btn btn-outline-success" data-category="both">For Everyone</button>
      <button type="button" class="btn btn-outline-dark" data-category="technical">Technical (Admin)</button>
    </div>
  </div>

  <!-- Tutorials Grid -->
  <div class="row" id="tutorialsGrid">
    <?php foreach ($tutorials as $tutorial): ?>
      <div class="col-md-6 col-lg-4 mb-4 tutorial-item" data-category="<?= htmlspecialchars($tutorial['category']) ?>" data-searchable="<?= strtolower(htmlspecialchars($tutorial['title'] . ' ' . $tutorial['description'])) ?>">
        <a href="help.php?topic=<?= htmlspecialchars($tutorial['id']) ?>" class="text-decoration-none">
          <div class="card h-100 shadow-sm border-0 rounded-3 tutorial-card">
            <div class="card-body text-center d-flex flex-column">
              <div class="mb-3">
                <i class="fa <?= htmlspecialchars($tutorial['icon']) ?> fa-2x text-<?= htmlspecialchars($tutorial['color']) ?>"></i>
              </div>
              <h5 class="card-title fw-bold text-dark mb-2"><?= htmlspecialchars($tutorial['title']) ?></h5>
              <p class="card-text text-muted flex-grow-1 mb-3"><?= htmlspecialchars($tutorial['description']) ?></p>
              <span class="btn btn-sm btn-outline-<?= htmlspecialchars($tutorial['color']) ?>">Read Tutorial</span>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <div id="noResults" class="text-center text-muted py-5" style="display: none;">
    <i class="fa fa-search fa-3x mb-3 opacity-50"></i>
    <p class="fs-5">No help topics found matching your search.</p>
  </div>
</div>

<style>
.tutorial-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.tutorial-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
}

.tutorial-item.hidden {
  display: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('helpSearch');
  const categoryButtons = document.querySelectorAll('[data-category]');
  const tutorialItems = document.querySelectorAll('.tutorial-item');
  const searchInfo = document.getElementById('searchInfo');
  const noResults = document.getElementById('noResults');
  
  let activeCategory = 'all';
  let searchTerm = '';

  function filterTutorials() {
    let visibleCount = 0;

    tutorialItems.forEach(item => {
      const itemCategory = item.dataset.category;
      const itemSearchable = item.dataset.searchable;
      
      const matchesCategory = activeCategory === 'all' || itemCategory === activeCategory || (activeCategory === 'general' && itemCategory === 'both') || (activeCategory === 'technical' && itemCategory === 'both');
      const matchesSearch = searchTerm === '' || itemSearchable.includes(searchTerm.toLowerCase());
      
      if (matchesCategory && matchesSearch) {
        item.classList.remove('hidden');
        visibleCount++;
      } else {
        item.classList.add('hidden');
      }
    });

    // Show/hide "no results" message
    if (visibleCount === 0) {
      noResults.style.display = 'block';
      searchInfo.textContent = 'No topics found';
    } else {
      noResults.style.display = 'none';
      searchInfo.textContent = `Showing ${visibleCount} of ${tutorialItems.length} topics`;
    }
  }

  // Search input listener
  searchInput.addEventListener('input', function() {
    searchTerm = this.value.trim();
    filterTutorials();
  });

  // Category filter buttons
  document.querySelectorAll('.btn-group .btn').forEach(button => {
    button.addEventListener('click', function() {
      // Update active button
      document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
      this.classList.add('active');
      
      // Update active category
      activeCategory = this.dataset.category || 'all';
      filterTutorials();
    });
  });
});
</script>
