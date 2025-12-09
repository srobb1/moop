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
