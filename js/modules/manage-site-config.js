/**
 * Manage Site Configuration - Page-Specific Functionality
 */

/**
 * Banner Image Upload Handler
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const uploadBtn = document.getElementById('uploadHeaderBtn');
        const fileInput = document.getElementById('header_upload');
        
        if (uploadBtn && fileInput) {
            uploadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (!fileInput.files || fileInput.files.length === 0) {
                    alert('Please select a file to upload');
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'upload_banner');
                formData.append('banner_file', fileInput.files[0]);
                
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading...';
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fa fa-upload"></i> Upload';
                    
                    if (data.success) {
                        alert('Banner uploaded successfully: ' + data.filename);
                        fileInput.value = '';
                        // Reload page to show new banner in gallery
                        location.reload();
                    } else {
                        alert('Upload failed: ' + data.message);
                    }
                })
                .catch(error => {
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fa fa-upload"></i> Upload';
                    alert('Error: ' + error.message);
                });
            });
        }
    });
})();

/**
 * Banner Image Delete Handler
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.delete-banner');
        deleteButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const filename = this.getAttribute('data-filename');
                if (!filename) {
                    alert('Error: No filename provided');
                    return;
                }
                
                if (!confirm('Are you sure you want to delete this banner image?')) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'delete_banner');
                formData.append('filename', filename);
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Banner deleted successfully');
                        // Reload page to update gallery
                        location.reload();
                    } else {
                        alert('Delete failed: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            });
        });
    });
})();

/**
 * Favicon Upload Preview Handler
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const faviconUpload = document.getElementById('favicon_upload');
        const faviconPreviewDiv = document.getElementById('favicon_upload_preview');
        const faviconPreviewImg = document.getElementById('favicon_new_preview');
        
        if (faviconUpload && faviconPreviewDiv && faviconPreviewImg) {
            faviconUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        faviconPreviewImg.src = event.target.result;
                        faviconPreviewDiv.style.display = 'block';
                    };
                    reader.onerror = function() {
                        console.error('FileReader error');
                    };
                    reader.readAsDataURL(file);
                } else {
                    faviconPreviewDiv.style.display = 'none';
                }
            });
        } else {
            console.warn('Favicon preview elements not found:', {
                faviconUpload: !!faviconUpload,
                faviconPreviewDiv: !!faviconPreviewDiv,
                faviconPreviewImg: !!faviconPreviewImg
            });
        }
    });
})();

/**
 * Sequence Type Color Input Handler
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const colorInputs = document.querySelectorAll('.color-input');
        
        // Function to check if a string is a hex color
        function isHexColor(str) {
            return str.startsWith('#') && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(str);
        }
        
        // Function to update badge color
        function updateBadgeColor(input) {
            const seqType = input.getAttribute('data-seq-type');
            const badgeElement = document.getElementById('badge_' + seqType);
            if (badgeElement && input.value.trim()) {
                const colorValue = input.value.trim();
                
                if (isHexColor(colorValue)) {
                    // Hex color - use inline style
                    badgeElement.style.backgroundColor = colorValue;
                    badgeElement.style.color = 'white';
                    // Remove Bootstrap bg-* classes but keep other classes
                    badgeElement.className = badgeElement.className.replace(/bg-\S+/g, '').trim();
                } else {
                    // Assume it's a Bootstrap class
                    badgeElement.style.backgroundColor = '';
                    badgeElement.style.color = '';
                    // Remove all bg-* classes
                    badgeElement.className = badgeElement.className.replace(/bg-\S+/g, '').trim();
                    // Add the Bootstrap class
                    badgeElement.className += ' ' + colorValue;
                }
            }
        }
        
        colorInputs.forEach(function(input) {
            // Initialize badge color on page load
            updateBadgeColor(input);
            
            // Update on user changes
            input.addEventListener('change', function() {
                updateBadgeColor(this);
            });
            input.addEventListener('input', function() {
                updateBadgeColor(this);
            });
        });
    });
})();
