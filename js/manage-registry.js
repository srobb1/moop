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
