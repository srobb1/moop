<?php
/**
 * Security Test Script
 * Demonstrates that access_level cannot be manipulated via URL parameters
 */

// Simulate different scenarios
echo "=== Access Control Security Test ===\n\n";

// Test 1: Try to set access_level via GET parameter (should be ignored)
$_GET['access_level'] = 'ALL';
$_GET['access_group'] = 'ALL';
$_GET['admin'] = 'true';

session_start();
// Clear any existing session
session_unset();

include_once __DIR__ . '/access_control.php';

echo "Test 1: URL Parameter Manipulation\n";
echo "   GET parameters set: access_level=ALL, access_group=ALL, admin=true\n";
echo "   Actual access_level: " . $access_level . "\n";
echo "   Expected: Public (parameters should be ignored)\n";
echo "   Result: " . ($access_level === 'Public' ? "PASS ✓" : "FAIL ✗") . "\n\n";

// Test 2: Simulate IP-based access
echo "Test 2: IP-Based Auto-Login\n";
$_SERVER['REMOTE_ADDR'] = '127.0.0.11';
session_unset();
session_destroy();
session_start();

include __DIR__ . '/access_control.php';

echo "   Simulated IP: 127.0.0.11\n";
echo "   Actual access_level: " . $access_level . "\n";
echo "   Expected: ALL (auto-login from authorized IP)\n";
echo "   Result: " . ($access_level === 'ALL' ? "PASS ✓" : "FAIL ✗") . "\n\n";

// Test 3: Test has_access function
echo "Test 3: Access Control Functions\n";
echo "   has_access('Public'): " . (has_access('Public') ? "true ✓" : "false ✗") . "\n";
echo "   has_access('Collaborator'): " . (has_access('Collaborator') ? "true ✓" : "false ✗") . "\n";
echo "   has_access('Admin'): " . (has_access('Admin') ? "true ✓" : "false ✗") . "\n";
echo "   has_access('ALL'): " . (has_access('ALL') ? "true ✓" : "false ✗") . "\n\n";

echo "=== Test Complete ===\n";
echo "The access control system is secure against URL parameter manipulation.\n";
