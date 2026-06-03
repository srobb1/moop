<?php
/**
 * RETRIEVE SEQUENCES - Content File
 * 
 * Variables available (extracted from $data array by render_display_page):
 * - $accessible_sources
 * - $sources_by_group
 * - $context_organism
 * - $context_assembly
 * - $context_group
 * - $filter_organisms
 * - $download_error_msg
 * - $uniquenames
 * - $found_ids
 * - $selected_organism
 * - $selected_assembly_accession
 * - $selected_assembly_name
 * - $uniquenames_string
 * - $displayed_content
 * - $sequence_types
 * - $available_sequences
 * - $selected_source
 * - $sample_feature_ids
 * - $parent_to_children
 * - $site
 */
?>

<div class="container mt-5">

    <!-- Page header -->
    <div class="card shadow-sm mb-4">
      <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#0891b2;">
        <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-dna me-2"></i>Sequence Retrieval</span>
        <button type="button" class="btn btn-link p-0 text-white"
                style="font-size:1rem; opacity:0.85; line-height:1;"
                data-bs-toggle="popover" data-bs-placement="left" data-bs-trigger="focus" data-bs-html="true"
                data-bs-title="How to use Sequence Retrieval"
                data-bs-content="<ol class='mb-0 ps-3'><li>Select an organism and assembly</li><li>Enter gene or feature IDs — one per line or comma-separated.<br><br><strong>Parent IDs are auto-expanded:</strong> entering a gene ID (e.g. <code>g24397</code>) automatically retrieves all its child transcripts (<code>g24397.t1</code>, <code>g24397.t2</code>, …). To target a specific transcript only, enter its ID directly.<br><br><strong>Subsequence ranges:</strong> append a coordinate range to extract just part of a sequence. All four formats are equivalent:<br><code>g24397.t1:1-500</code><br><code>g24397.t1:1..500</code><br><code>g24397.t1 1-500</code><br><code>g24397.t1 1..500</code><br>Note: if you enter ranged child IDs, the parent will not be auto-expanded.</li><li>Click <strong>Retrieve Sequences</strong> — results show all available sequence types (genomic, CDS, protein, flanking) for each ID</li><li>Copy or download individual sequences as needed</li></ol>">
          <i class="fa fa-info-circle"></i>
        </button>
      </div>
      <div class="card-body py-2">
        <p class="text-muted small mb-0">Look up sequences by feature ID across any accessible assembly. Enter gene or transcript IDs and retrieve genomic, CDS, protein, and flanking sequences.</p>
      </div>
    </div>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong>No accessible assemblies found.</strong>
            <p class="mb-0">You do not have access to any organism assemblies, or the data directory is misconfigured.</p>
        </div>
    <?php else: ?>

        <?php if (!empty($download_error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($download_error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="downloadForm">
            <input type="hidden" name="organism" value="<?= htmlspecialchars($selected_organism) ?>">
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($selected_assembly_accession) ?>">
            <input type="hidden" name="gene_set" value="<?= htmlspecialchars($selected_gene_set ?? '') ?>">
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_gene_set" value="<?= htmlspecialchars($context_gene_set ?? '') ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" id="expandedUniqueames" value="<?= htmlspecialchars(json_encode($uniquenames ?? [])) ?>">
            <input type="hidden" id="foundIds" value="<?= htmlspecialchars(json_encode($found_ids ?? [])) ?>">

            <!-- Step 1: Select organism -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
                    <span class="step-badge me-2">1</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Select organism and assembly</span>
                </div>
                <div class="card-body py-3">
                    <?php
                    $clear_filter_function = 'clearSourceFilter';
                    include __DIR__ . '/../../includes/source-list.php';
                    ?>
                    <div class="mt-3 p-3 bg-light border rounded">
                        <strong>Currently selected:</strong>
                        <div id="currentSelection" style="margin-top:8px; font-size:14px;">
                            <span style="color:#999;">None selected</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Enter IDs -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
                    <span class="step-badge me-2">2</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Enter feature or gene IDs</span>
                </div>
                <div class="card-body py-3">
                    <textarea
                        class="form-control moop-input textarea-ids"
                        id="featureIds"
                        name="uniquenames"
                        rows="6"
                        placeholder="Enter feature IDs — one per line or comma-separated.&#10;Coordinate ranges also accepted: ID 1-10, ID:1..10, ID:1-10."
                    ><?= htmlspecialchars($uniquenames_string) ?></textarea>

                    <?php if (!empty($sample_feature_ids)): ?>
                    <div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
                        <small class="text-muted">Examples: <?= htmlspecialchars(implode(', ', array_slice($sample_feature_ids, 0, 2))) ?><?= count($sample_feature_ids) > 2 ? '…' : '' ?></small>
                        <button type="button" class="btn btn-sm fw-semibold text-white ms-auto" style="background-color:#0891b2; border-color:#0891b2;" onclick="loadSampleIds()">
                            <i class="fa fa-bookmark me-1"></i>Load examples
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFeatureIds()">
                            <i class="fa fa-times me-1"></i>Clear
                        </button>
                    </div>
                    <?php endif; ?>

                    <div id="searchIdsDisplay" class="mt-3 p-3 bg-light border rounded" style="display:none;">
                        <small class="text-muted d-block mb-2"><strong>IDs to search:</strong></small>
                        <div id="searchIdsContent"></div>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#idInfoCollapse" aria-expanded="false" aria-controls="idInfoCollapse">
                            <i class="fa fa-info-circle me-1"></i>About parent and child IDs
                        </button>
                        <div class="collapse mt-2" id="idInfoCollapse">
                            <div class="p-3 bg-light border rounded small text-muted">
                                When you enter a parent gene ID (e.g., <code>g24397</code>), the system automatically retrieves all associated child transcript IDs (e.g., <code>g24397.t1</code>, <code>g24397.t2</code>). The "IDs to search" box shows which were found (<span style="background:#d4edda; padding:2px 4px; border-radius:2px;">green</span>) and which were not (<span style="background:#f8d7da; padding:2px 4px; border-radius:2px;">red</span>).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Retrieve -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
                    <span class="step-badge me-2">3</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Retrieve sequences</span>
                </div>
                <div class="card-body py-3">
                    <button type="submit" class="btn btn-lg fw-semibold text-white w-100" style="background-color:#6366f1; border-color:#6366f1;">
                        <i class="fa fa-eye me-1"></i>Retrieve Sequences
                    </button>
                </div>
            </div>

        </form>

        <!-- Sequences Display Section -->
        <?php if (!empty($displayed_content)): ?>
            <hr class="my-5" id="sequences-section">
            <?php
            // For retrieve_sequences.php, we have pre-extracted sequences with ranges
            // Don't set $gene_name to prevent sequences_display.php from re-extracting
            $gene_name = '';
            $organism_name = $selected_organism;
            $assembly_name = $selected_assembly_accession;
            $enable_downloads = true;
            $organism_data = $config->getPath('organism_data');
            
            include_once __DIR__ . '/../sequences_display.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Make sample feature IDs available to JavaScript
<?php if (!empty($sample_feature_ids)): ?>
    window.sampleFeatureIds = <?= json_encode($sample_feature_ids) ?>;
<?php endif; ?>

// Pass found IDs to JavaScript for coloring
window.foundIds = <?= json_encode($found_ids ?? []) ?>;

// Pass parent-to-children mapping for display
window.parentToChildren = <?= json_encode($parent_to_children ?? []) ?>;

// Pass auto-select flag to JavaScript
window.shouldAutoSelect = <?= json_encode($should_auto_select ?? true) ?>;
</script>
