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
<div class="card shadow-sm h-100">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fa fa-toolbox"></i> Tools</h5>
    </div>
    <div class="card-body p-2">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($tools as $tool_id => $tool): ?>
                <a href="<?= htmlspecialchars($tool['url']) ?>" 
                   class="btn <?= htmlspecialchars($tool['btn_class']) ?> btn-sm"
                   title="<?= htmlspecialchars($tool['description']) ?>">
                    <i class="fa <?= htmlspecialchars($tool['icon']) ?>"></i>
                    <span><?= htmlspecialchars($tool['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
