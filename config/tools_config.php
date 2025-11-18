<?php
/**
 * TOOL REGISTRY CONFIGURATION
 * 
 * Defines all available tools in the application.
 * 
 * ⚠️  IMPORTANT FOR NEW ADMINS:
 * - To add a new tool: Add an entry below with unique 'id'
 * - To disable a tool: Comment it out or remove it
 * - To change tool display: Edit name, icon, description, btn_class
 * - 'pages' controls where the tool shows up (omit = show everywhere)
 * 
 * CONTEXT PARAMS: Available values from page context
 *   - organism: Current organism name
 *   - assembly: Current assembly name
 *   - group: Current group name
 *   - display_name: Display name of current item
 *   - organisms: List of selected organisms (for multi-organism pages)
 */

return [
    'download_fasta' => [
        'id'              => 'download_fasta',
        'name'            => 'Retrieve Sequences',
        'icon'            => 'fa-dna',
        'description'     => 'Search and download sequences',
        'btn_class'       => 'btn-success',
        'url_path'        => '/tools/extract/retrieve_sequences.php',
        'context_params'  => ['organism', 'assembly', 'group', 'display_name', 'organisms'],
        'pages'           => 'all',
    ],
    
    'blast_search' => [
        'id'              => 'blast_search',
        'name'            => 'BLAST Search',
        'icon'            => 'fa-dna',
        'description'     => 'Search sequences against databases',
        'btn_class'       => 'btn-warning',
        'url_path'        => '/tools/blast/blast.php',
        'context_params'  => ['organism', 'assembly', 'group', 'display_name', 'organisms'],
        'pages'           => 'all',
    ],
    
    'phylo_search' => [
        'id'              => 'phylo_search',
        'name'            => 'Search Organisms',
        'icon'            => 'fa-search',
        'description'     => 'Search selected organisms',
        'btn_class'       => 'btn-info',
        'url_path'        => '/tools/search/multi_organism_search.php',
        'context_params'  => ['organisms', 'display_name'],
        'pages'           => ['index'],
    ],
    
    // HOW TO ADD A NEW TOOL:
    // 1. Choose a unique 'id' (use snake_case, like 'my_new_tool')
    // 2. Create the tool PHP file in /data/moop/tools/ (or subdirectory)
    // 3. Add entry below:
    // 'my_new_tool' => [
    //     'id'              => 'my_new_tool',
    //     'name'            => 'My New Tool',
    //     'icon'            => 'fa-star',  // See: https://fontawesome.com/icons
    //     'description'     => 'What this tool does',
    //     'btn_class'       => 'btn-primary',  // Bootstrap color class
    //     'url_path'        => '/tools/my_tool.php',
    //     'context_params'  => ['organism'],  // Which URL params to pass
    //     'pages'           => 'all',         // Show everywhere, or specific pages
    // ],
];

?>
