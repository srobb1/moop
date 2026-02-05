# JBrowse2 Integration Documentation Index

Complete planning documentation for integrating JBrowse2 genome browser with MOOP system, including modular track configs, dynamic per-user permission filtering, and secure remote tracks server access.

---

## Quick Start

**New to this project?** Start here:

1. **Read**: [`jbrowse2_quick_reference.md`](jbrowse2_quick_reference.md) (10 min read)
   - Overview of the architecture
   - Three files you need to create
   - Data flow diagram
   - Testing checklist

2. **Implement**: Follow the Quick Reference
   - Generate JWT keys
   - Create `/api/jbrowse2/assembly.php`
   - Create `/lib/track_token.php`
   - Set up tracks server

3. **Deep Dive**: Read full documents for details

---

## Complete Documentation

### [`jbrowse2_integration_plan.md`](jbrowse2_integration_plan.md) (26 KB)

**Full architectural overview and implementation plan.**

Topics:
- JBrowse2 architecture (client-side only, no server computation)
- Modular track config structure (separate files per track)
- Dynamic per-user config generation
- Permission system integration with MOOP
- File organization and storage
- Implementation breakdown (5 steps)
- HTTP range requests (critical for performance)
- Technology stack

**Read this for:**
- Understanding the complete system design
- File organization decisions
- How modular configs work
- Config generation workflow

---

### [`jbrowse2_track_access_security.md`](jbrowse2_track_access_security.md) (12 KB)

**Security implementation details and track access control.**

Topics:
- Remote tracks server security challenge
- **Three approaches to securing tracks:**
  - Approach A: Token-Based Access (JWT) ← **Recommended**
  - Approach B: Proxy All Requests Through MOOP
  - Approach C: IP Whitelist + Session Validation
- JWT implementation with full code examples
- Session token approach (simpler, slower)
- Special cases:
  - Whitelisted internal IPs (ALL users)
  - Collaborator-specific tracks
  - Token expiration & refresh
- Nginx + PHP implementation examples
- Security checklist
- CORS headers for cross-domain JBrowse2
- Deployment checklist
- Troubleshooting guide

**Read this for:**
- Understanding token-based security
- How to implement JWT validation
- Nginx configuration examples
- How to protect tracks from unauthorized access
- Handling different user access levels
- Deployment checklist

---

### [`jbrowse2_track_config_guide.md`](jbrowse2_track_config_guide.md) (10 KB)

**Track configuration file structure, examples, and best practices.**

Topics:
- Track config file structure and schema
- 5 complete example track configs:
  - RNA-seq Coverage (Public BigWig)
  - DNA Alignment (Admin-only BAM)
  - ChIP-seq (Collaborator-only)
  - Annotations (GFF feature track)
  - SNP Calls (VCF variant track)
- Access level reference (Public/Collaborator/ALL)
- Common file formats and adapter types
- Creating new track configs (step-by-step)
- Updating existing tracks
- Organizing tracks by category (hierarchical groups)
- Best practices

**Read this for:**
- Examples of track config JSON files
- How to define access levels per track
- File format recommendations
- How to create new tracks
- Best practices for naming and organizing

---

### [`jbrowse2_quick_reference.md`](jbrowse2_quick_reference.md) (8 KB)

**Quick reference guide for rapid implementation.**

Topics:
- What we're building (overview)
- Three main files to create with pseudocode
- File structure and organization
- Access levels explained
- Request flow (detailed)
- Data flow diagram
- JWT token contents
- Setting up keys (openssl commands)
- Nginx config snippet
- Testing checklist
- Common issues & solutions
- Next steps

**Read this for:**
- Quick overview before diving deep
- Nginx configuration
- Testing procedures
- Troubleshooting common issues

---

## Architecture Summary

### Core Concept

Instead of one monolithic config per organism-assembly, use a **modular, permission-aware system**:

```
Track Configs (separate files)      ┐
├─ rna_seq_coverage.json            │ Stored in
├─ dna_alignment.json               │ /metadata/jbrowse2-configs/tracks/
├─ histone_h3k4me3.json             │
└─ ...                              ┘
        ↓
    Loaded on-demand
        ↓
Dynamic Assembly Config Generator (/api/jbrowse2/assembly.php)
├─ 1. Validate user permission
├─ 2. Filter tracks by access_level
├─ 3. Generate JWT tokens
└─ 4. Return only accessible tracks
        ↓
Different config per user
├─ Public user:    [Public tracks only]
├─ Collaborator:   [Public + Collaborator tracks]
└─ Admin:          [All tracks]
        ↓
Browser loads JBrowse2 with user-specific config
```

### Three Files You Create

1. **`/api/jbrowse2/assembly.php`** (70 lines)
   - Validates permission
   - Filters tracks
   - Generates JWT tokens
   - Returns config

2. **`/lib/track_token.php`** (30 lines)
   - Generates JWT tokens
   - Signs with private key
   - Sets expiration (1 hour)

3. **Tracks Server: `/validate-jwt.php`** (40 lines)
   - Verifies JWT signature
   - Checks expiration
   - Returns 200 or 403

### Security Flow

```
User logs in
    ↓ (session established)
Browser requests assembly config
    ↓
MOOP validates permission + filters tracks
    ↓
Returns config with JWT-tokenized URLs
    ↓
Browser requests: GET /bigwig/file.bw?token=JWT
    ↓
Tracks server validates token
    ↓
Returns bytes (200) or error (403)
    ↓
Browser displays in JBrowse2
```

---

## Key Decisions

| Decision | What | Why |
|----------|------|-----|
| **Modular Tracks** | Separate config file per track | Easy to version, update, add/remove independently |
| **Dynamic Config** | Generated per-user on each request | Different users see different tracks automatically |
| **JWT Tokens** | Signed tokens in track URLs | Stateless, secure, scalable, can't be forged |
| **Token Expiry** | 1 hour | Forces re-authentication, reasonable UX |
| **Separate Server** | Tracks server independent | Isolates large files, better scaling |
| **IP Whitelist** | Internal users skip token validation | Fast path for trusted internal network |

---

## File Structure

```
/moop/
├── api/
│   └── jbrowse2/
│       ├── assembly.php              (to create)
│       ├── validate-token.php        (optional)
│       └── generate_track_configs.php (optional)
├── lib/
│   └── track_token.php               (to create)
├── metadata/
│   └── jbrowse2-configs/
│       └── tracks/
│           ├── rna_seq_coverage.json     (to create)
│           ├── dna_alignment.json        (to create)
│           └── ... (one per track type)
├── data/
│   └── genomes/
│       ├── Anoura_caudifer/GCA_004027475.1/
│       │   ├── reference.fasta
│       │   ├── reference.fasta.fai
│       │   ├── annotations.gff3.gz
│       │   └── annotations.gff3.gz.tbi
│       └── ...
└── certs/
    ├── jwt_private_key.pem           (to generate)
    └── jwt_public_key.pem            (to generate)

/tracks-server/
├── etc/tracks-server/
│   └── jwt_public_key.pem            (copy from MOOP)
└── var/tracks/data/
    ├── bigwig/
    │   ├── Anoura_caudifer_GCA_004027475.1_rna_coverage.bw
    │   └── ...
    ├── bam/
    │   ├── Anoura_caudifer_GCA_004027475.1_dna.bam
    │   ├── Anoura_caudifer_GCA_004027475.1_dna.bam.bai
    │   └── ...
    └── ...
```

---

## Implementation Phases

### Phase 1: Setup (2-3 hours)
- [ ] Generate JWT key pair
- [ ] Create track config files (3-5 examples)
- [ ] Set up tracks server directory structure
- [ ] Copy public key to tracks server

### Phase 2: MOOP Code (4-6 hours)
- [ ] Create `/api/jbrowse2/assembly.php`
- [ ] Create `/lib/track_token.php`
- [ ] Add JWT library to composer
- [ ] Test token generation

### Phase 3: Tracks Server (3-4 hours)
- [ ] Create `/validate-jwt.php`
- [ ] Configure Nginx auth_request
- [ ] Install JWT validation library
- [ ] Test token validation

### Phase 4: Integration (2-3 hours)
- [ ] Deploy JBrowse2 static files
- [ ] Configure JBrowse2 to load from API
- [ ] End-to-end testing
- [ ] Different user scenarios

### Phase 5: Production (1-2 hours)
- [ ] Monitor logs for token issues
- [ ] Set up alerting
- [ ] Document operations
- [ ] Training/documentation for admins

**Total: 12-18 hours** (depends on familiarity with JWT, Nginx, JBrowse2)

---

## Common Questions

**Q: Can I skip the token approach and just hide tracks with access control?**
A: No. Browser can see all tracks in config; without tokens, user could discover hidden track URLs.

**Q: What if token expires while user is browsing?**
A: Browser gets 403, should refresh config. Add refresh button or auto-refresh on 403.

**Q: Why separate files per track instead of one config per assembly?**
A: Easier to manage. Add/remove/update tracks without regenerating full config.

**Q: Can I use simple session tokens instead of JWT?**
A: Yes, see Approach B in track access security doc. Simpler but slower (hits MOOP cache for validation).

**Q: Do I need separate JBrowse2 instances per user?**
A: No. One JBrowse2 instance, different users load different configs (permission-filtered).

**Q: How do I add a new track?**
A: Create JSON file in `/metadata/jbrowse2-configs/tracks/`, upload data file to tracks server, done. No restart needed.

---

## Related MOOP Documentation

- `includes/access_control.php` - User session & access level management
- `lib/functions_access.php` - `getAccessibleAssemblies()` function
- `metadata/organism_assembly_groups.json` - Org-assembly-to-group mappings

---

## Support & Troubleshooting

See **jbrowse2_track_access_security.md** for:
- Token validation failures
- CORS issues
- Token expiration handling
- Different user access levels not working
- HTTP range request failures

---

## Summary

This is a **production-ready architecture** for secure, scalable JBrowse2 integration with MOOP:

✅ Different users see different tracks  
✅ Secure token-based track access  
✅ No direct file access without permission  
✅ Tokens can't be forged  
✅ Modular, easy to maintain  
✅ HTTP range requests work (critical!)  
✅ Handles Public/Collaborator/ALL access levels  
✅ IP whitelisting for internal users  

**Ready to implement?** Start with `jbrowse2_quick_reference.md`!
