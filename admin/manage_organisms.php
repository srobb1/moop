<?php
session_start();
include_once 'admin_header.php';
include_once __DIR__ . '/../site_config.php';

$access_group = 'Admin';

// Get all organisms
function get_all_organisms_info() {
    global $organism_data;
    $organisms_info = [];
    
    if (!is_dir($organism_data)) {
        return $organisms_info;
    }
    
    $organisms = scandir($organism_data);
    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$organism_data/$organism")) {
            continue;
        }
        
        // Get organism.json info if exists
        $organism_json = "$organism_data/$organism/organism.json";
        $info = [];
        if (file_exists($organism_json)) {
            $json_data = json_decode(file_get_contents($organism_json), true);
            if ($json_data) {
                // Handle wrapped JSON
                if (!isset($json_data['genus']) && !isset($json_data['common_name'])) {
                    $keys = array_keys($json_data);
                    if (count($keys) > 0 && is_array($json_data[$keys[0]])) {
                        $json_data = $json_data[$keys[0]];
                    }
                }
                $info = $json_data;
            }
        }
        
        // Get assemblies
        $assemblies = [];
        $assembly_path = "$organism_data/$organism";
        $files = scandir($assembly_path);
        foreach ($files as $file) {
            if ($file[0] === '.' || !is_dir("$assembly_path/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        
        // Check for database file
        $has_db = file_exists("$organism_data/$organism/genes.sqlite") || 
                  file_exists("$organism_data/$organism/$organism.genes.sqlite");
        
        $organisms_info[$organism] = [
            'info' => $info,
            'assemblies' => $assemblies,
            'has_db' => $has_db,
            'path' => "$organism_data/$organism"
        ];
    }
    
    return $organisms_info;
}

$organisms = get_all_organisms_info();

include_once '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Organisms</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
  <style>
    .structure-box {
      background: #f8f9fa;
      border-left: 4px solid #007bff;
      padding: 15px;
      font-family: monospace;
      font-size: 0.9em;
    }
    .file-icon { color: #6c757d; }
    .folder-icon { color: #ffc107; }
    .db-icon { color: #28a745; }
    .status-badge { font-size: 0.75rem; }
  </style>
</head>
<body class="bg-light">

<div class="container-fluid mt-5">
  <h2><i class="fa fa-dna"></i> Manage Organisms</h2>
  
  <div class="mb-3">
    <a href="index.php" class="btn btn-secondary">← Back to Admin Tools</a>
  </div>

  <!-- Information Panel -->
  <div class="card mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> Organism Data Management</h5>
    </div>
    <div class="card-body">
      <p>Organisms and genome assemblies are managed by creating or uploading directories to the organisms data folder. Each organism follows a specific directory structure.</p>
      
      <div class="row">
        <div class="col-md-6">
          <h6 class="fw-bold">Required Structure:</h6>
          <div class="structure-box">
            <i class="fa fa-folder folder-icon"></i> <strong>Genus_species</strong> (e.g., Anoura_caudifer)<br>
            &nbsp;&nbsp;<i class="fa fa-database db-icon"></i> genes.sqlite or Genus_species.genes.sqlite<br>
            &nbsp;&nbsp;<i class="fa fa-file file-icon"></i> organism.json<br>
            &nbsp;&nbsp;<i class="fa fa-folder folder-icon"></i> <strong>assembly_name</strong> (e.g., GCA_004027475.1)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.cds.nt.fa (coding sequences)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.protein.aa.fa (proteins)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.transcript.nt.fa (transcripts)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.genome.nt.fa (optional)
          </div>
        </div>
        
        <div class="col-md-6">
          <h6 class="fw-bold">Naming Conventions:</h6>
          <ul class="mb-0">
            <li><strong>Organism Directory:</strong> Genus_species_subspecies (underscores separate components)</li>
            <li><strong>Assembly Directory:</strong> Unique assembly identifier (e.g., GCA_004027475.1, assembly_v1)</li>
            <li><strong>Database File:</strong> genes.sqlite or Genus_species.genes.sqlite</li>
            <li><strong>Organism metadata file:</strong> organism.json</li>
          </ul>
          
          <div class="mt-3">
            <h6 class="fw-bold">
              <a class="text-decoration-none" data-bs-toggle="collapse" href="#jsonExample" role="button" aria-expanded="false" aria-controls="jsonExample">
                Click to view example organism.json <i class="fa fa-chevron-down"></i>
              </a>
            </h6>
            <div class="collapse" id="jsonExample">
              <pre class="bg-light p-2 rounded" style="font-size: 0.85em;">{
  "genus": "Anoura",
  "species": "caudifer",
  "common_name": "Tailed tailless bat",
  "taxon_id": "9999",
  "images": [
    {
      "file": "Anoura_caudifer.jpg",
      "caption": "Image of Tailed tailless bat"
    }
  ],
  "text_html": {
    "p": [
      "Diet: Feeds on nectar and insects...",
      "Fun Fact: Uses echolocation..."
    ]
  }
}</pre>
              <small class="text-muted"><strong>Note:</strong> Images should be placed in <code>/moop/images/</code></small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="alert alert-warning mt-3 mb-0">
        <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> Data must be uploaded directly to the server at: <code><?= htmlspecialchars($organism_data) ?></code>
      </div>
    </div>
  </div>

  <!-- Current Organisms Table -->
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fa fa-list"></i> Current Organisms (<?= count($organisms) ?>)</h5>
    </div>
    <div class="card-body">
      <table id="organismsTable" class="table table-striped table-hover">
        <thead>
          <tr>
            <th>Organism</th>
            <th>Common Name</th>
            <th>Assemblies</th>
            <th>Database</th>
            <th>Status</th>
            <th>Path</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($organisms as $organism => $data): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($organism) ?></strong>
                <?php if (isset($data['info']['genus']) && isset($data['info']['species'])): ?>
                  <br><small class="text-muted"><em><?= htmlspecialchars($data['info']['genus']) ?> <?= htmlspecialchars($data['info']['species']) ?></em></small>
                <?php endif; ?>
              </td>
              <td>
                <?php 
                  if (isset($data['info']['common_name'])) {
                      echo htmlspecialchars($data['info']['common_name']);
                  } else {
                      echo '<span class="text-muted">-</span>';
                  }
                ?>
              </td>
              <td>
                <span class="badge bg-secondary"><?= count($data['assemblies']) ?> assemblies</span>
                <?php if (!empty($data['assemblies'])): ?>
                  <div class="mt-1">
                    <?php foreach ($data['assemblies'] as $assembly): ?>
                      <small class="d-block text-muted"><i class="fa fa-folder"></i> <?= htmlspecialchars($assembly) ?></small>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($data['has_db']): ?>
                  <span class="badge bg-success status-badge"><i class="fa fa-check"></i> Present</span>
                <?php else: ?>
                  <span class="badge bg-danger status-badge"><i class="fa fa-times"></i> Missing</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($data['has_db'] && !empty($data['assemblies'])): ?>
                  <span class="badge bg-success status-badge"><i class="fa fa-check-circle"></i> Complete</span>
                <?php elseif (!empty($data['assemblies'])): ?>
                  <span class="badge bg-warning status-badge"><i class="fa fa-exclamation-triangle"></i> No Database</span>
                <?php else: ?>
                  <span class="badge bg-danger status-badge"><i class="fa fa-times-circle"></i> No Assemblies</span>
                <?php endif; ?>
              </td>
              <td>
                <small class="font-monospace"><?= htmlspecialchars($data['path']) ?></small>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="card mt-4 mb-5">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0"><i class="fa fa-bolt"></i> Quick Actions</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="d-grid">
            <a href="manage_groups.php" class="btn btn-primary">
              <i class="fa fa-layer-group"></i> Assign to Groups
            </a>
            <small class="text-muted mt-2">Add organisms to organism groups</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-grid">
            <a href="manage_group_descriptions.php" class="btn btn-primary">
              <i class="fa fa-edit"></i> Edit Group Descriptions
            </a>
            <small class="text-muted mt-2">Manage group metadata</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-grid">
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#helpModal">
              <i class="fa fa-question-circle"></i> Upload Help
            </button>
            <small class="text-muted mt-2">How to add new organisms</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-question-circle"></i> Adding New Organisms</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <h6 class="fw-bold">Step-by-Step Guide:</h6>
        <ol>
          <li><strong>Create organism directory:</strong> <code>mkdir <?= htmlspecialchars($organism_data) ?>/Genus_species</code></li>
          <li><strong>Add database file:</strong> Upload or create <code>genes.sqlite</code></li>
          <li><strong>Create organism.json:</strong> Add metadata about the organism</li>
          <li><strong>Create assembly directory:</strong> <code>mkdir Genus_species/assembly_name</code></li>
          <li><strong>Upload FASTA files:</strong> Add CDS, protein, transcript, and genome files to the assembly directory</li>
          <li><strong>Assign to groups:</strong> Use "Manage Groups" to make the organism accessible</li>
        </ol>
        
        <h6 class="fw-bold mt-4">Required Files in Assembly Directory:</h6>
        <ul>
          <li><code>*.cds.nt.fa</code> - Coding sequences (nucleotide)</li>
          <li><code>*.protein.aa.fa</code> - Protein sequences (amino acid)</li>
          <li><code>*.transcript.nt.fa</code> - Transcript sequences (nucleotide)</li>
          <li><code>*.genome.nt.fa</code> - Genome assembly (optional)</li>
        </ul>
        
        <h6 class="fw-bold mt-4">Additional Notes:</h6>
        <ul>
          <li><strong>Images:</strong> Place organism images in <code>/moop/images/</code></li>
          <li><strong>Viewing:</strong> Organisms are accessible via <code>/tools/display/organism_display.php?organism=Name</code></li>
          <li><strong>Documentation:</strong> See <code><?= htmlspecialchars($organism_data) ?>/ORGANISM_DISPLAY_README.md</code> for detailed specifications</li>
        </ul>
        
        <div class="alert alert-info mt-3">
          <i class="fa fa-lightbulb"></i> <strong>Tip:</strong> After uploading, this page will automatically detect and display the new organism. Then use "Assign to Groups" to control access.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#organismsTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [5] }
        ]
    });
});
</script>

</body>
</html>

<?php
include_once '../footer.php';
?>
