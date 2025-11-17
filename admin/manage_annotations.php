<?php
session_start();
include_once 'admin_access_check.php';
include_once __DIR__ . '/../site_config.php';
include_once __DIR__ . '/../includes/navigation.php';
include_once __DIR__ . '/../tools/moop_functions.php';
include_once '../includes/head.php';
include_once '../includes/navbar.php';


$config_file = "$metadata_path/annotation_config.json";

// Load config using helper
$config = loadJsonFile($config_file, []);

// Check if file is writable
$file_write_error = getFileWriteError($config_file);

// Transform annotation_types to analysis_order and descriptions if needed
if (!empty($config) && isset($config['annotation_types']) && !isset($config['analysis_order'])) {
    $analysis_order = [];
    $analysis_descriptions = [];
    
    foreach ($config['annotation_types'] as $key => $annotation) {
        $analysis_order[] = $key;
        $analysis_descriptions[$key] = [
            'display_name' => $annotation['display_name'] ?? $key,
            'description' => $annotation['description'] ?? '',
            'color' => $annotation['color'] ?? 'secondary',
            'enabled' => $annotation['enabled'] ?? true
        ];
    }
    
    // Sort by order field
    usort($analysis_order, function($a, $b) use ($config) {
        $orderA = $config['annotation_types'][$a]['order'] ?? 999;
        $orderB = $config['annotation_types'][$b]['order'] ?? 999;
        return $orderA - $orderB;
    });
    
    $config['analysis_order'] = $analysis_order;
    $config['analysis_descriptions'] = $analysis_descriptions;
}

// Initialize if empty
if (empty($config) || !isset($config['analysis_order'])) {
    $config = [
        'analysis_order' => [],
        'analysis_descriptions' => [],
        'organisms' => []
    ];
}

$message = "";
$messageType = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$file_write_error) {
    if (isset($_POST['add_section'])) {
        $section_name = trim($_POST['section_name'] ?? '');
        $section_desc = trim($_POST['section_description'] ?? '');
        
        if (!empty($section_name)) {
            if (!in_array($section_name, $config['analysis_order'])) {
                $config['analysis_order'][] = $section_name;
                if (!empty($section_desc)) {
                    $config['analysis_descriptions'][$section_name] = $section_desc;
                }
                
                file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
                $message = "Section added successfully!";
                $messageType = "success";
            } else {
                $message = "Section already exists.";
                $messageType = "warning";
            }
        }
    }
    
    if (isset($_POST['update_order'])) {
        $new_order = json_decode($_POST['order_data'], true);
        if ($new_order) {
            $config['analysis_order'] = $new_order;
            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
            $message = "Order updated successfully!";
            $messageType = "success";
        }
    }
    
    if (isset($_POST['update_description'])) {
        $section = $_POST['section'] ?? '';
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($section)) {
            $config['analysis_descriptions'][$section] = $description;
            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
            $message = "Description updated successfully!";
            $messageType = "success";
        }
    }
    
    if (isset($_POST['delete_section'])) {
        $section = $_POST['section'] ?? '';
        
        if (!empty($section)) {
            $config['analysis_order'] = array_values(array_diff($config['analysis_order'], [$section]));
            unset($config['analysis_descriptions'][$section]);
            
            // Remove from all organisms
            foreach ($config['organisms'] as $org => &$org_config) {
                if (isset($org_config['enabled_sections'])) {
                    $org_config['enabled_sections'] = array_values(array_diff($org_config['enabled_sections'], [$section]));
                }
            }
            
            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
            $message = "Section deleted successfully!";
            $messageType = "success";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Manage Annotation Sections</title>
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
</head>
<body class="bg-light">

<div class="container mt-5">
  <?php
  $nav_context = ['page' => 'admin_tool', 'tool' => 'manage_annotations'];
  echo render_navigation_buttons($nav_context);
  ?>
  
  <h2><i class="fa fa-tags"></i> Manage Annotation Sections</h2>

  <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($file_write_error): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h4><i class="fa fa-exclamation-circle"></i> File Permission Issue Detected</h4>
      <p><strong>Problem:</strong> The file <code>metadata/annotation_config.json</code> is not writable by the web server.</p>
      
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

  <!-- Info Panel -->
  <div class="card mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Annotation Sections</h5>
    </div>
    <div class="card-body">
      <p>Annotation sections define the types of analysis results that can be displayed on gene pages. Common sections include Orthology, Domains, Gene Ontology, etc.</p>
      <p class="mb-0">Each section can have a description that helps users understand what that type of analysis means.</p>
    </div>
  </div>

  <!-- Add New Section -->
  <div class="card mb-4">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="fa fa-plus"></i> Add New Section</h5>
    </div>
    <div class="card-body">
      <form method="post">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="section_name" class="form-label">Section Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="section_name" name="section_name" required>
            <small class="text-muted">e.g., "Orthology", "Domains", "Gene Ontology"</small>
          </div>
          <div class="col-md-8 mb-3">
            <label for="section_description" class="form-label">Description</label>
            <textarea class="form-control" id="section_description" name="section_description" rows="2"></textarea>
            <small class="text-muted">Optional: Explain what this section represents</small>
          </div>
        </div>
        <button type="submit" name="add_section" class="btn btn-success" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>
          <i class="fa fa-plus"></i> Add Section
        </button>
      </form>
    </div>
  </div>

  <!-- Current Sections -->
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fa fa-list"></i> Current Sections (<?php echo count($config['analysis_order']); ?>)</h5>
    </div>
    <div class="card-body">
      <?php if (empty($config['analysis_order'])): ?>
        <p class="text-muted">No sections configured yet. Add your first section above.</p>
      <?php else: ?>
        <p class="text-muted mb-3">
          <i class="fa fa-arrows-alt"></i> Drag and drop to reorder sections. This order will be used when displaying annotations.
        </p>
        
        <div id="sortable-sections" class="list-group">
          <?php foreach ($config['analysis_order'] as $section): ?>
            <div class="list-group-item" data-section="<?= htmlspecialchars($section) ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                  <h6 class="mb-1">
                    <i class="fa fa-grip-vertical text-muted"></i>
                    <strong><?= htmlspecialchars($config['analysis_descriptions'][$section]['display_name'] ?? $section) ?></strong>
                  </h6>
                  <p class="mb-2 text-muted" id="desc-<?= htmlspecialchars($section) ?>">
                    <?php 
                      $desc_data = $config['analysis_descriptions'][$section];
                      if (is_array($desc_data)) {
                          echo htmlspecialchars($desc_data['description'] ?? 'No description');
                      } else {
                          echo htmlspecialchars($desc_data ?? 'No description');
                      }
                    ?>
                  </p>
                </div>
                <div class="btn-group">
                  <button class="btn btn-sm btn-outline-primary edit-desc-btn" data-section="<?= htmlspecialchars($section) ?>" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>
                    <i class="fa fa-edit"></i> Edit
                  </button>
                  <button class="btn btn-sm btn-outline-danger delete-section-btn" data-section="<?= htmlspecialchars($section) ?>" <?= $file_write_error ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : '' ?>>
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        
        <button id="saveOrder" class="btn btn-primary mt-3" style="display: none;" <?= $file_write_error ? 'disabled' : '' ?>>
          <i class="fa fa-save"></i> Save New Order
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Edit Description Modal -->
<div class="modal fade" id="editDescModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Description: <span id="editSectionName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="editDescForm">
        <input type="hidden" name="update_description" value="1">
        <input type="hidden" name="section" id="editSection">
        <div class="modal-body">
          <div class="mb-3">
            <label for="editDescription" class="form-label">Description</label>
            <textarea class="form-control" name="description" id="editDescription" rows="4"></textarea>
            <small class="text-muted">HTML tags are allowed for formatting (e.g., &lt;strong&gt;, &lt;em&gt;)</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Permission Error Modal -->
<div class="modal fade" id="permissionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fa fa-exclamation-circle"></i> File Permission Error</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>The annotation configuration file is not writable by the web server.</strong></p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
const allSections = <?= json_encode($config['analysis_order']) ?>;
let originalOrder = [...allSections];

$(document).ready(function() {
    // Make sections sortable
    $('#sortable-sections').sortable({
        handle: '.fa-grip-vertical',
        update: function(event, ui) {
            $('#saveOrder').show();
        }
    });
    
    // Save order button
    $('#saveOrder').on('click', function() {
        const newOrder = [];
        $('#sortable-sections .list-group-item').each(function() {
            newOrder.push($(this).data('section'));
        });
        
        // Create form and submit
        const form = $('<form>', {
            method: 'POST',
            action: 'manage_annotations.php'
        });
        
        $('<input>').attr({
            type: 'hidden',
            name: 'update_order',
            value: '1'
        }).appendTo(form);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'order_data',
            value: JSON.stringify(newOrder)
        }).appendTo(form);
        
        form.appendTo('body').submit();
    });
    
    // Edit description button
    $('.edit-desc-btn').on('click', function() {
        const section = $(this).data('section');
        const currentDesc = $('#desc-' + section).text().trim();
        
        $('#editSectionName').text(section);
        $('#editSection').val(section);
        $('#editDescription').val(currentDesc === 'No description' ? '' : currentDesc);
        
        new bootstrap.Modal($('#editDescModal')).show();
    });
    
    // Delete section button
    $('.delete-section-btn').on('click', function() {
        const section = $(this).data('section');
        
        if (confirm(`Are you sure you want to delete the "${section}" section?\n\nThis will remove it from all organisms.`)) {
            const form = $('<form>', {
                method: 'POST',
                action: 'manage_annotations.php'
            });
            
            $('<input>').attr({
                type: 'hidden',
                name: 'delete_section',
                value: '1'
            }).appendTo(form);
            
            $('<input>').attr({
                type: 'hidden',
                name: 'section',
                value: section
            }).appendTo(form);
            
            form.appendTo('body').submit();
        }
    });
});
</script>

<style>
  #sortable-sections .list-group-item {
    cursor: move;
    transition: background-color 0.2s;
  }
  
  #sortable-sections .list-group-item:hover {
    background-color: #f8f9fa;
  }
  
  .fa-grip-vertical {
    cursor: grab;
    margin-right: 10px;
  }
  
  .ui-sortable-helper {
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  }
</style>

<?php
include_once '../includes/footer.php';
?>

</body>
</html>
