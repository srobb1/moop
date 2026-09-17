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
      </div>
      <div class="card-body py-2">
        <?= page_purpose('Download protein, mRNA, CDS or genomic sequences for a set of features chosen from a results table.') ?>
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

    <?php if (empty($displayed_content)): ?>
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
        $organism_data = $config->getPath('organism_data');
        
        // Include the reusable sequences display component
        include_once __DIR__ . '/../sequences_display.php';
        ?>
    <?php endif; ?>
</div>

