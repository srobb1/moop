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
        'color' => 'success',
    ],
    [
        'id' => 'organism-selection',
        'title' => 'Selecting Organisms',
        'description' => 'Choose organisms by group or use the interactive taxonomy tree for custom selections.',
        'icon' => 'fa-dna',
        'color' => 'info',
    ],
    [
        'id' => 'search-and-filter',
        'title' => 'Search & Filter',
        'description' => 'Use advanced search and filtering to find specific sequences and annotations.',
        'icon' => 'fa-search',
        'color' => 'primary',
    ],
    [
        'id' => 'blast-tutorial',
        'title' => 'BLAST Search',
        'description' => 'Learn how to use BLAST to compare sequences across organisms.',
        'icon' => 'fa-exchange-alt',
        'color' => 'warning',
    ],
    [
        'id' => 'multi-organism-analysis',
        'title' => 'Multi-Organism Analysis',
        'description' => 'Compare and analyze data across multiple organisms simultaneously.',
        'icon' => 'fa-project-diagram',
        'color' => 'danger',
    ],
    [
        'id' => 'data-export',
        'title' => 'Exporting Data',
        'description' => 'Download sequences and data in various formats for external analysis.',
        'icon' => 'fa-download',
        'color' => 'secondary',
    ],
    [
        'id' => 'organism-data-organization',
        'title' => 'Data Organization (Technical)',
        'description' => 'Technical guide on database schema, file organization, and data structure of organism data.',
        'icon' => 'fa-database',
        'color' => 'dark',
    ],
    [
        'id' => 'organism-setup-and-searches',
        'title' => 'Setup & Searches (Technical)',
        'description' => 'Technical guide for setting up new organisms, configuring metadata, and understanding search mechanics and the parent page.',
        'icon' => 'fa-cogs',
        'color' => 'secondary',
    ],
    [
        'id' => 'system-requirements',
        'title' => 'System Requirements & Planning',
        'description' => 'Hardware sizing, performance benchmarks, resource planning, and cost estimation based on organism scale.',
        'icon' => 'fa-server',
        'color' => 'info',
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

  <!-- Tutorials Grid -->
  <div class="row">
    <?php foreach ($tutorials as $tutorial): ?>
      <div class="col-md-6 col-lg-4 mb-4">
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
</div>

<style>
.tutorial-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.tutorial-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
}
</style>
