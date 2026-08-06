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
 * - $selected_source (string, optional) - "organism|assembly|gene_set" format
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
    <label class="form-label">
        <strong>Select Source</strong>
        <?php // Optional, opt-in: the calling page sets $source_list_help_id and renders
              // the matching help_modal(). Kept caller-supplied because what the list
              // MEANS -- and what an unusable source means -- differs per tool.
        if (!empty($source_list_help_id) && function_exists('help_modal_trigger')): ?>
            <?= help_modal_trigger($source_list_help_id, '', 'About this list') ?>
        <?php endif; ?>
    </label>
    
    <div class="fasta-source-filter">
        <div class="input-group input-group-sm">
            <input 
                type="text" 
                class="form-control" 
                id="sourceFilter" 
                placeholder="Filter by group, organism, assembly, or gene set..."
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
                    $genome_name = $source['genome_name'] ?? $source['assembly'];
                    $gene_set_label = $source['gene_set'] ?? '';
                    $search_text = strtolower("$group_name $organism $source[assembly] $genome_name $gene_set_label");
                    
                    // Determine if this source should be hidden (filtered out)
                    $is_filtered_out = false;
                    if (!empty($filter_organisms)) {
                        $is_filtered_out = !in_array($organism, $filter_organisms);
                    }
                    
                    $display_style = $is_filtered_out ? ' style="display: none;"' : '';
                    
                    // Determine if this source should be selected
                    $is_selected = false;
                    $source_value = $organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                    if (!empty($selected_source) && $selected_source === $source_value) {
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
                            value="<?= htmlspecialchars($source_value) ?>"
                            data-organism="<?= htmlspecialchars($organism) ?>"
                            data-assembly="<?= htmlspecialchars($source['assembly']) ?>"
                            data-gene-set="<?= htmlspecialchars($source['gene_set'] ?? '') ?>"
                            <?= !empty($on_change_function) ? 'onchange="' . htmlspecialchars($on_change_function) . '();"' : '' ?>
                            <?= $is_selected ? 'checked' : '' ?>
                            >
                        
                        <span class="badge badge-sm bg-<?= $group_color ?> text-white">
                            <?= htmlspecialchars($group_name) ?>
                        </span>
                        <?php /* The organism is a SCIENTIFIC NAME: sentence case, italic,
                                 and spaces rather than the underscores of the directory
                                 name it comes from. This badge rendered the raw directory
                                 key -- "Ptychodera_flava" -- 95 times per page on both
                                 pages that include this component. */ ?>
                        <span class="badge badge-sm bg-secondary text-white">
                            <em class="sci-name"><?= htmlspecialchars(str_replace('_', ' ', $organism)) ?></em>
                        </span>
                        <span class="badge badge-sm bg-info text-white">
                            <?= htmlspecialchars(assembly_label($source['genome_name'] ?? '', $source['assembly'])) ?>
                        </span>
                        <?php if (!empty($source['gene_set'])): ?>
                        <span class="badge badge-sm bg-light text-dark border">
                            <?= htmlspecialchars($source['gene_set']) ?>
                        </span>
                        <?php endif; ?>
                        <?php
                        // OPTIONAL, opt-in per calling page: $source_availability maps
                        // "organism|assembly|gene_set" => ['ok' => bool, 'reason' => string].
                        //
                        // It must come from the CALLER because "is this source usable" is
                        // a different question per tool -- BLAST wants any database, the
                        // primer tool wants the genome index specifically. A rule baked in
                        // here would be right for one page and wrong for the next.
                        //
                        // Pages that do not set it are unaffected: no marker, no change.
                        // Rendered LAST so it does not push the identifying badges out of
                        // alignment on the rows that carry it.
                        $avail = $source_availability[$source_value] ?? null;
                        if ($avail !== null && empty($avail['ok'])):
                        ?>
                        <span class="badge badge-sm bg-warning text-dark source-unavailable"
                              title="<?= htmlspecialchars($avail['reason'] ?? 'Not available for this tool') ?>"
                              data-bs-toggle="tooltip">!</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; 
            endforeach; 
        endforeach; ?>
    </div>
</div>
