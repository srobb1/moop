<?php
/**
 * Tools Section Component
 * Reusable component for displaying available tools on display pages
 * 
 * Usage:
 *   $context = ['organism' => $organism, 'assembly' => $assembly, 'display_name' => $display_name];
 *   include_once __DIR__ . '/tool_section.php';
 */

// Include dependencies if not already included
if (!function_exists('getAvailableTools')) {
    include_once __DIR__ . '/../tool_config.php';
    include_once __DIR__ . '/../moop_functions.php';
}

// Get available tools for this context
$tools = getAvailableTools($context ?? []);

// Debug: uncomment to see what's happening
// error_log("DEBUG tool_section: context=" . json_encode($context) . ", tools=" . count($tools));

if (empty($tools)) {
    return; // No tools available, don't render anything
}
?>

<!-- Tools Section -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fa fa-toolbox"></i> Tools</h5>
    </div>
    <div class="card-body">
        <div class="tools-grid">
            <?php foreach ($tools as $tool_id => $tool): ?>
                <a href="<?= htmlspecialchars($tool['url']) ?>" 
                   class="btn <?= htmlspecialchars($tool['btn_class']) ?> btn-lg tools-btn"
                   title="<?= htmlspecialchars($tool['description']) ?>">
                    <i class="fa <?= htmlspecialchars($tool['icon']) ?>"></i>
                    <span><?= htmlspecialchars($tool['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.tools-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.tools-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    white-space: nowrap;
}

.tools-btn i {
    font-size: 1.1rem;
}
</style>
