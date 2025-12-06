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

<script>
/**
 * Registry JavaScript Functions
 * Handles rendering, searching, filtering, and toggling
 */

let registryData = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const dataElement = document.getElementById('registryData');
    if (dataElement) {
        registryData = JSON.parse(dataElement.textContent);
        renderRegistry();
    }
});

/**
 * Render registry from JSON data
 */
function renderRegistry() {
    if (!registryData || !registryData.files) {
        return;
    }
    
    const container = document.getElementById('filesContainer');
    if (!container) {
        return;
    }
    
    container.innerHTML = '';
    
    let fileCount = 0;
    registryData.files.forEach(fileData => {
        fileCount++;
        const fileSection = document.createElement('div');
        fileSection.className = 'file-section';
        fileSection.setAttribute('data-file', fileData.name);
        
        const header = document.createElement('div');
        header.className = 'file-header';
        header.onclick = function() { toggleFile(this); };
        header.innerHTML = `
            📄 ${htmlEscape(fileData.name)} <span class="file-count">(${fileData.count})</span>
            <span class="expand-arrow">▶</span>
        `;
        
        const listContainer = document.createElement('div');
        listContainer.className = 'functions-list';
        
        fileData.functions.forEach(func => {
            const item = document.createElement('div');
            item.className = 'function-item';
            item.setAttribute('data-func', func.name);
            
            // Function header with toggle
            const funcHeader = document.createElement('div');
            funcHeader.className = 'function-header';
            funcHeader.onclick = function() { toggleFunctionCode(this); };
            funcHeader.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="function-name">${htmlEscape(func.name)}()</span>
                    <span class="function-counter">${func.usageCount}</span>
                    <span class="function-line">Line ${func.line}</span>
                </div>
                <span class="expand-arrow">▶</span>
            `;
            item.appendChild(funcHeader);
            
            // File location
            const fileLoc = document.createElement('div');
            fileLoc.style.cssText = "font-family: 'Courier New', monospace; font-size: 12px; color: #666; padding: 8px 0; border-bottom: 1px solid #ecf0f1; user-select: all;";
            fileLoc.textContent = `${fileData.name}: ${func.name}()`;
            item.appendChild(fileLoc);
            
            // Comment if exists
            if (func.comment) {
                const comment = document.createElement('div');
                comment.className = 'function-comment';
                const pre = document.createElement('pre');
                pre.textContent = func.comment;
                comment.appendChild(pre);
                item.appendChild(comment);
            }
            
            // Usages
            if (func.usages && func.usages.length > 0) {
                const usages = document.createElement('div');
                usages.className = 'function-usages';
                
                const usagesByFile = {};
                func.usages.forEach(usage => {
                    if (!usagesByFile[usage.file]) {
                        usagesByFile[usage.file] = [];
                    }
                    usagesByFile[usage.file].push(usage);
                });
                
                let html = `<strong>📍 Used in ${Object.keys(usagesByFile).length} file(s) (${func.usages.length} times):</strong>
                    <ul>`;
                
                Object.entries(usagesByFile).forEach(([file, usages]) => {
                    html += `<li><strong>${htmlEscape(file)}</strong> (${usages.length}x)
                        <ul style="margin-top: 5px;">`;
                    
                    usages.forEach(usage => {
                        html += `<li><code>line ${usage.line}</code>: <small>${htmlEscape(usage.context.substring(0, 80))}</small></li>`;
                    });
                    
                    html += `</ul></li>`;
                });
                
                html += `</ul>`;
                usages.innerHTML = html;
                item.appendChild(usages);
            }
            
            // Code
            if (func.code) {
                const code = document.createElement('div');
                code.className = 'function-code';
                code.innerHTML = `<pre>${htmlEscape(func.code)}</pre>`;
                item.appendChild(code);
            }
            
            listContainer.appendChild(item);
        });
        
        fileSection.appendChild(header);
        fileSection.appendChild(listContainer);
        container.appendChild(fileSection);
    });
    
    // Set up file sections
    const sections = container.querySelectorAll('.file-section');
    sections.forEach((section) => {
        section.style.display = 'block';
        section.style.visibility = 'visible';
    });
    
    // Attach click listeners to all file headers
    const headers = container.querySelectorAll('.file-header');
    headers.forEach((header) => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            toggleFile(this);
        });
    });
}

/**
 * Toggle file section
 */
function toggleFile(header) {
    const list = header.nextElementSibling;
    if (list && list.classList.contains('functions-list')) {
        list.classList.toggle('open');
        const arrow = header.querySelector('.expand-arrow');
        if (arrow) {
            arrow.textContent = list.classList.contains('open') ? '▼' : '▶';
        }
    } else {
        console.error('FAILED: nextElementSibling is not a functions-list');
    }
}

/**
 * Toggle function code visibility
 */
function toggleFunctionCode(header) {
    const item = header.closest('.function-item');
    if (!item) return;
    
    const code = item.querySelector('.function-code');
    if (code) {
        code.classList.toggle('open');
        const arrow = header.querySelector('.expand-arrow');
        if (arrow) {
            arrow.textContent = code.classList.contains('open') ? '▼' : '▶';
        }
    }
}

/**
 * Toggle unused section
 */
function toggleUnusedSection(header) {
    const content = header.nextElementSibling;
    if (content && content.classList.contains('unused-content')) {
        content.classList.toggle('hidden');
        const arrow = header.querySelector('.unused-arrow');
        if (arrow) {
            arrow.textContent = content.classList.contains('hidden') ? '▶' : '▼';
        }
    }
}

/**
 * Expand all files
 */
function expandAllFiles() {
    document.querySelectorAll('.functions-list').forEach(list => {
        list.classList.add('open');
        const header = list.previousElementSibling;
        if (header) {
            const arrow = header.querySelector('.expand-arrow');
            if (arrow) arrow.textContent = '▼';
        }
    });
}

/**
 * Collapse all files
 */
function collapseAllFiles() {
    document.querySelectorAll('.functions-list').forEach(list => {
        list.classList.remove('open');
        const header = list.previousElementSibling;
        if (header) {
            const arrow = header.querySelector('.expand-arrow');
            if (arrow) arrow.textContent = '▶';
        }
    });
}

/**
 * Filter/search registry
 */
function filterRegistry() {
    const searchInput = document.getElementById('searchInput');
    const searchMode = document.querySelector('input[name="searchMode"]:checked');
    
    if (!searchInput || !searchMode) return;
    
    const term = searchInput.value.toLowerCase();
    const mode = searchMode.value;
    
    document.querySelectorAll('.file-section').forEach(section => {
        const fileName = section.getAttribute('data-file').toLowerCase();
        let hasVisibleFunc = false;
        
        section.querySelectorAll('.function-item').forEach(item => {
            const funcName = item.getAttribute('data-func').toLowerCase();
            const code = item.querySelector('.function-code') ? item.querySelector('.function-code').textContent.toLowerCase() : '';
            const comment = item.querySelector('.function-comment') ? item.querySelector('.function-comment').textContent.toLowerCase() : '';
            
            let match = false;
            
            if (!term) {
                match = true;
            } else {
                if (mode === 'all') {
                    match = funcName.includes(term) || code.includes(term) || comment.includes(term) || fileName.includes(term);
                } else if (mode === 'name') {
                    match = funcName.includes(term);
                } else if (mode === 'code') {
                    match = code.includes(term);
                } else if (mode === 'comment') {
                    match = comment.includes(term);
                }
            }
            
            if (match) {
                item.classList.remove('hidden');
                hasVisibleFunc = true;
            } else {
                item.classList.add('hidden');
            }
        });
        
        if (hasVisibleFunc || !term || fileName.includes(term)) {
            section.classList.remove('hidden');
            if (term) {
                section.querySelector('.functions-list').classList.add('open');
                const header = section.querySelector('.file-header');
                const arrow = header.querySelector('.expand-arrow');
                if (arrow) arrow.textContent = '▼';
            }
        } else {
            section.classList.add('hidden');
        }
    });
}

/**
 * Clear search
 */
function clearSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
        filterRegistry();
    }
}

/**
 * Download registry as JSON
 */
function downloadRegistry() {
    if (!registryData) return;
    
    const dataStr = JSON.stringify(registryData, null, 2);
    const blob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'function_registry_' + new Date().toISOString().slice(0, 10) + '.json';
    link.click();
    URL.revokeObjectURL(url);
}

/**
 * Generate registry (placeholder - would call backend)
 */
function generateRegistry() {
    const btn = document.getElementById('generateBtn');
    const msg = document.getElementById('registryMessage');
    
    if (!btn) return;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
    
    // This would call the backend
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Generate/Update Registry';
    }, 2000);
}

/**
 * HTML escape utility
 */
function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize registry on page load or immediately if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        const dataElement = document.getElementById('registryData');
        if (dataElement) {
            try {
                registryData = JSON.parse(dataElement.textContent);
                renderRegistry();
            } catch (e) {
                console.error('Error parsing JSON or rendering:', e);
            }
        }
    });
} else {
    const dataElement = document.getElementById('registryData');
    if (dataElement) {
        try {
            registryData = JSON.parse(dataElement.textContent);
            renderRegistry();
        } catch (e) {
            console.error('Error parsing JSON or rendering:', e);
        }
    }
}
</script>
