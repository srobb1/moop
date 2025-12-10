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
    <div class="mb-3"></div>

    <h2 class="mb-4"><i class="fa fa-dna"></i> Sequence Retrieval & Download</h2>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong>No accessible assemblies found.</strong>
            <p class="mb-0">You do not have access to any organism assemblies, or the data directory is misconfigured.</p>
        </div>
    <?php else: ?>
        <div class="fasta-info-box">
            <strong><i class="fa fa-info-circle"></i> How to use:</strong>
            <ol class="mb-0 mt-2">
                <li>Select which organism and assembly to extract from</li>
                <li>Enter gene/feature IDs (one per line or comma-separated).</li>
                <li>Click "Display Sequences" to see all available sequence types</li>
                <li>Copy or download as needed</li>
            </ol>
        </div>

        <?php if (!empty($download_error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($download_error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <form method="POST" id="downloadForm">
            <input type="hidden" name="organism" value="<?= htmlspecialchars($selected_organism) ?>">
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($selected_assembly_accession) ?>">
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" id="expandedUniqueames" value="<?= htmlspecialchars(json_encode($uniquenames ?? [])) ?>">
            <input type="hidden" id="foundIds" value="<?= htmlspecialchars(json_encode($found_ids ?? [])) ?>">

            <!-- Source Selection -->
            <?php
            $clear_filter_function = 'clearSourceFilter';
            include __DIR__ . '/../../includes/source-list.php';
            ?>

            <!-- Current Selection Display -->
            <div class="mb-4 p-3 bg-light border rounded">
                <strong>Currently Selected:</strong>
                <div id="currentSelection" style="margin-top: 8px; font-size: 14px;">
                    <span style="color: #999;">None selected</span>
                </div>
            </div>

            <!-- Feature ID Input -->
            <div class="mb-4">
                <label for="featureIds" class="form-label"><strong>Feature/Gene IDs</strong></label>
                <textarea 
                    class="form-control textarea-ids" 
                    id="featureIds"
                    name="uniquenames" 
                    rows="6" 
		    placeholder="Enter feature IDs.
One ID per line or comma-separated.
Accepted sequence cooridate ranges formats: ID 1-10, ID:1..10, ID:1-10."
                    ><?= htmlspecialchars($uniquenames_string) ?></textarea>
                <small class="form-text text-muted d-block mt-2">
                    Example IDs: <?= htmlspecialchars(implode(', ', array_slice($sample_feature_ids, 0, 2))) ?><?= count($sample_feature_ids) > 2 ? ' (and ' . (count($sample_feature_ids) - 2) . ' more)' : '' ?>
                </small>
                
                <!-- Sample IDs Help -->
                <?php if (!empty($sample_feature_ids)): ?>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadSampleIds()">
                            <i class="fa fa-bookmark"></i> Load Example IDs
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFeatureIds()">
                            <i class="fa fa-times"></i> Clear
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- IDs to Search Display -->
                <div id="searchIdsDisplay" class="mt-3 p-3 bg-light border rounded" style="display: none;">
                    <small class="text-muted d-block mb-2"><strong>IDs to Search:</strong></small>
                    <div id="searchIdsContent"></div>
                </div>
                
                <!-- Collapsed info about Parent and Child IDs -->
                <div class="mt-3">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#idInfoCollapse" aria-expanded="false" aria-controls="idInfoCollapse">
                        <i class="fa fa-info-circle"></i> About Parent and Child IDs
                    </button>
                    <div class="collapse mt-2" id="idInfoCollapse">
                        <div class="p-3 bg-light border rounded">
                            <p class="mb-0">
                                When you enter a parent gene ID (e.g., <code>g24397</code>), the system looks it up in the organism's database 
                                and automatically retrieves all associated child transcript IDs (e.g., <code>g24397.t1</code>, <code>g24397.t2</code>). 
                                All IDs (parents and children) are then used to search the FASTA sequence database using <code>blastdbcmd</code>. 
                                The "IDs to Search" box shows which ones were found 
                                (<span style="background: #d4edda; padding: 2px 4px; border-radius: 2px;">green</span>) 
                                and which were not found 
                                (<span style="background: #f8d7da; padding: 2px 4px; border-radius: 2px;">red</span>).
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit button -->
            <div class="d-grid gap-2 d-md-flex gap-md-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-eye"></i> Display All Sequences
                </button>
            </div>
            </form>
        </div>

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
