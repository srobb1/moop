<?php
/**
 * JBrowse Admin API: Tracks Server Configuration
 *
 * Actions:
 *   get_config  - return current tracks server config + JWT public key
 *   save_config - persist settings to config_editable.json
 *   test_jwt    - verify JWT key pair works
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../admin/admin_access_check.php';
require_once __DIR__ . '/../../lib/jbrowse/track_token.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$config  = ConfigManager::getInstance();

switch ($action) {

    // ------------------------------------------------------------------ //
    case 'get_config':
        $tracks_server = $config->get('tracks_server', []);
        $pub_key_path  = __DIR__ . '/../../certs/jwt_public_key.pem';
        $pub_key       = file_exists($pub_key_path) ? trim(file_get_contents($pub_key_path)) : null;

        echo json_encode([
            'success'      => true,
            'tracks_server' => [
                'enabled'       => !empty($tracks_server['enabled']),
                'url'           => $tracks_server['url'] ?? '',
                'validate_jwt'  => $tracks_server['validate_jwt'] ?? true,
            ],
            'jwt_public_key' => $pub_key,
            'jwt_key_exists' => $pub_key !== null,
        ]);
        break;

    // ------------------------------------------------------------------ //
    case 'save_config':
        $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $url     = trim($_POST['url'] ?? '');

        // Validate URL if enabling
        if ($enabled && $url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'Invalid server URL']);
            exit;
        }

        // Load current config_editable.json
        $config_dir  = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
        $config_file = $config_dir . '/config_editable.json';

        if (!file_exists($config_file)) {
            echo json_encode(['success' => false, 'error' => 'config_editable.json not found']);
            exit;
        }

        $editable = json_decode(file_get_contents($config_file), true) ?? [];

        $editable['tracks_server'] = [
            'enabled'      => $enabled,
            'url'          => $url,
            'validate_jwt' => true,
            'sync_method'  => 'manual',
        ];

        // Also keep trusted_tracks_servers in sync with the configured URL
        if ($enabled && $url !== '') {
            $existing_trusted = $editable['jbrowse2']['trusted_tracks_servers'] ?? [];
            if (!in_array(rtrim($url, '/'), array_map(fn($s) => rtrim($s, '/'), $existing_trusted))) {
                $editable['jbrowse2']['trusted_tracks_servers'][] = rtrim($url, '/');
            }
        }

        if (file_put_contents($config_file, json_encode($editable, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to write config file']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Tracks server configuration saved.']);
        break;

    // ------------------------------------------------------------------ //
    case 'test_jwt':
        $organism = 'TestOrganism';
        $assembly = 'TestAssembly';

        try {
            $token    = generateTrackToken($organism, $assembly);
            $verified = verifyTrackToken($token);

            if (!$verified) {
                echo json_encode(['success' => false, 'error' => 'Token generated but verification failed']);
                exit;
            }

            echo json_encode([
                'success'     => true,
                'message'     => 'JWT key pair works correctly',
                'token_scope' => "{$verified->organism}/{$verified->assembly}",
                'expires_in'  => ($verified->exp - time()) . ' seconds',
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ------------------------------------------------------------------ //
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
}
?>
