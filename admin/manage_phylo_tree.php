<?php
include_once __DIR__ . '/admin_init.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$absolute_images_path = $config->getPath('absolute_images_path');

$tree_config_file = "$metadata_path/phylo_tree_config.json";
$organism_data_dir = $organism_data;
$message = '';
$error = '';
$file_write_error = getFileWriteError($tree_config_file);
$dir_error = getDirectoryError($absolute_images_path . '/ncbi_taxonomy');

// Function to get organism metadata
function get_organisms_metadata($organism_data_dir) {
    // This function is now deprecated - use loadAllOrganismsMetadata() from functions_data.php instead
    // Kept for backwards compatibility
    return loadAllOrganismsMetadata($organism_data_dir);
}

// Function to fetch and cache organism image from NCBI to ncbi_taxonomy directory
function fetch_organism_image($taxon_id, $organism_name = null) {
    global $absolute_images_path;
    
    $ncbi_dir = $absolute_images_path . '/ncbi_taxonomy';
    $image_path = $ncbi_dir . '/' . $taxon_id . '.jpg';
    
    // Check if image already cached
    if (file_exists($image_path)) {
        return 'images/ncbi_taxonomy/' . $taxon_id . '.jpg';
    }
    
    // Ensure directory exists
    if (!is_dir($ncbi_dir)) {
        @mkdir($ncbi_dir, 0755, true);
    }
    
    // Download from NCBI
    $image_url = "https://api.ncbi.nlm.nih.gov/datasets/v2/taxonomy/taxon/{$taxon_id}/image";
    
    $context = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'MOOP']]);
    $image_data = @file_get_contents($image_url, false, $context);
    
    if ($image_data === false || strlen($image_data) < 100) {
        return null;
    }
    
    // Save image
    if (file_put_contents($image_path, $image_data) !== false) {
        return 'images/ncbi_taxonomy/' . $taxon_id . '.jpg';
    }
    
    return null;
}

// Function to fetch taxonomic lineage from NCBI using XML parsing
// Uses file_get_contents for maximum compatibility
function fetch_taxonomy_lineage($taxon_id) {
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=taxonomy&id={$taxon_id}&retmode=xml";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'MOOP Phylo Tree Generator'
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    // Parse XML using regex since SimpleXML isn't always available
    $lineage = [];
    
    // Extract Lineage text (semicolon-separated)
    if (preg_match('/<Lineage>(.+?)<\/Lineage>/s', $response, $matches)) {
        $lineage_text = trim($matches[1]);
        $lineage_parts = array_filter(array_map('trim', explode(';', $lineage_text)));
        
        // Extract ranks from LineageEx
        $rank_map = [];
        if (preg_match_all('/<Taxon>.*?<ScientificName>(.+?)<\/ScientificName>.*?<Rank>(.+?)<\/Rank>.*?<\/Taxon>/s', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sci_name = trim($match[1]);
                $rank = trim($match[2]);
                $rank_map[$sci_name] = $rank;
            }
        }
        
        // Build lineage array with matched ranks
        $valid_ranks = ['superkingdom', 'kingdom', 'phylum', 'class', 'order', 'family', 'genus'];
        foreach ($lineage_parts as $name) {
            $rank = $rank_map[$name] ?? null;
            
            // Map domain to superkingdom
            if ($rank === 'domain') {
                $rank = 'superkingdom';
            }
            
            // Only include standard taxonomic ranks (skip intermediate ranks like 'clade')
            // $rank exists AND $rank is one of the major taxonomic levels
            if ($rank && in_array($rank, $valid_ranks)) {
                $lineage[] = [
                    'rank' => $rank,
                    'name' => $name
                ];
            }
        }
    }
    
    // Add the species itself
    if (preg_match('/<ScientificName>(.+?)<\/ScientificName>/', $response, $matches)) {
        $sci_name = trim($matches[1]);
        // Only add if it's not already in lineage
        if (empty($lineage) || $lineage[count($lineage)-1]['name'] !== $sci_name) {
            $lineage[] = [
                'rank' => 'species',
                'name' => $sci_name
            ];
        }
    }
    
    return !empty($lineage) ? $lineage : null;
}

// Function to build tree from organisms
function build_tree_from_organisms($organisms) {
    $all_lineages = [];
    
    foreach ($organisms as $organism_name => $data) {
        if (empty($data['taxon_id'])) {
            continue;
        }
        
        $lineage = fetch_taxonomy_lineage($data['taxon_id']);
        $image = fetch_organism_image($data['taxon_id'], $organism_name);
        if ($lineage) {
            $all_lineages[$organism_name] = [
                'lineage' => $lineage,
                'common_name' => $data['common_name'],
                'image' => $image
            ];
        }
        
        // Be nice to NCBI - rate limit
        usleep(350000); // 350ms = ~3 requests per second
    }
    
    // Build tree structure
    $tree = ['name' => 'Life', 'children' => []];
    
    foreach ($all_lineages as $organism_name => $info) {
        $current = &$tree;
        
        foreach ($info['lineage'] as $level) {
            $name = $level['name'];
            $rank = $level['rank'];
            
            // Find or create child node
            $found = false;
            foreach ($current['children'] as &$child) {
                if ($child['name'] === $name) {
                    $current = &$child;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $new_node = ['name' => $name];
                
                // If this is the species level, add organism info
                if ($rank === 'species') {
                    $new_node['organism'] = $organism_name;
                    $new_node['common_name'] = $info['common_name'];
                    if ($info['image']) {
                        $new_node['image'] = $info['image'];
                    }
                } else {
                    $new_node['children'] = [];
                }
                
                $current['children'][] = $new_node;
                $current = &$current['children'][count($current['children']) - 1];
            }
        }
    }
    
    return ['tree' => $tree];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($file_write_error) {
            $error = "File is not writable. Please fix permissions first.";
        } elseif ($_POST['action'] === 'generate') {
            try {
                $organisms = get_organisms_metadata($organism_data_dir);
                
                if (empty($organisms)) {
                    $error = "No organisms found in {$organism_data_dir}";
                } else {
                    $tree_data = build_tree_from_organisms($organisms);
                    
                    // Save to file
                    $json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    if ($json === false) {
                        $error = "Failed to encode tree data as JSON: " . json_last_error_msg();
                    } elseif (file_put_contents($tree_config_file, $json) !== false) {
                        $message = "Phylogenetic tree generated successfully! Found " . count($organisms) . " organisms.";
                    } else {
                        $error = "Failed to write tree config file to: " . $tree_config_file;
                        if (!is_writable($tree_config_file)) {
                            $error .= " (File is not writable by current process)";
                        }
                    }
                }
            } catch (Exception $e) {
                $error = "Error generating tree: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'save_manual') {
            $tree_json = $_POST['tree_json'] ?? '';
            
            // Validate JSON
            $tree_data = json_decode($tree_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($json === false) {
                    $error = "Failed to encode tree data as JSON: " . json_last_error_msg();
                } elseif (file_put_contents($tree_config_file, $json) !== false) {
                    $message = "Tree configuration saved successfully!";
                } else {
                    $error = "Failed to save tree configuration to: " . $tree_config_file;
                    if (!is_writable($tree_config_file)) {
                        $error .= " (File is not writable by current process)";
                    }
                }
            } else {
                $error = "Invalid JSON: " . json_last_error_msg();
            }
        }
    }
}

// Load current tree config using helper
$current_tree = loadJsonFile($tree_config_file, null);

// Get available organisms
$organisms = get_organisms_metadata($organism_data_dir);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Phylogenetic Tree - Admin</title>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fa fa-project-diagram"></i> Manage Phylogenetic Tree</h2>
            <p class="text-muted">Generate and customize the phylogenetic tree displayed on the homepage.</p>
            
            <?php
            ?>
            
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
                    <p><strong>Problem:</strong> The file <code>metadata/phylo_tree_config.json</code> is not writable by the web server.</p>
                    
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
                    <p>Automatically generate the phylogenetic tree from organism taxonomy IDs using NCBI Taxonomy database.</p>
                    
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

<script>
// Show loading state when generating
document.getElementById('generateForm').addEventListener('submit', function() {
    const btn = document.getElementById('generateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating... Please wait';
});

// Tree preview renderer
<?php if ($current_tree): ?>
function renderTreeNode(node, level = 0) {
    const indent = level * 20;
    const isLeaf = !!node.organism;
    const icon = isLeaf ? '🔬' : (level === 0 ? '🌳' : '├');
    
    let html = `<div style="margin-left: ${indent}px; padding: 4px 0;">`;
    html += `<span style="font-weight: ${isLeaf ? 'normal' : 'bold'}; font-style: ${isLeaf ? 'italic' : 'normal'}">`;
    html += `${icon} ${node.name}`;
    if (node.common_name) {
        html += ` <span class="text-muted small">(${node.common_name})</span>`;
    }
    html += `</span></div>`;
    
    if (node.children) {
        node.children.forEach(child => {
            html += renderTreeNode(child, level + 1);
        });
    }
    
    return html;
}

const treeData = <?= json_encode($current_tree) ?>;
document.getElementById('tree-preview').innerHTML = renderTreeNode(treeData.tree);
<?php endif; ?>
</script>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
