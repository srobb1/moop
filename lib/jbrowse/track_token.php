<?php
/**
 * Track Token Generator
 * Generates JWT tokens for track file access
 *
 * Requires: composer require firebase/php-jwt (loaded only when a token is signed or checked,
 * so the key paths below can be read on an install where composer has not run yet --
 * setup-check.php and setup.php need them before that).
 *
 * This same file is copied to the remote tracks server, which has no MOOP configuration.
 * Keep it self-contained: no ConfigManager, no other MOOP includes.
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Where the JWT key pair lives: <moop>/certs/. Every reader goes through here -- signing and
 * verifying below, the permissions check, housekeeping, setup and setup-check, the tracks
 * server admin page -- so a check can never inspect a different file from the one in use.
 *
 * Fixed, not configured, on purpose: this file also runs on the tracks server, which has no
 * MOOP config to read a path from. Until 2026-10-23 site_config.php carried six settings
 * (certs_directory, jwt_private_key, jwt_public_key, twice over) that LOOKED like they moved
 * the keys; the signing code never read any of them, so changing one would only have pointed
 * the health checks at the wrong file.
 *
 * The directory is inside the web root. nginx refuses it outright (location ^~ /moop/certs/
 * in docs/nginx/moop-security.conf); the 2750 directory mode is the second line, not the only one.
 *
 * @return array{dir: string, private: string, public: string}
 */
function moop_jwt_key_paths(): array {
    $dir = dirname(__DIR__, 2) . '/certs';
    return [
        'dir'     => $dir,
        'private' => $dir . '/jwt_private_key.pem',
        'public'  => $dir . '/jwt_public_key.pem',
    ];
}

/** Load firebase/php-jwt (Composer) on first use. */
function moop_jwt_library(): void {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

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
    $private_key_path = moop_jwt_key_paths()['private'];

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

    moop_jwt_library();
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
    $public_key_path = moop_jwt_key_paths()['public'];

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
    moop_jwt_library();
    JWT::$leeway = 60;

    try {
        $decoded = JWT::decode($token, new Key($public_key, 'RS256'));
        return $decoded;
    } catch (Exception $e) {
        error_log("JWT verification failed: " . $e->getMessage());
        return false;
    }
}
