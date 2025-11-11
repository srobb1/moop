<?php
/**
 * Tools Section Component
 * Reusable component for displaying available tools on display pages
 * 
 * This component dynamically loads and displays tools based on the provided context.
 * Tools are configured in tool_config.php and filtered based on available context parameters.
 * 
 * Usage Examples:
 * 
 *   1. Single Organism Context:
 *      $context = ['organism' => $organism_name, 'display_name' => $organism_info['common_name']];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   2. Multiple Organisms Context (Multi-Organism Search):
 *      $context = ['organisms' => $organisms_array, 'display_name' => 'Multi-Organism Search'];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   3. Group Context:
 *      $context = ['group' => $group_name, 'display_name' => $group_name];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   4. Assembly Context:
 *      $context = ['organism' => $organism, 'assembly' => $assembly_accession, 'display_name' => $assembly_name];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   5. Feature/Parent Context:
 *      $context = ['organism' => $organism, 'assembly' => $genome_accession, 'display_name' => $feature_uniquename];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 * How it works:
 * - Calls getAvailableTools($context) to get tools matching the context
 * - Renders tools as direct links with icon and label
 * - Only displays if tools are available (returns early if empty)
 * - Uses consistent styling: blue header, flex-wrap layout, small buttons
 * - Links are pre-built using buildToolUrl() from tool_config.php
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
