<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      
      <!-- Main Setup Card -->
      <div class="card border-warning shadow-lg">
        <div class="card-header bg-warning bg-opacity-10">
          <h3 class="mb-0">
            <i class="fa fa-exclamation-triangle text-warning"></i> 
            JBrowse2 Not Installed
          </h3>
        </div>
        <div class="card-body">
          <p class="lead">
            JBrowse2 genome browser is not currently installed on this system. 
            You'll need to install JBrowse2 before you can manage tracks and configurations.
          </p>
          
          <hr>
          
          <h5><i class="fa fa-info-circle text-primary"></i> What is JBrowse2?</h5>
          <p>
            JBrowse2 is a modern, web-based genome browser that allows users to visualize 
            genomic data including reference sequences, gene annotations, RNA-seq data, 
            variants, and more.
          </p>
          
          <h5 class="mt-4"><i class="fa fa-list-check text-success"></i> Installation Steps</h5>
          <div class="alert alert-info">
            <strong>Note:</strong> Installation requires command-line access to the server.
          </div>
          
          <ol class="mb-4">
            <li class="mb-2">
              <strong>Review Prerequisites</strong>
              <ul>
                <li>Node.js (v14 or higher)</li>
                <li>Command-line access to server</li>
                <li>Write permissions to <code><?php echo $site_path; ?>/</code></li>
              </ul>
            </li>
            
            <li class="mb-2">
              <strong>Follow Installation Documentation</strong>
              <p class="mt-2">
                Complete installation instructions are available in the project documentation:
              </p>
              <a href="https://github.com/NAL-i5K/jbrowse2-server-install" 
                 class="btn btn-primary" 
                 target="_blank">
                <i class="fa fa-external-link"></i> View JBrowse2 Installation Guide
              </a>
            </li>
            
            <li class="mb-2">
              <strong>Verify Installation</strong>
              <p class="mt-2">
                JBrowse2 should be installed to: <code><?php echo $site_path; ?>/jbrowse2/</code>
              </p>
              <p>
                After installation, the following should exist:
              </p>
              <ul>
                <li><code><?php echo $site_path; ?>/jbrowse2/index.html</code></li>
                <li><code><?php echo $site_path; ?>/jbrowse2/@jbrowse/</code> (dev build) OR <code>jbrowse2/static/</code> (production build)</li>
              </ul>
            </li>
            
            <li class="mb-2">
              <strong>Reload This Page</strong>
              <p class="mt-2">
                Once JBrowse2 is installed, refresh this page to access the management dashboard.
              </p>
            </li>
          </ol>
          
          <hr>
          
          <h5><i class="fa fa-book text-info"></i> Additional Resources</h5>
          <ul>
            <li>
              <a href="https://jbrowse.org/jb2/docs/" target="_blank">
                JBrowse2 Official Documentation
              </a>
            </li>
            <li>
              <a href="https://github.com/GMOD/jbrowse-components" target="_blank">
                JBrowse2 GitHub Repository
              </a>
            </li>
            <li>
              <strong>Internal Documentation:</strong> 
              Check <code>docs/JBrowse2/</code> for project-specific setup guides
            </li>
          </ul>
          
          <div class="mt-4 p-3 bg-light border rounded">
            <h6><i class="fa fa-terminal"></i> Quick Install Command</h6>
            <p class="mb-2">From your project directory:</p>
            <pre class="mb-0"><code>cd <?php echo $site_path; ?>
# Follow instructions at: https://github.com/NAL-i5K/jbrowse2-server-install</code></pre>
          </div>
        </div>
        <div class="card-footer text-center">
          <button onclick="window.location.reload()" class="btn btn-success btn-lg">
            <i class="fa fa-sync"></i> Check Again
          </button>
          <a href="admin.php" class="btn btn-secondary btn-lg ms-2">
            <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
          </a>
        </div>
      </div>
      
      <!-- Status Check Details -->
      <div class="card mt-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fa fa-search"></i> Installation Status Details</h5>
        </div>
        <div class="card-body">
          <p class="mb-3">The following checks were performed:</p>
          <table class="table table-sm">
            <tbody>
              <tr>
                <td><i class="fa fa-folder text-muted"></i> JBrowse2 directory</td>
                <td>
                  <?php if (is_dir($site_path . '/jbrowse2')): ?>
                    <span class="badge bg-success">Found</span>
                  <?php else: ?>
                    <span class="badge bg-danger">Not Found</span>
                  <?php endif; ?>
                </td>
                <td><code><?php echo $site_path; ?>/jbrowse2/</code></td>
              </tr>
              <tr>
                <td><i class="fa fa-file text-muted"></i> index.html</td>
                <td>
                  <?php if (file_exists($site_path . '/jbrowse2/index.html')): ?>
                    <span class="badge bg-success">Found</span>
                  <?php else: ?>
                    <span class="badge bg-danger">Not Found</span>
                  <?php endif; ?>
                </td>
                <td><code>jbrowse2/index.html</code></td>
              </tr>
              <tr>
                <td><i class="fa fa-folder-tree text-muted"></i> Core libraries</td>
                <td>
                  <?php 
                  $has_jbrowse = is_dir($site_path . '/jbrowse2/@jbrowse');
                  $has_static = is_dir($site_path . '/jbrowse2/static');
                  if ($has_jbrowse || $has_static): 
                  ?>
                    <span class="badge bg-success">Found</span>
                  <?php else: ?>
                    <span class="badge bg-danger">Not Found</span>
                  <?php endif; ?>
                </td>
                <td>
                  <code>jbrowse2/@jbrowse/</code> or <code>jbrowse2/static/</code>
                  <?php if ($has_static): ?>
                    <br><small class="text-muted">(Production build detected)</small>
                  <?php elseif ($has_jbrowse): ?>
                    <br><small class="text-muted">(Development build detected)</small>
                  <?php endif; ?>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
    </div>
  </div>
</div>
