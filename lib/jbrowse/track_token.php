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
 * Security: Minimal token design (2026-02-25)
 * - Only includes organism/assembly scope (required for validation)
 * - No user_id (prevents username leakage to external sites)
 * - No access_level (never used by tracks server)
 * 
 * @param string $organism
 * @param string $assembly
 * @return string JWT token
 */
function generateTrackToken($organism, $assembly) {
    $config_dir = dirname(__DIR__, 2) . '/certs';
    $private_key_path = $config_dir . '/jwt_private_key.pem';
    
    if (!file_exists($private_key_path)) {
        throw new Exception("JWT private key not found at: $private_key_path");
    }
    
    $private_key = file_get_contents($private_key_path);
    
    $token_data = [
        'organism' => $organism,
        'assembly' => $assembly,
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
        return false;
    }
    
    $public_key = file_get_contents($public_key_path);
    
    try {
        $decoded = JWT::decode($token, new Key($public_key, 'RS256'));
        
        // Verify token hasn't expired
        if ($decoded->exp < time()) {
            return false;
        }
        
        return $decoded;
    } catch (Exception $e) {
        error_log("JWT verification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user IP is whitelisted (internal network)
 * 
 * @return bool
 */
function isWhitelistedIP() {
    $trusted_ranges = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['127.0.0.1', '127.255.255.255']  // localhost
    ];
    
    $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $visitor_ip_long = ip2long($visitor_ip);
    
    if ($visitor_ip_long === false) {
        return false;
    }
    
    foreach ($trusted_ranges as $range) {
        $start_long = ip2long($range[0]);
        $end_long = ip2long($range[1]);
        
        if ($visitor_ip_long >= $start_long && $visitor_ip_long <= $end_long) {
            return true;
        }
    }
    
    return false;
}
?>
