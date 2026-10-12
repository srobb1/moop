<?php
/**
 * Organism modal: metadata (organism.json). Rendered by admin/api/get_organism_modal.php; the data comes
 * from organism_modal_metadata_data() in lib/organism_modals.php, so this file only displays it.
 */
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

      <h6 class="fw-bold mb-2"><i class="fa fa-clipboard-check"></i> File Status</h6>
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

      <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Required Fields <?= req_info('All fields must be present and non-empty: genus, species, common_name, taxon_id.') ?></h6>
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
        <p class="small text-muted mb-3">Define which feature types are parents (typically genes) and which are children (transcripts, proteins, etc.).</p>

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
        <p class="small text-muted mb-3">
          <strong>Leave this empty and MOOP finds an image itself</strong> — you only need to fill it in
          to override that choice. It tries, in order: a cached
          <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($data['info']['taxon_id'] ?? '') ?>/" target="_blank">NCBI
          Taxonomy (ID: <?= htmlspecialchars($data['info']['taxon_id'] ?? '[taxon_id]') ?>)</a>
          image at <code>images/ncbi_taxonomy/<?= htmlspecialchars($data['info']['taxon_id'] ?? '[taxon_id]') ?>.jpg</code>
          if one is already present, then Wikipedia — downloaded once and cached in
          <code>images/wikimedia/</code>.
        </p>
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
        <p class="small text-muted mb-3">
          <strong>Leave this empty and MOOP writes the description itself</strong> — a summary built from
          the organism's taxonomic lineage (via <code>taxon_id</code>) plus the Wikipedia extract, credited
          with a link back to the article. Add paragraphs here only to replace that text.
        </p>
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
    