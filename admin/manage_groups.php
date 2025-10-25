<?php
session_start();
include_once 'admin_header.php';
include_once __DIR__ . '/../site_config.php';

$access_group = 'Admin';
$groups_file = $organism_data . '/groups.json';
$groups_data = [];
if (file_exists($groups_file)) {
    $groups_data = json_decode(file_get_contents($groups_file), true);
}

function get_all_organisms() {
    global $organism_data;
    $orgs = [];
    $path = $organism_data;
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

function get_all_existing_groups($groups_data) {
    $all_groups = [];
    foreach ($groups_data as $data) {
        if (!empty($data['groups'])) {
            foreach ($data['groups'] as $group) {
                $all_groups[$group] = true;
            }
        }
    }
    $group_list = array_keys($all_groups);
    sort($group_list);
    return $group_list;
}

$all_organisms = get_all_organisms();

// Phase 1: Synchronize Data (JSON to Filesystem)
$cleaned_groups_data = [];
foreach ($groups_data as $data) {
    if (isset($all_organisms[$data['organism']]) && in_array($data['assembly'], $all_organisms[$data['organism']])) {
        $cleaned_groups_data[] = $data;
    }
}

// Only write if there are changes to prevent unnecessary file writes
if ($groups_data !== $cleaned_groups_data) {
    $groups_data = $cleaned_groups_data;
    file_put_contents($groups_file, json_encode($groups_data, JSON_PRETTY_PRINT));
}

// Handle POST requests for updates, additions, and deletions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['update'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $groups_input = trim($_POST['groups']);
        
        if ($groups_input === '') {
            $groups = [];
        } else {
            $groups = array_values(array_filter(array_map('trim', explode(',', $groups_input))));
        }

        foreach ($groups_data as &$data) {
            if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                $data['groups'] = $groups;
                break;
            }
        }
        unset($data);
        
    } elseif (isset($_POST['add'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $groups_input = trim($_POST['groups']);
        
        if ($groups_input === '') {
            $groups = [];
        } else {
            $groups = array_values(array_filter(array_map('trim', explode(',', $groups_input))));
        }

        $groups_data[] = [
            'organism' => $organism,
            'assembly' => $assembly,
            'groups' => $groups
        ];
    }

    if (file_put_contents($groups_file, json_encode(array_values($groups_data), JSON_PRETTY_PRINT)) === false) {
        echo "Error writing to groups.json.";
        exit;
    }

    header("Location: manage_groups.php");
    exit;
}

// Get all existing group tags
$existing_groups = get_all_existing_groups($groups_data);

// Identify unrepresented organisms/assemblies
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
  <h2>Manage Organism Assembly Groups</h2>
  
  <?php if (!empty($existing_groups)): ?>
    <div class="alert alert-info">
      <strong>Existing Group Tags:</strong> <?= implode(', ', $existing_groups) ?>
    </div>
  <?php else: ?>
    <div class="alert alert-warning">
      No group tags defined yet. You can create new ones by typing them below.
    </div>
  <?php endif; ?>

  <h3>Assemblies with Groups</h3>
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
        <tr data-organism="<?= htmlspecialchars($data['organism']) ?>" data-assembly="<?= htmlspecialchars($data['assembly']) ?>">
          <td><?= htmlspecialchars($data['organism']) ?></td>
          <td><?= htmlspecialchars($data['assembly']) ?></td>
          <td>
            <span class="groups-display"><?= htmlspecialchars(implode(', ', $data['groups'])) ?></span>
          </td>
          <td>
            <button type="button" class="btn btn-info btn-sm edit-groups">Edit</button>
            <button type="button" class="btn btn-success btn-sm update-btn" style="display:none;">Save</button>
            <button type="button" class="btn btn-secondary btn-sm cancel-btn" style="display:none;">Cancel</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (!empty($unrepresented_organisms)): ?>
    <h3 class="mt-4">Assemblies Without Groups</h3>
    <p class="text-muted">Add group tags to these assemblies to include them in the system.</p>
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
              <form method="post">
                <input type="hidden" name="organism" value="<?= htmlspecialchars($organism) ?>">
                <input type="hidden" name="assembly" value="<?= htmlspecialchars($assembly) ?>">
                <td><?= htmlspecialchars($organism) ?></td>
                <td><?= htmlspecialchars($assembly) ?></td>
                <td>
                  <input type="text" name="groups" class="form-control form-control-sm" placeholder="Enter groups (comma-separated)" value="">
                </td>
                <td>
                  <button type="submit" name="add" class="btn btn-success btn-sm">Add</button>
                </td>
              </form>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const existingGroups = <?= json_encode($existing_groups) ?>;
    
    document.querySelectorAll('.edit-groups').forEach(button => {
      const row = button.closest('tr');
      const groupsSpan = row.querySelector('.groups-display');
      const updateButton = row.querySelector('.update-btn');
      const cancelButton = row.querySelector('.cancel-btn');
      const organism = row.getAttribute('data-organism');
      const assembly = row.getAttribute('data-assembly');

      // Create a new text input for editing
      const editText = document.createElement('input');
      editText.type = 'text';
      editText.name = 'groups';
      editText.className = 'form-control form-control-sm edit-text-input';
      editText.style.display = 'none';
      editText.placeholder = 'Enter groups (comma-separated)';
      groupsSpan.parentNode.insertBefore(editText, groupsSpan.nextSibling);
      
      // Create suggestion helper
      if (existingGroups.length > 0) {
        const helpText = document.createElement('small');
        helpText.className = 'form-text text-muted edit-help';
        helpText.style.display = 'none';
        helpText.textContent = 'Existing tags: ' + existingGroups.join(', ');
        editText.parentNode.insertBefore(helpText, editText.nextSibling);
      }

      button.addEventListener('click', function() {
        groupsSpan.style.display = 'none';
        button.style.display = 'none';
        editText.value = groupsSpan.textContent.trim();
        editText.style.display = 'block';
        updateButton.style.display = 'inline-block';
        cancelButton.style.display = 'inline-block';
        if (row.querySelector('.edit-help')) {
          row.querySelector('.edit-help').style.display = 'block';
        }
        editText.focus();
      });

      updateButton.addEventListener('click', function() {
        // Create a form and submit
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'manage_groups.php';
        
        const orgInput = document.createElement('input');
        orgInput.type = 'hidden';
        orgInput.name = 'organism';
        orgInput.value = organism;
        
        const asmInput = document.createElement('input');
        asmInput.type = 'hidden';
        asmInput.name = 'assembly';
        asmInput.value = assembly;
        
        const groupsInput = document.createElement('input');
        groupsInput.type = 'hidden';
        groupsInput.name = 'groups';
        groupsInput.value = editText.value;
        
        const updateInput = document.createElement('input');
        updateInput.type = 'hidden';
        updateInput.name = 'update';
        updateInput.value = '1';
        
        form.appendChild(orgInput);
        form.appendChild(asmInput);
        form.appendChild(groupsInput);
        form.appendChild(updateInput);
        
        document.body.appendChild(form);
        form.submit();
      });

      cancelButton.addEventListener('click', function(event) {
        event.preventDefault();
        groupsSpan.style.display = 'inline';
        editText.style.display = 'none';
        button.style.display = 'inline-block';
        updateButton.style.display = 'none';
        cancelButton.style.display = 'none';
        if (row.querySelector('.edit-help')) {
          row.querySelector('.edit-help').style.display = 'none';
        }
      });
    });
  });
</script>

</body>
</html>