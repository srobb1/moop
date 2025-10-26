<?php
session_start();

$logged_in = $_SESSION["logged_in"] ?? false;
$username  = $_SESSION["username"] ?? '';
$user_access = $_SESSION["access"] ?? [];

include_once __DIR__ . '/site_config.php';
$usersFile = $users_file;
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
}

// Get visitor IP address
$ip = $_SERVER['REMOTE_ADDR'];

// Define allowed IP range
$start_ip = ip2long("127.0.0.11");  // Start of range
$end_ip   = ip2long("127.0.0.11"); // End of range

// Convert user IP to number
$user_ip = ip2long($ip);

function get_group_data($path) {
    $groups_file = "$path/organism_assembly_groups.json";
    $groups_data = [];
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true);
    }
    return $groups_data;
}

$group_data = get_group_data($organism_data);

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
} elseif ($logged_in && isset($users[$username]) && isset($users[$username]['role']) && $users[$username]['role'] === 'admin') {
    $access_group = 'Admin';
    $cards_to_display = $all_cards; // Admins can see all cards
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
    <h1 class="fw-bold mb-3"><?=$siteTitle?></h1>
    <hr class="mx-auto" style="width: 100px; height: 3px; opacity: 1; background: linear-gradient(to right, #007bff, #0056b3);">
    <h2 class="fw-bold mt-4 mb-3">Available Organisms</h2>
    <p class="text-muted">
      <i class="fa fa-network-wired"></i> IP: <span class="fw-semibold"><?= htmlspecialchars($ip) ?></span>  
      &nbsp;|&nbsp; <i class="fa fa-user-shield"></i> Access: <span class="fw-semibold"><?= htmlspecialchars($access_group) ?></span>
    </p>
  </div>

  <!-- Card Grid -->
  <div class="row g-4 justify-content-center">
    <?php foreach ($cards_to_display as $card): ?>
      <div class="col-md-6 col-lg-4">
        <a href="<?= htmlspecialchars($card['link']) ?>" class="text-decoration-none">
          <div class="card h-100 shadow-sm border-0 rounded-3 organism-card">
            <div class="card-body text-center d-flex flex-column">
              <div class="mb-3">
                <div class="organism-icon mx-auto">
                  <i class="fa fa-dna"></i>
                </div>
              </div>
              <h5 class="card-title mb-3 fw-bold text-dark"><?= htmlspecialchars($card['title']) ?></h5>
              <p class="card-text text-muted mb-3"><?= htmlspecialchars($card['text']) ?></p>
              <div class="mt-auto">
                <span class="btn btn-primary btn-sm">
                  View Details <i class="fa fa-arrow-right"></i>
                </span>
              </div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Enhanced CSS -->
<style>
  /* Organism card styling */
  .organism-card {
    transition: all 0.3s ease-in-out;
    border: 1px solid rgba(0,0,0,0.05) !important;
  }
  
  .organism-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 1rem 2rem rgba(0,0,0,0.15) !important;
  }
  
  /* Icon circle */
  .organism-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    transition: all 0.3s ease;
  }
  
  .organism-card:hover .organism-icon {
    transform: scale(1.1) rotate(5deg);
  }
  
  /* Card text colors */
  .organism-card .card-title {
    color: #2c3e50;
  }
  
  .organism-card:hover .card-title {
    color: #667eea;
  }
  
  /* Button styling */
  .organism-card .btn {
    transition: all 0.3s ease;
  }
  
  .organism-card:hover .btn {
    transform: translateX(5px);
  }
</style>
