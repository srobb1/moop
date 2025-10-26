<?php
session_start();
include_once 'admin_header.php';
include_once __DIR__ . '/../site_config.php';

$access_group = 'Admin';
$groups_file = $organism_data . '/organism_assembly_groups.json';
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
        echo "Error writing to organism_assembly_groups.json.";
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
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="assemblies-tbody">
      <?php foreach ($groups_data as $index => $data): ?>
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
              <?php if (empty($data['groups'])): ?>
                <span class="text-muted">(no groups)</span>
              <?php endif; ?>
            </span>
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
                <button type="button" class="btn btn-info btn-sm add-groups-btn">Add Groups</button>
                <button type="button" class="btn btn-success btn-sm save-new-btn" style="display:none;">Save</button>
                <button type="button" class="btn btn-secondary btn-sm cancel-new-btn" style="display:none;">Cancel</button>
              </td>
            </tr>
          <?php endforeach; ?>
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
</script>

</body>
</html>
