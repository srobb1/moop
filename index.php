<?php

session_start();

$logged_in = $_SESSION["logged_in"] ?? false;
$username  = $_SESSION["username"] ?? '';
$groups    = $_SESSION["groups"] ?? [];

// Get visitor IP address
$ip = $_SERVER['REMOTE_ADDR'];

// Define allowed IP range
$start_ip = ip2long("127.0.0.1");  // Start of range
$end_ip   = ip2long("127.0.0.1"); // End of range

// Convert user IP to number
$user_ip = ip2long($ip);

$all_cards = [
    "bats" => [
        "title" => "Bats",
        "text"  => "Explore Bat Data",
        "link"  => "bats/index.php"
    ],
    "corals" => [
        "title" => "Corals",
        "text"  => "Explore Coral Data",
        "link"  => "corals"
    ],
    "others" => [
        "title" => "Others",
        "text"  => "More to come",
        "link"  => ""
    ]
];


// Check if user IP is in range
if ($user_ip >= $start_ip && $user_ip <= $end_ip) {
    #$conf_path = "/var/www/html/SIMR/SIMR_data/egdb_conf";
    #include_once "$conf_path/easyGDB_conf.php";
    $access_group='SIMR';

    $cards = [
        $all_cards["bats"],
        $all_cards["corals"],
        $all_cards["others"]
    ];

}elseif($logged_in){ 
  echo "User: $username";
  $groups_str = implode(", ", $groups);
  echo " Access: $groups_str";
  $access_group = 'Collaborator';

  $cards = [];

  // Only add cards for groups they belong to
  foreach ($groups as $g) {
      $g = strtolower($g); // normalize
      if (isset($all_cards[$g])) {
          $cards[] = $all_cards[$g];
      }
  }
} else {
    // Show other content
    $access_group='Public';
    $cards = [
        $all_cards["bats"],
        $all_cards["others"],
        $all_cards["others"]
    ];
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
    <?php foreach ($cards as $card): ?>
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

