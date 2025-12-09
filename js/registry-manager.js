/**
 * Registry Manager - JavaScript functions for registry display
 * Handles function registry rendering, filtering, and interactions
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
        header.innerHTML = `<span><i class="fa fa-file-code"></i> ${htmlEscape(fileData.name)}</span> <span class="toggle-icon">▼</span>`;
        header.onclick = () => toggleFile(header);
        
        const list = document.createElement('div');
        list.className = 'functions-list open';
        list.style.maxHeight = 'none';
        
        fileData.functions.forEach(func => {
            const item = document.createElement('div');
            item.className = 'function-item';
            item.innerHTML = `
                <div class="function-name">
                    <i class="fa fa-cog"></i> ${htmlEscape(func.name)}
                </div>
                <div class="function-code hidden">
                    <pre><code>${htmlEscape(func.code)}</code></pre>
                </div>
            `;
            item.style.cursor = 'pointer';
            item.onclick = () => toggleFunctionCode(item);
            list.appendChild(item);
        });
        
        fileSection.appendChild(header);
        fileSection.appendChild(list);
        container.appendChild(fileSection);
    });
    
    if (registryData.unused && registryData.unused.length > 0) {
        const unusedSection = document.createElement('div');
        unusedSection.className = 'unused-section';
        
        const unusedHeader = document.createElement('div');
        unusedHeader.className = 'unused-section-header';
        unusedHeader.innerHTML = `<span><i class="fa fa-exclamation-circle"></i> Unused Functions (${registryData.unused.length})</span> <span class="toggle-icon">▼</span>`;
        unusedHeader.style.cursor = 'pointer';
        unusedHeader.onclick = () => toggleUnusedSection(unusedHeader);
        
        const unusedList = document.createElement('div');
        unusedList.className = 'unused-content hidden';
        
        registryData.unused.forEach(func => {
            const item = document.createElement('li');
            item.innerHTML = htmlEscape(func);
            unusedList.appendChild(item);
        });
        
        unusedSection.appendChild(unusedHeader);
        unusedSection.appendChild(unusedList);
        container.appendChild(unusedSection);
    }
}

function toggleFile(header) {
    const list = header.nextElementSibling;
    const toggleIcon = header.querySelector('.toggle-icon');
    
    if (list.classList.contains('open')) {
        list.classList.remove('open');
        list.style.maxHeight = '0';
        toggleIcon.textContent = '▶';
    } else {
        list.classList.add('open');
        list.style.maxHeight = 'none';
        toggleIcon.textContent = '▼';
    }
}

function toggleFunctionCode(header) {
    const codeDiv = header.querySelector('.function-code');
    if (codeDiv) {
        codeDiv.classList.toggle('hidden');
    }
}

function toggleUnusedSection(header) {
    const content = header.nextElementSibling;
    const toggleIcon = header.querySelector('.toggle-icon');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        toggleIcon.textContent = '▼';
    } else {
        content.classList.add('hidden');
        toggleIcon.textContent = '▶';
    }
}

function expandAllFiles() {
    document.querySelectorAll('.file-section').forEach(section => {
        const header = section.querySelector('.file-header');
        const list = section.querySelector('.functions-list');
        const toggleIcon = header.querySelector('.toggle-icon');
        
        if (!list.classList.contains('open')) {
            list.classList.add('open');
            list.style.maxHeight = 'none';
            toggleIcon.textContent = '▼';
        }
    });
}

function collapseAllFiles() {
    document.querySelectorAll('.file-section').forEach(section => {
        const header = section.querySelector('.file-header');
        const list = section.querySelector('.functions-list');
        const toggleIcon = header.querySelector('.toggle-icon');
        
        if (list.classList.contains('open')) {
            list.classList.remove('open');
            list.style.maxHeight = '0';
            toggleIcon.textContent = '▶';
        }
    });
}

function filterRegistry() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    document.querySelectorAll('.file-section').forEach(section => {
        let hasMatch = false;
        section.querySelectorAll('.function-item').forEach(item => {
            const funcName = item.querySelector('.function-name').textContent.toLowerCase();
            if (funcName.includes(searchTerm)) {
                item.style.display = 'block';
                hasMatch = true;
            } else {
                item.style.display = 'none';
            }
        });
        section.style.display = hasMatch ? 'block' : 'none';
    });
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    filterRegistry();
}

function downloadRegistry() {
    const registryJson = JSON.stringify(registryData, null, 2);
    const blob = new Blob([registryJson], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'function_registry.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function generateRegistry() {
    if (confirm('This will regenerate the function registry. Continue?')) {
        // This would typically make an AJAX call to regenerate the registry
        alert('Registry regeneration would be triggered here');
    }
}

function htmlEscape(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
