<?php
session_start();
include_once 'admin_header.php';
$access_group = 'Admin';
$groups_file = '/var/www/html/moop/organisms/groups.json';
$groups_data = [];
if (file_exists($groups_file)) {
    $groups_data = json_decode(file_get_contents($groups_file), true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $organism = $_POST['organism'];
    $assembly = $_POST['assembly'];
    $groups = array_map('trim', explode(',', $_POST['groups']));

    if (isset($_POST['update'])) {
        foreach ($groups_data as &$data) {
            if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                $data['groups'] = $groups;
                break;
            }
        }
    } elseif (isset($_POST['add'])) {
        $groups_data[] = [
            'organism' => $organism,
            'assembly' => $assembly,
            'groups' => $groups
        ];
    }

    if (file_put_contents($groups_file, json_encode($groups_data, JSON_PRETTY_PRINT))) {
        header("Location: manage_groups.php");
        exit;
    } else {
        echo "Error writing to groups.json.";
    }
}

function get_all_organisms() {
    $orgs = [];
    $path = '../organisms';
    if (!is_dir($path)) {
        return $orgs;
    }
    $organisms = scandir($path);
    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$path/$organism")) {
            continue;
        }
        $assemblies = [];
        $assemblyPath = "$path/$organism";
        $files = scandir($assemblyPath);
        foreach ($files as $file) {
            if ($file[0] === '.' || !is_dir("$assemblyPath/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        $orgs[$organism] = $assemblies;
    }
    return $orgs;
}

$all_organisms = get_all_organisms();

// Find unrepresented organisms
$represented_organisms = [];
foreach ($groups_data as $data) {
    $represented_organisms[$data['organism']][] = $data['assembly'];
}

$unrepresented_organisms = [];
foreach ($all_organisms as $organism => $assemblies) {
    foreach ($assemblies as $assembly) {
        if (!isset($represented_organisms[$organism]) || !in_array($assembly, $represented_organisms[$organism])) {
            $unrepresented_organisms[$organism][] = $assembly;
        }
    }
}

include_once '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Groups</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2>Manage Organism Groups</h2>

  <h3>Existing Groups</h3>
  <table class="table">
    <thead>
      <tr>
        <th>Organism</th>
        <th>Assembly</th>
        <th>Groups</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($groups_data as $index => $data): ?>
        <tr>
          <td><?= htmlspecialchars($data['organism']) ?></td>
          <td><?= htmlspecialchars($data['assembly']) ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="organism" value="<?= htmlspecialchars($data['organism']) ?>">
              <input type="hidden" name="assembly" value="<?= htmlspecialchars($data['assembly']) ?>">
              <input type="text" name="groups" value="<?= htmlspecialchars(implode(',', $data['groups'])) ?>">
          </td>
          <td>
              <button type="submit" name="update" class="btn btn-primary btn-sm">Update</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (!empty($unrepresented_organisms)): ?>
    <h3>Unrepresented Organisms</h3>
    <table class="table">
      <thead>
        <tr>
          <th>Organism</th>
          <th>Assembly</th>
          <th>Groups</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($unrepresented_organisms as $organism => $assemblies): ?>
          <?php foreach ($assemblies as $assembly): ?>
            <tr>
              <td><?= htmlspecialchars($organism) ?></td>
              <td><?= htmlspecialchars($assembly) ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="organism" value="<?= htmlspecialchars($organism) ?>">
                  <input type="hidden" name="assembly" value="<?= htmlspecialchars($assembly) ?>">
                  <input type="text" name="groups" value="">
              </td>
              <td>
                  <button type="submit" name="add" class="btn btn-success btn-sm">Add</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>
</body>
</html>
