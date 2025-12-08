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
    
    // Attach event listeners
    const toggleAllBtn = document.getElementById('toggleAllFilesBtn');
    if (toggleAllBtn) {
        toggleAllBtn.addEventListener('click', toggleAllFiles);
    }
    
    const unusedHeader = document.getElementById('unusedSectionHeader');
    if (unusedHeader) {
        unusedHeader.addEventListener('click', function() {
            toggleUnusedSection(this);
        });
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
        fileSection.style.overflow = 'visible';
        fileSection.setAttribute('data-file', fileData.name);
        
        const header = document.createElement('div');
        header.className = 'file-header';
        header.style.cssText = 'display: flex; justify-content: space-between; align-items: center; cursor: pointer;';
        header.innerHTML = `
            📄 ${htmlEscape(fileData.name)} <span class="file-count">(${fileData.count})</span>
            <span class="expand-arrow">▶</span>
        `;
        
        const listContainer = document.createElement('div');
        listContainer.className = 'functions-list';
        listContainer.style.cssText = 'max-height: 0; overflow: hidden; transition: max-height 0.3s ease; padding: 0;';
        
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
            
            // PHP Files using this function (JS specific)
            if (func.phpFilesCount && func.phpFilesCount > 0 && func.phpFiles) {
                const phpFiles = document.createElement('div');
                phpFiles.className = 'function-usages';
                phpFiles.style.backgroundColor = '#f0e6ff';
                phpFiles.style.borderLeftColor = '#9966ff';
                
                let html = `<strong>🐘 Used in ${func.phpFilesCount} PHP file(s):</strong>
                    <ul>`;
                
                func.phpFiles.forEach(phpFile => {
                    html += `<li><code>${htmlEscape(phpFile)}</code></li>`;
                });
                
                html += `</ul>`;
                phpFiles.innerHTML = html;
                item.appendChild(phpFiles);
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
                
                let html = `<strong>📍 Function calls in ${Object.keys(usagesByFile).length} file(s) (${func.usages.length} times):</strong>
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
        header.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleFile(header);
        });
    });
}

/**
 * Toggle file section
 */
function toggleFile(header) {
    const list = header.nextElementSibling;
    if (list && list.classList.contains('functions-list')) {
        const isOpen = list.classList.contains('open');
        list.classList.toggle('open');
        
        // Force inline styles to ensure visibility
        if (list.classList.contains('open')) {
            list.style.maxHeight = '10000px';
            list.style.overflow = 'visible';
        } else {
            list.style.maxHeight = '0';
            list.style.overflow = 'hidden';
        }
        
        const arrow = header.querySelector('.expand-arrow');
        if (arrow) {
            arrow.textContent = !isOpen ? '▼' : '▶';
        }
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
 * Toggle all file sections (toggleAll and toggleAllFiles are aliases)
 */
function toggleAll() {
    const lists = document.querySelectorAll('.functions-list');
    const hiddenCount = document.querySelectorAll('.functions-list:not(.open)').length;
    
    lists.forEach(list => {
        if (hiddenCount > 0) {
            list.classList.add('open');
            // Apply inline styles for open state
            list.style.maxHeight = '10000px';
            list.style.overflow = 'visible';
        } else {
            list.classList.remove('open');
            // Apply inline styles for closed state
            list.style.maxHeight = '0';
            list.style.overflow = 'hidden';
        }
    });
    
    // Update all arrows
    document.querySelectorAll('.file-header .expand-arrow').forEach(arrow => {
        const list = arrow.closest('.file-header').nextElementSibling;
        if (list && list.classList.contains('functions-list')) {
            arrow.textContent = list.classList.contains('open') ? '▼' : '▶';
        }
    });
}

function toggleAllFiles() {
    toggleAll();
}

/**
 * Expand all files
 */
function expandAllFiles() {
    document.querySelectorAll('.functions-list').forEach(list => {
        list.classList.add('open');
        list.style.maxHeight = '10000px';
        list.style.overflow = 'visible';
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
        list.style.maxHeight = '0';
        list.style.overflow = 'hidden';
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
 * Generate registry by calling backend API
 */
function generateRegistry() {
    const btn = document.getElementById('generateBtn');
    const msg = document.getElementById('registryMessage');
    
    if (!btn) return;
    
    // Get registry type from button data attribute (default to 'php')
    const registryType = btn.getAttribute('data-registry-type') || 'php';
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
    
    // Call the backend API to generate registry
    fetch('/moop/admin/api/generate_registry.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ type: registryType })
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Generate/Update Registry';
        
        if (data.success) {
            // Show success message
            if (msg) {
                msg.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                    '<i class="fa fa-check-circle"></i> ' + data.message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
            }
            
            // Reload page after 2 seconds to show updated status
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            // Show error message
            if (msg) {
                msg.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    '<i class="fa fa-exclamation-circle"></i> ' + data.message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
            }
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Generate/Update Registry';
        
        if (msg) {
            msg.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                '<i class="fa fa-exclamation-circle"></i> Error: ' + error.message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>';
        }
    });
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
