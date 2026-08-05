<?php
/**
 * Tools Section Component
 * Reusable component for displaying available tools on display pages
 * 
 * This component dynamically loads and displays tools based on the provided context.
 * Tools are configured in config/tools_config.php (loaded via ConfigManager).
 *
 * DEPENDENCIES: Requires config_init.php to be included first (which loads ConfigManager)
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
 * - Links are pre-built using _build_tool_url() from functions_tools.php
 */

// Ensure dependencies are loaded
if (!function_exists('getAvailableTools')) {
    include_once __DIR__ . '/moop_functions.php';
}

// Get available tools for this context, excluding toolbox=false tools
$tools = array_filter(getAvailableTools($context ?? []), fn($t) => ($t['toolbox'] ?? true) !== false);

// Debug: uncomment to see what's happening
// error_log("DEBUG tool_section: context=" . json_encode($context) . ", tools=" . count($tools));

if (empty($tools)) {
    return; // No tools available, don't render anything
}

// Determine if tools need special handling (e.g., require organism selection)
$use_onclick_handler = !empty($context['use_onclick_handler']);
?>

<!-- Tools Section -->
<div class="card shadow-sm">
    <div class="card-header bg-tools text-white py-2">
        <?php if (($context['page'] ?? '') === 'index'): ?>
            <span class="step-badge me-2">2</span>
            <span class="fw-semibold" style="font-size:0.9rem;">Choose a tool — it runs on your selection</span>
        <?php else: ?>
            <i class="fa fa-toolbox me-2"></i>
            <span class="fw-semibold" style="font-size:0.9rem;">Toolbox</span>
        <?php endif; ?>
        <?php /* The (i) lives in the SHARED component, not on each page that includes it —
                 the toolbox appears on seven pages (index, organism, group, multi-organism,
                 assembly, gene set, gene) and a per-page copy is how help ends up present on
                 some and missing on others. The index variant already explains itself in its
                 header text ("Choose a tool — it runs on your selection"); everywhere else
                 the header just said "Toolbox", so the one thing a user needs to know — that
                 a tool arrives already narrowed to what they are looking at — was never
                 stated. */ ?>
        <?= help_modal_trigger('toolbox-help', '', 'Help: what the Toolbox tools do') ?>
    </div>
    <div class="card-body p-2">
        <?php if ($use_onclick_handler): ?>
            <?php /* The "uh-oh, do this first" reminder. Tools are dimmed until an organism is
                     picked (.tools-locked, toggled by js/index.js); this says WHY, so a new user
                     is not left staring at greyed-out buttons. Shown only when locked, via CSS on
                     the same class — no extra JS. Only the gated index/custom-selection case has
                     it; organism and group pages already carry an organism in context. */ ?>
            <div class="tools-select-hint small mb-2">
                <?php /* No direction and no arrow: this block sits under the selection on a
                         narrow window and beside it on a wide one, so "above" was wrong half
                         the time and the arrow pointed at nothing. */ ?>
                <i class="fa fa-circle-exclamation me-1"></i> Pick an organism first — then these tools run on your selection.
            </div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($tools as $tool_id => $tool): ?>
                <?php if ($use_onclick_handler): ?>
                    <button
                       class="btn <?= htmlspecialchars($tool['btn_class']) ?> btn-sm"
                       title="<?= htmlspecialchars($tool['description']) ?>"
                       id="tool-btn-<?= htmlspecialchars($tool_id) ?>"
                       data-tool-id="<?= htmlspecialchars($tool_id) ?>"
                       data-tool-path="<?= htmlspecialchars($tool['url_path']) ?>"
                       onclick="handleToolClick('<?= htmlspecialchars($tool_id) ?>')">
                      <span><?= htmlspecialchars($tool['name']) ?></span>
                    </button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($tool['url']) ?>" 
                       target="_blank"
                       class="btn <?= htmlspecialchars($tool['btn_class']) ?> btn-sm"
                       title="<?= htmlspecialchars($tool['description']) ?>">
                        <span><?= htmlspecialchars($tool['name']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
// Emitted here rather than by each including page, for the same reason the trigger is here.
// Guarded because a page could legitimately render more than one toolbox (a future
// multi-panel layout), and duplicate modal ids would make the trigger open the wrong one.
if (!defined('MOOP_TOOLBOX_HELP_EMITTED')) {
    define('MOOP_TOOLBOX_HELP_EMITTED', true);
    echo help_modal(
        'toolbox-help',
        'What the Toolbox does',
        [[
            'heading' => '',
            'cards'   => [
                [
                    'label' => 'Tools arrive pre-filled',
                    'text'  => 'Every tool opens already narrowed to whatever you are looking at, '
                             . 'so you do not have to re-pick the organism, assembly or gene.',
                ],
                [
                    'label' => 'From an organism page',
                    'text'  => 'BLAST opens with that organism\'s databases already selected, rather '
                             . 'than the full site-wide list.',
                ],
                [
                    'label' => 'From a gene page',
                    'text'  => 'View in Genome Browser opens JBrowse at that gene\'s location, not at '
                             . 'the start of the assembly. Sequence and download tools act on that feature.',
                ],
                [
                    'label'  => 'Which tools appear',
                    'accent' => true,
                    'text'   => 'The list changes with the page, because a tool only shows where it has '
                              . 'enough context to run — View in Genome Browser needs a location, so it '
                              . 'appears on a gene page and not on a group page.',
                ],
            ],
        ]],
        ['intro' => 'The Toolbox is a shortcut, not a separate place to start: each tool carries your '
                  . 'current organism, assembly or gene across with you.']
    );
}
?>
