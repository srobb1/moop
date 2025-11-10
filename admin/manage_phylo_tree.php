<?php
session_start();
include_once 'admin_access_check.php';
include_once '../includes/head.php';
include_once '../includes/navbar.php';
$organism_data_dir = $organism_data;
$tree_config_file = __DIR__ . '/../phylo_tree_config.json';
$message = '';
$error = '';

// Function to get organism metadata
function get_organisms_metadata($organism_data_dir) {
    $organisms = [];
    $symlinks = glob($organism_data_dir . '/*', GLOB_ONLYDIR);
    
    foreach ($symlinks as $org_path) {
        $organism_json = $org_path . '/organism.json';
        if (file_exists($organism_json)) {
            $data = json_decode(file_get_contents($organism_json), true);
            $organism_name = basename($org_path);
            
            $organisms[$organism_name] = [
                'genus' => $data['genus'] ?? '',
                'species' => $data['species'] ?? '',
                'taxon_id' => $data['taxon_id'] ?? '',
                'common_name' => $data['common_name'] ?? ''
            ];
        }
    }
    
    return $organisms;
}

// Function to fetch taxonomic lineage from NCBI
function fetch_taxonomy_lineage($taxon_id) {
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=taxonomy&id={$taxon_id}&retmode=xml";
    
    // Try curl first, fall back to file_get_contents
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$response) {
            return null;
        }
    } else {
        // Fallback to file_get_contents
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
    }
    
    $xml = simplexml_load_string($response);
    if (!$xml || !isset($xml->Taxon)) {
        return null;
    }
    
    $lineage = [];
    $taxon = $xml->Taxon;
    
    // Parse lineage
    if (isset($taxon->LineageEx->Taxon)) {
        foreach ($taxon->LineageEx->Taxon as $rank_taxon) {
            $rank = (string)$rank_taxon->Rank;
            $sci_name = (string)$rank_taxon->ScientificName;
            
            // Only include standard taxonomic ranks
            $valid_ranks = ['superkingdom', 'kingdom', 'phylum', 'class', 'order', 'family', 'genus'];
            if (in_array($rank, $valid_ranks)) {
                $lineage[] = [
                    'rank' => $rank,
                    'name' => $sci_name
                ];
            }
        }
    }
    
    // Add the species itself
    $lineage[] = [
        'rank' => 'species',
        'name' => (string)$taxon->ScientificName
    ];
    
    return $lineage;
}

// Function to build tree from organisms
function build_tree_from_organisms($organisms) {
    $all_lineages = [];
    
    foreach ($organisms as $organism_name => $data) {
        if (empty($data['taxon_id'])) {
            continue;
        }
        
        $lineage = fetch_taxonomy_lineage($data['taxon_id']);
        if ($lineage) {
            $all_lineages[$organism_name] = [
                'lineage' => $lineage,
                'common_name' => $data['common_name']
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
        if ($_POST['action'] === 'generate') {
            try {
                $organisms = get_organisms_metadata($organism_data_dir);
                
                if (empty($organisms)) {
                    $error = "No organisms found in {$organism_data_dir}";
                } else {
                    $tree_data = build_tree_from_organisms($organisms);
                    
                    // Save to file
                    $json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    if (file_put_contents($tree_config_file, $json) !== false) {
                        $message = "Phylogenetic tree generated successfully! Found " . count($organisms) . " organisms.";
                    } else {
                        $error = "Failed to write tree config file";
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
                if (file_put_contents($tree_config_file, json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
                    $message = "Tree configuration saved successfully!";
                } else {
                    $error = "Failed to save tree configuration";
                }
            } else {
                $error = "Invalid JSON: " . json_last_error_msg();
            }
        }
    }
}

// Load current tree config
$current_tree = null;
if (file_exists($tree_config_file)) {
    $current_tree = json_decode(file_get_contents($tree_config_file), true);
}

// Get available organisms
$organisms = get_organisms_metadata($organism_data_dir);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Phylogenetic Tree - Admin</title>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fa fa-project-diagram"></i> Manage Phylogenetic Tree</h2>
            <p class="text-muted">Generate and customize the phylogenetic tree displayed on the homepage.</p>
            
            <div class="mb-3">
                <a href="index.php" class="btn btn-secondary">← Back to Admin Tools</a>
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
                        <button type="submit" class="btn btn-primary" id="generateBtn">
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
                        <button type="submit" class="btn btn-success">
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
