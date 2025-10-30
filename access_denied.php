<?php
include_once __DIR__ . '/site_config.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Denied - <?= htmlspecialchars($siteTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css">
  <style>
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .access-denied-card {
      max-width: 600px;
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      padding: 3rem;
      text-align: center;
    }
    .lock-icon {
      font-size: 80px;
      color: #dc3545;
      margin-bottom: 2rem;
    }
    .emoji {
      font-size: 60px;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="access-denied-card">
    <div class="emoji">🔒</div>
    <h1 class="mb-3">Oops! Access Denied</h1>
    <p class="lead text-muted mb-4">
      You don't have permission to view this page.
    </p>
    
    <div class="alert alert-warning" role="alert">
      <i class="fas fa-info-circle"></i> If you believe you're seeing this page erroneously, please contact the administrator.
    </div>
    
    <div class="mb-4">
      <p class="mb-1"><strong>Administrator Contact:</strong></p>
      <p>
        <a href="mailto:<?= htmlspecialchars($admin_email) ?>" class="text-decoration-none">
          <i class="fas fa-envelope"></i> <?= htmlspecialchars($admin_email) ?>
        </a>
      </p>
    </div>
    
    <div class="d-grid gap-2">
      <a href="/<?= htmlspecialchars($site) ?>/index.php" class="btn btn-primary btn-lg">
        <i class="fas fa-home"></i> Return to Home
      </a>
      <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Go Back
      </a>
    </div>
  </div>
</div>

</body>
</html>
