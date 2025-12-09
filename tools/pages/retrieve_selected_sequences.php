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

    <h2 class="mb-4"><i class="fa fa-dna"></i> Download Selected Sequences</h2>

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

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="/<?= $site ?>/js/modules/copy-to-clipboard.js"></script>

<style>
    body { padding: 20px; background-color: #f8f9fa; }
    .container { max-width: 1200px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin: 0 auto; }
    .sequence-option { padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 10px; cursor: pointer; }
    .sequence-option:hover { background-color: #f8f9fa; border-color: #0d6efd; }
    .sequence-option input[type="radio"] { margin-right: 10px; }
    .selected-ids { background-color: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 150px; overflow-y: auto; }
    .badge-custom { display: inline-block; background-color: #0d6efd; color: white; padding: 5px 10px; margin: 3px; border-radius: 3px; font-size: 0.85em; }
    .tooltip { z-index: 9999 !important; }
    .tooltip-inner { background-color: #000 !important; }
    body { position: relative; }
</style>
