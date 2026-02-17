# Dynamic JBrowse2 Configuration & JWT-Secured Track Access

## Overview

This document explains the technical implementation of MOOP's dynamic JBrowse2 configuration generation and JWT-based track request authentication system.

---

## 1. Dynamic Configuration Generation

### Server-Side Rendering (SSR)

The system uses PHP to dynamically generate JBrowse2 configurations based on user authentication state, rather than serving static JSON files.

### Session-Based Authentication

When you access `/moop/jbrowse2.php`, the server checks your session (`$_SESSION`) to determine:
- `logged_in`: Boolean indicating authentication status
- `username`: Your user identifier
- `access_level`: Your permission tier (Public/Collaborator/ALL)
- `is_admin`: Boolean for administrative privileges

### Modular Metadata System

Assembly and track definitions are stored as JSON files in `/metadata/jbrowse2-configs/`:
- `assemblies/*.json`: Reference genome definitions with access level requirements
- `tracks/*.json`: Track metadata (BAM, BigWig files) with access permissions

### Access Filtering Pipeline

The `/api/jbrowse2/config.php` endpoint implements the following pipeline:

1. **Permission Validation**: Calls `getAccessibleAssemblies()` to verify you can view the requested organism/assembly

2. **Assembly Loading**: Reads the assembly definition from metadata files

3. **Track Discovery**: Scans all available track definitions

4. **Access Control Logic**: Filters tracks based on hierarchical permissions:
   - `ADMIN`: Sees everything
   - `IP_IN_RANGE`: Sees everything (whitelisted IPs)
   - `COLLABORATOR`: Sees PUBLIC + specific assemblies they're granted access to
   - `PUBLIC`: Sees only public tracks

5. **Token Generation**: Generates JWT tokens for non-whitelisted IPs

6. **Config Assembly**: Constructs complete JBrowse2 JSON config with only authorized content

---

## 2. JWT-Based Track Request Security

### JWT (JSON Web Token)

A cryptographically signed token containing user claims (identity, permissions, expiration). It's stateless—the tracks server doesn't need to query a database.

### Asymmetric Cryptography (RS256) ✅

**Current Implementation:** Uses RSA public/private key pair (2048-bit)

- **Private Key** (`/certs/jwt_private_key.pem`): Kept secret on the MOOP server, used to *sign* tokens
- **Public Key** (`/certs/jwt_public_key.pem`): Can be shared with the tracks server, used to *verify* tokens

**Security Benefits:**
- ✅ Tracks server cannot forge tokens (needs private key to sign)
- ✅ Safe to deploy public key to multiple tracks servers
- ✅ Compromised tracks server cannot create valid tokens
- ✅ Private key never leaves MOOP server

### Token Generation (Current Implementation)

The `generateTrackToken()` function in `/lib/jbrowse/track_token.php` creates tokens:

```php
$token_data = [
    'user_id' => $_SESSION['username'],
    'organism' => $organism,
    'assembly' => $assembly,
    'access_level' => $access_level,
    'iat' => time(),                    // Issued At timestamp
    'exp' => time() + 3600              // Expires in 1 hour
];
$jwt = JWT::encode($token_data, $private_key, 'RS256');
```

**Token Claims Explained:**
- `user_id`: Username for audit logging
- `organism`/`assembly`: Restricts token to specific genome
- `access_level`: Permission tier for authorization
- `iat`: Issued at timestamp (for debugging/logging)
- `exp`: Expiration timestamp (security: limits token lifetime)

### Track URL Construction

When building track URLs in `config.php`, tokens are appended:

```
http://127.0.0.1:8888/tracks/bigwig/organism_assembly_track.bw?token={JWT}
```

### Token Verification (Current Implementation)

The `/api/jbrowse2/tracks.php` endpoint performs:

1. **Extract Token**: Reads `?token=` query parameter from track request

2. **Cryptographic Validation**: Uses public key (RS256) to verify the signature—only tokens signed by the matching private key are valid

3. **Expiration Check**: Ensures current time < `exp` timestamp (prevents replay attacks with old tokens)

4. **Claim Validation**: Verifies the token's `organism`/`assembly` matches the requested file path (prevents token reuse for unauthorized data)

5. **Range Request Support**: Serves file with HTTP range headers for efficient seeking in large genomic files

**Path Validation:**
```php
// File path format: organism/assembly/type/filename (or any structure after assembly)
$file_parts = explode('/', $file);

if (count($file_parts) < 2) {
    http_response_code(400);
    exit;
}

$file_organism = $file_parts[0];
$file_assembly = $file_parts[1];

// Verify token organism/assembly matches file path
if ($token_data->organism !== $file_organism || 
    $token_data->assembly !== $file_assembly) {
    http_response_code(403);
    exit;
}
```

---

## 3. Security Properties

### 🔐 Authentication ✅
JWTs prove the user was authenticated at token generation time—forged tokens fail RS256 signature verification.

### 🕐 Time-Limited Access ✅
1-hour expiration (`exp` claim) limits exposure if a token is compromised. Users must re-authenticate to get new tokens.

### 🎯 Scope Restriction ✅
Tokens are bound to specific `organism`/`assembly` pairs and validated against file paths—can't use a token for one genome to access another.

### 🔒 Cryptographic Integrity ✅
RS256 asymmetric signature ensures tokens can't be tampered with or forged. Changing any claim (like upgrading `access_level`) invalidates the signature. Only the private key holder (MOOP server) can create valid tokens.

### 🌐 Distributed Architecture ✅
The tracks server doesn't need direct database access or shared sessions—it only needs the public key to verify tokens independently.

### 🛡️ Path Traversal Protection ✅
The tracks server validates file paths to prevent `../` directory traversal attacks and ensures paths start with valid organism/assembly.

### 📍 IP Whitelisting ✅
Internal IPs (10.x.x.x, 192.168.x.x, 127.x.x.x) bypass token requirements—useful for trusted networks while enforcing security for external collaborators.

### 🚫 Stateless Verification ✅
The tracks server doesn't maintain sessions or connection to the auth database—it verifies requests purely from the cryptographic token, making it horizontally scalable.

---

## 4. Request Flow Diagram

```
User Browser                 MOOP Server              Tracks Server
     |                           |                          |
     |---(1) GET /jbrowse2.php-->|                          |
     |                           |                          |
     |<--(2) HTML + JS ----------|                          |
     |                           |                          |
     |---(3) GET /api/jbrowse2/config.php?organism=X&assembly=Y
     |                           |                          |
     |                      [Check session]                 |
     |                      [Load metadata]                 |
     |                      [Filter tracks]                 |
     |                      [Generate JWT]                  |
     |                           |                          |
     |<--(4) JSON config with----|                          |
     |       track URLs + tokens |                          |
     |                           |                          |
     |---(5) GET tracks.php?file=track.bw&token=JWT---->    |
     |                           |                          |
     |                           |                   [Verify JWT]
     |                           |                   [Validate claims]
     |                           |                   [Check expiration]
     |                           |                          |
     |<--(6) Binary track data--------------------------|   |
     |                           |                          |
```

---

## 5. Key Files

### Configuration Generation
- `/jbrowse2.php` - Main entry point with MOOP layout
- `/api/jbrowse2/config.php` - Primary endpoint: Returns filtered assembly list OR complete JBrowse2 config with tokens
- `/api/jbrowse2/config-optimized.php` - Optimized endpoint for >1000 tracks (available but not currently used)
- `/js/jbrowse2-loader.js` - Client-side assembly list and loader

### JWT System
- `/lib/jbrowse/track_token.php` - Token generation and verification functions
- `/certs/jwt_private_key.pem` - RSA private key (secret, for signing)
- `/certs/jwt_public_key.pem` - RSA public key (for verification)

### Track Server (Current Implementation)
- `/api/jbrowse2/tracks.php` - Track file server with RS256 JWT verification and claim validation (critical infrastructure)

### Metadata
- `/metadata/jbrowse2-configs/assemblies/*.json` - Assembly definitions
- `/metadata/jbrowse2-configs/tracks/*.json` - Track definitions

---

## 6. Production Deployment Considerations

### Separate Tracks Server

In production, the tracks server should run on separate infrastructure:

1. **Copy public key** to tracks server: `/path/to/jwt_public_key.pem`
2. **Update track URLs** in config.php to point to external server
3. **Configure CORS** headers on tracks server for JBrowse2 access
4. **Enable HTTPS** for secure token transmission
5. **Monitor token verification logs** for security auditing

### Token Management

- **Rotation**: Regenerate key pairs periodically (requires coordinated deployment)
- **Expiration**: Adjust token lifetime based on security requirements
- **Refresh**: Implement token refresh endpoint for long sessions
- **Revocation**: For compromised tokens, key rotation is the primary mitigation

### Performance Optimization

- **Caching**: Enable HTTP caching headers for track data (immutable genomic data)
- **CDN**: Use CDN for tracks server if serving external collaborators globally
- **Connection Pooling**: Tracks server should reuse database connections
- **Range Request Optimization**: Ensure efficient byte-range serving for large files

---

## 7. Security Best Practices

### Key Management
- Store private keys with restricted file permissions (600)
- Never commit keys to version control
- Use different keys for dev/staging/production
- Store keys outside web root directory

### Token Security
- Always use HTTPS in production (prevents token interception)
- Log token verification failures for security monitoring
- Implement rate limiting on token endpoints
- Consider shorter expiration times for sensitive data

### Access Control
- Regularly audit user access levels
- Implement "least privilege" principle
- Monitor for unusual access patterns
- Maintain audit logs of data access

---

## 8. Troubleshooting

### Token Verification Fails
- Check server time synchronization (JWT exp claim is time-based)
- Verify public/private key pair matches
- Check file permissions on key files
- Review error logs for specific JWT library errors

### Tracks Not Loading
- Inspect browser console for CORS errors
- Verify token is present in track URLs
- Check tracks server logs for verification errors
- Ensure file paths are correct and accessible

### Access Denied
- Verify user session is active
- Check access_level in session matches assembly requirements
- Review getAccessibleAssemblies() logic
- Confirm metadata files have correct access_levels

---

## 9. System Architecture Notes

### Current Implementation (as of 2026-02-17)

The system uses **`config.php`** as the primary configuration endpoint (consolidated on 2026-02-14):

**Single Endpoint Pattern:**
- `GET /api/jbrowse2/config.php` - Returns filtered assembly list
- `GET /api/jbrowse2/config.php?organism=X&assembly=Y` - Returns complete JBrowse2 config with tracks

**Benefits:**
- Single security implementation point
- Consistent permission filtering
- Simplified maintenance
- Clear API contract

**Available Alternative:**
- `config-optimized.php` - Ready for assemblies with >1000 tracks using lazy-loading pattern

**Historical Note:**
The original `assembly.php` endpoint was consolidated into `config.php` on 2026-02-14 to streamline the API architecture. All functionality remains the same - only the endpoint name changed.

---

**Last Updated**: 2026-02-17

**Current Status:**
- ✅ RS256 asymmetric JWT implementation
- ✅ Token claims validation (organism/assembly matching)
- ✅ IP whitelisting for internal networks
- ✅ HTTP range request support
- ✅ Directory traversal protection
- ✅ 1-hour token expiry
