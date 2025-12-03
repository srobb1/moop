<?php
/**
 * TEST PAGE WRAPPER
 * 
 * This wrapper demonstrates the clean architecture system.
 * 
 * To test:
 * 1. Open in browser: /moop/test_layout.php
 * 2. Open DevTools (F12) and check HTML structure
 * 3. Verify all components load correctly
 * 4. Check console for errors
 */

// Load initialization
include_once __DIR__ . '/tools/tool_init.php';

// Load the layout system
include_once __DIR__ . '/includes/layout.php';

// Render the test page using the layout system
echo render_display_page(
    __DIR__ . '/tools/pages/test.php',
    [
        'config' => $config,
        'test_timestamp' => date('Y-m-d H:i:s'),
    ],
    'Layout System Test - Phase 1 Verification'
);

?>
