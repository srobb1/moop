# JBrowse2 Genome Browser - MOOP Integration

**Version:** 1.0  
**Status:** Production Ready  
**Last Updated:** February 12, 2026

---

## Overview

JBrowse2 is integrated into MOOP to provide dynamic, permission-based genome browsing. Users see different assemblies and tracks based on their authentication level.

### Key Features

- ✅ **Google Sheets Integration** - Manage tracks via spreadsheet
- ✅ **Zero-Copy Policy** - No file duplication (50% storage savings)
- ✅ **Dynamic Configuration** - Assemblies loaded based on permissions
- ✅ **JWT Authentication** - Secure track access
- ✅ **Access Levels** - Public, Collaborator, and Admin tiers
- ✅ **Multiple Track Types** - BigWig, BAM, VCF, GFF, synteny, and more

### Access URL

- **Main Interface:** http://localhost:8000/moop/jbrowse2.php
- **API Endpoint:** http://localhost:8000/moop/api/jbrowse2/get-config.php

---

## Quick Start

### For End Users

1. Navigate to JBrowse2 in MOOP
2. Browse available assemblies (filtered by your access level)
3. Click "View Genome" to open assembly
4. Pan, zoom, and explore genome features

See [USER_GUIDE.md](USER_GUIDE.md) for detailed instructions.

### For Administrators

1. Prepare genome files with `setup_jbrowse_assembly.sh`
2. Register assembly with `add_assembly_to_jbrowse.sh`
3. Assembly automatically appears for users with appropriate access

See [ADMIN_GUIDE.md](ADMIN_GUIDE.md) for complete setup instructions.

### For Developers

1. Assembly definitions in `/metadata/jbrowse2-configs/assemblies/`
2. Track definitions in `/metadata/jbrowse2-configs/tracks/`
3. API reads metadata and filters by user session
4. JWT tokens secure track file access

See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for architecture details.

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

### Technical Documentation
📁 **[technical/](technical/)** - Implementation details and infrastructure
- **NO_COPY_FILE_HANDLING.md** ⭐ Zero-copy policy
- Security and access control
- Config generation and caching
- Remote tracks server setup

### Workflows
📁 **[workflows/](workflows/)** - Step-by-step workflows
- **GOOGLE_SHEETS_WORKFLOW.md** ⭐ Google Sheets integration
- Example Google Sheet template

### Archive
📁 **[archive/](archive/)** - Historical docs and examples
- Nematostella vectensis example
- Session notes and code reviews
- Legacy documentation

---

## Access Control

### User Types

| User Type | Access Level | Sees |
|-----------|-------------|------|
| Anonymous | Public | Public assemblies only |
| Logged In | Collaborator | Public + Collaborator assemblies |
| Admin | ALL | All assemblies |

### Assembly Access Levels

Set in assembly definition JSON:

```json
{
  "defaultAccessLevel": "Public"  // or "Collaborator" or "ALL"
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
