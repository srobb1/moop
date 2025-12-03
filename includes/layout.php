<?php
/**
 * PAGE LAYOUT SYSTEM - Core Infrastructure
 * 
 * Provides unified page rendering with automatic header/footer wrapping.
 * All display pages (organism.php, assembly.php, etc.) use this system.
 * 
 * This is the heart of the clean architecture:
 * - Handles all HTML structure (<!DOCTYPE>, <html>, <head>, <body>, closing tags)
 * - Loads all resources in one place (CSS, JS, meta tags)
 * - Separates content from structure
 * - Enables single-point layout management
 * 
 * KEY BENEFITS:
 * - One file to change layout site-wide
 * - Consistent HTML structure guaranteed
 * - Proper opening and closing of all tags
 * - Clean separation: wrapper handles structure, content handles display
 * 
 * USAGE:
 *   echo render_display_page(
 *       'tools/pages/organism.php',
 *       [
 *           'organism_name' => $organism_name,
 *           'config' => $config,
 *       ],
 *       'Page Title Here'
 *   );
 * 
 * FUNCTIONS PROVIDED:
 * - render_display_page() - Main function, wraps content with full HTML structure
 * - render_json_response() - Alternative for AJAX endpoints returning JSON
 */

/**
 * Render a display page with full HTML structure
 * 
 * This function:
 * 1. Ensures config is loaded
 * 2. Includes access control
 * 3. Extracts data to variables
 * 4. Outputs complete HTML structure
 * 5. Includes content file in middle
 * 6. Returns complete page as string
 * 
 * @param string $content_file Path to content file to include (relative or absolute)
 * @param array $data Data to make available to content file as variables
 * @param string $title HTML page title (shown in browser tab)
 * @return string Complete HTML page ready to output
 * 
 * @example
 *   echo render_display_page('tools/pages/organism.php', [
 *       'organism_name' => $name,
 *       'config' => $config,
 *       'page_script' => '/moop/js/organism-display.js'
 *   ], 'Organism Display');
 */
function render_display_page($content_file, $data = [], $title = '') {
    // Ensure config is loaded
    if (!class_exists('ConfigManager')) {
        include_once __DIR__ . '/config_init.php';
    }
    
    // Include access control (authentication, permissions)
    include_once __DIR__ . '/access_control.php';
    
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
        
        <!-- Page-specific script (if provided in data) -->
        <?php
        if (isset($page_script)) {
            echo '<script src="' . htmlspecialchars($page_script) . '"></script>' . "\n";
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
