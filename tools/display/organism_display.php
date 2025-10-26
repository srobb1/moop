<?php
session_start();

$logged_in = $_SESSION["logged_in"] ?? false;
$username  = $_SESSION["username"] ?? '';
$user_access = $_SESSION["access"] ?? [];

include_once __DIR__ . '/../../site_config.php';

// Get organism name from query parameter
$organism_name = $_GET['organism'] ?? '';

if (empty($organism_name)) {
    header("Location: /$site/index.php");
    exit;
}

// Load organism JSON file
$organism_json_path = "$organism_data/$organism_name/organism.json";
$organism_info = null;

if (file_exists($organism_json_path)) {
    $json_content = file_get_contents($organism_json_path);
    $organism_info = json_decode($json_content, true);
    
    // Handle improperly wrapped JSON (extra outer braces)
    if ($organism_info && !isset($organism_info['genus']) && !isset($organism_info['common_name'])) {
        // Check if data is wrapped in an unnamed object
        $keys = array_keys($organism_info);
        if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]]['genus'])) {
            $organism_info = $organism_info[$keys[0]];
        }
    }
}

if (!$organism_info || !isset($organism_info['common_name'])) {
    header("Location: /$site/index.php");
    exit;
}

$access_group = '';
include_once realpath(__DIR__ . '/../../header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) ?> - <?= $siteTitle ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="mb-3">
    <a href="javascript:history.back()" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>

  <!-- Organism Header Section -->
  <div class="row mb-4">
    <?php if (!empty($organism_info['image'])): ?>
      <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
          <img src="/<?= $images_path ?>/<?= htmlspecialchars($organism_info['image']) ?>" 
               class="card-img-top" 
               alt="<?= htmlspecialchars($organism_info['common_name'] ?? $organism_name) ?>">
          <?php if (!empty($organism_info['image_src'])): ?>
            <div class="card-body">
              <p class="card-text small text-muted">
                <?php if (filter_var($organism_info['image_src'], FILTER_VALIDATE_URL)): ?>
                  Image source: <a href="<?= htmlspecialchars($organism_info['image_src']) ?>" target="_blank">Link</a>
                <?php else: ?>
                  <?= htmlspecialchars($organism_info['image_src']) ?>
                <?php endif; ?>
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="<?= !empty($organism_info['image']) ? 'col-md-8' : 'col-12' ?>">
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
        </div>
      </div>
    </div>
  </div>

  <!-- Description Section -->
  <?php if (!empty($organism_info['text'])): ?>
    <div class="row mb-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="card-title mb-4">About <?= htmlspecialchars($organism_info['common_name'] ?? $organism_name) ?></h3>
            <div class="organism-text">
              <?= $organism_info['text'] ?>
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

  <!-- Data Resources Section -->
  <div class="row mb-5">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4"><i class="fa fa-database"></i> Available Resources</h3>
          
          <div class="row g-3">
            <?php if (!empty($organism_info['genome_fasta'])): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-dna"></i> Genome FASTA</h5>
                  <p class="text-muted small mb-2">Complete genome sequence</p>
                  <code class="small"><?= htmlspecialchars($organism_info['genome_fasta']) ?></code>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($organism_info['protein_fasta'])): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-atom"></i> Protein FASTA</h5>
                  <p class="text-muted small mb-2">Amino acid sequences</p>
                  <code class="small"><?= htmlspecialchars($organism_info['protein_fasta']) ?></code>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($organism_info['cds_fasta'])): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-code"></i> CDS FASTA</h5>
                  <p class="text-muted small mb-2">Coding sequences</p>
                  <code class="small"><?= htmlspecialchars($organism_info['cds_fasta']) ?></code>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($organism_info['transcript_fasta'])): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-file-code"></i> Transcript FASTA</h5>
                  <p class="text-muted small mb-2">Transcript sequences</p>
                  <code class="small"><?= htmlspecialchars($organism_info['transcript_fasta']) ?></code>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .organism-text {
    text-align: justify;
    line-height: 1.6;
  }
  
  .organism-text p {
    margin-bottom: 1rem;
  }
  
  .resource-card {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
  }
  
  .resource-card:hover {
    background-color: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1);
  }
  
  .card {
    border: 1px solid rgba(0,0,0,0.1);
  }
  
  code {
    word-break: break-all;
  }
</style>

</body>
</html>

<?php
include_once __DIR__ . '/../../footer.php';
?>
