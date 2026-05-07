// Index page shared functions

function handleToolClick(toolId) {
  if (!phyloTree || phyloTree.selectedOrganisms.size === 0) {
    alert('Please select at least one organism');
    return;
  }

  const toolBtn = document.getElementById(`tool-btn-${toolId}`);
  if (!toolBtn) {
    console.error(`Tool button not found: tool-btn-${toolId}`);
    return;
  }

  const toolPath = toolBtn.getAttribute('data-tool-path');
  if (!toolPath) {
    console.error('Tool path missing from button');
    return;
  }

  const organisms = Array.from(phyloTree.selectedOrganisms);
  const siteName = typeof sitePath !== 'undefined' ? sitePath.replace(/^\//,'').split('/')[0] : 'moop';

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = `/${siteName}${toolPath}`;
  form.target = '_blank';

  organisms.forEach(org => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'organisms[]';
    input.value = org;
    form.appendChild(input);
  });

  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}

function switchView(view) {
  const cardView = document.getElementById('card-view');
  const treeView = document.getElementById('tree-view');
  const cardBtn = document.getElementById('card-view-btn');
  const treeBtn = document.getElementById('tree-view-btn');
  
  if (view === 'card') {
    cardView.style.display = 'block';
    treeView.style.display = 'none';
    cardBtn.classList.add('active');
    treeBtn.classList.remove('active');
  } else {
    cardView.style.display = 'none';
    treeView.style.display = 'block';
    cardBtn.classList.remove('active');
    treeBtn.classList.add('active');
    
    // Initialize tree on first view
    if (!phyloTree) {
      initPhyloTree(treeData, userAccess);
      // Setup filter listener after tree is initialized
      setupTaxonomyFilter();
    }
  }
}

function setupTaxonomyFilter() {
  const filterInput = document.getElementById('taxonomy-filter');
  if (!filterInput) return;
  
  filterInput.addEventListener('keyup', function() {
    filterTaxonomyTree(this.value.toLowerCase());
  });
}

function filterTaxonomyTree(filterText) {
  const nodes = document.querySelectorAll('.phylo-node');
  
  nodes.forEach(node => {
    const nodeLabel = node.textContent.toLowerCase();
    const matches = filterText === '' || nodeLabel.includes(filterText);
    
    if (matches) {
      node.style.display = '';
      // Show parent nodes if this node is visible
      let parent = node.closest('.phylo-children')?.parentElement;
      while (parent && parent.classList.contains('phylo-node')) {
        parent.style.display = '';
        parent = parent.closest('.phylo-children')?.parentElement;
      }
    } else {
      node.style.display = 'none';
    }
  });
}
