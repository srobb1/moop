<?php
session_start();

$logged_in = $_SESSION["logged_in"] ?? false;
$username  = $_SESSION["username"] ?? '';
$user_access = $_SESSION["access"] ?? [];

include_once __DIR__ . '/../../site_config.php';

// Get the group name from query parameter
$group_name = $_GET['group'] ?? '';

if (empty($group_name)) {
    header("Location: /moop/index.php");
    exit;
}

// Load group descriptions
$group_descriptions_file = "$organism_data/group_descriptions.json";
$group_descriptions = [];
if (file_exists($group_descriptions_file)) {
    $group_descriptions = json_decode(file_get_contents($group_descriptions_file), true);
}

// Load organism assembly groups
$groups_file = "$organism_data/organism_assembly_groups.json";
$groups_data = [];
if (file_exists($groups_file)) {
    $groups_data = json_decode(file_get_contents($groups_file), true);
}

// Find the description for this group
$group_info = null;
foreach ($group_descriptions as $desc) {
    if ($desc['group_name'] === $group_name && ($desc['in_use'] ?? false)) {
        $group_info = $desc;
        break;
    }
}

// Find all organisms that belong to this group
$group_organisms = [];
foreach ($groups_data as $data) {
    if (in_array($group_name, $data['groups'])) {
        $organism = $data['organism'];
        $assembly = $data['assembly'];
        
        if (!isset($group_organisms[$organism])) {
            $group_organisms[$organism] = [];
        }
        $group_organisms[$organism][] = $assembly;
    }
}

// Sort organisms alphabetically
ksort($group_organisms);

$access_group = '';
include_once realpath(__DIR__ . '/../../header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($group_name) ?> - <?= $siteTitle ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="mb-3">
    <a href="/<?= $site ?>/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to Home</a>
  </div>

  <div class="text-center mb-4">
    <h1 class="fw-bold"><i class="fa fa-layer-group"></i> <?= htmlspecialchars($group_name) ?></h1>
  </div>

  <?php if ($group_info): ?>
    <!-- Group Description Section -->
    <div class="row mb-5">
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
  <div class="mb-5">
    <h2 class="fw-bold mb-4"><i class="fa fa-dna"></i> Available Organisms</h2>
    
    <?php if (empty($group_organisms)): ?>
      <div class="alert alert-info">
        <i class="fa fa-info-circle"></i> No organisms are currently available in this group.
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($group_organisms as $organism => $assemblies): ?>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm organism-detail-card">
              <div class="card-body">
                <h5 class="card-title fw-bold text-primary mb-3">
                  <a href="/<?= $site ?>/tools/display/organism_display.php?organism=<?= urlencode($organism) ?>" 
                     class="text-decoration-none text-primary">
                    <i class="fa fa-microscope"></i> <?= htmlspecialchars(str_replace('_', ' ', $organism)) ?>
                  </a>
                </h5>
                <h6 class="text-muted mb-2">Assemblies (<?= count($assemblies) ?>):</h6>
                <ul class="list-unstyled">
                  <?php foreach ($assemblies as $assembly): ?>
                    <li class="mb-2">
                      <a href="/<?= $site ?>/<?= strtolower($organism) ?>/<?= $assembly ?>/index.php" 
                         class="text-decoration-none">
                        <i class="fa fa-chevron-right text-primary"></i> 
                        <span class="assembly-link"><?= htmlspecialchars($assembly) ?></span>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
  .organism-detail-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.1);
  }
  
  .organism-detail-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important;
  }
  
  .assembly-link {
    color: #495057;
    transition: color 0.2s ease;
  }
  
  .assembly-link:hover {
    color: #007bff;
    text-decoration: underline !important;
  }
</style>

</body>
</html>

<?php
include_once __DIR__ . '/../../footer.php';
?>
