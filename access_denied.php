<?php
session_start();
include_once __DIR__ . '/includes/config_init.php';

$config = ConfigManager::getInstance();
$siteTitle = $config->getString('siteTitle');
$adminEmail = $config->getString('admin_email');
$site = $config->getString('site');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Access Denied - <?= htmlspecialchars($siteTitle) ?></title>
  <?php include_once __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-light">

<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?= htmlspecialchars($siteTitle) ?></h1>
    <hr class="mx-auto" style="width: 100px; border: 2px solid #0d6efd;">
  </div>

  <!-- Access Denied Card -->
  <div class="row g-4 justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card h-100 shadow-sm border-0 rounded-3">
        <div class="card-body p-5 text-center">
          <div class="mb-4" style="font-size: 60px;">🔒</div>
          <h2 class="card-title fw-bold text-dark mb-4">Access Denied</h2>
          
          <p class="card-text text-muted mb-4">
            You don't have permission to view this page. If you believe you're seeing this page erroneously, please contact the administrator.
          </p>
          
          <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <i class="fa fa-info-circle"></i> <strong>Administrator Contact:</strong>
            <br>
            <a href="mailto:<?= htmlspecialchars($adminEmail) ?>" class="text-decoration-none">
              <?= htmlspecialchars($adminEmail) ?>
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          
          <a href="/<?= htmlspecialchars($site) ?>/index.php" class="btn btn-primary btn-lg w-100">
            <i class="fa fa-home"></i> Return to Home
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
