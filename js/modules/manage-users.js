/**
 * Manage Users - Page-Specific Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
  // Setup DataTables if present
  const usersTables = document.querySelectorAll('.users-table');
  usersTables.forEach(table => {
    if (typeof DataTable !== 'undefined') {
      new DataTable(table);
    }
  });
});
