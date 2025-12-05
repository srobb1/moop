/**
 * Manage Groups - Page-Specific Functionality
 * 
 * Handles group description editing, image/paragraph management, and group tag editing
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

// Group Description Functions (global scope)
function toggleGroup(groupName) {
  const content = document.getElementById('content-' + groupName);
  const header = event.target.closest('.group-header');
  const arrow = header.querySelector('span:last-child');
  
  if (content.style.display === 'none') {
    content.style.display = 'block';
    arrow.textContent = '▲';
  } else {
    content.style.display = 'none';
    arrow.textContent = '▼';
  }
}

function addImage(groupName) {
  const container = document.getElementById('images-container-' + groupName);
  const newIndex = container.children.length;
  
  const html = `
    <div class="image-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
      <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeImage('${groupName}', ${newIndex})" style="float: right;" ${isDescFileWriteError ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : ''}>Remove</button>
      <div class="form-group">
        <label>Image File</label>
        <input type="text" class="form-control image-file" value="" placeholder="e.g., Reef0607_0.jpg">
      </div>
      <div class="form-group">
        <label>Caption (HTML allowed)</label>
        <textarea class="form-control image-caption" rows="2"></textarea>
      </div>
    </div>
  `;
  
  container.insertAdjacentHTML('beforeend', html);
}

function removeImage(groupName, index) {
  const container = document.getElementById('images-container-' + groupName);
  const items = container.querySelectorAll('.image-item');
  if (items.length > 1) {
    items[index].remove();
  } else {
    alert('At least one image entry must remain (it can be empty).');
  }
}

function addParagraph(groupName) {
  const container = document.getElementById('paragraphs-container-' + groupName);
  const newIndex = container.children.length;
  
  const html = `
    <div class="paragraph-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
      <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeParagraph('${groupName}', ${newIndex})" style="float: right;" ${isDescFileWriteError ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : ''}>Remove</button>
      <div class="form-group">
        <label>Text (HTML allowed)</label>
        <textarea class="form-control para-text" rows="4"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>CSS Style</label>
          <input type="text" class="form-control para-style" value="" placeholder="e.g., color: red;">
        </div>
        <div class="form-group col-md-6">
          <label>CSS Class</label>
          <input type="text" class="form-control para-class" value="" placeholder="e.g., lead">
        </div>
      </div>
    </div>
  `;
  
  container.insertAdjacentHTML('beforeend', html);
}

function removeParagraph(groupName, index) {
  const container = document.getElementById('paragraphs-container-' + groupName);
  const items = container.querySelectorAll('.paragraph-item');
  if (items.length > 1) {
    items[index].remove();
  } else {
    alert('At least one paragraph entry must remain (it can be empty).');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const colors = [
    '#007bff', '#28a745', '#17a2b8', '#ffc107', '#dc3545', 
    '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6610f2',
    '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4', '#009688',
    '#4caf50', '#8bc34a', '#cddc39', '#ff9800', '#ff5722',
    '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#00897b',
    '#5e35b1', '#1e88e5', '#43a047'
  ];
  
  // Create a persistent color mapping for tags
  const tagColorMap = {};
  let nextColorIndex = 0;
  
  function getColorForTag(tag) {
    // If we've already assigned a color to this tag, use it
    if (tagColorMap[tag]) {
      return tagColorMap[tag];
    }
    
    // Assign the next available color
    tagColorMap[tag] = colors[nextColorIndex % colors.length];
    nextColorIndex++;
    
    return tagColorMap[tag];
  }
  
  // Pre-assign colors to all existing groups to ensure consistency
  existingGroups.forEach(tag => {
    getColorForTag(tag);
  });
  
  // Color all stale entry chips with consistent colors
  document.querySelectorAll('.table-hover .tag-chip.selected').forEach(chip => {
    const tag = chip.textContent.trim();
    chip.style.background = getColorForTag(tag);
    chip.style.borderColor = getColorForTag(tag);
  });
  
  // Color group name badges in descriptions section with consistent colors
  document.querySelectorAll('.group-card .tag-chip[data-group-name]').forEach(chip => {
    const groupName = chip.getAttribute('data-group-name');
    chip.style.background = getColorForTag(groupName);
    chip.style.borderColor = getColorForTag(groupName);
  });

  // Handle "Add Groups" button for new assemblies
  document.querySelectorAll('.add-groups-btn').forEach(button => {
    const row = button.closest('tr');
    const groupsSpan = row.querySelector('.groups-display-new');
    const saveButton = row.querySelector('.save-new-btn');
    const cancelButton = row.querySelector('.cancel-new-btn');
    const organism = row.getAttribute('data-organism');
    const assembly = row.getAttribute('data-assembly');
    
    let selectedTags = [];
    
    // Create tag editor container
    const tagEditor = document.createElement('div');
    tagEditor.className = 'tag-editor';
    tagEditor.style.display = 'none';
    
    // Create selected tags display
    const selectedDisplay = document.createElement('div');
    selectedDisplay.className = 'selected-tags-display';
    selectedDisplay.innerHTML = '<small class="text-muted">Selected tags:</small>';
    tagEditor.appendChild(selectedDisplay);
    
    // Create available tags section
    const availableSection = document.createElement('div');
    availableSection.innerHTML = '<small class="text-muted">Available tags (click to add):</small><br>';
    tagEditor.appendChild(availableSection);
    
    // Create new tag input
    const newTagDiv = document.createElement('div');
    newTagDiv.className = 'new-tag-input';
    newTagDiv.innerHTML = `
      <small class="text-muted">Add new tag:</small><br>
      <input type="text" class="form-control form-control-sm d-inline-block" style="width: 150px;" placeholder="New tag name">
      <button type="button" class="btn btn-sm btn-primary add-new-tag-new">Add</button>
    `;
    tagEditor.appendChild(newTagDiv);
    
    groupsSpan.parentNode.insertBefore(tagEditor, groupsSpan.nextSibling);
    
    function renderTags() {
      // Render selected tags
      selectedDisplay.innerHTML = '<small class="text-muted">Selected tags:</small><br>';
      selectedTags.forEach(tag => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip selected';
        chip.style.background = getColorForTag(tag);
        chip.style.borderColor = getColorForTag(tag);
        chip.innerHTML = `${tag} <span class="remove">×</span>`;
        chip.onclick = function() {
          selectedTags = selectedTags.filter(t => t !== tag);
          renderTags();
        };
        selectedDisplay.appendChild(chip);
      });
      
      // Render available tags
      availableSection.innerHTML = '<small class="text-muted">Available tags (click to add):</small><br>';
      existingGroups.forEach(tag => {
        if (!selectedTags.includes(tag)) {
          const chip = document.createElement('span');
          chip.className = 'tag-chip available';
          chip.textContent = tag;
          chip.onclick = function() {
            selectedTags.push(tag);
            renderTags();
          };
          availableSection.appendChild(chip);
        }
      });
    }
    
    // Add new tag functionality
    const newTagInput = newTagDiv.querySelector('input');
    const addNewTagBtn = newTagDiv.querySelector('.add-new-tag-new');
    
    addNewTagBtn.addEventListener('click', function() {
      const newTag = newTagInput.value.trim();
      if (newTag && !selectedTags.includes(newTag)) {
        selectedTags.push(newTag);
        if (!existingGroups.includes(newTag)) {
          existingGroups.push(newTag);
        }
        newTagInput.value = '';
        renderTags();
      }
    });
    
    newTagInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        addNewTagBtn.click();
      }
    });
    
    button.addEventListener('click', function() {
      groupsSpan.style.display = 'none';
      button.style.display = 'none';
      tagEditor.style.display = 'block';
      saveButton.style.display = 'inline-block';
      cancelButton.style.display = 'inline-block';
      renderTags();
    });
    
    saveButton.addEventListener('click', function() {
      // Create a form and submit
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'manage_groups.php';
      
      const orgInput = document.createElement('input');
      orgInput.type = 'hidden';
      orgInput.name = 'organism';
      orgInput.value = organism;
      
      const asmInput = document.createElement('input');
      asmInput.type = 'hidden';
      asmInput.name = 'assembly';
      asmInput.value = assembly;
      
      const groupsInput = document.createElement('input');
      groupsInput.type = 'hidden';
      groupsInput.name = 'groups';
      groupsInput.value = selectedTags.join(', ');
      
      const addInput = document.createElement('input');
      addInput.type = 'hidden';
      addInput.name = 'add';
      addInput.value = '1';
      
      form.appendChild(orgInput);
      form.appendChild(asmInput);
      form.appendChild(groupsInput);
      form.appendChild(addInput);
      
      document.body.appendChild(form);
      form.submit();
    });
    
    cancelButton.addEventListener('click', function(event) {
      event.preventDefault();
      groupsSpan.style.display = 'inline';
      tagEditor.style.display = 'none';
      button.style.display = 'inline-block';
      saveButton.style.display = 'none';
      cancelButton.style.display = 'none';
      selectedTags = [];
    });
  });
  
  // Handle "Delete Entry" for stale entries
  document.querySelectorAll('.delete-stale-btn').forEach(button => {
    button.addEventListener('click', function() {
      const row = button.closest('tr');
      const organism = row.getAttribute('data-organism');
      const assembly = row.getAttribute('data-assembly');
      
      if (confirm(`Delete entry for ${organism} / ${assembly}? This cannot be undone.`)) {
        // Create a form and submit
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'manage_groups.php';
        
        const orgInput = document.createElement('input');
        orgInput.type = 'hidden';
        orgInput.name = 'organism';
        orgInput.value = organism;
        
        const asmInput = document.createElement('input');
        asmInput.type = 'hidden';
        asmInput.name = 'assembly';
        asmInput.value = assembly;
        
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete';
        deleteInput.value = '1';
        
        form.appendChild(orgInput);
        form.appendChild(asmInput);
        form.appendChild(deleteInput);
        
        document.body.appendChild(form);
        form.submit();
      }
    });
  });

  // Before submitting, collect all images and paragraphs into JSON
  document.querySelectorAll('form[id^="form-"]').forEach(form => {
    form.addEventListener('submit', function(e) {
      const groupName = this.querySelector('input[name="group_name"]').value;
      const imagesContainer = document.getElementById('images-container-' + groupName);
      const paragraphsContainer = document.getElementById('paragraphs-container-' + groupName);
      
      // Collect images
      const images = [];
      imagesContainer.querySelectorAll('.image-item').forEach(item => {
        images.push({
          file: item.querySelector('.image-file').value,
          caption: item.querySelector('.image-caption').value
        });
      });
      
      // Collect paragraphs
      const paragraphs = [];
      paragraphsContainer.querySelectorAll('.paragraph-item').forEach(item => {
        paragraphs.push({
          text: item.querySelector('.para-text').value,
          style: item.querySelector('.para-style').value,
          class: item.querySelector('.para-class').value
        });
      });
      
      // Set hidden fields
      document.getElementById('images-json-' + groupName).value = JSON.stringify(images);
      document.getElementById('html-p-json-' + groupName).value = JSON.stringify(paragraphs);
    });
  });
});
