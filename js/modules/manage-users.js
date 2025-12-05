/**
 * Manage Users - Page-Specific Functionality
 * Handles user creation/editing with organism access control
 */

/**
 * Simple Collapse Handler - REPLACES Bootstrap Collapse
 * Manually toggle all collapses
 */
(function() {
    // Add styles
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

document.addEventListener('DOMContentLoaded', function() {
  // Pre-assign colors to existing organisms (color function defined in inline_scripts)
  Object.keys(allOrganisms).forEach(org => getColorForOrganism(org));
  
  // Apply colors to existing chips
  document.querySelectorAll('.tag-chip').forEach(chip => {
    const organism = chip.getAttribute('data-organism');
    if (organism) {
      chip.style.background = getColorForOrganism(organism);
      chip.style.borderColor = getColorForOrganism(organism);
    }
  });
  
  // Initialize DataTable
  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#usersTable').DataTable({
      pageLength: 10,
      order: [[0, 'asc']]
    });
  }
  
  const isAdminCheckbox = document.getElementById('isAdmin');
  const groupsSection = document.getElementById('groups-section');
  const organismFilter = document.getElementById('organism-filter');
  const createAccessContainer = document.getElementById('create-access-container');
  
  // Apply colors to create chips and add click handlers
  document.querySelectorAll('.create-chip').forEach(chip => {
    const organism = chip.getAttribute('data-organism');
    chip.style.background = getColorForOrganism(organism);
    chip.style.borderColor = getColorForOrganism(organism);
    chip.style.color = 'white';
    chip.style.opacity = '0.5';
    
    chip.addEventListener('click', function() {
      if (!isAdminCheckbox.checked) {
        this.classList.toggle('selected');
        if (this.classList.contains('selected')) {
          this.style.opacity = '1';
        } else {
          this.style.opacity = '0.5';
        }
        updateCreateHiddenInputs();
      }
    });
  });
  
  // Function to update hidden inputs for form submission
  function updateCreateHiddenInputs() {
    const hiddenContainer = document.getElementById('create-selected-hidden');
    if (!hiddenContainer) return;
    
    hiddenContainer.innerHTML = '';
    
    document.querySelectorAll('.create-chip.selected').forEach(chip => {
      const organism = chip.getAttribute('data-organism');
      const assembly = chip.getAttribute('data-assembly');
      
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = `groups[${organism}][]`;
      input.value = assembly;
      hiddenContainer.appendChild(input);
    });
  }
  
  // Filter organisms
  if (organismFilter) {
    organismFilter.addEventListener('input', function() {
      const filterValue = this.value.toLowerCase();
      const organismGroups = document.querySelectorAll('.organism-group');
      
      organismGroups.forEach(function(group) {
        const organismName = group.getAttribute('data-organism-name');
        if (organismName.includes(filterValue)) {
          group.style.display = '';
        } else {
          group.style.display = 'none';
        }
      });
    });
  }
  
  // Toggle groups section based on admin checkbox
  if (isAdminCheckbox) {
    isAdminCheckbox.addEventListener('change', function() {
      if (this.checked) {
        groupsSection.style.opacity = '0.5';
        groupsSection.style.pointerEvents = 'none';
      } else {
        groupsSection.style.opacity = '1';
        groupsSection.style.pointerEvents = 'auto';
      }
    });
  }
  
  // Handle edit buttons
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('tr');
      const username = row.getAttribute('data-username');
      const userData = allUsers[username];
      
      if (!userData) return;
      
      document.getElementById('editUsernameInput').value = username;
      document.getElementById('editPassword').value = '';
      document.getElementById('editEmail').value = userData.email || '';
      document.getElementById('editFirstName').value = userData.first_name || '';
      document.getElementById('editLastName').value = userData.last_name || '';
      document.getElementById('editAccountHost').value = userData.account_host || '';
      
      const isAdmin = userData.role === 'admin';
      document.getElementById('editIsAdmin').checked = isAdmin;
      
      // Build access selector
      const container = document.getElementById('edit-access-container');
      container.innerHTML = '';
      
      if (!isAdmin) {
        // Get current user access
        const userGroups = userData.groups || {};
        
        // Build organism chips
        Object.keys(allOrganisms).forEach(organism => {
          const orgDiv = document.createElement('div');
          orgDiv.className = 'organism-group-edit';
          orgDiv.setAttribute('data-organism-name', organism.toLowerCase());
          orgDiv.style.marginBottom = '15px';
          
          const label = document.createElement('div');
          label.style.fontWeight = 'bold';
          label.style.marginBottom = '8px';
          label.textContent = organism;
          orgDiv.appendChild(label);
          
          allOrganisms[organism].forEach(assembly => {
            const chip = document.createElement('span');
            chip.className = 'tag-chip-selector';
            chip.setAttribute('data-organism', organism);
            chip.setAttribute('data-assembly', assembly);
            chip.style.background = getColorForOrganism(organism);
            chip.style.borderColor = getColorForOrganism(organism);
            chip.style.color = 'white';
            chip.style.opacity = '0.5';
            chip.textContent = assembly;
            
            // Check if selected
            if (userGroups[organism] && userGroups[organism].includes(assembly)) {
              chip.classList.add('selected');
              chip.style.opacity = '1';
            }
            
            chip.addEventListener('click', function() {
              this.classList.toggle('selected');
              if (this.classList.contains('selected')) {
                this.style.opacity = '1';
              } else {
                this.style.opacity = '0.5';
              }
              updateEditHiddenInputs();
            });
            
            orgDiv.appendChild(chip);
          });
          
          container.appendChild(orgDiv);
        });
      }
    });
  });
  
  // Function to update edit hidden inputs
  function updateEditHiddenInputs() {
    const hiddenContainer = document.getElementById('edit-selected-hidden');
    if (!hiddenContainer) return;
    
    hiddenContainer.innerHTML = '';
    
    document.querySelectorAll('#edit-access-container .tag-chip-selector.selected').forEach(chip => {
      const organism = chip.getAttribute('data-organism');
      const assembly = chip.getAttribute('data-assembly');
      
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = `groups[${organism}][]`;
      input.value = assembly;
      hiddenContainer.appendChild(input);
    });
  }
  
  // Edit admin checkbox
  const editIsAdminCheckbox = document.getElementById('editIsAdmin');
  if (editIsAdminCheckbox) {
    editIsAdminCheckbox.addEventListener('change', function() {
      const container = document.getElementById('edit-access-container');
      if (this.checked) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
      } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
      }
    });
  }
  
  // Handle delete buttons
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('tr');
      const username = row.getAttribute('data-username');
      
      if (confirm(`Are you sure you want to delete user "${username}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_users.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_user';
        input.value = '1';
        
        const usernameInput = document.createElement('input');
        usernameInput.type = 'hidden';
        usernameInput.name = 'username';
        usernameInput.value = username;
        
        form.appendChild(input);
        form.appendChild(usernameInput);
        document.body.appendChild(form);
        form.submit();
      }
    });
  });
});
