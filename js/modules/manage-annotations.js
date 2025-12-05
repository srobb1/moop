/**
 * Manage Annotations - Page-Specific Functionality
 */

/**
 * Manual Collapse Handler with Chevron Rotation
 * Handles collapse toggles and rotates chevron icons
 */
(function() {
    // Add styles for collapse behavior
    const style = document.createElement('style');
    style.textContent = `
        .collapse {
            display: none;
        }
        .collapse.show {
            display: block;
        }
        .fa-chevron-down {
            transition: transform 0.3s ease;
        }
        .fa-grip-vertical {
            cursor: grab;
            margin-right: 10px;
        }
        .ui-sortable-helper {
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .spinner-border-sm {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            vertical-align: text-bottom;
            border: 0.25em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border 0.75s linear infinite;
        }
        @keyframes spinner-border {
            to { transform: rotate(360deg); }
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
                        const isOpen = element.classList.contains('show');
                        element.classList.toggle('show');
                        
                        // Rotate chevron
                        const chevron = this.querySelector('.fa-chevron-down');
                        if (chevron) {
                            chevron.style.transform = !isOpen 
                                ? 'rotate(-180deg)' 
                                : 'rotate(0deg)';
                        }
                    }
                }
            }, true);
        });
    });
})();

document.addEventListener('DOMContentLoaded', function() {
  // Setup DataTables if present
  const annotationsTables = document.querySelectorAll('.annotations-table');
  annotationsTables.forEach(table => {
    if (typeof DataTable !== 'undefined') {
      new DataTable(table);
    }
  });
  
  // Make annotation types sortable - auto-save on stop
  if ($('#sortable-annotation-types').length) {
      console.log('Initializing annotation types sortable');
      console.log('Found ' + $('#sortable-annotation-types .card').length + ' cards to sort');
      
      $('#sortable-annotation-types').sortable({
          handle: '.fa-grip-vertical',
          items: '.card',
          start: function(event, ui) {
              console.log('Drag started');
          },
          change: function(event, ui) {
              console.log('Item moved during drag');
          },
          stop: function(event, ui) {
              console.log('Drag stopped - auto-saving order');
              saveTypeOrder();
          }
      });
  } else {
      console.log('ERROR: #sortable-annotation-types not found');
  }
  
  // Edit type description button
  $('.edit-type-desc-btn').on('click', function() {
      const typeName = $(this).data('type');
      // Use attribute selector to handle type names with spaces
      const descElement = $('p[id="desc-type-' + typeName.replace(/"/g, '\\"') + '"]');
      const currentDesc = descElement.data('full-desc') || descElement.text().trim();
      
      $('#editTypeDescName').text(typeName);
      $('#editTypeName').val(typeName);
      $('#editTypeDescription').val(currentDesc === 'No description' ? '' : currentDesc);
      
      new bootstrap.Modal($('#editTypeDescModal')).show();
  });
  
  // Add loading spinner to form buttons
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
      console.log('Form submitted:', this.name || 'unnamed');
      const buttons = this.querySelectorAll('button[type="submit"]');
      buttons.forEach(btn => {
        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) {
          icon.className = 'fa fa-spinner fa-spin';
        }
      });
    });
  });
});

// Function to save annotation type order
function saveTypeOrder() {
    const newOrder = [];
    $('#sortable-annotation-types .card').each(function() {
        const type = $(this).data('type');
        if (type) {
            newOrder.push(type);
        }
    });
    
    console.log('saveTypeOrder called');
    console.log('Saving annotation type order:', newOrder);
    console.log('Order array length:', newOrder.length);
    
    if (newOrder.length === 0) {
        console.error('No types found to save!');
        return;
    }
    
    // Use fetch API for more reliable form submission
    fetch('manage_annotations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'update_type_order': '1',
            'type_order_data': JSON.stringify(newOrder)
        })
    })
    .then(response => {
        console.log('Response received:', response.status);
        if (response.ok) {
            console.log('Order saved successfully');
            // Show brief success message
            showSaveNotification('Order saved successfully');
        } else {
            console.error('Server error:', response.status);
            showSaveNotification('Error saving order. Please try again.', 'danger');
        }
    })
    .catch(error => {
        console.error('Error submitting form:', error);
        showSaveNotification('Error saving order: ' + error.message, 'danger');
    });
}

// Function to show a temporary success/error notification
function showSaveNotification(message, type = 'success') {
    const alertDiv = $('<div>')
        .addClass('alert alert-' + type + ' alert-dismissible fade show')
        .attr('role', 'alert')
        .html(message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
    
    // Insert at top of page and auto-dismiss after 3 seconds
    $('main, .container, body').first().prepend(alertDiv);
    setTimeout(() => {
        alertDiv.fadeOut(300, function() { $(this).remove(); });
    }, 3000);
}

// Toggle type details expand/collapse
function toggleTypeDetails(typeName) {
  const details = document.getElementById('details-' + typeName);
  const button = document.querySelector(`[data-type="${typeName}"].expand-type-btn`);
  if (details) {
    if (details.style.display === 'none') {
      details.style.display = 'block';
      button.querySelector('i').className = 'fa fa-chevron-up';
    } else {
      details.style.display = 'none';
      button.querySelector('i').className = 'fa fa-chevron-down';
    }
  }
}

// Delete annotation type
function deleteType(typeName) {
  if (confirm('Delete annotation type "' + typeName + '"? This cannot be undone.')) {
    const form = document.createElement('form');
    form.method = 'POST';
    
    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'type_name';
    typeInput.value = typeName;
    form.appendChild(typeInput);
    
    const deleteInput = document.createElement('input');
    deleteInput.type = 'hidden';
    deleteInput.name = 'delete_annotation_type';
    deleteInput.value = '1';
    form.appendChild(deleteInput);
    
    document.body.appendChild(form);
    form.submit();
  }
}

// Handle expand button clicks
document.addEventListener('click', function(e) {
  if (e.target.closest('.expand-type-btn')) {
    const button = e.target.closest('.expand-type-btn');
    const typeName = button.getAttribute('data-type');
    toggleTypeDetails(typeName);
  }
});
