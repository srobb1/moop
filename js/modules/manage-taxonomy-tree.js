/**
 * Manage Taxonomy Tree - Page-Specific Functionality
 */

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
