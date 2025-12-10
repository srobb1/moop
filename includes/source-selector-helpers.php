<?php
/**
 * SOURCE SELECTOR HELPERS - Centralized source selection logic
 * 
 * Provides unified source selection logic for tools like retrieve_sequences.php and blast.php
 * Handles filtering by assembly, organism, group, or multiple organisms
 * 
 * Cases:
 * 1. Assembly specified: show assembly name, auto-select that assembly
 * 2. Organism specified: show organism name, auto-select first assembly of that organism
 * 3. Group specified: show group name, DON'T auto-select (let user choose from group)
 * 4. Multiple organisms: show empty filter, restrict list to those organisms only
 */

/**
 * Prepare source selection state for proper filtering and display
 * 
 * This function centralizes all the logic needed to:
 * - Determine what context (group/organism/assembly) is active
 * - Build the filter_organisms list
 * - Decide whether to auto-select or not
 * - Return selected source information for the filter input
 * 
 * @param array $context - Parsed context parameters (['organism', 'assembly', 'group'])
 * @param array $sources_by_group - Nested sources array from getAccessibleAssemblies()
 * @param array $accessible_sources - Flat sources array
 * @param string $selected_organism - Pre-selected organism (from POST/GET)
 * @param string $assembly_param - Pre-selected assembly parameter (from POST/GET)
 * @param array $organisms_param - Multi-organism list (from ?organisms[] parameter)
 * @return array - [
 *     'filter_organisms' => [...],                    // Organisms to show in list
 *     'selected_source' => 'organism|assembly',       // Radio selection value
 *     'selected_organism' => 'organism_name',         // Selected organism
 *     'selected_assembly_accession' => 'assembly',    // Selected assembly accession
 *     'selected_assembly_name' => 'genome_name',      // Selected assembly display name
 *     'should_auto_select' => true/false,             // Whether JS should auto-select
 *     'context_group' => 'group_name'                 // Group name if filtering by group
 * ]
 */
function prepareSourceSelection($context, $sources_by_group, $accessible_sources, $selected_organism = '', $assembly_param = '', $organisms_param = []) {
    // Initialize result with defaults
    $result = [
        'filter_organisms' => [],
        'selected_source' => '',
        'selected_organism' => $selected_organism,
        'selected_assembly_accession' => '',
        'selected_assembly_name' => '',
        'should_auto_select' => true,
        'context_group' => $context['group'] ?? ''
    ];

    // Initialize filter_organisms from multi-organism parameter (if present)
    $filter_organisms = [];
    if (is_array($organisms_param) && !empty($organisms_param)) {
        $filter_organisms = array_filter($organisms_param);
    }

    // Process assembly parameter - match by directory, genome_name, or accession
    $selected_assembly_accession = '';
    $selected_assembly_name = '';
    if (!empty($assembly_param)) {
        foreach ($accessible_sources as $source) {
            if (empty($selected_organism) || $source['organism'] === $selected_organism) {
                // Match by assembly directory, genome_name, or genome_accession
                $matches = false;
                if ($source['assembly'] === $assembly_param) {
                    $matches = true;
                } elseif ($source['genome_name'] === $assembly_param) {
                    $matches = true;
                } elseif ($source['genome_accession'] === $assembly_param) {
                    $matches = true;
                }
                
                if ($matches) {
                    $selected_assembly_accession = $source['assembly'];
                    // Always use genome_name for display (use assembly as fallback)
                    $selected_assembly_name = $source['genome_name'] ?? $source['assembly'];
                    if (empty($selected_organism)) {
                        $selected_organism = $source['organism'];
                    }
                    break;
                }
            }
        }
    }

    // Build filter_organisms list based on priority (assembly > organism > group > multi-organism)
    if (!empty($selected_assembly_name)) {
        // Case 1: Filter by assembly (only show organism containing this assembly)
        $filter_organisms = [];
        foreach ($accessible_sources as $source) {
            if (($source['genome_name'] === $selected_assembly_name || $source['assembly'] === $selected_assembly_accession) && 
                !in_array($source['organism'], $filter_organisms)) {
                $filter_organisms[] = $source['organism'];
            }
        }
    } elseif (!empty($selected_organism)) {
        // Case 2: Filter by organism if no assembly specified
        if (empty($filter_organisms)) {
            $filter_organisms = [$selected_organism];
        }
    } elseif (!empty($context['group'])) {
        // Case 3: Filter by group if only group specified
        $filter_organisms = [];
        if (isset($sources_by_group[$context['group']])) {
            foreach ($sources_by_group[$context['group']] as $organism => $assemblies) {
                $filter_organisms[] = $organism;
            }
        }
        // Don't auto-select when only group is specified - let user choose
        $result['should_auto_select'] = false;
    }
    // else: Case 4 - multi-organism list from ?organisms[] is preserved if no assembly/organism/group specified

    // Determine selected source
    $only_group_specified = !empty($context['group']) && empty($selected_organism) && empty($assembly_param);
    $only_multi_organism_specified = !empty($organisms_param) && empty($selected_organism) && empty($assembly_param) && empty($context['group']);

    if ($only_group_specified) {
        // Case 3: Group specified only - don't auto-select, let user choose from filtered group
        $result['selected_source'] = '';
        $result['selected_organism'] = '';
        $result['selected_assembly_accession'] = '';
        $result['selected_assembly_name'] = '';
        $result['should_auto_select'] = false;
    } elseif ($only_multi_organism_specified) {
        // Case 4: Multiple organisms specified - don't auto-select, let user choose from filtered list
        $result['selected_source'] = '';
        $result['selected_organism'] = '';
        $result['selected_assembly_accession'] = '';
        $result['selected_assembly_name'] = '';
        $result['should_auto_select'] = false;
    } else {
        // Cases 1 & 2: Assembly or single organism specified - use existing logic to auto-select
        $selection_result = determineSelectedSource(
            $context,
            $filter_organisms,
            $accessible_sources,
            $selected_organism,
            $selected_assembly_accession
        );
        $result['selected_source'] = $selection_result['selected_source'];
        $result['selected_organism'] = $selection_result['selected_organism'];
        $result['selected_assembly_accession'] = $selection_result['selected_assembly_accession'];
        
        // Determine which name to use for filter display
        if (!empty($assembly_param)) {
            // Case 1: Assembly specified - always use the assembly_name (genome_name)
            // It was already set on lines 55-76 when we looked up the assembly
            $result['selected_assembly_name'] = $selected_assembly_name;
        } elseif (!empty($selected_organism)) {
            // Case 2: Organism specified without assembly - show organism name
            $result['selected_assembly_name'] = '';
        } else {
            // Fallback: use what determineSelectedSource returned
            $result['selected_assembly_name'] = $selection_result['selected_assembly_name'];
        }
    }

    $result['filter_organisms'] = $filter_organisms;
    return $result;
}

?>
