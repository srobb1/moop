/**
 * Organism Setup Checklist (admin/organism_checklist.php) -- the page's buttons.
 *
 * Moved out of an inline <script> in admin/pages/organism_checklist.php (CLAUDE.md §7).
 * Requires js/admin-utilities.js (refreshOrganismCache), loaded before it.
 */

// Auto-generate organism.json files
async function generateOrganismJson() {
  const btn = document.getElementById('generateJsonBtn');
  const statusDiv = document.getElementById('generateJsonStatus');
  
  // Disable button and show loading
  btn.disabled = true;
  statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Generating organism.json files from NCBI (this may take a minute)...</div>';
  statusDiv.style.display = 'block';
  
  try {
    const response = await fetch('api/generate_organism_json.php', {
      method: 'POST'
    });
    
    if (response.ok) {
      const data = await response.json();
      let message = `<strong>Success!</strong> Generated ${data.count} organism.json file${data.count !== 1 ? 's' : ''}. `;
      
      if (data.errors && data.errors.length > 0) {
        message += `<strong>Errors:</strong> <ul class="mb-0 mt-2">`;
        data.errors.forEach(err => {
          message += `<li>${err}</li>`;
        });
        message += `</ul>`;
        statusDiv.innerHTML = `<div class="alert alert-warning"><i class="fa fa-check-circle"></i> ${message}</div>`;
      } else {
        message += 'Reloading...';
        statusDiv.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> ${message}</div>`;
      }
      
      // Reload the page after a short delay
      setTimeout(() => {
        location.reload();
      }, 2000);
    } else {
      const errorText = await response.text();
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> Server returned ' + response.status + '. Response: ' + errorText.substring(0, 200) + '</div>';
      btn.disabled = false;
    }
  } catch (error) {
    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + error.message + '</div>';
    btn.disabled = false;
  }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
  const genJsonBtn = document.getElementById('generateJsonBtn');
  if (genJsonBtn) {
    genJsonBtn.addEventListener('click', generateOrganismJson);
  }
  
  // Both cache-refresh buttons (Step 4's "Refresh Cache Now" and the freshness note's
  // "Refresh cache") use the shared refreshOrganismCache() from admin-utilities.js:
  // progress per step, reload when the refresh has actually finished. An inline copy
  // here used to be bound to Step 4's button IN ADDITION to admin-utilities.js's own
  // handler, so one click sent two refresh requests -- one of them a forced full rescan
  // that reloaded the page after a fixed 5 s, usually before it had finished.
  [['generateTreeBtn', 'generateTreeStatus'], ['checklistRefreshCacheBtn', 'checklistRefreshCacheStatus']]
    .forEach(([btnId, statusId]) => {
      const refreshBtn = document.getElementById(btnId);
      if (!refreshBtn) return;
      const label = refreshBtn.innerHTML;
      refreshBtn.addEventListener('click', () =>
        refreshOrganismCache(refreshBtn, document.getElementById(statusId), false, label));
    });

  const assignBtn = document.getElementById('assignToNewGroupBtn');
  if (assignBtn) {
    assignBtn.addEventListener('click', assignOrganismsToNewGroup);
  }
});

// Assign unassigned organisms to "New" group
async function assignOrganismsToNewGroup() {
  const btn = document.getElementById('assignToNewGroupBtn');
  const statusDiv = document.getElementById('assignToGroupStatus');
  
  // Disable button and show loading
  btn.disabled = true;
  statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Adding organisms to "New" group...</div>';
  statusDiv.style.display = 'block';
  
  try {
    const response = await fetch('api/assign_organisms_to_group.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'group=New'
    });
    
    if (response.ok) {
      const data = await response.json();
      statusDiv.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>Success!</strong> Added ${data.count} organism-assembly entries to "New" group. Reloading...</div>`;
      
      // Reload the page after a short delay
      setTimeout(() => {
        location.reload();
      }, 2000);
    } else {
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> Failed to add organisms to group. Please try again or use the full management page.</div>';
      btn.disabled = false;
    }
  } catch (error) {
    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + error.message + '</div>';
    btn.disabled = false;
  }
}

// Copy BLAST command to clipboard
document.addEventListener('DOMContentLoaded', function() {
  const copyBtns = document.querySelectorAll('.copy-cmd-btn');
  copyBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const cmdText = this.getAttribute('data-cmd-text');
      
      navigator.clipboard.writeText(cmdText).then(() => {
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fa fa-check"></i> Copied!';
        this.classList.remove('btn-outline-primary');
        this.classList.add('btn-success');
        
        setTimeout(() => {
          this.innerHTML = originalText;
          this.classList.remove('btn-success');
          this.classList.add('btn-outline-primary');
        }, 2000);
      }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy command. Please copy manually.');
      });
    });
  });

  // Generate BLAST indexes
  const generateBtns = document.querySelectorAll('.generate-blast-btn');
  generateBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const organism = this.getAttribute('data-organism');
      const assembly = this.getAttribute('data-assembly');
      const geneSet  = this.getAttribute('data-gene-set') || 'v1';
      const fasta = this.getAttribute('data-fasta');

      if (!confirm(`Generate BLAST indexes for ${organism}/${assembly}/${geneSet}/${fasta}?\n\nThis may take a few minutes.`)) {
        return;
      }

      const originalText = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

      fetch('api/generate_blast_indexes.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          organism: organism,
          assembly: assembly,
          gene_set: geneSet,
          fasta_file: fasta
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          this.innerHTML = '<i class="fa fa-check"></i> Success!';
          this.classList.remove('btn-outline-success');
          this.classList.add('btn-success');
          
          // Show success message
          const successDiv = document.createElement('div');
          successDiv.className = 'alert alert-success mt-2';
          successDiv.innerHTML = '<i class="fa fa-check-circle"></i> BLAST indexes generated successfully! Reloading...';
          this.closest('li').appendChild(successDiv);
          
          // Reload page after 3 seconds
          setTimeout(() => {
            location.reload();
          }, 3000);
        } else {
          this.disabled = false;
          this.innerHTML = originalText;
          const errorDiv = document.createElement('div');
          errorDiv.className = 'alert alert-danger mt-2';
          errorDiv.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + (data.message || 'Failed to generate indexes');
          this.closest('li').appendChild(errorDiv);
        }
      })
      .catch(err => {
        this.disabled = false;
        this.innerHTML = originalText;
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-2';
        errorDiv.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + err.message;
        this.closest('li').appendChild(errorDiv);
      });
    });
  });
  // Generate FAI indexes
  document.querySelectorAll('.generate-fai-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const organism = this.getAttribute('data-organism');
      const assembly = this.getAttribute('data-assembly');

      if (!confirm(`Generate FAI index for ${organism}/${assembly}/genome.fa?\n\nThis runs: samtools faidx`)) {
        return;
      }

      const originalText = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

      fetch('api/generate_fai_index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ organism, assembly })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          this.innerHTML = '<i class="fa fa-check"></i> Success!';
          this.classList.remove('btn-outline-success');
          this.classList.add('btn-success');
          const ok = document.createElement('div');
          ok.className = 'alert alert-success mt-2';
          ok.innerHTML = '<i class="fa fa-check-circle"></i> FAI index generated successfully! Reloading...';
          this.closest('li').appendChild(ok);
          setTimeout(() => location.reload(), 3000);
        } else {
          this.disabled = false;
          this.innerHTML = originalText;
          const err = document.createElement('div');
          err.className = 'alert alert-danger mt-2';
          err.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + (data.message || 'Failed to generate FAI index');
          this.closest('li').appendChild(err);
        }
      })
      .catch(err => {
        this.disabled = false;
        this.innerHTML = originalText;
        const errDiv = document.createElement('div');
        errDiv.className = 'alert alert-danger mt-2';
        errDiv.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + err.message;
        this.closest('li').appendChild(errDiv);
      });
    });
  });
});
