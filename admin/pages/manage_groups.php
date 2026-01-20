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
        
        <p><strong>How It Works:</strong> Each organism assembly gets one or more group tags. Organism assembly names are pulled from their directory names. Users see organisms based on:</p>
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
        
        <hr class="my-3">
        
        <p><strong>Configuration Files:</strong></p>
        <ul class="mb-0">
          <li><strong>Group Assignments:</strong> <code><?= htmlspecialchars($groups_file) ?></code></li>
          <li><strong>Group Descriptions:</strong> <code><?= htmlspecialchars($descriptions_file) ?></code></li>
          <li><strong>Change Log:</strong> <code><?= htmlspecialchars($metadata_path . '/change_log/manage_groups.log') ?></code></li>
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
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <?= htmlspecialchars($_SESSION['error_message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
