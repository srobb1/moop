// Phylogenetic Tree Selector
class PhyloTree {
    constructor(containerId, data, userAccess) {
        this.container = document.getElementById(containerId);
        this.data = data;
        this.userAccess = userAccess;
        this.nodeMap = new Map();
        this.render();
        this.attachEventListeners();
    }

    hasAccess(organism) {
        if (!this.userAccess) return false;
        if (Object.keys(this.userAccess).length === 0) return false;
        return this.userAccess.hasOwnProperty(organism);
    }

    getAllOrganismsInNode(node) {
        const organisms = [];
        const traverse = (n) => {
            if (n.organism && this.hasAccess(n.organism)) {
                organisms.push(n.organism);
            }
            if (n.children) {
                n.children.forEach(child => traverse(child));
            }
        };
        traverse(node);
        return organisms;
    }

    toggleNode(node) {
        const organisms = this.getAllOrganismsInNode(node);
        const allSelected = organisms.every(org => selectedOrganisms.has(org));
        if (allSelected) {
            organisms.forEach(org => selectedOrganisms.delete(org));
        } else {
            organisms.forEach(org => selectedOrganisms.add(org));
        }
        this.updateUI();
        if (typeof updateSelectedList !== 'undefined') {
            updateSelectedList();
            refreshAllHighlights();
        }
    }

    toggleCollapse(nodeId) {
        // Use dataset comparison to avoid CSS selector escaping issues
        const children = Array.from(this.container.querySelectorAll('.phylo-children'))
            .find(el => el.dataset.parentId === nodeId);
        const nodeEl = Array.from(this.container.querySelectorAll('.phylo-node'))
            .find(el => el.dataset.nodeId === nodeId);
        if (!children) return;
        const isNowCollapsed = children.classList.toggle('collapsed');
        nodeEl?.classList.toggle('node-collapsed', isNowCollapsed);
    }

    updateUI() {
        document.querySelectorAll('.phylo-node').forEach(nodeElement => {
            const nodeId = nodeElement.dataset.nodeId;
            const nodeData = this.nodeMap.get(nodeId);
            if (!nodeData) return;
            const organisms = this.getAllOrganismsInNode(nodeData);
            const allSelected = organisms.length > 0 && organisms.every(org => selectedOrganisms.has(org));
            const someSelected = organisms.some(org => selectedOrganisms.has(org));
            nodeElement.classList.remove('selected', 'partial');
            if (allSelected) {
                nodeElement.classList.add('selected');
            } else if (someSelected) {
                nodeElement.classList.add('partial');
            }
            const btnIcon = nodeElement.querySelector('.phylo-select-btn i');
            if (btnIcon) btnIcon.className = allSelected ? 'fas fa-check' : 'fas fa-plus';
        });
    }

    renderNode(node, level = 0, parentPath = '') {
        const hasAccessibleChildren = this.getAllOrganismsInNode(node).length > 0;
        if (!hasAccessibleChildren) return '';

        const isLeaf = !!node.organism;
        const nodeId = parentPath ? `${parentPath}/${node.name}` : node.name;
        this.nodeMap.set(nodeId, node);

        const indent = level * 16;
        const nodeClass = isLeaf ? 'phylo-leaf' : 'phylo-branch';

        const icon = isLeaf
            ? (node.image
                ? `<img src="${node.image}" alt="" style="width:18px;height:18px;border-radius:4px;object-fit:cover;vertical-align:middle;">`
                : '🌿')
            : '';

        let displayName = node.name;
        if (isLeaf && node.common_name) {
            displayName += ` <span class="text-muted small">(${node.common_name})</span>`;
        }

        const childrenCount = this.getAllOrganismsInNode(node).length;
        const countBadge = !isLeaf
            ? `<span class="badge bg-secondary ms-1" style="font-size:0.65rem;">${childrenCount}</span>`
            : '';

        const chevron = !isLeaf
            ? `<span class="phylo-toggle" data-node-id="${nodeId}"><i class="fas fa-chevron-down"></i></span>`
            : `<span class="phylo-toggle-spacer"></span>`;

        const selectBtn = `<button class="phylo-select-btn" data-node-id="${nodeId}" title="Add to selection">
                <i class="fas fa-plus"></i>
            </button>`;

        let html = `
            <div class="phylo-node ${nodeClass}"
                 style="padding-left: ${indent}px"
                 data-node-id="${nodeId}">
                ${chevron}
                ${icon ? `<span class="phylo-icon">${icon}</span>` : ''}
                <span class="phylo-name">${displayName}</span>
                ${countBadge}
                ${selectBtn}
            </div>`;

        if (node.children) {
            html += `<div class="phylo-children" data-parent-id="${nodeId}">`;
            node.children.forEach(child => {
                html += this.renderNode(child, level + 1, nodeId);
            });
            html += `</div>`;
        }

        return html;
    }

    collapseAll() {
        this.container.querySelectorAll('.phylo-children').forEach(el => el.classList.add('collapsed'));
        this.container.querySelectorAll('.phylo-node.phylo-branch').forEach(el => el.classList.add('node-collapsed'));

        // Re-expand the root level so Life's direct children remain visible
        const rootNode = this.container.querySelector('.phylo-node.phylo-branch');
        if (rootNode) {
            const rootId = rootNode.dataset.nodeId;
            const rootChildren = Array.from(this.container.querySelectorAll('.phylo-children'))
                .find(el => el.dataset.parentId === rootId);
            if (rootChildren) {
                rootChildren.classList.remove('collapsed');
                rootNode.classList.remove('node-collapsed');
            }
        }
    }

    expandAll() {
        this.container.querySelectorAll('.phylo-children').forEach(el => el.classList.remove('collapsed'));
        this.container.querySelectorAll('.phylo-node.phylo-branch').forEach(el => el.classList.remove('node-collapsed'));
    }

    attachEventListeners() {
        this.container.addEventListener('click', (e) => {
            // + button click — toggle selection only
            const selectBtn = e.target.closest('.phylo-select-btn');
            if (selectBtn) {
                const nodeId = selectBtn.dataset.nodeId;
                const node = this.nodeMap.get(nodeId);
                if (node) this.toggleNode(node);
                return;
            }
            // Chevron click — collapse/expand only
            const toggle = e.target.closest('.phylo-toggle');
            if (toggle) {
                const nodeId = toggle.dataset.nodeId;
                if (nodeId) this.toggleCollapse(nodeId);
                return;
            }
            // Branch row click — collapse/expand
            const nodeElement = e.target.closest('.phylo-node');
            if (nodeElement && nodeElement.classList.contains('phylo-branch')) {
                const nodeId = nodeElement.dataset.nodeId;
                if (nodeId) this.toggleCollapse(nodeId);
            }
        });
    }

    render() {
        if (this.container) {
            this.container.innerHTML = this.renderNode(this.data.tree);
        }
    }
}
