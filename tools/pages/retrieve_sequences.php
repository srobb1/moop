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
 * - $selected_assembly
 * - $uniquenames_string
 * - $displayed_content
 * - $sequence_types
 * - $selected_source
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
                <li>Enter gene/feature IDs (one per line or comma-separated)</li>
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
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($selected_assembly) ?>">
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" id="expandedUniqueames" value="<?= htmlspecialchars(json_encode($uniquenames ?? [])) ?>">
            <input type="hidden" id="foundIds" value="<?= htmlspecialchars(json_encode($found_ids ?? [])) ?>">

            <!-- Source Selection -->
            <div class="fasta-source-selector">
                <label class="form-label"><strong>Select Source</strong></label>
                
                <div class="fasta-source-filter">
                    <div class="input-group input-group-sm">
                        <input 
                            type="text" 
                            class="form-control" 
                            id="sourceFilter" 
                            placeholder="Filter by group, organism, or assembly..."
                            value="<?= htmlspecialchars($context_organism ?: $context_group) ?>"
                            >
                        <button type="button" class="btn btn-success" onclick="clearSourceFilter();">
                            <i class="fa fa-times"></i> Clear Filters
                        </button>
                    </div>
                </div>
                
                <div class="fasta-source-list">
                    <?php 
                    $group_color_map = assignGroupColors($sources_by_group);
                    
                    foreach ($sources_by_group as $group_name => $organisms): 
                        $group_color = $group_color_map[$group_name];
                        
                        foreach ($organisms as $organism => $assemblies): 
                            foreach ($assemblies as $source): 
                                $search_text = strtolower("$group_name $organism $source[assembly]");
                                $is_filtered_out = !empty($filter_organisms) && !in_array($organism, $filter_organisms);
                                $display_style = $is_filtered_out ? ' style="display: none;"' : '';
                                ?>
                                <div class="fasta-source-line" data-search="<?= htmlspecialchars($search_text) ?>"<?= $display_style ?>>
                                    <input 
                                        type="radio" 
                                        name="selected_source" 
                                        value="<?= htmlspecialchars($source['organism'] . '|' . $source['assembly']) ?>"
                                        data-organism="<?= htmlspecialchars($source['organism']) ?>"
                                        data-assembly="<?= htmlspecialchars($source['assembly']) ?>"
                                        >
                                    
                                    <span class="badge badge-sm bg-<?= $group_color ?> text-white">
                                        <?= htmlspecialchars($group_name) ?>
                                    </span>
                                    <span class="badge badge-sm bg-secondary text-white">
                                        <?= htmlspecialchars($organism) ?>
                                    </span>
                                    <span class="badge badge-sm bg-info text-white">
                                        <?= htmlspecialchars($source['assembly']) ?>
                                    </span>
                                </div>
                            <?php endforeach; 
                        endforeach; 
                    endforeach; ?>
                </div>
            </div>

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
                    placeholder="Enter feature IDs (one per line or comma-separated)"
                    ><?= htmlspecialchars($uniquenames_string) ?></textarea>
                <small class="form-text text-muted">Enter one ID per line, or use commas to separate multiple IDs on one line.</small>
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
            $gene_name = implode(', ', $uniquenames);
            $organism_name = $selected_organism;
            $assembly_name = $selected_assembly;
            $enable_downloads = true;
            $organism_data = $config->getPath('organism_data');
            
            include_once __DIR__ . '/../sequences_display.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    .tooltip { z-index: 9999 !important; }
    .tooltip-inner { background-color: #000 !important; }
    body { position: relative; }
</style>
