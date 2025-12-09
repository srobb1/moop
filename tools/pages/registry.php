<?php
/**
 * PHP Function Registry Display
 * Renders registry from JSON data with search, filtering, and toggle functionality
 */

$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');
$json_registry = $docs_path . '/function_registry_test.json';

// Load JSON registry
$registry = null;
$lastUpdate = 'Never';
if (file_exists($json_registry)) {
    $lastUpdate = date('Y-m-d H:i:s', filemtime($json_registry));
    $json_content = file_get_contents($json_registry);
    $registry = json_decode($json_content, true);
}
?>

<div class="container mt-5">
    <!-- Header -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0"><i class="fa fa-list"></i> PHP Function Registry</h2>
        </div>
        <div class="card-body">
            <div class="registry-info">
                <strong>About this Registry:</strong> This is an auto-generated catalog of all PHP functions in the MOOP codebase.
                It includes functions used by the system and those that are currently unused. Last updated: <code><?= htmlspecialchars($lastUpdate) ?></code>
            </div>

            <!-- Search Controls -->
            <div class="registry-controls">
                <input type="text" id="searchInput" class="form-control" placeholder="Search functions..." onkeyup="filterRegistry()">
                <button class="btn btn-primary btn-sm" onclick="expandAllFiles()"><i class="fa fa-expand"></i> Expand All</button>
                <button class="btn btn-primary btn-sm" onclick="collapseAllFiles()"><i class="fa fa-compress"></i> Collapse All</button>
                <button class="btn btn-secondary btn-sm" onclick="clearSearch()"><i class="fa fa-times"></i> Clear</button>
                <button class="btn btn-info btn-sm" onclick="downloadRegistry()"><i class="fa fa-download"></i> Download JSON</button>
                <button class="btn btn-warning btn-sm" onclick="generateRegistry()"><i class="fa fa-refresh"></i> Regenerate</button>
            </div>

            <!-- Stats -->
            <?php if ($registry): ?>
                <div class="alert alert-info">
                    <strong>Total Files:</strong> <?= count($registry['files']) ?> | 
                    <strong>Total Functions:</strong> <?= array_sum(array_map(function($f) { return count($f['functions']); }, $registry['files'])) ?> | 
                    <strong>Unused Functions:</strong> <?= count($registry['unused'] ?? []) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Files Container -->
    <div id="filesContainer" class="files-container">
        <!-- Populated by renderRegistry() JavaScript function -->
    </div>

    <!-- Registry Data (for JavaScript) -->
    <script type="application/json" id="registryData">
<?php echo json_encode($registry, JSON_UNESCAPED_SLASHES); ?></script>
</div>
