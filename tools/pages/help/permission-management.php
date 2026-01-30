<?php
/**
 * PERMISSION MANAGEMENT - Admin Help Tutorial
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
?>

<div class="container mt-5">
  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-4"><i class="fa fa-lock"></i> Permission Management & Alerts</h1>

      <!-- Overview Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">What is Permission Management?</h3>
          <p class="text-muted mb-3">
            Permission Management is a critical system that ensures the web server has the correct access to read and write files and directories needed by MOOP. It includes:
          </p>
          <ul class="text-muted mb-0">
            <li><strong>Automated Detection:</strong> Continuously checks if files and directories are readable and writable by the web server</li>
            <li><strong>Permission Alerts:</strong> Visual warnings when permission issues are detected in the admin interface</li>
            <li><strong>Automated Fixes:</strong> One-click fixes for permission issues when possible</li>
            <li><strong>Manual Instructions:</strong> Clear commands for system administrators to fix issues when automatic fixes aren't possible</li>
            <li><strong>User & Group Management:</strong> Tracks which user/group runs the web server and manages file ownership</li>
          </ul>
        </div>
      </div>

      <!-- Why It Matters Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Why Permission Management is Critical</h3>
          
          <h5 class="fw-semibold text-dark mt-3 mb-2">Common Permission Issues:</h5>
          <ul class="text-muted mb-3">
            <li><strong>Cannot read metadata files:</strong> MOOP cannot load organism information</li>
            <li><strong>Cannot write to logs:</strong> System errors aren't recorded, making debugging difficult</li>
            <li><strong>Cannot update configuration:</strong> Changes made in the admin interface don't persist</li>
            <li><strong>Cannot create organism databases:</strong> New organisms can't be fully set up</li>
            <li><strong>Cannot write to data directories:</strong> Annotations and analysis results can't be saved</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Impact Without Proper Permissions:</h5>
          <ul class="text-muted">
            <li>Silent failures - operations fail without error messages</li>
            <li>Data loss - changes appear to save but don't actually persist</li>
            <li>Difficult troubleshooting - root cause isn't obvious</li>
            <li>Incomplete organism setup - databases can't be fully populated</li>
            <li>Annotation loading failures - analysis results can't be imported</li>
          </ul>
        </div>
      </div>

      <!-- How Permission Alerts Work Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">How Permission Alerts Work</h3>
          
          <h5 class="fw-semibold text-dark mb-2">Alert Appearance:</h5>
          <p class="text-muted mb-3">
            When a permission issue is detected, a yellow alert appears at the top of the admin page. The alert shows:
          </p>
          <ul class="text-muted mb-3">
            <li><strong>Problem Description:</strong> What permission is missing</li>
            <li><strong>File Information:</strong> Current owner, group, and permissions (e.g., <code>644</code>)</li>
            <li><strong>Current Status:</strong> Whether the file is readable/writable</li>
            <li><strong>Web Server User:</strong> Which user runs your web server (usually <code>www-data</code> or <code>apache</code>)</li>
          </ul>

          <h5 class="fw-semibold text-dark mb-2">Two Types of Fixes:</h5>
          <div class="row">
            <div class="col-md-6 mb-3">
              <div class="bg-light p-3 rounded border-left border-success">
                <h6 class="fw-bold text-success mb-2"><i class="fa fa-bolt"></i> Automatic Fix (Recommended)</h6>
                <p class="text-muted small mb-0">
                  When the web server has sufficient permissions, a <strong>"Fix Permissions"</strong> button appears. Click it to automatically fix the issue. The page refreshes when complete.
                </p>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="bg-light p-3 rounded border-left border-warning">
                <h6 class="fw-bold text-warning mb-2"><i class="fa fa-terminal"></i> Manual Fix</h6>
                <p class="text-muted small mb-0">
                  If automatic fix isn't available, exact commands are provided. Copy and run them on your server with appropriate privileges (usually <code>sudo</code>).
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Key Directories Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Key Directories That Need Permissions</h3>
          
          <div class="table-responsive">
            <table class="table table-sm text-muted">
              <thead>
                <tr class="border-bottom">
                  <th class="fw-bold text-dark">Directory</th>
                  <th class="fw-bold text-dark">Purpose</th>
                  <th class="fw-bold text-dark">Needed Access</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><code>metadata/</code></td>
                  <td>Organism metadata and configuration files</td>
                  <td>Read + Write</td>
                </tr>
                <tr>
                  <td><code>organisms/</code></td>
                  <td>Organism databases and data files</td>
                  <td>Read + Write</td>
                </tr>
                <tr>
                  <td><code>logs/</code></td>
                  <td>Application and error logs</td>
                  <td>Write</td>
                </tr>
                <tr>
                  <td><code>config/</code></td>
                  <td>Application configuration files</td>
                  <td>Read + Write</td>
                </tr>
                <tr>
                  <td><code>admin/</code></td>
                  <td>Admin panel files and caches</td>
                  <td>Read + Write</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Best Practices Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Best Practices for Permission Management</h3>
          
          <h5 class="fw-semibold text-dark mb-2">1. Initial Setup:</h5>
          <ul class="text-muted mb-3">
            <li>Set proper permissions during MOOP installation</li>
            <li>Use consistent user/group ownership across all MOOP directories</li>
            <li>Enable SGID bit on key directories for group-based access</li>
          </ul>

          <h5 class="fw-semibold text-dark mb-2">2. File Permissions:</h5>
          <ul class="text-muted mb-3">
            <li><strong>Configuration files:</strong> <code>644</code> (rw-r--r--) or <code>664</code> (rw-rw-r--)</li>
            <li><strong>Directories:</strong> <code>755</code> (rwxr-xr-x) or <code>775</code> (rwxrwxr-x)</li>
            <li><strong>Database files:</strong> <code>664</code> (rw-rw-r--) for group access</li>
          </ul>

          <h5 class="fw-semibold text-dark mb-2">3. Regular Monitoring:</h5>
          <ul class="text-muted mb-3">
            <li>Check the Organism Management page regularly for permission alerts</li>
            <li>Fix issues promptly - don't ignore yellow alerts</li>
            <li>Test file operations after fixing permissions</li>
          </ul>

          <h5 class="fw-semibold text-dark mb-2">4. When to Use Manual Fixes:</h5>
          <ul class="text-muted">
            <li>If automatic fix button is disabled or doesn't work</li>
            <li>If you prefer to fix via command line</li>
            <li>For batch operations affecting multiple files</li>
            <li>When permissions require special system configuration</li>
          </ul>
        </div>
      </div>

      <!-- Troubleshooting Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Troubleshooting Permission Issues</h3>
          
          <div class="accordion" id="troubleshootingAccordion">
            <!-- Item 1 -->
            <div class="accordion-item border-0 mb-2">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#trouble1">
                  <i class="fa fa-circle text-danger me-2"></i> "Fix Permissions" button is disabled or missing
                </button>
              </h2>
              <div id="trouble1" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                <div class="accordion-body text-muted">
                  <p><strong>Cause:</strong> The web server doesn't have write access to the parent directory of the file that needs fixing.</p>
                  <p><strong>Solution:</strong></p>
                  <ol>
                    <li>Use the manual commands provided in the alert</li>
                    <li>Or enable the SGID bit and group permissions on the parent directory</li>
                    <li>Run: <code>sudo chmod g+s /path/to/parent && sudo chmod 775 /path/to/parent</code></li>
                  </ol>
                </div>
              </div>
            </div>

            <!-- Item 2 -->
            <div class="accordion-item border-0 mb-2">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#trouble2">
                  <i class="fa fa-circle text-danger me-2"></i> Permission fixed but alert reappears after page refresh
                </button>
              </h2>
              <div id="trouble2" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                <div class="accordion-body text-muted">
                  <p><strong>Cause:</strong> The file's permissions were changed back by another process, or ownership is incorrect.</p>
                  <p><strong>Solution:</strong></p>
                  <ol>
                    <li>Check current ownership: <code>ls -la /path/to/file</code></li>
                    <li>Ensure web server user owns the file: <code>sudo chown www-data:www-data /path/to/file</code></li>
                    <li>Set permissions: <code>sudo chmod 664 /path/to/file</code></li>
                    <li>Check if another process is resetting permissions</li>
                  </ol>
                </div>
              </div>
            </div>

            <!-- Item 3 -->
            <div class="accordion-item border-0 mb-2">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#trouble3">
                  <i class="fa fa-circle text-danger me-2"></i> Manual fix command fails with permission denied
                </button>
              </h2>
              <div id="trouble3" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                <div class="accordion-body text-muted">
                  <p><strong>Cause:</strong> You don't have sufficient permissions to run the command, or the target path doesn't exist.</p>
                  <p><strong>Solution:</strong></p>
                  <ol>
                    <li>Ensure you use <code>sudo</code> for the command</li>
                    <li>Verify the path exists: <code>ls /path/to/file</code></li>
                    <li>Check your user is in the sudoers group: <code>sudo -l</code></li>
                    <li>If path contains spaces, quote it: <code>sudo chown www-data "/path with spaces/file"</code></li>
                  </ol>
                </div>
              </div>
            </div>

            <!-- Item 4 -->
            <div class="accordion-item border-0 mb-2">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#trouble4">
                  <i class="fa fa-circle text-danger me-2"></i> Data isn't saving to database or log files
                </button>
              </h2>
              <div id="trouble4" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                <div class="accordion-body text-muted">
                  <p><strong>Cause:</strong> The web server doesn't have write permissions to the database or logs directory.</p>
                  <p><strong>Solution:</strong></p>
                  <ol>
                    <li>Check the permission alert on the Organism Management page</li>
                    <li>Look for alerts on database or logs directories</li>
                    <li>Use the automated fix or follow the manual commands provided</li>
                    <li>Test the operation again after fixing</li>
                  </ol>
                </div>
              </div>
            </div>

            <!-- Item 5 -->
            <div class="accordion-item border-0">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#trouble5">
                  <i class="fa fa-circle text-danger me-2"></i> New organism can't be created or metadata can't be edited
                </button>
              </h2>
              <div id="trouble5" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                <div class="accordion-body text-muted">
                  <p><strong>Cause:</strong> The metadata directory or configuration files don't have write permissions.</p>
                  <p><strong>Solution:</strong></p>
                  <ol>
                    <li>Check Organism Management page for permission alerts on <code>metadata/</code></li>
                    <li>Ensure both the directory and individual metadata files are writable</li>
                    <li>Use automated fix or run: <code>sudo chown -R www-data:www-data /path/to/metadata && sudo chmod -R 775 /path/to/metadata</code></li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin Tool Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Permission Management Tool</h3>
          <p class="text-muted mb-3">
            MOOP provides an admin tool to identify and fix permission issues automatically. Access it at:
          </p>
          <p class="text-muted mb-0">
            <strong>Admin Panel → Filesystem Permissions</strong> or navigate directly to <code>admin/manage_filesystem_permissions.php</code>
          </p>
          <p class="text-muted mt-2 small">
            This tool scans all critical MOOP directories and provides one-click fixes for most permission issues, or clear instructions for manual fixes when needed.
          </p>
        </div>
      </div>

      <!-- Related Topics Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Related Topics</h3>
          <ul class="text-muted">
            <li><a href="help.php?topic=organism-data-organization" class="text-decoration-none">Data Organization (Technical)</a> - Understand MOOP's file structure</li>
            <li><a href="help.php?topic=organism-setup-and-searches" class="text-decoration-none">Setup & Searches (Technical)</a> - Complete setup instructions</li>
            <li><a href="help.php?topic=system-requirements" class="text-decoration-none">System Requirements (Technical)</a> - Hardware and OS requirements</li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>
