<div class="container-fluid py-3">

    <div class="mb-3">
        <h4 class="mb-1">MOOP Mega Search</h4>
        <p class="text-muted mb-0 small">Filter features across organisms, assemblies, and gene sets. Download results as TSV (with annotation columns) or FASTA (genomic regions, pre-built sequences).</p>
    </div>

    <div class="row g-3">

        <!-- 1. Dataset panel -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold">1. Dataset</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="mm-select-all">All</button>
                        <button type="button" class="btn btn-outline-secondary" id="mm-clear-all">None</button>
                    </div>
                </div>
                <div class="card-body p-2" style="max-height:460px; overflow-y:auto;">
                    <div id="mm-scope-tree">
                        <p class="text-muted small p-2">Loading…</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2 + 3. Filters and Attributes -->
        <div class="col-lg-8 d-flex flex-column gap-3">

            <!-- Filters -->
            <div class="card">
                <div class="card-header py-2">
                    <span class="fw-semibold">2. Filters</span>
                    <small class="text-muted ms-1">— all fields are optional and combined with AND</small>
                </div>
                <div class="card-body pb-2">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label small mb-1">Feature type</label>
                            <select id="mm-feature-type" class="form-select form-select-sm">
                                <option value="">All types</option>
                                <option value="gene">gene</option>
                                <option value="pseudogene">pseudogene</option>
                                <option value="mRNA">mRNA</option>
                                <option value="CDS">CDS</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small mb-1">Annotation source</label>
                            <select id="mm-annotation-source" class="form-select form-select-sm">
                                <option value="">Any source</option>
                                <?php foreach ($annotation_source_names as $src_name): ?>
                                <option value="<?= htmlspecialchars($src_name) ?>"><?= htmlspecialchars($src_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small mb-1">Accession <span class="text-muted">(exact, e.g. GO:0006351)</span></label>
                            <input type="text" id="mm-annotation-accession" class="form-control form-control-sm" placeholder="GO:0000000">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small mb-1">Annotation keyword</label>
                            <input type="text" id="mm-annotation-keyword" class="form-control form-control-sm" placeholder="Search descriptions…">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Coordinate range <span class="text-muted">(genes overlapping this region)</span></label>
                            <div class="d-flex gap-2 flex-wrap">
                                <input type="text"   id="mm-coord-chr"   class="form-control form-control-sm" placeholder="Chr / scaffold" style="max-width:160px;">
                                <input type="number" id="mm-coord-start" class="form-control form-control-sm" placeholder="Start (1-based)" min="1">
                                <input type="number" id="mm-coord-end"   class="form-control form-control-sm" placeholder="End (1-based)"   min="1">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attributes -->
            <div class="card">
                <div class="card-header py-2">
                    <span class="fw-semibold">3. Attributes</span>
                    <small class="text-muted ms-1">— choose which annotation sources appear as TSV columns</small>
                </div>
                <div class="card-body pb-2">
                    <p class="small text-muted mb-2">
                        <strong>Always included:</strong> organism, assembly, gene set, gene ID, name, description, type, chr, start, end, strand
                    </p>
                    <?php if (!empty($annotation_source_names)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Annotation sources</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-link btn-sm p-0 me-2" id="mm-ann-all">All</button>
                            <button type="button" class="btn btn-link btn-sm p-0" id="mm-ann-none">None</button>
                        </div>
                    </div>
                    <div id="mm-attribute-sources" class="d-flex flex-wrap gap-x-3 gap-y-1" style="column-gap:1.5rem;">
                        <?php foreach ($annotation_source_names as $src_name):
                            $safe_id = 'mm-ann-' . preg_replace('/[^a-z0-9]/i', '_', $src_name);
                        ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input mm-ann-col" type="checkbox"
                                   value="<?= htmlspecialchars($src_name) ?>"
                                   id="<?= $safe_id ?>" checked>
                            <label class="form-check-label small" for="<?= $safe_id ?>">
                                <?= htmlspecialchars($src_name) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="small text-muted">No annotation sources found in the selected dataset.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Results + Download bar -->
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
