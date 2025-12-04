<?php
/**
 * ERROR LOG VIEWER - Wrapper
 * 
 * Handles admin access verification and renders error log viewer
 * using clean architecture layout system.
 */

// Load admin initialization (handles auth, config, includes)
include_once __DIR__ . '/admin_init.php';

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Get config
$siteTitle = $config->getString('siteTitle');

// Handle clear log action
$cleared = false;
if (isset($_GET['action']) && $_GET['action'] === 'clear' && isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    if (clearErrorLog()) {
        $cleared = true;
    }
}

// Get all errors from log
$all_errors = getErrorLog(500); // Get more for filtering

// Apply filters
$filter_type = $_GET['filter_type'] ?? '';
$filter_organism = $_GET['filter_organism'] ?? '';
$filter_search = $_GET['filter_search'] ?? '';

$errors = $all_errors;

// Filter by error type
if (!empty($filter_type)) {
    $errors = array_filter($errors, function($error) use ($filter_type) {
        return strpos($error['error'], $filter_type) !== false;
    });
}

// Filter by organism (in context field)
if (!empty($filter_organism)) {
    $errors = array_filter($errors, function($error) use ($filter_organism) {
        return strpos($error['context'], $filter_organism) !== false;
    });
}

// Search in error message and details
if (!empty($filter_search)) {
    $search_term = strtolower($filter_search);
    $errors = array_filter($errors, function($error) use ($search_term) {
        $searchable = strtolower(json_encode($error));
        return strpos($searchable, $search_term) !== false;
    });
}

// Get unique error types and organisms for filter dropdowns
$error_types = [];
$organisms = [];
foreach ($all_errors as $error) {
    if (!in_array($error['error'], $error_types)) {
        $error_types[] = $error['error'];
    }
    if (!empty($error['context']) && !in_array($error['context'], $organisms)) {
        $organisms[] = $error['context'];
    }
}
sort($organisms);

// Configure display
$display_config = [
    'title' => 'Error Log Viewer - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/error_log.php',
];

// Prepare data for content file
$data = [
    'cleared' => $cleared,
    'errors' => $errors,
    'all_errors' => $all_errors,
    'error_types' => $error_types,
    'organisms' => $organisms,
    'filter_type' => $filter_type,
    'filter_organism' => $filter_organism,
    'filter_search' => $filter_search,
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
