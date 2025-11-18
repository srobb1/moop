<?php
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/head.php';
include_once __DIR__ . '/../../includes/navbar.php';
include_once __DIR__ . '/../../includes/navigation.php';
include_once __DIR__ . '/../moop_functions.php';

// Validate parameters
$organism_name = validateOrganismParam($_GET['organism'] ?? '');
$assembly_accession = validateAssemblyParam($_GET['assembly'] ?? '');

// Setup organism context (loads info, checks access)
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
$organism_info = $organism_context['info'];

// Verify database exists
$db_path = verifyOrganismDatabase($organism_name, $organism_data);

// Query to get assembly info and feature counts
$query = "SELECT 
            g.genome_accession, 
            g.genome_name,
            COUNT(DISTINCT CASE WHEN f.feature_type = 'gene' THEN f.feature_id END) as gene_count,
            COUNT(DISTINCT CASE WHEN f.feature_type = 'mRNA' THEN f.feature_id END) as mrna_count
          FROM genome g
          LEFT JOIN feature f ON g.genome_id = f.genome_id
          WHERE g.genome_accession = ?
          GROUP BY g.genome_id";

$params = [$assembly_accession];
$results = fetchData($query, $params, $db_path);

if (empty($results)) {
    die("Error: Assembly not found.");
}

$assembly_info = $results[0];

// Get parent page info from referer
$parent_uniquename = $_GET['parent'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= htmlspecialchars($assembly_info['genome_name']) ?> - <?= $siteTitle ?></title>
  <?php include_once __DIR__ . '/../../includes/head.php'; ?>
  <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container mt-5">
  <?php
  $nav_context = [
      'page' => 'assembly',
      'organism' => $organism_name,
      'assembly' => $assembly_accession,
      'parent' => $parent_uniquename
  ];
  echo render_navigation_buttons($nav_context);
  ?>

  <!-- Assembly Header Section -->

  <!-- Assembly and Tools Row -->
  <div class="row mb-4">
    <!-- Assembly Column -->
    <div class="col-lg-8">
      <!-- Assembly Header Card -->
      <div class="assembly-header shadow mb-4 h-100">
        <div class="d-flex align-items-start justify-content-between">
          <div class="flex-grow-1">
            <h1 class="mb-3">
              <?= htmlspecialchars($assembly_info['genome_name']) ?>
              <span class="badge bg-warning text-dark ms-2">
                Assembly
              </span>
            </h1>
          </div>
        </div>
        
        <div>
          <div class="feature-info-item">
            <strong>Accession:</strong> <span class="assembly-value"><?= htmlspecialchars($assembly_info['genome_accession']) ?></span>
          </div>
          <div class="feature-info-item">
            <strong>Organism:</strong> <span class="assembly-value"><em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tools Column -->
    <div class="col-lg-4">
      <?php 
      $context = createAssemblyToolContext($organism_name, $assembly_accession, $assembly_info['genome_name']);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Statistics Section -->
  <div class="row g-4 mb-5">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h6 class="text-muted mb-3"><i class="fas fa-dna"></i> Genes</h6>
            <h2 class="fw-bold feature-color-gene"><?= number_format($assembly_info['gene_count'] ?? 0) ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h6 class="text-muted mb-3"><i class="fas fa-scroll"></i> mRNA Transcripts</h6>
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
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4"><i class="fa fa-download"></i> Download Sequence Files</h3>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($fasta_files as $type => $file_info): ?>
              <a href="/<?= $site ?>/tools/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_accession) ?>&type=<?= urlencode($type) ?>" 
                 class="btn btn-primary"
                 download>
                <i class="fa fa-download"></i> <?= htmlspecialchars($file_info['label']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>
