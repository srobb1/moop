<!-- Manage Taxonomy Tree Content -->

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fa fa-project-diagram"></i> Manage Taxonomy Tree</h2>
            
            <!-- About Section -->
            <div class="card mb-4 border-info">
                <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutTaxonomyTree">
                    <h5 class="mb-0"><i class="fa fa-info-circle"></i> About the Taxonomy Tree <i class="fa fa-chevron-down float-end"></i></h5>
                </div>
                <div class="collapse" id="aboutTaxonomyTree">
                    <div class="card-body">
                        <p>The taxonomy tree is displayed on the homepage to allow visitors to create a custom assortment of organisms for searching.</p>
                        
                        <p><strong>How it works:</strong></p>
                        <ul>
                            <li>Organism names are pulled from the organisms directory</li>
                            <li>Taxon IDs are read from each organism's <code>organism.json</code> file</li>
                            <li>These are combined to generate a taxonomical tree structure</li>
                            <li>The tree is used on the homepage for interactive organism selection</li>
                        </ul>
                        
                        <p class="mb-0"><strong>You can:</strong></p>
                        <ul class="mb-0">
                            <li>Auto-generate the tree from NCBI taxonomy data</li>
                            <li>Manually edit the tree structure using JSON</li>
                            <li>Customize the taxonomy hierarchy as needed</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($file_write_error): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <h4><i class="fa fa-exclamation-circle"></i> File Permission Issue Detected</h4>
                    <p><strong>Problem:</strong> The file <code>metadata/taxonomy_tree_config.json</code> is not writable by the web server.</p>
                    
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
            
            <?php if ($dir_error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <h4><i class="fa fa-folder-times"></i> Directory Setup Required</h4>
                    
                    <?php if ($dir_error['type'] === 'missing'): ?>
                        <p><strong>Problem:</strong> The image cache directory does not exist.</p>
                        <p><strong>Missing Directory:</strong> <code><?= htmlspecialchars($dir_error['dir']) ?></code></p>
                    <?php else: ?>
                        <p><strong>Problem:</strong> The image cache directory is not writable by the web server.</p>
                        <p><strong>Directory:</strong> <code><?= htmlspecialchars($dir_error['dir']) ?></code></p>
                        
                        <p><strong>Current Status:</strong></p>
                        <ul class="mb-3">
                            <li>Owner: <code><?= htmlspecialchars($dir_error['owner']) ?></code></li>
                            <li>Permissions: <code><?= $dir_error['perms'] ?></code></li>
                            <li>Web server group: <code><?= htmlspecialchars($dir_error['web_group']) ?></code></li>
                        </ul>
                    <?php endif; ?>
                    
                    <p><strong>To Fix:</strong> Run <?php echo count($dir_error['commands']) > 1 ? 'these commands' : 'this command'; ?> on the server:</p>
                    <div style="margin: 10px 0; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                        <?php foreach ($dir_error['commands'] as $cmd): ?>
                            <code style="word-break: break-all; display: block; font-size: 0.9em; margin-bottom: 5px;">
                                <?= htmlspecialchars($cmd) ?>
                            </code>
                        <?php endforeach; ?>
                    </div>
                    
                    <p><small class="text-muted">After running the commands, refresh this page.</small></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fa fa-magic"></i> Auto-Generate Tree</h5>
                </div>
                <div class="card-body">
                    <p>Automatically generate the taxonomy tree from organism taxonomy IDs using NCBI Taxonomy database.</p>
                    
                    <div class="alert alert-info">
                        <strong><i class="fa fa-info-circle"></i> How it works:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Reads all organism directories from <code><?= htmlspecialchars($organism_data_dir) ?></code></li>
                            <li>Fetches taxonomic lineage from NCBI using each organism's <code>taxon_id</code></li>
                            <li>Builds hierarchical tree structure automatically</li>
                            <li>Rate-limited to ~3 requests/second (NCBI requirement)</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Found Organisms:</strong>
                        <ul class="list-unstyled mt-2">
                            <?php foreach ($organisms as $name => $data): ?>
                                <li>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($name) ?></span>
                                    <small class="text-muted">
                                        Taxon ID: <?= htmlspecialchars($data['taxon_id']) ?>
                                        <?php if ($data['common_name']): ?>
                                            (<?= htmlspecialchars($data['common_name']) ?>)
                                        <?php endif; ?>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <form method="post" id="generateForm">
                        <input type="hidden" name="action" value="generate">
                        <button type="submit" class="btn btn-primary" id="generateBtn" <?= $file_write_error ? 'disabled' : '' ?>>
                            <i class="fa fa-sync-alt"></i> Generate Tree from NCBI
                        </button>
                        <small class="text-muted d-block mt-2">
                            <i class="fa fa-clock"></i> This may take ~<?= count($organisms) ?> seconds
                        </small>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fa fa-edit"></i> Manual Editor</h5>
                </div>
                <div class="card-body">
                    <p>Edit the tree structure manually in JSON format. You can:</p>
                    <ul>
                        <li>Remove taxonomic levels you don't want to display</li>
                        <li>Reorganize the hierarchy</li>
                        <li>Add custom groupings</li>
                    </ul>
                    
                    <form method="post" id="manualForm">
                        <input type="hidden" name="action" value="save_manual">
                        <div class="mb-3">
                            <label for="tree_json" class="form-label">Tree JSON:</label>
                            <textarea class="form-control font-monospace" name="tree_json" id="tree_json" rows="15"><?= htmlspecialchars(json_encode($current_tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
                            <small class="form-text text-muted">Edit carefully - invalid JSON will not be saved</small>
                        </div>
                        <button type="submit" class="btn btn-success" <?= $file_write_error ? 'disabled' : '' ?>>
                            <i class="fa fa-save"></i> Save Manual Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($current_tree): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fa fa-eye"></i> Current Tree Preview</h5>
                </div>
                <div class="card-body">
                    <div id="tree-preview" class="tree-preview"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.tree-preview {
    font-family: 'Courier New', monospace;
    max-height: 500px;
    overflow-y: auto;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}
</style>
