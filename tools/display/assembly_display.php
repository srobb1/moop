<?php
include_once __DIR__ . '/../../access_control.php';
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
$db_path = "$organism_data/$organism_name/$organism_name.genes.sqlite";

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

include_once __DIR__ . '/../../includes/header.php';
include_once realpath(__DIR__ . '/../../toolbar.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= htmlspecialchars($assembly_info['genome_name']) ?> - <?= $siteTitle ?></title>
  <style>
    .assembly-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 2rem;
      border-radius: 0.5rem;
      margin-bottom: 2rem;
    }
    
    .assembly-header h1 {
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .stat-card {
      background: white;
      border-left: 4px solid;
      padding: 1.5rem;
      border-radius: 0.5rem;
      box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
    }

    .stat-card.gene {
      border-left-color: #764ba2;
    }

    .stat-card.mrna {
      border-left-color: #17a2b8;
    }

    .stat-count {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0.5rem 0;
    }

    .stat-label {
      font-size: 1rem;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-card.gene .stat-count {
      color: #764ba2;
    }

    .stat-card.mrna .stat-count {
      color: #17a2b8;
    }
  </style>
</head>
<body class="bg-light">

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

  <!-- Assembly Header -->
  <div class="assembly-header shadow">
    <h1><?= htmlspecialchars($assembly_info['genome_name']) ?> <span class="heading-small">(<?= htmlspecialchars($assembly_info['genome_accession']) ?>)</span></h1>
  </div>

  <!-- Statistics Section -->
  <div class="row g-4 mb-5">
    <div class="col-md-6">
      <div class="stat-card gene">
        <div class="stat-label"><i class="fas fa-dna"></i> Genes</div>
        <div class="stat-count"><?= number_format($assembly_info['gene_count'] ?? 0) ?></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="stat-card mrna">
        <div class="stat-label"><i class="fas fa-scroll"></i> mRNA Transcripts</div>
        <div class="stat-count"><?= number_format($assembly_info['mrna_count'] ?? 0) ?></div>
      </div>
    </div>
  </div>

</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>
