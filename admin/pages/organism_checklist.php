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
        
        <p class="mb-0"><strong>Note:</strong> Most organism data files are uploaded directly to the server. Use the links below to manage access, configuration, and visibility.</p>
      </div>
    </div>
  </div>

  <!-- Checklist Steps -->
  <div class="mt-5">
    <h3 class="mb-3"><i class="fa fa-list-check"></i> Setup Steps</h3>

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

        <p class="mt-3"><strong>Go to:</strong> <a href="manage_filesystem_permissions.php" class="btn btn-primary"><i class="fa fa-lock"></i> Manage Filesystem Permissions</a></p>
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

        <p class="mt-3"><strong>Go to:</strong> <a href="manage_organisms.php" class="btn btn-success"><i class="fa fa-dna"></i> Manage Organisms</a></p>
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

        <p class="mt-3"><strong>Go to:</strong> <a href="manage_taxonomy_tree.php" class="btn btn-info"><i class="fa fa-sitemap"></i> Manage Taxonomy Tree</a></p>
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

        <p class="mt-3"><strong>Go to:</strong> <a href="manage_groups.php" class="btn btn-warning"><i class="fa fa-layer-group"></i> Manage Groups</a></p>
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
  <div class="mt-5 mb-5">
    <p class="text-center">
      <a href="admin.php" class="btn btn-secondary">
        <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
      </a>
    </p>
  </div>
</div>

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
