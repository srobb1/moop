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

<style>
    /* Registry Styles */
    .file-section { 
        background: white; 
        margin: 15px 0; 
        border-radius: 4px; 
        overflow: hidden; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
        border-left: 4px solid #3498db; 
    }
    .file-section.hidden { display: none !important; }
    .file-header { 
        background: #34495e; 
        color: white; 
        padding: 15px 20px; 
        font-weight: bold; 
        cursor: pointer; 
        user-select: none; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
    }
    .file-header:hover { background: #2c3e50; }
    .functions-list { 
        padding: 0; 
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .functions-list.open { 
        max-height: 10000px;
    }
    .function-item { 
        padding: 15px 20px; 
        border-bottom: 1px solid #ecf0f1; 
        transition: background-color 0.2s; 
    }
    .function-item:last-child { border-bottom: none; }
    .function-item:hover { background-color: #f9f9f9; }
    .function-item.hidden { display: none !important; }
    .function-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        cursor: pointer; 
        padding-bottom: 10px; 
        user-select: none; 
    }
    .function-header:hover { color: #3498db; }
    .function-name { 
        font-family: 'Courier New', monospace; 
        color: #2980b9; 
        font-weight: 500; 
    }
    .function-counter { 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        background: #3498db; 
        color: white; 
        border-radius: 50%; 
        width: 28px; 
        height: 28px; 
        font-size: 12px; 
        font-weight: bold; 
        margin-left: 8px; 
        flex-shrink: 0; 
    }
    .expand-arrow { 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 14px; 
        margin-left: 10px; 
        flex-shrink: 0; 
        transition: transform 0.2s; 
    }
    .function-line { 
        font-size: 11px; 
        color: #7f8c8d; 
    }
    .function-code { 
        display: none; 
        background: #f8f8f8; 
        padding: 15px; 
        margin-top: 10px; 
        border-radius: 4px; 
        border-left: 3px solid #3498db; 
        overflow-x: auto; 
        font-family: 'Courier New', monospace; 
        font-size: 12px; 
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .function-code.open { 
        display: block;
        max-height: 10000px;
    }
    .function-comment { 
        background: #fffacd; 
        padding: 12px 15px; 
        margin: 10px 0; 
        border-left: 3px solid #f39c12; 
        border-radius: 3px; 
        font-size: 12px; 
    }
    .function-comment pre { 
        margin: 0; 
        font-family: 'Courier New', monospace; 
        color: #555; 
        white-space: pre-wrap; 
        word-break: break-word; 
    }
    .function-usages { 
        background: #e8f4f8; 
        padding: 12px 15px; 
        margin: 10px 0; 
        border-left: 3px solid #3498db; 
        border-radius: 3px; 
        font-size: 12px; 
    }
    .function-usages strong { 
        display: block; 
        margin-bottom: 8px; 
        color: #2c3e50; 
    }
    .function-usages ul { 
        margin: 0; 
        padding-left: 20px; 
    }
    .function-usages li { 
        margin: 6px 0; 
    }
    .function-usages code { 
        display: inline; 
        background: white; 
        padding: 2px 6px; 
        border-radius: 3px; 
        border: 1px solid #bbb; 
    }
    .file-count { 
        font-size: 12px; 
        color: #7f8c8d; 
        margin-left: 10px; 
    }
    .unused-content { 
        display: block; 
    }
    .unused-content.hidden { 
        display: none !important; 
    }
</style>

<div class="container mt-5">
    <h2><i class="fa fa-code"></i> PHP Function Registry</h2>
    
    <!-- Info Card -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10">
            <h5 class="mb-0"><i class="fa fa-info-circle"></i> About This Registry</h5>
        </div>
        <div class="card-body">
            <p>This is an auto-generated registry of all PHP functions in your codebase. It includes:</p>
            <ul class="mb-0">
                <li>All functions from <code>lib/</code>, <code>tools/</code>, <code>admin/</code>, and root directories</li>
                <li>Function documentation and comments</li>
                <li>Usage tracking (where each function is called)</li>
                <li>Detection of unused functions</li>
            </ul>
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
                    <button class="btn btn-primary w-100" onclick="generateRegistry()" id="generateBtn" data-registry-type="php">
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
                        <h4 id="totalCount" class="text-primary"><?php echo $registry['metadata']['totalFunctions']; ?></h4>
                        <p>Total Functions</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="fileCount" class="text-info"><?php echo $registry['metadata']['totalFiles']; ?></h4>
                        <p>Files Scanned</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="usedCount" class="text-success"><?php echo $registry['metadata']['totalFunctions'] - count($registry['unused']); ?></h4>
                        <p>Functions Used</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="unusedCount" class="text-warning"><?php echo count($registry['unused']); ?></h4>
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
                    <i class="fa fa-times"></i> Clear Search
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
                <button class="btn btn-sm btn-light" onclick="toggleAllFiles()" title="Toggle all sections">
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
            <div class="card-header bg-danger text-white" style="cursor: pointer;" onclick="toggleUnusedSection(this)">
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
<?php echo json_encode($registry, JSON_UNESCAPED_SLASHES); ?></script>
    <?php endif; ?>
</div>

