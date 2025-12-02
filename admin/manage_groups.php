<?php
ob_start();
include_once __DIR__ . '/admin_init.php';

// Handle AJAX requests after admin access verification
handleAdminAjax();

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');
$organism_data_path = $config->getPath('organism_data');

$groups_file = $metadata_path . '/organism_assembly_groups.json';
$file_write_error = null;
$desc_file_write_error = null;

// Load group data using helper
$groups_data = loadJsonFile($groups_file, []);

// Load group descriptions using helper
$descriptions_file = $metadata_path . '/group_descriptions.json';
$descriptions_data = loadJsonFile($descriptions_file, []);

// Check if groups file is writable
$file_write_error = getFileWriteError($groups_file);

// Check if descriptions file is writable
$desc_file_write_error = getFileWriteError($descriptions_file);

// Check if change_log directory is writable (do this check early but safely)
$change_log_dir = $metadata_path . '/change_log';
$change_log_error = null;
if (!is_dir($change_log_dir)) {
    // Try to create it
    if (!@mkdir($change_log_dir, 0775, true)) {
        $change_log_error = @getDirectoryError($change_log_dir);
    }
} else {
    $change_log_error = @getDirectoryError($change_log_dir);
}

$all_organisms = getOrganismsWithAssemblies($organism_data_path);

$all_existing_groups = getAllExistingGroups($groups_data);
$descriptions_data = loadJsonFile($descriptions_file, []);
$updated_descriptions = syncGroupDescriptions($all_existing_groups, $descriptions_data);

// Save synced descriptions data
if (file_exists($descriptions_file) && is_writable($descriptions_file)) {
    file_put_contents($descriptions_file, json_encode($descriptions_data, JSON_PRETTY_PRINT));
}

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
    $log_file = $metadata_path . '/change_log/manage_groups.log';
    $timestamp = date('Y-m-d H:i:s');
    $username = $_SESSION['username'] ?? 'unknown';
    
    // Ensure log directory exists
    $log_dir = $metadata_path . '/change_log';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }
    
    // Handle group description updates
    if (isset($_POST['save_description']) && !$desc_file_write_error) {
        $group_name = $_POST['group_name'];
        $images = json_decode($_POST['images_json'], true);
        $html_p = json_decode($_POST['html_p_json'], true);
        
        // Update the description
        foreach ($descriptions_data as &$desc) {
            if ($desc['group_name'] === $group_name) {
                $desc['images'] = $images;
                $desc['html_p'] = $html_p;
                break;
            }
        }
        unset($desc);
        
        // Save to file
        $save_result = file_put_contents($descriptions_file, json_encode($descriptions_data, JSON_PRETTY_PRINT));
        
        if ($save_result === false) {
            $_SESSION['error_message'] = "Error: Could not write to group_descriptions.json. Check file permissions.";
        } else {
            // Log the change
            $desc_log_entry = sprintf(
                "[%s] UPDATE by %s | Group: %s\n",
                $timestamp,
                $username,
                $group_name
            );
            file_put_contents($log_file, $desc_log_entry, FILE_APPEND);
            $_SESSION['success_message'] = "Group description updated successfully!";
        }
    }
    
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

        // Check for duplicate entry
        $duplicate_found = false;
        foreach ($groups_data as $data) {
            if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                $duplicate_found = true;
                break;
            }
        }

        if ($duplicate_found) {
            $_SESSION['error_message'] = "Error: An entry for $organism / $assembly already exists. Please update the existing entry instead.";
        } else {
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
    } elseif (isset($_POST['delete'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        
        // Find and store old groups for logging
        $old_groups = [];
        foreach ($groups_data as $data) {
            if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                $old_groups = $data['groups'];
                break;
            }
        }
        
        // Remove the entry
        $groups_data = array_values(array_filter($groups_data, function($data) use ($organism, $assembly) {
            return !($data['organism'] === $organism && $data['assembly'] === $assembly);
        }));
        
        // Log the deletion
        $log_entry = sprintf(
            "[%s] DELETE by %s | Organism: %s | Assembly: %s | Deleted groups: [%s]\n",
            $timestamp,
            $username,
            $organism,
            $assembly,
            implode(', ', $old_groups)
        );
        $log_write = @file_put_contents($log_file, $log_entry, FILE_APPEND);
        if ($log_write === false) {
            logError('manage_groups.php', "Failed to write to change_log/manage_groups.log", [
                'file' => $log_file,
                'action' => 'delete_entry'
            ]);
        }
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
        ob_end_clean();
        header("Location: manage_groups.php");
        exit;
    }
}

// Get all existing group tags
$existing_groups = getAllExistingGroups($groups_data);

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
  <?php include_once '../includes/head.php'; ?>
</head>
<body class="bg-light">

<?php include_once '../includes/navbar.php'; ?>

<div class="container mt-5" id="top">
  
  <?php 
    echo generatePermissionAlert(
        $groups_file,
        'Group Configuration Not Writable',
        'Cannot modify group configurations. File permissions must be fixed.',
        'file'
    );
  ?>

  <?php if ($change_log_error): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h4><i class="fa fa-exclamation-circle"></i> Change Log Directory Permission Issue</h4>
      <p><strong>Problem:</strong> The directory <code>metadata/change_log</code> is not writable by the web server.</p>
      
      <?php if ($change_log_error['type'] === 'missing'): ?>
        <p><strong>Issue:</strong> Directory does not exist and could not be created automatically.</p>
      <?php else: ?>
        <p><strong>Current Status:</strong></p>
        <ul class="mb-3">
          <li>Owner: <code><?= htmlspecialchars($change_log_error['owner']) ?></code></li>
          <li>Permissions: <code><?= $change_log_error['perms'] ?></code></li>
          <li>Web server user: <code><?= htmlspecialchars($change_log_error['web_user']) ?></code></li>
        </ul>
      <?php endif; ?>
      
      <p><strong>To Fix:</strong> Run <?php echo count($change_log_error['commands']) > 1 ? 'these commands' : 'this command'; ?> on the server:</p>
      <div style="margin: 10px 0; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
        <?php foreach ($change_log_error['commands'] as $cmd): ?>
          <code style="word-break: break-all; display: block; font-size: 0.9em; margin-bottom: 5px;">
            <?= htmlspecialchars($cmd) ?>
          </code>
        <?php endforeach; ?>
      </div>
      
      <p><small class="text-muted">After running the commands, refresh this page.</small></p>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['permission_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h4><i class="fa fa-exclamation-triangle"></i> File Write Permission Error</h4>
      <p><strong>Problem:</strong> Unable to save changes to <code>metadata/organism_assembly_groups.json</code></p>
      
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
    <h2><i class="fa fa-layer-group"></i> Manage Organism Assembly Groups & Descriptions</h2>
  </div>
  
  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutOrganismGroups">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Organism Groups <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutOrganismGroups">
      <div class="card-body">
        <p><strong>Purpose:</strong> Organize how organisms are categorized and viewed using flexible multi-group tagging.</p>
        
        <p><strong>Why It Matters:</strong></p>
        <ul>
          <li>Organisms can belong to multiple groups (e.g., "Flatworms", "Invertebrates", "Sanchez Lab" simultaneously)</li>
          <li>The special "Public" group makes organism assemblies visible to ALL visitors (including anonymous users)</li>
          <li>Non-public assemblies are only visible to logged-in users with appropriate access</li>
          <li>Groups organize organisms by taxonomy, research group, project, access level, or any dimension you choose</li>
        </ul>
        
        <p><strong>How It Works:</strong> Each organism assembly gets one or more group tags. Users see organisms based on:</p>
        <ul>
          <li><strong>Logged in:</strong> Their assigned groups + the Public group</li>
          <li><strong>Not logged in:</strong> Only the Public group organisms</li>
        </ul>
        
        <p class="mb-0"><strong>What You Can Do:</strong></p>
        <ul class="mb-0">
          <li>Assign organisms to multiple groups at once</li>
          <li>Create new group names dynamically</li>
          <li>Add images and HTML descriptions for each group</li>
          <li>Track stale entries (deleted from filesystem)</li>
          <li>Visually edit group assignments with a tag interface</li>
        </ul>
      </div>
    </div>
  </div>
  
  <?php
  ?>

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
            <button type="button" class="btn btn-info btn-sm edit-groups" <?= $file_write_error ? 'data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Edit</button>
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
                <button type="button" class="btn btn-info btn-sm add-groups-btn" <?= $file_write_error ? 'data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Add Groups</button>
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
              <button type="button" class="btn btn-warning btn-sm delete-stale-btn" data-index="<?= htmlspecialchars(json_encode($data)) ?>" <?= $file_write_error ? 'data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Delete Entry</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- Manage Group Descriptions Section -->
  <h3 class="mt-5 mb-4"><i class="fa fa-file-text"></i> Group Descriptions</h3>
  
  <?php 
    echo generatePermissionAlert(
        $descriptions_file,
        'Group Descriptions Not Writable',
        'Cannot modify group descriptions. File permissions must be fixed.',
        'file'
    );
  ?>

  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <?= htmlspecialchars($_SESSION['success_message']) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <?= htmlspecialchars($_SESSION['error_message']) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <div class="alert alert-info">
    <strong>Legend:</strong><br>
    <span style="color: #28a745; font-size: 18px;">✓</span> Has content (images or paragraphs) |
    <span style="color: #ffc107; font-size: 18px;">⚠</span> No content yet<br>
    <span style="color: #28a745;">Green</span> border = Currently in use | 
    <span style="color: #dc3545;">Red</span> border = Obsolete (retained for reference)
  </div>

  <div class="group-descriptions-container">
    <?php foreach ($descriptions_data as $desc): 
      // Check if group has content
      $has_images = false;
      foreach ($desc['images'] as $img) {
        if (!empty($img['file']) || !empty($img['caption'])) {
          $has_images = true;
          break;
        }
      }
      
      $has_paragraphs = false;
      foreach ($desc['html_p'] as $para) {
        if (!empty($para['text'])) {
          $has_paragraphs = true;
          break;
        }
      }
      
      $has_content = $has_images || $has_paragraphs;
    ?>
      <div class="group-card <?= $desc['in_use'] ? 'in-use' : 'not-in-use' ?>" style="margin-bottom: 20px; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; background-color: #fff;">
        <div class="group-header" onclick="toggleGroup('<?= htmlspecialchars($desc['group_name']) ?>')" style="cursor: pointer; padding: 10px; background: #f8f9fa; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <?php if ($has_content): ?>
              <span style="color: #28a745; font-size: 18px; margin-right: 5px;" title="Has content">✓</span>
            <?php else: ?>
              <span style="color: #ffc107; font-size: 18px; margin-right: 5px;" title="No content">⚠</span>
            <?php endif; ?>
            <span class="tag-chip selected" data-group-name="<?= htmlspecialchars($desc['group_name']) ?>" style="cursor: default; margin-right: 10px;"><?= htmlspecialchars($desc['group_name']) ?></span>
            <span class="badge bg-<?= $desc['in_use'] ? 'success' : 'danger' ?>">
              <?= $desc['in_use'] ? 'In Use' : 'Not In Use' ?>
            </span>
          </div>
          <span style="font-size: 18px; color: #666;">▼</span>
        </div>
        <div class="group-content" id="content-<?= htmlspecialchars($desc['group_name']) ?>" style="padding: 20px; display: none;">
          <form method="post" id="form-<?= htmlspecialchars($desc['group_name']) ?>">
            <input type="hidden" name="save_description" value="1">
            <input type="hidden" name="group_name" value="<?= htmlspecialchars($desc['group_name']) ?>">
            <input type="hidden" name="images_json" id="images-json-<?= htmlspecialchars($desc['group_name']) ?>">
            <input type="hidden" name="html_p_json" id="html-p-json-<?= htmlspecialchars($desc['group_name']) ?>">
            
            <h5>Images</h5>
            <div id="images-container-<?= htmlspecialchars($desc['group_name']) ?>">
              <?php foreach ($desc['images'] as $idx => $image): ?>
                <div class="image-item" data-index="<?= $idx ?>" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
                  <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeImage('<?= htmlspecialchars($desc['group_name']) ?>', <?= $idx ?>)" style="float: right;" <?= $desc_file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Remove</button>
                  <div class="form-group">
                    <label>Image File</label>
                    <input type="text" class="form-control image-file" value="<?= htmlspecialchars($image['file']) ?>" placeholder="e.g., Reef0607_0.jpg">
                  </div>
                  <div class="form-group">
                    <label>Caption (HTML allowed)</label>
                    <textarea class="form-control image-caption" rows="2"><?= htmlspecialchars($image['caption']) ?></textarea>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-primary mb-3" onclick="addImage('<?= htmlspecialchars($desc['group_name']) ?>')" <?= $desc_file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>+ Add Image</button>
            
            <h5>HTML Paragraphs</h5>
            <div id="paragraphs-container-<?= htmlspecialchars($desc['group_name']) ?>">
              <?php foreach ($desc['html_p'] as $idx => $para): ?>
                <div class="paragraph-item" data-index="<?= $idx ?>" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
                  <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeParagraph('<?= htmlspecialchars($desc['group_name']) ?>', <?= $idx ?>)" style="float: right;" <?= $desc_file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Remove</button>
                  <div class="form-group">
                    <label>Text (HTML allowed)</label>
                    <textarea class="form-control para-text" rows="4"><?= htmlspecialchars($para['text']) ?></textarea>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>CSS Style</label>
                      <input type="text" class="form-control para-style" value="<?= htmlspecialchars($para['style']) ?>" placeholder="e.g., color: red;">
                    </div>
                    <div class="form-group col-md-6">
                      <label>CSS Class</label>
                      <input type="text" class="form-control para-class" value="<?= htmlspecialchars($para['class']) ?>" placeholder="e.g., lead">
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-primary mb-3" onclick="addParagraph('<?= htmlspecialchars($desc['group_name']) ?>')" <?= $desc_file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>+ Add Paragraph</button>
            
            <div class="mt-3">
              <button type="submit" name="save_description" class="btn btn-success" <?= $desc_file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

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
  // Group Description Functions (global scope)
  function toggleGroup(groupName) {
    const content = document.getElementById('content-' + groupName);
    const header = event.target.closest('.group-header');
    const arrow = header.querySelector('span:last-child');
    
    if (content.style.display === 'none') {
      content.style.display = 'block';
      arrow.textContent = '▲';
    } else {
      content.style.display = 'none';
      arrow.textContent = '▼';
    }
  }
  
  function addImage(groupName) {
    const container = document.getElementById('images-container-' + groupName);
    const newIndex = container.children.length;
    const isDisabled = <?php echo $desc_file_write_error ? 'true' : 'false'; ?>;
    
    const html = `
      <div class="image-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeImage('${groupName}', ${newIndex})" style="float: right;" ${isDisabled ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : ''}>Remove</button>
        <div class="form-group">
          <label>Image File</label>
          <input type="text" class="form-control image-file" value="" placeholder="e.g., Reef0607_0.jpg">
        </div>
        <div class="form-group">
          <label>Caption (HTML allowed)</label>
          <textarea class="form-control image-caption" rows="2"></textarea>
        </div>
      </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
  }
  
  function removeImage(groupName, index) {
    const container = document.getElementById('images-container-' + groupName);
    const items = container.querySelectorAll('.image-item');
    if (items.length > 1) {
      items[index].remove();
    } else {
      alert('At least one image entry must remain (it can be empty).');
    }
  }
  
  function addParagraph(groupName) {
    const container = document.getElementById('paragraphs-container-' + groupName);
    const newIndex = container.children.length;
    const isDisabled = <?php echo $desc_file_write_error ? 'true' : 'false'; ?>;
    
    const html = `
      <div class="paragraph-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeParagraph('${groupName}', ${newIndex})" style="float: right;" ${isDisabled ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : ''}>Remove</button>
        <div class="form-group">
          <label>Text (HTML allowed)</label>
          <textarea class="form-control para-text" rows="4"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>CSS Style</label>
            <input type="text" class="form-control para-style" value="" placeholder="e.g., color: red;">
          </div>
          <div class="form-group col-md-6">
            <label>CSS Class</label>
            <input type="text" class="form-control para-class" value="" placeholder="e.g., lead">
          </div>
        </div>
      </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
  }
  
  function removeParagraph(groupName, index) {
    const container = document.getElementById('paragraphs-container-' + groupName);
    const items = container.querySelectorAll('.paragraph-item');
    if (items.length > 1) {
      items[index].remove();
    } else {
      alert('At least one paragraph entry must remain (it can be empty).');
    }
  }

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
    
    // Color all stale entry chips with consistent colors
    document.querySelectorAll('.table-hover .tag-chip.selected').forEach(chip => {
      const tag = chip.textContent.trim();
      chip.style.background = getColorForTag(tag);
      chip.style.borderColor = getColorForTag(tag);
    });
    
    // Color group name badges in descriptions section with consistent colors
    document.querySelectorAll('.group-card .tag-chip[data-group-name]').forEach(chip => {
      const groupName = chip.getAttribute('data-group-name');
      chip.style.background = getColorForTag(groupName);
      chip.style.borderColor = getColorForTag(groupName);
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
    
    // Handle "Delete Entry" for stale entries
    document.querySelectorAll('.delete-stale-btn').forEach(button => {
      button.addEventListener('click', function() {
        const row = button.closest('tr');
        const organism = row.getAttribute('data-organism');
        const assembly = row.getAttribute('data-assembly');
        
        if (confirm(`Delete entry for ${organism} / ${assembly}? This cannot be undone.`)) {
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
          
          const deleteInput = document.createElement('input');
          deleteInput.type = 'hidden';
          deleteInput.name = 'delete';
          deleteInput.value = '1';
          
          form.appendChild(orgInput);
          form.appendChild(asmInput);
          form.appendChild(deleteInput);
          
          document.body.appendChild(form);
          form.submit();
        }
      });
    });

    // Before submitting, collect all images and paragraphs into JSON
    document.querySelectorAll('form[id^="form-"]').forEach(form => {
      form.addEventListener('submit', function(e) {
        const groupName = this.querySelector('input[name="group_name"]').value;
        const imagesContainer = document.getElementById('images-container-' + groupName);
        const paragraphsContainer = document.getElementById('paragraphs-container-' + groupName);
        
        // Collect images
        const images = [];
        imagesContainer.querySelectorAll('.image-item').forEach(item => {
          images.push({
            file: item.querySelector('.image-file').value,
            caption: item.querySelector('.image-caption').value
          });
        });
        
        // Collect paragraphs
        const paragraphs = [];
        paragraphsContainer.querySelectorAll('.paragraph-item').forEach(item => {
          paragraphs.push({
            text: item.querySelector('.para-text').value,
            style: item.querySelector('.para-style').value,
            class: item.querySelector('.para-class').value
          });
        });
        
        // Set hidden fields
        document.getElementById('images-json-' + groupName).value = JSON.stringify(images);
        document.getElementById('html-p-json-' + groupName).value = JSON.stringify(paragraphs);
      });
    });
  });
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
<script src="/moop/js/permission-manager.js"></script>
</html>
<?php ob_end_flush(); ?>
