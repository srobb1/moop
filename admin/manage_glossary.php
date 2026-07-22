<?php
/**
 * MANAGE GLOSSARY — Admin Controller
 *
 * Edits metadata/glossary.json, the single source of truth for the dashed-underline
 * term definitions rendered site-wide by gloss() (lib/glossary.php). Editing a
 * definition here updates every occurrence of that term across the site — no code
 * change, no redeploy.
 *
 * CSRF is verified in admin_init.php on every POST. Uses POST-redirect-GET so a
 * refresh never re-submits an add/edit/delete.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';   // provides render_display_page()

$metadata_path = $config->getPath('metadata_path');
$glossary_file = "$metadata_path/glossary.json";

// Writability is surfaced to the page (same helper the other manage pages use).
$file_write_error = getFileWriteError($glossary_file);

// Read RAW (preserving original key casing, e.g. "GO term") — not via
// glossary_terms(), which lower-cases keys for case-insensitive matching.
$terms = loadJsonFile($glossary_file, []);
if (!is_array($terms)) {
    $terms = [];
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$file_write_error) {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add') {
        $term = trim($_POST['term'] ?? '');
        $def  = trim($_POST['definition'] ?? '');
        if ($term === '' || $def === '') {
            $flash = ['type' => 'danger', 'msg' => 'Both a term and a definition are required.'];
        } elseif (glossary_has_key($terms, $term)) {
            $flash = ['type' => 'danger', 'msg' => 'A term "' . htmlspecialchars($term) . '" already exists.'];
        } else {
            $terms[$term] = $def;
            saveJsonFile($glossary_file, $terms);
            $flash = ['type' => 'success', 'msg' => 'Added "' . htmlspecialchars($term) . '".'];
        }

    } elseif ($action === 'update') {
        $term = $_POST['term'] ?? '';
        $def  = trim($_POST['definition'] ?? '');
        $key  = glossary_resolve_key($terms, $term);
        if ($key === null) {
            $flash = ['type' => 'danger', 'msg' => 'That term no longer exists.'];
        } elseif ($def === '') {
            $flash = ['type' => 'danger', 'msg' => 'A definition cannot be empty — delete the term instead.'];
        } else {
            $terms[$key] = $def;
            saveJsonFile($glossary_file, $terms);
            $flash = ['type' => 'success', 'msg' => 'Updated "' . htmlspecialchars($key) . '".'];
        }

    } elseif ($action === 'delete') {
        $term = $_POST['term'] ?? '';
        $key  = glossary_resolve_key($terms, $term);
        if ($key !== null) {
            unset($terms[$key]);
            saveJsonFile($glossary_file, $terms);
            $flash = ['type' => 'success', 'msg' => 'Deleted "' . htmlspecialchars($key) . '".'];
        }
    }

    // POST-redirect-GET: carry the flash through the session, then reload clean.
    $_SESSION['glossary_flash'] = $flash;
    header('Location: manage_glossary.php');
    exit;
}

// Hydrate a flash left by the redirect above.
if (isset($_SESSION['glossary_flash'])) {
    $flash = $_SESSION['glossary_flash'];
    unset($_SESSION['glossary_flash']);
}

// Sort terms alphabetically for display (case-insensitive), keeping raw casing.
uksort($terms, fn($a, $b) => strcasecmp($a, $b));

/** Case-insensitive existence check against the raw-cased keys. */
function glossary_has_key(array $terms, string $term): bool {
    return glossary_resolve_key($terms, $term) !== null;
}
/** Return the actual stored key matching $term case-insensitively, or null. */
function glossary_resolve_key(array $terms, string $term): ?string {
    $needle = strtolower(trim($term));
    foreach ($terms as $k => $_) {
        if (strtolower((string) $k) === $needle) {
            return (string) $k;
        }
    }
    return null;
}

$data = [
    'site'             => $config->getString('site'),
    'terms'            => $terms,
    'flash'            => $flash,
    'file_write_error' => $file_write_error,
    'glossary_file'    => $glossary_file,
];

echo render_display_page(__DIR__ . '/pages/manage_glossary.php', $data, 'Manage Glossary');
