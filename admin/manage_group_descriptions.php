<?php
session_start();
include_once 'admin_access_check.php';
include_once __DIR__ . '/../site_config.php';
include_once __DIR__ . '/../tools/moop_functions.php';

$groups_file = $organism_data . '/organism_assembly_groups.json';
$descriptions_file = $organism_data . '/group_descriptions.json';
$file_write_error = null;

// Load organism assembly groups
$groups_data = [];
if (file_exists($groups_file)) {
    $groups_data = json_decode(file_get_contents($groups_file), true);
}

// Load descriptions
$descriptions_data = [];
if (file_exists($descriptions_file)) {
    $descriptions_data = json_decode(file_get_contents($descriptions_file), true);
}

// Check if file is writable
if (file_exists($descriptions_file) && !is_writable($descriptions_file)) {
    $webserver = getWebServerUser();
    $web_user = $webserver['user'];
    $web_group = $webserver['group'];
    $current_owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($descriptions_file))['name'] ?? 'unknown' : 'unknown';
    $current_perms = substr(sprintf('%o', fileperms($descriptions_file)), -4);
    $fix_command = "sudo chown " . escapeshellarg("$web_user:$web_group") . " " . escapeshellarg($descriptions_file) . " && sudo chmod 644 " . escapeshellarg($descriptions_file);
    
    $file_write_error = [
        'owner' => $current_owner,
        'perms' => $current_perms,
        'web_user' => $web_user,
        'web_group' => $web_group,
        'command' => $fix_command,
        'file' => $descriptions_file
    ];
}

// Get all existing groups from organism_assembly_groups.json
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

$existing_groups = get_all_existing_groups($groups_data);

// Sync descriptions with current groups
function sync_group_descriptions($existing_groups, $descriptions_data) {
    $desc_map = [];
    foreach ($descriptions_data as $desc) {
        $desc_map[$desc['group_name']] = $desc;
    }
    
    $updated_descriptions = [];
    
    // Update existing groups to in_use = true
    foreach ($existing_groups as $group) {
        if (isset($desc_map[$group])) {
            $desc_map[$group]['in_use'] = true;
            $updated_descriptions[] = $desc_map[$group];
        } else {
            // New group - add with default structure
            $updated_descriptions[] = [
                'group_name' => $group,
                'images' => [
                    [
                        'file' => '',
                        'caption' => ''
                    ]
                ],
                'html_p' => [
                    [
                        'text' => '',
                        'style' => '',
                        'class' => ''
                    ]
                ],
                'in_use' => true
            ];
        }
        unset($desc_map[$group]);
    }
    
    // Add remaining groups that are not in use anymore
    foreach ($desc_map as $group_name => $desc) {
        $desc['in_use'] = false;
        $updated_descriptions[] = $desc;
    }
    
    return $updated_descriptions;
}

$descriptions_data = sync_group_descriptions($existing_groups, $descriptions_data);

// Save synced data
$sync_result = file_put_contents($descriptions_file, json_encode($descriptions_data, JSON_PRETTY_PRINT));
if ($sync_result === false) {
    $sync_error = "Warning: Could not write to group_descriptions.json. Check file permissions.";
}

// Handle POST request to update descriptions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_description']) && !$file_write_error) {
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
        $log_file = $organism_data . '/group_descriptions_changes.log';
        $timestamp = date('Y-m-d H:i:s');
        $username = $_SESSION['username'] ?? 'unknown';
        $log_entry = sprintf(
            "[%s] UPDATE by %s | Group: %s\n",
            $timestamp,
            $username,
            $group_name
        );
        $log_result = file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        if ($log_result === false) {
            $_SESSION['warning_message'] = "Changes saved but could not write to log file. Check file permissions.";
        } else {
            $_SESSION['success_message'] = "Group description updated successfully!";
        }
    }
    
    header("Location: manage_group_descriptions.php");
    exit;
}

include_once '../includes/header.php';
?>

<style>
  .group-card {
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
  }
  .group-card.in-use {
    border-left: 4px solid #28a745;
  }
  .group-card.not-in-use {
    border-left: 4px solid #dc3545;
    opacity: 0.7;
  }
  .group-header {
    padding: 15px;
    background: #f8f9fa;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .group-header:hover {
    background: #e9ecef;
  }
  .group-content {
    padding: 20px;
    display: none;
  }
  .group-content.show {
    display: block;
  }
  .image-item, .paragraph-item {
    border: 1px solid #dee2e6;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 5px;
    background: #f8f9fa;
  }
  .remove-btn {
    float: right;
  }
</style>

<div class="container mt-5">
  <h2>Manage Group Descriptions</h2>
  
  <div class="mb-3">
    <a href="index.php" class="btn btn-secondary">← Back to Admin Tools</a>
    <a href="manage_groups.php" class="btn btn-secondary">← Back to Manage Groups</a>
  </div>

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

  <?php if (isset($_SESSION['warning_message'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <?= htmlspecialchars($_SESSION['warning_message']) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php unset($_SESSION['warning_message']); ?>
  <?php endif; ?>

  <?php if (isset($sync_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <?= htmlspecialchars($sync_error) ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
  <?php endif; ?>

  <?php if ($file_write_error): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <button type="button" class="close" data-dismiss="alert">&times;</button>
      <h4><i class="fa fa-exclamation-circle"></i> File Permission Issue Detected</h4>
      <p><strong>Problem:</strong> The file <code>organisms/group_descriptions.json</code> is not writable by the web server.</p>
      
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

  <div class="alert alert-info">
    <strong>Legend:</strong><br>
    <span style="color: #28a745; font-size: 18px;">✓</span> Has content (images or paragraphs) |
    <span style="color: #ffc107; font-size: 18px;">⚠</span> No content yet<br>
    <span style="color: #28a745;">Green</span> border = Currently in use | 
    <span style="color: #dc3545;">Red</span> border = Obsolete (retained for reference)
  </div>

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
    <div class="group-card <?= $desc['in_use'] ? 'in-use' : 'not-in-use' ?>">
      <div class="group-header" onclick="toggleGroup('<?= htmlspecialchars($desc['group_name']) ?>')">
        <div>
          <?php if ($has_content): ?>
            <span style="color: #28a745; font-size: 18px; margin-right: 5px;" title="Has content">✓</span>
          <?php else: ?>
            <span style="color: #ffc107; font-size: 18px; margin-right: 5px;" title="No content">⚠</span>
          <?php endif; ?>
          <strong><?= htmlspecialchars($desc['group_name']) ?></strong>
          <span class="badge badge-<?= $desc['in_use'] ? 'success' : 'danger' ?> ml-2">
            <?= $desc['in_use'] ? 'In Use' : 'Not In Use' ?>
          </span>
        </div>
        <span id="arrow-<?= htmlspecialchars($desc['group_name']) ?>">▼</span>
      </div>
      
      <div class="group-content" id="content-<?= htmlspecialchars($desc['group_name']) ?>">
        <form method="post" id="form-<?= htmlspecialchars($desc['group_name']) ?>">
          <input type="hidden" name="group_name" value="<?= htmlspecialchars($desc['group_name']) ?>">
          <input type="hidden" name="images_json" id="images-json-<?= htmlspecialchars($desc['group_name']) ?>">
          <input type="hidden" name="html_p_json" id="html-p-json-<?= htmlspecialchars($desc['group_name']) ?>">
          
          <h5>Images</h5>
          <div id="images-container-<?= htmlspecialchars($desc['group_name']) ?>">
            <?php foreach ($desc['images'] as $idx => $image): ?>
              <div class="image-item" data-index="<?= $idx ?>">
                <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeImage('<?= htmlspecialchars($desc['group_name']) ?>', <?= $idx ?>)" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Remove</button>
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
          <button type="button" class="btn btn-sm btn-primary mb-3" onclick="addImage('<?= htmlspecialchars($desc['group_name']) ?>')" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>+ Add Image</button>
          
          <h5>HTML Paragraphs</h5>
          <div id="paragraphs-container-<?= htmlspecialchars($desc['group_name']) ?>">
            <?php foreach ($desc['html_p'] as $idx => $para): ?>
              <div class="paragraph-item" data-index="<?= $idx ?>">
                <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeParagraph('<?= htmlspecialchars($desc['group_name']) ?>', <?= $idx ?>)" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Remove</button>
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
          <button type="button" class="btn btn-sm btn-primary mb-3" onclick="addParagraph('<?= htmlspecialchars($desc['group_name']) ?>')" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>+ Add Paragraph</button>
          
          <div class="mt-3">
            <button type="submit" name="save_description" class="btn btn-success" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
  function toggleGroup(groupName) {
    const content = document.getElementById('content-' + groupName);
    const arrow = document.getElementById('arrow-' + groupName);
    
    if (content.classList.contains('show')) {
      content.classList.remove('show');
      arrow.textContent = '▼';
    } else {
      content.classList.add('show');
      arrow.textContent = '▲';
    }
  }
  
  function addImage(groupName) {
    const container = document.getElementById('images-container-' + groupName);
    const newIndex = container.children.length;
    
    const html = `
      <div class="image-item" data-index="${newIndex}">
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeImage('${groupName}', ${newIndex})">Remove</button>
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
    
    const html = `
      <div class="paragraph-item" data-index="${newIndex}">
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeParagraph('${groupName}', ${newIndex})">Remove</button>
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
</script>

<!-- Permission Error Modal -->
<div class="modal fade" id="permissionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fa fa-exclamation-circle"></i> File Permission Error</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>The group descriptions configuration file is not writable by the web server.</strong></p>
        <p>You cannot make changes until this is fixed.</p>
        
        <p><strong>Current Status:</strong></p>
        <ul>
          <li>File: <code><?php echo htmlspecialchars($file_write_error['file'] ?? ''); ?></code></li>
          <li>Owner: <code><?php echo htmlspecialchars($file_write_error['owner'] ?? ''); ?></code></li>
          <li>Permissions: <code><?php echo htmlspecialchars($file_write_error['perms'] ?? ''); ?></code></li>
          <li>Web server user: <code><?php echo htmlspecialchars($file_write_error['web_user'] ?? ''); ?></code></li>
        </ul>
        
        <p><strong>To Fix:</strong> Run this command on the server:</p>
        <div style="margin: 10px 0; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd; word-break: break-all;">
          <code><?php echo htmlspecialchars($file_write_error['command'] ?? ''); ?></code>
        </div>
        
        <p><small class="text-muted">After running the command, refresh this page.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
