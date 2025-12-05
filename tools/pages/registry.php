<?php
/**
 * PHP Function Registry Display Content
 * Displays the PHP function registry with search, filter, and update capabilities
 */

$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');
$html_registry = $docs_path . '/function_registry.html';

$lastUpdate = file_exists($html_registry) ? date('Y-m-d H:i:s', filemtime($html_registry)) : 'Never';
?>

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
                >
            </div>
            
            <div class="mb-3">
                <label class="form-label">Search in:</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchAll" value="all" checked>
                        <label class="form-check-label" for="searchAll">All</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchName" value="name">
                        <label class="form-check-label" for="searchName">Function Name</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchCode" value="code">
                        <label class="form-check-label" for="searchCode">Code</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="searchMode" id="searchComment" value="comment">
                        <label class="form-check-label" for="searchComment">Comments</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-0">
                <button class="btn btn-sm btn-secondary" onclick="expandAllFiles()">
                    <i class="fa fa-folder-open"></i> Expand All
                </button>
                <button class="btn btn-sm btn-secondary" onclick="collapseAllFiles()">
                    <i class="fa fa-folder"></i> Collapse All
                </button>
                <button class="btn btn-sm btn-secondary" onclick="clearSearch()">
                    <i class="fa fa-times"></i> Clear Search
                </button>
            </div>
            <div id="searchMessage" class="mt-2"></div>
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
                        <h4 id="totalCount" class="text-primary">-</h4>
                        <p>Total Functions</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="fileCount" class="text-info">-</h4>
                        <p>Files Scanned</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="usedCount" class="text-success">-</h4>
                        <p>Functions Used</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4 id="unusedCount" class="text-warning">-</h4>
                        <p>Possibly Unused</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Registry Content -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fa fa-list"></i> Functions</h5>
        </div>
        <div class="card-body" id="registryContent">
            <?php
            if (!file_exists($html_registry)) {
                echo '<div class="alert alert-warning">';
                echo '<i class="fa fa-warning"></i> Registry not yet generated. Click "Generate Registry" to create it.';
                echo '</div>';
            } else {
                // Load and display the generated registry
                $registryContent = file_get_contents($html_registry);
                
                // Extract and display just the content between body tags, stripping unnecessary parts
                if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $registryContent, $matches)) {
                    $bodyContent = $matches[1];
                    
                    // Remove old inline styles and scripts
                    $bodyContent = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $bodyContent);
                    $bodyContent = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $bodyContent);
                    
                    // Remove wrapper divs that the old generator added
                    $bodyContent = preg_replace('/<div class="container"[^>]*>/', '', $bodyContent);
                    $bodyContent = preg_replace('/<\/div>\s*$/', '', $bodyContent);
                    
                    // Display the registry sections
                    echo $bodyContent;
                } else {
                    echo '<div class="alert alert-danger"><i class="fa fa-exclamation"></i> Error loading registry file.</div>';
                }
            }
            ?>
        </div>
    </div>
</div>
