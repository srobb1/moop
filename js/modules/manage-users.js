/**
 * Manage Users - Page-Specific Functionality
 */

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
  
  // Setup DataTables if present
  const usersTables = document.querySelectorAll('.users-table');
  usersTables.forEach(table => {
    if (typeof DataTable !== 'undefined') {
      new DataTable(table);
    }
  });
});
