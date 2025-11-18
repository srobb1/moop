// Index page shared functions

function handleToolClick(toolId) {
  if (!phyloTree || phyloTree.selectedOrganisms.size === 0) {
    alert('Please select at least one organism');
    return;
  }
  
  // Get tool button and its configuration
  const toolBtn = document.getElementById(`tool-btn-${toolId}`);
  if (!toolBtn) {
    console.error(`Tool button not found: tool-btn-${toolId}`);
    return;
  }
  
  const toolPath = toolBtn.getAttribute('data-tool-path');
  const contextParamsJson = toolBtn.getAttribute('data-context-params');
  
  if (!toolPath || !contextParamsJson) {
    console.error('Tool configuration missing from button');
    return;
  }
  
  const contextParams = JSON.parse(contextParamsJson);
  const organisms = Array.from(phyloTree.selectedOrganisms);
  
  // Get site name from global scope (set in index.php via PHP variable)
  // Fallback to 'moop' if not available
  const siteName = typeof sitePath !== 'undefined' ? sitePath.replace(/^\//,'').split('/')[0] : 'moop';
  
  // Build query parameters based on tool's context_params
  const params = new URLSearchParams();
  
  // Always add organisms if the tool supports them
  if (contextParams.includes('organisms')) {
    organisms.forEach(org => {
      params.append('organisms[]', org);
    });
  }
  
  // Add display_name
  if (contextParams.includes('display_name')) {
    params.append('display_name', 'Multi-Organism Search');
  }
  
  // Build URL: /{site}{toolPath}?params
  const url = `/${siteName}${toolPath}${params.toString() ? '?' + params.toString() : ''}`;
  window.location.href = url;
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
    }
  }
}
