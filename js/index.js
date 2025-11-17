// Index page shared functions

function handleToolClick(toolId) {
  if (!phyloTree || phyloTree.selectedOrganisms.size === 0) {
    alert('Please select at least one organism');
    return;
  }
  
  const organisms = Array.from(phyloTree.selectedOrganisms);
  
  if (toolId === 'phylo_search') {
    const params = organisms.map(org => `organisms[]=${encodeURIComponent(org)}`).join('&');
    window.location.href = `tools/search/multi_organism_search.php?${params}`;
  } else if (toolId === 'download_fasta') {
    const params = organisms.map(org => encodeURIComponent(org)).join(',');
    window.location.href = `tools/extract/retrieve_sequences.php?organisms=${params}`;
  }
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
