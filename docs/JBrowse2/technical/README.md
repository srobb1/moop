# JBrowse2 Technical Documentation - MOOP Integration

**Last Updated:** February 18, 2026

This directory contains technical documentation for MOOP's JBrowse2 integration, focused on security architecture, dynamic configuration generation, and deployment infrastructure.

---

## 📚 Core Documentation (Start Here)

### For JBrowse2 Community & Security Reviewers

1. **[SECURITY.md](SECURITY.md)** ⭐  
   Complete security architecture including RS256 JWT authentication, multi-layer access control, and threat model. Start here for understanding how MOOP secures genomic data.

2. **[dynamic-config-and-jwt-security.md](dynamic-config-and-jwt-security.md)** ⭐  
   Technical deep-dive into dynamic configuration generation, permission filtering, and JWT token integration. Explains how configs are built per-user.

3. **[TRACKS_SERVER_IT_SETUP.md](TRACKS_SERVER_IT_SETUP.md)** ⭐  
   Complete setup guide for deploying secure tracks servers. For IT administrators and DevOps engineers.

---

## 🔒 Security Model Overview

MOOP implements **4-layer security** for JBrowse2:

### Layer 1: Session Authentication
- PHP session-based login
- Access levels: `PUBLIC` < `COLLABORATOR` < `IP_IN_RANGE` < `ADMIN`
- IP-based auto-authentication for internal networks

### Layer 2: Assembly Filtering  
- Dynamic config generation filters assemblies by `defaultAccessLevel`
- Users only see assemblies they're authorized to access
- Server-side filtering (not client-side)

### Layer 3: Track Filtering
- Track metadata defines `access_level` per track
- Config API filters tracks during generation
- COLLABORATOR users verified against explicit permissions

### Layer 4: JWT Track Authentication
- RS256 asymmetric signatures (2048-bit RSA)
- Tokens scoped to organism/assembly pair
- 1-hour expiration with claims validation
- Stateless tracks servers (no database needed)

**Key Innovation:** Private key stays on MOOP server (signs tokens), public key deployed to tracks servers (verifies tokens). Compromised tracks server cannot forge tokens.

---

## 🏗️ Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    MOOP Web Server                          │
│              • Session-based authentication                 │
│              • Dynamic config generation                    │
│              • JWT token signing (RS256)                    │
│              • Private key stored securely                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ HTTPS (config API with embedded JWT tokens)
                       ↓
┌─────────────────────────────────────────────────────────────┐
│                  JBrowse2 Client (Browser)                  │
│              • Standard React Linear Genome View            │
│              • No custom patches or forks                   │
│              • Fetches filtered configs                     │
│              • Tracks URLs include JWT tokens               │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ HTTPS (track data requests with JWT)
                       ↓
┌─────────────────────────────────────────────────────────────┐
│                   Tracks Server(s)                          │
│              • JWT validation (RS256)                       │
│              • Public key only (verifies tokens)            │
│              • Organism/assembly claims verification        │
│              • HTTP range request support                   │
│              • NO database, NO sessions - stateless         │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Metadata-Driven System

All assemblies and tracks defined in JSON metadata files:

```
metadata/jbrowse2-configs/
├── assemblies/
│   └── Organism_Assembly.json          # Assembly definitions
└── tracks/
    └── Organism/Assembly/type/
        └── track.json                   # Track definitions
```

**Benefits:**
- ✅ No hard-coded configs
- ✅ Easy to add/remove tracks (just add/delete JSON)
- ✅ Google Sheets integration for bulk track management
- ✅ Permission metadata drives access control

---

## 🔑 JWT Token Flow

1. **User requests assembly config**  
   `GET /api/jbrowse2/config.php?organism=X&assembly=Y`

2. **MOOP validates permissions**  
   Session check → Assembly access → Track filtering

3. **MOOP generates JWT token**  
   Token claims: `{organism: X, assembly: Y, user_id: ..., exp: now+3600}`  
   Signed with RS256 private key

4. **MOOP injects token into track URLs**  
   ```json
   "uri": "/api/jbrowse2/tracks.php?file=X/Y/sample.bw&token=eyJhbGc..."
   ```

5. **JBrowse2 requests track data**  
   Browser fetches track file using URI (with embedded token)

6. **Tracks server validates token**  
   Verify RS256 signature → Check expiration → Validate organism/assembly match → Serve file

---

## 🚀 Key Features

### ✅ Standards-Compliant
- Uses unmodified JBrowse2 React components
- Standard JBrowse2 config format
- HTTP range request support for efficient streaming

### ✅ Secure by Design
- Multi-layer defense in depth
- Server-side permission filtering (not client-side)
- Stateless authentication (JWT)
- Asymmetric cryptography (RS256)

### ✅ Scalable
- Stateless tracks servers (horizontally scalable)
- No database required on tracks servers
- Gzip compression for large configs
- Optional lazy-loading for >1000 tracks

### ✅ Flexible Access Control
- Fine-grained per-track permissions
- Per-assembly user access
- IP whitelisting for internal networks
- Time-limited tokens (1-hour expiry)

---

## 📖 Additional Documentation

### Configuration & Automation
- **[AUTO_CONFIG_GENERATION.md](AUTO_CONFIG_GENERATION.md)** - How configs are generated from metadata
- **[ACCESS_CONTROL_UPDATE.md](ACCESS_CONTROL_UPDATE.md)** - Access control implementation details

### File Management
- **[NO_COPY_FILE_HANDLING.md](NO_COPY_FILE_HANDLING.md)** - Zero-copy policy (tracks used in-place)
- **[File_Patterns_Configuration.md](File_Patterns_Configuration.md)** - File pattern matching system

### Advanced Features  
- **[TEXT_SEARCH_INDEXING.md](TEXT_SEARCH_INDEXING.md)** - Text search indexing for gene/feature search
- **[FULLSCREEN_IMPLEMENTATION.md](FULLSCREEN_IMPLEMENTATION.md)** - Fullscreen mode
- **[session-sharing-security-analysis.md](session-sharing-security-analysis.md)** - Session sharing analysis

---

## 🎯 Quick Start Guide

**For Developers:**
1. Read [dynamic-config-and-jwt-security.md](dynamic-config-and-jwt-security.md) to understand the config API
2. Review metadata format examples
3. Check [AUTO_CONFIG_GENERATION.md](AUTO_CONFIG_GENERATION.md) for adding tracks

**For Security Auditors:**
1. Read [SECURITY.md](SECURITY.md) for complete security model
2. Review threat model and mitigations
3. Check JWT implementation details

**For IT/DevOps:**
1. Follow [TRACKS_SERVER_IT_SETUP.md](TRACKS_SERVER_IT_SETUP.md) for deployment
2. Review testing procedures
3. Set up monitoring and alerts

---

## 🔗 Related Documentation

- [Main JBrowse2 Docs](../) - User guides, workflows, reference
- [Developer Guide](../DEVELOPER_GUIDE.md) - Development workflow
- [Setup Guide](../SETUP_NEW_ORGANISM.md) - Adding new organisms

---

## 💡 Key Concepts

### Access Level Hierarchy
```
ADMIN (4)           → Sees everything
    ↓
IP_IN_RANGE (3)     → Sees everything, no JWT tokens (whitelisted IPs)
    ↓
COLLABORATOR (2)    → Sees PUBLIC + explicitly granted assemblies
    ↓
PUBLIC (1)          → Sees only PUBLIC assemblies/tracks
```

### Token Scoping
Each JWT token is locked to a specific `organism/assembly` pair. Cannot be reused across different assemblies.

**Example:**
```
Token for: Nematostella_vectensis / GCA_033964005.1
✅ Can access: Nematostella_vectensis/GCA_033964005.1/bigwig/sample.bw
❌ Cannot access: Other_organism/Other_assembly/bigwig/other.bw
```

### Stateless Validation
Tracks servers validate requests purely from JWT tokens - no database queries, no session checks, no shared state. This enables:
- Horizontal scaling (add servers without coordination)
- High performance (no database bottleneck)
- Security (compromised tracks server can't forge tokens)

---

## 📞 Support

**Security issues:** Report immediately to MOOP administrator  
**Technical questions:** See main documentation or contact development team  
**JBrowse2 questions:** Check [JBrowse2.org](https://jbrowse.org/jb2/) documentation

---

**Documentation Version:** 3.0  
**MOOP Integration:** Production Ready  
**JBrowse2 Version:** 2.x (React Linear Genome View)  
**Last Review:** February 18, 2026
