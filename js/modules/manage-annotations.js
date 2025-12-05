/**
 * Manage Annotations - Page-Specific Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
  // Setup DataTables if present
  const annotationsTables = document.querySelectorAll('.annotations-table');
  annotationsTables.forEach(table => {
    if (typeof DataTable !== 'undefined') {
      new DataTable(table);
    }
  });
});
