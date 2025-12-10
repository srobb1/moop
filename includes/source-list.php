<?php
/**
 * SHARED SOURCE LIST COMPONENT
 * 
 * Renders the FASTA source selector with filtering capability.
 * Used by: retrieve_sequences.php, blast.php
 * 
 * Required variables:
 * - $sources_by_group (array)
 * - $context_organism (string, optional)
 * - $context_assembly (string, optional)
 * - $context_group (string, optional)
 * - $selected_source (string, optional) - "organism|assembly" format
 * - $selected_organism (string, optional)
 * - $selected_assembly_accession (string, optional)
 * - $selected_assembly_name (string, optional) - genome_name for matching
 * - $filter_organisms (array, optional)
 * 
 * Optional parameters:
 * - $clear_filter_function (string) - JavaScript function to call on "Clear" button
 * - $on_change_function (string) - JavaScript function to call on selection change
 */

$group_colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
$group_color_map = [];

// Assign colors to groups consistently
foreach ($sources_by_group as $group_name => $organisms) {
    if (!isset($group_color_map[$group_name])) {
        $group_color_map[$group_name] = $group_colors[count($group_color_map) % count($group_colors)];
    }
}
?>

<div class="fasta-source-selector">
    <label class="form-label"><strong>Select Source</strong></label>
    
    <div class="fasta-source-filter">
        <div class="input-group input-group-sm">
            <input 
                type="text" 
                class="form-control" 
                id="sourceFilter" 
                placeholder="Filter by group, organism, or assembly..."
                value="<?= htmlspecialchars($selected_assembly_name ?: ($selected_organism ?: $context_group)) ?>"
                >
            <button type="button" class="btn btn-success" onclick="<?= $clear_filter_function ?? 'clearSourceFilter' ?>();">
                <i class="fa fa-times"></i> Clear Filters
            </button>
        </div>
    </div>
    
    <div class="fasta-source-list">
        <?php 
        foreach ($sources_by_group as $group_name => $organisms): 
            $group_color = $group_color_map[$group_name];
            
            foreach ($organisms as $organism => $assemblies): 
                foreach ($assemblies as $source): 
                    $search_text = strtolower("$group_name $organism $source[assembly]");
                    
                    // Determine if this source should be hidden (filtered out)
                    $is_filtered_out = false;
                    if (!empty($filter_organisms)) {
                        $is_filtered_out = !in_array($organism, $filter_organisms);
                    }
                    
                    $display_style = $is_filtered_out ? ' style="display: none;"' : '';
                    
                    // Determine if this source should be selected
                    $is_selected = false;
                    if (!empty($selected_source) && $selected_source === ($organism . '|' . $source['assembly'])) {
                        $is_selected = true;
                    } elseif (!empty($selected_organism) && $selected_organism === $organism) {
                        // Match by organism and either accession or name
                        if (!empty($selected_assembly_accession) && $selected_assembly_accession === $source['assembly']) {
                            $is_selected = true;
                        } elseif (!empty($selected_assembly_name) && $selected_assembly_name === $source['genome_name']) {
                            $is_selected = true;
                        }
                    }
                    ?>
                    <div class="fasta-source-line" data-search="<?= htmlspecialchars($search_text) ?>"<?= $display_style ?>>
                        <input 
                            type="radio" 
                            name="selected_source" 
                            value="<?= htmlspecialchars($organism . '|' . $source['assembly']) ?>"
                            data-organism="<?= htmlspecialchars($organism) ?>"
                            data-assembly="<?= htmlspecialchars($source['assembly']) ?>"
                            <?= !empty($on_change_function) ? 'onchange="' . htmlspecialchars($on_change_function) . '();"' : '' ?>
                            <?= $is_selected ? 'checked' : '' ?>
                            >
                        
                        <span class="badge badge-sm bg-<?= $group_color ?> text-white">
                            <?= htmlspecialchars($group_name) ?>
                        </span>
                        <span class="badge badge-sm bg-secondary text-white">
                            <?= htmlspecialchars($organism) ?>
                        </span>
                        <span class="badge badge-sm bg-info text-white">
                            <?= htmlspecialchars($source['assembly']) ?>
                        </span>
                    </div>
                <?php endforeach; 
            endforeach; 
        endforeach; ?>
    </div>
</div>
