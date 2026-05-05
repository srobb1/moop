<?php
/**
 * On-demand organism modal renderer.
 * Returns HTML for a single Bootstrap modal-dialog based on type + organism.
 */
include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/blast_functions.php';
include_once __DIR__ . '/../../lib/functions_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$type     = $_POST['type']     ?? '';
$organism = $_POST['organism'] ?? '';
$assembly = $_POST['assembly'] ?? '';

if (!in_array($type, ['db', 'metadata', 'asm', 'status'], true)) {
    http_response_code(400);
    echo 'Invalid modal type';
    exit;
}

// Organism names are directory names: letters, digits, underscores, hyphens, dots.
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $organism)) {
    http_response_code(400);
    echo 'Invalid organism name';
    exit;
}

$organism_data      = $config->getPath('organism_data');
$metadata_path      = $config->getPath('metadata_path');
$sequence_types     = $config->getSequenceTypes();
$groups_data        = getGroupData();
$taxonomy_tree_file = $metadata_path . '/taxonomy_tree_config.json';

$cache_file = "$organism_data/.organism_cache.json";
if (!file_exists($cache_file)) {
    http_response_code(503);
    echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-warning">Cache unavailable — try refreshing the cache.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
    exit;
}

$raw_cache = json_decode(file_get_contents($cache_file), true);
$organisms = $raw_cache['data'] ?? [];

if (!isset($organisms[$organism])) {
    http_response_code(404);
    echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-danger">Organism not found. Try refreshing the cache.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
    exit;
}

$data = $organisms[$organism];

header('Content-Type: text/html; charset=utf-8');

switch ($type) {
    case 'db':       render_db_modal($organism, $data); break;
    case 'metadata': render_metadata_modal($organism, $data, $organism_data); break;
    case 'asm':
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $assembly)) {
            http_response_code(400);
            echo 'Invalid assembly name';
            exit;
        }
        render_asm_modal($organism, $assembly, $data, $sequence_types, $groups_data, $organism_data);
        break;
    case 'status':
        render_status_modal($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
        break;
}

// ---------------------------------------------------------------------------

function render_db_modal($organism, $data) {
    if (!$data['db_validation']) {
        echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-warning">No database validation data available.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
        return;
    }
    $validation         = $data['db_validation'];
    $assembly_validation = $data['assembly_validation'];
    $org_safe           = htmlspecialchars($organism);
    ?>
<div class="modal-dialog modal-lg">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="fa fa-database"></i> Database Status: <?= $org_safe ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">

      <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Overall Status</h6>
      <div class="card mb-3">
        <div class="card-body">
          <?php if ($validation['valid']): ?>
            <span class="badge bg-success h6"><i class="fa fa-check-circle"></i> Database is Healthy</span>
          <?php else: ?>
            <span class="badge bg-danger h6"><i class="fa fa-times-circle"></i> Database has Issues</span>
            <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below before using this organism.</p>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> Database File</h6>
      <div class="alert alert-info small mb-3">
        <strong>Required:</strong> A valid SQLite database file named <code>organism.sqlite</code> must exist in the organism directory with read permissions for the web server.
      </div>
      <div class="card mb-3">
        <div class="card-body small">
          <p class="mb-1"><strong>Path:</strong> <?= htmlspecialchars($data['db_file'] ?? 'N/A') ?></p>
          <p class="mb-0">
            <strong>Readable:</strong>
            <?= $validation['readable'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
          </p>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Database Validity</h6>
      <div class="alert alert-info small mb-3">
        <strong>Required:</strong> Database must be a valid SQLite3 file with proper structure. It should contain all required tables from the schema.
      </div>
      <div class="card mb-3">
        <div class="card-body small">
          <p class="mb-1">
            <strong>Valid SQLite:</strong>
            <?= $validation['database_valid'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
          </p>
          <?php if (!empty($validation['errors'])): ?>
            <p class="mb-0"><strong>Errors:</strong></p>
            <ul class="mb-0">
              <?php foreach ($validation['errors'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-table"></i> Database Tables</h6>
      <div class="alert alert-info small mb-3">
        <strong>Required Tables:</strong> organism, genome, feature, annotation_source, annotation, feature_annotation. Each table should have relevant data.
      </div>
      <div class="card mb-3">
        <div class="card-body small">
          <?php if (!empty($validation['tables_present'])): ?>
            <p class="mb-2"><strong>Present (<?= count($validation['tables_present']) ?>):</strong></p>
            <ul class="mb-2">
              <?php foreach ($validation['tables_present'] as $table): ?>
                <li><?= htmlspecialchars($table) ?>
                  <?php if (isset($validation['row_counts'][$table])): ?>
                    <span class="badge bg-info"><?= $validation['row_counts'][$table] ?> rows</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if (!empty($validation['tables_missing'])): ?>
            <p class="mb-2"><strong class="text-danger">Missing (<?= count($validation['tables_missing']) ?>):</strong></p>
            <ul class="mb-0">
              <?php foreach ($validation['tables_missing'] as $table): ?>
                <li><span class="text-danger"><?= htmlspecialchars($table) ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-sitemap"></i> Feature Types and Counts</h6>
      <div class="alert alert-info small mb-3">
        <strong>Summary:</strong> Shows the count of each feature type in the database.
      </div>
      <div class="card mb-3">
        <div class="card-body small">
          <?php if (!empty($validation['feature_counts'])): ?>
            <p class="mb-2"><strong>Features (<?= count($validation['feature_counts']) ?>):</strong></p>
            <ul class="mb-0">
              <?php foreach ($validation['feature_counts'] as $feature_type => $count): ?>
                <li><?= htmlspecialchars($feature_type) ?>
                  <span class="badge bg-info"><?= number_format($count) ?> features</span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="mb-0 text-muted"><i class="fa fa-info-circle"></i> No feature data available</p>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-exclamation-triangle"></i> Data Quality</h6>
      <div class="alert alert-info small mb-3">
        <strong>Check:</strong> Database records should have valid relationships and complete data. This checks for orphaned annotations, missing accessions, and features without proper organism links.
      </div>
      <div class="card mb-3 <?= empty($validation['data_issues']) ? 'border-success' : 'border-danger border-2' ?>">
        <div class="card-body small">
          <?php if (empty($validation['data_issues'])): ?>
            <p class="mb-0"><span class="badge bg-success"><i class="fa fa-check"></i></span> No data quality issues found</p>
          <?php else: ?>
            <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Issues Found:</strong></p>
            <ul class="mb-0">
              <?php foreach ($validation['data_issues'] as $issue): ?>
                <li class="mb-2">
                  <span class="text-danger"><?= htmlspecialchars($issue) ?></span>
                  <br>
                  <small class="text-muted">
                    <?php
                      if (strpos($issue, 'Orphaned annotations') !== false) {
                        echo 'Annotations exist in the database but are not linked to any annotation source. These records cannot be properly accessed.';
                      } elseif (strpos($issue, 'missing accession') !== false) {
                        echo 'An accession is a unique identifier (like a UniProt ID or NCBI accession number). Annotations should have accession values for proper identification and linking to external databases. Missing accessions prevent proper data cross-referencing.';
                      } elseif (strpos($issue, 'Features without organism') !== false) {
                        echo 'Features (genes, proteins, etc.) exist in the database but are not properly linked to an organism record. They cannot be associated with the correct biological entity.';
                      }
                    ?>
                  </small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($assembly_validation): ?>
        <h6 class="fw-bold mb-2"><i class="fa fa-folder"></i> Assembly Validation</h6>
        <div class="alert alert-info small mb-3">
          <strong>Required:</strong> For each genome record in the database, at least one directory must exist in the organism folder with a name matching either the <code>genome_name</code> or <code>genome_accession</code> value from the database.
        </div>
        <div class="card mb-3 <?= $assembly_validation['valid'] ? 'border-success' : 'border-danger border-2' ?>">
          <div class="card-body small">
            <?php if ($assembly_validation['valid'] && empty($assembly_validation['mismatches'])): ?>
              <p class="mb-0"><span class="badge bg-success"><i class="fa fa-check"></i></span> All assembly directories match database records</p>
            <?php else: ?>
              <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Assembly Issues Found:</strong></p>
              <p class="mb-2 text-danger"><small>One or more genome records in the database do not have corresponding directories in the organism folder.</small></p>
              <ul class="mb-0">
                <?php if (!empty($assembly_validation['mismatches'])): ?>
                  <?php foreach ($assembly_validation['mismatches'] as $mismatch): ?>
                    <li class="mb-2">
                      <span class="text-danger"><strong><?= htmlspecialchars($mismatch['type'] === 'missing_directory' ? 'Missing Directory' : 'Name Mismatch') ?>:</strong></span>
                      <br>
                      <?php if ($mismatch['type'] === 'missing_directory'): ?>
                        <small class="text-muted">No directory found matching genome_name "<?= htmlspecialchars($mismatch['genome_name']) ?>" or genome_accession "<?= htmlspecialchars($mismatch['genome_accession']) ?>".</small>
                      <?php else: ?>
                        <small class="text-muted">Directory "<?= htmlspecialchars($mismatch['found_directory']) ?>" doesn't match genome_name "<?= htmlspecialchars($mismatch['genome_name']) ?>" or genome_accession "<?= htmlspecialchars($mismatch['genome_accession']) ?>".</small>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!$validation['readable']): ?>
        <div class="card border-warning">
          <div class="card-header bg-warning bg-opacity-25">
            <h6 class="mb-0"><i class="fa fa-wrench"></i> Fix Permissions</h6>
          </div>
          <div class="card-body small">
            <p class="mb-2">The database file is not readable by the web server. Click the button below to attempt an automatic fix.</p>
            <button class="btn btn-warning btn-sm" onclick="fixDatabasePermissions(event, '<?= $org_safe ?>')">
              <i class="fa fa-wrench"></i> Fix Permissions
            </button>
            <div id="fixResult<?= $org_safe ?>" class="mt-3 d-none"></div>
          </div>
        </div>
      <?php endif; ?>

    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
</div>
    <?php
}

// ---------------------------------------------------------------------------

function render_metadata_modal($organism, $data, $organism_data) {
    $json_val = $data['json_validation'];
    $org_safe = htmlspecialchars($organism);
    ?>
<div class="modal-dialog modal-lg">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="fa fa-file-code"></i> Organism Metadata: <?= $org_safe ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">

      <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Validation Status</h6>
      <div class="card mb-3">
        <div class="card-body">
          <?php if ($json_val['exists'] && $json_val['readable'] && $json_val['valid_json'] && $json_val['has_required_fields']): ?>
            <span class="badge bg-success h6"><i class="fa fa-check-circle"></i> Metadata is Complete</span>
          <?php elseif (!$json_val['exists']): ?>
            <span class="badge bg-danger h6"><i class="fa fa-times-circle"></i> Metadata File Missing</span>
            <p class="mt-2 mb-0 text-muted small">The organism.json file does not exist. Click "Create Metadata File" below to create one.</p>
          <?php else: ?>
            <span class="badge bg-warning h6"><i class="fa fa-exclamation-triangle"></i> Metadata has Issues</span>
            <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below.</p>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> File Status</h6>
      <div class="card mb-3">
        <div class="card-body small">
          <p class="mb-2">
            <?php if ($json_val['exists']): ?>
              <strong>Exists:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
            <?php else: ?>
              <strong>Exists:</strong> <span class="badge bg-danger"><i class="fa fa-times"></i> No</span>
            <?php endif; ?>
          </p>
          <p class="mb-2">
            <?php if ($json_val['readable']): ?>
              <strong>Readable:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
            <?php elseif ($json_val['exists']): ?>
              <strong>Readable:</strong> <span class="badge bg-danger"><i class="fa fa-times"></i> No (Permission denied)</span>
            <?php endif; ?>
          </p>
          <p class="mb-2">
            <?php if ($json_val['readable']): ?>
              <?php if ($json_val['writable']): ?>
                <strong>Writable:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
              <?php else: ?>
                <strong>Writable:</strong> <span class="badge bg-warning"><i class="fa fa-lock"></i> No (Read-only)</span>
              <?php endif; ?>
            <?php endif; ?>
          </p>
          <p class="mb-0">
            <?php if ($json_val['valid_json']): ?>
              <strong>JSON Valid:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
            <?php elseif ($json_val['readable']): ?>
              <strong>JSON Valid:</strong> <span class="badge bg-danger"><i class="fa fa-times"></i> No (Invalid JSON)</span>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <?php echo generatePermissionAlert(
          $data['path'] . '/organism.json',
          'Metadata File Permission Issue',
          'The organism.json file has permission issues.',
          'file',
          $organism
      ); ?>

      <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Required Fields</h6>
      <div class="alert alert-info small mb-3">
        <strong>Required:</strong> All fields must be present and non-empty: genus, species, common_name, taxon_id
      </div>
      <div class="card mb-3">
        <div class="card-body small">
          <?php if (!empty($json_val['errors'])): ?>
            <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Errors:</strong></p>
            <ul class="mb-0">
              <?php foreach ($json_val['errors'] as $error): ?>
                <li class="text-danger"><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <ul class="mb-0">
              <?php foreach ($json_val['required_fields'] as $field): ?>
                <li class="mb-1">
                  <span class="badge bg-success"><i class="fa fa-check"></i></span> <strong><?= htmlspecialchars($field) ?></strong>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-edit"></i> Metadata Editor</h6>
      <form id="metadataForm<?= $org_safe ?>" class="metadata-form">
        <input type="hidden" name="organism" value="<?= $org_safe ?>">
        <input type="hidden" name="images_json" id="images-json-<?= $org_safe ?>">
        <input type="hidden" name="html_p_json" id="html-p-json-<?= $org_safe ?>">

        <div class="mb-3">
          <label for="genus<?= $org_safe ?>" class="form-label">Genus <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="genus<?= $org_safe ?>" name="genus"
                 value="<?= htmlspecialchars($data['info']['genus'] ?? '') ?>" required>
          <small class="text-muted">e.g., Anoura</small>
        </div>

        <div class="mb-3">
          <label for="species<?= $org_safe ?>" class="form-label">Species <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="species<?= $org_safe ?>" name="species"
                 value="<?= htmlspecialchars($data['info']['species'] ?? '') ?>" required>
          <small class="text-muted">e.g., caudifer</small>
        </div>

        <div class="mb-3">
          <label for="common_name<?= $org_safe ?>" class="form-label">Common Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="common_name<?= $org_safe ?>" name="common_name"
                 value="<?= htmlspecialchars($data['info']['common_name'] ?? '') ?>" required>
          <small class="text-muted">e.g., Tailed Tailless Bat</small>
        </div>

        <div class="mb-3">
          <label for="taxon_id<?= $org_safe ?>" class="form-label">Taxon ID <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="taxon_id<?= $org_safe ?>" name="taxon_id"
                 value="<?= htmlspecialchars($data['info']['taxon_id'] ?? '') ?>" required>
          <small class="text-muted">NCBI taxonomy ID, e.g., 27642</small>
        </div>

        <hr class="my-4">

        <h5 class="mb-3"><i class="fa fa-sitemap"></i> Feature Types</h5>
        <div class="alert alert-info small mb-3">
          <strong>Note:</strong> Define which feature types are parents (typically genes) and which are children (transcripts, proteins, etc.)
        </div>

        <input type="hidden" name="parents_json" id="parents-json-<?= $org_safe ?>">
        <input type="hidden" name="children_json" id="children-json-<?= $org_safe ?>">

        <div class="row">
          <div class="col-md-6">
            <label class="form-label">Parent Features</label>
            <div id="parents-<?= $org_safe ?>" class="feature-tag-container" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; min-height: 50px; background: #f8f9fa;">
              <?php
                $parents = $data['info']['feature_types']['parents'] ?? ['gene'];
                foreach ($parents as $feature):
              ?>
                <span class="badge bg-primary me-2 mb-2 feature-tag" data-feature="<?= htmlspecialchars($feature) ?>">
                  <?= htmlspecialchars($feature) ?> <i class="fa fa-times" style="cursor: pointer;" onclick="removeFeatureTag(this, '<?= $org_safe ?>')"></i>
                </span>
              <?php endforeach; ?>
            </div>
            <input type="text" class="form-control mt-2" id="parent-feature-input-<?= $org_safe ?>" placeholder="e.g., gene, pseudogene">
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addFeatureTag('<?= $org_safe ?>', 'parent')">
              <i class="fa fa-plus"></i> Add Parent Feature
            </button>
          </div>
          <div class="col-md-6">
            <label class="form-label">Child Features</label>
            <div id="children-<?= $org_safe ?>" class="feature-tag-container" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; min-height: 50px; background: #f8f9fa;">
              <?php
                $children = $data['info']['feature_types']['children'] ?? ['mRNA', 'transcript'];
                foreach ($children as $feature):
              ?>
                <span class="badge bg-info me-2 mb-2 feature-tag" data-feature="<?= htmlspecialchars($feature) ?>">
                  <?= htmlspecialchars($feature) ?> <i class="fa fa-times" style="cursor: pointer;" onclick="removeFeatureTag(this, '<?= $org_safe ?>')"></i>
                </span>
              <?php endforeach; ?>
            </div>
            <input type="text" class="form-control mt-2" id="child-feature-input-<?= $org_safe ?>" placeholder="e.g., mRNA, transcript, protein">
            <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="addFeatureTag('<?= $org_safe ?>', 'child')">
              <i class="fa fa-plus"></i> Add Child Feature
            </button>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3"><i class="fa fa-image"></i> Images</h5>
        <div class="alert alert-info small mb-3">
          <strong>Note:</strong> If no images are provided here, the image from
          <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($data['info']['taxon_id'] ?? '') ?>/" target="_blank">
            NCBI Taxonomy (ID: <?= htmlspecialchars($data['info']['taxon_id'] ?? '[taxon_id]') ?>)
          </a>
          will be used as the default.
        </div>
        <div id="images-container-<?= $org_safe ?>">
          <?php
            $images = $data['info']['images'] ?? [['file' => '', 'caption' => '']];
            foreach ($images as $idx => $image):
          ?>
            <div class="image-item" data-index="<?= $idx ?>" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
              <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeMetadataImage('<?= $org_safe ?>', <?= $idx ?>)" style="float: right;">Remove</button>
              <div class="form-group mb-3">
                <label>Image File</label>
                <div class="input-group">
                  <input type="text" class="form-control image-file" value="<?= htmlspecialchars($image['file'] ?? '') ?>" placeholder="e.g., organism_image.jpg">
                  <button type="button" class="btn btn-outline-secondary upload-image-btn">Upload</button>
                </div>
                <input type="file" class="image-upload-input" style="display:none;" accept="image/*">
                <small class="form-text text-muted">Or upload a photo directly</small>
              </div>
              <div class="form-group">
                <label>Caption (HTML allowed)</label>
                <textarea class="form-control image-caption" rows="2"><?= htmlspecialchars($image['caption'] ?? '') ?></textarea>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-primary mb-4" onclick="addMetadataImage('<?= $org_safe ?>')">
          <i class="fa fa-plus"></i> Add Image
        </button>

        <h5 class="mb-3"><i class="fa fa-paragraph"></i> HTML Paragraphs</h5>
        <div id="paragraphs-container-<?= $org_safe ?>">
          <?php
            $paragraphs = $data['info']['html_p'] ?? [['text' => '', 'style' => '', 'class' => '']];
            foreach ($paragraphs as $idx => $para):
          ?>
            <div class="paragraph-item" data-index="<?= $idx ?>" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
              <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeMetadataParagraph('<?= $org_safe ?>', <?= $idx ?>)" style="float: right;">Remove</button>
              <div class="form-group mb-3">
                <label>Text (HTML allowed)</label>
                <textarea class="form-control para-text" rows="4"><?= htmlspecialchars($para['text'] ?? '') ?></textarea>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>CSS Style</label>
                    <input type="text" class="form-control para-style" value="<?= htmlspecialchars($para['style'] ?? '') ?>" placeholder="e.g., color: red;">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>CSS Class</label>
                    <input type="text" class="form-control para-class" value="<?= htmlspecialchars($para['class'] ?? '') ?>" placeholder="e.g., lead">
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-primary mb-4" onclick="addMetadataParagraph('<?= $org_safe ?>')">
          <i class="fa fa-plus"></i> Add Paragraph
        </button>

        <div id="saveResult<?= $org_safe ?>"></div>

        <button type="button" class="btn btn-success" onclick="saveMetadata(event, '<?= $org_safe ?>')">
          <i class="fa fa-save"></i> Save Metadata
        </button>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
</div>
    <?php
}

// ---------------------------------------------------------------------------

function render_asm_modal($organism, $assembly, $data, $sequence_types, $groups_data, $organism_data) {
    $safe_asm_id         = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism . '_' . $assembly);
    $asm_fasta           = $data['fasta_validation']['assemblies'][$assembly] ?? null;
    $is_missing          = isset($data['fasta_validation']['missing_files'][$assembly]);
    $assembly_validation = $data['assembly_validation'];
    $assembly_path       = $data['path'] . '/' . $assembly;

    $blast_validation = $data['blast_validation'][$assembly] ?? validateBlastIndexFiles($assembly_path, $sequence_types);

    $genome_fa_path = $assembly_path . '/genome.fa';
    $fai_info = $data['fai_validation'][$assembly] ?? [
        'genome_fa_exists' => file_exists($genome_fa_path),
        'fai_exists'       => file_exists($genome_fa_path . '.fai'),
    ];

    $assembly_groups = getAssemblyGroups($organism, $assembly, $groups_data);

    $modal_has_missing_blast = false;
    if (!empty($blast_validation['databases'])) {
        foreach ($blast_validation['databases'] as $db) {
            if (!$db['has_indexes']) { $modal_has_missing_blast = true; break; }
        }
    }
    $modal_has_missing_fai = ($fai_info['genome_fa_exists'] && !$fai_info['fai_exists']);

    $has_db_mismatch      = false;
    $db_mismatch_messages = [];
    $matching_genome      = null;

    if ($assembly_validation) {
        foreach ($assembly_validation['genomes'] as $genome) {
            if ($assembly === $genome['genome_name'] || $assembly === $genome['genome_accession']) {
                $matching_genome = $genome;
                break;
            }
        }
        if (!$matching_genome) {
            $has_db_mismatch      = true;
            $db_mismatch_messages[] = "Assembly directory '$assembly' does not match any genome_name or genome_accession in the database";
        }
    }
    ?>
<div class="modal-dialog modal-lg">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="fa fa-folder"></i> Assembly: <?= htmlspecialchars($assembly) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">

      <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Overall Status</h6>
      <div class="card mb-3">
        <div class="card-body">
          <?php if (!$has_db_mismatch && !$is_missing && !$modal_has_missing_blast && !$modal_has_missing_fai): ?>
            <span class="badge bg-success h6"><i class="fa fa-check-circle"></i> Assembly is Complete</span>
          <?php else: ?>
            <span class="badge bg-danger h6"><i class="fa fa-times-circle"></i> Assembly has Issues</span>
            <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below.</p>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> Assembly Information</h6>
      <div class="card mb-3">
        <div class="card-body small">
          <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($assembly) ?></p>
          <p class="mb-1"><strong>Organism:</strong> <?= htmlspecialchars($organism) ?></p>
          <p class="mb-0"><strong>Path:</strong> <?= htmlspecialchars($data['path'] . '/' . $assembly) ?></p>
        </div>
      </div>

      <?php if ($assembly_validation): ?>
        <h6 class="fw-bold mb-2"><i class="fa fa-database"></i> Database Directory Matching</h6>
        <div class="alert alert-info small mb-3">
          <strong>Required:</strong> Assembly directory name must match either the <code>genome_name</code> or <code>genome_accession</code> from the database.
        </div>
        <div class="card mb-3 <?= $has_db_mismatch ? 'border-danger border-2' : 'border-success' ?>">
          <div class="card-body small">
            <?php
              if ($matching_genome) {
                echo '<p class="mb-2"><strong>The assembly directory name "' . htmlspecialchars($assembly) . '" matches:</strong></p>';
                echo '<ul class="mb-0">';
                if ($assembly === $matching_genome['genome_name']) {
                  echo '  <li><span class="badge bg-success"><i class="fa fa-check"></i></span> DB genome_name: ' . htmlspecialchars($matching_genome['genome_name']) . '</li>';
                  echo '  <li>DB genome_accession: ' . htmlspecialchars($matching_genome['genome_accession']) . '</li>';
                } else {
                  echo '  <li>DB genome_name: ' . htmlspecialchars($matching_genome['genome_name']) . '</li>';
                  echo '  <li><span class="badge bg-success"><i class="fa fa-check"></i></span> DB genome_accession: ' . htmlspecialchars($matching_genome['genome_accession']) . '</li>';
                }
                echo '</ul>';
              } else {
                echo '<p class="text-danger"><i class="fa fa-exclamation-circle"></i> No matching genome record found in database.</p>';
                if (!empty($db_mismatch_messages)) {
                  echo '<p class="mb-0"><small class="text-muted">' . implode('<br>', array_map('htmlspecialchars', $db_mismatch_messages)) . '</small></p>';
                }
              }
            ?>
          </div>
        </div>

        <?php if ($has_db_mismatch): ?>
          <h6 class="fw-bold mb-2"><i class="fa fa-tools"></i> Rename Assembly Directory</h6>
          <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-25">
              <h6 class="mb-0"><i class="fa fa-exclamation-circle"></i> Action Needed: Rename existing directory to match database</h6>
            </div>
            <div class="card-body small">
              <p class="mb-3">If you have an assembly directory with the wrong name, you can rename it to match the database records.</p>
              <div class="row mb-3">
                <div class="col-md-4">
                  <label for="oldDirName<?= htmlspecialchars($safe_asm_id) ?>" class="form-label">Current Directory Name</label>
                  <select class="form-select form-select-sm" id="oldDirName<?= htmlspecialchars($safe_asm_id) ?>">
                    <option value="">-- Select directory to rename --</option>
                    <?php
                      $organism_path = $data['path'];
                      if (is_dir($organism_path)) {
                        $dirs = array_diff(scandir($organism_path), ['.', '..', 'organism.json', basename($data['db_file'] ?? '')]);
                        foreach ($dirs as $dir) {
                          if (is_dir("$organism_path/$dir")) {
                            echo '<option value="' . htmlspecialchars($dir) . '">' . htmlspecialchars($dir) . '</option>';
                          }
                        }
                      }
                    ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="newDirName<?= htmlspecialchars($safe_asm_id) ?>" class="form-label">Rename To</label>
                  <select class="form-select form-select-sm" id="newDirName<?= htmlspecialchars($safe_asm_id) ?>">
                    <option value="">-- Select new name --</option>
                    <?php
                      foreach ($assembly_validation['genomes'] as $genome) {
                        echo '<optgroup label="Genome ' . htmlspecialchars($genome['genome_id']) . '">';
                        if (!empty($genome['genome_name'])) {
                          echo '<option value="' . htmlspecialchars($genome['genome_name']) . '">name: ' . htmlspecialchars($genome['genome_name']) . '</option>';
                        }
                        if (!empty($genome['genome_accession'])) {
                          echo '<option value="' . htmlspecialchars($genome['genome_accession']) . '">accession: ' . htmlspecialchars($genome['genome_accession']) . '</option>';
                        }
                        echo '</optgroup>';
                      }
                    ?>
                  </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <button class="btn btn-info btn-sm w-100" onclick="renameAssemblyDirectory(event, '<?= htmlspecialchars($organism) ?>', '<?= htmlspecialchars($safe_asm_id) ?>')">
                    <i class="fa fa-exchange-alt"></i> Rename
                  </button>
                </div>
              </div>
              <div id="renameResult<?= htmlspecialchars($safe_asm_id) ?>" class="d-none"></div>

              <hr class="my-3">

              <h6 class="fw-bold mb-3"><i class="fa fa-trash-alt"></i> Delete Directory</h6>
              <p class="mb-3 small">If you no longer need this assembly directory, you can delete it permanently. This action cannot be undone.</p>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="dirToDelete<?= htmlspecialchars($safe_asm_id) ?>" class="form-label">Directory to Delete</label>
                  <select class="form-select form-select-sm" id="dirToDelete<?= htmlspecialchars($safe_asm_id) ?>">
                    <option value="">-- Select directory to delete --</option>
                    <?php
                      if (is_dir($organism_path)) {
                        $dirs = array_diff(scandir($organism_path), ['.', '..', 'organism.json', basename($data['db_file'] ?? '')]);
                        foreach ($dirs as $dir) {
                          if (is_dir("$organism_path/$dir")) {
                            echo '<option value="' . htmlspecialchars($dir) . '">' . htmlspecialchars($dir) . '</option>';
                          }
                        }
                      }
                    ?>
                  </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                  <button class="btn btn-danger btn-sm w-100" onclick="deleteAssemblyDirectory(event, '<?= htmlspecialchars($organism) ?>', '<?= htmlspecialchars($safe_asm_id) ?>')">
                    <i class="fa fa-trash-alt"></i> Delete Directory
                  </button>
                </div>
              </div>
              <div id="deleteResult<?= htmlspecialchars($safe_asm_id) ?>" class="d-none"></div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <h6 class="fw-bold mb-2"><i class="fa fa-dna"></i> FASTA Files</h6>
      <div class="alert alert-info small mb-3">
        <strong>Required:</strong> Each assembly directory should contain FASTA files matching the configured sequence type patterns.
      </div>
      <div class="card mb-3 <?= $is_missing ? 'border-danger border-2' : 'border-success' ?>">
        <div class="card-body small">
          <?php if ($asm_fasta): ?>
            <ul class="mb-0">
              <?php foreach ($asm_fasta['fasta_files'] as $type => $file_info): ?>
                <li class="mb-2 pb-2 border-bottom" style="<?= $file_info['found'] ? '' : 'background-color: #fff3cd;' ?>">
                  <?php if ($file_info['found']): ?>
                    <span class="badge bg-success"><i class="fa fa-check"></i></span>
                    <strong><?= htmlspecialchars($type) ?>:</strong>
                    <?= htmlspecialchars($file_info['file']) ?>
                  <?php else: ?>
                    <span class="badge bg-danger"><i class="fa fa-times"></i></span>
                    <strong><?= htmlspecialchars($type) ?>:</strong>
                    <small class="text-muted">Missing pattern: *<?= htmlspecialchars($file_info['pattern']) ?></small>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="alert alert-warning mb-0">No FASTA file information available</div>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-rocket"></i> BLAST Database Indexes</h6>
      <div class="alert alert-info small mb-3">
        <strong>Required:</strong> Each FASTA file needs BLAST index files to be searchable.
      </div>
      <div class="card mb-3 <?= $blast_validation['missing_count'] > 0 ? 'border-warning border-2' : 'border-success' ?>">
        <div class="card-body small">
          <?php if (!empty($blast_validation['databases'])): ?>
            <ul class="mb-0">
              <?php foreach ($blast_validation['databases'] as $db): ?>
                <li class="mb-2 pb-2 border-bottom">
                  <?php if ($db['has_indexes']): ?>
                    <span class="badge bg-success"><i class="fa fa-check"></i></span>
                    <strong><?= htmlspecialchars($db['name']) ?>:</strong> <?= htmlspecialchars($db['fasta']) ?>
                    <br><small class="text-muted">Indexes: ✓ Present</small>
                  <?php else: ?>
                    <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i></span>
                    <strong><?= htmlspecialchars($db['name']) ?>:</strong> <?= htmlspecialchars($db['fasta']) ?>
                    <br><small class="text-danger">Missing indexes: <?= htmlspecialchars(implode(', ', $db['missing_indexes'])) ?></small>
                    <br><small class="text-muted">This FASTA file cannot be used for BLAST searches until indexes are created.</small>
                    <div class="mt-2 p-2 bg-light border rounded small">
                      <strong class="d-block mb-2">To generate BLAST indexes, run on the server:</strong>
                      <?php
                        $assembly_fullpath = $organism_data . '/' . $organism . '/' . $assembly;
                        $fasta_full_path   = $assembly_fullpath . '/' . $db['fasta'];
                        $is_protein        = strpos($db['fasta'], 'protein') !== false;
                        $db_type           = $is_protein ? 'prot' : 'nucl';
                        $cd_cmd            = 'cd ' . htmlspecialchars($assembly_fullpath);
                        $makeblastdb_cmd   = 'makeblastdb -in ' . htmlspecialchars($db['fasta']) . ' -dbtype ' . htmlspecialchars($db_type) . ' -parse_seqids';
                      ?>
                      <code class="d-block" style="word-break: break-all; white-space: normal;">
                        <?= $cd_cmd ?> && \<br><?= $makeblastdb_cmd ?>
                      </code>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="alert alert-warning mb-0">No FASTA files found for BLAST indexing</div>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-dna"></i> Genome FAI Index</h6>
      <div class="alert alert-info small mb-3">
        <strong>Required:</strong> A <code>genome.fa.fai</code> samtools index is needed for the SVG gene model sequence viewer to fetch region sequences.
      </div>
      <?php $fai_border = (!$fai_info['genome_fa_exists'] || $fai_info['fai_exists']) ? 'border-success' : 'border-warning border-2'; ?>
      <div class="card mb-3 <?= $fai_border ?>">
        <div class="card-body small">
          <?php if (!$fai_info['genome_fa_exists']): ?>
            <div class="alert alert-secondary mb-0">
              <i class="fa fa-info-circle"></i> No <code>genome.fa</code> found in this assembly — FAI index not applicable.
            </div>
          <?php elseif ($fai_info['fai_exists']): ?>
            <span class="badge bg-success"><i class="fa fa-check"></i></span>
            <strong>genome.fa.fai:</strong> Present
          <?php else: ?>
            <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i></span>
            <strong>genome.fa.fai:</strong>
            <small class="text-danger"> Missing — SVG sequence viewer will be unavailable for this assembly.</small>
            <div class="mt-2 p-2 bg-light border rounded small">
              <strong class="d-block mb-2">To generate the FAI index, run on the server:</strong>
              <?php
                $fai_asm_path = $organism_data . '/' . $organism . '/' . $assembly;
                $fai_cd_cmd   = 'cd ' . htmlspecialchars($fai_asm_path);
              ?>
              <code class="d-block" style="word-break: break-all; white-space: normal;">
                <?= $fai_cd_cmd ?> && \<br>samtools faidx genome.fa
              </code>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-sitemap"></i> Group Membership</h6>
      <div class="card mb-3">
        <div class="card-body small">
          <?php if (!empty($assembly_groups)): ?>
            <p class="mb-2"><strong>This assembly is in <?= count($assembly_groups) ?> group(s):</strong></p>
            <ul class="mb-3">
              <?php foreach ($assembly_groups as $group): ?>
                <li><span class="badge bg-info"><?= htmlspecialchars($group) ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-muted mb-3"><i class="fa fa-info-circle"></i> This assembly is not currently assigned to any groups.</p>
          <?php endif; ?>
          <a href="manage_groups.php" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="fa fa-edit"></i> Manage Groups
          </a>
        </div>
      </div>

      <div class="alert <?= ($is_missing || $has_db_mismatch || $modal_has_missing_blast || $modal_has_missing_fai) ? 'alert-danger' : 'alert-success' ?>">
        <?php if ($has_db_mismatch || $is_missing || $modal_has_missing_blast || $modal_has_missing_fai): ?>
          <i class="fa fa-exclamation-circle"></i> <strong>Issues Found:</strong>
          <ul class="mb-0 mt-2">
            <?php if ($has_db_mismatch): ?><li>Directory name does not match any genome record in the database</li><?php endif; ?>
            <?php if ($is_missing): ?><li>Missing required FASTA files</li><?php endif; ?>
            <?php if ($modal_has_missing_blast): ?><li>Missing BLAST index files</li><?php endif; ?>
            <?php if ($modal_has_missing_fai): ?><li>Missing <code>genome.fa.fai</code> index (required for SVG sequence viewer)</li><?php endif; ?>
          </ul>
        <?php else: ?>
          <i class="fa fa-check-circle"></i> <strong>Complete:</strong> All checks passed.
        <?php endif; ?>
      </div>

    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-danger" onclick="deleteCurrentAssemblyDirectory(event, '<?= htmlspecialchars($organism) ?>', '<?= htmlspecialchars($safe_asm_id) ?>')">
        <i class="fa fa-trash-alt"></i> Delete Assembly Directory
      </button>
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
</div>
    <?php
}

// ---------------------------------------------------------------------------

function render_status_modal($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types) {
    $status      = getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
    $checks      = $status['checks'];
    $pass_count  = $status['pass_count'];
    ?>
<div class="modal-dialog">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="fa fa-star"></i> Status: <?= htmlspecialchars($organism) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="fa fa-list-check"></i> <strong>Setup Checklist</strong></h6>
            <span class="badge bg-success fs-6"><?= $pass_count ?>/9 Complete</span>
          </div>
          <div class="list-group">
            <?php
              $checklist = [
                  'has_assemblies'    => 'Has assemblies',
                  'has_fasta'         => 'Has FASTA files',
                  'has_blast_indexes' => 'Has BLAST indexes',
                  'has_fai_index'     => ['Has FAI index', '<small class="text-muted">(genome.fa.fai — required for SVG sequence viewer)</small>'],
                  'has_database'      => 'Has database file',
                  'database_readable' => 'Database is readable',
                  'assemblies_in_groups' => 'Assembly in organism groups',
                  'in_taxonomy_tree'  => 'In taxonomy tree',
                  'metadata_complete' => 'Metadata complete',
              ];
              foreach ($checklist as $key => $label):
                  $pass = $checks[$key] ?? false;
                  $labelText = is_array($label) ? $label[0] : $label;
                  $labelExtra = is_array($label) ? $label[1] : '';
            ?>
              <div class="list-group-item <?= $pass ? '' : 'bg-light' ?>">
                <div class="d-flex align-items-center">
                  <?php if ($pass): ?>
                    <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                  <?php else: ?>
                    <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                  <?php endif; ?>
                  <span><strong><?= htmlspecialchars($labelText) ?></strong> <?= $labelExtra ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
</div>
    <?php
}
