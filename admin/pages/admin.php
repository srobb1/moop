<?php
/**
 * ADMIN DASHBOARD - Content File
 * 
 * Pure display content - no HTML structure, scripts, or styling.
 * 
 * Layout system (layout.php) handles:
 * - HTML structure (<!DOCTYPE>, <html>, <head>, <body>)
 * - All CSS and resources
 * - All scripts
 * - Navbar and footer
 * 
 * This file has access to variables passed from admin.php:
 * - $config (ConfigManager instance)
 * - $site (site name)
 */
?>

<div class="container mt-5">
  <h2><i class="fa fa-tools"></i> Admin Tools</h2>
  
  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutAdminTools">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Admin Tools <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutAdminTools">
      <div class="card-body">
        <p><strong>Purpose:</strong> Central navigation hub for all administrative functions.</p>
        
        <p><strong>Why This Matters:</strong> This is the entry point for managing your MOOP system. Use these tools to:</p>
        <ul>
          <li>Control who has access to what organisms</li>
          <li>Manage your organism data and metadata</li>
          <li>Organize annotations for display</li>
          <li>Build the taxonomy tree for discovery</li>
          <li>Maintain user accounts and permissions</li>
          <li>Monitor system health and errors</li>
        </ul>
        
        <p><strong>Available Tools:</strong></p>
        <ul class="mb-0">
          <li><strong>Manage Site Configuration</strong> - Edit site title, admin email, and appearance settings</li>
          <li><strong>Manage Users</strong> - Create collaborator accounts and control access</li>
          <li><strong>Manage Organisms</strong> - View and manage all organism data</li>
          <li><strong>Manage Groups</strong> - Tag organisms with flexible categories</li>
          <li><strong>Manage Annotations</strong> - Customize annotation display</li>
          <li><strong>Manage Taxonomy Tree</strong> - Build the organism selector</li>
          <li><strong>Error Logs</strong> - Monitor system health</li>
          <li><strong>Function Registry</strong> - Maintain code documentation</li>
          <li><strong>Filesystem Permissions</strong> - Check and fix file permissions</li>
        </ul>
      </div>
    </div>
  </div>
  
  <div class="row mt-4">
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-cog"></i> Manage Site Configuration</h5>
          <p class="card-text">Edit site settings like title, branding, and admin contact information.</p>
          <a href="manage_site_config.php" class="btn btn-primary">Go to Site Configuration</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-users"></i> Manage Users</h5>
          <p class="card-text">Add new users to the system and manage user accounts.</p>
          <a href="manage_users.php" class="btn btn-primary">Go to Manage Users</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-dna"></i> Manage Organisms</h5>
          <p class="card-text">View current organisms, assemblies, and learn how to add new data.</p>
          <a href="manage_organisms.php" class="btn btn-primary">Go to Manage Organisms</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-layer-group"></i> Manage Groups</h5>
          <p class="card-text">Configure organism assembly groups and group descriptions.</p>
          <a href="manage_groups.php" class="btn btn-primary">Go to Manage Groups</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-tags"></i> Manage Annotation Sections</h5>
          <p class="card-text">Configure annotation section types and descriptions.</p>
          <a href="manage_annotations.php" class="btn btn-primary">Go to Annotation Sections</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-project-diagram"></i> Manage Taxonomy Tree</h5>
          <p class="card-text">Generate and customize the taxonomy tree from organism taxonomy data.</p>
          <a href="manage_taxonomy_tree.php" class="btn btn-primary">Go to Taxonomy Tree</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-exclamation-triangle"></i> Error Logs</h5>
          <p class="card-text">View and manage application error logs for debugging and monitoring.</p>
          <a href="error_log.php" class="btn btn-primary">View Error Logs</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-file-code"></i> Function Registry Management</h5>
          <p class="card-text">Manage PHP and JavaScript function registries. Update, view, and search all functions.</p>
          <a href="manage_registry.php" class="btn btn-primary">Manage Registry</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-lock"></i> Filesystem Permissions</h5>
          <p class="card-text">Complete guide to file and directory permissions. Check and fix permission issues.</p>
          <a href="filesystem_permissions.php" class="btn btn-primary">Check Permissions</a>
        </div>
      </div>
    </div>
  </div>
</div>
