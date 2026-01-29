<?php
/**
 * Tools Section Component
 * Reusable component for displaying available tools on display pages
 * 
 * This component dynamically loads and displays tools based on the provided context.
 * Tools are configured in tool_config.php and filtered based on available context parameters.
 * 
 * DEPENDENCIES: Requires access_control.php to be included first (which loads tool_config.php)
 * 
 * Context Parameters:
 * - organism (string, optional): Single organism context
 * - assembly (string, optional): Assembly/genome context
 * - group (string, optional): Group context
 * - display_name (string, recommended): Human-readable name for display
 * - page (string, required): Page identifier for tool visibility filtering
 *   Valid values: 'index', 'organism', 'assembly', 'group', 'multi_organism_search', 'parent'
 * - use_onclick_handler (bool, optional): If true on index page, tools use onclick buttons
 *   instead of direct links. Required for tools that need JavaScript interaction
 *   (e.g., multi-organism selection). Default: false
 * 
 * Usage Examples:
 * 
 *   1. Single Organism Context:
 *      $context = ['organism' => $organism_name, 'display_name' => $organism_info['common_name'], 'page' => 'organism'];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   2. Index Page with Phylogenetic Tree (needs onclick handlers):
 *      $context = ['display_name' => 'Multi-Organism Search', 'page' => 'index', 'use_onclick_handler' => true];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   3. Multiple Organisms Context (Multi-Organism Search):
 *      $context = ['organisms' => $organisms_array, 'display_name' => 'Multi-Organism Search', 'page' => 'multi_organism_search'];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   4. Group Context:
 *      $context = ['group' => $group_name, 'display_name' => $group_name, 'page' => 'group'];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   5. Assembly Context:
 *      $context = ['organism' => $organism, 'assembly' => $assembly_accession, 'display_name' => $assembly_name, 'page' => 'assembly'];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 *   6. Feature/Parent Context:
 *      $context = ['organism' => $organism, 'assembly' => $genome_accession, 'display_name' => $feature_uniquename, 'page' => 'parent'];
 *      include_once __DIR__ . '/tool_section.php';
 * 
 * How it works:
 * - Calls getAvailableTools($context) to get tools matching the context
 * - Renders tools as either direct links (default) or onclick buttons (if use_onclick_handler=true)
 * - Only displays if tools are available (returns early if empty)
 * - Uses consistent styling: blue header, flex-wrap layout, small buttons
 * - Links are pre-built using buildToolUrl() from tool_config.php
 */

// Ensure dependencies are loaded
if (!function_exists('getAvailableTools')) {
    include_once __DIR__ . '/moop_functions.php';
}

// Get available tools for this context
$tools = getAvailableTools($context ?? []);

// Debug: uncomment to see what's happening
// error_log("DEBUG tool_section: context=" . json_encode($context) . ", tools=" . count($tools));

if (empty($tools)) {
    return; // No tools available, don't render anything
}

// Determine if tools need special handling (e.g., require organism selection)
$use_onclick_handler = $context['page'] === 'index' && !empty($context['use_onclick_handler']);
?>

<!-- Tools Section -->
<div class="card shadow-sm" style="min-height: 200px;">
    <div class="card-header bg-tools text-white">
        <h5 class="mb-0"><i class="fa fa-toolbox"></i> Tools</h5>
    </div>
    <div class="card-body p-2">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($tools as $tool_id => $tool): ?>
                <?php if ($use_onclick_handler): ?>
                    <button 
                       class="btn <?= htmlspecialchars($tool['btn_class']) ?> btn-sm"
                       title="<?= htmlspecialchars($tool['description']) ?>"
                       id="tool-btn-<?= htmlspecialchars($tool_id) ?>"
                       data-tool-id="<?= htmlspecialchars($tool_id) ?>"
                       data-tool-path="<?= htmlspecialchars($tool['url_path']) ?>"
                       data-context-params="<?= htmlspecialchars(json_encode($tool['context_params'])) ?>"
                       onclick="handleToolClick('<?= htmlspecialchars($tool_id) ?>')">
                      <i class="fa <?= htmlspecialchars($tool['icon']) ?>"></i>
                      <span><?= htmlspecialchars($tool['name']) ?></span>
                    </button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($tool['url']) ?>" 
                       target="_blank"
                       class="btn <?= htmlspecialchars($tool['btn_class']) ?> btn-sm"
                       title="<?= htmlspecialchars($tool['description']) ?>">
                        <i class="fa <?= htmlspecialchars($tool['icon']) ?>"></i>
                        <span><?= htmlspecialchars($tool['name']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
