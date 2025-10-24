<?php
session_start();

$logged_in = $_SESSION["logged_in"] ?? false;
$username  = $_SESSION["username"] ?? '';
$user_access = $_SESSION["access"] ?? [];

// Get visitor IP address
$ip = $_SERVER['REMOTE_ADDR'];

// Define allowed IP range
$start_ip = ip2long("127.0.0.11");  // Start of range
$end_ip   = ip2long("127.0.0.11"); // End of range

// Convert user IP to number
$user_ip = ip2long($ip);

function get_group_data() {
    $groups_file = '/var/www/html/moop/organisms/groups.txt';
    $groups_data = [];
    if (file_exists($groups_file)) {
        $lines = file($groups_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line, 3);
            $groups = explode(',', $parts[0]);
            $organism = $parts[1];
            $assembly = $parts[2];
            $groups_data[] = [
                'groups' => $groups,
                'organism' => $organism,
                'assembly' => $assembly
            ];
        }
    }
    return $groups_data;
}

$group_data = get_group_data();

function get_all_cards($group_data) {
    $cards = [];
    foreach ($group_data as $data) {
        foreach ($data['groups'] as $group) {
            if (!isset($cards[$group])) {
                $cards[$group] = [
                    'title' => $group,
                    'text' => "Explore $group Data",
                    'link' => strtolower($group) . '/index.php'
                ];
            }
        }
    }
    return $cards;
}

$all_cards = get_all_cards($group_data);

$access_group = '';
$cards_to_display = [];

if ($user_ip >= $start_ip && $user_ip <= $end_ip) {
    $access_group = 'ALL';
    $cards_to_display = $all_cards;
} elseif ($logged_in) {
    $access_group = 'Collaborator';
    if (isset($all_cards['Public'])) {
        $cards_to_display['Public'] = $all_cards['Public'];
    }
    foreach ($user_access as $organism => $assemblies) {
        if (!isset($cards_to_display[$organism])) {
            $cards_to_display[$organism] = [
                'title' => $organism,
                'text'  => "Explore $organism Data",
                'link'  => strtolower($organism) . '/index.php'
            ];
        }
    }
} else {
    $access_group = 'Public';
    if (isset($all_cards['Public'])) {
        $cards_to_display['Public'] = $all_cards['Public'];
    }
}

include_once realpath("header.php");
?>

<body class="bg-light">
<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold">SIMRbase</h1>
<br><hr><br>
    <h2 class="fw-bold">Available Organisms</h2>
    <p class="text-muted">
      For your IP: <span class="fw-semibold"><?= htmlspecialchars($ip) ?></span>  
      &nbsp;|&nbsp; Group: <span class="fw-semibold"><?= htmlspecialchars($access_group) ?> </span>
    </p>
  </div>

  <!-- Card Grid -->
  <div class="row g-4">
    <?php foreach ($cards_to_display as $card): ?>
      <div class="col-md-6 col-lg-4">
        <a href="<?= htmlspecialchars($card['link']) ?>" class="text-decoration-none">
          <div class="card h-100 shadow-sm border-0 rounded-3 hover-shadow">
            <div class="card-body text-center">
              <h5 class="card-title mb-3"><?= htmlspecialchars($card['title']) ?></h5>
              <p class="card-text text-muted"><?= htmlspecialchars($card['text']) ?></p>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Small CSS enhancement -->
<style>
  /* Subtle hover effect */
  .hover-shadow:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.75rem 1.25rem rgba(0,0,0,0.15);
    transition: all 0.25s ease-in-out;
  }
</style>
