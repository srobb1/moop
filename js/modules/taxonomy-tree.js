// Phylogenetic Tree Selector
class PhyloTree {
    constructor(containerId, data, userAccess) {
        this.container = document.getElementById(containerId);
        this.data = data;
        this.userAccess = userAccess;
        this.selectedOrganisms = new Set();
        this.nodeMap = new Map();
        this.render();
        this.attachEventListeners();
    }

    hasAccess(organism) {
        if (!this.userAccess) return false;
        // If userAccess is empty object, no access
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
        const allSelected = organisms.every(org => this.selectedOrganisms.has(org));
        
        if (allSelected) {
            organisms.forEach(org => this.selectedOrganisms.delete(org));
        } else {
            organisms.forEach(org => this.selectedOrganisms.add(org));
        }
        
        this.updateUI();
        this.updateSelectedList();
    }

    updateUI() {
        document.querySelectorAll('.phylo-node').forEach(nodeElement => {
            const nodeId = nodeElement.dataset.nodeId;
            const nodeData = this.nodeMap.get(nodeId);
            if (!nodeData) return;
            
            const organisms = this.getAllOrganismsInNode(nodeData);
            const allSelected = organisms.length > 0 && organisms.every(org => this.selectedOrganisms.has(org));
            const someSelected = organisms.some(org => this.selectedOrganisms.has(org));
            
            nodeElement.classList.remove('selected', 'partial');
            if (allSelected) {
                nodeElement.classList.add('selected');
            } else if (someSelected) {
                nodeElement.classList.add('partial');
            }
        });
    }

    updateSelectedList() {
        const listEl = document.getElementById('selected-organisms-list');
        const countEl = document.getElementById('selected-count');
        const searchBtn = document.getElementById('taxonomy-search-btn');
        
        if (this.selectedOrganisms.size === 0) {
            listEl.innerHTML = '<div class="text-muted fst-italic">No organisms selected</div>';
            countEl.textContent = '0';
            if (searchBtn) searchBtn.disabled = true;
        } else {
            const items = Array.from(this.selectedOrganisms).map(org => {
                const parts = org.split('_');
                const formatted = `<i>${parts[0]} ${parts[1]}</i>`;
                return `<span class="badge bg-primary me-1 mb-1">${formatted}</span>`;
            }).join('');
            listEl.innerHTML = items;
            countEl.textContent = this.selectedOrganisms.size;
            if (searchBtn) searchBtn.disabled = false;
        }
    }

    navigateToSearch() {
        const organisms = Array.from(this.selectedOrganisms);
        if (organisms.length === 1) {
            window.open(`tools/organism.php?organism=${encodeURIComponent(organisms[0])}&multi_search[]=${encodeURIComponent(organisms[0])}`, '_blank');
        } else {
            const params = organisms.map(org => `organisms[]=${encodeURIComponent(org)}`).join('&');
            window.open(`tools/multi_organism.php?${params}`, '_blank');
        }
    }

    renderNode(node, level = 0, parentPath = '') {
        const hasAccessibleChildren = this.getAllOrganismsInNode(node).length > 0;
        if (!hasAccessibleChildren) return '';

        const isLeaf = !!node.organism;
        const indent = level * 20;
        const nodeClass = isLeaf ? 'phylo-leaf' : 'phylo-branch';
        let icon = level === 0 ? '🌳' : '├';
        if (isLeaf) {
            icon = node.image 
                ? `<img src="${node.image}" alt="${node.name}" style="width:18px;height:18px;border-radius:4px;object-fit:cover;vertical-align:middle;">`
                : '🌳';
        }
        
        // Create unique ID for this node
        const nodeId = parentPath ? `${parentPath}/${node.name}` : node.name;
        this.nodeMap.set(nodeId, node);
        
        let displayName = node.name;
        if (isLeaf && node.common_name) {
            displayName += ` <span class="text-muted small">(${node.common_name})</span>`;
        }

        const childrenCount = this.getAllOrganismsInNode(node).length;
        const countBadge = !isLeaf ? `<span class="badge bg-secondary ms-2">${childrenCount}</span>` : '';

        let html = `
            <div class="phylo-node ${nodeClass}" 
                 style="margin-left: ${indent}px"
                 data-node-id="${nodeId}">
                <span class="phylo-icon">${icon}</span>
                <span class="phylo-name">${displayName}</span>
                ${countBadge}
            </div>
        `;

        if (node.children) {
            node.children.forEach(child => {
                html += this.renderNode(child, level + 1, nodeId);
            });
        }

        return html;
    }

    attachEventListeners() {
        this.container.addEventListener('click', (e) => {
            const nodeElement = e.target.closest('.phylo-node');
            if (nodeElement) {
                const nodeId = nodeElement.dataset.nodeId;
                const node = this.nodeMap.get(nodeId);
                if (node) {
                    this.toggleNode(node);
                }
            }
        });
    }

    render() {
        const html = this.renderNode(this.data.tree);
        if (this.container) {
            this.container.innerHTML = html;
        } else {
            console.error('Container not found!');
        }
    }
}

// Initialize on page load
let phyloTree = null;

function initPhyloTree(treeData, userAccess) {
    phyloTree = new PhyloTree('taxonomy-tree-container', treeData, userAccess);
}
