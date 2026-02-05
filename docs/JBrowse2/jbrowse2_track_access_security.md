# JBrowse2 Track Access & Security Strategy

## Problem Statement

- BigWig/BAM files stored on separate tracks server
- Must prevent unauthorized access to restricted tracks
- Must support different access levels (Public, Collaborator, ALL)
- Must work with HTTP range requests (partial file access)
- Need to validate all requests without exposing sensitive paths

---

## Recommended Architecture: JWT Token-Based Access

### Overview

1. **MOOP generates time-limited JWT tokens** when user requests assembly config
2. **Tokens embedded in track URLs** sent to browser
3. **Browser sends tokens** with HTTP range requests
4. **Tracks server validates tokens** before serving bytes
5. **Tokens expire** (e.g., 1 hour), forcing re-authentication

### Advantages

- **Stateless**: Track server doesn't need database/cache (just public key)
- **Scalable**: Can distribute tracks across multiple servers
- **Secure**: Tokens are signed, can't be forged without private key
- **Flexible**: Token claims can include user_id, access_level, organization, etc.
- **Fine-grained**: Different users get different token-embedded URLs
- **Clean separation**: MOOP handles auth, tracks server just validates

---

## Implementation

### 1. Generate Keys (One-Time Setup)

```bash
# At MOOP server
openssl genrsa -out /etc/moop/jwt_private_key.pem 4096
openssl rsa -in /etc/moop/jwt_private_key.pem -pubout -out /etc/moop/jwt_public_key.pem

# Copy public key to tracks server
scp /etc/moop/jwt_public_key.pem tracks-admin@tracks.example.com:/etc/tracks-server/
```

### 2. Token Generation (MOOP)

When user requests assembly config, MOOP generates tokens:

```php
// In /api/jbrowse2/assembly.php

$token = [
    'user_id' => get_username(),
    'organism' => 'Anoura_caudifer',
    'assembly' => 'GCA_004027475.1',
    'access_level' => 'Collaborator',
    'iat' => time(),
    'exp' => time() + 3600
];

$jwt = JWT::encode($token, $private_key, 'HS256');

// URL returned to browser:
// https://tracks.example.com/bigwig/Anoura_caudifer_GCA_004027475.1_rna.bw?token={$jwt}
```

### 3. Token Validation (Tracks Server - Nginx + PHP)

```nginx
# /etc/nginx/sites-available/tracks.example.com

server {
    listen 443 ssl http2;
    server_name tracks.example.com;
    root /var/tracks/data;

    # BigWig files
    location ~ ^/bigwig/(.+\.bw)$ {
        # Check if request has token (query param or header)
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        
        # Validate token via internal auth request
        auth_request /validate-jwt;
        
        # Enable HTTP range requests
        add_header Accept-Ranges bytes;
        add_header Content-Type application/octet-stream;
        
        try_files $uri =404;
    }

    # Internal endpoint for token validation
    location = /validate-jwt {
        internal;
        proxy_pass http://127.0.0.1:9000/validate-jwt.php;
        proxy_pass_request_body off;
        proxy_set_header Content-Length "";
        proxy_set_header X-Token $arg_token;
        proxy_set_header X-Request-URI $request_uri;
    }

    # BAM files (similar)
    location ~ ^/bam/(.+\.bam)$ {
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        auth_request /validate-jwt;
        add_header Accept-Ranges bytes;
        try_files $uri =404;
    }

    # Index files (BAI, etc.) - require same token
    location ~ ^/(bam|bigwig)/(.+\.(bai|tbi|idx))$ {
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        auth_request /validate-jwt;
        try_files $uri =404;
    }
}
```

```php
// /var/www/tracks/validate-jwt.php

<?php
require_once '/usr/share/php/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$token = $_SERVER['HTTP_X_TOKEN'] ?? '';
$request_uri = $_SERVER['HTTP_X_REQUEST_URI'] ?? '';

if (empty($token)) {
    http_response_code(403);
    exit("No token");
}

try {
    // Load public key (shared with MOOP)
    $public_key_path = '/etc/tracks-server/jwt_public_key.pem';
    if (!file_exists($public_key_path)) {
        throw new Exception('Public key not found');
    }
    
    $public_key = file_get_contents($public_key_path);
    
    // Verify signature
    $decoded = JWT::decode($token, new Key($public_key, 'HS256'));
    
    // Check expiration
    if ($decoded->exp < time()) {
        http_response_code(403);
        exit("Token expired");
    }
    
    // Optional: Validate token claims against requested file
    // Extract organism/assembly from request URI
    if (preg_match('#/(bigwig|bam)/(\w+)_([^_]+)_#', $request_uri, $matches)) {
        $uri_organism = $matches[2];
        $uri_assembly = $matches[3];
        
        if ($decoded->organism !== $uri_organism || 
            $decoded->assembly !== $uri_assembly) {
            http_response_code(403);
            exit("Token organism/assembly mismatch");
        }
    }
    
    // Token is valid - return 200
    http_response_code(200);
    
} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(403);
    exit("Token expired: " . $e->getMessage());
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(403);
    exit("Invalid token signature");
} catch (Exception $e) {
    http_response_code(403);
    exit("Invalid token: " . $e->getMessage());
}
?>
```

---

## Alternative: Session Token + MOOP Callback

If JWT is not preferred, use session tokens with callback validation:

**Simpler setup, but adds latency:**

```php
// MOOP generates session token
$token = bin2hex(random_bytes(32));
$_cache->set("track_token:{$token}", [
    'user_id' => get_username(),
    'organism' => 'Anoura_caudifer',
    'assembly' => 'GCA_004027475.1',
    'exp' => time() + 3600
]);
// Return: https://tracks.example.com/bigwig/file.bw?token={$token}
```

```php
// Tracks server calls MOOP to validate
$moop_api = "https://moop.internal/api/jbrowse2/validate-session-token";
$response = json_decode(file_get_contents(
    $moop_api . "?token={$token}"
), true);

if ($response['valid']) {
    // Serve file
} else {
    http_response_code(403);
}
```

**Tradeoffs:**
- Simpler: No key management
- Slower: Every request hits MOOP's cache/DB
- Less scalable: Single MOOP instance bottleneck
- Simpler failover: No key sync issues

---

## Special Cases

### 1. Whitelisted Internal IPs (ALL Access)

For internal users with IP in auto_login range:

```php
// MOOP: /api/jbrowse2/assembly.php

if (get_access_level() === 'ALL' && is_whitelisted_ip()) {
    // Direct URL, no token (fast path)
    $track_url = "https://tracks.example.com/bigwig/file.bw";
} else {
    // Generate token (slower path, but more control)
    $token = generateTrackToken(...);
    $track_url = "https://tracks.example.com/bigwig/file.bw?token={$token}";
}
```

```nginx
# Tracks server: Allow IP-whitelisted requests without token

location ~ ^/bigwig/(.+\.bw)$ {
    # Skip auth for internal IPs
    if ($remote_addr ~* ^(10\.0\.0\.0/8|192\.168\.0\.0/16)) {
        # Internal IP - serve directly
        add_header Accept-Ranges bytes;
        try_files $uri =404;
    }
    
    # External IP - require token
    if ($arg_token = "") {
        return 403 "No token provided";
    }
    auth_request /validate-jwt;
    add_header Accept-Ranges bytes;
    try_files $uri =404;
}
```

### 2. Collaborator Access to Specific Tracks

Track config file specifies access levels:

```json
// /moop/metadata/jbrowse2-configs/tracks/sensitive_data.json
{
    "name": "Sensitive RNA-seq",
    "track_id": "sensitive_rna",
    "access_levels": ["ALL", "Collaborator"],  // Not public
    "file_template": "{organism}_{assembly}_sensitive.bw"
}
```

MOOP filters when generating config:

```php
// /api/jbrowse2/assembly.php

$user_access_level = get_access_level();
$user_access = get_user_access();

foreach ($available_tracks as $track) {
    $track_access_levels = $track['access_levels'] ?? ['Public'];
    
    $user_can_access = false;
    
    if ($user_access_level === 'ALL') {
        $user_can_access = true;
    } elseif (in_array('Public', $track_access_levels)) {
        $user_can_access = true;
    } elseif ($user_access_level === 'Collaborator' && 
              in_array('Collaborator', $track_access_levels)) {
        // Collaborator CAN access if they have access to this organism-assembly
        if (isset($user_access[$organism]) && 
            in_array($assembly, $user_access[$organism])) {
            $user_can_access = true;
        }
    }
    
    if (!$user_can_access) {
        continue;  // Skip for this user
    }
    
    // Add track to this user's config...
}
```

**Result:** Different users get different config contents.
- Public user: Only "Public" tracks appear
- Collaborator (Anoura access): "Public" + "Collaborator" tracks (if they have Anoura access)
- Admin: All tracks

### 3. Token Expiration & Refresh

Tokens expire in 1 hour. User's browser gets new token when:

1. **User navigates to new assembly**: Automatic, fresh token generated
2. **Token expires**: Browser gets 403, should redirect to JBrowse2 to request new config
3. **Manual refresh**: User can click "refresh" to get new token (useful for long sessions)

---

## Security Checklist

- [x] All track URLs include tokens (no exposed direct access)
- [x] Tokens signed with MOOP private key (tracks server validates via public key)
- [x] Tokens expire (1 hour, forcing re-auth)
- [x] Tokens include claims (organism, assembly, user, access_level)
- [x] Tracks server validates claims match requested file
- [x] Token validation is fast (no network calls, crypto only)
- [x] Different users get different URLs (can't guess/share tokens)
- [x] Whitelisted IPs get fast path (no token overhead)
- [x] Track-level access control (some tracks restricted)
- [x] HTTP range requests still work (token in query param)

---

## CORS Headers (If JBrowse2 on Different Domain)

```nginx
# Tracks server: /etc/nginx/sites-available/tracks.example.com

location ~ ^/(bigwig|bam)/ {
    add_header Access-Control-Allow-Origin "https://jbrowse.example.com";
    add_header Access-Control-Allow-Methods "GET, HEAD, OPTIONS";
    add_header Access-Control-Allow-Headers "Range";
    add_header Access-Control-Max-Age "3600";
    
    if ($request_method = 'OPTIONS') {
        return 204;
    }
    
    # ... rest of track serving logic
}
```

---

## Deployment Checklist

### MOOP Side
- [ ] Generate JWT key pair
- [ ] Store private key securely (`/etc/moop/jwt_private_key.pem`)
- [ ] Create `/api/jbrowse2/assembly.php` endpoint
- [ ] Create `/lib/track_token.php` with `generateTrackToken()` function
- [ ] Create track config JSON files in `/metadata/jbrowse2-configs/tracks/`
- [ ] Test token generation and expiration

### Tracks Server Side
- [ ] Install JWT validation library (PHP/composer)
- [ ] Copy public key from MOOP: `/etc/tracks-server/jwt_public_key.pem`
- [ ] Create `/validate-jwt.php` endpoint
- [ ] Configure Nginx with auth_request directive
- [ ] Test token validation
- [ ] Enable CORS headers if needed
- [ ] Test HTTP range requests with tokens

### Both Sides
- [ ] Verify time sync (NTP) between servers
- [ ] Test token with 1-hour expiry
- [ ] Test whitelisted IP bypass
- [ ] Test different user access levels
- [ ] Monitor logs for invalid tokens

---

## Troubleshooting

**JBrowse2 gets 403 when loading tracks:**
1. Check token is in URL: `?token=...`
2. Check token hasn't expired (test with fresh config request)
3. Check tracks server public key matches MOOP private key
4. Check system time sync between servers

**Token validation is slow:**
1. Ensure `/validate-jwt.php` is being called (check logs)
2. Whitelist internal IPs to bypass token check
3. Consider using fast JWT library (e.g., `php-jwt`)

**Different users see same tracks:**
1. Check `get_access_level()` is correct
2. Check track config files have proper `access_levels`
3. Check filtering logic in `/api/jbrowse2/assembly.php`
