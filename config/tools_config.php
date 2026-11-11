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
 * - 'menu_label' / 'menu_order' put it in the navbar's Tools menu (no menu_order = not in it);
 *   'menu_divider_after' draws a line after it. 'name' is its toolbox label. A site can reorder
 *   and rename both on Admin -> Manage Tools ('tools_layout' in config_editable.json); these
 *   are the defaults, and they reproduce the menu as it was hand-written until 2026-11-03.
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
        'menu_label'      => 'Retrieve Sequences',
        'menu_order'      => 8,
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
        'menu_label'      => 'BLAST Search',
        'menu_order'      => 5,
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
        'menu_label'      => 'Downloads',
        'menu_order'      => 9,
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
        'menu_label'      => 'Genome Browser',
        'menu_order'      => 1,
        'menu_divider_after' => true,
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
        'menu_label'      => 'Annotation Search',
        'menu_order'      => 2,
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
        'menu_label'      => 'MOOPmart: Data Exporter',
        'menu_order'      => 3,
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
        'menu_label'      => 'Primer BLAST',
        'menu_order'      => 6,
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

    'expression' => [
        'id'              => 'expression',
        'menu_label'      => 'Expression Explorer',
        'menu_order'      => 4,
        'name'            => 'Expression Explorer',
        'icon'            => 'fa-th',
        'description'     => 'Compare a list of genes across expression experiments as a heatmap',
        'btn_class'       => 'btn-tool-teal',
        'url_path'        => '/tools/expression.php',
        'context_params'  => ['organism', 'assembly', 'gene_set'],
        // Only where there is something to show: the gene set page sets has_expression when
        // the gene set has expression bundles this visitor may see. Elsewhere the button would
        // lead to an empty page.
        'required_params' => ['has_expression'],
        'pages'           => ['gene_set'],
    ],

    'anatomy_search' => [
        'id'              => 'anatomy_search',
        'menu_label'      => 'Anatomical Expression Search',
        'menu_order'      => 4.5,
        'name'            => 'Anatomical Expression Search',
        'icon'            => 'fa-microscope',
        'description'     => 'Where genes were published as expressed: by body part, by paper, or by gene',
        'btn_class'       => 'btn-tool-teal',
        'url_path'        => '/tools/anatomy_search.php',
        'context_params'  => ['organism'],
        // Off on a site with no curated data (no organism has anatomy_expression.sqlite) --
        // nothing in the menu, no toolbox button, its page a 404. Admin -> Manage Tools can still
        // switch it off where there is data.
        'available_if'    => 'moop_anatomical_expression_available',
        // Toolbox: only on the organism page of an organism that HAS curations.
        'required_params' => ['has_anatomical_expression'],
        'pages'           => ['organism'],
    ],

    'crosswalk' => [
        'id'              => 'crosswalk',
        'menu_label'      => 'ID Translator',
        'menu_order'      => 4.6,
        'name'            => 'ID Translator',
        'icon'            => 'fa-exchange-alt',
        'description'     => 'Translate transcript, EST and array IDs into the same gene in other sources',
        'btn_class'       => 'btn-tool-teal',
        'url_path'        => '/tools/crosswalk.php',
        'context_params'  => ['organism'],
        // Off on a site with no crosswalk file (cuttingclass today): no menu entry, no toolbox
        // button, its page a 404. The site's own name for it (simrbase: "Rosetta Stone Transcript
        // Mapper") is shown on the page, from the crosswalk config.
        'available_if'    => 'moop_crosswalk_available',
        'required_params' => ['has_crosswalk'],
        'pages'           => ['organism'],
    ],

    'ontology_browser' => [
        'id'              => 'ontology_browser',
        'menu_label'      => 'Ontology Browser',
        'menu_order'      => 4.7,
        'name'            => 'Ontology Browser',
        'icon'            => 'fa-sitemap',
        'description'     => 'Explore an ontology as a tree, with a page for every term',
        'btn_class'       => 'btn-tool-teal',
        'url_path'        => '/tools/ontology_browser.php',
        'context_params'  => [],
        // Only where an ontology with a link tree is built (lib/ontologies.php): menu entry and page.
        'available_if'    => 'moop_ontology_browser_available',
        'pages'           => [],
    ],

    'primer_maker' => [
        'id'             => 'primer_maker',
        'menu_label'      => 'Primer Maker',
        'menu_order'      => 7,
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
