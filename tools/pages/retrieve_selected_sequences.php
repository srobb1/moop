<?php
/**
 * RETRIEVE SELECTED SEQUENCES - Content File
 * 
 * Variables available (extracted from $data array by render_display_page):
 * - $organism_name
 * - $assembly_name
 * - $uniquenames
 * - $uniquenames_string
 * - $displayed_content
 * - $sequence_types
 * - $site
 */
?>

<div class="container">
    <div class="mb-4"></div>

    <?php /* A header card like every other tool page, replacing a bare <h2>. This page's
             highest heading was level 2, so the document read as a fragment of something
             else rather than a page: it had no <h1> at all, which is the same audit #9 gap
             the sibling Sequence Retrieval page records fixing. page_title() rather than a
             hand-rolled <h1> so the markup lives in one place -- measured identical.

             NOT added to the page finder, deliberately (decision 2026-09-17). The finder
             answers "which page do I want for this task", and nobody sets out to come here:
             it is a step in a flow that starts on a results table. The purpose sentence is
             for the person standing on the page, which is everyone who reaches it. */ ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
        <?= page_title('Download Selected Sequences', 'fa fa-dna') ?>
          <?= page_purpose_info('Download protein, mRNA, CDS or genomic sequences for a set of features chosen from a results table.') ?>
      </div>
    </div>

    <div class="alert alert-info">
        <strong>Organism:</strong> <em><?= htmlspecialchars($organism_name) ?></em><br>
        <strong>Selected Features:</strong> <span class="badge bg-secondary"><?= count($uniquenames) ?></span>
    </div>

    <div class="mb-4">
        <h5>Selected Feature IDs</h5>
        <div class="selected-ids">
            <?php foreach (array_slice($uniquenames, 0, 10) as $id): ?>
                <span class="badge-custom"><?= htmlspecialchars($id) ?></span>
            <?php endforeach; ?>
            <?php if (count($uniquenames) > 10): ?>
                <span class="badge-custom">+<?= count($uniquenames) - 10 ?> more</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($download_only_types)): ?>
        <?php /* A long selection: one download per type, no sequences drawn (the controller
                 skipped extracting them). Each button posts what the per-type Download buttons
                 below the drawn sequences post, so the server streams that one type. */ ?>
        <div class="card shadow-sm mb-4" id="retrievalSummary">
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Selections of more than <?= (int)$show_max_ids ?> features are available as a download only, so the page stays quick.
                    Every feature is included.
                </p>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-muted me-1"><i class="fa fa-download me-1"></i>Download as FASTA:</span>
                    <?php foreach ($download_only_types as $dl_type): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="organism" value="<?= htmlspecialchars($organism_name) ?>">
                            <input type="hidden" name="assembly" value="<?= htmlspecialchars($assembly_name) ?>">
                            <input type="hidden" name="gene_set" value="<?= htmlspecialchars($gene_set_name) ?>">
                            <input type="hidden" name="uniquenames" value="<?= htmlspecialchars($uniquenames_string) ?>">
                            <input type="hidden" name="sequence_type" value="<?= htmlspecialchars($dl_type) ?>">
                            <input type="hidden" name="download_file" value="1">
                            <button type="submit" class="btn btn-sm fw-semibold text-white btn-moop-teal">
                                <?= htmlspecialchars($sequence_types[$dl_type]['label'] ?? $dl_type) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php elseif (empty($displayed_content)): ?>
        <!-- If no sequences yet, just show simple submit button -->
        <form method="POST">
            <input type="hidden" name="organism" value="<?= htmlspecialchars($organism_name) ?>">
            <input type="hidden" name="uniquenames" value="<?= htmlspecialchars($uniquenames_string) ?>">
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($assembly_name) ?>">
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-eye"></i> Display All Sequences
                </button>
            </div>
        </form>
    <?php else: ?>
        <!-- Sequences Display Section -->
        <hr class="my-4">
        <?php
        // Set up variables for sequences_display.php
        $gene_name = $uniquenames_string;
        $enable_downloads = true;
        
        // Include the reusable sequences display component
        include_once __DIR__ . '/../sequences_display.php';
        ?>
    <?php endif; ?>
</div>

