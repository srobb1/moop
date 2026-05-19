<?php
/**
 * MOOPmart — MOOP Mega Search Display Page
 * Variables: $scope_tree, $organism_info, $annotation_source_names
 */
?>
<div class="container-fluid py-3">

    <div class="mb-3">
        <h4 class="mb-1">MOOP Mega Search</h4>
        <p class="text-muted mb-0 small">Designed for bulk download of selected features and annotations. Filter across organisms, assemblies, and gene sets, then export results as a TSV with annotation columns or as FASTA sequences (genomic regions or pre-built protein, transcript, and CDS sequences).</p>
    </div>

    <div class="row g-3">

        <!-- Scope -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center py-2">
                    <i class="fa fa-sitemap me-2 text-muted"></i>
                    <strong class="me-auto">Scope</strong>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-select-all">All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-clear-all">None</button>
                    </div>
                </div>
                <div class="px-2 pt-2 pb-1 border-bottom">
                    <input type="text" class="form-control form-control-sm" id="mm-scope-filter"
                           placeholder="Filter organisms, assemblies…" autocomplete="off">
                </div>
                <div class="card-body p-2" style="overflow-y:auto; max-height:320px;">
                    <div id="mm-scope-tree">
                    <?php if (empty($scope_tree)): ?>
                        <p class="text-muted small p-2">No accessible sources.</p>
                    <?php else: ?>
                        <?php $oi = 0; foreach ($scope_tree as $organism => $assemblies): $oi++;
                            $info   = $organism_info[$organism] ?? [];
                            $genus  = $info['genus']       ?? '';
                            $sp     = $info['species']     ?? '';
                            $cn     = $info['common_name'] ?? '';
                            $label  = trim("$genus $sp") ?: str_replace('_', ' ', $organism);
                            $oid    = 'mm-o' . $oi;
                        ?>
                        <div class="mm-org mb-1" data-org="<?= htmlspecialchars($organism) ?>">
                            <div class="d-flex align-items-center gap-1 px-1 py-1 rounded"
                                 style="background:#f8f9fa;">
                                <input type="checkbox" class="form-check-input flex-shrink-0 mm-org-cb mb-0"
                                       id="<?= $oid ?>" data-org="<?= htmlspecialchars($organism) ?>" checked>
                                <label class="form-check-label fw-semibold mb-0 me-auto"
                                       for="<?= $oid ?>" style="cursor:pointer; font-size:0.9rem;">
                                    <em><?= htmlspecialchars($label) ?></em>
                                    <?php if ($cn): ?>
                                    <span class="text-muted fw-normal">(<?= htmlspecialchars($cn) ?>)</span>
                                    <?php endif; ?>
                                </label>
                                <i class="fa fa-chevron-down mm-toggle text-muted"
                                   style="cursor:pointer; font-size:0.75rem;"
                                   data-target="<?= $oid ?>-body"></i>
                            </div>
                            <div id="<?= $oid ?>-body" class="ps-3 pt-1">
                                <?php $ai = 0; foreach ($assemblies as $assembly => $gene_sets): $ai++;
                                    $aid = $oid . '_a' . $ai;
                                ?>
                                <div class="mm-asm mb-1" data-org="<?= htmlspecialchars($organism) ?>"
                                     data-asm="<?= htmlspecialchars($assembly) ?>">
                                    <div class="d-flex align-items-center gap-1 px-1 py-1 rounded"
                                         style="background:#fff3cd20;">
                                        <input type="checkbox" class="form-check-input flex-shrink-0 mm-asm-cb mb-0"
                                               id="<?= $aid ?>"
                                               data-org="<?= htmlspecialchars($organism) ?>"
                                               data-asm="<?= htmlspecialchars($assembly) ?>" checked>
                                        <label class="form-check-label fw-semibold mb-0 me-auto"
                                               for="<?= $aid ?>" style="cursor:pointer; font-size:0.85rem; color:#b45309;">
                                            <?= htmlspecialchars($assembly) ?>
                                        </label>
                                    </div>
                                    <div class="ps-3">
                                        <?php $gi = 0; foreach ($gene_sets as $gs): $gi++;
                                            $gsid  = $aid . '_g' . $gi;
                                            $gsKey = "$organism|$assembly|$gs";
                                        ?>
                                        <div class="d-flex align-items-center gap-1 px-1 py-1">
                                            <input type="checkbox" class="form-check-input flex-shrink-0 mm-gs-cb mb-0"
                                                   id="<?= $gsid ?>"
                                                   data-org="<?= htmlspecialchars($organism) ?>"
                                                   data-asm="<?= htmlspecialchars($assembly) ?>"
                                                   data-gs="<?= htmlspecialchars($gs) ?>"
                                                   data-key="<?= htmlspecialchars($gsKey) ?>" checked>
                                            <label class="form-check-label mb-0" for="<?= $gsid ?>"
                                                   style="cursor:pointer; font-size:0.82rem;">
                                                <span class="badge bg-gene-set me-1" style="font-size:0.65rem;">GS</span><?= htmlspecialchars($gs ?: '(default)') ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer py-1 px-2 text-muted" style="font-size:0.8rem;">
                    <span id="mm-scope-summary"></span>
                </div>
            </div>
        </div>

        <!-- Annotation Sources (controls which columns appear in TSV output) -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center py-2">
                    <i class="fa fa-sliders-h me-2 text-muted"></i>
                    <strong class="me-auto">Annotation Sources</strong>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-ann-all">All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-ann-none">None</button>
                    </div>
                </div>
                <?php if (!empty($annotation_source_types)): ?>
                <div class="px-2 pt-2 pb-1 border-bottom">
                    <input type="text" class="form-control form-control-sm" id="mm-ann-filter"
                           placeholder="Filter sources…" autocomplete="off">
                </div>
                <div class="card-body p-2" id="mm-ann-panel" style="overflow-y:auto; max-height:320px;">
                    <p class="small text-muted mb-2">
                        Select which annotation sources appear as columns in TSV downloads.<br>
                        <strong>Always included:</strong> organism, assembly, gene set, gene ID, name, description, type, chr, start, end, strand
                    </p>
                    <?php foreach ($annotation_source_types as $type => $type_data):
                        $type_safe = 'mm-atype-' . preg_replace('/[^a-z0-9]/i', '_', $type);
                        $color     = htmlspecialchars($type_data['color']);
                    ?>
                    <div class="mm-ann-group mb-2">
                        <div class="d-flex align-items-center px-2 py-1 rounded mb-1" style="background:#f1f3f5;">
                            <input type="checkbox" class="form-check-input me-2 mb-0 mm-ann-type-cb flex-shrink-0"
                                   id="<?= $type_safe ?>" data-type="<?= htmlspecialchars($type) ?>">
                            <label for="<?= $type_safe ?>"
                                   class="form-check-label fw-semibold mb-0 me-auto"
                                   style="cursor:pointer; font-size:0.88rem;">
                                <span class="badge bg-<?= $color ?> me-1"><?= htmlspecialchars($type) ?></span>
                            </label>
                        </div>
                        <div class="ps-3">
                            <?php foreach ($type_data['sources'] as $src_name):
                                $safe_id = 'mm-ann-' . preg_replace('/[^a-z0-9]/i', '_', $src_name);
                            ?>
                            <div class="d-flex align-items-center gap-1 px-1 py-1 mm-ann-item">
                                <input type="checkbox" class="form-check-input flex-shrink-0 mm-ann-col mb-0"
                                       id="<?= $safe_id ?>"
                                       value="<?= htmlspecialchars($src_name) ?>"
                                       data-type="<?= htmlspecialchars($type) ?>">
                                <label class="form-check-label mb-0" for="<?= $safe_id ?>"
                                       style="cursor:pointer; font-size:0.82rem;">
                                    <?= htmlspecialchars($src_name) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="card-body p-2 text-muted small">No annotation sources found in the accessible data.</div>
                <?php endif; ?>
                <div class="card-footer py-1 px-2 text-muted" style="font-size:0.8rem;">
                    <span id="mm-ann-summary"></span>
                </div>
            </div>
        </div>

    </div>

    <!-- Filters -->
    <div class="card mt-3">
        <div class="card-header py-2">
            <span class="fw-semibold">Filters</span>
            <small class="text-muted ms-1">— all optional, combined with AND</small>
        </div>
        <div class="card-body pb-2">
            <div class="row g-2">
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Feature ID</label>
                    <input type="text" id="mm-feature-id" class="form-control form-control-sm" placeholder="exact gene ID">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Gene name</label>
                    <input type="text" id="mm-gene-name" class="form-control form-control-sm" placeholder="partial match">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Description keyword</label>
                    <input type="text" id="mm-gene-description" class="form-control form-control-sm" placeholder="search gene descriptions…">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Annotation source</label>
                    <select id="mm-annotation-source" class="form-select form-select-sm">
                        <option value="">Any source</option>
                        <?php foreach ($annotation_source_types as $type => $type_data): ?>
                        <optgroup label="<?= htmlspecialchars($type) ?>">
                            <?php foreach ($type_data['sources'] as $src_name): ?>
                            <option value="<?= htmlspecialchars($src_name) ?>"><?= htmlspecialchars($src_name) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Accession <span class="text-muted">(exact, e.g. GO:0006351)</span></label>
                    <input type="text" id="mm-annotation-accession" class="form-control form-control-sm" placeholder="GO:0000000">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Annotation keyword</label>
                    <input type="text" id="mm-annotation-keyword" class="form-control form-control-sm" placeholder="Search descriptions…">
                </div>
                <div class="col-12">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small fw-semibold text-muted">Coordinates</span>
                        <span id="mm-coord-note" class="small text-muted fst-italic">
                            — only available when a single assembly is selected
                        </span>
                    </div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Chr / scaffold</label>
                    <input type="text" id="mm-coord-chr" class="form-control form-control-sm"
                           placeholder="e.g. CHR01" list="mm-chr-datalist" autocomplete="off" disabled>
                    <datalist id="mm-chr-datalist"></datalist>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Start <span class="text-muted">(1-based)</span></label>
                    <input type="number" id="mm-coord-start" class="form-control form-control-sm"
                           placeholder="1" min="1" disabled>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">End <span class="text-muted">(1-based)</span></label>
                    <input type="number" id="mm-coord-end" class="form-control form-control-sm"
                           placeholder="1000000" min="1" disabled>
                </div>
            </div>
        </div>
    </div>

    <!-- Action bar -->
    <div class="card mt-3">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">

                <button type="button" class="btn btn-outline-primary" id="mm-preview-btn">
                    <span id="mm-count-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    Preview Results
                </button>
                <span id="mm-count-result" class="small text-muted"></span>

                <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">

                    <button type="button" class="btn btn-success" id="mm-dl-tsv">
                        <i class="fa fa-file-alt me-1"></i>Download TSV
                    </button>

                    <!-- FASTA dropdown button group -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" id="mm-dl-fasta-btn">
                            <i class="fa fa-dna me-1"></i><span id="mm-fasta-label">Gene sequence</span>
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Select FASTA type</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Genomic sequences</h6></li>
                            <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="gene">Whole gene body</a></li>
                            <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="upstream">Upstream <span class="mm-flank-label">500</span> bp</a></li>
                            <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="downstream">Downstream <span class="mm-flank-label">500</span> bp</a></li>
                            <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="exons">Exons / sub-features</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Pre-built sequences</h6></li>
                            <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="protein">Protein</a></li>
                            <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="transcript">Transcript (mRNA)</a></li>
                            <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="cds">CDS</a></li>
                        </ul>
                    </div>

                    <!-- Flank bp input: visible when upstream or downstream is selected -->
                    <div id="mm-flank-input" class="input-group input-group-sm d-none" style="max-width:160px;">
                        <span class="input-group-text">bp</span>
                        <input type="number" id="mm-flank-bp" class="form-control"
                               value="500" min="1" max="100000" title="Flank size in bp">
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Results preview table (shown after Preview Results is clicked) -->
    <div id="mm-results-section" class="d-none mt-3">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Preview <small id="mm-results-caption" class="text-muted fw-normal ms-1"></small></span>
                <span class="text-muted small">Gene ID links open the gene page. Download buttons above export the full result set.</span>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table id="mm-results-table" class="table table-sm table-striped table-hover w-100" style="font-size:0.85rem;"></table>
                </div>
            </div>
        </div>
    </div>

</div>
