<?php
session_start();
include_once 'admin_access_check.php';
include_once __DIR__ . '/../site_config.php';
include_once __DIR__ . '/../tools/moop_functions.php';
include_once '../includes/head.php';
include_once '../includes/navbar.php';

$groups_file = $organism_data . '/organism_assembly_groups.json';
$groups_data = [];
$file_write_error = null;

if (file_exists($groups_file)) {
    $groups_data = json_decode(file_get_contents($groups_file), true);
}

// Check if file is writable
if (file_exists($groups_file) && !is_writable($groups_file)) {
    $webserver = getWebServerUser();
    $web_user = $webserver['user'];
    $web_group = $webserver['group'];
    $current_owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($groups_file))['name'] ?? 'unknown' : 'unknown';
    $current_perms = substr(sprintf('%o', fileperms($groups_file)), -4);
    $fix_command = "sudo chown " . escapeshellarg("$web_user:$web_group") . " " . escapeshellarg($groups_file) . " && sudo chmod 644 " . escapeshellarg($groups_file);
    
    $file_write_error = [
        'owner' => $current_owner,
        'perms' => $current_perms,
        'web_user' => $web_user,
        'web_group' => $web_group,
        'command' => $fix_command,
        'file' => $groups_file
    ];
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

// Create a mapping of which entries exist in the filesystem
$groups_data_with_status = [];
foreach ($groups_data as $data) {
    $exists_in_fs = isset($all_organisms[$data['organism']]) && 
                    in_array($data['assembly'], $all_organisms[$data['organism']]);
    $data['_fs_exists'] = $exists_in_fs;
    $groups_data_with_status[] = $data;
}

// Keep the status-marked data, but don't modify the original JSON file
// The original JSON is preserved as-is for user review

// Handle POST requests for updates, additions, and deletions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $log_file = $organism_data . '/organism_assembly_groups_changes.log';
    $timestamp = date('Y-m-d H:i:s');
    $username = $_SESSION['username'] ?? 'unknown';
    
    if (isset($_POST['update'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $groups_input = trim($_POST['groups']);
        
        if ($groups_input === '') {
            $groups = [];
        } else {
            $groups = array_values(array_filter(array_map('trim', explode(',', $groups_input))));
        }

        // Find old groups for logging
        $old_groups = [];
        foreach ($groups_data as &$data) {
            if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                $old_groups = $data['groups'];
                $data['groups'] = $groups;
                break;
            }
        }
        unset($data);
        
        // Log the change
        $log_entry = sprintf(
            "[%s] UPDATE by %s | Organism: %s | Assembly: %s | Old groups: [%s] | New groups: [%s]\n",
            $timestamp,
            $username,
            $organism,
            $assembly,
            implode(', ', $old_groups),
            implode(', ', $groups)
        );
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
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
        
        // Log the addition
        $log_entry = sprintf(
            "[%s] ADD by %s | Organism: %s | Assembly: %s | Groups: [%s]\n",
            $timestamp,
            $username,
            $organism,
            $assembly,
            implode(', ', $groups)
        );
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    if (file_put_contents($groups_file, json_encode(array_values($groups_data), JSON_PRETTY_PRINT)) === false) {
        // File write failed - log it
        logError('manage_groups.php', "Failed to write to organism_assembly_groups.json", [
            'file' => $groups_file,
            'action' => 'update_groups',
            'organism' => $organism ?? 'unknown',
            'assembly' => $assembly ?? 'unknown'
        ]);
        // The page-level check will detect the permission issue and show alert
    } else {
        // Success - redirect to refresh
        header("Location: manage_groups.php");
        exit;
    }
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Manage Groups</title>
</head>
<body class="bg-light">

<div class="container mt-5" id="top">
  
  <?php if ($file_write_error): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h4><i class="fa fa-exclamation-circle"></i> File Permission Issue Detected</h4>
      <p><strong>Problem:</strong> The file <code>organisms/organism_assembly_groups.json</code> is not writable by the web server.</p>
      
      <p><strong>Current Status:</strong></p>
      <ul class="mb-3">
        <li>File owner: <code><?= htmlspecialchars($file_write_error['owner']) ?></code></li>
        <li>Current permissions: <code><?= $file_write_error['perms'] ?></code></li>
        <li>Web server user: <code><?= htmlspecialchars($file_write_error['web_user']) ?></code></li>
        <?php if ($file_write_error['web_group']): ?>
        <li>Web server group: <code><?= htmlspecialchars($file_write_error['web_group']) ?></code></li>
        <?php endif; ?>
      </ul>
      
      <p><strong>To Fix:</strong> Run this command on the server:</p>
      <div style="margin: 10px 0; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
        <code style="word-break: break-all; display: block; font-size: 0.9em;">
          <?= htmlspecialchars($file_write_error['command']) ?>
        </code>
      </div>
      
      <p><small class="text-muted">After running the command, refresh this page.</small></p>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['permission_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h4><i class="fa fa-exclamation-triangle"></i> File Write Permission Error</h4>
      <p><strong>Problem:</strong> Unable to save changes to <code>organisms/organism_assembly_groups.json</code></p>
      
      <p><strong>Current Status:</strong></p>
      <ul>
        <li>File owner: <code><?= htmlspecialchars($_SESSION['permission_error']['owner']) ?></code></li>
        <li>Current permissions: <code><?= $_SESSION['permission_error']['perms'] ?></code></li>
        <li>Web server user: <code><?= htmlspecialchars($_SESSION['permission_error']['web_user']) ?></code></li>
      </ul>
      
      <p><strong>To Fix:</strong> Run this command on the server:</p>
      <div style="margin-top: 10px; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
        <code style="word-break: break-all; display: block;">
          <?= htmlspecialchars($_SESSION['permission_error']['command']) ?>
        </code>
      </div>
      
      <p class="mt-3"><small class="text-muted">After running the command, refresh this page to try again.</small></p>
    </div>
    <?php unset($_SESSION['permission_error']); ?>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa fa-layer-group"></i> Manage Organism Assembly Groups</h2>
    <a href="manage_group_descriptions.php" class="btn btn-secondary">Manage Group Descriptions ↗</a>
  </div>
  
  <div class="mb-3">
    <a href="index.php" class="btn btn-secondary">← Back to Admin Tools</a>
  </div>

  <h3>Assemblies with Groups</h3>
  <table class="table table-hover">
    <thead>
      <tr>
        <th style="cursor: pointer;" onclick="sortTable(0)">Organism <span id="sort-icon-0">⇅</span></th>
        <th style="cursor: pointer;" onclick="sortTable(1)">Assembly <span id="sort-icon-1">⇅</span></th>
        <th>Groups</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="assemblies-tbody">
      <?php foreach ($groups_data_with_status as $index => $data): ?>
        <?php if (!empty($data['groups'])): ?>
        <tr data-organism="<?= htmlspecialchars($data['organism']) ?>" data-assembly="<?= htmlspecialchars($data['assembly']) ?>" 
            style="<?= !$data['_fs_exists'] ? 'background-color: #fff3cd;' : '' ?>">
          <td><?= htmlspecialchars($data['organism']) ?></td>
          <td><?= htmlspecialchars($data['assembly']) ?></td>
          <td>
            <span class="groups-display">
              <?php 
                $sorted_groups = $data['groups'];
                sort($sorted_groups);
                foreach ($sorted_groups as $group): 
              ?>
                <span class="tag-chip selected" style="cursor: default;"><?= htmlspecialchars($group) ?></span>
              <?php endforeach; ?>
            </span>
          </td>
          <td>
            <?php if (!$data['_fs_exists']): ?>
              <span class="badge bg-warning text-dark" title="Directory not found in filesystem">⚠️ Stale (dir missing)</span>
            <?php else: ?>
              <span class="badge bg-success">✓ Synced</span>
            <?php endif; ?>
          </td>
          <td>
            <button type="button" class="btn btn-info btn-sm edit-groups" <?= $file_write_error ? 'disabled' : '' ?>>Edit</button>
            <button type="button" class="btn btn-success btn-sm update-btn" style="display:none;">Save</button>
            <button type="button" class="btn btn-secondary btn-sm cancel-btn" style="display:none;">Cancel</button>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($file_write_error): ?>
    <div class="alert alert-info mt-3">
      <i class="fa fa-info-circle"></i> <strong>Note:</strong> Edit and Add buttons are disabled because the file is not writable. Fix the permissions (see warning above) to enable editing.
    </div>
  <?php endif; ?>

  <?php if (!empty($unrepresented_organisms)): ?>
    <h3 class="mt-4">Assemblies Without Groups</h3>
    <p class="text-muted">Add group tags to these assemblies to include them in the system.</p>
    <table class="table table-hover">
      <thead>
        <tr>
          <th style="cursor: pointer;" onclick="sortTable(2)">Organism <span id="sort-icon-2">⇅</span></th>
          <th style="cursor: pointer;" onclick="sortTable(3)">Assembly <span id="sort-icon-3">⇅</span></th>
          <th>Groups</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="new-assemblies-tbody">
        <?php foreach ($unrepresented_organisms as $organism => $assemblies): ?>
          <?php foreach ($assemblies as $assembly): ?>
            <tr data-organism="<?= htmlspecialchars($organism) ?>" data-assembly="<?= htmlspecialchars($assembly) ?>" class="new-assembly-row">
              <td><?= htmlspecialchars($organism) ?></td>
              <td><?= htmlspecialchars($assembly) ?></td>
              <td>
                <span class="groups-display-new">(no groups)</span>
              </td>
              <td>
                <button type="button" class="btn btn-info btn-sm add-groups-btn" <?= $file_write_error ? 'disabled' : '' ?>>Add Groups</button>
                <button type="button" class="btn btn-success btn-sm save-new-btn" style="display:none;">Save</button>
                <button type="button" class="btn btn-secondary btn-sm cancel-new-btn" style="display:none;">Cancel</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php 
    // Find stale entries (in JSON but directory doesn't exist)
    $stale_entries = array_filter($groups_data_with_status, function($data) {
      return !$data['_fs_exists'];
    });
  ?>
  
  <?php if (!empty($stale_entries)): ?>
    <h3 class="mt-4"><span class="badge bg-warning text-dark">⚠️ Stale Entries</span></h3>
    <p class="text-muted">These entries are in the JSON file but the corresponding directories were moved or deleted. You can delete them or find the renamed directories.</p>
    <table class="table table-hover" style="background-color: #fff3cd;">
      <thead>
        <tr>
          <th>Organism</th>
          <th>Assembly</th>
          <th>Groups</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stale_entries as $index => $data): ?>
          <tr data-organism="<?= htmlspecialchars($data['organism']) ?>" data-assembly="<?= htmlspecialchars($data['assembly']) ?>">
            <td><?= htmlspecialchars($data['organism']) ?></td>
            <td><?= htmlspecialchars($data['assembly']) ?></td>
            <td>
              <span class="groups-display">
                <?php 
                  $sorted_groups = $data['groups'];
                  sort($sorted_groups);
                  foreach ($sorted_groups as $group): 
                ?>
                  <span class="tag-chip selected" style="cursor: default;"><?= htmlspecialchars($group) ?></span>
                <?php endforeach; ?>
              </span>
            </td>
            <td>
              <button type="button" class="btn btn-warning btn-sm delete-stale-btn" data-index="<?= htmlspecialchars(json_encode($data)) ?>" <?= $file_write_error ? 'disabled' : '' ?>>Delete Entry</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<style>
  .tag-editor {
    display: none;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
    margin-top: 5px;
  }
  .tag-chip {
    display: inline-block;
    padding: 5px 10px;
    margin: 3px;
    border-radius: 15px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
  }
  .tag-chip.available {
    background: #e9ecef;
    color: #495057;
    border: 2px solid #dee2e6;
  }
  .tag-chip.available:hover {
    background: #d3d6da;
    border-color: #adb5bd;
  }
  .tag-chip.selected {
    background: #007bff;
    color: white;
    border: 2px solid #0056b3;
  }
  .tag-chip.selected:hover {
    background: #0056b3;
  }
  .tag-chip.display-only {
    cursor: default;
  }
  .tag-chip .remove {
    margin-left: 5px;
    font-weight: bold;
  }
  .new-tag-input {
    display: inline-block;
    margin-top: 5px;
  }
  .selected-tags-display {
    margin-bottom: 10px;
    padding: 5px;
    min-height: 30px;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    background: white;
  }
</style>

<script>
  // Sorting functionality for tables
  const sortStates = [0, 0, 0, 0]; // 0 = unsorted, 1 = ascending, -1 = descending
  
  function sortTable(columnIndex) {
    const tableId = columnIndex < 2 ? 'assemblies-tbody' : 'new-assemblies-tbody';
    const tbody = document.getElementById(tableId);
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const colIndex = columnIndex % 2; // 0 for organism, 1 for assembly
    
    // Toggle sort state
    sortStates[columnIndex] = sortStates[columnIndex] === 1 ? -1 : 1;
    const isAscending = sortStates[columnIndex] === 1;
    
    // Update icons
    document.querySelectorAll('[id^="sort-icon-"]').forEach(icon => {
      if (icon.id === `sort-icon-${columnIndex}`) {
        icon.textContent = isAscending ? '↑' : '↓';
      } else if (icon.id.startsWith(`sort-icon-${Math.floor(columnIndex / 2) * 2}`)) {
        icon.textContent = '⇅';
      }
    });
    
    // Sort rows
    rows.sort((a, b) => {
      const aText = a.cells[colIndex].textContent.trim().toLowerCase();
      const bText = b.cells[colIndex].textContent.trim().toLowerCase();
      
      if (aText < bText) return isAscending ? -1 : 1;
      if (aText > bText) return isAscending ? 1 : -1;
      return 0;
    });
    
    // Re-append rows in sorted order
    rows.forEach(row => tbody.appendChild(row));
  }
  
  document.addEventListener('DOMContentLoaded', function() {
    const existingGroups = <?= json_encode($existing_groups) ?>;
    const colors = [
      '#007bff', '#28a745', '#17a2b8', '#ffc107', '#dc3545', 
      '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6610f2',
      '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4', '#009688',
      '#4caf50', '#8bc34a', '#cddc39', '#ff9800', '#ff5722',
      '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#00897b',
      '#5e35b1', '#1e88e5', '#43a047'
    ];
    
    // Create a persistent color mapping for tags
    const tagColorMap = {};
    let nextColorIndex = 0;
    
    function getColorForTag(tag) {
      // If we've already assigned a color to this tag, use it
      if (tagColorMap[tag]) {
        return tagColorMap[tag];
      }
      
      // Assign the next available color
      tagColorMap[tag] = colors[nextColorIndex % colors.length];
      nextColorIndex++;
      
      return tagColorMap[tag];
    }
    
    // Pre-assign colors to all existing groups to ensure consistency
    existingGroups.forEach(tag => {
      getColorForTag(tag);
    });
    
    document.querySelectorAll('.edit-groups').forEach(button => {
      const row = button.closest('tr');
      const groupsSpan = row.querySelector('.groups-display');
      const updateButton = row.querySelector('.update-btn');
      const cancelButton = row.querySelector('.cancel-btn');
      const organism = row.getAttribute('data-organism');
      const assembly = row.getAttribute('data-assembly');
      
      // Get current tags from chip elements
      const chipElements = groupsSpan.querySelectorAll('.tag-chip');
      let selectedTags = Array.from(chipElements).map(chip => chip.textContent.trim());
      const originalTags = [...selectedTags]; // Store original state
      
      // Color the chips on page load
      chipElements.forEach((chip, index) => {
        if (selectedTags[index]) {
          chip.style.background = getColorForTag(selectedTags[index]);
          chip.style.borderColor = getColorForTag(selectedTags[index]);
        }
      });
      
      // Create tag editor container
      const tagEditor = document.createElement('div');
      tagEditor.className = 'tag-editor';
      
      // Create selected tags display
      const selectedDisplay = document.createElement('div');
      selectedDisplay.className = 'selected-tags-display';
      selectedDisplay.innerHTML = '<small class="text-muted">Selected tags:</small>';
      tagEditor.appendChild(selectedDisplay);
      
      // Create available tags section
      const availableSection = document.createElement('div');
      availableSection.innerHTML = '<small class="text-muted">Available tags (click to add):</small><br>';
      tagEditor.appendChild(availableSection);
      
      // Create new tag input
      const newTagDiv = document.createElement('div');
      newTagDiv.className = 'new-tag-input';
      newTagDiv.innerHTML = `
        <small class="text-muted">Add new tag:</small><br>
        <input type="text" class="form-control form-control-sm d-inline-block" style="width: 150px;" placeholder="New tag name">
        <button type="button" class="btn btn-sm btn-primary add-new-tag">Add</button>
      `;
      tagEditor.appendChild(newTagDiv);
      
      groupsSpan.parentNode.insertBefore(tagEditor, groupsSpan.nextSibling);
      
      function renderTags() {
        // Render selected tags
        selectedDisplay.innerHTML = '<small class="text-muted">Selected tags:</small><br>';
        selectedTags.forEach(tag => {
          const chip = document.createElement('span');
          chip.className = 'tag-chip selected';
          chip.style.background = getColorForTag(tag);
          chip.style.borderColor = getColorForTag(tag);
          chip.innerHTML = `${tag} <span class="remove">×</span>`;
          chip.onclick = function() {
            selectedTags = selectedTags.filter(t => t !== tag);
            renderTags();
          };
          selectedDisplay.appendChild(chip);
        });
        
        // Render available tags
        availableSection.innerHTML = '<small class="text-muted">Available tags (click to add):</small><br>';
        existingGroups.forEach(tag => {
          if (!selectedTags.includes(tag)) {
            const chip = document.createElement('span');
            chip.className = 'tag-chip available';
            chip.textContent = tag;
            chip.onclick = function() {
              selectedTags.push(tag);
              renderTags();
            };
            availableSection.appendChild(chip);
          }
        });
      }
      
      // Add new tag functionality
      const newTagInput = newTagDiv.querySelector('input');
      const addNewTagBtn = newTagDiv.querySelector('.add-new-tag');
      
      addNewTagBtn.addEventListener('click', function() {
        const newTag = newTagInput.value.trim();
        if (newTag && !selectedTags.includes(newTag)) {
          selectedTags.push(newTag);
          if (!existingGroups.includes(newTag)) {
            existingGroups.push(newTag);
          }
          newTagInput.value = '';
          renderTags();
        }
      });
      
      newTagInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          addNewTagBtn.click();
        }
      });

      button.addEventListener('click', function() {
        groupsSpan.style.display = 'none';
        button.style.display = 'none';
        tagEditor.style.display = 'block';
        updateButton.style.display = 'inline-block';
        cancelButton.style.display = 'inline-block';
        renderTags();
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
        groupsInput.value = selectedTags.join(', ');
        
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
        tagEditor.style.display = 'none';
        button.style.display = 'inline-block';
        updateButton.style.display = 'none';
        cancelButton.style.display = 'none';
        // Reset selected tags
        selectedTags = [...originalTags];
      });
    });
    
    // Handle "Add Groups" for new assemblies
    document.querySelectorAll('.add-groups-btn').forEach(button => {
      const row = button.closest('tr');
      const groupsSpan = row.querySelector('.groups-display-new');
      const saveButton = row.querySelector('.save-new-btn');
      const cancelButton = row.querySelector('.cancel-new-btn');
      const organism = row.getAttribute('data-organism');
      const assembly = row.getAttribute('data-assembly');
      
      let selectedTags = [];
      
      // Create tag editor container
      const tagEditor = document.createElement('div');
      tagEditor.className = 'tag-editor';
      
      // Create selected tags display
      const selectedDisplay = document.createElement('div');
      selectedDisplay.className = 'selected-tags-display';
      selectedDisplay.innerHTML = '<small class="text-muted">Selected tags:</small>';
      tagEditor.appendChild(selectedDisplay);
      
      // Create available tags section
      const availableSection = document.createElement('div');
      availableSection.innerHTML = '<small class="text-muted">Available tags (click to add):</small><br>';
      tagEditor.appendChild(availableSection);
      
      // Create new tag input
      const newTagDiv = document.createElement('div');
      newTagDiv.className = 'new-tag-input';
      newTagDiv.innerHTML = `
        <small class="text-muted">Add new tag:</small><br>
        <input type="text" class="form-control form-control-sm d-inline-block" style="width: 150px;" placeholder="New tag name">
        <button type="button" class="btn btn-sm btn-primary add-new-tag-new">Add</button>
      `;
      tagEditor.appendChild(newTagDiv);
      
      groupsSpan.parentNode.insertBefore(tagEditor, groupsSpan.nextSibling);
      
      function renderTags() {
        // Render selected tags
        selectedDisplay.innerHTML = '<small class="text-muted">Selected tags:</small><br>';
        selectedTags.forEach(tag => {
          const chip = document.createElement('span');
          chip.className = 'tag-chip selected';
          chip.style.background = getColorForTag(tag);
          chip.style.borderColor = getColorForTag(tag);
          chip.innerHTML = `${tag} <span class="remove">×</span>`;
          chip.onclick = function() {
            selectedTags = selectedTags.filter(t => t !== tag);
            renderTags();
          };
          selectedDisplay.appendChild(chip);
        });
        
        // Render available tags
        availableSection.innerHTML = '<small class="text-muted">Available tags (click to add):</small><br>';
        existingGroups.forEach(tag => {
          if (!selectedTags.includes(tag)) {
            const chip = document.createElement('span');
            chip.className = 'tag-chip available';
            chip.textContent = tag;
            chip.onclick = function() {
              selectedTags.push(tag);
              renderTags();
            };
            availableSection.appendChild(chip);
          }
        });
      }
      
      // Add new tag functionality
      const newTagInput = newTagDiv.querySelector('input');
      const addNewTagBtn = newTagDiv.querySelector('.add-new-tag-new');
      
      addNewTagBtn.addEventListener('click', function() {
        const newTag = newTagInput.value.trim();
        if (newTag && !selectedTags.includes(newTag)) {
          selectedTags.push(newTag);
          if (!existingGroups.includes(newTag)) {
            existingGroups.push(newTag);
          }
          newTagInput.value = '';
          renderTags();
        }
      });
      
      newTagInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          addNewTagBtn.click();
        }
      });
      
      button.addEventListener('click', function() {
        groupsSpan.style.display = 'none';
        button.style.display = 'none';
        tagEditor.style.display = 'block';
        saveButton.style.display = 'inline-block';
        cancelButton.style.display = 'inline-block';
        renderTags();
      });
      
      saveButton.addEventListener('click', function() {
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
        groupsInput.value = selectedTags.join(', ');
        
        const addInput = document.createElement('input');
        addInput.type = 'hidden';
        addInput.name = 'add';
        addInput.value = '1';
        
        form.appendChild(orgInput);
        form.appendChild(asmInput);
        form.appendChild(groupsInput);
        form.appendChild(addInput);
        
        document.body.appendChild(form);
        form.submit();
      });
      
      cancelButton.addEventListener('click', function(event) {
        event.preventDefault();
        groupsSpan.style.display = 'inline';
        tagEditor.style.display = 'none';
        button.style.display = 'inline-block';
        saveButton.style.display = 'none';
        cancelButton.style.display = 'none';
        selectedTags = [];
      });
    });
  });

  // Modal for disabled buttons due to file permissions
  const modalElement = document.getElementById('permissionModal');
  if (modalElement) {
    const permissionModal = new bootstrap.Modal(modalElement, {});
    
    document.querySelectorAll('button[disabled]').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        permissionModal.show();
        return false;
      });
    });
  }
</script>

<!-- Permission Issue Modal -->
<div class="modal fade" id="permissionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fa fa-lock"></i> Actions Disabled</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>File permissions issue detected.</strong></p>
        <p>The configuration file <code>organism_assembly_groups.json</code> is not writable by the web server, so editing, adding, and deleting groups is temporarily disabled.</p>
        
        <p class="mb-0"><strong>To fix:</strong></p>
        <ol class="mt-2">
          <li>Scroll to the top of this page</li>
          <li>Copy the command from the <span class="badge bg-warning text-dark">Permission Issue</span> alert</li>
          <li>Run it on the server terminal</li>
          <li>Refresh this page</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <a href="#top" class="btn btn-warning" data-bs-dismiss="modal">Jump to Warning</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>
