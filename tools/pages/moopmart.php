<?php
/**
 * MOOPmart — MOOP Mega Search Display Page
 * Variables: $scope_tree, $organism_info, $annotation_source_names, $annotation_source_types
 */
?>
<div class="container-fluid py-3">

    <!-- Header -->
    <div class="mb-4">
        <h4 class="mb-1">MOOPmart: Mega Search</h4>
        <p class="text-muted mb-0 small">
            <strong>Bulk download</strong> — export many genes at once as a TSV table or FASTA sequences.
            All filters are optional. Use
            <a href="search.php" class="text-decoration-none">Annotation Search</a>
            if you want to find specific genes by keyword first.
        </p>
    </div>

    <!-- ① Scope -->
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center py-2">
            <span class="step-badge me-2">1</span>
            <strong>Scope</strong>
            <i class="fa fa-info-circle text-muted ms-2" id="mm-scope-info" style="cursor:pointer;"
               data-bs-toggle="popover" data-bs-placement="right" data-bs-html="true"
               data-bs-title="Scope"
               data-bs-content="Select which organisms, assemblies, and gene sets to export from.<br><br>The tree has three levels: <strong>organism → assembly → gene set</strong>. Checking a parent automatically selects all children below it."></i>
            <span id="mm-scope-counts" class="text-muted small fw-normal ms-2 me-auto"></span>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-select-all">All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-clear-all">None</button>
            </div>
        </div>
        <div class="px-2 pt-2 pb-1 border-bottom">
            <input type="text" class="form-control form-control-sm" id="mm-scope-filter"
                   placeholder="Filter organisms, assemblies…" autocomplete="off">
        </div>
        <div class="card-body p-2" style="overflow-y:auto; max-height:280px;">
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
                    <div class="d-flex align-items-center gap-1 px-1 py-1 rounded" style="background:#f8f9fa;">
                        <input type="checkbox" class="form-check-input flex-shrink-0 mm-org-cb mb-0"
                               id="<?= $oid ?>" data-org="<?= htmlspecialchars($organism) ?>">
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
                            <div class="d-flex align-items-center gap-1 px-1 py-1 rounded" style="background:#fff3cd20;">
                                <input type="checkbox" class="form-check-input flex-shrink-0 mm-asm-cb mb-0"
                                       id="<?= $aid ?>"
                                       data-org="<?= htmlspecialchars($organism) ?>"
                                       data-asm="<?= htmlspecialchars($assembly) ?>">
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
                                           data-key="<?= htmlspecialchars($gsKey) ?>">
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
    </div>

    <!-- ② Filters -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center">
            <span class="step-badge me-2">2</span>
            <strong>Filters</strong>
            <small class="text-muted ms-2">— all optional, combined with AND</small>
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
                    <input type="text" id="mm-annotation-keyword" class="form-control form-control-sm" placeholder="search descriptions…">
                </div>
                <div class="col-12 mt-1">
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
                    <input type="number" id="mm-coord-start" class="form-control form-control-sm" placeholder="1" min="1" disabled>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">End <span class="text-muted">(1-based)</span></label>
                    <input type="number" id="mm-coord-end" class="form-control form-control-sm" placeholder="1000000" min="1" disabled>
                </div>
            </div>
        </div>
    </div>

    <!-- ③ Output -->
    <div class="row g-3 mb-3">

        <!-- TSV Output Columns -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center py-2">
                    <span class="step-badge me-2">3</span>
                    <strong>TSV Output Columns</strong>
                    <i class="fa fa-info-circle text-muted ms-2" id="mm-ann-sources-info" style="cursor:pointer;"
                       data-bs-toggle="popover" data-bs-placement="right" data-bs-html="true"
                       data-bs-title="TSV Output Columns"
                       data-bs-content="Select which annotation columns to include in the TSV download.<br><br><strong>Always included:</strong> organism, assembly, gene set, gene ID, name, description, type, chr, start, end, strand.<br><br>Each checked source adds columns for its accession IDs and descriptions."></i>
                    <span id="mm-ann-counts" class="text-muted small fw-normal ms-2 me-auto"></span>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-ann-all">All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="mm-ann-none">None</button>
                    </div>
                </div>
                <?php if (!empty($annotation_source_types)): ?>
                <div class="px-2 pt-2 pb-1 border-bottom">
                    <input type="text" class="form-control form-control-sm" id="mm-ann-filter"
                           placeholder="Filter annotation sources…" autocomplete="off">
                </div>
                <div class="card-body p-2" id="mm-ann-panel" style="overflow-y:auto; max-height:280px;">
                    <p class="small text-muted mb-2">
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
                            <label for="<?= $type_safe ?>" class="form-check-label fw-semibold mb-0 me-auto"
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
            </div>
        </div>

        <!-- Download -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header py-2 d-flex align-items-center">
                    <span class="step-badge me-2">4</span>
                    <strong>Preview &amp; Download</strong>
                    <i class="fa fa-info-circle text-muted ms-2" id="mm-preview-info" style="cursor:pointer;"
                       data-bs-toggle="popover" data-bs-placement="left" data-bs-html="true"
                       data-bs-title="Preview Results"
                       data-bs-content="Preview shows the first 100 matching features so you can verify your settings before downloading. The download exports <strong>all</strong> matching features."></i>
                </div>
                <div class="card-body d-flex flex-column gap-3 py-3">

                    <!-- Preview -->
                    <div>
                        <button type="button" class="btn btn-outline-primary w-100" id="mm-preview-btn">
                            <span id="mm-count-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="fa fa-eye me-1"></i> Preview Results
                        </button>
                        <div id="mm-count-result" class="small text-muted text-center mt-1"></div>
                    </div>

                    <hr class="my-0">

                    <!-- TSV download -->
                    <div>
                        <div class="small fw-semibold text-muted mb-2">Download TSV</div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-tool-emerald btn-sm flex-grow-1" id="mm-dl-tsv">
                                <i class="fa fa-file-alt me-1"></i>Download TSV
                            </button>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="mm-ann-format" id="mm-ann-wide" value="wide" checked>
                                <label class="btn btn-outline-secondary" for="mm-ann-wide" title="One row per gene">Wide</label>
                                <input type="radio" class="btn-check" name="mm-ann-format" id="mm-ann-long" value="long">
                                <label class="btn btn-outline-secondary" for="mm-ann-long" title="One row per annotation term">Long</label>
                            </div>
                            <i class="fa fa-info-circle text-muted" id="mm-ann-format-info" style="cursor:pointer;"
                               data-bs-toggle="popover" data-bs-placement="left" data-bs-html="true"
                               data-bs-title="TSV format"
                               data-bs-content="<strong>Wide</strong> — one row per gene. Multiple annotation IDs for the same source are joined with '; '<br><br><strong>Long</strong> — one row per annotation term. Columns become <em>annotation_source</em>, <em>annotation_id</em>, <em>annotation_description</em>."></i>
                        </div>
                    </div>

                    <hr class="my-0">

                    <!-- FASTA download -->
                    <div>
                        <div class="small fw-semibold text-muted mb-2">Download FASTA</div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="btn-group flex-grow-1">
                                <button type="button" class="btn btn-tool-sky btn-sm" id="mm-dl-fasta-btn">
                                    <i class="fa fa-dna me-1"></i><span id="mm-fasta-label">Genomic</span>
                                </button>
                                <button type="button" class="btn btn-tool-sky btn-sm dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">Select sequence type</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="gene">Genomic</a></li>
                                    <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="transcript">mRNA</a></li>
                                    <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="cds">CDS</a></li>
                                    <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="protein">Protein</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="upstream">Upstream</a></li>
                                    <li><a class="dropdown-item mm-fasta-mode" href="#" data-mode="downstream">Downstream</a></li>
                                </ul>
                            </div>
                            <!-- Flank bp: only shown when upstream or downstream is selected -->
                            <div id="mm-flank-input" class="input-group input-group-sm d-none" style="max-width:130px;">
                                <input type="number" id="mm-flank-bp" class="form-control"
                                       placeholder="bp" min="1" max="100000" title="Flank size in bp">
                                <span class="input-group-text">bp</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- Results preview table -->
    <div id="mm-results-section" class="d-none mt-3">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Preview <small id="mm-results-caption" class="text-muted fw-normal ms-1"></small></span>
                <span class="text-muted small">Gene ID links open the gene page. Download exports the full result set.</span>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table id="mm-results-table" class="table table-sm table-striped table-hover w-100" style="font-size:0.85rem;"></table>
                </div>
            </div>
        </div>
    </div>

</div>
