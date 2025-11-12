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
    resultDiv.style.display = 'none';
    
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
            html += '<code class="text-break">' + data.command + '</code><br>';
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
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-wrench"></i> Fix Permissions';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.style.display = 'block';
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
    
    const formData = new FormData(form);
    const data = new URLSearchParams();
    data.append('action', 'save_metadata');
    data.append('organism', organism);
    data.append('genus', formData.get('genus'));
    data.append('species', formData.get('species'));
    data.append('common_name', formData.get('common_name'));
    data.append('taxon_id', formData.get('taxon_id'));
    
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

function renameAssemblyDirectory(event, organism, safeAsmId) {
    event.preventDefault();
    
    const elementId = safeAsmId || organism;
    
    const oldDir = document.getElementById('oldDirName' + elementId).value;
    const newDir = document.getElementById('newDirName' + elementId).value;
    const resultDiv = document.getElementById('renameResult' + elementId);
    const button = event.target;
    
    if (!oldDir || !newDir) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please select both current and new directory names</div>';
        resultDiv.style.display = 'block';
        return;
    }
    
    if (oldDir === newDir) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Current and new names are the same</div>';
        resultDiv.style.display = 'block';
        return;
    }
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Renaming...';
    resultDiv.style.display = 'none';
    
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
            html += '<code class="text-break">' + data.command + '</code><br>';
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
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-exchange-alt"></i> Rename';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.style.display = 'block';
    });
}

function deleteAssemblyDirectory(event, organism, safeAsmId) {
    event.preventDefault();
    
    const dirToDelete = document.getElementById('dirToDelete' + safeAsmId).value;
    const resultDiv = document.getElementById('deleteResult' + safeAsmId);
    const button = event.target;
    
    if (!dirToDelete) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please select a directory to delete</div>';
        resultDiv.style.display = 'block';
        return;
    }
    
    if (!confirm('⚠️  CAUTION: You are about to permanently delete the directory "' + dirToDelete + '". This action CANNOT be undone!\n\nAre you absolutely sure you want to continue?')) {
        return;
    }
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting...';
    resultDiv.style.display = 'none';
    
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
            html += '<code class="text-break">' + data.command + '</code><br>';
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
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-trash-alt"></i> Delete Directory';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.style.display = 'block';
    });
}
