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

function rebuildTaxonomyTree(force) {
    const btn      = force ? document.getElementById('forceRefetchBtn')  : document.getElementById('rebuildTreeBtn');
    const statusEl = force ? document.getElementById('forceRefetchStatus') : document.getElementById('rebuildTreeStatus');
    const label    = btn?.innerHTML;
    refreshOrganismCache(btn, statusEl, force, label);
}


// Rebuild buttons (inline onclick until 2026-11-25). The force button's data-confirm question is
// asked by utilities.js first; a "Cancel" stops the click before it reaches this listener.
document.getElementById('rebuildTreeBtn')?.addEventListener('click', () => rebuildTaxonomyTree(false));
document.getElementById('forceRefetchBtn')?.addEventListener('click', () => rebuildTaxonomyTree(true));
