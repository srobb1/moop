<?php
/**
 * Track Token Generator
 * Generates JWT tokens for track file access
 * 
 * Requires: composer require firebase/php-jwt
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Canonical MOOP access-level hierarchy → numeric rank.
 *
 * The single source of truth for both token minting (the bearer's level) and
 * tracks.php enforcement (a file's required level). Lives here so it travels with
 * this file when track_token.php is copied to the tracks server — do NOT duplicate
 * it there. Case-insensitive; unset/blank and unrecognized values map to PUBLIC (1),
 * matching the app's long-standing "no access_level ⇒ public" convention.
 *
 * @param string|null $level e.g. 'PUBLIC', 'Public', 'COLLABORATOR', 'ADMIN'
 * @return int 1..4 (PUBLIC..ADMIN)
 */
function trackAccessLevelValue($level) {
    static $map = [
        'PUBLIC'       => 1,
        'COLLABORATOR' => 2,
        'IP_IN_RANGE'  => 3,
        'ADMIN'        => 4,
    ];
    $key = strtoupper(trim((string)$level));
    return $map[$key] ?? 1;
}

/**
 * Generate JWT token for track access
 *
 * Token design (2026-07-13): organism/assembly scope + the bearer's numeric access
 * `level`. tracks.php uses `level` to enforce per-file access against a per-assembly
 * access_manifest.json, so a low-privilege token can no longer fetch a higher-level
 * file on the same assembly merely by knowing its path. Still no user_id (avoids
 * username leakage to external servers).
 *
 * @param string $organism
 * @param string $assembly
 * @param string $user_level  Bearer's access level ('PUBLIC'..'ADMIN'); default PUBLIC (least privilege)
 * @return string JWT token
 */
function generateTrackToken($organism, $assembly, $user_level = 'PUBLIC') {
    $config_dir = dirname(__DIR__, 2) . '/certs';
    $private_key_path = $config_dir . '/jwt_private_key.pem';

    if (!file_exists($private_key_path)) {
        throw new Exception("JWT private key not found at: $private_key_path");
    }

    $private_key = file_get_contents($private_key_path);

    $token_data = [
        'organism' => $organism,
        'assembly' => $assembly,
        'level'    => trackAccessLevelValue($user_level),
        'iat' => time(),
        'exp' => time() + 3600  // 1 hour expiry
    ];

    try {
        $jwt = JWT::encode($token_data, $private_key, 'RS256');
        return $jwt;
    } catch (Exception $e) {
        throw new Exception("Failed to generate JWT: " . $e->getMessage());
    }
}

/**
 * Verify JWT token
 * 
 * @param string $token JWT token
 * @return object|false Token payload if valid, false otherwise
 */
function verifyTrackToken($token) {
    $config_dir = dirname(__DIR__, 2) . '/certs';
    $public_key_path = $config_dir . '/jwt_public_key.pem';

    if (!file_exists($public_key_path)) {
        error_log("JWT verification failed: public key not found at $public_key_path");
        return false;
    }

    $public_key = file_get_contents($public_key_path);
    if ($public_key === false) {
        error_log("JWT verification failed: public key at $public_key_path is not readable");
        return false;
    }

    // The signing host (MOOP) and this verifying host may be seconds apart, and
    // php-jwt defaults to zero tolerance: a token whose `iat` is one second in
    // the verifier's future is rejected outright. Tokens live an hour, so a
    // minute of slack costs nothing.
    JWT::$leeway = 60;

    try {
        $decoded = JWT::decode($token, new Key($public_key, 'RS256'));
        return $decoded;
    } catch (Exception $e) {
        error_log("JWT verification failed: " . $e->getMessage());
        return false;
    }
}
