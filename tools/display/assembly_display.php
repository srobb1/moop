<?php
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/head.php';
include_once __DIR__ . '/../../includes/navbar.php';
include_once __DIR__ . '/../moop_functions.php';

// Get organism and assembly from query parameters
$organism_name = $_GET['organism'] ?? '';
$assembly_accession = $_GET['assembly'] ?? '';

if (empty($organism_name) || empty($assembly_accession)) {
    header("Location: /$site/index.php");
    exit;
}

// Load organism info
$organism_json_path = "$organism_data/$organism_name/organism.json";
$organism_info = null;

if (file_exists($organism_json_path)) {
    $organism_info = json_decode(file_get_contents($organism_json_path), true);
    
    // Handle improperly wrapped JSON
    if ($organism_info && !isset($organism_info['genus']) && !isset($organism_info['common_name'])) {
        $keys = array_keys($organism_info);
        if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]]['genus'])) {
            $organism_info = $organism_info[$keys[0]];
        }
    }
}

if (!$organism_info) {
    header("Location: /$site/index.php");
    exit;
}

// Access control
$is_public = is_public_organism($organism_name);
$has_organism_access = has_access('Collaborator', $organism_name);

if (!$has_organism_access && !$is_public) {
    header("Location: /$site/access_denied.php");
    exit;
}

// Get database path
$db_path = "$organism_data/$organism_name/organism.sqlite";

if (!file_exists($db_path)) {
    die("Error: Database not found for organism '$organism_name'.");
}

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
  <!-- Navigation Buttons -->
  <div class="mb-3">
    <?php if (!empty($parent_uniquename)): ?>
      <a href="/<?= $site ?>/tools/display/parent_display.php?organism=<?= urlencode($organism_name) ?>&uniquename=<?= urlencode($parent_uniquename) ?>" class="btn btn-secondary">
        <i class="fa fa-arrow-left"></i> Back to <?= htmlspecialchars($parent_uniquename) ?>
      </a>
    <?php endif; ?>
    <a href="/<?= $site ?>/tools/display/organism_display.php?organism=<?= urlencode($organism_name) ?>" class="btn btn-secondary">
      <i class="fa fa-arrow-left"></i> Back to <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>
    </a>
    <a href="/<?= $site ?>/index.php" class="btn btn-secondary">
      <i class="fa fa-home"></i> Home
    </a>
  </div>

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
      $context = ['organism' => $organism_name, 'assembly' => $assembly_accession, 'display_name' => $assembly_info['genome_name'], 'page' => 'assembly'];
      include_once __DIR__ . '/tool_section.php';
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
              <a href="/organisms_data/<?= htmlspecialchars($file_info['path']) ?>" 
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
