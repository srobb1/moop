<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fa fa-cog"></i> Manage Site Configuration</h2>
            <p class="text-muted">Edit site-wide settings and appearance</p>
            
            <!-- About Section -->
            <div class="card mb-4 border-info">
                <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutSiteConfig">
                    <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Site Configuration <i class="fa fa-chevron-down float-end"></i></h5>
                </div>
                <div class="collapse" id="aboutSiteConfig">
                    <div class="card-body">
                        <p><strong>Purpose:</strong> Edit key site-wide settings and appearance without modifying configuration files directly.</p>
                        
                        <p><strong>What You Can Change:</strong></p>
                        <ul>
                            <li><strong>Site Title</strong> - Name displayed in browser tab, header, and throughout the site</li>
                            <li><strong>Admin Email</strong> - Contact email for site administrators</li>
                            <li><strong>Header Banner Image</strong> - Large banner image at the top of pages</li>
                            <li><strong>Favicon</strong> - Small icon displayed in browser tab</li>
                            <li><strong>Sequence Types</strong> - Configure display labels and colors for sequence file types</li>
                            <li><strong>Auto-Login IP Ranges</strong> - Allow automatic login from specific IP addresses (institutional networks)</li>
                        </ul>
                        
                        <p class="mb-0"><small class="text-muted"><i class="fa fa-lock"></i> <strong>Structural settings</strong> (paths, directories, database connections) are not editable to maintain system integrity. <a href="#" data-bs-toggle="modal" data-bs-target="#structuralSettingsModal">Learn how to change these →</a></small></p>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Permission Warning -->
            <?php if ($file_write_error): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <h4><i class="fa fa-exclamation-triangle"></i> File Permission Issue</h4>
                    <p><strong>Problem:</strong> The configuration file is not writable by the web server.</p>
                    
                    <p><strong>Current Status:</strong></p>
                    <ul class="mb-3">
                        <li>File: <code><?= htmlspecialchars($config_file) ?></code></li>
                        <li>Owner: <code><?= htmlspecialchars($file_write_error['owner']) ?></code></li>
                        <li>Group: <code><?= htmlspecialchars($file_write_error['group']) ?></code></li>
                        <li>Permissions: <code><?= $file_write_error['perms'] ?></code></li>
                        <li>Web server user: <code><?= htmlspecialchars($file_write_error['web_user']) ?></code></li>
                    </ul>
                    
                    <p><strong>To Fix:</strong> Run this command on the server:</p>
                    <div style="background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd; margin: 10px 0;">
                        <code style="word-break: break-all; display: block; font-size: 0.9em;">
                            <?= htmlspecialchars($file_write_error['command']) ?>
                        </code>
                    </div>
                    
                    <p><small class="text-muted">After running the command, refresh this page.</small></p>
                </div>
            <?php endif; ?>

            <!-- Configuration Cards -->
            <form method="post" id="configForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_config">
                
                <!-- Site Title Card -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-heading"></i> Site Title</h5>
                    </div>
                    <div class="card-body">
                        <label for="siteTitle" class="form-label">
                            <strong><?= htmlspecialchars($editable_config['siteTitle']['label']) ?></strong>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="siteTitle" 
                            name="siteTitle" 
                            value="<?= htmlspecialchars($editable_config['siteTitle']['current_value']) ?>"
                            maxlength="<?= $editable_config['siteTitle']['max_length'] ?>"
                            required>
                        <small class="form-text text-muted d-block mt-2">
                            <?= htmlspecialchars($editable_config['siteTitle']['description']) ?>
                        </small>
                    </div>
                </div>

                <!-- Admin Email Card -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-envelope"></i> Admin Email</h5>
                    </div>
                    <div class="card-body">
                        <label for="admin_email" class="form-label">
                            <strong><?= htmlspecialchars($editable_config['admin_email']['label']) ?></strong>
                        </label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="admin_email" 
                            name="admin_email" 
                            value="<?= htmlspecialchars($editable_config['admin_email']['current_value']) ?>"
                            maxlength="<?= $editable_config['admin_email']['max_length'] ?>"
                            required>
                        <small class="form-text text-muted d-block mt-2">
                            <?= htmlspecialchars($editable_config['admin_email']['description']) ?>
                        </small>
                    </div>
                </div>

                <!-- Sequence Types Card -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-dna"></i> Sequence Types</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <?= htmlspecialchars($editable_config['sequence_types']['description']) ?>
                        </p>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">Enabled</th>
                                        <th style="width: 22%;">Type</th>
                                        <th style="width: 22%;">Display Label</th>
                                        <th style="width: 22%;">Badge Color</th>
                                        <th style="width: 24%; white-space: nowrap;">Pattern</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($editable_config['sequence_types']['current_value'] as $seq_type => $seq_config): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" 
                                                   name="sequence_types[<?= htmlspecialchars($seq_type) ?>][enabled]" 
                                                   value="1" 
                                                   class="form-check-input" 
                                                   checked>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($seq_type) ?></code>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="sequence_types[<?= htmlspecialchars($seq_type) ?>][label]" 
                                                   value="<?= htmlspecialchars($seq_config['label'] ?? $seq_type) ?>"
                                                   class="form-control form-control-sm"
                                                   placeholder="Display name">
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="text" 
                                                       name="sequence_types[<?= htmlspecialchars($seq_type) ?>][color]" 
                                                       value="<?= htmlspecialchars($seq_config['color'] ?? 'bg-secondary') ?>"
                                                       class="form-control color-input"
                                                       data-seq-type="<?= htmlspecialchars($seq_type) ?>"
                                                       placeholder="e.g., bg-info">
                                                <span class="input-group-text p-0 ps-2">
                                                    <?php 
                                                      $colorInfo = getColorClassOrStyle($seq_config['color'] ?? 'bg-secondary');
                                                    ?>
                                                    <span class="badge text-white px-2 py-1 <?= $colorInfo['class'] ?>" 
                                                          id="badge_<?= htmlspecialchars($seq_type) ?>"
                                                          data-seq-type="<?= htmlspecialchars($seq_type) ?>"
                                                          <?php if ($colorInfo['style']): ?>style="<?= $colorInfo['style'] ?>"<?php endif; ?>>
                                                        <?= htmlspecialchars($seq_config['label'] ?? $seq_type) ?>
                                                    </span>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="small"><?= htmlspecialchars($seq_config['pattern'] ?? '') ?></code>
                                            <input type="hidden" 
                                                   name="sequence_types[<?= htmlspecialchars($seq_type) ?>][pattern]" 
                                                   value="<?= htmlspecialchars($seq_config['pattern'] ?? '') ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <small class="form-text text-muted d-block mt-2">
                            <i class="fa fa-info-circle"></i> 
                            <strong>File patterns:</strong> Read-only and match files in organism directories<br>
                            <strong>Badge colors:</strong> Use Bootstrap CSS classes (e.g., bg-info, bg-success, bg-warning, bg-danger). Preview updates as you type.
                        </small>
                    </div>
                </div>

                <!-- Header Image Card -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-image"></i> Header Banner Image</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <?= htmlspecialchars($editable_config['header_img']['description']) ?>
                        </p>
                        
                        <div class="alert alert-info mb-3">
                            <strong>Recommended:</strong> <?= $editable_config['header_img']['upload_info']['recommended_dimensions'] ?> <br>
                            <strong>Accepted:</strong> <?= implode(', ', array_map('strtoupper', $editable_config['header_img']['upload_info']['allowed_types'])) ?> <br>
                            <strong>Max size:</strong> <?= $editable_config['header_img']['upload_info']['max_size_mb'] ?> MB
                        </div>
                        
                        <div class="mb-4">
                            <label for="header_upload" class="form-label"><strong>Upload New Banner Image:</strong></label>
                            <div class="input-group">
                                <input type="file" class="form-control" id="header_upload" name="header_upload" accept="image/*">
                                <button type="button" class="btn btn-outline-primary" id="uploadHeaderBtn">
                                    <i class="fa fa-upload"></i> Upload
                                </button>
                            </div>
                            <small class="form-text text-muted">Leave empty to keep current image</small>
                        </div>
                        
                        <!-- Banner Images Gallery -->
                        <?php if (!empty($banner_images)): ?>
                        <div>
                            <label class="form-label"><strong>Available Banner Images:</strong></label>
                            <p class="text-muted small mb-3">Select fallback image to use if banner directory is empty.</p>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">Use as<br>Fallback</th>
                                            <th style="width: 150px;">Preview</th>
                                            <th>Filename</th>
                                            <th style="width: 80px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($banner_images as $idx => $banner_file): ?>
                                        <tr class="<?= $idx % 2 === 0 ? 'table-light' : '' ?>">
                                            <td class="text-center">
                                                <input type="radio" 
                                                       name="header_img" 
                                                       value="<?= htmlspecialchars($banner_file) ?>"
                                                       <?= ($editable_config['header_img']['current_value'] === $banner_file) ? 'checked' : '' ?>>
                                            </td>
                                            <td>
                                                <img src="/<?= htmlspecialchars($config->getPath('images_path')) ?>/banners/<?= htmlspecialchars($banner_file) ?>" 
                                                     class="img-fluid" 
                                                     style="max-height: 100px; max-width: 150px; object-fit: cover;"
                                                     alt="<?= htmlspecialchars($banner_file) ?>">
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($banner_file) ?></code>
                                                <?php if ($editable_config['header_img']['current_value'] === $banner_file): ?>
                                                <br><span class="badge bg-primary">Current Fallback</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger delete-banner" data-filename="<?= htmlspecialchars($banner_file) ?>">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fa fa-info-circle"></i> No banner images found in <code><?= htmlspecialchars($banners_path) ?></code>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Favicon Card -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-icon"></i> Favicon</h5>
                    </div>
                    <div class="card-body">
                        <!-- Current Favicon Preview -->
                        <?php if (!empty($editable_config['favicon_filename']['current_value'])): ?>
                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="d-flex align-items-center gap-3">
                                    <div>
                                        <small class="text-muted d-block mb-2"><strong>Current Favicon:</strong></small>
                                        <img id="favicon_preview" 
                                             src="<?= '/' . $config->getString('images_path') . '/' . htmlspecialchars($editable_config['favicon_filename']['current_value']) . '?t=' . time() ?>" 
                                             alt="Current Favicon" 
                                             style="width: 64px; height: 64px; border: 1px solid #ddd; padding: 4px; background: white;">
                                    </div>
                                    <div>
                                        <small class="text-muted"><?= htmlspecialchars($editable_config['favicon_filename']['current_value']) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <label for="favicon_upload" class="form-label">
                            <strong><?= htmlspecialchars($editable_config['favicon_filename']['label']) ?></strong>
                        </label>
                        
                        <!-- File Upload Input -->
                        <div class="input-group mb-3">
                            <input 
                                type="file" 
                                class="form-control" 
                                id="favicon_upload" 
                                name="favicon_upload" 
                                accept=".ico,.png,.jpg,.jpeg,.gif,.webp">
                            <button type="submit" class="btn btn-outline-primary" id="uploadFaviconBtn" name="action" value="save_config">
                                <i class="fa fa-upload"></i> Upload
                            </button>
                        </div>
                        
                        <!-- Upload Preview (before submit) -->
                        <div id="favicon_upload_preview" class="mt-3" style="display: none;">
                            <small class="text-muted d-block mb-2"><strong>New Favicon Preview:</strong></small>
                            <img id="favicon_new_preview" 
                                 alt="New Favicon Preview" 
                                 style="width: 64px; height: 64px; border: 1px solid #ddd; padding: 4px; background: white;">
                        </div>
                        
                        <small class="form-text text-muted d-block mt-2">
                            <?= htmlspecialchars($editable_config['favicon_filename']['description']) ?> <br>
                            <strong>Recommended:</strong> <?= $editable_config['favicon_filename']['upload_info']['recommended_dimensions'] ?> <br>
                            <strong>Accepted:</strong> <?= implode(', ', array_map('strtoupper', $editable_config['favicon_filename']['upload_info']['allowed_types'])) ?> <br>
                            <strong>Max size:</strong> <?= $editable_config['favicon_filename']['upload_info']['max_size_mb'] ?> MB
                        </small>
                    </div>
                </div>
                
                <!-- Auto-Login IP Ranges Card -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-network-wired"></i> Auto-Login IP Ranges</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-3">
                            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($editable_config['auto_login_ip_ranges']['note']) ?>
                        </div>
                        <p class="text-muted small mb-3">
                            <?= htmlspecialchars($editable_config['auto_login_ip_ranges']['description']) ?>
                        </p>
                        
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>IP Range Start</th>
                                        <th>IP Range End</th>
                                        <th style="width: 60px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="ip_ranges_tbody">
                                    <?php if (empty($editable_config['auto_login_ip_ranges']['current_value'])): ?>
                                    <tr class="no-ranges-row">
                                        <td colspan="3" class="text-center text-muted"><em>No IP ranges configured</em></td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($editable_config['auto_login_ip_ranges']['current_value'] as $idx => $range): ?>
                                    <tr class="ip-range-row">
                                        <td>
                                            <input type="text" 
                                                   name="auto_login_ip_ranges[<?= $idx ?>][start]" 
                                                   value="<?= htmlspecialchars($range['start']) ?>"
                                                   class="form-control form-control-sm"
                                                   placeholder="192.168.1.0">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="auto_login_ip_ranges[<?= $idx ?>][end]" 
                                                   value="<?= htmlspecialchars($range['end']) ?>"
                                                   class="form-control form-control-sm"
                                                   placeholder="192.168.1.255">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger remove-ip-range">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="add_ip_range">
                            <i class="fa fa-plus"></i> Add IP Range
                        </button>
                        <small class="form-text text-muted d-block mt-2">
                            Example: 127.0.0.0 to 127.0.0.255 for localhost
                        </small>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="d-flex gap-2 mb-4">
                    <button type="submit" class="btn btn-primary btn-lg" id="saveBtn" <?= !$file_writable ? 'disabled' : '' ?>>
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                    <button type="reset" class="btn btn-secondary btn-lg">
                        <i class="fa fa-undo"></i> Reset
                    </button>
                </div>
            </form>

            <!-- Change History -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fa fa-history"></i> Recent Changes</h5>
                </div>
                <div class="card-body">
                    <?php
                    $editable_json = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : null;
                    if ($editable_json && isset($editable_json['_metadata'])):
                        $metadata = $editable_json['_metadata'];
                    ?>
                        <ul class="list-unstyled">
                            <li>
                                <strong>Last updated:</strong> 
                                <?= isset($metadata['last_updated']) ? htmlspecialchars($metadata['last_updated']) : 'Never' ?>
                            </li>
                            <li>
                                <strong>Updated by:</strong> 
                                <?= isset($metadata['last_updated_by']) ? htmlspecialchars($metadata['last_updated_by']) : 'Unknown' ?>
                            </li>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No changes recorded yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Structural Settings Tutorial Modal -->
<style>
.modal-body .nav-tabs .nav-link {
    color: #212529 !important;
}
.modal-body .nav-tabs .nav-link.active {
    color: #212529 !important;
}
</style>
<div class="modal fade" id="structuralSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-lock"></i> Structural Settings Tutorial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-4"><i class="fa fa-info-circle"></i> These settings control core system paths and structure. They are protected from the web interface to prevent accidental misconfigurations.</p>

                <!-- Tabs for different settings -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="root-path-tab" data-bs-toggle="tab" data-bs-target="#root-path-content" type="button">Root Path</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="site-dir-tab" data-bs-toggle="tab" data-bs-target="#site-dir-content" type="button">Site Directory</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="organism-path-tab" data-bs-toggle="tab" data-bs-target="#organism-path-content" type="button">Organism Path</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="metadata-path-tab" data-bs-toggle="tab" data-bs-target="#metadata-path-content" type="button">Metadata Path</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Root Path Tab -->
                    <div class="tab-pane fade show active" id="root-path-content">
                        <h6><strong>Root Path</strong></h6>
                        <p class="text-muted small">The base directory where the application and all its data are stored on the server.</p>
                        
                        <div class="alert alert-light border mb-3">
                            <p class="mb-1"><small class="text-muted"><strong>Currently set in your config file:</strong></small></p>
                            <p class="mb-1"><code>$root_path = '<?= htmlspecialchars($root_path) ?>';</code></p>
                            <p class="mb-0"><small class="text-muted"><code>config/site_config.php</code>, line 29</small></p>
                        </div>

                        <p><strong>How to Change:</strong></p>
                        <ol class="small">
                            <li>Connect to your server via SSH or file manager</li>
                            <li>Open <code><?= htmlspecialchars($site_path) ?>/config/site_config.php</code></li>
                            <li>Find line 29: <code>$root_path = '/var/www/html';</code></li>
                            <li>Change the path to your desired root directory</li>
                            <li>Save the file and verify all paths still point to valid locations</li>
                        </ol>

                        <div class="alert alert-warning small mb-0">
                            <i class="fa fa-exclamation-triangle"></i> <strong>⚠️ Important:</strong> Changing the root path affects all derived paths. Ensure the new path exists and has proper permissions before changing.
                        </div>
                    </div>

                    <!-- Site Directory Tab -->
                    <div class="tab-pane fade" id="site-dir-content">
                        <h6><strong>Site Directory</strong></h6>
                        <p class="text-muted small">The subdirectory name where this specific MOOP installation lives. Useful for multi-site deployments.</p>
                        
                        <div class="alert alert-light border mb-3">
                            <p class="mb-1"><small class="text-muted"><strong>Currently set in your config file:</strong></small></p>
                            <p class="mb-1"><code class="text-dark">$site = '<?= htmlspecialchars($site) ?>';</code></p>
                            <p class="mb-0"><small class="text-muted"><code>config/site_config.php</code>, line 30</small></p>
                        </div>

                        <p><strong>Example Use Cases:</strong></p>
                        <ul class="small">
                            <li><code>moop</code> - Default installation at <code>/var/www/html/moop</code></li>
                            <li><code>simrbase</code> - Alternative installation at <code>/var/www/html/simrbase</code></li>
                            <li><code>public_genomes</code> - Public genome repository at <code>/var/www/html/public_genomes</code></li>
                        </ul>

                        <p><strong>How to Change for Multi-Site Deployment:</strong></p>
                        <ol class="small">
                            <li>Connect to your server via SSH</li>
                            <li>Open <code><?= htmlspecialchars($root_path) ?>/moop/config/site_config.php</code></li>
                            <li>Find line 30: <code>$site = 'moop';</code></li>
                            <li>Change to your new directory name (e.g., <code>'simrbase'</code>)</li>
                            <li>Ensure the directory exists at <code><?= htmlspecialchars($root_path) ?>/your_directory</code></li>
                            <li>Verify file permissions are correct (see Filesystem Permissions admin page)</li>
                        </ol>

                        <div class="alert alert-info small mb-0">
                            <i class="fa fa-lightbulb"></i> <strong>Tip:</strong> All URLs and paths will automatically update based on this setting. You can run multiple MOOP instances with different site directories on the same server.
                        </div>
                    </div>

                    <!-- Organism Path Tab -->
                    <div class="tab-pane fade" id="organism-path-content">
                        <h6><strong>Organism Data Path</strong></h6>
                        <p class="text-muted small">Directory containing all organism data: databases, FASTA files, and metadata.</p>
                        
                        <div class="alert alert-light border mb-3">
                            <p class="mb-1"><small class="text-muted"><strong>Currently set in your config file:</strong></small></p>
                            <p class="mb-1"><code class="text-dark">'organism_data' => '<?= htmlspecialchars($organism_data) ?>'</code></p>
                            <p class="mb-0"><small class="text-muted"><code>config/site_config.php</code>, line 41</small></p>
                        </div>

                        <p><strong>What This Directory Contains:</strong></p>
                        <ul class="small">
                            <li><code>organisms/OrganismName/</code> - Subdirectory per organism</li>
                            <li><code>organisms/OrganismName/organism.sqlite</code> - Gene/protein database</li>
                            <li><code>organisms/OrganismName/assembly_name/</code> - Assembly subdirectories</li>
                            <li><code>organisms/OrganismName/assembly_name/*.fa</code> - FASTA sequence files</li>
                            <li><code>organisms/OrganismName/organism.json</code> - Organism metadata</li>
                        </ul>

                        <p><strong>How to Change:</strong></p>
                        <ol class="small">
                            <li>Connect to your server and create the new directory (e.g., <code><?= htmlspecialchars($root_path) ?>/organisms_v2</code>)</li>
                            <li>Copy all organism data from the current location to the new location</li>
                            <li>Open <code>config/site_config.php</code> and modify line 41</li>
                            <li>Update the path in the derived path calculation or directly set: <code>'organism_data' => '<?= htmlspecialchars($root_path) ?>/organisms_v2'</code></li>
                            <li>Verify file permissions using the Filesystem Permissions admin page</li>
                        </ol>

                        <div class="alert alert-warning small mb-0">
                            <i class="fa fa-exclamation-triangle"></i> <strong>⚠️ Important:</strong> Changing this path will break the system if organism data isn't copied to the new location. Always backup before making changes.
                        </div>
                    </div>

                    <!-- Metadata Path Tab -->
                    <div class="tab-pane fade" id="metadata-path-content">
                        <h6><strong>Metadata Path</strong></h6>
                        <p class="text-muted small">Directory containing system configuration files like taxonomy, annotations, and group definitions.</p>
                        
                        <div class="alert alert-light border mb-3">
                            <p class="mb-1"><small class="text-muted"><strong>Currently set in your config file:</strong></small></p>
                            <p class="mb-1"><code class="text-dark">'metadata_path' => '<?= htmlspecialchars($metadata_path) ?>'</code></p>
                            <p class="mb-0"><small class="text-muted"><code>config/site_config.php</code>, line 49</small></p>
                        </div>

                        <p><strong>What This Directory Contains:</strong></p>
                        <ul class="small">
                            <li><code>annotation_config.json</code> - Annotation settings and feature types</li>
                            <li><code>taxonomy_tree_config.json</code> - Taxonomy tree configuration</li>
                            <li><code>group_descriptions.json</code> - User group definitions</li>
                            <li><code>organism_assembly_groups.json</code> - Organism-to-group mappings</li>
                            <li><code>backups/</code> - Automatic configuration backups</li>
                            <li><code>change_log/</code> - Audit trail of changes</li>
                        </ul>

                        <p><strong>How to Change:</strong></p>
                        <ol class="small">
                            <li>Create a new metadata directory (e.g., <code><?= htmlspecialchars($root_path) ?>/metadata_v2</code>)</li>
                            <li>Copy all files from the current metadata directory to the new location</li>
                            <li>Open <code>config/site_config.php</code> and modify line 49</li>
                            <li>Update: <code>'metadata_path' => '<?= htmlspecialchars($root_path) ?>/metadata_v2'</code></li>
                            <li>Verify permissions on the Filesystem Permissions admin page</li>
                        </ol>

                        <div class="alert alert-info small mb-0">
                            <i class="fa fa-lightbulb"></i> <strong>Tip:</strong> This directory should have SGID permissions (2775) so new backups and change logs automatically get the correct group ownership.
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="mb-3"><strong>All Changeable Configuration Variables</strong></h6>
                <p class="text-muted small mb-3">Reference table of variables you can modify in <code>config/site_config.php</code></p>
                
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 25%;">Variable</th>
                                <th style="width: 25%;">Current Value</th>
                                <th style="width: 50%;">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>$root_path</code></td>
                                <td><code><?= htmlspecialchars($root_path) ?></code></td>
                                <td>Base directory where all application data is stored. Change if relocating the entire installation.</td>
                            </tr>
                            <tr>
                                <td><code>$site</code></td>
                                <td><code><?= htmlspecialchars($site) ?></code></td>
                                <td>Subdirectory name for this installation. Change to deploy multiple MOOP instances on the same server.</td>
                            </tr>
                            <tr>
                                <td><code>$site_path</code></td>
                                <td><code><?= htmlspecialchars($site_path) ?></code></td>
                                <td>Derived from root_path and site. Auto-calculated, no need to edit directly.</td>
                            </tr>
                            <tr>
                                <td><code>organism_data</code></td>
                                <td><code><?= htmlspecialchars($organism_data) ?></code></td>
                                <td>Directory containing all organism databases and FASTA files. Change if moving organisms to a different location.</td>
                            </tr>
                            <tr>
                                <td><code>metadata_path</code></td>
                                <td><code><?= htmlspecialchars($metadata_path) ?></code></td>
                                <td>Directory for system configs (taxonomy, annotations, groups). Change if using a separate storage location.</td>
                            </tr>
                            <tr>
                                <td><code>images_dir</code></td>
                                <td><code>images</code></td>
                                <td>Name of the images subdirectory. Rarely changed unless you have a custom setup.</td>
                            </tr>
                            <tr>
                                <td><code>docs_path</code></td>
                                <td><code><?= htmlspecialchars($site_path) ?>/docs</code></td>
                                <td>Directory for documentation and README files.</td>
                            </tr>
                            <tr>
                                <td><code>users_file</code></td>
                                <td><code><?= htmlspecialchars($root_path) ?>/users.json</code></td>
                                <td>Path to user credentials file. Change only if using a different user storage location.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <hr class="my-4">

                <h6 class="mb-3"><strong>General Tips for Changing Structural Settings</strong></h6>
                <ul class="small">
                    <li><strong>Backup First:</strong> Always backup <code>config/site_config.php</code> before making changes</li>
                    <li><strong>One Change at a Time:</strong> Change one setting at a time and test thoroughly</li>
                    <li><strong>Verify Permissions:</strong> Use the "Filesystem Permissions" admin page to ensure new paths have correct ownership and permissions</li>
                    <li><strong>Test After Changes:</strong> Navigate the site and verify all functionality works after making changes</li>
                    <li><strong>Check Logs:</strong> Review server logs and application logs for any errors after changes</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
