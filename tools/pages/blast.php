<?php
/**
 * BLAST SEARCH - Content File
 * 
 * Variables available (extracted from $data array by render_display_page):
 * - $accessible_sources
 * - $sources_by_group
 * - $context_organism
 * - $context_assembly
 * - $context_group
 * - $search_query
 * - $blast_program
 * - $selected_source
 * - $search_error
 * - $blast_result
 * - $evalue
 * - $max_results
 * - $site
 */
?>

<div class="container mt-5">
    <div class="mb-3"></div>

    <h2 class="mb-4"><i class="fa fa-dna"></i> BLAST Search</h2>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong>No accessible assemblies found.</strong>
            <p class="mb-0">You do not have access to any organism assemblies, or the data directory is misconfigured.</p>
        </div>
    <?php else: ?>
        <div class="fasta-info-box">
            <strong><i class="fa fa-info-circle"></i> How to use:</strong>
            <ol class="mb-0 mt-2">
                <li>Paste a DNA or protein sequence</li>
                <li>Select BLAST program (type determines available databases)</li>
                <li>Select organism and database</li>
                <li>Configure advanced options if needed</li>
                <li>Click Search to run BLAST</li>
            </ol>
        </div>

        <?php if (isset($search_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($search_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="blastForm">
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" name="organism" value="">
            <input type="hidden" name="assembly" value="">

            <!-- Sequence Input -->
            <div class="mb-4">
                <label for="query" class="form-label"><strong>Paste Sequence</strong></label>
                <textarea 
                    id="query" 
                    name="query" 
                    class="form-control fasta-textarea-ids" 
                    rows="8"
                    required
                    placeholder="Enter sequence in FASTA format or plain text"
                ><?= htmlspecialchars($search_query) ?></textarea>
                <small class="form-text text-muted">You can paste FASTA format (with >) or just the raw sequence.</small>
                
                <div class="mt-2 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="loadSampleSequence('protein')">
                        <i class="fa fa-flask"></i> Sample Protein
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="loadSampleSequence('nucleotide')">
                        <i class="fa fa-flask"></i> Sample Nucleotide
                    </button>
                </div>
            </div>

            <!-- BLAST Program Selection -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="blast_program" class="form-label"><strong>BLAST Program</strong></label>
                    <div id="sequenceTypeInfo" class="sequence-type-info" style="display: none;">
                        <small id="sequenceTypeMessage"></small>
                    </div>
                    <select id="blast_program" name="blast_program" class="form-control" onchange="updateDatabaseList();">
                        <option value="blastn" <?= $blast_program === 'blastn' ? 'selected' : '' ?>>BLASTn (DNA vs DNA)</option>
                        <option value="blastp" <?= $blast_program === 'blastp' ? 'selected' : '' ?>>BLASTp (Protein vs Protein)</option>
                        <option value="blastx" <?= $blast_program === 'blastx' ? 'selected' : '' ?>>BLASTx (DNA to Protein)</option>
                        <option value="tblastn" <?= $blast_program === 'tblastn' ? 'selected' : '' ?>>tBLASTn (Protein vs DNA)</option>
                        <option value="tblastx" <?= $blast_program === 'tblastx' ? 'selected' : '' ?>>tBLASTx (DNA vs DNA)</option>
                    </select>
                </div>
            </div>

            <!-- Source Selection -->
            <?php
            $clear_filter_function = 'clearBlastSourceFilters';
            $on_change_function = 'updateDatabaseList';
            include __DIR__ . '/../../includes/source-list.php';
            ?>

            <!-- Current Selection Display -->
            <div class="mb-4 p-3 bg-light border rounded">
                <strong>Currently Selected:</strong>
                <div id="currentSelection" style="margin-top: 8px; font-size: 14px;">
                    <span style="color: #999;">None selected</span>
                </div>
            </div>

            <!-- Database Selection -->
            <div class="mt-4" id="databaseSelector">
                <label class="form-label"><strong>Select Database</strong></label>
                <div id="databaseBadges" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
                    <div style="padding: 15px; text-align: center; color: #666; width: 100%;">
                        <small>Select an assembly first</small>
                    </div>
                </div>
            </div>

            <!-- Advanced Options -->
            <div class="mt-4">
                <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#advOptions" aria-expanded="false" aria-controls="advOptions">
                    <i class="fas fa-sliders-h"></i> <strong>Advanced Options</strong>
                </button>
                
                <div id="advOptions" class="collapse mt-3">
                    <div class="card card-body">
                        <!-- Basic Parameters -->
                        <div class="row">
                            <div class="col-md-6">
                                <label for="evalue" class="form-label"><strong>E-value Threshold</strong></label>
                                <select id="evalue" name="evalue" class="form-select" onchange="toggleEvalueCustom()">
                                    <option value="10" <?= $evalue === '10' ? 'selected' : '' ?>>10</option>
                                    <option value="1" <?= $evalue === '1' ? 'selected' : '' ?>>1</option>
                                    <option value="0.1" <?= $evalue === '0.1' ? 'selected' : '' ?>>0.1</option>
                                    <option value="1e-3" <?= $evalue === '1e-3' ? 'selected' : '' ?>>1e-3 (default)</option>
                                    <option value="1e-6" <?= $evalue === '1e-6' ? 'selected' : '' ?>>1e-6</option>
                                    <option value="1e-9" <?= $evalue === '1e-9' ? 'selected' : '' ?>>1e-9</option>
                                    <option value="1e-12" <?= $evalue === '1e-12' ? 'selected' : '' ?>>1e-12</option>
                                    <option value="custom" <?= !in_array($evalue, ['10', '1', '0.1', '1e-3', '1e-6', '1e-9', '1e-12']) && !empty($evalue) ? 'selected' : '' ?>>Custom</option>
                                </select>
                                <div id="evalue_custom_container" style="display: <?= !in_array($evalue, ['10', '1', '0.1', '1e-3', '1e-6', '1e-9', '1e-12']) && !empty($evalue) ? 'block' : 'none' ?>; margin-top: 8px;">
                                    <input type="text" id="evalue_custom" name="evalue_custom" class="form-control" placeholder="e.g., 1e-15, 0.05" value="<?= !in_array($evalue, ['10', '1', '0.1', '1e-3', '1e-6', '1e-9', '1e-12']) && !empty($evalue) ? htmlspecialchars($evalue) : htmlspecialchars($evalue_custom) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="max_results" class="form-label"><strong>Maximum Hits</strong></label>
                                <input type="number" id="max_results" name="max_results" class="form-control" value="<?= htmlspecialchars($max_results) ?>" min="1">
                                <small class="form-text text-muted">Maximum number of hits to return</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="matrix" class="form-label"><strong>Scoring Matrix</strong></label>
                                <select id="matrix" name="matrix" class="form-select">
                                    <option value="BLOSUM45" <?= isset($blast_options) && $blast_options['matrix'] === 'BLOSUM45' ? 'selected' : '' ?>>BLOSUM45</option>
                                    <option value="BLOSUM62" <?= !isset($blast_options) || (isset($blast_options) && $blast_options['matrix'] === 'BLOSUM62') ? 'selected' : '' ?>>BLOSUM62 (default)</option>
                                    <option value="BLOSUM80" <?= isset($blast_options) && $blast_options['matrix'] === 'BLOSUM80' ? 'selected' : '' ?>>BLOSUM80</option>
                                    <option value="PAM30" <?= isset($blast_options) && $blast_options['matrix'] === 'PAM30' ? 'selected' : '' ?>>PAM30</option>
                                    <option value="PAM70" <?= isset($blast_options) && $blast_options['matrix'] === 'PAM70' ? 'selected' : '' ?>>PAM70</option>
                                    <option value="PAM250" <?= isset($blast_options) && $blast_options['matrix'] === 'PAM250' ? 'selected' : '' ?>>PAM250</option>
                                </select>
                                <small class="form-text text-muted">Only used for protein searches</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="filter_seq" id="filter_seq" <?= (isset($blast_options) && $blast_options['filter']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filter_seq">
                                        Filter low complexity regions
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Advanced Options -->
                        <hr class="my-4">
                        <h6 class="text-muted mb-3">Additional Parameters</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="word_size" class="form-label"><strong>Word Size</strong></label>
                                <input type="number" id="word_size" name="word_size" class="form-control" value="<?= isset($blast_options) && $blast_options['word_size'] ? htmlspecialchars($blast_options['word_size']) : '' ?>" placeholder="Default (program-specific)" min="1">
                                <small class="form-text text-muted">Length of initial exact match (typically 11 for blastn, 3 for blastp)</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="max_hsps" class="form-label"><strong>Max HSPs</strong></label>
                                <input type="number" id="max_hsps" name="max_hsps" class="form-control" value="<?= isset($blast_options) && $blast_options['max_hsps'] ? htmlspecialchars($blast_options['max_hsps']) : '' ?>" placeholder="Unlimited" min="1">
                                <small class="form-text text-muted">Maximum number of HSPs to return per subject</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="perc_identity" class="form-label"><strong>Percent Identity</strong></label>
                                <input type="number" id="perc_identity" name="perc_identity" class="form-control" value="<?= isset($blast_options) && $blast_options['perc_identity'] ? htmlspecialchars($blast_options['perc_identity']) : '' ?>" placeholder="No threshold" min="0" max="100" step="0.1">
                                <small class="form-text text-muted">Minimum percent identity (0-100)</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="culling_limit" class="form-label"><strong>Culling Limit</strong></label>
                                <input type="number" id="culling_limit" name="culling_limit" class="form-control" value="<?= isset($blast_options) && $blast_options['culling_limit'] ? htmlspecialchars($blast_options['culling_limit']) : '' ?>" placeholder="No limit" min="0">
                                <small class="form-text text-muted">Max alignments per subject (0 = unlimited)</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="gapopen" class="form-label"><strong>Gap Open Penalty</strong></label>
                                <input type="number" id="gapopen" name="gapopen" class="form-control" value="<?= isset($blast_options) && $blast_options['gapopen'] ? htmlspecialchars($blast_options['gapopen']) : '' ?>" placeholder="Default" min="1">
                                <small class="form-text text-muted">Cost to open a gap</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="gapextend" class="form-label"><strong>Gap Extend Penalty</strong></label>
                                <input type="number" id="gapextend" name="gapextend" class="form-control" value="<?= isset($blast_options) && $blast_options['gapextend'] ? htmlspecialchars($blast_options['gapextend']) : '' ?>" placeholder="Default" min="1">
                                <small class="form-text text-muted">Cost to extend a gap</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="threshold" class="form-label"><strong>Threshold</strong></label>
                                <input type="number" id="threshold" name="threshold" class="form-control" value="<?= isset($blast_options) && $blast_options['threshold'] ? htmlspecialchars($blast_options['threshold']) : '' ?>" placeholder="Default" step="0.1">
                                <small class="form-text text-muted">Minimum score for extending HSP (protein searches)</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="strand" class="form-label"><strong>Strand (DNA only)</strong></label>
                                <select id="strand" name="strand" class="form-select">
                                    <option value="plus" <?= isset($blast_options) && $blast_options['strand'] === 'plus' ? 'selected' : 'selected' ?>>Plus strand</option>
                                    <option value="minus" <?= isset($blast_options) && $blast_options['strand'] === 'minus' ? 'selected' : '' ?>>Minus strand</option>
                                    <option value="both" <?= isset($blast_options) && $blast_options['strand'] === 'both' ? 'selected' : '' ?>>Both strands</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="soft_masking" id="soft_masking" <?= isset($blast_options) && $blast_options['soft_masking'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="soft_masking">
                                        <strong>Soft Masking</strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-block">Apply soft masking to query and database</small>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ungapped" id="ungapped" <?= isset($blast_options) && $blast_options['ungapped'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ungapped">
                                        <strong>Ungapped</strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-block">Perform ungapped alignment only</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="d-grid gap-2 d-md-flex gap-md-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg" id="searchBtn">
                    <i class="fa fa-search"></i> Search BLAST
                </button>
            </div>
        </form>
        
        <!-- Results Section -->
        <?php if (isset($blast_result) && !empty($blast_result)): ?>
            <div class="card mt-5 shadow-sm" id="blastResultsCard">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fa fa-chart-bar"></i> BLAST Results</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadResultsText();">
                            <i class="fa fa-download"></i> Download Results as TXT
                        </button>
                    </div>
                    
                    <?= getToggleQuerySectionScript() ?>
                    <?= generateCompleteBlastVisualization($blast_result, $search_query, $blast_program, $blast_options ?? []) ?>
                    
                    <?php if (isset($blast_result['pairwise'])): ?>
                        <div id="pairwiseOutput" style="display: none;">
                            <?= htmlspecialchars($blast_result['pairwise']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>
