/**
 * Manage Taxonomy Tree - Page-Specific Functionality
 */

/**
 * Manual Collapse Handler with Chevron Rotation
 */
(function() {
    // Add styles for collapse behavior
    const style = document.createElement('style');
    style.textContent = `
        .collapse {
            display: none;
        }
        .collapse.show {
            display: block;
        }
        .fa-chevron-down {
            transition: transform 0.3s ease;
        }
    `;
    document.head.appendChild(style);
    
    // Add toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const triggers = document.querySelectorAll('[data-bs-toggle="collapse"]');
        triggers.forEach(function(trigger) {
            // Remove data-bs-toggle to prevent Bootstrap from handling it
            trigger.removeAttribute('data-bs-toggle');
            
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                const target = this.getAttribute('data-bs-target') || this.getAttribute('href');
                if (target) {
                    const element = document.querySelector(target);
                    if (element) {
                        const isOpen = element.classList.contains('show');
                        element.classList.toggle('show');
                        
                        // Rotate chevron
                        const chevron = this.querySelector('.fa-chevron-down');
                        if (chevron) {
                            chevron.style.transform = !isOpen 
                                ? 'rotate(-180deg)' 
                                : 'rotate(0deg)';
                        }
                    }
                }
            }, true);
        });
    });
})();

/**
 * Tree Preview Renderer
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Show loading state when generating
        const generateForm = document.getElementById('generateForm');
        if (generateForm) {
            generateForm.addEventListener('submit', function() {
                const btn = document.getElementById('generateBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating... Please wait';
                }
            });
        }
        
        // Render tree preview if tree data exists
        if (typeof currentTree !== 'undefined' && currentTree) {
            renderTreePreview(currentTree);
        }
    });
    
    /**
     * Render tree node hierarchy
     */
    function renderTreeNode(node, level = 0) {
        const indent = level * 20;
        const isLeaf = !!node.organism;
        const icon = isLeaf ? '🧬' : (level === 0 ? '🌳' : '├');
        
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
    
    /**
     * Render the tree preview
     */
    function renderTreePreview(treeData) {
        const previewElement = document.getElementById('tree-preview');
        if (previewElement && treeData.tree) {
            previewElement.innerHTML = renderTreeNode(treeData.tree);
        }
    }
})();

