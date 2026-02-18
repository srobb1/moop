# JBrowse2 Genome Browser - MOOP Integration

**Version:** 3.0  
**Status:** Production Ready  
**Last Updated:** February 18, 2026

---

## Overview

JBrowse2 is integrated into MOOP to provide dynamic, permission-based genome browsing with secure track access. The system generates configurations dynamically based on user authentication, enabling fine-grained access control while using standard JBrowse2 components.

### Key Features

- ✅ **Dynamic Configuration** - Per-user configs generated with permission filtering
- ✅ **JWT Security** - RS256 tokens for track authentication (updated Feb 2026)
- ✅ **Access Levels** - PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN
- ✅ **Google Sheets Integration** - Manage tracks via spreadsheet
- ✅ **Zero-Copy Policy** - No file duplication (50% storage savings)
- ✅ **External URLs** - Support for UCSC, Ensembl public data (no token leakage)
- ✅ **Remote Tracks Servers** - Scalable, stateless JWT validation
- ✅ **Multiple Track Types** - BigWig, BAM, VCF, GFF, CRAM, synteny, and more

### Recent Updates (Feb 2026)

**Security Improvements:**
1. All users now receive JWT tokens (including IP-whitelisted)
2. External + PUBLIC URLs skip tokens (prevents leakage to UCSC, etc.)
3. Whitelisted IPs can use expired tokens (convenience without compromising security)
4. Enhanced logging and audit trail for all track access

### Access URL

- **Main Interface:** `/moop/jbrowse2.php`
- **Config API:** `/moop/api/jbrowse2/config.php`
- **Tracks Server:** `/moop/api/jbrowse2/tracks.php`

---

## Quick Start

### For End Users

1. Navigate to JBrowse2 in MOOP
2. Browse available assemblies (filtered by your access level)
3. Click an assembly to open genome viewer
4. Pan, zoom, search, and explore genome features
5. Enable/disable tracks, adjust visibility

**→ See [USER_GUIDE.md](USER_GUIDE.md) for detailed instructions.**

### For Administrators

1. Prepare genome files with `setup_jbrowse_assembly.sh`
2. Add tracks via Google Sheets or manual metadata
3. Set access levels (PUBLIC, COLLABORATOR, ADMIN)
4. Assembly automatically appears for users with access

**→ See [ADMIN_GUIDE.md](ADMIN_GUIDE.md) for complete setup instructions.**

### For Developers

1. Study architecture in [technical/](technical/) docs
2. Understand metadata-driven configuration
3. Review JWT token generation and validation
4. Learn permission filtering logic

**→ See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for architecture details.**

### For IT/DevOps

1. Deploy remote tracks servers
2. Configure JWT keys and CORS
3. Set up monitoring and alerts
4. Handle key rotation

**→ See [technical/TRACKS_SERVER_IT_SETUP.md](technical/TRACKS_SERVER_IT_SETUP.md) for deployment guide.**

---

## Documentation Structure

This documentation is organized into the following sections:

### Core Guides (This Directory)
- **[README.md](README.md)** - This file (overview and quick start)
- **[USER_GUIDE.md](USER_GUIDE.md)** - For end users browsing genomes
- **[ADMIN_GUIDE.md](ADMIN_GUIDE.md)** - For administrators managing assemblies
- **[DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)** - For developers and integrators
- **[SETUP_NEW_ORGANISM.md](SETUP_NEW_ORGANISM.md)** - Complete workflow for adding organisms

### Reference Documentation
📁 **[reference/](reference/)** - Track types, formats, and API reference
- Supported formats and track types
- Multi-BigWig combo tracks
- Synteny tracks (PAF, MAF, MCScan)
- Color groups and API reference

### Technical Documentation ⭐
📁 **[technical/](technical/)** - Security, architecture, and deployment

**Start here for JBrowse2 developers:**
- **[SECURITY.md](technical/SECURITY.md)** ⭐ Complete security architecture (RS256 JWT, access control)
- **[dynamic-config-and-jwt-security.md](technical/dynamic-config-and-jwt-security.md)** ⭐ How configs are generated
- **[TRACKS_SERVER_IT_SETUP.md](technical/TRACKS_SERVER_IT_SETUP.md)** ⭐ Deploy remote tracks servers
- **[README.md](technical/README.md)** - Technical docs overview

**Additional technical docs:**
- NO_COPY_FILE_HANDLING.md - Zero-copy policy
- AUTO_CONFIG_GENERATION.md - Config generation system
- TEXT_SEARCH_INDEXING.md - Search implementation

### Workflows
📁 **[workflows/](workflows/)** - Step-by-step procedures
- **GOOGLE_SHEETS_WORKFLOW.md** ⭐ Manage tracks via spreadsheet
- Example templates and automation

### Archive
📁 **[archive/](archive/)** - Historical docs and examples
- Legacy documentation
- Session notes and code reviews
- Examples (Nematostella vectensis)

---

## Access Control

### Access Level Hierarchy

```
ADMIN (4)           → Sees everything, manages all content
    ↓
IP_IN_RANGE (3)     → Internal network users, can use expired tokens
    ↓
COLLABORATOR (2)    → Logged-in users with explicit assembly access
    ↓
PUBLIC (1)          → Anonymous users, see only PUBLIC assemblies/tracks
```

### How It Works

1. **Session Authentication** - PHP session determines user access level
2. **Assembly Filtering** - Config API filters assemblies by `defaultAccessLevel`
3. **Track Filtering** - Tracks filtered by `access_level` metadata
4. **JWT Validation** - Tracks server validates tokens with organism/assembly claims

**Updated Feb 2026:** All users now get JWT tokens. IP whitelisted users can use expired tokens for convenience.

### Assembly Access Levels

Set in assembly definition JSON:

```json
{
  "defaultAccessLevel": "PUBLIC"  // PUBLIC, COLLABORATOR, ADMIN
}
```

### Track Access Levels

Set in track definition JSON:

```json
{
  "access_levels": ["Public", "Collaborator", "ALL"]
}
```

---

## Architecture

### Components

```
┌─────────────────┐
│   User Browser  │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  jbrowse2.php   │  ← Main UI (checks session)
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  get-config.php │  ← API (filters assemblies)
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  Metadata JSON  │  ← Assembly/track definitions
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  JBrowse2 Core  │  ← Renders genome view
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  Tracks Server  │  ← Serves BigWig/BAM (JWT validated)
└─────────────────┘
```

### Data Flow

1. User visits `jbrowse2.php`
2. JavaScript calls `get-config.php` with session cookie
3. API reads user's access level from session
4. API scans `/metadata/jbrowse2-configs/assemblies/*.json`
5. API filters assemblies by `defaultAccessLevel`
6. API returns personalized config
7. JBrowse2 renders accessible assemblies
8. Track requests include JWT token for validation

---

## File Structure

```
/data/moop/
├── docs/JBrowse2/
│   ├── README.md                    ← This file
│   ├── USER_GUIDE.md                ← For end users
│   ├── ADMIN_GUIDE.md               ← For administrators
│   ├── DEVELOPER_GUIDE.md           ← For developers
│   ├── API_REFERENCE.md             ← API documentation
│   └── SECURITY.md                  ← Security architecture
│
├── jbrowse2.php                     ← Main entry point
├── tools/pages/jbrowse2.php         ← UI template
├── js/jbrowse2-loader.js            ← Frontend loader
│
├── api/jbrowse2/
│   ├── get-config.php               ← Main API endpoint
│   ├── assembly.php                 ← Assembly-specific config
│   └── fake-tracks-server.php       ← Test tracks server
│
├── metadata/jbrowse2-configs/
│   ├── assemblies/                  ← Assembly definitions
│   │   └── {organism}_{assembly}.json
│   └── tracks/                      ← Track definitions
│       └── {track_name}.json
│
├── data/
│   ├── genomes/{organism}/{assembly}/
│   │   ├── reference.fasta          ← Symlink to genome
│   │   ├── reference.fasta.fai      ← FASTA index
│   │   ├── annotations.gff3.gz      ← Compressed annotations
│   │   └── annotations.gff3.gz.tbi  ← GFF index
│   └── tracks/                      ← Track data files
│       └── {organism}_{assembly}_{track}.{bw,bam}
│
├── lib/jbrowse/
│   └── track_token.php              ← JWT generation/validation
│
└── tools/jbrowse/
    ├── setup_jbrowse_assembly.sh    ← Phase 1: Prepare files
    ├── add_assembly_to_jbrowse.sh   ← Phase 2: Register assembly
    ├── bulk_load_assemblies.sh      ← Bulk processing
    └── README.md                    ← Scripts documentation
```

---

## Documentation Guide

| Document | Audience | Purpose |
|----------|----------|---------|
| [README.md](README.md) | Everyone | Overview and quick reference |
| [USER_GUIDE.md](USER_GUIDE.md) | End Users | How to use JBrowse2 |
| [ADMIN_GUIDE.md](ADMIN_GUIDE.md) | Admins | Setup and maintenance |
| [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) | Developers | Architecture and API |
| [API_REFERENCE.md](API_REFERENCE.md) | Developers | API endpoints |
| [SECURITY.md](SECURITY.md) | Admins/Devs | Security architecture |

---

## Quick Reference Commands

### Add a New Assembly

```bash
# Step 1: Prepare files
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Organism/Assembly

# Step 2: Register in JBrowse2
./tools/jbrowse/add_assembly_to_jbrowse.sh Organism Assembly
```

### Bulk Load Multiple Assemblies

```bash
# Auto-discover and load all
./tools/jbrowse/bulk_load_assemblies.sh --auto-discover --organisms /organisms
```

### Test Assembly Access

```bash
curl -s "http://localhost:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq .
```

### Generate JWT Keys

```bash
cd /data/moop/certs
openssl genrsa -out jwt_private_key.pem 4096
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
chmod 600 jwt_private_key.pem
chmod 644 jwt_public_key.pem
```

---

## Troubleshooting

### Assembly Not Showing

1. Check metadata exists: `ls /data/moop/metadata/jbrowse2-configs/assemblies/`
2. Validate JSON: `jq . /data/moop/metadata/jbrowse2-configs/assemblies/Organism_Assembly.json`
3. Check access level matches user permissions
4. Clear browser cache

### Tracks Not Loading

1. Check JWT keys exist: `ls /data/moop/certs/jwt_*.pem`
2. Verify token generation: Check browser console (F12)
3. Test tracks server: `curl -I "http://localhost:8888/api/jbrowse2/fake-tracks-server.php?file=bigwig/test.bw"`
4. Check token expiry (1 hour default)

### Permission Denied

1. Verify user is logged in: Check session
2. Check assembly `defaultAccessLevel` in metadata
3. Verify user's `access_level` in session
4. Check admin flag: `$_SESSION['is_admin']`

---

## Next Steps

- [ ] Add more assemblies to system
- [ ] Configure remote tracks server
- [ ] Add annotation tracks (GFF3)
- [ ] Set up automated backups
- [ ] Add user favorites/recent assemblies

---

## Support

- **Scripts Documentation:** [tools/jbrowse/README.md](../../tools/jbrowse/README.md)
- **Implementation Review:** [IMPLEMENTATION_REVIEW.md](IMPLEMENTATION_REVIEW.md)
- **Archive:** [archive/](archive/) (historical documentation)

---

**Project Status:** ✅ Production Ready  
**Maintainer:** MOOP Team  
**JBrowse2 Version:** 2.x
