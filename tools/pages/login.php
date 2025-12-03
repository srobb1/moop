<?php
/**
 * LOGIN - Content File
 * 
 * Pure display content for login form.
 * All HTML structure handled by layout.php.
 * 
 * Available variables:
 * - $siteTitle: Site title from config
 * - $error: Login error message (if any)
 */
?>

<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?= htmlspecialchars($siteTitle) ?></h1>
    <hr class="mx-auto" style="width: 100px; border: 2px solid #0d6efd;">
  </div>

  <!-- Login Card -->
  <div class="row g-4 justify-content-center mb-5">
    <div class="col-md-8 col-lg-6">
      <div class="card h-100 shadow-sm border-0 rounded-3">
        <div class="card-body p-5">
          <h2 class="card-title fw-bold text-dark mb-4 text-center">Login</h2>

          <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form method="post">
            <div class="mb-4">
              <label for="username" class="form-label fw-semibold text-dark">Username</label>
              <input type="text" class="form-control form-control-lg" id="username" name="username" required>
            </div>
            <div class="mb-4">
              <label for="password" class="form-label fw-semibold text-dark">Password</label>
              <input type="password" class="form-control form-control-lg" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">
              <i class="fa fa-sign-in"></i> Login
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer Note -->
  <div class="row g-4 justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card border-0 rounded-3 bg-info bg-opacity-10">
        <div class="card-body text-center">
          <p class="card-text text-muted mb-0">
            <small>Need assistance? Contact the administrator for account access.</small>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
