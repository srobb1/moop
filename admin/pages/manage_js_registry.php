<?php
/**
 * JavaScript Function Registry Display
 * Renders registry from JSON data with search, filtering, and toggle functionality
 */

$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');
$json_registry = $docs_path . '/js_function_registry.json';

// Load JSON registry
$registry = null;
$lastUpdate = 'Never';
$registryStatus = [];
if (file_exists($json_registry)) {
    $json_content = file_get_contents($json_registry);
    $registry = json_decode($json_content, true);
    
    // Get registry status (includes staleness check)
    require_once __DIR__ . '/../../lib/functions_filesystem.php';
    $registryStatus = getRegistryLastUpdate($json_registry, $json_registry);
    $lastUpdate = $registryStatus['timestamp'];
    $isStale = $registryStatus['isStale'];
    $statusMessage = $registryStatus['status'];
}
?>


<div class="container mt-5">
    <h2><i class="fa fa-code"></i> JavaScript Function Registry</h2>
    
    <!-- Info Card -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutRegistry">
            <h5 class="mb-0"><i class="fa fa-info-circle"></i> About This Registry <i class="fa fa-chevron-down float-end"></i></h5>
        </div>
        <div class="collapse" id="aboutRegistry">
            <div class="card-body">
                <p>This is an auto-generated registry of all JavaScript functions in your codebase. It includes:</p>
                <ul class="mb-0">
                    <li>All functions from <code>js/</code> directory</li>
                    <li>Function documentation and comments</li>
                    <li>Usage tracking (where each function is called)</li>
                    <li>Detection of unused functions</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Control Panel -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa fa-cog"></i> Registry Controls</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <button class="btn btn-primary w-100" onclick="generateRegistry()" id="generateBtn" data-registry-type="js">
                        <i class="fa fa-refresh"></i> Generate/Update Registry
                    </button>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-info w-100" onclick="downloadRegistry()" title="Download as JSON">
                        <i class="fa fa-download"></i> Export as JSON
                    </button>
                </div>
            </div>
            <small class="text-muted">
                <i class="fa fa-clock-o"></i> Last updated: <strong><?php echo $lastUpdate; ?></strong>
                <?php if ($registry): ?>
                    <?php if ($isStale): ?>
                        <span class="badge bg-warning text-dark ms-2" title="Some JavaScript files are newer than the registry">
                            <i class="fa fa-exclamation-triangle"></i> You should update
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success ms-2" title="All JavaScript files are up to date">
                            <i class="fa fa-check-circle"></i> Up to date
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </small>
            <div id="registryMessage" class="mt-2"></div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fa fa-bar-chart"></i> Statistics</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="totalCount" class="text-primary"><?php echo $registry ? $registry['metadata']['totalFunctions'] : 0; ?></h4>
                        <p>Total Functions</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="fileCount" class="text-info"><?php echo $registry ? $registry['metadata']['totalFiles'] : 0; ?></h4>
                        <p>Files Scanned</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="usedCount" class="text-success"><?php echo $registry ? $registry['metadata']['totalFunctions'] - count($registry['unused']) : 0; ?></h4>
                        <p>Functions Used</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="unusedCount" class="text-warning"><?php echo $registry ? count($registry['unused']) : 0; ?></h4>
                        <p>Unused</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search & Filter -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fa fa-search"></i> Search & Filter</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <input 
                    type="text" 
                    id="searchInput" 
                    class="form-control" 
                    placeholder="Search functions, code, or comments..."
                    onkeyup="filterRegistry()"
                >
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Filter by Category:</label>
                    <select id="categoryFilter" class="form-select form-select-sm" onchange="filterRegistry()">
                        <option value="">All Categories</option>
                        <option value="ui-dom">🎨 UI/DOM</option>
                        <option value="event-handling">⚡ Event Handling</option>
                        <option value="data-processing">🔄 Data Processing</option>
                        <option value="search-filter">🔍 Search/Filter</option>
                        <option value="export">💾 Export</option>
                        <option value="datatable">📊 DataTable</option>
                        <option value="admin">⚙️ Admin</option>
                        <option value="blast">🧬 BLAST</option>
                        <option value="utilities">🛠️ Utilities</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Filter by Type:</label>
                    <select id="typeFilter" class="form-select form-select-sm" onchange="filterRegistry()">
                        <option value="">All Types</option>
                        <option value="dom-manipulation">DOM Manipulation</option>
                        <option value="asynchronous">Asynchronous/Async</option>
                        <option value="ajax">AJAX</option>
                        <option value="event-listener">Event Listener</option>
                        <option value="state-modifying">State Modifying</option>
                        <option value="error-handling">Error Handling</option>
                        <option value="validation">Validation</option>
                        <option value="loops">Contains Loops</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Search in:</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchAll" value="all" checked onchange="filterRegistry()">
                        <label class="form-check-label" for="searchAll">All</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchName" value="name" onchange="filterRegistry()">
                        <label class="form-check-label" for="searchName">Function Name</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchCode" value="code" onchange="filterRegistry()">
                        <label class="form-check-label" for="searchCode">Code</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchComment" value="comment" onchange="filterRegistry()">
                        <label class="form-check-label" for="searchComment">Comments</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-0">
                <button class="btn btn-sm btn-secondary" onclick="clearSearch()">
                    <i class="fa fa-times"></i> Clear All Filters
                </button>
            </div>
            <div id="searchMessage" class="mt-2"></div>
        </div>
    </div>
    
    <?php if (!$registry): ?>
        <!-- No Registry Alert -->
        <div class="alert alert-warning">
            <i class="fa fa-warning"></i> Registry not yet generated. Click "Generate Registry" to create it.
        </div>
    <?php else: ?>
        <!-- Registry Content -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white" style="display: flex; justify-content: space-between; align-items: center;">
                <h5 class="mb-0"><i class="fa fa-list"></i> Functions</h5>
                <button class="btn btn-sm btn-light" id="toggleAllFilesBtn" title="Toggle all sections">
                    <i class="fa fa-folder-open"></i> Toggle All
                </button>
            </div>
            <div class="card-body" id="registryContent">
                <div id="filesContainer"></div>
            </div>
        </div>
        
        <?php if (!empty($registry['unused'])): ?>
        <!-- Unused Functions Alert -->
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white" style="cursor: pointer;" id="unusedSectionHeader">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h5 class="mb-0"><i class="fa fa-exclamation-circle"></i> ⚠️ <?php echo count($registry['unused']); ?> Unused Function(s) Found</h5>
                    <span class="unused-arrow">▶</span>
                </div>
            </div>
            <div class="unused-content hidden" style="padding: 20px;">
                <p class="text-muted">These functions are defined but never called:</p>
                <ul class="list-unstyled">
                    <?php foreach ($registry['unused'] as $func): ?>
                    <li class="mb-2">
                        <code class="text-danger"><?php echo htmlspecialchars($func['name']); ?>()</code> 
                        in <small class="text-muted"><?php echo htmlspecialchars($func['file']); ?>:<?php echo $func['line']; ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Embedded registry data for JavaScript -->
        <script type="application/json" id="registryData">
<?php echo json_encode($registry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?></script>
    <?php endif; ?>
</div>

