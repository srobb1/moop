/**
 * Manage Annotations - Page-Specific Functionality
 */

/**
 * Submit color form when color is selected
 */
function submitColorForm(radioButton) {
  if (!radioButton.checked) return;
  
  const typeName = radioButton.getAttribute('data-type');
  const color = radioButton.value;
  
  const form = document.getElementById(`colorForm_${typeName}`);
  if (!form) return;
  
  const colorInput = document.getElementById(`colorValue_${typeName}`);
  if (!colorInput) return;
  
  // Set the hidden _form_action before submitting
  const formActionInput = form.querySelector('input[name="_form_action"]');
  if (formActionInput) {
    formActionInput.value = 'update_color';
  }
  
  colorInput.value = color;
  form.submit();
}

document.addEventListener('DOMContentLoaded', function() {
  // Setup DataTables if present
  const annotationsTables = document.querySelectorAll('.annotations-table');
  annotationsTables.forEach(table => {
    if (typeof DataTable !== 'undefined') {
      new DataTable(table);
    }
  });
  
  // Make annotation types sortable - auto-save on stop.
  //
  // Guarded because .sortable() comes from jQuery UI, not jQuery. If it is ever missing, the
  // bare call throws a TypeError, and since everything here runs inside one DOMContentLoaded
  // handler that abort takes the move-up/move-down buttons below down with it — losing the
  // drag handle AND its keyboard-accessible fallback, with nothing but a console error. Now
  // drag-to-reorder degrades on its own and the buttons keep working.
  if ($('#sortable-annotation-types').length) {
      if (typeof $.fn.sortable === 'function') {
          $('#sortable-annotation-types').sortable({
              handle: '.fa-grip-vertical',
              items: '.card',
              stop: function() { saveTypeOrder(); }
          });
      } else {
          console.error('jQuery UI did not load — drag-to-reorder is unavailable. ' +
                        'Use the move up/down buttons. Expected /js/vendor/jquery-ui.min.js.');
          $('#sortable-annotation-types').find('.fa-grip-vertical').hide();
      }
  }

  // Move-up / move-down buttons
  $('#sortable-annotation-types').on('click', '.move-up-btn', function() {
      const card = $(this).closest('.card');
      const prev = card.prev('.card');
      if (prev.length) { card.insertBefore(prev); saveTypeOrder(); }
  }).on('click', '.move-down-btn', function() {
      const card = $(this).closest('.card');
      const next = card.next('.card');
      if (next.length) { card.insertAfter(next); saveTypeOrder(); }
  });

  // Preserve open state of customize sections across page reloads
  const customizeForms = document.querySelectorAll('.type-details form');
  customizeForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      const detailsId = this.closest('.type-details').id;
      if (detailsId) {
        sessionStorage.setItem('openCustomizeSection', detailsId);
      }
    });
  });
  
  // Restore open state if previously opened
  const openSection = sessionStorage.getItem('openCustomizeSection');
  if (openSection) {
    const element = document.getElementById(openSection);
    if (element) {
      element.style.display = 'block';
      // Rotate the chevron on the button
      const button = document.querySelector(`[data-type="${openSection.replace('details-', '')}"].expand-type-btn`);
      if (button) {
        const chevron = button.querySelector('.fa-chevron-down');
        if (chevron) {
          chevron.style.transform = 'rotate(-180deg)';
        }
      }
    }
    sessionStorage.removeItem('openCustomizeSection');
  }
  
  // Edit type description button
  $('.edit-type-desc-btn').on('click', function() {
      const typeName = $(this).data('type');
      // Find the description element by closest card and look for the description p tag
      const card = $(this).closest('.card');
      const descElement = card.find('p[id^="desc-type-"]');
      const currentDesc = descElement.data('full-desc') || 'No description';
      
      $('#editTypeDescName').text(typeName);
      $('#editTypeName').val(typeName);
      $('#editTypeDescription').val(currentDesc === 'No description' ? '' : currentDesc);
      
      new bootstrap.Modal($('#editTypeDescModal')).show();
  });
  
  // Add loading spinner to form buttons
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
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
    
    
    if (newOrder.length === 0) {
        return;
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('manage_annotations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
        },
        body: new URLSearchParams({
            'update_type_order': '1',
            'type_order_data': JSON.stringify(newOrder)
        })
    })
    .then(response => {
        if (response.ok) {
            // Show brief success message
            showSaveNotification('Order saved successfully');
        } else {
            showSaveNotification('Error saving order. Please try again.', 'danger');
        }
    })
    .catch(error => {
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
