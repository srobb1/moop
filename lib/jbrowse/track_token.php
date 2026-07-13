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
 * Generate JWT token for track access
 *
 * Token design (2026-07-13): each token is bound to the ONE file it authorizes, via
 * the `file` claim (the exact `?file=` path tracks.php will receive, e.g.
 * "Organism/Assembly/type/reads.bam"). tracks.php serves a file only if the token's
 * `file` matches the requested one, so a token handed out for a PUBLIC file cannot be
 * replayed against a COLLABORATOR file on the same assembly — the authorization rides
 * in the signed token itself, so the tracks server needs no access list of its own.
 * organism/assembly are kept for logging/context; the security check is on `file`.
 * Still no user_id (avoids username leakage to external servers).
 *
 * @param string $organism
 * @param string $assembly
 * @param string $file  The exact tracks.php `?file=` path this token authorizes
 * @return string JWT token
 */
function generateTrackToken($organism, $assembly, $file = '') {
    $config_dir = dirname(__DIR__, 2) . '/certs';
    $private_key_path = $config_dir . '/jwt_private_key.pem';

    if (!file_exists($private_key_path)) {
        throw new Exception("JWT private key not found at: $private_key_path");
    }

    $private_key = file_get_contents($private_key_path);

    $token_data = [
        'organism' => $organism,
        'assembly' => $assembly,
        'file'     => $file,
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
