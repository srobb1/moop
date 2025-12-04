/**
 * Organism Management Functions
 * Handles database permissions, assembly operations, and metadata editing
 */

function fixDatabasePermissions(event, organism) {
    event.preventDefault();
    
    const resultDiv = document.getElementById('fixResult' + organism);
    const button = event.target.closest('button');
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Fixing...';
    resultDiv.classList.add('d-none');
    
    fetch('manage_organisms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=fix_permissions&organism=' + encodeURIComponent(organism)
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        
        let alertClass = data.success ? 'alert-success' : 'alert-danger';
        let html = '<div class="alert ' + alertClass + '">';
        html += '<strong>' + (data.success ? '✓ Success!' : '✗ Failed!') + '</strong><br>';
        html += '<p>' + data.message + '</p>';
        
        if (data.command) {
            html += '<div class="alert alert-info mt-2 small">';
            html += '<strong>Run this command on the server:</strong><br>';
            html += '<code class="text-break">' + escapeHtml(data.command) + '</code><br>';
            html += '<small class="mt-2 d-block text-muted">After running the command, refresh this page to verify the fix.</small>';
            html += '</div>';
        }
        
        html += '</div>';
        
        if (data.success) {
            button.innerHTML = '<i class="fa fa-check"></i> Fixed!';
            button.classList.remove('btn-warning');
            button.classList.add('btn-success');
        } else {
            button.innerHTML = '<i class="fa fa-wrench"></i> Try Again';
        }
        
        resultDiv.innerHTML = html;
        resultDiv.classList.remove('d-none');
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-wrench"></i> Fix Permissions';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.classList.remove('d-none');
    });
}

function saveMetadata(event, organism) {
    event.preventDefault();
    
    const form = document.getElementById('metadataForm' + organism);
    const resultDiv = document.getElementById('saveResult' + organism);
    const button = event.target;
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Collect images data
    const imagesContainer = document.getElementById('images-container-' + organism);
    const images = [];
    imagesContainer.querySelectorAll('.image-item').forEach(item => {
        const file = item.querySelector('.image-file').value;
        const caption = item.querySelector('.image-caption').value;
        if (file || caption) {
            images.push({
                file: file,
                caption: caption
            });
        }
    });
    
    // Collect paragraphs data
    const paragraphsContainer = document.getElementById('paragraphs-container-' + organism);
    const paragraphs = [];
    paragraphsContainer.querySelectorAll('.paragraph-item').forEach(item => {
        const text = item.querySelector('.para-text').value;
        const style = item.querySelector('.para-style').value;
        const cssClass = item.querySelector('.para-class').value;
        if (text || style || cssClass) {
            paragraphs.push({
                text: text,
                style: style,
                class: cssClass
            });
        }
    });
    
    // Collect feature types data
    const parentFeatures = [];
    const childFeatures = [];
    document.querySelectorAll('#parents-' + organism + ' .feature-tag').forEach(tag => {
        parentFeatures.push(tag.getAttribute('data-feature'));
    });
    document.querySelectorAll('#children-' + organism + ' .feature-tag').forEach(tag => {
        childFeatures.push(tag.getAttribute('data-feature'));
    });
    
    // Prepare form data
    const formData = new FormData(form);
    const data = new URLSearchParams();
    data.append('action', 'save_metadata');
    data.append('organism', organism);
    data.append('genus', formData.get('genus'));
    data.append('species', formData.get('species'));
    data.append('common_name', formData.get('common_name'));
    data.append('taxon_id', formData.get('taxon_id'));
    data.append('images_json', JSON.stringify(images));
    data.append('html_p_json', JSON.stringify(paragraphs));
    data.append('parents_json', JSON.stringify(parentFeatures));
    data.append('children_json', JSON.stringify(childFeatures));
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
    resultDiv.innerHTML = '';
    
    fetch('manage_organisms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(response => {
        button.disabled = false;
        
        let alertClass = response.success ? 'alert-success' : 'alert-danger';
        let html = '<div class="alert ' + alertClass + ' mt-3">';
        html += '<strong>' + (response.success ? '✓ Success!' : '✗ Failed!') + '</strong><br>';
        html += '<p>' + response.message + '</p>';
        
        if (response.error) {
            html += '<p class="mt-2 mb-2"><strong>Current Status:</strong></p>';
            html += '<ul class="mb-3">';
            html += '<li>File owner: <code>' + escapeHtml(response.error.owner) + '</code></li>';
            html += '<li>Current permissions: <code>' + response.error.perms + '</code></li>';
            html += '<li>Web server user: <code>' + escapeHtml(response.error.web_user) + '</code></li>';
            if (response.error.web_group) {
                html += '<li>Web server group: <code>' + escapeHtml(response.error.web_group) + '</code></li>';
            }
            html += '</ul>';
            
            html += '<p><strong>To Fix:</strong> Run this command on the server:</p>';
            html += '<div class="bg-light p-2 rounded border">';
            html += '<code class="text-break">' + escapeHtml(response.error.command) + '</code>';
            html += '</div>';
            html += '<p class="small text-muted mt-2">After running the command, try saving again.</p>';
        }
        
        html += '</div>';
        
        if (response.success) {
            button.innerHTML = '<i class="fa fa-check"></i> Saved!';
            button.classList.remove('btn-success');
            button.classList.add('btn-success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            button.innerHTML = '<i class="fa fa-save"></i> Try Again';
        }
        
        resultDiv.innerHTML = html;
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-save"></i> Save Metadata';
        resultDiv.innerHTML = '<div class="alert alert-danger mt-3">Error: ' + error + '</div>';
    });
}

// Uses window.escapeHtml from utilities.js

function addMetadataImage(organism) {
    const container = document.getElementById('images-container-' + organism);
    const newIndex = container.children.length;
    
    const html = `
      <div class="image-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeMetadataImage('${organism}', ${newIndex})" style="float: right;">Remove</button>
        <div class="form-group mb-3">
          <label>Image File</label>
          <input type="text" class="form-control image-file" value="" placeholder="e.g., organism_image.jpg">
          <small class="text-muted">Place images in /moop/images/ directory</small>
        </div>
        <div class="form-group">
          <label>Caption (HTML allowed)</label>
          <textarea class="form-control image-caption" rows="2"></textarea>
        </div>
      </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
}

function removeMetadataImage(organism, index) {
    const container = document.getElementById('images-container-' + organism);
    const items = container.querySelectorAll('.image-item');
    if (items.length > 1) {
        items[index].remove();
    } else {
        alert('At least one image entry must remain (it can be empty).');
    }
}

function addMetadataParagraph(organism) {
    const container = document.getElementById('paragraphs-container-' + organism);
    const newIndex = container.children.length;
    
    const html = `
      <div class="paragraph-item" data-index="${newIndex}" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeMetadataParagraph('${organism}', ${newIndex})" style="float: right;">Remove</button>
        <div class="form-group mb-3">
          <label>Text (HTML allowed)</label>
          <textarea class="form-control para-text" rows="4"></textarea>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label>CSS Style</label>
              <input type="text" class="form-control para-style" value="" placeholder="e.g., color: red;">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>CSS Class</label>
              <input type="text" class="form-control para-class" value="" placeholder="e.g., lead">
            </div>
          </div>
        </div>
      </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
}

function removeMetadataParagraph(organism, index) {
    const container = document.getElementById('paragraphs-container-' + organism);
    const items = container.querySelectorAll('.paragraph-item');
    if (items.length > 1) {
        items[index].remove();
    } else {
        alert('At least one paragraph entry must remain (it can be empty).');
    }
}

function renameAssemblyDirectory(event, organism, safeAsmId) {
    event.preventDefault();
    
    const elementId = safeAsmId || organism;
    
    const oldDir = document.getElementById('oldDirName' + elementId).value;
    const newDir = document.getElementById('newDirName' + elementId).value;
    const resultDiv = document.getElementById('renameResult' + elementId);
    const button = event.target;
    
    if (!oldDir || !newDir) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please select both current and new directory names</div>';
        resultDiv.classList.remove('d-none');
        return;
    }
    
    if (oldDir === newDir) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Current and new names are the same</div>';
        resultDiv.classList.remove('d-none');
        return;
    }
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Renaming...';
    resultDiv.classList.add('d-none');
    
    fetch('manage_organisms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=rename_assembly&organism=' + encodeURIComponent(organism) + 
              '&old_name=' + encodeURIComponent(oldDir) + 
              '&new_name=' + encodeURIComponent(newDir)
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        
        let alertClass = data.success ? 'alert-success' : 'alert-danger';
        let html = '<div class="alert ' + alertClass + '">';
        html += '<strong>' + (data.success ? '✓ Success!' : '✗ Failed!') + '</strong><br>';
        html += '<p>' + data.message + '</p>';
        
        if (data.command) {
            html += '<div class="alert alert-info mt-2 small">';
            html += '<strong>Run this command on the server:</strong><br>';
            html += '<code class="text-break">' + escapeHtml(data.command) + '</code><br>';
            html += '<small class="mt-2 d-block text-muted">After running the command, refresh this page to verify the fix.</small>';
            html += '</div>';
        }
        
        html += '</div>';
        
        if (data.success) {
            button.innerHTML = '<i class="fa fa-check"></i> Renamed!';
            button.classList.remove('btn-info');
            button.classList.add('btn-success');
            document.getElementById('oldDirName' + elementId).value = '';
            document.getElementById('newDirName' + elementId).value = '';
        } else {
            button.innerHTML = '<i class="fa fa-exchange-alt"></i> Try Again';
        }
        
        resultDiv.innerHTML = html;
        resultDiv.classList.remove('d-none');
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-exchange-alt"></i> Rename';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.classList.remove('d-none');
    });
}

function deleteAssemblyDirectory(event, organism, safeAsmId) {
    event.preventDefault();
    
    const dirToDelete = document.getElementById('dirToDelete' + safeAsmId).value;
    const resultDiv = document.getElementById('deleteResult' + safeAsmId);
    const button = event.target;
    
    if (!dirToDelete) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please select a directory to delete</div>';
        resultDiv.classList.remove('d-none');
        return;
    }
    
    if (!confirm('⚠️  CAUTION: You are about to permanently delete the directory "' + dirToDelete + '". This action CANNOT be undone!\n\nAre you absolutely sure you want to continue?')) {
        return;
    }
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting...';
    resultDiv.classList.add('d-none');
    
    fetch('manage_organisms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=delete_assembly&organism=' + encodeURIComponent(organism) + 
              '&dir_name=' + encodeURIComponent(dirToDelete)
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        
        let alertClass = data.success ? 'alert-success' : 'alert-danger';
        let html = '<div class="alert ' + alertClass + '">';
        html += '<strong>' + (data.success ? '✓ Deleted!' : '✗ Failed!') + '</strong><br>';
        html += '<p>' + data.message + '</p>';
        
        if (data.command) {
            html += '<div class="alert alert-info mt-2 small">';
            html += '<strong>Web server lacks permissions. Run this command on the server:</strong><br>';
            html += '<code class="text-break">' + escapeHtml(data.command) + '</code><br>';
            html += '<small class="mt-2 d-block text-muted">After running the command, refresh this page to verify the deletion.</small>';
            html += '</div>';
        }
        
        html += '</div>';
        
        if (data.success) {
            button.innerHTML = '<i class="fa fa-check"></i> Deleted!';
            button.classList.remove('btn-danger');
            button.classList.add('btn-success');
            document.getElementById('dirToDelete' + safeAsmId).value = '';
        } else {
            button.innerHTML = '<i class="fa fa-trash-alt"></i> Try Again';
        }
        
        resultDiv.innerHTML = html;
        resultDiv.classList.remove('d-none');
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-trash-alt"></i> Delete Directory';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.classList.remove('d-none');
    });
}

function addFeatureTag(organism, type) {
    const inputId = type + '-feature-input-' + organism;
    const containerId = type + '-features-' + organism;
    const input = document.getElementById(inputId);
    const container = document.getElementById(containerId);
    
    const feature = input.value.trim();
    if (!feature) {
        alert('Please enter a feature type');
        return;
    }
    
    // Check if already exists
    const existing = Array.from(container.querySelectorAll('.feature-tag')).map(t => t.getAttribute('data-feature'));
    if (existing.includes(feature)) {
        alert('This feature type is already added');
        return;
    }
    
    const badge = document.createElement('span');
    badge.className = 'badge me-2 mb-2 feature-tag';
    badge.className += type === 'parent' ? ' bg-primary' : ' bg-info';
    badge.setAttribute('data-feature', feature);
    badge.innerHTML = `${feature} <i class="fa fa-times" style="cursor: pointer;" onclick="removeFeatureTag(this, '${organism}')"></i>`;
    
    container.appendChild(badge);
    input.value = '';
}

function removeFeatureTag(element, organism) {
    element.closest('.feature-tag').remove();
}

// Uses window.escapeHtml from utilities.js

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
                console.log('Manual collapse toggle:', target);
                if (target) {
                    const element = document.querySelector(target);
                    if (element) {
                        const before = element.classList.contains('show');
                        element.classList.toggle('show');
                        console.log('Toggled from', before, 'to', element.classList.contains('show'));
                    }
                }
            }, true);
        });
    });
})();


/**
 * Legend Toggle Handler
 * Handles the Legend & Status Guide section
 */
document.addEventListener('DOMContentLoaded', function() {
    const legendHeader = document.getElementById('legendHeader');
    const legendContent = document.getElementById('legendContent');
    
    if (legendHeader && legendContent) {
        // Initialize legend as closed
        legendContent.style.display = 'none';
        
        legendHeader.addEventListener('click', function(e) {
            e.preventDefault();
            const isOpen = legendContent.style.display !== 'none';
            legendContent.style.display = isOpen ? 'none' : 'block';
            console.log('Legend toggled:', isOpen ? 'closing' : 'opening');
        });
    }
});

/**
 * Toggle Path Display
 * Shows/hides the organism directory path
 */
function togglePath(button, organism_path, organism) {
    const pathId = 'path-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    let pathDiv = document.getElementById(pathId);
    
    if (pathDiv) {
        // Already exists, toggle visibility
        if (pathDiv.style.display === 'none') {
            pathDiv.style.display = 'block';
            button.innerHTML = '<i class="fa fa-folder-open"></i> Hide Path';
        } else {
            pathDiv.style.display = 'none';
            button.innerHTML = '<i class="fa fa-folder"></i> View Path';
        }
    } else {
        // Create the path display
        pathDiv = document.createElement('div');
        pathDiv.id = pathId;
        pathDiv.className = 'mt-2';
        pathDiv.innerHTML = '<small class="font-monospace text-muted" style="user-select: all; cursor: text;">' + organism_path + '</small>';
        button.parentNode.insertBefore(pathDiv, button.nextSibling);
        button.innerHTML = '<i class="fa fa-folder-open"></i> Hide Path';
    }
}
