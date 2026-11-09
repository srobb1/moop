<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
              <h2 class="mb-0"><i class="fa fa-cog"></i> Manage Site Configuration</h2>
              <?= help_modal_trigger('siteconfig-guide', 'How this page works') ?>
            </div>
            <p class="text-muted">Edit site-wide settings and appearance</p>
            

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
                    <?= adm_command($file_write_error['command']) ?>
                    
                    <p><small class="text-muted">After running the command, refresh this page.</small></p>
                </div>
            <?php endif; ?>

            <!-- Section navigation — sidebar (wide) / jump-to bar (narrow). The TOC is built
                 at runtime by js/modules/parent-nav.js from the [data-nav-label] sections below,
                 the same module the gene page uses, so the two pages behave identically. -->
            <div class="pnav-jumpbar">
              <button class="pnav-jb-btn" id="pnavJbBtn" type="button" aria-expanded="false" aria-controls="pnavJbDd">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2 4h12v1.6H2zM2 7.2h12v1.6H2zM2 10.4h8V12H2z"/></svg>
                Jump to
                <svg class="pnav-jb-chev" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
              </button>
              <span class="pnav-jb-current" id="pnavJbCurrent">Site Title</span>
              <div class="pnav-jb-dd" id="pnavJbDd"></div>
            </div>

            <div class="pnav-layout">
              <aside class="pnav-side" aria-label="Jump to">
                <div class="pnav-side-head">
                  <span class="pnav-side-title">Jump to</span>
                  <button class="pnav-toggle" id="pnavToggle" type="button" aria-label="Collapse navigation" aria-expanded="true" title="Collapse (content goes full width)">
                    <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
                  </button>
                </div>
                <div class="pnav-scroll" id="pnavToc"></div>
              </aside>

              <div class="pnav-main">

            <!-- Configuration Cards -->
            <form method="post" id="configForm" enctype="multipart/form-data">
                <?= csrf_input_field() ?>
                <input type="hidden" name="action" value="save_config">
                
                <!-- Site Title Card -->
                <div class="card adm-card mb-3" id="pnav-site-title" data-nav-label="Site Title">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-heading"></i> <?= htmlspecialchars($editable_config['siteTitle']['label']) ?></h5>
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
                <div class="card adm-card mb-3" id="pnav-admin-email" data-nav-label="Administrator Email">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-envelope"></i> <?= htmlspecialchars($editable_config['admin_email']['label']) ?></h5>
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
                <div class="card adm-card mb-3" id="pnav-sequence-types" data-nav-label="Sequence File Types">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-dna"></i> <?= htmlspecialchars($editable_config['sequence_types']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <?= htmlspecialchars($editable_config['sequence_types']['description']) ?>
                        </p>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 14%;">Type</th>
                                        <th style="width: 16%;">Display Label</th>
                                        <th style="width: 24%;">Badge Color</th>
                                        <th style="width: 32%;">File Name</th>
                                        <th style="width: 14%; white-space: nowrap;">Files Found</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($editable_config['sequence_types']['current_value'] as $seq_type => $seq_config): ?>
                                    <tr>
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
                                            <?php $_m = $sequence_type_matches[$seq_type] ?? 0; ?>
                                            <input type="text"
                                                   class="form-control form-control-sm font-monospace"
                                                   name="sequence_types[<?= htmlspecialchars($seq_type) ?>][pattern]"
                                                   value="<?= htmlspecialchars($seq_config['pattern'] ?? '') ?>"
                                                   aria-describedby="patmatch-<?= htmlspecialchars($seq_type) ?>"
                                                   required>
                                        </td>
                                        <td class="text-nowrap align-middle">
                                            <?php if ($_m > 0): ?>
                                                <span id="patmatch-<?= htmlspecialchars($seq_type) ?>" class="cfg-match-ok">
                                                    <i class="fa fa-check-circle"></i> <?= number_format($_m) ?>
                                                </span>
                                            <?php else: ?>
                                                <span id="patmatch-<?= htmlspecialchars($seq_type) ?>" class="cfg-match-none"
                                                      title="No file with this name exists in any gene-set directory">
                                                    <i class="fa fa-exclamation-circle"></i> none
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <small class="form-text text-muted d-block mt-2">
                            <i class="fa fa-info-circle"></i> 
                            <strong>File names:</strong> see the explanation below the table.<br>
                            <strong>Badge colors:</strong> Use Bootstrap CSS classes (e.g., bg-info, bg-success, bg-warning, bg-danger). Preview updates as you type.
                        </small>

                        <div class="alert alert-light border mt-3 mb-0 small">
                            <p class="mb-2"><strong><i class="fa fa-info-circle"></i> What the file name means</strong></p>
                            <p class="mb-2">
                                This is the <strong>exact file name</strong> that every organism must use for this
                                sequence type. It is not a search pattern and not a wildcard — the site looks for
                                this precise name inside each gene-set directory, and finds nothing else.
                            </p>
                            <p class="mb-1">With the file name <code>protein.aa.fa</code>, every organism needs:</p>
                            <ul class="mb-2">
                                <li><code>organisms/<em>Organism</em>/<em>Assembly</em>/<em>GeneSet</em>/protein.aa.fa</code>
                                    &nbsp;<span class="text-success fw-semibold">found</span></li>
                                <li><code>&hellip;/<em>GeneSet</em>/Nvec.protein.aa.fa</code>
                                    &nbsp;<span class="text-danger fw-semibold">not found</span> — prefixes are a different name</li>
                                <li><code>&hellip;/<em>GeneSet</em>/protein.fa</code>
                                    &nbsp;<span class="text-danger fw-semibold">not found</span></li>
                            </ul>
                            <p class="mb-2">
                                <i class="fa fa-exclamation-triangle text-warning"></i>
                                Changing this changes what the site looks for <strong>everywhere at once</strong> —
                                BLAST databases, FASTA downloads and sequence search all use it. It does not rename
                                anything on disk, so a new name only works once every organism's files already use it.
                            </p>
                            <p class="mb-0">
                                <strong>Files Found</strong> is how many files match that name right now, across every
                                organism, assembly and gene set. If a change takes a count to zero, that sequence type
                                disappears from the whole site &mdash; the files are still there, but nothing looks for
                                them any more.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Header Image Card -->
                <div class="card adm-card mb-3" id="pnav-banner" data-nav-label="Header Banner Images">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-image"></i> <?= htmlspecialchars($editable_config['header_img']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <?= htmlspecialchars($editable_config['header_img']['description']) ?>
                        </p>
                        
                        <div class="cfg-note mb-3">
                            <strong>Recommended:</strong> <?= $editable_config['header_img']['upload_info']['recommended_dimensions'] ?> <br>
                            <strong>Accepted:</strong> <?= implode(', ', array_map('strtoupper', $editable_config['header_img']['upload_info']['allowed_types'])) ?> <br>
                            <strong>Max size:</strong> <?= $editable_config['header_img']['upload_info']['max_size_mb'] ?> MB
                        </div>

                        <div class="alert alert-light border small mb-3">
                            <p class="mb-2"><strong><i class="fa fa-random"></i> How the banner is chosen</strong></p>
                            <ul class="mb-2">
                                <li><strong>The home page always shows the image selected below</strong>, so the
                                    site's front door looks the same on every visit.</li>
                                <li><strong>Every other page picks one at random per page load</strong> from the
                                    images in <code>images/banners/</code> — not a sequence, and not the order
                                    shown here. Reloading usually shows a different one.</li>
                                <li><strong>Uploading adds an image to the rotation</strong>; deleting removes it.
                                    There is no per-image setting and no ordering.</li>
                            </ul>
                            <p class="mb-0">
                                <?php $_bn = count($banner_images ?? []); ?>
                                <i class="fa fa-info-circle"></i>
                                <strong><?= $_bn ?> image<?= $_bn === 1 ? '' : 's' ?></strong> in the rotation.
                            </p>
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
                            <p class="text-muted small mb-3">Every image below is in the rotation shown on ordinary pages.
                                Choose one to pin as the <strong>home page banner</strong>.</p>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 90px;">Home page<br>banner</th>
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
                                                <br><span class="badge bg-info text-dark">Home page banner</span>
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
                <div class="card adm-card mb-3" id="pnav-favicon" data-nav-label="Favicon Image">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-star"></i> <?= htmlspecialchars($editable_config['favicon_filename']['label']) ?></h5>
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
                <div class="card adm-card mb-3" id="pnav-colors" data-nav-label="Site Colors">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-palette"></i> <?= htmlspecialchars($editable_config['theme_colors']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?= htmlspecialchars($editable_config['theme_colors']['description']) ?></p>
                        <?php
                        // lib/theme_colors.php works the palette out; js/modules/site-colors.js asks
                        // admin/api/theme_preview.php for it as the fields change (one implementation).
                        $tc = (array) $editable_config['theme_colors']['current_value'];
                        $tc_fields = [
                            'main'     => ['Main color', 'Tool headers, buttons, links, washes, organism headers.'],
                            'assembly' => ['Assembly color', 'Assembly and gene-set headers. Empty: worked out from the main color.'],
                            'action'   => ['Action color', 'The main button on a tool page. Empty: worked out from the main color.'],
                        ];
                        ?>
                        <div id="siteColors" class="row g-3">
                            <div class="col-lg-6">
                            <?php foreach ($tc_fields as $tc_key => [$tc_label, $tc_help]): $tc_val = (string) ($tc[$tc_key] ?? ''); ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-1" for="tc-<?= $tc_key ?>"><?= htmlspecialchars($tc_label) ?></label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="color" class="form-control form-control-color tc-picker" data-for="tc-<?= $tc_key ?>"
                                               value="<?= htmlspecialchars($tc_val !== '' ? $tc_val : ($tc_key === 'main' ? '#0891b2' : '#888888')) ?>" aria-label="<?= htmlspecialchars($tc_label) ?> picker">
                                        <input type="text" class="form-control tc-hex" id="tc-<?= $tc_key ?>" name="theme_colors[<?= $tc_key ?>]"
                                               value="<?= htmlspecialchars($tc_val) ?>" placeholder="<?= $tc_key === 'main' ? 'empty = MOOP teal' : 'empty = suggested' ?>"
                                               pattern="#[0-9a-fA-F]{6}" maxlength="7" style="max-width: 10rem;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary tc-clear" data-for="tc-<?= $tc_key ?>">Clear</button>
                                    </div>
                                    <div class="form-text"><?= htmlspecialchars($tc_help) ?></div>
                                </div>
                            <?php endforeach; ?>
                                <div id="tcWarning" class="alert alert-warning small py-2 d-none"></div>
                            </div>
                            <div class="col-lg-6">
                                <div class="small fw-semibold mb-1">Preview</div>
                                <div id="tcPreview" class="border rounded p-2">
                                    <div class="rounded px-3 py-2 mb-2 text-white fw-semibold" style="background: var(--moop-tool-header);">Tool page header</div>
                                    <div class="d-flex gap-2 mb-2">
                                        <span class="btn btn-sm text-white" style="background: var(--moop-action);">Run search</span>
                                        <span class="btn btn-sm text-white" style="background: var(--moop-tool-hover);">Hover</span>
                                        <a href="#" class="small align-self-center" style="color: var(--moop-tool-header);" onclick="return false;">A link</a>
                                    </div>
                                    <div class="rounded px-3 py-2 mb-2 small" style="background: rgba(var(--moop-main-rgb), 0.07); border-left: 3px solid var(--moop-tool-header); color: var(--moop-main-dark);">A light wash, as on admin cards</div>
                                    <div class="d-flex gap-2">
                                        <div class="rounded px-3 py-2 text-white small flex-fill" style="background: var(--moop-organism-header);">Organism header</div>
                                        <div class="rounded px-3 py-2 text-white small flex-fill" style="background: var(--moop-assembly-header);">Assembly header</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-2" id="tcSwatches"></div>
                                </div>
                                <div class="form-text">The page itself changes after you save.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card adm-card mb-3" id="pnav-ip-ranges" data-nav-label="Auto-Login IP Ranges">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-network-wired"></i> <?= htmlspecialchars($editable_config['auto_login_ip_ranges']['label']) ?></h5>
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

                <!-- Ontologies (PAGE search) -->
                <?php
                require_once __DIR__ . '/../../lib/ontologies.php';
                $onto_rows   = $config->getArray('ontologies', []);
                $onto_status = ($_SESSION['ontologies']['ontologies'] ?? []);
                $onto_check  = $_SESSION['ontologies']['checked_at'] ?? null;
                $onto_purposes = ['anatomy' => 'Anatomy', 'phenotype' => 'Phenotype', 'evidence' => 'Evidence', 'names' => 'Extra names only'];
                $onto_rows[''] = ['name' => '', 'purpose' => '', 'terms' => '', 'closure' => '', 'releases' => '', 'search' => false, 'relations' => []];
                ?>
                <div class="card adm-card mb-3" id="pnav-ontologies" data-nav-label="Ontologies">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-sitemap"></i> Ontologies</h5>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="ontologies_form" value="1">
                        <p class="text-muted small mb-2">The ontologies the PAGE search uses, by <strong>purpose</strong>: <em>Anatomy</em> (where a gene is expressed, life-cycle stages), <em>Phenotype</em>, <em>Evidence</em> (how it was observed), or <em>Extra names only</em> (to name terms from another ontology, e.g. a term an obsolete one was replaced by). Each needs a <strong>terms</strong> file, and anatomy, phenotype and evidence also a <strong>link-tree</strong> file, in the format of <a href="https://github.com/planosphere/PAGE/tree/master/ontology" target="_blank" rel="noopener">planosphere/PAGE ontology/</a> (made by its GitHub Action). Files are accepted only from <code>https://raw.githubusercontent.com/</code>.</p>
                        <p class="text-muted small mb-3">Each ontology is a separate read-only file in the cache folder, rebuilt on its own: weekly when its <code>releases.txt</code> changes, or now with <strong>Rebuild now</strong>. Nothing is rebuilt on a site where none has been built yet.</p>
                        <?php foreach ($onto_rows as $op => $o): $i = $op === '' ? 'new' : $op; $st = $op === '' ? [] : moop_ontology_meta($op); $hs = $onto_status[$op] ?? []; ?>
                        <div class="border rounded p-2 mb-2<?= $op === '' ? ' bg-light' : '' ?>">
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-2"><label class="form-label small mb-0">Prefix</label>
                                    <input type="text" class="form-control form-control-sm font-monospace" name="ontologies[<?= htmlspecialchars($i) ?>][prefix]" value="<?= htmlspecialchars($op) ?>" placeholder="<?= $op === '' ? 'add: e.g. PLANP' : '' ?>"></div>
                                <div class="col-sm-4"><label class="form-label small mb-0">Name</label>
                                    <input type="text" class="form-control form-control-sm" name="ontologies[<?= htmlspecialchars($i) ?>][name]" value="<?= htmlspecialchars((string) ($o['name'] ?? '')) ?>"></div>
                                <div class="col-sm-3"><label class="form-label small mb-0">Purpose</label>
                                    <select class="form-select form-select-sm" name="ontologies[<?= htmlspecialchars($i) ?>][purpose]">
                                        <option value="">choose…</option>
                                        <?php foreach ($onto_purposes as $pv => $pl): ?><option value="<?= $pv ?>"<?= ($o['purpose'] ?? '') === $pv ? ' selected' : '' ?>><?= $pl ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="col-sm-3"><div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="onto_search_<?= htmlspecialchars($i) ?>" name="ontologies[<?= htmlspecialchars($i) ?>][search]" value="1"<?= !empty($o['search']) ? ' checked' : '' ?>>
                                    <label class="form-check-label small" for="onto_search_<?= htmlspecialchars($i) ?>">Offer in the term search box</label></div></div>
                                <?php foreach (['terms' => 'Terms file (.tsv)', 'closure' => 'Link-tree file (.tsv)', 'releases' => 'releases.txt'] as $f => $fl): ?>
                                <div class="col-sm-<?= $f === 'releases' ? 4 : 4 ?>"><label class="form-label small mb-0"><?= $fl ?></label>
                                    <input type="url" class="form-control form-control-sm font-monospace" name="ontologies[<?= htmlspecialchars($i) ?>][<?= $f ?>]" value="<?= htmlspecialchars((string) ($o[$f] ?? '')) ?>" placeholder="https://raw.githubusercontent.com/…"></div>
                                <?php endforeach; ?>
                                <div class="col-12"><label class="form-label small mb-0">Relationships the search offers <span class="text-muted">(anatomy / phenotype; ids separated by commas -- <code>rdfs:subClassOf</code> = is a)</span></label>
                                    <input type="text" class="form-control form-control-sm font-monospace" name="ontologies[<?= htmlspecialchars($i) ?>][relations]" value="<?= htmlspecialchars(implode(', ', (array) ($o['relations'] ?? []))) ?>">
                                    <?php if ($op !== '' && !empty($o['relations'])): ?>
                                        <small class="text-muted"><?= htmlspecialchars(implode(' · ', array_map(fn($r) => moop_ontology_label($r, array_keys($onto_rows)) ?: $r, $o['relations']))) ?></small>
                                    <?php endif; ?></div>
                                <?php if ($op !== ''): ?>
                                <div class="col-12 small">
                                    <?php if ($st): ?>
                                        <i class="fa fa-check text-success me-1"></i>Built <?= htmlspecialchars(substr((string) ($st['built_at'] ?? ''), 0, 16)) ?> from release <strong><?= htmlspecialchars((string) ($st['release'] ?? '?')) ?></strong>:
                                        <?= number_format((int) ($st['labelled'] ?? 0)) ?> terms, <?= number_format((int) ($st['synonyms'] ?? 0)) ?> synonyms, <?= number_format((int) ($st['closure'] ?? 0)) ?> links<?= (int) ($st['skipped_rows'] ?? 0) ? ', ' . (int) $st['skipped_rows'] . ' rows skipped' : '' ?>.
                                    <?php else: ?>
                                        <i class="fa fa-minus text-muted me-1"></i>Not built on this site.
                                    <?php endif; ?>
                                    <?php if (($hs['state'] ?? '') === 'failed'): ?>
                                        <span class="text-danger ms-1"><i class="fa fa-exclamation-triangle me-1"></i>Last rebuild failed: <?= htmlspecialchars((string) ($hs['error'] ?? '')) ?> -- the previous file is still in use.</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="ontologyRebuildBtn"><i class="fa fa-sync"></i> Rebuild now</button>
                            <small class="text-muted" id="ontologyRebuildMsg"><?= $onto_check ? 'Last weekly check: ' . htmlspecialchars(substr((string) $onto_check, 0, 16)) . '.' : '' ?> Save your changes first; Rebuild uses the saved list.</small>
                        </div>
                    </div>
                </div>

                <!-- Cloudflare Turnstile -->
                <div class="card adm-card mb-3" id="pnav-turnstile" data-nav-label="Cloudflare Turnstile">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-shield-alt"></i> <?= htmlspecialchars($editable_config['turnstile']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">When enabled, all visitors must pass a Cloudflare Turnstile challenge once per session before accessing the site. Usually invisible to real users.</p>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="turnstile_enabled" name="turnstile[enabled]" value="1"
                                <?= !empty($editable_config['turnstile']['current_value']['enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="turnstile_enabled">Enable Turnstile verification</label>
                        </div>
                        <label class="form-label small fw-semibold">Site Key <span class="text-muted fw-normal">(public — from Cloudflare dashboard)</span></label>
                        <input type="text" class="form-control form-control-sm font-monospace"
                               name="turnstile[site_key]" placeholder="0x4AAAA…"
                               value="<?= htmlspecialchars($editable_config['turnstile']['current_value']['site_key'] ?? '') ?>">
                        <?php
                        // Where the keys actually come from, and whether they are set -- never their values.
                        // The secret can only live in config/secrets.php (never in the browser or this form);
                        // the site key may be here or there. lib/human_check.php switches the check OFF,
                        // silently, unless both are set -- so say so when "enabled" is ticked without them.
                        $ts_eff       = $config->getArray('turnstile', []);
                        $ts_file      = rtrim($config->getPath('site_path'), '/') . '/config/secrets.php';
                        $ts_file_ok   = is_file($ts_file);
                        $ts_secret_ok = trim((string) ($ts_eff['secret_key'] ?? '')) !== '';
                        $ts_site_ok   = trim((string) ($ts_eff['site_key'] ?? '')) !== '';
                        $ts_site_here = trim((string) ($editable_config['turnstile']['current_value']['site_key'] ?? '')) !== '';
                        ?>
                        <ul class="list-unstyled small mt-3 mb-2">
                            <li><i class="fa fa-<?= $ts_secret_ok ? 'check text-success' : 'times text-danger' ?> me-1"></i>
                                <strong>Secret key</strong> <?= $ts_secret_ok ? 'is set' : 'is not set' ?> &mdash; it can only go in
                                <code><?= htmlspecialchars($ts_file) ?></code><?= $ts_file_ok ? '' : ' (this file does not exist yet)' ?>. Never shown here.</li>
                            <li><i class="fa fa-<?= $ts_site_ok ? 'check text-success' : 'times text-danger' ?> me-1"></i>
                                <strong>Site key</strong> <?= $ts_site_ok ? ($ts_site_here ? 'is set in the box above' : 'is set in that file (the box above may be left empty)') : 'is not set &mdash; paste it in the box above' ?>.</li>
                        </ul>
                        <?php if (!empty($ts_eff['enabled']) && !($ts_secret_ok && $ts_site_ok)): ?>
                            <div class="alert alert-warning small py-2 mb-2"><i class="fa fa-exclamation-triangle me-1"></i>
                                Turnstile is switched on but a key is missing, so <strong>no visitor is being checked</strong>.</div>
                        <?php endif; ?>
                        <?php if (!$ts_secret_ok): ?>
                            <details class="small">
                                <summary>How to add the secret key</summary>
                                <ol class="mt-2 mb-0">
                                    <li>In the Cloudflare dashboard, open Turnstile and the widget for this site's host name, and copy its <strong>secret key</strong>.</li>
                                    <?php if (!$ts_file_ok): ?>
                                    <li>On the server, create the file from its template, readable by the web server only:
                                        <pre class="bg-light p-2 rounded mt-1 mb-1">cd <?= htmlspecialchars(rtrim($config->getPath('site_path'), '/')) ?>

cp config/secrets.php.example config/secrets.php
chmod 640 config/secrets.php &amp;&amp; chgrp <?= htmlspecialchars(function_exists('getWebServerUser') ? (string) (getWebServerUser()['group'] ?? 'apache') : 'apache') ?> config/secrets.php</pre></li>
                                    <?php endif; ?>
                                    <li>Edit <code><?= htmlspecialchars($ts_file) ?></code> and paste it as <code>'secret_key' =&gt; '…'</code> under <code>'turnstile'</code>.</li>
                                    <li>Reload this page: the line above turns green. The file is never committed to git, the web server refuses to serve it, and the site-data backup keeps a private copy.</li>
                                </ol>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search Results Limit -->
                <div class="card adm-card mb-3" id="pnav-search-limit" data-nav-label="Search Results">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-list-ol"></i> Search Results</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-1"><?= htmlspecialchars($editable_config['search_results_limit']['label']) ?></h6>
                        <p class="text-muted small"><?= htmlspecialchars($editable_config['search_results_limit']['description']) ?></p>
                        <div style="max-width: 160px;">
                            <input type="number"
                                   id="search_results_limit"
                                   name="search_results_limit"
                                   class="form-control"
                                   value="<?= (int)$editable_config['search_results_limit']['current_value'] ?>"
                                   min="<?= (int)$editable_config['search_results_limit']['min'] ?>"
                                   max="<?= (int)$editable_config['search_results_limit']['max'] ?>">
                        </div>
                        <small class="form-text text-muted"><?= htmlspecialchars($editable_config['search_results_limit']['note']) ?></small>

                        <?php $col_over = moop_result_column_overrides(); ?>
                        <h6 class="mt-4 mb-1">Columns shown by default</h6>
                        <p class="text-muted small mb-2">
                            Which columns the gene search results show when they open, on every search page.
                            Visitors can still switch any column on with <strong>Column visibility</strong>, and
                            downloads always include every column. <em>Select</em> and <em>Feature ID</em> are always shown.
                        </p>
                        <input type="hidden" name="search_results_columns_form" value="1">
                        <div class="row g-1">
                            <?php foreach (moop_result_columns() as $key => [$label, $default, $view]): ?>
                                <?php $on = $col_over[$key] ?? $default; ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="search_results_columns[]"
                                               value="<?= htmlspecialchars($key) ?>" id="src-<?= htmlspecialchars($key) ?>" <?= $on ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="src-<?= htmlspecialchars($key) ?>">
                                            <?= htmlspecialchars($label) ?>
                                            <?php if ($view !== 'all'): ?><span class="text-muted small">(<?= htmlspecialchars($view) ?> view)</span><?php endif; ?>
                                            <?php if ($on !== $default): ?><span class="badge bg-light text-dark border ms-1">changed</span><?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- BLAST CPU Threads -->
                <div class="card adm-card mb-3" id="pnav-blast-threads" data-nav-label="BLAST CPU Threads">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-microchip"></i> <?= htmlspecialchars($editable_config['blast_num_threads']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?= htmlspecialchars($editable_config['blast_num_threads']['description']) ?></p>
                        <div style="max-width: 160px;">
                            <input type="number"
                                   id="blast_num_threads"
                                   name="blast_num_threads"
                                   class="form-control"
                                   value="<?= (int)$editable_config['blast_num_threads']['current_value'] ?>"
                                   min="<?= (int)$editable_config['blast_num_threads']['min'] ?>"
                                   max="<?= (int)$editable_config['blast_num_threads']['max'] ?>">
                        </div>
                        <small class="form-text text-muted">Set to the number of CPU cores on this server. Run <code>nproc</code> in a terminal to check.</small>
                    </div>
                </div>

                <!-- BLAST Queue -->
                <?php
                $_bq = $blast_queue_effective;
                $_bq_auto = [
                    'blast_memory_budget_mb' => number_format($_bq['units'] * BLAST_QUEUE_UNIT_MB / 1024, 1) . ' GB',
                    'blast_max_concurrent'   => $_bq['max_concurrent'] . ' at once',
                ];
                // The value in use, for the two settings where 0 means "work it out from this
                // machine" -- the box shows that number, not a bare 0.
                $_bq_in_use = [
                    'blast_memory_budget_mb' => $_bq['units'] * BLAST_QUEUE_UNIT_MB,
                    'blast_max_concurrent'   => $_bq['max_concurrent'],
                ];
                ?>
                <div class="card adm-card mb-3" id="pnav-blast-queue" data-nav-label="BLAST Queue">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-hourglass-half"></i> BLAST Queue</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">
                            When the server is busy, BLAST and Primer BLAST searches wait their turn instead of all
                            running at once &mdash; too many big genome searches together run the server out of memory.
                            A waiting user sees their place in line. Nothing can get stuck: a search that dies frees
                            its place immediately, and both the wait and the run are time-limited.
                        </p>
                        <p class="small mb-3">
                            <strong>Right now:</strong>
                            <?php if ($blast_queue_now['ok']): ?>
                                <?= (int)$blast_queue_now['running'] ?> running, <?= (int)$blast_queue_now['waiting'] ?> waiting.
                            <?php else: ?>
                                <span class="text-danger">the queue directory cannot be used, so searches run unqueued (see the error log).</span>
                            <?php endif; ?>
                            <span class="text-muted">In use: <?= htmlspecialchars($_bq_auto['blast_memory_budget_mb']) ?> memory budget,
                            <?= (int)$_bq['max_concurrent'] ?> searches at once, wait up to <?= (int)$_bq['max_wait'] ?> s, run up to <?= (int)$_bq['max_runtime'] ?> s.</span>
                        </p>
                        <div class="row g-3">
                            <?php foreach (['blast_memory_budget_mb', 'blast_max_concurrent', 'blast_queue_max_wait', 'blast_max_runtime', 'blast_max_output_mb', 'blast_max_hits'] as $_qk):
                                $_qm = $editable_config[$_qk];
                                $_can_auto = isset($_bq_in_use[$_qk]);
                                $_is_auto  = $_can_auto && (int)$_qm['current_value'] === 0; ?>
                            <div class="col-sm-6 col-lg-4">
                                <label class="form-label small fw-semibold mb-1" for="<?= $_qk ?>"><?= htmlspecialchars($_qm['label']) ?></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="number" class="form-control form-control-sm" style="max-width: 140px;"
                                           id="<?= $_qk ?>" name="<?= $_qk ?>"
                                           value="<?= $_is_auto ? (int)$_bq_in_use[$_qk] : (int)$_qm['current_value'] ?>"
                                           min="<?= $_can_auto ? 1 : (int)$_qm['min'] ?>" max="<?= (int)$_qm['max'] ?>"
                                           <?= $_is_auto ? 'disabled' : '' ?>>
                                    <?php if ($_can_auto): ?>
                                    <div class="form-check mb-0 small">
                                        <input class="form-check-input bq-auto" type="checkbox" id="<?= $_qk ?>_auto" name="<?= $_qk ?>_auto"
                                               value="1" data-target="<?= $_qk ?>" <?= $_is_auto ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="<?= $_qk ?>_auto">Automatic</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted d-block mt-1"><?= htmlspecialchars($_qm['description']) ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-text text-muted d-block mt-3">
                            Longest wait + longest run must stay under 280 s: nginx gives up on a page after 300 s
                            (<code>scripts/install_nginx_timeouts.sh</code>). Background and measurements:
                            <code>notes/CAPACITY_TEST_2026-10-23.md</code>.
                        </small>
                    </div>
                </div>

                <!-- BLAST Sample Sequences -->
                <div class="card adm-card mb-3" id="pnav-blast-samples" data-nav-label="BLAST Sample Sequences">
                    <div class="card-header adm-head d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="fa fa-flask"></i>
                                <?= htmlspecialchars($editable_config['blast_sample_sequences']['label']) ?>
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($editable_config['blast_sample_sequences']['note']): ?>
                            <div class="cfg-note mb-3">
                                <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($editable_config['blast_sample_sequences']['note']) ?>
                            </div>
                        <?php endif; ?>
                        <p class="text-muted small">
                            <?= htmlspecialchars($editable_config['blast_sample_sequences']['description']) ?>
                        </p>
                        
                        <!-- Protein Sample -->
                        <div class="mb-3">
                            <label for="blast_sample_protein" class="form-label">
                                <strong>Protein Sample Sequence</strong>
                            </label>
                            <textarea 
                                id="blast_sample_protein" 
                                name="blast_sample_sequences[protein]" 
                                class="form-control" 
                                rows="5"
                                placeholder="Enter sample protein sequence..."
                                required><?= htmlspecialchars($editable_config['blast_sample_sequences']['current_value']['protein'] ?? '') ?></textarea>
                            <small class="form-text text-muted">FASTA format or plain text</small>
                        </div>
                        
                        <!-- Nucleotide Sample -->
                        <div class="mb-3">
                            <label for="blast_sample_nucleotide" class="form-label">
                                <strong>Nucleotide Sample Sequence</strong>
                            </label>
                            <textarea 
                                id="blast_sample_nucleotide" 
                                name="blast_sample_sequences[nucleotide]" 
                                class="form-control" 
                                rows="5"
                                placeholder="Enter sample nucleotide sequence..."
                                required><?= htmlspecialchars($editable_config['blast_sample_sequences']['current_value']['nucleotide'] ?? '') ?></textarea>
                            <small class="form-text text-muted">FASTA format or plain text</small>
                        </div>
                    </div>
                </div>

                <!-- Sample Feature IDs -->
                <div class="card adm-card mb-3" id="pnav-sample-ids" data-nav-label="Sample Feature IDs">
                    <div class="card-header adm-head d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="fa fa-bookmark"></i>
                                <?= htmlspecialchars($editable_config['sample_feature_ids']['label']) ?>
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($editable_config['sample_feature_ids']['note']): ?>
                            <div class="cfg-note mb-3">
                                <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($editable_config['sample_feature_ids']['note']) ?>
                            </div>
                        <?php endif; ?>
                        <p class="text-muted small">
                            <?= htmlspecialchars($editable_config['sample_feature_ids']['description']) ?>
                        </p>
                        
                        <label for="sample_feature_ids" class="form-label">
                            <strong>Gene / feature IDs (one per line)</strong>
                        </label>
                        <textarea id="sample_feature_ids" name="sample_feature_ids" class="form-control" rows="3"
                                  placeholder="Empty = automatic"><?php
                            $ids = $editable_config['sample_feature_ids']['current_value'];
                            echo is_array($ids) ? htmlspecialchars(implode("\n", $ids)) : htmlspecialchars($ids);
                        ?></textarea>
                        <label for="home_search_examples" class="form-label mt-3">
                            <strong><?= htmlspecialchars($editable_config['home_search_examples']['label']) ?> (one per line)</strong>
                        </label>
                        <p class="text-muted small mb-1"><?= htmlspecialchars($editable_config['home_search_examples']['description']) ?></p>
                        <textarea id="home_search_examples" name="home_search_examples" class="form-control" rows="3"
                                  placeholder="Empty = automatic"><?= htmlspecialchars(implode("\n", (array)$editable_config['home_search_examples']['current_value'])) ?></textarea>
                        <small class="form-text text-muted">Leave both empty to let the site choose examples every viewer can open.</small>
                    </div>
                </div>

                <!-- Site Data Backup Path -->
                <div class="card adm-card mb-3" id="pnav-backup-path" data-nav-label="Site Data Backup Path">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-save"></i> <?= htmlspecialchars($editable_config['site_data_path']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($editable_config['site_data_path']['note']): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($editable_config['site_data_path']['note']) ?>
                            </div>
                        <?php endif; ?>
                        <p class="text-muted small"><?= htmlspecialchars($editable_config['site_data_path']['description']) ?></p>
                        <input type="text"
                               id="site_data_path"
                               name="site_data_path"
                               class="form-control font-monospace"
                               value="<?= htmlspecialchars($editable_config['site_data_path']['current_value']) ?>"
                               placeholder="<?= htmlspecialchars(dirname($root_path) . '/' . $site . '-site-data') ?>">
                        <small class="form-text text-muted mt-1">
                            Must be an absolute path. Leave empty to disable backups.
                            To enable git version history: <code>cd /path/to/dir && git init -b main && git add -A && git commit -m "Initial snapshot"</code>
                        </small>
                    </div>
                </div>

                <!-- Cache Directory -->
                <div class="card adm-card mb-3" id="pnav-cache-path" data-nav-label="Cache Directory">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-database"></i> <?= htmlspecialchars($editable_config['cache_path']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($editable_config['cache_path']['note']): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($editable_config['cache_path']['note']) ?>
                            </div>
                        <?php endif; ?>
                        <p class="text-muted small"><?= htmlspecialchars($editable_config['cache_path']['description']) ?></p>
                        <input type="text"
                               id="cache_path"
                               name="cache_path"
                               class="form-control font-monospace"
                               value="<?= htmlspecialchars($editable_config['cache_path']['current_value']) ?>"
                               placeholder="/var/www/moop-cache">
                        <small class="form-text text-muted mt-1">
                            Must be an absolute path. Leave empty to store caches inside the organisms/ tree (legacy behavior).
                        </small>
                    </div>
                </div>

                <!-- Technical Panels (cuttingclass: a teaching site) -->
                <div class="card adm-card mb-3" id="pnav-technical" data-nav-label="Technical Panels">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-microscope"></i> <?= htmlspecialchars($editable_config['technical_panels']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?= htmlspecialchars($editable_config['technical_panels']['description']) ?></p>
                        <?php $tp_current = $editable_config['technical_panels']['current_value']; ?>
                        <select id="technical_panels" name="technical_panels" class="form-select" style="max-width: 28rem;">
                            <option value="show"<?= $tp_current === 'show' ? ' selected' : '' ?>>Show them (default)</option>
                            <option value="researchers"<?= $tp_current === 'researchers' ? ' selected' : '' ?>>Put them under "For researchers"</option>
                        </select>
                    </div>
                </div>

                <!-- Time Zone -->
                <div class="card adm-card mb-3" id="pnav-timezone" data-nav-label="Time Zone">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-clock"></i> <?= htmlspecialchars($editable_config['timezone']['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?= htmlspecialchars($editable_config['timezone']['description']) ?></p>
                        <?php
                        $tz_current = $editable_config['timezone']['current_value'];
                        // What the server itself would use, for the "Server default" label.
                        $tz_php = ini_get('date.timezone') ?: 'UTC';
                        ?>
                        <select id="timezone" name="timezone" class="form-select" style="max-width: 28rem;">
                            <option value=""<?= $tz_current === '' ? ' selected' : '' ?>>Server default (<?= htmlspecialchars($tz_php) ?>)</option>
                            <?php foreach (timezone_identifiers_list() as $tz_id): ?>
                                <option value="<?= htmlspecialchars($tz_id) ?>"<?= $tz_id === $tz_current ? ' selected' : '' ?>><?= htmlspecialchars($tz_id) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted mt-1 d-block">
                            The site's time right now: <strong><?= date('Y-m-d H:i') ?></strong> (<?= htmlspecialchars(date_default_timezone_get()) ?>).
                            Dates saved before a change were written in the old zone and are not rewritten.
                        </small>
                    </div>
                </div>

                <!-- Footer Settings Card -->
                <div class="card adm-card mb-3" id="pnav-footer" data-nav-label="Footer">
                    <div class="card-header adm-head">
                        <h5 class="mb-0"><i class="fa fa-shoe-prints"></i> Footer</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Content displayed in the site footer. The contact administrator link uses the Admin Email above.</p>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Institute Name</label>
                                <input type="text" class="form-control" name="footer[institute_name]"
                                       value="<?= htmlspecialchars($footer_config['institute_name'] ?? '') ?>"
                                       placeholder="e.g. Stowers Institute">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Institute URL <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="url" class="form-control" name="footer[institute_url]"
                                       value="<?= htmlspecialchars($footer_config['institute_url'] ?? '') ?>"
                                       placeholder="https://www.yourinstitute.org">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">License Name <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="text" class="form-control" name="footer[license_name]"
                                       value="<?= htmlspecialchars($footer_config['license_name'] ?? '') ?>"
                                       placeholder="e.g. CC BY 4.0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">License URL <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="url" class="form-control" name="footer[license_url]"
                                       value="<?= htmlspecialchars($footer_config['license_url'] ?? '') ?>"
                                       placeholder="https://creativecommons.org/licenses/by/4.0/">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Copyright Holder <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="text" class="form-control" name="footer[copyright_holder]"
                                       value="<?= htmlspecialchars($footer_config['copyright_holder'] ?? '') ?>"
                                       placeholder="e.g. Stowers Institute for Medical Research">
                                <small class="text-muted">Shown as "© YYYY [holder]". Leave empty to omit.</small>
                            </div>
                        </div>

                        <label class="form-label fw-semibold">Footer Links</label>
                        <p class="text-muted small mb-2">Links shown in the right section of the footer.</p>
                        <div class="table-responsive mb-2">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Label</th>
                                        <th>URL</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="footer_links_tbody">
                                    <?php
                                    $footer_links = $footer_config['links'] ?? [];
                                    if (empty($footer_links)):
                                    ?>
                                    <tr class="no-footer-links-row">
                                        <td colspan="3" class="text-center text-muted"><em>No links configured</em></td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($footer_links as $i => $link): ?>
                                        <tr class="footer-link-row">
                                            <td><input type="text" class="form-control form-control-sm"
                                                       name="footer[links][<?= $i ?>][label]"
                                                       value="<?= htmlspecialchars($link['label'] ?? '') ?>"
                                                       placeholder="Link label"></td>
                                            <td><input type="url" class="form-control form-control-sm"
                                                       name="footer[links][<?= $i ?>][url]"
                                                       value="<?= htmlspecialchars($link['url'] ?? '') ?>"
                                                       placeholder="https://..."></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger remove-footer-link">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="add_footer_link">
                            <i class="fa fa-plus"></i> Add Link
                        </button>
                    </div>
                </div>

                <!-- Sticky save bar: stays in view rather than sitting below ~600 lines of
                     form. js/modules/manage-site-config.js toggles .is-dirty and the note. -->
                <div class="cfg-savebar" id="cfgSaveBar">
                    <span class="cfg-dirty-note" id="cfgDirtyNote">
                        <?php if (!$file_writable): ?>
                            <i class="fa fa-lock"></i> Configuration file is not writable — saving is disabled.
                        <?php else: ?>
                            No unsaved changes
                        <?php endif; ?>
                    </span>
                    <button type="reset" class="btn btn-outline-secondary" id="resetBtn">
                        <i class="fa fa-undo"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveBtn" <?= !$file_writable ? 'disabled' : '' ?>>
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </div>
            </form>

            <!-- Change History -->
            <div class="card adm-card mt-4" id="pnav-recent-changes" data-nav-label="Recent Changes">
                <div class="card-header adm-head">
                    <h5 class="mb-0"><i class="fa fa-history"></i> Recent Changes</h5>
                </div>
                <div class="card-body">
                    <?php
                    $editable_json = loadJsonFile($config_file, null);
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

            <p class="small text-muted mt-3 mb-0"><i class="fa fa-lock"></i> The site's paths and directories are not set on this page.
                <a href="#" data-bs-toggle="modal" data-bs-target="#structuralSettingsModal">Where they come from →</a></p>

              </div><!-- /.pnav-main -->
            </div><!-- /.pnav-layout -->
        </div>
    </div>

<!-- Structural settings: where the paths come from. Opened from the link under Recent Changes.
     No line numbers into site_config.php, on purpose: the old tutorial quoted four, every one
     went stale when root_path/site became derived (2026-07-22), and it told admins to set
     $site by hand -- the very edit that let one clone write into another site's users.json.
     Describe the mechanism (derived from the folder, site_paths.php, env vars); never a line. -->
<div class="modal fade" id="structuralSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header adm-head">
                <h5 class="modal-title"><i class="fa fa-lock"></i> Where the site's paths come from</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3"><i class="fa fa-info-circle"></i> These paths are not settings on this page, and you should not edit <code>config/site_config.php</code> to change them: it is part of the code, so a local edit conflicts with every update. They follow from where the site's folder sits.</p>

                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light">
                            <tr><th style="width:20%;">Path</th><th style="width:35%;">Current value</th><th>Where it comes from</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><code>site_path</code></td><td><code><?= htmlspecialchars($site_path) ?></code></td><td>The folder this copy of MOOP is in.</td></tr>
                            <tr><td><code>root_path</code></td><td><code><?= htmlspecialchars($root_path) ?></code></td><td>The folder above it.</td></tr>
                            <tr><td><code>site</code></td><td><code><?= htmlspecialchars($site) ?></code></td><td>The folder's name. It also names this site's cookies, caches and backup folders. Links do not use it: they start with <code>link_prefix</code>.</td></tr>
                            <tr><td><code>link_prefix</code></td><td><code><?= htmlspecialchars(moop_url_base() === '' ? '/' : moop_url_base()) ?></code></td><td>The start of every link (<code><?= htmlspecialchars(moop_url('tools/search.php')) ?></code>). Defaults to <code>/</code> + the folder name. A site served at the root of its own domain sets it to <code>"/"</code> in <code>config/config_editable.json</code>. Change it <strong>only together with</strong> the web-server change that serves the folder at that address, or every link breaks at once (<code>notes/MOVE_SITE_TO_OWN_ADDRESS.md</code>).</td></tr>
                            <tr><td><code>organism_data</code></td><td><code><?= htmlspecialchars($organism_data) ?></code></td><td>Always <code>organisms/</code> inside the site folder.</td></tr>
                            <tr><td><code>metadata_path</code></td><td><code><?= htmlspecialchars($metadata_path) ?></code></td><td>Always <code>metadata/</code> inside the site folder.</td></tr>
                            <tr><td><code>users_file</code></td><td><code><?= htmlspecialchars($config->getPath('users_file')) ?></code></td><td>Kept <strong>outside</strong> the web root (it holds password hashes). The default is in <code>site_config.php</code>; a site can set its own with the <code>users_file</code> key in <code>config/config_editable.json</code>.</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6><strong>Moving the site, or running a second one</strong></h6>
                <p class="small">Move or clone the whole folder. The paths above follow it, with nothing to edit. A clone at <code><?= htmlspecialchars($root_path) ?>/cuttingclass</code> is the site <code>cuttingclass</code>, with its own <code>organisms/</code> and <code>metadata/</code>. The web server must also be told about the new folder.</p>

                <h6><strong>When the folder's location is not the real one</strong></h6>
                <p class="small mb-2">With a symlinked web root or a container, the folder can sit somewhere other than where it is served from. Only then, set the location by hand, in either of these:</p>
                <ul class="small">
                    <li><code>config/site_paths.php</code>: copy <code>config/site_paths.php.example</code> and set <code>root_path</code> and <code>site</code>. It is not part of the code, so updates never touch it.</li>
                    <li>The environment variables <code>MOOP_ROOT_PATH</code> and <code>MOOP_SITE</code>, which win over that file.</li>
                </ul>
                <div class="alert alert-warning small">
                    <i class="fa fa-exclamation-triangle"></i> <strong><code>root_path</code> + <code>site</code> must name the folder this copy actually lives in.</strong> Pointing them at another site's folder makes this copy read and write that site's data and users, including its admin accounts. <code>setup.php</code> refuses to run when they disagree.
                </div>

                <h6><strong>Organism data on another disk</strong></h6>
                <p class="small mb-0">There is no setting for this. Leave <code>organisms</code> where it is and make it a symlink to the other disk. The new location needs the same owner and permissions as the folder it replaces and, on an SELinux host, the same label. <code>scripts/fix_moop_selinux.sh</code> labels <code><?= htmlspecialchars($organism_data) ?></code> by that path only, so the real folder needs its own <code>semanage fcontext</code> rule. Check the Filesystem Permissions page afterwards.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/guides/manage_site_config.php';   // the "How this page works" guide ?>
