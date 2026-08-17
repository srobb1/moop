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
        'btn_class'       => 'btn-tool-emerald',
        'url_path'        => '/tools/retrieve_sequences.php',
        'context_params'  => ['organism', 'assembly', 'gene_set', 'group', 'display_name', 'organisms'],
        'pages'           => 'all',
    ],

    'blast_search' => [
        'id'              => 'blast_search',
        'name'            => 'BLAST Search',
        'icon'            => 'fa-dna',
        'description'     => 'Search sequences against databases',
        'btn_class'       => 'btn-tool-orange',
        'url_path'        => '/tools/blast.php',
        'context_params'  => ['organism', 'assembly', 'gene_set', 'group', 'display_name', 'organisms'],
        'pages'           => 'all',
    ],
    
    'taxonomy_search' => [
        'id'              => 'taxonomy_search',
        'name'            => 'Search Organisms',
        'icon'            => 'fa-search',
        'description'     => 'Search selected organisms',
        'btn_class'       => 'btn-tool-rose',
        'url_path'        => '/tools/multi_organism.php',
        'context_params'  => ['organisms', 'display_name'],
        'pages'           => ['index'],
    ],

    'downloads' => [
        'id'              => 'downloads',
        'name'            => 'Downloads',
        'icon'            => 'fa-download',
        'description'     => 'Browse and download genome files',
        'btn_class'       => 'btn-tool-sky',
        'url_path'        => '/tools/downloads.php',
        'context_params'  => ['organism', 'assembly', 'gene_set', 'group', 'display_name'],
        'pages'           => ['organism', 'assembly', 'gene_set', 'parent', 'group', 'multi_organism_search', 'index'],
    ],

    'genome_browser' => [
        'id'              => 'genome_browser',
        'name'            => 'View in Genome Browser',
        'icon'            => 'fa-dna',
        'description'     => 'Open this feature in JBrowse2 genome browser',
        'btn_class'       => 'btn-tool-teal',
        'url_path'        => '/jbrowse2.php',
        'context_params'  => ['organism', 'assembly', 'loc'],
        'pages'           => ['parent'],
        'target'          => '_blank',
    ],
    
    'annotation_search' => [
        'id'             => 'annotation_search',
        'name'           => 'Annotation Search',
        'icon'           => 'fa-search',
        'description'    => 'Search annotations across organisms, assemblies, and gene sets',
        'btn_class'      => 'btn-tool-amber',
        'url_path'       => '/tools/search.php',
        'context_params' => ['organism', 'assembly', 'gene_set', 'group', 'display_name', 'organisms'],
        'pages'          => 'all',
        'toolbox'        => false,
    ],

    'moopmart' => [
        'id'             => 'moopmart',
        'name'           => 'MOOPmart: Data Exporter',
        'icon'           => 'fa-filter',
        'description'    => 'Filter features by annotation, coordinates, and gene set — download as TSV or FASTA',
        'btn_class'      => 'btn-tool-fuchsia',
        'url_path'       => '/tools/moopmart.php',
        'context_params' => [],
        'pages'          => 'all',
        'toolbox'        => false,
    ],

    'primer_blast' => [
        'id'             => 'primer_blast',
        'name'           => 'Primer BLAST',
        'icon'           => 'fa-vials',
        'description'    => 'Check primer pairs against a genome and transcriptome — product sizes and how many places they amplify',
        'btn_class'      => 'btn-tool-violet',
        'url_path'       => '/tools/primer_blast.php',
        'context_params' => ['organism', 'assembly', 'gene_set', 'group', 'display_name', 'organisms'],
        'pages'          => 'all',
        // IN the toolbox, unlike annotation_search and moopmart which sit in the
        // navbar only. The exception is deliberate: picking the source is this
        // tool's first and fiddliest step, and the toolbox passes the organism,
        // assembly and gene set of the page you came from — so arriving from a
        // gene set page lands with the right databases already selected.
        'toolbox'        => true,
    ],

    'primer_maker' => [
        'id'             => 'primer_maker',
        'name'           => 'Primer Maker',
        'icon'           => 'fa-magic',
        'description'    => 'Design PCR, qPCR, RT-PCR or sequencing primers from a sequence',
        'btn_class'      => 'btn-tool-indigo',
        'url_path'       => '/tools/primer_maker.php',
        'context_params' => ['organism', 'assembly', 'gene_set', 'feature', 'display_name'],
        // Gene pages only, and the Tools menu — by decision (user, 2026-08-07),
        // NOT 'all' like Primer BLAST. Designing primers is something you do
        // about a specific gene, so an organism or group page has no sequence to
        // offer it and the entry would be a dead end.
        'pages'          => ['parent'],
        'toolbox'        => true,
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
