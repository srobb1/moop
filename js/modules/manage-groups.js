/**
 * Manage Groups - Page-Specific Functionality
 * 
 * Handles group description editing, image/paragraph management
 */

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
  const isDisabled = isDescFileWriteError; // From inline_scripts
  
  const html = `
    <div class="image-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
      <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeImage('${groupName}', ${newIndex})" style="float: right;" ${isDisabled ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : ''}>Remove</button>
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
  const isDisabled = isDescFileWriteError; // From inline_scripts
  
  const html = `
    <div class="paragraph-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
      <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeParagraph('${groupName}', ${newIndex})" style="float: right;" ${isDisabled ? 'disabled data-bs-toggle="modal" data-bs-target="#permissionModal"' : ''}>Remove</button>
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
  // Permission modal setup
  const rows = document.querySelectorAll('[data-organism]');
  rows.forEach(row => {
    const deleteBtn = row.querySelector('.delete-btn');
    if (deleteBtn) {
      deleteBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (isDescFileWriteError) {
          const permissionModal = new bootstrap.Modal(document.getElementById('permissionModal'));
          permissionModal.show();
          return;
        }
        
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
    }
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
