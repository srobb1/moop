<?php
/**
 * Cloudflare Turnstile human verification.
 * Shown once per session when Turnstile is enabled.
 * Sets $_SESSION['human_verified'] = true on success, then redirects back.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

include_once __DIR__ . '/includes/config_init.php';

$config     = ConfigManager::getInstance();
$site       = $config->getString('site', 'moop');
$siteTitle  = $config->getString('siteTitle', 'MOOP');
$ts         = $config->getArray('turnstile', []);
$site_key   = $ts['site_key']   ?? '';
$secret_key = $ts['secret_key'] ?? '';
$enabled    = $ts['enabled']    ?? false;

// If Turnstile is disabled, or site key not configured, just mark as verified and go home
if (!$enabled || empty($site_key)) {
    $_SESSION['human_verified'] = true;
    header('Location: ' . $return);
    exit;
}

// Sanitise and store return URL (same origin only)
$return = $_GET['return'] ?? ('/' . $site . '/');
if (!preg_match('#^/#', $return)) {
    $return = '/' . $site . '/';
}

$error = '';

// Handle token submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cf-turnstile-response'])) {
    $token    = $_POST['cf-turnstile-response'];
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v1/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret_key,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ]),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log('Turnstile curl error: ' . $curl_err);
    }

    $result = $response ? json_decode($response, true) : null;

    if ($result && $result['success']) {
        $_SESSION['human_verified'] = true;
        header('Location: ' . $return);
        exit;
    }

    $error = 'Verification failed — please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($siteTitle) ?></title>
  <link rel="stylesheet" href="/<?= htmlspecialchars($site) ?>/css/bootstrap.min.css">
  <script src="https://challenges.cloudflare.com/turnstile/v1/api.js" async defer></script>
  <style>
    body { background: #f8f9fa; }
    .verify-card {
      max-width: 420px;
      margin: 10vh auto 0;
    }
  </style>
</head>
<body>
  <div class="verify-card px-3">
    <div class="text-center mb-4">
      <p class="text-uppercase fw-semibold mb-1" style="letter-spacing:0.18em;font-size:1.05rem;color:#0891b2;">
        <?= htmlspecialchars($siteTitle) ?>
      </p>
      <p class="text-muted small mb-0">One moment while we verify your browser.</p>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <?php if ($error): ?>
          <div class="alert alert-danger alert-sm py-2 small mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="verify-form">
          <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">
          <div class="d-flex justify-content-center mb-3">
            <div class="cf-turnstile"
                 data-sitekey="<?= htmlspecialchars($site_key) ?>"
                 data-callback="onVerified"
                 data-theme="light">
            </div>
          </div>
          <noscript>
            <button type="submit" class="btn btn-primary w-100">Verify</button>
          </noscript>
        </form>
      </div>
    </div>

    <p class="text-center text-muted mt-3" style="font-size:0.75rem;">
      Protected by <a href="https://www.cloudflare.com/products/turnstile/" target="_blank" class="text-muted">Cloudflare Turnstile</a>
    </p>
  </div>

  <script>
    function onVerified(token) {
      document.getElementById('verify-form').submit();
    }
  </script>
</body>
</html>
