/**
 * Manage Users - Redesigned Form-Based UI
 * 
 * Features:
 * - Single form for create and edit
 * - Collapsible organism selection
 * - Assembly validation (require >= 1 unless admin)
 * - Inline stale assembly display
 * - Collapse handlers (replaces Bootstrap)
 */

// Global state
let isEditMode = false;
let currentEditUsername = '';
let selectedAccess = {};
let allExpanded = false;

function resetForm() {
  document.getElementById('userForm').reset();
  document.getElementById('username').readOnly = false;
  document.getElementById('is_create').value = '1';
  document.getElementById('original_username').value = '';
  document.getElementById('form-title').textContent = 'Create New User';
  document.getElementById('submit-text').textContent = 'Create User';
  document.getElementById('password_label').innerHTML = 'Password <span class="text-danger">*</span>';
  document.getElementById('password_help').style.display = 'none';
  document.getElementById('stale-alert').style.display = 'none';
  document.getElementById('stale-items').innerHTML = '';
  isEditMode = false;
  selectedAccess = {};
  // Don't call renderAssemblySelector here - it will be called after DOM is ready
}

function renderAssemblySelector() {
  const container = document.getElementById('access-container');
  
  if (!container) {
    return;
  }
  
  container.innerHTML = '';
  
  Object.keys(allOrganisms).sort().forEach(organism => {
    const orgDiv = document.createElement('div');
    orgDiv.className = 'organism-group';
    
    // Header
    const header = document.createElement('div');
    header.className = 'organism-toggle';
    
    const chevron = document.createElement('i');
    chevron.className = 'fa fa-chevron-right';
    chevron.style.marginRight = '6px';
    
    header.appendChild(chevron);
    header.appendChild(document.createTextNode(organism));
    
    // Assembly container (hidden by default)
    const assemblyContainer = document.createElement('div');
    assemblyContainer.style.display = 'none';
    
    // Add assemblies
    allOrganisms[organism].forEach(assembly => {
      const chip = document.createElement('span');
      chip.className = 'tag-chip-selector';
      chip.setAttribute('data-organism', organism);
      chip.setAttribute('data-assembly', assembly);
      chip.style.fontSize = '11px';
      chip.style.padding = '3px 8px';
      chip.style.margin = '2px';
      chip.textContent = assembly;
      
      // Check if selected
      if (selectedAccess[organism] && selectedAccess[organism].includes(assembly)) {
        console.log('Marking as selected:', organism, assembly);
        chip.classList.add('selected');
        chip.style.opacity = '1';
      } else {
        chip.style.opacity = '0.5';
      }
      
      // Color
      chip.style.background = getColorForOrganism(organism);
      chip.style.borderColor = getColorForOrganism(organism);
      chip.style.color = 'white';
      chip.style.border = '2px solid ' + getColorForOrganism(organism);
      
      chip.addEventListener('click', function() {
        this.classList.toggle('selected');
        if (this.classList.contains('selected')) {
          this.style.opacity = '1';
          if (!selectedAccess[organism]) {
            selectedAccess[organism] = [];
          }
          if (!selectedAccess[organism].includes(assembly)) {
            selectedAccess[organism].push(assembly);
          }
        } else {
          this.style.opacity = '0.5';
          if (selectedAccess[organism]) {
            selectedAccess[organism] = selectedAccess[organism].filter(a => a !== assembly);
            if (selectedAccess[organism].length === 0) {
              delete selectedAccess[organism];
            }
          }
        }
        updateHiddenInputs();
      });
      
      assemblyContainer.appendChild(chip);
    });
    
    // Toggle handler
    header.addEventListener('click', function() {
      const isHidden = assemblyContainer.style.display === 'none';
      assemblyContainer.style.display = isHidden ? 'block' : 'none';
      chevron.classList.toggle('fa-chevron-right');
      chevron.classList.toggle('fa-chevron-down');
    });
    
    orgDiv.appendChild(header);
    orgDiv.appendChild(assemblyContainer);
    container.appendChild(orgDiv);
  });
}

function updateHiddenInputs() {
  const hiddenContainer = document.getElementById('selected-assemblies-hidden');
  const previewContainer = document.getElementById('selected-preview');
  
  hiddenContainer.innerHTML = '';
  previewContainer.innerHTML = '';
  
  let totalCount = 0;
  
  Object.keys(selectedAccess).sort().forEach(organism => {
    selectedAccess[organism].forEach(assembly => {
      totalCount++;
      
      // Add hidden input
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = `access[${organism}][]`;
      input.value = assembly;
      hiddenContainer.appendChild(input);
      
      // Add preview badge
      const badge = document.createElement('span');
      badge.className = 'tag-chip';
      badge.style.background = getColorForOrganism(organism);
      badge.style.borderColor = getColorForOrganism(organism);
      badge.style.color = 'white';
      badge.style.marginRight = '5px';
      badge.style.marginBottom = '5px';
      badge.style.display = 'inline-block';
      badge.textContent = `${organism}: ${assembly}`;
      
      // Add remove button to badge
      const removeBtn = document.createElement('i');
      removeBtn.className = 'fa fa-times';
      removeBtn.style.cursor = 'pointer';
      removeBtn.style.marginLeft = '8px';
      removeBtn.style.fontWeight = 'bold';
      removeBtn.style.opacity = '0.8';
      removeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        // Remove from selectedAccess
        if (selectedAccess[organism]) {
          selectedAccess[organism] = selectedAccess[organism].filter(a => a !== assembly);
          if (selectedAccess[organism].length === 0) {
            delete selectedAccess[organism];
          }
        }
        // Re-render both preview and selector
        updateHiddenInputs();
        renderAssemblySelector();
      });
      
      badge.appendChild(removeBtn);
      previewContainer.appendChild(badge);
    });
  });
  
  if (totalCount === 0) {
    previewContainer.innerHTML = '<span class="text-muted small"><i class="fa fa-check-circle"></i> Select assemblies above to see them here</span>';
  }
}

function populateForm(username) {
  console.log('populateForm called for:', username);
  console.log('allUsers:', allUsers);
  console.log('userData:', allUsers[username]);
  
  const userData = allUsers[username];
  if (!userData) {
    console.error('User not found:', username);
    return;
  }
  
  // Fill basic fields
  document.getElementById('username').value = username;
  document.getElementById('username').readOnly = true;
  document.getElementById('email').value = userData.email || '';
  document.getElementById('first_name').value = userData.first_name || '';
  document.getElementById('last_name').value = userData.last_name || '';
  document.getElementById('account_host').value = userData.account_host || '';
  document.getElementById('password').value = '';
  
  // Update labels
  document.getElementById('form-title').textContent = `Edit User: ${username}`;
  document.getElementById('submit-text').textContent = 'Update User';
  document.getElementById('password_label').innerHTML = 'New Password (leave blank to keep current)';
  document.getElementById('password_help').style.display = 'block';
  
  // Admin status
  const isAdmin = userData.role === 'admin';
  document.getElementById('isAdmin').checked = isAdmin;
  
  // Update form mode
  document.getElementById('is_create').value = '0';
  document.getElementById('original_username').value = username;
  
  isEditMode = true;
  
  // Build selected assemblies from user data
  selectedAccess = {};
  console.log('userData.access:', userData.access);
  if (userData.access && typeof userData.access === 'object') {
    console.log('Building selectedAccess from user data');
    Object.keys(userData.access).forEach(organism => {
      console.log('Processing organism:', organism, 'assemblies:', userData.access[organism]);
      if (Array.isArray(userData.access[organism])) {
        selectedAccess[organism] = [...userData.access[organism]];
      }
    });
  }
  
  console.log('selectedAccess:', selectedAccess);
  
  // Re-render to show selections
  console.log('Calling renderAssemblySelector');
  renderAssemblySelector();
  updateHiddenInputs();
  
  // Show stale alert if any
  const staleAssemblies = [];
  Object.keys(userData.access || {}).forEach(organism => {
    const assemblies = allOrganisms[organism] || [];
    (userData.access[organism] || []).forEach(assembly => {
      if (!assemblies.includes(assembly)) {
        staleAssemblies.push({organism, assembly});
      }
    });
  });
  
  if (staleAssemblies.length > 0) {
    const staleAlert = document.getElementById('stale-alert');
    const staleItems = document.getElementById('stale-items');
    staleAlert.style.display = 'block';
    staleItems.innerHTML = '';
    
    staleAssemblies.forEach(item => {
      const chip = document.createElement('span');
      chip.className = 'tag-chip tag-chip-stale';
      chip.style.marginRight = '5px';
      chip.style.marginBottom = '5px';
      chip.style.display = 'inline-block';
      chip.textContent = `${item.organism}: ${item.assembly}`;
      
      const removeBtn = document.createElement('i');
      removeBtn.className = 'fa fa-times';
      removeBtn.style.cursor = 'pointer';
      removeBtn.style.marginLeft = '5px';
      removeBtn.style.fontWeight = 'bold';
      removeBtn.addEventListener('click', function() {
        if (selectedAccess[item.organism]) {
          selectedAccess[item.organism] = selectedAccess[item.organism].filter(a => a !== item.assembly);
          if (selectedAccess[item.organism].length === 0) {
            delete selectedAccess[item.organism];
          }
        }
        updateHiddenInputs();
        renderAssemblySelector();
        // Refresh stale alert
        populateForm(username);
      });
      
      chip.appendChild(removeBtn);
      staleItems.appendChild(chip);
    });
  } else {
    document.getElementById('stale-alert').style.display = 'none';
  }
  
  // Toggle admin to show/hide access section
  toggleAccessSection();
  
  // Scroll to form
  document.querySelector('.card').scrollIntoView({behavior: 'smooth'});
}

function toggleAccessSection() {
  const isAdmin = document.getElementById('isAdmin').checked;
  const accessSection = document.getElementById('access-section');
  const previewSection = document.getElementById('preview-section');
  const requiredBadge = document.getElementById('required-badge');
  
  if (isAdmin) {
    accessSection.style.opacity = '0.5';
    accessSection.style.pointerEvents = 'none';
    previewSection.style.opacity = '0.5';
    previewSection.style.pointerEvents = 'none';
    requiredBadge.style.display = 'none';
    selectedAccess = {}; // Clear selections for admin
    updateHiddenInputs();
  } else {
    accessSection.style.opacity = '1';
    accessSection.style.pointerEvents = 'auto';
    previewSection.style.opacity = '1';
    previewSection.style.pointerEvents = 'auto';
    requiredBadge.style.display = 'inline';
  }
}

function validateForm() {
  const isAdmin = document.getElementById('isAdmin').checked;
  const hasAssemblies = Object.keys(selectedAccess).some(org => 
    selectedAccess[org] && selectedAccess[org].length > 0
  );
  
  if (!isAdmin && !hasAssemblies) {
    alert('Please select at least one assembly (or check Admin for full access)');
    return false;
  }
  
  return true;
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
  // Pre-assign colors
  Object.keys(allOrganisms).forEach(org => getColorForOrganism(org));
  
  // Initial render
  renderAssemblySelector();
  
  // Initialize DataTable for users list
  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#usersTable').DataTable({
      pageLength: 10,
      order: [[0, 'asc']],
      columnDefs: [
        { targets: 5, orderable: false } // Disable sorting on Actions column
      ]
    });
  }
  
  // Admin checkbox handler
  document.getElementById('isAdmin').addEventListener('change', toggleAccessSection);
  
  // Edit buttons
  document.querySelectorAll('.edit-user-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const username = this.getAttribute('data-username');
      populateForm(username);
    });
  });
  
  // Delete buttons
  document.querySelectorAll('.delete-user-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const username = this.getAttribute('data-username');
      if (confirm(`Delete user "${username}"? This cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="delete_user" value="1">
          <input type="hidden" name="username" value="${username}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    });
  });
  
  // Form filter
  const filterInput = document.getElementById('organism-filter');
  if (filterInput) {
    filterInput.addEventListener('input', function() {
      const filterValue = this.value.toLowerCase();
      const organisms = document.querySelectorAll('.organism-group');
      
      organisms.forEach(org => {
        const name = org.textContent.toLowerCase();
        if (filterValue === '' || name.includes(filterValue)) {
          org.style.display = '';
          if (filterValue !== '') {
            org.querySelector('div[style*="display"]').style.display = 'block';
            org.querySelector('i').classList.remove('fa-chevron-right');
            org.querySelector('i').classList.add('fa-chevron-down');
          }
        } else {
          org.style.display = 'none';
        }
      });
      
      allExpanded = false;
      document.getElementById('toggle-all-btn').innerHTML = '<i class="fa fa-plus"></i> Expand All';
    });
  }
  
  // Expand/Collapse All
  document.getElementById('toggle-all-btn').addEventListener('click', function(e) {
    e.preventDefault();
    allExpanded = !allExpanded;
    const containers = document.querySelectorAll('.organism-group > div[style*="display"]');
    const icons = document.querySelectorAll('.organism-group i');
    
    containers.forEach(container => {
      container.style.display = allExpanded ? 'block' : 'none';
    });
    
    icons.forEach(icon => {
      if (allExpanded) {
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
      } else {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
      }
    });
    
    this.innerHTML = allExpanded ? '<i class="fa fa-minus"></i> Collapse All' : '<i class="fa fa-plus"></i> Expand All';
  });
  
  // Form submit with validation
  document.getElementById('userForm').addEventListener('submit', function(e) {
    if (!validateForm()) {
      e.preventDefault();
    }
  });
});

/**
 * Universal Collapse Handler
 * Handles all data-bs-toggle="collapse" elements
 */
(function() {
  // Add styles for collapse
  const style = document.createElement('style');
  style.textContent = `
    .collapse {
      display: none;
    }
    .collapse.show {
      display: block;
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
            element.classList.toggle('show');
          }
        }
      }, true);
    });
  });
})();
