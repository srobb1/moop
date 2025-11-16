<?php
/**
 * Navigation Utility Functions
 * Provides centralized back button and navigation rendering across all pages
 * 
 * Usage:
 *   $nav_context = [
 *       'page' => 'organism',
 *       'organism' => $organism_name,
 *       'group' => $group_name,
 *       'parent' => $parent_uniquename
 *   ];
 *   echo render_navigation_buttons($nav_context);
 */

/**
 * Format organism name by replacing underscores with spaces
 */
function formatOrganismName($organism_name) {
    return str_replace('_', ' ', htmlspecialchars($organism_name));
}

/**
 * Render navigation buttons with smart back button logic
 * 
 * @param array $page_context - Context information about current page
 *        Keys: page, organism, group, assembly, parent, genus, species, display_name, organisms
 * @param array $options - Rendering options
 *        Keys: include_home (bool, default true), btn_class (string, default 'btn-secondary')
 * @return string - HTML for navigation buttons
 */
function render_navigation_buttons($page_context = [], $options = []) {
    global $site;
    
    // Set defaults
    $include_home = $options['include_home'] ?? true;
    $btn_class = $options['btn_class'] ?? 'btn-secondary';
    
    // Get page type
    $page_type = $page_context['page'] ?? 'unknown';
    
    $html = '<div class="navigation-buttons-container mb-3">';
    
    switch ($page_type) {
        case 'organism':
            $html .= renderOrganismNav($page_context, $btn_class);
            break;
        case 'group':
            $html .= renderGroupNav($page_context, $btn_class);
            break;
        case 'assembly':
            $html .= renderAssemblyNav($page_context, $btn_class);
            break;
        case 'parent':
            $html .= renderParentNav($page_context, $btn_class);
            break;
        case 'tool':
            $html .= renderToolNav($page_context, $btn_class);
            break;
        case 'admin_tool':
            $html .= renderAdminToolNav($page_context, $btn_class);
            break;
        case 'access_denied':
            $html .= renderAccessDeniedNav($page_context, $btn_class);
            break;
        default:
            $include_home = true;
    }
    
    // Always include home button unless explicitly disabled
    if ($include_home) {
        $html .= sprintf(
            '<a href="/%s/index.php" class="btn %s btn-navigation"><i class="fa fa-home"></i> Home</a>',
            htmlspecialchars($site),
            htmlspecialchars($btn_class)
        );
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render back buttons for organism display page
 */
function renderOrganismNav($context, $btn_class) {
    global $site;
    $html = '';
    
    // Back to group (if came from group)
    if (!empty($context['group'])) {
        $html .= sprintf(
            '<a href="/%s/tools/display/groups_display.php?group=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['group']),
            htmlspecialchars($btn_class),
            htmlspecialchars($context['group'])
        );
    }
    
    // Back to parent (if viewing from parent context)
    if (!empty($context['parent'])) {
        $html .= sprintf(
            '<a href="/%s/tools/display/parent_display.php?organism=%s&uniquename=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['organism']),
            urlencode($context['parent']),
            htmlspecialchars($btn_class),
            htmlspecialchars($context['parent'])
        );
    }
    
    return $html;
}

/**
 * Render back buttons for group display page
 */
function renderGroupNav($context, $btn_class) {
    // Groups typically go to home (no parent page)
    return '';
}

/**
 * Render back buttons for assembly display page
 */
function renderAssemblyNav($context, $btn_class) {
    global $site;
    $html = '';
    
    // Back to parent (if came from parent)
    if (!empty($context['parent'])) {
        $html .= sprintf(
            '<a href="/%s/tools/display/parent_display.php?organism=%s&uniquename=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['organism']),
            urlencode($context['parent']),
            htmlspecialchars($btn_class),
            htmlspecialchars($context['parent'])
        );
    }
    
    // Back to organism (default)
    if (!empty($context['organism'])) {
        $organism_display = formatOrganismName($context['organism']);
        $html .= sprintf(
            '<a href="/%s/tools/display/organism_display.php?organism=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['organism']),
            htmlspecialchars($btn_class),
            $organism_display
        );
    }
    
    return $html;
}

/**
 * Render back buttons for parent/feature display page
 */
function renderParentNav($context, $btn_class) {
    global $site;
    $html = '';
    
    // Build organism display link text
    $organism_text = '';
    if (!empty($context['genus']) && !empty($context['species'])) {
        $organism_text = htmlspecialchars($context['genus'] . ' ' . $context['species']);
    } else {
        $organism_text = 'Organism';
    }
    
    if (!empty($context['organism'])) {
        $html .= sprintf(
            '<a href="/%s/tools/display/organism_display.php?organism=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['organism']),
            htmlspecialchars($btn_class),
            $organism_text
        );
    }
    
    return $html;
}

/**
 * Render back buttons for tool pages
 * Smart hierarchy: assembly -> organism -> group -> home
 */
function renderToolNav($context, $btn_class) {
    global $site;
    $html = '';
    
    // Priority 1: Back to assembly
    if (!empty($context['assembly']) && !empty($context['organism'])) {
        $display_label = $context['display_name'] ?: formatOrganismName($context['assembly']);
        $html .= sprintf(
            '<a href="/%s/tools/display/assembly_display.php?organism=%s&assembly=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['organism']),
            urlencode($context['assembly']),
            htmlspecialchars($btn_class),
            $display_label
        );
        return $html;  // Don't show other options if assembly is available
    }
    
    // Priority 2: Back to organism
    if (!empty($context['organism'])) {
        $display_label = $context['display_name'] ?: formatOrganismName($context['organism']);
        $html .= sprintf(
            '<a href="/%s/tools/display/organism_display.php?organism=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['organism']),
            htmlspecialchars($btn_class),
            $display_label
        );
        return $html;
    }
    
    // Priority 3: Back to group
    if (!empty($context['group'])) {
        $display_label = $context['display_name'] ?: htmlspecialchars($context['group']);
        $html .= sprintf(
            '<a href="/%s/tools/display/groups_display.php?group=%s" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to %s</a>',
            htmlspecialchars($site),
            urlencode($context['group']),
            htmlspecialchars($btn_class),
            $display_label
        );
        return $html;
    }
    
    // Fallback: home is added by default in render_navigation_buttons
    return $html;
}

/**
 * Render back buttons for admin tool pages
 */
function renderAdminToolNav($context, $btn_class) {
    global $site;
    $html = sprintf(
        '<a href="/%s/admin/index.php" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Back to Admin</a>',
        htmlspecialchars($site),
        htmlspecialchars($btn_class)
    );
    return $html;
}

/**
 * Render back buttons for access denied page
 */
function renderAccessDeniedNav($context, $btn_class) {
    // Access denied shows: back button (via JS) and home
    $html = sprintf(
        '<button onclick="javascript:history.back();" class="btn %s btn-navigation"><i class="fa fa-arrow-left"></i> Go Back</button>',
        htmlspecialchars($btn_class)
    );
    return $html;
}
?>
