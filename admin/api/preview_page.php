<?php
/**
 * Preview for Manage Pages: POST markdown (the text) + format -> JSON {html, removed}. Uses
 * moop_page_render(), the SAME renderer page.php publishes with, so the preview is exactly what
 * visitors will see; for HTML, 'removed' lists what the cleaner took out.
 * Admin only; admin_init.php checks the role and the CSRF token (sent by js/modules/csrf.js).
 */
include_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/content_pages.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}
$markdown = (string) ($_POST['markdown'] ?? '');
if (strlen($markdown) > 500000) {
    http_response_code(413);
    echo json_encode(['error' => 'Too long to preview']);
    exit;
}
$format  = ($_POST['format'] ?? '') === 'html' ? 'html' : 'markdown';
$removed = [];
$html    = moop_page_render($markdown, $format, $removed);
echo json_encode(['html' => $html, 'removed' => $removed]);
