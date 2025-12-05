/**
 * Registry Display JavaScript
 * Handles search, filtering, toggling, and generation for function registries
 */

/**
 * Load and display registry on page load
 */
function loadRegistry() {
    updateStats();
}

/**
 * Update statistics from registry data
 */
function updateStats() {
    const fileSections = document.querySelectorAll('.file-section');
    const totalFiles = fileSections.length;
    
    let totalFunctions = 0;
    let usedFunctions = 0;
    let unusedFunctions = 0;
    
    fileSections.forEach(section => {
        const items = section.querySelectorAll('.function-item');
        totalFunctions += items.length;
        
        items.forEach(item => {
            const usageText = item.textContent;
            if (usageText.includes('Used in: 0 files') || usageText.includes('possibly unused') || item.querySelector('.unused-badge')) {
                unusedFunctions++;
            } else {
                usedFunctions++;
            }
        });
    });
    
    const totalEl = document.getElementById('totalCount');
    const fileEl = document.getElementById('fileCount');
    const usedEl = document.getElementById('usedCount');
    const unusedEl = document.getElementById('unusedCount');
    
    if (totalEl) totalEl.textContent = totalFunctions || '-';
    if (fileEl) fileEl.textContent = totalFiles || '-';
    if (usedEl) usedEl.textContent = usedFunctions || '-';
    if (unusedEl) unusedEl.textContent = unusedFunctions || '-';
}

/**
 * Generate/update registry
 */
function generateRegistry() {
    const btn = document.getElementById('generateBtn');
    const msg = document.getElementById('registryMessage');
    
    if (!btn) return;
    
    const registryType = btn.dataset.registryType || 'php';
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
    
    fetch('/admin/api/generate_registry.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            type: registryType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (msg) {
            msg.innerHTML = `<div class="alert alert-${data.success ? 'success' : 'danger'}">
                <i class="fa fa-${data.success ? 'check' : 'exclamation'}"></i> ${data.message}
            </div>`;
        }
        
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Generate/Update Registry';
        
        if (data.success) {
            setTimeout(() => location.reload(), 1500);
        }
    })
    .catch(error => {
        if (msg) {
            msg.innerHTML = `<div class="alert alert-danger">
                <i class="fa fa-exclamation"></i> Error: ${error.message}
            </div>`;
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Generate/Update Registry';
    });
}

/**
 * Search/filter functions
 */
function filterFunctions() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    const searchMode = document.querySelector('input[name="searchMode"]:checked');
    const mode = searchMode ? searchMode.value : 'all';
    const fileSections = document.querySelectorAll('.file-section');
    
    let visibleSections = 0;
    let totalMatches = 0;
    
    fileSections.forEach(section => {
        const fileHeader = section.querySelector('.file-header');
        const fileName = fileHeader ? fileHeader.textContent.toLowerCase() : '';
        const functionItems = section.querySelectorAll('.function-item');
        let hasVisibleFunction = false;
        let sectionMatches = 0;
        
        functionItems.forEach(item => {
            const funcNameEl = item.querySelector('.function-name');
            const funcName = funcNameEl ? funcNameEl.textContent.toLowerCase() : '';
            const funcCodeEl = item.querySelector('.function-code');
            const funcCode = funcCodeEl ? funcCodeEl.textContent.toLowerCase() : '';
            const funcCommentEl = item.querySelector('.function-comment');
            const funcComment = funcCommentEl ? funcCommentEl.textContent.toLowerCase() : '';
            
            let match = false;
            
            if (searchTerm === '') {
                match = true;
            } else {
                if (mode === 'all') {
                    match = funcName.includes(searchTerm) || funcCode.includes(searchTerm) || funcComment.includes(searchTerm) || fileName.includes(searchTerm);
                } else if (mode === 'name') {
                    match = funcName.includes(searchTerm);
                } else if (mode === 'code') {
                    match = funcCode.includes(searchTerm);
                } else if (mode === 'comment') {
                    match = funcComment.includes(searchTerm);
                }
            }
            
            if (match) {
                item.classList.remove('hidden');
                hasVisibleFunction = true;
                sectionMatches++;
                totalMatches++;
            } else {
                item.classList.add('hidden');
            }
        });
        
        if (hasVisibleFunction || (searchTerm !== '' && fileName.includes(searchTerm))) {
            section.classList.remove('hidden');
            visibleSections++;
            
            // Auto-expand if searching
            if (searchTerm !== '') {
                const list = section.querySelector('.functions-list');
                if (list) list.classList.add('open');
            }
        } else {
            section.classList.add('hidden');
        }
    });
    
    // Show message if no results
    const msg = document.getElementById('searchMessage');
    if (msg) {
        if (searchTerm !== '' && totalMatches === 0) {
            msg.innerHTML = `<div class="alert alert-info"><i class="fa fa-info-circle"></i> No functions match your search.</div>`;
        } else {
            msg.innerHTML = '';
        }
    }
}

/**
 * Toggle file section visibility
 */
function toggleFile(header) {
    const list = header.nextElementSibling;
    if (list && list.classList.contains('functions-list')) {
        list.classList.toggle('open');
    }
}

/**
 * Expand all file sections
 */
function toggleAllFiles() {
    const lists = document.querySelectorAll('.functions-list');
    const hiddenCount = document.querySelectorAll('.functions-list:not(.open)').length;
    
    lists.forEach(list => {
        if (hiddenCount > 0) {
            list.classList.add('open');
        } else {
            list.classList.remove('open');
        }
    });
}

/**
 * Expand all file sections
 */
function expandAllFiles() {
    document.querySelectorAll('.functions-list').forEach(list => {
        list.classList.add('open');
    });
}

/**
 * Collapse all file sections
 */
function collapseAllFiles() {
    document.querySelectorAll('.functions-list').forEach(list => {
        list.classList.remove('open');
    });
}

/**
 * Toggle function code visibility
 */
function toggleFunctionCode(header) {
    const item = header.closest('.function-item');
    if (!item) return;
    
    const codeBlock = item.querySelector('.function-code');
    if (codeBlock) {
        codeBlock.classList.toggle('open');
        const arrow = header.querySelector('.expand-arrow');
        if (arrow) {
            arrow.textContent = codeBlock.classList.contains('open') ? '▼' : '▶';
        }
    }
}

/**
 * Download registry as JSON
 */
function downloadRegistry() {
    const registryType = document.body.dataset.registryType || 'php';
    const fileSections = document.querySelectorAll('.file-section');
    const registry = {};
    
    fileSections.forEach(section => {
        const fileHeader = section.querySelector('.file-header-title');
        const fileName = fileHeader ? fileHeader.textContent.trim() : 'unknown';
        registry[fileName] = [];
        
        section.querySelectorAll('.function-item').forEach(item => {
            const funcNameEl = item.querySelector('.function-name');
            const funcName = funcNameEl ? funcNameEl.textContent.trim() : '';
            const funcCodeEl = item.querySelector('.function-code');
            const funcCode = funcCodeEl ? funcCodeEl.textContent.trim() : '';
            const funcCommentEl = item.querySelector('.function-comment');
            const funcComment = funcCommentEl ? funcCommentEl.textContent.trim() : '';
            const metaEl = item.querySelector('.function-meta');
            const meta = metaEl ? metaEl.textContent.trim() : '';
            
            registry[fileName].push({
                name: funcName,
                code: funcCode,
                comment: funcComment,
                meta: meta
            });
        });
    });
    
    // Create download
    const dataStr = JSON.stringify(registry, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = registryType + '_function_registry_' + new Date().toISOString().slice(0,10) + '.json';
    link.click();
    URL.revokeObjectURL(url);
}

/**
 * Clear search
 */
function clearSearch() {
    const input = document.getElementById('searchInput');
    if (input) {
        input.value = '';
        filterFunctions();
    }
}

/**
 * Initialize registry page
 */
document.addEventListener('DOMContentLoaded', function() {
    loadRegistry();
    
    // Set up event listeners
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterFunctions);
    }
    
    const searchRadios = document.querySelectorAll('input[name="searchMode"]');
    searchRadios.forEach(radio => {
        radio.addEventListener('change', filterFunctions);
    });
    
    const fileHeaders = document.querySelectorAll('.file-header');
    fileHeaders.forEach(header => {
        header.addEventListener('click', function() {
            toggleFile(this);
        });
    });
    
    const functionHeaders = document.querySelectorAll('.function-header');
    functionHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            if (e.target.closest('.expand-arrow')) {
                toggleFunctionCode(this);
            }
        });
    });
});
