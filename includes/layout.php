<?php
/**
 * PAGE LAYOUT SYSTEM - Core Infrastructure
 * 
 * ========== CLEAN ARCHITECTURE OVERVIEW ==========
 * 
 * DATA FLOW:
 *   Display Page (organism.php)
 *     ↓
 *   Loads data, validates access, configures layout
 *     ↓
 *   Calls render_display_page(content_file, data, title)
 *     ↓
 *   layout.php (this file)
 *     ↓
 *   Outputs HTML structure with embedded content file
 *     ↓
 *   Browser displays complete page
 * 
 * ========== FILE RESPONSIBILITIES ==========
 * 
 * Display Page (e.g., tools/organism.php):
 *   - Validates user access
 *   - Loads organism data from database
 *   - Configures layout (title, scripts, styles)
 *   - Calls render_display_page() with content file and data
 * 
 * Content File (e.g., tools/pages/organism.php):
 *   - ONLY displays HTML/data received
 *   - Does NOT include <html>, <head>, <body> tags
 *   - Does NOT load CSS/JS libraries (layout.php handles that)
 *   - Uses variables passed from display page
 * 
 * layout.php (this file):
 *   - Provides render_display_page() function
 *   - Outputs complete HTML structure (<!DOCTYPE>, <html>, etc.)
 *   - Loads all CSS/JS libraries in one place
 *   - Includes navbar, footer, content file in proper order
 *   - Executes inline scripts after libraries load
 * 
 * ========== KEY BENEFITS ==========
 * 
 * - One file to change layout site-wide
 * - Consistent HTML structure guaranteed
 * - Proper opening and closing of all tags
 * - Clean separation: wrapper handles structure, content handles display
 * - All scripts load in consistent order
 * - No duplicate script loading
 * 
 * ========== USAGE EXAMPLE ==========
 * 
 *   // In tools/organism.php:
 *   include_once __DIR__ . '/../includes/access_control.php';
 *   include_once __DIR__ . '/../includes/layout.php';
 *   
 *   $organism_name = $_GET['organism'];
 *   $organism_data = loadOrganism($organism_name);
 *   
 *   echo render_display_page(
 *       __DIR__ . '/pages/organism.php',
 *       [
 *           'organism_name' => $organism_name,
 *           'organism_data' => $organism_data,
 *           'page_script' => '/moop/js/organism-display.js',
 *           'inline_scripts' => [
 *               "const sitePath = '/moop';",
 *               "const organism = '" . addslashes($organism_name) . "';"
 *           ]
 *       ],
 *       'Organism Display'
 *   );
 * 
 * ========== FUNCTIONS PROVIDED ==========
 * 
 * - render_display_page() - Main function, wraps content with full HTML structure
 * - render_json_response() - Alternative for AJAX endpoints returning JSON
 */

/**
 * Render a display page with full HTML structure
 * 
 * IMPORTANT: Call access_control.php ONCE at the top of your page BEFORE calling this function.
 * This ensures authentication/authorization is handled only once per page load.
 * 
 * This function:
 * 1. Extracts data to variables (making $organism_name available instead of $data['organism_name'])
 * 2. Outputs complete HTML structure
 * 3. Includes content file in the middle
 * 4. Returns complete page as string
 * 
 * @param string $content_file Path to content file to include (relative or absolute)
 * @param array $data Data to make available to content file as variables
 *                     Optional keys:
 *                     - 'page_styles' (array) - Additional CSS files: ['/moop/css/custom.css', '/moop/css/other.css']
 *                     - 'page_script' (string|array) - Page-specific JS file(s): '/moop/js/script.js' or ['/moop/js/script1.js', '/moop/js/script2.js']
 *                     - 'inline_scripts' (array) - JS code to execute inline (variables, init)
 * @param string $title HTML page title (shown in browser tab)
 * @return string Complete HTML page ready to output
 * 
 * @example
 *   // At top of your page:
 *   include_once __DIR__ . '/includes/access_control.php';
 *   include_once __DIR__ . '/includes/layout.php';
 *   
 *   // Later in your page:
 *   echo render_display_page('tools/pages/organism.php', [
 *       'organism_name' => $name,
 *       'config' => $config,
 *       'page_styles' => ['/moop/css/display.css', '/moop/css/parent.css'],
 *       'page_script' => '/moop/js/organism-display.js',
 *       'inline_scripts' => [
 *           "const sitePath = '/moop';",
 *           "const organism = '" . addslashes($name) . "';"
 *       ]
 *   ], 'Organism Display');
 */
function render_display_page($content_file, $data = [], $title = '') {
    // Get config instance for use in layout
    $config = ConfigManager::getInstance();
    
    // Extract data array to variables for use in included content file
    // This allows content file to use $organism_name directly instead of $data['organism_name']
    extract($data);
    
    // Start output buffering to capture complete page
    ob_start();
    
    // Output complete HTML structure
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title><?= htmlspecialchars($title) ?></title>
        <?php include_once __DIR__ . '/head-resources.php'; ?>
        
        <!-- Page-specific styles (if provided in data) -->
        <?php
        if (isset($page_styles) && is_array($page_styles)) {
            foreach ($page_styles as $style) {
                echo '<link rel="stylesheet" href="' . htmlspecialchars($style) . '">' . "\n";
            }
        }
        ?>
    </head>
    <body class="bg-light">
        <?php include_once __DIR__ . '/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <?php 
            // Include content file
            // Content file has access to all extracted variables
            if (file_exists($content_file)) {
                include $content_file;
            } else {
                echo '<div class="alert alert-danger">';
                echo '<i class="fa fa-exclamation-circle"></i> ';
                echo 'Error: Content file not found: ' . htmlspecialchars($content_file);
                echo '</div>';
            }
            ?>
        </div>
        
        <?php include_once __DIR__ . '/footer.php'; ?>
        
        <!-- 
        SCRIPT MANAGEMENT - All external scripts in one place
        This ensures:
        - Scripts load in consistent order
        - No duplicate script loading
        - Easy to add/remove scripts site-wide
        - Page-specific scripts load last (can depend on libraries)
        -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js"></script>
        
        <!-- MOOP shared modules - Available to all pages -->
        <script src="/<?= $config->getString('site') ?>/js/modules/datatable-config.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/shared-results-table.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/annotation-search.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/advanced-search-filter.js"></script>
        
        <!-- Inline scripts - Page-specific variable definitions (must load before page_script) -->
        <?php
        if (isset($inline_scripts) && is_array($inline_scripts)) {
            echo '<script>' . "\n";
            foreach ($inline_scripts as $script) {
                echo $script . "\n";
            }
            echo '</script>' . "\n";
        }
        ?>
        
        <!-- Page-specific script file(s)
             
             HOW TO USE page_script:
             
             1. PASSING page_script TO render_display_page():
                - Add 'page_script' key to the $data array passed to render_display_page()
                - Value can be a STRING or ARRAY:
                  * String: '/moop/js/my-script.js'
                  * Array: ['/moop/js/jquery-ui.js', '/moop/js/my-script.js']
                
                Example in admin/manage_annotations.php:
                    $data = [
                        'annotations' => $annotations,
                        'page_script' => [
                            '/' . $config->getString('site') . '/js/jquery-ui.min.js',
                            '/' . $config->getString('site') . '/js/modules/manage-annotations.js'
                        ],
                    ];
             
             2. WHAT page_script IS USED FOR:
                - Load JavaScript modules specific to that page
                - Runs AFTER all inline_scripts (so inline vars are available)
                - Runs AFTER all Bootstrap, jQuery, and other libraries
                - Good place for page-specific event handlers, DOM manipulation, etc.
             
             3. SCRIPT LOAD ORDER (IMPORTANT):
                1. Bootstrap CSS and libraries (head-resources.php)
                2. Navbar HTML
                3. Content file HTML (main page content)
                4. inline_scripts (global vars like sitePath, inline handlers)
                5. page_script (page-specific JS module(s)) ← YOU ARE HERE
                6. Footer
             
             4. ACCESSING VARIABLES FROM inline_scripts:
                - inline_scripts defines page-wide variables (sitePath, organism, etc.)
                - page_script can use those variables since it loads after inline_scripts
                
                Example:
                    inline_scripts: "const sitePath = '/moop';"
                    page_script: "alert(sitePath); // Works! sitePath is defined"
             
             5. SHARED UTILITIES:
                - Use js/admin-utilities.js for handlers needed on multiple admin pages
                - Use js/modules/*.js for page-specific logic
                - Put reusable DOM manipulation in admin-utilities.js
        -->
        <?php
        if (isset($page_script)) {
            // Handle both string and array
            $scripts = is_array($page_script) ? $page_script : [$page_script];
            foreach ($scripts as $script) {
                echo '<script src="' . htmlspecialchars($script) . '"></script>' . "\n";
            }
        }
        ?>
    </body>
    </html>
    <?php
    
    // Return buffered content as string
    return ob_get_clean();
}

/**
 * Render a response as JSON (for AJAX requests)
 * 
 * This function:
 * 1. Sets appropriate HTTP headers
 * 2. Encodes data as JSON
 * 3. Outputs and exits
 * 
 * Used when display pages need to return data instead of HTML
 * (e.g., search results, validation responses)
 * 
 * @param array $data Data to return as JSON
 * @param int $status HTTP status code (200, 400, 404, 500, etc.)
 * @return void (outputs JSON and exits script)
 * 
 * @example
 *   render_json_response([
 *       'success' => true,
 *       'message' => 'Search completed',
 *       'results' => $search_results
 *   ]);
 * 
 *   render_json_response([
 *       'success' => false,
 *       'error' => 'Invalid organism'
 *   ], 404);
 */
function render_json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

?>
