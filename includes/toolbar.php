<?php
if (!class_exists('ConfigManager')) {
    include_once __DIR__ . '/config_init.php';
}
$config = ConfigManager::getInstance();
$site   = $config->getString('site');
$title  = $config->getString('siteTitle');
$favicon_path = $config->getUrl('favicon_path');

$access_level = get_access_level();
$access_display = [
    'PUBLIC'       => 'Public',
    'COLLABORATOR' => 'Collaborator',
    'IP_IN_RANGE'  => 'Trusted Network',
    'ADMIN'        => 'Administrator',
];
$access_text = $access_display[$access_level] ?? ucfirst(strtolower($access_level));
$access_class = [
    'PUBLIC'       => 'badge bg-secondary',
    'COLLABORATOR' => 'badge bg-info',
    'IP_IN_RANGE'  => 'badge bg-warning',
    'ADMIN'        => 'badge bg-danger',
];
$badge_class = $access_class[$access_level] ?? 'badge bg-secondary';
?>
<nav class="navbar navbar-expand-md bg-dark navbar-dark sticky-top">
  <div class="container-fluid px-3">

    <a class="navbar-brand" href="/<?= $site ?>/index.php">
      <img id="site_logo" src="<?= $favicon_path ?>" alt="Logo">
    </a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar"
            aria-controls="collapsibleNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="collapsibleNavbar">

      <!-- Left nav items -->
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/index.php"><i class="fa fa-home me-1"></i><?= htmlspecialchars($title) ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/jbrowse2.php"><i class="fa fa-dna me-1"></i> Genome Browser</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="toolsDropdown" role="button"
             data-bs-toggle="dropdown" aria-expanded="false">Tools</a>
          <ul class="dropdown-menu" aria-labelledby="toolsDropdown">
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/search.php">Annotation Search</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/moopmart.php">MOOPmart: Gene List Builder</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/blast.php">BLAST Search</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/retrieve_sequences.php">Retrieve Sequences</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/downloads.php">Downloads</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/about.php">About</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/help.php">Help</a>
        </li>
        <?php if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/admin/admin.php"><i class="fa fa-tools me-1"></i> Admin Tools</a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Right nav items (collapse into menu on mobile) -->
      <ul class="navbar-nav align-items-md-center gap-2">
        <li class="nav-item">
          <span class="<?= $badge_class ?>"><?= htmlspecialchars($access_text) ?></span>
        </li>
        <li class="nav-item">
          <?php if (get_access_level() === 'IP_IN_RANGE' || !is_logged_in()): ?>
            <a id="login_link" class="nav-link" href="/<?= $site ?>/login.php">Log In <i class="fa fa-sign-in-alt"></i></a>
          <?php else: ?>
            <a id="logout_link" class="nav-link" href="/<?= $site ?>/logout.php">Log Out <i class="fa fa-sign-out-alt"></i></a>
          <?php endif; ?>
        </li>
      </ul>

    </div>
  </div>
</nav>
