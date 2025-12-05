
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-5">
  <?php
  ?>
  
  <h2><i class="fa fa-tags"></i> Manage Annotation Sections</h2>

  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutAnnotationTypes">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Annotation Types <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutAnnotationTypes">
      <div class="card-body">
        <p><strong>Purpose:</strong> Manage annotation types - the different categories of analysis results stored in your organism databases (e.g., Orthologs, Homologs, Domains, Gene Ontology, Pathways).</p>
        
        <p><strong>Why This Matters:</strong> Annotation types are how results are organized and displayed to users. When users view a gene, they see different annotation categories. This page lets you:</p>
        <ul>
          <li>Control which annotation types appear and in what order</li>
          <li>Create alternate names (synonyms) for database annotation types</li>
          <li>Add descriptions to help users understand each annotation type</li>
          <li>Customize display labels (show "Homologous Proteins" instead of "homolog_search_result")</li>
        </ul>
        
        <p><strong>How It Works:</strong></p>
        <ul>
          <li>The system automatically scans your organism databases for annotation types</li>
          <li>It tracks modification timestamps so counts update automatically</li>
          <li>You can customize how each type appears to users</li>
          <li>Reordering here changes how annotations display on gene pages</li>
        </ul>
        
        <p class="mb-0"><strong>What You Can Do:</strong></p>
        <ul class="mb-0">
          <li>Add synonyms for annotation types (alternate search names)</li>
          <li>Customize the display label shown to users</li>
          <li>Edit descriptions for each annotation type</li>
          <li>Drag and drop to reorder how annotations appear</li>
          <li>Delete annotation types that have no data</li>
          <li>Enable/disable annotation types per organism</li>
        </ul>
      </div>
    </div>
  </div>

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


  <!-- PHASE 3: Annotation Type Configuration -->
  <?php if (!empty($annotation_config['annotation_types'])): ?>
  <div class="card mb-4">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0"><i class="fa fa-cog"></i> Configure Annotation Types</h5>
    </div>
    <div class="card-body">
      <p class="text-muted mb-3">
        <i class="fa fa-arrows-alt"></i> Drag and drop to reorder annotation types. This order will be used when displaying on gene pages.
      </p>
      
      <div id="sortable-annotation-types">
        <?php 
        // Loop through annotation types in the defined order
        $type_order = $annotation_config['annotation_type_order'] ?? array_keys($annotation_config['annotation_types'] ?? []);
        foreach ($type_order as $type_name):
            if (!isset($annotation_config['annotation_types'][$type_name])) continue;
            $type_config = $annotation_config['annotation_types'][$type_name];
        ?>
        <div class="card mb-3" data-type="<?= htmlspecialchars($type_name) ?>" style="cursor: move; touch-action: none;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <h6 class="mb-1">
                  <i class="fa fa-grip-vertical text-muted"></i>
                  <strong><?= htmlspecialchars($type_config['display_label'] ?? $type_name) ?></strong>
                  <span class="badge bg-<?= ($type_config['in_database'] ?? false) ? 'success' : 'warning' ?>" style="margin-left: 8px;">
                    <?= ($type_config['in_database'] ?? false) ? 'In Use' : 'Not In Use' ?>
                  </span>
                </h6>
                <div style="font-size: 0.85rem;"><small class="text-muted">DB Type: <code><?= htmlspecialchars($type_name) ?></code></small></div>
                <?php if (!empty($type_config['synonyms'])): ?>
                <div style="margin-top: 5px;"><small class="text-muted"><?= count($type_config['synonyms']) ?> synonym(s)</small></div>
                <?php endif; ?>
                <p class="mb-0 mt-2 text-muted" id="desc-type-<?= htmlspecialchars($type_name) ?>" data-full-desc="<?= htmlspecialchars($type_config['description'] ?? 'No description') ?>">
                  <?php 
                    $desc = $type_config['description'] ?? 'No description';
                    if (strlen($desc) > 150) {
                      echo htmlspecialchars(substr($desc, 0, 150)) . '...';
                    } else {
                      echo htmlspecialchars($desc);
                    }
                  ?>
                </p>
              </div>
              <div class="btn-group" role="group">
                <button class="btn btn-sm btn-outline-primary edit-type-desc-btn" data-type="<?= htmlspecialchars($type_name) ?>" title="Edit description" <?= $file_write_error ? 'disabled' : '' ?>>
                  <i class="fa fa-edit"></i> Edit description
                </button>
                <button class="btn btn-sm btn-outline-info expand-type-btn" data-type="<?= htmlspecialchars($type_name) ?>" title="Expand details">
                  <i class="fa fa-chevron-down"></i> Customize annotation
                </button>
              </div>
            </div>
          </div>
          
          <!-- Expanded details (hidden by default) -->
          <div class="type-details" id="details-<?= htmlspecialchars($type_name) ?>" style="padding: 15px; border-top: 1px solid #dee2e6; display: none; background-color: #f8f9fa;">
            <div class="row mb-3">
              <!-- Display Label -->
              <div class="col-md-6">
                <h6>Display Label</h6>
                <form method="post" action="manage_annotations.php" class="d-flex gap-2 mb-2" onsubmit="event.stopPropagation(); this.querySelector('input[name=\'_form_action\']').value = 'update_display_label';">
                  <input type="hidden" name="_form_action" value="">
                  <input type="hidden" name="type_name" value="<?= htmlspecialchars($type_name) ?>">
                  <select class="form-select form-select-sm" name="display_label" required>
                    <option value="<?= htmlspecialchars($type_name) ?>" <?= ($type_config['display_label'] ?? $type_name) === $type_name ? 'selected' : '' ?>>
                      <?= htmlspecialchars($type_name) ?>
                    </option>
                    <?php foreach (($type_config['synonyms'] ?? []) as $synonym): ?>
                    <option value="<?= htmlspecialchars($synonym) ?>" <?= ($type_config['display_label'] ?? $type_name) === $synonym ? 'selected' : '' ?>>
                      <?= htmlspecialchars($synonym) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-sm btn-primary" <?= $file_write_error ? 'disabled' : '' ?>>
                    <i class="fa fa-save"></i>
                  </button>
                </form>
                <small class="text-muted">Choose which name displays in the UI</small>
              </div>
              
              <!-- Database Info -->
              <div class="col-md-6">
                <h6>Database Info</h6>
                <small class="text-muted">
                  <div>Annotations: <strong><?= $type_config['annotation_count'] ?? 0 ?></strong></div>
                  <div>Features: <strong><?= $type_config['feature_count'] ?? 0 ?></strong></div>
                </small>
                <?php if (($type_config['annotation_count'] ?? 0) === 0 && ($type_config['feature_count'] ?? 0) === 0): ?>
                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="deleteType('<?= htmlspecialchars($type_name) ?>')">
                  <i class="fa fa-trash"></i> Delete
                </button>
                <?php endif; ?>
              </div>
            </div>
            
            <hr>
            
            <!-- Synonyms Management -->
            <div class="row">
              <div class="col-md-6">
                <h6>Add Synonym</h6>
                <form method="post" action="manage_annotations.php" class="d-flex gap-2 mb-2" onsubmit="event.stopPropagation(); this.querySelector('input[name=\'_form_action\']').value = 'add_synonym';">
                  <input type="hidden" name="_form_action" value="">
                  <input type="hidden" name="type_name" value="<?= htmlspecialchars($type_name) ?>">
                  <input type="text" class="form-control form-control-sm" name="new_synonym" placeholder="Synonym name" required>
                  <button type="submit" class="btn btn-sm btn-success" <?= $file_write_error ? 'disabled' : '' ?>>
                    <i class="fa fa-plus"></i>
                  </button>
                </form>
                <small class="text-muted">Alternative names or aliases</small>
              </div>
              
              <div class="col-md-6">
                <h6>Current Synonyms (<?= count($type_config['synonyms'] ?? []) ?>)</h6>
                <?php if (!empty($type_config['synonyms'])): ?>
                <div class="list-group list-group-sm">
                  <?php foreach ($type_config['synonyms'] as $synonym): ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center">
                    <code><?= htmlspecialchars($synonym) ?></code>
                    <form method="post" action="manage_annotations.php" style="display: inline;" onsubmit="event.stopPropagation(); this.querySelector('input[name=\'_form_action\']').value = 'remove_synonym';">
                      <input type="hidden" name="_form_action" value="">
                      <input type="hidden" name="type_name" value="<?= htmlspecialchars($type_name) ?>">
                      <input type="hidden" name="synonym_to_remove" value="<?= htmlspecialchars($synonym) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" <?= $file_write_error ? 'disabled' : '' ?>>
                        <i class="fa fa-times"></i>
                      </button>
                    </form>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <small class="text-muted">No synonyms added yet</small>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>


</div>

<!-- Edit Type Description Modal -->
<div class="modal fade" id="editTypeDescModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Description: <span id="editTypeDescName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="editTypeDescForm">
        <input type="hidden" name="_form_action" value="update_type_description">
        <input type="hidden" name="type_name" id="editTypeName">
        <div class="modal-body">
          <div class="mb-3">
            <label for="editTypeDescription" class="form-label">Description</label>
            <textarea class="form-control" name="description" id="editTypeDescription" rows="4"></textarea>
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
const allSections = <?= json_encode($annotation_config['analysis_order']) ?>;
let originalOrder = [...allSections];

$(document).ready(function() {
    // Make annotation types sortable - auto-save on stop
    if ($('#sortable-annotation-types').length) {
        console.log('Initializing annotation types sortable');
        console.log('Found ' + $('#sortable-annotation-types .card').length + ' cards to sort');
        
        $('#sortable-annotation-types').sortable({
            handle: '.fa-grip-vertical',
            items: '.card',
            start: function(event, ui) {
                console.log('Drag started');
            },
            change: function(event, ui) {
                console.log('Item moved during drag');
            },
            stop: function(event, ui) {
                console.log('Drag stopped - auto-saving order');
                saveTypeOrder();
            }
        });
    } else {
        console.log('ERROR: #sortable-annotation-types not found');
    }

    
    // Function to save annotation type order
    function saveTypeOrder() {
        const newOrder = [];
        $('#sortable-annotation-types .card').each(function() {
            const type = $(this).data('type');
            if (type) {
                newOrder.push(type);
            }
        });
        
        console.log('saveTypeOrder called');
        console.log('Saving annotation type order:', newOrder);
        console.log('Order array length:', newOrder.length);
        
        if (newOrder.length === 0) {
            console.error('No types found to save!');
            return;
        }
        
        // Use fetch API for more reliable form submission
        fetch('manage_annotations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'update_type_order': '1',
                'type_order_data': JSON.stringify(newOrder)
            })
        })
        .then(response => {
            console.log('Response received:', response.status);
            if (response.ok) {
                console.log('Order saved successfully');
                // Show brief success message
                showSaveNotification('Order saved successfully');
            } else {
                console.error('Server error:', response.status);
                showSaveNotification('Error saving order. Please try again.', 'danger');
            }
        })
        .catch(error => {
            console.error('Error submitting form:', error);
            showSaveNotification('Error saving order: ' + error.message, 'danger');
        });
    }
    
    // Function to show a temporary success/error notification
    function showSaveNotification(message, type = 'success') {
        const alertDiv = $('<div>')
            .addClass('alert alert-' + type + ' alert-dismissible fade show')
            .attr('role', 'alert')
            .html(message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
        
        // Insert at top of page and auto-dismiss after 3 seconds
        $('main, .container, body').first().prepend(alertDiv);
        setTimeout(() => {
            alertDiv.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }
    
    // Edit type description button
    $('.edit-type-desc-btn').on('click', function() {
        const typeName = $(this).data('type');
        const currentDesc = $('#desc-type-' + typeName).data('full-desc') || $('#desc-type-' + typeName).text().trim();
        
        $('#editTypeDescName').text(typeName);
        $('#editTypeName').val(typeName);
        $('#editTypeDescription').val(currentDesc === 'No description' ? '' : currentDesc);
        
        new bootstrap.Modal($('#editTypeDescModal')).show();
    });
});
</script>

<style>
  .fa-grip-vertical {
    cursor: grab;
    margin-right: 10px;
  }
  
  .ui-sortable-helper {
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  }
  
  .spinner-border-sm {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    vertical-align: text-bottom;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
  }
  
  @keyframes spinner-border {
    to { transform: rotate(360deg); }
  }
</style>

<script>
// Add loading spinner to form buttons
document.addEventListener('DOMContentLoaded', function() {
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
      console.log('Form submitted:', this.name || 'unnamed');
      const buttons = this.querySelectorAll('button[type="submit"]');
      buttons.forEach(btn => {
        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) {
          icon.className = 'fa fa-spinner fa-spin';
        }
      });
    });
  });
});

// Toggle type details expand/collapse
function toggleTypeDetails(typeName) {
  const details = document.getElementById('details-' + typeName);
  const button = document.querySelector(`[data-type="${typeName}"].expand-type-btn`);
  if (details) {
    if (details.style.display === 'none') {
      details.style.display = 'block';
      button.querySelector('i').className = 'fa fa-chevron-up';
    } else {
      details.style.display = 'none';
      button.querySelector('i').className = 'fa fa-chevron-down';
    }
  }
}

// Delete annotation type
function deleteType(typeName) {
  if (confirm('Delete annotation type "' + typeName + '"? This cannot be undone.')) {
    const form = document.createElement('form');
    form.method = 'POST';
    
    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'type_name';
    typeInput.value = typeName;
    form.appendChild(typeInput);
    
    const deleteInput = document.createElement('input');
    deleteInput.type = 'hidden';
    deleteInput.name = 'delete_annotation_type';
    deleteInput.value = '1';
    form.appendChild(deleteInput);
    
    document.body.appendChild(form);
    form.submit();
  }
}

// Handle expand button clicks
document.addEventListener('click', function(e) {
  if (e.target.closest('.expand-type-btn')) {
    const button = e.target.closest('.expand-type-btn');
    const typeName = button.getAttribute('data-type');
    toggleTypeDetails(typeName);
  }
});
</script>

<?php
include_once '../includes/footer.php';
