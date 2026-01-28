<?php
/**
 * ORGANISM CHECKLIST - Content File
 * 
 * Pure display content - no HTML structure, scripts, or styling.
 * 
 * Layout system (layout.php) handles:
 * - HTML structure (<!DOCTYPE>, <html>, <head>, <body>)
 * - All CSS and resources
 * - All scripts
 * - Navbar and footer
 * 
 * This file has access to variables passed from organism_checklist.php:
 * - $config (ConfigManager instance)
 * - $site (site name)
 * - $organism_data (path to organism data directory)
 */
?>

<div class="container mt-5">
  <h2><i class="fa fa-clipboard-list"></i> New Organism Setup Checklist</h2>

  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutChecklist" role="button" tabindex="0">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About This Checklist <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutChecklist">
      <div class="card-body">
        <p><strong>Purpose:</strong> Step-by-step guide for adding a new organism to your system with quick links to relevant admin tools.</p>
        
        <p><strong>Why This Matters:</strong> Adding a new organism requires several coordinated steps across the system. This checklist helps you:</p>
        <ul>
          <li>Follow the correct sequence of operations</li>
          <li>Remember all necessary configuration steps</li>
          <li>Avoid common setup mistakes</li>
          <li>Quickly navigate to relevant admin pages</li>
          <li>Verify each step is complete before moving on</li>
        </ul>
        
        <p><strong>Built-in Checks & Actions:</strong> Each step includes automated checks to verify configuration and quick action buttons to fix easy-to-address issues:</p>
        <ul>
          <li><strong>Automated Checks:</strong> File permissions, missing configuration files, group assignments, and taxonomy tree membership</li>
          <li><strong>Quick Fix Actions:</strong> Generate missing organism.json files, assign organisms to default groups, and add organisms to taxonomy tree</li>
          <li><strong>Detailed Management:</strong> Links to full management pages for complex customizations</li>
        </ul>
        
        <p class="mb-0"><strong>Note:</strong> Most organism data files are uploaded directly to the server. Use the links below to manage access, configuration, and visibility.</p>
      </div>
    </div>
  </div>

  <!-- Checklist Steps -->
  <div class="mt-5">
    <h3 class="mb-3"><i class="fa fa-list-check"></i> Setup Steps</h3>

    <?php
    // Load all organisms in system once (used by all steps)
    $organisms_in_system = [];
    if (is_dir($organism_data)) {
        foreach (scandir($organism_data) as $item) {
            if ($item !== '.' && $item !== '..' && is_dir("$organism_data/$item")) {
                $organisms_in_system[] = $item;
            }
        }
    }
    sort($organisms_in_system);
    ?>

    <!-- Step 1: Copy Files -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 1</span>
          Copy Files to Organism Directory
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Create a new directory in <code><?= htmlspecialchars($organism_data) ?></code> with the organism name (e.g., <code>Anoura_caudifer</code>)</li>
          <li>Upload or copy the SQLite database file: <code>organism.sqlite</code></li>
          <li>Copy an existing <code>organism.json</code> metadata file into this directory or create one in Step 3</li>
          <li>Create assembly subdirectory (e.g., <code>GCA_004027475.1_v1</code>)</li>
          <li>Place FASTA files in the assembly directory following naming conventions (see below)</li>
          <li>Copy BLAST index files or generate them in Step 3</li>
        </ul>

        <!-- Directory Structure -->
        <div class="mt-4">
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#directoryStructureStep1" role="button" tabindex="0">
            <i class="fa fa-folder"></i> Expected Directory Structure <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse" id="directoryStructureStep1">
            <div class="structure-box bg-light p-3 border rounded mb-3">
              <div class="mb-2">
                <code><strong>Genus_species</strong></code> (e.g., Anoura_caudifer)<br/>
                <div class="ms-3">
                  <code>├─ organism.sqlite</code> (gene database)<br/>
                  <code>├─ organism.json</code> (metadata file)<br/>
                  <code>└─ <strong>assembly_name</strong></code> (e.g., GCA_004027475.1_v1)<br/>
                  <div class="ms-3">
                    <code>├─ transcript.nt.fa</code> (mRNA sequences)<br/>
                    <code>├─ protein.aa.fa</code> (protein sequences)<br/>
                    <code>├─ cds.nt.fa</code> (coding sequences)<br/>
                    <code>└─ genome.fa</code> (optional: full genome)<br/>
                  </div>
                </div>
              </div>
            </div>

            <p class="mb-2"><strong>File Naming Notes:</strong></p>
            <ul class="mb-0">
              <li>Organism directory: Use underscores to separate genus, species, and optional subspecies</li>
              <li>Database file: Must be named <code>organism.sqlite</code></li>
              <li>Metadata file: Must be named <code>organism.json</code></li>
              <li>FASTA files: Patterns shown above are the defaults. <strong>These are configurable!</strong> See <a href="manage_site_config.php" target="_blank">Manage Site Configuration</a> to customize which file types are enabled and their naming patterns</li>
              <li>Assembly directory: Use a unique identifier (typically from NCBI: GCA_xxxxxxx.x)</li>
            </ul>
          </div>
        </div>

        <!-- organism.json Format -->
        <div class="mt-4">
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#jsonFormatStep1" role="button" tabindex="0">
            <i class="fa fa-file-code"></i> organism.json Format <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse" id="jsonFormatStep1">
            <p class="mb-2">The <code>organism.json</code> file contains metadata about your organism. You have two options to create it:</p>
            
            <div class="alert alert-info mb-3">
              <i class="fa fa-lightbulb"></i> <strong>Easy Option:</strong> Use the <strong>Status</strong> button in <a href="manage_organisms.php" target="_blank"><strong>Manage Organisms</strong></a> (Step 3) to create and edit the organism.json file through an interactive interface. No manual file editing needed!
            </div>

            <p class="mb-2"><strong>Required Fields:</strong></p>
            <ul class="mb-3">
              <li><code>genus</code> - Organism genus (required)</li>
              <li><code>species</code> - Organism species (required)</li>
              <li><code>common_name</code> - User-friendly name (required)</li>
              <li><code>taxon_id</code> - NCBI taxonomy ID (required)</li>
            </ul>

            <p class="mb-2"><strong>Optional Fields:</strong></p>
            <ul class="mb-3">
              <li><code>images</code> - Array of image objects with filename and caption</li>
              <li><code>html_p</code> - Array of HTML content paragraphs</li>
            </ul>

            <p class="mb-2"><strong>Example organism.json:</strong></p>
            <pre class="bg-light p-3 rounded code-example">{
  "genus": "Lasiurus",
  "species": "cinereus",
  "common_name": "Hoary bat",
  "taxon_id": "257879",
  "images": [
    {
      "file": "Lasiurus_cinereus.jpg",
      "caption": "Image of a Hoary bat"
    }
  ],
  "html_p": [
    {
      "text": "&lt;u&gt;Diet:&lt;/u&gt; Hoary bats feed primarily on moths...",
      "style": "",
      "class": "fs-5"
    }
  ]
}</pre>
            <small class="text-muted"><strong>Note:</strong> Images should be placed in <code>/moop/images/</code>. Complete and review this metadata in <strong>Step 3</strong>.</small>
          </div>
        </div>

        <!-- organism.sqlite Status Check -->
        <div class="mt-4">
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#sqliteStatusStep1" role="button" tabindex="0">
            <i class="fa fa-database"></i> Database File Status <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse show" id="sqliteStatusStep1">
            <?php
            // Check for missing SQLite databases
            $missing_sqlite = [];
            foreach ($organisms_in_system as $org) {
                $sqlite_path = "$organism_data/$org/organism.sqlite";
                if (!file_exists($sqlite_path)) {
                    $missing_sqlite[] = $org;
                }
            }
            ?>

            <?php if (!empty($missing_sqlite)): ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> <strong>Missing organism.sqlite files:</strong>
                <p class="mb-3 mt-2">The following organisms don't have an <code>organism.sqlite</code> database file:</p>
                <div class="bg-light p-2 rounded mb-3" style="max-height: 150px; overflow-y: auto;">
                  <ul class="mb-0">
                    <?php foreach ($missing_sqlite as $org): ?>
                      <li><code><?= htmlspecialchars($org) ?>/organism.sqlite</code></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                
                <p class="mb-2"><strong>What to do:</strong></p>
                <ul class="mb-2">
                  <li>Create your own <code>organism.sqlite</code> file with gene/feature data</li>
                  <li>Or use an empty placeholder database to get started</li>
                  <li>See the <a href="/moop/help.php?topic=organism-data-organization#database-schema" target="_blank"><strong>Database Schema section</strong></a> in the organism data organization help for detailed information on file format and structure</li>
                </ul>
              </div>
            <?php else: ?>
              <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> All organisms have <code>organism.sqlite</code> files!
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Step 2: Check File Permissions -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 2</span>
          Check File Permissions
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Verify the web server can read all organism files and databases</li>
          <li>Check that directories have execute permissions for traversal</li>
          <li>Ensure databases are readable by the web server user</li>
        </ul>

        <?php
        // Quick permission check on organism directories using same logic as manage_filesystem_permissions.php
        $permission_issues = [];
        
        foreach ($organisms_in_system as $org) {
            $org_dir = "$organism_data/$org";
            
            if (!file_exists($org_dir)) {
                $permission_issues[] = [
                    'organism' => $org,
                    'path' => $org_dir,
                    'issues' => ['Directory does not exist']
                ];
                continue;
            }
            
            // Get full permission with leading digits (e.g., 2775, 0755)
            $perms_full = substr(sprintf('%o', fileperms($org_dir)), -4);
            $perms = ltrim($perms_full, '0') ?: '0';
            
            $group = posix_getgrgid(filegroup($org_dir))['name'] ?? 'unknown';
            
            $issues = [];
            
            // Check permissions - should be 2775 (with SGID bit)
            if ($perms !== '2775') {
                $issues[] = "Permissions are $perms, should be 2775";
            }
            
            // Check group - should be www-data
            if ($group !== 'www-data') {
                $issues[] = "Group is $group, should be www-data";
            }
            
            if (!empty($issues)) {
                $permission_issues[] = [
                    'organism' => $org,
                    'path' => $org_dir,
                    'perms' => $perms,
                    'group' => $group,
                    'issues' => $issues
                ];
            }
        }
        
        // DEBUG: Show what we're checking
        // echo "<!-- Debug: organisms_in_system = " . implode(', ', $organisms_in_system) . " -->";
        // echo "<!-- Debug: permission_issues count = " . count($permission_issues) . " -->";
        ?>
        
        <?php if (!empty($permission_issues)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Permission Issues Found:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($permission_issues as $item): ?>
                <li>
                  <strong><?= htmlspecialchars($item['organism']) ?></strong><br>
                  Current: <?= $item['perms'] ?? 'unknown' ?> (group: <?= htmlspecialchars($item['group'] ?? 'unknown') ?>) | Required: 2775 (group: www-data)
                  <?php if (!empty($item['issues'])): ?>
                    <ul class="mt-1 mb-0">
                      <?php foreach ($item['issues'] as $issue): ?>
                        <li><?= htmlspecialchars($issue) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <div class="mt-3 p-2 bg-dark text-light rounded" style="font-family: monospace; font-size: 0.85em;">
              <strong>To fix all organism directories, run:</strong><br>
              sudo chmod -R 2775 <?= htmlspecialchars($organism_data) ?><br>
              sudo chgrp -R www-data <?= htmlspecialchars($organism_data) ?>
            </div>
          </div>
          <p class="mt-3"><strong>Full Management:</strong> <a href="manage_filesystem_permissions.php" class="btn btn-primary"><i class="fa fa-lock"></i> Manage Filesystem Permissions</a></p>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All organism directories have proper permissions (2775, www-data group)!
          </div>
          <p class="mt-3"><strong>Full Management:</strong> <a href="manage_filesystem_permissions.php" class="btn btn-primary"><i class="fa fa-lock"></i> Manage Filesystem Permissions</a></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Step 3: Check Organism Status -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 3</span>
          Check Organism Status & Configure Metadata
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Verify assemblies and FASTA files are detected correctly</li>
          <li>Complete organism metadata:
            <ul class="mt-2">
              <li><strong>Genus</strong> and <strong>Species</strong> (required)</li>
              <li><strong>Common Name</strong> (required) - user-friendly name</li>
              <li><strong>Taxon ID</strong> (required) - NCBI taxonomy identifier</li>
              <li><strong>Images</strong> (optional) - organism photos for display</li>
              <li><strong>Descriptions</strong> (optional) - additional HTML content about the organism</li>
            </ul>
          </li>
          <li>Check that FASTA files are properly formatted for BLAST (if indexes are missing, you may need to rebuild BLAST indexes)</li>
        </ul>

        <?php
        // Load all organisms in system (used by Steps 3, 4, and 5)
        $organisms_in_system = [];
        if (is_dir($organism_data)) {
            foreach (scandir($organism_data) as $item) {
                if ($item !== '.' && $item !== '..' && is_dir("$organism_data/$item")) {
                    $organisms_in_system[] = $item;
                }
            }
        }
        sort($organisms_in_system);
        ?>

        <?php
        // Check for organisms missing organism.json
        $organisms_missing_json = [];
        foreach ($organisms_in_system as $org) {
            $json_file = "$organism_data/$org/organism.json";
            if (!file_exists($json_file)) {
                $organisms_missing_json[] = $org;
            }
        }
        ?>
        
        <?php if (!empty($organisms_missing_json)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Missing organism.json:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($organisms_missing_json as $org): ?>
                <li><?= htmlspecialchars($org) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          
          <p class="mt-3"><strong>Quick Action:</strong></p>
          <button type="button" class="btn btn-primary" id="generateJsonBtn">
            <i class="fa fa-magic"></i> Auto-Generate Missing Files
          </button>
          <small class="text-muted d-block mt-2">
            <i class="fa fa-info-circle"></i> This will fetch organism data from NCBI and generate organism.json files
          </small>
          <div id="generateJsonStatus" style="display: none; margin-top: 1rem;"></div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All organisms have organism.json files!
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Management:</strong> <a href="manage_organisms.php" class="btn btn-success"><i class="fa fa-dna"></i> Manage Organisms</a></p>
      </div>
    </div>

    <!-- Step 4: Add to Taxonomy Tree -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 4</span>
          Add Organism to Taxonomy Tree
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Add the new organism to the site's taxonomy tree</li>
          <li>This makes it discoverable on the homepage organism selector</li>
          <li>Organize it within the appropriate taxonomic hierarchy</li>
        </ul>

        <?php
        // Check taxonomy tree
        $tree_file = dirname($organism_data) . '/metadata/taxonomy_tree_config.json';
        $organisms_not_in_tree = [];
        
        foreach ($organisms_in_system as $org) {
            if (!isAssemblyInTaxonomyTree($org, '', $tree_file)) {
                $organisms_not_in_tree[] = $org;
            }
        }
        ?>
        
        <?php if (!empty($organisms_not_in_tree)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Missing from Taxonomy Tree:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($organisms_not_in_tree as $org): ?>
                <li><?= htmlspecialchars($org) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          
          <p class="mt-3"><strong>Quick Action:</strong></p>
          <button type="button" class="btn btn-primary" id="generateTreeBtn">
            <i class="fa fa-sync-alt"></i> Auto-Generate Tree from NCBI
          </button>
          <small class="text-muted d-block mt-2">
            <i class="fa fa-clock"></i> This will generate the tree for all organisms (~<?= count($organisms_in_system) ?> seconds)
          </small>
          <div id="generateTreeStatus" style="display: none; margin-top: 1rem;"></div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All organisms are in the taxonomy tree!
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Management:</strong> <a href="manage_taxonomy_tree.php" class="btn btn-info"><i class="fa fa-sitemap"></i> Manage Taxonomy Tree</a></p>
      </div>
    </div>

    <!-- Step 5: Assign to Groups -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 5</span>
          Assign to Groups & Control Access
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Assign the organism and its assemblies to user groups</li>
          <li><strong>Public Group:</strong> Makes the organism assembly accessible to anyone.</li>
          <li><strong>Other Groups:</strong> Groups are used to make an organism and assembly easy for users to locate</li>
          <li>You can create new groups and customize them with:
            <ul class="mt-2">
              <li>Group descriptions</li>
              <li>Group images and branding</li>
              <li>HTML descriptions</li>
            </ul>
          </li>
        </ul>

        <?php
        // Load organism_assembly_groups.json
        $groups_file = dirname($organism_data) . '/metadata/organism_assembly_groups.json';
        $group_assignments = [];
        
        if (file_exists($groups_file)) {
            $group_data = json_decode(file_get_contents($groups_file), true);
            if (is_array($group_data)) {
                foreach ($group_data as $item) {
                    $org = $item['organism'] ?? '';
                    if (!empty($org)) {
                        $group_assignments[$org] = $item['groups'] ?? [];
                    }
                }
            }
        }
        
        // Check which organisms in the system don't have group assignments
        $unassigned_organisms = [];
        foreach ($organisms_in_system as $org) {
            if (empty($group_assignments[$org])) {
                $unassigned_organisms[] = $org;
            }
        }
        ?>
        
        <?php if (!empty($unassigned_organisms)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Not Assigned to Groups:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($unassigned_organisms as $org): ?>
                <li><?= htmlspecialchars($org) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          
          <p class="mt-3"><strong>Quick Action:</strong></p>
          <button type="button" class="btn btn-primary" id="assignToNewGroupBtn">
            <i class="fa fa-plus-circle"></i> Add to "New" Group
          </button>
          <small class="text-muted d-block mt-2">
            <i class="fa fa-info-circle"></i> This will add all unassigned organisms to the "New" group
          </small>
          <div id="assignToGroupStatus" style="display: none; margin-top: 1rem;"></div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All organisms are assigned to groups!
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Management:</strong> <a href="manage_groups.php" class="btn btn-warning"><i class="fa fa-layer-group"></i> Manage Groups</a></p>
      </div>
    </div>

    <!-- Step 6: Manage User Access -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 6</span>
          Manage User Access (If Non-Public Assembly)
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>If the organism assembly is NOT in the public group, assign access to specific users</li>
          <li>Users in the all-access IP range can view all organisms and assemblies. (See the Auto-Login IP Ranges section in the <a href="manage_site_config.php">Manage Site Configuration</a>)</li>
          <li>Create user accounts for collaborators who need access to specific assemblies</li>
          <li>Assign users to specific assemblies</li>
          <li>Users must be authenticated to view non-public organisms/assemblies</li>
        </ul>

        <p class="mt-3"><strong>Go to:</strong> <a href="manage_users.php" class="btn btn-danger"><i class="fa fa-users"></i> Manage Users</a></p>
        <p class="mt-3"><strong>Configure IP ranges:</strong> <a href="manage_site_config.php" class="btn btn-secondary"><i class="fa fa-cog"></i> Manage Site Configuration</a></p>
      </div>
    </div>
  </div>


  <!-- Navigation -->


<style>
.structure-box {
  font-family: monospace;
  font-size: 0.95em;
  line-height: 1.6;
}

.card-header {
  cursor: pointer;
}

.btn-outline-primary:hover {
  text-decoration: none;
}
</style>

<script>
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

// Define function inline (will also be in admin-utilities.js but this ensures it's available)
async function generateTreeFromChecklist() {
  const btn = document.getElementById('generateTreeBtn');
  const statusDiv = document.getElementById('generateTreeStatus');
  
  // Disable button and show loading
  btn.disabled = true;
  statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Generating taxonomy tree from NCBI (this may take a minute)...</div>';
  statusDiv.style.display = 'block';
  
  try {
    const response = await fetch('manage_taxonomy_tree.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'action=generate'
    });
    
    if (response.ok) {
      statusDiv.innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>Success!</strong> Taxonomy tree has been generated. Reloading...</div>';
      
      // Reload the page after a short delay
      setTimeout(() => {
        location.reload();
      }, 2000);
    } else {
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> Failed to generate tree. Please try again or use the full management page.</div>';
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
  
  const btn = document.getElementById('generateTreeBtn');
  if (btn) {
    btn.addEventListener('click', generateTreeFromChecklist);
  }
  
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
</script>

  <!-- Back to Admin Link (Bottom) -->
  <div class="mt-5 mb-4">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin
    </a>
  </div>
</div>
